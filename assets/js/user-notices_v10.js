/**
 * user-notices.js - 사용자 공지 표시 및 관리
 */

// 전역 변수
let currentGlobalNotices = [];
let currentNoticeIndex = 0;

/**
 * 로그인 후 읽지 않은 전체공지 조회 및 표시
 * auth_v7.js의 로그인 성공 후 호출됨
 */
function checkAndShowGlobalNotices() {
    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getUnreadGlobalNotices'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notices && data.notices.length > 0) {
            currentGlobalNotices = data.notices;
            currentNoticeIndex = 0;
            showGlobalNoticeModal();
        }
    })
    .catch(error => {
        console.error('전체공지 조회 오류:', error);
    });
}

/**
 * 전체공지 모달 표시
 */
function showGlobalNoticeModal() {
    if (currentNoticeIndex >= currentGlobalNotices.length) {
        // 모든 공지를 표시했으면 종료
        return;
    }
    
    const notice = currentGlobalNotices[currentNoticeIndex];
    const modal = document.getElementById('globalNoticeModal');
    const body = document.getElementById('globalNoticeBody');
    const checkbox = document.getElementById('dontShowAgainCheckbox');
    
    // 공지 내용 표시
    let html = '';
    
    // 제목이 있으면 표시
    if (notice.title) {
        html += `<h4 class="notice-title">${escapeHtml(notice.title)}</h4>`;
    }
    
    // 내용 표시 (줄바꿈 처리)
    html += `<div class="notice-message">${escapeHtml(notice.message).replace(/\n/g, '<br>')}</div>`;
    
    // 작성일시 표시 (날짜만)
    const dateOnly = notice.created_at.split(' ')[0];
    html += `<div class="notice-date">작성일: ${dateOnly}</div>`;
    
    body.innerHTML = html;
    
    // 체크박스 초기화
    checkbox.checked = false;
    
    // 현재 공지 ID 저장
    modal.dataset.noticeId = notice.id;
    
    // 모달 표시
    modal.classList.remove('hidden');
    
    // ESC 키로 닫기 방지 (확인 버튼만 사용하도록)
    document.addEventListener('keydown', preventEscClose);
}

/**
 * ESC 키로 모달 닫기 방지
 */
function preventEscClose(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('globalNoticeModal');
        if (!modal.classList.contains('hidden')) {
            e.preventDefault();
        }
    }
}

/**
 * 전체공지 모달 닫기 (X 버튼)
 * 읽음 처리 하지 않고 모달만 닫기
 */
function closeGlobalNoticeModal() {
    const modal = document.getElementById('globalNoticeModal');
    modal.classList.remove('hidden');
    modal.classList.add('hidden');
    
    document.removeEventListener('keydown', preventEscClose);
    
    // 다음 로그인 시 다시 표시되도록 읽음 처리 안 함
}

/**
 * 전체공지 확인 버튼 클릭
 */
function confirmGlobalNotice() {
    const modal = document.getElementById('globalNoticeModal');
    const checkbox = document.getElementById('dontShowAgainCheckbox');
    const noticeId = parseInt(modal.dataset.noticeId);
    
    if (checkbox.checked) {
        // "다시 보지 않기" 체크된 경우 - 읽음 처리
        markNoticeAsRead(noticeId, () => {
            // 읽음 처리 완료 후 다음 공지 표시
            showNextNotice();
        });
    } else {
        // 체크 안 된 경우 - 읽음 처리 없이 다음 공지 표시
        showNextNotice();
    }
}

/**
 * 다음 공지 표시
 */
function showNextNotice() {
    currentNoticeIndex++;
    
    if (currentNoticeIndex < currentGlobalNotices.length) {
        // 다음 공지가 있으면 표시
        showGlobalNoticeModal();
    } else {
        // 모든 공지를 확인했으면 모달 닫기
        const modal = document.getElementById('globalNoticeModal');
        modal.classList.add('hidden');
        document.removeEventListener('keydown', preventEscClose);
    }
}

/**
 * 공지 읽음 처리
 */
function markNoticeAsRead(noticeId, callback) {
    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'markNoticeAsRead',
            noticeId: noticeId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('공지 읽음 처리 완료:', noticeId);
            if (callback) callback();
        } else {
            console.error('공지 읽음 처리 실패:', data.message);
            // 실패해도 다음 공지는 표시
            if (callback) callback();
        }
    })
    .catch(error => {
        console.error('공지 읽음 처리 오류:', error);
        // 오류가 나도 다음 공지는 표시
        if (callback) callback();
    });
}

// ========================================
// 개별메시지 배너 관련 함수들 (신규 추가)
// ========================================

/**
 * 주문 화면에서 개별메시지 조회 및 표시
 * order_v11.js에서 주문 화면 표시 시 호출됨
 */
function loadAndShowIndividualNotices(companyName) {
    if (!companyName) {
        console.error('업체명이 없습니다.');
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getIndividualNotices',
            companyName: companyName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notices && data.notices.length > 0) {
            displayIndividualNoticeBanners(data.notices);
        } else {
            // 공지가 없으면 배너 영역 숨김
            const bannerContainer = document.getElementById('individualNoticeBanner');
            if (bannerContainer) {
                bannerContainer.innerHTML = '';
            }
        }
    })
    .catch(error => {
        console.error('개별메시지 조회 오류:', error);
    });
}

/**
 * 개별메시지 배너 표시
 */
function displayIndividualNoticeBanners(notices) {
    const bannerContainer = document.getElementById('individualNoticeBanner');
    if (!bannerContainer) return;
    
    let html = '';
    
    notices.forEach(notice => {
        const bannerClass = notices.length > 1 ? 'individual-notice-banner multiple' : 'individual-notice-banner';
        
        html += `
            <div class="${bannerClass}">
                <div class="banner-header">
                    <div class="banner-title">알림</div>
                    ${notice.priority >= 5 ? `<div class="banner-priority">중요</div>` : ''}
                </div>
                <div class="banner-message">${escapeHtml(notice.message).replace(/\n/g, '<br>')}</div>
                <div class="banner-date">작성: ${notice.created_at.split(' ')[0]}</div>
            </div>
        `;
    });
    
    bannerContainer.innerHTML = html;
}

/**
 * HTML 이스케이프 (XSS 방지)
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 전역 스코프에 함수 노출
window.checkAndShowGlobalNotices = checkAndShowGlobalNotices;
window.closeGlobalNoticeModal = closeGlobalNoticeModal;
window.confirmGlobalNotice = confirmGlobalNotice;
window.loadAndShowIndividualNotices = loadAndShowIndividualNotices;