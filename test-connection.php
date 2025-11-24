<?php
// test-connection.php - Google Sheets API 연결 테스트 (수정된 버전)
session_start();
require_once 'config.php';

// 오류 처리 개선
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 안전한 파일 로드
function safeRequire($file) {
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

$functionsLoaded = safeRequire('functions.php');
$googleSheetsLoaded = safeRequire('google-sheets.php');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Google Sheets API 연결 테스트</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-item {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        
        .test-item.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .test-item.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .test-item.info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        h1 {
            color: #333;
            text-align: center;
        }
        
        h2 {
            color: #666;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn.btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn.btn-warning:hover {
            background: #e0a800;
        }
        
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        
        .error-details {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Google Sheets API 연결 테스트</h1>
        
        <h2>1. 기본 파일 및 설정 확인</h2>
        
        <?php
        // PHP 파일 로드 상태 확인
        if (!$functionsLoaded) {
            echo '<div class="test-item error">❌ functions.php 파일을 찾을 수 없습니다.</div>';
        } else {
            echo '<div class="test-item success">✅ functions.php 파일 로드됨</div>';
        }
        
        if (!$googleSheetsLoaded) {
            echo '<div class="test-item error">❌ google-sheets.php 파일을 찾을 수 없습니다.</div>';
        } else {
            echo '<div class="test-item success">✅ google-sheets.php 파일 로드됨</div>';
        }
        
        // 1. Composer 설치 확인
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            echo '<div class="test-item success">✅ Composer 패키지 설치됨</div>';
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            echo '<div class="test-item error">❌ Composer 패키지 없음<br>
                  <strong>해결방법:</strong> <code>composer require google/apiclient:^2.0</code> 실행</div>';
        }
        
        // 2. 서비스 계정 파일 확인
        if (defined('GOOGLE_CREDENTIALS_PATH') && file_exists(GOOGLE_CREDENTIALS_PATH)) {
            echo '<div class="test-item success">✅ 서비스 계정 파일 존재: ' . basename(GOOGLE_CREDENTIALS_PATH) . '</div>';
            
            // JSON 파일 유효성 검사
            $credentials = json_decode(file_get_contents(GOOGLE_CREDENTIALS_PATH), true);
            if ($credentials && isset($credentials['client_email'])) {
                echo '<div class="test-item info">📧 서비스 계정 이메일: ' . htmlspecialchars($credentials['client_email']) . '</div>';
            } else {
                echo '<div class="test-item error">❌ 서비스 계정 파일 형식 오류</div>';
            }
        } else {
            echo '<div class="test-item error">❌ 서비스 계정 파일 없음<br>
                  <strong>경로:</strong> ' . (defined('GOOGLE_CREDENTIALS_PATH') ? GOOGLE_CREDENTIALS_PATH : '정의되지 않음') . '</div>';
        }
        
        // 3. 스프레드시트 ID 확인
        if (defined('SPREADSHEET_ID') && SPREADSHEET_ID) {
            echo '<div class="test-item success">✅ 스프레드시트 ID 설정됨: ' . htmlspecialchars(SPREADSHEET_ID) . '</div>';
        } else {
            echo '<div class="test-item error">❌ 스프레드시트 ID 없음</div>';
        }
        
        // 4. 시트명 상수 확인
        $sheetConstants = [
            'SHEET_COMPANY_MANAGEMENT' => '업체관리 시트명',
            'SHEET_COMPANY_INFO' => '업체정보 시트명',
            'SHEET_ORDER_LOG' => '주문로그 시트명',
            'SHEET_ITEM_LIST' => '품목리스트 시트명'  // 새로 추가
        ];
        
        foreach ($sheetConstants as $const => $desc) {
            if (defined($const)) {
                echo '<div class="test-item success">✅ ' . $desc . ': ' . htmlspecialchars(constant($const)) . '</div>';
            } else {
                echo '<div class="test-item error">❌ ' . $desc . ' 상수 정의되지 않음: ' . $const . '</div>';
            }
        }
        ?>
        
        <h2>2. Google Sheets API 연결 테스트</h2>
        
        <?php
        if (file_exists(__DIR__ . '/vendor/autoload.php') && 
            defined('GOOGLE_CREDENTIALS_PATH') && 
            file_exists(GOOGLE_CREDENTIALS_PATH) && 
            $googleSheetsLoaded) {
            
            try {
                // Google Sheets 함수가 존재하는지 확인
                if (function_exists('testGoogleSheetsConnection')) {
                    $testResult = testGoogleSheetsConnection();
                    if ($testResult['success']) {
                        echo '<div class="test-item success">✅ ' . htmlspecialchars($testResult['message']) . '</div>';
                    } else {
                        echo '<div class="test-item error">❌ ' . htmlspecialchars($testResult['message']) . '</div>';
                    }
                } else {
                    echo '<div class="test-item error">❌ testGoogleSheetsConnection 함수를 찾을 수 없습니다.</div>';
                }
            } catch (Exception $e) {
                echo '<div class="test-item error">❌ 연결 테스트 실패: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<div class="error-details">오류 상세: ' . htmlspecialchars($e->getTraceAsString()) . '</div>';
            }
        } else {
            echo '<div class="test-item error">❌ 필수 파일이 없어 연결 테스트를 할 수 없습니다.</div>';
        }
        ?>
        
        <h2>3. 스프레드시트 구조 확인</h2>
        
        <?php
        if (file_exists(__DIR__ . '/vendor/autoload.php') && 
            defined('GOOGLE_CREDENTIALS_PATH') && 
            file_exists(GOOGLE_CREDENTIALS_PATH) && 
            $googleSheetsLoaded &&
            function_exists('getSheetsService')) {
            
            try {
                $service = getSheetsService();
                $spreadsheet = $service->spreadsheets->get(SPREADSHEET_ID);
                $sheets = $spreadsheet->getSheets();
                
                echo '<div class="test-item success">✅ 스프레드시트 시트 목록 (' . count($sheets) . '개):</div>';
                echo '<div class="test-item info">';
                foreach ($sheets as $sheet) {
                    $title = $sheet->getProperties()->getTitle();
                    echo '• ' . htmlspecialchars($title) . '<br>';
                }
                echo '</div>';
                
                // 업체관리 시트 확인
                if (function_exists('syncCompaniesFromGoogleSheets')) {
                    $syncResult = syncCompaniesFromGoogleSheets();
                    if ($syncResult['success']) {
                        $companies = $syncResult['data'];
                        echo '<div class="test-item success">✅ 업체관리 시트에서 ' . count($companies) . '개 업체 발견:</div>';
                        echo '<div class="test-item info">';
                        foreach (array_slice($companies, 0, 5) as $company) {
                            echo '• ' . htmlspecialchars($company['companyName']) . 
                                 ' (배송: ' . htmlspecialchars($company['deliveryDay']) . 
                                 ', 품목: ' . count($company['items']) . '개)<br>';
                        }
                        if (count($companies) > 5) {
                            echo '• ... 및 ' . (count($companies) - 5) . '개 더<br>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="test-item error">❌ 업체 데이터 동기화 실패: ' . htmlspecialchars($syncResult['message']) . '</div>';
                    }
                } else {
                    echo '<div class="test-item error">❌ syncCompaniesFromGoogleSheets 함수를 찾을 수 없습니다.</div>';
                }
                
                // 품목리스트 시트 확인 (새로 추가)
                if (function_exists('syncItemsFromGoogleSheets')) {
                    $itemsResult = syncItemsFromGoogleSheets();
                    if ($itemsResult['success']) {
                        $categories = $itemsResult['data'];
                        echo '<div class="test-item success">✅ 품목리스트 시트에서 ' . count($categories) . '개 카테고리 발견:</div>';
                        echo '<div class="test-item info">';
                        foreach (array_slice($categories, 0, 3) as $category) {
                            echo '• ' . htmlspecialchars($category['name']) . 
                                 ' (' . htmlspecialchars($category['description']) . 
                                 ', 품목: ' . count($category['items']) . '개)<br>';
                        }
                        if (count($categories) > 3) {
                            echo '• ... 및 ' . (count($categories) - 3) . '개 더<br>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="test-item error">❌ 품목리스트 시트 읽기 실패: ' . htmlspecialchars($itemsResult['message']) . '</div>';
                    }
                } else {
                    echo '<div class="test-item error">❌ syncItemsFromGoogleSheets 함수를 찾을 수 없습니다.</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="test-item error">❌ 스프레드시트 구조 확인 실패: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<div class="error-details">오류 상세: ' . htmlspecialchars($e->getTraceAsString()) . '</div>';
            }
        } else {
            echo '<div class="test-item error">❌ Google Sheets 서비스를 초기화할 수 없습니다.</div>';
        }
        ?>
        
        <h2>4. 데이터베이스 연결 테스트</h2>
        
        <?php
        try {
            if (function_exists('getDBConnection')) {
                $pdo = getDBConnection();
                echo '<div class="test-item success">✅ 데이터베이스 연결 성공</div>';
                
                // 테이블 존재 확인
                $tables = ['companies', 'company_items', 'company_details', 'order_status', 'order_logs', 'registration_requests', 'categories', 'items'];
                $stmt = $pdo->query("SHOW TABLES");
                $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tables as $table) {
                    if (in_array($table, $existingTables)) {
                        // 테이블 행 수 확인
                        $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                        $count = $countStmt->fetchColumn();
                        echo '<div class="test-item success">✅ 테이블 ' . $table . ' 존재 (' . $count . '행)</div>';
                    } else {
                        echo '<div class="test-item error">❌ 테이블 ' . $table . ' 없음</div>';
                    }
                }
                
            } else {
                echo '<div class="test-item error">❌ getDBConnection 함수를 찾을 수 없습니다.</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="test-item error">❌ 데이터베이스 연결 실패: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <h2>5. 함수 존재 확인</h2>
        
        <?php
        $requiredFunctions = [
            'loadCompaniesData',
            'processOrder',
            'findCompanyByPassword',
            'getSheetsService',
            'testGoogleSheetsConnection',
            'syncCompaniesFromGoogleSheets',
            'updateGoogleSheets',
            'syncItemsFromGoogleSheets'  // 새로 추가
        ];
        
        foreach ($requiredFunctions as $funcName) {
            if (function_exists($funcName)) {
                echo '<div class="test-item success">✅ 함수 ' . $funcName . ' 존재</div>';
            } else {
                echo '<div class="test-item error">❌ 함수 ' . $funcName . ' 없음</div>';
            }
        }
        ?>
        
        <h2>6. 테스트 도구</h2>
        
        <button class="btn" onclick="testBasicConnection()">기본 연결 테스트</button>
        <button class="btn" onclick="testDataSync()">데이터 동기화 테스트</button>
        <button class="btn btn-warning" onclick="syncItems()">품목리스트 동기화</button>
        <button class="btn" onclick="refreshCache()">캐시 갱신</button>
        
        <div id="testResult"></div>
        
        <h2>7. 설정 정보</h2>
        
        <div class="test-item info">
            <strong>스프레드시트 ID:</strong> <?= defined('SPREADSHEET_ID') ? htmlspecialchars(SPREADSHEET_ID) : '정의되지 않음' ?><br>
            <strong>서비스 계정 파일:</strong> <?= defined('GOOGLE_CREDENTIALS_PATH') ? htmlspecialchars(GOOGLE_CREDENTIALS_PATH) : '정의되지 않음' ?><br>
            <strong>캐시 디렉토리:</strong> <?= defined('CACHE_DIR') ? htmlspecialchars(CACHE_DIR) : '정의되지 않음' ?><br>
            <strong>업체관리 시트:</strong> <?= defined('SHEET_COMPANY_MANAGEMENT') ? htmlspecialchars(SHEET_COMPANY_MANAGEMENT) : '정의되지 않음' ?><br>
            <strong>업체정보 시트:</strong> <?= defined('SHEET_COMPANY_INFO') ? htmlspecialchars(SHEET_COMPANY_INFO) : '정의되지 않음' ?><br>
            <strong>품목리스트 시트:</strong> <?= defined('SHEET_ITEM_LIST') ? htmlspecialchars(SHEET_ITEM_LIST) : '정의되지 않음' ?><br>
            <strong>PHP 버전:</strong> <?= PHP_VERSION ?><br>
            <strong>현재 시간:</strong> <?= date('Y-m-d H:i:s') ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">메인 주문 시스템으로 이동</a>
        </div>
    </div>

    <script>
        function testBasicConnection() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="test-item info">기본 연결 테스트 중...</div>';
            
            fetch('test-functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'testConnection' })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    resultDiv.innerHTML = '<div class="test-item success">✅ ' + result.message + '</div>';
                    if (result.details) {
                        let detailsHtml = '';
                        for (const [key, detail] of Object.entries(result.details)) {
                            const statusClass = detail.success ? 'success' : 'error';
                            const icon = detail.success ? '✅' : '❌';
                            detailsHtml += `<div class="test-item ${statusClass}">${icon} ${key}: ${detail.message}</div>`;
                        }
                        resultDiv.innerHTML += detailsHtml;
                    }
                } else {
                    resultDiv.innerHTML = '<div class="test-item error">❌ ' + result.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="test-item error">❌ 오류: ' + error.message + '</div>';
            });
        }
        
        function testDataSync() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="test-item info">데이터 동기화 테스트 중...</div>';
            
            fetch('test-functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'syncSheets' })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    resultDiv.innerHTML = '<div class="test-item success">✅ ' + result.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-item error">❌ ' + result.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="test-item error">❌ 오류: ' + error.message + '</div>';
            });
        }
        
        function syncItems() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="test-item info">품목리스트 동기화 중...</div>';
            
            fetch('test-functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'syncItems' })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    let message = result.message;
                    if (result.categoryCount && result.itemCount) {
                        message += ` (카테고리: ${result.categoryCount}개, 품목: ${result.itemCount}개)`;
                    }
                    resultDiv.innerHTML = '<div class="test-item success">✅ ' + message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-item error">❌ ' + result.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="test-item error">❌ 오류: ' + error.message + '</div>';
            });
        }
        
        function refreshCache() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<div class="test-item info">캐시 갱신 중...</div>';
            
            fetch('test-functions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'refreshCache' })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    resultDiv.innerHTML = '<div class="test-item success">✅ ' + result.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-item error">❌ ' + result.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="test-item error">❌ 오류: ' + error.message + '</div>';
            });
        }
    </script>
</body>
</html>