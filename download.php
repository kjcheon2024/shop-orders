<?php
/**
 * download.php - 사업자등록증 파일 다운로드 처리
 */
session_start();
require_once 'config.php';

// 관리자 권한 확인
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    die('접근 권한이 없습니다.');
}

// 요청 ID 확인
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('잘못된 요청입니다.');
}

$requestId = intval($_GET['id']);

try {
    $pdo = getDBConnection();
    
    // 파일 정보 조회
    $stmt = $pdo->prepare("
        SELECT business_license_file, business_license_path, business_license_size 
        FROM registration_requests 
        WHERE id = ?
    ");
    $stmt->execute([$requestId]);
    $fileInfo = $stmt->fetch();
    
    if (!$fileInfo) {
        http_response_code(404);
        die('파일 정보를 찾을 수 없습니다.');
    }
    
    if (empty($fileInfo['business_license_file']) || empty($fileInfo['business_license_path'])) {
        http_response_code(404);
        die('첨부된 파일이 없습니다.');
    }
    
    // 실제 파일 경로 (오타 수정: **DIR** -> __DIR__)
    $filePath = __DIR__ . '/' . $fileInfo['business_license_path'];
    
    // 파일 존재 확인
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('파일을 찾을 수 없습니다.');
    }
    
    // 파일 읽기 권한 확인
    if (!is_readable($filePath)) {
        http_response_code(500);
        die('파일을 읽을 수 없습니다.');
    }
    
    // 파일 확장자에 따른 MIME 타입 설정
    $originalFileName = $fileInfo['business_license_file'];
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf'
    ];
    
    $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
    
    // 파일 크기 확인
    $fileSize = filesize($filePath);
    if ($fileSize === false) {
        http_response_code(500);
        die('파일 크기를 확인할 수 없습니다.');
    }
    
    // 다운로드 로그 기록
    error_log("파일 다운로드: {$originalFileName} (요청ID: {$requestId}, 관리자: admin)");
    
    // 헤더 설정
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: attachment; filename="' . addslashes($originalFileName) . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 출력 버퍼 정리
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // 파일 출력
    if ($fileSize > 8 * 1024 * 1024) { // 8MB 이상인 경우 청크 단위로 읽기
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die('파일을 열 수 없습니다.');
        }
        
        while (!feof($handle)) {
            $chunk = fread($handle, 8192); // 8KB씩 읽기
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
        }
        fclose($handle);
    } else {
        // 작은 파일은 한번에 출력
        readfile($filePath);
    }
    
    exit;
    
} catch (PDOException $e) {
    error_log("데이터베이스 오류 (파일 다운로드): " . $e->getMessage());
    http_response_code(500);
    die('데이터베이스 오류가 발생했습니다.');
    
} catch (Exception $e) {
    error_log("파일 다운로드 오류: " . $e->getMessage());
    http_response_code(500);
    die('파일 다운로드 중 오류가 발생했습니다.');
}
?>