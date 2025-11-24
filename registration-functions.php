<?php
// registration-functions.php - 업체 등록 관련 함수들 (관리자가 배송요일/소속그룹 설정)

/**
 * 소속그룹 유효성 검사 (동적 관리)
 */
function validateItemGroup($itemGroup) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_groups WHERE group_name = ?");
        $stmt->execute([$itemGroup]);
        $count = $stmt->fetchColumn();
        
        return $count > 0;
    } catch (Exception $e) {
        error_log("Item group validation error: " . $e->getMessage());
        // 오류 시 기본값으로 검증
        $validGroups = ['도야짬뽕', '기타업체'];
        return in_array($itemGroup, $validGroups);
    }
}

/**
 * 배송요일 유효성 검사
 */
function validateDeliveryDay($deliveryDay) {
    $validDays = ['월수금', '화목토', '요일미정'];
    return in_array($deliveryDay, $validDays);
}

/**
 * 업체명 중복 체크
 */
function checkCompanyNameDuplicate($companyName) {
    try {
        $pdo = getDBConnection();
        
        // 기존 업체 테이블에서 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_name = ?");
        $stmt->execute([$companyName]);
        $existingCount = $stmt->fetchColumn();
        
        // 대기 중인 신청에서도 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registration_requests WHERE company_name = ? AND status = 'pending'");
        $stmt->execute([$companyName]);
        $pendingCount = $stmt->fetchColumn();
        
        return ($existingCount > 0 || $pendingCount > 0);
        
    } catch (Exception $e) {
        error_log("업체명 중복 체크 오류: " . $e->getMessage());
        return true; // 오류 시 중복으로 처리
    }
}

/**
 * 파일 업로드 검증 및 저장
 */
function handleFileUpload($fileData) {
    try {
        if (!$fileData || $fileData['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => '파일 업로드 중 오류가 발생했습니다.'
            ];
        }
        
        // 파일 크기 검증 (5MB 제한)
        if ($fileData['size'] > 5 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => '파일 크기는 5MB를 초과할 수 없습니다.'
            ];
        }
        
        // 파일 확장자 검증
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileExtension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'JPG,PNG,PDF만 업로드 가능합니다'
            ];
        }
        
        // MIME 타입 검증
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'application/pdf'
        ];
        
        if (!in_array($fileData['type'], $allowedMimeTypes)) {
            return [
                'success' => false,
                'message' => '올바르지 않은 파일형식입니다'
            ];
        }
        
        // 업로드 디렉토리 생성
        $uploadDir = __DIR__ . '/uploads/business_licenses/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => '파일저장 디렉토리를 생성할 수 없습니다.'
                ];
            }
        }
        
        // 고유한 파일명 생성 (보안을 위해)
        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $uniqueFileName;
        
        // 파일 이동
        if (!move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
            return [
                'success' => false,
                'message' => '파일 저장에 실패했습니다.'
            ];
        }
        
        return [
            'success' => true,
            'original_name' => $fileData['name'],
            'stored_path' => 'uploads/business_licenses/' . $uniqueFileName,
            'file_size' => $fileData['size']
        ];
        
    } catch (Exception $e) {
        error_log("파일 업로드 처리오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '파일 처리중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 업체 등록 신청 처리 (배송요일/소속그룹 제외)
 */
function processRegistrationRequest($data, $fileData = null) {
    try {
        // 입력 데이터 검증 (deliveryDay, itemGroup 제거)
        $requiredFields = ['companyName', 'password', 'contactPerson', 'phoneNumber'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => '필수정보를 모두 입력해주세요.'
                ];
            }
        }
        
        $companyName = trim($data['companyName']);
        $password = trim($data['password']);
        $address = trim($data['address'] ?? '');
        $zipCode = trim($data['zipCode'] ?? '');
        $contactPerson = trim($data['contactPerson']);
        $phoneNumber = trim($data['phoneNumber']);
        $email = trim($data['email'] ?? '');
        
        // 업체명 중복 체크
        if (checkCompanyNameDuplicate($companyName)) {
            return [
                'success' => false,
                'message' => '이미 등록된 업체명이거나 승인 대기중인 업체입니다.'
            ];
        }
        
        // 비밀번호 길이 체크
        if (strlen($password) < 4) {
            return [
                'success' => false,
                'message' => '비밀번호는 4자리 이상이어야 합니다.'
            ];
        }
        
        // 전화번호 형식 체크
        if (!preg_match('/^[0-9-+\s()]+$/', $phoneNumber)) {
            return [
                'success' => false,
                'message' => '올바른 전화번호 형식을 입력해주세요.'
            ];
        }
        
        // 우편번호 형식 체크 (5자리 숫자, 선택사항)
        if (!empty($zipCode) && !preg_match('/^\d{5}$/', $zipCode)) {
            return [
                'success' => false,
                'message' => '우편번호는 5자리 숫자로 입력해주세요. (예: 12345)'
            ];
        }
        
        // 사업자등록증 파일 처리
        $fileResult = null;
        if ($fileData) {
            $fileResult = handleFileUpload($fileData);
            if (!$fileResult['success']) {
                return $fileResult; // 파일 업로드 실패 시 즉시 반환
            }
        } else {
            return [
                'success' => false,
                'message' => '사업자등록증을 첨부해주세요.'
            ];
        }
        
        // 데이터베이스에 등록 신청 저장 (배송요일/소속그룹 제외)
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO registration_requests 
            (company_name, password, company_address, zip_code, contact_person, phone_number, email,
             business_license_file, business_license_path, business_license_size, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $companyName,
            $password,
            $address,
            $zipCode,
            $contactPerson,
            $phoneNumber,
            $email,
            $fileResult['original_name'],
            $fileResult['stored_path'],
            $fileResult['file_size']
        ]);
        
        // 로그 기록
        writeLog("신규업체 등록신청: {$companyName} (담당자: {$contactPerson}, 파일: {$fileResult['original_name']})");
        
        return [
            'success' => true,
            'message' => '등록 신청이 완료되었습니다.\n관리자 확인 후 사용승인 됩니다.',
            'requestId' => $pdo->lastInsertId()
        ];
        
    } catch (Exception $e) {
        error_log("업체등록 처리오류: " . $e->getMessage());
        
        // 파일이 저장된 경우 롤백을 위해 삭제
        if (isset($fileResult) && $fileResult && $fileResult['success']) {
            $filePath = __DIR__ . '/' . $fileResult['stored_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        return [
            'success' => false,
            'message' => '등록 처리중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.'
        ];
    }
}

/**
 * 등록 신청 목록 조회 (관리자용)
 */
function getRegistrationRequests($status = 'all') {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT * FROM registration_requests";
        $params = [];
        
        if ($status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY requested_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(),
            'count' => $stmt->rowCount()
        ];
        
    } catch (Exception $e) {
        error_log("등록신청 조회오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '데이터 조회중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 등록 신청 승인 처리 (관리자가 배송요일/소속그룹 설정)
 */
function approveRegistrationRequestWithSettings($requestId, $deliveryDay, $itemGroup, $approvedBy = 'admin') {
    try {
        // 입력값 검증 (배송요일 검증 제거)
        if (!validateItemGroup($itemGroup)) {
            return [
                'success' => false,
                'message' => '올바른 소속그룹을 선택해주세요.'
            ];
        }
        
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 신청 정보 조회
        $stmt = $pdo->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            throw new Exception("승인 대기건을 찾을 수 없습니다.");
        }
        
        // companies 테이블에 추가 (관리자가 설정한 배송요일/소속그룹 사용)
        $stmt = $pdo->prepare("
            INSERT INTO companies (company_name, password, delivery_day, item_group, approval_status, approved_at, approved_by, active) 
            VALUES (?, ?, ?, ?, 'approved', NOW(), ?, 1)
        ");
        $stmt->execute([
            $request['company_name'],
            $request['password'],
            $deliveryDay, // 관리자가 선택한 값
            $itemGroup,   // 관리자가 선택한 값
            $approvedBy
        ]);
        
        $companyId = $pdo->lastInsertId();
        
        // company_details 테이블에 상세 정보 추가
        $stmt = $pdo->prepare("
            INSERT INTO company_details (company_id, company_name, zip_code, company_address, contact_person, phone_number, email) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $companyId,
            $request['company_name'],
            $request['zip_code'] ?? '',
            $request['company_address'] ?? '',
            $request['contact_person'] ?? '',
            $request['phone_number'] ?? '',
            $request['email'] ?? ''
        ]);
        
        // 신청 상태 업데이트 (관리자가 설정한 값들을 기록)
        $stmt = $pdo->prepare("
            UPDATE registration_requests 
            SET status = 'approved', processed_at = NOW(), processed_by = ?, 
                delivery_day = ?, item_group = ?
            WHERE id = ?
        ");
        $stmt->execute([$approvedBy, $deliveryDay, $itemGroup, $requestId]);
        
        $pdo->commit();
        
        // Google Sheets 동기화
        $sheetsResult = ['success' => false, 'message' => 'Google Sheets 연동 시도하지 않음'];
        
        if (file_exists(__DIR__ . '/google-sheets.php')) {
            try {
                require_once __DIR__ . '/google-sheets.php';
                
                $companyData = [
                    'company_name' => $request['company_name'],
                    'password' => $request['password'],
                    'delivery_day' => $deliveryDay, // 관리자가 설정한 값
                    'item_group' => $itemGroup,     // 관리자가 설정한 값
                    'zip_code' => $request['zip_code'] ?? '',
                    'company_address' => $request['company_address'] ?? '',
                    'contact_person' => $request['contact_person'] ?? '',
                    'phone_number' => $request['phone_number'] ?? '',
                    'email' => $request['email'] ?? ''
                ];
                
                if (function_exists('syncApprovedCompanyToSheets')) {
                    $sheetsResult = syncApprovedCompanyToSheets($companyData);
                    
                    if ($sheetsResult['success']) {
                        writeLog("Google Sheets에 업체정보 동기화 성공: {$request['company_name']} (그룹: {$itemGroup}, 배송: {$deliveryDay})");
                    } else {
                        writeLog("Google Sheets 동기화 실패: " . ($sheetsResult['message'] ?? '알 수 없는 오류'));
                    }
                } else {
                    writeLog("syncApprovedCompanyToSheets 함수를 찾을 수 없음");
                    $sheetsResult = ['success' => false, 'message' => '동기화 함수 없음'];
                }
                
            } catch (Exception $e) {
                writeLog("Google Sheets 연동 오류: " . $e->getMessage());
                $sheetsResult = ['success' => false, 'message' => 'Google Sheets 연동오류: ' . $e->getMessage()];
            }
        } else {
            writeLog("google-sheets.php 파일을 찾을 수 없음");
        }
        
        // 캐시 갱신
        clearCache('companies_data');
        
        // 결과 메시지 구성
        $message = "'{$request['company_name']}' 업체가 승인되었습니다. (그룹: {$itemGroup})";
        
        // 로그 기록
        writeLog("업체등록 승인: {$request['company_name']} (승인자: {$approvedBy}, 그룹: {$itemGroup}, 배송: {$deliveryDay}, Sheets 동기화: " . 
                 ($sheetsResult['success'] ? '성공' : '실패') . ")");
        
        return [
            'success' => true,
            'message' => $message,
            'companyId' => $companyId,
            'sheetsSync' => $sheetsResult['success']
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("등록승인 처리오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '승인 처리중 오류가 발생했습니다: ' . $e->getMessage()
        ];
    }
}

/**
 * 기존 승인 함수 (호환성을 위해 유지, 기본값 사용)
 */
function approveRegistrationRequest($requestId, $approvedBy = 'admin') {
    // 기본값으로 승인 처리
    return approveRegistrationRequestWithSettings($requestId, '월수금', '도야짬뽕', $approvedBy);
}