// 담당자변경 모달 표시
function showProfileEditModal() {
    if (!currentCompany) {
        alert('로그인 정보가 없습니다.');
        return;
    }
    
    const modal = document.getElementById('profileEditModal');
    modal.classList.remove('hidden');
    
    // 현재 업체 정보 로드
    loadCurrentProfile();
}

// 담당자변경 모달 닫기
function closeProfileEditModal() {
    const modal = document.getElementById('profileEditModal');
    modal.classList.add('hidden');
    
    // 폼 초기화
    document.getElementById('profileEditForm').reset();
}

// 현재 업체 프로필 정보 로드
function loadCurrentProfile() {
    if (!currentCompany) return;
    
    showLoading(true);
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'getCompanyProfile',
            companyName: currentCompany
        })
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        
        if (result.success) {
            // 폼에 현재 정보 채우기
            document.getElementById('editPassword').value = result.data.password || '';
            document.getElementById('editContactPerson').value = result.data.contact_person || '';
            document.getElementById('editPhoneNumber').value = result.data.phone_number || '';
        } else {
            alert('프로필 정보를 불러오는 중 오류가 발생했습니다: ' + result.message);
            closeProfileEditModal();
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('프로필 로드 오류:', error);
        alert('프로필 정보를 불러오는 중 오류가 발생했습니다.');
        closeProfileEditModal();
    });
}

// 프로필 변경사항 저장
function saveProfileChanges() {
    const password = document.getElementById('editPassword').value.trim();
    const contactPerson = document.getElementById('editContactPerson').value.trim();
    const phoneNumber = document.getElementById('editPhoneNumber').value.trim();
    
    // 입력값 검증
    if (!password) {
        alert('비밀번호를 입력해주세요.');
        document.getElementById('editPassword').focus();
        return;
    }
    
    if (password.length < 4) {
        alert('비밀번호는 4자리 이상이어야 합니다.');
        document.getElementById('editPassword').focus();
        return;
    }
    
    if (!contactPerson) {
        alert('담당자명을 입력해주세요.');
        document.getElementById('editContactPerson').focus();
        return;
    }
    
    if (!phoneNumber) {
        alert('전화번호를 입력해주세요.');
        document.getElementById('editPhoneNumber').focus();
        return;
    }
    
    // 전화번호 형식 검증
    if (!validatePhoneNumber(phoneNumber)) {
        alert('올바른 전화번호 형식을 입력해주세요.');
        document.getElementById('editPhoneNumber').focus();
        return;
    }
    
    if (confirm('담당자 정보를 수정하시겠습니까?')) {
        showLoading(true);
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'updateCompanyProfile',
                companyName: currentCompany,
                password: password,
                contactPerson: contactPerson,
                phoneNumber: phoneNumber
            })
        })
        .then(response => response.json())
        .then(result => {
            showLoading(false);
            
            if (result.success) {
                alert('담당자 정보가 성공적으로 수정되었습니다.');
                closeProfileEditModal();
                
                // 필요시 캐시 갱신 또는 재로그인 안내
                if (result.requireRelogin) {
                    if (confirm('비밀번호가 변경되어 다시 로그인해야 합니다. 로그인 화면으로 이동하시겠습니까?')) {
                        logout();
                    }
                }
            } else {
                alert('수정 중 오류가 발생했습니다: ' + result.message);
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('프로필 수정 오류:', error);
            alert('수정 중 오류가 발생했습니다.');
        });
    }
}

// 전화번호 하이픈 자동입력
function autoHypenPhone(str){
            str = str.replace(/[^0-9]/g, '');
            var tmp = '';
            if( str.length < 4){
                return str;
            }else if(str.length < 7){
                tmp += str.substr(0, 3);
                tmp += '-';
                tmp += str.substr(3);
                return tmp;
            }else if(str.length < 11){
                tmp += str.substr(0, 3);
                tmp += '-';
                tmp += str.substr(3, 3);
                tmp += '-';
                tmp += str.substr(6);
                return tmp;
            }else{              
                tmp += str.substr(0, 3);
                tmp += '-';
                tmp += str.substr(3, 4);
                tmp += '-';
                tmp += str.substr(7);
                return tmp;
            }
            return str;
        }

var phonenumber = document.getElementById('editPhoneNumber');
phonenumber.onkeyup = function(event){
        event = event || window.event;
        var _val = this.value.trim();
        this.value = autoHypenPhone(_val) ;
}

// 전화번호 형식 검증
function validatePhoneNumber(phoneNumber) {
    // 숫자, 하이픈, 공백, 괄호, + 기호만 허용
    const phoneRegex = /^[0-9-+\s()]+$/;
    return phoneRegex.test(phoneNumber) && phoneNumber.length >= 10;
}

// 모달 외부 클릭시 닫기
document.addEventListener('click', function(event) {
    const modal = document.getElementById('profileEditModal');
    if (modal && event.target === modal) {
        closeProfileEditModal();
    }
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('profileEditModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeProfileEditModal();
        }
    }
});