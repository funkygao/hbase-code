=================
how to tune HBase
=================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: Build a high performance and high throughput HBase cluster
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


Basic
=====

speed and throughput
--------------------

=========== =========== =========== =========== =============== =============== =========== ================
Item        L1          L2          L3          memory          disk            SSD         NIC
=========== =========== =========== =========== =============== =============== =========== ================
volumn      32KB        256KB       8MB         X0 GB           X TB            X00 GB      -
seek        2ns         5ns         15ns        50ns            10ms            100us       -
throuput    6500MB/s    3000        2200        800             100             250         100
=========== =========== =========== =========== =============== =============== =========== ================
