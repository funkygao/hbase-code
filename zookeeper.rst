==================
how zookeeper runs
==================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: internals of zookeeper
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::

Overview
========

Metrics
-------

- Java Files

  429

- LOC

  78,324


Peer
----

global view
^^^^^^^^^^^

- getView

- getVotingView

- getObservingView

Queues
^^^^^^

::

        C=consumer  P=producer

        Peer1                                           Peer2
        -----                                           -----------------------------------------------------------------------------

        QuorumCnxManager                  Socket        QuorumCnxManager                        FastLeaderElection      lookForLeader
        ----------------                  ------        ----------------                        ------------------      -------------   
             |                              |               |                                       |                       |  
             |                              |               |                                       |                       | C
             |        C              write  |   read        |          P                    C       |            P          V
        queueSendMap--->SenderWorker------->|---------->RecvWorker--------->recvQueue---------->WorkerReceiver--------->recvqueue<Notification>
             |                              |               |                                       |   |
             |                              |               |                                       |   |        P
             |                              |               |                                       |    ------------>-----
             |        P              read   |   write       |          C                    P       |            C         |
        recvQueue<------RecvWorker<---------|<----------SenderWorker<-------queueSendMap<-------WorkerSender<-----------sendqueue<ToSend>
                                            |
                                            |



       QuorumCnxManager                                                 FastLeaderElection
       ----------------                                                 ------------------

                     P                                                                  C
       SendWorker----------->senderWorkerMap<sid, SendWorker>           WorkerSender------------>sendqueue<ToSend>
           |                                                                |                       ^
           |         CP                                                 P   |                       |
            ---------------->queueSendMap<sid, ArrayBlockingQueue>  <-------            P           |
                                                                              ----------------------
                                                                             |
                     P                                        C              |          P
       RecvWorker----------->recvQueue<Message> <-----------------------WorkerReceiver---------->recvqueue<Notification>
                                                                                                    ^
                                                                                                    | C
                                                                                                    |
                                                                                                lookForLeader()



Threads
^^^^^^^

=============================================== =============================================================== =============== =====
Class                                           Description                                                     CountUnit       Ready
=============================================== =============================================================== =============== =====
QuorumPeer                                      LOOKING/OBSERVING/FOLLOWING/LEADING各状态转换的housekeeping     per server      启动时
NIOServerCnxnFactory                            bind(`clientPort`)，为每个cnxn创建一个NIOServerCnxn处理请求     per server      启动时
ClientCnxn.EventThread                          接收NIOServerCnxn的response                                     per client
ClientCnxn.SendThread                           向NIOServerCnxn发送request                                      per client
QuorumCnxManager.Listener                       bind(`electionPort`), sleeps on accept()                        per server      启动时
QuorumCnxManager.SendWorker                                                                                     per sid         [被]连接(connect/accept)其他peer后
QuorumCnxManager.RecvWorker                                                                                     per sid         [被]连接(connect/accept)其他peer后
FastLeaderElection.Messenger.WorkerReceiver     LeaderElection中收报文，每个connection一个该线程                per connection  启动时
FastLeaderElection.Messenger.WorkerSender       LeaderElection中发报文，每个connection一个该线程                per connection  启动时
Leader.LearnerCnxAcceptor                       bind(quorumPort)，为每个follower的连接建立1个LearnerHandler                     称为leader后马上
LearnerHandler                                                                                                                  accept之后
SessionTrackerImpl                              跟踪session是否超时，Leader only
=============================================== =============================================================== =============== =====

quorum connection direction with 5 nodes

======= ======= ======= ======= ======= ========
sid     1       2       3       4       5
======= ======= ======= ======= ======= ========
1       <>      <       <       <       <
2               <>      <       <       <
3                       <>      <       <
4                               <>      <
5                                       <>
======= ======= ======= ======= ======= ========


protocols msg format

=============== =========================== ===========
port            phase                       msg                
=============== =========================== ===========
electionPort    initiateConnection          long(sid)           
electionPort    recv msg                    int(length)  -> byte[length]
quorumPort
clientPort
=============== =========================== ===========


C/S
^^^

::


                            ServerCnxnFactory                   ServerCnxn                  
                                ^                                  ^
                                | extends                          | extends
                                |                                  | 
    ClientCnxn         NIOServerCnxnFactory                     NIOServerCnxn               ZooKeeperServer
      |                         |                                  |                                |
      |                         | bind(clientPort)                 |                                |
      |                         |------<>---------                 |                                |
      |  connect                |                                  |                                |
      |------------------------>|                                  |                                |
      |                         | accept                           |                                |
      |                         |---<>--                           |                                |
      |                         |                                  |                                |
      |                         | new instance                     |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | interestOps(OP_READ)           |
      |                         |                                  |---<>----------------           |
      |                         | register cnxn                    |                                |
      |                         |-----<>--------                   |                                |
      |  connect request        |                                  |                                |
      |------------------------>|                                  |                                |
      |                         |                                  |                                |
      |                         | doIO                             |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | checkFourLetterWord            |
      |                         |                                  |------<>------------            |
      |                         |                                  |                                |
      |                         |                                  | readPayload                    |
      |                         |                                  |------<>------------            |
      |                         |                                  |                                |
      |                         |                                  | processConnectRequest          |
      |                         |                                  |------------------------------->|
      |                         |                                  |                                |
      |  request                |                                  |                                |
      |------------------------>|                                  |                                |
      |                         |                                  |                                |
      |                         | doIO                             |                                |
      |                         |--------------------------------->|                                |
      |                         |                                  |                                |
      |                         |                                  | processPacket                  |
      |                         |                                  |------------------------------->|
      |                         |                                  |                                |


Election
^^^^^^^^

::


         Peer               QuorumCnxManager    Listener
          |                     |                   |
          |                     | new               |
          |                     |------------------>|
          |                     |                   | bind(electionAddr)
          |                     |                   |--------<>---------
          | connect             |                   |
          |-------------------->|                   |
          |                     |                   | accept
          |                     |                   |--<>---
          |                     |                   |
                                |                   |
                                |                   |
        

NOTES
=====

选择leader过程中将不能处理用户的请求

::

    public enum ServerState {
        LOOKING, FOLLOWING, LEADING, OBSERVING;
    }


    public enum LearnerType {
        PARTICIPANT, OBSERVER;
    }


    每个写入操作要在至少过半的投票节点达成一致


    client                 follower                     leader
       |                      |                            |
       |      request         |                            |
       |--------------------->|    forward request         |
       |                      |--------------------------->|
       |                      |    proposal                |
       |                      |<---------------------------|
       |                      |    ack                     |
       |                      |--------------------------->|
       |                      |    commit                  |
       |                      |<---------------------------|
       |      response        |                            |
       |<---------------------|                            |
       |                      |                            |




=========================== =============== ===================== ======================= =======================
processor                   ZooKeeperServer LeaderZooKeeperServer FollowerZooKeeperServer ObserverZooKeeperServer
=========================== =============== ===================== ======================= =======================
PreRequestProcessor         ■               ■                     □                       □
SyncRequestProcessor        ■               □                     □                       □
ProposalRequestProcessor    □               ■                     □                       □
FollowerRequestProcessor    □               □                     ■                       □
ObserverRequestProcessor    □               □                     □                       ■
CommitProcessor             □               ■                     ■                       ■
ToBeAppliedRequestProcessor □               ■                     □                       □
FinalRequestProcessor       ■               ■                     ■                       ■
SyncRequestProcessor        □               ■                     ■                       ■
AckRequestProcessor         □               ■                     □                       □
SendAckRequestProcessor     □               □                     ■                       ■
=========================== =============== ===================== ======================= =======================


Replica consistency
===================

Zab(zookeeper atomic broadcast) protocol  - a high performance broadcast protocol

它有2种模式：

- 恢复模式

- 广播模式

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

QuorumCnxManager
================

class
-----

=============== =================
Internal class  Role
=============== =================
Message         msg  
Listener        绑定到当前QuorumPeer的 electionAddr
SendWorker      send msg
RecvWorker      receive msg
=============== =================

queue
-----

- ArrayBlockingQueue<Message> recvQueue

- ConcurrentHashMap<Long, SendWorker> senderWorkerMap

- ConcurrentHashMap<Long, ArrayBlockingQueue<ByteBuffer>> queueSendMap

- ConcurrentHashMap<Long, ByteBuffer> lastMessageSent


FastLeaderElection
==================

class
-----

========================== =================
Internal class             Role
========================== =================
Notification
ToSend
Messenger
Messenger.WorkerReceiver
Messenger.WorkerSender
========================== =================

queue
-----

- LinkedBlockingQueue<ToSend> sendqueue

- LinkedBlockingQueue<Notification> recvqueue


::

            FastLeaderElection.Messenger.WorkerSender
                | poll
            sendqueue
                | offer
            FastLeaderElection.Messenger.WorkerReceiver




QuorumPeer
==========

Declaration
-----------
extends Thread implements QuorumStats.Provider

Members
-------

=============================== ======================================= ===============
class                           member                                  desc
=============================== ======================================= ===============
QuorumPeer                      long myid
QuorumPeer                      int tickTime
QuorumPeer                      volatile Vote currentVote               This is who I think the leader currently is
QuorumPeer                      volatile boolean running
QuorumPeer                      Map<Long, QuorumServer> quorumPeers     cluster里的所有服务器，包括自己
QuorumPeer                      QuorumVerifier quorumConfig             strategy pattern
QuorumPeer                      QuorumCnxManager qcm
QuorumPeer                      FileTxnSnapLog logFactory
QuorumPeer                      ZKDatabase zkDb
QuorumPeer                      LearnerType learnerType
QuorumPeer                      ServerState state = ServerState.LOOKING
QuorumPeer                      InetSocketAddress myQuorumAddr
QuorumPeer                      int electionType
QuorumPeer                      Election electionAlg
QuorumPeer                      NIOServerCnxn.Factory cnxnFactory       通信线程，接收client请求
QuorumPeer                      QuorumStats quorumStats
QuorumPeer                      ResponderThread responder
QuorumPeer                      Follower follower
QuorumPeer                      Leader leader
QuorumPeer                      Observer observer
=============================== ======================================= ===============

start
-----

::

    zkDb.loadDataBase()
           |
    cnxnFactory.start()
           |
    startLeaderElection() --- 启动response线程（根据自身状态）向其他server回复推荐的leader
           |
    super.start() --- 进行选举根据选举结果设置自己的状态和角色


state
------

刚开始的时候，每个peer都是LOOKING状态

做Leader的server如果发现拥有的follower少于半数时，它重新进入looking状态，重新进行leader选举过程

============ ==========================
State        Description
============ ==========================
LOOKING      不知道谁是leader，会发起leader选举
OBSERVING    观察leader是否有改变，然后同步leader的状态
FOLLOWING    接收leader的proposal ，进行投票。并和leader进行状态同步
LEADING      对Follower的投票进行决议，将状态和follower进行同步
============ ==========================

::

                                    ---------
                                   |         |lookForLeader
                                   V         |
                                LOOKING -----
                                   ^
                                   |
                     --------------------------------------------------
                    |                       |                          |
                OBSERVING               FOLLOWING                   LEADING
                    |                       |                          |
             observeLeader()            followLeader()               lead()
                                               |
                                               |- connectLeader
                                               |
                                               |      ------------
                                               |     |            |
                                               |- readPacket      |
                                                - processPackage  |
                                                     ^            |
                                                     |   loop     |
                                                      -------------

run
---

Related
-------

::

                                               
                    Learner ◇--- LearnerZooKeeperServer 
                       ^                               
                       | extends
                    ----------------
                   |                |
                Follower        Observer



                                               - ServerStats serverStats
                                              |- NIOServerCnxn.Factory serverCnxnFactory
                                              |- HashMap<String, ChangeRecord> outstandingChangesForPath
                                              |- SessionTracker sessionTracker
                                              |- FileTxnSnapLog txnLogFactory
                                              |- ZKDatabase zkDb
                    ZooKeeperServer ◇---------|
                            |                  - RequestProcessor firstProcessor
                            |
                    QuorumZooKeeperServer
                            |
                        ----------------------------------------
                       |                                        |
                    LearnerZooKeeperServer              LeaderZooKeeperServer
                                |
                        ----------------------------------------
                       |                                        |
                    ObserverZooKeeperServer     FollowerZooKeeperServer


Definitions
===========

cnxn
----
connection

zxid
-----

zxid = (epoch, counter)
epoch = zxid >> 32
counter = zxid & 0xffffffffL


ZooKeeper Transaction Id，global ordered sequence id

每次write请求对应一个唯一的zxid，如果zxid(a) < zxid(b)，则可以保证a一定发生在b之前

zxid为一64位数字，高32位为leader信息又称为epoch，每次leader转换时递增；低32位为消息编号，Leader转换时应该从0重新开始编号。

The epoch number represents a change in leadership. Each time a new leader comes into power it will have its own epoch number. 

ZxidUtils

通过zxid，Follower能很容易发现请求是否来自老Leader，从而拒绝老Leader的请求

czxid
^^^^^
The zxid of the change that caused this znode to be created.
创建本节点时的zxid 

mzxid
^^^^^
The zxid of the change that last modified this znode.
本节点最后修改时的zxid

pzxid
^^^^^
The zxid of the last proposal commited.

time
----

ctime
^^^^^
The time in milliseconds from epoch when this znode was created.
都以leader时间为准

mtime
^^^^^
last modified, 以leader时间为准 

version
--------
The number of changes to the data of this znode

通过setData会增加版本，每次修改会使version版本增加1.

cversion
^^^^^^^^
The number of changes to the children of this znode
孩子变化时会更改父亲节点的版本，每当有孩子增加或者删除时，此版本增加1 

aversion
^^^^^^^^
The number of changes to the ACL of this znode.

每当有对此节点进行setACL操作时，aversion会自动增加1

ephemeralOwner
--------------
The session id of the owner of this znode if the znode is an ephemeral node. 
If it is not an ephemeral node, it will be zero.

如果节点为临时节点，则表明那个session创建此节点


startup
=======

State
-----



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
                  |                           -  bind 2181 (clientPort)
            configure(2181, maxClientCnxns) -|
                  |                           -  register OP_ACCEPT
                  |                           
            new QuorumPeer
                  |                           
            loadDataBase
                  |           client                
            cnxnFatory.start --------
                  |                           
            startLeaderElection
                  |                           
                 run


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

::

                     path               DataNode
                    -----------        ----------------------------- <----------
                   | /         |----->| content | parent | children |---        |
                   |-----------|       -----------------------------    |       |
                   |           |                    ^         |         |       |
                   |           |                    |         |         V       |
                   |           |        DataNode    |         V         |       |
                   |           |       -----------------------------    |       |
                   | /demo     |----->| content | parent | children |<--|       |
                   |           |       -----------------------------    |       |
                   |           |                    ^         |         |       |
                   |-----------|                    |         |         |       |
                   |           |        DataNode    |         V         |       ^
                   |           |       -----------------------------    |       |
                   | /demo/foo |----->| content | parent | children |<--|       |
                   |           |       -----------------------------    |       |
                   |           |                                        |       |
                   |-----------|                                        |       |
                   |           |                                        |       |
                   |           |        DataNode                        |       |
                   |           |       -----------------------------    |       |
                   | /bar      |----->| content | parent | children |<--        |
                   |           |       -----------------------------            |
                   |           |                    |                           |
                   |           |                    |                           |
                   |-----------|                     ------------>--------------
                   |           |       
                   | ...       |
                   |           |       
                    -----------


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


Log文件格式
-----------

Preallocate strategy, we define EOF to be an empty transaction

::

    struct FileHeader {
        int magic;      // "ZKLG"
        int version;    // 2
        long dbid;      // 0
    }

    struct TxnHeader {
        long clientId; // session id
        int cxid;
        long zxid;
        long time;
        int type; // 事务类型
    }
    

    5a4b 4c47   0000 0002   0000 0000   0000 0000  ---- FileHeader
    ---------   ---------   ---------------------
    magic       version     dbid

    0000 0000   81ec 0918   0000 0024   0139 90db  ---
    ---------------------   ---------   ---------     |
    crc value of the entry  entry len   {clientId     |
                                                      | 1                 - CheckVersionTxn
    01c8 0000   0000 0000   0000 0000   0000 000e     | Transaction -----|- SetMaxChildrenTxn
    ---------   ---------   ---------------------     | entry            |- SetDataTxn
            }   cxid        zxid                      |                  |- SetACLTxn
                                                      |                  |- MultiTxn
    0000 0139   94ab 4f3b   ffff fff6   0000 7530     |                  |- ErrorTxn
    ---------------------   ---------   ---------     |                  |- DeleteTxn
    time                    type        txn data      |                  |- CreateTxn
                                                      |                   - CreateSessionTxn
    42                                             ---                   
    --                                              
    B(End of record flag)

    00 0000   0062 6a09   04  00 0000   20  01 3990
    ------------------------  ------------  -------
    crc value of the entry    entry len     {clientId


SnapShot文件格式
----------------

The server itself only needs the latest complete fuzzy snapshot and the log files from the start of that snapshot.

snapshot.xxx：
xxx is the zxid, the ZooKeeper transaction id, of the last committed transaction at the start of the snapshot

log.xxx：
xxx is the first zxid written to that log

Current DataTree = snapshort.xxx + log.xxx

LogFormatter is used to check out contents of log file

文件尾：

writeLong(crcChecksumValue)
writeString("/")  // 00 0000 012f


文件头：

::

    struct FileHeader {
        int magic;      // "ZKSN"
        int version;    // 2
        long dbid;      // -1
    }

    struct Sessions {
        int count;
        List<long sessionId, int sessionTimeout>; // count
    }

    struct DataTree {
        int mapSize;
        List<Map<Long, List<ACL>>> map;

        List struct DataNode {
            int pathLen;
            string path;

            int dataLen;
            byte[] data;
            long acl;
            
            struct Stat {
                long czxid;
                long mzxid;
                long ctime;
                long mtime;
                int version;
                int cversion;
                int aversion;
                long ephemeralOwner;
                long pzxid;
            };
        }

        string nextPath;
    }



    5a4b 534e 0000 0002 ffff ffff ffff ffff         ----  FileHeader
    --------- --------- -------------------
    magic     version   dbid

    0000 0000                                       ----  Sessions
    ---------
    session count

    0000 0001 0000 0000 0000 0001                    ---
    --------- -------------------                       |
    map       long                                      |
                                                        |
    0000 0001 0000 001f 0000 0005 776f 726c             |
    --------- --------- --------- ---------             |
    aclLen   aclPerms   {strLen    schem                |
                                                        |
    64 00 0000 06 61 6e79 6f6e 65 00 0000 00 00         |
    -- ---------- --------------- ---------- --         |
    a  strLen     schema}id        path                 |
                                                        | DataTree
    0000 00   ff ffff ffff ffff ff 00 0000 0000         |
    -------   -------------------- ------------         |
    {nodeData acl                  czxid                |
                                                        |
    0000 00 00 0000 0000 0000 00 00 0000 0000           |
    ------- -------------------- ------------           |
            mzxid                ctime                  |
                                                        |
    0000 00 00 0000 0000 0000 00 00 0000 00 00          |
    ------- -------------------- ---------- --          |
            mtime                version                |
                                                        |
    0000 01  00 0000 00 00 0000 0000 0000 00 00         |
    -------  ---------- -------------------- --         |
    cversion aversion   ephemeralOwner                  |
                                                        |
    0000 0000 0000 03 00 0000 05 2f 6465 6d6f           |
    ----------------- ---------- ------------           |
    pzxid}dataNode    pathLen    /demo                  |
                                                        |
    0000 0006 4269 6e67 6f21 0000 0000 0000             |
    --------- -------------- --------------             |
    dataLen   Bingo!         {acl                       |
                                                        |
    0001 0000 0000 0000 0003 0000 0000 0000             |
    ---- ------------------- --------------             |
         czxid               mzxid                      |
                                                        |
    000a 0000 0138 b1d5 8bf4 0000 0138 b208             |
    ---- ------------------- --------------             |
         ctime               mtime                      |
                                                        |
    c53c 0000 0002 0000 0000 0000 0000 0000             |
    ---- --------- --------- --------- ----             |
         version   cversion  aversion                   |
                                                        |
    0000 0000 0000 0000 0000 0000 0003 0000          --- 
    -------------- -------------------              
    ephemeralOwner  pzxid                          


restore
-------

::

        ZKDatabase.loadDataBase()
                    |
        从dataDir里按照文件倒序排列取得最多100个snapshot.xxx文件
                    |
        找到第一个有效的snapshot文件，并反序列化到内存里的DataTree
                    |
        通过该snapshot文件名，得到lastProcessedZxid
                    |
        lastProcessedZxid以前的所有数据都在snapshot里了，更新的数据在txnLog(WAL)里
                    |
        从lastProcessedZxid + 1开始找txnLog
                    |
        对每个transaction，在内存replay，同时通过队列机制发送给learners
                    |
        得到当前系统的最新zxid值, 内存数据库DataTree初始化完毕


Contribute
==========

src
---

::

    svn checkout http://svn.apache.org/repos/asf/zookeeper/trunk/ zookeeper-trunk

unittest
--------

::

    ant -Djavac.args="-Xlint -Xmaxwarns 1000" clean test tar
    ant test
    ant -diagnostics

javadoc
-------

::

    ant javadoc
    open build/docs/api/index.html

patch
-----

create
^^^^^^

::

    svn stat
    svn diff > ZOOKEEPER-<JIRA#>.patch

apply
^^^^^

::

    patch -p0 [--dry-run] < ZOOKEEPER-<JIRA#>.patch

