// admin-settings_v4.js - 모바일 최적화된 설정 관리 JavaScript

// 페이지 로드 시 설정 데이터 로드
document.addEventListener('DOMContentLoaded', function() {
    loadSheetConfigs();
    loadItemGroups();
});

// 구글시트 설정 관련 함수들
function loadSheetConfigs() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getSheetConfigs'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySheetConfigs(data.data);
        } else {
            showAlert('시트 설정 조회 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('시트 설정 조회 중 오류가 발생했습니다.', 'error');
    });
}

function displaySheetConfigs(configs) {
    const container = document.getElementById('sheetConfigsList');
    
    if (!configs || configs.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 시트가 없습니다.</div>';
        return;
    }
    
    let html = '<div class="simple-list">';
    configs.forEach(config => {
        html += `
            <div class="list-item">
                <div class="item-info">
                    <span class="item-name">${config.sheet_name}</span>
                    <div class="item-actions">
                        <button class="btn btn-secondary btn-sm" onclick="editSheetConfig(${config.id}, '${config.sheet_name}', '${config.description || ''}')">수정</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteSheetConfig(${config.id})">삭제</button>
                    </div>
                </div>
                ${config.description ? `<div class="item-description">- ${config.description}</div>` : ''}
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function addSheetConfig() {
    const sheetName = document.getElementById('sheetName').value.trim();
    const description = document.getElementById('sheetDescription').value.trim();
    
    if (!sheetName) {
        showAlert('시트명을 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'updateSheetConfig');
    formData.append('id', 0); // 새로 추가
    formData.append('sheetName', sheetName);
    formData.append('description', description);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            clearSheetForm();
            loadSheetConfigs();
        } else {
            showAlert('저장 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('저장 중 오류가 발생했습니다.', 'error');
    });
}

function deleteSheetConfig(id) {
    if (!confirm('정말로 이 시트 설정을 삭제하시겠습니까?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'deleteSheetConfig');
    formData.append('id', id);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadSheetConfigs();
        } else {
            showAlert('삭제 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('삭제 중 오류가 발생했습니다.', 'error');
    });
}

function clearSheetForm() {
    document.getElementById('sheetName').value = '';
    document.getElementById('sheetDescription').value = '';
}

function editSheetConfig(id, sheetName, description) {
    document.getElementById('sheetName').value = sheetName;
    document.getElementById('sheetDescription').value = description;
    
    // 수정 모드로 전환
    const addButton = document.querySelector('button[onclick="addSheetConfig()"]');
    addButton.textContent = '수정';
    addButton.onclick = () => updateSheetConfig(id);
    
    // 폼 스크롤
    document.getElementById('sheetName').scrollIntoView({ behavior: 'smooth' });
}

function updateSheetConfig(id) {
    const sheetName = document.getElementById('sheetName').value.trim();
    const description = document.getElementById('sheetDescription').value.trim();
    
    if (!sheetName) {
        showAlert('시트명을 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'updateSheetConfig');
    formData.append('id', id);
    formData.append('sheetName', sheetName);
    formData.append('description', description);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            clearSheetForm();
            loadSheetConfigs();
            
            // 버튼을 다시 추가 모드로 변경
            const addButton = document.querySelector('button[onclick="updateSheetConfig(' + id + ')"]');
            addButton.textContent = '추가';
            addButton.onclick = addSheetConfig;
        } else {
            showAlert('수정 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('수정 중 오류가 발생했습니다.', 'error');
    });
}

// 품목 그룹 설정 관련 함수들
function loadItemGroups() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getItemGroups'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayItemGroups(data.data);
        } else {
            showAlert('품목 그룹 조회 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('품목 그룹 조회 중 오류가 발생했습니다.', 'error');
    });
}

function displayItemGroups(groups) {
    const container = document.getElementById('itemGroupsList');
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 그룹이 없습니다.</div>';
        return;
    }
    
    let html = '<div class="simple-list">';
    groups.forEach(group => {
        html += `
            <div class="list-item">
                <div class="item-info">
                    <span class="item-name">${group.group_name}</span>
                    <div class="item-actions">
                        <button class="btn btn-secondary btn-sm" onclick="editItemGroup(${group.id}, '${group.group_name}', '${group.description || ''}')">수정</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteItemGroup(${group.id})">삭제</button>
                    </div>
                </div>
                ${group.description ? `<div class="item-description">- ${group.description}</div>` : ''}
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function addItemGroup() {
    const groupName = document.getElementById('groupName').value.trim();
    const description = document.getElementById('groupDescription').value.trim();
    
    if (!groupName) {
        showAlert('그룹명을 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'updateItemGroup');
    formData.append('id', 0); // 새로 추가
    formData.append('groupName', groupName);
    formData.append('description', description);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            clearGroupForm();
            loadItemGroups();
        } else {
            showAlert('저장 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('저장 중 오류가 발생했습니다.', 'error');
    });
}

function deleteItemGroup(id) {
    if (!confirm('정말로 이 그룹 설정을 삭제하시겠습니까?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'deleteItemGroup');
    formData.append('id', id);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadItemGroups();
        } else {
            showAlert('삭제 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('삭제 중 오류가 발생했습니다.', 'error');
    });
}

function clearGroupForm() {
    document.getElementById('groupName').value = '';
    document.getElementById('groupDescription').value = '';
}

function editItemGroup(id, groupName, description) {
    document.getElementById('groupName').value = groupName;
    document.getElementById('groupDescription').value = description;
    
    // 수정 모드로 전환
    const addButton = document.querySelector('button[onclick="addItemGroup()"]');
    addButton.textContent = '수정';
    addButton.onclick = () => updateItemGroup(id);
    
    // 폼 스크롤
    document.getElementById('groupName').scrollIntoView({ behavior: 'smooth' });
}

function updateItemGroup(id) {
    const groupName = document.getElementById('groupName').value.trim();
    const description = document.getElementById('groupDescription').value.trim();
    
    if (!groupName) {
        showAlert('그룹명을 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'updateItemGroup');
    formData.append('id', id);
    formData.append('groupName', groupName);
    formData.append('description', description);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            clearGroupForm();
            loadItemGroups();
            
            // 버튼을 다시 추가 모드로 변경
            const addButton = document.querySelector('button[onclick="updateItemGroup(' + id + ')"]');
            addButton.textContent = '추가';
            addButton.onclick = addItemGroup;
        } else {
            showAlert('수정 실패: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('수정 중 오류가 발생했습니다.', 'error');
    });
}