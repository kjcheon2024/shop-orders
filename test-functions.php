<?php
// test-functions.php - 테스트 및 관리 기능 API
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'google-sheets.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'testOrder':
            echo json_encode(handleTestOrder($input['data'] ?? []));
            break;
            
        case 'refreshCache':
            echo json_encode(handleRefreshCache());
            break;
            
        case 'syncSheets':
            echo json_encode(handleSyncSheets());
            break;
            
        case 'syncItems':
            echo json_encode(handleSyncItems());
            break;
            
        case 'getCompanyOrders':
            echo json_encode(handleGetCompanyOrders($input['companyName'] ?? ''));
            break;
            
        case 'testConnection':
            echo json_encode(handleTestConnection());
            break;
            
        case 'clearAllCache':
            echo json_encode(handleClearAllCache());
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '알 수 없는 액션입니다.']);
    }
} catch (Exception $e) {
    error_log("Test API 오류: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => '처리 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}

/**
 * 테스트 주문 처리
 */
function handleTestOrder($orderData) {
    try {
        if (empty($orderData['companyName']) || empty($orderData['orders'])) {
            return ['success' => false, 'message' => '테스트 데이터가 올바르지 않습니다.'];
        }
        
        // 임시로 세션 설정 (테스트용)
        $_SESSION['logged_in'] = true;
        $_SESSION['company_name'] = $orderData['companyName'];
        $_SESSION['login_time'] = time();
        
        $result = processOrderWithSheets($orderData);
        
        // 테스트 후 세션 정리
        unset($_SESSION['logged_in']);
        unset($_SESSION['company_name']);
        unset($_SESSION['login_time']);
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '테스트 주문 실패: ' . $e->getMessage()];
    }
}

/**
 * 캐시 갱신 처리
 */
function handleRefreshCache() {
    try {
        clearCache(); // 모든 캐시 삭제
        loadCompaniesData(); // 새로 로드하여 캐시 생성
        
        return ['success' => true, 'message' => '캐시가 성공적으로 갱신되었습니다.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '캐시 갱신 실패: ' . $e->getMessage()];
    }
}

/**
 * 품목리스트 동기화 처리 (새로 추가된 함수)
 */
function handleSyncItems() {
    try {
        // Google Sheets에서 품목 데이터 가져오기
        $sheetsResult = syncItemsFromGoogleSheets();
        
        if (!$sheetsResult['success']) {
            return $sheetsResult;
        }
        
        $categoriesData = $sheetsResult['data'];
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // 기존 데이터 백업을 위해 비활성화만 (삭제하지 않음)
            $pdo->exec("UPDATE categories SET active = 0");
            $pdo->exec("UPDATE items SET active = 0");
            
            $categoryCount = 0;
            $itemCount = 0;
            
            foreach ($categoriesData as $categoryData) {
                // 카테고리 삽입 또는 업데이트
                $stmt = $pdo->prepare("
                    INSERT INTO categories (category_name, description, display_order, active) 
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                    description = VALUES(description), 
                    display_order = VALUES(display_order), 
                    active = 1,
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    $categoryData['name'],
                    $categoryData['description'],
                    $categoryData['order']
                ]);
                
                // 카테고리 ID 가져오기
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
                $stmt->execute([$categoryData['name']]);
                $categoryId = $stmt->fetchColumn();
                
                if (!$categoryId) {
                    error_log("카테고리 ID를 찾을 수 없음: " . $categoryData['name']);
                    continue;
                }
                
                $categoryCount++;
                
                // 품목들 삽입
                foreach ($categoryData['items'] as $itemData) {
                    $stmt = $pdo->prepare("
                        INSERT INTO items (item_name, category_id, description, display_order, active) 
                        VALUES (?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                        description = VALUES(description), 
                        display_order = VALUES(display_order), 
                        category_id = VALUES(category_id),
                        active = 1,
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([
                        $itemData['name'],
                        $categoryId,
                        $itemData['description'],
                        $itemData['order']
                    ]);
                    
                    $itemCount++;
                }
            }
            
            $pdo->commit();
            
            // 동기화 결과 로그 기록
            error_log("품목리스트 동기화 완료: {$categoryCount}개 카테고리, {$itemCount}개 품목");
            
            return [
                'success' => true,
                'message' => "품목리스트 동기화 완료: {$categoryCount}개 카테고리, {$itemCount}개 품목",
                'categoryCount' => $categoryCount,
                'itemCount' => $itemCount
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("품목 동기화 실패: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 동기화 실패: ' . $e->getMessage()];
    }
}

/**
 * Google Sheets 동기화 처리 (수정됨)
 */
function handleSyncSheets() {
    try {
        $syncResult = syncCompaniesFromGoogleSheets();
        
        if (!$syncResult['success']) {
            return $syncResult;
        }
        
        $companiesData = $syncResult['data'];
        $syncCount = 0;
        $updateCount = 0;
        $newCount = 0;
        
        // MySQL 데이터베이스에 업체 정보 동기화
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // 업체정보 시트에서 추가 정보 가져오기
            $companyDetailsMap = getCompanyDetailsFromSheets();
            
            foreach ($companiesData as $companyData) {
                // 먼저 기존 업체가 있는지 확인
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ?");
                $stmt->execute([$companyData['companyName']]);
                $existingCompany = $stmt->fetch();
                
                if ($existingCompany) {
                    // 기존 업체 정보 업데이트 (그룹명, 비밀번호, 배송요일 포함)
                    $companyId = $existingCompany['id'];
                    $itemGroup = '';
                    $password = 'temp_' . substr(md5($companyData['companyName']), 0, 8); // 기본값
                    $deliveryDay = $companyData['deliveryDay'];
                    
                    // 업체정보 시트에서 실제 정보 가져오기
                    if (isset($companyDetailsMap[$companyData['companyName']])) {
                        $details = $companyDetailsMap[$companyData['companyName']];
                        if (!empty($details['item_group'])) {
                            $itemGroup = $details['item_group'];
                        }
                        if (!empty($details['password'])) {
                            $password = $details['password'];
                        }
                        if (!empty($details['delivery_day'])) {
                            $deliveryDay = $details['delivery_day'];
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE companies 
                        SET item_group = ?, password = ?, delivery_day = ?, active = 1, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$itemGroup, $password, $deliveryDay, $companyId]);
                    
                    // company_details 테이블도 업데이트 (추가됨)
                    if (isset($companyDetailsMap[$companyData['companyName']])) {
                        $details = $companyDetailsMap[$companyData['companyName']];
                        
                        // 기존 company_details 있는지 확인
                        $stmt = $pdo->prepare("SELECT id FROM company_details WHERE company_id = ?");
                        $stmt->execute([$companyId]);
                        $existingDetails = $stmt->fetch();
                        
                        if ($existingDetails) {
                            // 기존 상세정보 업데이트
                            $stmt = $pdo->prepare("
                                UPDATE company_details 
                                SET company_name = ?, zip_code = ?, company_address = ?, 
                                    contact_person = ?, phone_number = ?, email = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE company_id = ?
                            ");
                            $stmt->execute([
                                $companyData['companyName'],
                                $details['zip_code'] ?? '',
                                $details['company_address'] ?? '',
                                $details['contact_person'] ?? '',
                                $details['phone_number'] ?? '',
                                $details['email'] ?? '',
                                $companyId
                            ]);
                        } else {
                            // 새 상세정보 삽입
                            $stmt = $pdo->prepare("
                                INSERT INTO company_details (company_id, company_name, zip_code, company_address, contact_person, phone_number, email) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $companyId,
                                $companyData['companyName'],
                                $details['zip_code'] ?? '',
                                $details['company_address'] ?? '',
                                $details['contact_person'] ?? '',
                                $details['phone_number'] ?? '',
                                $details['email'] ?? ''
                            ]);
                        }
                    }
                    
                    $updateCount++;
                } else {
                    // 새 업체 추가
                    $itemGroup = '';
                    $password = 'temp_' . substr(md5($companyData['companyName']), 0, 8); // 기본값
                    $deliveryDay = $companyData['deliveryDay'];
                    
                    // 업체정보 시트에서 실제 정보 가져오기
                    if (isset($companyDetailsMap[$companyData['companyName']])) {
                        $details = $companyDetailsMap[$companyData['companyName']];
                        if (!empty($details['item_group'])) {
                            $itemGroup = $details['item_group'];
                        }
                        if (!empty($details['password'])) {
                            $password = $details['password'];
                        }
                        if (!empty($details['delivery_day'])) {
                            $deliveryDay = $details['delivery_day'];
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO companies (company_name, item_group, password, delivery_day, active) 
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $companyData['companyName'],
                        $itemGroup,
                        $password,
                        $deliveryDay
                    ]);
                    $companyId = $pdo->lastInsertId();
                    
                    // company_details 추가
                    if (isset($companyDetailsMap[$companyData['companyName']])) {
                        $details = $companyDetailsMap[$companyData['companyName']];
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO company_details (company_id, company_name, zip_code, company_address, contact_person, phone_number, email) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $companyId,
                            $companyData['companyName'],
                            $details['zip_code'] ?? '',
                            $details['company_address'] ?? '',
                            $details['contact_person'] ?? '',
                            $details['phone_number'] ?? '',
                            $details['email'] ?? ''
                        ]);
                    }
                    
                    $newCount++;
                }
                
                // 기존 품목 삭제
                $stmt = $pdo->prepare("DELETE FROM company_items WHERE company_id = ?");
                $stmt->execute([$companyId]);
                
                // 새 품목 삽입
                $stmt = $pdo->prepare("
                    INSERT INTO company_items (company_id, item_name, item_order, active) 
                    VALUES (?, ?, ?, 1)
                ");
                
                foreach ($companyData['items'] as $index => $item) {
                    if (!empty(trim($item))) {
                        $stmt->execute([$companyId, trim($item), $index + 1]);
                    }
                }
                
                $syncCount++;
            }
            
            $pdo->commit();
            
            // 캐시 갱신
            clearCache();
            
            return [
                'success' => true, 
                'message' => "Google Sheets에서 {$syncCount}개 업체 정보를 MySQL로 동기화했습니다. (신규: {$newCount}개, 업데이트: {$updateCount}개)"
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Google Sheets 동기화 실패: ' . $e->getMessage()];
    }
}

/**
 * 업체정보 시트에서 상세 정보 가져오기 (컬럼 순서 수정)
 */
function getCompanyDetailsFromSheets() {
    try {
        $service = getSheetsService();
        $sheetName = SHEET_COMPANY_INFO;
        
        // A:업체명, B:그룹명, C:비밀번호, D:배송요일, E:우편번호, F:주소, G:담당자, H:전화번호, I:이메일
        $range = "{$sheetName}!A:I";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        $detailsMap = [];
        
        if ($values && count($values) > 1) {
            // 2행부터 데이터 (1행은 헤더)
            for ($i = 1; $i < count($values); $i++) {
                $row = $values[$i];
                if (!empty($row[0])) { // 업체명이 있는 경우만
                    $companyName = trim($row[0]);
                    $detailsMap[$companyName] = [
                        'item_group' => isset($row[1]) ? trim($row[1]) : '',        // B: 그룹명
                        'password' => isset($row[2]) ? trim($row[2]) : '',          // C: 비밀번호  
                        'delivery_day' => isset($row[3]) ? trim($row[3]) : '요일미정', // D: 배송요일
                        'zip_code' => isset($row[4]) ? trim($row[4]) : '',          // E: 우편번호
                        'company_address' => isset($row[5]) ? trim($row[5]) : '',   // F: 주소
                        'contact_person' => isset($row[6]) ? trim($row[6]) : '',    // G: 담당자
                        'phone_number' => isset($row[7]) ? trim($row[7]) : '',      // H: 전화번호
                        'email' => isset($row[8]) ? trim($row[8]) : ''              // I: 이메일
                    ];
                }
            }
        }
        
        return $detailsMap;
        
    } catch (Exception $e) {
        error_log("업체정보 시트 읽기 오류: " . $e->getMessage());
        return [];
    }
}

/**
 * 업체 주문 현황 조회
 */
function handleGetCompanyOrders($companyName) {
    try {
        if (empty($companyName)) {
            return ['success' => false, 'message' => '업체명을 입력해주세요.'];
        }
        
        // Google Sheets에서 현재 주문 현황 조회
        $sheetsResult = getCompanyCurrentOrderFromSheets($companyName);
        
        // MySQL에서도 조회
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT item_name, quantity, order_date 
            FROM order_status 
            WHERE company_name = ? AND DATE(order_date) = CURDATE()
            ORDER BY item_name
        ");
        $stmt->execute([$companyName]);
        $dbOrders = $stmt->fetchAll();
        
        return [
            'success' => true,
            'companyName' => $companyName,
            'googleSheets' => $sheetsResult,
            'database' => $dbOrders,
            'message' => '주문 현황 조회 완료'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '주문 현황 조회 실패: ' . $e->getMessage()];
    }
}

/**
 * 전체 연결 테스트
 */
function handleTestConnection() {
    try {
        $results = [];
        
        // 1. 데이터베이스 연결 테스트
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
            $companyCount = $stmt->fetch()['count'];
            $results['database'] = [
                'success' => true,
                'message' => "데이터베이스 연결 성공 ({$companyCount}개 업체)"
            ];
        } catch (Exception $e) {
            $results['database'] = [
                'success' => false,
                'message' => '데이터베이스 연결 실패: ' . $e->getMessage()
            ];
        }
        
        // 2. Google Sheets 연결 테스트
        $results['googleSheets'] = testGoogleSheetsConnection();
        
        // 3. 캐시 테스트
        try {
            $companiesData = loadCompaniesData();
            $results['cache'] = [
                'success' => true,
                'message' => '캐시 동작 정상 (' . count($companiesData) . '개 업체 캐시됨)'
            ];
        } catch (Exception $e) {
            $results['cache'] = [
                'success' => false,
                'message' => '캐시 오류: ' . $e->getMessage()
            ];
        }
        
        // 4. 전체 상태 판단
        $allSuccess = $results['database']['success'] && 
                     $results['googleSheets']['success'] && 
                     $results['cache']['success'];
        
        return [
            'success' => $allSuccess,
            'message' => $allSuccess ? '모든 연결이 정상입니다.' : '일부 연결에 문제가 있습니다.',
            'details' => $results
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '연결 테스트 실패: ' . $e->getMessage()];
    }
}

/**
 * 모든 캐시 삭제
 */
function handleClearAllCache() {
    try {
        clearCache();
        return ['success' => true, 'message' => '모든 캐시가 삭제되었습니다.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '캐시 삭제 실패: ' . $e->getMessage()];
    }
}
?>