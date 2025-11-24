/**
 * 관리자 비밀번호 변경 기능 (모달 버전)
 * admin-password.js
 */

// 모달 관련 함수들
function showPasswordChangeModal() {
    document.getElementById('passwordChangeModal').style.display = 'block';
    clearModalPasswordForm();
}

function closePasswordChangeModal() {
    document.getElementById('passwordChangeModal').style.display = 'none';
    clearModalPasswordForm();
}

// 모달용 비밀번호 강도 체크 함수
function checkModalPasswordStrength() {
    const password = document.getElementById('modalNewPassword').value;
    const indicator = document.getElementById('modalPasswordStrengthIndicator');
    const strengthBar = document.getElementById('modalStrengthBar');
    const strengthText = document.getElementById('modalStrengthText');
    const strengthErrors = document.getElementById('modalStrengthErrors');
    
    if (password.length === 0) {
        indicator.style.display = 'none';
        updateModalChangeButton();
        return;
    }
    
    indicator.style.display = 'block';
    
    // 서버에서 비밀번호 강도 검증
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'validatePasswordStrength',
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        // 강도 바 업데이트
        const strengthColors = {
            'weak': '#dc3545',
            'medium': '#ffc107',
            'strong': '#28a745',
            'very_strong': '#007bff'
        };
        
        const strengthLabels = {
            'weak': '약함',
            'medium': '보통',
            'strong': '강함',
            'very_strong': '매우 강함'
        };
        
        const strengthPercentages = {
            'weak': '25%',
            'medium': '50%',
            'strong': '75%',
            'very_strong': '100%'
        };
        
        const strength = data.strength;
        strengthBar.style.width = strengthPercentages[strength];
        strengthBar.style.backgroundColor = strengthColors[strength];
        strengthText.textContent = `비밀번호 강도: ${strengthLabels[strength]}`;
        strengthText.style.color = strengthColors[strength];
        
        // 오류 메시지 표시
        if (data.errors && data.errors.length > 0) {
            strengthErrors.innerHTML = '<div style="color: #dc3545; margin-top: 5px;"><strong>요구사항 미충족:</strong><br>• ' + 
                data.errors.join('<br>• ') + '</div>';
        } else {
            strengthErrors.innerHTML = '<div style="color: #28a745; margin-top: 5px;">✓ 모든 요구사항을 만족합니다.</div>';
        }
        
        // 전역 변수에 검증 결과 저장
        window.modalPasswordValidation = data;
        updateModalChangeButton();
        checkModalPasswordMatch(); // 비밀번호 일치 여부도 다시 확인
    })
    .catch(error => {
        console.error('비밀번호 강도 검증 오류:', error);
        strengthText.textContent = '강도 검증 중 오류 발생';
        strengthText.style.color = '#dc3545';
    });
}

// 모달용 비밀번호 일치 확인 함수
function checkModalPasswordMatch() {
    const newPassword = document.getElementById('modalNewPassword').value;
    const confirmPassword = document.getElementById('modalConfirmPassword').value;
    const matchIndicator = document.getElementById('modalPasswordMatchIndicator');
    
    if (confirmPassword.length === 0) {
        matchIndicator.style.display = 'none';
        updateModalChangeButton();
        return;
    }
    
    matchIndicator.style.display = 'block';
    
    if (newPassword === confirmPassword) {
        matchIndicator.innerHTML = '<span style="color: #28a745;">✓ 비밀번호가 일치합니다.</span>';
        window.modalPasswordsMatch = true;
    } else {
        matchIndicator.innerHTML = '<span style="color: #dc3545;">✗ 비밀번호가 일치하지 않습니다.</span>';
        window.modalPasswordsMatch = false;
    }
    
    updateModalChangeButton();
}

// 모달용 변경 버튼 활성화/비활성화 함수
function updateModalChangeButton() {
    const currentPassword = document.getElementById('modalCurrentPassword').value;
    const newPassword = document.getElementById('modalNewPassword').value;
    const confirmPassword = document.getElementById('modalConfirmPassword').value;
    const changeBtn = document.getElementById('modalChangePasswordBtn');
    
    const isValid = currentPassword.length > 0 && 
                   newPassword.length > 0 && 
                   confirmPassword.length > 0 &&
                   window.modalPasswordValidation && 
                   window.modalPasswordValidation.valid &&
                   window.modalPasswordsMatch === true;
    
    changeBtn.disabled = !isValid;
    changeBtn.style.opacity = isValid ? '1' : '0.6';
}

// 모달용 비밀번호 변경 함수
function changeModalPassword() {
    const currentPassword = document.getElementById('modalCurrentPassword').value;
    const newPassword = document.getElementById('modalNewPassword').value;
    const confirmPassword = document.getElementById('modalConfirmPassword').value;
    const changeBtn = document.getElementById('modalChangePasswordBtn');
    
    // 최종 검증
    if (!currentPassword || !newPassword || !confirmPassword) {
        showAlert('모든 필드를 입력해주세요.', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showAlert('새 비밀번호와 확인 비밀번호가 일치하지 않습니다.', 'error');
        return;
    }
    
    if (!window.modalPasswordValidation || !window.modalPasswordValidation.valid) {
        showAlert('비밀번호 요구사항을 만족하지 않습니다.', 'error');
        return;
    }
    
    // 확인 대화상자
    if (!confirm('비밀번호를 변경하시겠습니까?\n\n변경 후 자동으로 로그아웃되어 다시 로그인해야 합니다.')) {
        return;
    }
    
    // 버튼 비활성화 및 로딩 표시
    changeBtn.disabled = true;
    changeBtn.textContent = '변경 중...';
    
    // 서버에 비밀번호 변경 요청
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'changeAdminPassword',
            currentPassword: currentPassword,
            newPassword: newPassword,
            confirmPassword: confirmPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('비밀번호가 성공적으로 변경되었습니다.\n\n새 비밀번호로 다시 로그인해주세요.', 'success');
            
            // 모달 닫기
            closePasswordChangeModal();
            
            // 3초 후 로그아웃
            setTimeout(() => {
                window.location.href = 'admin.php?logout=1';
            }, 3000);
        } else {
            showAlert('비밀번호 변경에 실패했습니다.\n\n' + (data.message || '알 수 없는 오류가 발생했습니다.'), 'error');
            
            // 오류 세부사항이 있으면 표시
            if (data.errors && data.errors.length > 0) {
                const errorList = data.errors.join('\n• ');
                showAlert('비밀번호 요구사항:\n• ' + errorList, 'warning');
            }
        }
    })
    .catch(error => {
        console.error('비밀번호 변경 오류:', error);
        showAlert('비밀번호 변경 중 네트워크 오류가 발생했습니다.', 'error');
    })
    .finally(() => {
        // 버튼 상태 복원
        changeBtn.disabled = false;
        changeBtn.textContent = '비밀번호 변경';
    });
}

// 모달용 폼 초기화 함수
function clearModalPasswordForm() {
    document.getElementById('modalCurrentPassword').value = '';
    document.getElementById('modalNewPassword').value = '';
    document.getElementById('modalConfirmPassword').value = '';
    
    document.getElementById('modalPasswordStrengthIndicator').style.display = 'none';
    document.getElementById('modalPasswordMatchIndicator').style.display = 'none';
    
    // 전역 변수 초기화
    window.modalPasswordValidation = null;
    window.modalPasswordsMatch = false;
    
    updateModalChangeButton();
}

// 기존 탭용 비밀번호 강도 체크 함수 (호환성 유지)
function checkPasswordStrength() {
    const password = document.getElementById('newPassword').value;
    const indicator = document.getElementById('passwordStrengthIndicator');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const strengthErrors = document.getElementById('strengthErrors');
    
    if (password.length === 0) {
        indicator.style.display = 'none';
        updateChangeButton();
        return;
    }
    
    indicator.style.display = 'block';
    
    // 서버에서 비밀번호 강도 검증
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'validatePasswordStrength',
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        // 강도 바 업데이트
        const strengthColors = {
            'weak': '#dc3545',
            'medium': '#ffc107',
            'strong': '#28a745',
            'very_strong': '#007bff'
        };
        
        const strengthLabels = {
            'weak': '약함',
            'medium': '보통',
            'strong': '강함',
            'very_strong': '매우 강함'
        };
        
        const strengthPercentages = {
            'weak': '25%',
            'medium': '50%',
            'strong': '75%',
            'very_strong': '100%'
        };
        
        const strength = data.strength;
        strengthBar.style.width = strengthPercentages[strength];
        strengthBar.style.backgroundColor = strengthColors[strength];
        strengthText.textContent = `비밀번호 강도: ${strengthLabels[strength]}`;
        strengthText.style.color = strengthColors[strength];
        
        // 오류 메시지 표시
        if (data.errors && data.errors.length > 0) {
            strengthErrors.innerHTML = '<div style="color: #dc3545; margin-top: 5px;"><strong>요구사항 미충족:</strong><br>• ' + 
                data.errors.join('<br>• ') + '</div>';
        } else {
            strengthErrors.innerHTML = '<div style="color: #28a745; margin-top: 5px;">✓ 모든 요구사항을 만족합니다.</div>';
        }
        
        // 전역 변수에 검증 결과 저장
        window.passwordValidation = data;
        updateChangeButton();
        checkPasswordMatch(); // 비밀번호 일치 여부도 다시 확인
    })
    .catch(error => {
        console.error('비밀번호 강도 검증 오류:', error);
        strengthText.textContent = '강도 검증 중 오류 발생';
        strengthText.style.color = '#dc3545';
    });
}

// 비밀번호 일치 확인 함수
function checkPasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchIndicator = document.getElementById('passwordMatchIndicator');
    
    if (confirmPassword.length === 0) {
        matchIndicator.style.display = 'none';
        updateChangeButton();
        return;
    }
    
    matchIndicator.style.display = 'block';
    
    if (newPassword === confirmPassword) {
        matchIndicator.innerHTML = '<span style="color: #28a745;">✓ 비밀번호가 일치합니다.</span>';
        window.passwordsMatch = true;
    } else {
        matchIndicator.innerHTML = '<span style="color: #dc3545;">✗ 비밀번호가 일치하지 않습니다.</span>';
        window.passwordsMatch = false;
    }
    
    updateChangeButton();
}

// 변경 버튼 활성화/비활성화 함수
function updateChangeButton() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const changeBtn = document.getElementById('changePasswordBtn');
    
    const isValid = currentPassword.length > 0 && 
                   newPassword.length > 0 && 
                   confirmPassword.length > 0 &&
                   window.passwordValidation && 
                   window.passwordValidation.valid &&
                   window.passwordsMatch === true;
    
    changeBtn.disabled = !isValid;
    changeBtn.style.opacity = isValid ? '1' : '0.6';
}

// 비밀번호 변경 함수
function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const changeBtn = document.getElementById('changePasswordBtn');
    
    // 최종 검증
    if (!currentPassword || !newPassword || !confirmPassword) {
        showAlert('모든 필드를 입력해주세요.', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showAlert('새 비밀번호와 확인 비밀번호가 일치하지 않습니다.', 'error');
        return;
    }
    
    if (!window.passwordValidation || !window.passwordValidation.valid) {
        showAlert('비밀번호 요구사항을 만족하지 않습니다.', 'error');
        return;
    }
    
    // 확인 대화상자
    if (!confirm('비밀번호를 변경하시겠습니까?\n\n변경 후 자동으로 로그아웃되어 다시 로그인해야 합니다.')) {
        return;
    }
    
    // 버튼 비활성화 및 로딩 표시
    changeBtn.disabled = true;
    changeBtn.textContent = '변경 중...';
    
    // 서버에 비밀번호 변경 요청
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'changeAdminPassword',
            currentPassword: currentPassword,
            newPassword: newPassword,
            confirmPassword: confirmPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('비밀번호가 성공적으로 변경되었습니다.\n\n새 비밀번호로 다시 로그인해주세요.', 'success');
            
            // 3초 후 로그아웃
            setTimeout(() => {
                window.location.href = 'admin.php?logout=1';
            }, 3000);
        } else {
            showAlert('비밀번호 변경에 실패했습니다.\n\n' + (data.message || '알 수 없는 오류가 발생했습니다.'), 'error');
            
            // 오류 세부사항이 있으면 표시
            if (data.errors && data.errors.length > 0) {
                const errorList = data.errors.join('\n• ');
                showAlert('비밀번호 요구사항:\n• ' + errorList, 'warning');
            }
        }
    })
    .catch(error => {
        console.error('비밀번호 변경 오류:', error);
        showAlert('비밀번호 변경 중 네트워크 오류가 발생했습니다.', 'error');
    })
    .finally(() => {
        // 버튼 상태 복원
        changeBtn.disabled = false;
        changeBtn.textContent = '비밀번호 변경';
    });
}

// 폼 초기화 함수
function clearPasswordForm() {
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    
    document.getElementById('passwordStrengthIndicator').style.display = 'none';
    document.getElementById('passwordMatchIndicator').style.display = 'none';
    
    // 전역 변수 초기화
    window.passwordValidation = null;
    window.passwordsMatch = false;
    
    updateChangeButton();
}

// 현재 비밀번호 입력 시 버튼 상태 업데이트
document.addEventListener('DOMContentLoaded', function() {
    // 기존 탭용 (호환성 유지)
    const currentPasswordInput = document.getElementById('currentPassword');
    if (currentPasswordInput) {
        currentPasswordInput.addEventListener('input', updateChangeButton);
    }
    
    // 모달용
    const modalCurrentPasswordInput = document.getElementById('modalCurrentPassword');
    if (modalCurrentPasswordInput) {
        modalCurrentPasswordInput.addEventListener('input', updateModalChangeButton);
    }
});

// CSS 스타일 추가
const style = document.createElement('style');
style.textContent = `
    .password-requirements {
        margin-top: 8px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
        border-left: 4px solid #007bff;
    }
    
    .strength-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .strength-fill {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s ease;
        border-radius: 4px;
    }
    
    .strength-text {
        font-size: 14px;
        font-weight: bold;
        margin-top: 5px;
    }
    
    .strength-errors {
        font-size: 13px;
        line-height: 1.4;
    }
    
    .password-change-buttons {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
    
    .password-change-buttons .btn {
        min-width: 120px;
    }
    
    #changePasswordBtn:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
`;
document.head.appendChild(style);
