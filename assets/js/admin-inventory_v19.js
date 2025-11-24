/**
 * admin-inventory_v3.js - 관리자 페이지 재고/상품 관리 기능 (수정된 버전)
 */

// ========================================
// 유틸리티 함수들
// ========================================

function escapeForJs(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\')
              .replace(/'/g, "\\'")
              .replace(/"/g, '\\"')
              .replace(/\n/g, '\\n')
              .replace(/\r/g, '\\r')
              .replace(/\t/g, '\\t');
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ========================================
// 카테고리 관리 함수들
// ========================================

function loadCategories() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getCategories'
    })
    .then(response => response.json())
    .then(data => {
        displayCategories(data.categories || []);
    })
    .catch(error => {
        console.error('카테고리 로드 오류:', error);
        showAlert('카테고리 목록을 불러오는데 실패했습니다.', 'error');
    });
}

function displayCategories(categories) {
    const container = document.getElementById('categoryItems');
    
    if (categories.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 카테고리가 없습니다.</div>';
        return;
    }
    
    let html = '<div class="category-section">' +
                '<h3>카테고리 목록' +
                    '<button class="reorder-btn" onclick="reorderAllCategories()" title="카테고리 순서 자동 재정렬">재정렬</button>' +
                '</h3>' +
                '<div class="category-list">';
    
    html += categories.map(category => 
        '<div class="category-item" data-category-id="' + category.id + '" draggable="true">' +
            '<div>' +
                '<div class="category-name">' + escapeHtml(category.category_name) + '</div>' +
                (category.description ? '<div class="category-description">' + escapeHtml(category.description) + '</div>' : '') +
            '</div>' +
            '<div class="category-actions">' +
                '<span class="item-order">' + category.display_order + '</span>' +
                '<button class="btn btn-small btn-edit" data-category-id="' + category.id + '" data-category-name="' + escapeHtml(category.category_name) + '" data-category-desc="' + escapeHtml(category.description || '') + '" data-category-order="' + category.display_order + '" onclick="editCategorySafe(this)">수정</button>' +
                '<button class="btn btn-small btn-reject" data-category-id="' + category.id + '" data-category-name="' + escapeHtml(category.category_name) + '" onclick="deleteCategorySafe(this)">삭제</button>' +
                '<span class="drag-handle" title="드래그하여 순서 변경">⋮⋮</span>' +
            '</div>' +
        '</div>'
    ).join('');
    
    html += '</div></div>';
    
    container.innerHTML = html;
    
    // 카테고리 드래그 앤 드롭 이벤트 리스너 추가
    initializeCategoryDragAndDrop();
}

function editCategorySafe(button) {
    const categoryId = button.getAttribute('data-category-id');
    const categoryName = button.getAttribute('data-category-name');
    const categoryDesc = button.getAttribute('data-category-desc');
    const categoryOrder = button.getAttribute('data-category-order');
    
    editCategory(categoryId, categoryName, categoryDesc, categoryOrder);
}

function deleteCategorySafe(button) {
    const categoryId = button.getAttribute('data-category-id');
    const categoryName = button.getAttribute('data-category-name');
    
    deleteCategory(categoryId, categoryName);
}

function addCategory() {
    const categoryName = document.getElementById('newCategoryName').value.trim();
    const categoryDesc = document.getElementById('newCategoryDesc').value.trim();
    const categoryOrder = document.getElementById('categoryOrder').value;
    
    if (!categoryName) {
        showAlert('카테고리명을 입력해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=addCategory&categoryName=' + encodeURIComponent(categoryName) + 
              '&categoryDesc=' + encodeURIComponent(categoryDesc) + 
              '&categoryOrder=' + categoryOrder
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            document.getElementById('newCategoryName').value = '';
            document.getElementById('newCategoryDesc').value = '';
            document.getElementById('categoryOrder').value = '1';
            loadCategories();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('카테고리 추가 오류:', error);
        showAlert('카테고리 추가 중 오류가 발생했습니다.', 'error');
    });
}

function editCategory(categoryId, currentName, currentDesc, currentOrder) {
    document.getElementById('editCategoryId').value = categoryId;
    document.getElementById('editCategoryName').value = currentName;
    document.getElementById('editCategoryDesc').value = currentDesc;
    document.getElementById('editCategoryOrder').value = currentOrder;
    
    document.getElementById('categoryEditModal').style.display = 'block';
}

function closeCategoryEditModal() {
    document.getElementById('categoryEditModal').style.display = 'none';
}

function saveCategoryEdit() {
    const categoryId = document.getElementById('editCategoryId').value;
    const categoryName = document.getElementById('editCategoryName').value.trim();
    const categoryDesc = document.getElementById('editCategoryDesc').value.trim();
    const categoryOrder = document.getElementById('editCategoryOrder').value;
    
    if (!categoryName) {
        showAlert('카테고리명을 입력해주세요.', 'error');
        return;
    }
    
    if (!categoryOrder || categoryOrder < 1) {
        showAlert('순서는 1 이상의 숫자를 입력해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateCategory&categoryId=' + categoryId + 
              '&categoryName=' + encodeURIComponent(categoryName) + 
              '&categoryDesc=' + encodeURIComponent(categoryDesc) + 
              '&categoryOrder=' + categoryOrder
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeCategoryEditModal();
            loadCategories();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('카테고리 수정 오류:', error);
        showAlert('카테고리 수정 중 오류가 발생했습니다.', 'error');
    });
}

function deleteCategory(categoryId, categoryName) {
    if (!confirm('"' + categoryName + '" 카테고리를 삭제하시겠습니까?\n\n※ 이 카테고리에 속한 모든 품목도 함께 삭제됩니다.')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=deleteCategory&categoryId=' + categoryId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadCategories();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('카테고리 삭제 오류:', error);
        showAlert('카테고리 삭제 중 오류가 발생했습니다.', 'error');
    });
}

// ========================================
// 품목 관리 함수들
// ========================================

function loadCategoriesForSelect() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getCategories'
    })
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById('itemCategory');
        if (select) {
            select.innerHTML = '<option value="">카테고리 선택</option>';
            
            if (data.categories) {
                data.categories.forEach(category => {
                    select.innerHTML += '<option value="' + category.id + '">' + escapeHtml(category.category_name) + '</option>';
                });
            }
        }
    })
    .catch(error => {
        console.error('카테고리 선택 로드 오류:', error);
    });
}

function loadItems() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getItems'
    })
    .then(response => response.json())
    .then(data => {
        displayItems(data.items || []);
    })
    .catch(error => {
        console.error('품목 로드 오류:', error);
        showAlert('품목 목록을 불러오는데 실패했습니다.', 'error');
    });
}

function displayItems(items) {
    const container = document.getElementById('itemsContainer');
    
    if (items.length === 0) {
        container.innerHTML = '<div class="no-data">등록된 품목이 없습니다.</div>';
        return;
    }
    
    const itemsByCategory = {};
    items.forEach(item => {
        const categoryName = item.category_name || '미분류';
        if (!itemsByCategory[categoryName]) {
            itemsByCategory[categoryName] = [];
        }
        itemsByCategory[categoryName].push(item);
    });
    
    let html = '';
    Object.keys(itemsByCategory).forEach(categoryName => {
        const firstItem = (itemsByCategory[categoryName] && itemsByCategory[categoryName][0]) || null;
        const categoryId = firstItem ? firstItem.category_id : '';
        html += '<div class="category-section">' +
                    '<h3>' + escapeHtml(categoryName) + 
                        (categoryId ? '<button class="reorder-btn" onclick="reorderItemsByCategory(' + categoryId + ')" title="순서 자동 재정렬">재정렬</button>' : '') +
                    '</h3>' +
                    '<div class="item-list">';
        
        itemsByCategory[categoryName].forEach(item => {
            html += '<div class="item-row" data-item-id="' + item.item_id + '" data-category-id="' + item.category_id + '" draggable="true">' +
                        '<div style="flex: 1;">' +
                            '<div class="item-name">' + escapeHtml(item.item_name) + '</div>' +
                            (item.description ? '<div class="item-description">' + escapeHtml(item.description) + '</div>' : '') +
                        '</div>' +
                        '<div class="item-actions">' +
                            '<span class="item-order">' + item.display_order + '</span>' +
                            '<button class="btn btn-small btn-edit" data-item-id="' + item.item_id + '" data-item-name="' + escapeHtml(item.item_name) + '" data-item-desc="' + escapeHtml(item.description || '') + '" data-item-order="' + item.display_order + '" data-category-id="' + item.category_id + '" onclick="editItemSafe(this)">수정</button>' +
                            '<button class="btn btn-small btn-reject" data-item-id="' + item.item_id + '" data-item-name="' + escapeHtml(item.item_name) + '" onclick="deleteItemSafe(this)">삭제</button>' +
                            '<span class="drag-handle" title="드래그하여 순서 변경">⋮⋮</span>' +
                        '</div>' +
                    '</div>';
        });
        
    });
    
    container.innerHTML = html;
    
    // 드래그 앤 드롭 이벤트 리스너 추가
    initializeDragAndDrop();
}

// ========================================
// 드래그 앤 드롭 및 순서 관리 함수들
// ========================================

let draggedElement = null;

function initializeDragAndDrop() {
    const itemRows = document.querySelectorAll('.item-row');
    
    itemRows.forEach(row => {
        row.addEventListener('dragstart', handleDragStart);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('drop', handleDrop);
        row.addEventListener('dragend', handleDragEnd);
    });
}

function handleDragStart(e) {
    draggedElement = this;
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.outerHTML);
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    if (draggedElement !== this) {
        // 같은 카테고리 내에서만 드롭 허용
        const draggedCategory = draggedElement.closest('.category-section').querySelector('h3').textContent;
        const targetCategory = this.closest('.category-section').querySelector('h3').textContent;
        
        if (draggedCategory === targetCategory) {
            // 순서 스왑 요청 (원자적 처리)
            const draggedId = draggedElement.getAttribute('data-item-id');
            const targetId = this.getAttribute('data-item-id');
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=swapItemOrder&draggedId=' + draggedId + '&targetId=' + targetId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadItems();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('순서 변경 오류:', error);
                showAlert('순서 변경 중 오류가 발생했습니다.', 'error');
            });
        }
    }
    
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '';
    draggedElement = null;
}

function updateItemOrder(draggedId, targetId) {
    // 간단한 순서 교체 로직
    const draggedOrder = parseInt(document.querySelector(`[data-item-id="${draggedId}"] .item-order`).textContent);
    const targetOrder = parseInt(document.querySelector(`[data-item-id="${targetId}"] .item-order`).textContent);
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateItemOrder&itemId=' + draggedId + '&newOrder=' + targetOrder
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 성공 시 목록 새로고침
            loadItems();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('순서 업데이트 오류:', error);
        showAlert('순서 업데이트 중 오류가 발생했습니다.', 'error');
    });
}

function reorderItemsByCategory(categoryId) {
    if (!confirm('선택한 카테고리의 품목 순서를 자동으로 재정렬하시겠습니까?')) {
        return;
    }
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reorderItems&categoryId=' + categoryId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadItems();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('순서 재정렬 오류:', error);
        showAlert('순서 재정렬 중 오류가 발생했습니다.', 'error');
    });
}

function editItemSafe(button) {
    const itemId = button.getAttribute('data-item-id');
    const itemName = button.getAttribute('data-item-name');
    const itemDesc = button.getAttribute('data-item-desc');
    const itemOrder = button.getAttribute('data-item-order');
    const categoryId = button.getAttribute('data-category-id');
    
    editItem(itemId, itemName, itemDesc, itemOrder, categoryId);
}

function deleteItemSafe(button) {
    const itemId = button.getAttribute('data-item-id');
    const itemName = button.getAttribute('data-item-name');
    
    deleteItem(itemId, itemName);
}

function addItem() {
    const categoryId = document.getElementById('itemCategory').value;
    const itemName = document.getElementById('newItemName').value.trim();
    const itemDesc = document.getElementById('newItemDesc').value.trim();
    const itemOrder = document.getElementById('itemOrder').value;
    
    if (!categoryId) {
        showAlert('카테고리를 선택해주세요.', 'error');
        return;
    }
    
    if (!itemName) {
        showAlert('품목명을 입력해주세요.', 'error');
        return;
    }
    
    // 자동 순서 할당: 순서가 0 이하이면 자동으로 설정
    const orderValue = (itemOrder && parseInt(itemOrder) > 0) ? itemOrder : 0;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=addItem&categoryId=' + categoryId + 
              '&itemName=' + encodeURIComponent(itemName) + 
              '&itemDesc=' + encodeURIComponent(itemDesc) + 
              '&itemOrder=' + orderValue
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            document.getElementById('itemCategory').value = '';
            document.getElementById('newItemName').value = '';
            document.getElementById('newItemDesc').value = '';
            document.getElementById('itemOrder').value = '0'; // 자동 할당을 위해 0으로 설정
            loadItems();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('품목 추가 오류:', error);
        showAlert('품목 추가 중 오류가 발생했습니다.', 'error');
    });
}

function editItem(itemId, currentName, currentDesc, currentOrder, currentCategoryId) {
    document.getElementById('editItemId').value = itemId;
    document.getElementById('editItemName').value = currentName;
    document.getElementById('editItemDesc').value = currentDesc;
    document.getElementById('editItemOrder').value = currentOrder;
    
    document.getElementById('itemEditModal').style.display = 'block';
}

function closeItemEditModal() {
    document.getElementById('itemEditModal').style.display = 'none';
}

function saveItemEdit() {
    const itemId = document.getElementById('editItemId').value;
    const itemName = document.getElementById('editItemName').value.trim();
    const itemDesc = document.getElementById('editItemDesc').value.trim();
    const itemOrder = document.getElementById('editItemOrder').value;
    
    if (!itemName) {
        showAlert('품목명을 입력해주세요.', 'error');
        return;
    }
    
    if (!itemOrder || itemOrder < 1) {
        showAlert('순서는 1 이상의 숫자를 입력해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateItem&itemId=' + itemId + 
              '&itemName=' + encodeURIComponent(itemName) + 
              '&itemDesc=' + encodeURIComponent(itemDesc) + 
              '&itemOrder=' + itemOrder
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeItemEditModal();
            loadItems();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('품목 수정 오류:', error);
        showAlert('품목 수정 중 오류가 발생했습니다.', 'error');
    });
}

function deleteItem(itemId, itemName) {
    if (!confirm('"' + itemName + '" 품목을 삭제하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=deleteItem&itemId=' + itemId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadItems();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('품목 삭제 오류:', error);
        showAlert('품목 삭제 중 오류가 발생했습니다.', 'error');
    });
}

// ========================================
// 업체-품목 할당 관리 함수들
// ========================================

// 전역 변수: 모든 업체 데이터 저장 (품목업체할당 탭용)
let assignmentCompaniesData = null;
let selectedCompany = null;

// 안전하게 선택된 업체 ID를 가져오는 헬퍼 (기존 select 요소가 없을 경우 전역 상태 사용)
function getSelectedCompanyId() {
    console.log('getSelectedCompanyId 호출됨');
    
    // 1. assignmentCompany select 요소 확인
    const el = document.getElementById('assignmentCompany');
    console.log('assignmentCompany element:', el);
    if (el && el.value) {
        console.log('assignmentCompany value:', el.value);
        return el.value;
    }
    
    // 2. selectedCompany 전역 변수 확인
    console.log('selectedCompany:', selectedCompany);
    if (selectedCompany && selectedCompany.id) {
        console.log('selectedCompany.id:', selectedCompany.id);
        return selectedCompany.id;
    }
    
    // 3. 현재 선택된 업체 정보를 다른 방법으로 찾기
    const companySearchInput = document.getElementById('assignmentCompanySearch');
    console.log('assignmentCompanySearch element:', companySearchInput);
    if (companySearchInput && companySearchInput.value) {
        // 검색된 업체명으로 ID 찾기
        const companyName = companySearchInput.value.trim();
        console.log('search input value:', companyName);
        if (assignmentCompaniesData && assignmentCompaniesData.companies) {
            const foundCompany = assignmentCompaniesData.companies.find(c => 
                c.company_name.toLowerCase() === companyName.toLowerCase()
            );
            if (foundCompany) {
                console.log('found company by search:', foundCompany);
                return foundCompany.id;
            }
        }
    }
    
    // 4. 마지막으로 현재 활성화된 업체 정보 확인
    const activeCompanyElement = document.querySelector('.company-item.selected');
    console.log('activeCompanyElement:', activeCompanyElement);
    if (activeCompanyElement) {
        const companyId = activeCompanyElement.getAttribute('data-company-id');
        console.log('activeCompanyElement data-company-id:', companyId);
        if (companyId) return companyId;
    }
    
    console.warn('getSelectedCompanyId: 업체 ID를 찾을 수 없습니다.');
    return '';
}

function loadCompanies() {
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getCompanies'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.companies && data.companies.length > 0) {
            // 전역 변수에 데이터 저장
            assignmentCompaniesData = data;
        } else {
            console.error('업체 데이터 로드 실패:', data.message);
        }
    })
    .catch(error => {
        console.error('업체 목록 로드 오류:', error);
        showAlert('업체 목록을 불러오는데 실패했습니다. 페이지를 새로고침해주세요.', 'error');
    });
}

// 업체 데이터가 로드되었는지 확인하고 필요시 로드하는 함수
function ensureCompaniesLoaded() {
    if (!assignmentCompaniesData) {
        loadCompanies();
    }
}

// 업체 검색 및 선택 함수
function searchAndSelectCompany() {
    if (!assignmentCompaniesData) return;
    
    const searchTerm = document.getElementById('assignmentCompanySearch').value.toLowerCase().trim();
    const resultsContainer = document.getElementById('companySearchResults');
    
    if (searchTerm === '') {
        resultsContainer.style.display = 'none';
        return;
    }
    
    // 검색어와 일치하는 업체 필터링
    const filteredCompanies = assignmentCompaniesData.companies.filter(company => 
        company.company_name.toLowerCase().includes(searchTerm)
    );
    
    if (filteredCompanies.length > 0) {
        let html = '';
        filteredCompanies.forEach(company => {
            html += `
                <div class="search-result-item" onclick="selectCompany(${company.id}, '${escapeHtml(company.company_name)}')">
                    <div class="company-name">${escapeHtml(company.company_name)}</div>
                    <div class="company-details">ID: ${company.id}</div>
                </div>
            `;
        });
        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';
    } else {
        resultsContainer.innerHTML = '<div class="search-result-item" style="color: #9ca3af; cursor: default;">검색 결과가 없습니다</div>';
        resultsContainer.style.display = 'block';
    }
}

// 업체 선택 함수
function selectCompany(companyId, companyName) {
    console.log('selectCompany 호출됨 - companyId:', companyId, 'companyName:', companyName);
    
    // 전역 변수에 선택된 업체 정보 저장
    selectedCompany = { id: companyId, name: companyName };
    console.log('selectedCompany 설정됨:', selectedCompany);
    
    // 선택된 업체 정보 표시
    const selectedInfo = document.getElementById('selectedCompanyInfo');
    if (selectedInfo) {
        selectedInfo.innerHTML = `<span class="company-selected">${companyName}</span>`;
    }
    
    // 검색 결과 숨기기
    const searchResults = document.getElementById('companySearchResults');
    if (searchResults) {
        searchResults.style.display = 'none';
    }
    
    // 검색 필드 초기화
    const searchInput = document.getElementById('assignmentCompanySearch');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // 전체 할당 버튼 활성화
    const assignmentBtn = document.getElementById('assignmentBtn');
    if (assignmentBtn) {
        assignmentBtn.disabled = false;
    }
    
    // 개별 품목 추가 섹션 표시
    const individualSection = document.getElementById('individualAssignmentSection');
    if (individualSection) {
        individualSection.style.display = 'block';
    }
    
    // 업체 할당 정보 로드
    loadCompanyAssignments(companyId);
}

// 검색 결과 외부 클릭 시 닫기
document.addEventListener('click', function(event) {
    const searchInput = document.getElementById('assignmentCompanySearch');
    const searchResults = document.getElementById('companySearchResults');
    
    if (searchInput && searchResults && 
        !searchInput.contains(event.target) && 
        !searchResults.contains(event.target)) {
        searchResults.style.display = 'none';
    }
});

// 업체 할당 정보 로드 함수 (기존 함수 수정)
function loadCompanyAssignments(companyId = null) {
    const targetCompanyId = companyId || (selectedCompany ? selectedCompany.id : null);
    
    if (!targetCompanyId) {
        document.getElementById('assignmentsContainer').innerHTML = '<div class="no-data">업체를 선택하세요.</div>';
        document.getElementById('individualAssignmentSection').style.display = 'none';
        return;
    }
    
    document.getElementById('individualAssignmentSection').style.display = 'block';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getCompanyAssignments&companyId=' + targetCompanyId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayCompanyAssignments(data);
        } else {
            document.getElementById('assignmentsContainer').innerHTML = '<div class="no-data">' + (data.message || '업체 할당 정보를 불러올 수 없습니다.') + '</div>';
        }
    })
    .catch(error => {
        console.error('업체 할당 정보 로드 오류:', error);
        document.getElementById('assignmentsContainer').innerHTML = '<div class="no-data">업체 할당 정보를 불러오는데 실패했습니다.</div>';
        showAlert('업체 할당 정보를 불러오는데 실패했습니다. 페이지를 새로고침해주세요.', 'error');
    });
}

function displayCompanyAssignments(data) {
    const container = document.getElementById('assignmentsContainer');
    
    if (!data.success) {
        container.innerHTML = '<div class="no-data">업체 정보를 불러올 수 없습니다.</div>';
        return;
    }
    
    const company = data.company;
    const assignments = data.assignments || [];
    
    let html = '<div class="company-info">' +
                   '<h4>' + escapeHtml(company.company_name) + '</h4>' +
                   '<div class="info-grid">' +
                       '<div class="info-item">' +
                           '<span class="info-label">소속그룹:</span>' +
                           '<span class="info-value">' + escapeHtml(company.item_group || '미설정') + '</span>' +
                       '</div>' +
                       '<div class="info-item">' +
                           '<span class="info-label">등록일:</span>' +
                           '<span class="info-value">' + company.created_at + '</span>' +
                       '</div>' +
                   '</div>' +
               '</div>';
    
    if (assignments.length === 0) {
        html += '<div class="no-data">할당된 품목이 없습니다.</div>';
    } else {
        assignments.forEach(assignment => {
            html += '<div class="assignment-row">' +
                        '<div class="assignment-content">' +
                            '<div class="item-name">' + escapeHtml(assignment.assigned_item_name) + '</div>' +
                            '<div class="item-description">카테고리: ' + escapeHtml(assignment.category_name || '미분류') + '</div>' +
                        '</div>' +
                        '<div class="assignment-actions">' +
                            '<span class="item-order">' + assignment.item_order + '</span>' +
                            '<button class="btn btn-small btn-delete" data-assignment-id="' + assignment.assignment_id + '" data-item-name="' + escapeHtml(assignment.assigned_item_name) + '" onclick="removeAssignmentSafe(this)" title="품목 제거">' +
                                '<span class="delete-icon">×</span>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
        });
    }
    
    container.innerHTML = html;
}

function removeAssignmentSafe(button) {
    const assignmentId = button.getAttribute('data-assignment-id');
    const itemName = button.getAttribute('data-item-name');
    
    removeAssignment(assignmentId, itemName);
}

// ========================================
// 전체 할당 관리 함수들
// ========================================

function showAssignmentModal() {
    const companyId = getSelectedCompanyId();
    
    if (!companyId) {
        showAlert('업체를 먼저 선택해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getAvailableItems&companyId=' + companyId
    })
    .then(response => response.json())
    .then(data => {
        displayAvailableItems(data.items || []);
        document.getElementById('assignmentModal').style.display = 'block';
    })
    .catch(error => {
        console.error('할당 가능한 품목 로드 오류:', error);
        showAlert('할당 가능한 품목을 불러오는데 실패했습니다.', 'error');
    });
}

function displayAvailableItems(items) {
    const container = document.getElementById('availableItems');
    
    if (items.length === 0) {
        container.innerHTML = '<div class="no-data">할당 가능한 품목이 없습니다.</div>';
        return;
    }
    
    const itemsByCategory = {};
    items.forEach(item => {
        const categoryName = item.category_name || '미분류';
        if (!itemsByCategory[categoryName]) {
            itemsByCategory[categoryName] = [];
        }
        itemsByCategory[categoryName].push(item);
    });
    
    let html = '';
    Object.keys(itemsByCategory).forEach(categoryName => {
        html += '<div class="category-section">' +
                    '<h4>' + escapeHtml(categoryName) + '</h4>';
        
        itemsByCategory[categoryName].forEach(item => {
            html += '<div class="assignment-item ' + (item.is_assigned ? 'assigned' : '') + '">' +
                        '<input type="checkbox" id="item_' + item.item_id + '" value="' + item.item_id + '"' + (item.is_assigned ? ' checked' : '') + '>' +
                        '<label for="item_' + item.item_id + '">' + escapeHtml(item.item_name) + '</label>' +
                        (item.description ? '<span class="item-description"> - ' + escapeHtml(item.description) + '</span>' : '') +
                    '</div>';
        });
        
        html += '</div>';
    });
    
    container.innerHTML = html;
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').style.display = 'none';
}

function saveAssignments() {
    const companyId = getSelectedCompanyId();
    const checkboxes = document.querySelectorAll('#availableItems input[type="checkbox"]');
    const selectedItems = [];
    
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedItems.push(checkbox.value);
        }
    });
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=saveAssignments&companyId=' + companyId + '&itemIds=' + selectedItems.join(',')
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeAssignmentModal();
            loadCompanyAssignments();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('할당 저장 오류:', error);
        showAlert('할당 저장 중 오류가 발생했습니다.', 'error');
    });
}

// ========================================
// 개별 품목 할당 관리 함수들
// ========================================

function showIndividualAssignmentModal() {
    const companyId = getSelectedCompanyId();
    
    console.log('showIndividualAssignmentModal - companyId:', companyId);
    console.log('showIndividualAssignmentModal - companyId type:', typeof companyId);
    
    if (!companyId || companyId === '' || companyId === null || companyId === undefined) {
        showAlert('업체를 먼저 선택해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getUnassignedItems&companyId=' + companyId
    })
    .then(response => response.json())
    .then(data => {
        displayUnassignedItems(data.items || []);
        document.getElementById('individualAssignmentModal').style.display = 'block';
    })
    .catch(error => {
        console.error('미할당 품목 로드 오류:', error);
        showAlert('미할당 품목을 불러오는데 실패했습니다.', 'error');
    });
}

function displayUnassignedItems(items) {
    const container = document.getElementById('unassignedItems');
    
    if (items.length === 0) {
        container.innerHTML = '<div class="no-data">추가할 수 있는 품목이 없습니다.</div>';
        return;
    }
    
    const itemsByCategory = {};
    items.forEach(item => {
        const categoryName = item.category_name || '미분류';
        if (!itemsByCategory[categoryName]) {
            itemsByCategory[categoryName] = [];
        }
        itemsByCategory[categoryName].push(item);
    });
    
    let html = '';
    Object.keys(itemsByCategory).forEach(categoryName => {
        html += '<div class="category-section">' +
                    '<h4>' + escapeHtml(categoryName) + '</h4>';
        
        itemsByCategory[categoryName].forEach(item => {
            html += '<div class="assignment-item">' +
                        '<input type="checkbox" id="unassigned_item_' + item.item_id + '" value="' + item.item_id + '">' +
                        '<label for="unassigned_item_' + item.item_id + '">' + escapeHtml(item.item_name) + '</label>' +
                        (item.description ? '<span class="item-description"> - ' + escapeHtml(item.description) + '</span>' : '') +
                    '</div>';
        });
        
        html += '</div>';
    });
    
    container.innerHTML = html;
}

function closeIndividualAssignmentModal() {
    document.getElementById('individualAssignmentModal').style.display = 'none';
}

function saveIndividualAssignments() {
    const companyId = getSelectedCompanyId();
    const checkboxes = document.querySelectorAll('#unassignedItems input[type="checkbox"]');
    const selectedItems = [];
    
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedItems.push(checkbox.value);
        }
    });
    
    if (selectedItems.length === 0) {
        showAlert('추가할 품목을 선택해주세요.', 'error');
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=addIndividualAssignment&companyId=' + companyId + '&itemIds=' + selectedItems.join(',')
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeIndividualAssignmentModal();
            loadCompanyAssignments();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('개별 할당 저장 오류:', error);
        showAlert('개별 할당 저장 중 오류가 발생했습니다.', 'error');
    });
}

function removeAssignment(assignmentId, itemName) {
    if (!confirm('"' + itemName + '" 품목 할당을 제거하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=removeAssignment&assignmentId=' + assignmentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadCompanyAssignments();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('할당 제거 오류:', error);
        showAlert('할당 제거 중 오류가 발생했습니다.', 'error');
    });
}

// ========================================
// 재고 관리 초기화 및 이벤트 리스너
// ========================================

function initializeInventoryFeatures() {
    // 카테고리 입력 엔터키 이벤트
    const categoryInput = document.getElementById('newCategoryName');
    if (categoryInput) {
        categoryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addCategory();
            }
        });
    }
    
    // 품목 입력 엔터키 이벤트
    const itemInput = document.getElementById('newItemName');
    if (itemInput) {
        itemInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addItem();
            }
        });
    }
    
    // 모달 클릭 이벤트 (모달 바깥 클릭 시 닫기)
    window.addEventListener('click', function(event) {
        const assignmentModal = document.getElementById('assignmentModal');
        const individualModal = document.getElementById('individualAssignmentModal');
        const categoryEditModal = document.getElementById('categoryEditModal');
        const itemEditModal = document.getElementById('itemEditModal');
        
        if (event.target === assignmentModal) {
            closeAssignmentModal();
        }
        if (event.target === individualModal) {
            closeIndividualAssignmentModal();
        }
        if (event.target === categoryEditModal) {
            closeCategoryEditModal();
        }
        if (event.target === itemEditModal) {
            closeItemEditModal();
        }
    });
}

// DOM 로드 시 재고 관리 기능 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeInventoryFeatures();
});

// ========================================
// 카테고리 드래그 앤 드롭 및 순서 관리 함수들
// ========================================

let draggedCategoryElement = null;

function initializeCategoryDragAndDrop() {
    const categoryItems = document.querySelectorAll('.category-item');
    
    categoryItems.forEach(item => {
        item.addEventListener('dragstart', handleCategoryDragStart);
        item.addEventListener('dragover', handleCategoryDragOver);
        item.addEventListener('drop', handleCategoryDrop);
        item.addEventListener('dragend', handleCategoryDragEnd);
    });
}

function handleCategoryDragStart(e) {
    draggedCategoryElement = this;
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.outerHTML);
}

function handleCategoryDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleCategoryDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    if (draggedCategoryElement !== this) {
        // 카테고리 순서 스왑 요청 (원자적 처리)
        const draggedId = draggedCategoryElement.getAttribute('data-category-id');
        const targetId = this.getAttribute('data-category-id');
        
        fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=swapCategoryOrder&draggedId=' + draggedId + '&targetId=' + targetId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCategories();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('카테고리 순서 변경 오류:', error);
            showAlert('카테고리 순서 변경 중 오류가 발생했습니다.', 'error');
        });
    }
    
    return false;
}

function handleCategoryDragEnd(e) {
    this.style.opacity = '';
    draggedCategoryElement = null;
}

function reorderAllCategories() {
    if (!confirm('모든 카테고리의 순서를 자동으로 재정렬하시겠습니까?')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reorderCategories'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            loadCategories();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('카테고리 순서 재정렬 오류:', error);
        showAlert('카테고리 순서 재정렬 중 오류가 발생했습니다.', 'error');
    });
}