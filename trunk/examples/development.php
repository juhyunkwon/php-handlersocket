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

/* 接続情報 */
$host = 'localhost';
$port = 9999;
//$port = '9999';
$dbname = 'hstestdb';
$table = 'hstesttbl';
$auth = 'pass';


//HandlerSocket: 接続
try
{
    //成功:
    $hs = new HandlerSocket(
        $host, //ホスト名
        $port  //ポート番号
    );
    //$hs = new HandlerSocket($host, $port, null);    //null オプション
    //$hs = new HandlerSocket(null, $port, array('host' => $host));
    //$hs = new HandlerSocket($host, null, array('port' => $port));
    //$hs = new HandlerSocket(null, null, array('host' => $host, 'port' => $port));

    //失敗:
    //$hs = new HandlerSocket();                    //引数なし
    //$hs = new HandlerSocket($host);               //ポート指定なし
    //$hs = new HandlerSocket('', $port);           //空ホスト
    //$hs = new HandlerSocket('testserver', $port); //無効ホスト
    //$hs = new HandlerSocket($host, '');           //空ポート
    //$hs = new HandlerSocket($host, 8888);         //無効ポート
    //$hs = new HandlerSocket(null, $port);         //null ホスト
    //$hs = new HandlerSocket($host, null);         //null ポート
    //$hs = new HandlerSocket(null, null);          //null
}
catch (HandlerSocketException $exception)
{
    //エラー時は HandlerSocketException が発生
    echo $exception->getMessage(), PHP_EOL;
    die();
}

status_ok('new HandlerSocket', $hs);


//認証
//成功:
$ret = $hs->auth($auth);
//$ret = $hs->auth($auth, '1');
//$ret = $hs->auth($auth, 'hoge');

//失敗:
//$ret = $hs->auth(); //引数なし
//$ret = $hs->auth(''); //空
//$ret = $hs->auth(null); //NULL
//$ret = $hs->auth(array($auth)); //配列

status_ok('HandlerSocket::auth', $ret);


//インデックス展開
//成功:
$id = 1;
$key = 'PRIMARY';
//$key = 'i1';
//$key = ''; //空は PRIMARY と同じ
//$key = null; //NULLも PRIMARY と同じ
$field = 'k,v';
//$field = array('k','v');
//$field = ''; //なにも取得できない
//$field = null; //なにも取得できない
//$filter = 'f1,f2';
//$filter = array('f1','f2');
//$filter = ''; //--> この場合どーなるの ?
$filter = null; //--> この場合どーなるの ?

$ret = $hs->openIndex(
    $id,     //インデックス番号
    $dbname, //データベース名
    $table,  //テーブル名
    $key,    //インデックス名
    $field,  //フィールドリスト (カンマ区切りテキスト or 配列)
    $filter  //フィルターフィールドリスト (カンマ区切りテキスト or 配列)
);

/*
//失敗:
$key = 'PRIMARY';
$field = 'k,v';
$filter = 'f1,f2';
//$ret = $hs->openIndex(); //引数なし
//$ret = $hs->openIndex($id); //DB, Table, Key, Field なし
//$ret = $hs->openIndex($id, $dbname); //Table, Key, Field なし
//$ret = $hs->openIndex($id, $dbname, $table); //Key, Field なし
//$ret = $hs->openIndex($id, $dbname, $table, $key); //Field なし
//$ret = $hs->openIndex('', $dbname, $table, $key, $field); //空 ID
//$ret = $hs->openIndex('a', $dbname, $table, $key, $field); //文字列 ID
//$ret = $hs->openIndex($id, '', $table, $key, $field); //空 DB
//$ret = $hs->openIndex($id, 'hoge', $table, $key, $field); //無効 DB
//$ret = $hs->openIndex($id, null, $table, $key, $field); //NULL DB
//$ret = $hs->openIndex($id, array($db), $table, $key, $field); //配列 DB
//$ret = $hs->openIndex($id, $dbname, '', $key, $field); //空 Table
//$ret = $hs->openIndex($id, $dbname, 'hoge', $key, $field); //無効 Table
//$ret = $hs->openIndex($id, $dbname, null, $key, $field); //NULL Table
//$ret = $hs->openIndex($id, $dbname, array($table), $key, $field); //配列 Table
//$ret = $hs->openIndex($id, $dbname, $table, 'hoge', $field); //無効 Key
//$ret = $hs->openIndex($id, $dbname, $table, array($key), $field); //配列 Key
//$ret = $hs->openIndex($id, $dbname, $table, $key, 'hoge'); //無効 Field
//$ret = $hs->openIndex($id, $dbname, $table, $key, $field, 'hoge'); //無効 Filter
//$ret = $hs->openIndex($id, $dbname, $table, $key, $field, array('hoge')); //無効配列 Filter
*/

status_ok('HandlerSocket::openIndex', $ret);


//処理の実行
//成功:
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

//失敗:
//$ret = $hs->executeSingle(); //引数なし
//$ret = $hs->executeSingle($id); //比較演算子, 比較値なし
//$ret = $hs->executeSingle($id, '>'); //比較値なし
//$ret = $hs->executeSingle(10, '>', array('k1')); //無効接続 ID
//$ret = $hs->executeSingle($id, '>>', array('k1')); //無効比較演算子
//$ret = $hs->executeSingle($id, '>', array('k1'), 'a'); //無効リミット値
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 'a'); //無効オフセット値
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 'a'); //無効オフセット値
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, 'u'); //無効更新演算子

//無視:フィルター無視
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, null, null, array()); //空フィルター
//$ret = $hs->executeSingle($id, '>', array('k1'), 1, 0, null, null, array('F', '>=', 0)); //無効フィルター

status_ok('HandlerSocket::executeSingle', $ret);


//複数処理の実行
//成功:
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

//失敗:
//$ret = $hs->executeMulti(); //引数なし
//$ret = $hs->executeMulti(''); //空
//$ret = $hs->executeMulti(array()); //空配列
//$ret = $hs->executeMulti(array('')); //空配列

status_ok('HandlerSocket::executeMulti', $ret);


//更新処理の実行
$hs->openIndex(2, $dbname, $table, $key, 'k,v,f1,f2');
$hs->executeSingle(2, '+', array('k20', 'v20', 'f20', 'f220'));

//成功:
$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'V20', 'F20', 'F220'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'VV20', 'FF20'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), array('k20', 'VV20'));
//$ret = $hs->executeUpdate(2, '=', array('k20'), 'k20');
//$ret = $hs->executeUpdate(2, '=', array('hoge'), array('k20', 'V20', 'F20', 'F220')); //対象なし

//失敗:
//$ret = $hs->executeUpdate(); //引数なし

status_ok('HandlerSocket::executeUpdate', $ret);


//削除処理の実行
//成功:
$ret = $hs->executeDelete($id, '=', array('k20'));
//$ret = $hs->executeDelete($id, '=', 'k20');
//$ret = $hs->executeDelete($id, '=', 'hoge'); //対象なし

//失敗:
//$ret = $hs->executeDelete(); //引数なし

status_ok('HandlerSocket::executeDelete', $ret);


//挿入処理の実行
$hs->openIndex(2, $dbname, $table, $key, 'k,v,f1,f2');

$ret = $hs->executeInsert(2, array('K30', 'V30', 'F130', 'F230'));
//$ret = $hs->executeInsert(2, array('K30', 'V30', 'F130', null, 'F230'));

//失敗:
//$ret = $hs->executeInsert(); //引数なし
//$ret = $hs->executeInsert(2, array()); //空

status_ok('HandlerSocket::executeInsert', $ret);

$hs->executeDelete($id, '=', array('K30'));


//HandlerSocketIndex オブジェクトの作成
$id = 3;
$key = 'PRIMARY';
//$field = 'k,v';
$field = array('k','v');
//$filter = 'f1,f2';
$filter = array('f1','f2');

try
{
    //成功:
    $index = $hs->createIndex(
        $id,     //インデックス番号
        $dbname, //データベース名
        $table,  //テーブル名
        $key,    //インデックス名
        $field,  //フィールドリスト (カンマ区切りテキスト or 配列)
        array('filter' => $filter)
    );

    /*
    $index = new HandlerSocketIndex(
        $hs,     //HandlerSocket オブジェクト
        $id,       //index 番号
        $dbname, //データベース名
        $table,  //テーブル名
        $key,    //インデックス名
        $field,  //フィールドリスト (カンマ区切りテキスト or 配列)
        array('filter' => $filter)
    );
    */

    //失敗:
    //$index = $hs->createIndex(); //引数なし
    //$index = $hs->createIndex($id); //DB, Table, Key, Field なし
    //$index = $hs->createIndex($id, $dbname); //Table, Key, Field なし
    //$index = $hs->createIndex($id, $dbname, $table); //Key, Field なし
    //$index = $hs->createIndex($id, $dbname, $table, $key); //Field なし
    //$index = $hs->createIndex('', $dbname, $table, $key, $field); //空 ID
    //$index = $hs->creteIndex('a', $dbname, $table, $key, $field); //文字列 ID
    //$index = $hs->createIndex($id, '', $table, $key, $field); //空 DB
    //$index = $hs->createIndex($id, 'hoge', $table, $key, $field); //無効 DB
    //$index = $hs->createIndex($id, $dbname, '', $key, $field); //空 Table
    //$index = $hs->createIndex($id, $dbname, 'hoge', $key, $field); //無効 Table
    //$index = $hs->createIndex($id, $dbname, $table, 'hoge', $field); //無効 Key
    //$index = $hs->createIndex($id, $dbname, $table, $key, 'hoge'); //無効 Field

    //失敗: HandlerSocketIndex
    //class Obj {}
    //$obj = new Obj();
    //$index = new HandlerSocketIndex(
    //    $obj, //無効オブジェクト
    //    $id, $dbname, $table, $key, $field
    //);

}
catch (HandlerSocketException $exception)
{
    //エラー時は HandlerSocketException が発生
    echo 'Error:', $exception->getMessage(), PHP_EOL;
    die();
}

status_ok('HandlerSocket::createIndex', $index);


//接続番号の取得
status_ok('HandlerSocketIndex::getId', $index->getId());

//データベース名の取得
status_ok('HandlerSocketIndex::getDatabase', $index->getDatabase());

//テーブル名の取得
status_ok('HandlerSocketIndex::getTable', $index->getTable());

//フィールドリストの取得 (配列)
status_ok('HandlerSocketIndex::getField', $index->getField());

//フィルターリストの取得 (配列)
status_ok('HandlerSocketIndex::getFilter', $index->getFilter());

//有効演算子の取得
status_ok('HandlerSocketIndex::getOperator', $index->getOperator());



//データを取得する
//成功:
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

//失敗:
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

//$ret = $index->find(); //引数なし
//$ret = $index->find(array('>>' => 'k3')); //不正演算子
//$ret = $index->find(array('=' => array('k3', 'k2'))); //無効配列
//$ret = $index->find('', 'a'); //無効リミット値
//$ret = $index->find('', 2, 'a'); //無効オフセット値

//無視:
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>'))); //フィルタ引数未満
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>', 'f1'))); //フィルタ引数未満
//$ret = $index->find(
//    array('>' => ''), 10, 0,
//    array('filter' => array('>', 'f3', 'f'))); //無効フィルタキー


//データを追加する
//成功:
status_ok('HandlerSocketIndex:insert', $index->insert('K40', 'V40'));
//status_ok('HandlerSocketIndex:insert', $index->insert(array('K40', 'V40')));
//status_ok('HandlerSocketIndex:insert', $index->insert(array('K40', 'V40', 'F40')));

//失敗:
//status_ok('HandlerSocketIndex:insert', $index->insert()); //引数なし


//データを更新する
//成功:
//$ret = $index->update('K40', array('K40', '^^40'));
//$ret = $index->update('K40', array('U' => array('K40', '^^40')));
$ret = $index->update('K40', array('U?' => array('K40', '^^40')));
//$ret = $index->update('K40', 'K40');

//失敗:
//$ret = $index->update(); //引数なし
//$ret = $index->update('K40'); //更新引数なし
//$ret = $index->update('K40', array('u' => array('K40', 'V40'))); //無効演算子

status_ok('HandlerSocketIndex:update', $ret);


//データを削除する
//成功:
status_ok('HandlerSocketIndex:remove', $index->remove('K40'));
//status_ok('HandlerSocketIndex:remove', $index->remove(array('=' => 'K40')));
//status_ok('HandlerSocketIndex:remove', $index->remove(array('K40')));

//失敗:
//$ret = $index->remove(); //引数なし


//複数の処理を実行する
//成功:
$ret = $index->multi(
    array(
        array('find', 'k0'),
        array('insert', array('k0', 'vv0')),
        array('find', array('k0')),
        array('update',  array('=' => 'k0'), array('U' => array('k0', '^^0'))),
        array('find', array('=' => 'k0')),
        array('update',  'k0', array('k0', '0xx0')),
        array('find', 'k0'),
        array('update',  'k0', array('U?' => array('k0', '0---0'))),
        array('find', array('k0')),
        array('remove', array('=' => 'k0')),
        array('find', array('=' => 'k0')),
    ));

status_ok('HandlerSocketIndex:multi', $ret);
unset($ret);

//失敗:
//$ret = $index->multi(); //引数なし
//$ret = $index->multi(''); //空引数
//$ret = $index->multi(null); //NULL 引数
//$ret = $index->multi(array()); //空配列
//$ret = $index->multi(array('insert', 'ke', 've')); //無効配列
//$ret = $index->multi(array(array())); //空配列 * 2
//$ret = $index->multi(array(array('insert'))); //無効配列



echo PHP_EOL;
echo '__RESULT__', PHP_EOL;
var_dump(isset($ret) ? $ret : null);
echo '__ERROR__', PHP_EOL;
var_dump(isset($hs) ? $hs->getError() : null);
var_dump(isset($index) ? $index->getError() : null);
echo '__END__', PHP_EOL;
