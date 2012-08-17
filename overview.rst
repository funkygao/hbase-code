==============
how HBase runs
==============

:Author: Gao Peng <funky.gao@gmail.com>
:Description: internals of hbase
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


ZooKeeper
=========

znode
-----

PERSISTENT
^^^^^^^^^^

- /hbase

- /hbase/unassigned

- /hbase/rs

- /hbase/table

- /hbase/root-region-server

EPHEMERAL
^^^^^^^^^


- /hbase/master

- /hbase/rs/${rs server info}

arch
----

实现了基于分布式的观察者模式，ZooKeeperWatcher是subject，ZooKeeperListener是observer

每个master/rs/client process都会创建一个ZooKeeperWatcher实例

::


           [subject]--------------------------------------------------------------------
        ZooKeeperWatcher ---                                                            |
                |           |---- registerListener(ZooKeeperListener)                   |
                |           |                                                           |
                |           |   1            connect                                    |
                |           |◇--- ZooKeeper ---------> zk quorum => zk cluster          |
         notify |           |        |                                                  |
                |           |        | watch(notify)                                    |
                |           |        V                                                  |
                |            ---- process(WatchedEvent)                                 |
                |                    |                                                  |
                |-------<------------                                                   |
                |                                                                       |
        -------------------------------------------------------------------             |
                |                                                                       |
                V                                                                       |
           [observer]                                                                   |
        ZooKeeperListener -----                                                         |
                ^              |- nodeCreated                                           |
                |              |- nodeDeleted                                           |
         extend |              |- nodeDataChanged                                       |
                |               - nodeChildrenChanged                                   |
                |                                                                       |
                |-----------------------------------------------------------            |
                |                   |                   |                   |           |
        ZooKeeperNodeTracker ActiveMasterManager RegionServerTracker AssignmentManager  |
                ^                   |                   |                   |           |
         extend |                    ---------------------------------------            |
                |                                                   |                   |
                |                                                   |                   |
                |--- MasterAddressTracker                           |                   |
                |                                                   |                   |   
                |--- RootRegionTracker --                           ◇                   |
                |                        |--◇ CatalogTracker --◇ HMaster ◇--------------
                |--- MetaNodeTracker ----                           ◇
                |                                                   |
                |--- ReplicationStatusTracker                       |
                |                                                   |
                 --- ClusterStatusTracker --------------------------




instances:

============================ ======= ====== ================
Class                        master  rs     HConnection
============================ ======= ====== ================
ZooKeeperWatcher             ■       ■      ■
ActiveMasterManager          ■       □      □
RegionServerTracker          ■       □      ■
AssignmentManager            ■       □      □
CatalogTracker               ■       ■      □
ClusterStatusTracker         ■       ■      □
MasterAddressTracker         □       ■      ■
============================ ======= ====== ================



Servers
=======

::


                    - abort                 - isStopped()
                   |                       |- stop(String why)
        Abortable -             Stoppable -
            |                       |
             -----------------------
                   ^
            extend |                                    HBaseRPCProtocolVersion
                   |                                         ^
                  --------------------------------------     | extend
                 |                                      |    |
                Server -                        HRegionInterface -
                        |- getConfiguration                       |- getRegionInfo(regionName)
                        |- getZooKeeper                           |- get
                        |- getCatalogTracker                      |- put
                         - getServerName                          |- scan
                                                                  |- checkAndPut
                MasterServices                                    |- increment
                        |                                          - ...
                        |- getAssignmentManager
                        |- getServerManager
                        |- getMasterFileSystem
                        |- getExecutorService
                         - checkTableModifiable
              
                HMasterInterface
                        |
                        |- isMasterRunning
                        |- createTable
                        |- addColumn
                        |- enableTable
                        |- shutdown
                        |- stopMaster
                        |- getClusterStatus
                        |
                        |- move(regionName, destServerName)
                        |- assign(regionName)
                         - balance
              
                HMasterRegionInterface
                        | 
                        |- regionServerStartup
                         - regionServerReport
              

                RegionServerServices
                        |
                        |- HLog getWAL
                        |- CompactionRequestor getCompactionRequester
                        |- FlushRequester getFlushRequester
                        |- HBaseRpcMetrics getRpcMetrics
                         - HServerInfo getServerInfo


          HMaster       -> (HMasterInterface, HMasterRegionInterface, MasterServices,       Server)
          HRegionServer -> (HRegionInterface,                         RegionServerServices, Server) 


HMaster
=======

::

    HMasterCommandLine
      |                
      |- run               local
          |                -----
          |- startMaster -|     |
                          |     |- new MiniZooKeeperCluster.startup
                          |     |   |
                          |     |   |- zks = new ZooKeeperServer
                          |     |   |- new NIOServerCnxn.Factory(clientPort).startup
                          |     |   |        |
                          |     |   |        |- zks.startdata
                          |     |   |        |    |
                          |     |   |        |    |- new ZKDatabase
                          |     |   |        |    
                          |     |   |        |- zks.startup
                          |     |   |             |
                          |     |   |             |- startSessionTracker
                          |     |   |             |- setupRequestProcessors
                          |     |   |                   |
                          |     |   |                   | PrepRequestProcessor -> SyncRequestProcessor -> FinalRequestProcessor
                          |     |   |                   |
                          |     |   |                   |- new FinalRequestProcessor
                          |     |   |                   |- new SyncRequestProcessor
                          |     |   |                   |- new PrepRequestProcessor
                          |     |   |
                          |     |   |- socket connect clientPort 'stat' to assert zk alive
                          |     |
                          |     |
                          |     |- new LocalHBaseCluster().startup
                          |         |
                          |         |- HMaster.newInstance
                          |         |    |
                          |         |    |- rpcServer = HBaseRPC.getServer
                          |         |    |- rpcServer.startThreads
                          |         |    |     |
                          |         |    |     |- responder.start()
                          |         |    |     |- listener.start()
                          |         |    |     |- handlers = new Handler[handlerCount].startall()
                          |         |    |
                          |         |    |- new ZooKeeperWatcher
                          |         |
                          |         |- HRegionServer.newInstance
                          |         |    |
                          |         |    |- server = HBaseRPC.getServer
                          |         |    |- run
                          |         |        |
                          |         |        |- server.startThreads
                          |         |
                          |         |- start master and rs threads
                          |
                          |
                           ------------ HMaster.constructMaster(HMaster.class, conf)->start();
                           distributed




RPC
===

classes
-------

  - HBaseClient

    ::

                                                 1
                                                -- Socket
                                               |
        HBaseClient ◇---- Connection(Thread) ◇-|
                    1   *                    1 | *
                                                -- Call

  - `HBaseServer`

    The RPC server. HMaster和HRegionServer都会创建该对象，作为成员变量

    HBaseServer server = HBaseRPC.getServer();

    ::


                                                     1
                                                    -- acceptChannel --- bind
                           1                   1   |
        HBaseServer ◇---|--- Listener(Thread) ◇----|-- Reader(Runnable)
                        |                          | *      |
                        |                          |        ^ execute
                        |                          |        |
                        |                           -- readPool(newFixedThreadPool)
                        |                            1
                        |                        
                        |  1                    
                        |--- Responder(Thread)
                        |
                        |  *
                        |--- Handler(Thread)
                        |
                        |--- Connection
                         --- Call
 
header
------

::

    Request: client -> server

    header:
    struct {
        char[4] magic = 'hrpc';
        char version = 3;
        int lenOfUserGroupInformation;
        UserGroupInformation obj;
    }

    body:
    HbaseObjectWritable

    Response: server -> client
    struct {
        int id;
    }


    
序列化
------------

没有通过标准的Serialize接口，而是利用org.apache.hadoop.io.Writable实现，它有2个方法：

#. write(DataOutput out)

    将数据写入流中，相当于系列化

#. readFields(DataInput in)

    从流中读出这数据实例化这个对象，相当于反序列化

HBase里真正传输的是HBaseObjectWritable



通信信道
------------

**单向的**

- HMasterInterface

  client --> master

- HRegionInterface

  client --> rs

- HMasterRegionInterface

  rs --> master


.. image:: http://s15.sinaimg.cn/orignal/630c58cbtc5e5547dd23e&690
    :alt: hbase channels


HConnection
-----------

连接到zk和rs的抽象

::

    HConnection conn = HConnectionManager.getConnection();

    HMasterInterface master = conn.getMaster();
    HRegionInterface rs = conn.getHRegionConnection();
    ZooKeeperWatcher zk = conn.getZooKeeperWatcher();
    HRegionLocation rsLocation = conn.locateRegion();


报文
-------

::

    RegionServer1   RegionServerN
        |                |
         ----------------
                |
                V HMsg
                |
             Master



运行机制
-----------

.. image:: http://s12.sinaimg.cn/orignal/630c58cbtc5e5ff85fc2b&690
    :alt: hbase client rpc stub

.. image:: http://s9.sinaimg.cn/orignal/630c58cbt7a309f2464a8&690

原理类似于RMI:

#. client端访问RPC模块得到一个实例化RegionserverInterface接口的的代理类对象

   1,2
#. client通过代理对象访问代理机制实现的Invoker类

   其中的方法invoke()调用一个call()函数建立连接，通过socket建立连接，序列化发送的数据，发送到rs

   3,4
#. HBaseClient会开启一个线程connection，监听rs的执行结果，监听到结果后反序列化，还原对象

   并回复给client调用端

   5,6


HConnectionManager
==================
::

    // A LRU Map of HConnectionKey -> HConnection
    LinkedHashMapMap<HConnectionKey, HConnectionImplementation> HBASE_INSTANCES; 
                             |
                             | new and put
                             |                     create                    connect quorum
                        HConnectionImplementation ◇------- ZooKeeperWatcher ◇--------------> ZooKeeper
                             |         ◇                     |
                             |         | create              | process zk events
                             |         | and                 V
                             |         | start()          Watcher
                             |         |
                             |       ------------------------
                             |      |                        |
                             |   MasterAddressTracker   rootRegionTracker
                             |
                             |
                             |◇-- master = HBaseRPC.getProxy(HMasterInterface.class)
                             |                  |
                             |      java.lang.reflect.Proxy.newProxyInstance(new Invoker(addr))
                             |                                                     ◇  |
                             |                             rpc client              |  | invoke
                             |                           --------------------------   |
                             |                          |                             |
                             |                          |                 ------<-----
                             |                          |                |
                             |                      HBaseClient --------------> call
                             |                          ◇
                             |                          |
                             |                          | HbaseObjectWritable
                             |                          |
                             |                      Connection(Thread)
                             |                       |  |
                             |                       |    --- waitForWork ->- receiveResponse ---
                             |        setupIOstreams |     |                                     |
                             |                       |     |                                     |
                             |                       |      ---------------<---------------------
                             |                       |
                             |                 socket(create,connect)
                             |
                        ConcurrentHashMap<String, HRegionInterface> servers
                        Map<Integer, SoftValueSortedMap<byte [], HRegionLocation>> cachedRegionLocations


 
-ROOT-/.META.
=============

当Region被拆分、合并或者重新分配的时候，都需要来修改这张表的内容。

schema
------

它们的表结构是相同的

.. image:: http://s3.sinaimg.cn/orignal/630c58cbt7a30a3ce2452&690


locating
--------

::

    HConnectionManager.locateRegion()



HLog
=================

它是一个Sequence file，由一个文件头 ＋ 一条条HLog.Entry构成。

.. image:: http://s3.sinaimg.cn/orignal/630c58cbtc5effc295e52&690
    :alt: hadoop sequence file header

- 每个rs只有1个HLog

  而不是每个HRegion一个HLog

- reader/writer

  - SequenceFileLogWriter

  - SequenceFileLogReader


- writer只有append(HLog.Entry entry)操作

  HLog file = file header + [entry, ...]

- HRegionServer.instantiateHLog

- HLog.Entry

  ::

                     1
                     --- WALEdit◇----KeyValue[]
                    |  
    HLog.Entry◇-----|
              1     |
                     --- HLogKey
                     1



Filter
======

::

            Filter
              ^
              |--------------
              |              |
            FilterBase  FilterList
              ^
              |---------------------------------------------------------
              |                 |                   |                   |
            CompareFilter  FirstKeyOnlyFilter  ColumnPrefixFilter  ColumnPaginationFilter
              ^   ◇
              |   |      - CompareOp                       - BinaryComparator
              |    -----|                                 |- RegexStringComparator
              |          - WritableByteArrayComparable <--|- SubstringComparator
              |                                            - BinaryPrefixComparator
              |
              |--------------------------------------
              |           |           |              |
            RowFilter ValueFilter FamilyFilter QualifierFilter


Coprocessor
===========
::


                   - RegionObserver
    Coprocessor --|- MasterObserver
                   - WALObserver


                              - MasterCoprocessorEnvironment
    CoprocessorEnvironment --|- RegionCoprocessorEnvironment
                              - WALCoprocessorEnvironment

                       - MasterCoprocessorHost
    CoprocessorHost --|- RegionCoprocessorHost
                       - WALCoprocessorHost



Thrift
======

Message
-------

Message types:

- CALL

- REPLY

- EXCEPTION

- ONEWAY


Network layer
-------------

::


            Client                      Server
      |  --------------              --------------  |
      |                              Handler         |
      |                              --------------  |
      |  GeneratedClient             Processor       |
      |  --------------              --------------  |
      |  Protocol                    Protocol        |
      V  --------------              --------------  ^
      |  Transport                   Transport       |
      |  --------------              --------------  |
      |  Buffer                      Buffer          |
      |  --------------              --------------  |
      |  socket                      socket          |
      |  --------------              --------------  |
      |  NIC                         NIC             |
         --------------              --------------
            |                           |
             ---------------------------
                        network



        Transport --------◇ Protocol -----------◇ Client(e.g HbaseClient)
        ---------           --------              ------
         |                   |
         |- close            |- writeBool
         |- read             |- writeByte
         |- write            |- writeI16
          - flush            |- writeI32
                             |- writeI64
                             |- writeDouble
                             |- writeString
                             |- readBool
                             |- readByte
                             |- readI16
                             |- readI32
                             |
                             |- writeStructBegin
                             |- writeStructEnd
                             |- readStructBegin
                             |- readStructEnd
                             |-
                              - ...


Server
------

- TNonblockingServer

- THsHaServer

  HsHa = half sync half async

- TThreadPoolServer



::

    
            TServer
                |
              ------------------------------
             |                              |
            AbstractNonblockingServer   TThreadPoolServer
                |
              ----------------------
             |                      |
            THsHaServer     TNonblockingServer      



                         implements
            HBaseHandler ------------> Hbase.Iface
                |
                |
                |
                ◇
            Hbase.Processor


Lease
=====

::



                - getDelay()
               |
            Delayed                              use cases
               ^                                 ---------
               |     leaseExpired                    |
            Lease ◇--------------- LeaseListener     |
               |                        ^            |
               |                        |            |
               |                ----------------------------
               |               |                            |
               |            RowLockListener         ScannerListener
               |               |                            |
               |                ----------------------------
               |                                |
               |                                ◇
               |                          HRegionServer    
               ◇
            Leasese -----> Thread
               |
               |- createLease()
               |- addLease()
               |- renewLease()
               |- cancelLease()
                - removeLease()



HBase shell
===========

DDL
---

- alter

- create

- describe

- disable

- drop

- enable

- exists

- list

DML
---

- count

- delete

- deleteall

- get

- get_counter

- incr

- put

- scan

- truncate


config
======

- hbase.zookeeper.property.maxClientCnxns

  Defaults 5000


debug
=====

shell
-----

- debug

- bin/hbase shell -d

IDE 
---

How to make hbase run step by step?

- hbase.cluster.distributed

- LocalHBaseCluster

- HMaster

  - program arguments: start

  - set breakpoint at HMasterCommandLine.startMaster


- HRegionServer

