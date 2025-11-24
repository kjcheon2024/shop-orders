// 로그인 비밀번호 실시간 검색 초기화
function initPasswordSearch() {
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value.trim();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
                searchTimeout = null;
            }
            
            if (password.length > 0) {
                resetPreview();
                
                searchTimeout = setTimeout(() => {
                    console.log('비밀번호 검색시작:', password);
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'findCompanyByPassword',
                            password: password
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (passwordInput.value.trim() === password) {
                            console.log('검색결과:', result);
                            onCompanyPreview(result);
                        }
                    })
                    .catch(error => {
                        if (passwordInput.value.trim() === password) {
                            console.error('검색오류:', error);
                            onCompanyPreviewError(error);
                        }
                    });
                }, 200);
            } else {
                resetPreview();
            }
        });

        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const loginBtn = document.getElementById('loginBtn');
                if (loginBtn && !loginBtn.disabled && previewedCompany) {
                    login();
                }
            }
        });
    }
}

// 미리보기 초기화
function resetPreview() {
    const companyPreview = document.getElementById('companyPreview');
    const loginBtn = document.getElementById('loginBtn');
    
    companyPreview.classList.add('hidden');
    loginBtn.disabled = true;
    previewedCompany = '';
}

// 업체 미리보기
function onCompanyPreview(result) {
    const companyPreview = document.getElementById('companyPreview');
    const previewCompanyName = document.getElementById('previewCompanyName');
    const loginBtn = document.getElementById('loginBtn');
    
    try {
        if (result && result.success && result.companyName) {
            previewedCompany = result.companyName;
            companyItems = result.items || [];
            
            previewCompanyName.textContent = result.companyName;
            companyPreview.classList.remove('hidden');
            loginBtn.disabled = false;
            
            showMessage('loginMessage', '', '');
            console.log('미리보기 성공:', result.companyName, '품목수:', companyItems.length);
        } else {
            console.log('업체 없음:', result);
            resetPreview();
        }
    } catch (error) {
        console.error('미리보기 처리오류:', error);
        resetPreview();
    }
}

// 업체 미리보기 에러
function onCompanyPreviewError(error) {
    console.error('미리보기 오류:', error);
    resetPreview();
}

// 로그인
function login() {
    const password = document.getElementById('password').value.trim();
    
    if (!password) {
        showMessage('loginMessage', '비밀번호를 입력하세요.', 'error');
        return;
    }
    
    if (!previewedCompany) {
        showMessage('loginMessage', '올바른 비밀번호를 입력하세요.', 'error');
        return;
    }
    
    if (companyItems.length > 0) {
        console.log('캐시된 데이터로 즉시 로그인');
        onLoginSuccess({
            success: true,
            companyName: previewedCompany,
            items: companyItems
        });
        return;
    }
    
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = true;
    
    showLoading(true);
    
    console.log('서버에서 최종 로그인 처리');
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'findCompanyByPassword',
            password: password
        })
    })
    .then(response => response.json())
    .then(result => {
        onLoginSuccess(result);
    })
    .catch(error => {
        console.error('로그인 오류:', error);
        onLoginError(error);
        loginBtn.disabled = false;
    });
}

// 로그인 성공 처리 (공지 조회 추가 - 수정된 부분)
function onLoginSuccess(result) {
    showLoading(false);
    
    console.log('로그인 성공:', result);
    
    try {
        if (result && result.success && result.companyName && result.items) {
            currentCompany = result.companyName;
            companyItems = result.items || [];
            
            // 세션 스토리지에도 업체명 저장 (품목관리에서 사용)
            sessionStorage.setItem('company_name', result.companyName);
            
            // 모든 업체명 표시 업데이트
            document.getElementById('currentCompany').textContent = result.companyName;
            document.getElementById('currentCompany2').textContent = result.companyName;
            document.getElementById('currentCompany3').textContent = result.companyName;
            document.getElementById('currentCompany4').textContent = result.companyName;
            document.getElementById('currentRestrictedCompany').textContent = result.companyName;
            
            // 로그인 후 탭 버튼 숨기기
            const mainTabs = document.getElementById('mainTabs');
            if (mainTabs) {
                mainTabs.classList.add('hidden');
            }
            
            console.log('로그인 완료, 품목수:', companyItems.length);
            console.log('서버 응답 orderTimeAllowed:', result.orderTimeAllowed);
            console.log('서버 응답 orderBlocked:', result.orderBlocked);
            console.log('서버 응답 blockReason:', result.blockReason);
            
            // ========================================
            // 공지 조회 (신규 추가) - 로그인 성공 직후
            // ========================================
            if (typeof checkAndShowGlobalNotices === 'function') {
                checkAndShowGlobalNotices();
            }
            
            // 주문차단 상태 확인 (최우선)
            if (result.orderBlocked === true) {
                console.log('주문 차단 감지 - 차단 화면 표시');
                isOrderBlocked = true;
                blockReason = result.blockReason || '주문이 차단되었습니다.';
                showOrderBlockedForm();
                return;
            }
            
            // 차단되지 않은 경우 시간 체크
            const clientTimeCheck = isOrderTimeAllowed();
            console.log('클라이언트 시간 체크:', clientTimeCheck);
            
            // 서버 응답과 클라이언트 체크 모두 확인
            if (result.orderTimeAllowed === false || !clientTimeCheck) {
                // 주문 불가 시간대 - 제한 화면으로 이동
                console.log('주문 불가 시간대 감지 - 제한 화면 표시');
                showOrderRestrictedForm(result);
            } else {
                // 주문 가능 시간대 - 기존 로직대로 진행
                console.log('주문 가능 시간대 - 정상 진행');
                checkTodayOrderAndRedirect();
            }
            
        } else {
            console.log('로그인 실패:', result);
            showMessage('loginMessage', result.message || '로그인에 실패했습니다.', 'error');
            document.getElementById('loginBtn').disabled = false;
        }
    } catch (error) {
        console.error('로그인 처리오류:', error);
        showMessage('loginMessage', '로그인 처리중 오류가 발생했습니다.', 'error');
        document.getElementById('loginBtn').disabled = false;
    }
}

// 주문차단 화면 표시 (새로 추가된 함수)
function showOrderBlockedForm() {
    console.log('주문차단 화면 표시');
    
    hideAllForms();
    
    // 차단 알림 생성
    const container = document.querySelector('.container');
    const mainTabs = container.querySelector('#mainTabs');
    
    // 기존 차단 알림 제거
    const existingNotice = document.getElementById('orderBlockedNotice');
    if (existingNotice) {
        existingNotice.remove();
    }
    
    // 새 차단 알림 생성
    const blockedDiv = document.createElement('div');
    blockedDiv.id = 'orderBlockedNotice';
    blockedDiv.className = 'order-blocked-notice';
    blockedDiv.innerHTML = `
        <div class="alert alert-error">
            <h3>⚠️ 온라인 주문불가</h3>
            <p><strong>사유:</strong> ${blockReason}</p>
            <p>관리자에게 문의하세요</p>
        </div>
        <div class="blocked-actions">
            <button class="logout-btn" onclick="logout()">로그아웃</button>
            <div class="nav-buttons-container">
                <button class="nav-btn-item-management" onclick="showItemManagementForm()">품목관리</button>
                <button class="nav-btn-order-status" onclick="showOrderStatusForm()">주문조회</button>
            </div>
        </div>
    `;
    
    // 메인 탭 다음에 추가
    if (mainTabs && mainTabs.nextSibling) {
        container.insertBefore(blockedDiv, mainTabs.nextSibling);
    } else {
        container.appendChild(blockedDiv);
    }
    
    // 전역 차단 상태 설정
    isOrderBlocked = true;
}

// 클라이언트 시간 체크 함수
function isOrderTimeAllowed() {
    const now = new Date();
    const currentHour = now.getHours();
    
    // 05:00~08:00은 주문 불가 시간
    const timeAllowed = !(currentHour >= 5 && currentHour < 8);
    console.log('현재 시간:', currentHour + '시, 주문 가능:', timeAllowed);
    
    return timeAllowed;
}

// 로그인 에러 처리
function onLoginError(error) {
    showLoading(false);
    showMessage('loginMessage', '로그인에 오류가 발생했습니다.', 'error');
    console.error('Login error:', error);
}

// 당일 주문 여부 확인 후 적절한 화면으로 이동 (차단 확인 추가)
function checkTodayOrderAndRedirect() {
    console.log('당일주문 여부 확인 중...');
    
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
        console.log('당일주문 확인결과:', result);
        
        // 서버에서 차단 응답이 온 경우
        if (result.blocked) {
            console.log('서버에서 차단 응답 - 차단 화면 표시');
            isOrderBlocked = true;
            blockReason = result.reason || '관리자에 의해 주문이 차단되었습니다.';
            showOrderBlockedForm();
            return;
        }
        
        hideAllForms();
        
        if (result.success && result.orders && result.orders.length > 0) {
            // 당일 주문이 있는 경우 - 주문조회 화면으로 이동
            console.log('당일주문 존재 - 주문조회 화면으로 이동');
            document.getElementById('orderStatusForm').classList.remove('hidden');
            showOrderTab('today');
            loadTodayOrderStatus();
            
            // 환영 메시지 표시 (사라지지 않음)
            showMessage('orderStatusMessage', '오늘 주문이 있습니다', 'info');
            
        } else {
            // 당일 주문이 없는 경우 - goToOrder 함수를 사용하여 일관된 로직 적용
            console.log('당일주문 없음 - goToOrder 함수로 주문 화면 이동');
            if (typeof goToOrder === 'function') {
                goToOrder();
            } else {
                console.log('goToOrder 함수가 아직 로드되지 않음, 기본 화면으로 이동');
                hideAllForms();
                document.getElementById('orderForm').classList.remove('hidden');
            }
        }
    })
    .catch(error => {
        console.error('당일주문 확인 오류:', error);
        // 오류 발생 시에도 goToOrder 함수를 사용하여 일관된 로직 적용
        if (typeof goToOrder === 'function') {
            goToOrder();
        } else {
            console.log('goToOrder 함수가 아직 로드되지 않음, 기본 화면으로 이동');
            hideAllForms();
            document.getElementById('orderForm').classList.remove('hidden');
        }
        
        console.log('당일주문 확인실패 - 기본 화면으로 이동');
    });
}

// 로그아웃
function logout() {
    currentCompany = '';
    companyItems = [];
    previewedCompany = '';
    selectedItems = [];
    orderItems = [];
    selectedFile = null;
    currentOrderData = null;
    
    // 차단 상태 초기화
    isOrderBlocked = false;
    blockReason = '';
    
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
    
    // 시간 업데이트 인터벌 정리
    if (restrictedTimeInterval) {
        clearInterval(restrictedTimeInterval);
        restrictedTimeInterval = null;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'logout'
        })
    });
    
    document.getElementById('mainTabs').classList.remove('hidden');
    document.getElementById('loginForm').classList.remove('hidden');
    document.getElementById('registrationForm').classList.add('hidden');
    document.getElementById('orderForm').classList.add('hidden');
    document.getElementById('quantityForm').classList.add('hidden');
    document.getElementById('confirmForm').classList.add('hidden');
    document.getElementById('orderStatusForm').classList.add('hidden');
    document.getElementById('orderRestrictedForm').classList.add('hidden');
    
    // 차단 알림 제거
    const blockedNotice = document.getElementById('orderBlockedNotice');
    if (blockedNotice) {
        blockedNotice.remove();
    }
    
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.tab-btn').classList.add('active');
    
    document.getElementById('password').value = '';
    resetPreview();
    showMessage('loginMessage', '', '');
    showMessage('orderMessage', '', '');
    showMessage('quantityMessage', '', '');
    showMessage('confirmMessage', '', '');
    showMessage('orderStatusMessage', '', '');
}

// 주문조회 화면 표시 (차단 시에도 사용 가능)
function showOrderStatusForm() {
    hideAllForms();
    
    // 차단 알림은 유지하되 주문조회 화면 표시
    const blockedNotice = document.getElementById('orderBlockedNotice');
    if (blockedNotice) {
        blockedNotice.style.display = 'block';
    }
    
    document.getElementById('orderStatusForm').classList.remove('hidden');
    
    // 기본적으로 오늘 주문 탭을 선택
    showOrderTab('today');
    loadTodayOrderStatus();
}

// 임시: time-restriction 함수들 (기존 유지)
function showOrderRestrictedForm(result) {
    console.log('주문 불가 시간대 - 제한 화면 표시');
    
    hideAllForms();
    document.getElementById('orderRestrictedForm').classList.remove('hidden');
    
    if (result && result.nextOrderTime) {
        document.getElementById('nextRestrictedOrderTime').textContent = formatDateTime(result.nextOrderTime);
    }
    
    startRestrictedTimeUpdate();
    
    showMessage('orderStatusMessage', '현재는 주문 처리 시간대(05:00~08:00)입니다. 주문조회만 가능합니다.', 'info');
}

function startRestrictedTimeUpdate() {
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                          now.getMinutes().toString().padStart(2, '0');
        
        const currentTimeElement = document.getElementById('currentRestrictedTime');
        if (currentTimeElement) {
            currentTimeElement.textContent = timeString;
        }
    }
    
    updateCurrentTime();
    
    if (window.restrictedTimeInterval) {
        clearInterval(window.restrictedTimeInterval);
    }
    window.restrictedTimeInterval = setInterval(updateCurrentTime, 60000);
}