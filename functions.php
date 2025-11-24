<?php
require_once 'config.php';
require_once __DIR__ . '/functions-notice.php';

/**
 * 캐시 관련 함수들
 */
function getCacheKey($key) {
    return CACHE_DIR . md5($key) . '.cache';
}

function getCache($key) {
    $cacheFile = getCacheKey($key);
    
    if (file_exists($cacheFile)) {
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] > time()) {
            return $cacheData['data'];
        } else {
            unlink($cacheFile); // 만료된 캐시 삭제
        }
    }
    
    return null;
}

function setCache($key, $data, $duration = CACHE_DURATION) {
    $cacheFile = getCacheKey($key);
    $cacheData = [
        'data' => $data,
        'expires' => time() + $duration
    ];
    
    file_put_contents($cacheFile, serialize($cacheData));
}

function clearCache($key = null) {
    if ($key) {
        $cacheFile = getCacheKey($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    } else {
        // 모든 캐시 삭제
        $files = glob(CACHE_DIR . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

/**
 * 주문차단 상태 확인 함수
 */
function checkCompanyOrderBlock($companyName) {
    try {
        $pdo = getDBConnection();
        
        // companies 테이블에 order_blocked, block_reason 컬럼이 없다면 추가
        $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'order_blocked'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN order_blocked TINYINT(1) DEFAULT 0");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'block_reason'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN block_reason VARCHAR(500) DEFAULT NULL");
        }
        
        // 업체의 차단 상태 조회
        $stmt = $pdo->prepare("
            SELECT order_blocked, block_reason 
            FROM companies 
            WHERE company_name = ? AND active = 1
        ");
        $stmt->execute([$companyName]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return ['blocked' => false, 'reason' => ''];
        }
        
        return [
            'blocked' => (bool)$result['order_blocked'],
            'reason' => $result['block_reason'] ?? ''
        ];
        
    } catch (Exception $e) {
        error_log("Check company order block error: " . $e->getMessage());
        return ['blocked' => false, 'reason' => ''];
    }
}

/**
 * 주문 가능 시간인지 체크
 */
function isOrderTimeAllowed() {
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $currentHour = (int)$now->format('H');
    // 05:00 ~ 08:00 시간대는 주문 불가
    return !($currentHour >= 5 && $currentHour < 8);
}

/**
 * 다음 주문 가능 시간 반환
 */
function getNextOrderTime() {
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $currentHour = (int)$now->format('H');
    
    if ($currentHour >= 5 && $currentHour < 8) {
        // 05:00~08:00 시간대면 오늘 08:00
        $nextTime = clone $now;
        $nextTime->setTime(8, 0, 0);
    } else {
        // 다른 시간대면 내일 08:00
        $nextTime = clone $now;
        $nextTime->modify('+1 day')->setTime(8, 0, 0);
    }
    
    return $nextTime->format('Y-m-d H:i:s');
}

/**
 * 업체 데이터 로드 (캐시 활용)
 */
function loadCompaniesData() {
    $cacheKey = 'companies_data';
    $cachedData = getCache($cacheKey);
    
    if ($cachedData !== null) {
        return $cachedData;
    }
    
    // 캐시 미스 - 데이터베이스에서 로드 (품목 설명 포함)
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT c.company_name, c.password, c.delivery_day,
               GROUP_CONCAT(
                   CONCAT(ci.item_name, '|', COALESCE(i.description, '')) 
                   ORDER BY ci.item_order
               ) as items_with_descriptions
        FROM companies c
        LEFT JOIN company_items ci ON c.id = ci.company_id AND ci.active = 1
        LEFT JOIN items i ON ci.item_id = i.id AND i.active = 1
        WHERE c.active = 1
        GROUP BY c.id, c.company_name, c.password, c.delivery_day
    ");
    
    $companiesData = [];
    while ($row = $stmt->fetch()) {
        $items = [];
        if ($row['items_with_descriptions']) {
            $itemPairs = explode(',', $row['items_with_descriptions']);
            foreach ($itemPairs as $itemPair) {
                $parts = explode('|', $itemPair, 2);
                $items[] = [
                    'name' => $parts[0],
                    'description' => isset($parts[1]) ? $parts[1] : ''
                ];
            }
        }
        
        $companiesData[$row['password']] = [
            'companyName' => $row['company_name'],
            'items' => $items,
            'deliveryDay' => $row['delivery_day'] ?? '요일미정'
        ];
    }
    
    // 캐시에 저장
    setCache($cacheKey, $companiesData);
    
    return $companiesData;
}

/**
 * 비밀번호로 업체 찾기 (차단 정보 포함 - 수정됨)
 */
function findCompanyByPassword($password) {
    try {
        if (!$password || trim($password) === '') {
            return ['success' => false, 'message' => '비밀번호를 입력해주세요.'];
        }
        
        $trimmedPassword = trim($password);
        $companiesData = loadCompaniesData();
        
        if (isset($companiesData[$trimmedPassword])) {
            $companyData = $companiesData[$trimmedPassword];
            $companyName = $companyData['companyName'];
            
            // 세션에 로그인 정보 저장
            $_SESSION['company_name'] = $companyName;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // 주문차단 상태 확인 (추가된 부분)
            $blockStatus = checkCompanyOrderBlock($companyName);
            
            return [
                'success' => true,
                'companyName' => $companyName,
                'items' => $companyData['items'],
                'deliveryDay' => $companyData['deliveryDay'],
                'orderTimeAllowed' => isOrderTimeAllowed(),
                'nextOrderTime' => getNextOrderTime(),
                // 차단 정보 추가
                'orderBlocked' => $blockStatus['blocked'],
                'blockReason' => $blockStatus['reason']
            ];
        }
        
        return ['success' => false, 'message' => '올바르지 않은 비밀번호입니다.'];
        
    } catch (Exception $e) {
        error_log("업체 찾기 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 정보를 찾는 중 오류가 발생했습니다.'];
    }
}

/**
 * MySQL 전용 주문 처리 (주문 시간 체크 추가)
 */
function processOrderMySQL($orderData) {
    try {
        // 주문 시간 체크
        if (!isOrderTimeAllowed()) {
            return [
                'success' => false, 
                'message' => '지금은 주문처리 시간대로 주문을 받을 수 없습니다. (주문 가능 시간: 08:00~익일 05:00)',
                'orderTimeRestricted' => true,
                'nextOrderTime' => getNextOrderTime()
            ];
        }
        
        // 로그인 체크
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        
        if (!isset($orderData['companyName']) || !isset($orderData['orders'])) {
            return ['success' => false, 'message' => '주문 데이터가 올바르지 않습니다.'];
        }
        
        $companyName = $orderData['companyName'];
        $orders = $orderData['orders'];
        
        // 세션의 업체명과 일치하는지 확인
        if ($_SESSION['company_name'] !== $companyName) {
            return ['success' => false, 'message' => '권한이 없습니다.'];
        }
        
        if (empty($orders)) {
            // 빈 주문 배열인 경우 - 모든 주문을 삭제하는 경우로 처리
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            try {
                // 업체 정보 조회
                $stmt = $pdo->prepare("SELECT id, delivery_day FROM companies WHERE company_name = ? AND active = 1");
                $stmt->execute([$companyName]);
                $company = $stmt->fetch();
                
                if (!$company) {
                    throw new Exception("업체 정보를 찾을 수 없습니다.");
                }
                
                $companyId = $company['id'];
                $deliveryDay = $company['delivery_day'] ?? '요일미정';
                
                // 모든 주문 삭제
                updateOrderStatus($pdo, $companyId, $companyName, [], $deliveryDay);
                
                $pdo->commit();
                
                return [
                    'success' => true,
                    'message' => '모든 주문이 삭제되었습니다.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // 1. 업체 정보 조회
            $stmt = $pdo->prepare("SELECT id, delivery_day FROM companies WHERE company_name = ? AND active = 1");
            $stmt->execute([$companyName]);
            $company = $stmt->fetch();
            
            if (!$company) {
                throw new Exception("업체 정보를 찾을 수 없습니다.");
            }
            
            $companyId = $company['id'];
            $deliveryDay = $company['delivery_day'] ?? '요일미정'; 
            //"업체의 배송 요일이 있으면 그걸 쓰고, 없으면 기본값으로 '요일미정'을 사용한다"
            
            // 2. 주문 현황 업데이트
            updateOrderStatus($pdo, $companyId, $companyName, $orders, $deliveryDay);
            
            // 3. 주문 로그 기록
            logOrder($pdo, $companyId, $companyName, $orders, $deliveryDay);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => '주문접수',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("주문 처리 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '주문 처리 중 오류가 발생했습니다: ' . $e->getMessage()
        ];
    }
}

/**
 * Google Sheets 연동 주문 처리 (구글시트 메시지 제거)
 */
function processOrder($orderData) {
    try {
        // 1. MySQL 저장 (안정성 우선)
        $mysqlResult = processOrderMySQL($orderData);
        
        if (!$mysqlResult['success']) {
            return $mysqlResult; // MySQL 실패시 즉시 중단
        }
        
        // 2. Google Sheets 업데이트 시도 (백그라운드 처리, 사용자 메시지에 포함하지 않음)
        error_log("Google Sheets 연동 시작");
        
        try {
            // google-sheets.php 파일 먼저 로드
            require_once 'google-sheets.php';
            error_log("google-sheets.php 로드 성공");
            
            // 함수 존재 확인
            if (function_exists('updateGoogleSheets')) {
                error_log("updateGoogleSheets 함수 존재함");
                
                $sheetsResult = updateGoogleSheets($orderData['companyName'], $orderData['orders']);
                error_log("Google Sheets 결과: " . json_encode($sheetsResult));
                
            } else {
                error_log("updateGoogleSheets 함수 없음");
            }
            
        } catch (Exception $e) {
            error_log("Google Sheets 연결 오류: " . $e->getMessage());
        }
        
        // 사용자에게는 MySQL 성공 메시지만 반환 (구글시트 관련 메시지 제거)
        return $mysqlResult;
        
    } catch (Exception $e) {
        error_log("주문 처리 통합 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '주문 처리 중 오류가 발생했습니다.'];
    }
}

/**
 * 주문 현황 업데이트 (오전 8시~익일 오전 5시 기준으로 수정)
 */
function updateOrderStatus($pdo, $companyId, $companyName, $orders, $deliveryDay) {
    // 현재 주문 기간 계산 (오전 8시~익일 오전 5시)
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $currentHour = (int)$now->format('H');
    
    if ($currentHour < 8) {
        // 현재 시간이 오전 8시 이전이면 어제 오전 8시부터
        $startTime = clone $now;
        $startTime->modify('-1 day')->setTime(8, 0, 0);
        $endTime = clone $now;
        $endTime->setTime(5, 0, 0);
    } else {
        // 현재 시간이 오전 8시 이후면 오늘 오전 8시부터
        $startTime = clone $now;
        $startTime->setTime(8, 0, 0);
        $endTime = clone $now;
        $endTime->modify('+1 day')->setTime(5, 0, 0);
    }
    
    // 기존 해당 기간 주문 현황 삭제 (더 안전한 범위로 확장)
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
    
    // 추가 안전장치: 당일 모든 주문도 삭제 (혹시 놓친 데이터가 있을 경우)
    $today = $now->format('Y-m-d');
    $stmt = $pdo->prepare("
        DELETE FROM order_status 
        WHERE company_id = ? 
        AND DATE(order_date) = ?
    ");
    $stmt->execute([$companyId, $today]);
    
    // 새 주문 현황 삽입 (빈 배열인 경우 아무것도 삽입하지 않음)
    if (!empty($orders)) {
        $stmt = $pdo->prepare("
            INSERT INTO order_status (company_id, company_name, item_name, quantity, order_date, delivery_day)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        
        foreach ($orders as $order) {
            if ($order['quantity'] > 0) {
                $stmt->execute([
                    $companyId,
                    $companyName,
                    $order['item'],
                    $order['quantity'],
                    $deliveryDay
                ]);
            }
        }
    }
}

/**
 * 주문 로그 기록
 */
function logOrder($pdo, $companyId, $companyName, $orders, $deliveryDay) {
    $stmt = $pdo->prepare("
        INSERT INTO order_logs (company_id, company_name, delivery_day, item_name, quantity, order_source, created_at)
        VALUES (?, ?, ?, ?, ?, '웹주문', NOW())
    ");
    
    foreach ($orders as $order) {
        if ($order['quantity'] > 0) {
            $stmt->execute([
                $companyId,
                $companyName,
                $deliveryDay,
                $order['item'],
                $order['quantity']
            ]);
        }
    }
}

/**
 * 업체의 당일 주문 현황 조회 (차단 상태 확인 추가 - 수정됨)
 */
function getTodayOrderStatus($companyName) {
    try {
        // 로그인 상태 확인
        if (!validateSession()) {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        
        // 세션의 업체명과 일치하는지 확인
        if (!isset($_SESSION['company_name']) || $_SESSION['company_name'] !== $companyName) {
            return ['success' => false, 'message' => '권한이 없습니다.'];
        }
        
        $pdo = getDBConnection();
        
        // 업체 정보 조회
        $stmt = $pdo->prepare("SELECT id, delivery_day FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '업체 정보를 찾을 수 없습니다.'];
        }
        
        // 주문차단 상태 확인 (차단되어도 조회는 계속 진행)
        $blockStatus = checkCompanyOrderBlock($companyName);
        
        // 주문 시간 범위 계산: 오전 8시부터 다음날 오전 5시까지
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $currentHour = (int)$now->format('H');
        
        if ($currentHour < 8) {
            // 현재 시간이 오전 8시 이전이면 어제 오전 8시부터
            $startTime = clone $now;
            $startTime->modify('-1 day')->setTime(8, 0, 0);
            $endTime = clone $now;
            $endTime->setTime(5, 0, 0);
        } else {
            // 현재 시간이 오전 8시 이후면 오늘 오전 8시부터
            $startTime = clone $now;
            $startTime->setTime(8, 0, 0);
            $endTime = clone $now;
            $endTime->modify('+1 day')->setTime(5, 0, 0);
        }
        
        // 해당 시간 범위의 주문 현황 조회 - LEFT JOIN으로 변경하여 누락 방지, 정렬 보정
        $stmt = $pdo->prepare("
            SELECT os.item_name, os.quantity, os.order_date, ci.item_order 
            FROM order_status os
            LEFT JOIN company_items ci ON os.company_id = ci.company_id AND os.item_name = ci.item_name
            WHERE os.company_id = ? 
            AND os.order_date >= ? 
            AND os.order_date < ?
            ORDER BY 
                CASE WHEN ci.item_order IS NULL THEN 1 ELSE 0 END ASC, 
                ci.item_order ASC, 
                os.item_name ASC
        ");
        $stmt->execute([
            $company['id'], 
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s')
        ]);
        $todayOrders = $stmt->fetchAll();
        
        // 총 품목 수와 총 수량 계산
        $totalItems = count($todayOrders);
        $totalQuantity = 0;
        foreach ($todayOrders as $order) {
            $totalQuantity += $order['quantity'];
        }
        
        // 마지막 주문 시간 조회
        $lastOrderTime = null;
        if (!empty($todayOrders)) {
            $stmt = $pdo->prepare("
                SELECT MAX(created_at) as last_order_time 
                FROM order_logs 
                WHERE company_id = ? 
                AND created_at >= ? 
                AND created_at < ?
            ");
            $stmt->execute([
                $company['id'], 
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s')
            ]);
            $result = $stmt->fetch();
            $lastOrderTime = $result['last_order_time'];
        }
        
        // 수정 가능 여부 결정 (차단 상태가 아니고 주문 시간이 허용되며 주문이 있는 경우)
        $canModify = !$blockStatus['blocked'] && isOrderTimeAllowed() && !empty($todayOrders);
        
        return [
            'success' => true,
            'companyName' => $companyName,
            'deliveryDay' => $company['delivery_day'],
            'orderPeriod' => $startTime->format('Y-m-d H:i') . ' ~ ' . $endTime->format('Y-m-d H:i'),
            'orders' => $todayOrders,
            'orderTimeAllowed' => isOrderTimeAllowed(),
            'summary' => [
                'totalItems' => $totalItems,
                'totalQuantity' => $totalQuantity,
                'lastOrderTime' => $lastOrderTime
            ],
            // 차단 정보 추가 (조회는 가능하지만 수정은 불가능함을 표시)
            'orderBlocked' => $blockStatus['blocked'],
            'blockReason' => $blockStatus['reason'],
            'canModify' => $canModify
        ];
        
    } catch (Exception $e) {
        error_log("당일 주문 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '주문 현황을 조회하는 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 업체의 최근 주문 이력 조회 (차단 상태 확인 추가 - 수정됨)
 */
function getRecentOrderHistory($companyName, $days = 7) {
    try {
        // 로그인 상태 확인
        if (!validateSession()) {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        
        // 세션의 업체명과 일치하는지 확인
        if (!isset($_SESSION['company_name']) || $_SESSION['company_name'] !== $companyName) {
            return ['success' => false, 'message' => '권한이 없습니다.'];
        }
        
        $pdo = getDBConnection();
        
        // 업체 정보 조회
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '업체 정보를 찾을 수 없습니다.'];
        }
        
        // 주문차단 상태 확인 (차단되어도 이력 조회는 계속 진행)
        $blockStatus = checkCompanyOrderBlock($companyName);
        
        // 최근 N일간의 기간 계산 (오늘 포함)
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $periodStart = clone $now;
        $periodStart->modify("-{$days} days");
        
        error_log("주문 이력 조회: {$companyName}, 기간: " . $periodStart->format('Y-m-d H:i:s') . " ~ " . $now->format('Y-m-d H:i:s'));
        
        // 해당 기간의 주문 날짜들 조회 (오전 8시 기준으로 일자 구분, 오늘 포함)
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                CASE 
                    WHEN HOUR(order_date) < 8 THEN DATE_SUB(DATE(order_date), INTERVAL 1 DAY)
                    ELSE DATE(order_date)
                END as order_day
            FROM order_status 
            WHERE company_id = ? 
            AND order_date >= ?
            ORDER BY order_day DESC
        ");
        $stmt->execute([
            $company['id'], 
            $periodStart->format('Y-m-d H:i:s')
        ]);
        $orderDates = $stmt->fetchAll();
        
        error_log("조회된 주문 날짜 수: " . count($orderDates));
        
        // 각 날짜별로 주문 상태 조회
        $groupedHistory = [];
        foreach ($orderDates as $dateRow) {
            $orderDay = $dateRow['order_day'];
            
            // 해당 날짜의 주문 기간 계산 (오전 8시~익일 오전 5시)
            $dayStart = new DateTime($orderDay . ' 08:00:00', new DateTimeZone('Asia/Seoul'));
            $dayEnd = clone $dayStart;
            $dayEnd->modify('+1 day')->setTime(5, 0, 0);
            
            // 해당 날짜의 주문 상태와 시간 조회 - 수정됨: 품목 순서대로 정렬
            $stmt = $pdo->prepare("
                SELECT 
                    os.item_name,
                    os.quantity,
                    TIME(MAX(ol.created_at)) as order_time
                FROM order_status os
                LEFT JOIN order_logs ol ON os.company_id = ol.company_id 
                    AND os.item_name = ol.item_name 
                    AND ol.created_at >= ?
                    AND ol.created_at < ?
                JOIN company_items ci ON os.company_id = ci.company_id AND os.item_name = ci.item_name
                WHERE os.company_id = ? 
                AND os.order_date >= ?
                AND os.order_date < ?
                GROUP BY os.item_name, os.quantity
                ORDER BY ci.item_order ASC
            ");
            $stmt->execute([
                $dayStart->format('Y-m-d H:i:s'),
                $dayEnd->format('Y-m-d H:i:s'),
                $company['id'],
                $dayStart->format('Y-m-d H:i:s'),
                $dayEnd->format('Y-m-d H:i:s')
            ]);
            $dayOrders = $stmt->fetchAll();
            
            if (!empty($dayOrders)) {
                $totalItems = count($dayOrders);
                $totalQuantity = 0;
                $orders = [];
                
                foreach ($dayOrders as $order) {
                    $totalQuantity += $order['quantity'];
                    $orders[] = [
                        'item_name' => $order['item_name'],
                        'quantity' => $order['quantity'],
                        'order_time' => $order['order_time'] ?: '00:00:00'
                    ];
                }
                
                $groupedHistory[] = [
                    'date' => $orderDay,
                    'orders' => $orders,
                    'totalItems' => $totalItems,
                    'totalQuantity' => $totalQuantity
                ];
            }
        }
        
        error_log("최종 이력 일수: " . count($groupedHistory));
        
        // 주문복사 가능 여부 결정 (차단 상태가 아닌 경우에만)
        $canCopyOrder = !$blockStatus['blocked'] && isOrderTimeAllowed();
        
        return [
            'success' => true,
            'companyName' => $companyName,
            'period' => $days . '일',
            'history' => $groupedHistory,
            'totalDays' => count($groupedHistory),
            // 차단 정보 추가 (이력 조회는 가능하지만 복사는 불가능함을 표시)
            'orderBlocked' => $blockStatus['blocked'],
            'blockReason' => $blockStatus['reason'],
            'canCopyOrder' => $canCopyOrder
        ];
        
    } catch (Exception $e) {
        error_log("주문 이력 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '주문 이력을 조회하는 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 당일 주문 수정 가능 여부 확인 (차단 상태 확인 추가 - 수정됨)
 */
function canModifyTodayOrder($companyName) {
    try {
        // 주문 시간 체크
        if (!isOrderTimeAllowed()) {
            return [
                'canModify' => false, 
                'reason' => '지금은 주문처리 시간대로 수정할 수 없습니다.',
                'orderTimeRestricted' => true
            ];
        }
        
        // 로그인 상태 확인
        if (!validateSession()) {
            return ['canModify' => false, 'reason' => '로그인이 필요합니다.'];
        }
        
        // 세션의 업체명과 일치하는지 확인
        if (!isset($_SESSION['company_name']) || $_SESSION['company_name'] !== $companyName) {
            return ['canModify' => false, 'reason' => '권한이 없습니다.'];
        }
        
        // 주문차단 상태 확인 (추가된 부분)
        $blockStatus = checkCompanyOrderBlock($companyName);
        if ($blockStatus['blocked']) {
            return [
                'canModify' => false,
                'blocked' => true,
                'reason' => '주문이 차단되었습니다.<br>사유: ' . $blockStatus['reason']
            ];
        }
        
        $pdo = getDBConnection();
        
        // 업체 정보 조회
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['canModify' => false, 'reason' => '업체 정보를 찾을 수 없습니다.'];
        }
        
        // 현재 주문 기간 계산
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
        
        // 해당 기간 주문이 있는지 확인
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as order_count 
            FROM order_status 
            WHERE company_id = ? 
            AND order_date >= ? 
            AND order_date < ?
        ");
        $stmt->execute([
            $company['id'], 
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s')
        ]);
        $result = $stmt->fetch();
        
        if ($result['order_count'] == 0) {
            return ['canModify' => false, 'reason' => '현재 주문 기간에 주문한 내역이 없습니다.'];
        }
        
        return [
            'canModify' => true, 
            'reason' => '수정 가능'
        ];
        
    } catch (Exception $e) {
        error_log("주문 수정 가능 여부 확인 오류: " . $e->getMessage());
        return [
            'canModify' => false,
            'reason' => '수정 가능 여부를 확인하는 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * Google Sheets 동기화 상태 확인
 */
function checkGoogleSheetsSync($companyName) {
    try {
        // Google Sheets에서 현재 업체의 주문 현황 조회
        if (function_exists('getCompanyCurrentOrderFromSheets')) {
            $sheetsResult = getCompanyCurrentOrderFromSheets($companyName);
            
            if ($sheetsResult['success']) {
                return [
                    'success' => true,
                    'syncStatus' => 'synced',
                    'message' => 'Google Sheets와 동기화됨',
                    'sheetsData' => $sheetsResult['orders']
                ];
            } else {
                return [
                    'success' => false,
                    'syncStatus' => 'error',
                    'message' => 'Google Sheets 연결 오류'
                ];
            }
        } else {
            // Google Sheets 기능이 없을 때는 unavailable 상태로 반환 (메시지 없음)
            return [
                'success' => false,
                'syncStatus' => 'unavailable',
                'message' => '' // 빈 메시지로 변경
            ];
        }
        
    } catch (Exception $e) {
        error_log("Google Sheets 동기화 확인 오류: " . $e->getMessage());
        return [
            'success' => false,
            'syncStatus' => 'error',
            'message' => 'Google Sheets 동기화 확인 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 업체 프로필 정보 조회
 */
function getCompanyProfile($companyName) {
    try {
        // 로그인 상태 확인
        if (!validateSession()) {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        
        // 세션의 업체명과 일치하는지 확인
        if (!isset($_SESSION['company_name']) || $_SESSION['company_name'] !== $companyName) {
            return ['success' => false, 'message' => '권한이 없습니다.'];
        }
        
        $pdo = getDBConnection();
        
        // companies 테이블과 company_details 테이블에서 정보 조회
        $stmt = $pdo->prepare("
            SELECT c.password, cd.contact_person, cd.phone_number, cd.email,
                   c.company_name, cd.company_address, cd.zip_code
            FROM companies c
            LEFT JOIN company_details cd ON c.id = cd.company_id
            WHERE c.company_name = ? AND c.active = 1
        ");
        $stmt->execute([$companyName]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            return ['success' => false, 'message' => '업체 정보를 찾을 수 없습니다.'];
        }
        
        return [
            'success' => true,
            'data' => [
                'company_name' => $profile['company_name'],
                'password' => $profile['password'],
                'contact_person' => $profile['contact_person'] ?: '',
                'phone_number' => $profile['phone_number'] ?: '',
                'email' => $profile['email'] ?: '',
                'company_address' => $profile['company_address'] ?: '',
                'zip_code' => $profile['zip_code'] ?: ''
            ]
        ];
        
    } catch (Exception $e) {
        error_log("프로필 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '프로필 정보를 조회하는 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 업체 프로필 정보 수정 (비밀번호, 담당자, 전화번호만) - Google Sheets 동기화 포함
 */
function updateCompanyProfile($data) {
    try {
        // 로그인 상태 확인
        if (!validateSession()) {
            return ['success' => false, 'message' => '로그인이 필요합니다.'];
        }
        
        $companyName = $data['companyName'] ?? '';
        $newPassword = trim($data['password'] ?? '');
        $contactPerson = trim($data['contactPerson'] ?? '');
        $phoneNumber = trim($data['phoneNumber'] ?? '');
        
        // 세션의 업체명과 일치하는지 확인
        if (!isset($_SESSION['company_name']) || $_SESSION['company_name'] !== $companyName) {
            return ['success' => false, 'message' => '권한이 없습니다.'];
        }
        
        // 입력값 검증
        if (empty($newPassword) || empty($contactPerson) || empty($phoneNumber)) {
            return ['success' => false, 'message' => '모든 필수 항목을 입력해주세요.'];
        }
        
        if (strlen($newPassword) < 4) {
            return ['success' => false, 'message' => '비밀번호는 4자리 이상이어야 합니다.'];
        }
        
        // 전화번호 형식 검증
        if (!preg_match('/^[0-9-+\s()]+$/', $phoneNumber) || strlen($phoneNumber) < 10) {
            return ['success' => false, 'message' => '올바른 전화번호 형식을 입력해주세요.'];
        }
        
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // 현재 업체 정보 조회
            $stmt = $pdo->prepare("SELECT id, password FROM companies WHERE company_name = ? AND active = 1");
            $stmt->execute([$companyName]);
            $company = $stmt->fetch();
            
            if (!$company) {
                throw new Exception("업체 정보를 찾을 수 없습니다.");
            }
            
            $companyId = $company['id'];
            $oldPassword = $company['password'];
            $passwordChanged = ($oldPassword !== $newPassword);
            
            // 비밀번호가 변경되었을 때 중복 검사
            if ($passwordChanged) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE password = ? AND id != ?");
                $stmt->execute([$newPassword, $companyId]);
                $duplicateCount = $stmt->fetchColumn();
                
                if ($duplicateCount > 0) {
                    throw new Exception("이미 사용중인 비밀번호입니다. 다른 비밀번호를 선택해주세요.");
                }
            }
            
            // companies 테이블 업데이트 (비밀번호)
            $stmt = $pdo->prepare("UPDATE companies SET password = ? WHERE id = ?");
            $stmt->execute([$newPassword, $companyId]);
            
            // company_details 테이블 업데이트 (담당자, 전화번호)
            $stmt = $pdo->prepare("
                UPDATE company_details 
                SET contact_person = ?, phone_number = ?, updated_at = NOW()
                WHERE company_id = ?
            ");
            $stmt->execute([$contactPerson, $phoneNumber, $companyId]);
            
            // company_details 레코드가 없는 경우 생성
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO company_details (company_id, company_name, contact_person, phone_number, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$companyId, $companyName, $contactPerson, $phoneNumber]);
            }
            
            $pdo->commit();
            
            // Google Sheets 동기화 시도 (MySQL 성공 후)
            $sheetsResult = ['success' => false, 'message' => 'Google Sheets 연동 시도하지 않음'];
            
            if (file_exists(__DIR__ . '/google-sheets.php')) {
                try {
                    require_once __DIR__ . '/google-sheets.php';
                    
                    if (function_exists('updateCompanyProfileInSheets')) {
                        $sheetsResult = updateCompanyProfileInSheets($companyName, $newPassword, $contactPerson, $phoneNumber);
                        
                        if ($sheetsResult['success']) {
                            writeLog("Google Sheets 담당자정보 동기화 성공: {$companyName}");
                        } else {
                            writeLog("Google Sheets 담당자정보 동기화 실패: " . ($sheetsResult['message'] ?? '알 수 없는 오류'));
                        }
                    } else {
                        writeLog("updateCompanyProfileInSheets 함수를 찾을 수 없음");
                        $sheetsResult = ['success' => false, 'message' => '동기화 함수 없음'];
                    }
                    
                } catch (Exception $e) {
                    writeLog("Google Sheets 담당자정보 연동 오류: " . $e->getMessage());
                    $sheetsResult = ['success' => false, 'message' => 'Google Sheets 연동오류: ' . $e->getMessage()];
                }
            } else {
                writeLog("google-sheets.php 파일을 찾을 수 없음");
            }
            
            // 캐시 갱신 (비밀번호가 변경된 경우)
            if ($passwordChanged) {
                clearCache('companies_data');
            }
            
            // 결과 메시지 구성 (Google Sheets 결과는 로그에만 기록, 사용자 메시지에는 포함하지 않음)
            $message = '담당자 정보가 성공적으로 수정되었습니다.';
            if ($passwordChanged) {
                $message .= ' 비밀번호가 변경되었습니다.';
            }
            
            // 로그 기록
            writeLog("담당자정보 수정: {$companyName} (담당자: {$contactPerson}, 전화: {$phoneNumber}" . 
                     ($passwordChanged ? ", 비밀번호 변경됨" : "") . 
                     ", Google Sheets 동기화: " . ($sheetsResult['success'] ? '성공' : '실패') . ")");
            
            return [
                'success' => true,
                'message' => $message,
                'requireRelogin' => $passwordChanged, // 비밀번호 변경시 재로그인 필요
                'sheetsSync' => $sheetsResult['success'] // 내부 참조용 (UI에 표시하지 않음)
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("프로필 수정 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 업체명으로 미확인 전체공지 조회 (사용자용)
 */
function getUnreadGlobalNoticesForCompany($companyName) {
    try {
        $pdo = getDBConnection();
        
        // 업체 ID 조회
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'notices' => []];
        }
        
        // 전체공지(global)만 조회
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.title,
                n.message,
                n.priority,
                n.created_at
            FROM notices n
            LEFT JOIN notice_reads nr ON n.id = nr.notice_id AND nr.company_id = :company_id
            WHERE n.is_active = 1
              AND n.notice_type = 'global'
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
              AND nr.id IS NULL
            ORDER BY n.priority DESC, n.created_at DESC
        ");
        
        $stmt->execute([':company_id' => $company['id']]);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'notices' => $notices
        ];
        
    } catch (Exception $e) {
        error_log("전체공지 조회 오류: " . $e->getMessage());
        return ['success' => false, 'notices' => []];
    }
}

/**
 * 업체명으로 개별메시지 조회 (사용자용)
 */
function getIndividualNoticesForCompany($companyName) {
    try {
        $pdo = getDBConnection();
        
        // 업체 ID 조회
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'notices' => []];
        }
        
        // 해당 업체 대상 개별메시지만 조회 (읽음 여부 무관)
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.message,
                n.priority,
                n.created_at
            FROM notices n
            JOIN notice_targets nt ON n.id = nt.notice_id
            WHERE n.is_active = 1
              AND n.notice_type = 'individual'
              AND nt.company_id = :company_id
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
            ORDER BY n.priority DESC, n.created_at DESC
        ");
        
        $stmt->execute([':company_id' => $company['id']]);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'notices' => $notices
        ];
        
    } catch (Exception $e) {
        error_log("개별메시지 조회 오류: " . $e->getMessage());
        return ['success' => false, 'notices' => []];
    }
}

/**
 * 업체명으로 공지 읽음 처리 (사용자용)
 */
function markNoticeAsReadByCompany($noticeId, $companyName) {
    try {
        $pdo = getDBConnection();
        
        // 업체 ID 조회
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
        $stmt->execute([$companyName]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '업체 정보를 찾을 수 없습니다.'];
        }
        
        // markNoticeAsRead 함수 호출 (functions-notice.php에 정의됨)
        return markNoticeAsRead($noticeId, $company['id']);
        
    } catch (Exception $e) {
        error_log("공지 읽음 처리 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '공지 읽음 처리 중 오류가 발생했습니다.'];
    }
}

/**
 * 캐시 강제 갱신
 */
function forceCacheRefresh() {
    try {
        clearCache('companies_data');
        loadCompaniesData(); // 새로 로드하여 캐시 생성
        return ['success' => true, 'message' => '캐시가 성공적으로 갱신되었습니다.'];
    } catch (Exception $e) {
        error_log("캐시 갱신 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '캐시 갱신 중 오류가 발생했습니다.'];
    }
}

/**
 * 세션 검증
 */
function validateSession() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        return false;
    }
    
    // 세션 타임아웃 체크 (24시간)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * 로그 기록 함수
 */
function writeLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '/logs/app.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * 업체의 품목 요청 생성
 */
function createCompanyItemRequest($companyId, $itemId, $action) {
    try {
        $pdo = getDBConnection();
        
        // 중복 요청 체크
        $stmt = $pdo->prepare("
            SELECT id FROM company_item_requests 
            WHERE company_id = ? AND item_id = ? AND action = ? AND status = 'pending'
        ");
        $stmt->execute([$companyId, $itemId, $action]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '이미 처리 대기 중인 요청입니다.'];
        }
        
        // 요청 생성
        $stmt = $pdo->prepare("
            INSERT INTO company_item_requests (company_id, item_id, action, status, requested_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$companyId, $itemId, $action]);
        
        return ['success' => true, 'message' => '품목 요청이 생성되었습니다.'];
        
    } catch (Exception $e) {
        error_log("Create company item request error: " . $e->getMessage());
        return ['success' => false, 'message' => '요청 생성 중 오류가 발생했습니다.'];
    }
}

/**
 * 업체별 품목 요청 현황 조회
 */
function getCompanyItemRequestStatus($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 새로운 컬럼과 테이블 존재 여부 확인
        $stmt = $pdo->query("SHOW COLUMNS FROM company_items LIKE 'status'");
        $hasStatusColumn = $stmt->fetch();
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'company_item_requests'");
        $hasRequestsTable = $stmt->fetch();
        
        if (!$hasStatusColumn || !$hasRequestsTable) {
            return getCompanyItemRequestStatusLegacy($companyId);
        }
        
        // 현재 할당된 품목과 요청 상태 조회
        $stmt = $pdo->prepare("
            SELECT 
                ci.item_id,
                ci.item_name,
                ci.status as assignment_status,
                cir.status as request_status,
                cir.action as request_action,
                cir.requested_at,
                cir.approved_at,
                cir.reason
            FROM company_items ci
            LEFT JOIN company_item_requests cir ON ci.company_id = cir.company_id 
                AND ci.item_id = cir.item_id 
                AND cir.status = 'pending'
            WHERE ci.company_id = ? AND ci.active = 1
            ORDER BY ci.item_order ASC
        ");
        $stmt->execute([$companyId]);
        $assignedItems = $stmt->fetchAll();
        
        // 모든 품목과 할당 상태 조회
        $stmt = $pdo->prepare("
            SELECT 
                i.id as item_id,
                i.item_name,
                i.description,
                c.category_name,
                CASE WHEN ci.id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
                ci.status as assignment_status,
                cir.status as request_status,
                cir.action as request_action
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN company_items ci ON i.id = ci.item_id AND ci.company_id = ? AND ci.active = 1
            LEFT JOIN company_item_requests cir ON i.id = cir.item_id AND cir.company_id = ? AND cir.status = 'pending'
            WHERE i.active = 1 AND c.active = 1
            ORDER BY c.display_order ASC, i.display_order ASC, i.item_name ASC
        ");
        $stmt->execute([$companyId, $companyId]);
        $allItems = $stmt->fetchAll();
        
        return [
            'success' => true,
            'assignedItems' => $assignedItems,
            'allItems' => $allItems
        ];
        
    } catch (Exception $e) {
        error_log("Get company item request status error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 상태 조회 중 오류가 발생했습니다.'];
    }
}

/**
 * 기존 스키마용 품목 요청 현황 조회 (임시)
 */
function getCompanyItemRequestStatusLegacy($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 현재 할당된 품목 조회 (기존 방식)
        $stmt = $pdo->prepare("
            SELECT 
                ci.item_id,
                ci.item_name,
                'approved' as assignment_status,
                NULL as request_status,
                NULL as request_action,
                NULL as requested_at,
                NULL as approved_at,
                NULL as reason
            FROM company_items ci
            WHERE ci.company_id = ? AND ci.active = 1
            ORDER BY ci.item_order ASC
        ");
        $stmt->execute([$companyId]);
        $assignedItems = $stmt->fetchAll();
        
        // 모든 품목과 할당 상태 조회 (기존 방식)
        $stmt = $pdo->prepare("
            SELECT 
                i.id as item_id,
                i.item_name,
                i.description,
                c.category_name,
                CASE WHEN ci.id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
                CASE WHEN ci.id IS NOT NULL THEN 'approved' ELSE NULL END as assignment_status,
                NULL as request_status,
                NULL as request_action
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN company_items ci ON i.id = ci.item_id AND ci.company_id = ? AND ci.active = 1
            WHERE i.active = 1 AND c.active = 1
            ORDER BY c.display_order ASC, i.display_order ASC, i.item_name ASC
        ");
        $stmt->execute([$companyId]);
        $allItems = $stmt->fetchAll();
        
        return [
            'success' => true,
            'assignedItems' => $assignedItems,
            'allItems' => $allItems,
            'legacy' => true // 레거시 모드임을 표시
        ];
        
    } catch (Exception $e) {
        error_log("Get company item request status legacy error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 상태 조회 중 오류가 발생했습니다.'];
    }
}

/**
 * 품목 요청 처리 (승인/거부)
 */
function processItemRequest($requestId, $action, $approvedBy = 'admin', $reason = null) {
    try {
        $pdo = getDBConnection();
        
        // 요청 정보 조회
        $stmt = $pdo->prepare("
            SELECT company_id, item_id, action as request_action, status
            FROM company_item_requests 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            return ['success' => false, 'message' => '처리할 수 없는 요청입니다.'];
        }
        
        $companyId = $request['company_id'];
        $itemId = $request['item_id'];
        $requestAction = $request['request_action']; // 'add' or 'remove'
        
        // 승인 처리
        if ($action === 'approve') {
            if ($requestAction === 'add') {
                error_log("Adding item: companyId=$companyId, itemId=$itemId");
                
                // 먼저 해당 품목이 이미 할당되어 있는지 확인
                $checkStmt = $pdo->prepare("
                    SELECT id FROM company_items 
                    WHERE company_id = ? AND item_id = ? AND active = 1
                ");
                $checkStmt->execute([$companyId, $itemId]);
                
                if ($checkStmt->fetch()) {
                    error_log("Item already exists for this company");
                } else {
                    // 품목 정보 조회
                    $itemStmt = $pdo->prepare("
                        SELECT item_name, display_order FROM items 
                        WHERE id = ? AND active = 1
                    ");
                    $itemStmt->execute([$itemId]);
                    $item = $itemStmt->fetch();
                    
                    if ($item) {
                        // 순서 계산: display_order를 사용하되, 중복이면 마지막 순서 + 1
                        $orderStmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM company_items 
                            WHERE company_id = ? AND item_order = ? AND active = 1
                        ");
                        $orderStmt->execute([$companyId, $item['display_order']]);
                        $orderExists = $orderStmt->fetch()['count'] > 0;
                        
                        $finalOrder = $orderExists ? 
                            $pdo->query("SELECT COALESCE(MAX(item_order), 0) + 1 FROM company_items WHERE company_id = $companyId AND active = 1")->fetchColumn() :
                            $item['display_order'];
                        
                        error_log("Using order: $finalOrder for item: " . $item['item_name']);
                        
                        // 품목 추가
                        $insertStmt = $pdo->prepare("
                            INSERT INTO company_items (company_id, item_id, item_name, item_order, active, status, approved_at, approved_by, created_at)
                            VALUES (?, ?, ?, ?, 1, 'approved', NOW(), ?, NOW())
                        ");
                        $insertStmt->execute([$companyId, $itemId, $item['item_name'], $finalOrder, $approvedBy]);
                        
                        error_log("Item added successfully");
                    } else {
                        error_log("Item not found or inactive: itemId=$itemId");
                    }
                }
                
            } elseif ($requestAction === 'remove') {
                // 품목 제거: company_items 테이블에서 해당 레코드 비활성화
                $stmt = $pdo->prepare("
                    UPDATE company_items 
                    SET active = 0, status = 'rejected', approved_at = NOW(), approved_by = ?, reason = ?
                    WHERE company_id = ? AND item_id = ? AND active = 1
                ");
                $stmt->execute([$approvedBy, $reason, $companyId, $itemId]);
            }
            
            // 요청 상태를 승인으로 업데이트
            $stmt = $pdo->prepare("
                UPDATE company_item_requests 
                SET status = 'approved', approved_at = NOW(), approved_by = ?, reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$approvedBy, $reason, $requestId]);
            
            return ['success' => true, 'message' => '품목 요청이 승인되었습니다.'];
            
        } elseif ($action === 'reject') {
            // 거부 처리: 요청 상태만 거부로 업데이트
            $stmt = $pdo->prepare("
                UPDATE company_item_requests 
                SET status = 'rejected', approved_at = NOW(), approved_by = ?, reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$approvedBy, $reason, $requestId]);
            
            return ['success' => true, 'message' => '품목 요청이 거부되었습니다.'];
            
        } else {
            return ['success' => false, 'message' => '잘못된 액션입니다.'];
        }
        
    } catch (Exception $e) {
        error_log("Process item request error: " . $e->getMessage());
        return ['success' => false, 'message' => '요청 처리 중 오류가 발생했습니다.'];
    }
}

/**
 * 모든 품목 요청 조회 (관리자용)
 */
function getAllCompanyItemRequests() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                cir.id as request_id,
                cir.company_id,
                c.company_name,
                cir.item_id,
                i.item_name,
                i.description,
                cat.category_name,
                cir.action as request_action,
                cir.status,
                cir.requested_at,
                cir.approved_at,
                cir.approved_by,
                cir.reason
            FROM company_item_requests cir
            LEFT JOIN companies c ON cir.company_id = c.id
            LEFT JOIN items i ON cir.item_id = i.id
            LEFT JOIN categories cat ON i.category_id = cat.id
            WHERE cir.status = 'pending'
            ORDER BY cir.requested_at ASC
        ");
        $stmt->execute();
        $requests = $stmt->fetchAll();
        
        return [
            'success' => true,
            'requests' => $requests
        ];
        
    } catch (Exception $e) {
        error_log("Get all company item requests error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 요청 조회 중 오류가 발생했습니다.'];
    }
}

/**
 * 대기 중인 품목 요청 개수 조회
 */
function getPendingItemRequestCount() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM company_item_requests 
            WHERE status = 'pending'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Get pending item request count error: " . $e->getMessage());
        return 0;
    }
}

// registration-functions.php 호출
require_once 'registration-functions.php';
?>