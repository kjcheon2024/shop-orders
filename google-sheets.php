<?php
// google-sheets.php - Google Sheets API 완전 연동 (최종 버전)
require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

/**
 * Google Sheets 클라이언트 초기화
 */
function getGoogleSheetsClient() {
    static $client = null;
    
    if ($client === null) {
        try {
            $client = new Google_Client();
            $client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
            $client->addScope(Google_Service_Sheets::SPREADSHEETS);
            $client->setApplicationName('Order System');
            
            // 인증 정보 확인
            if (!file_exists(GOOGLE_CREDENTIALS_PATH)) {
                throw new Exception('서비스 계정 인증 파일이 없습니다: ' . GOOGLE_CREDENTIALS_PATH);
            }
            
        } catch (Exception $e) {
            error_log("Google Client 초기화 오류: " . $e->getMessage());
            throw $e;
        }
    }
    
    return $client;
}

/**
 * Google Sheets 서비스 가져오기
 */
function getSheetsService() {
    static $service = null;
    
    if ($service === null) {
        try {
            $client = getGoogleSheetsClient();
            $service = new Google_Service_Sheets($client);
        } catch (Exception $e) {
            error_log("Google Sheets 서비스 초기화 오류: " . $e->getMessage());
            throw $e;
        }
    }
    
    return $service;
}

/**
 * 품목리스트 시트에서 카테고리와 품목 정보 가져오기
 */
function syncItemsFromGoogleSheets() {
    try {
        $service = getSheetsService();
        $sheetName = SHEET_ITEM_LIST; // '품목리스트'
        
        // 전체 데이터 읽기 (A열부터 충분한 범위까지)
        $range = "{$sheetName}!A1:Z100";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        if (!$values || count($values) < 2) {
            return ['success' => false, 'message' => '품목리스트 시트에 데이터가 없습니다.'];
        }
        
        $categories = [];
        $categoryOrder = 0;
        
        error_log("품목리스트 시트 읽기 시작 - 총 " . count($values) . "행, " . count($values[0]) . "열");
        
        // 홀수 열(A,C,E,G...)에서 카테고리와 품목 추출
        for ($col = 0; $col < count($values[0]); $col += 2) {
            // 카테고리명이 있는지 확인 (1행)
            if (isset($values[0][$col]) && !empty(trim($values[0][$col]))) {
                $categoryName = trim($values[0][$col]);
                $categoryDesc = isset($values[0][$col + 1]) ? trim($values[0][$col + 1]) : '';
                
                error_log("카테고리 발견: '{$categoryName}' (설명: '{$categoryDesc}')");
                
                $items = [];
                $itemOrder = 0;
                
                // 2행부터 품목들 추출
                for ($row = 1; $row < count($values); $row++) {
                    if (isset($values[$row][$col]) && !empty(trim($values[$row][$col]))) {
                        $itemName = trim($values[$row][$col]);
                        $itemDesc = isset($values[$row][$col + 1]) ? trim($values[$row][$col + 1]) : '';
                        
                        $items[] = [
                            'name' => $itemName,
                            'description' => $itemDesc,
                            'order' => $itemOrder++
                        ];
                        
                        error_log("  품목 추가: '{$itemName}' (설명: '{$itemDesc}')");
                    }
                }
                
                if (!empty($items)) {
                    $categories[] = [
                        'name' => $categoryName,
                        'description' => $categoryDesc,
                        'order' => $categoryOrder++,
                        'items' => $items
                    ];
                    
                    error_log("카테고리 '{$categoryName}' 완료 - {$itemOrder}개 품목");
                }
            }
        }
        
        $totalItems = array_sum(array_map(function($cat) { return count($cat['items']); }, $categories));
        
        return [
            'success' => true,
            'data' => $categories,
            'message' => count($categories) . '개 카테고리, ' . $totalItems . '개 품목 발견'
        ];
        
    } catch (Exception $e) {
        error_log("품목리스트 시트 읽기 오류: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => '품목리스트 시트 읽기 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 활성화된 시트명들 가져오기 (동적 관리)
 */
function getActiveSheetNames() {
    try {
        $pdo = getDBConnection();
        
        // 간단하게: 테이블에 있는 모든 시트명을 가져옴 (삭제되지 않은 것들)
        $stmt = $pdo->query("
            SELECT sheet_name 
            FROM sheet_configs 
            ORDER BY sheet_name ASC
        ");
        $sheets = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 기본값이 없으면 빈 배열 반환 (관리자가 설정해야 함)
        if (empty($sheets)) {
            error_log("Warning: No sheets configured. Please add sheets in admin panel.");
            return [];
        }
        
        return $sheets;
    } catch (Exception $e) {
        error_log("Get active sheet names error: " . $e->getMessage());
        // 오류 시 빈 배열 반환 (관리자가 설정해야 함)
        return [];
    }
}

/**
 * 활성화된 그룹명들 가져오기 (동적 관리)
 */
function getActiveGroupNames() {
    try {
        $pdo = getDBConnection();
        
        // 간단하게: 테이블에 있는 모든 그룹명을 가져옴 (삭제되지 않은 것들)
        $stmt = $pdo->query("
            SELECT group_name 
            FROM item_groups 
            ORDER BY group_name ASC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 기본값이 없으면 기존 하드코딩된 값들 사용
        if (empty($groups)) {
            return ['도야짬뽕', '기타업체', '업체명'];
        }
        
        return $groups;
    } catch (Exception $e) {
        error_log("Get active group names error: " . $e->getMessage());
        // 오류 시 기본값 사용
        return ['도야짬뽕', '기타업체', '업체명'];
    }
}

/**
 * 업체가 어느 시트에 있는지 찾기 (주문용 시트) - 그룹 정보 포함
 */
function findCompanyInSheets($service, $companyName) {
    // 데이터베이스에서 활성화된 시트명들 가져오기
    $sheetNames = getActiveSheetNames();
    
    error_log("업체 '{$companyName}' 검색 시작 - 검색할 시트들: " . json_encode($sheetNames));
    
    foreach ($sheetNames as $sheetName) {
        try {
            error_log("시트 '{$sheetName}'에서 업체 '{$companyName}' 검색 중...");
            
            // A열 전체 읽기 (업체명 열)
            $range = "{$sheetName}!A:A";
            $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
            $values = $response->getValues();
            
            if (!$values) {
                error_log("시트 '{$sheetName}'에 데이터가 없음");
                continue;
            }
            
            error_log("시트 '{$sheetName}'에서 " . count($values) . "행 발견");
            
            // 3번째 행부터 검색 (1,2행은 헤더)
            for ($i = 2; $i < count($values); $i++) {
                if (isset($values[$i][0])) {
                    $cellValue = trim($values[$i][0]);
                    error_log("행 {$i}: '{$cellValue}' vs '{$companyName}'");
                    
                    if ($cellValue === trim($companyName)) {
                        error_log("업체 '{$companyName}' 발견! 시트: {$sheetName}, 행: " . ($i + 1));
                        
                        // 해당 업체가 속한 그룹 찾기
                        $itemGroup = findCompanyItemGroup($service, $sheetName, $i + 1);
                        
                        return [
                            'sheetName' => $sheetName,
                            'row' => $i + 1, // 1-based 행 번호
                            'foundAt' => $i,
                            'itemGroup' => $itemGroup // 추가
                        ];
                    }
                }
            }
            
            error_log("시트 '{$sheetName}'에서 업체 '{$companyName}'를 찾지 못함");
            
        } catch (Exception $e) {
            error_log("시트 '{$sheetName}' 검색 오류: " . $e->getMessage());
            continue;
        }
    }
    
    error_log("모든 시트에서 업체 '{$companyName}'를 찾지 못함");
    return null;
}

/**
 * 업체가 속한 품목 그룹 찾기 (특별한 매칭 로직 포함)
 */
function findCompanyItemGroup($service, $sheetName, $companyRow) {
    try {
        // 데이터베이스에서 활성화된 그룹명들 가져오기
        $groupKeywords = getActiveGroupNames();
        
        // A열 전체 읽기
        $range = "{$sheetName}!A:A";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        if (!$values) return null;
        
        // 업체 행보다 위에 있는 그룹 키워드 찾기 (역순으로 검색)
        for ($i = $companyRow - 2; $i >= 0; $i--) { // companyRow는 1-based이므로 -2
            if (isset($values[$i][0])) {
                $cellValue = trim($values[$i][0]);
                if (in_array($cellValue, $groupKeywords)) {
                    // 특별한 로직: 기타업체 + 요일미정 시트 → 업체명 헤더 사용
                    $finalGroupName = $cellValue;
                    if ($cellValue === '기타업체' && $sheetName === SHEET_ORDER_NEXT_DAY) {
                        // 요일미정 시트에서 "업체명" 키워드 행 찾기
                        $companyNameHeaderRow = findHeaderRowByKeyword($service, $sheetName, '업체명');
                        if ($companyNameHeaderRow) {
                            $finalGroupName = '업체명';
                            return [
                                'groupName' => $finalGroupName,
                                'headerRow' => $companyNameHeaderRow
                            ];
                        }
                    }
                    
                    return [
                        'groupName' => $finalGroupName,
                        'headerRow' => $i + 1 // 1-based 행 번호
                    ];
                }
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("품목 그룹 찾기 오류: " . $e->getMessage());
        return null;
    }
}

/**
 * 특정 키워드의 헤더 행 찾기
 */
function findHeaderRowByKeyword($service, $sheetName, $keyword) {
    try {
        // A열 전체 읽기
        $range = "{$sheetName}!A:A";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        if (!$values) return null;
        
        // 키워드가 있는 행 찾기
        for ($i = 0; $i < count($values); $i++) {
            if (isset($values[$i][0]) && trim($values[$i][0]) === trim($keyword)) {
                return $i + 1; // 1-based 행 번호
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("헤더 행 찾기 오류 ({$keyword}): " . $e->getMessage());
        return null;
    }
}

/**
 * 특정 그룹의 품목 헤더 가져오기 (두 줄 헤더 지원)
 */
function getGroupItemHeaders($service, $sheetName, $headerRow) {
    try {
        // 더 넓은 범위로 읽어서 두 줄 헤더 패턴 감지
        $range = "{$sheetName}!{$headerRow}:" . ($headerRow + 2);
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        if (empty($values)) {
            return [];
        }
        
        // 첫 번째 행만 있는 경우 (한 줄 헤더)
        if (count($values) == 1) {
            $headers = $values[0] ?? [];
            error_log("한 줄 헤더 감지: " . json_encode($headers));
            return $headers;
        }
        
        // 두 줄 이상인 경우 두 줄 헤더 패턴 확인
        $firstRow = $values[0] ?? [];
        $secondRow = $values[1] ?? [];
        
        // 두 줄 헤더 패턴 감지: 두 번째 행에 빈 셀이 있거나 연속된 텍스트가 있는 경우
        $isTwoLineHeader = false;
        $hasSecondRowContent = false;
        
        for ($i = 0; $i < max(count($firstRow), count($secondRow)); $i++) {
            $firstPart = trim($firstRow[$i] ?? '');
            $secondPart = trim($secondRow[$i] ?? '');
            
            if ($secondPart) {
                $hasSecondRowContent = true;
                // 첫 번째 행이 비어있고 두 번째 행에 내용이 있으면 두 줄 헤더
                if (empty($firstPart)) {
                    $isTwoLineHeader = true;
                    break;
                }
            }
        }
        
        // 두 줄 헤더인 경우 합치기
        if ($isTwoLineHeader || $hasSecondRowContent) {
            $headers = [];
            
            for ($i = 0; $i < max(count($firstRow), count($secondRow)); $i++) {
                $firstPart = trim($firstRow[$i] ?? '');
                $secondPart = trim($secondRow[$i] ?? '');
                
                if ($firstPart && $secondPart) {
                    // 두 줄로 된 헤더: 합치기 (공백 없이)
                    $headers[] = $firstPart . $secondPart;
                } elseif ($firstPart) {
                    // 한 줄 헤더
                    $headers[] = $firstPart;
                } elseif ($secondPart) {
                    // 두 번째 줄만 있는 경우
                    $headers[] = $secondPart;
                } else {
                    $headers[] = '';
                }
            }
            
            error_log("두 줄 헤더 감지 및 처리: " . json_encode($headers));
            return $headers;
        }
        
        // 한 줄 헤더인 경우
        $headers = $firstRow;
        error_log("한 줄 헤더로 처리: " . json_encode($headers));
        return $headers;
        
    } catch (Exception $e) {
        error_log("그룹 품목 헤더 가져오기 오류 ({$sheetName}, 행 {$headerRow}): " . $e->getMessage());
        return [];
    }
}

/**
 * MySQL 데이터베이스 업데이트 (요일미정 반영)
 */
function updateMySQLDatabase($companyName, $orders) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 업체 정보 조회
        $stmt = $pdo->prepare("SELECT id, delivery_day FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            throw new Exception("업체 정보를 찾을 수 없습니다: {$companyName}");
        }
        
        $companyId = $company['id'];
        $deliveryDay = $company['delivery_day'] ?? '요일미정';
        
        // 당일 기존 주문 삭제 (더 안전한 범위로 확장)
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("DELETE FROM order_status WHERE company_id = ? AND DATE(order_date) = ?");
        $stmt->execute([$companyId, $today]);
        
        // 추가 안전장치: 시간 범위 기반 삭제도 수행
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $currentHour = (int)$now->format('H');
        
        if ($currentHour < 8) {
            $startTime = clone $now;
            $startTime->modify('-1 day')->setTime(8, 0, 0);
            $endTime = clone $now;
            $endTime->setTime(5, 0, 0);
        } else {
            $startTime = clone $now;
            $startTime->setTime(8, 0, 0);
            $endTime = clone $now;
            $endTime->modify('+1 day')->setTime(5, 0, 0);
        }
        
        $stmt = $pdo->prepare("
            DELETE FROM order_status 
            WHERE company_id = ? 
            AND order_date >= ? 
            AND order_date < ?
        ");
        $stmt->execute([
            $companyId, 
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s')
        ]);
        
        // 새 주문 현황 삽입 (빈 배열인 경우 아무것도 삽입하지 않음)
        if (!empty($orders)) {
            $stmt = $pdo->prepare("
                INSERT INTO order_status (company_id, company_name, delivery_day, item_name, quantity, order_date)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            // 주문 로그 삽입
            $logStmt = $pdo->prepare("
                INSERT INTO order_logs (company_id, company_name, delivery_day, item_name, quantity, order_source, created_at)
                VALUES (?, ?, ?, ?, ?, '웹주문', NOW())
            ");
            
            foreach ($orders as $order) {
                if ($order['quantity'] > 0) {
                    // 주문 현황 업데이트
                    $stmt->execute([
                        $companyId,
                        $companyName,
                        $deliveryDay,
                        $order['item'],
                        $order['quantity']
                    ]);
                    
                    // 주문 로그 기록
                    $logStmt->execute([
                        $companyId,
                        $companyName,
                        $deliveryDay,
                        $order['item'],
                        $order['quantity']
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'MySQL 데이터베이스 업데이트 완료'
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("MySQL 업데이트 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'MySQL 업데이트 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * Google Sheets 업데이트 (그룹별 품목 매칭)
 */
function updateGoogleSheets($companyName, $orders) {
    try {
        $service = getSheetsService();
        
        error_log("=== 주문 처리 시작 ===");
        error_log("업체명: {$companyName}");
        error_log("주문 개수: " . count($orders));
        error_log("주문 데이터: " . json_encode($orders));
        
        // 1. 업체가 어느 시트에 있는지 찾기 (그룹 정보 포함)
        error_log("업체 검색 시작...");
        $sheetInfo = findCompanyInSheets($service, $companyName);
        
        if (!$sheetInfo) {
            error_log("업체를 찾을 수 없음: {$companyName}");
            return [
                'success' => false,
                'message' => "업체 '{$companyName}'를 Google Sheets에서 찾을 수 없습니다."
            ];
        }
        
        error_log("업체 발견: " . json_encode($sheetInfo));
        
        // 2. 업체가 속한 그룹의 품목 헤더 정보 가져오기
        if (!$sheetInfo['itemGroup']) {
            error_log("품목 그룹을 찾을 수 없음");
            return [
                'success' => false,
                'message' => "업체 '{$companyName}'의 품목 그룹을 찾을 수 없습니다."
            ];
        }
        
        $headerRow = $sheetInfo['itemGroup']['headerRow'];
        $groupName = $sheetInfo['itemGroup']['groupName'];
        $headers = getGroupItemHeaders($service, $sheetInfo['sheetName'], $headerRow);
        
        if (empty($headers)) {
            return [
                'success' => false,
                'message' => "시트 '{$sheetInfo['sheetName']}'의 '{$groupName}' 그룹 헤더를 읽을 수 없습니다."
            ];
        }
        
        // 3. 기존 주문 데이터 클리어 (해당 업체 행만)
        clearCompanyOrderRow($service, $sheetInfo, $headers);
        
        // 4. 새 주문 데이터 업데이트
        $updateResult = updateOrderInSheet($service, $sheetInfo, $headers, $orders, $groupName);
        
        if (!$updateResult['success']) {
            return $updateResult;
        }
        
        // 5. 주문 로그 시트에도 기록
        logOrderToSheet($service, $companyName, $orders);
        
        return [
            'success' => true,
            'message' => "Google Sheets 업데이트 완료 ({$sheetInfo['sheetName']}, {$groupName} 그룹, {$updateResult['updatedCount']}개 품목)"
        ];
        
    } catch (Exception $e) {
        error_log("Google Sheets 업데이트 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Google Sheets 업데이트 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 업체 행의 기존 주문 데이터 클리어
 */
function clearCompanyOrderRow($service, $sheetInfo, $headers) {
    try {
        // 업체명(A열)을 제외한 나머지 셀들을 빈 값으로 설정
        $clearData = [];
        
        // B열부터 마지막 헤더 열까지 클리어
        for ($col = 1; $col < count($headers); $col++) {
            $columnLetter = columnIndexToLetter($col);
            $cellRange = "{$sheetInfo['sheetName']}!{$columnLetter}{$sheetInfo['row']}";
            $clearData[] = [
                'range' => $cellRange,
                'values' => [['']]
            ];
        }
        
        if (!empty($clearData)) {
            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateValuesRequest();
            $batchUpdateRequest->setValueInputOption('RAW');
            $batchUpdateRequest->setData($clearData);
            
            $service->spreadsheets_values->batchUpdate(SPREADSHEET_ID, $batchUpdateRequest);
        }
        
    } catch (Exception $e) {
        error_log("주문 행 클리어 오류: " . $e->getMessage());
        // 클리어 실패해도 계속 진행
    }
}

/**
 * 시트에 주문 데이터 업데이트 (그룹별 품목 매칭)
 */
function updateOrderInSheet($service, $sheetInfo, $headers, $orders, $groupName = '') {
    try {
        $updates = [];
        $updatedCount = 0;
        
        error_log("주문 업데이트 시작 - 업체: {$sheetInfo['row']}행, 그룹: {$groupName}");
        error_log("사용 헤더: " . json_encode($headers));
        error_log("주문 데이터: " . json_encode($orders));
        
        foreach ($orders as $order) {
            if ($order['quantity'] <= 0) continue;
            
            $itemName = trim($order['item']);
            error_log("품목 '{$itemName}' 매칭 시도... (수량: {$order['quantity']})");
            
            // 품목에 해당하는 열 찾기
            $columnIndex = null;
            
            // 1. 정확 매칭 시도
            for ($i = 0; $i < count($headers); $i++) {
                $headerItem = trim($headers[$i]);
                error_log("헤더 비교: '{$itemName}' vs '{$headerItem}' (인덱스: {$i})");
                
                if ($headerItem === $itemName) {
                    $columnIndex = $i;
                    error_log("품목 '{$itemName}' 정확 매칭 성공! 열 인덱스: {$columnIndex}");
                    break;
                }
            }
            
            // 2. 정확 매칭이 실패하면 부분 매칭 시도 (양방향)
            if ($columnIndex === null) {
                error_log("정확 매칭 실패, 부분 매칭 시도...");
                for ($i = 0; $i < count($headers); $i++) {
                    $headerItem = trim($headers[$i]);
                    if (!empty($headerItem)) {
                        // 주문 품목이 헤더에 포함되어 있는지 확인
                        if (strpos($headerItem, $itemName) !== false) {
                            $columnIndex = $i;
                            error_log("품목 '{$itemName}' 부분 매칭 성공! 열 인덱스: {$columnIndex} (헤더: '{$headerItem}')");
                            break;
                        }
                        // 헤더가 주문 품목에 포함되어 있는지 확인 (역방향)
                        if (strpos($itemName, $headerItem) !== false) {
                            $columnIndex = $i;
                            error_log("품목 '{$itemName}' 역방향 부분 매칭 성공! 열 인덱스: {$columnIndex} (헤더: '{$headerItem}')");
                            break;
                        }
                    }
                }
            }
            
            // 3. 여전히 매칭이 안되면 유사도 기반 매칭 시도
            if ($columnIndex === null) {
                error_log("부분 매칭도 실패, 유사도 기반 매칭 시도...");
                $bestMatch = null;
                $bestScore = 0;
                
                for ($i = 0; $i < count($headers); $i++) {
                    $headerItem = trim($headers[$i]);
                    if (!empty($headerItem)) {
                        // 간단한 유사도 계산 (공통 문자 개수 기반)
                        $similarity = similar_text($itemName, $headerItem);
                        $maxLength = max(strlen($itemName), strlen($headerItem));
                        $score = $maxLength > 0 ? $similarity / $maxLength : 0;
                        
                        error_log("유사도 계산: '{$itemName}' vs '{$headerItem}' = {$score}");
                        
                        if ($score > $bestScore && $score > 0.6) { // 60% 이상 유사도
                            $bestMatch = $i;
                            $bestScore = $score;
                        }
                    }
                }
                
                if ($bestMatch !== null) {
                    $columnIndex = $bestMatch;
                    error_log("품목 '{$itemName}' 유사도 매칭 성공! 열 인덱스: {$columnIndex} (유사도: {$bestScore})");
                }
            }
            
            if ($columnIndex !== null && $columnIndex > 0) { // A열(업체명)은 제외
                $columnLetter = columnIndexToLetter($columnIndex);
                $cellRange = "{$sheetInfo['sheetName']}!{$columnLetter}{$sheetInfo['row']}";
                
                error_log("셀 업데이트: {$cellRange} = {$order['quantity']}");
                
                $updates[] = [
                    'range' => $cellRange,
                    'values' => [[$order['quantity']]]
                ];
                $updatedCount++;
            } else {
                error_log("품목 '{$itemName}' 매칭 완전 실패 - 헤더에서 찾을 수 없음");
                
                // 디버깅: 모든 헤더 출력
                error_log("사용 가능한 헤더들:");
                foreach ($headers as $idx => $header) {
                    error_log("  인덱스 {$idx}: '{$header}'");
                }
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => false,
                'message' => "업데이트할 품목이 없습니다. (그룹: {$groupName})"
            ];
        }
        
        // 배치 업데이트 실행
        try {
            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateValuesRequest();
            $batchUpdateRequest->setValueInputOption('RAW');
            $batchUpdateRequest->setData($updates);
            
            $service->spreadsheets_values->batchUpdate(SPREADSHEET_ID, $batchUpdateRequest);
            
            error_log("배치 업데이트 완료: {$updatedCount}개 품목");
            
            return [
                'success' => true,
                'message' => "{$updatedCount}개 품목 업데이트 완료 (그룹: {$groupName})",
                'updatedCount' => $updatedCount
            ];
            
        } catch (Exception $e) {
            error_log("배치 업데이트 실패, 개별 업데이트 시도: " . $e->getMessage());
            
            // 배치 업데이트 실패 시 개별 업데이트 시도
            $successCount = 0;
            foreach ($updates as $update) {
                try {
                    $service->spreadsheets_values->update(
                        SPREADSHEET_ID, 
                        $update['range'], 
                        new Google_Service_Sheets_ValueRange([
                            'values' => $update['values']
                        ]), 
                        ['valueInputOption' => 'RAW']
                    );
                    $successCount++;
                    error_log("개별 업데이트 성공: {$update['range']}");
                } catch (Exception $individualError) {
                    error_log("개별 업데이트 실패: {$update['range']} - " . $individualError->getMessage());
                }
            }
            
            return [
                'success' => $successCount > 0,
                'message' => "개별 업데이트 완료: {$successCount}/{$updatedCount}개 품목 (그룹: {$groupName})",
                'updatedCount' => $successCount
            ];
        }
        
    } catch (Exception $e) {
        error_log("시트 업데이트 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '시트 업데이트 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 주문 로그 시트에 기록
 */
function logOrderToSheet($service, $companyName, $orders) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        $deliveryDay = getCompanyDeliveryDayFromDB($companyName);
        
        $logData = [];
        foreach ($orders as $order) {
            if ($order['quantity'] > 0) {
                $logData[] = [
                    $timestamp,
                    $companyName,
                    $deliveryDay,
                    $order['item'],
                    $order['quantity'],
                    '웹주문'
                ];
            }
        }
        
        if (!empty($logData)) {
            // 주문로그 시트의 마지막 행에 추가
            $range = SHEET_ORDER_LOG . '!A:F';
            $body = new Google_Service_Sheets_ValueRange();
            $body->setValues($logData);
            
            $params = [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS'
            ];
            
            $service->spreadsheets_values->append(
                SPREADSHEET_ID,
                $range,
                $body,
                $params
            );
            
            error_log("주문 로그 시트에 " . count($logData) . "개 항목 기록 완료");
        }
        
    } catch (Exception $e) {
        error_log("주문 로그 시트 기록 오류: " . $e->getMessage());
        // 로그 실패해도 주문은 성공으로 처리
    }
}

/**
 * 신규 승인된 업체를 업체정보 시트에 추가 (소속그룹 포함)
 * 컬럼 순서: A:업체명, B:그룹명, C:비밀번호, D:배송요일, E:우편번호, F:주소, G:담당자, H:전화번호, I:이메일
 */
function syncApprovedCompanyToSheets($companyData) {
    try {
        $service = getSheetsService();
        $sheetName = SHEET_COMPANY_INFO;
        
        // 기존 업체 존재 여부 확인
        $range = "{$sheetName}!A:A";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        // 중복 체크 (2행부터 - 1행은 헤더)
        if ($values) {
            for ($i = 1; $i < count($values); $i++) {
                if (isset($values[$i][0]) && trim($values[$i][0]) === trim($companyData['company_name'])) {
                    return [
                        'success' => false,
                        'message' => "업체 '{$companyData['company_name']}'가 이미 업체정보 시트에 존재합니다."
                    ];
                }
            }
        }
        
        // 새 행에 데이터 추가
        $newRowData = [
            $companyData['company_name'],           // A: 업체명
            $companyData['item_group'] ?? '',       // B: 그룹명
            $companyData['password'],               // C: 비밀번호
            $companyData['delivery_day'],           // D: 배송요일
            $companyData['zip_code'] ?? '',         // E: 우편번호
            $companyData['company_address'] ?? '',  // F: 주소
            $companyData['contact_person'] ?? '',   // G: 담당자
            $companyData['phone_number'] ?? '',     // H: 전화번호
            $companyData['email'] ?? ''             // I: 이메일
        ];
        
        // 시트에 새 행 추가
        $range = "{$sheetName}!A:I";  // I열까지 확장
        $body = new Google_Service_Sheets_ValueRange();
        $body->setValues([$newRowData]);
        
        $params = [
            'valueInputOption' => 'RAW',
            'insertDataOption' => 'INSERT_ROWS'
        ];
        
        $service->spreadsheets_values->append(
            SPREADSHEET_ID,
            $range,
            $body,
            $params
        );
        
        return [
            'success' => true,
            'message' => "업체 '{$companyData['company_name']}'가 업체정보 시트에 추가되었습니다. (그룹: {$companyData['item_group']})"
        ];
        
    } catch (Exception $e) {
        error_log("업체정보 시트 동기화 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '업체정보 시트 동기화 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 열 인덱스를 Excel 컬럼 문자로 변환 (0=A, 1=B, ...)
 */
function columnIndexToLetter($index) {
    $letters = '';
    while ($index >= 0) {
        $letters = chr($index % 26 + 65) . $letters;
        $index = intval($index / 26) - 1;
    }
    return $letters;
}

/**
 * DB에서 업체의 배송요일 조회 (요일미정 반영)
 */
function getCompanyDeliveryDayFromDB($companyName) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT delivery_day FROM companies WHERE company_name = ?");
        $stmt->execute([$companyName]);
        $result = $stmt->fetch();
        
        return $result ? $result['delivery_day'] : '요일미정';
    } catch (Exception $e) {
        error_log("배송요일 조회 오류: " . $e->getMessage());
        return '요일미정';
    }
}

/**
 * 업체관리 시트에서 업체 및 품목 정보 가져오기 (요일미정 반영)
 */
function syncCompaniesFromGoogleSheets() {
    try {
        $service = getSheetsService();
        $companiesData = [];
        
        // 1. "업체관리" 시트에서 업체명과 품목 정보 읽기
        $managementSheetName = SHEET_COMPANY_MANAGEMENT;
        $range = "{$managementSheetName}!A:Z";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $managementValues = $response->getValues();
        
        if (count($managementValues) < 2) {
            return [
                'success' => false,
                'message' => '업체관리 시트에 데이터가 없습니다.'
            ];
        }
        
        // 2. "업체정보" 시트에서 배송요일 정보 읽기
        $infoSheetName = SHEET_COMPANY_INFO;
        $infoRange = "{$infoSheetName}!A:D"; // A:업체명, B:그룹명, C:비밀번호, D:배송요일
        $infoResponse = $service->spreadsheets_values->get(SPREADSHEET_ID, $infoRange);
        $infoValues = $infoResponse->getValues();
        
        // 업체정보 시트에서 배송요일 매핑 생성
        $deliveryDayMap = [];
        if ($infoValues && count($infoValues) > 1) {
            for ($i = 1; $i < count($infoValues); $i++) {
                if (isset($infoValues[$i][0]) && isset($infoValues[$i][3])) {
                    $companyName = trim($infoValues[$i][0]);
                    $deliveryDay = trim($infoValues[$i][3]);
                    $deliveryDayMap[$companyName] = $deliveryDay;
                }
            }
        }
        
        // 3. 업체관리 시트 데이터 처리 (2번째 행부터 업체 데이터, 1행은 헤더)
        for ($i = 1; $i < count($managementValues); $i++) {
            $row = $managementValues[$i];
            if (empty($row[0])) continue; // 업체명이 없으면 건너뛰기
            
            $companyName = trim($row[0]);
            $items = [];
            
            // 품목 추출 (B열부터 - 업체명 다음부터 품목들)
            for ($j = 1; $j < count($row); $j++) {
                if (!empty($row[$j]) && trim($row[$j]) !== '') {
                    $items[] = trim($row[$j]);
                }
            }
            
            // 배송요일은 업체정보 시트에서 가져오기, 없으면 기본값
            $deliveryDay = $deliveryDayMap[$companyName] ?? '요일미정';
            
            $companiesData[] = [
                'companyName' => $companyName,
                'deliveryDay' => $deliveryDay,  // 업체정보 시트에서 가져온 배송요일
                'items' => $items,
                'sheetName' => $managementSheetName,
                'row' => $i + 1
            ];
        }
        
        return [
            'success' => true,
            'data' => $companiesData,
            'message' => count($companiesData) . '개 업체 정보 동기화 완료'
        ];
        
    } catch (Exception $e) {
        error_log("Google Sheets 동기화 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Google Sheets 동기화 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * Google Sheets 연결 테스트
 */
function testGoogleSheetsConnection() {
    try {
        $service = getSheetsService();
        
        // 스프레드시트 메타데이터 가져오기
        $spreadsheet = $service->spreadsheets->get(SPREADSHEET_ID);
        $title = $spreadsheet->getProperties()->getTitle();
        
        // 시트 목록 확인
        $sheets = $spreadsheet->getSheets();
        $sheetCount = count($sheets);
        
        return [
            'success' => true,
            'message' => "Google Sheets 연결 성공: '{$title}' ({$sheetCount}개 시트)"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Google Sheets 연결 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 특정 업체의 현재 주문 현황 조회 (Google Sheets에서)
 */
function getCompanyCurrentOrderFromSheets($companyName) {
    try {
        $service = getSheetsService();
        $sheetInfo = findCompanyInSheets($service, $companyName);
        
        if (!$sheetInfo) {
            return [
                'success' => false,
                'message' => "업체 '{$companyName}'를 찾을 수 없습니다."
            ];
        }
        
        // 해당 업체 행 전체 데이터 가져오기
        $range = "{$sheetInfo['sheetName']}!{$sheetInfo['row']}:{$sheetInfo['row']}";
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        // 헤더 가져오기
        if ($sheetInfo['itemGroup']) {
            $headerRow = $sheetInfo['itemGroup']['headerRow'];
            $headers = getGroupItemHeaders($service, $sheetInfo['sheetName'], $headerRow);
        } else {
            $headers = [];
        }
        
        $currentOrders = [];
        if (!empty($values[0]) && !empty($headers)) {
            $row = $values[0];
            for ($i = 1; $i < count($headers) && $i < count($row); $i++) {
                if (!empty($headers[$i]) && !empty($row[$i]) && is_numeric($row[$i])) {
                    $currentOrders[] = [
                        'item' => $headers[$i],
                        'quantity' => intval($row[$i])
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'companyName' => $companyName,
            'sheetName' => $sheetInfo['sheetName'],
            'orders' => $currentOrders,
            'message' => count($currentOrders) . '개 품목 주문 현황 조회 완료'
        ];
        
    } catch (Exception $e) {
        error_log("주문 현황 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '주문 현황 조회 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * Google Sheets 업체정보 시트에서 담당자 정보 업데이트
 * 업체정보 시트 컬럼 구조: A:업체명, B:그룹명, C:비밀번호, D:배송요일, E:우편번호, F:주소, G:담당자, H:전화번호, I:이메일
 */
function updateCompanyProfileInSheets($companyName, $password, $contactPerson, $phoneNumber) {
    try {
        $service = getSheetsService();
        $sheetName = SHEET_COMPANY_INFO;
        
        // 1. 업체정보 시트에서 해당 업체 찾기
        $range = "{$sheetName}!A:I"; // A부터 I열까지 (이메일 포함)
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
        $values = $response->getValues();
        
        if (!$values || count($values) < 2) {
            return [
                'success' => false,
                'message' => '업체정보 시트에 데이터가 없습니다.'
            ];
        }
        
        // 2. 해당 업체가 있는 행 찾기 (2행부터 검색 - 1행은 헤더)
        $companyRow = null;
        for ($i = 1; $i < count($values); $i++) {
            if (isset($values[$i][0]) && trim($values[$i][0]) === trim($companyName)) {
                $companyRow = $i + 1; // 1-based 행 번호
                break;
            }
        }
        
        if (!$companyRow) {
            return [
                'success' => false,
                'message' => "업체 '{$companyName}'를 업체정보 시트에서 찾을 수 없습니다."
            ];
        }
        
        // 3. 업데이트할 데이터 준비
        $updates = [];
        
        // C열: 비밀번호
        $updates[] = [
            'range' => "{$sheetName}!C{$companyRow}",
            'values' => [[$password]]
        ];
        
        // G열: 담당자
        $updates[] = [
            'range' => "{$sheetName}!G{$companyRow}",
            'values' => [[$contactPerson]]
        ];
        
        // H열: 전화번호
        $updates[] = [
            'range' => "{$sheetName}!H{$companyRow}",
            'values' => [[$phoneNumber]]
        ];
        
        // 4. 배치 업데이트 실행
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateValuesRequest();
        $batchUpdateRequest->setValueInputOption('RAW');
        $batchUpdateRequest->setData($updates);
        
        $result = $service->spreadsheets_values->batchUpdate(SPREADSHEET_ID, $batchUpdateRequest);
        
        // 5. 업데이트 로그 기록
        $timestamp = date('Y-m-d H:i:s');
        error_log("Google Sheets 담당자정보 업데이트: {$companyName} (행: {$companyRow}, 시간: {$timestamp})");
        
        return [
            'success' => true,
            'message' => "Google Sheets 업체정보 시트에서 '{$companyName}' 담당자 정보가 업데이트되었습니다.",
            'updatedCells' => $result->getTotalUpdatedCells(),
            'sheetName' => $sheetName,
            'row' => $companyRow
        ];
        
    } catch (Exception $e) {
        error_log("Google Sheets 담당자정보 업데이트 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Google Sheets 담당자정보 업데이트 실패: ' . $e->getMessage()
        ];
    }
}
?>