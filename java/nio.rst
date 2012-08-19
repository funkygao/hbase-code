==================
Java NIO explained
==================

:Author: Gao Peng <funky.gao@gmail.com>
:Description: Java NIO design and internals, jdk since 1.4
:Revision: $Id$

.. contents:: Table Of Contents
.. section-numbering::


JCP
============

http://jcp.org/en/jsr/detail?id=51


Buffer
======

在 NIO 库中,所有数据都是用缓冲区处理的。在读取数据时,它是直接读到缓冲区中的。在写入数据时,它是写入到
缓冲区中的。

::


                                     |- capacity
                                     |- position
                                     |- mark
                                     |- limit
                                     |- reset
                                     |- clear
                                     |- flip
                                     |- rewind
                      [abstract]     |- remaining
                        Buffer-------|- hasRemaining
                           |         |- isReadOnly
                           |
            ---------------------------------------------------------------------
           |           |        |             |          |          |            |
        CharBuffer IntBuffer DoubleBuffer ShortBuffer LongBuffer FloatBuffer ByteBuffer
                                                                                 |
                                                                             MappedByteBuffer



Channel
=======

A channel represents an open connection to an entity such as a hardware device, a file, a network 
socket, or a program component that is capable of performing one or more distinct I/O operations, 
such as reading or writing.

In most cases, channels are 1-1 relationship with OS file descriptors.

Scatter/gather refers to performing a single I/O operation across multiple buffers. 

File channels are always blocking and cannot be placed into nonblocking mode.

.. image:: http://s8.sinaimg.cn/orignal/630c58cbtc79480c21507&690

Selector
========

A Selector class is a multiplexor of Channels.

Selector.select 更新所有就绪的SelectionKey的状态, 并返回就绪的channel个数


SelectionKey
============

A Channel's registration with the Selector is represented by a SelectionKey object.

表示Selector和被注册的channel 之间关系, 一份凭证, 保存channel感兴趣的事件

It is valid until one of these 3 conditions is met:

- the channel is closed

- the selector is closed

- the SelectionKey is cancelled by invoking its cancel() method



::

    SelectionKey key = serverChannel.register(selector, SelectionKey.OP_ACCEPT);
