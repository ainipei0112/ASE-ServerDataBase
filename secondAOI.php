<?php
require_once 'dbConnection.php';
require_once 'config.php';

date_default_timezone_set('Asia/Taipei');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: access');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

session_start();

// 讀取前端傳送過來的JSON資料(F12->網路->酬載)
$data = json_decode(file_get_contents('php://input'), true);

// 檢查是否成功解析JSON資料
if ($data === null) {
    // 處理JSON解析失敗的情況
    echo 'Failed to parse JSON data';
} else {
    // 檢查是否存在'action'鍵
    if (isset($data['action'])) {
        // 取得'action'的值
        $action = $data['action'];
    } else {
        echo 'Missing action key';
    }
}

// 更新訪客計數
updateVisitorCount();

// 根據'action'的值執行相應的操作
switch ($action) {
    case 'getAIResults':
        getAIResults($data['selectedCustomer'], $data['selectedDateRange']);
        break;
    case 'getProductById':
        getProductById($data['productId']);
        break;
    case 'getMailAlert':
        getMailAlert();
        break;
    case 'getVisitorCount':
        getVisitorCount();
        break;
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

function updateVisitorCount() {
    global $dbConn;

    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $userIP = $_SERVER['REMOTE_ADDR'];

    // 檢查 session 中是否已經記錄了當天的訪客
    if (!isset($_SESSION['visited']) || $_SESSION['visited'] !== $currentDate) {
        // ON DUPLICATE KEY UPDATE 判別 VALUES 插入的資料是否已經存在，如果存在則更新，不存在則插入。
        $sql = "INSERT INTO 2o_visitor_count (visit_date, visit_time, ip_address, count)
                VALUES ('$currentDate', '$currentTime', '$userIP', 1)
                ON DUPLICATE KEY UPDATE count = count + 1";
        mysqli_query($dbConn, $sql);

        // 設定 session 記錄當天已訪問
        $_SESSION['visited'] = $currentDate;
    }
}

function getVisitorCount() {
    global $dbConn;

    $currentDate = date('Y-m-d');
    $sql = "SELECT count FROM 2o_visitor_count WHERE visit_date = '$currentDate'";
    $result = mysqli_query($dbConn, $sql);
    $count = 0;

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $count = $row['count'];
    }
    echo json_encode(['success' => 1, 'count' => $count]);
}

function getAIResults($selectedCustomer, $selectedDateRange) {
    global $dbConn;

    $customerCode = $selectedCustomer['CustomerCode'];
    $start_date = $selectedDateRange[0];
    $end_date = $selectedDateRange[1];

    $sql = "SELECT * FROM all_2oaoi WHERE Date_1 BETWEEN '$start_date' AND '$end_date'";
    $sql .= ($customerCode !== 'ALL') ? " AND SUBSTRING(Lot, 3, 2) = '$customerCode'" : "";
    $allproducts = mysqli_query($dbConn, $sql);

    if (mysqli_num_rows($allproducts) > 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('getAIResults', 'Success');
        echo json_encode(['success' => 1, 'products' => $all_products, 'customerCode' => $customerCode]);
    } else if (mysqli_num_rows($allproducts) == 0) {
        $all_products = mysqli_fetch_all($allproducts, MYSQLI_ASSOC);
        writeLog('getAIResults', 'No Data Found');
        echo json_encode(['success' => 1, 'products' => $all_products, 'customerCode' => $customerCode]);
    } else {
        writeLog('getAIResults', 'Failure');
        echo json_encode(['success' => 0, 'msg' => 'getAIResults Search Product Failure']);
    }
}

function getProductById($productId) {
    global $dbConn;

    if ($productId !== NULL) {
        $productById = mysqli_query($dbConn, "SELECT * FROM all_2oaoi WHERE all_2oaoi.Lot LIKE '%$productId%'");
    }

    if (mysqli_num_rows($productById) > 0) {
        $product_byId = mysqli_fetch_all($productById, MYSQLI_ASSOC);
        writeLog('getProductById', 'Success');
        echo json_encode(['success' => 1, 'products' => $product_byId], JSON_UNESCAPED_UNICODE);
    } else if (mysqli_num_rows($productById) == 0) {
        $product_byId = mysqli_fetch_all($productById, MYSQLI_ASSOC);
        writeLog('getProductById', 'No Data Found');
        echo json_encode(['success' => 1, 'products' => $product_byId], JSON_UNESCAPED_UNICODE);
    } else {
        writeLog('getProductById', 'Failure');
        echo json_encode(['success' => 0, 'msg' => 'getProductById Search Product Failure']);
    }
}

function getMailAlert() {
    global $config;

    $to = "AndyZT_Hsieh@aseglobal.com";
    $subject = "2/O AOI DashBoard 寄信測試";
    $txt = "寄信成功!";
    $headers = "From: ASE-WB-2OAOI@aseglobal.com" . "\r\n" .
        "CC:" . "\r\n" .
        "Content-Type: text/plain; charset=UTF-8" . "\r\n" .
        "Content-Transfer-Encoding: 8bit";

    // 從設定檔讀取郵件伺服器地址和端口號
    ini_set("SMTP", $config['smtp_server']);
    ini_set("smtp_port", $config['smtp_port']);

    // 發送郵件
    $mailResult = mb_send_mail($to, $subject, $txt, $headers, 'UTF-8');

    if ($mailResult) {
        writeLog('getMailAlert', 'Success');
        echo "郵件已成功發送";
    } else {
        $error = error_get_last();
        writeLog('getMailAlert', 'Failure');
        echo "發送郵件失敗：{$error['message']}";
    }
}

function writeLog($action, $status) {
    define('LOG_FILE_PATH', '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/2OAOI/');
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $logFile = LOG_FILE_PATH  . $currentDate . '.txt'; // 使用 UNC 路徑
    $userIP = $_SERVER['REMOTE_ADDR'] . "：";
    $logMessage = "$currentTime $action $status " . PHP_EOL;

    // 使用 fopen() 寫入檔案
    $fp = fopen($logFile, 'a');
    fwrite($fp, $userIP . $logMessage);
    fclose($fp);
}
