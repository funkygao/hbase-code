==================
how zookeeper runs
==================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: internals of zookeeper
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


NOTES
=====

::

    public enum ServerState {
        LOOKING, FOLLOWING, LEADING, OBSERVING;
    }

    public enum LearnerType {
        PARTICIPANT, OBSERVER;
    }


Replica consistency
===================

Zab(zookeeper atomic broadcast) protocol  - a high performance broadcast protocol

packets 
-------
a sequence of bytes sent through a FIFO channel

proposals
---------
a unit of agreement. 

Proposals are agreed upon by exchanging packets with a quorum of ZooKeeper servers. 

Most proposals contain messages, however the NEW_LEADER proposal is an example of a proposal that does not correspond to a message.

messages
--------
a sequence of bytes to be atomically broadcast to all ZooKeeper servers. 

A message put into a proposal and agreed upon before it is delivered.


API
===

- create

- getData / setData

- getChildren

- exists

- delete


Intro
=====

Guarantees
---------------------

- Sequential Consistency 
  
  client的updates请求都会根据它发出的顺序被顺序的处理

- Atomicity
  
  一个update操作要么成功要么失败，没有其他可能的结果

  read/write is atmoic at a single znode level

- Single System Image
  
  client不论连接到哪个server，展示给它都是同一个视图

- Reliability
  
  一旦一个update被应用就被持久化了，除非另一个update请求更新了当前值

- Timeliness
  
  对于每个client它的系统视图都是最新的

Roles
------

Leader
^^^^^^

不接受client的请求，负责进行投票的发起和决议，最终更新状态

Learner
^^^^^^^

和leader进行状态同步的server统称

- Follower

  用于接收客户请求并返回客户结果。参与Leader发起的投票

- Observer

  可以接收客户端连接，将写请求转发给leader节点。但是Observer不参加投票过程，只是同步leader的状态

Cluster
-------

每个server叫做QuorumPeer，每个server通过配置文件知道所有其他server的存在

quorum peers refer to the servers that make up an ensemble
Servers refer to machines that make up the ZooKeeper service
client refers to any host or process which uses a ZooKeeper service.

Data model
==========


Definitions
===========

cnxn
----
connection

zxid
-----

ZooKeeper Transaction Id，global ordered sequence id

每次write请求对应一个唯一的zxid，如果zxid(a) < zxid(b)，则可以保证a一定发生在b之前

zxid为一64位数字，高32位为leader信息又称为epoch，每次leader转换时递增；低32位为消息编号，Leader转换时应该从0重新开始编号。

The epoch number represents a change in leadership. Each time a new leader comes into power it will have its own epoch number. 

ZxidUtils

通过zxid，Follower能很容易发现请求是否来自老Leader，从而拒绝老Leader的请求

czxid
^^^^^
The zxid of the change that caused this znode to be created.

mzxid
^^^^^
The zxid of the change that last modified this znode.

pzxid
^^^^^
The zxid of the last proposal commited.

time
----

ctime
^^^^^
The time in milliseconds from epoch when this znode was created.

mtime
^^^^^
last modified

version
--------
The number of changes to the data of this znode

cversion
^^^^^^^^
The number of changes to the children of this znode

aversion
^^^^^^^^
The number of changes to the ACL of this znode.

ephemeralOwner
--------------
The session id of the owner of this znode if the znode is an ephemeral node. 
If it is not an ephemeral node, it will be zero.



startup
=======

State
-----

=============================== =========================
class                           member
=============================== =========================
QuorumPeer                      long myid
QuorumPeer                      QuorumVerifier quorumConfig             strategy pattern
QuorumPeer                      QuorumCnxManager qcm
QuorumPeer                      ZKDatabase zkDb
QuorumPeer                      LearnerType learnerType
QuorumPeer                      Map<Long, QuorumServer> quorumPeers     cluster里的所有服务器，包括自己
QuorumPeer                      volatile Vote currentVote               This is who I think the leader currently is
QuorumPeer                      volatile boolean running
QuorumPeer                      int tickTime
QuorumPeer                      ServerState state = ServerState.LOOKING
QuorumPeer                      InetSocketAddress myQuorumAddr
QuorumPeer                      int electionType
QuorumPeer                      Election electionAlg
QuorumPeer                      ServerCnxnFactory cnxnFactory
QuorumPeer                      FileTxnSnapLog logFactory
QuorumPeer                      QuorumStats quorumStats

=============================== =========================


::

            QuorumPeerMain.main
                  |
            QuorumPeerConfig.parse(configFile)
                  |
                 -----------------------
                |                       | daemon
                |                       | 
            runFromConfig       DatadirCleanupManager.start
                  |
            create ServerCnxnFactory (default NIOServerCnxnFactory)
                  |
                  | serverCnxnFactory.
                  |                           -  bind 2181
            configure(2181, maxClientCnxns) -|
                  |                           -  register OP_ACCEPT
                  |                           
            new QuorumPeer
                  |                           
            loadDataTree
                  |                           
            cnxnFatory.start
                  |                           
            startLeaderElection
                  |                           
            start self thread


port
----

- client port

- server port

  - election port

  - quorum port


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

znode data size <= 1M

ephemeral znode are not allowed have children

DataTree (内存树)
FileTxnSnapLog (disk持久化)
committedLog (FileTxnSnapLog的一份内存数据cache，默认存储500条变更记录)

::

        

      |
      |- loadDataBase()
      |
      |           - LinkedList<Proposal> committedLog
      |          |
      |          |                            - FileTxnLog (binlog alike)                   
      |          |- FileTxnSnapLog snapLog ◇-|                  
      |          |                            - FileSnap   (DataTree's mirror)    
      |          |                                            
      |          |                                                            - DataNode parent
      |          |                                              - transient -|              
      |          |                 {path: node}                |              - Set<String> children
      |          |              ------------------- DataNode ◇-|
    ZKDatabase ◇--- DataTree ◇-|                               |              - byte data[]
      |               |        |                                - persisted -|- Long acl
      |               |        |                                              - StatPersisted stat
      |               |        |- DataNode root             (/)                           
      ◇               |        |             \                                
    QuorumPeer        |        |-- DataNode procDataNode    (/zookeeper is proc filesystem of zk)
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


Consistency
============


Write path
----------

::


        FileTxnLog.append()

Client
======

new ZooKeeper(ensemble) 会通过 Collections.shuffle()随机找个zk连接，当这个有问题时，会next
