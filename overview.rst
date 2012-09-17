==============
how HBase runs
==============

:Author: Gao Peng <funky.gao@gmail.com>
:Description: internals of hbase
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


TODO
=====

Scan 是通过 RegionScanner 实现的，它会为每个 Store 实例执行 StoreScanner 检索

- KeyValue

- HFile

- hadoop TFile


::


                            ServerCnxnFactory                   ServerCnxn                  
                                ^                                  ^
                                | extends                          | extends
                                |                                  | 
    ClientCnxn         NIOServerCnxnFactory                     NIOServerCnxn               ZooKeeperServer
      |                         |                                  |                                |
      |                         | bind(clientPort)                 |                                |
      |                         |------<>---------                 |                                |
      |  connect                |                                  |                                |
      |------------------------>|                                  |                                |
      |                         | accept                           |                                |
      |                         |---<>--                           |                                |
      |                         |                                  |                                |
      |                         | new instance                     |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | interestOps(OP_READ)           |
      |                         |                                  |---<>----------------           |
      |                         | register cnxn                    |                                |
      |                         |-----<>--------                   |                                |
      |  connect request        |                                  |                                |
      |------------------------>|                                  |                                |
      |                         |                                  |                                |
      |                         | doIO                             |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | checkFourLetterWord            |
      |                         |                                  |------<>------------            |
      |                         |                                  |                                |
      |                         |                                  | readPayload                    |
      |                         |                                  |------<>------------            |
      |                         |                                  |                                |
      |                         |                                  | processConnectRequest          |
      |                         |                                  |------------------------------->|
      |                         |                                  |                                |
      |  request                |                                  |                                |
      |------------------------>|                                  |                                |
      |                         |                                  |                                |
      |                         | doIO                             |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | processPacket                  |
      |                         |                                  |------------------------------->|
      |                         |                                  |                                |



Startup
=======

::

    HMasterCommandLine
      |                
      |                
       - run               local
          |                -----
           - startMaster -|     |
                          |     |- new MiniZooKeeperCluster.startup
                          |     |   |
                          |     |   |- zks = new ZooKeeperServer
                          |     |   |- new NIOServerCnxn.Factory(clientPort).startup
                          |     |   |        |
                          |     |   |        |- zks.startdata
                          |     |   |        |    |
                          |     |   |        |    |- new ZKDatabase
                          |     |   |        |    
                          |     |   |         - zks.startup
                          |     |   |             |
                          |     |   |             |- startSessionTracker
                          |     |   |              - setupRequestProcessors
                          |     |   |                   |
                          |     |   |                   | PrepRequestProcessor -> SyncRequestProcessor -> FinalRequestProcessor
                          |     |   |                   |
                          |     |   |                   |- new FinalRequestProcessor
                          |     |   |                   |- new SyncRequestProcessor
                          |     |   |                    - new PrepRequestProcessor
                          |     |   |
                          |     |    - socket connect clientPort 'stat' to assert zk alive
                          |     |
                          |     |
                          |      - new LocalHBaseCluster().startup
                          |         |
                          |         |- HMaster.newInstance
                          |         |    |
                          |         |    |- rpcServer = HBaseRPC.getServer
                          |         |    |- rpcServer.startThreads
                          |         |    |     |
                          |         |    |     |- responder.start()
                          |         |    |     |- listener.start()
                          |         |    |      - handlers = new Handler[handlerCount].startall()
                          |         |    |
                          |         |     - new ZooKeeperWatcher
                          |         |
                          |         |- HRegionServer.newInstance
                          |         |    |
                          |         |    |- server = HBaseRPC.getServer
                          |         |     - run
                          |         |        |
                          |         |         - server.startThreads
                          |         |
                          |          - start master and rs threads
                          |
                          |
                           ------------ HMaster.constructMaster(HMaster.class, conf)->start();
                           distributed

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


HConnection
-----------

连接到zk和rs的抽象

::

    HConnection conn = HConnectionManager.getConnection();

    HMasterInterface master = conn.getMaster();
    HRegionInterface rs = conn.getHRegionConnection();
    ZooKeeperWatcher zk = conn.getZooKeeperWatcher();
    HRegionLocation rsLocation = conn.locateRegion();


ClientCnxn
==========

::


                                            ClientWatchManager  ClientCnxnSocket
                                                  ^                     ^
                                                  | extends             | extends
                                                  |                     |
    ZooKeeper           ClientCnxn          ZKWatchManager      ClientCnxnSocketNIO 
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |
       |                    |                     |                     |


    sendThread

    eventThread


Packet
------

::

    RequestHeader
    ReplyHeader
    Record request
    Record response
    WatchRegistration 

Servers
=======

Interfaces
----------

::


                    - abort                 - isStopped()
                   |                       |- stop(String why)
        Abortable -             Stoppable -
            |                       |        
            |                       |       
             -----------------------       
                   ^                      
            extend |                     
                   |                                         
                Server -                        
                        |- getConfiguration                       
                        |- getZooKeeper                          
                        |- getCatalogTracker                    
                         - getServerName                       


                             VersionedProtocol
                                    ^
                                    | extend
                                    |
                             HBaseRPCProtocolVersion
                                    ^
                                    | extend
                                    |
                         ------------------------------
                        |                               |
                HMasterInterface                    HRegionInterface
                        |                               |
                        |- isMasterRunning              |- getRegionInfo(byte[] regionName)
                        |- createTable                  |- get(byte[] regionName, Get get)
                        |- addColumn                    |- put(byte[] regionName, final Put put)
                        |- enableTable                  |- scan
                        |- shutdown                     |- checkAndPut
                        |- stopMaster                   |- increment
                        |- getClusterStatus              - ...
                        |
                        |- move(regionName, destServerName)
                        |- assign(regionName)
                         - balance(定时对Region Server的Region数进行balance)
              
                HMasterRegionInterface
                        | 
                        |- regionServerStartup
                         - regionServerReport
              
                MasterServices                                    
                        |                                        
                        |- getAssignmentManager
                        |- getServerManager
                        |- getMasterFileSystem
                        |- getExecutorService
                         - checkTableModifiable
              

                RegionServerServices
                        |
                        |- HLog getWAL
                        |- CompactionRequestor getCompactionRequester
                        |- FlushRequester getFlushRequester
                        |- HBaseRpcMetrics getRpcMetrics
                         - HServerInfo getServerInfo


Signature
---------

============== ================ ====================== ============== ================ ==================== ======
Server         HMasterInterface HMasterRegionInterface MasterServices HRegionInterface RegionServerServices Server
============== ================ ====================== ============== ================ ==================== ======
HMaster        ■                ■                      ■              □                □                    ■
HRegionServer  □                □                      □              ■                ■                    ■
============== ================ ====================== ============== ================ ==================== ======


Class Members
=============

Queue
-----

============================================= ===================  =====================================
Queue                                         Owner                desc
============================================= ===================  =====================================
LinkedBlockingQueue<Call> callQueue           HBaseServer          call queue
LinkedBlockingQueue<Call> priorityCallQueue   HBaseServer          priority call queue
DelayQueue<FlushQueueEntry> flushQueue        MemStoreFlusher      获取刷磁盘的请求，超时时间为10s
PriorityCompactionQueue compactionQueue       CompactSplitThread   获取compact的请求，超时时间20s
BlockingQueue<Call> callQueue                 HBaseServer          RPC server获得请求后，由Reader线程放入队列，等待Handler线程处理
PriorityCompactionQueue compactionQueue       CompactSplitThread   获取需要Compact的HRegion
============================================= ===================  =====================================

Container
---------

=============================== ========================================================
Owner                           Members
=============================== ========================================================
HRegionServer                   ConcurrentHashMap<String, HRegion>() onlineRegions
HLog                            ConcurrentSkipListMap<byte [], Long>(Bytes.BYTES_COMPARATOR) lastSeqWritten
HRegion                         ConcurrentSkipListMap<byte [], Store>(Bytes.BYTES_RAWCOMPARATOR) stores
HBaseClient                     Hashtable<ConnectionId, Connection>() connections
HBaseClient.Connection          new Hashtable<Integer, Call>() calls
HBaseRPC.ClientCache            HashMap<SocketFactory, HBaseClient>() clients
HConnectionManager              LinkedHashMapMap<HConnectionKey, HConnectionImplementation> HBASE_INSTANCES; 
=============================== ========================================================

Storage
=======

overview
--------

A Store holds a column family in a Region

每个 Store 实例代表一个HColumnFamily

TODO merge behavior

::


                                                                    KeyValue
                                                                    --------    
                                                                   |        |   
             WALEdit -  entry                               HFile.Reader  HFile.Writer
                      |------ SequenceFile                         |        |   
             HLogKey -             |                                --------    
                                   |     LogSyncer                      |
                                   |         |                          |
                              1    |         |   ------                 |
                             ---- HLog(WAL) --- | roll |                |
                            |     128M           ------                 |     
                            |                   LogRoller       N       ◇     compactionThreshold   ---------
                            |                                  ---- StoreFile -------------------> | compact |
                   1        | N (start,end)   N               |         ^        HFile              ---------
    HRegionServer ◇---------|---- HRegion ◇----- Store ◇------|         |                         CompactSplitThread
                            |        |      cf                |         |
                            |        |                        |         |
                            |        | too many rows          |         |
                            |        V                        |          ---------------
                            |      -------                    |                         |
                            |     | split |                   |                         |
                            |      -------                    |               64M    -------
                            |                                  ---- MemStore -----> | flush |
                            | 1                                 1   SortedMap        -------
                             ---- LruBlockCache                                     MemStoreFlusher




    client          rs          WAL         memstore        HFile
      |             |            |              |             |
      | Put/Delete  |            |              |             |
      |------------>|   write    |              |             |
      |             |----------->|              |             |
      |             |<-----------|              |             |
      |             |            |              |             |
      |             |-------------------------->|             |
      |             |<--------------------------|             |
      |<------------|            |              |   flush     |
      |             |            |              |------------>|
      |             |            |              |             |


HLog
----

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


因为KeyValue仅表示了row key,column family,column qualifier,timestamp,type 和 value;
这样就需要有地方存放 KeyValue 的归属信息,比如 region 和 table 名称。
这些信息会被存储在 HLogKey 中

通过将针对多个 cells 的更新操作包装到一个单个 WALEdit 实例中,将所有的更新看做是一个原子性的操作

Compaction
----------

每次memstore flush，都会产生一个HFile，如果很多，就会compaction，把它们合成更少更大的HFile，当该HFile大到一定程度，则会产生region split

- minior

  把 最少hbase.hstore.compaction.min/最多hbase.hstore.compaction.max 个HFile合并成1个HFile，这些HFile每个大小要在 hbase.hstore.compaction.min.size 和 hbase.hstore.compaction.max.size 范围之间才会合并

- major

  把所有HFile合并成1个HFile


Physical storage
----------------

- HDFS

  - HLog

  - HFile

- mem

  - memstore

    Data in the memstore is sorted in the same manner as data in a HFile

- .tableinfo

  ..tableinfo.0000000001.crc

- .regioninfo

  ..regioninfo.crc


HFile
-----

It's based upon hadoop TFile

该文件是变长的，定长的block只有file into和trailer这2部分

data blocks, meta blocks, file info, data index blocks, meta index blocks, trailer

The data blocks contain the actual key/values as a MapFile.  
For each “block close operation” the first key is added to the index, and the index is written on HFile close.

The HFile format also adds two extra “metadata” block types: Meta and FileInfo.  
These two key/value blocks are written upon file close.

The Meta block is designed to keep a large amount of data with its key as a String, while FileInfo is a simple Map preferred for small information with keys and values that are both byte-array. Regionserver’s StoreFile uses Meta-Blocks to store a Bloom Filter, and FileInfo for Max SequenceId, Major compaction key and Timerange info. This information is useful to avoid reading the file if there’s no chance that the key is present (Bloom Filter), if the file is too old (Max SequenceId) or if the file is too new (Timerange) to contain what we’re looking for.

shell$ bin/hbase org.apache.hadoop.hbase.io.hfile.HFile -v -p -m -f filename

::

    open HFile
    seek to end with offset trailer size


HDFS
----

::

    /hbase
      |
      |-- -ROOT-/
      |-- .META./
      |
      |-- .logs/ ---
      |             |
      |              -- ${rsServer}/ ---
      |                                 |
      |                                  - HLog files
      |-- .oldlogs/
      |
      |-- .corrupt/
      |-- splitlog/
      |
      |-- hbase.id(the uniq id of the cluster for internal usage)
      |-- hbase.version
      |
       -- ${tableName}/ ---
                           |-- .tableinfo (HTableDescriptor)
                           |-- .tmp/
                           |
                            -- ${h(RegionName)}/ ---
                                 Jenkins Hash       |-- .regioninfo (HRegionInfo)
                                                    |-- .tmp/
                                                    |
                                                     -- ${cfName}/ ---
                                                                      |
                                                                       - HFiles with random name without conflict


Configuration
-------------

- hbase.hregion.preclose.flush.size

  default 5M

- hbase.hregion.memstore.flush.size

  default 64M

- hbase.master.logcleaner.ttl

  default 10m

- hbase.master.cleaner.interval

  default 1m

- hbase.hregion.max.filesize

- hbase.hstore.compaction.min

  default 3

- hbase.hstore.compaction.min.size

- hbase.hstore.compaction.max

  default 10

- hbase.hstore.compaction.max.size

  default Long.MAX_VALUE

- hbase.regionserver.logroll.period

  default 1h

- hbase.regionserver.logroll.multiplier

  default 95%

  WAL file is rolled when its size is about 95% of the HDFS block size

- hbase.zookeeper.property.maxClientCnxns

  default 5000

Data lookup
-----------

::

        client ask for data with row(key)
            |
        ZK Quorum
            |
        Get the 1 -ROOt- rs address
            |
        Get the key's .META. rs address
            |
        connect to key's rs server
            |
        open the key's HRegion


put
---

::

        client ask for kv put with row(key)
            |
        ZK Quorum
            |
        Get the 1 -ROOt- rs address
            |
        Get the key's .META. rs address
            |
        connect to key's rs server
            |
            | RPC
            |
        HRegionServer
            |
        locate the row's HRegion
            |
        write to WAL
            |
        write to MemStore ------- MemStoreFlusher --
                                                    |
                     -------------------------------
                    |
           --------------------
          |     |       |      |
        HFile  HFile  HFile  HFile
           --------------------
                   |
                   | CompactSplitThread
                   |
           --------------------
          |     |       |      |
           --------------------
                 HFile


Region splits
-------------

::

        (parent) region split into 2
            |
        mkdir /hbase/${table}/${regionName}/splits/
            |
        close parent region (no longer take requests)A
            |
        prepare 2 daughters in splits/
            |           |
            |           |- mkdir 2 ${daughterDir}
            |            - reference file
            |            
        mv ${daughterDir} ../
            |            
        update .META. table


log splits
----------

The process of grouping the WAL edits by region is called log splitting.

Log splitting is done by HMaster as the cluster starts or by ServerShutdownHandler as a region server shuts down. 


.. image:: http://s14.sinaimg.cn/orignal/630c58cbtc7f90776e6ed&690

ZooKeeper
=========

One or more ZooKeeper servers form what’s called an “ensemble”, which are in constant communication

Overview
--------

::

    
                        HMasterInterface HBaseRPC.getProxy
            --------------------------------------------------------------------
           |                                                                    |
           |    MasterAddressTracker                                            |
           |    RootRegionTracker                                               |
        Client -----------------------> ZooKeeper                               |
           |                              ^   ^                                 |
           | HRegionInterface             |   |                                 |
           | HBaseRPC.waitForProxy        |   |                                 |
           |                              |   |                                 |
           |   ---------------------------     ----                             |
           |  |                                    | ActiveMasterManager        |
           |  |                                    | ActiveMasterManager        |
           |  |                                    | AssignmentManager          |
           |  | CatalogTracker                     | CatalogTracker             |
           |  | ClusterStatusTracker               | ClusterStatusTracker       |
           |  | MasterAddressTracker               |                            |
           |  |                                    |                            |
           V  |            HMsg                    |                            |
        RegionServer -------------------------> Master <------------------------
           |           HMasterRegionInterface      |
           |                                       |
            ---------------------------------------
                            |
                            V
                           HDFS


znode
-----

PERSISTENT
^^^^^^^^^^

- /hbase

  baseZNode, base znode for this cluster

- /hbase/unassigned

  assignmentZNode, znode used for region transitioning and assignment

  see ZKAssign

  该znode是由 AssignmentManager 用来追踪整个cluster的region状态的，它包含了那些未被打开或者处于过渡状态的 regions 对应的 znodes，zodes 的名称就是该 region 的 hash

- /hbase/shutdown

  clusterStateZNode, znode containing the current cluster state

  用来追踪cluster status，当cluster关闭时内容为空

  znode值是Bytes.toBytes(new java.util.Date().toString())，启动时间

- /hbase/rs

  rsZNode

  所有region servers的根结点，会记录它们是何时启动的，用来追踪服务器的失败

- /hbase/table

  tableZNode, znode used for table disabling/enabling

  该目录下的每个child node表示一个disabled table

- /hbase/root-region-server

  rootServerZNode


EPHEMERAL
^^^^^^^^^

- /hbase/master

  masterAddressZNode, znode of currently active master

- /hbase/rs/${rs server info}

  znode containing ephemeral nodes of the regionservers

  每个rs下的node name为：${rsHostName},${rsPort},${rsStartcode}, data为：address.toBytes

Pattern
-------

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



RootRegionTracker
-----------------

- HServerAddress getRootRegionLocation()

- HServerAddress waitRootRegionLocation(long timeout)

MetaNodeTracker
---------------

::

    nodeDeleted -> this.catalogTracker.waitForMetaServerConnectionDefault()


MetaEditor
^^^^^^^^^^

Writes region and assignment information to .META.

MetaReader
^^^^^^^^^^


instances
---------

============================ ======= ====== ============ ===============================================
Class                        master  rs     HConnection  desc
============================ ======= ====== ============ ===============================================
ZooKeeperWatcher             ■       ■      ■            Acts as the single ZooKeeper Watcher
ActiveMasterManager          ■       □      □            master选举机制的实现 blockUntilBecomingActiveMaster()
RegionServerTracker          ■       □      □            Tracks the online region servers expiration. serverManager.expireServer, getOnlineServers()
AssignmentManager            ■       □      □            记下 region 从 offline 状态开始的整个生命周期
CatalogTracker               ■       ■      □            Tracks the availability of the catalog tables -ROOT- and .META.
ClusterStatusTracker         ■       ■      □            标识当前cluster是启动还是关闭状态。master设置状态setClusterUp()/setClusterDown(), rs读状态isClusterUp()
MasterAddressTracker         □       ■      ■            追踪当前的master，这样当master切换时客户端和rs都自动切换 getMasterAddress()
============================ ======= ====== ============ ===============================================

Paxos
-----

::


            proposer                        acceptor                 learner
                |                               |                       | 
                |prepare req with N             |                       | 
                |------------------             |                       | 
                |                               |                       | 
                |send to majority of accptors   |                       | 
                |------------------------------>|                       | 
                |                               |                       | 
                |     YES if N > max(self.nList)|                       | 
                |<------------------------------|                       | 
                |                               |                       | 
                |accept req                     |                       | 
                |------------------------------>|                       | 
                |                               |                       | 
                |                               |accept                 | 
                |                               |------                 | 
                |                               |                       | 
                |                               |inform all learners    | 
                |                               |---------------------->| 
                |                               |                       | 

 
-ROOT-/.META.
=============

-ROOT-表用于保存.META.表的所有 regions 的信息。

.META.表存储row range位置信息

三层的类 B+Tree 的定位模式

::

        zk quorum 
            |
            | /hbase/root-region-server
            |
        1. found the rs of -ROOT-
            |
        connect to the root rs
            |
        2. found the .META. from the -ROOT-
            |
        3. find the target rs from .META.
            |
        connect to the target rs


一个新的客户端为找到某个特定的行 key 首先需要联系 Zookeeper Qurom。
它会从ZooKeeper检索持有 -ROOT- region的服务器名。通过这个信息,它询问拥有 -ROOT- region的region server,得到持有对应行key的.META. 表 region 的服务器名。

这两个操作的结果都会被缓存下来,因此只需要查找一次。 

最后,它就可以查询.META.服务器然后检索到包含给定行 key 的 region 所在的服务器。

当Region被拆分、合并或者重新分配的时候，都需要来修改这张表的内容。

schema
------

它们的表结构是相同的

.. image:: http://s3.sinaimg.cn/orignal/630c58cbt7a30a3ce2452&690

HTableDescriptor.ROOT_TABLEDESC
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

        new HTableDescriptor(
            "-ROOT-", // table name
            new HColumnDescriptor[] { 
                new HColumnDescriptor (
                    "info",  // family name
                    10,  // max versions
                    Compression.Algorithm.NONE.getName(), // compression
                    true, // inMemory
                    true,  // blockCacheEnabled
                    8 * 1024, // blocksize
                    HConstants.FOREVER, // ttl
                    StoreFile.BloomType.NONE.toString(), // bloomFilter
                    HConstants.REPLICATION_SCOPE_LOCAL //scope
                ) 
            }
        );


HTableDescriptor.META_TABLEDESC
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

::

        new HTableDescriptor(
            ".META.", // table name
            new HColumnDescriptor[] { 
                new HColumnDescriptor (
                    "info",  // family name
                    10,  // max versions
                    Compression.Algorithm.NONE.getName(), // compression
                    true, // inMemory
                    true,  // blockCacheEnabled
                    8 * 1024, // blocksize
                    HConstants.FOREVER, // ttl
                    StoreFile.BloomType.NONE.toString(), // bloomFilter
                    HConstants.REPLICATION_SCOPE_LOCAL //scope
                ) 
            }
        );


Locating
--------

::

    HConnectionManager.locateRegion(byte [] regionName)


RPC
===

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


HBaseRPC
--------

::

        HBaseRPC 
            |                                             
            |-- getServer -> HBaseRPC.Server(HBaseServer) ◇--------------------- 
            |                   |                                               |
            |                   | startThreads()                                |
            |                   |                                               |
            |                   |   1                                           |   
            |                   |   -- responder.start()                        |
            |                   |  |                                            |
            |                   |  |10                                          |   
            |                    --|-- handlers.start all() -------             |   
            |                      |                               |            |   
            |                      |                               | consumer   |
            |                      |                               V            |
            |                      |                            callQueue ------
            |                      |                               ^
            |                      |                               | producer
            |                      |1                    10        |
            |                       -- listener.start() ◇----- reader.start()
            |
            |
            |                                     _ HMaster
             -- getProxy  -> VersionedProtocol <-|- HRegionServer
                   |                             |
                   |                             |- HRegionInterface
            Proxy.newProxyInstance               |- HMasterInterface
                   |                              - HMasterRegionInterface
                   | on invoke
                   |
            HBaseClient.call(new new Invocation(method, args), addr)

    
        

HBaseClient
-----------

对外只提供call这个方法

::


                            Connections to a given host/port are reused
                                      /
                                    /
                      pool        /                   1
        HBaseClient ◇------ Connection(Thread) ◇------- Socket
            |       1     *                    1   
            |                                    
            |
            |- call(Writable[] params, InetSocketAddress[] addresses) throws IOException
            |
            |- call(Writable param, InetSocketAddress addr,
            |           UserGroupInformation ticket, int rpcTimeout) throws IOException
             - call(Writable param, InetSocketAddress address) throws IOException
            


    Internal class

    ::

                        - Connection(Thread) --- PingInputStream(FilterInputStream)
                       |
                       |- ConnectionId
                       |
        HBaseClient ---|- Call
                       |
                       |
                       |
                       |- ParalleCall(Call)
                       |
                        - ParalleResults


    monitor
    
    ::


                              --- callComplete
                             |       |
                   - Call ---|       | notify
                  |          |       V
                  |           -- HBaseClient.call() 
        resource -|              
                  |
                  |                 --- addCall
                  |                |       |
                   - Connection ---|       | notify
                                   |       V
                                    -- waitForWork
                                       

HBaseServer
-----------

The RPC server. HMaster和HRegionServer都会创建该对象，作为成员变量

Reader线程接收到RPC请求后，丢到Queue里；10个Handler线程处理Queue(默认1000)

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
                        |  *                 1       *
                         --- Handler(Thread) ◇-------- Call
                                             callQueue

 
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


Mechanism
=========

Lease
-----

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


Event handler
-------------

用于局部内的调用，不属于整体的架构范畴

intro
^^^^^

Hbase通过event的方式(command pattern)，利用ExecutorService执行各种命令，例如:
::

    new ExecutorService.submit(new CloseRootHandler)


ExecutorService
^^^^^^^^^^^^^^^
利用java.util.concurrent.ThreadPoolExecutor


classes
^^^^^^^

::

        Runnable
          ^                1
          |                --- EventType
          |               |1
        EventHandler ◇----|--- EventHandlerListener
          ^               |
          |               |--- Server
          |               |
          |                --- seqid
          |                
          |                
          |          master   - CloseRegionHandler
          |         ---------|- DeleteTableHanler
           --------|         |- DisableTableHandler
                   |         |- EnableTableHandler
                   |         |- MetaServerShutdownHandler
                   |         |- ModifyTableHandler
                   |         |- OpenRegionHandler
                   |          - ....
                   |          
                   | rs       - CloseMetaHandler
                    ---------|- CloseRegionHandler
                             |- CloseRootHandler
                             |- OpenMetaHandler
                             |- OpenRegionHandler
                             |- OpenRootHandler
                              - ...



Advanced
========

Filter
------

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


Replication
-----------

HBase replication是在不同的HBase部署之间拷贝数据的一种方式。

它可以作为一种灾难恢复解决方案, 也可以用于提供 HBase 层的更高的可用性

采用与mysql replication类似的 master push架构

Coprocessor
-----------

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

Processor
^^^^^^^^^

::


    public interface TProcessor  {
        boolean process(
            org.apache.thrift.protocol.TProtocol tProtocol, 
            org.apache.thrift.protocol.TProtocol tProtocol1) throws org.apache.thrift.TException;
    }


Handler
^^^^^^^

::

    public interface Hbase.Iface {
        public void enableTable(byte[] tableName) throws IOError, TException;
        public void disableTable(byte[] tableName) throws IOError, TException;
        public List<TCell> get(byte[] tableName, byte[] row, byte[] column) throws IOError, TException;
        ...
    }


Server
------

- TNonblockingServer

- THsHaServer

  HsHa = half sync half async

  ::

        workerThread1  workerThread2  workerThreadN
        -------------  -------------  -------------
            |               |               |
             -------------------------------
                          |
                          | deque
                          V
                   -------------------------
                  | [priority]Request Queue |
                   -------------------------
                          ^
                          | enque
                          |
                    Handlers, Acceptor
                          |
                        Reactor


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



startup
-------
::

    ./bin/hbase-daemon.sh start thrift


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


Debug
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


Load test
---------

- self contained

  hbase org.apache.hadoop.hbase.PerformanceEvaluation

- YCSB

