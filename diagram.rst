=========================
hbase重要部件架构图
=========================

:Author: Gao Peng <funky.gao@gmail.com>
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


RPC
===

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
