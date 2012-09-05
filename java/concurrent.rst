==================
Java concurrent
==================

:Author: Gao Peng <funky.gao@gmail.com>
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


Thread
============

System.exit(status) will terminate all threads of the current process!

::

    private Runnable target;

    public synchronized void start();

    public void run() {
        if (target != null) {
            target.run();
        }
    }


synchronized
============

for static members, must synchronized the class

::

    class Foo {
        private static int shared;

        public int read() {
            synchronized(Foo.class) {
                return shared;
            }
            
        }

        public static synchronized void write(int x) {
            shared = x;
        }

    }


volatile
========

The Java Language Specification requires that a volatile field not be held in local memory and that all reads and writes go straight to main memory.

Furthermore, operations on volatile fields must be performed in exactly the order that a thread requests. 

A further rule requires that volatile double or long variables must be read and written atomically.

Objects and arrays are accessed via references and, therefore, marking them as volatile only applies to the references, not to the fields of the objects or the components of the arrays. 
It is not possible to specify that elements of an array are volatile.
