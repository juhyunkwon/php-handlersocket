<?php
/*
  CREATE TABLE hstesttbl (
    k varchar(30) PRIMARY KEY,
    v varchar(30) NOT NULL,
    f1 varchar(30),
    f2 varchar(30)
   ) Engine = innodb;

   CREATE INDEX i1 ON hstesttbl(v, k);

   INSERT INTO hstesttbl VALUES ('k1', 'v1', 'f1', 'f2');
   INSERT INTO hstesttbl VALUES ('k2', 'v2', 'f2', NULL);
   INSERT INTO hstesttbl VALUES ('k3', 'v3', 'f3', '');
   INSERT INTO hstesttbl VALUES ('k4', 'v4', 'f4', 'f24');
   INSERT INTO hstesttbl VALUES ('k5', 'v5', 'f5', NULL);
   INSERT INTO hstesttbl VALUES ('k6', 'v6', 'f6', '');
*/

function status_ok($proc, $msg = 'OK')
{
    if (is_scalar($msg))
    {
        if (is_bool($msg))
        {
            if ($msg)
            {
                echo "$proc => \033[1;34mtrue\033[0m\n";
            }
            else
            {
                echo "$proc => \033[1;34mfalse\033[0m\n";
            }
        }
        else
        {
            echo "$proc => \033[1;34m$msg\033[0m\n";
        }
    }
    else
    {
        echo "$proc => \033[1;34m\n";
        var_dump($msg);
        echo "\033[0m";
    }
}

/* Connection information */
$host = 'localhost';
$port = 9999;
//$port = '9999';
$dbname = 'hstestdb';
$table = 'hstesttbl';
$auth = 'pass';


// HandlerSocket: connection
try
{
    //Success:
    $hs = new HandlerSocket(
        $host, // host
        $port  // port
    );
    //$hs = new HandlerSocket($host, $port, null);  //null option
    //$hs = new HandlerSocket(null, $port, array('host' => $host));
    //$hs = new HandlerSocket($host, null, array('port' => $port));
    //$hs = new HandlerSocket(null, null, array('host' => $host, 'port' => $port));

    //Failure:
    //$hs = new HandlerSocket();                    //no arguments
    //$hs = new HandlerSocket($host);               //pot without specifying
    //$hs = new HandlerSocket('', $port);           //host empty
    //$hs = new HandlerSocket('testserver', $port); //Invalid host
    //$hs = new HandlerSocket($host,'');            //port empty
    //$hs = new HandlerSocket($host, 8888);         //port disable
    //$hs = new HandlerSocket(null, $port);         //null host
    //$hs = new HandlerSocket($host, null);         //null port
    //$hs = new HandlerSocket(null, null);          //null
}
catch (HandlerSocketException $exception)
{
    //When an error occurs HandlerSocketException
    echo $exception->getMessage(), PHP_EOL;
    die();
}

status_ok('new HandlerSocket', $hs);


//Authentication
//Success:
$ret = $hs->auth($auth);
//$ret = $hs->auth($auth, '1');
//$ret = $hs->auth($auth, 'hoge');

//Failure:
//$ret = $hs->auth(); //no arguments
//$ret = $hs->auth(''); //empty
//$ret = $hs->auth(null); //NULL
//$ret = $hs->auth(array($auth)); //array

status_ok('HandlerSocket::auth', $ret);


//Expansion index
//Success:
$id = 1;
$key = 'PRIMARY';
//$key = 'i1';
//$key = ''; //empty is the same as PRIMARY
//$key = null; //the same as PRIMARY may be NULL
$field = 'k,v';
//$field = array('k', 'v');
//$field =''; //can not be retrieved nothing
//$field = null; //can not be retrieved nothing
//$filter = 'f1,f2';
//$filter = array('f1', 'f2');
//$filter =''; //what in this case?
$filter = null; //what In this case?

$ret = $hs->openIndex(
    $id,     //number index
    $dbname, //name database
    $table,  //name table
    $key,    //name index
    $field,  //field list (array or comma-delimited text)
    $filter  //filter the field list (array or comma-delimited text)
);

/*
//Failure:
$key = 'PRIMARY';
$field = 'k,v';
$filter = 'f1,f2';
//$ret = $hs->openIndex(); //no arguments
//$ret = $hs->openIndex($id); //DB, Table, Key, No Field
//$ret = $hs->openIndex($id, $dbname); //Table, Key, No Field
//$ret = $hs->openIndex($id, $dbname, $table); //Key, No Field
//$ret = $hs->openIndex($id, $dbname, $table, $key); //without Field
//$ret = $hs->openIndex('', $dbname, $table, $key, $field); //ID empty
//$ret = $hs->openIndex('a', $dbname, $table, $key, $field); //ID string
//$ret = $hs->openIndex($id,'', $table, $key, $field); //DB empty
//$ret = $hs->openIndex($id, 'hoge', $table, $key, $field); //DB Invalid
//$ret = $hs->openIndex($id, null, $table, $key, $field); //NULL DB
//$ret = $hs->openIndex($id, array ($db), $table, $key, $field); //DB array
//$ret = $hs->openIndex($id, $dbname,'', $key, $field); //Table empty
//$ret = $hs->openIndex($id, $dbname, 'hoge', $key, $field); //Table Invalid
//$ret = $hs->openIndex($id, $dbname, null, $key, $field); //NULL Table
//$ret = $hs->openIndex($id, $dbname, array ($table), $key, $field); //Table array
//$ret = $hs->openIndex($id, $dbname, $table, 'hoge', $field); //Key Invalid
//$ret = $hs->openIndex($id, $dbname, $table, array ($key), $field); //Key array
//$ret = $hs->openIndex($id, $dbname, $table, $key, 'hoge'); Field //Invalid
//$ret = $hs->openIndex($id, $dbname, $table, $key, $field, 'hoge'); //Filter Invalid
//$ret = $hs->openIndex($id, $dbname, $table, $key, $field, array ('hoge')); //Filter array Invalid
*/

status_ok('HandlerSocket::openIndex', $ret);


//Execute the processing
//Success:
//$ret = $hs->executeSingle($id, '>', array('k1'));
//$ret = $hs->executeSingle($id, '>', 'k1');
//[SQL] => SELECT k, v FROM hstesttbl WHERE k = 'k1' LIMIT 0, 1

//$ret = $hs->executeSingle($id, '>=', array('k1'), 2);
//[SQL] => SELECT k, v FROM hstesttbl WHERE k >= 'k1' LIMIT 0, 2

//$ret = $hs->executeSingle($id, '>=', array('k1'), 2, 1);
//[SQL] => SELECT k, v FROM hstesttbl WHERE k >= 'k1' LIMIT 1, 2

//$ret = $hs->executeSingle($id, '>=', array('k1'), 2, 0, null, null, array('F', '>=', 0, 'f2'));
//$ret = $hs->executeSingle($id, '>=', array('k1'), 2, 0, null, null, array(array('F', '>=', 0, 'f2')));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k >= 'k1' AND f1 >= 'f2' LIMIT 0, 2

//$ret = $hs->executeSingle($id, '>=', array('k1'), 2, 0, null, null, array(array('F', '>=', 0, 'f2'), array('F', '=', 1, null)));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k >= 'k1' AND f1 >= 'f2' AND f2 IS NULL LIMIT 0, 2

//$ret = $hs->executeSingle($id, '=', array(''), 3, 0, null, null, null, 0, array('k3', 'k6'));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k IN ('k3', 'k6') LIMIT 0, 3

//$ret = $hs->executeSingle($id, '=', array('k1'), 1, 0, 'U', array('k1', 'V1'));
//$ret = $hs->executeSingle($id, '=', array('k1'), null, null, 'U', array('k1', 'v1'));
//[SQL] => UPDATE hstesttbl SET k = 'k1', v = 'V1' WHERE k = 'k1';

//$ret = $hs->executeSingle($id, '=', array('k1'), 1, 0, 'U?', array('k1', 'V1'));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k = 'k1' LIMIT 0, 1; UPDATE hstesttbl SET k = 'k1', v = 'V1' WHERE k = 'k1' LIMIT 0, 1

//Failure:
//$ret = $hs->executeSingle(); //no arguments
//$ret = $hs->executeSingle($id); //comparison operator, no comparison value
//$ret = $hs->executeSingle($id, '>'); //no comparison value
//$ret = $hs->executeSingle(10, '>', array('k1')); //ID invalid connection
//$ret = $hs->executeSingle($id, '>>', array('k1')); //invalid comparison operator
//$ret = $hs->executeSingle($id, '>', array('k1'), 'a'); //limit value Invalid
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 'a'); //invalid offset value
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 'a'); //invalid offset value
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, 'u'); //update operator Invalid

//Ignore: ignore filter
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, null, null, array()); //filter empty
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, null, null, array('F', '> =', 0)); // Disable filter

status_ok('HandlerSocket::executeSingle', $ret);


//Execution of multiple processing
//Success:
$ret = $hs->executeMulti(
    array(
        array($id, '=', 'k10'),
        array($id, '+', array('k10', 'v10')),
        array($id, '=', array('k10')),
        array($id, '=', array('k10'), 1, 0, 'U', array('k10', '^^10')),
        array($id, '>=', array('k10'), 1, 0),
        array($id, '=', array('k10'), 1, 0, 'D'),
        array($id, '=', array('k10'), 1, 0)
    ));

//Failure:
//$ret = $hs->executeMulti(); //no arguments
//$ret = $hs->executeMulti(''); //empty
//$ret = $hs->executeMulti(array()); //an empty array
//$ret = $hs->executeMulti(array('')); //an empty array

status_ok('HandlerSocket::executeMulti', $ret);


//Execute the update process
$hs->openIndex(2, $dbname, $table, $key, 'k,v,f1,f2');
$hs->executeSingle(2, '+', array('k20', 'v20', 'f20', 'f220'));

//Success:
$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'V20', 'F20', 'F220'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'VV20', 'FF20'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'VV20'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), 'k20');
//$ret = $hs->executeUpdate(2, '=', array('hoge'), array('k20', 'V20', 'F20', 'F220')) //No target

//Failure:
//$ret = $hs->executeUpdate(); //no arguments

status_ok('HandlerSocket::executeUpdate', $ret);


//Execute the delete operation
//Success:
$ret = $hs->executeDelete($id, '=', array('k20'));
//$ret = $hs->executeDelete($id, '=', 'k20');
//$ret = $hs->executeDelete($id, '=', 'hoge'); // No target

//Failure:
//$ret = $hs->executeDelete(); //no arguments

status_ok('HandlerSocket::executeDelete', $ret);


//Execute the insert operation
$hs->openIndex(2, $dbname, $table, $key, 'k,v,f1,f2');

$ret = $hs->executeInsert(2, array('K30', 'V30', 'F130', 'F230'));
//$ret = $hs->executeInsert(2, array('K30', 'V30', 'F130', null, 'F230'));

//Failure:
//$ret = $hs->executeInsert(); //no arguments
//$ret = $hs->executeInsert(2, array()); //empty

status_ok('HandlerSocket::executeInsert', $ret);

$hs->executeDelete($id, '=', array('K30'));


//Create object HandlerSocketIndex
$id = 3;
$key = 'PRIMARY';
//$field = 'k,v';
$field = array('k', 'v');
//$filter = 'f1,f2';
$filter = array('f1', 'f2');

try
{
    //Success:
    $index = $hs->createIndex(
        $id,     //number index
        $dbname, //name database
        $table,  //name table
        $key,    //name index
        $field,  //field list (array or comma-delimited text)
        array('filter' => $filter)
    );

    /*
    $index = new HandlerSocketIndex(
        $hs,     //HandlerSocket object
        $id,     //number index
        $dbname, //name database
        $table,  //name table
        $key,    //name index
        $field,  // field list (array or comma-delimited text)
        array('filter' => $filter)
    );
    */

    //Failure:
    //$index = $hs->createIndex(); //no arguments
    //$index = $hs->createIndex($id); // DB, Table, Key, No Field
    //$index = $hs->createIndex($id, $dbname); // Table, Key, No Field
    //$index = $hs->createIndex($id, $dbname, $table); // Key, No Field
    //$index = $hs->createIndex($id, $dbname, $table, $key); //without Field
    //$index = $hs->createIndex('', $dbname, $table, $key, $field); //ID empty
    //$index = $hs->creteIndex('a', $dbname, $table, $key, $field); //ID string
    //$index = $hs->createIndex($id,'', $table, $key, $field); //DB empty
    //$index = $hs->createIndex($id, 'hoge', $table, $key, $field); //DB Invalid
    //$index = $hs->createIndex($id, $dbname,'', $key, $field); //Table empty
    //$index = $hs->createIndex($id, $dbname, 'hoge', $key, $field); //Table Invalid
    //$index = $hs->createIndex($id, $dbname, $table, 'hoge', $field); //Key Invalid
    //$index = $hs->createIndex($id, $dbname, $table, $key, 'hoge'); //Field Invalid

    //Failure: HandlerSocketIndex
    //class Obj {}
    //$obj = new Obj();
    //$index = new HandlerSocketIndex(
    //    $obj, object // Invalid
    //    $id, $dbname, $table, $key, $field
    //);

}
catch (HandlerSocketException $exception)
{
    //When an error occurs HandlerSocketException
    echo 'Error:', $exception->getMessage(), PHP_EOL;
    die();
}

status_ok('HandlerSocket::createIndex', $index);


//Get the connection number
status_ok('HandlerSocketIndex::getId', $index->getId());

//Get the name of the database
status_ok('HandlerSocketIndex::getDatabase', $index->getDatabase());

//Get the table name
status_ok('HandlerSocketIndex::getTable', $index->getTable());

//Get the field list (array)
status_ok('HandlerSocketIndex::getField', $index->getField());

//Get the filter list (array)
status_ok('HandlerSocketIndex::getFilter', $index->getFilter());

//Get the effective operator
status_ok('HandlerSocketIndex::getOperator', $index->getOperator());



//Get the data
//Success:
status_ok('HandlerSocketIndex:find', $index->find('k2'));
status_ok('HandlerSocketIndex:find', $index->find(array('k2')));
status_ok('HandlerSocketIndex:find', $index->find(array('=' => 'k2')));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k = 'k2' LIMIT 1

status_ok('HandlerSocketIndex:find', $index->find(array('>' => ''), 2, 0));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k > '' LIMIT 2

status_ok('HandlerSocketIndex:find', $index->find(array('>' => ''), 2, 1));
//[SQL] => SELECT k, v FROM hstesttbl WHERE k > '' LIMIT 1, 2

//status_ok('HandlerSocketIndex:find', $index->find(null));

$ret = $index->find(
    '', 10, 0, array('in' => array('k2', 'k4')));
    //'', 10, 0, array('in' => 'k5'));
    //'', 10, 0, array('in' => array(array('k3', 'k6'))));
status_ok('HandlerSocketIndex:find', $ret);
//[SQL] => SELECT k, v FROM hstesttbl WHERE k IN ('k2', 'k4) LIMIT 10

$ret = $index->find(
    array('>' => ''), 10, 0, array('filter' => array('=', 'f1', 'f2')));
//$ret = $index->find(
//    array('>' => ''), 10, 0, array('filter' => array(array('=', 'f1', 'f2'))));
//$ret = $index->find(
//    array('>' => ''), 2, 0, array('filter' => array(
//                                      array('>=', 'f1', 'f2'),
//                                      array('=', 'f2', null),
//                                   )));
status_ok('HandlerSocketIndex:find', $ret);
//[SQL] => SELECT k, v FROM hstesttbl WHERE k > '' AND f1 = 'f2' LIMIT 10

//Failure:
//$ret = $index->find(array('>>' => 'k3'));
//try
//{
//    $ret = $index->find(array('>>' => 'k3'), 1, 0, array('safe' => true));
//}
//catch (HandlerSocketException $exception)
//{
//    $ret = false;
//    echo $exception->getMessage(), PHP_EOL;
//}

//$ret = $index->find(); //no arguments
//$ret = $index->find(array('>>' => 'k3')); //rogue operator
//$ret = $index->find(array('=' => array('k3', 'k2'))); //array Invalid
//$ret = $index->find('', 'a'); //limit value Invalid
//$ret = $index->find('', 2, 'a'); //offset value Invalid

//Ignore:
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>'))); //less than the argument filter
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>', 'f1'))); //less than the argument filter
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>', 'f3', 'f'))); //filter invalid key


//Add the data
//Success:
status_ok('HandlerSocketIndex:insert', $index->insert('K40', 'V40'));
//status_ok('HandlerSocketIndex:insert', $index->insert(array('K40', 'V40')));
//status_ok('HandlerSocketIndex:insert', $index->insert(array('K40', 'V40', 'F40')));

//Failure:
//status_ok('HandlerSocketIndex:insert', $index->insert()); //no arguments


//Update the data
//Success:
//$ret = $index->update('K40', array('K40', '^^40'));
//$ret = $index->update('K40', array('U' => array('K40', '^^40')));
$ret = $index->update('K40', array('U?' => array('K40', '^^40')));
//$ret = $index->update('K40', 'K40');

//Failure:
//$ret = $index->update(); //no arguments
//$ret = $index->update('K40'); //no arguments update
//$ret = $index->update('K40', array('u' => array('K40', 'V40'))); //operator Invalid

status_ok('HandlerSocketIndex:update', $ret);


//Delete data
//Success:
status_ok('HandlerSocketIndex:remove', $index->remove('K40'));
//status_ok('HandlerSocketIndex:remove', $index->remove(array('=' => 'K40')));
//status_ok('HandlerSocketIndex:remove', $index->remove(array('K40')));

//Failure:
//$ret = $index->remove(); //no arguments


//Perform multiple operations
// Success:
$ret = $index->multi(
    array(
        array('find', 'k0'),
        array('insert', array('k0', 'vv0')),
        array('find', array('k0')),
        array('update', array('=' => 'k0'), array('U' => array('k0', '^^0'))),
        array('find', array('=' => 'k0')),
        array('update', 'k0', array('k0', '0xx0')),
        array('find', 'k0'),
        array('update', 'k0', array('U?' => array('k0', '0---0'))),
        array('find', array('k0')),
        array('remove', array('=' => 'k0')),
        array('find', array('=' => 'k0')),
    ));

status_ok('HandlerSocketIndex:multi', $ret);
unset($ret);

//Failure:
//$ret = $index->multi(); //no arguments
//$ret = $index->multi(''); //argument empty
//$ret = $index->multi(null); //argument NULL
//$ret = $index->multi(array()); //an empty array
//$ret = $index->multi(array('insert', 'ke', 've')); //array Invalid
//$ret = $index->multi(array(array())); //empty array * 2
//$ret = $index->multi(array(array('insert'))); //array Invalid



echo PHP_EOL;
echo '__RESULT__', PHP_EOL;
var_dump(isset($ret) ? $ret : null);
echo '__ERROR__', PHP_EOL;
var_dump(isset($hs) ? $hs->getError() : null);
var_dump(isset($index) ? $index->getError() : null);
echo '__END__', PHP_EOL;
