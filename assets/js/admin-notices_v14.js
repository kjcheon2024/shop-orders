/**
 * admin-notices.js - 관리자 공지관리 기능
 */

// 전역 변수
let allCompanies = [];

// 공지관리 탭 초기화
function initializeNoticesTab() {
    loadActiveCompanies();
    loadNoticesList();
    
    // 초기 상태 설정
    toggleCompanySelection();
}

// 활성 업체 목록 로드
function loadActiveCompanies() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getActiveCompaniesForNotice'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allCompanies = data.companies;
            displayCompanyCheckboxes(data.companies);
        }
    })
    .catch(error => {
        console.error('업체 목록 조회 오류:', error);
    });
}

// 업체 체크박스 표시
function displayCompanyCheckboxes(companies) {
    const container = document.getElementById('companyCheckboxes');
    const editContainer = document.getElementById('editCompanyCheckboxes');
    
    let html = '';
    
    if (companies.length === 0) {
        html = '<div class="no-data">등록된 업체가 없습니다.</div>';
    } else {
        companies.forEach(company => {
            html += `
                <div class="assignment-item" style="margin-bottom: 8px;">
                    <input type="checkbox" id="company_${company.id}" value="${company.id}">
                    <label for="company_${company.id}">
                        ${escapeHtml(company.company_name)}
                    </label>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
    editContainer.innerHTML = html.replace(/company_/g, 'edit_company_');
}

// 공지 유형 변경 시 업체 선택 영역 토글
function toggleCompanySelection() {
    const noticeType = document.getElementById('noticeType').value;
    const companySection = document.getElementById('companySelectionSection');
    const titleRow = document.getElementById('noticeTitleRow');
    const messageRow = document.getElementById('noticeMessageRow');
    const expiresRow = document.getElementById('noticeExpiresRow');
    
    console.log('공지 유형 변경:', noticeType);
    console.log('companySection:', companySection);
    console.log('titleRow:', titleRow);
    console.log('messageRow:', messageRow);
    console.log('expiresRow:', expiresRow);
    
    if (noticeType === 'individual') {
        // 개별메시지 선택 시: 개별메시지 섹션 표시, 전체공지 필드들 숨김
        if (companySection) companySection.style.display = 'block';
        if (titleRow) titleRow.style.display = 'none';
        if (messageRow) messageRow.style.display = 'none';
        if (expiresRow) expiresRow.style.display = 'none';
        console.log('개별메시지 선택됨 - 업체 선택 섹션 표시, 전체공지 필드들 숨김');
    } else {
        // 전체공지 선택 시: 개별메시지 섹션 숨김, 전체공지 필드들 표시
        if (companySection) companySection.style.display = 'none';
        if (titleRow) titleRow.style.display = 'block';
        if (messageRow) messageRow.style.display = 'block';
        if (expiresRow) expiresRow.style.display = 'block';
        console.log('전체공지 선택됨 - 전체공지 필드들 표시, 개별메시지 섹션 숨김');
    }
}

// 전체 선택/해제
function selectAllCompanies() {
    document.querySelectorAll('#companyCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllCompanies() {
    document.querySelectorAll('#companyCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}

function selectAllEditCompanies() {
    document.querySelectorAll('#editCompanyCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllEditCompanies() {
    document.querySelectorAll('#editCompanyCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}

// 공지 목록 로드
function loadNoticesList() {
    const container = document.getElementById('noticesList');
    container.innerHTML = '<div class="loading-message">공지 목록을 불러오는 중...</div>';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getNoticeList'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayNoticesList(data.notices);
        } else {
            container.innerHTML = '<div class="error-message">' + escapeHtml(data.message) + '</div>';
        }
    })
    .catch(error => {
        console.error('공지 목록 조회 오류:', error);
        container.innerHTML = '<div class="error-message">공지 목록을 불러오는 중 오류가 발생했습니다.</div>';
    });
}

// 공지 목록 표시
function displayNoticesList(notices) {
    const container = document.getElementById('noticesList');
    
    if (notices.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 공지가 없습니다.</div>';
        return;
    }
    
    let html = '';
    notices.forEach(notice => {
        const isActive = notice.is_active == 1;
        const typeText = notice.notice_type === 'global' ? '전체공지' : '개별메시지';
        const typeBadge = notice.notice_type === 'global' ? 'badge-approved' : 'badge-pending';
        
        html += `
            <div class="category-item ${!isActive ? 'company-blocked' : ''}">
                <div>
                    <div class="category-name">
                        <span class="status-badge ${typeBadge}">${typeText}</span>
                        ${notice.title ? escapeHtml(notice.title) : '(제목없음)'}
                        ${!isActive ? '<span class="status-badge badge-blocked">비활성</span>' : ''}
                    </div>
                    <div class="category-description" style="margin-top: 8px;">
                        ${escapeHtml(notice.message.substring(0, 100))}${notice.message.length > 100 ? '...' : ''}
                    </div>
                    ${notice.notice_type === 'individual' ? `
                        <div class="category-description" style="margin-top: 4px; color: #007bff;">
                            대상: ${escapeHtml(notice.target_companies || '없음')} (${notice.target_count}개 업체)
                        </div>
                    ` : ''}
                    <div class="category-description" style="margin-top: 4px;">
                        작성: ${notice.created_at} | 우선순위: ${notice.priority}
						<br class="mobile-break">
						확인: ${notice.read_count}명
                        ${notice.expires_at ? ' | 만료: ' + notice.expires_at : ''}
                    </div>
                </div>
                <div class="category-actions">
                    <label style="display: flex; align-items: center; margin-right: 10px; cursor: pointer;">
                        <input type="checkbox" ${isActive ? 'checked' : ''} 
                               onchange="toggleNoticeStatus(${notice.id}, this.checked)"
                               style="margin-right: 5px;">
                        활성
                    </label>
                    <button class="btn btn-small btn-edit" onclick="editNotice(${notice.id})">수정</button>
                    <button class="btn btn-small btn-reject" onclick="deleteNotice(${notice.id}, '${escapeForJs(notice.title || notice.message.substring(0, 20))}')">삭제</button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// 새 공지 등록
function createNewNotice() {
    const noticeType = document.getElementById('noticeType').value;
    const title = document.getElementById('noticeTitle').value.trim();
    const individualTitle = document.getElementById('individualNoticeTitle').value.trim();
    const message = document.getElementById('noticeMessage').value.trim();
    const individualMessage = document.getElementById('individualNoticeMessage').value.trim();
    const priority = document.getElementById('noticePriority').value;
    const expires = document.getElementById('noticeExpires').value;
    const individualExpires = document.getElementById('individualNoticeExpires').value;
    
    let finalMessage = '';
    let finalExpires = '';
    
    if (noticeType === 'individual') {
        finalMessage = individualMessage;
        finalExpires = individualExpires;
    } else {
        finalMessage = message;
        finalExpires = expires;
    }
    
    if (!finalMessage) {
        alert('공지 내용을 입력해주세요.');
        return;
    }
    
    let companyIds = [];
    let finalTitle = '';
    
    if (noticeType === 'individual') {
        const checkboxes = document.querySelectorAll('#companyCheckboxes input[type="checkbox"]:checked');
        companyIds = Array.from(checkboxes).map(cb => cb.value);
        
        if (companyIds.length === 0) {
            alert('대상 업체를 선택해주세요.');
            return;
        }
        
        finalTitle = individualTitle;
    } else {
        finalTitle = title;
    }
    
    if (!confirm('공지를 등록하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=createNotice' +
              '&noticeType=' + encodeURIComponent(noticeType) +
              '&title=' + encodeURIComponent(finalTitle) +
              '&message=' + encodeURIComponent(finalMessage) +
              '&priority=' + encodeURIComponent(priority) +
              '&expires=' + encodeURIComponent(finalExpires) +
              '&companyIds=' + encodeURIComponent(companyIds.join(','))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            
            // 폼 초기화
            document.getElementById('noticeTitle').value = '';
            document.getElementById('individualNoticeTitle').value = '';
            document.getElementById('noticeMessage').value = '';
            document.getElementById('individualNoticeMessage').value = '';
            document.getElementById('noticePriority').value = '0';
            document.getElementById('noticeExpires').value = '';
            document.getElementById('individualNoticeExpires').value = '';
            deselectAllCompanies();
            
            // 목록 새로고침
            loadNoticesList();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('공지 등록 오류:', error);
        alert('공지 등록 중 오류가 발생했습니다.');
    });
}

// 공지 수정 모달 열기
function editNotice(noticeId) {
    console.log('수정 버튼 클릭됨, 공지 ID:', noticeId);
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getNoticeDetail&noticeId=' + noticeId
    })
    .then(response => response.json())
    .then(data => {
        console.log('공지 상세 조회 응답:', data);
        if (data.success) {
            const notice = data.notice;
            console.log('공지 데이터:', notice);
            
            document.getElementById('editNoticeId').value = notice.id;
            document.getElementById('editNoticeType').value = notice.notice_type;
            document.getElementById('editNoticeTitle').value = notice.title || '';
            document.getElementById('editNoticeMessage').value = notice.message;
            document.getElementById('editNoticePriority').value = notice.priority;
            document.getElementById('editNoticeExpires').value = notice.expires_at ? notice.expires_at.substring(0, 16) : '';
            
            // 개별메시지인 경우 대상 업체 체크
            if (notice.notice_type === 'individual') {
                document.getElementById('editCompanySelectionSection').style.display = 'block';
                document.getElementById('editNoticeTitleGroup').style.display = 'none';
                document.getElementById('editNoticeMessageGroup').style.display = 'none';
                document.getElementById('editNoticeExpiresGroup').style.display = 'none';
                
                // 개별메시지 필드들 설정
                document.getElementById('editIndividualNoticeTitle').value = notice.title || '';
                document.getElementById('editIndividualNoticeMessage').value = notice.message || '';
                document.getElementById('editIndividualNoticeExpires').value = notice.expires_at ? notice.expires_at.substring(0, 16) : '';
                
                // 모든 체크박스 초기화
                deselectAllEditCompanies();
                
                // 기존 대상 업체 체크
                if (notice.target_company_ids) {
                    notice.target_company_ids.forEach(companyId => {
                        const checkbox = document.getElementById('edit_company_' + companyId);
                        if (checkbox) checkbox.checked = true;
                    });
                }
            } else {
                document.getElementById('editCompanySelectionSection').style.display = 'none';
                document.getElementById('editNoticeTitleGroup').style.display = 'block';
                document.getElementById('editNoticeMessageGroup').style.display = 'block';
                document.getElementById('editNoticeExpiresGroup').style.display = 'block';
            }
            
            document.getElementById('noticeEditModal').style.display = 'block';
            console.log('수정 모달 열림 완료');
        } else {
            console.error('공지 상세 조회 실패:', data.message);
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('공지 상세 조회 오류:', error);
        alert('공지 정보를 불러오는 중 오류가 발생했습니다.');
    });
}

// 공지 수정 모달 닫기
function closeNoticeEditModal() {
    document.getElementById('noticeEditModal').style.display = 'none';
}

// 공지 수정 저장
function saveNoticeEdit() {
    const noticeId = document.getElementById('editNoticeId').value;
    const noticeType = document.getElementById('editNoticeType').value;
    const title = document.getElementById('editNoticeTitle').value.trim();
    const individualTitle = document.getElementById('editIndividualNoticeTitle').value.trim();
    const message = document.getElementById('editNoticeMessage').value.trim();
    const individualMessage = document.getElementById('editIndividualNoticeMessage').value.trim();
    const priority = document.getElementById('editNoticePriority').value;
    const expires = document.getElementById('editNoticeExpires').value;
    const individualExpires = document.getElementById('editIndividualNoticeExpires').value;
    
    let finalMessage = '';
    let finalExpires = '';
    
    if (noticeType === 'individual') {
        finalMessage = individualMessage;
        finalExpires = individualExpires;
    } else {
        finalMessage = message;
        finalExpires = expires;
    }
    
    if (!finalMessage) {
        alert('공지 내용을 입력해주세요.');
        return;
    }
    
    let companyIds = [];
    let finalTitle = '';
    
    if (noticeType === 'individual') {
        const checkboxes = document.querySelectorAll('#editCompanyCheckboxes input[type="checkbox"]:checked');
        companyIds = Array.from(checkboxes).map(cb => cb.value);
        
        if (companyIds.length === 0) {
            alert('대상 업체를 선택해주세요.');
            return;
        }
        
        finalTitle = individualTitle;
    } else {
        finalTitle = title;
    }
    
    if (!confirm('공지를 수정하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateNotice' +
              '&noticeId=' + noticeId +
              '&title=' + encodeURIComponent(finalTitle) +
              '&message=' + encodeURIComponent(finalMessage) +
              '&priority=' + encodeURIComponent(priority) +
              '&expires=' + encodeURIComponent(finalExpires) +
              '&companyIds=' + encodeURIComponent(companyIds.join(','))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeNoticeEditModal();
            loadNoticesList();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('공지 수정 오류:', error);
        alert('공지 수정 중 오류가 발생했습니다.');
    });
}

// 공지 삭제
function deleteNotice(noticeId, noticeTitle) {
    if (!confirm('"' + noticeTitle + '"\n\n이 공지를 삭제하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=deleteNotice&noticeId=' + noticeId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadNoticesList();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('공지 삭제 오류:', error);
        alert('공지 삭제 중 오류가 발생했습니다.');
    });
}

// 공지 활성화/비활성화 토글
function toggleNoticeStatus(noticeId, isActive) {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggleNoticeStatus&noticeId=' + noticeId + '&isActive=' + (isActive ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 조용히 처리 (알림 없음)
            loadNoticesList();
        } else {
            alert(data.message);
            loadNoticesList(); // 실패 시에도 목록 새로고침하여 원래 상태로
        }
    })
    .catch(error => {
        console.error('공지 상태 변경 오류:', error);
        loadNoticesList();
    });
}

// 공지관리 탭 활성화 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    const originalShowTab = window.showTab;
    window.showTab = function(tabName) {
        originalShowTab.call(this, tabName);
        
        if (tabName === 'notices') {
            setTimeout(() => {
                initializeNoticesTab();
            }, 100);
        }
    };
});