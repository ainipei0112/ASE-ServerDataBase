<?php
$db_host = '127.0.0.1'; // localhost
$db_user = 'root';  // 資料庫帳號
$db_password = '';  // 資料庫密碼
$db_name = 'wb';   // 資料庫

$db_connection = mysqli_connect($db_host, $db_user, $db_password, $db_name);

// 檢查資料庫連接
if(mysqli_connect_errno()) {
    echo "Connection Failed".mysqli_connect_error();
    exit;
}
else {
    // 設定 mysqli 資料庫連結編碼
    mysqli_query($db_connection,"set names utf8");
}
?>
