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
        updateVisitorCount(); // 更新訪客計數
        getVisitorCount();
        break;
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

function updateVisitorCount() {
    global $dbConn;

    $currentDate = date('Y-m-d');
    $userIP = $_SERVER['REMOTE_ADDR'];

    // 檢查是否已經存在當天的記錄，並且該 IP 是否已被計數
    $checkSql = "SELECT *
                FROM 2o_visitor_count
                WHERE visit_date = '$currentDate'
                AND FIND_IN_SET('$userIP', ip_address)";
    $result = mysqli_query($dbConn, $checkSql);

    // 如果不存在記錄或該 IP 尚未被計數
    if (mysqli_num_rows($result) == 0) {
        $updateSql = "INSERT INTO 2o_visitor_count (visit_date, ip_address, count)
                      VALUES ('$currentDate', '$userIP', 1)
                      ON DUPLICATE KEY UPDATE
                      count = count + 1,
                      ip_address = CONCAT(ip_address, ',$userIP')";
        mysqli_query($dbConn, $updateSql);

        writeLog('updateVisitorCount', "Success - New visitor");
    } else {
        writeLog('updateVisitorCount', "Skipped - Visitor already counted");
    }
}

function getVisitorCount() {
    global $dbConn;

    $currentDate = date('Y-m-d');
    $sql = "SELECT count FROM 2o_visitor_count WHERE visit_date = '$currentDate'";
    $result = mysqli_query($dbConn, $sql);
    $count = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result)['count'] : 0;
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
    writeLog('getMailAlert', $mailResult ? 'Success' : 'Failure');
    echo $mailResult ? "郵件已成功發送" : "發送郵件失敗";
}

function writeLog($action, $status) {
    define('LOG_FILE_PATH', '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/2OAOI/');
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $logFile = LOG_FILE_PATH  . $currentDate . '.txt'; // 使用 UNC 路徑
    $userIP = $_SERVER['REMOTE_ADDR'] . "：";
    $logMessage = "$currentTime $action $status " . PHP_EOL;
    file_put_contents($logFile, $userIP . $logMessage, FILE_APPEND);
}
