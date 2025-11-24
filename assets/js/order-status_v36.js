// 주문조회 폼 표시 - 수정됨: 당일 주문 유무에 따른 주문하기 버튼 제어
function showOrderStatusForm() {
    hideAllForms();
    document.getElementById('orderStatusForm').classList.remove('hidden');
    
    // 기본으로 오늘 주문 탭 활성화
    showOrderTab('today');
    
    // 당일 주문 현황 로드
    loadTodayOrderStatus();
}

// 주문조회 탭 전환
function showOrderTab(tabName) {
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.order-status-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 모든 탭 콘텐츠 숨기기
    document.querySelectorAll('.order-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    if (tabName === 'today') {
        document.querySelector('[onclick="showOrderTab(\'today\')"]').classList.add('active');
        document.getElementById('todayOrderTab').classList.remove('hidden');
        loadTodayOrderStatus();
    } else if (tabName === 'history') {
        document.querySelector('[onclick="showOrderTab(\'history\')"]').classList.add('active');
        document.getElementById('historyOrderTab').classList.remove('hidden');
        loadOrderHistory();
    }
}

// 주문조회 탭 전환 (데이터 로드 없이)
function showOrderTabWithoutLoad(tabName) {
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.order-status-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 모든 탭 콘텐츠 숨기기
    document.querySelectorAll('.order-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    if (tabName === 'today') {
        document.querySelector('[onclick="showOrderTab(\'today\')"]').classList.add('active');
        document.getElementById('todayOrderTab').classList.remove('hidden');
        // loadTodayOrderStatus() 호출하지 않음
    } else if (tabName === 'history') {
        document.querySelector('[onclick="showOrderTab(\'history\')"]').classList.add('active');
        document.getElementById('historyOrderTab').classList.remove('hidden');
        // loadOrderHistory() 호출하지 않음
    }
}

// 당일 주문 현황 로드 (로딩 메시지 없이)
function loadTodayOrderStatus() {
    if (!currentCompany) return;
    
    const content = document.getElementById('todayOrderContent');
    // 로딩 메시지 표시하지 않음
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getTodayOrderStatus',
            companyName: currentCompany
        })
    })
    .then(response => response.json())
    .then(result => {
        displayTodayOrderStatus(result);
        checkSyncStatus();
        
        // 수정됨: 당일 주문 유무에 따른 주문하기 버튼 제어
        controlOrderButton(result);
    })
    .catch(error => {
        console.error('당일 주문 조회 오류:', error);
        content.innerHTML = '<div class="error-message">주문 현황을 불러오는 중 오류가 발생했습니다.</div>';
    });
}

// 로딩 메시지 없이 주문 현황 새로고침 (수정 완료 후 사용)
function refreshOrderStatusWithoutLoading() {
    if (!currentCompany) return;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getTodayOrderStatus',
            companyName: currentCompany
        })
    })
    .then(response => response.json())
    .then(result => {
        displayTodayOrderStatus(result);
        checkSyncStatus();
        
        // 수정됨: 당일 주문 유무에 따른 주문하기 버튼 제어
        controlOrderButton(result);
    })
    .catch(error => {
        console.error('당일 주문 조회 오류:', error);
        const content = document.getElementById('todayOrderContent');
        content.innerHTML = '<div class="error-message">주문 현황을 불러오는 중 오류가 발생했습니다.</div>';
    });
}

// 추가됨: 주문하기 버튼 제어 함수
function controlOrderButton(orderResult) {
    const orderButton = document.querySelector('#orderStatusForm .nav-btn[onclick="goToOrder()"]');
    if (!orderButton) return;
    
    // 오늘 주문이 있는 경우 버튼 비활성화
    if (orderResult.success && orderResult.orders && orderResult.orders.length > 0) {
        orderButton.disabled = true;
        orderButton.style.opacity = '0.5';
        orderButton.style.cursor = 'not-allowed';
        orderButton.title = '오늘 이미 주문했습니다. 수정은 아래에서 가능합니다.';
    } else {
        // 오늘 주문이 없는 경우 버튼 활성화
        orderButton.disabled = false;
        orderButton.style.opacity = '1';
        orderButton.style.cursor = 'pointer';
        orderButton.title = '새로운 주문하기';
    }
}

// 당일 주문 현황 표시 (차단 상태 고려 - 수정됨)
function displayTodayOrderStatus(result) {
    const content = document.getElementById('todayOrderContent');
    
    if (!result.success) {
        content.innerHTML = `<div class="error-message">${result.message}</div>`;
        return;
    }
    
    // 차단 상태 알림 추가 (조회는 가능하지만 수정은 불가능함을 명시)
    let blockNoticeHtml = '';
    if (result.orderBlocked) {
        blockNoticeHtml = `
            <div class="order-blocked-notice">
                <div class="alert alert-warning">
                    <h4>⚠️ 주문 수정 불가</h4>
                    <p><strong>사유:</strong> ${result.blockReason || '관리자에 의해 주문이 차단되었습니다.'}</p>
                    <p>주문 조회만 가능합니다.</p>
                </div>
            </div>
        `;
    }
    
    if (!result.orders || result.orders.length === 0) {
        content.innerHTML = blockNoticeHtml + '<div class="no-order-message">오늘 주문한 내역이 없습니다.</div>';
        return;
    }
    
    // 현재 주문 데이터를 전역 변수에 저장 (편집용)
    currentOrderData = {
        companyName: result.companyName,
        orders: result.orders.map(order => ({
            item: order.item_name,
            quantity: order.quantity
        })),
        summary: result.summary,
        deliveryDay: result.deliveryDay,
        canModify: result.canModify || false, // 수정 가능 여부 (차단 상태 고려)
        orderBlocked: result.orderBlocked || false
    };
    
let html = blockNoticeHtml + `
        <div class="order-summary">
            ${result.summary.lastOrderTime ? `
            <div class="summary-item">
                <span class="summary-label">주문시간:</span>
                <span class="summary-value">${formatDateTime(result.summary.lastOrderTime)}</span>
            </div>
            ` : ''}
            <div class="summary-item">
                <span class="summary-label">품목수:</span>
                <span class="summary-value" id="totalItems">${result.summary.totalItems}개</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">전체수량:</span>
                <span class="summary-value" id="totalQuantity">${result.summary.totalQuantity}개</span>
            </div>
        </div>
        
        <div class="order-items">
            <div class="order-items-header">
                <h4>주문 내역</h4>
                ${result.canModify ? `<button class="btn btn-small btn-primary" onclick="showAddItemModal()">품목 추가</button>` : ''}
            </div>
            <div id="editableOrderItems">
    `;
    
    result.orders.forEach((order, index) => {
        html += `
            <div class="editable-order-item" data-index="${index}">
                <span class="item-name">${order.item_name}</span>
                <div class="quantity-controls">
                    ${result.canModify ? 
                        `<span class="quantity-display" onclick="editQuantity(${index})">${order.quantity}</span>
                         <input type="number" class="quantity-edit hidden" min="1" max="999" value="${order.quantity}" onblur="saveQuantity(${index})" onkeypress="handleQuantityKeyPress(event, ${index})">
                         <button class="delete-item-btn" onclick="deleteOrderItem(${index})" title="품목 삭제">×</button>` 
                        : 
                        `<span class="quantity-display readonly">${order.quantity}</span>`
                    }
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
            ${result.canModify ? `
            <div class="order-edit-actions hidden" id="orderEditActions">
                <button class="btn btn-success btn-small" onclick="saveOrderChanges()">수정 완료</button>
                <button class="btn btn-secondary btn-small" onclick="cancelOrderEdit()">취소</button>
            </div>
            ` : ''}
        </div>
    `;
    
    // 수정 가능한 경우에만 모달 추가
    if (result.canModify) {
        html += `
            <!-- 품목 추가 모달 -->
            <div id="addItemModal" class="modal hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>품목 추가</h3>
                        <span class="close" onclick="closeAddItemModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="newItemSelect">추가할 품목:</label>
                            <select id="newItemSelect">
                                <option value="">선택</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="newItemQuantity">수량:</label>
                            <input type="number" id="newItemQuantity" min="1" max="999" placeholder="입력">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-success" onclick="addNewOrderItem()">추가</button>
                        <button class="btn btn-secondary" onclick="closeAddItemModal()">취소</button>
                    </div>
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
    
    // 사용 가능한 품목 목록 로드 (수정 가능한 경우에만)
    if (result.canModify) {
        loadAvailableItems();
    }
}

// 수량 편집 모드로 전환
function editQuantity(index) {
    const item = document.querySelector(`[data-index="${index}"]`);
    const display = item.querySelector('.quantity-display');
    const input = item.querySelector('.quantity-edit');
    
    display.classList.add('hidden');
    input.classList.remove('hidden');
    input.focus();
    input.select();
    
    // 편집 액션 버튼 표시
    document.getElementById('orderEditActions').classList.remove('hidden');
}

// 수량 저장
function saveQuantity(index) {
    const item = document.querySelector(`[data-index="${index}"]`);
    const display = item.querySelector('.quantity-display');
    const input = item.querySelector('.quantity-edit');
    
    const newQuantity = parseInt(input.value) || 1;
    if (newQuantity < 1) {
        input.value = 1;
        return;
    }
    
    // 데이터 업데이트
    if (currentOrderData && currentOrderData.orders[index]) {
        currentOrderData.orders[index].quantity = newQuantity;
    }
    
    display.textContent = newQuantity;
    display.classList.remove('hidden');
    input.classList.add('hidden');
    
    // 요약 정보 업데이트
    updateOrderSummary();
    
    // 편집 액션 버튼 표시
    document.getElementById('orderEditActions').classList.remove('hidden');
    
    // 안내 메시지 표시
    showMessage('orderStatusMessage', '수정완료 버튼을 클릭하세요', 'info');
}

// 키 입력 처리 (엔터 키로 저장)
function handleQuantityKeyPress(event, index) {
    if (event.key === 'Enter') {
        saveQuantity(index);
    }
}

// 주문 품목 삭제
function deleteOrderItem(index) {
    if (confirm('해당 품목이 주문에서 삭제됩니다')) {
        // 데이터에서 제거
        if (currentOrderData && currentOrderData.orders[index]) {
            currentOrderData.orders.splice(index, 1);
        }
        
        // 화면 다시 그리기
        refreshOrderDisplay();
        
        // 편집 액션 버튼 표시
        document.getElementById('orderEditActions').classList.remove('hidden');
        
        // 안내 메시지 표시
        showMessage('orderStatusMessage', '수정완료 버튼을 클릭하세요', 'info');
    }
}

// 주문 표시 새로고침
function refreshOrderDisplay() {
    if (!currentOrderData || !currentOrderData.orders) return;
    
    const container = document.getElementById('editableOrderItems');
    let html = '';
    
    currentOrderData.orders.forEach((order, index) => {
        html += `
            <div class="editable-order-item" data-index="${index}">
                <span class="item-name">${order.item}</span>
                <div class="quantity-controls">
                    <span class="quantity-display" onclick="editQuantity(${index})">${order.quantity}</span>
                    <input type="number" class="quantity-edit hidden" min="1" max="999" value="${order.quantity}" onblur="saveQuantity(${index})" onkeypress="handleQuantityKeyPress(event, ${index})">
                    <button class="delete-item-btn" onclick="deleteOrderItem(${index})" title="품목 삭제">×</button>
                </div>
            </div>
        `;
    });
    
    if (currentOrderData.orders.length === 0) {
        // 편집 모드에서는 빈 상태 메시지를 표시하지 않음 (수정완료 버튼 클릭 시까지 대기)
        html = '<div class="empty-order-placeholder">모든 품목이 삭제됩니다 <br>"수정완료" 버튼을 클릭하세요</div>';
    }
    
    container.innerHTML = html;
    updateOrderSummary();
}

// 요약 정보 업데이트
function updateOrderSummary() {
    if (!currentOrderData || !currentOrderData.orders) return;
    
    const totalItems = currentOrderData.orders.length;
    const totalQuantity = currentOrderData.orders.reduce((sum, order) => sum + order.quantity, 0);
    
    document.getElementById('totalItems').textContent = totalItems + '개';
    document.getElementById('totalQuantity').textContent = totalQuantity;
}

// 사용 가능한 품목 목록 로드
function loadAvailableItems() {
    const select = document.getElementById('newItemSelect');
    if (!select || !companyItems) return;
    
    // helper: 객체/문자열 혼용 대비하여 품목명 추출
    const getItemName = (it) => {
        if (typeof it === 'string') return it;
        if (it && typeof it === 'object') {
            return it.name || it.item_name || '';
        }
        return '';
    };

    // 현재 주문에 없는 품목만 필터링 (문자열 기준 비교)
    const orderedNames = (currentOrderData.orders || []).map(o => o.item);
    const availableNames = companyItems
        .map(getItemName)
        .filter(name => !!name)
        .filter(name => !orderedNames.includes(name));
    
    select.innerHTML = '<option value="">선택</option>';
    availableNames.forEach(name => {
        select.innerHTML += `<option value="${name}">${name}</option>`;
    });
}

// 품목 추가 모달 표시
function showAddItemModal() {
    document.getElementById('addItemModal').classList.remove('hidden');
    loadAvailableItems();
    document.getElementById('newItemQuantity').value = '';
}

// 품목 추가 모달 닫기
function closeAddItemModal() {
    document.getElementById('addItemModal').classList.add('hidden');
    document.getElementById('newItemSelect').value = '';
    document.getElementById('newItemQuantity').value = '';
}

// 새 품목 추가
function addNewOrderItem() {
    const select = document.getElementById('newItemSelect');
    const quantityInput = document.getElementById('newItemQuantity');
    
    const selectedItem = select.value;
    const quantity = parseInt(quantityInput.value) || 0;
    
    if (!selectedItem) {
        alert('품목을 선택하세요');
        return;
    }
    
    if (quantity < 1) {
        alert('수량을 입력하세요');
        quantityInput.focus();
        return;
    }
    
    // 중복 체크
    if (currentOrderData.orders.some(order => order.item === selectedItem)) {
        alert('이미 주문한 품목입니다');
        return;
    }
    
    // 새 품목 추가
    currentOrderData.orders.push({
        item: selectedItem,
        quantity: quantity
    });
    
    // 화면 새로고침
    refreshOrderDisplay();
    closeAddItemModal();
    
    // 편집 액션 버튼 표시
    document.getElementById('orderEditActions').classList.remove('hidden');
    
    // 안내 메시지 표시
    showMessage('orderStatusMessage', '수정완료 버튼을 클릭하세요', 'info');
}

// 주문 변경사항 저장
function saveOrderChanges() {
    if (!currentOrderData) {
        showMessage('orderStatusMessage', '주문 데이터를 찾을 수 없습니다.', 'error');
        return;
    }

    // 빈 주문(모든 품목 삭제)도 허용: 사용자 확인 후 서버에 삭제로 전달
    const isDeletingAll = Array.isArray(currentOrderData.orders) && currentOrderData.orders.length === 0;
    if (isDeletingAll) {
        const confirmed = confirm('모든 주문이 삭제됩니다\n"확인"을 누르면 오늘 주문이 비워지며 관리자 화면에서도 삭제됩니다.');
        if (!confirmed) return;
    }
    
    if (confirm('주문 변경을 저장합니다')) {
        showLoading(true);
        
        // 기존 processOrder와 동일한 형식으로 데이터 전송 (빈 배열 허용)
        const orderData = {
            companyName: currentCompany,
            orders: currentOrderData.orders
        };
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'processOrder',
                orderData: orderData
            })
        })
        .then(response => response.json())
        .then(result => {
            showLoading(false);
            
            if (result.success) {
                // 구글시트 관련 텍스트 제거
                const cleanMessage = result.message.replace(/\s*\(구글시트.*?\)/, '');
                // 주문 수정 완료 메시지로 변경
                showMessage('orderStatusMessage', '주문이 수정되었습니다.', 'success');
                
                // 편집 액션 버튼 숨기기
                document.getElementById('orderEditActions').classList.add('hidden');
                
                // 주문 현황 즉시 새로고침 (로딩 메시지 없이)
                setTimeout(() => {
                    refreshOrderStatusWithoutLoading();
                }, 500);
                
            } else {
                showMessage('orderStatusMessage', result.message || '주문 수정 중 오류가 발생했습니다.', 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('주문 수정 오류:', error);
            showMessage('orderStatusMessage', '주문 수정 중 오류가 발생했습니다.', 'error');
        });
    }
}

// 주문 편집 취소
function cancelOrderEdit() {
    if (confirm('변경사항을 취소합니다')) {
        // 편집 액션 버튼 숨기기
        document.getElementById('orderEditActions').classList.add('hidden');
        
        // 메시지 클리어
        showMessage('orderStatusMessage', '', '');
        
        // 원래 주문 상태로 복원
        loadTodayOrderStatus();
    }
}

// Google Sheets 동기화 상태 확인 (수정됨: 동기화 상태 완전히 숨김)
function checkSyncStatus() {
    if (!currentCompany) return;
    
    const syncStatus = document.getElementById('syncStatus');
    if (!syncStatus) return; // 동기화 상태 요소가 없으면 건너뛰기
    
    // 동기화 상태 요소를 완전히 숨김
    syncStatus.classList.add('hidden');
}

// 최근 주문 이력 로드 (로딩 메시지 없이)
function loadOrderHistory() {
    if (!currentCompany) return;
    
    const content = document.getElementById('historyOrderContent');
    // 로딩 메시지 표시하지 않음
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getRecentOrderHistory',
            companyName: currentCompany,
            days: 7
        })
    })
    .then(response => response.json())
    .then(result => {
        displayOrderHistory(result);
    })
    .catch(error => {
        console.error('주문 이력 조회 오류:', error);
        content.innerHTML = '<div class="error-message">주문 이력을 불러오는 중 오류가 발생했습니다.</div>';
    });
}

// 주문 이력 표시 - 한 줄 레이아웃 (수정됨: 시간 포맷 개선 및 차단 상태에 따른 주문복사 버튼 제어)
function displayOrderHistory(result) {
    const content = document.getElementById('historyOrderContent');
    
    if (!result.success) {
        content.innerHTML = `<div class="error-message">${result.message}</div>`;
        return;
    }
    
    if (!result.history || result.history.length === 0) {
        content.innerHTML = `<div class="no-order-message">최근 일주일간 주문 이력이 없습니다.</div>`;
        return;
    }
    
    let html = `
        <div class="history-summary">
            <span>최근 일주일간 총 ${result.totalDays}일 주문 이력</span>
            ${result.orderBlocked ? `
            <div class="alert alert-warning" style="margin-top: 10px;">
                <small>⚠️ 주문이 차단되어 주문복사 기능을 사용할 수 없습니다.</small>
            </div>
            ` : ''}
        </div>
        <div class="history-list">
    `;
    
    result.history.forEach((dayData, dayIndex) => {
        html += `
            <div class="history-day">
                <div class="history-date">
                    <span class="date">${formatDate(dayData.date)}</span>
                    <span class="day-summary">${dayData.totalItems}개 품목 / 총 ${dayData.totalQuantity}개</span>
                    ${result.canCopyOrder ? 
                        `<button class="btn btn-small btn-secondary copy-order-btn" onclick="copyOrder(${dayIndex})" title="이 날짜의 주문을 오늘로 복사">주문복사</button>` 
                        : 
                        `<button class="btn btn-small btn-secondary copy-order-btn" disabled title="주문이 차단되어 복사할 수 없습니다" style="opacity: 0.5;">주문복사</button>`
                    }
                </div>
                <div class="history-items">
        `;
        
        dayData.orders.forEach(order => {
            html += `
                <div class="history-item">
                    <span class="item-name">${order.item_name}</span>
                    <span class="item-quantity">${order.quantity}</span>
                    <span class="item-time">${formatOrderTime(order.order_time)}</span>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
    
    // 주문 이력 데이터를 전역 변수에 저장 (복사 기능용)
    window.orderHistoryData = result.history;
}

// 주문 시간 포맷팅 함수 추가 (시간만 표시)
function formatOrderTime(timeString) {
    if (!timeString || timeString === '00:00:00') return '';
    
    try {
        // timeString이 "HH:MM:SS" 형식인 경우
        const timeParts = timeString.split(':');
        if (timeParts.length >= 2) {
            const hours = timeParts[0];
            const minutes = timeParts[1];
            return `${hours}:${minutes}`;
        }
        
        // 전체 날짜/시간 문자열인 경우 formatDateTime 사용
        return formatDateTime(timeString);
    } catch (error) {
        console.error('시간 포맷팅 오류:', error);
        return timeString;
    }
}

// 수정됨: 주문 복사 기능 (차단 상태 체크)
function copyOrder(dayIndex) {
    if (!window.orderHistoryData || !window.orderHistoryData[dayIndex]) {
        alert('복사할 주문 데이터를 찾을 수 없습니다.');
        return;
    }
    
    // 차단 상태 체크
    if (currentOrderData && currentOrderData.orderBlocked) {
        alert('주문이 차단되어 복사할 수 없습니다.');
        return;
    }
    
    const dayData = window.orderHistoryData[dayIndex];
    const dateStr = formatDate(dayData.date);
    
    if (confirm(`${dateStr}의 주문을 오늘 주문으로 복사하여\n 품목 및 수량을 수정합니다.`)) {
        // 복사할 주문 데이터를 currentOrderData 형식으로 변환
        const copiedOrderData = {
            companyName: currentCompany,
            orders: dayData.orders.map(order => ({
                item: order.item_name,
                quantity: order.quantity
            })),
            summary: {
                totalItems: dayData.totalItems,
                totalQuantity: dayData.totalQuantity,
                lastOrderTime: null // 복사된 주문이므로 null로 설정
            },
            deliveryDay: null, // 복사된 주문이므로 null로 설정
            isCopied: true, // 복사된 주문임을 표시
            canModify: true, // 복사된 주문은 수정 가능
            orderBlocked: false // 복사된 주문은 차단되지 않음
        };
        
        // 전역 변수에 저장
        currentOrderData = copiedOrderData;
        
        // 오늘 주문 탭으로 전환 (loadTodayOrderStatus 호출 방지)
        showOrderTabWithoutLoad('today');
        
        // 복사된 주문 데이터로 화면 업데이트
        displayCopiedOrder(copiedOrderData, dateStr);
        
        // 성공 메시지 표시 (화면 업데이트 후)
        setTimeout(() => {
            showMessage('orderStatusMessage', `${dateStr}의 주문이 복사되었습니다.`, 'success');
        }, 100);
    }
}

// 추가됨: 복사된 주문 표시 함수
function displayCopiedOrder(orderData, sourceDate) {
    const content = document.getElementById('todayOrderContent');
    
    let html = `
        <div class="copied-order-notice">
            <div class="notice-header">
                <span class="notice-icon">📋</span>
                <span class="notice-text">${sourceDate} 주문이 복사됨</span>
            </div>
            <div class="notice-description">수정후 "수정완료" 버튼을 클릭하면<br>오늘 주문으로 저장됩니다.</div>
        </div>
        
        <div class="order-summary">
            <div class="summary-item">
                <span class="summary-label">품목수:</span>
                <span class="summary-value" id="totalItems">${orderData.summary.totalItems}개</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">전체수량:</span>
                <span class="summary-value" id="totalQuantity">${orderData.summary.totalQuantity}개</span>
            </div>
        </div>
        
        <div class="order-items">
            <div class="order-items-header">
                <h4>복사된 주문 품목</h4>
                <button class="btn btn-small btn-primary" onclick="showAddItemModal()">품목 추가</button>
            </div>
            <div id="editableOrderItems">
    `;
    
    orderData.orders.forEach((order, index) => {
        html += `
            <div class="editable-order-item" data-index="${index}">
                <span class="item-name">${order.item}</span>
                <div class="quantity-controls">
                    <span class="quantity-display" onclick="editQuantity(${index})">${order.quantity}</span>
                    <input type="number" class="quantity-edit hidden" min="1" max="999" value="${order.quantity}" onblur="saveQuantity(${index})" onkeypress="handleQuantityKeyPress(event, ${index})">
                    <button class="delete-item-btn" onclick="deleteOrderItem(${index})" title="품목 삭제">×</button>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
            <div class="order-edit-actions" id="orderEditActions">
                <button class="btn btn-success btn-small" onclick="saveOrderChanges()">수정 완료</button>
                <button class="btn btn-secondary btn-small" onclick="cancelCopiedOrder()">복사 취소</button>
            </div>
        </div>
        
        <!-- 품목 추가 모달 -->
        <div id="addItemModal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>품목 추가</h3>
                    <span class="close" onclick="closeAddItemModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newItemSelect">추가할 품목:</label>
                        <select id="newItemSelect">
                            <option value="">선택</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="newItemQuantity">수량:</label>
                        <input type="number" id="newItemQuantity" min="1" max="999" placeholder="입력">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success" onclick="addNewOrderItem()">추가</button>
                    <button class="btn btn-secondary" onclick="closeAddItemModal()">취소</button>
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    
    // 사용 가능한 품목 목록 로드
    loadAvailableItems();
}

// 추가됨: 복사된 주문 취소 함수
function cancelCopiedOrder() {
    if (confirm('복사된 주문을 취소하시겠습니까?')) {
        // 원래 오늘 주문 상태로 복원
        loadTodayOrderStatus();
        showMessage('orderStatusMessage', '주문 복사가 취소되었습니다.', 'info');
    }
}