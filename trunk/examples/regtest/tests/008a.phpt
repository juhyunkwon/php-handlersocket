--TEST--
not-found
--SKIPIF--
<?php
ob_start();
phpinfo();
$str = ob_get_clean();
$array = explode("\n", $str);
$zts = false;
foreach ($array as $key => $val)
{
    if (strstr($val, 'Thread Safety') != false)
    {
        $retval = explode(' ', $val);
        if (strcmp($retval[3], 'enabled') == 0)
        {
            $zts = true;
        }
    }
}
if (!$zts)
{
    echo 'skip tests in Thread Safety enabled';
}
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


$hs = new HandlerSocket(MYSQL_HOST, MYSQL_HANDLERSOCKET_PORT);
if (!($hs->openIndex(1, MYSQL_DBNAME, $table, '', 'k,v')))
{
    die();
}

//found
$retval = $hs->executeSingle(1, '=', array('k5'), 1, 0);
var_dump($retval);

//not found
$retval = $hs->executeSingle(1, '=', array('k000000'), 1, 0);
var_dump($retval);


mysql_close($mysql);

--EXPECT--
array(1) {
  [0]=>
  array(2) {
    [0]=>
    string(2) "k5"
    [1]=>
    string(5) "v5925"
  }
}
array(0) {
}
