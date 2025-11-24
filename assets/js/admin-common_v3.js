/**
 * admin-common.js - 관리자 페이지 공통 기능
 */

// 탭 전환 기능
function showTab(tabName) {
    // 모든 탭 콘텐츠 숨기기
    document.querySelectorAll('.tab-content').forEach(el => {
        el.style.display = 'none';
    });
    
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('active');
    });
    
    // 선택된 탭 표시
    document.getElementById(tabName + '-tab').style.display = 'block';
    const activeTab = document.querySelector('[onclick="showTab(\'' + tabName + '\')"]');
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // 탭별 데이터 로드
    switch(tabName) {
        case 'categories':
            if (typeof loadCategories === 'function') {
                loadCategories();
            }
            break;
        case 'items':
            if (typeof loadItems === 'function') {
                loadItems();
            }
            if (typeof loadCategoriesForSelect === 'function') {
                loadCategoriesForSelect();
            }
            break;
        case 'assignments':
            if (typeof loadCompanies === 'function') {
                loadCompanies();
            }
            break;
        case 'orders':
            if (typeof initializeOrdersTab === 'function') {
                initializeOrdersTab();
            }
            break;
    }
}

// 알림 메시지 표시
function showAlert(message, type) {
    let alertContainer = document.getElementById('alertContainer');
    
    if (!alertContainer) {
        const container = document.querySelector('.container');
        alertContainer = document.createElement('div');
        alertContainer.id = 'alertContainer';
        if (container && container.children.length > 0) {
            container.insertBefore(alertContainer, container.children[1]);
        }
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.textContent = message;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alertDiv);
    
    if (type !== 'info') {
        setTimeout(() => {
            if (alertDiv && alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
}

// 통계 정보 업데이트
function updateStatistics() {
    const tabs = document.querySelectorAll('.tab-btn');
    
    tabs.forEach(tab => {
        let count = null; // 기본적으로 카운트 없음
        const tabText = tab.textContent || '';
        
        // 승인대기와 승인거부만 카운트 표시
        if (tabText.includes('승인대기')) {
            const tabContent = document.getElementById('pending-tab');
            if (tabContent) {
                // 신규업체 등록 승인대기 개수
                const companyRequests = tabContent.querySelectorAll('.request-card').length;
                
                // 품목 요청 승인대기 개수
                const itemRequests = tabContent.querySelectorAll('.item-request-card').length;
                
                // 합계 계산
                count = companyRequests + itemRequests;
            }
        } else if (tabText.includes('승인 거부')) {
            const tabContent = document.getElementById('rejected-tab');
            if (tabContent) {
                count = tabContent.querySelectorAll('.request-card').length;
            }
        }
        // 나머지 탭들은 모두 카운트 없음 (count = null)
        
        updateTabCount(tab, count);
    });
}

// 탭 카운트 업데이트 헬퍼 함수
function updateTabCount(tab, count) {
    const originalText = tab.textContent.split('(')[0].trim();
    if (count === null) {
        tab.textContent = originalText; // 카운트 없이 라벨만
    } else {
        tab.textContent = originalText + ' (' + count + ')';
    }
}

// HTML 이스케이프 함수
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// JavaScript용 이스케이프 함수
function escapeForJs(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\\/g, '\\\\');
}

// 알림 메시지 자동 숨김 초기화
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert && alert.style) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                
                setTimeout(() => {
                    if (alert && alert.style) {
                        alert.style.display = 'none';
                    }
                }, 500);
            }
        }, 5000);
        
        alert.addEventListener('click', function() {
            if (this && this.style) {
                this.style.display = 'none';
            }
        });
    });
}

// 모바일 반응형 처리
function handleMobileView() {
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        const tabs = document.querySelector('.tabs');
        if (tabs) {
            tabs.style.flexDirection = 'column';
        }
        
        const fileNames = document.querySelectorAll('.file-name');
        fileNames.forEach(fileName => {
            if (fileName && fileName.style) {
                fileName.style.wordBreak = 'break-all';
            }
        });
    }
}

// 공통 초기화 함수
function initializeCommon() {
    // 기본 탭 표시
    showTab('pending');
    
    // 알림 컨테이너 생성
    if (!document.getElementById('alertContainer')) {
        const container = document.querySelector('.container');
        if (container) {
            const alertContainer = document.createElement('div');
            alertContainer.id = 'alertContainer';
            if (container.children.length > 0) {
                container.insertBefore(alertContainer, container.children[1]);
            } else {
                container.appendChild(alertContainer);
            }
        }
    }
    
    // 모바일 뷰 처리
    handleMobileView();
    
    // 키보드 단축키 설정
    setupKeyboardShortcuts();
}

// 키보드 단축키 설정
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey) {
            switch(e.key) {
                case '1':
                    e.preventDefault();
                    showTab('pending');
                    break;
                case '2':
                    e.preventDefault();
                    showTab('approved');
                    break;
                case '3':
                    e.preventDefault();
                    showTab('rejected');
                    break;
                case '4':
                    e.preventDefault();
                    showTab('categories');
                    break;
                case '5':
                    e.preventDefault();
                    showTab('items');
                    break;
                case '6':
                    e.preventDefault();
                    showTab('assignments');
                    break;
            }
        }
    });
}

// 페이지 로드 시 공통 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeCommon();
});

// 페이지 로드 완료 후 초기화
window.addEventListener('load', function() {
    initializeAlerts();
    updateStatistics();
});

// 윈도우 리사이즈 이벤트
window.addEventListener('resize', handleMobileView);