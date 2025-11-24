// 페이지 로드 시 차단 상태 확인
document.addEventListener('DOMContentLoaded', function() {
    checkOrderBlockStatus();
});

// 주문차단 상태 확인 함수 (수정됨: 차단되어도 주문조회는 허용)
function checkOrderBlockStatus() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkOrderBlock'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.blocked) {
            isOrderBlocked = true;
            blockReason = result.reason || '';
            // 차단 UI는 표시하지 않음 - 로그인 후 주문조회는 가능하도록
            console.log('주문이 차단되었습니다:', blockReason);
        } else {
            isOrderBlocked = false;
            blockReason = '';
        }
    })
    .catch(error => {
        console.error('차단상태 확인 오류:', error);
    });
}

// 품목 체크박스 생성 (차단 상태 확인 추가 - 수정됨)
function createItemCheckboxes() {
    const container = document.getElementById('itemCheckboxes');
    container.innerHTML = '';
    selectedItems = [];
    orderItems = [];
    
    console.log('체크박스생성, 품목들:', companyItems);
    
    // ========================================
    // 개별메시지 배너 로드 (신규 추가)
    // ========================================
    if (currentCompany && typeof loadAndShowIndividualNotices === 'function') {
        loadAndShowIndividualNotices(currentCompany);
    }
    
    // 실시간 품목 데이터 로드
    loadCurrentCompanyItems();
}

// 현재 업체의 최신 품목 목록 로드
function loadCurrentCompanyItems() {
    if (!currentCompany) {
        document.getElementById('itemCheckboxes').innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #666;">업체정보를 찾을 수 없습니다.</div>';
        return;
    }
    
    // 업체명 정리
    const companyName = cleanCompanyName(currentCompany);
    
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
            // 최신 품목 목록 조회
            return fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'getCompanyItemRequestStatus',
                    companyId: data.companyId
                })
            });
        } else {
            throw new Error('업체정보 조회 실패: ' + data.message);
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 할당된 품목만 사용 (주문 가능한 품목)
            const assignedItems = data.assignedItems || [];
            companyItems = assignedItems.map(item => item.item_name);
            
            console.log('최신품목 로드 완료:', companyItems);
            
            // 체크박스 생성
            createItemCheckboxesWithData();
        } else {
            throw new Error('품목정보 조회 실패: ' + data.message);
        }
    })
    .catch(error => {
        console.error('품목로드 오류:', error);
        document.getElementById('itemCheckboxes').innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #666;">품목 호출중 오류가 발생했습니다.</div>';
    });
}

// 실제 체크박스 생성 함수
function createItemCheckboxesWithData() {
    const container = document.getElementById('itemCheckboxes');
    container.innerHTML = '';
    
    if (!companyItems || companyItems.length === 0) {
        container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #666;">등록된 품목이 없습니다.</div>';
        return;
    }
    
    // 차단 상태라면 체크박스는 생성하되 비활성화
    companyItems.forEach((item, index) => {
        // 품목 데이터가 객체인지 문자열인지 확인
        let itemName, itemDescription;
        if (typeof item === 'object' && item !== null) {
            itemName = item.name || item;
            itemDescription = item.description || '';
        } else {
            itemName = item;
            itemDescription = '';
        }
        
        if (itemName && itemName.trim()) {
            const checkboxItem = document.createElement('div');
            checkboxItem.className = 'checkbox-item';
            
            // 품목 설명이 있으면 표시 (괄호 포함)
            const labelContent = itemDescription 
                ? `<div class="item-name">${itemName}</div><div class="item-description">(${itemDescription})</div>`
                : itemName;
                
            checkboxItem.innerHTML = `
                <input type="checkbox" id="check_${index}" onchange="updateSelectedItems()" ${isOrderBlocked ? 'disabled' : ''}>
                <label for="check_${index}">${labelContent}</label>
            `;
            container.appendChild(checkboxItem);
        }
    });
    
    // 차단 상태 메시지 표시
    if (isOrderBlocked) {
        const warningDiv = document.createElement('div');
        warningDiv.className = 'order-blocked-warning';
        warningDiv.style.cssText = 'grid-column: 1 / -1; text-align: center; color: #e74c3c; font-weight: bold; padding: 10px; background: #ffeaa7; border-radius: 5px; margin-top: 10px;';
        warningDiv.innerHTML = `⚠️ 주문불가 (사유: ${blockReason})`;
        container.appendChild(warningDiv);
    }
    
    updateSelectedItems();
}

// 선택된 품목 업데이트 (차단 상태 확인 추가 - 수정됨: 중복 메시지 제거)
function updateSelectedItems() {
    selectedItems = [];
    const selectCompleteBtn = document.getElementById('selectCompleteBtn');
    
    companyItems.forEach((item, index) => {
        // 품목 데이터가 객체인지 문자열인지 확인
        let itemName;
        if (typeof item === 'object' && item !== null) {
            itemName = item.name || item;
        } else {
            itemName = item;
        }
        
        if (itemName && itemName.trim()) {
            const checkbox = document.getElementById(`check_${index}`);
            const checkboxItem = checkbox ? checkbox.closest('.checkbox-item') : null;
            
            if (checkbox && checkbox.checked) {
                selectedItems.push(itemName); // 품목명만 저장
                if (checkboxItem) checkboxItem.classList.add('selected');
            } else {
                if (checkboxItem) checkboxItem.classList.remove('selected');
            }
        }
    });
    
    if (selectCompleteBtn) {
        // 차단 상태면 버튼을 비활성화
        selectCompleteBtn.disabled = selectedItems.length === 0 || isOrderBlocked;
        
        // 품목이 선택되었을 때 안내 메시지 표시 (차단 메시지는 체크박스 영역에서만 표시)
        if (!isOrderBlocked && selectedItems.length > 0) {
            showMessage('orderMessage', `${selectedItems.length}개 품목이 선택되었습니다.`, 'info');
        }
        // 차단 상태일 때는 메시지를 표시하지 않음 (체크박스 영역의 경고로 충분)
    }
}

// 수량 입력 폼 표시 (차단 상태 확인 강화)
function showQuantityForm() {
    // 차단 상태 먼저 확인
    if (isOrderBlocked) {
        showMessage('orderMessage', `주문불가 (사유: ${blockReason})`, 'error');
        return;
    }
    
    if (selectedItems.length === 0) {
        showMessage('orderMessage', '주문할 품목을 선택하세요.', 'error');
        return;
    }
    
    // 서버에서 실시간 차단 상태 재확인
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkOrderBlock'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.blocked) {
            isOrderBlocked = true;
            blockReason = result.reason || '';
            showMessage('orderMessage', `주문불가 (사유: ${blockReason})`, 'error');
            return;
        }
        
        // 차단되지 않은 경우에만 수량 입력 폼 표시
        proceedToQuantityForm();
    })
    .catch(error => {
        console.error('차단 상태 확인 오류:', error);
        showMessage('orderMessage', '차단상태 확인중 오류가 발생했습니다.', 'error');
    });
}

// 수량 입력 폼 표시 실행
function proceedToQuantityForm() {
    document.getElementById('orderForm').classList.add('hidden');
    document.getElementById('quantityForm').classList.remove('hidden');
    
    const container = document.getElementById('quantityInputs');
    container.innerHTML = '';
    
    selectedItems.forEach((itemName, index) => {
        // 선택된 품목의 설명 찾기
        let itemDescription = '';
        const originalItem = companyItems.find(ci => {
            const name = typeof ci === 'object' && ci !== null ? ci.name : ci;
            return name === itemName;
        });
        
        if (originalItem && typeof originalItem === 'object' && originalItem.description) {
            itemDescription = originalItem.description;
        }
        
        const quantityItem = document.createElement('div');
        quantityItem.className = 'quantity-item';
        
        const itemNameHtml = itemDescription 
            ? `<div class="quantity-item-name">${itemName}</div><div class="quantity-item-description">(${itemDescription})</div>`
            : `<div class="quantity-item-name">${itemName}</div>`;
            
        quantityItem.innerHTML = `
            <div class="quantity-item-info">
                ${itemNameHtml}
            </div>
            <input type="number" class="quantity-input" min="1" max="999"
                   id="qty_${index}" placeholder="수량" ${isOrderBlocked ? 'disabled' : ''}>
        `;
        container.appendChild(quantityItem);
    });
    
    const firstInput = container.querySelector('.quantity-input');
    if (firstInput && !isOrderBlocked) {
        setTimeout(() => firstInput.focus(), 100);
    }
    
    // 안내 메시지 표시
    showMessage('quantityMessage', '수량입력은 필수입니다', 'info');
}

// 품목 선택으로 돌아가기
function backToItemSelection() {
    document.getElementById('quantityForm').classList.add('hidden');
    document.getElementById('orderForm').classList.remove('hidden');
}

// 수량 확인 (차단 상태 확인 추가)
function confirmQuantities() {
    // 차단 상태 먼저 확인
    if (isOrderBlocked) {
        showMessage('quantityMessage', `주문불가 (사유: ${blockReason})`, 'error');
        return;
    }
    
    orderItems = [];
    let hasError = false;
    
    selectedItems.forEach((item, index) => {
        const qtyInput = document.getElementById(`qty_${index}`);
        const quantity = parseInt(qtyInput.value) || 0;
        
        console.log(`품목: ${item}, 수량: ${quantity}`);
        
        if (quantity > 0) {
            orderItems.push({
                item: item,
                quantity: quantity
            });
        } else {
            hasError = true;
        }
    });
    
    console.log('orderItems:', orderItems);
    console.log('hasError:', hasError);
    
    if (hasError || orderItems.length === 0) {
        showMessage('quantityMessage', '수량입력은 필수입니다', 'error');
        return;
    }
    
    // 서버에서 실시간 차단 상태 재확인
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkOrderBlock'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.blocked) {
            isOrderBlocked = true;
            blockReason = result.reason || '';
            showMessage('quantityMessage', `주문불가 (사유: ${blockReason})`, 'error');
            return;
        }
        
        // 차단되지 않은 경우에만 확인 화면으로 이동
        proceedToConfirmForm();
    })
    .catch(error => {
        console.error('차단 상태 확인 오류:', error);
        showMessage('quantityMessage', '차단상태 확인중 오류가 발생했습니다.', 'error');
    });
}

// 확인 화면으로 이동 실행
function proceedToConfirmForm() {
    document.getElementById('quantityForm').classList.add('hidden');
    document.getElementById('confirmForm').classList.remove('hidden');
    updateSelectedItemsDisplay();
    
    const submitBtn = document.getElementById('submitOrderBtn');
    if (submitBtn) {
        submitBtn.disabled = isOrderBlocked;
    }
}

// 수량 입력으로 돌아가기
function backToQuantityInput() {
    document.getElementById('confirmForm').classList.add('hidden');
    document.getElementById('quantityForm').classList.remove('hidden');
}

// 선택된 품목 표시 업데이트
function updateSelectedItemsDisplay() {
    const listContainer = document.getElementById('selectedItemsList');
    listContainer.innerHTML = '';
    
    orderItems.forEach(order => {
        const selectedItem = document.createElement('div');
        selectedItem.className = 'selected-item';
        selectedItem.innerHTML = `
            <div>${order.item}</div>
            <div style="font-weight: bold; color: #f39c12;">${order.quantity}개</div>
        `;
        listContainer.appendChild(selectedItem);
    });
}

// 주문 제출 (차단 상태 확인 강화)
function submitOrder() {
    // 차단 상태 먼저 확인
    if (isOrderBlocked) {
        showMessage('confirmMessage', `주문불가 (사유: ${blockReason})`, 'error');
        return;
    }
    
    if (orderItems.length === 0) {
        showMessage('confirmMessage', '주문할 품목을 선택하고 수량을 입력하세요.', 'error');
        return;
    }
    
    const submitBtn = document.getElementById('submitOrderBtn');
    submitBtn.disabled = true;
    
    showLoading(true);
    
    const orderData = {
        companyName: currentCompany,
        orders: orderItems
    };
    
    console.log('주문제출:', orderData);
    
    // 서버로 주문 제출 (서버에서 차단 상태 재확인됨)
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
        console.log('주문결과:', result);
        
        // 서버에서 차단 응답이 온 경우
        if (result.blocked) {
            showLoading(false);
            isOrderBlocked = true;
            blockReason = result.reason || '';
            showMessage('confirmMessage', `주문불가 (사유: ${blockReason})`, 'error');
            submitBtn.disabled = false;
            return;
        }
        
        // 주문 시간 제한 체크
        if (result.orderTimeRestricted) {
            onOrderTimeRestricted(result);
        } else {
            onOrderSuccess(result);
        }
    })
    .catch(error => {
        console.error('주문오류:', error);
        onOrderError(error);
        submitBtn.disabled = false;
    });
}

// 주문 시간 제한 처리
function onOrderTimeRestricted(result) {
    showLoading(false);
    
    // 주문 불가 시간대로 전환
    hideAllForms();
    document.getElementById('orderRestrictedForm').classList.remove('hidden');
    
    // 다음 주문 시간 표시
    if (result.nextOrderTime) {
        const nextTimeElement = document.getElementById('nextRestrictedOrderTime');
        if (nextTimeElement) {
            nextTimeElement.textContent = formatDateTime(result.nextOrderTime);
        }
    }
    
    // 현재 시간 업데이트 시작
    startRestrictedTimeUpdate();
    
    // 에러 메시지 표시
    showMessage('orderStatusMessage', result.message, 'error');
}

// 주문 성공 처리 (차단 상태 초기화 포함 - 수정됨)
function onOrderSuccess(result) {
    showLoading(false);
    
    if (result && result.success) {
        // 차단 상태 초기화 (주문 성공 시)
        isOrderBlocked = false;
        blockReason = '';
        
        // 구글시트 관련 텍스트 제거 - 기본 메시지만 표시
        const cleanMessage = result.message.replace(/\s*\(구글시트.*?\)/, '');
        showMessage('confirmMessage', `${cleanMessage} (${result.timestamp})`, 'success');
        
        // 주문 데이터 저장 (주문조회에서 사용)
        currentOrderData = {
            companyName: currentCompany,
            orders: orderItems,
            timestamp: result.timestamp
        };
        
        // 체크박스 상태 초기화
        companyItems.forEach((item, index) => {
            if (item && typeof item === 'object' && item.name && item.name.trim()) {
                const checkbox = document.getElementById(`check_${index}`);
                if (checkbox) {
                    checkbox.checked = false;
                    const checkboxItem = checkbox.closest('.checkbox-item');
                    if (checkboxItem) {
                        checkboxItem.classList.remove('selected');
                    }
                }
            }
        });
        
        selectedItems = [];
        orderItems = [];
        
        // 2초 후 주문조회 화면으로 자동 이동
        setTimeout(() => {
            hideAllForms();
            document.getElementById('orderStatusForm').classList.remove('hidden');
            showOrderTab('today');
            loadTodayOrderStatus();
            
            // 성공 메시지 표시
            showMessage('orderStatusMessage', '주문완료 - (05:00까지 주문수정가능)', 'success');
            
        }, 2000);
        
    } else {
        showMessage('confirmMessage', result.message || '주문처리중 오류가 발생했습니다.', 'error');
        document.getElementById('submitOrderBtn').disabled = false;
    }
}

// 주문 에러 처리
function onOrderError(error) {
    showLoading(false);
    showMessage('confirmMessage', '주문처리중 오류가 발생했습니다.', 'error');
    document.getElementById('submitOrderBtn').disabled = false;
    console.error('Order error:', error);
}

// 주문하기 버튼 클릭 시 차단 상태 확인 (수정됨: 주문조회는 항상 허용)
function goToOrder() {
    // 차단 상태 확인
    if (isOrderBlocked) {
        showMessage('orderStatusMessage', `주문불가 (사유: ${blockReason})`, 'error');
        return;
    }
    
    // 시간 제한 확인
    if (!isOrderTimeAllowed()) {
        showMessage('orderStatusMessage', '지금은 주문처리 시간대(05:00~08:00)입니다.', 'error');
        return;
    }
    
    // 당일 주문 중복 확인
    console.log('당일 주문 중복 확인 중...');
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
        console.log('당일 주문 확인 결과:', result);
        
        // 당일 주문이 있는 경우 중복 주문 방지
        if (result.success && result.orders && result.orders.length > 0) {
            console.log('당일 주문 존재 - 중복 주문 방지');
            showMessage('orderStatusMessage', '이미 오늘 주문이 있습니다', 'error');
            return;
        }
        
        // 당일 주문이 없는 경우에만 차단 상태 재확인 후 주문 화면으로 이동
        console.log('당일 주문 없음 - 차단 상태 재확인 후 주문 화면 이동');
        return fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'checkOrderBlock'
            })
        });
    })
    .then(response => {
        if (!response) return; // 당일 주문이 있는 경우 위에서 return됨
        
        return response.json();
    })
    .then(result => {
        if (!result) return; // 당일 주문이 있는 경우 위에서 return됨
        
        if (result.blocked) {
            isOrderBlocked = true;
            blockReason = result.reason || '';
            showMessage('orderStatusMessage', `주문불가 (사유: ${blockReason})`, 'error');
            // 차단 UI는 표시하지 않음 - 주문조회는 계속 가능하도록
            return;
        }
        
        // 차단되지 않은 경우에만 주문 화면으로 이동
        hideAllForms();
        document.getElementById('orderForm').classList.remove('hidden');
        createItemCheckboxes();
    })
    .catch(error => {
        console.error('주문 화면 이동 오류:', error);
        showMessage('orderStatusMessage', '주문 화면 이동 중 오류가 발생했습니다.', 'error');
    });
}