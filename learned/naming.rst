=========================
naming learned from hbase 
=========================

:Author: Funky Gao <funky.gao@gmail.com>
:Description: What we can learn from hbase about the naming? Including variable, method and class names.

.. contents:: TOC
.. section-numbering::

Class naming
============

- HConstants

- YouAreDeadException


Method naming
=============

- getRegionsCount

- prettyPrint

- isType(HMsg.Type type other)

  instead of hmsg.getType().equals(xxx)

- createAndFailSilent

  try {} catch (Exception e) { // noop }


Variable naming
===============


Constant naming
===============

- HConstants.FOREVER
