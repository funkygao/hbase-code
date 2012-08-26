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



deployment
==========

::


                                HAProxy
                                   |
                     ---------------------------
                    |                           |
                web server1                web serverN...
             -------------------         -------------------
            |   apache mod_php  |       | apache mod_php    |
            |    |              |       |                   |
            |    | socket       |       |                   |
            |    | transport    |  ...  |                   |
            |    | protocol     |       |                   |
            |    | HbaseClient  |       |                   |
            |    |              |       |                   |
             -------------------         -------------------
                 |                          |
                 |  in-house load balancer  |
                  --------------------------
                              |
               ---------------------------------
              |       ThriftServer cluster      |
              |       usually reside on some rs |
               ---------------------------------
                              |              
                              |                     (hadoop + hbase [thrift]) cluster
           --------------------------------------------------------------------------
          |                                                                          |
          |  zk cluster     master  standbyMaster  rs+dataNode+[thrift]  nameNode    |
          |  ----------     ------  -------------  --------------------  --------    |
          |                   |           |          |      |       |       |        |
          |                    ----------------------       |        -------         |
          |                            HBase           ThriftServer  hadoop          |
           --------------------------------------------------------------------------


