--TEST--
libmysql
--SKIPIF--
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
    $v = 'v' . (int)rand(1, 1000) . $i;

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
k0 v30
k1 v6451
k10 v92910
k11 v21211
k12 v37812
k13 v19213
k14 v9114
k15 v14615
k16 v40116
k17 v48017
k18 v53618
k19 v66719
k2 v8252
k20 v26520
k21 v97221
k22 v11422
k23 v72223
k24 v44324
k25 v84425
k26 v2226
k27 v40427
k28 v96228
k29 v48029
k3 v2663
k30 v1730
k31 v96531
k32 v12432
k33 v84133
k34 v23034
k35 v19735
k36 v92036
k37 v86337
k38 v90038
k39 v8239
k4 v734
k40 v92140
k41 v82941
k42 v29342
k43 v29843
k44 v2144
k45 v38345
k46 v44346
k47 v42247
k48 v86348
k49 v97949
k5 v795
k50 v8850
k51 v12851
k52 v95152
k53 v20153
k54 v84954
k55 v39355
k56 v4556
k57 v87057
k58 v79658
k59 v659
k6 v6346
k60 v35060
k61 v81361
k62 v97062
k63 v47463
k64 v65464
k65 v20065
k66 v67066
k67 v57467
k68 v6268
k69 v57069
k7 v7047
k70 v65570
k71 v98371
k72 v39872
k73 v94873
k74 v28074
k75 v41875
k76 v33176
k77 v72377
k78 v83978
k79 v19479
k8 v1628
k80 v70280
k81 v92681
k82 v32182
k83 v65283
k84 v12784
k85 v16985
k86 v4586
k87 v17187
k88 v3988
k89 v84189
k9 v589
k90 v17790
k91 v38891
k92 v65392
k93 v14693
k94 v86294
k95 v30795
k96 v34596
k97 v53197
k98 v88098
k99 v40799
