/**
 * admin-approval.js - 관리자 페이지 승인 관련 기능
 */

// 설정값과 함께 승인 처리
function approveWithSettings(requestId) {
    const itemGroup = document.getElementById('itemGroup_' + requestId).value;
    
    if (confirm('다음 설정으로 승인하시겠습니까?\n\n소속그룹: ' + itemGroup)) {
        showAlert('처리중...', 'info');
        
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=approveWithSettings&requestId=' + requestId + 
                  '&deliveryDay=요일미정' + 
                  '&itemGroup=' + encodeURIComponent(itemGroup)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('처리 중 오류가 발생했습니다.', 'error');
            console.error('Error:', error);
        });
    }
}

// 거부 확인 및 처리
function confirmReject(requestId, companyName) {
    const reason = prompt(companyName + ' 업체 등록을 거부하는 이유를 입력해주세요:', '관리자에 의한 거부');
    
    if (reason !== null && reason.trim() !== '') {
        showAlert('처리중...', 'info');
        
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=reject&requestId=' + requestId + '&reason=' + encodeURIComponent(reason)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('처리 중 오류가 발생했습니다.', 'error');
            console.error('Error:', error);
        });
    }
}

// 파일 다운로드 링크 초기화
function initializeDownloadLinks() {
    const downloadLinks = document.querySelectorAll('.download-btn');
    
    downloadLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const originalText = this.textContent;
            this.textContent = '다운로드중...';
            this.style.opacity = '0.6';
            this.style.pointerEvents = 'none';
            
            setTimeout(() => {
                this.textContent = originalText;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';
            }, 2000);
        });
    });
}

// 액션 버튼 초기화
function initializeActionButtons() {
    const oldStyleApproveButtons = document.querySelectorAll('a.btn-approve');
    
    oldStyleApproveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const requestCard = this.closest('.request-card');
            if (requestCard) {
                const h3 = requestCard.querySelector('h3');
                if (h3) {
                    const companyName = h3.textContent.trim();
                    const cleanCompanyName = companyName.replace(/대기중|승인완료|거부됨/g, '').trim();
                    
                    if (!confirm('"' + cleanCompanyName + '" 업체를 기본 설정으로 승인하시겠습니까?\n\n승인 후에는 해당 업체가 시스템을 이용할 수 있게 됩니다.')) {
                        e.preventDefault();
                    } else {
                        this.style.opacity = '0.6';
                        this.style.pointerEvents = 'none';
                        this.textContent = '처리중...';
                    }
                }
            }
        });
    });
}

// 승인 관련 초기화
function initializeApprovalFeatures() {
    initializeDownloadLinks();
    initializeActionButtons();
}

// DOM 로드 시 승인 기능 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeApprovalFeatures();
});