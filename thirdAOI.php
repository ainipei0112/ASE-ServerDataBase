<?php

// 連接 SQLite 資料庫
$dbConn = new SQLite3('qv/qv.sqlite');
require_once 'config.php';

// 檢查資料庫連接
if (!$dbConn) {
    echo $dbConn->lastErrorMsg();
}

date_default_timezone_set('Asia/Taipei');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: access');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// 讀取前端傳送過來的JSON資料(F12->網路->酬載)
$data = json_decode(file_get_contents('php://input'), true);

// 檢查是否成功解析JSON資料
if ($data === null) {
    echo 'Failed to parse JSON data';
    exit;
}

// 檢查是否存在'action'
if (!isset($data['action'])) {
    echo 'Missing action key';
    exit;
}

// 取得'action'的值
$action = $data['action'];

// 根據'action'的值執行相應的操作
switch ($action) {
    case 'get3oaoidata':
        get3oaoidata();
        break;
    case 'getDataByCondition':
        getDataByCondition($data['drawingNo'], $data['machineId']);
        break;
    case 'exportDataByCondition':
        exportDataByCondition($data['drawingNo'], $data['machineId']);
        break;
    case 'mailAlert':
        mailAlert($data['emailData']);
        break;
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

// 取得3OAOI資料
function get3oaoidata() {
    global $dbConn;

    $sql = "SELECT * FROM stripData";
    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        writeLog('get3oaoidata', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $result = $stmt->execute();

    // 檢查查詢是否成功
    if (!$result) {
        writeLog('get3oaoidata', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'get3oaoidata Search Product Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Strip_No' => $row['strip_no'],
            'Drawing_No' => $row['drawing_no'],
            'Machine_Id' => $row['machine_id'],
            'Fail_Ppm' => $row['fail_ppm'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate'],
            'Ao_Time_Start' => $row['ao_time_start'],
            'Device_Id' => $row['device_id']
        ];
    }
    writeLog('get3oaoidata', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// 根據條件查詢資料
function getDataByCondition($drawingNo, $machineId) {
    global $dbConn;

    $sql = "SELECT * FROM stripData WHERE 1 = 1";
    // 檢查條件是否為 NULL
    if ($drawingNo !== null) {
        $sql .= " AND drawing_no = :drawingNo";
    }
    if ($machineId !== null) {
        $sql .= " AND machine_id = :machineId";
    }
    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        writeLog('getDataByCondition', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':drawingNo', $drawingNo, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('getDataByCondition', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Search by Condition Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Strip_No' => $row['strip_no'],
            'Drawing_No' => $row['drawing_no'],
            'Machine_Id' => $row['machine_id'],
            'Fail_Ppm' => $row['fail_ppm'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate'],
            'Fail_Count' => $row['fail_count'],
            'Pass_Count' => $row['pass_count'],
            'Aoi_Defect' => $row['aoi_defect'],
            'Ao_Time_Start' => $row['ao_time_start'],
            'Device_Id' => $row['device_id']
        ];
    }

    writeLog('getDataByCondition', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// 根據條件匯出資料
function exportDataByCondition($drawingNo, $machineId) {
    global $dbConn;

    $sql = "SELECT * FROM stripData WHERE 1 = 1";
    // 檢查條件是否為 NULL
    if ($drawingNo !== null) {
        $sql .= " AND drawing_no = :drawingNo";
    }
    if ($machineId !== null) {
        $sql .= " AND machine_id = :machineId";
    }
    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        writeLog('getDataByCondition', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':drawingNo', $drawingNo, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('getDataByCondition', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Search by Drawing No Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Lot_No' => $row['lot_no'],
            'Strip_No' => $row['strip_no'],
            'Drawing_No' => $row['drawing_no'],
            'Machine_Id' => $row['machine_id'],
            'Fail_Ppm' => $row['fail_ppm'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate'],
            'Ao_Time_Start' => $row['ao_time_start'],
            'Device_Id' => $row['device_id']
        ];
    }

    writeLog('getDataByCondition', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// 信件派送
function mailAlert($emailData) {
    global $config;

    $to = $emailData['recipient'];
    $subject = $emailData['subject'];
    $txt = $emailData['content'];

    $headers = "From: ASE-WB-3OAOI@aseglobal.com" . "\r\n" .
        "CC:" . "\r\n" .
        "MIME-Version: 1.0" . "\r\n" .
        "Content-Type: text/html; charset=UTF-8" . "\r\n" .
        "Content-Transfer-Encoding: 8bit";

    ini_set("SMTP", $config['smtp_server']);
    ini_set("smtp_port", $config['smtp_port']);

    $mailResult = mb_send_mail($to, $subject, $txt, $headers, 'UTF-8');
    writeLog('mailAlert', $mailResult ? 'Success' : 'Failure');
    echo json_encode(['success' => $mailResult, 'message' => $mailResult ? "郵件已成功發送" : "發送郵件失敗"]);
}

function writeLog($action, $status) {
    $currentDate = date('Y-m-d');
    $logFile = '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/3OAOI/' . $currentDate . '.txt'; // 使用 UNC 路徑
    $currentTime = date('H:i:s');
    $userIP = $_SERVER['REMOTE_ADDR'] . '：';
    $logMessage = "$currentTime $action $status" . PHP_EOL;
    file_put_contents($logFile, $userIP . $logMessage, FILE_APPEND);
}
