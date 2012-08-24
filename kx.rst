===================
kx how to use HBase
===================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: how can our company use HBase for dev and ops
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


use case
========

动态
---------


短消息
---------



arch
====

::


                                HAProxy
                                   |
                                   | dispatch
                                   |
                     ---------------------------
                    |                           |
                web server1                web serverN...
             -------------------         -------------------
            |   apache mod_php  |       | apache mod_php    |
            |    |              |       |   |               |
            |    | socket       |       |   |               |
            |    V transport    |       |   V               |
            |    | protocol     |       |   |               |
            |    | HbaseClient  |       |   |               |
            |    |              |       |   |               |
            |   ThriftServer    |       | ThriftServer      |
             -------------------         -------------------
                 |                          |
                 |                          |       (hadoop + hbase) cluster
           -----------------------------------------------------------------
          |                                                                 |
          |  zk cluster     master  standbyMaster  rs+dataNode  nameNode    |
          |  ----------     ------  -------------  -----------  --------    |
          |                   |           |          |     |       |        |
          |                    ----------------------       -------         |
          |                            HBase                hadoop          |
           -----------------------------------------------------------------


