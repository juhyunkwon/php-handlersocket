--TEST--
libmysql
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
    'CREATE TABLE %s (k varchar(30) primary key, v varchar(30) not null) ' .
    'Engine = innodb', mysql_real_escape_string($table));
if (!mysql_query($sql, $mysql))
{
    die(mysql_error());
}

srand(999);

$valmap = array();

for ($i = 0; $i < $tablesize; ++$i)
{
    $k = 'k' . $i;
    $v = 'v' . (int)rand(0, 1000) . $i;

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

$sql = 'SELECT k,v FROM ' . $table . ' ORDER BY k';
$result = mysql_query($sql, $mysql);
if ($result)
{
    while ($row = mysql_fetch_assoc($result))
    {
        echo $row['k'], ' ', $row['v'], PHP_EOL;
    }
    mysql_free_result($result);
}

mysql_close($mysql);

--EXPECT--
k0 v6080
k1 v2171
k10 v67810
k11 v73011
k12 v71612
k13 v95513
k14 v37014
k15 v46915
k16 v67916
k17 v80217
k18 v9318
k19 v99019
k2 v4712
k20 v44620
k21 v60121
k22 v2622
k23 v91923
k24 v94024
k25 v7325
k26 v49826
k27 v44027
k28 v29328
k29 v27929
k3 v693
k30 v62430
k31 v60631
k32 v34832
k33 v47633
k34 v80134
k35 v35635
k36 v25636
k37 v93837
k38 v53438
k39 v76739
k4 v1854
k40 v66040
k41 v24141
k42 v10742
k43 v1043
k44 v98344
k45 v53745
k46 v60646
k47 v54047
k48 v30348
k49 v26649
k5 v5925
k50 v77750
k51 v98951
k52 v32752
k53 v86753
k54 v22854
k55 v28355
k56 v95056
k57 v93457
k58 v48758
k59 v49559
k6 v6896
k60 v91860
k61 v35561
k62 v38562
k63 v88263
k64 v7464
k65 v91865
k66 v29866
k67 v67367
k68 v71568
k69 v87869
k7 v6157
k70 v72670
k71 v6171
k72 v51572
k73 v373
k74 v71374
k75 v91475
k76 v57576
k77 v57977
k78 v62278
k79 v70579
k8 v9318
k80 v3880
k81 v89381
k82 v73982
k83 v16783
k84 v81784
k85 v67685
k86 v23386
k87 v16087
k88 v90288
k89 v2289
k9 v2939
k90 v95390
k91 v25891
k92 v77792
k93 v27593
k94 v56994
k95 v55695
k96 v41496
k97 v37497
k98 v56298
k99 v19699
