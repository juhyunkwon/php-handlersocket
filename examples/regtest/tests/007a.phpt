--TEST--
nulls
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
    'k int PRIMARY KEY, ' .
    'v1 varchar(30), ' .
    'v2 varchar(30), ' .
    'key idxv1 (v1)) ' .
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
    $k = (string)$i;
    $v1 = '1v' . rand(0, 1000) . $i;
    $v2 = '2v' . rand(0, 1000) . $i;

    if ($i % 10 == 3)
    {
        $sql = sprintf(
            'INSERT INTO ' . $table . ' values (%d, NULL, \'%s\')',
            mysql_real_escape_string($k),
            mysql_real_escape_string($v2));
        $v1 = null;
    }
    else
    {
        $sql = sprintf(
            'INSERT INTO ' . $table . ' values (%d, \'%s\', \'%s\')',
            mysql_real_escape_string($k),
            mysql_real_escape_string($v1),
            mysql_real_escape_string($v2));
    }
    if (!mysql_query($sql, $mysql))
    {
        break;
    }

    $valmap[$k] = $v1;
}

echo 'MY', PHP_EOL;
$sql = 'SELECT k,v1,v2 FROM ' . $table . ' ORDER BY k';
$result = mysql_query($sql, $mysql);
if ($result)
{
    while ($row = mysql_fetch_assoc($result))
    {
        if ($row['v1'] == null)
        {
            $row['v1'] = '[null]';
        }

        echo $row['k'], ' ', $row['v1'], ' ', $row['v2'], PHP_EOL;
    }
}
mysql_free_result($result);


echo 'HS', PHP_EOL;
$hs = new HandlerSocket(MYSQL_HOST, MYSQL_HANDLERSOCKET_PORT);
if (!($hs->openIndex(1, MYSQL_DBNAME, $table, '', 'k,v1,v2')))
{
    die();
}

$retval = $hs->executeSingle(1, '>=', array(), 10000, 0);

for ($i = 0; $i < $tablesize; $i++)
{
    $k = $retval[$i][0];
    $v1 = $retval[$i][1];
    $v2 = $retval[$i][2];

    if ($v1 == null)
    {
        $v1 = '[null]';
    }

    echo $k, ' ', $v1, ' ', $v2, PHP_EOL;

}

echo '2ndIDX', PHP_EOL;
if (!($hs->openIndex(2, MYSQL_DBNAME, $table, 'idxv1', 'k,v1,v2')))
{
    die();
}

for ($i = 0; $i < $tablesize; $i++)
{
    $k = (string)$i;
    $v1 = $valmap[$k];

    if ($v1 == null)
    {
        continue;
    }

    $retval = $hs->executeSingle(2, '=', array($v1), 1, 0);

    $ret_k = $retval[0][0];
    $ret_v1 = $retval[0][1];
    $ret_v2 = $retval[0][2];

    echo '2ndidx ', $k, ' ', $v1, ' => ', $ret_k, ' ', $ret_v1, ' ', $ret_v2, PHP_EOL;
}


echo '2ndIDX NULL', PHP_EOL;

$retval = $hs->executeSingle(2, '=', array(null), 10000, 0);

$rvals = array();
$count = count($retval);

for ($i = 0; $i < $count; $i++)
{
    $k = $retval[$i][0];
    $v1 = $retval[$i][1];
    $v2 = $retval[$i][2];

    $rvals[$k] = array($k, $v1, $v2);
}

asort($rvals);

foreach ($rvals as $i => $val)
{
    echo '2ndidxnull ', $val[0], ' ', $val[2], PHP_EOL;
}


mysql_close($mysql);

--EXPECT--
MY
0 1v6080 2v2170
1 1v4711 2v691
2 1v1852 2v5922
3 [null] 2v6153
4 1v9314 2v2934
5 1v6785 2v7305
6 1v7166 2v9556
7 1v3707 2v4697
8 1v6798 2v8028
9 1v939 2v9909
10 1v44610 2v60110
11 1v2611 2v91911
12 1v94012 2v7312
13 [null] 2v44013
14 1v29314 2v27914
15 1v62415 2v60615
16 1v34816 2v47616
17 1v80117 2v35617
18 1v25618 2v93818
19 1v53419 2v76719
20 1v66020 2v24120
21 1v10721 2v1021
22 1v98322 2v53722
23 [null] 2v54023
24 1v30324 2v26624
25 1v77725 2v98925
26 1v32726 2v86726
27 1v22827 2v28327
28 1v95028 2v93428
29 1v48729 2v49529
30 1v91830 2v35530
31 1v38531 2v88231
32 1v7432 2v91832
33 [null] 2v67333
34 1v71534 2v87834
35 1v72635 2v6135
36 1v51536 2v336
37 1v71337 2v91437
38 1v57538 2v57938
39 1v62239 2v70539
40 1v3840 2v89340
41 1v73941 2v16741
42 1v81742 2v67642
43 [null] 2v16043
44 1v90244 2v2244
45 1v95345 2v25845
46 1v77746 2v27546
47 1v56947 2v55647
48 1v41448 2v37448
49 1v56249 2v19649
50 1v37750 2v18450
51 1v55151 2v11251
52 1v50152 2v28452
53 [null] 2v48953
54 1v68654 2v72654
55 1v7555 2v45155
56 1v26556 2v26256
57 1v32757 2v45457
58 1v48158 2v54758
59 1v7559 2v91459
60 1v54860 2v79860
61 1v61661 2v53361
62 1v81262 2v43462
63 [null] 2v87063
64 1v49764 2v17764
65 1v68965 2v60365
66 1v56066 2v12566
67 1v79567 2v3167
68 1v12168 2v29368
69 1v84569 2v28869
70 1v570 2v12270
71 1v12871 2v76671
72 1v86172 2v45872
73 [null] 2v27373
74 1v38774 2v50074
75 1v29175 2v40475
76 1v14376 2v21676
77 1v69977 2v61977
78 1v46378 2v72878
79 1v34079 2v55979
80 1v95280 2v16080
81 1v27581 2v6781
82 1v7982 2v47182
83 [null] 2v43483
84 1v38084 2v73684
85 1v81785 2v36185
86 1v72686 2v41186
87 1v44087 2v13887
88 1v20688 2v6188
89 1v27689 2v55289
90 1v10090 2v5590
91 1v91691 2v99791
92 1v44292 2v73592
93 [null] 2v31693
94 1v67594 2v54994
95 1v24795 2v86395
96 1v90696 2v65496
97 1v42197 2v26597
98 1v25398 2v49098
99 1v17799 2v43399
HS
0 1v6080 2v2170
1 1v4711 2v691
2 1v1852 2v5922
3 [null] 2v6153
4 1v9314 2v2934
5 1v6785 2v7305
6 1v7166 2v9556
7 1v3707 2v4697
8 1v6798 2v8028
9 1v939 2v9909
10 1v44610 2v60110
11 1v2611 2v91911
12 1v94012 2v7312
13 [null] 2v44013
14 1v29314 2v27914
15 1v62415 2v60615
16 1v34816 2v47616
17 1v80117 2v35617
18 1v25618 2v93818
19 1v53419 2v76719
20 1v66020 2v24120
21 1v10721 2v1021
22 1v98322 2v53722
23 [null] 2v54023
24 1v30324 2v26624
25 1v77725 2v98925
26 1v32726 2v86726
27 1v22827 2v28327
28 1v95028 2v93428
29 1v48729 2v49529
30 1v91830 2v35530
31 1v38531 2v88231
32 1v7432 2v91832
33 [null] 2v67333
34 1v71534 2v87834
35 1v72635 2v6135
36 1v51536 2v336
37 1v71337 2v91437
38 1v57538 2v57938
39 1v62239 2v70539
40 1v3840 2v89340
41 1v73941 2v16741
42 1v81742 2v67642
43 [null] 2v16043
44 1v90244 2v2244
45 1v95345 2v25845
46 1v77746 2v27546
47 1v56947 2v55647
48 1v41448 2v37448
49 1v56249 2v19649
50 1v37750 2v18450
51 1v55151 2v11251
52 1v50152 2v28452
53 [null] 2v48953
54 1v68654 2v72654
55 1v7555 2v45155
56 1v26556 2v26256
57 1v32757 2v45457
58 1v48158 2v54758
59 1v7559 2v91459
60 1v54860 2v79860
61 1v61661 2v53361
62 1v81262 2v43462
63 [null] 2v87063
64 1v49764 2v17764
65 1v68965 2v60365
66 1v56066 2v12566
67 1v79567 2v3167
68 1v12168 2v29368
69 1v84569 2v28869
70 1v570 2v12270
71 1v12871 2v76671
72 1v86172 2v45872
73 [null] 2v27373
74 1v38774 2v50074
75 1v29175 2v40475
76 1v14376 2v21676
77 1v69977 2v61977
78 1v46378 2v72878
79 1v34079 2v55979
80 1v95280 2v16080
81 1v27581 2v6781
82 1v7982 2v47182
83 [null] 2v43483
84 1v38084 2v73684
85 1v81785 2v36185
86 1v72686 2v41186
87 1v44087 2v13887
88 1v20688 2v6188
89 1v27689 2v55289
90 1v10090 2v5590
91 1v91691 2v99791
92 1v44292 2v73592
93 [null] 2v31693
94 1v67594 2v54994
95 1v24795 2v86395
96 1v90696 2v65496
97 1v42197 2v26597
98 1v25398 2v49098
99 1v17799 2v43399
2ndIDX
2ndidx 0 1v6080 => 0 1v6080 2v2170
2ndidx 1 1v4711 => 1 1v4711 2v691
2ndidx 2 1v1852 => 2 1v1852 2v5922
2ndidx 4 1v9314 => 4 1v9314 2v2934
2ndidx 5 1v6785 => 5 1v6785 2v7305
2ndidx 6 1v7166 => 6 1v7166 2v9556
2ndidx 7 1v3707 => 7 1v3707 2v4697
2ndidx 8 1v6798 => 8 1v6798 2v8028
2ndidx 9 1v939 => 9 1v939 2v9909
2ndidx 10 1v44610 => 10 1v44610 2v60110
2ndidx 11 1v2611 => 11 1v2611 2v91911
2ndidx 12 1v94012 => 12 1v94012 2v7312
2ndidx 14 1v29314 => 14 1v29314 2v27914
2ndidx 15 1v62415 => 15 1v62415 2v60615
2ndidx 16 1v34816 => 16 1v34816 2v47616
2ndidx 17 1v80117 => 17 1v80117 2v35617
2ndidx 18 1v25618 => 18 1v25618 2v93818
2ndidx 19 1v53419 => 19 1v53419 2v76719
2ndidx 20 1v66020 => 20 1v66020 2v24120
2ndidx 21 1v10721 => 21 1v10721 2v1021
2ndidx 22 1v98322 => 22 1v98322 2v53722
2ndidx 24 1v30324 => 24 1v30324 2v26624
2ndidx 25 1v77725 => 25 1v77725 2v98925
2ndidx 26 1v32726 => 26 1v32726 2v86726
2ndidx 27 1v22827 => 27 1v22827 2v28327
2ndidx 28 1v95028 => 28 1v95028 2v93428
2ndidx 29 1v48729 => 29 1v48729 2v49529
2ndidx 30 1v91830 => 30 1v91830 2v35530
2ndidx 31 1v38531 => 31 1v38531 2v88231
2ndidx 32 1v7432 => 32 1v7432 2v91832
2ndidx 34 1v71534 => 34 1v71534 2v87834
2ndidx 35 1v72635 => 35 1v72635 2v6135
2ndidx 36 1v51536 => 36 1v51536 2v336
2ndidx 37 1v71337 => 37 1v71337 2v91437
2ndidx 38 1v57538 => 38 1v57538 2v57938
2ndidx 39 1v62239 => 39 1v62239 2v70539
2ndidx 40 1v3840 => 40 1v3840 2v89340
2ndidx 41 1v73941 => 41 1v73941 2v16741
2ndidx 42 1v81742 => 42 1v81742 2v67642
2ndidx 44 1v90244 => 44 1v90244 2v2244
2ndidx 45 1v95345 => 45 1v95345 2v25845
2ndidx 46 1v77746 => 46 1v77746 2v27546
2ndidx 47 1v56947 => 47 1v56947 2v55647
2ndidx 48 1v41448 => 48 1v41448 2v37448
2ndidx 49 1v56249 => 49 1v56249 2v19649
2ndidx 50 1v37750 => 50 1v37750 2v18450
2ndidx 51 1v55151 => 51 1v55151 2v11251
2ndidx 52 1v50152 => 52 1v50152 2v28452
2ndidx 54 1v68654 => 54 1v68654 2v72654
2ndidx 55 1v7555 => 55 1v7555 2v45155
2ndidx 56 1v26556 => 56 1v26556 2v26256
2ndidx 57 1v32757 => 57 1v32757 2v45457
2ndidx 58 1v48158 => 58 1v48158 2v54758
2ndidx 59 1v7559 => 59 1v7559 2v91459
2ndidx 60 1v54860 => 60 1v54860 2v79860
2ndidx 61 1v61661 => 61 1v61661 2v53361
2ndidx 62 1v81262 => 62 1v81262 2v43462
2ndidx 64 1v49764 => 64 1v49764 2v17764
2ndidx 65 1v68965 => 65 1v68965 2v60365
2ndidx 66 1v56066 => 66 1v56066 2v12566
2ndidx 67 1v79567 => 67 1v79567 2v3167
2ndidx 68 1v12168 => 68 1v12168 2v29368
2ndidx 69 1v84569 => 69 1v84569 2v28869
2ndidx 70 1v570 => 70 1v570 2v12270
2ndidx 71 1v12871 => 71 1v12871 2v76671
2ndidx 72 1v86172 => 72 1v86172 2v45872
2ndidx 74 1v38774 => 74 1v38774 2v50074
2ndidx 75 1v29175 => 75 1v29175 2v40475
2ndidx 76 1v14376 => 76 1v14376 2v21676
2ndidx 77 1v69977 => 77 1v69977 2v61977
2ndidx 78 1v46378 => 78 1v46378 2v72878
2ndidx 79 1v34079 => 79 1v34079 2v55979
2ndidx 80 1v95280 => 80 1v95280 2v16080
2ndidx 81 1v27581 => 81 1v27581 2v6781
2ndidx 82 1v7982 => 82 1v7982 2v47182
2ndidx 84 1v38084 => 84 1v38084 2v73684
2ndidx 85 1v81785 => 85 1v81785 2v36185
2ndidx 86 1v72686 => 86 1v72686 2v41186
2ndidx 87 1v44087 => 87 1v44087 2v13887
2ndidx 88 1v20688 => 88 1v20688 2v6188
2ndidx 89 1v27689 => 89 1v27689 2v55289
2ndidx 90 1v10090 => 90 1v10090 2v5590
2ndidx 91 1v91691 => 91 1v91691 2v99791
2ndidx 92 1v44292 => 92 1v44292 2v73592
2ndidx 94 1v67594 => 94 1v67594 2v54994
2ndidx 95 1v24795 => 95 1v24795 2v86395
2ndidx 96 1v90696 => 96 1v90696 2v65496
2ndidx 97 1v42197 => 97 1v42197 2v26597
2ndidx 98 1v25398 => 98 1v25398 2v49098
2ndidx 99 1v17799 => 99 1v17799 2v43399
2ndIDX NULL
2ndidxnull 3 2v6153
2ndidxnull 13 2v44013
2ndidxnull 23 2v54023
2ndidxnull 33 2v67333
2ndidxnull 43 2v16043
2ndidxnull 53 2v48953
2ndidxnull 63 2v87063
2ndidxnull 73 2v27373
2ndidxnull 83 2v43483
2ndidxnull 93 2v31693
