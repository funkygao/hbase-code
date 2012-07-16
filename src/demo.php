<?php
/**
 * 演示PHP通过thrift操作HBase.
 *
 * <code>
 * cd /opt/app/hbase-0.90.3/
 * ./bin/start-hbase.sh
 * ./bin/hbase-daemon.sh start thrift
 *
 * php demo.php
 * </code>
 *
 */

$GLOBALS['THRIFT_ROOT'] = '/usr/lib/php';

require_once($GLOBALS['THRIFT_ROOT'] . '/Thrift.php');
require_once($GLOBALS['THRIFT_ROOT'] . '/transport/TSocket.php');
require_once($GLOBALS['THRIFT_ROOT'] . '/transport/TBufferedTransport.php');
require_once($GLOBALS['THRIFT_ROOT'] . '/protocol/TBinaryProtocol.php');

require_once dirname(__FILE__) . '/Hbase.php';

//open connection
$socket = new TSocket('192.168.0.106', 9090);
$transport = new TBufferedTransport($socket);
$protocol = new TBinaryProtocol($transport);
$client = new HbaseClient($protocol);
$transport->open();

//show all tables
$tables = $client->getTableNames();
foreach ($tables as $name) {
    echo("  found: {$name}\n");
}

//Create a table
try {
    $columns = array(new ColumnDescriptor(array(
        'name' => 'colFamily:',
        'maxVersions' => 10)));

    $client->createTable("tableName", $columns);
} catch (AlreadyExists $ae) {
    echo("WARN: {$ae->message}\n");
}

//insert data to table
$mutations = array(
    new Mutation(array(
        'column' => 'colFamily:Col',
        'value' => 'value123'
    )),
);
$client->mutateRow("tableName", "ID_1237846634624", $mutations);

//get table data
$rows = $client->getRow("tableName", "ID_1237846634624");
foreach ($rows as $row)
{
    var_dump($row);
}
