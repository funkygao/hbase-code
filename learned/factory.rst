=====================
HBase factory pattern
=====================

:Author: Funky Gao <funky.gao@gmail.com>
:Description: HBase里工厂的设计和使用

.. contents:: TOC
.. section-numbering::


HTableFactory
=============

它是HTable的工厂类，把HTable的创建分离出来

::


    public class HTableFactory {
        public HTableInterface createHTableInterface(Configuration config,
            byte[] tableName) {
            try {
                return new HTable(config, tableName);
            } catch (IOException ioe) {
                throw new RuntimeException(ioe);
            }
        }

        public void releaseHTableInterface(HTableInterface table) {
            try {
                table.close();
            } catch (IOException ioe) {
                throw new RuntimeException(ioe);
            }
        }

    }
