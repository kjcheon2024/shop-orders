<?php
// config.php - 완전한 설정 파일 (Google Sheets API 연동 포함)

// 데이터베이스 설정
define('DB_HOST', 'localhost');
define('DB_NAME', 'u102965352_shoporders');  // 실제 데이터베이스명으로 변경
define('DB_USER', 'u102965352_shoporders');
define('DB_PASS', 'Chfood2026**//');
define('DB_CHARSET', 'utf8mb4');

// Google Sheets 설정
define('SPREADSHEET_ID', '1UJEK-OUlN_BF7Z_bGzZpe8F21Lu2PYt4Yibk2eqLEcA');
define('GOOGLE_CREDENTIALS_PATH', __DIR__ . '/credentials/service-account.json');

// Google Sheets 시트명 설정 (동적 관리로 변경)
// 이제 데이터베이스의 sheet_configs 테이블에서 관리됩니다
// define('SHEET_ORDER_MON_WED_FRI', '배송-월수금');
// define('SHEET_ORDER_TUE_THU_SAT', '배송-화목토');
// define('SHEET_ORDER_NEXT_DAY', '배송-요일미정'); 
define('SHEET_COMPANY_MANAGEMENT', '업체관리');
define('SHEET_COMPANY_INFO', '업체정보'); 
define('SHEET_ORDER_LOG', '주문로그');
define('SHEET_ITEM_LIST', '품목리스트');

// 캐시 설정
define('CACHE_DURATION', 300); // 5분 (초 단위)
define('CACHE_DIR', __DIR__ . '/cache/');

// 로그 설정
define('LOG_DIR', __DIR__ . '/logs/');

// 파일 업로드 설정
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('BUSINESS_LICENSE_DIR', UPLOAD_DIR . 'business_licenses/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'application/pdf'
]);

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 관리자 인증 설정 (보안 강화)
// 비밀번호는 password_hash() 함수로 생성된 해시값을 사용
// 새 비밀번호 생성: echo password_hash('새비밀번호', PASSWORD_DEFAULT);
define('ADMIN_PASSWORD_HASH', '$2y$10$lBt0mOQSjSWwuRy8L/BpRO29rIr779iglMWwCXTq4wVchjEiUqt8K');
define('ADMIN_SESSION_TIMEOUT', 3600); // 1시간 (초 단위)

// 에러 리포팅 (개발환경에서만 사용)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// 운영환경 설정
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_DIR . 'php_errors.log');

/**
 * 데이터베이스 연결
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            // 디비타임존을 서울로 변경 설정
            $pdo->exec("SET time_zone = '+09:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("데이터베이스 연결에 실패했습니다.");
        }
    }
    
    return $pdo;
}

/**
 * 필요한 디렉토리 생성
 */
function createRequiredDirectories() {
    $directories = [
        CACHE_DIR,
        LOG_DIR,
        UPLOAD_DIR,
        BUSINESS_LICENSE_DIR,
        dirname(GOOGLE_CREDENTIALS_PATH)
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// 디렉토리 생성
createRequiredDirectories();

/**
 * 환경 검증
 */
function validateEnvironment() {
    $errors = [];
    
    // PHP 버전 확인 (최소 7.4)
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = 'PHP 7.4 이상이 필요합니다. 현재 버전: ' . PHP_VERSION;
    }
    
    // 필수 PHP 확장 확인
    $required_extensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'fileinfo'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "PHP 확장 '{$ext}'이 설치되지 않았습니다.";
        }
    }
    
    // Composer 설치 확인
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        $errors[] = 'Composer 패키지가 설치되지 않았습니다. "composer require google/apiclient:^2.0" 명령을 실행하세요.';
    }
    
    // Google 서비스 계정 파일 확인
    if (!file_exists(GOOGLE_CREDENTIALS_PATH)) {
        $errors[] = 'Google 서비스 계정 파일이 없습니다: ' . GOOGLE_CREDENTIALS_PATH;
    } else {
        // JSON 파일 유효성 검사
        $credentials = json_decode(file_get_contents(GOOGLE_CREDENTIALS_PATH), true);
        if (!$credentials || !isset($credentials['client_email'])) {
            $errors[] = 'Google 서비스 계정 파일 형식이 올바르지 않습니다.';
        }
    }
    
    // 디렉토리 권한 확인
    $directories = [CACHE_DIR, LOG_DIR, UPLOAD_DIR, BUSINESS_LICENSE_DIR];
    foreach ($directories as $dir) {
        if (!is_writable($dir)) {
            $errors[] = "디렉토리 '{$dir}'에 쓰기 권한이 없습니다.";
        }
    }
    
    // 파일 업로드 설정 확인
    $upload_max_filesize = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');
    $max_file_size_mb = MAX_FILE_SIZE / (1024 * 1024);
    
    if (parseSize($upload_max_filesize) < MAX_FILE_SIZE) {
        $errors[] = "upload_max_filesize ({$upload_max_filesize})가 설정된 최대 파일 크기({$max_file_size_mb}MB)보다 작습니다.";
    }
    
    if (parseSize($post_max_size) < MAX_FILE_SIZE) {
        $errors[] = "post_max_size ({$post_max_size})가 설정된 최대 파일 크기({$max_file_size_mb}MB)보다 작습니다.";
    }
    
    return $errors;
}

/**
 * 크기 문자열을 바이트로 변환
 */
function parseSize($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

/**
 * 파일 업로드 설정 확인
 */
function getFileUploadSettings() {
    return [
        'max_file_size' => MAX_FILE_SIZE,
        'max_file_size_mb' => round(MAX_FILE_SIZE / (1024 * 1024), 2),
        'allowed_extensions' => ALLOWED_FILE_EXTENSIONS,
        'allowed_mime_types' => ALLOWED_MIME_TYPES,
        'upload_dir' => BUSINESS_LICENSE_DIR,
        'php_upload_max_filesize' => ini_get('upload_max_filesize'),
        'php_post_max_size' => ini_get('post_max_size'),
        'php_max_file_uploads' => ini_get('max_file_uploads')
    ];
}

/**
 * 관리자 비밀번호 변경 함수
 */
function changeAdminPassword($newPassword) {
    try {
        // 새 비밀번호 해시 생성
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // config.php 파일 읽기
        $configFile = __FILE__;
        $configContent = file_get_contents($configFile);
        
        if ($configContent === false) {
            throw new Exception('config.php 파일을 읽을 수 없습니다.');
        }
        
        // 기존 해시값 찾아서 교체
        $pattern = "/define\('ADMIN_PASSWORD_HASH',\s*'[^']*'\);/";
        $replacement = "define('ADMIN_PASSWORD_HASH', '{$newHash}');";
        
        $newContent = preg_replace($pattern, $replacement, $configContent);
        
        if ($newContent === null || $newContent === $configContent) {
            throw new Exception('비밀번호 해시 교체에 실패했습니다.');
        }
        
        // 백업 파일 생성
        $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($configFile, $backupFile)) {
            throw new Exception('백업 파일 생성에 실패했습니다.');
        }
        
        // 새 내용으로 파일 저장
        if (file_put_contents($configFile, $newContent, LOCK_EX) === false) {
            throw new Exception('config.php 파일 저장에 실패했습니다.');
        }
        
        // 변경 로그 기록
        error_log("Admin password changed successfully from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        return [
            'success' => true,
            'message' => '비밀번호가 성공적으로 변경되었습니다.',
            'backup_file' => $backupFile
        ];
        
    } catch (Exception $e) {
        error_log("Admin password change failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '비밀번호 변경 중 오류가 발생했습니다: ' . $e->getMessage()
        ];
    }
}

/**
 * 현재 비밀번호 검증 함수
 */
function verifyCurrentAdminPassword($password) {
    return password_verify($password, ADMIN_PASSWORD_HASH);
}

/**
 * 비밀번호 강도 검증 함수 (간소화된 요구사항)
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    // 최소 길이 확인
    if (strlen($password) < 6) {
        $errors[] = '비밀번호는 최소 6자 이상이어야 합니다.';
    }
    
    // 최대 길이 확인
    if (strlen($password) > 128) {
        $errors[] = '비밀번호는 128자를 초과할 수 없습니다.';
    }
    
    // 숫자 포함 확인
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = '비밀번호에 숫자가 포함되어야 합니다.';
    }
    
    // 특수문자 포함 확인
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = '비밀번호에 특수문자가 포함되어야 합니다.';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'strength' => calculatePasswordStrength($password)
    ];
}

/**
 * 비밀번호 강도 계산 함수 (간소화된 기준)
 */
function calculatePasswordStrength($password) {
    $score = 0;
    $length = strlen($password);
    
    // 길이 점수
    if ($length >= 6) $score += 1;
    if ($length >= 8) $score += 1;
    if ($length >= 12) $score += 1;
    
    // 문자 종류 점수
    if (preg_match('/[a-z]/', $password)) $score += 1;
    if (preg_match('/[A-Z]/', $password)) $score += 1;
    if (preg_match('/[0-9]/', $password)) $score += 1;  // 필수
    if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $score += 1;  // 필수
    
    // 복잡성 보너스 점수
    if (preg_match('/[a-zA-Z].*[0-9]|[0-9].*[a-zA-Z]/', $password)) $score += 1;
    
    // 강도 레벨 반환 (더 관대한 기준)
    if ($score <= 2) return 'weak';
    if ($score <= 4) return 'medium';
    if ($score <= 6) return 'strong';
    return 'very_strong';
}

/**
 * 설정 유효성 검사 실행
 */
function checkConfiguration() {
    $errors = validateEnvironment();
    
    if (!empty($errors)) {
        error_log("설정 오류 발견: " . implode(', ', $errors));
        
        // 개발 환경에서는 오류 표시
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;">';
            echo '<h3>설정 오류</h3>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        return false;
    }
    
    return true;
}

// 자동 설정 검사 (개발 환경에서만)
if (isset($_GET['check_config'])) {
    checkConfiguration();
    
    // 파일 업로드 설정 표시
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin: 10px; border-radius: 5px;">';
        echo '<h3>파일 업로드 설정</h3>';
        $settings = getFileUploadSettings();
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                echo '<p><strong>' . $key . ':</strong> ' . implode(', ', $value) . '</p>';
            } else {
                echo '<p><strong>' . $key . ':</strong> ' . $value . '</p>';
            }
        }
        echo '</div>';
    }
}
?>