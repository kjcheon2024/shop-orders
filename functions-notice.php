<?php
/**
 * functions-notice.php
 * 공지사항 기능 전용 함수 모음
 * 
 * 주요 기능:
 * - 관리자: 공지 생성/수정/삭제/목록조회
 * - 사용자: 미확인 공지 조회, 읽음 처리
 */

// ========================================
// 관리자용 함수
// ========================================

/**
 * 공지사항 목록 조회 (관리자용)
 * @return array 공지 목록과 성공 여부
 */
function getNoticeList() {
    try {
        $pdo = getDBConnection();
        
        // 만료일시가 경과된 공지들을 자동으로 비활성화
        $stmt = $pdo->prepare("
            UPDATE notices 
            SET is_active = 0, updated_at = NOW()
            WHERE expires_at IS NOT NULL 
              AND expires_at != '0000-00-00 00:00:00'
              AND expires_at > '1900-01-01 00:00:00'
              AND expires_at <= NOW() 
              AND is_active = 1
        ");
        $stmt->execute();
        $autoDeactivatedCount = $stmt->rowCount();
        
        // 자동 비활성화된 공지가 있으면 로그 기록
        if ($autoDeactivatedCount > 0) {
            error_log("자동 비활성화된 만료 공지 수: {$autoDeactivatedCount}개");
        }
        
        // v_active_notices 뷰 사용 (활성/비활성 모두 조회)
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.notice_type,
                n.title,
                n.message,
                n.is_active,
                n.priority,
                n.created_by,
                n.created_at,
                n.updated_at,
                n.expires_at,
                GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ') AS target_companies,
                COUNT(DISTINCT nt.company_id) AS target_count,
                COUNT(DISTINCT nr.id) AS read_count
            FROM notices n
            LEFT JOIN notice_targets nt ON n.id = nt.notice_id
            LEFT JOIN companies c ON nt.company_id = c.id
            LEFT JOIN notice_reads nr ON n.id = nr.notice_id
            GROUP BY n.id, n.notice_type, n.title, n.message, n.is_active, n.priority,
                     n.created_by, n.created_at, n.updated_at, n.expires_at
            ORDER BY n.is_active DESC, n.priority DESC, n.created_at DESC
        ");
        
        $stmt->execute();
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'notices' => $notices,
            'total' => count($notices)
        ];
        
    } catch (PDOException $e) {
        error_log("공지 목록 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '공지 목록을 불러오는 중 오류가 발생했습니다.'
        ];
    }
}

/**
 * 공지사항 생성
 * @param string $noticeType 'global' 또는 'individual'
 * @param string $title 공지 제목
 * @param string $message 공지 내용
 * @param array $companyIds 대상 업체 ID 배열 (individual인 경우)
 * @param int $priority 우선순위 (0-9, 높을수록 먼저 표시)
 * @param string $expiresAt 만료일시 (NULL이면 무기한)
 * @return array 성공 여부와 메시지
 */
function createNotice($noticeType, $title, $message, $companyIds = [], $priority = 0, $expiresAt = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 입력 검증
        if (!in_array($noticeType, ['global', 'individual'])) {
            throw new Exception('올바르지 않은 공지 유형입니다.');
        }
        
        if (empty($message)) {
            throw new Exception('공지 내용을 입력해주세요.');
        }
        
        if ($noticeType === 'individual' && empty($companyIds)) {
            throw new Exception('대상 업체를 선택해주세요.');
        }
        
        // notices 테이블에 삽입
        $stmt = $pdo->prepare("
            INSERT INTO notices (notice_type, title, message, is_active, priority, created_by, expires_at)
            VALUES (:notice_type, :title, :message, 1, :priority, 'admin', :expires_at)
        ");
        
        $stmt->execute([
            ':notice_type' => $noticeType,
            ':title' => $title,
            ':message' => $message,
            ':priority' => $priority,
            ':expires_at' => $expiresAt
        ]);
        
        $noticeId = $pdo->lastInsertId();
        
        // individual인 경우 notice_targets에 대상 업체 삽입
        if ($noticeType === 'individual' && !empty($companyIds)) {
            $stmt = $pdo->prepare("
                INSERT INTO notice_targets (notice_id, company_id)
                VALUES (:notice_id, :company_id)
            ");
            
            foreach ($companyIds as $companyId) {
                $stmt->execute([
                    ':notice_id' => $noticeId,
                    ':company_id' => $companyId
                ]);
            }
        }
        
        $pdo->commit();
        
        $typeText = $noticeType === 'global' ? '전체공지' : '개별메시지';
        return [
            'success' => true,
            'message' => $typeText . '가 등록되었습니다.',
            'notice_id' => $noticeId
        ];
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("공지 생성 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 공지사항 수정
 * @param int $noticeId 공지 ID
 * @param string $title 공지 제목
 * @param string $message 공지 내용
 * @param array $companyIds 대상 업체 ID 배열 (individual인 경우)
 * @param int $priority 우선순위
 * @param string $expiresAt 만료일시
 * @return array 성공 여부와 메시지
 */
function updateNotice($noticeId, $title, $message, $companyIds = [], $priority = 0, $expiresAt = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 공지 존재 확인
        $stmt = $pdo->prepare("SELECT notice_type FROM notices WHERE id = :id");
        $stmt->execute([':id' => $noticeId]);
        $notice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notice) {
            throw new Exception('존재하지 않는 공지입니다.');
        }
        
        // notices 테이블 업데이트
        $stmt = $pdo->prepare("
            UPDATE notices 
            SET title = :title,
                message = :message,
                priority = :priority,
                expires_at = :expires_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $noticeId,
            ':title' => $title,
            ':message' => $message,
            ':priority' => $priority,
            ':expires_at' => $expiresAt
        ]);
        
        // individual인 경우 대상 업체 업데이트
        if ($notice['notice_type'] === 'individual') {
            // 기존 대상 삭제
            $stmt = $pdo->prepare("DELETE FROM notice_targets WHERE notice_id = :notice_id");
            $stmt->execute([':notice_id' => $noticeId]);
            
            // 새 대상 삽입
            if (!empty($companyIds)) {
                $stmt = $pdo->prepare("
                    INSERT INTO notice_targets (notice_id, company_id)
                    VALUES (:notice_id, :company_id)
                ");
                
                foreach ($companyIds as $companyId) {
                    $stmt->execute([
                        ':notice_id' => $noticeId,
                        ':company_id' => $companyId
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => '공지가 수정되었습니다.'
        ];
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("공지 수정 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 공지사항 삭제
 * @param int $noticeId 공지 ID
 * @return array 성공 여부와 메시지
 */
function deleteNotice($noticeId) {
    try {
        $pdo = getDBConnection();
        
        // CASCADE로 notice_targets, notice_reads도 자동 삭제됨
        $stmt = $pdo->prepare("DELETE FROM notices WHERE id = :id");
        $stmt->execute([':id' => $noticeId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('존재하지 않는 공지입니다.');
        }
        
        return [
            'success' => true,
            'message' => '공지가 삭제되었습니다.'
        ];
        
    } catch (Exception $e) {
        error_log("공지 삭제 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 공지사항 활성화/비활성화 토글
 * @param int $noticeId 공지 ID
 * @param bool $isActive 활성화 여부
 * @return array 성공 여부와 메시지
 */
function toggleNoticeStatus($noticeId, $isActive) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            UPDATE notices 
            SET is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $noticeId,
            ':is_active' => $isActive ? 1 : 0
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('존재하지 않는 공지입니다.');
        }
        
        $statusText = $isActive ? '활성화' : '비활성화';
        return [
            'success' => true,
            'message' => '공지가 ' . $statusText . '되었습니다.'
        ];
        
    } catch (Exception $e) {
        error_log("공지 상태 변경 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 공지 상세 조회 (수정용)
 * @param int $noticeId 공지 ID
 * @return array 공지 정보
 */
function getNoticeDetail($noticeId) {
    try {
        $pdo = getDBConnection();
        
        // 공지 기본 정보
        $stmt = $pdo->prepare("
            SELECT * FROM notices WHERE id = :id
        ");
        $stmt->execute([':id' => $noticeId]);
        $notice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notice) {
            throw new Exception('존재하지 않는 공지입니다.');
        }
        
        // 대상 업체 ID 목록 조회 (individual인 경우)
        if ($notice['notice_type'] === 'individual') {
            $stmt = $pdo->prepare("
                SELECT company_id FROM notice_targets WHERE notice_id = :notice_id
            ");
            $stmt->execute([':notice_id' => $noticeId]);
            $notice['target_company_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        return [
            'success' => true,
            'notice' => $notice
        ];
        
    } catch (Exception $e) {
        error_log("공지 상세 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// ========================================
// 사용자용 함수
// ========================================

/**
 * 미확인 공지 조회 (사용자용)
 * @param int $companyId 업체 ID
 * @return array 미확인 공지 목록
 */
function getUnreadNotices($companyId) {
    try {
        $pdo = getDBConnection();
        
        // 전체공지 + 해당 업체 대상 개별메시지 중 읽지 않은 것만
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.notice_type,
                n.title,
                n.message,
                n.priority,
                n.created_at
            FROM notices n
            LEFT JOIN notice_targets nt ON n.id = nt.notice_id
            LEFT JOIN notice_reads nr ON n.id = nr.notice_id AND nr.company_id = :company_id
            WHERE n.is_active = 1
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
              AND (
                  n.notice_type = 'global' 
                  OR (n.notice_type = 'individual' AND nt.company_id = :company_id)
              )
              AND nr.id IS NULL
            ORDER BY n.priority DESC, n.created_at DESC
        ");
        
        $stmt->execute([':company_id' => $companyId]);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'notices' => $notices,
            'count' => count($notices)
        ];
        
    } catch (PDOException $e) {
        error_log("미확인 공지 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '공지를 불러오는 중 오류가 발생했습니다.',
            'notices' => []
        ];
    }
}

/**
 * 공지 읽음 처리
 * @param int $noticeId 공지 ID
 * @param int $companyId 업체 ID
 * @return array 성공 여부
 */
function markNoticeAsRead($noticeId, $companyId) {
    try {
        $pdo = getDBConnection();
        
        // INSERT IGNORE를 사용하여 중복 방지
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO notice_reads (notice_id, company_id)
            VALUES (:notice_id, :company_id)
        ");
        
        $stmt->execute([
            ':notice_id' => $noticeId,
            ':company_id' => $companyId
        ]);
        
        return [
            'success' => true,
            'message' => '공지 확인 처리되었습니다.'
        ];
        
    } catch (PDOException $e) {
        error_log("공지 읽음 처리 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '공지 확인 처리 중 오류가 발생했습니다.'
        ];
    }
}

// ========================================
// 보조 함수
// ========================================

/**
 * 활성 업체 목록 조회 (체크박스용)
 * @return array 업체 목록
 */
function getActiveCompaniesForNotice() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, company_name, item_group, delivery_day
            FROM companies
            WHERE active = 1
            ORDER BY company_name ASC
        ");
        
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'companies' => $companies
        ];
        
    } catch (PDOException $e) {
        error_log("업체 목록 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '업체 목록을 불러오는 중 오류가 발생했습니다.',
            'companies' => []
        ];
    }
}

/**
 * 공지 통계 조회 (대시보드용)
 * @return array 통계 정보
 */
function getNoticeStatistics() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_notices,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_notices,
                SUM(CASE WHEN notice_type = 'global' THEN 1 ELSE 0 END) as global_notices,
                SUM(CASE WHEN notice_type = 'individual' THEN 1 ELSE 0 END) as individual_notices
            FROM notices
        ");
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'stats' => $stats
        ];
        
    } catch (PDOException $e) {
        error_log("공지 통계 조회 오류: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '통계 조회 중 오류가 발생했습니다.'
        ];
    }
}

?>