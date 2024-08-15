<?php

// 連接 SQLite 資料庫
$dbConn = new SQLite3('qv/qv.sqlite');

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
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

// 取得3OAOI資料
function get3oaoidata() {
    global $dbConn;

    $sql = "SELECT * FROM stripData";
    $allProducts = $dbConn->query($sql);

    // 檢查查詢是否成功
    if ($allProducts === false) {
        writeLog('get3oaoidata', 'Failure');
        echo json_encode(['success' => 0, 'msg' => 'get3oaoidata Search Product Failure']);
        return;
    }

    // 輸出查詢結果
    $results = [];
    while ($row = $allProducts->fetchArray()) {
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

function writeLog($action, $status) {
    $currentDate = date('Y-m-d');
    $logFile = '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/3OAOI/' . $currentDate . '.txt'; // 使用 UNC 路徑
    $currentTime = date('H:i:s');
    $userIP = $_SERVER['REMOTE_ADDR'] . '：';
    $logMessage = "$currentTime $action $status" . PHP_EOL;

    // 使用 fopen() 寫入檔案
    $fp = fopen($logFile, 'a');
    fwrite($fp, $userIP . $logMessage);
    fclose($fp);
}
