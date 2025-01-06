<?php
require_once 'dbConnection.php';
require_once 'config.php';

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
    case 'userLogin':
        userLogin($data['userData']);
        break;
    case 'getVisitorCount':
        getVisitorCount();
        break;
    case 'getCustomerData':
        getCustomerData();
        break;
    case 'getCustomerDetails':
        getCustomerDetails($data['customerCode'], $data['dateRange']);
        break;
    case 'getAIResults':
        getAIResults($data['selectedCustomer'], $data['selectedMachine'], $data['selectedDateRange']);
        break;
    case 'getProductByCondition':
        getProductByCondition($data['searchCriteria']);
        break;
    case 'getImageFiles':
        $files = getImageFiles($data['lot'], $data['date'], $data['id']);
        echo json_encode(['success' => 1, 'files' => $files]);
        break;
    default:
        echo json_encode(['success' => 0, 'msg' => "無對應action: '$action'"]);
        break;
}

// 前端 - LDAP登入
function userLogin($userData) {
    // Active Directory 伺服器資訊
    $empId = $userData["empId"];
    $ldapHost = "ldap://KHADDC04.kh.asegroup.com";
    $ldapDn = "DC=kh,DC=asegroup,DC=com";
    $ldapUser = "kh\\" . $empId;
    $ldapPassword = $userData["password"];

    // 有權限使用系統的廠處
    $validFactoryCodes = ['1200', 'SA00', 'SD00', 'SE00', 'SH00', 'SN00'];

    // 建立 LDAP 連接
    $ldapConn = ldap_connect($ldapHost);

    if ($ldapConn) {
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        $bind = ldap_bind($ldapConn, $ldapUser, $ldapPassword);

        if ($bind) {
            // 使用 sAMAccountName 進行搜尋
            $result = ldap_search($ldapConn, $ldapDn, "(sAMAccountName=$empId)", ["sAMAccountName", "displayName", "department", "title", "telephonenumber", "mail", "dn"]);

            if (!$result) {
                writeLog('userLogin', 'No Data Found');
                echo json_encode(['success' => 1, 'userDatas' => []], JSON_UNESCAPED_UNICODE);
            } else {
                $entries = ldap_get_entries($ldapConn, $result);

                if ($entries['count'] > 0) {
                    // 解析 DN 獲取 OU 資訊
                    $dnParts = explode(',', $entries[0]['dn']);
                    $departmentCode = '';
                    $factoryCode = '';

                    foreach ($dnParts as $part) {
                        if (strpos($part, 'OU=') !== false) {
                            $ouName = trim(str_replace('OU=', '', $part));
                            if (empty($departmentCode)) {
                                $departmentCode = $ouName; // 第一個 OU 為部門代碼
                            } else {
                                $factoryCode = $ouName; // 第二個 OU 為廠處代碼
                                break; // 只取前兩個 OU
                            }
                        }
                    }

                    // 檢查廠處代碼是否有效
                    if (!in_array($factoryCode, $validFactoryCodes)) {
                        writeLog('userLogin', "Invalid Factory Code: $factoryCode");
                        echo json_encode(['success' => 0, 'msg' => '此廠處無系統存取權限'], JSON_UNESCAPED_UNICODE);
                        return;
                    }

                    $userDatas = [
                        [
                            "Emp_ID" => isset($entries[0]['samaccountname'][0]) ? $entries[0]['samaccountname'][0] : '無',
                            "Emp_Name" => isset($entries[0]['displayname'][0]) ? $entries[0]['displayname'][0] : '無',
                            "Job_Title" => isset($entries[0]['title'][0]) ? $entries[0]['title'][0] : '無',
                            "Factory_Code" => $factoryCode,
                            "Department_Code" => $departmentCode,
                            "Dept_Name" => isset($entries[0]['department'][0]) ? $entries[0]['department'][0] : '無',
                            "Phone_Number" => isset($entries[0]['telephonenumber'][0]) ? $entries[0]['telephonenumber'][0] : '無',
                            "E_Mail" => isset($entries[0]['mail'][0]) ? $entries[0]['mail'][0] : '無',
                        ]
                    ];

                    updateVisitorCount($empId); // 更新訪客計數
                    writeLog('userLogin', "Success: $empId");
                    echo json_encode(['success' => 1, 'userDatas' => $userDatas], JSON_UNESCAPED_UNICODE);
                } else {
                    writeLog('userLogin', "Failure: $empId");
                    echo json_encode(['success' => 0, 'msg' => '找不到使用者。'], JSON_UNESCAPED_UNICODE);
                }
            }
        } else {
            writeLog('userLogin', "Failure: $empId");
            echo json_encode(['success' => 0, 'msg' => 'User Login Failure'], JSON_UNESCAPED_UNICODE);
        }

        ldap_unbind($ldapConn);
    } else {
        echo json_encode(['success' => 0, 'msg' => '無法連接到 LDAP 伺服器。'], JSON_UNESCAPED_UNICODE);
    }
}

// 前端 - 更新瀏覽人次
function updateVisitorCount($empId) {
    global $dbConn;
    $currentDate = date('Y-m-d');

    // 檢查當天的記錄是否存在
    $checkSql = "SELECT * FROM 2o_visitor_count WHERE visit_date = '$currentDate'";
    $result = mysqli_query($dbConn, $checkSql);

    if ($result && mysqli_num_rows($result) > 0) {
        // 如果記錄存在，檢查該工號是否已被計數
        $row = mysqli_fetch_assoc($result);
        $emp_ids = explode(',', $row['emp_ids']); // 將 emp_ids 轉為陣列

        if (!in_array($empId, $emp_ids)) {
            // 如果該工號不在 emp_ids 中，則將其加入並更新計數
            $updatedEmpIds = $row['emp_ids'] . ',' . $empId;
            $updateSql = "UPDATE 2o_visitor_count
                           SET emp_ids = '$updatedEmpIds', count = count + 1
                           WHERE visit_date = '$currentDate'";
            mysqli_query($dbConn, $updateSql);
            writeLog('updateVisitorCount', "Success - New visitor added: $empId");
        } else {
            // 如果該工號已存在，僅更新計數
            $updateSql = "UPDATE 2o_visitor_count
                           SET count = count + 1
                           WHERE visit_date = '$currentDate'";
            mysqli_query($dbConn, $updateSql);
            writeLog('updateVisitorCount', "Skipped - Visitor already counted: $empId");
        }
    } else {
        // 如果沒有記錄，則插入新記錄
        $insertSql = "INSERT INTO 2o_visitor_count (visit_date, emp_ids, count)
                      VALUES ('$currentDate', '$empId', 1)";
        mysqli_query($dbConn, $insertSql);
        writeLog('updateVisitorCount', "Success - New visitor: $empId");
    }
}

// 前端 - 查詢瀏覽人次
function getVisitorCount() {
    global $dbConn;

    $currentDate = date('Y-m-d');

    $sql = "SELECT count FROM 2o_visitor_count WHERE visit_date = '$currentDate'";
    $result = mysqli_query($dbConn, $sql);
    $count = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result)['count'] : 0;

    echo json_encode(['success' => 1, 'count' => $count]);
}

// 後端 - 執行狀態紀錄
function writeLog($action, $status) {
    if (!defined('LOG_FILE_PATH')) {
        define('LOG_FILE_PATH', '//10.11.33.122/D$/khwbpeaiaoi_Shares$/K18330/Log/2OAOI/');
    }
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $logFile = LOG_FILE_PATH  . $currentDate . '.txt'; // 使用 UNC 路徑
    $userIP = "[IP：" . $_SERVER['REMOTE_ADDR'] . "->";
    $logMessage = "$currentTime] $action $status " . PHP_EOL;
    file_put_contents($logFile, $userIP . $logMessage, FILE_APPEND);
}

// Summary - 取得所有客戶名稱 & Yield目標值
function getCustomerData() {
    global $dbConn;

    $sql = "SELECT * FROM customer_data ";
    $allcustomers = mysqli_query($dbConn, $sql);

    if (mysqli_num_rows($allcustomers) > 0) {
        $all_customers = mysqli_fetch_all($allcustomers, MYSQLI_ASSOC);
        writeLog('getCustomerData', 'Success');
        echo json_encode(['success' => 1, 'datas' => $all_customers]);
    } else if (mysqli_num_rows($allcustomers) == 0) {
        $all_customers = mysqli_fetch_all($allcustomers, MYSQLI_ASSOC);
        writeLog('getCustomerData', 'No Data Found');
        echo json_encode(['success' => 1, 'datas' => $all_customers]);
    } else {
        writeLog('getCustomerData', 'Failure');
        echo json_encode(['success' => 0, 'msg' => 'getCustomerData Failure']);
    }
}

// Summary - 取得客戶作貨資料
function getCustomerDetails($customerCode, $dateRange = null) {
    global $dbConn;

    $start_date = $dateRange ? $dateRange[0] : date('Y-m-d', strtotime('-1 day'));
    $end_date = $dateRange ? $dateRange[1] : date('Y-m-d', strtotime('-1 day'));

    $sql =
        "SELECT
        a.Date_1 as Date,
        a.Lot,
        a.Device_ID,
        SUM(a.AOI_Scan_Amount) as AOI_Scan_Amount,  -- 將 AOI_Scan_Amount 加總
        SUM(a.Final_Pass_Amount) as Final_Pass_Amount,  -- 將 Final_Pass_Amount 加總
        SUM(a.AOI_Scan_Amount) - SUM(a.Final_Pass_Amount) as Actual_Deduction, -- 計算實際扣量數
        AVG(a.Final_Yield) as Final_Yield,  -- 將 Final_Yield 平均
        a.Machine_ID,
        c.Final_Yield_Goal as Yield_Goal
    FROM all_2oaoi a
    LEFT JOIN customer_data c ON SUBSTRING(a.Lot, 3, 2) = c.Customer_Code
    WHERE SUBSTRING(a.Lot, 3, 2) = ?
    AND a.Date_1 BETWEEN ? AND ?  -- 若無日期選擇就只撈取昨天的資料
    GROUP BY a.Date_1, a.Lot, a.Device_ID, a.Machine_ID, c.Final_Yield_Goal
    ORDER BY a.Date_1 DESC, Final_Yield DESC, Actual_Deduction DESC";

    $stmt = mysqli_prepare($dbConn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $customerCode, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $details = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode(['success' => 1, 'details' => $details]);
    } else {
        echo json_encode(['success' => 0, 'msg' => 'Failed to get customer details']);
    }
}

// AI Result - 查詢條件
function getAIResults($selectedCustomer, $selectedMachine, $selectedDateRange) {
    global $dbConn;

    $customerCode = ($selectedCustomer === null) ? 'ALL' : $selectedCustomer['CustomerCode'];
    $machineName = ($selectedMachine === null) ? 'ALL' : $selectedMachine['MachineName'];
    $start_date = $selectedDateRange[0];
    $end_date = $selectedDateRange[1];

    $sql = "SELECT * FROM all_2oaoi WHERE Date_1 BETWEEN '$start_date' AND '$end_date'";
    $sql .= ($customerCode !== 'ALL') ? " AND SUBSTRING(Lot, 3, 2) = '$customerCode'" : "";
    $sql .= ($machineName !== 'ALL') ? " AND Machine_ID = '$machineName'" : "";
    $allcustomers = mysqli_query($dbConn, $sql);

    if (mysqli_num_rows($allcustomers) > 0) {
        $all_products = mysqli_fetch_all($allcustomers, MYSQLI_ASSOC);
        writeLog('getAIResults', 'Success');
        echo json_encode(['success' => 1, 'products' => $all_products, 'customerCode' => $customerCode, 'machineName' => $machineName]);
    } else if (mysqli_num_rows($allcustomers) == 0) {
        $all_products = mysqli_fetch_all($allcustomers, MYSQLI_ASSOC);
        writeLog('getAIResults', 'No Data Found');
        echo json_encode(['success' => 1, 'products' => $all_products, 'customerCode' => $customerCode, 'machineName' => $machineName]);
    } else {
        writeLog('getAIResults', 'Failure');
        echo json_encode(['success' => 0, 'msg' => 'getAIResults Search Product Failure']);
    }
}

// AOI產品資料 - 查詢條件
function getProductByCondition($searchCriteria) {
    global $dbConn;

    $conditions = [];
    $params = [];

    if (!empty($searchCriteria['lotNo'])) {
        $conditions[] = "Lot LIKE ?";
        $params[] = "%{$searchCriteria['lotNo']}%";
    }

    if (!empty($searchCriteria['deviceId'])) {
        $conditions[] = "Device_ID LIKE ?";
        $params[] = "%{$searchCriteria['deviceId']}%";
    }

    if (!empty($searchCriteria['machineId'])) {
        $conditions[] = "Machine_ID LIKE ?";
        $params[] = "%{$searchCriteria['machineId']}%";
    }

    if (!empty($searchCriteria['customerCode'])) {
        $conditions[] = "SUBSTRING(Lot, 3, 2) = ?";
        $params[] = $searchCriteria['customerCode'];
    }

    if (!empty($searchCriteria['dateRange'])) {
        $conditions[] = "Date_1 BETWEEN ? AND ?";
        $params[] = $searchCriteria['dateRange'][0];
        $params[] = $searchCriteria['dateRange'][1];
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $sql = "SELECT * FROM all_2oaoi $whereClause";

    $stmt = mysqli_prepare($dbConn, $sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode(['success' => 1, 'products' => $products]);
    } else {
        echo json_encode(['success' => 0, 'msg' => 'Search failed']);
    }
}

// AOI產品資料 - 彈窗照片
function getImageFiles($lot, $date, $id) {
    $formattedDate = date('mdY', strtotime($date));
    $path = "//khwbpeaiaoi01/2451AOI$/WaferMapTemp/Image/$formattedDate/$lot/$lot.$id/";
    $files = [];

    if (is_dir($path)) {
        $allFiles = scandir($path);
        foreach ($allFiles as $file) {
            if (strpos($file, 'WFR_NO_' . $id . '_KLA_') === 0) {
                $displayName = substr($file, 0, strpos($file, '_DIEX_')); // 只取到 KLA_N 的部分
                $files[] = [
                    'fullPath' => $path . $file,
                    'displayName' => $displayName
                ];
            }
        }
        // 根據 KLA 編號排序
        usort($files, function ($a, $b) {
            preg_match('/KLA_(\d+)/', $a['displayName'], $matchesA);
            preg_match('/KLA_(\d+)/', $b['displayName'], $matchesB);
            return $matchesA[1] - $matchesB[1];
        });
    }

    return $files;
}

// ----------abandon----------

// 信件派送
// function mailAlert() {
//     global $config;

//     $to = "AndyZT_Hsieh@aseglobal.com";
//     $subject = "2/O AOI DashBoard 寄信測試";
//     $txt = "寄信成功!";
//     $headers = "From: ASE-WB-2OAOI@aseglobal.com" . "\r\n" .
//         "CC:" . "\r\n" .
//         "Content-Type: text/plain; charset=UTF-8" . "\r\n" .
//         "Content-Transfer-Encoding: 8bit";

//     // 從設定檔讀取郵件伺服器地址和端口號
//     ini_set("SMTP", $config['smtp_server']);
//     ini_set("smtp_port", $config['smtp_port']);

//     // 發送郵件
//     $mailResult = mb_send_mail($to, $subject, $txt, $headers, 'UTF-8');
//     writeLog('mailAlert', $mailResult ? 'Success' : 'Failure');
//     echo $mailResult ? "郵件已成功發送" : "發送郵件失敗";
// }

// 上傳Excel寫入資料庫
// function uploadExcel() {
//     global $dbConn;

//     // 讀取 JSON 數據
//     $data = json_decode(file_get_contents('php://input'), true);

//     if (!isset($data['data'])) {
//         echo json_encode(['success' => 0, 'msg' => 'No data received']);
//         return;
//     }

//     $rows = $data['data'];
//     foreach ($rows as $index => $row) {
//         if ($index == 0) {
//             continue; // 跳過標題行
//         }

//         // 假設你的資料庫有 visit_date, ip_address 和 count 欄位
//         $visit_date = mysqli_real_escape_string($dbConn, $row[0]); // 假設第一列為 visit_date
//         $ip_address = mysqli_real_escape_string($dbConn, $row[1]); // 假設第二列為 ip_address
//         $count = (int)$row[2]; // 假設第三列為 count

//         $sql = "INSERT INTO 2o_visitor_count (visit_date, ip_address, count)
//                 VALUES ('$visit_date', '$ip_address', $count)
//                 ON DUPLICATE KEY UPDATE
//                 ip_address = CONCAT(ip_address, ',$ip_address'),
//                 count = count + $count";

//         mysqli_query($dbConn, $sql);
//     }

//     echo json_encode(['success' => 1, 'msg' => '檔案上傳並寫入資料庫成功！']);
// }
