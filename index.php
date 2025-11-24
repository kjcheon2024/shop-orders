<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // 파일 업로드를 포함한 폼 데이터 처리
    if (isset($_POST['action']) && $_POST['action'] === 'registerCompany') {
        // 멀티파트 폼 데이터에서 JSON이 아닌 POST 데이터로 처리
        $data = [
            'companyName' => $_POST['companyName'] ?? '',
            'password' => $_POST['password'] ?? '',
            'contactPerson' => $_POST['contactPerson'] ?? '',
            'phoneNumber' => $_POST['phoneNumber'] ?? '',
            // 배송요일과 소속그룹 제거 - 관리자가 승인 시 설정
            'zipCode' => $_POST['zipCode'] ?? '',
            'address' => $_POST['address'] ?? '', // 결합된 주소
            'email' => $_POST['email'] ?? ''
        ];
        
        // 파일 업로드 처리
        $fileData = null;
        if (isset($_FILES['businessLicense']) && $_FILES['businessLicense']['error'] === UPLOAD_ERR_OK) {
            $fileData = $_FILES['businessLicense'];
        }
        
        echo json_encode(processRegistrationRequest($data, $fileData));
        exit;
    }
    
    // 기존 JSON 기반 AJAX 요청 처리
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'findCompanyByPassword':
            echo json_encode(findCompanyByPassword($input['password']));
            exit;
            
        case 'checkOrderBlock':
            // 주문차단 상태 확인
            $companyName = $_SESSION['company_name'] ?? '';
            if (empty($companyName)) {
                echo json_encode(['blocked' => false, 'reason' => '']);
                exit;
            }
            
            $blockStatus = checkCompanyOrderBlock($companyName);
            echo json_encode($blockStatus);
            exit;
            
        case 'processOrder':
            // 주문 처리 전에 차단 상태 확인
            $companyName = $_SESSION['company_name'] ?? '';
            
            if (empty($companyName)) {
                echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
                exit;
            }
            
            // 주문차단 상태 확인
            $blockStatus = checkCompanyOrderBlock($companyName);
            if ($blockStatus['blocked']) {
                echo json_encode([
                    'success' => false,
                    'blocked' => true,
                    'message' => '주문이 차단되었습니다.',
                    'reason' => $blockStatus['reason']
                ]);
                exit;
            }
            
            // 기존 processOrder 호출
            echo json_encode(processOrder($input['orderData']));
            exit;

        case 'checkCompanyName':
            echo json_encode([
                'duplicate' => checkCompanyNameDuplicate($input['companyName'])
            ]);
            exit;
            
        case 'getTodayOrderStatus':
            // 주문조회는 차단 상태와 관계없이 허용 (함수 내부에서 처리됨)
            echo json_encode(getTodayOrderStatus($input['companyName']));
            exit;
            
        case 'getRecentOrderHistory':
            // 주문이력도 차단 상태와 관계없이 허용 (함수 내부에서 처리됨)
            $days = $input['days'] ?? 7;
            echo json_encode(getRecentOrderHistory($input['companyName'], $days));
            exit;
            
        case 'canModifyTodayOrder':
            // 주문수정은 차단 상태 확인하도록 함수 내부에서 처리됨
            echo json_encode(canModifyTodayOrder($input['companyName']));
            exit;
            
        case 'checkGoogleSheetsSync':
            // Google Sheets 동기화 확인 - 차단 상태와 관계없이 동기화 상태만 확인
            $companyName = $_SESSION['company_name'] ?? '';
            if (!empty($companyName)) {
                // 차단 상태 확인은 하되, 동기화 확인은 계속 진행
                $blockStatus = checkCompanyOrderBlock($companyName);
                
                // 차단 상태라면 동기화 상태를 숨김 처리
                if ($blockStatus['blocked']) {
                    echo json_encode([
                        'success' => false,
                        'syncStatus' => 'unavailable',
                        'message' => '' // 빈 메시지로 동기화 상태를 숨김
                    ]);
                    exit;
                }
            }
            echo json_encode(checkGoogleSheetsSync($input['companyName']));
            exit;
            
        case 'getCompanyProfile':
            echo json_encode(getCompanyProfile($input['companyName']));
            exit;
            
        case 'updateCompanyProfile':
            echo json_encode(updateCompanyProfile($input));
            exit;
            
        // ========================================
        // 공지 관련 액션 (신규 추가)
        // ========================================
        case 'getUnreadGlobalNotices':
            // 로그인한 업체의 읽지 않은 전체공지 조회
            $companyName = $_SESSION['company_name'] ?? '';
            if (empty($companyName)) {
                echo json_encode(['success' => false, 'notices' => []]);
                exit;
            }
            echo json_encode(getUnreadGlobalNoticesForCompany($companyName));
            exit;
            
        case 'markNoticeAsRead':
            // 공지 읽음 처리
            $companyName = $_SESSION['company_name'] ?? '';
            $noticeId = $input['noticeId'] ?? 0;
            
            if (empty($companyName) || $noticeId <= 0) {
                echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
                exit;
            }
            
            echo json_encode(markNoticeAsReadByCompany($noticeId, $companyName));
            exit;
	    
        case 'getIndividualNotices':
            // 개별메시지 조회 (신규 추가)
            $companyName = $_SESSION['company_name'] ?? '';
            
            if (empty($companyName)) {
                echo json_encode(['success' => false, 'notices' => []]);
                exit;
            }
            
            echo json_encode(getIndividualNoticesForCompany($companyName));
            exit;
            
        case 'getCompanyId':
            // 업체명으로 업체 ID 조회
            $companyName = $input['companyName'] ?? '';
            if (empty($companyName)) {
                echo json_encode(['success' => false, 'message' => '업체명이 필요합니다.']);
                exit;
            }
            
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE company_name = ? AND active = 1");
                $stmt->execute([$companyName]);
                $company = $stmt->fetch();
                
                if ($company) {
                    echo json_encode(['success' => true, 'companyId' => $company['id']]);
                } else {
                    echo json_encode(['success' => false, 'message' => '업체를 찾을 수 없습니다.']);
                }
            } catch (Exception $e) {
                error_log("Get company ID error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => '업체 ID 조회 중 오류가 발생했습니다.']);
            }
            exit;
            
        // ========================================
        // 품목 요청 관련 액션 (신규 추가)
        // ========================================
        case 'getCompanyItemRequestStatus':
            try {
                $companyId = intval($input['companyId'] ?? 0);
                if ($companyId <= 0) {
                    echo json_encode(['success' => false, 'message' => '유효하지 않은 업체 ID입니다.']);
                    exit;
                }
                
                $result = getCompanyItemRequestStatus($companyId);
                echo json_encode($result);
                exit;
            } catch (Exception $e) {
                error_log("Get company item request status error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => '품목 상태 조회 중 오류가 발생했습니다.']);
                exit;
            }
            
        case 'createCompanyItemRequest':
            try {
                $companyId = intval($input['companyId'] ?? 0);
                $itemId = intval($input['itemId'] ?? 0);
                $requestAction = $input['requestAction'] ?? ''; // 'add' or 'remove'
                
                if ($companyId <= 0 || $itemId <= 0 || !in_array($requestAction, ['add', 'remove'])) {
                    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
                    exit;
                }
                
                $result = createCompanyItemRequest($companyId, $itemId, $requestAction);
                echo json_encode($result);
                exit;
            } catch (Exception $e) {
                error_log("Create company item request error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => '요청 처리 중 오류가 발생했습니다.']);
                exit;
            }
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>주문 시스템</title>
    <link rel="stylesheet" href="assets/css/style_v44.css">
    <link rel="stylesheet" href="assets/css/form.css">
    <link rel="stylesheet" href="assets/css/user-notices_v10.css">
    <link rel="stylesheet" href="assets/css/item-management_v14.css">
    
    <style>
        /* 신규업체 등록 폼 모바일 최적화 */
        .registration-form .form-group {
            margin-bottom: 15px;
        }
        
        .registration-form .form-group label {
            display: inline-block;
            width: 80px;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-right: 10px;
            vertical-align: top;
            padding-top: 8px;
        }
        
        .registration-form .form-group input,
        .registration-form .form-group select {
            display: inline-block;
            width: calc(100% - 90px);
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            height: 36px;
            box-sizing: border-box;
        }
        
        /* 모바일에서 라벨과 입력박스를 한 줄에 표시 */
        @media (max-width: 768px) {
            .registration-form .form-group {
                display: flex;
                align-items: center;
                margin-bottom: 12px;
            }
            
            .registration-form .form-group label {
                width: 90px;
                margin-right: 8px;
                padding-top: 0;
                flex-shrink: 0;
                font-size: 13px;
                line-height: 1.2;
            }
            
            .registration-form .form-group input,
            .registration-form .form-group select {
                width: calc(100% - 98px);
                height: 32px;
                padding: 6px 10px;
                font-size: 14px;
            }
            
            /* form-row는 모바일에서 세로로 배치 */
            .registration-form .form-row {
                display: block;
            }
            
            .registration-form .form-row .form-group {
                width: 100%;
                margin-bottom: 12px;
            }
            
            /* 우편번호 검색 섹션 정렬 개선 - 기존 CSS 오버라이드 */
            .registration-form .address-search-row {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-bottom: 12px !important;
                margin-top: -12px !important;
            }
            
            .registration-form .address-search-row .zipcode-group {
                display: flex !important;
                align-items: center !important;
                flex: 1 !important;
                margin-bottom: 0 !important;
            }
            
            .registration-form .address-search-row .zipcode-group label {
                width: 90px !important;
                margin-right: 8px !important;
                margin-bottom: 0 !important;
                flex-shrink: 0 !important;
                font-size: 13px !important;
                line-height: 1.2 !important;
            }
            
            .registration-form .address-search-row .zipcode-group input {
                width: calc(100% - 98px) !important;
                height: 32px !important;
                padding: 6px 10px !important;
                font-size: 14px !important;
                box-sizing: border-box !important;
                border: 1px solid #ddd !important;
                margin-bottom: 0 !important;
            }
            
            .registration-form .address-search-row .search-btn-group {
                flex-shrink: 0 !important;
                display: flex !important;
                align-items: stretch !important;
                margin-bottom: 0 !important;
            }
            
            .registration-form .address-search-btn {
                height: 32px !important;
                padding: 6px 12px !important;
                font-size: 13px !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                margin: 0 !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                border: 1px solid #ddd !important;
                background-color: #f8f9fa !important;
                color: #333 !important;
                box-sizing: border-box !important;
                min-width: auto !important;
                position: relative !important;
                top: 12px !important;
                align-self: center !important;
            }
            
            /* 사업자등록증 첨부 섹션 개선 */
            .file-upload-container {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .file-upload-container input[type="file"] {
                width: 100%;
                padding: 12px 16px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                line-height: 1.6;
                box-sizing: border-box;
                min-height: 48px;
            }
            
            .file-upload-info {
                font-size: 12px;
                color: #666;
                margin-top: 4px;
            }
            
            .file-upload-container .file-info {
                font-size: 12px;
                color: #007bff;
                margin-top: 4px;
                word-break: break-all;
                padding: 4px 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #e9ecef;
            }
            
            .file-upload-container .file-info.hidden {
                display: none;
            }
            
            .file-preview {
                margin-top: 8px;
            }
            
            .file-preview .preview-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px;
                background: #e7f3ff;
                border-radius: 4px;
                border: 1px solid #b3d9ff;
            }
            
            .file-preview .file-name {
                font-size: 12px;
                color: #007bff;
                font-weight: 500;
            }
            
            .file-preview .file-size {
                font-size: 11px;
                color: #666;
            }
            
            .file-preview .remove-file {
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                font-size: 12px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 메인 탭 네비게이션 -->
        <div id="mainTabs" class="form-tabs">
            <button class="tab-btn active" onclick="showTab('login')">기존 거래처</button>
            <button class="tab-btn" onclick="showTab('register')">신규업체</button>
        </div>

        <!-- 로그인 폼 -->
        <div id="loginForm">
            <div class="header">
                <h1>천하유통</h1>
                <p>비밀번호 입력시 업체명 자동매칭</p>
                <p style="font-size:12px; text-decoration:underline; text-underline-offset:5px;">(주문시간: 08:00~익일 05:00 까지)</p>
            </div>
            
            <div class="form-group">
                <!-- <label for="password">비밀번호</label>-->
                <input type="password" id="password" placeholder="비밀번호" required>
            </div>
            
            <div id="companyPreview" class="company-preview hidden">
                <div class="preview-label">업체명:</div>
                <div id="previewCompanyName" class="preview-company"></div>
            </div>
            
            <button class="btn btn-primary" id="loginBtn" onclick="login()" disabled>로그인</button>
            
            <div id="loginMessage"></div>
        </div>

        <!-- 업체 등록 폼 -->
        <div id="registrationForm" class="hidden">
            <div class="header">
                <h1>등록 신청</h1>
                <p style="font-size:12px; text-decoration:underline; text-underline-offset:5px;">신청 > 관리자 확인 후 발주가능</p>
            </div>

            <form id="registrationFormElement" class="registration-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="registerCompany">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="regCompanyName">업체명 <span style="color: red;">*</span></label>
                        <input type="text" id="regCompanyName" name="companyName" placeholder="상호명" required>
                        <div id="companyNameStatus" class="validation-status"></div>
                    </div>
                    <div class="form-group">
                        <label for="regPassword">비밀번호 <span style="color: red;">*</span></label>
                        <input type="password" id="regPassword" name="password" placeholder="4자리 이상" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="regContactPerson">주문담당자 <span style="color: red;">*</span></label>
                        <input type="text" id="regContactPerson" name="contactPerson" placeholder="직급" required>
                    </div>
                    <div class="form-group">
                        <label for="regPhoneNumber">전화번호 <span style="color: red;">*</span></label>
                        <input type="text" id="regPhoneNumber" name="phoneNumber" placeholder="010-1234-5678" required>
                    </div>
                </div>

                <!-- 주소 섹션 -->
                <div class="address-search-row">
                    <div class="form-group zipcode-group">
                        <label for="regZipCode">우편번호</label>
                        <input type="text" id="regZipCode" name="zipCode" placeholder="" maxlength="5" readonly>
                    </div>
                    <div class="search-btn-group">
                        <button type="button" class="address-search-btn" id="addressSearchBtn" onclick="findAddr()">
                            주소검색
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="regAddress1">주소</label>
                    <input type="text" id="regAddress1" name="address1" placeholder="도로명 주소" readonly>
                </div>

                <div class="form-group">
                    <label for="regAddress2">상세주소</label>
                    <input type="text" id="regAddress2" name="address2" placeholder="동,호수...">
                </div>

                <!-- 이메일 섹션 주석처리
                <div class="form-group">
                    <label for="regEmail">이메일</label>
                    <input type="text" id="regEmail" name="email" placeholder="(선택사항)">
                </div>
                -->

                <!-- 사업자등록증 파일 업로드 필드 -->
                <div class="form-group">
                    <label for="regBusinessLicense">사업자등록증 <span style="color: red;">*</span></label>
                    <div class="file-upload-container">
                        <input type="file" id="regBusinessLicense" name="businessLicense" accept="image/*,.pdf" required>
                        <div class="file-upload-info">
                            <small>JPG, PNG, PDF (최대 5MB)</small>
                        </div>
                        <div id="filePreview" class="file-preview hidden">
                            <div class="preview-content">
                                <span class="file-name"></span>
                                <span class="file-size"></span>
                                <button type="button" class="remove-file" onclick="removeFile()">×</button>
                            </div>
                        </div>
                        <div id="fileInfo" class="file-info hidden">
                            <span id="selectedFileName"></span>
                        </div>
                    </div>
                </div>

                <!-- 히든 필드로 결합된 주소 전송 -->
                <input type="hidden" id="regAddress" name="address">

                <button type="submit" class="btn btn-success" id="registerBtn">등록신청</button>
            </form>

            <div id="registrationMessage"></div>
        </div>
        
        <!-- 주문 불가 시간대 안내 화면 -->
        <div id="orderRestrictedForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <div class="nav-buttons-container">
                <button class="nav-btn-item-management" onclick="showItemManagementForm()">품목관리</button>
                <button class="nav-btn-order-status" onclick="showOrderStatusForm()">주문조회</button>
            </div>
            
            <div class="header">
                <h1>주문 처리 시간</h1>
                <p>지금은 주문을 받을 수 없는 시간입니다</p>
            </div>
            
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentRestrictedCompany"></strong> ]
            </div>
            
            <div class="order-restricted-content">
                <div class="restricted-message">
                    <h3>🕐 주문시간 안내</h3>
                    <div class="time-info">
                        <div class="time-item">
                            <span class="time-label">현재 시간:</span>
                            <span class="time-value" id="currentRestrictedTime">--:--</span>
                        </div>
                        <div class="time-item">
                            <span class="time-label">주문가능 시간:</span>
                            <span class="time-value">08:00 ~ 익일 05:00</span>
                        </div>
						<!--
                        <div class="time-item">
                            <span class="time-label">다음 주문 시간:</span>
                            <span class="time-value" id="nextRestrictedOrderTime">--:--</span>
                        </div>
						-->
                    </div>
                </div>
                
                <div class="restricted-actions">
                    <p class="restricted-notice">
                        주문조회는 가능합니다
                    </p>
                </div>
            </div>
        </div>
        
        <!-- 주문 폼 - 품목 선택 -->
        <div id="orderForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <div class="nav-buttons-container">
                <button class="nav-btn-order-status" onclick="showOrderStatusForm()">주문조회</button>
                <button class="nav-btn-item-management" onclick="showItemManagementForm()">품목관리</button>
            </div>
			
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentCompany"></strong> ]
                <button class="profile-edit-btn" onclick="showProfileEditModal()">담당자/비번 변경</button>
            </div>
	    
            <!-- ========================================
                 개별메시지 배너 컨테이너 (신규 추가)
                 ======================================== -->
            <div id="individualNoticeBanner">
                <!-- JavaScript로 동적 생성됨 -->
            </div>	    
			
            <div class="header">
                <h3 style="text-decoration:underline; text-underline-offset:5px;">주문-품목선택</h3>
				<p style="font-size: 10px; color: #666; margin-top: 5px;">(선택 후 아래 선택완료)</p>
            </div>
            
            <div>
                <!-- <h3 style="font-size:16px; font-weight:normal; text-align:center;">주문할 품목을 선택하세요</h3> -->
                <div id="itemCheckboxes" class="checkbox-grid">
                    <!-- 동적으로 생성됨 -->
                </div>
                <button class="btn btn-secondary" id="selectCompleteBtn" onclick="showQuantityForm()" disabled>선택완료</button>
            </div>
            
            <div id="orderMessage"></div>
        </div>

        <!-- 수량 입력 폼 -->
        <div id="quantityForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <button class="nav-btn" onclick="showOrderStatusForm()">주문조회</button>
			
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentCompany2"></strong> ]
            </div>			
            
            <div class="header">
                <h3 style="text-decoration:underline; text-underline-offset:5px;">수량입력</h3>
				<p style="font-size: 10px; color: #666; margin-top: 5px;">(입력 후 아래 입력완료)</p>               
            </div>           
            
            <div class="quantity-notice">
                중량(kg)이 아닌 팩(수량)을 입력해 주세요
            </div>
            
            <div class="quantity-container">
                <!--<h3 style="font-size:14px; font-weight:normal; text-align:center;">선택 품목의 수량을 입력하세요</h3>-->
                <div id="quantityInputs">
                    <!-- 동적으로 생성됨 -->
                </div>
            </div>
            
            <button class="btn back-btn" onclick="backToItemSelection()">이전으로</button>
            <button class="btn btn-primary" onclick="confirmQuantities()">입력완료</button>
            
            <div id="quantityMessage"></div>
        </div>
        
        <!-- 주문 확인 폼 -->
        <div id="confirmForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <button class="nav-btn" onclick="showOrderStatusForm()">주문조회</button>
			
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentCompany3"></strong> ]
            </div>			
            
            <div class="header">
                <h3 style="text-decoration:underline; text-underline-offset:5px;">주문확인</h3>
				<p style="font-size: 10px; color: #666; margin-top: 5px;">(확인 후 아래 주문하기)</p>                
            </div>            
            
            <div id="selectedItemsDisplay" class="selected-items">
                <!--<h3 style="font-size:14px; font-weight:normal; margin-bottom:10px;">주문내역</h3>-->
                <div id="selectedItemsList"></div>
            </div>
            
            <button class="btn back-btn" onclick="backToQuantityInput()">수량변경</button>
            <button class="btn btn-success" id="submitOrderBtn" onclick="submitOrder()">주문하기</button>
            
            <div id="confirmMessage"></div>
        </div>
        
        <!-- 주문조회 폼 -->
        <div id="orderStatusForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <div class="nav-buttons-container">
                <button class="nav-btn-go-order" onclick="goToOrder()">주문하기</button>
                <button class="nav-btn-item-management" onclick="showItemManagementForm()">품목관리</button>
            </div>
			
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentCompany4"></strong> ]
            </div>			
            
            <div class="header">
                <h3 style="text-decoration:underline; text-underline-offset:5px;">주문조회</h3>
            </div>           
            
            <!-- 주문조회 탭 네비게이션 -->
            <div class="order-status-tabs">
                <button class="tab-btn active" onclick="showOrderTab('today')">오늘 주문</button>
                <button class="tab-btn" onclick="showOrderTab('history')">최근 일주일</button>
            </div>
            
            <!-- 오늘 주문 현황 - 간소화된 헤더 -->
            <div id="todayOrderTab" class="order-tab-content">
                <div class="order-status-header-simple">
                    <h3 >오늘 주문 현황</h3>
                </div>
                
                <div id="todayOrderContent">
                    <div class="loading-message">주문 현황을 불러오는 중...</div>
                </div>
                
                <div class="sync-status hidden" id="syncStatus">
                    <div class="sync-indicator">
                        <span class="sync-icon">🔄</span>
                        <span class="sync-text">동기화 상태 확인 중...</span>
                    </div>
                </div>
            </div>
            
            <!-- 최근 주문 이력 -->
            <div id="historyOrderTab" class="order-tab-content hidden">
                <div class="order-status-header">
                    <h3>최근 일주일 주문 이력</h3>
                </div>
                
                <div id="historyOrderContent">
                    <div class="loading-message">주문 이력을 불러오는 중...</div>
                </div>
            </div>
            
            <div id="orderStatusMessage"></div>
        </div>
        
        <!-- 담당자변경 모달 -->
        <div id="profileEditModal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>담당자/비번 정보수정</h3>
                    <span class="close" onclick="closeProfileEditModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="profileEditForm">
                        <div class="form-group">
                            <label for="editPassword">비밀번호 <span style="color: red;">*</span></label>
                            <input type="password" id="editPassword" placeholder="4자리 이상" required>
                        </div>
                        <div class="form-group">
                            <label for="editContactPerson">담당자명 <span style="color: red;">*</span></label>
                            <input type="text" id="editContactPerson" placeholder="담당자명" required>
                        </div>
                        <div class="form-group">
                            <label for="editPhoneNumber">전화번호 <span style="color: red;">*</span></label>
                            <input type="text" id="editPhoneNumber" placeholder="010-1234-5678" required>
                        </div>
                        <div class="profile-readonly-info">
                            <p><strong>수정 불가 항목:</strong> 업체명, 주소, 사업자등록증</p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success" onclick="saveProfileChanges()">수정 완료</button>
                    <button class="btn btn-secondary" onclick="closeProfileEditModal()">취소</button>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             전체공지 모달 (신규 추가)
             ======================================== -->
        <div id="globalNoticeModal" class="notice-modal hidden">
            <div class="notice-modal-overlay"></div>
            <div class="notice-modal-content">
                <div class="notice-modal-header">
                    <h3>중요 공지</h3>
                    <button class="notice-close-btn" onclick="closeGlobalNoticeModal()">&times;</button>
                </div>
                <div class="notice-modal-body" id="globalNoticeBody">
                    <!-- 공지 내용이 여기에 표시됨 -->
                </div>
                <div class="notice-modal-footer">
                    <label class="dont-show-again">
                        <input type="checkbox" id="dontShowAgainCheckbox">
                        공지사항 완전숙지 (다시 보지 않음)
                    </label>
                    <button class="btn btn-primary" onclick="confirmGlobalNotice()">확인</button>
                </div>
            </div>
        </div>
        
        <!-- 품목관리 폼 (신규 추가) -->
        <div id="itemManagementForm" class="hidden">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <div class="nav-buttons-container">
                <button class="nav-btn-go-order" onclick="goToOrder()">주문하기</button>
                <button class="nav-btn-order-status" onclick="showOrderStatusForm()">주문조회</button>
            </div>
			
            <div class="company-info" style="font-size: 20px;">
                [ <strong id="currentCompany5"></strong> ]
            </div>
            
            <div class="header">
                <h3 style="text-decoration:underline; text-underline-offset:5px;">품목관리</h3>
                <p style="font-size: 12px; color: #666; margin-top: 5px;">요청은 관리자 확인 후 반영됩니다.</p>
            </div>
            
            <!-- 현재 할당된 품목 -->
            <div class="assigned-items-section">
                <h4>현재 주문가능 품목</h4>
                <div id="assignedItemsList" class="item-list">
                    <div class="loading-message">할당된 품목을 불러오는 중...</div>
                </div>
            </div>
            
            <!-- 전체 품목 목록 -->
            <div class="all-items-section">
                <h4>전체 목록</h4>
                <div class="item-filter">
                    <select id="categoryFilter" onchange="filterItemsByCategory()">
                        <option value="">전체 카테고리</option>
                    </select>
                </div>
                <div id="allItemsList" class="item-list">
                    <div class="loading-message">전체 품목을 불러오는 중...</div>
                </div>
            </div>
            
            <!-- 요청 상태 표시 -->
            <div id="requestStatus" class="request-status hidden">
                <h4>요청 상태</h4>
                <div id="pendingRequests"></div>
            </div>
            
            <div id="itemManagementMessage"></div>
        </div>
        
        <div id="loading" class="loading hidden">
            <p>처리중...</p>
        </div>
    </div>
    
    <!-- External Scripts -->
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    
    <!-- Modular JavaScript Files -->
    <script src="assets/js/global_v7.js"></script>
    <script src="assets/js/auth_v21.js"></script>
    <script src="assets/js/registration_v2.js"></script>
    <script src="assets/js/order_v28.js"></script>
    <script src="assets/js/time-restriction_v5.js"></script>
    <script src="assets/js/order-status_v36.js"></script>
    <script src="assets/js/profile-edit_v3.js"></script>
    <script src="assets/js/user-notices_v10.js"></script>
    <script src="assets/js/item-management_v11.js"></script>
    <script src="assets/js/app_v10.js"></script>
</body>
</html>