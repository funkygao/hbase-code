==============================
hbase source code explaination
==============================

:Author: Funky Gao <funky.gao@gmail.com>
:Description: Let's study hbase from it's source code from scratch!

.. contents:: TOC
.. section-numbering::


Code Metrics
============

Test code excluded:

- 452 java files

- 146,616 java LOC


Tips
====

Enable log debug level and check out the log file and find the main routines.

How to start
============

Scripts
-------
bin/hbase

bin/start-hbase.sh

bin/stop-hbase.sh

bin/hbase-daemon.sh

bin/zookeepers.sh

Main Packages
-------------
::

    org.apache.hadoop.hbase
       |
       |- client
       |   |
       |   |- HConnection
       |   |- Get
       |   |- Put
       |   |- Delete
       |   |- Scan
       |   |- Increment
       |   |- Row
       |   |- RowLock
       |   |- HTable
       |   └─ HConnection
       |
       |- filter
       |
       |- io
       |   |
       |   |- hfile
       |   |   |
       |   |   |- HFile
       |   |   └─ BlockCache
       |   |
       |   └─ HeapSize
       |
       |- ipc
       |
       |- master
       |   |
       |   |- HMaster
       |   └─ LoadBalancer
       |
       |- regionserver
       |   |
       |   |- HRegion
       |   |- HRegionServer
       |   |- MemStore
       |   |- Store
       |   └─ StoreFile
       |
       |- util
       |
       └─ zookeeper
           |
           |- ClusterStatusTracker
           |- MetaNodeTracker
           └─ RegionServerTracker


Server
------
org.apache.hadoop.hbase.master.HMaster

org.apache.hadoop.hbase.regionserver.HRegionServer

org.apache.hadoop.hbase.regionserver.HRegion


Client
------
org.apache.hadoop.hbase.client.HTable


Config
------
org.apache.hadoop.hbase.HBaseConfiguration


Logs
====

- master log

- region server log

- zookeeper log
