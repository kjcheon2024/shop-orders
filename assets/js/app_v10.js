// 초기화 및 이벤트 바인딩
document.addEventListener('DOMContentLoaded', function() {
    // 인증 관련 초기화
    initPasswordSearch();
    
    // 등록 관련 초기화
    initRegistration();
    
    // 전역 키보드 이벤트
    initGlobalKeyboardEvents();
});

// 전역 키보드 이벤트 초기화
function initGlobalKeyboardEvents() {
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            // 로그인 폼이 보이는 경우
            if (!document.getElementById('loginForm').classList.contains('hidden')) {
                const loginBtn = document.getElementById('loginBtn');
                if (loginBtn && !loginBtn.disabled) {
                    e.preventDefault();
                    login();
                }
            }
            // 수량 입력 폼이 보이는 경우
            else if (!document.getElementById('quantityForm').classList.contains('hidden')) {
                e.preventDefault();
                confirmQuantities();
            }
            // 등록 폼이 보이는 경우 → Enter 기본 동작 허용
            else if (!document.getElementById('registrationForm').classList.contains('hidden')) {
                return; 
            }
        }
    });
}

// Daum 주소 검색 API
function findAddr() {
    new daum.Postcode({
        oncomplete: function(data) {
            var addr = '';
            var extraAddr = '';
            if (data.userSelectedType === 'R') {
                addr = data.roadAddress;
            } else {
                addr = data.jibunAddress;
            }
            if(data.userSelectedType === 'R'){
                if(data.bname !== '' && /[동|로|가]$/g.test(data.bname)){
                    extraAddr += data.bname;
                }
                if(data.buildingName !== '' && data.apartment === 'Y'){
                    extraAddr += (extraAddr !== '' ? ', ' + data.buildingName : data.buildingName);
                }
                if(extraAddr !== ''){
                    extraAddr = ' (' + extraAddr + ')';
                }
                document.getElementById("regAddress2").value = extraAddr;
            } else {
                document.getElementById("regAddress2").value = '';
            }
            document.getElementById('regZipCode').value = data.zonecode;
            document.getElementById("regAddress1").value = addr;
            document.getElementById("regAddress2").focus();
        }
    }).open();
}

// 주문조회에서 주문하기로 이동하는 함수 (시간 제한 체크 포함)
function goToOrder() {
    // 시간 제한 확인 (auth_v2.js의 함수 사용)
    if (!isOrderTimeAllowed()) {
        showOrderRestrictedForm();
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
        
        // 당일 주문이 없는 경우에만 주문 화면으로 이동
        console.log('당일 주문 없음 - 주문 화면으로 이동');
        hideAllForms();
        document.getElementById('orderForm').classList.remove('hidden');
        
        // 회사명 표시 업데이트
        if (currentCompany) {
            document.getElementById('currentCompany').textContent = currentCompany;
        }
        
        // 품목 체크박스 초기화
        createItemCheckboxes();
    })
    .catch(error => {
        console.error('주문 화면 이동 오류:', error);
        showMessage('orderStatusMessage', '주문 화면 이동 중 오류가 발생했습니다.', 'error');
    });
}

// 주문조회 화면 표시
function showOrderStatusForm() {
    hideAllForms();
    document.getElementById('orderStatusForm').classList.remove('hidden');
    
    // 기본적으로 오늘 주문 탭을 선택
    showOrderTab('today');
    loadTodayOrderStatus();
}

// 탭 전환 함수
function showTab(tabName) {
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.form-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 클릭된 탭 버튼 활성화
    const clickedBtn = document.querySelector(`[onclick="showTab('${tabName}')"]`);
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }
    
    if (tabName === 'login') {
        hideAllForms();
        document.getElementById('loginForm').classList.remove('hidden');
    } else if (tabName === 'register') {
        hideAllForms();
        document.getElementById('registrationForm').classList.remove('hidden');
    }
}

// 전역 유틸리티 함수들
function hideAllForms() {
    const forms = [
        'loginForm', 'registrationForm', 'orderForm', 'quantityForm', 
        'confirmForm', 'orderStatusForm', 'orderRestrictedForm', 'itemManagementForm'
    ];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.classList.add('hidden');
        }
    });
    
    // 주문차단 알림도 숨기기 (혹시 있다면)
    const blockedNotice = document.getElementById('orderBlockedNotice');
    if (blockedNotice) {
        blockedNotice.remove();
    }
}