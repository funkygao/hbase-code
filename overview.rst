=========================
hbase重要部件架构图
=========================

:Author: Gao Peng <funky.gao@gmail.com>
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::

基础类
================

- HLog

- HRegion

- HMsg

- HServerInfo

- CatalogTracker

  - RootRegionTracker

  - MetaNodeTracker

- RPC

  - HBaseClient

    ::

                                                 1
                                                -- Socket
                                               |
        HBaseClient ◇---- Connection(Thread) ◇-|
                    1   *                    1 | *
                                                -- Call

  - `HBaseServer`

    HBaseServer server = HBaseRPC.getServer();

    ::


                         - Call
        HBaseServer ----|- Listener(Thread)
                        |- Reader(Runnable)
                        |- Responder(Thread)
                        |- Connection
                        |- Handler(Thread)

        


关键类说明
=================

HBaseConfiguration
------------------

::

    addResource('hbase-default.xml');
    addResource('hbase-site.xml);


HLog
----

它是一个Sequence file，由一个文件头 ＋ 一条条HLog.Entry构成。

.. image:: http://s3.sinaimg.cn/orignal/630c58cbtc5effc295e52&690
    :alt: hadoop sequence file header

- 每个rs只有1个HLog

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


RegionServerTracker
-------------------

rs down了，zk会调用RegionServerTracker.nodeDeleted()


CompactSplitThread
------------------

MajorCompactionChecker

RPC
===

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
                | HMsg
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



HConnection
-----------

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
                | HMsg
                |
             Master


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

debug
-----

- debug

- bin/hbase shell -d

- LocalHBaseCluster

  set breakpoints on this file
