/**
 * admin-companies.js - 관리자 업체목록 관리 기능
 */

// 전역 변수: 원본 업체 데이터 저장
let allCompaniesData = null;

// 업체목록 탭 초기화
function initializeCompaniesTab() {
    loadAllCompaniesWithStatus();
}

// 모든 업체 정보 로드
function loadAllCompaniesWithStatus() {
    const container = document.getElementById('companiesListContainer');
    container.innerHTML = '<div class="loading-message">업체 정보를 불러오는 중...</div>';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getAllCompaniesWithStatus'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 원본 데이터 저장
            allCompaniesData = data;
            displayCompaniesWithStatus(data);
        } else {
            container.innerHTML = '<div class="error-message">' + escapeHtml(data.message) + '</div>';
        }
    })
    .catch(error => {
        console.error('업체 목록 조회 오류:', error);
        container.innerHTML = '<div class="error-message">업체 목록을 불러오는 중 오류가 발생했습니다.</div>';
    });
}

// 업체 필터링 함수
function filterCompanies() {
    if (!allCompaniesData) return;
    
    const searchTerm = document.getElementById('companySearch').value.toLowerCase().trim();
    const statusFilter = document.getElementById('statusFilter').value;
    const groupFilter = document.getElementById('groupFilter').value;
    
    let filteredCompanies = allCompaniesData.companies.filter(company => {
        // 업체명 검색
        const matchesSearch = !searchTerm || 
            company.company_name.toLowerCase().includes(searchTerm) ||
            (company.contact_person && company.contact_person.toLowerCase().includes(searchTerm));
        
        // 상태 필터
        const matchesStatus = !statusFilter || 
            (statusFilter === 'active' && company.order_blocked == 0) ||
            (statusFilter === 'blocked' && company.order_blocked == 1);
        
        // 그룹 필터
        const matchesGroup = !groupFilter || company.item_group === groupFilter;
        
        return matchesSearch && matchesStatus && matchesGroup;
    });
    
    // 필터링된 데이터로 표시
    const filteredData = {
        ...allCompaniesData,
        companies: filteredCompanies
    };
    
    displayCompaniesWithStatus(filteredData);
}

// 필터 초기화
function clearFilters() {
    document.getElementById('companySearch').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('groupFilter').value = '';
    
    // 원본 데이터로 다시 표시
    if (allCompaniesData) {
        displayCompaniesWithStatus(allCompaniesData);
    }
}

// 업체 목록 표시
function displayCompaniesWithStatus(data) {
    const container = document.getElementById('companiesListContainer');
    const summarySection = document.getElementById('companiesSummary');
    
    // 요약 정보 업데이트
    document.getElementById('totalCompaniesCount').textContent = data.total_companies + '개';
    document.getElementById('blockedCompaniesCount').textContent = data.blocked_companies + '개';
    document.getElementById('filteredCompaniesCount').textContent = data.companies.length + '개';
    
    summarySection.style.display = 'flex';
    
    if (data.companies.length === 0) {
        // 필터가 적용된 상태인지 확인
        const hasActiveFilters = document.getElementById('companySearch').value.trim() !== '' ||
                                document.getElementById('statusFilter').value !== '' ||
                                document.getElementById('groupFilter').value !== '';
        
        if (hasActiveFilters) {
            container.innerHTML = '<div class="no-data">검색 조건에 맞는 업체가 없습니다.</div>';
        } else {
            container.innerHTML = '<div class="no-data">등록된 업체가 없습니다.</div>';
        }
        return;
    }
    
    let html = '';
    data.companies.forEach(company => {
        const isBlocked = company.order_blocked == 1;
        const blockClass = isBlocked ? 'company-blocked' : '';
        
        html += '<div class="company-item ' + blockClass + '">' +
                    '<div class="company-main-info">' +
                        '<div class="company-header">' +
                            '<input type="checkbox" id="block_' + company.id + '" ' + 
                            (isBlocked ? 'checked' : '') + ' onchange="toggleCompanyBlock(' + company.id + ', this.checked)">' +
                            '<h3>' + escapeHtml(company.company_name) + '</h3>' +
                            (isBlocked ? '<span class="status-badge badge-blocked">차단됨</span>' : '<span class="status-badge badge-active">정상</span>') +
                        '</div>' +
                        '<div class="company-details">' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">등록일:</span>' +
                                '<span class="detail-value">' + formatDate(company.created_at) + '</span>' +
                            '</div>' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">담당자:</span>' +
                                '<span class="detail-value">' + escapeHtml(company.contact_person || '미입력') + '</span>' +
                            '</div>' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">전화번호:</span>' +
                                '<span class="detail-value">' + escapeHtml(company.phone_number || '미입력') + '</span>' +
                            '</div>' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">소속그룹:</span>' +
                                '<span class="detail-value">' + escapeHtml(company.item_group || '미설정') + '</span>' +
                            '</div>' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">할당품목:</span>' +
                                '<span class="detail-value">' + company.assigned_items_count + '개</span>' +
                            '</div>' +
                            '<div class="detail-item">' +
                                '<span class="detail-label">최근주문:</span>' +
                                '<span class="detail-value">' + 
                                (company.last_order_date ? formatDate(company.last_order_date) + ' (' + company.last_order_count + '건)' : '없음') +
                                '</span>' +
                            '</div>' +
                        '</div>';
        
        // 차단 사유 표시 (차단된 경우에만)
        if (isBlocked && company.block_reason) {
            html += '<div class="block-reason-section">' +
                        '<div class="block-reason-header">' +
                            '<span class="block-reason-label">차단 사유:</span>' +
                            '<button class="btn btn-small btn-edit" onclick="editBlockReason(' + company.id + ', \'' + 
                            escapeForJs(company.block_reason) + '\')">수정</button>' +
                        '</div>' +
                        '<div class="block-reason-text">' + escapeHtml(company.block_reason) + '</div>' +
                    '</div>';
        }
        
        // 업체 액션 버튼 추가
        html += '<div class="company-actions">' +
                    '<button class="btn btn-small btn-primary" onclick="editCompanyGroup(' + company.id + ', \'' + escapeForJs(company.company_name) + '\', \'' + escapeForJs(company.item_group || '') + '\')">그룹수정</button>' +
                '</div>';
        
        html += '</div></div>';
    });
    
    container.innerHTML = html;
}

// 업체 주문차단 토글
function toggleCompanyBlock(companyId, isBlocked) {
    if (isBlocked) {
        // 차단하는 경우 - 사유 입력 프롬프트
        const reason = prompt('주문차단 사유를 입력해주세요:', '');
        
        if (reason === null) {
            // 취소한 경우 체크박스 원래대로
            document.getElementById('block_' + companyId).checked = false;
            return;
        }
        
        if (reason.trim() === '') {
            showAlert('차단 사유를 입력해주세요.', 'error');
            document.getElementById('block_' + companyId).checked = false;
            return;
        }
        
        updateCompanyBlockStatus(companyId, true, reason.trim());
    } else {
        // 차단 해제하는 경우
        if (confirm('주문차단을 해제하시겠습니까?')) {
            updateCompanyBlockStatus(companyId, false, '');
        } else {
            // 취소한 경우 체크박스 원래대로
            document.getElementById('block_' + companyId).checked = true;
        }
    }
}

// 업체 차단 상태 업데이트
function updateCompanyBlockStatus(companyId, isBlocked, blockReason) {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggleCompanyOrderBlock&companyId=' + companyId + 
              '&isBlocked=' + isBlocked + 
              '&blockReason=' + encodeURIComponent(blockReason)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // 업체 목록 새로고침
            setTimeout(() => {
                loadAllCompaniesWithStatus();
            }, 1000);
        } else {
            showAlert(data.message, 'error');
            // 실패한 경우 체크박스 원래대로
            document.getElementById('block_' + companyId).checked = !isBlocked;
        }
    })
    .catch(error => {
        console.error('업체 차단 상태 업데이트 오류:', error);
        showAlert('차단 상태 업데이트 중 오류가 발생했습니다.', 'error');
        // 오류 발생 시 체크박스 원래대로
        document.getElementById('block_' + companyId).checked = !isBlocked;
    });
}

// 차단 사유 수정
function editBlockReason(companyId, currentReason) {
    document.getElementById('blockReasonCompanyId').value = companyId;
    document.getElementById('blockReasonText').value = currentReason;
    document.getElementById('blockReasonModal').style.display = 'block';
}

// 차단 사유 모달 닫기
function closeBlockReasonModal() {
    document.getElementById('blockReasonModal').style.display = 'none';
}

// 차단 사유 저장
function saveBlockReason() {
    const companyId = document.getElementById('blockReasonCompanyId').value;
    const blockReason = document.getElementById('blockReasonText').value.trim();
    
    if (!blockReason) {
        showAlert('차단 사유를 입력해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateBlockReason&companyId=' + companyId + 
              '&blockReason=' + encodeURIComponent(blockReason)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeBlockReasonModal();
            // 업체 목록 새로고침
            setTimeout(() => {
                loadAllCompaniesWithStatus();
            }, 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('차단 사유 업데이트 오류:', error);
        showAlert('차단 사유 업데이트 중 오류가 발생했습니다.', 'error');
    });
}

// 날짜 포맷팅 (간단한 버전)
function formatDate(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    } catch (error) {
        return dateString;
    }
}

// JavaScript용 이스케이프 함수
function escapeForJs(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\\/g, '\\\\');
}

// 업체목록 탭이 활성화될 때 호출
document.addEventListener('DOMContentLoaded', function() {
    // showTab 함수에 companies 탭 처리 추가
    const originalShowTab = window.showTab;
    window.showTab = function(tabName) {
        originalShowTab.call(this, tabName);
        
        if (tabName === 'companies') {
            setTimeout(() => {
                initializeCompaniesTab();
            }, 100);
        }
    };
    
    // 모달 바깥 클릭 시 닫기
    window.addEventListener('click', function(event) {
        const blockReasonModal = document.getElementById('blockReasonModal');
        const editCompanyModal = document.getElementById('editCompanyModal');
        const addCompanyModal = document.getElementById('addCompanyModal');
        
        if (event.target === blockReasonModal) {
            closeBlockReasonModal();
        }
        if (event.target === editCompanyModal) {
            closeEditCompanyModal();
        }
        if (event.target === addCompanyModal) {
            closeAddCompanyModal();
        }
    });
});

// 업체 그룹 수정 모달 열기
function editCompanyGroup(companyId, companyName, currentGroup) {
    // 모달 폼에 데이터 채우기
    document.getElementById('editCompanyId').value = companyId;
    document.getElementById('editCompanyName').value = companyName;
    document.getElementById('editItemGroup').value = currentGroup;
    
    // 다른 필드들 숨기기
    document.getElementById('editPassword').parentElement.style.display = 'none';
    document.getElementById('editContactPerson').parentElement.style.display = 'none';
    document.getElementById('editPhoneNumber').parentElement.style.display = 'none';
    document.getElementById('editEmail').parentElement.style.display = 'none';
    document.getElementById('editZipCode').parentElement.style.display = 'none';
    document.getElementById('editCompanyAddress').parentElement.style.display = 'none';
    
    // 업체명과 소속그룹만 표시
    document.getElementById('editCompanyName').parentElement.style.display = 'block';
    document.getElementById('editItemGroup').parentElement.style.display = 'block';
    
    // 모달 제목 변경
    document.querySelector('#editCompanyModal .modal-header h3').textContent = '업체 그룹 수정';
    
    // 모달 표시
    document.getElementById('editCompanyModal').style.display = 'block';
}

// 업체 그룹 저장
function saveCompanyGroup() {
    const companyId = document.getElementById('editCompanyId').value;
    const companyName = document.getElementById('editCompanyName').value;
    const itemGroup = document.getElementById('editItemGroup').value;
    
    if (!itemGroup) {
        showAlert('소속그룹을 선택해주세요.', 'error');
        return;
    }
    
    showAlert('업체 그룹을 수정하는 중...', 'info');
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateCompanyGroup&companyId=' + companyId + '&itemGroup=' + encodeURIComponent(itemGroup)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeEditCompanyModal();
            // 업체 목록 새로고침
            loadAllCompaniesWithStatus();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('업체 그룹 수정 오류:', error);
        showAlert('업체 그룹 수정 중 오류가 발생했습니다.', 'error');
    });
}

// 업체 정보 수정 모달 닫기
function closeEditCompanyModal() {
    document.getElementById('editCompanyModal').style.display = 'none';
    document.getElementById('editCompanyForm').reset();
    
    // 모든 필드 다시 표시
    document.getElementById('editPassword').parentElement.style.display = 'block';
    document.getElementById('editContactPerson').parentElement.style.display = 'block';
    document.getElementById('editPhoneNumber').parentElement.style.display = 'block';
    document.getElementById('editEmail').parentElement.style.display = 'block';
    document.getElementById('editZipCode').parentElement.style.display = 'block';
    document.getElementById('editCompanyAddress').parentElement.style.display = 'block';
    
    // 모달 제목 원래대로
    document.querySelector('#editCompanyModal .modal-header h3').textContent = '업체 정보 수정';
}

// ========================================
// 업체추가 모달 관련 함수들
// ========================================

// 업체추가 모달 열기
function showAddCompanyModal() {
    // 폼 초기화
    document.getElementById('addCompanyForm').reset();
    document.getElementById('addCompanyMessage').innerHTML = '';
    
    // 모달 표시
    document.getElementById('addCompanyModal').style.display = 'block';
}

// 업체추가 모달 닫기
function closeAddCompanyModal() {
    document.getElementById('addCompanyModal').style.display = 'none';
    document.getElementById('addCompanyForm').reset();
    document.getElementById('addCompanyMessage').innerHTML = '';
}

// 업체추가 폼 제출
function submitAddCompany() {
    const form = document.getElementById('addCompanyForm');
    const formData = new FormData(form);
    
    // 주소 결합
    const address1 = document.getElementById('addAddress1').value.trim();
    const address2 = document.getElementById('addAddress2').value.trim();
    const combinedAddress = address1 + (address2 ? ' ' + address2 : '');
    formData.set('address', combinedAddress);
    
    // 기본 검증 (모든 필드가 선택사항이므로 기본적인 형식만 체크)
    const password = formData.get('password');
    if (password && password.length < 4) {
        showAddCompanyMessage('비밀번호는 4자리 이상이어야 합니다.', 'error');
        return;
    }
    
    const phoneNumber = formData.get('phoneNumber');
    if (phoneNumber && !/^[0-9-+\s()]+$/.test(phoneNumber)) {
        showAddCompanyMessage('올바른 전화번호 형식을 입력해주세요.', 'error');
        return;
    }
    
    const zipCode = formData.get('zipCode');
    if (zipCode && !/^\d{5}$/.test(zipCode)) {
        showAddCompanyMessage('우편번호는 5자리 숫자여야 합니다.', 'error');
        return;
    }
    
    const email = formData.get('email');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAddCompanyMessage('올바른 이메일 형식을 입력해주세요.', 'error');
        return;
    }
    
    // 서버로 전송
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=addCompanyByAdmin&' + new URLSearchParams(formData).toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAddCompanyMessage(data.message, 'success');
            closeAddCompanyModal();
            // 업체 목록 새로고침
            setTimeout(() => {
                loadAllCompaniesWithStatus();
            }, 1000);
        } else {
            showAddCompanyMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('업체 추가 오류:', error);
        showAddCompanyMessage('업체 추가 중 오류가 발생했습니다.', 'error');
    });
}

// 업체추가 메시지 표시
function showAddCompanyMessage(message, type) {
    const messageDiv = document.getElementById('addCompanyMessage');
    messageDiv.innerHTML = '<div class="message ' + type + '">' + escapeHtml(message) + '</div>';
}