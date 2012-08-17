=============
HRegionServer
=============

:Author: Funky Gao <funky.gao@gmail.com>

.. contents:: TOC
.. section-numbering::


class declaration
=================
public class HRegionServer
    implements HRegionInterface, HBaseRPCErrorHandler, Runnable, RegionServerServices, Server


threads
=======

- MemStoreFlusher

- CompactSplitThread

- MajorCompactionChecker

- Leases


Related classes
===============

- MemStore

- MemStoreFlusher

- Store 

- StoreFile

- HRegion

- ReadWriteConsistencyControl

  实现了MVCC，基于行的数据一致性

  Readers can read without having to wait for writers. Writers do need to wait for other writers to complete before they can continue.


run
===

::

    initializeZooKeeper
      |
      |- zooKeeper = new ZooKeeperWatcher
      |- masterAddressManager = new MasterAddressTracker.start
      |- clusterStatusTracker = new ClusterStatusTracker.start
      |- catalogTracker = new CatalogTracker.start
      |- 
      |- 

