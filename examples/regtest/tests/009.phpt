--TEST--
multiple modify requests
--SKIPIF--
--FILE--
<?php
require_once dirname(__FILE__) . '/../common/config.php';

$mysql = get_mysql_connection();

init_mysql_testdb($mysql);

$table = 'hstesttbl';
$tablesize = 100;
$sql = sprintf(
    'CREATE TABLE %s ( ' .
    'k varchar(30) PRIMARY KEY, ' .
    'v varchar(30) NOT NULL) ' .
    'Engine = innodb',
    mysql_real_escape_string($table));
if (!mysql_query($sql, $mysql))
{
    die(mysql_error());
}

srand(999);

$valmap = array();

for ($i = 0; $i < $tablesize; $i++)
{
    $k = 'k' . $i;
    $v = 'v' . rand(0, 1000) . $i;

    $sql = sprintf(
        'INSERT INTO ' . $table . ' values (\'%s\', \'%s\')',
        mysql_real_escape_string($k),
        mysql_real_escape_string($v));
    if (!mysql_query($sql, $mysql))
    {
        break;
    }

    $valmap[$k] = $v;
}


$hs = new HandlerSocket(MYSQL_HOST, MYSQL_HANDLERSOCKET_PORT_WR);
if (!($hs->openIndex(1, MYSQL_DBNAME, $table, '', 'k,v')))
{
    die();
}


echo 'DEL', PHP_EOL;
$retval = $hs->executeMulti(
    array(
        array(1, '=', array('k5'), 1, 0, 'D'),
        array(1, '>=', array('k5'), 2, 0)
        ));
var_dump($retval);

echo 'DELINS', PHP_EOL;
$retval = $hs->executeMulti(
    array(
        array(1, '>=', array('k6'), 3, 0),
        array(1, '=', array('k60'), 1, 0, 'D'),
        array(1, '+', array('k60', 'INS')),
        array(1, '>=', array('k6'), 3, 0)
        ));
var_dump($retval);


echo 'DELUPUP', PHP_EOL;
$retval = $hs->executeMulti(
    array(
        array(1, '>=', array('k7'), 3, 0),
        array(1, '=', array('k70'), 1, 0, 'U', array('k70', 'UP')),
        array(1, '>=', array('k7'), 3, 0)
        ));
var_dump($retval);

mysql_close($mysql);

--EXPECT--
DEL
array(2) {
  [0]=>
  array(1) {
    [0]=>
    array(1) {
      [0]=>
      string(1) "1"
    }
  }
  [1]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      string(3) "k50"
      [1]=>
      string(5) "v8750"
    }
    [1]=>
    array(2) {
      [0]=>
      string(3) "k51"
      [1]=>
      string(6) "v12751"
    }
  }
}
DELINS
array(4) {
  [0]=>
  array(3) {
    [0]=>
    array(2) {
      [0]=>
      string(2) "k6"
      [1]=>
      string(5) "v6336"
    }
    [1]=>
    array(2) {
      [0]=>
      string(3) "k60"
      [1]=>
      string(6) "v34960"
    }
    [2]=>
    array(2) {
      [0]=>
      string(3) "k61"
      [1]=>
      string(6) "v81361"
    }
  }
  [1]=>
  array(1) {
    [0]=>
    array(1) {
      [0]=>
      string(1) "1"
    }
  }
  [2]=>
  array(0) {
  }
  [3]=>
  array(3) {
    [0]=>
    array(2) {
      [0]=>
      string(2) "k6"
      [1]=>
      string(5) "v6336"
    }
    [1]=>
    array(2) {
      [0]=>
      string(3) "k60"
      [1]=>
      string(3) "INS"
    }
    [2]=>
    array(2) {
      [0]=>
      string(3) "k61"
      [1]=>
      string(6) "v81361"
    }
  }
}
DELUPUP
array(3) {
  [0]=>
  array(3) {
    [0]=>
    array(2) {
      [0]=>
      string(2) "k7"
      [1]=>
      string(5) "v7037"
    }
    [1]=>
    array(2) {
      [0]=>
      string(3) "k70"
      [1]=>
      string(6) "v65570"
    }
    [2]=>
    array(2) {
      [0]=>
      string(3) "k71"
      [1]=>
      string(6) "v98371"
    }
  }
  [1]=>
  array(1) {
    [0]=>
    array(1) {
      [0]=>
      string(1) "1"
    }
  }
  [2]=>
  array(3) {
    [0]=>
    array(2) {
      [0]=>
      string(2) "k7"
      [1]=>
      string(5) "v7037"
    }
    [1]=>
    array(2) {
      [0]=>
      string(3) "k70"
      [1]=>
      string(2) "UP"
    }
    [2]=>
    array(2) {
      [0]=>
      string(3) "k71"
      [1]=>
      string(6) "v98371"
    }
  }
}
