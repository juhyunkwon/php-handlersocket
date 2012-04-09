<?php
$host = 'localhost';
$port = 9998;
$port_wr = 9999;
$dbname = 'hstestdb';
$table = 'hstesttbl';


//GET
try
{
    $hs = new HandlerSocket($host, $port);
    $index = $hs->createIndex(1, $dbname, $table, HandlerSocket::PRIMARY, 'k,v');
}
catch (HandlerSocketException $exception)
{
    var_dump($exception->getMessage());
    die();
}

$retval = $index->find(array('=' => array('k1')));

var_dump($retval);


$retval = $index->find(array('=' => array('k2')), 1, 0);

var_dump($retval);

$retval = $index->multi(
    array(array('find' => array(array('=' => array('k1')), 1, 0)),
          array('find' => array(array('=' => array('k2'))))));

var_dump($retval);

unset($index);
unset($hs);


//UPDATE
try
{
    $hs = new HandlerSocket($host, $port_wr);
    $index = $hs->createIndex(2, $dbname, $table, '', 'v');
}
catch (HandlerSocketException $exception)
{
    var_dump($exception->getMessage());
    die();
}

if ($index->update(
        array('U' => array('V1')),
        array('=' => array('k1'))) === false)
{
    echo __LINE__, ':', $index->getError(), ':', PHP_EOL;
    die();
}

unset($index);
unset($hs);


//INSERT
try
{
    $hs = new HandlerSocket($host, $port_wr);
    $index = $hs->createIndex(3, $dbname, $table, '', 'k,v');
}
catch (HandlerSocketException $exception)
{
    var_dump($exception->getMessage());
    die();
}

if ($index->insert('k2', 'v2') === false)
{
    echo __LINE__, ':', $index->getError(), ':', PHP_EOL;
}

if ($index->insert('k3', 'v3') === false)
{
    echo __LINE__, ':', $index->getError(), ':', PHP_EOL;
}
if ($index->insert('k4', 'v4') === false)
{
    echo __LINE__, ':', $index->getError(), ':', PHP_EOL;
}

unset($index);
unset($hs);


//DELETE
try
{
    $hs = new HandlerSocket($host, $port_wr);
    $index = $hs->createIndex(4, $dbname, $table, '', '');
}
catch (HandlerSocketException $exception)
{
    var_dump($exception->getMessage());
    die();
}

if (!($index->remove(array('=' => array('k2')))))
{
    echo __LINE__, ':', $index->getError(), ':', PHP_EOL;
    die();
}
