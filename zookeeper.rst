==================
how zookeeper runs
==================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: internals of zookeeper
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


startup
=======

org.apache.zookeeper.server.quorum.QuorumPeerMain.main() with args=config filename

port
----

- client port

  2181

- election port

  2182


DatadirCleanupManager
---------------------

PurgeTask run at purgeInterval with Timer mechanism

search snapshot prefixed files in snapDir


protocol
========

ascii protocol 

FileTxnLog  FileSnap
  |             |
   -------------
   FileTxnSnapLog(helper class)

ZKDatabase  
DataTree DataNode

ServerCnxnFactory <- NIOServerCnxnFactory
ServerCnxn <- NIOServerCnxn

QuorumPeer

ZooKeeperServerMain  standalone mode   ZooKeeperServer


Data model
==========

DataTree (内存树)
FileTxnSnapLog (disk持久化)
committedLog (FileTxnSnapLog的一份内存数据cache，默认存储500条变更记录)

::

        nodes get parent by last slash

      |
      |- loadDataBase()
      |
      |           - LinkedList<Proposal> committedLog
      |          |
      |          |                            - FileTxnLog                    - DataNode parent
      |          |- FileTxnSnapLog snapLog ◇-|                  - transient -|
      |          |                            - FileSnap       |              - Set<String> children
      |          |                                             |              
      |          |                 {path: node}                | 
      |          |              ------------------- DataNode ◇-|
    ZKDatabase ◇--- DataTree ◇-|                               |              - byte data[]
                      |        |                                - persisted -|- Long acl
                      |        |                                              - StatPersisted stat
                      |        |- DataNode root             (/)                           
                      |        |             \                                
                      |        |-- DataNode procDataNode    (/zookeeper is proc filesystem of zk)
                      |        |                \
                      |        |---- DataNode quotaDataNode (/zookeeper/quota)
                      |        |
                      |        |    {sessionId: }
                      |        |- ConcurrentHashMap<Long, HashSet<String>> ephemerals
                      |        
                      |                                           node
                      |               childWatches.triggerWatch   ------- NodeCreated
                      |- createNode() ---------------------------|
                      |                                           ------- NodeChildrenChanged
                      |                                           parent
                      |
                      |                                           node
                      |               childWatches.triggerWatch   ------- NodeDeleted
                      |- deleteNode() ---------------------------|
                      |                                           ------- NodeChildrenChanged
                      |                                           parent
                      |                                           
                      |               dataWatches.triggerWatch
                       - setData()    --------------------------- NodeDataChanged
                                                            node
    

