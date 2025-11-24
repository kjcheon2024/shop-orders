<?php
/**
 * admin.php - 업체 등록 승인 관리 페이지 (order_status 테이블 기반으로 수정된 버전)
 */
 
session_start();
require_once 'config.php';
require_once 'functions.php';

// 보안 강화된 관리자 인증
if (isset($_POST['admin_login'])) {
    $input_password = $_POST['admin_password'] ?? '';
    
    // 비밀번호 해시 검증 (임시로 평문 'admin2025'도 허용)
    if (password_verify($input_password, ADMIN_PASSWORD_HASH) || $input_password === 'admin2025') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time(); // 로그인 시간 기록
        
        // 로그인 성공 로그
        error_log("Admin login successful from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // 평문 비밀번호로 로그인한 경우 경고 로그
        if ($input_password === 'admin2025') {
            error_log("WARNING: Admin logged in with fallback password. Please update hash in config.php");
        }
    } else {
        $login_error = '관리자 비밀번호가 올바르지 않습니다.';
        
        // 로그인 실패 로그 (보안 모니터링용)
        error_log("Admin login failed from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - Invalid password");
    }
}

if (isset($_GET['logout'])) {
    // 로그아웃 로그
    error_log("Admin logout from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // 세션 완전 정리
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
    session_destroy();
    
    header('Location: admin.php');
    exit;
}

// 세션 타임아웃 검증
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    $login_time = $_SESSION['admin_login_time'] ?? 0;
    $current_time = time();
    
    // 세션 만료 확인
    if (($current_time - $login_time) > ADMIN_SESSION_TIMEOUT) {
        // 세션 만료 로그
        error_log("Admin session expired for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_login_time']);
        session_destroy();
        
        $login_error = '세션만료 - 다시 로그인 해 주세요.';
        $_SESSION['admin_logged_in'] = false;
    }
}

// 로그인 확인
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    // 업체 품목관리 관련 액션들은 로그인 없이 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if (in_array($action, ['getCompanyItemRequestStatus', 'createCompanyItemRequest', 'getAllCompanyItemRequests', 'processItemRequest'])) {
            header('Content-Type: application/json');
            
            try {
                switch($action) {
                    case 'getCompanyItemRequestStatus':
                        $companyId = intval($_POST['companyId']);
                        $result = getCompanyItemRequestStatus($companyId);
                        echo json_encode($result);
                        break;
                        
                    case 'createCompanyItemRequest':
                        $companyId = intval($_POST['companyId']);
                        $itemId = intval($_POST['itemId']);
                        $action = $_POST['action']; // 'add' or 'remove'
                        $result = createCompanyItemRequest($companyId, $itemId, $action);
                        echo json_encode($result);
                        break;
                        
                    case 'getAllCompanyItemRequests':
                        $result = getAllCompanyItemRequests();
                        echo json_encode($result);
                        break;
                        
                    case 'processItemRequest':
                        $requestId = intval($_POST['requestId']);
                        $requestAction = $_POST['requestAction']; // 'approve' or 'reject'
                        $approvedBy = $_POST['approvedBy'] ?? 'admin';
                        $reason = $_POST['reason'] ?? null;
                        
                        $result = processItemRequest($requestId, $requestAction, $approvedBy, $reason);
                        echo json_encode($result);
                        break;
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '오류 발생: ' . $e->getMessage()]);
            }
            exit;
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>관리자</title>
        <link rel="stylesheet" href="assets/css/admin-form.css">
    </head>
    <body class="login-body">
        <div class="login-form">
            <h2>관리자</h2>
            <?php if (isset($login_error)): ?>
                <div class="error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="password" name="admin_password" placeholder="관리자 비밀번호를 입력하세요" required>
                <button type="submit" name="admin_login">로그인</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    try {
        switch($action) {
            // ========================================
            // 기존 승인 관리 액션들
            // ========================================
            case 'approveWithSettings':
                $requestId = intval($_POST['requestId']);
                $deliveryDay = $_POST['deliveryDay'] ?? '';
                $itemGroup = $_POST['itemGroup'] ?? '';
                
                $result = approveRegistrationRequestWithSettings($requestId, $deliveryDay, $itemGroup, 'admin');
                echo json_encode($result);
                break;
                
            case 'reject':
                $requestId = intval($_POST['requestId']);
                $reason = $_POST['reason'] ?? '관리자에 의한 거부';
                
                $result = rejectRegistrationRequest($requestId, $reason, 'admin');
                echo json_encode($result);
                break;
            
            // ========================================
            // 카테고리 관리 액션들
            // ========================================
            case 'getCategories':
                $result = getCategories();
                echo json_encode($result);
                break;
                
            case 'addCategory':
                $categoryName = trim($_POST['categoryName'] ?? '');
                $categoryDesc = trim($_POST['categoryDesc'] ?? '');
                $categoryOrder = intval($_POST['categoryOrder'] ?? 1);
                
                if (empty($categoryName)) {
                    echo json_encode(['success' => false, 'message' => '카테고리명을 입력해주세요.']);
                    break;
                }
                
                $result = addCategory($categoryName, $categoryDesc, $categoryOrder);
                echo json_encode($result);
                break;
                
            case 'updateCategory':
                $categoryId = intval($_POST['categoryId']);
                $categoryName = trim($_POST['categoryName'] ?? '');
                $categoryDesc = trim($_POST['categoryDesc'] ?? '');
                $categoryOrder = intval($_POST['categoryOrder'] ?? 1);
                
                if (empty($categoryName)) {
                    echo json_encode(['success' => false, 'message' => '카테고리명을 입력해주세요.']);
                    break;
                }
                
                $result = updateCategory($categoryId, $categoryName, $categoryDesc, $categoryOrder);
                echo json_encode($result);
                break;
                
            case 'deleteCategory':
                $categoryId = intval($_POST['categoryId']);
                $result = deleteCategory($categoryId);
                echo json_encode($result);
                break;
                
            case 'swapCategoryOrder':
                $draggedId = intval($_POST['draggedId'] ?? 0);
                $targetId = intval($_POST['targetId'] ?? 0);
                $result = swapCategoryOrder($draggedId, $targetId);
                echo json_encode($result);
                break;
                
            case 'reorderCategories':
                $result = reorderCategories();
                echo json_encode($result);
                break;
            
            // ========================================
            // 품목 관리 액션들
            // ========================================
            case 'getItems':
                $result = getItems();
                echo json_encode($result);
                break;
                
            case 'addItem':
                $categoryId = intval($_POST['categoryId']);
                $itemName = trim($_POST['itemName'] ?? '');
                $itemDesc = trim($_POST['itemDesc'] ?? '');
                $itemOrder = intval($_POST['itemOrder'] ?? 1);
                
                if (empty($itemName)) {
                    echo json_encode(['success' => false, 'message' => '품목명을 입력해주세요.']);
                    break;
                }
                
                if ($categoryId <= 0) {
                    echo json_encode(['success' => false, 'message' => '카테고리를 선택해주세요.']);
                    break;
                }
                
                $result = addItem($categoryId, $itemName, $itemDesc, $itemOrder);
                echo json_encode($result);
                break;
                
            case 'updateItem':
                $itemId = intval($_POST['itemId']);
                $itemName = trim($_POST['itemName'] ?? '');
                $itemDesc = trim($_POST['itemDesc'] ?? '');
                $itemOrder = intval($_POST['itemOrder'] ?? 1);
                
                if (empty($itemName)) {
                    echo json_encode(['success' => false, 'message' => '품목명을 입력해주세요.']);
                    break;
                }
                
                $result = updateItem($itemId, $itemName, $itemDesc, $itemOrder);
                echo json_encode($result);
                break;
                
            case 'deleteItem':
                $itemId = intval($_POST['itemId']);
                $result = deleteItem($itemId);
                echo json_encode($result);
                break;
                
            case 'updateItemOrder':
                $itemId = intval($_POST['itemId']);
                $newOrder = intval($_POST['newOrder']);
                $result = updateItemOrder($itemId, $newOrder);
                echo json_encode($result);
                break;
                
            case 'swapItemOrder':
                $draggedId = intval($_POST['draggedId'] ?? 0);
                $targetId = intval($_POST['targetId'] ?? 0);
                $result = swapItemOrder($draggedId, $targetId);
                echo json_encode($result);
                break;

            case 'reorderItems':
                $categoryId = intval($_POST['categoryId']);
                $result = reorderItems($categoryId);
                echo json_encode($result);
                break;
            
            // ========================================
            // 공지 관련 액션들 (신규 추가)
            // ========================================
            case 'getActiveCompaniesForNotice':
                echo json_encode(getActiveCompaniesForNotice());
                break;

            case 'getNoticeList':
                echo json_encode(getNoticeList());
                break;

            case 'createNotice':
                $noticeType = $_POST['noticeType'] ?? '';
                $title = $_POST['title'] ?? '';
                $message = $_POST['message'] ?? '';
                $priority = intval($_POST['priority'] ?? 0);
                $expires = $_POST['expires'] ?? null;
                $companyIds = !empty($_POST['companyIds']) ? array_map('intval', explode(',', $_POST['companyIds'])) : [];
                
                echo json_encode(createNotice($noticeType, $title, $message, $companyIds, $priority, $expires));
                break;

            case 'updateNotice':
                $noticeId = intval($_POST['noticeId'] ?? 0);
                $title = $_POST['title'] ?? '';
                $message = $_POST['message'] ?? '';
                $priority = intval($_POST['priority'] ?? 0);
                $expires = $_POST['expires'] ?? null;
                $companyIds = !empty($_POST['companyIds']) ? array_map('intval', explode(',', $_POST['companyIds'])) : [];
                
                echo json_encode(updateNotice($noticeId, $title, $message, $companyIds, $priority, $expires));
                break;

            case 'deleteNotice':
                $noticeId = intval($_POST['noticeId'] ?? 0);
                echo json_encode(deleteNotice($noticeId));
                break;
            
            // ========================================
            // 설정 관리 관련 액션들 (신규 추가)
            // ========================================
            case 'getSheetConfigs':
                echo json_encode(getSheetConfigs());
                break;
                
            case 'getItemGroups':
                echo json_encode(getItemGroups());
                break;
                
            case 'updateSheetConfig':
                $id = intval($_POST['id'] ?? 0);
                $sheetName = $_POST['sheetName'] ?? '';
                $description = $_POST['description'] ?? '';
                
                echo json_encode(updateSheetConfig($id, $sheetName, $description));
                break;
                
            case 'updateItemGroup':
                $id = intval($_POST['id'] ?? 0);
                $groupName = $_POST['groupName'] ?? '';
                $description = $_POST['description'] ?? '';
                
                echo json_encode(updateItemGroup($id, $groupName, $description));
                break;
                
            case 'deleteSheetConfig':
                $id = intval($_POST['id'] ?? 0);
                echo json_encode(deleteSheetConfig($id));
                break;
                
            case 'deleteItemGroup':
                $id = intval($_POST['id'] ?? 0);
                echo json_encode(deleteItemGroup($id));
                break;

            case 'toggleNoticeStatus':
                $noticeId = intval($_POST['noticeId'] ?? 0);
                $isActive = $_POST['isActive'] === '1';
                echo json_encode(toggleNoticeStatus($noticeId, $isActive));
                break;

            case 'getNoticeDetail':
                $noticeId = intval($_POST['noticeId'] ?? 0);
                echo json_encode(getNoticeDetail($noticeId));
                break;
            
            // ========================================
            // 관리자 비밀번호 변경 액션들 (신규 추가)
            // ========================================
            case 'validatePasswordStrength':
                $password = $_POST['password'] ?? '';
                $result = validatePasswordStrength($password);
                echo json_encode($result);
                break;
                
            case 'changeAdminPassword':
                $currentPassword = $_POST['currentPassword'] ?? '';
                $newPassword = $_POST['newPassword'] ?? '';
                $confirmPassword = $_POST['confirmPassword'] ?? '';
                
                // 입력값 검증
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    echo json_encode(['success' => false, 'message' => '모든 필드를 입력해주세요.']);
                    break;
                }
                
                // 새 비밀번호 확인
                if ($newPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'message' => '새 비밀번호와 확인 비밀번호가 일치하지 않습니다.']);
                    break;
                }
                
                // 현재 비밀번호 검증 (임시로 평문도 허용)
                if (!verifyCurrentAdminPassword($currentPassword) && $currentPassword !== 'admin2025') {
                    echo json_encode(['success' => false, 'message' => '현재 비밀번호가 올바르지 않습니다.']);
                    break;
                }
                
                // 새 비밀번호 강도 검증
                $validation = validatePasswordStrength($newPassword);
                if (!$validation['valid']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => '비밀번호 요구사항을 만족하지 않습니다.',
                        'errors' => $validation['errors']
                    ]);
                    break;
                }
                
                // 현재 비밀번호와 동일한지 확인
                if (verifyCurrentAdminPassword($newPassword) || $newPassword === 'admin2025') {
                    echo json_encode(['success' => false, 'message' => '새 비밀번호는 현재 비밀번호와 달라야 합니다.']);
                    break;
                }
                
                // 비밀번호 변경 실행
                $result = changeAdminPassword($newPassword);
                echo json_encode($result);
                break;
            
            // ========================================
            // 업체-품목 할당 관리 액션들
            // ========================================
            case 'getCompanies':
                $result = getCompanies();
                echo json_encode($result);
                break;
                
            case 'getCompanyAssignments':
                $companyId = intval($_POST['companyId']);
                $result = getCompanyAssignments($companyId);
                echo json_encode($result);
                break;
                
            case 'getAvailableItems':
                $companyId = intval($_POST['companyId']);
                $result = getAvailableItems($companyId);
                echo json_encode($result);
                break;
                
            case 'saveAssignments':
                $companyId = intval($_POST['companyId']);
                $itemIds = $_POST['itemIds'] ?? '';
                $itemIdsArray = array_filter(array_map('intval', explode(',', $itemIds)));
                
                $result = saveCompanyAssignments($companyId, $itemIdsArray);
                echo json_encode($result);
                break;
                
            case 'removeAssignment':
                $assignmentId = intval($_POST['assignmentId']);
                $result = removeCompanyAssignment($assignmentId);
                echo json_encode($result);
                break;
                
            // ========================================
            // 업체 품목 요청 관리 액션들 (신규 추가)
            // ========================================
            case 'getAllCompanyItemRequests':
                $result = getAllCompanyItemRequests();
                echo json_encode($result);
                break;
                
            case 'processItemRequest':
                $requestId = intval($_POST['requestId']);
                $requestAction = $_POST['requestAction']; // 'approve' or 'reject'
                $approvedBy = $_POST['approvedBy'] ?? 'admin';
                $reason = $_POST['reason'] ?? null;
                
                $result = processItemRequest($requestId, $requestAction, $approvedBy, $reason);
                echo json_encode($result);
                break;
                
            // ========================================
            // 개별 품목 할당 액션들 (수정됨)
            // ========================================
            case 'getUnassignedItems':
                $companyId = intval($_POST['companyId']);
                $result = getUnassignedItems($companyId);
                echo json_encode($result);
                break;
                
            case 'addIndividualAssignment':
                $companyId = intval($_POST['companyId']);
                $itemIds = $_POST['itemIds'] ?? '';
                $itemIdsArray = array_filter(array_map('intval', explode(',', $itemIds)));
                
                $result = addIndividualCompanyAssignments($companyId, $itemIdsArray);
                echo json_encode($result);
                break;
				
            // ========================================
            // 주문확인 관련 액션들 (order_status 기반으로 수정)
            // ========================================
            case 'getOrdersByDate':
                $selectedDate = $_POST['selectedDate'] ?? date('Y-m-d');
                $result = getOrdersByDate($selectedDate);
                echo json_encode($result);
                break;
                
            case 'getOrderDateRange':
                $result = getOrderDateRange();
                echo json_encode($result);
                break;
                
            // ========================================
            // 업체목록 관리 액션들
            // ========================================
            case 'getAllCompaniesWithStatus':
                $result = getAllCompaniesWithStatus();
                echo json_encode($result);
                break;
                
            case 'toggleCompanyOrderBlock':
                $companyId = intval($_POST['companyId']);
                $isBlocked = $_POST['isBlocked'] === 'true';
                $blockReason = trim($_POST['blockReason'] ?? '');
                
                $result = toggleCompanyOrderBlock($companyId, $isBlocked, $blockReason);
                echo json_encode($result);
                break;
                
            case 'updateBlockReason':
                $companyId = intval($_POST['companyId']);
                $blockReason = trim($_POST['blockReason'] ?? '');
                
                $result = updateCompanyBlockReason($companyId, $blockReason);
                echo json_encode($result);
                break;
                
            case 'updateCompanyInfo':
                $companyId = intval($_POST['companyId']);
                $companyName = trim($_POST['companyName'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $contactPerson = trim($_POST['contactPerson'] ?? '');
                $phoneNumber = trim($_POST['phoneNumber'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $companyAddress = trim($_POST['companyAddress'] ?? '');
                $zipCode = trim($_POST['zipCode'] ?? '');
                $itemGroup = trim($_POST['itemGroup'] ?? '');
                
                $result = updateCompanyInfoByAdmin($companyId, $companyName, $password, $contactPerson, $phoneNumber, $email, $companyAddress, $zipCode, $itemGroup);
                echo json_encode($result);
                break;
                
            case 'getActiveItemGroups':
                $result = getActiveItemGroups();
                echo json_encode($result);
                break;
                
            case 'updateCompanyGroup':
                $companyId = intval($_POST['companyId']);
                $itemGroup = trim($_POST['itemGroup'] ?? '');
                
                $result = updateCompanyGroup($companyId, $itemGroup);
                echo json_encode($result);
                break;
                
            case 'addCompanyByAdmin':
                $companyName = trim($_POST['companyName'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $contactPerson = trim($_POST['contactPerson'] ?? '');
                $phoneNumber = trim($_POST['phoneNumber'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $zipCode = trim($_POST['zipCode'] ?? '');
                $itemGroup = trim($_POST['itemGroup'] ?? '');
                
                $result = addCompanyByAdmin($companyName, $password, $contactPerson, $phoneNumber, $email, $address, $zipCode, $itemGroup);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '알 수 없는 액션입니다.']);
                break;
        }
    } catch (Exception $e) {
        error_log("Admin action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
    }
    exit;
}

// ========================================
// 관리 함수들
// ========================================

/**
 * 카테고리 관리 함수들
 */
function getCategories() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY display_order ASC, category_name ASC");
        $categories = $stmt->fetchAll();
        
        return ['success' => true, 'categories' => $categories];
    } catch (Exception $e) {
        error_log("Get categories error: " . $e->getMessage());
        return ['success' => false, 'message' => '카테고리 조회 중 오류가 발생했습니다.'];
    }
}

function addCategory($categoryName, $description, $displayOrder) {
    try {
        $pdo = getDBConnection();
        
        // 중복 체크
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ? AND active = 1");
        $stmt->execute([$categoryName]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '이미 존재하는 카테고리명입니다.'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO categories (category_name, description, display_order) VALUES (?, ?, ?)");
        $stmt->execute([$categoryName, $description, $displayOrder]);
        
        return ['success' => true, 'message' => '카테고리가 성공적으로 추가되었습니다.'];
    } catch (Exception $e) {
        error_log("Add category error: " . $e->getMessage());
        return ['success' => false, 'message' => '카테고리 추가 중 오류가 발생했습니다.'];
    }
}

function updateCategory($categoryId, $categoryName, $description, $displayOrder) {
    try {
        $pdo = getDBConnection();
        
        // 중복 체크 (자신 제외)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ? AND id != ? AND active = 1");
        $stmt->execute([$categoryName, $categoryId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '이미 존재하는 카테고리명입니다.'];
        }
        
        $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ?, display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$categoryName, $description, $displayOrder, $categoryId]);
        
        return ['success' => true, 'message' => '카테고리가 성공적으로 수정되었습니다.'];
    } catch (Exception $e) {
        error_log("Update category error: " . $e->getMessage());
        return ['success' => false, 'message' => '카테고리 수정 중 오류가 발생했습니다.'];
    }
}

function deleteCategory($categoryId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 카테고리에 속한 품목이 있는지 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ? AND active = 1");
        $stmt->execute([$categoryId]);
        $itemCount = $stmt->fetchColumn();
        
        if ($itemCount > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '이 카테고리에 속한 품목이 있어 삭제할 수 없습니다. 먼저 품목을 삭제하거나 다른 카테고리로 이동해주세요.'];
        }
        
        // 실제 데이터베이스에서 완전 삭제 (hard delete)
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        
        $pdo->commit();
        
        // 캐시 무효화
        clearCache('companies_data');
        
        return ['success' => true, 'message' => '카테고리가 완전히 삭제되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

/**
 * 두 카테고리의 순서를 스왑 (원자적)
 */
function swapCategoryOrder($draggedId, $targetId) {
    try {
        $pdo = getDBConnection();
        
        // 두 카테고리 조회
        $stmt = $pdo->prepare("SELECT id, display_order FROM categories WHERE id IN (?, ?) AND active = 1");
        $stmt->execute([$draggedId, $targetId]);
        $categories = $stmt->fetchAll();
        if (count($categories) !== 2) {
            return ['success' => false, 'message' => '카테고리를 찾을 수 없습니다.'];
        }
        
        $a = $categories[0];
        $b = $categories[1];
        
        $pdo->beginTransaction();
        // 임시 값 사용하여 충돌 없이 스왑
        $stmt = $pdo->prepare("UPDATE categories SET display_order = -1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$a['id']]);
        
        $stmt = $pdo->prepare("UPDATE categories SET display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$a['display_order'], $b['id']]);
        
        $stmt = $pdo->prepare("UPDATE categories SET display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$b['display_order'], $a['id']]);
        
        $pdo->commit();
        return ['success' => true, 'message' => '카테고리 순서가 변경되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Swap category order error: " . $e->getMessage());
        return ['success' => false, 'message' => '카테고리 순서 변경 중 오류가 발생했습니다.'];
    }
}

/**
 * 모든 카테고리 순서 자동 재정렬
 */
function reorderCategories() {
    try {
        $pdo = getDBConnection();
        
        // 모든 카테고리를 순서대로 조회
        $stmt = $pdo->query("SELECT id FROM categories WHERE active = 1 ORDER BY display_order ASC, id ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 순서를 1부터 연속으로 재할당
        $pdo->beginTransaction();
        foreach ($categories as $index => $categoryId) {
            $newOrder = $index + 1;
            $stmt = $pdo->prepare("UPDATE categories SET display_order = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newOrder, $categoryId]);
        }
        $pdo->commit();
        
        return ['success' => true, 'message' => '카테고리 순서가 자동으로 재정렬되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Reorder categories error: " . $e->getMessage());
        return ['success' => false, 'message' => '카테고리 순서 재정렬 중 오류가 발생했습니다.'];
    }
}

/**
 * 품목 관리 함수들
 */
function getItems() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT i.id as item_id, i.item_name, i.description, i.display_order, i.category_id,
                   c.category_name
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.active = 1 AND c.active = 1
            ORDER BY c.display_order ASC, i.display_order ASC, i.item_name ASC
        ");
        $items = $stmt->fetchAll();
        
        return ['success' => true, 'items' => $items];
    } catch (Exception $e) {
        error_log("Get items error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 조회 중 오류가 발생했습니다.'];
    }
}

function addItem($categoryId, $itemName, $description, $displayOrder) {
    try {
        $pdo = getDBConnection();
        
        // 카테고리 존재 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND active = 1");
        $stmt->execute([$categoryId]);
        if ($stmt->fetchColumn() == 0) {
            return ['success' => false, 'message' => '존재하지 않는 카테고리입니다.'];
        }
        
        // 중복 체크 (같은 카테고리 내)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE item_name = ? AND category_id = ? AND active = 1");
        $stmt->execute([$itemName, $categoryId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '이 카테고리에 이미 존재하는 품목명입니다.'];
        }
        
        // 자동 순서 할당: 해당 카테고리의 최대 순서 + 1
        if ($displayOrder <= 0) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 FROM items WHERE category_id = ? AND active = 1");
            $stmt->execute([$categoryId]);
            $displayOrder = $stmt->fetchColumn();
        }
        
        $stmt = $pdo->prepare("INSERT INTO items (item_name, category_id, description, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$itemName, $categoryId, $description, $displayOrder]);
        
        return ['success' => true, 'message' => '품목이 성공적으로 추가되었습니다.'];
    } catch (Exception $e) {
        error_log("Add item error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 추가 중 오류가 발생했습니다.'];
    }
}

function updateItem($itemId, $itemName, $description, $displayOrder) {
    try {
        $pdo = getDBConnection();
        
        // 현재 품목의 카테고리 조회
        $stmt = $pdo->prepare("SELECT category_id FROM items WHERE id = ? AND active = 1");
        $stmt->execute([$itemId]);
        $categoryId = $stmt->fetchColumn();
        
        if (!$categoryId) {
            return ['success' => false, 'message' => '존재하지 않는 품목입니다.'];
        }
        
        // 중복 체크 (같은 카테고리 내, 자신 제외)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE item_name = ? AND category_id = ? AND id != ? AND active = 1");
        $stmt->execute([$itemName, $categoryId, $itemId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '이 카테고리에 이미 존재하는 품목명입니다.'];
        }
        
        $stmt = $pdo->prepare("UPDATE items SET item_name = ?, description = ?, display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$itemName, $description, $displayOrder, $itemId]);
        
        return ['success' => true, 'message' => '품목이 성공적으로 수정되었습니다.'];
    } catch (Exception $e) {
        error_log("Update item error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 수정 중 오류가 발생했습니다.'];
    }
}

function deleteItem($itemId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 할당된 업체가 있는지 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM company_items WHERE item_id = ? AND active = 1");
        $stmt->execute([$itemId]);
        $assignmentCount = $stmt->fetchColumn();
        
        if ($assignmentCount > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '이 품목을 사용하는 업체가 있어 삭제할 수 없습니다. 먼저 업체 할당을 해제해주세요.'];
        }
        
        // 먼저 관련된 company_items에서 비활성화된 할당도 모두 삭제 (정리)
        $stmt = $pdo->prepare("DELETE FROM company_items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        
        // 실제 데이터베이스에서 품목 완전 삭제 (hard delete)
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        
        $pdo->commit();
        
        // 캐시 무효화
        clearCache('companies_data');
        
        return ['success' => true, 'message' => '품목이 완전히 삭제되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete item error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 삭제 중 오류가 발생했습니다.'];
    }
}

/**
 * 품목 순서 업데이트
 */
function updateItemOrder($itemId, $newOrder) {
    try {
        $pdo = getDBConnection();
        
        // 품목 존재 확인
        $stmt = $pdo->prepare("SELECT id, category_id FROM items WHERE id = ? AND active = 1");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            return ['success' => false, 'message' => '존재하지 않는 품목입니다.'];
        }
        
        // 같은 카테고리 내에서 순서 업데이트
        $stmt = $pdo->prepare("UPDATE items SET display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newOrder, $itemId]);
        
        return ['success' => true, 'message' => '품목 순서가 업데이트되었습니다.'];
    } catch (Exception $e) {
        error_log("Update item order error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 순서 업데이트 중 오류가 발생했습니다.'];
    }
}

/**
 * 카테고리별 품목 순서 자동 재정렬
 */
function reorderItems($categoryId) {
    try {
        $pdo = getDBConnection();
        
        // 카테고리 존재 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND active = 1");
        $stmt->execute([$categoryId]);
        if ($stmt->fetchColumn() == 0) {
            return ['success' => false, 'message' => '존재하지 않는 카테고리입니다.'];
        }
        
        // 해당 카테고리의 모든 품목을 순서대로 조회
        $stmt = $pdo->prepare("SELECT id FROM items WHERE category_id = ? AND active = 1 ORDER BY display_order ASC, id ASC");
        $stmt->execute([$categoryId]);
        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 순서를 1부터 연속으로 재할당
        $pdo->beginTransaction();
        foreach ($items as $index => $itemId) {
            $newOrder = $index + 1;
            $stmt = $pdo->prepare("UPDATE items SET display_order = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newOrder, $itemId]);
        }
        $pdo->commit();
        
        return ['success' => true, 'message' => '품목 순서가 자동으로 재정렬되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Reorder items error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 순서 재정렬 중 오류가 발생했습니다.'];
    }
}

/**
 * 두 품목의 순서를 같은 카테고리 내에서 스왑 (원자적)
 */
function swapItemOrder($draggedId, $targetId) {
    try {
        $pdo = getDBConnection();
        
        // 두 아이템 조회 및 카테고리 동일성 확인
        $stmt = $pdo->prepare("SELECT id, category_id, display_order FROM items WHERE id IN (?, ?) AND active = 1");
        $stmt->execute([$draggedId, $targetId]);
        $items = $stmt->fetchAll();
        if (count($items) !== 2) {
            return ['success' => false, 'message' => '품목을 찾을 수 없습니다.'];
        }
        
        $a = $items[0];
        $b = $items[1];
        if ($a['category_id'] !== $b['category_id']) {
            return ['success' => false, 'message' => '서로 다른 카테고리 간 순서 변경은 허용되지 않습니다.'];
        }
        
        $pdo->beginTransaction();
        // 임시 값 사용하여 충돌 없이 스왑
        $stmt = $pdo->prepare("UPDATE items SET display_order = -1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$a['id']]);
        
        $stmt = $pdo->prepare("UPDATE items SET display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$a['display_order'], $b['id']]);
        
        $stmt = $pdo->prepare("UPDATE items SET display_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$b['display_order'], $a['id']]);
        
        $pdo->commit();
        return ['success' => true, 'message' => '순서가 변경되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Swap item order error: " . $e->getMessage());
        return ['success' => false, 'message' => '순서 변경 중 오류가 발생했습니다.'];
    }
}

function getCompanies() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT id, company_name, item_group, delivery_day FROM companies WHERE active = 1 ORDER BY company_name ASC");
        $companies = $stmt->fetchAll();
        
        return ['success' => true, 'companies' => $companies];
    } catch (Exception $e) {
        error_log("Get companies error: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 조회 중 오류가 발생했습니다.'];
    }
}

function getCompanyAssignments($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 업체 정보 조회
        $stmt = $pdo->prepare("SELECT company_name, item_group, delivery_day, created_at FROM companies WHERE id = ? AND active = 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '존재하지 않는 업체입니다.'];
        }
        
        // 할당된 품목 조회
        $stmt = $pdo->prepare("
            SELECT ci.id as assignment_id, ci.item_id, ci.item_name as assigned_item_name, 
                   ci.item_order, i.item_name as standard_item_name, c.category_name
            FROM company_items ci
            LEFT JOIN items i ON ci.item_id = i.id
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE ci.company_id = ? AND ci.active = 1
            ORDER BY ci.item_order ASC, ci.item_name ASC
        ");
        $stmt->execute([$companyId]);
        $assignments = $stmt->fetchAll();
        
        return [
            'success' => true, 
            'company' => $company,
            'assignments' => $assignments
        ];
    } catch (Exception $e) {
        error_log("Get company assignments error: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 할당 정보 조회 중 오류가 발생했습니다.'];
    }
}

function getAvailableItems($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 모든 활성화된 품목과 해당 업체의 할당 상태 조회
        $stmt = $pdo->prepare("
            SELECT i.id as item_id, i.item_name, i.description, c.category_name,
                   CASE WHEN ci.id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN company_items ci ON i.id = ci.item_id AND ci.company_id = ? AND ci.active = 1
            WHERE i.active = 1 AND c.active = 1
            ORDER BY c.display_order ASC, i.display_order ASC, i.item_name ASC
        ");
        $stmt->execute([$companyId]);
        $items = $stmt->fetchAll();
        
        return ['success' => true, 'items' => $items];
    } catch (Exception $e) {
        error_log("Get available items error: " . $e->getMessage());
        return ['success' => false, 'message' => '할당 가능한 품목 조회 중 오류가 발생했습니다.'];
    }
}

function saveCompanyAssignments($companyId, $itemIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 기존 할당을 완전히 삭제 (hard delete)
        $stmt = $pdo->prepare("DELETE FROM company_items WHERE company_id = ?");
        $stmt->execute([$companyId]);
        
        // 새로운 할당 추가
        if (!empty($itemIds)) {
            $stmt = $pdo->prepare("
                INSERT INTO company_items (company_id, item_id, item_name, item_order, active)
                SELECT ?, i.id, i.item_name, i.display_order, 1
                FROM items i
                WHERE i.id = ? AND i.active = 1
            ");
            
            foreach ($itemIds as $index => $itemId) {
                $stmt->execute([$companyId, $itemId]);
            }
        }
        
        $pdo->commit();
        
        // 캐시 무효화
        clearCache('companies_data');
        
        return ['success' => true, 'message' => '품목 할당이 성공적으로 저장되었습니다.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Save company assignments error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 할당 저장 중 오류가 발생했습니다.'];
    }
}

function removeCompanyAssignment($assignmentId) {
    try {
        $pdo = getDBConnection();
        
        // 할당 정보 조회 (로깅용)
        $stmt = $pdo->prepare("
            SELECT ci.company_id, ci.item_name, c.company_name 
            FROM company_items ci 
            LEFT JOIN companies c ON ci.company_id = c.id 
            WHERE ci.id = ?
        ");
        $stmt->execute([$assignmentId]);
        $assignmentInfo = $stmt->fetch();
        
        if (!$assignmentInfo) {
            return ['success' => false, 'message' => '존재하지 않는 할당 정보입니다.'];
        }
        
        // 실제 데이터베이스에서 할당 완전 삭제 (hard delete)
        $stmt = $pdo->prepare("DELETE FROM company_items WHERE id = ?");
        $stmt->execute([$assignmentId]);
        
        // 삭제 로그 기록
        error_log("Assignment removed: Company '{$assignmentInfo['company_name']}', Item '{$assignmentInfo['item_name']}', Assignment ID: {$assignmentId}");
        
        // 캐시 무효화
        clearCache('companies_data');
        
        return ['success' => true, 'message' => '품목 할당이 완전히 제거되었습니다.'];
    } catch (Exception $e) {
        error_log("Remove company assignment error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 할당 제거 중 오류가 발생했습니다.'];
    }
}

/**
 * 개별 품목 할당 관리 함수들 (수정됨)
 */
function getUnassignedItems($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 해당 업체에 할당되지 않은 품목들만 조회 (수정된 쿼리)
        $stmt = $pdo->prepare("
            SELECT i.id as item_id, i.item_name, i.description, c.category_name
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN company_items ci ON i.id = ci.item_id AND ci.company_id = ? AND ci.active = 1
            WHERE i.active = 1 AND c.active = 1 AND ci.id IS NULL
            ORDER BY c.display_order ASC, i.display_order ASC, i.item_name ASC
        ");
        $stmt->execute([$companyId]);
        $items = $stmt->fetchAll();
        
        // 디버깅을 위한 로그 추가
        error_log("getUnassignedItems - Company ID: {$companyId}, Found items: " . count($items));
        
        // 더 자세한 디버깅: 첫 5개 품목 이름 로그
        if (count($items) > 0) {
            $itemNames = array_slice(array_column($items, 'item_name'), 0, 5);
            error_log("First 5 unassigned items: " . implode(', ', $itemNames));
        }
        
        return ['success' => true, 'items' => $items];
    } catch (Exception $e) {
        error_log("Get unassigned items error: " . $e->getMessage());
        return ['success' => false, 'message' => '미할당 품목 조회 중 오류가 발생했습니다.'];
    }
}

function addIndividualCompanyAssignments($companyId, $itemIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        if (empty($itemIds)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '추가할 품목을 선택해주세요.'];
        }
        
        // 중복 할당 체크
        $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT item_id FROM company_items 
            WHERE company_id = ? AND active = 1 AND item_id IN ($placeholders)
        ");
        $params = array_merge([$companyId], $itemIds);
        $stmt->execute($params);
        $duplicateItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($duplicateItems)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '이미 할당된 품목이 포함되어 있습니다.'];
        }
        
        // 새로운 품목 할당 추가
        $stmt = $pdo->prepare("
            INSERT INTO company_items (company_id, item_id, item_name, item_order, active)
            SELECT ?, i.id, i.item_name, i.display_order, 1
            FROM items i
            WHERE i.id = ? AND i.active = 1
        ");
        
        foreach ($itemIds as $itemId) {
            $stmt->execute([$companyId, $itemId]);
        }
        
        $pdo->commit();
        
        // 캐시 무효화
        clearCache('companies_data');
        
        $addedCount = count($itemIds);
        return ['success' => true, 'message' => "{$addedCount}개의 품목이 성공적으로 추가되었습니다."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Add individual company assignments error: " . $e->getMessage());
        return ['success' => false, 'message' => '개별 품목 할당 중 오류가 발생했습니다.'];
    }
}

// 주문확인 관련 함수들 (order_status 테이블 기반으로 수정)
function getOrdersByDate($selectedDate) {
    try {
        $pdo = getDBConnection();
        
        // order_status 테이블 존재 확인
        $stmt = $pdo->query("SHOW TABLES LIKE 'order_status'");
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => '주문 테이블이 존재하지 않습니다.'];
        }
        
        // 08:00 ~ 익일 05:00 기준의 해당 일자 시간 범위 계산
        $dayStart = new DateTime($selectedDate . ' 08:00:00', new DateTimeZone('Asia/Seoul'));
        $dayEnd = clone $dayStart;
        $dayEnd->modify('+1 day')->setTime(5, 0, 0);

        $stmt = $pdo->prepare("
            SELECT os.id, os.company_name, os.item_name, os.quantity, os.order_date,
                   c.delivery_day, c.item_group
            FROM order_status os
            LEFT JOIN companies c ON os.company_name = c.company_name AND c.active = 1
            WHERE os.order_date >= ?
              AND os.order_date < ?
            ORDER BY os.order_date DESC, os.company_name ASC
        ");
        $stmt->execute([$dayStart->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s')]);
        $orders = $stmt->fetchAll();
        
        $ordersByCompany = [];
        $totalOrders = 0;
        $totalQuantity = 0;
        
        foreach ($orders as $order) {
            $companyName = $order['company_name'];
            if (!isset($ordersByCompany[$companyName])) {
                $ordersByCompany[$companyName] = [
                    'company_name' => $companyName,
                    'delivery_day' => $order['delivery_day'] ?? '미설정',
                    'item_group' => $order['item_group'] ?? '미설정',
                    'orders' => [],
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'order_time' => $order['order_date']
                ];
            }
            
            $ordersByCompany[$companyName]['orders'][] = [
                'item_name' => $order['item_name'],
                'quantity' => $order['quantity']
            ];
            $ordersByCompany[$companyName]['total_items']++;
            $ordersByCompany[$companyName]['total_quantity'] += $order['quantity'];
            
            $totalOrders++;
            $totalQuantity += $order['quantity'];
        }
        
        // 업체별 주문을 최신 주문 시간순으로 정렬
        $sortedOrdersByCompany = array_values($ordersByCompany);
        usort($sortedOrdersByCompany, function($a, $b) {
            return strtotime($b['order_time']) - strtotime($a['order_time']);
        });
        
        return [
            'success' => true,
            'date' => $selectedDate,
            'orders_by_company' => $sortedOrdersByCompany,
            'summary' => [
                'total_companies' => count($ordersByCompany),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity
            ]
        ];
    } catch (Exception $e) {
        error_log("Get orders by date error: " . $e->getMessage());
        return ['success' => false, 'message' => '주문 조회 중 오류가 발생했습니다.'];
    }
}

function getOrderDateRange() {
    try {
        $pdo = getDBConnection();
        
        // order_status 테이블 존재 확인
        $stmt = $pdo->query("SHOW TABLES LIKE 'order_status'");
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => '주문 테이블이 존재하지 않습니다.'];
        }
        
        // 08:00 ~ 익일 05:00 기준으로 일자를 변환하여 범위를 계산
        // 해당일 08:00 ~ 익일 05:00을 당일 주문으로 인식
        // 주의: 05:00 ~ 08:00 사이에는 주문이 없음 (주문 불가 시간대)
        // 예: 2024-01-01 08:00 ~ 2024-01-02 05:00 사이의 주문은 2024-01-01로 조회
        // 예: 2024-01-01 00:00 ~ 2024-01-01 05:00 사이의 주문은 2023-12-31로 조회
        $stmt = $pdo->query("\n            SELECT\n                MIN(business_day) as min_date,\n                MAX(business_day) as max_date,\n                COUNT(DISTINCT business_day) as total_days\n            FROM (\n                SELECT CASE\n                    WHEN HOUR(order_date) < 5 THEN DATE_SUB(DATE(order_date), INTERVAL 1 DAY)\n                    ELSE DATE(order_date)\n                END AS business_day\n                FROM order_status\n            ) t\n        ");
        $result = $stmt->fetch();
        
        if (!$result || !$result['min_date']) {
            return [
                'success' => true,
                'min_date' => date('Y-m-d'),
                'max_date' => date('Y-m-d'),
                'total_days' => 0
            ];
        }
        
        return [
            'success' => true,
            'min_date' => $result['min_date'],
            'max_date' => $result['max_date'],
            'total_days' => $result['total_days']
        ];
    } catch (Exception $e) {
        error_log("Get order date range error: " . $e->getMessage());
        return ['success' => false, 'message' => '주문 날짜 조회 중 오류가 발생했습니다.'];
    }
}

// 업체목록 관리 함수들
function getAllCompaniesWithStatus() {
    try {
        $pdo = getDBConnection();
        
        // 업체 테이블에 order_blocked, block_reason 컬럼 존재 여부 확인 및 추가
        $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'order_blocked'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN order_blocked TINYINT(1) DEFAULT 0");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'block_reason'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN block_reason VARCHAR(500) DEFAULT NULL");
        }
        
        // 모든 업체 정보 조회
        $stmt = $pdo->query("
            SELECT c.id, c.company_name, c.contact_person, c.phone_number, 
                   c.delivery_day, c.item_group, c.created_at,
                   c.order_blocked, c.block_reason,
                   COUNT(ci.id) as assigned_items_count
            FROM companies c
            LEFT JOIN company_items ci ON c.id = ci.company_id AND ci.active = 1
            WHERE c.active = 1
            GROUP BY c.id
            ORDER BY c.order_blocked ASC, c.company_name ASC
        ");
        $companies = $stmt->fetchAll();
        
        // 각 업체의 최근 주문 정보 조회 (order_status 테이블 기반)
        foreach ($companies as &$company) {
            $stmt = $pdo->prepare("
                SELECT DATE(order_date) as last_order_date, 
                       COUNT(*) as total_orders
                FROM order_status 
                WHERE company_name = ? 
                GROUP BY DATE(order_date) 
                ORDER BY order_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$company['company_name']]);
            $lastOrder = $stmt->fetch();
            
            $company['last_order_date'] = $lastOrder ? $lastOrder['last_order_date'] : null;
            $company['last_order_count'] = $lastOrder ? $lastOrder['total_orders'] : 0;
        }
        
        return [
            'success' => true,
            'companies' => $companies,
            'total_companies' => count($companies),
            'blocked_companies' => count(array_filter($companies, function($c) { return $c['order_blocked']; }))
        ];
    } catch (Exception $e) {
        error_log("Get all companies with status error: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 목록 조회 중 오류가 발생했습니다.'];
    }
}

function toggleCompanyOrderBlock($companyId, $isBlocked, $blockReason = '') {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? AND active = 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '존재하지 않는 업체입니다.'];
        }
        
        if ($isBlocked) {
            if (empty($blockReason)) {
                return ['success' => false, 'message' => '차단 사유를 입력해주세요.'];
            }
            $stmt = $pdo->prepare("UPDATE companies SET order_blocked = 1, block_reason = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$blockReason, $companyId]);
            $message = "{$company['company_name']} 업체의 주문이 차단되었습니다.";
        } else {
            $stmt = $pdo->prepare("UPDATE companies SET order_blocked = 0, block_reason = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$companyId]);
            $message = "{$company['company_name']} 업체의 주문차단이 해제되었습니다.";
        }
        
        error_log("Company order block toggled: {$company['company_name']} - Blocked: " . ($isBlocked ? 'YES' : 'NO') . ($isBlocked ? " - Reason: {$blockReason}" : ""));
        
        return ['success' => true, 'message' => $message];
    } catch (Exception $e) {
        error_log("Toggle company order block error: " . $e->getMessage());
        return ['success' => false, 'message' => '주문차단 설정 중 오류가 발생했습니다.'];
    }
}

function updateCompanyBlockReason($companyId, $blockReason) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT company_name, order_blocked FROM companies WHERE id = ? AND active = 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['success' => false, 'message' => '존재하지 않는 업체입니다.'];
        }
        
        if (!$company['order_blocked']) {
            return ['success' => false, 'message' => '차단되지 않은 업체입니다.'];
        }
        
        if (empty($blockReason)) {
            return ['success' => false, 'message' => '차단 사유를 입력해주세요.'];
        }
        
        $stmt = $pdo->prepare("UPDATE companies SET block_reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$blockReason, $companyId]);
        
        error_log("Company block reason updated: {$company['company_name']} - New reason: {$blockReason}");
        
        return ['success' => true, 'message' => '차단 사유가 업데이트되었습니다.'];
    } catch (Exception $e) {
        error_log("Update company block reason error: " . $e->getMessage());
        return ['success' => false, 'message' => '차단 사유 업데이트 중 오류가 발생했습니다.'];
    }
}

// 대기 중인 신청 조회
$pendingRequests = getRegistrationRequests('pending');
$approvedRequests = getRegistrationRequests('approved');
$rejectedRequests = getRegistrationRequests('rejected');

// 품목 요청 개수 조회
$pendingItemRequests = getPendingItemRequestCount();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>등록관리</title>
    <link rel="stylesheet" href="assets/css/admin_vs67.css">
    <link rel="stylesheet" href="assets/css/admin-tab_v19.css">
    <style>
        .approval-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .approval-section h3 {
            margin: 0 0 20px 0;
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .approval-section:last-child {
            margin-bottom: 0;
        }
        
        .item-request-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .company-name {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .item-name {
            color: #555;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .request-date {
            color: #666;
            font-size: 12px;
        }
        
        .item-request-info .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            margin-bottom: 2px;
        }
        
        .item-request-info .info-value {
            color: #333;
            font-size: 14px;
        }
        
        .item-request-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .action-add {
            background: #d4edda;
            color: #155724;
        }
        
        .action-remove {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* 사업자등록증 파일 스타일 */
        .file-section {
            margin-bottom: 20px;
        }
        
        .file-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
            border: 1px solid #28a745;
            border-radius: 6px;
            background: #f8fff9;
        }
        
        .file-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .file-name {
            font-weight: 500;
            color: #333;
            word-break: break-all;
            margin-bottom: 8px;
        }
        
        .download-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .no-file {
            color: #666;
            font-style: italic;
            padding: 8px;
        }
        
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            .file-info {
                padding: 10px;
            }
            
            .file-name {
                font-size: 14px;
                line-height: 1.4;
            }
            
            .download-btn {
                padding: 10px 16px;
                font-size: 16px;
            }
        }
        
        /* 주문확인 탭 전용 스타일 */
        .summary-main-row {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .summary-unconfirmed-row {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #d1e7ff;
        }

        .summary-item {
            display: flex;
            gap: 5px;
            align-items: center;
            white-space: nowrap;
        }

        /* 데스크톱에서는 모든 항목을 한 줄로 표시 */
        @media (min-width: 769px) {
            .companies-summary {
                display: flex !important;
                gap: 20px !important;
                align-items: center !important;
            }
            
            .summary-main-row {
                display: flex !important;
                gap: 20px !important;
                align-items: center !important;
            }
            
            .summary-unconfirmed-row {
                display: flex !important;
                gap: 20px !important;
                align-items: center !important;
                margin-top: 0 !important;
                padding-top: 0 !important;
                border-top: none !important;
            }
            
            .summary-item {
                display: flex !important;
                gap: 5px !important;
                align-items: center !important;
                white-space: nowrap !important;
            }
        }

        /* 모바일에서는 두 줄로 표시 */
        @media (max-width: 768px) {
            .companies-summary {
                display: block !important;
            }
            
            .summary-main-row {
                display: flex !important;
                gap: 15px !important;
                align-items: center !important;
                margin-bottom: 10px !important;
            }
            
            .summary-unconfirmed-row {
                display: flex !important;
                gap: 15px !important;
                align-items: center !important;
                margin-top: 10px !important;
                padding-top: 10px !important;
                border-top: 1px solid #d1e7ff !important;
            }
            
            .summary-item {
                display: flex !important;
                gap: 5px !important;
                align-items: center !important;
                white-space: nowrap !important;
            }
        }
        
        /* 거래처 탭 업체목록 요약 - 모바일에서도 한 줄로 표시 */
        #companiesSummary.companies-summary {
            display: flex !important;
            gap: 20px !important;
            align-items: center !important;
        }
        
        @media (max-width: 768px) {
            #companiesSummary.companies-summary {
                display: flex !important;
                gap: 15px !important;
                align-items: center !important;
                flex-wrap: wrap !important;
            }
            
            #companiesSummary .summary-item {
                display: flex !important;
                gap: 5px !important;
                align-items: center !important;
                white-space: nowrap !important;
                flex: 1 !important;
                min-width: 0 !important;
            }
        }
        
        /* 확인함 버튼 스타일 */
        .confirmation-btn {
            padding: 6px 16px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 10px;
        }

        .confirmation-btn.unconfirmed {
            background-color: #007bff;
            color: white;
        }

        .confirmation-btn.confirmed {
            background-color: #6c757d;
            color: white;
        }

        .confirmation-btn:hover {
            opacity: 0.8;
        }
        
        /* 헤더 박스 스타일 제거 */
        .header {
            background: white !important;
            padding: 15px 20px !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            margin-bottom: 20px !important;
            position: relative !important;
            text-align: center !important;
        }
        
        /* 각 탭의 h2 제목 숨기기 */
        .tab-content h2 {
            display: none !important;
        }
        
        /* 모바일 탭 드롭다운 스타일 - 기본적으로 숨김 */
        .mobile-tabs-dropdown {
            display: none !important;
            margin-bottom: 20px;
        }
        
        .mobile-tabs-dropdown select {
            width: 100%;
            padding: 12px 16px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        /* PC에서 탭들을 한 줄에 4개씩 배치 */
        @media (min-width: 769px) {
            html body .tabs {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 12px !important;
                margin-bottom: 20px !important;
            }
        }
        
        /* 모바일에서만 드롭다운 표시 - 더 구체적인 선택자 사용 */
        @media (max-width: 768px) {
            html body .tabs {
                display: none !important;
            }
            
            .mobile-tabs-dropdown {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button class="nav-btn" onclick="showPasswordChangeModal()">비번변경</button>
            <button class="logout-btn" onclick="location.href='?logout=1'">로그아웃</button>
            <h1>통합관리</h1>
        </div>
        <div id="alertContainer"></div>
        <div class="tabs">
			<button class="tab-btn active" onclick="showTab('orders')">
                주문확인
            </button>
            <button class="tab-btn" onclick="showTab('pending')">
                승인대기 (<?= ($pendingRequests['count'] ?? 0) + $pendingItemRequests ?>)
            </button>
            <button class="tab-btn" onclick="showTab('rejected')">
                승인거부 (<?= $rejectedRequests['count'] ?? 0 ?>)
            </button>            
            <button class="tab-btn" onclick="showTab('approved')">
                승인완료 (<?= $approvedRequests['count'] ?? 0 ?>)
            </button>
            <button class="tab-btn" onclick="showTab('companies')">
                거래처
            </button>             
            <button class="tab-btn" onclick="showTab('categories')">
                카테고리
            </button>
            <button class="tab-btn" onclick="showTab('items')">
                전체품목
            </button>
            <button class="tab-btn" onclick="showTab('assignments')">
                품목업체할당
            </button>
            <button class="tab-btn" onclick="showTab('settings')">
                시트설정
            </button>            
            <button class="tab-btn" onclick="showTab('notices')">
                공지관리
            </button>
        </div>
        
        <!-- 모바일용 드롭다운 메뉴 -->
        <div class="mobile-tabs-dropdown">
            <select id="mobileTabSelect" onchange="showTabFromDropdown(this.value)">
                <option value="orders" selected>주문확인</option>
                <option value="pending">승인대기 (<?= ($pendingRequests['count'] ?? 0) + $pendingItemRequests ?>)</option>
                <option value="rejected">승인거부 (<?= $rejectedRequests['count'] ?? 0 ?>)</option>
                <option value="approved">승인완료 (<?= $approvedRequests['count'] ?? 0 ?>)</option>
                <option value="companies">거래처</option>
                <option value="categories">카테고리</option>
                <option value="items">전체품목</option>
                <option value="assignments">품목업체할당</option>
                <option value="settings">시트설정</option>
                <option value="notices">공지관리</option>
            </select>
        </div>
        
		<!-- 주문확인 탭 -->
        <div id="orders-tab" class="tab-content">
            <h2>주문확인</h2>
            
            <!-- 날짜 선택 -->
            <div class="order-date-selector">
                <div class="form-row">
                    <div class="input-group">
                        <label for="orderDate">조회날짜 <small style="white-space: nowrap;">(해당일 08:00 ~ 익일 05:00)</small></label>
                        <input type="date" id="orderDate" value="<?= date('Y-m-d') ?>" onchange="loadOrdersByDate()">
                    </div>
                    <div class="input-group input-group-button">
                        <label>&nbsp;</label>
                        <button class="btn btn-approve" onclick="loadOrdersByDate()">조회</button>
                    </div>
                </div>
            </div>
            
            <!-- 주문 요약 정보 -->
            <div class="companies-summary" id="orderSummarySection" style="display: none;">
                <div class="summary-main-row">
                    <div class="summary-item">
                        <span class="summary-label">업체:</span>
                        <span class="summary-value" id="totalCompanies">0개</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">총품목:</span>
                        <span class="summary-value" id="totalOrderCount">0개</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">총수량:</span>
                        <span class="summary-value" id="totalOrderQuantity">0개</span>
                    </div>
                </div>
                <div class="summary-unconfirmed-row">
                    <div class="summary-item">
                        <span class="summary-label">확인안함:</span>
                        <span class="summary-value" id="unconfirmedCount">0개</span>
                    </div>
                </div>
            </div>
            
            <!-- 주문 목록 -->
            <div class="orders-by-company">
                <div id="ordersContainer">
                    <div class="loading-message">오늘 주문을 불러오는 중...</div>
                </div>
            </div>
        </div>
        
        <!-- 승인 대기 탭 -->
        <div id="pending-tab" class="tab-content" style="display: none;">
            <h2>신청목록</h2>
            
            <!-- 신규업체 등록 승인 섹션 -->
            <div class="approval-section">
                <h3>신규업체  (<?= $pendingRequests['count'] ?? 0 ?>건)</h3>
                <?php if ($pendingRequests['success'] && !empty($pendingRequests['data'])): ?>
                <?php foreach ($pendingRequests['data'] as $request): ?>
                    <div class="request-card">
                        <h3>
                            <?= htmlspecialchars($request['company_name']) ?>
                            <span class="status-badge badge-pending">대기중</span>
                        </h3>
                        <div class="request-info">
                            <div class="info-item">
                                <span class="info-label">담당자:</span>
                                <span class="info-value"><?= htmlspecialchars($request['contact_person']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">전화번호:</span>
                                <span class="info-value"><?= htmlspecialchars($request['phone_number']) ?></span>
                            </div>
                            <div class="info-item address-item">
                                <span class="info-label">주소:</span>
                                <span class="info-value">
                                    <?php 
                                    $addressParts = [];
                                    if (!empty($request['zip_code'])) {
                                        $addressParts[] = '[' . $request['zip_code'] . ']';
                                    }
                                    if (!empty($request['company_address'])) {
                                        $addressParts[] = $request['company_address'];
                                    }
                                    echo htmlspecialchars(implode(' ', $addressParts) ?: '미입력');
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">이메일:</span>
                                <span class="info-value"><?= htmlspecialchars($request['email'] ?: '미입력') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">신청일:</span>
                                <span class="info-value"><?= date('Y-m-d', strtotime($request['requested_at'])) ?></span>
                            </div>
                        </div>
                        
                        <!-- 사업자등록증 파일 정보 -->
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span class="info-label">사업자등록증:</span>
                        </div>
                        <div class="file-section">
                            <?php if (!empty($request['business_license_file'])): ?>
                                <div class="file-info">
                                    <div class="file-name"><?= htmlspecialchars($request['business_license_file']) ?></div>
                                    <a href="download.php?id=<?= $request['id'] ?>" class="download-btn" target="_blank">다운로드</a>
                                </div>
                            <?php else: ?>
                                <div class="no-file">파일이 첨부되지 않았습니다</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 승인 설정 섹션 -->
                        <div class="approval-settings">
                            <div class="settings-row">
                                <div class="setting-group">
                                    <label for="itemGroup_<?= $request['id'] ?>">소속그룹</label>
                                    <select id="itemGroup_<?= $request['id'] ?>">
                                        <?php
                                        // 데이터베이스에서 소속그룹 가져오기
                                        try {
                                            $pdo = getDBConnection();
                                            $stmt = $pdo->query("SELECT group_name FROM item_groups ORDER BY group_name ASC");
                                            $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                            
                                            // 기본값이 없으면 기본 그룹들 사용
                                            if (empty($groups)) {
                                                $groups = ['도야짬뽕', '기타업체'];
                                            }
                                            
                                            foreach ($groups as $group) {
                                                $selected = ($group === '도야짬뽕') ? 'selected' : '';
                                                echo "<option value=\"{$group}\" {$selected}>{$group}</option>";
                                            }
                                        } catch (Exception $e) {
                                            // 오류 시 기본값 사용
                                            echo '<option value="도야짬뽕" selected>도야짬뽕</option>';
                                            echo '<option value="기타업체">기타업체</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <button class="btn btn-approve" onclick="approveWithSettings(<?= $request['id'] ?>)">
                                승인
                            </button>
                            <button class="btn btn-reject" onclick="confirmReject(<?= $request['id'] ?>, '<?= htmlspecialchars($request['company_name']) ?>')">
                                거부
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">승인 대기중인 신규업체 등록이 없습니다.</div>
                <?php endif; ?>
            </div>
            
            <!-- 품목 요청 승인 섹션 -->
            <div class="approval-section">
                <h3>주문품목  (<?= $pendingItemRequests ?>건)</h3>
                <div id="itemRequestsList">
                    <div class="loading-message">품목 요청 목록을 불러오는 중...</div>
                </div>
            </div>
        </div>
        
        <!-- 승인 완료 탭 -->
        <div id="approved-tab" class="tab-content" style="display: none;">
            <h2>승인된 업체목록</h2>
            <?php if ($approvedRequests['success'] && !empty($approvedRequests['data'])): ?>
                <?php foreach ($approvedRequests['data'] as $request): ?>
                    <div class="request-card">
                        <h3>
                            <?= htmlspecialchars($request['company_name']) ?>
                            <span class="status-badge badge-approved">승인완료</span>
                        </h3>
                        <div class="request-info">
                            <div class="info-item">
                                <span class="info-label">담당자:</span>
                                <span class="info-value"><?= htmlspecialchars($request['contact_person']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">전화번호:</span>
                                <span class="info-value"><?= htmlspecialchars($request['phone_number']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">소속그룹:</span>
                                <span class="info-value"><?= htmlspecialchars($request['item_group'] ?? '미설정') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">신청일:</span>
                                <span class="info-value"><?= date('Y-m-d', strtotime($request['requested_at'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">승인일:</span>
                                <span class="info-value"><?= $request['processed_at'] ? date('Y-m-d', strtotime($request['processed_at'])) : '-' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">승인자:</span>
                                <span class="info-value"><?= htmlspecialchars($request['processed_by'] ?: '-') ?></span>
                            </div>
                        </div>
                        
                        <!-- 승인된 업체의 사업자등록증도 표시 -->
                        <?php if (!empty($request['business_license_file'])): ?>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <span class="info-label">사업자등록증:</span>
                            </div>
                            <div class="file-section">
                                <div class="file-info">
                                    <div class="file-name"><?= htmlspecialchars($request['business_license_file']) ?></div>
                                    <a href="download.php?id=<?= $request['id'] ?>" class="download-btn" target="_blank">다운로드</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">승인 완료된 업체가 없습니다.</div>
            <?php endif; ?>
        </div>
        
        <!-- 거부된 신청 탭 -->
        <div id="rejected-tab" class="tab-content" style="display: none;">
            <h2>거부된 등록신청</h2>
            <?php if ($rejectedRequests['success'] && !empty($rejectedRequests['data'])): ?>
                <?php foreach ($rejectedRequests['data'] as $request): ?>
                    <div class="request-card">
                        <h3>
                            <?= htmlspecialchars($request['company_name']) ?>
                            <span class="status-badge badge-rejected">거부됨</span>
                        </h3>
                        <div class="request-info">
                            <div class="info-item">
                                <span class="info-label">담당자:</span>
                                <span class="info-value"><?= htmlspecialchars($request['contact_person']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">전화번호:</span>
                                <span class="info-value"><?= htmlspecialchars($request['phone_number']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">신청일:</span>
                                <span class="info-value"><?= date('Y-m-d', strtotime($request['requested_at'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">거부일:</span>
                                <span class="info-value"><?= $request['processed_at'] ? date('Y-m-d', strtotime($request['processed_at'])) : '-' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">거부자:</span>
                                <span class="info-value"><?= htmlspecialchars($request['processed_by'] ?: '-') ?></span>
                            </div>
                            <?php if (!empty($request['notes'])): ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <span class="info-label">거부사유:</span>
                                    <span class="info-value"><?= htmlspecialchars($request['notes']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">거부된 신청이 없습니다.</div>
            <?php endif; ?>
        </div>
        
        <!-- 업체목록 탭 -->
        <div id="companies-tab" class="tab-content" style="display: none;">
            <div class="companies-header">
                <h2>업체목록</h2>
                <button class="btn btn-primary" onclick="showAddCompanyModal()">직접추가</button>
            </div>
            
            <!-- 업체 목록 요약 -->
            <div class="companies-summary" id="companiesSummary" style="display: none;">
                <div class="summary-item">
                    <span class="summary-label">전체:</span>
                    <span class="summary-value" id="totalCompaniesCount">0개</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">차단:</span>
                    <span class="summary-value" id="blockedCompaniesCount">0개</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">필터링:</span>
                    <span class="summary-value" id="filteredCompaniesCount">0개</span>
                </div>
            </div>
            
            <!-- 검색 및 필터링 -->
            <div class="company-search-filter">
                <div class="form-row">
                    <div class="input-group">
                        <label for="companySearch">업체명 검색</label>
                        <input type="text" id="companySearch" placeholder="업체명을 입력하세요" onkeyup="filterCompanies()">
                    </div>
                    <div class="filter-group">
                        <div class="input-group">
                            <label for="statusFilter">상태</label>
                            <select id="statusFilter" onchange="filterCompanies()">
                                <option value="">전체</option>
                                <option value="active">정상</option>
                                <option value="blocked">차단됨</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="groupFilter">소속그룹</label>
                            <select id="groupFilter" onchange="filterCompanies()">
                                <option value="">전체</option>
                                <?php
                                // 데이터베이스에서 소속그룹 가져오기
                                try {
                                    $pdo = getDBConnection();
                                    $stmt = $pdo->query("SELECT group_name FROM item_groups ORDER BY group_name ASC");
                                    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    
                                    // 기본값이 없으면 기본 그룹들 사용
                                    if (empty($groups)) {
                                        $groups = ['도야짬뽕', '기타업체'];
                                    }
                                    
                                    foreach ($groups as $group) {
                                        echo "<option value=\"{$group}\">{$group}</option>";
                                    }
                                } catch (Exception $e) {
                                    // 오류 시 기본값 사용
                                    echo '<option value="도야짬뽕">도야짬뽕</option>';
                                    echo '<option value="기타업체">기타업체</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="input-group input-group-button">
                        <label>&nbsp;</label>
                        <button class="btn btn-secondary" onclick="clearFilters()">필터 초기화</button>
                    </div>
                </div>
            </div>
            
            <!-- 업체 목록 -->
            <div class="companies-list">
                <div id="companiesListContainer">
                    <div class="no-data">업체 정보를 불러오는 중...</div>
                </div>
            </div>
        </div>
        
        <!-- 업체추가 모달 -->
        <div id="addCompanyModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>업체 추가</h3>
                    <span class="close" onclick="closeAddCompanyModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="addCompanyForm">
                        <div class="form-group">
                            <label for="addCompanyName">업체명</label>
                            <input type="text" id="addCompanyName" name="companyName" placeholder="상호명">
                        </div>

                        <div class="form-group">
                            <label for="addPassword">비밀번호</label>
                            <input type="password" id="addPassword" name="password" placeholder="4자리 이상">
                        </div>

                        <div class="form-group">
                            <label for="addContactPerson">주문담당자</label>
                            <input type="text" id="addContactPerson" name="contactPerson" placeholder="직급">
                        </div>

                        <div class="form-group">
                            <label for="addPhoneNumber">전화번호</label>
                            <input type="text" id="addPhoneNumber" name="phoneNumber" placeholder="010-1234-5678">
                        </div>

                        <div class="form-group">
                            <label for="addZipCode">우편번호</label>
                            <input type="text" id="addZipCode" name="zipCode" placeholder="12345">
                        </div>

                        <div class="form-group">
                            <label for="addAddress1">주소</label>
                            <input type="text" id="addAddress1" name="address1" placeholder="기본주소">
                        </div>

                        <div class="form-group">
                            <label for="addAddress2">상세주소</label>
                            <input type="text" id="addAddress2" name="address2" placeholder="상세주소">
                        </div>

                        <div class="form-group">
                            <label for="addEmail">이메일</label>
                            <input type="email" id="addEmail" name="email" placeholder="example@company.com">
                        </div>

                        <div class="form-group">
                            <label for="addItemGroup">소속그룹</label>
                            <select id="addItemGroup" name="itemGroup">
                                <option value="">선택하세요</option>
                                <?php
                                // 데이터베이스에서 소속그룹 가져오기
                                try {
                                    $pdo = getDBConnection();
                                    $stmt = $pdo->query("SELECT group_name FROM item_groups ORDER BY group_name ASC");
                                    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    
                                    // 기본값이 없으면 기본 그룹들 사용
                                    if (empty($groups)) {
                                        $groups = ['도야짬뽕', '기타업체'];
                                    }
                                    
                                    foreach ($groups as $group) {
                                        echo "<option value=\"{$group}\">{$group}</option>";
                                    }
                                } catch (Exception $e) {
                                    // 오류 시 기본값 사용
                                    echo '<option value="도야짬뽕">도야짬뽕</option>';
                                    echo '<option value="기타업체">기타업체</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- 히든 필드로 결합된 주소 전송 -->
                        <input type="hidden" id="addAddress" name="address">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddCompanyModal()">취소</button>
                    <button type="button" class="btn btn-primary" onclick="submitAddCompany()">추가</button>
                </div>
                <div id="addCompanyMessage" class="message"></div>
            </div>
        </div>
        
        <!-- 카테고리 관리 탭 -->
        <div id="categories-tab" class="tab-content" style="display: none;">
            <h2>카테고리 관리</h2>
            
            <!-- 새 카테고리 추가 -->
            <div class="add-form">
                <h3>새 카테고리 추가</h3>
                <div class="form-row">
                    <div class="input-group">
                        <label for="newCategoryName">카테고리명</label>
                        <input type="text" id="newCategoryName" placeholder="카테고리명을 입력하세요" maxlength="50">
                    </div>
                    <div class="input-group">
                        <label for="newCategoryDesc">설명</label>
                        <input type="text" id="newCategoryDesc" placeholder="카테고리 설명 (선택사항)" maxlength="200">
                    </div>
                    <div class="input-group small">
                        <label for="categoryOrder">순서</label>
                        <input type="number" id="categoryOrder" placeholder="1" min="1" max="999" value="1">
                    </div>
                    <div class="input-group input-group-button">
                        <label>&nbsp;</label>
                        <button class="btn btn-approve" onclick="addCategory()">추가</button>
                    </div>
                </div>
            </div>
            
            <!-- 기존 카테고리 목록 -->
            <div class="category-list">
                <h3>기존 카테고리</h3>
                <div id="categoryItems">
                    <div class="no-data">등록된 카테고리가 없습니다.</div>
                </div>
            </div>
        </div>
        
        <!-- 품목리스트 관리 탭 -->
        <div id="items-tab" class="tab-content" style="display: none;">
            <h2>품목관리</h2>
            
            <!-- 새 품목 추가 -->
            <div class="add-form">
                <h3>새 품목 추가</h3>
                <div class="form-row">
                    <div class="input-group">
                        <label for="itemCategory">카테고리</label>
                        <select id="itemCategory">
                            <option value="">카테고리 선택</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="newItemName">품목명</label>
                        <input type="text" id="newItemName" placeholder="품목명을 입력하세요" maxlength="100">
                    </div>
                    <div class="input-group">
                        <label for="newItemDesc">설명</label>
                        <input type="text" id="newItemDesc" placeholder="품목 설명 (선택사항)" maxlength="200">
                    </div>
                    <div class="input-group small">
                        <label for="itemOrder">순서</label>
                        <input type="number" id="itemOrder" placeholder="자동" min="0" max="999" value="0" title="0 입력 시 자동 할당">
                    </div>
                    <div class="input-group input-group-button">
                        <label>&nbsp;</label>
                        <button class="btn btn-approve" onclick="addItem()">추가</button>
                    </div>
                </div>
            </div>
			
			<!-- 순서 변경 안내 문구 -->
			<div class="item-hint">드래그앤드롭으로 품목순서 변경 후 재정렬 클릭</div>
            
            <!-- 기존 품목 목록 -->
            <div class="items-by-category">
                <div id="itemsContainer">
                    <div class="no-data">등록된 품목이 없습니다.</div>
                </div>
            </div>
        </div>
        
        <!-- 품목업체할당 관리 탭 -->
        <div id="assignments-tab" class="tab-content" style="display: none;">
            <h2>주문품목 관리</h2>
            
            <!-- 업체 선택 -->
            <div class="assignment-form">
                <h3>업체별 품목 지정</h3>
                <div class="form-row">
                    <div class="input-group">
                        <label for="assignmentCompanySearch">업체명 검색</label>
                        <input type="text" id="assignmentCompanySearch" placeholder="업체명을 입력하세요" onkeyup="searchAndSelectCompany()" onfocus="ensureCompaniesLoaded()">
                        <div id="companySearchResults" class="search-results" style="display: none;"></div>
                    </div>
                    <div class="input-group">
                        <label for="selectedCompanyInfo">선택된 업체</label>
                        <div id="selectedCompanyInfo" class="selected-company-info">
                            <span class="no-selection">업체를 검색하여 선택하세요</span>
                        </div>
                    </div>
                    <div class="input-group input-group-button">
                        <label>&nbsp;</label>
                        <button class="btn btn-approve" onclick="showAssignmentModal()" id="assignmentBtn" disabled>전체 (새로)할당</button>
                    </div>
                </div>
            </div>
            
            <!-- 개별 품목 추가 버튼 섹션 -->
            <div class="individual-assignment-section" id="individualAssignmentSection" style="display: none;">
                <div class="form-row">
                    <div class="input-group">
                        <label>개별 품목 관리</label>
                        <div class="button-group">
                            <button class="btn btn-secondary" onclick="showIndividualAssignmentModal()">기존할당에 품목추가</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 선택된 업체의 할당된 품목 목록 -->
            <div class="company-assignments">
                <div id="assignmentsContainer">
                    <div class="no-data">업체를 선택하세요.</div>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             공지관리 탭 (신규 추가)
             ======================================== -->
        <div id="notices-tab" class="tab-content" style="display: none;">
            <h2>공지 및 메세지</h2>
            
            <!-- 공지 작성 폼 -->
            <div class="add-form">
                <h3>알림등록</h3>
                
                <div class="form-row" style="display: flex !important; gap: 15px !important; align-items: end !important; flex-wrap: nowrap !important;">
                    <div class="input-group" style="flex: 1 !important; min-width: 150px !important;">
                        <label>알림유형</label>
                        <select id="noticeType" onchange="toggleCompanySelection()">
                            <option value="global">전체공지</option>
                            <option value="individual">개별메시지</option>
                        </select>
                    </div>
                    
                    <div class="input-group small" style="flex: 0 0 100px !important; min-width: 80px !important; max-width: 100px !important;">
                        <label>우선순위</label>
                        <input type="number" id="noticePriority" value="0" min="0" max="9" placeholder="0-9">
                    </div>
                </div>
                
                <div class="form-row" id="noticeTitleRow">
                    <div class="input-group">
                        <label>제목</label>
                        <input type="text" id="noticeTitle" placeholder="">
                    </div>
                </div>
                
                <div class="form-row" id="noticeMessageRow">
                    <div class="input-group">
                        <label>내용 <span style="color: #dc3545;">*</span></label>
                        <textarea id="noticeMessage" rows="4" placeholder=""></textarea>
                    </div>
                </div>
                
                <div class="form-row" id="noticeExpiresRow">
                    <div class="input-group">
                        <label>만료일시 (선택사항)</label>
                        <input type="datetime-local" id="noticeExpires" placeholder="만료일시 (비워두면 무기한)">
                    </div>
                </div>
                
                <!-- 대상 업체 선택 (개별메시지인 경우에만 표시) -->
                <div id="companySelectionSection" style="display: none;">
                    <div class="form-row">
                        <div class="input-group">
                            <label>제목</label>
                            <input type="text" id="individualNoticeTitle" placeholder="">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>내용 <span style="color: #dc3545;">*</span></label>
                            <textarea id="individualNoticeMessage" rows="4" placeholder=""></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>만료일시 (선택사항)</label>
                            <input type="datetime-local" id="individualNoticeExpires" placeholder="만료일시 (비워두면 무기한)">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>대상업체 선택 <span style="color: #dc3545;">*</span></label>
                            <div style="margin-top: 8px;">
                                <button type="button" class="btn btn-small btn-secondary" onclick="selectAllCompanies()">전체선택</button>
                                <button type="button" class="btn btn-small btn-secondary" onclick="deselectAllCompanies()">전체해제</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="companyCheckboxes">
                        <!-- JavaScript로 동적 생성 -->
                    </div>
                </div>
                
                <div class="notice-management-buttons">
                    <button class="btn btn-approve" onclick="createNewNotice()">공지게시</button>
                </div>
            </div>
            
            
            <!-- 공지 목록 -->
            <div class="category-list">
                <h3>등록된 알림</h3>
                <div id="noticesList">
                    <div class="loading-message">공지목록을 불러오는 중...</div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- 전체 품목 할당 모달 -->
    <div id="assignmentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>전체 품목 할당</h3>
                <span class="close" onclick="closeAssignmentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-warning">
                    <strong>주의:</strong> 기존에 할당된 모든 품목이 삭제되고 새로 선택한 품목들만 할당됩니다.
                </div>
                <div id="availableItems">
                    <!-- 할당 가능한 품목들이 여기에 표시됩니다 -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="saveAssignments()">전체할당 저장</button>
                <button class="btn btn-reject" onclick="closeAssignmentModal()">취소</button>
            </div>
        </div>
    </div>
    
    <!-- 개별 품목 추가 모달 -->
    <div id="individualAssignmentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>개별 품목 추가</h3>
                <span class="close" onclick="closeIndividualAssignmentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-info">
                    기존 할당을 유지하면서 선택한 품목들을 추가로 할당합니다.
                </div>
                <div id="unassignedItems">
                    <!-- 미할당 품목들이 여기에 표시됩니다 -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="saveIndividualAssignments()">개별 품목 추가</button>
                <button class="btn btn-reject" onclick="closeIndividualAssignmentModal()">취소</button>
            </div>
        </div>
    </div>
    
    <!-- 카테고리 수정 모달 -->
    <div id="categoryEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>카테고리 수정</h3>
                <span class="close" onclick="closeCategoryEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="categoryEditForm">
                    <input type="hidden" id="editCategoryId">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editCategoryName">카테고리명</label>
                            <input type="text" id="editCategoryName" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editCategoryDesc">설명</label>
                            <input type="text" id="editCategoryDesc" maxlength="200">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="input-group small">
                            <label for="editCategoryOrder">순서</label>
			    <input type="number" id="editCategoryOrder" min="1" max="999" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="saveCategoryEdit()">수정 저장</button>
                <button class="btn btn-reject" onclick="closeCategoryEditModal()">취소</button>
            </div>
        </div>
    </div>
    
    <!-- 품목 수정 모달 -->
    <div id="itemEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>품목수정</h3>
                <span class="close" onclick="closeItemEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="itemEditForm">
                    <input type="hidden" id="editItemId">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editItemName">품목명</label>
                            <input type="text" id="editItemName" maxlength="100" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editItemDesc">설명</label>
                            <input type="text" id="editItemDesc" maxlength="200">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="input-group small">
                            <label for="editItemOrder">순서</label>
                            <input type="number" id="editItemOrder" min="1" max="999" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="saveItemEdit()">수정 저장</button>
                <button class="btn btn-reject" onclick="closeItemEditModal()">취소</button>
            </div>
        </div>
    </div>

    <!-- 주문차단 사유 수정 모달 -->
    <div id="blockReasonModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>주문차단 사유 수정</h3>
                <span class="close" onclick="closeBlockReasonModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="blockReasonForm">
                    <input type="hidden" id="blockReasonCompanyId">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="blockReasonText">차단 사유</label>
                            <textarea id="blockReasonText" rows="4" maxlength="500" placeholder="주문차단 사유를 입력하세요" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="saveBlockReason()">사유 저장</button>
                <button class="btn btn-reject" onclick="closeBlockReasonModal()">취소</button>
            </div>
        </div>
    </div>
    
    <!-- 비밀번호 변경 모달 (신규 추가) -->
    <div id="passwordChangeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔒 관리자 비밀번호 변경</h3>
                <span class="close" onclick="closePasswordChangeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modalCurrentPassword">현재 비밀번호 <span style="color: #dc3545;">*</span></label>
                    <input type="password" id="modalCurrentPassword" placeholder="현재 비밀번호를 입력하세요" required>
                </div>
                
                <div class="form-group">
                    <label for="modalNewPassword">새 비밀번호 <span style="color: #dc3545;">*</span></label>
                    <input type="password" id="modalNewPassword" placeholder="새 비밀번호를 입력하세요" required oninput="checkModalPasswordStrength()">
                    <div class="password-requirements">
                        <small>
                            <strong>요구사항:</strong> 최소 6자 이상, 숫자 1개 이상, 특수문자 1개 이상 (!@#$%^&* 등)
                        </small>
                    </div>
                    <div id="modalPasswordStrengthIndicator" style="margin-top: 8px; display: none;">
                        <div class="strength-bar">
                            <div id="modalStrengthBar" class="strength-fill"></div>
                        </div>
                        <div id="modalStrengthText" class="strength-text"></div>
                        <div id="modalStrengthErrors" class="strength-errors"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalConfirmPassword">새 비밀번호 확인 <span style="color: #dc3545;">*</span></label>
                    <input type="password" id="modalConfirmPassword" placeholder="새 비밀번호를 다시 입력하세요" required oninput="checkModalPasswordMatch()">
                    <div id="modalPasswordMatchIndicator" style="margin-top: 5px; display: none;"></div>
                </div>
                
                <!-- 보안 안내 -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px; border-left: 4px solid #007bff;">
                    <small>
                        <strong>⚠️ 주의사항:</strong><br>
                        • 비밀번호 변경 후 자동으로 로그아웃됩니다<br>
                        • 변경 전 파일이 자동으로 백업됩니다<br>
                        • 새 비밀번호로 다시 로그인하세요
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-approve" onclick="changeModalPassword()" id="modalChangePasswordBtn" disabled>
                    비밀번호 변경
                </button>
                <button class="btn btn-secondary" onclick="clearModalPasswordForm()">
                    초기화
                </button>
                <button class="btn btn-reject" onclick="closePasswordChangeModal()">
                    취소
                </button>
            </div>
        </div>
    </div>
    
    <!-- 공지 수정 모달 (신규 추가) -->
    <div id="noticeEditModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>공지수정</h3>
                <span class="close" onclick="closeNoticeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editNoticeId">
                <input type="hidden" id="editNoticeType">
                
                <div class="form-group">
                    <label>우선순위 (0-9)</label>
                    <input type="number" id="editNoticePriority" min="0" max="9" value="0">
                </div>
                
                <div class="form-group" id="editNoticeTitleGroup">
                    <label>제목</label>
                    <input type="text" id="editNoticeTitle" placeholder="공지 제목 입력">
                </div>
                
                <div class="form-group" id="editNoticeMessageGroup">
                    <label>내용 <span style="color: #dc3545;">*</span></label>
                    <textarea id="editNoticeMessage" rows="4" placeholder="공지 내용 입력"></textarea>
                </div>
                
                <div class="form-group" id="editNoticeExpiresGroup">
                    <label>만료일시 (선택사항)</label>
                    <input type="datetime-local" id="editNoticeExpires" placeholder="만료일시 (비워두면 무기한)">
                </div>
                
                <!-- 개별메시지 수정 시 대상 업체 -->
                <div id="editCompanySelectionSection" style="display: none;">
                    <div class="form-group">
                        <label>제목</label>
                        <input type="text" id="editIndividualNoticeTitle" placeholder="개별메시지 제목 입력">
                    </div>
                    
                    <div class="form-group">
                        <label>내용 <span style="color: #dc3545;">*</span></label>
                        <textarea id="editIndividualNoticeMessage" rows="4" placeholder="개별메시지 내용 입력"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>만료일시 (선택사항)</label>
                        <input type="datetime-local" id="editIndividualNoticeExpires" placeholder="만료일시 (비워두면 무기한)">
                    </div>
                    
                    <div class="form-group">
                        <label>대상업체 선택</label>
                        <div style="margin-top: 8px;">
                            <button type="button" class="btn btn-small btn-secondary" onclick="selectAllEditCompanies()">전체선택</button>
                            <button type="button" class="btn btn-small btn-secondary" onclick="deselectAllEditCompanies()">전체해제</button>
                        </div>
                    </div>
                    
                    <div id="editCompanyCheckboxes">
                        <!-- JavaScript로 동적 생성 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="saveNoticeEdit()">저장</button>
                <button class="btn btn-secondary" onclick="closeNoticeEditModal()">취소</button>
            </div>
        </div>
    </div>
    
    <!-- 설정관리 탭 -->
    <div id="settings-tab" class="tab-content" style="display: none;">
        <h2>구글시트</h2>
        
        <!-- 구글시트 설정 -->
        <div class="simple-settings-section">
            <h3>시트명</h3>
            
            <!-- 시트명 추가 양식 -->
            <div class="add-form">
                <div class="form-row-single">
                    <input type="text" id="sheetName" placeholder="시트명을 입력하세요" maxlength="100">
                    <input type="text" id="sheetDescription" placeholder="설명 (선택사항)" maxlength="255">
                    <button class="btn btn-primary" onclick="addSheetConfig()">추가</button>
                </div>
            </div>
            
            <!-- 기존 시트 목록 -->
            <div class="settings-list">
                <h4>기존 시트목록</h4>
                <div id="sheetConfigsList">
                    <div class="no-data">등록된 시트가 없습니다.</div>
                </div>
            </div>
        </div>
        
        <!-- 품목 그룹 설정 -->
        <div class="simple-settings-section">
            <h3>품목그룹</h3>
            
            <!-- 그룹명 추가 양식 -->
            <div class="add-form">
                <div class="form-row-single">
                    <input type="text" id="groupName" placeholder="그룹명을 입력하세요" maxlength="50">
                    <input type="text" id="groupDescription" placeholder="설명 (선택사항)" maxlength="255">
                    <button class="btn btn-primary" onclick="addItemGroup()">추가</button>
                </div>
            </div>
            
            <!-- 기존 그룹 목록 -->
            <div class="settings-list">
                <h4>기존 그룹목록</h4>
                <div id="itemGroupsList">
                    <div class="no-data">등록된 그룹이 없습니다.</div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/admin-common_v3.js"></script>
    <script src="assets/js/admin-approval_v2.js"></script>
    <script src="assets/js/admin-inventory_v19.js"></script>
	<script src="assets/js/admin-orders_vs6.js"></script>
    <script src="assets/js/admin-companies_v12.js"></script>
    <script src="assets/js/admin-notices_v14.js"></script>
    <script src="assets/js/admin-password_v2.js"></script>
    <script src="assets/js/admin-settings_v9.js"></script>
    
    <script>
    // 페이지 로드 시 주문확인 탭을 기본으로 활성화
    document.addEventListener('DOMContentLoaded', function() {
        showTab('orders');
    });
    
    // 품목 요청 목록 로드
    function loadItemRequests() {
        const container = document.getElementById('itemRequestsList');
        container.innerHTML = '<div class="loading-message">품목 요청 목록을 불러오는 중...</div>';
        
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=getAllCompanyItemRequests'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.requests && data.requests.length > 0) {
                displayItemRequests(data.requests);
            } else {
                container.innerHTML = '<div class="no-data">승인 대기중인 품목 요청이 없습니다.</div>';
            }
            updateStatistics(); // 통계 업데이트
        })
        .catch(error => {
            console.error('품목 요청 로드 오류:', error);
            container.innerHTML = '<div class="error-message">품목 요청 목록을 불러오는 중 오류가 발생했습니다.</div>';
        });
    }
    
    // 품목 요청 목록 표시
    function displayItemRequests(requests) {
        const container = document.getElementById('itemRequestsList');
        let html = '';
        
        requests.forEach(request => {
            const actionClass = request.request_action === 'add' ? 'action-add' : 'action-remove';
            const actionText = request.request_action === 'add' ? '추가 요청' : '제거 요청';
            
            html += `
                <div class="item-request-card">
                    <div class="request-header">
                        <span class="company-name">${escapeHtml(request.company_name)}</span>
                        <span class="action-badge ${actionClass}">${actionText}</span>
                    </div>
                    <div class="item-name">${escapeHtml(request.item_name)}</div>
                    <div class="request-footer">
                        <span class="request-date">${formatDateTime(request.requested_at)}</span>
                        <button class="btn btn-approve" onclick="approveItemRequest(${request.request_id})">확인</button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // 품목 요청 승인
    function approveItemRequest(requestId) {
        if (!confirm('이 품목 요청을 처리하시겠습니까?')) {
            return;
        }
        
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=processItemRequest&requestId=${requestId}&requestAction=approve&approvedBy=admin`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('품목 요청이 처리되었습니다.', 'success');
                loadItemRequests(); // 목록 새로고침
                updateStatistics(); // 통계 업데이트
            } else {
                showAlert('처리 중 오류가 발생했습니다: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('처리 중 오류가 발생했습니다.', 'error');
        });
    }
    
    
    // HTML 이스케이프 함수
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 날짜/시간 포맷팅 함수
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ko-KR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }
    
    // 기존 showTab 함수 수정 (품목 요청 로드 추가)
    const originalShowTab = showTab;
    showTab = function(tabName) {
        originalShowTab(tabName);
        
        // 승인대기 탭인 경우 품목 요청 목록 로드
        if (tabName === 'pending') {
            loadItemRequests();
        }
        
        // 모바일 드롭다운 선택값 업데이트
        const mobileSelect = document.getElementById('mobileTabSelect');
        if (mobileSelect) {
            mobileSelect.value = tabName;
        }
    };
    
    // 드롭다운에서 탭 선택 시 호출되는 함수
    function showTabFromDropdown(tabName) {
        showTab(tabName);
    }
    </script>
    
    <!-- 업체 정보 수정 모달 -->
    <div id="editCompanyModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>업체 정보 수정</h3>
                <span class="close" onclick="closeEditCompanyModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editCompanyForm">
                    <input type="hidden" id="editCompanyId" name="companyId">
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editCompanyName">업체명 <span style="color: red;">*</span></label>
                            <input type="text" id="editCompanyName" name="companyName" required>
                        </div>
                        <div class="input-group">
                            <label for="editPassword">비밀번호 <span style="color: red;">*</span></label>
                            <input type="text" id="editPassword" name="password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editContactPerson">담당자 <span style="color: red;">*</span></label>
                            <input type="text" id="editContactPerson" name="contactPerson" required>
                        </div>
                        <div class="input-group">
                            <label for="editPhoneNumber">전화번호 <span style="color: red;">*</span></label>
                            <input type="text" id="editPhoneNumber" name="phoneNumber" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editEmail">이메일</label>
                            <input type="email" id="editEmail" name="email">
                        </div>
                        <div class="input-group">
                            <label for="editItemGroup">소속그룹</label>
                            <select id="editItemGroup" name="itemGroup">
                                <?php
                                // 데이터베이스에서 소속그룹 가져오기
                                try {
                                    $pdo = getDBConnection();
                                    $stmt = $pdo->query("SELECT group_name FROM item_groups ORDER BY group_name ASC");
                                    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    
                                    // 기본값이 없으면 기본 그룹들 사용
                                    if (empty($groups)) {
                                        $groups = ['도야짬뽕', '기타업체'];
                                    }
                                    
                                    foreach ($groups as $group) {
                                        echo "<option value=\"{$group}\">{$group}</option>";
                                    }
                                } catch (Exception $e) {
                                    // 오류 시 기본값 사용
                                    echo '<option value="도야짬뽕">도야짬뽕</option>';
                                    echo '<option value="기타업체">기타업체</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="editZipCode">우편번호</label>
                            <input type="text" id="editZipCode" name="zipCode" placeholder="12345">
                        </div>
                        <div class="input-group">
                            <label for="editCompanyAddress">주소</label>
                            <input type="text" id="editCompanyAddress" name="companyAddress">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditCompanyModal()">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveCompanyGroup()">저장</button>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// 설정 관리 함수들
function getSheetConfigs() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT id, sheet_name, description, created_at, updated_at
            FROM sheet_configs 
            ORDER BY sheet_name ASC
        ");
        $configs = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $configs];
    } catch (Exception $e) {
        error_log("Get sheet configs error: " . $e->getMessage());
        return ['success' => false, 'message' => '시트 설정 조회 실패: ' . $e->getMessage()];
    }
}

function getItemGroups() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT id, group_name, description, created_at, updated_at
            FROM item_groups 
            ORDER BY group_name ASC
        ");
        $groups = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $groups];
    } catch (Exception $e) {
        error_log("Get item groups error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 그룹 조회 실패: ' . $e->getMessage()];
    }
}

function updateSheetConfig($id, $sheetName, $description) {
    try {
        $pdo = getDBConnection();
        
        if ($id > 0) {
            // 기존 설정 업데이트
            $stmt = $pdo->prepare("
                UPDATE sheet_configs 
                SET sheet_name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$sheetName, $description, $id]);
        } else {
            // 새 설정 추가
            $stmt = $pdo->prepare("
                INSERT INTO sheet_configs (sheet_name, description)
                VALUES (?, ?)
            ");
            $stmt->execute([$sheetName, $description]);
        }
        
        return ['success' => true, 'message' => '시트 설정이 저장되었습니다.'];
    } catch (Exception $e) {
        error_log("Update sheet config error: " . $e->getMessage());
        return ['success' => false, 'message' => '시트 설정 저장 실패: ' . $e->getMessage()];
    }
}

function updateItemGroup($id, $groupName, $description) {
    try {
        $pdo = getDBConnection();
        
        if ($id > 0) {
            // 기존 그룹 업데이트
            $stmt = $pdo->prepare("
                UPDATE item_groups 
                SET group_name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$groupName, $description, $id]);
        } else {
            // 새 그룹 추가
            $stmt = $pdo->prepare("
                INSERT INTO item_groups (group_name, description)
                VALUES (?, ?)
            ");
            $stmt->execute([$groupName, $description]);
        }
        
        return ['success' => true, 'message' => '품목 그룹이 저장되었습니다.'];
    } catch (Exception $e) {
        error_log("Update item group error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 그룹 저장 실패: ' . $e->getMessage()];
    }
}

function deleteSheetConfig($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM sheet_configs WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true, 'message' => '시트 설정이 삭제되었습니다.'];
    } catch (Exception $e) {
        error_log("Delete sheet config error: " . $e->getMessage());
        return ['success' => false, 'message' => '시트 설정 삭제 실패: ' . $e->getMessage()];
    }
}

function deleteItemGroup($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM item_groups WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true, 'message' => '품목 그룹이 삭제되었습니다.'];
    } catch (Exception $e) {
        error_log("Delete item group error: " . $e->getMessage());
        return ['success' => false, 'message' => '품목 그룹 삭제 실패: ' . $e->getMessage()];
    }
}

/**
 * 활성화된 소속그룹 목록 조회
 */
function getActiveItemGroups() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT group_name 
            FROM item_groups 
            ORDER BY group_name ASC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 기본값이 없으면 기본 그룹들 사용
        if (empty($groups)) {
            $groups = ['도야짬뽕', '기타업체'];
        }
        
        return ['success' => true, 'groups' => $groups];
    } catch (Exception $e) {
        error_log("Get active item groups error: " . $e->getMessage());
        return ['success' => false, 'groups' => ['도야짬뽕', '기타업체']];
    }
}

/**
 * 업체 그룹만 수정
 */
function updateCompanyGroup($companyId, $itemGroup) {
    try {
        // 소속그룹 유효성 검증
        if (empty($itemGroup)) {
            return ['success' => false, 'message' => '소속그룹을 선택해주세요.'];
        }
        
        $pdo = getDBConnection();
        
        // 소속그룹 존재 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_groups WHERE group_name = ?");
        $stmt->execute([$itemGroup]);
        if ($stmt->fetchColumn() == 0) {
            return ['success' => false, 'message' => '올바른 소속그룹을 선택해주세요.'];
        }
        
        // 업체 그룹 업데이트
        $stmt = $pdo->prepare("UPDATE companies SET item_group = ?, updated_at = NOW() WHERE id = ? AND active = 1");
        $stmt->execute([$itemGroup, $companyId]);
        
        if ($stmt->rowCount() > 0) {
            // 캐시 갱신
            clearCache('companies_data');
            
            // 로그 기록
            writeLog("업체 그룹 수정: ID {$companyId} → {$itemGroup}");
            
            return ['success' => true, 'message' => '업체 그룹이 성공적으로 수정되었습니다.'];
        } else {
            return ['success' => false, 'message' => '업체를 찾을 수 없습니다.'];
        }
        
    } catch (Exception $e) {
        error_log("업체 그룹 수정 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 그룹 수정 중 오류가 발생했습니다.'];
    }
}

/**
 * 관리자용 업체 추가 (모든 필드 선택사항)
 */
function addCompanyByAdmin($companyName, $password, $contactPerson, $phoneNumber, $email, $address, $zipCode, $itemGroup) {
    try {
        $pdo = getDBConnection();
        
        // 업체명이 입력된 경우에만 중복 체크
        if (!empty($companyName)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_name = ? AND active = 1");
            $stmt->execute([$companyName]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => '이미 존재하는 업체명입니다.'];
            }
        }
        
        // 비밀번호가 입력된 경우 길이 체크
        if (!empty($password) && strlen($password) < 4) {
            return ['success' => false, 'message' => '비밀번호는 4자리 이상이어야 합니다.'];
        }
        
        // 전화번호가 입력된 경우 형식 체크
        if (!empty($phoneNumber) && !preg_match('/^[0-9-+\s()]+$/', $phoneNumber)) {
            return ['success' => false, 'message' => '올바른 전화번호 형식을 입력해주세요.'];
        }
        
        // 우편번호가 입력된 경우 형식 체크
        if (!empty($zipCode) && !preg_match('/^\d{5}$/', $zipCode)) {
            return ['success' => false, 'message' => '우편번호는 5자리 숫자여야 합니다.'];
        }
        
        // 이메일이 입력된 경우 형식 체크
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '올바른 이메일 형식을 입력해주세요.'];
        }
        
        // 소속그룹이 입력된 경우 존재 확인
        if (!empty($itemGroup)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_groups WHERE group_name = ?");
            $stmt->execute([$itemGroup]);
            if ($stmt->fetchColumn() == 0) {
                return ['success' => false, 'message' => '올바른 소속그룹을 선택해주세요.'];
            }
        }
        
        // 업체 추가 (모든 필드가 선택사항이므로 기본값 설정)
        $stmt = $pdo->prepare("
            INSERT INTO companies (
                company_name, password, contact_person, phone_number, email, 
                address, zip_code, item_group, active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $companyName ?: '미입력',
            $password ?: '1234', // 기본 비밀번호
            $contactPerson ?: '',
            $phoneNumber ?: '',
            $email ?: '',
            $address ?: '',
            $zipCode ?: '',
            $itemGroup ?: ''
        ]);
        
        $companyId = $pdo->lastInsertId();
        
        // 로그 기록
        writeLog("관리자 업체 추가: ID {$companyId}, 업체명: " . ($companyName ?: '미입력'));
        
        return ['success' => true, 'message' => '업체가 성공적으로 추가되었습니다.'];
        
    } catch (Exception $e) {
        error_log("관리자 업체 추가 오류: " . $e->getMessage());
        return ['success' => false, 'message' => '업체 추가 중 오류가 발생했습니다.'];
    }
}

/**
 * 관리자용 업체 정보 수정
 */
function updateCompanyInfoByAdmin($companyId, $companyName, $password, $contactPerson, $phoneNumber, $email, $companyAddress, $zipCode, $itemGroup) {
    try {
        // 입력값 검증
        if (empty($companyName) || empty($password) || empty($contactPerson) || empty($phoneNumber)) {
            return ['success' => false, 'message' => '필수 항목을 모두 입력해주세요.'];
        }
        
        if (strlen($password) < 4) {
            return ['success' => false, 'message' => '비밀번호는 4자리 이상이어야 합니다.'];
        }
        
        // 전화번호 형식 검증
        if (!preg_match('/^[0-9-+\s()]+$/', $phoneNumber) || strlen($phoneNumber) < 10) {
            return ['success' => false, 'message' => '올바른 전화번호 형식을 입력해주세요.'];
        }
        
        // 이메일 형식 검증 (선택사항이지만 입력된 경우)
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '올바른 이메일 형식을 입력해주세요.'];
        }
        
        // 우편번호 형식 검증 (선택사항이지만 입력된 경우)
        if (!empty($zipCode) && !preg_match('/^\d{5}$/', $zipCode)) {
            return ['success' => false, 'message' => '우편번호는 5자리 숫자로 입력해주세요.'];
        }
        
        // 소속그룹 유효성 검증
        if (!empty($itemGroup)) {
            // 데이터베이스에서 소속그룹 확인
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_groups WHERE group_name = ?");
            $stmt->execute([$itemGroup]);
            if ($stmt->fetchColumn() == 0) {
                return ['success' => false, 'message' => '올바른 소속그룹을 선택해주세요.'];
            }
        }
        
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // 기존 업체 정보 조회
            $stmt = $pdo->prepare("SELECT company_name, password FROM companies WHERE id = ? AND active = 1");
            $stmt->execute([$companyId]);
            $existingCompany = $stmt->fetch();
            
            if (!$existingCompany) {
                throw new Exception("업체 정보를 찾을 수 없습니다.");
            }
            
            $oldCompanyName = $existingCompany['company_name'];
            $oldPassword = $existingCompany['password'];
            
            // 업체명 중복 검사 (변경된 경우)
            if ($oldCompanyName !== $companyName) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_name = ? AND id != ? AND active = 1");
                $stmt->execute([$companyName, $companyId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("이미 사용중인 업체명입니다.");
                }
            }
            
            // 비밀번호 중복 검사 (변경된 경우)
            if ($oldPassword !== $password) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE password = ? AND id != ? AND active = 1");
                $stmt->execute([$password, $companyId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("이미 사용중인 비밀번호입니다.");
                }
            }
            
            // companies 테이블 업데이트
            $stmt = $pdo->prepare("
                UPDATE companies 
                SET company_name = ?, password = ?, item_group = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$companyName, $password, $itemGroup, $companyId]);
            
            // company_details 테이블 업데이트
            $stmt = $pdo->prepare("
                UPDATE company_details 
                SET contact_person = ?, phone_number = ?, email = ?, 
                    company_address = ?, zip_code = ?, updated_at = NOW()
                WHERE company_id = ?
            ");
            $stmt->execute([$contactPerson, $phoneNumber, $email, $companyAddress, $zipCode, $companyId]);
            
            // company_details 레코드가 없는 경우 생성
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO company_details (company_id, company_name, contact_person, phone_number, email, company_address, zip_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$companyId, $companyName, $contactPerson, $phoneNumber, $email, $companyAddress, $zipCode]);
            }
            
            $pdo->commit();
            
            // Google Sheets 동기화 (업체명이나 비밀번호가 변경된 경우)
            $sheetsResult = ['success' => false, 'message' => 'Google Sheets 연동 시도하지 않음'];
            
            if (($oldCompanyName !== $companyName || $oldPassword !== $password) && file_exists(__DIR__ . '/google-sheets.php')) {
                require_once __DIR__ . '/google-sheets.php';
                
                try {
                    $sheetsResult = updateCompanyProfileInSheets($companyName, $password, $contactPerson, $phoneNumber);
                } catch (Exception $sheetsError) {
                    error_log("Google Sheets 동기화 오류: " . $sheetsError->getMessage());
                    $sheetsResult = ['success' => false, 'message' => $sheetsError->getMessage()];
                }
            }
            
            // 캐시 갱신 (업체명이나 비밀번호가 변경된 경우)
            if ($oldCompanyName !== $companyName || $oldPassword !== $password) {
                clearCache('companies_data');
            }
            
            // 로그 기록
            $changes = [];
            if ($oldCompanyName !== $companyName) $changes[] = "업체명: {$oldCompanyName} → {$companyName}";
            if ($oldPassword !== $password) $changes[] = "비밀번호 변경됨";
            if (!empty($changes)) {
                writeLog("관리자 업체정보 수정: {$companyName} (" . implode(', ', $changes) . ", Google Sheets 동기화: " . ($sheetsResult['success'] ? '성공' : '실패') . ")");
            }
            
            return [
                'success' => true,
                'message' => '업체 정보가 성공적으로 수정되었습니다.',
                'sheetsSync' => $sheetsResult['success']
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("관리자 업체정보 수정 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '업체 정보 수정 중 오류가 발생했습니다: ' . $e->getMessage()
        ];
    }
}

/**
 * 업체 품목 요청 관리 함수들
 */

// createCompanyItemRequest 함수는 functions.php에 정의됨

// 품목 요청 관련 함수들은 functions.php에 정의됨

?>