// 주문 불가 시간대 화면 표시
function showOrderRestrictedForm(result) {
    console.log('주문 불가 시간대 - 제한 화면 표시');
    
    hideAllForms();
    document.getElementById('orderRestrictedForm').classList.remove('hidden');
    
    // 다음 주문 시간 표시
    if (result && result.nextOrderTime) {
        document.getElementById('nextRestrictedOrderTime').textContent = formatDateTime(result.nextOrderTime);
    }
    
    // 현재 시간 업데이트 시작
    startRestrictedTimeUpdate();
    
    showMessage('orderStatusMessage', '주문처리 시간대로 조회만 가능합니다.', 'info');
}

// 제한 시간대 현재 시간 업데이트
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
    
    // 즉시 업데이트
    updateCurrentTime();
    
    // 1분마다 업데이트
    if (restrictedTimeInterval) {
        clearInterval(restrictedTimeInterval);
    }
    restrictedTimeInterval = setInterval(updateCurrentTime, 60000);
}