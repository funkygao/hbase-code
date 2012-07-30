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
