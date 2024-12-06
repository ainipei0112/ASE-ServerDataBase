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
    case 'getDataByBDOrMachine':
        getDataByBDOrMachine($data['drawingNo'], $data['machineId']);
        break;
    case 'exportDataByBDOrMachine':
        exportDataByBDOrMachine($data['drawingNo'], $data['machineId']);
        break;
    case 'getDetailsByDate':
        getDetailsByDate($data['deviceId'], $data['date'], $data['periodType']);
        break;
    case 'getBDDetailsByMachineStrip':
        getBDDetailsByMachineStrip($data['deviceId'], $data['machineId'], $data['date'], $data['periodType']);
        break;
    case 'getMachineDetailsByBD':
        getMachineDetailsByBD($data['drawingNo'], $data['machineId'], $data['periodType']);
        break;
    case 'mailAlert':
        mailAlert($data['emailData']);
        break;
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

// ---------DB---------
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
            'Fail_Count' => $row['fail_count'],
            'Pass_Count' => $row['pass_count'],
            'Aoi_Defect' => $row['aoi_defect'],
            'Ao_Time_Start' => $row['ao_time_start'],
            'Device_Id' => $row['device_id']
        ];
    }
    writeLog('get3oaoidata', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// 根據條件查詢資料
function getDataByBDOrMachine($drawingNo, $machineId) {
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
        writeLog('getDataByBDOrMachine', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':drawingNo', $drawingNo, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('getDataByBDOrMachine', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
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

    writeLog('getDataByBDOrMachine', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// 根據條件匯出資料
function exportDataByBDOrMachine($drawingNo, $machineId) {
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
        writeLog('exportDataByBDOrMachine', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':drawingNo', $drawingNo, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('exportDataByBDOrMachine', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
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

    writeLog('exportDataByBDOrMachine', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

function getDetailsByDate($deviceId, $date, $periodType = 'daily') {
    global $dbConn;

    // 月份轉置
    $months = ["Jan" => "01", "Feb" => "02", "Mar" => "03", "Apr" => "04", "May" => "05", "Jun" => "06", "Jul" => "07", "Aug" => "08", "Sep" => "09", "Oct" => "10", "Nov" => "11", "Dec" => "12"];

    // 如果是monthly，將月份名稱轉換為數字
    if ($periodType === 'monthly' && isset($months[$date])) {
        $date = date('Y') . '-' . $months[$date];
    }

    // 如果是weekly，將Wxx格式轉換為年和週次格式
    if ($periodType === 'weekly' && preg_match('/^W(\d{2})$/', $date, $matches)) {
        $date = date('Y') . '-W' . $matches[1];
    }

    $sql = "SELECT machine_id, fail_ppm, pass_rate, overkill_rate, ao_time_start
            FROM stripData
            WHERE device_id = :deviceId";

    // 根據不同時間週期調整 SQL 查詢
    switch ($periodType) {
        case 'daily':
            $sql .= " AND date(ao_time_start) = :date";
            break;
        case 'weekly':
            $sql .= " AND strftime('%Y-W%W', ao_time_start) = :date";
            break;
        case 'monthly':
            $sql .= " AND strftime('%Y-%m', ao_time_start) = :date";
            break;
    }

    $stmt = $dbConn->prepare($sql);
    $stmt->bindValue(':deviceId', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $result = $stmt->execute();

    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Machine_Id' => $row['machine_id'],
            'Fail_Ppm' => $row['fail_ppm'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate']
        ];
    }

    echo json_encode(['success' => 1, 'results' => $results]);
}

function getBDDetailsByMachineStrip($deviceId, $machineId, $date, $periodType = 'daily') {
    global $dbConn;

    // 月份轉置
    $months = ["Jan" => "01", "Feb" => "02", "Mar" => "03", "Apr" => "04", "May" => "05", "Jun" => "06", "Jul" => "07", "Aug" => "08", "Sep" => "09", "Oct" => "10", "Nov" => "11", "Dec" => "12"];

    // 如果是monthly，將月份名稱轉換為數字
    if ($periodType === 'monthly' && isset($months[$date])) {
        $date = date('Y') . '-' . $months[$date];
    }

    // 如果是weekly，將Wxx格式轉換為年和週次格式
    if ($periodType === 'weekly' && preg_match('/^W(\d{2})$/', $date, $matches)) {
        $date = date('Y') . '-W' . $matches[1];
    }

    $sql = "SELECT ao_time_start, lot_no, strip_no, aoi_defect, pass_count, fail_count, pass_rate, overkill_rate, machine_id, drawing_no
            FROM stripData
            WHERE device_id = :deviceId
            AND machine_id = :machineId";

    // 根據不同時間週期調整 SQL 查詢
    switch ($periodType) {
        case 'daily':
            $sql .= " AND date(ao_time_start) = :date";
            break;
        case 'weekly':
            $sql .= " AND strftime('%Y-W%W', ao_time_start) = :date";
            break;
        case 'monthly':
            $sql .= " AND strftime('%Y-%m', ao_time_start) = :date";
            break;
    }

    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        writeLog('getBDDetailsByMachineStrip', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':deviceId', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('getBDDetailsByMachineStrip', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Search by Condition Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Ao_Time_Start' => $row['ao_time_start'],
            'Lot_No' => $row['lot_no'],
            'Strip_No' => $row['strip_no'],
            'Aoi_Defect' => $row['aoi_defect'],
            'Pass_Count' => $row['pass_count'],
            'Fail_Count' => $row['fail_count'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate'],
            'Machine_Id' => $row['machine_id'],
            'Drawing_No' => $row['drawing_no']
        ];
    }

    writeLog('getBDDetailsByMachineStrip', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

function getMachineDetailsByBD($drawingNo, $machineId, $periodType) {
    global $dbConn;

    $sql = "SELECT ao_time_start, lot_no, strip_no, aoi_defect, pass_count, fail_count, pass_rate,
                   CASE WHEN overkill_rate < 0 THEN 0 ELSE overkill_rate END AS overkill_rate,
                   machine_id, drawing_no
            FROM stripData
            WHERE drawing_no = :drawingNo
            AND machine_id = :machineId";

    // 根據不同時間週期調整 SQL 查詢
    switch ($periodType) {
        case 'daily':
            $sql .= " AND date(strftime('%Y-%m-%d', ao_time_start)) = date('now', '-1 day')";
            break;
        case 'weekly':
            $sql .= " AND date(strftime('%Y-%m-%d', ao_time_start)) >= date('now', '-7 days')
                      AND date(strftime('%Y-%m-%d', ao_time_start)) < date('now', 'localtime')";
            break;
        case 'monthly':
            $sql .= " AND date(strftime('%Y-%m-%d', ao_time_start)) >= date('now', '-1 month')
                      AND date(strftime('%Y-%m-%d', ao_time_start)) < date('now', 'localtime')";
            break;
    }

    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        writeLog('getMachineDetailsByBD', 'SQL Prepare Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Database error occurred']);
        return;
    }
    $stmt->bindValue(':drawingNo', $drawingNo, SQLITE3_TEXT);
    $stmt->bindValue(':machineId', $machineId, SQLITE3_TEXT);
    $result = $stmt->execute();

    // 檢查查詢執行是否成功
    if (!$result) {
        writeLog('getMachineDetailsByBD', 'Query Execution Failure: ' . $dbConn->lastErrorMsg());
        echo json_encode(['success' => 0, 'msg' => 'Search by Condition Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = [
            'Ao_Time_Start' => $row['ao_time_start'],
            'Lot_No' => $row['lot_no'],
            'Strip_No' => $row['strip_no'],
            'Aoi_Defect' => $row['aoi_defect'],
            'Pass_Count' => $row['pass_count'],
            'Fail_Count' => $row['fail_count'],
            'Pass_Rate' => $row['pass_rate'],
            'Overkill_Rate' => $row['overkill_rate'],
            'Machine_Id' => $row['machine_id'],
            'Drawing_No' => $row['drawing_no']
        ];
    }

    writeLog('getMachineDetailsByBD', count($results) > 0 ? 'Success' : 'No Data Found');
    echo json_encode(['success' => 1, 'results' => $results]);
}

// ---------API---------
// // 取得3OAOI資料
// function get3oaoidata() {
//     global $config;

//     // 初始化cURL會話
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $config['api_url']);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($ch, CURLOPT_USERPWD, "{$config['username']}:{$config['password']}");

//     // 執行cURL請求
//     $response = curl_exec($ch);

//     // 檢查是否有錯誤發生
//     if (curl_errno($ch)) {
//         writeLog('get3oaoidata', 'cURL Error: ' . curl_error($ch));
//         echo json_encode(['success' => 0, 'msg' => 'API request failed']);
//         curl_close($ch);
//         return;
//     }

//     curl_close($ch);

//     // 解析JSON回應
//     $data = json_decode($response, true);

//     // 檢查JSON解析是否成功
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         writeLog('get3oaoidata', 'JSON Decoding Error: ' . json_last_error_msg());
//         echo json_encode(['success' => 0, 'msg' => 'Failed to parse API response']);
//         return;
//     }

//     // 轉換資料格式
//     $results = [];
//     foreach ($data as $item) {
//         $results[] = [
//             'Strip_No' => $item['strip_no'] ?? '',
//             'Drawing_No' => $item['drawing_no'] ?? '',
//             'Machine_Id' => $item['machine_id'] ?? '',
//             'Fail_Ppm' => $item['fail_ppm'] ?? '',
//             'Pass_Rate' => $item['pass_rate'] ?? '',
//             'Overkill_Rate' => $item['overkill_rate'] ?? '',
//             'Ao_Time_Start' => $item['ao_time_start'] ?? '',
//             'Device_Id' => $item['device_id'] ?? ''
//         ];
//     }

//     writeLog('get3oaoidata', count($results) > 0 ? 'Success' : 'No Data Found');
//     echo json_encode(['success' => 1, 'results' => $results]);
// }

// // 根據條件查詢資料
// function getDataByBDOrMachine($drawingNo, $machineId) {
//     global $config;

//     $api_url = $config['api_url'] . "?drawing_no=" . urlencode($drawingNo) . "&machine_id=" . urlencode($machineId);

//     // 初始化cURL會話
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $api_url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($ch, CURLOPT_USERPWD, "{$config['username']}:{$config['password']}");

//     // 執行cURL請求
//     $response = curl_exec($ch);

//     // 檢查是否有錯誤發生
//     if (curl_errno($ch)) {
//         writeLog('getDataByBDOrMachine', 'cURL Error: ' . curl_error($ch));
//         echo json_encode(['success' => 0, 'msg' => 'API request failed']);
//         curl_close($ch);
//         return;
//     }

//     curl_close($ch);

//     // 解析JSON回應
//     $data = json_decode($response, true);

//     // 檢查JSON解析是否成功
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         writeLog('getDataByBDOrMachine', 'JSON Decoding Error: ' . json_last_error_msg());
//         echo json_encode(['success' => 0, 'msg' => 'Failed to parse API response']);
//         return;
//     }

//     // 輸出查詢結果
//     $results = [];
//     foreach ($data as $item) {
//         $results[] = [
//             'Strip_No' => $item['strip_no'] ?? '',
//             'Drawing_No' => $item['drawing_no'] ?? '',
//             'Machine_Id' => $item['machine_id'] ?? '',
//             'Fail_Ppm' => $item['fail_ppm'] ?? '',
//             'Pass_Rate' => $item['pass_rate'] ?? '',
//             'Overkill_Rate' => $item['overkill_rate'] ?? '',
//             'Fail_Count' => $item['fail_count'] ?? '',
//             'Pass_Count' => $item['pass_count'] ?? '',
//             'Aoi_Defect' => $item['aoi_defect'] ?? '',
//             'Ao_Time_Start' => $item['ao_time_start'] ?? '',
//             'Device_Id' => $item['device_id'] ?? ''
//         ];
//     }

//     writeLog('getDataByBDOrMachine', count($results) > 0 ? 'Success' : 'No Data Found');
//     echo json_encode(['success' => 1, 'results' => $results]);
// }

// // 根據條件匯出資料
// function exportDataByBDOrMachine($drawingNo, $machineId) {
//     global $config;

//     $api_url = $config['api_url'] . "?drawing_no=" . urlencode($drawingNo) . "&machine_id=" . urlencode($machineId);

//     // 初始化cURL會話
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $api_url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($ch, CURLOPT_USERPWD, "{$config['username']}:{$config['password']}");

//     // 執行cURL請求
//     $response = curl_exec($ch);

//     // 檢查是否有錯誤發生
//     if (curl_errno($ch)) {
//         writeLog('exportDataByBDOrMachine', 'cURL Error: ' . curl_error($ch));
//         echo json_encode(['success' => 0, 'msg' => 'API request failed']);
//         curl_close($ch);
//         return;
//     }

//     curl_close($ch);

//     // 解析JSON回應
//     $data = json_decode($response, true);

//     // 檢查JSON解析是否成功
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         writeLog('exportDataByBDOrMachine', 'JSON Decoding Error: ' . json_last_error_msg());
//         echo json_encode(['success' => 0, 'msg' => 'Failed to parse API response']);
//         return;
//     }

//     // 輸出查詢結果
//     $results = [];
//     foreach ($data as $item) {
//         $results[] = [
//             'Lot_No' => $item['lot_no'] ?? '',
//             'Strip_No' => $item['strip_no'] ?? '',
//             'Drawing_No' => $item['drawing_no'] ?? '',
//             'Machine_Id' => $item['machine_id'] ?? '',
//             'Fail_Ppm' => $item['fail_ppm'] ?? '',
//             'Pass_Rate' => $item['pass_rate'] ?? '',
//             'Overkill_Rate' => $item['overkill_rate'] ?? '',
//             'Ao_Time_Start' => $item['ao_time_start'] ?? '',
//             'Device_Id' => $item['device_id'] ?? ''
//         ];
//     }

//     writeLog('exportDataByBDOrMachine', count($results) > 0 ? 'Success' : 'No Data Found');
//     echo json_encode(['success' => 1, 'results' => $results]);
// }

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
