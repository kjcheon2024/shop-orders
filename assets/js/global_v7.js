// Global variables
let currentCompany = '';
let companyItems = [];
let previewedCompany = '';
let selectedItems = [];
let orderItems = [];
let searchTimeout = null;
let companyNameCheckTimeout = null;
let selectedFile = null;
let currentOrderData = null;
let restrictedTimeInterval = null;
let isOrderBlocked = false;
let blockReason = '';

// Utility functions
function showMessage(elementId, message, type) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = message ? `<div class="message ${type}">${message.replace(/\n/g, '<br>')}</div>` : '';
    }
}

function showLoading(show) {
    const loadingElement = document.getElementById('loading');
    if (loadingElement) {
        loadingElement.classList.toggle('hidden', !show);
    }
}

function hideAllForms() {
    document.getElementById('mainTabs').classList.add('hidden');
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registrationForm').classList.add('hidden');
    document.getElementById('orderForm').classList.add('hidden');
    document.getElementById('quantityForm').classList.add('hidden');
    document.getElementById('confirmForm').classList.add('hidden');
    document.getElementById('orderStatusForm').classList.add('hidden');
    document.getElementById('orderRestrictedForm').classList.add('hidden');
    document.getElementById('itemManagementForm').classList.add('hidden');
    
    // 주문차단 알림도 숨기기
    const blockedNotice = document.getElementById('orderBlockedNotice');
    if (blockedNotice) {
        blockedNotice.remove();
    }
    
    // 시간 업데이트 인터벌 정리
    if (restrictedTimeInterval) {
        clearInterval(restrictedTimeInterval);
        restrictedTimeInterval = null;
    }
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return '';
    
    try {
        const date = new Date(dateTimeString);
        
        // 유효한 날짜인지 확인
        if (isNaN(date.getTime())) {
            return dateTimeString; // 원본 문자열 반환
        }
        
        const month = date.getMonth() + 1; // 0부터 시작하므로 +1
        const day = date.getDate();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${month}월${day}일 ${hours}:${minutes}`;
    } catch (error) {
        console.error('날짜 포맷팅 오류:', error);
        return dateTimeString; // 오류 시 원본 문자열 반환
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        
        if (isNaN(date.getTime())) {
            return dateString;
        }
        
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        const weekday = weekdays[date.getDay()];
        
        return `${month}월${day}일 (${weekday})`;
    } catch (error) {
        console.error('날짜 포맷팅 오류:', error);
        return dateString;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Tab switching function
function showTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    if (tabName === 'login') {
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('registrationForm').classList.add('hidden');
    } else if (tabName === 'register') {
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registrationForm').classList.remove('hidden');
    }
}