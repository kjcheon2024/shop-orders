/**
 * admin-orders.js - 관리자 주문확인 탭 기능
 */

// 주문확인 탭 초기화
function initializeOrdersTab() {
    // 오늘 날짜로 초기화
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('orderDate').value = today;
    
    // 주문 날짜 범위 로드
    loadOrderDateRange();
    
    // 오늘 날짜의 주문 자동 로드
    loadOrdersByDate();
}

// 날짜별 주문 조회
function loadOrdersByDate() {
    const selectedDate = document.getElementById('orderDate').value;
    
    if (!selectedDate) {
        showAlert('날짜를 선택해주세요.', 'error');
        return;
    }
    
    // 로딩 표시
    document.getElementById('ordersContainer').innerHTML = '<div class="loading-message">주문 정보를 불러오는 중...</div>';
    document.getElementById('orderSummarySection').style.display = 'none';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getOrdersByDate&selectedDate=' + encodeURIComponent(selectedDate)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayOrdersByDate(data);
        } else {
            document.getElementById('ordersContainer').innerHTML = '<div class="error-message">' + escapeHtml(data.message) + '</div>';
            document.getElementById('orderSummarySection').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('주문 조회 오류:', error);
        document.getElementById('ordersContainer').innerHTML = '<div class="error-message">주문 조회 중 오류가 발생했습니다.</div>';
        document.getElementById('orderSummarySection').style.display = 'none';
    });
}

// 주문 데이터 표시
function displayOrdersByDate(data) {
    const container = document.getElementById('ordersContainer');
    const summarySection = document.getElementById('orderSummarySection');
    
    // 요약 정보 업데이트
    document.getElementById('totalCompanies').textContent = data.summary.total_companies + '개';
    document.getElementById('totalOrderCount').textContent = data.summary.total_orders + '개';
    document.getElementById('totalOrderQuantity').textContent = data.summary.total_quantity + '개';
    
    if (data.orders_by_company.length === 0) {
        container.innerHTML = '<div class="no-data">선택한 날짜에 주문이 없습니다.</div>';
        summarySection.style.display = 'none';
        return;
    }
    
    // 요약 섹션 표시
    summarySection.style.display = 'flex';
    
    let html = '';
    data.orders_by_company.forEach(companyData => {
        html += '<div class="order-company-card">' +
                    '<div class="company-header">' +
                        '<div class="company-name">' + escapeHtml(companyData.company_name) + '</div>' +
                        '<div class="order-time">' + formatOrderTime(companyData.order_time) + '</div>' +
                        '<button class="confirmation-btn unconfirmed" onclick="toggleConfirmation(this, \'' + escapeHtml(companyData.company_name) + '\')">확인안함</button>' +
                    '</div>' +
                    '<div class="company-summary">' +
                        '<span>품목 ' + companyData.total_items + '개 / 수량 ' + companyData.total_quantity + '개</span>' +
                    '</div>' +
                    '<div class="order-items-list">';
        
        companyData.orders.forEach(order => {
            html += '<div class="order-item-row">' +
                        '<span class="item-name">' + escapeHtml(order.item_name) + '</span>' +
                        '<span class="item-quantity">' + order.quantity + '</span>' +
                    '</div>';
        });
        
        html += '</div></div>';
    });
    
    container.innerHTML = html;
    
    // 확인안함 개수 업데이트
    updateUnconfirmedCount();
}

// 확인함 버튼 토글 함수
function toggleConfirmation(button, companyName) {
    if (button.classList.contains('confirmed')) {
        // 확인함 -> 확인안함으로 변경
        button.classList.remove('confirmed');
        button.classList.add('unconfirmed');
        button.textContent = '확인안함';
    } else {
        // 확인안함 -> 확인함으로 변경
        button.classList.remove('unconfirmed');
        button.classList.add('confirmed');
        button.textContent = '확인함';
    }
    
    // 확인안함 개수 업데이트
    updateUnconfirmedCount();
}

// 확인안함 개수 업데이트 함수
function updateUnconfirmedCount() {
    const unconfirmedButtons = document.querySelectorAll('.confirmation-btn.unconfirmed');
    const unconfirmedCount = unconfirmedButtons.length;
    document.getElementById('unconfirmedCount').textContent = unconfirmedCount + '개';
}

// 주문 시간 포맷팅
function formatOrderTime(orderTime) {
    if (!orderTime) return '';
    
    try {
        const date = new Date(orderTime);
        return date.toLocaleTimeString('ko-KR', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch (error) {
        return orderTime;
    }
}

// 주문 날짜 범위 로드
function loadOrderDateRange() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getOrderDateRange'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.total_days > 0) {
            const dateInput = document.getElementById('orderDate');
            if (dateInput) {
                dateInput.min = data.min_date;
                dateInput.max = data.max_date;
            }
        }
    })
    .catch(error => {
        console.error('주문 날짜 범위 조회 오류:', error);
    });
}

// 주문확인 탭이 활성화될 때 호출
document.addEventListener('DOMContentLoaded', function() {
    // showTab 함수가 'orders' 탭을 호출할 때 초기화
    const originalShowTab = window.showTab;
    window.showTab = function(tabName) {
        originalShowTab.call(this, tabName);
        
        if (tabName === 'orders') {
            setTimeout(() => {
                initializeOrdersTab();
            }, 100);
        }
    };
});