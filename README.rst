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

conf/log4j.properties

How to start
============

Scripts
-------

bin/hbase-config.sh
###################

Setup the following environ variables for other scripts:

- JAVA_HOME

- HBASE_HOME

- HBASE_CONF_DIR

- HBASE_REGIONSERVERS  

- HBASE_BACKUP_MASTERS

- source ${HBASE_CONF_DIR}/hbase-env.sh

bin/hbase
#########

Invoker for other scripts!

Run a dedicated class. e,g:

bin/hbase org.apache.hadoop.hbase.zookeeper.ZooKeeperMainServerArg

Setup classpath and map command to class name and invoke class method:

- shell

  org.jruby.Main ${HBASE_HOME}/bin/hirb.rb

- master

  org.apache.hadoop.hbase.master.HMaster

- regionserver

  org.apache.hadoop.hbase.regionserver.HRegionServer

- thrift

  org.apache.hadoop.hbase.thrift.ThriftServer

- rest

  org.apache.hadoop.hbase.rest.Main

- avro

  org.apache.hadoop.hbase.avro.AvroServer

- migrate

  org.apache.hadoop.hbase.util.Migrate

- hbck

  org.apache.hadoop.hbase.util.HBaseFsck

- zookeeper

  org.apache.hadoop.hbase.zookeeper.HQuorumPeer

- zkcli

  org.apache.zookeeper.ZooKeeperMain


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
       |- mapreduce(通过mapreduce实现运维工具)
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

It will read hbase-default.xml then hbase-site.xml using the current Java classpath.


Logs
====

- master log

- region server log

- zookeeper log
