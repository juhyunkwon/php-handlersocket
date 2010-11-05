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
if ($zts)
{
    echo 'skip tests in Thread Safety disabled';
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
0 1v20 2v6440
1 1v8251 2v2651
2 1v722 2v792
3 [null] 2v7033
4 1v1614 2v574
5 1v9295 2v2115
6 1v3776 2v1926
7 1v907 2v1457
8 1v4018 2v4808
9 1v5359 2v6669
10 1v26410 2v97210
11 1v11311 2v72111
12 1v44312 2v84412
13 [null] 2v40313
14 1v96214 2v48014
15 1v1615 2v96515
16 1v12316 2v84116
17 1v22917 2v19617
18 1v92018 2v86318
19 1v90019 2v8119
20 1v92120 2v82820
21 1v29321 2v29721
22 1v2022 2v38322
23 [null] 2v42123
24 1v86324 2v97924
25 1v8725 2v12725
26 1v95126 2v20126
27 1v84927 2v39327
28 1v4428 2v87028
29 1v79629 2v529
30 1v34930 2v81330
31 1v97031 2v47331
32 1v65432 2v19932
33 [null] 2v57333
34 1v6234 2v56934
35 1v65535 2v98335
36 1v39736 2v94836
37 1v28037 2v41737
38 1v33038 2v72338
39 1v83939 2v19339
40 1v70140 2v92640
41 1v32041 2v65241
42 1v12642 2v16842
43 [null] 2v17043
44 1v3844 2v84044
45 1v17645 2v38845
46 1v65346 2v14546
47 1v86147 2v30647
48 1v34548 2v53148
49 1v88049 2v40749
50 1v10050 2v53450
51 1v38951 2v49851
52 1v48252 2v66952
53 [null] 2v81253
54 1v39254 2v75454
55 1v455 2v9355
56 1v67956 2v32556
57 1v74557 2v80657
58 1v49458 2v78958
59 1v97759 2v53259
60 1v62960 2v15260
61 1v92161 2v28161
62 1v29862 2v78262
63 [null] 2v64363
64 1v31264 2v46764
65 1v4965 2v41265
66 1v166 2v43966
67 1v91067 2v48367
68 1v10868 2v82568
69 1v29469 2v50069
70 1v57970 2v29970
71 1v59371 2v25871
72 1v62472 2v33772
73 [null] 2v11873
74 1v12674 2v3974
75 1v65075 2v75575
76 1v19276 2v57076
77 1v3677 2v49077
78 1v35178 2v62478
79 1v13379 2v66479
80 1v9080 2v18380
81 1v7681 2v9181
82 1v62282 2v98682
83 [null] 2v73083
84 1v81184 2v86984
85 1v22985 2v39085
86 1v16786 2v82386
87 1v64887 2v79287
88 1v16088 2v71188
89 1v91089 2v28689
90 1v75190 2v56090
91 1v4191 2v94391
92 1v13092 2v7792
93 [null] 2v48293
94 1v70194 2v56694
95 1v14595 2v79195
96 1v74996 2v22196
97 1v88397 2v37097
98 1v20798 2v45798
99 1v10099 2v1899
HS
0 1v20 2v6440
1 1v8251 2v2651
2 1v722 2v792
3 [null] 2v7033
4 1v1614 2v574
5 1v9295 2v2115
6 1v3776 2v1926
7 1v907 2v1457
8 1v4018 2v4808
9 1v5359 2v6669
10 1v26410 2v97210
11 1v11311 2v72111
12 1v44312 2v84412
13 [null] 2v40313
14 1v96214 2v48014
15 1v1615 2v96515
16 1v12316 2v84116
17 1v22917 2v19617
18 1v92018 2v86318
19 1v90019 2v8119
20 1v92120 2v82820
21 1v29321 2v29721
22 1v2022 2v38322
23 [null] 2v42123
24 1v86324 2v97924
25 1v8725 2v12725
26 1v95126 2v20126
27 1v84927 2v39327
28 1v4428 2v87028
29 1v79629 2v529
30 1v34930 2v81330
31 1v97031 2v47331
32 1v65432 2v19932
33 [null] 2v57333
34 1v6234 2v56934
35 1v65535 2v98335
36 1v39736 2v94836
37 1v28037 2v41737
38 1v33038 2v72338
39 1v83939 2v19339
40 1v70140 2v92640
41 1v32041 2v65241
42 1v12642 2v16842
43 [null] 2v17043
44 1v3844 2v84044
45 1v17645 2v38845
46 1v65346 2v14546
47 1v86147 2v30647
48 1v34548 2v53148
49 1v88049 2v40749
50 1v10050 2v53450
51 1v38951 2v49851
52 1v48252 2v66952
53 [null] 2v81253
54 1v39254 2v75454
55 1v455 2v9355
56 1v67956 2v32556
57 1v74557 2v80657
58 1v49458 2v78958
59 1v97759 2v53259
60 1v62960 2v15260
61 1v92161 2v28161
62 1v29862 2v78262
63 [null] 2v64363
64 1v31264 2v46764
65 1v4965 2v41265
66 1v166 2v43966
67 1v91067 2v48367
68 1v10868 2v82568
69 1v29469 2v50069
70 1v57970 2v29970
71 1v59371 2v25871
72 1v62472 2v33772
73 [null] 2v11873
74 1v12674 2v3974
75 1v65075 2v75575
76 1v19276 2v57076
77 1v3677 2v49077
78 1v35178 2v62478
79 1v13379 2v66479
80 1v9080 2v18380
81 1v7681 2v9181
82 1v62282 2v98682
83 [null] 2v73083
84 1v81184 2v86984
85 1v22985 2v39085
86 1v16786 2v82386
87 1v64887 2v79287
88 1v16088 2v71188
89 1v91089 2v28689
90 1v75190 2v56090
91 1v4191 2v94391
92 1v13092 2v7792
93 [null] 2v48293
94 1v70194 2v56694
95 1v14595 2v79195
96 1v74996 2v22196
97 1v88397 2v37097
98 1v20798 2v45798
99 1v10099 2v1899
2ndIDX
2ndidx 0 1v20 => 0 1v20 2v6440
2ndidx 1 1v8251 => 1 1v8251 2v2651
2ndidx 2 1v722 => 2 1v722 2v792
2ndidx 4 1v1614 => 4 1v1614 2v574
2ndidx 5 1v9295 => 5 1v9295 2v2115
2ndidx 6 1v3776 => 6 1v3776 2v1926
2ndidx 7 1v907 => 7 1v907 2v1457
2ndidx 8 1v4018 => 8 1v4018 2v4808
2ndidx 9 1v5359 => 9 1v5359 2v6669
2ndidx 10 1v26410 => 10 1v26410 2v97210
2ndidx 11 1v11311 => 11 1v11311 2v72111
2ndidx 12 1v44312 => 12 1v44312 2v84412
2ndidx 14 1v96214 => 14 1v96214 2v48014
2ndidx 15 1v1615 => 15 1v1615 2v96515
2ndidx 16 1v12316 => 16 1v12316 2v84116
2ndidx 17 1v22917 => 17 1v22917 2v19617
2ndidx 18 1v92018 => 18 1v92018 2v86318
2ndidx 19 1v90019 => 19 1v90019 2v8119
2ndidx 20 1v92120 => 20 1v92120 2v82820
2ndidx 21 1v29321 => 21 1v29321 2v29721
2ndidx 22 1v2022 => 22 1v2022 2v38322
2ndidx 24 1v86324 => 24 1v86324 2v97924
2ndidx 25 1v8725 => 25 1v8725 2v12725
2ndidx 26 1v95126 => 26 1v95126 2v20126
2ndidx 27 1v84927 => 27 1v84927 2v39327
2ndidx 28 1v4428 => 28 1v4428 2v87028
2ndidx 29 1v79629 => 29 1v79629 2v529
2ndidx 30 1v34930 => 30 1v34930 2v81330
2ndidx 31 1v97031 => 31 1v97031 2v47331
2ndidx 32 1v65432 => 32 1v65432 2v19932
2ndidx 34 1v6234 => 34 1v6234 2v56934
2ndidx 35 1v65535 => 35 1v65535 2v98335
2ndidx 36 1v39736 => 36 1v39736 2v94836
2ndidx 37 1v28037 => 37 1v28037 2v41737
2ndidx 38 1v33038 => 38 1v33038 2v72338
2ndidx 39 1v83939 => 39 1v83939 2v19339
2ndidx 40 1v70140 => 40 1v70140 2v92640
2ndidx 41 1v32041 => 41 1v32041 2v65241
2ndidx 42 1v12642 => 42 1v12642 2v16842
2ndidx 44 1v3844 => 44 1v3844 2v84044
2ndidx 45 1v17645 => 45 1v17645 2v38845
2ndidx 46 1v65346 => 46 1v65346 2v14546
2ndidx 47 1v86147 => 47 1v86147 2v30647
2ndidx 48 1v34548 => 48 1v34548 2v53148
2ndidx 49 1v88049 => 49 1v88049 2v40749
2ndidx 50 1v10050 => 50 1v10050 2v53450
2ndidx 51 1v38951 => 51 1v38951 2v49851
2ndidx 52 1v48252 => 52 1v48252 2v66952
2ndidx 54 1v39254 => 54 1v39254 2v75454
2ndidx 55 1v455 => 55 1v455 2v9355
2ndidx 56 1v67956 => 56 1v67956 2v32556
2ndidx 57 1v74557 => 57 1v74557 2v80657
2ndidx 58 1v49458 => 58 1v49458 2v78958
2ndidx 59 1v97759 => 59 1v97759 2v53259
2ndidx 60 1v62960 => 60 1v62960 2v15260
2ndidx 61 1v92161 => 61 1v92161 2v28161
2ndidx 62 1v29862 => 62 1v29862 2v78262
2ndidx 64 1v31264 => 64 1v31264 2v46764
2ndidx 65 1v4965 => 65 1v4965 2v41265
2ndidx 66 1v166 => 66 1v166 2v43966
2ndidx 67 1v91067 => 67 1v91067 2v48367
2ndidx 68 1v10868 => 68 1v10868 2v82568
2ndidx 69 1v29469 => 69 1v29469 2v50069
2ndidx 70 1v57970 => 70 1v57970 2v29970
2ndidx 71 1v59371 => 71 1v59371 2v25871
2ndidx 72 1v62472 => 72 1v62472 2v33772
2ndidx 74 1v12674 => 74 1v12674 2v3974
2ndidx 75 1v65075 => 75 1v65075 2v75575
2ndidx 76 1v19276 => 76 1v19276 2v57076
2ndidx 77 1v3677 => 77 1v3677 2v49077
2ndidx 78 1v35178 => 78 1v35178 2v62478
2ndidx 79 1v13379 => 79 1v13379 2v66479
2ndidx 80 1v9080 => 80 1v9080 2v18380
2ndidx 81 1v7681 => 81 1v7681 2v9181
2ndidx 82 1v62282 => 82 1v62282 2v98682
2ndidx 84 1v81184 => 84 1v81184 2v86984
2ndidx 85 1v22985 => 85 1v22985 2v39085
2ndidx 86 1v16786 => 86 1v16786 2v82386
2ndidx 87 1v64887 => 87 1v64887 2v79287
2ndidx 88 1v16088 => 88 1v16088 2v71188
2ndidx 89 1v91089 => 89 1v91089 2v28689
2ndidx 90 1v75190 => 90 1v75190 2v56090
2ndidx 91 1v4191 => 91 1v4191 2v94391
2ndidx 92 1v13092 => 92 1v13092 2v7792
2ndidx 94 1v70194 => 94 1v70194 2v56694
2ndidx 95 1v14595 => 95 1v14595 2v79195
2ndidx 96 1v74996 => 96 1v74996 2v22196
2ndidx 97 1v88397 => 97 1v88397 2v37097
2ndidx 98 1v20798 => 98 1v20798 2v45798
2ndidx 99 1v10099 => 99 1v10099 2v1899
2ndIDX NULL
2ndidxnull 3 2v7033
2ndidxnull 13 2v40313
2ndidxnull 23 2v42123
2ndidxnull 33 2v57333
2ndidxnull 43 2v17043
2ndidxnull 53 2v81253
2ndidxnull 63 2v64363
2ndidxnull 73 2v11873
2ndidxnull 83 2v73083
2ndidxnull 93 2v48293
