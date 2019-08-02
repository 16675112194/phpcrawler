<?php 
    $dbms='mysql';     //数据库类型
    $host='127.0.0.1'; //数据库主机名
    $dbName='sex_img';    //使用的数据库
    $user='root';      //数据库连接用户名
    $pass='jdn667788';          //对应的密码
    $dsn="$dbms:host=$host;dbname=$dbName;charset=utf8";
    try {
        $dbh = new \PDO($dsn, $user, $pass); //初始化一个PDO对象
    } catch (PDOException $e) {
        die ("Error!: " . $e->getMessage());
    }
    $db = new \PDO($dsn, $user, $pass, array(\PDO::ATTR_PERSISTENT => true));

    $sql = ""
