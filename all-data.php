<?php
// DB connection: $db_connection from db_connection.php
require 'db_connection.php';

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
    case 'getAIResults':
        getAIResults($data['selectedCustomer'], $data['selectedDateRange']);
        break;
    case 'getProductById':
        getProductById($data['productId']);
        break;
    default:
        echo json_encode(["success" => 0, "msg" => "無對應action: '$action'"]);
        break;
}

function getAIResults($selectedCustomer, $selectedDateRange)
{
    global $db_connection;

    $customerCode = $selectedCustomer['CustomerCode'];
    $start_date = $selectedDateRange[0];
    $end_date = $selectedDateRange[1];

    $sql = "SELECT * FROM all_2oaoi WHERE Date_1 BETWEEN '$start_date' AND '$end_date'";
    $sql .= ($customerCode !== 'ALL') ? " AND SUBSTRING(Lot, 3, 2) = '$customerCode'" : "";
    $allproducts = mysqli_query($db_connection, $sql);

    if (mysqli_num_rows($allproducts) > 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('getAIResults', 'Success');
        echo json_encode(["success" => 1, "products" => $all_products, "customerCode" => $customerCode]);
    } else if (mysqli_num_rows($allproducts) == 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('getAIResults', 'No Data Found');
        echo json_encode(["success" => 1, "products" => $all_products, "customerCode" => $customerCode]);
    } else {
        writeLog('getAIResults', 'Failure');
        echo json_encode(["success" => 0, "msg" => "getAIResults Search Product Failure"]);
    }
}

function getProductById($productId)
{
    global $db_connection;

    if ($productId !== NULL) {
        $productById = mysqli_query($db_connection, "SELECT * FROM all_2oaoi WHERE all_2oaoi.Lot LIKE '%$productId%'");
    }

    if (mysqli_num_rows($productById) > 0) {
        $product_byId = mysqli_fetch_all($productById, MYSQLI_ASSOC);
        writeLog('getProductById', 'Success');
        echo json_encode(["success" => 1, "products" => $product_byId], JSON_UNESCAPED_UNICODE);
    } else if (mysqli_num_rows($productById) == 0) {
        $product_byId = mysqli_fetch_all($productById, MYSQLI_ASSOC);
        writeLog('getProductById', 'No Data Found');
        echo json_encode(["success" => 1, "products" => $product_byId], JSON_UNESCAPED_UNICODE);
    } else {
        writeLog('getProductById', 'Failure');
        echo json_encode(["success" => 0, "msg" => "getProductById Search Product Failure"]);
    }
}

function writeLog($action, $status)
{
    $currentDate = date('Y-m-d');
    $logFile = '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/WBAOI/' . $currentDate . '.txt'; // 使用 UNC 路徑
    $currentTime = date('H:i:s');
    $userIP = $_SERVER['REMOTE_ADDR'] . "：";
    $logMessage = "$currentTime $action $status " . PHP_EOL;

    // 使用 fopen() 寫入檔案
    $fp = fopen($logFile, "a");
    fwrite($fp, $userIP . $logMessage);
    fclose($fp);
}
