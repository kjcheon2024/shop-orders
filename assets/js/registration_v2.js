// 등록 관련 초기화
function initRegistration() {
    const companyNameInput = document.getElementById('regCompanyName');
    const fileInput = document.getElementById('regBusinessLicense');
    const registrationForm = document.getElementById('registrationFormElement');

    // 업체명 실시간 중복 체크
    if (companyNameInput) {
        companyNameInput.addEventListener('input', function() {
            const companyName = this.value.trim();
            const statusDiv = document.getElementById('companyNameStatus');
            
            if (companyNameCheckTimeout) {
                clearTimeout(companyNameCheckTimeout);
            }
            
            if (companyName.length < 2) {
                statusDiv.textContent = '';
                statusDiv.className = 'validation-status';
                return;
            }
            
            statusDiv.textContent = '확인중...';
            statusDiv.className = 'validation-status checking';
            
            companyNameCheckTimeout = setTimeout(() => {
                checkCompanyNameDuplicate(companyName);
            }, 300);
        });
    }

    // 파일 입력 이벤트 리스너
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }

    // 등록 폼 제출 이벤트
    if (registrationForm) {
        registrationForm.addEventListener('submit', handleRegistrationSubmit);
    }
}

// 업체명 중복 체크
function checkCompanyNameDuplicate(companyName) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'checkCompanyName',
            companyName: companyName
        })
    })
    .then(response => response.json())
    .then(result => {
        const statusDiv = document.getElementById('companyNameStatus');
        const currentValue = document.getElementById('regCompanyName').value.trim();
        
        if (currentValue !== companyName) {
            return;
        }
        
        if (result.duplicate) {
            statusDiv.textContent = '이미 사용중인 업체명입니다';
            statusDiv.className = 'validation-status unavailable';
        } else {
            statusDiv.textContent = '사용 가능한 업체명입니다';
            statusDiv.className = 'validation-status available';
        }
    })
    .catch(error => {
        console.error('업체명 확인오류:', error);
        const statusDiv = document.getElementById('companyNameStatus');
        statusDiv.textContent = '확인중 오류가 발생했습니다';
        statusDiv.className = 'validation-status unavailable';
    });
}

// 전화번호 하이픈 자동추가
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

var phonenumber = document.getElementById('regPhoneNumber');
phonenumber.onkeyup = function(event){
        event = event || window.event;
        var _val = this.value.trim();
        this.value = autoHypenPhone(_val) ;
}

// 파일 선택 처리
function handleFileSelect(event) {
    const file = event.target.files[0];
    const filePreview = document.getElementById('filePreview');
    const fileError = document.querySelector('.file-error');
    
    // 기존 에러 메시지 제거
    if (fileError) {
        fileError.remove();
    }
    
    if (!file) {
        selectedFile = null;
        filePreview.classList.add('hidden');
        return;
    }
    
    // 파일 검증
    const validationResult = validateFile(file);
    if (!validationResult.valid) {
        showFileError(validationResult.message);
        event.target.value = '';
        selectedFile = null;
        filePreview.classList.add('hidden');
        return;
    }
    
    selectedFile = file;
    showFilePreview(file);
}

// 파일 검증
function validateFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    // 파일 크기 확인
    if (file.size > maxSize) {
        return {
            valid: false,
            message: '파일크기는 5MB를 초과할 수 없습니다.'
        };
    }
    
    // MIME 타입 확인
    if (!allowedTypes.includes(file.type)) {
        return {
            valid: false,
            message: 'JPG,PNG,PDF만 업로드 가능합니다.'
        };
    }
    
    // 파일 확장자 확인
    const fileExtension = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: '올바르지 않은 파일 확장자입니다.'
        };
    }
    
    return { valid: true };
}

// 파일 미리보기 표시
function showFilePreview(file) {
    const filePreview = document.getElementById('filePreview');
    const fileName = filePreview.querySelector('.file-name');
    const fileSize = filePreview.querySelector('.file-size');
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    filePreview.classList.remove('hidden');
}

// 파일 에러 메시지 표시
function showFileError(message) {
    const fileContainer = document.querySelector('.file-upload-container');
    const existingError = document.querySelector('.file-error');
    
    if (existingError) {
        existingError.remove();
    }
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'file-error';
    errorDiv.textContent = message;
    
    fileContainer.appendChild(errorDiv);
}

// 파일 제거
function removeFile() {
    const fileInput = document.getElementById('regBusinessLicense');
    const filePreview = document.getElementById('filePreview');
    const fileError = document.querySelector('.file-error');
    
    fileInput.value = '';
    selectedFile = null;
    filePreview.classList.add('hidden');
    
    if (fileError) {
        fileError.remove();
    }
}

// 등록 폼 제출 처리 (메시지 유지)
function handleRegistrationSubmit(event) {
    event.preventDefault();
    
    if (!selectedFile) {
        showMessage('registrationMessage', '사업자등록증을 첨부해주세요.', 'error');
        return;
    }
    
    // address1과 address2를 결합해서 하나의 주소로 만들기
    const address1 = document.getElementById('regAddress1').value.trim();
    const address2 = document.getElementById('regAddress2').value.trim();
    const combinedAddress = address1 + (address2 ? ' ' + address2 : '');
    
    // 히든 필드에 결합된 주소 설정
    document.getElementById('regAddress').value = combinedAddress;
    
    const formData = new FormData(event.target);
    const registerBtn = document.getElementById('registerBtn');
    
    // 필수 항목 확인
    const requiredFields = ['companyName', 'password', 'contactPerson', 'phoneNumber'];
    for (const field of requiredFields) {
        if (!formData.get(field) || formData.get(field).trim() === '') {
            showMessage('registrationMessage', '필수항목을 모두 입력해주세요.', 'error');
            return;
        }
    }
    
    // 비밀번호 길이 확인
    if (formData.get('password').length < 4) {
        showMessage('registrationMessage', '비밀번호는 4자리 이상이어야 합니다.', 'error');
        return;
    }
    
    registerBtn.disabled = true;
    showLoading(true);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        
        if (result.success) {
            showMessage('registrationMessage', result.message, 'success');
            
            // 폼 초기화
            event.target.reset();
            removeFile();
            document.getElementById('companyNameStatus').textContent = '';
            
            // 주소 필드들 초기화
            document.getElementById('regZipCode').value = '';
            document.getElementById('regAddress1').value = '';
            document.getElementById('regAddress2').value = '';
            
            // 메시지를 유지하면서 탭 전환 (3초 후)
            setTimeout(() => {
                showTab('login');
                document.querySelector('.tab-btn').classList.add('active');
                document.querySelectorAll('.tab-btn')[1].classList.remove('active');
                // 메시지는 그대로 유지 - 자동으로 지우지 않음
            }, 3000);
        } else {
            showMessage('registrationMessage', result.message, 'error');
        }
        
        registerBtn.disabled = false;
    })
    .catch(error => {
        showLoading(false);
        console.error('등록오류:', error);
        showMessage('registrationMessage', '등록 처리중 오류가 발생했습니다.', 'error');
        registerBtn.disabled = false;
    });
}