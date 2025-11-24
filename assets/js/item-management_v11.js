/**
 * item-management.js - 업체 품목 관리 기능
 */

// 전역 변수
let currentCompanyId = null;
let allItems = [];
let assignedItems = [];
let pendingRequests = [];

/**
 * 품목관리 폼 표시
 */
function showItemManagementForm() {
    // 현재 업체명 표시
    const companyName = window.currentCompany;
    
    if (companyName) {
        const cleanName = cleanCompanyName(companyName);
        document.getElementById('currentCompany5').textContent = cleanName;
    }
    
    // 모든 폼 숨기기
    hideAllForms();
    
    // 품목관리 폼 표시
    document.getElementById('itemManagementForm').classList.remove('hidden');
    
    // 품목 데이터 로드
    loadItemManagementData();
}

/**
 * 품목관리 데이터 로드
 */
function loadItemManagementData() {
    // 여러 방법으로 업체명 찾기
    let companyName = window.currentCompany;
    
    // DOM에서 업체명 찾기
    if (!companyName) {
        const companyElement = document.getElementById('currentCompany');
        if (companyElement) {
            companyName = companyElement.textContent.trim();
        }
    }
    
    // 세션 스토리지에서 찾기
    if (!companyName) {
        companyName = sessionStorage.getItem('company_name');
    }
    
    // 업체명 정리
    if (companyName) {
        companyName = cleanCompanyName(companyName);
    }
    
    if (!companyName) {
        showMessage('itemManagementMessage', '업체 정보를 찾을 수 없습니다. 다시 로그인해주세요.', 'error');
        return;
    }
    
    // 업체 ID 조회
    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getCompanyId',
            companyName: companyName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentCompanyId = data.companyId;
            loadItemRequestStatus();
        } else {
            showMessage('itemManagementMessage', '업체 정보 조회 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('itemManagementMessage', '업체 정보 조회 중 오류가 발생했습니다.', 'error');
    });
}

/**
 * 품목 요청 현황 로드
 */
function loadItemRequestStatus() {
    if (!currentCompanyId) return;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getCompanyItemRequestStatus',
            companyId: currentCompanyId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error('서버에서 HTML을 반환했습니다.');
            });
        }
    })
    .then(data => {
        if (data.success) {
            assignedItems = data.assignedItems || [];
            allItems = data.allItems || [];
            
            // 레거시 모드인 경우 메시지 표시
            if (data.legacy) {
                showMessage('itemManagementMessage', '기존 모드로 작동 중입니다. 품목 요청 기능은 관리자 승인 후 사용 가능합니다.', 'info');
            }
            
            displayAssignedItems();
            displayAllItems();
            loadCategories();
        } else {
            showMessage('itemManagementMessage', '품목 정보 조회 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('itemManagementMessage', '품목 정보 조회 중 오류가 발생했습니다: ' + error.message, 'error');
    });
}

/**
 * 할당된 품목 표시
 */
function displayAssignedItems() {
    const container = document.getElementById('assignedItemsList');
    
    if (assignedItems.length === 0) {
        container.innerHTML = '<div class="no-data">할당된 품목이 없습니다.</div>';
        return;
    }
    
    let html = '';
    assignedItems.forEach(item => {
        const requestStatus = getRequestStatus(item.item_id);
        const statusClass = getStatusClass(requestStatus);
        const statusText = getStatusText(requestStatus);
        
        html += `
            <div class="item-card assigned">
                <div class="item-info">
                    <h5>
                        <span>${escapeHtml(item.item_name)}</span>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </h5>
                    <p class="item-description">${escapeHtml(item.description || '')}</p>
                </div>
                <div class="item-actions">
                    ${requestStatus.status === 'pending' ? 
                        '<button class="btn btn-small btn-secondary" disabled>요청 대기중</button>' :
                        '<button class="btn btn-small btn-danger" onclick="requestItemRemoval(' + item.item_id + ')">제거 요청</button>'
                    }
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * 전체 품목 표시
 */
function displayAllItems() {
    const container = document.getElementById('allItemsList');
    
    if (allItems.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 품목이 없습니다.</div>';
        return;
    }
    
    let html = '';
    let currentCategory = '';
    
    allItems.forEach(item => {
        // 카테고리 헤더 표시
        if (item.category_name !== currentCategory) {
            currentCategory = item.category_name;
            html += `<div class="category-header">${escapeHtml(currentCategory)}</div>`;
        }
        
        const isAssigned = item.is_assigned == 1;
        const requestStatus = getRequestStatus(item.item_id);
        const statusClass = getStatusClass(requestStatus);
        const statusText = getStatusText(requestStatus);
        
        html += `
            <div class="item-card ${isAssigned ? 'assigned' : 'unassigned'}">
                <div class="item-info">
                    <h5>
                        <span>${escapeHtml(item.item_name)}</span>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </h5>
                    <p class="item-description">${escapeHtml(item.description || '')}</p>
                </div>
                <div class="item-actions">
                    ${isAssigned ? 
                        (requestStatus.status === 'pending' ? 
                            '<button class="btn btn-small btn-secondary" disabled>요청 대기중</button>' :
                            '<button class="btn btn-small btn-danger" onclick="requestItemRemoval(' + item.item_id + ')">제거 요청</button>'
                        ) :
                        (requestStatus.status === 'pending' ? 
                            '<button class="btn btn-small btn-secondary" disabled>요청 대기중</button>' :
                            '<button class="btn btn-small btn-primary" onclick="requestItemAddition(' + item.item_id + ')">추가 요청</button>'
                        )
                    }
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * 카테고리 목록 로드
 */
function loadCategories() {
    console.log('loadCategories 호출됨, allItems:', allItems);
    
    const categories = [...new Set(allItems.map(item => item.category_name))];
    console.log('추출된 카테고리:', categories);
    
    const select = document.getElementById('categoryFilter');
    if (!select) {
        console.error('categoryFilter 요소를 찾을 수 없습니다.');
        return;
    }
    
    select.innerHTML = '<option value="">전체 카테고리</option>';
    categories.forEach(category => {
        if (category && category.trim() !== '') {
            select.innerHTML += `<option value="${escapeHtml(category)}">${escapeHtml(category)}</option>`;
        }
    });
    
    console.log('카테고리 필터 옵션 생성 완료');
}

/**
 * 카테고리별 품목 필터링
 */
function filterItemsByCategory() {
    const select = document.getElementById('categoryFilter');
    if (!select) {
        console.error('categoryFilter 요소를 찾을 수 없습니다.');
        return;
    }
    
    const selectedCategory = select.value;
    console.log('선택된 카테고리:', selectedCategory);
    console.log('전체 품목 수:', allItems.length);
    
    if (selectedCategory === '') {
        // 전체 카테고리 선택 시 모든 품목 표시
        console.log('전체 품목 표시');
        displayAllItems();
    } else {
        // 선택된 카테고리만 필터링하여 표시
        console.log('카테고리 필터링:', selectedCategory);
        displayFilteredItems(selectedCategory);
    }
}

/**
 * 필터링된 품목 표시
 */
function displayFilteredItems(selectedCategory) {
    console.log('displayFilteredItems 호출됨, 카테고리:', selectedCategory);
    
    const container = document.getElementById('allItemsList');
    if (!container) {
        console.error('allItemsList 요소를 찾을 수 없습니다.');
        return;
    }
    
    // 선택된 카테고리의 품목만 필터링
    const filteredItems = allItems.filter(item => item.category_name === selectedCategory);
    console.log('필터링된 품목 수:', filteredItems.length);
    console.log('필터링된 품목:', filteredItems);
    
    if (filteredItems.length === 0) {
        container.innerHTML = '<div class="no-data">선택된 카테고리에 품목이 없습니다.</div>';
        return;
    }
    
    let html = '';
    let currentCategory = '';
    
    filteredItems.forEach(item => {
        // 카테고리 헤더 표시
        if (item.category_name !== currentCategory) {
            currentCategory = item.category_name;
            html += `<div class="category-header">${escapeHtml(currentCategory)}</div>`;
        }
        
        const isAssigned = item.is_assigned == 1;
        const requestStatus = getRequestStatus(item.item_id);
        const statusClass = getStatusClass(requestStatus);
        const statusText = getStatusText(requestStatus);
        
        html += `
            <div class="item-card ${isAssigned ? 'assigned' : 'unassigned'}">
                <div class="item-info">
                    <h5>
                        <span>${escapeHtml(item.item_name)}</span>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </h5>
                    <p class="item-description">${escapeHtml(item.description || '')}</p>
                </div>
                <div class="item-actions">
                    ${isAssigned ? 
                        (requestStatus.status === 'pending' ? 
                            '<button class="btn btn-small btn-secondary" disabled>요청 대기중</button>' :
                            '<button class="btn btn-small btn-danger" onclick="requestItemRemoval(' + item.item_id + ')">제거 요청</button>'
                        ) :
                        (requestStatus.status === 'pending' ? 
                            '<button class="btn btn-small btn-secondary" disabled>요청 대기중</button>' :
                            '<button class="btn btn-small btn-primary" onclick="requestItemAddition(' + item.item_id + ')">추가 요청</button>'
                        )
                    }
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    console.log('필터링된 품목 표시 완료');
}

/**
 * 품목 추가 요청
 */
function requestItemAddition(itemId) {
    if (!currentCompanyId) {
        showMessage('itemManagementMessage', '업체 정보를 찾을 수 없습니다. 페이지를 새로고침해주세요.', 'error');
        return;
    }
    
    if (!confirm('이 품목을 주문 가능 품목에 추가 요청하시겠습니까?')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'createCompanyItemRequest',
            companyId: currentCompanyId,
            itemId: itemId,
            requestAction: 'add'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showMessage('itemManagementMessage', '품목 추가 요청이 완료되었습니다.', 'success');
                loadItemRequestStatus();
            } else {
                showMessage('itemManagementMessage', '요청 실패: ' + data.message, 'error');
            }
        } catch (parseError) {
            showMessage('itemManagementMessage', '서버 응답을 처리할 수 없습니다.', 'error');
        }
    })
    .catch(error => {
        showMessage('itemManagementMessage', '요청 중 오류가 발생했습니다.', 'error');
    });
}

/**
 * 품목 제거 요청
 */
function requestItemRemoval(itemId) {
    if (!currentCompanyId) return;
    
    if (!confirm('이 품목을 주문 가능 품목에서 제거 요청하시겠습니까?')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'createCompanyItemRequest',
            companyId: currentCompanyId,
            itemId: itemId,
            requestAction: 'remove'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showMessage('itemManagementMessage', '품목 제거 요청이 완료되었습니다.', 'success');
                loadItemRequestStatus();
            } else {
                showMessage('itemManagementMessage', '요청 실패: ' + data.message, 'error');
            }
        } catch (parseError) {
            showMessage('itemManagementMessage', '서버 응답을 처리할 수 없습니다.', 'error');
        }
    })
    .catch(error => {
        showMessage('itemManagementMessage', '요청 중 오류가 발생했습니다.', 'error');
    });
}

/**
 * 업체명에서 HTML 태그 제거 및 정리
 */
function cleanCompanyName(companyName) {
    if (!companyName) return '';
    
    // DOM 요소인 경우 textContent 추출
    if (companyName.nodeType === 1) {
        return companyName.textContent.trim();
    }
    
    // 문자열인 경우 HTML 태그 제거
    if (typeof companyName === 'string') {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = companyName;
        let cleanName = tempDiv.textContent || tempDiv.innerText || '';
        
        // 공백 제거 및 정리
        cleanName = cleanName.trim();
        cleanName = cleanName.replace(/[\r\n\t]/g, '').trim();
        
        return cleanName;
    }
    
    // 기타 경우 문자열로 변환
    return String(companyName).trim();
}

/**
 * 요청 상태 조회
 */
function getRequestStatus(itemId) {
    const item = allItems.find(i => i.item_id == itemId);
    if (item && item.request_status) {
        return {
            status: item.request_status,
            action: item.request_action
        };
    }
    return { status: null, action: null };
}

/**
 * 상태 클래스 반환
 */
function getStatusClass(requestStatus) {
    if (!requestStatus.status) return 'status-none';
    switch (requestStatus.status) {
        case 'pending': return 'status-pending';
        case 'approved': return 'status-approved';
        case 'rejected': return 'status-rejected';
        default: return 'status-none';
    }
}

/**
 * 상태 텍스트 반환
 */
function getStatusText(requestStatus) {
    if (!requestStatus.status) return '';
    switch (requestStatus.status) {
        case 'pending': return '승인 대기중';
        case 'approved': return '승인됨';
        case 'rejected': return '거부됨';
        default: return '';
    }
}

/**
 * 메시지 표시
 */
function showMessage(elementId, message, type) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `<div class="message ${type}">${escapeHtml(message)}</div>`;
    }
}

/**
 * HTML 이스케이프
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 전역 함수로 노출
window.showItemManagementForm = showItemManagementForm;
window.filterItemsByCategory = filterItemsByCategory;
window.requestItemAddition = requestItemAddition;
window.requestItemRemoval = requestItemRemoval;

