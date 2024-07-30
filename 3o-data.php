<?php
require_once 'db_connection.php';
require_once 'config.php';

date_default_timezone_set("Asia/Taipei");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 讀取前端傳送過來的JSON資料(F12->網路->酬載)
$data = json_decode(file_get_contents('php://input'), true);

// 檢查是否成功解析JSON資料
if ($data === null) {
    // 處理JSON解析失敗的情況
    echo "Failed to parse JSON data";
} else {
    // 檢查是否存在'action'鍵
    if (isset($data['action'])) {
        // 取得'action'的值
        $action = $data['action'];
    } else {
        echo "Missing 'action' key";
    }
}

// 根據'action'的值執行相應的操作
switch ($action) {
    case 'get3oaoidata':
        get3oaoidata();
        break;
    default:
        echo json_encode(["success" => 0, "msg" => "無對應action: '$action'"]);
        break;
}

function get3oaoidata()
{
    global $db_connection;

    $sql = "SELECT * FROM all_3oaoi";
    $allproducts = mysqli_query($db_connection, $sql);

    if (mysqli_num_rows($allproducts) > 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('get3oaoidata', 'Success');
        echo json_encode(["success" => 1, "products" => $all_products]);
    } else if (mysqli_num_rows($allproducts) == 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('get3oaoidata', 'No Data Found');
        echo json_encode(["success" => 1, "products" => $all_products]);
    } else {
        writeLog('get3oaoidata', 'Failure');
        echo json_encode(["success" => 0, "msg" => "get3oaoidata Search Product Failure"]);
    }
}

function writeLog($action, $status)
{
    $currentDate = date('Y-m-d');
    $logFile = '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/3OAOI/' . $currentDate . '.txt'; // 使用 UNC 路徑
    $currentTime = date('H:i:s');
    $userIP = $_SERVER['REMOTE_ADDR'] . "：";
    $logMessage = "$currentTime $action $status " . PHP_EOL;

    // 使用 fopen() 寫入檔案
    $fp = fopen($logFile, "a");
    fwrite($fp, $userIP . $logMessage);
    fclose($fp);
}
