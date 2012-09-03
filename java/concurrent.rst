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

