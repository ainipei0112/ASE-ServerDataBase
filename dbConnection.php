<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'wb');

$dbConn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// 檢查資料庫連接
if (mysqli_connect_errno()) {
    echo 'Connection Failed' . mysqli_connect_error();
    exit;
} else {
    // 設定 mysqli 資料庫連結編碼
    mysqli_query($dbConn, 'set names utf8');
}
