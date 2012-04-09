--TEST--
HandlerSocketIndex: some trailing bytes were dropped for varlen or nullable key fields
--SKIPIF--
--FILE--
<?php
require_once dirname(__FILE__) . '/../common/config.php';

$mysql = get_mysql_connection();

init_mysql_testdb($mysql);

$table = 'hstesttbl';
$tablesize = 50;
$sql = sprintf(
    'CREATE TABLE %s ( ' .
    'k int PRIMARY KEY, ' .
    'v1 varchar(30), ' .
    'v2 varchar(30), ' .
    'index i1(v1), index i2(v2, v1)) ' .
    'Engine = myisam default charset = binary',
    mysql_real_escape_string($table));
if (!mysql_query($sql, $mysql))
{
    die(mysql_error());
}

$valmap = array();

for ($i = 0; $i < $tablesize; $i++)
{
    $k = $i;
    $s1 = '';
    $s2 = '';

    for ($j = 0; $j < $i; $j++)
    {
        $s1 .= chr(48 + $j % 10);
        $s2 .= chr(65 + $j % 10);
    }

    $v1 = $s1;
    $v2 = $s2;

    $sql = sprintf(
        'INSERT INTO ' . $table . ' VALUES (\'%s\', \'%s\', \'%s\')',
        mysql_real_escape_string($k),
        mysql_real_escape_string($v1),
        mysql_real_escape_string($v2));
    if (!mysql_query($sql, $mysql))
    {
        break;
    }

    $valmap[$k] = array($v1, $v2);
}

dump_table($mysql, $table);

try
{
    $hs = new HandlerSocket(MYSQL_HOST, MYSQL_HANDLERSOCKET_PORT_WR);
    $index1 = $hs->createIndex(1, MYSQL_DBNAME, $table, '', 'k,v1,v2');
    $index2 = $hs->createIndex(2, MYSQL_DBNAME, $table, 'i1', 'k,v1,v2');
    $index3 = $hs->createIndex(3, MYSQL_DBNAME, $table, 'i2', 'k,v1,v2');
}
catch (HandlerSocketException $exception)
{
    echo $exception->getMessage(), PHP_EOL;
    die();
}

for ($i = 0; $i <= 30; $i++)
{
    if (isset($valmap[$i][0]))
    {
        $v1 = $valmap[$i][0];
    }
    else
    {
        $v1 = '';
    }

    if (isset($valmap[$i][1]))
    {
        $v2 = $valmap[$i][1];
    }
    else
    {
        $v2 = '';
    }

    $retval = $index1->find($i, 1, 0);
    if ($retval)
    {
        $retval = array_shift($retval);

        echo 'PK';
        for ($j = 0; $j < 3; $j++)
        {
            if (isset($retval[$j]))
            {
                echo ' ', $retval[$j];
            }
        }
        echo PHP_EOL;
    }
    else
    {
        echo $index1->getError(), PHP_EOL;
    }

    $retval = $index2->find($v1, 1, 0);
    if ($retval)
    {
        $retval = array_shift($retval);

        echo 'I1';
        for ($j = 0; $j < 3; $j++)
        {
            if (isset($retval[$j]))
            {
                echo ' ', $retval[$j];
            }
        }
        echo PHP_EOL;
    }
    else
    {
        echo $index2->getError(), PHP_EOL;
    }

    $retval = $index3->find(array($v2, $v1), 1, 0);
    if ($retval)
    {
        $retval = array_shift($retval);

        echo 'I2';
        for ($j = 0; $j < 3; $j++)
        {
            if (isset($retval[$j]))
            {
                echo ' ', $retval[$j];
            }
        }
        echo PHP_EOL;
    }
    else
    {
        echo $index3->getError(), PHP_EOL;
    }
}

function dump_table($mysql, $table)
{
    echo 'DUMP_TABLE', PHP_EOL;
    $sql = 'SELECT k,v1,v2 FROM ' . $table . ' ORDER BY k';
    $result = mysql_query($sql, $mysql);
    if ($result)
    {
        while ($row = mysql_fetch_assoc($result))
        {
            echo $row['k'], ' ', $row['v1'], ' ', $row['v2'], PHP_EOL;
        }
    }
    mysql_free_result($result);
}

mysql_close($mysql);

--EXPECT--
DUMP_TABLE
0  
1 0 A
2 01 AB
3 012 ABC
4 0123 ABCD
5 01234 ABCDE
6 012345 ABCDEF
7 0123456 ABCDEFG
8 01234567 ABCDEFGH
9 012345678 ABCDEFGHI
10 0123456789 ABCDEFGHIJ
11 01234567890 ABCDEFGHIJA
12 012345678901 ABCDEFGHIJAB
13 0123456789012 ABCDEFGHIJABC
14 01234567890123 ABCDEFGHIJABCD
15 012345678901234 ABCDEFGHIJABCDE
16 0123456789012345 ABCDEFGHIJABCDEF
17 01234567890123456 ABCDEFGHIJABCDEFG
18 012345678901234567 ABCDEFGHIJABCDEFGH
19 0123456789012345678 ABCDEFGHIJABCDEFGHI
20 01234567890123456789 ABCDEFGHIJABCDEFGHIJ
21 012345678901234567890 ABCDEFGHIJABCDEFGHIJA
22 0123456789012345678901 ABCDEFGHIJABCDEFGHIJAB
23 01234567890123456789012 ABCDEFGHIJABCDEFGHIJABC
24 012345678901234567890123 ABCDEFGHIJABCDEFGHIJABCD
25 0123456789012345678901234 ABCDEFGHIJABCDEFGHIJABCDE
26 01234567890123456789012345 ABCDEFGHIJABCDEFGHIJABCDEF
27 012345678901234567890123456 ABCDEFGHIJABCDEFGHIJABCDEFG
28 0123456789012345678901234567 ABCDEFGHIJABCDEFGHIJABCDEFGH
29 01234567890123456789012345678 ABCDEFGHIJABCDEFGHIJABCDEFGHI
30 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
31 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
32 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
33 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
34 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
35 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
36 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
37 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
38 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
39 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
40 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
41 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
42 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
43 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
44 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
45 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
46 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
47 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
48 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
49 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
PK 0  
I1 0  
I2 0  
PK 1 0 A
I1 1 0 A
I2 1 0 A
PK 2 01 AB
I1 2 01 AB
I2 2 01 AB
PK 3 012 ABC
I1 3 012 ABC
I2 3 012 ABC
PK 4 0123 ABCD
I1 4 0123 ABCD
I2 4 0123 ABCD
PK 5 01234 ABCDE
I1 5 01234 ABCDE
I2 5 01234 ABCDE
PK 6 012345 ABCDEF
I1 6 012345 ABCDEF
I2 6 012345 ABCDEF
PK 7 0123456 ABCDEFG
I1 7 0123456 ABCDEFG
I2 7 0123456 ABCDEFG
PK 8 01234567 ABCDEFGH
I1 8 01234567 ABCDEFGH
I2 8 01234567 ABCDEFGH
PK 9 012345678 ABCDEFGHI
I1 9 012345678 ABCDEFGHI
I2 9 012345678 ABCDEFGHI
PK 10 0123456789 ABCDEFGHIJ
I1 10 0123456789 ABCDEFGHIJ
I2 10 0123456789 ABCDEFGHIJ
PK 11 01234567890 ABCDEFGHIJA
I1 11 01234567890 ABCDEFGHIJA
I2 11 01234567890 ABCDEFGHIJA
PK 12 012345678901 ABCDEFGHIJAB
I1 12 012345678901 ABCDEFGHIJAB
I2 12 012345678901 ABCDEFGHIJAB
PK 13 0123456789012 ABCDEFGHIJABC
I1 13 0123456789012 ABCDEFGHIJABC
I2 13 0123456789012 ABCDEFGHIJABC
PK 14 01234567890123 ABCDEFGHIJABCD
I1 14 01234567890123 ABCDEFGHIJABCD
I2 14 01234567890123 ABCDEFGHIJABCD
PK 15 012345678901234 ABCDEFGHIJABCDE
I1 15 012345678901234 ABCDEFGHIJABCDE
I2 15 012345678901234 ABCDEFGHIJABCDE
PK 16 0123456789012345 ABCDEFGHIJABCDEF
I1 16 0123456789012345 ABCDEFGHIJABCDEF
I2 16 0123456789012345 ABCDEFGHIJABCDEF
PK 17 01234567890123456 ABCDEFGHIJABCDEFG
I1 17 01234567890123456 ABCDEFGHIJABCDEFG
I2 17 01234567890123456 ABCDEFGHIJABCDEFG
PK 18 012345678901234567 ABCDEFGHIJABCDEFGH
I1 18 012345678901234567 ABCDEFGHIJABCDEFGH
I2 18 012345678901234567 ABCDEFGHIJABCDEFGH
PK 19 0123456789012345678 ABCDEFGHIJABCDEFGHI
I1 19 0123456789012345678 ABCDEFGHIJABCDEFGHI
I2 19 0123456789012345678 ABCDEFGHIJABCDEFGHI
PK 20 01234567890123456789 ABCDEFGHIJABCDEFGHIJ
I1 20 01234567890123456789 ABCDEFGHIJABCDEFGHIJ
I2 20 01234567890123456789 ABCDEFGHIJABCDEFGHIJ
PK 21 012345678901234567890 ABCDEFGHIJABCDEFGHIJA
I1 21 012345678901234567890 ABCDEFGHIJABCDEFGHIJA
I2 21 012345678901234567890 ABCDEFGHIJABCDEFGHIJA
PK 22 0123456789012345678901 ABCDEFGHIJABCDEFGHIJAB
I1 22 0123456789012345678901 ABCDEFGHIJABCDEFGHIJAB
I2 22 0123456789012345678901 ABCDEFGHIJABCDEFGHIJAB
PK 23 01234567890123456789012 ABCDEFGHIJABCDEFGHIJABC
I1 23 01234567890123456789012 ABCDEFGHIJABCDEFGHIJABC
I2 23 01234567890123456789012 ABCDEFGHIJABCDEFGHIJABC
PK 24 012345678901234567890123 ABCDEFGHIJABCDEFGHIJABCD
I1 24 012345678901234567890123 ABCDEFGHIJABCDEFGHIJABCD
I2 24 012345678901234567890123 ABCDEFGHIJABCDEFGHIJABCD
PK 25 0123456789012345678901234 ABCDEFGHIJABCDEFGHIJABCDE
I1 25 0123456789012345678901234 ABCDEFGHIJABCDEFGHIJABCDE
I2 25 0123456789012345678901234 ABCDEFGHIJABCDEFGHIJABCDE
PK 26 01234567890123456789012345 ABCDEFGHIJABCDEFGHIJABCDEF
I1 26 01234567890123456789012345 ABCDEFGHIJABCDEFGHIJABCDEF
I2 26 01234567890123456789012345 ABCDEFGHIJABCDEFGHIJABCDEF
PK 27 012345678901234567890123456 ABCDEFGHIJABCDEFGHIJABCDEFG
I1 27 012345678901234567890123456 ABCDEFGHIJABCDEFGHIJABCDEFG
I2 27 012345678901234567890123456 ABCDEFGHIJABCDEFGHIJABCDEFG
PK 28 0123456789012345678901234567 ABCDEFGHIJABCDEFGHIJABCDEFGH
I1 28 0123456789012345678901234567 ABCDEFGHIJABCDEFGHIJABCDEFGH
I2 28 0123456789012345678901234567 ABCDEFGHIJABCDEFGHIJABCDEFGH
PK 29 01234567890123456789012345678 ABCDEFGHIJABCDEFGHIJABCDEFGHI
I1 29 01234567890123456789012345678 ABCDEFGHIJABCDEFGHIJABCDEFGHI
I2 29 01234567890123456789012345678 ABCDEFGHIJABCDEFGHIJABCDEFGHI
PK 30 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
I1 30 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
I2 30 012345678901234567890123456789 ABCDEFGHIJABCDEFGHIJABCDEFGHIJ
