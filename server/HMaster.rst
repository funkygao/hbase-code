=======
HMaster
=======

:Author: Funky Gao <funky.gao@gmail.com>

.. contents:: TOC
.. section-numbering::


class declaration
=================
public class HMaster
    extends Thread
    implements HMasterInterface, HMasterRegionInterface, MasterServices, Server


constructor
===========

::

    configuration
          |
          |
    determine address
          |
          |                     - responder    -
    init rpc server and start -|- listener     -|- extends Thread
          |                     - handlers[10] -
          |
    create ZooKeeperWatcher(out zk client)
          |
          |
    create MasterMetrics


run
===

::

    create ActiveMasterManager(Manager and zk listener for master election)
          |
          |
    stallIfBackupMaster
          |
          |
    activeMasterManager.blockUntilBecomingActiveMaster
          |
          |
    finishInitialization
          |        |             create      - MasterFileSystem
          |         ------------------------|- HConnectionImplementation
          |                                 |- ExecutorService
     main thread loop                       |- ServerManager(to deal with region server info)
                                            |- CatalogTracker and start root/meta tracker
                                            |- AssignmentManager
                                            |- LoadBalancer
                                            |- RegionServerTracker
                                            |- ClusterStatusTracker and setClusterUp
                                            |-
                                            |- startServiceThreads
                                            |       |
                                            |       |- startExecutorService
                                            |       |- LogCleaner
                                            |        - InfoServer
                                            |       
                                            |- serverManager.waitForRegionServers
                                            |-
                                            |- assignRootAndMeta
                                            |-
                                            |- start blancer chore(runs every 5m)
                                             - start catalogJanitorChore(runs every 5m)



stop
====

::


                 - balancerChore.interrupt()
    stopChores -|
          |      - catalogJanitorChore.interrupt()
          |
    serverManager.letRegionServersShutdown
          |
          |
          |              - rpcServer
    stopServiceThreads -|- logCleaner
          |             |- infoServer
          |              - executorService
          |
          |
    HConnectionManager.deleteConnection
          |
          |
    zooKeeper.close()



main components
===============


