<?php
/**
 * 演示PHP通过thrift操作HBase.
 *
 * <code>
 * /opt/app/thrift/bin/thrift -gen php /opt/app/hbase-0.90.3/src/main/resources/org/apache/hadoop/hbase/thrift/Hbase.thrift
 *
 * cd /opt/app/hbase-0.90.3/
 * ./bin/start-hbase.sh
 * ./bin/hbase-daemon.sh start thrift
 *
 * php demo.php
 * </code>
 *
 */

define('THRIFT_ROOT', '/usr/lib/php');

define('HBASE_HOST', '192.168.0.106');
define('HBASE_PORT', 9090);

// load thrift lib for php
require_once(THRIFT_ROOT . '/Thrift.php');
require_once(THRIFT_ROOT . '/transport/TSocket.php');
require_once(THRIFT_ROOT . '/transport/TBufferedTransport.php');
require_once(THRIFT_ROOT . '/protocol/TBinaryProtocol.php');

// load the thrift generated stub
require_once dirname(__FILE__) . '/Hbase.php';

// open connection
$socket = new TSocket(HBASE_HOST, HBASE_PORT);
$transport = new TBufferedTransport($socket);
$protocol = new TBinaryProtocol($transport);
$transport->open();

// create hbase client
$client = new HbaseClient($protocol);

// show all tables
echo "Existing tables:\n";
$tables = $client->getTableNames();
foreach ($tables as $name)
{
    echo("  found: {$name}\n");
}

// create a table with name 'tableName'
echo "\nCreate table: tableName\n";
try
{
    $columns = array(
        new ColumnDescriptor(
            array(
                'name' => 'colFamily:',
                'maxVersions' => 10,
            )
        )
    );

    $client->createTable("tableName", $columns);
}
catch (AlreadyExists $ae)
{
    echo("WARN: {$ae->message}\n");
}

// insert data to table
echo "\nInsert data to table: tableName\n";
$mutations = array(
    new Mutation(array(
        'column' => 'colFamily:Col',
        'value' => 'value123'
    )),
);
$client->mutateRow("tableName", "ID_1237846634624", $mutations);

// get table data
echo "\nData in table: tableName\n";
$rows = $client->getRow("tableName", "ID_1237846634624");
foreach ($rows as $row)
{
    var_dump($row);
}
