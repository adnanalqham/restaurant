<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة طلبات الصرف', 'inventory_requests_manage');
?>

<div class="card">
    <div class="card-header flex justify-between align-center">
        <h3>طلبات الصرف من الأقسام</h3>
        <div class="flex gap-12 align-center">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="search-requests" class="form-control" placeholder="بحث برقم الطلب أو الموظف..." oninput="filterRequests()">
            </div>
            <select id="filter-status" class="form-control" style="width:auto" onchange="loadRequests()">
                <option value="">كل الحالات</option>
                <option value="pending">بانتظار الموافقة</option>
                <option value="approved">تمت الموافقة (بانتظار الصرف)</option>
                <option value="issued">تم الصرف</option>
                <option value="received">تم استلامها في الفرع</option>
                <option value="rejected">مرفوض</option>
                <option value="cancelled">ملغي</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <div id="requests-admin-list" class="requests-grid">
            <div class="text-center p-20" style="grid-column:1/-1"><div class="spinner"></div> جاري تحميل الطلبات...</div>
        </div>
    </div>
</div>

<!-- Modal for Request Approval/Issuing -->
<div class="modal-backdrop hidden" id="request-action-modal">
    <div class="modal" style="max-width:750px">
        <div class="modal-header">
            <h3>معالجة طلب الصرف #<span id="act-request-id"></span></h3>
            <button class="modal-close" onclick="closeModal('request-action-modal')">✕</button>
        </div>
        <div class="modal-body" style="padding:0">
            <div id="request-action-content"></div>
        </div>
        <div class="modal-footer" id="request-action-footer"></div>
    </div>
</div>

<script>
let allRequests = [];
let currentRequest = null;

const statusMap = {
    'pending': { label: 'بانتظار الموافقة', class: 'status-pending', icon: 'fa-clock' },
    'approved': { label: 'تمت الموافقة', class: 'status-approved', icon: 'fa-check' },
    'issued': { label: 'تم الصرف', class: 'status-issued', icon: 'fa-truck-loading' },
    'received': { label: 'تم الاستلام', class: 'status-received', icon: 'fa-check-double' },
    'rejected': { label: 'مرفوض', class: 'status-rejected', icon: 'fa-times-circle' },
    'cancelled': { label: 'ملغي', class: 'status-cancelled', icon: 'fa-ban' }
};

async function loadRequests() {
    const status = document.getElementById('filter-status').value;
    const res = await apiCall('/api/inventory.php?action=get_requests&status=' + status);
    if (res.success) {
        allRequests = res.data;
        filterRequests();
    }
}

function filterRequests() {
    const query = document.getElementById('search-requests').value.toLowerCase();
    const container = document.getElementById('requests-admin-list');
    
    const filtered = allRequests.filter(r => 
        r.id.toString().includes(query) || 
        r.requester_name.toLowerCase().includes(query)
    );

    if (!filtered.length) {
        container.innerHTML = '<div style="grid-column: 1 / -1; padding:40px;" class="text-center text-muted">لا توجد طلبات صرف مطابقة للبحث</div>';
        return;
    }

    container.innerHTML = filtered.map(r => {
        let btnIcon = 'fa-eye';
        let btnText = 'التفاصيل';
        let btnClass = 'btn-secondary';
        
        if(r.status === 'pending') {
            btnIcon = 'fa-tasks';
            btnText = 'مراجعة واعتماد';
            btnClass = 'btn-primary';
        } else if(r.status === 'approved') {
            btnIcon = 'fa-box-open';
            btnText = 'تجهيز وصرف';
            btnClass = 'btn-success';
        }

        return `
        <div class="order-card" style="background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; border-top: 4px solid var(--primary); display: flex; flex-direction: column; min-height: 200px; margin-bottom: 20px;">
            <div class="order-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px;">
                <div style="display:flex; align-items:center; gap:6px">
                    <strong style="color: var(--primary); font-size: 1.05rem">#${r.id}</strong>
                </div>
                <span class="status-badge status-${r.status}" style="font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; font-weight: 700;">${translateStatus(r.status)}</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: .82rem; color: var(--text-muted); margin-bottom: 12px">
                <span dir="ltr"><i class="fas fa-clock"></i> ${formatDate(r.created_at)}</span>
                <span><i class="fas fa-user"></i> طلب من: ${r.requester_name}</span>
            </div>
            
            <div class="order-items-preview" style="flex:1">
                ${r.items.map(i => `
                    <div class="order-item-line" style="display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; border-bottom:1px dashed var(--border);">
                        <span>${i.item_name}</span>
                        <span style="color:var(--primary); font-weight:bold;">${i.requested_qty} ${i.unit}</span>
                    </div>
                `).join('')}
            </div>
            
            <div class="order-card-footer" style="display:flex; justify-content:flex-end; align-items:center; border-top:1px solid var(--border); padding-top:10px; margin-top:10px;">
                <button class="btn ${btnClass} btn-sm w-100" onclick="openRequestAction(${r.id})">
                    <i class="fas ${btnIcon}"></i> ${btnText}
                </button>
            </div>
        </div>
        `;
    }).join('');
}

function translateStatus(s) {
    return statusMap[s]?.label || s;
}

async function openRequestAction(id) {
    currentRequest = allRequests.find(r => r.id == id);
    if (!currentRequest) return;

    document.getElementById('act-request-id').textContent = id;
    const content = document.getElementById('request-action-content');
    const footer = document.getElementById('request-action-footer');

    let itemsTable = `<table class="table mt-12" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="padding:8px; border-bottom:1px solid #ddd;">الصنف</th>
                <th style="padding:8px; border-bottom:1px solid #ddd;">المطلوب</th>
                <th style="padding:8px; border-bottom:1px solid #ddd;">المعتمد</th>
                <th style="padding:8px; border-bottom:1px solid #ddd;">المنصرف فعلياً</th>
                <th style="padding:8px; border-bottom:1px solid #ddd;">إجراء</th>
            </tr>
        </thead>
        <tbody>
            ${currentRequest.items.map(i => {
                const isCancelled = i.status === 'cancelled' || i.status === 'rejected';
                return `
                <tr id="row-item-${i.item_id}" style="border-bottom:1px solid #eee; ${isCancelled ? 'background-color:#ffebee;' : ''}">
                    <td style="padding:8px;">
                        <strong>${i.item_name}</strong>
                        ${isCancelled ? '<br><span class="badge badge-danger">ملغي</span>' : ''}
                    </td>
                    <td style="padding:8px;">${i.requested_qty} ${i.unit}</td>
                    <td style="padding:8px;">
                        <input type="number" step="0.01" class="form-control app-qty" data-id="${i.item_id}" data-req="${i.requested_qty}"
                               value="${isCancelled ? 0 : (i.approved_qty || i.requested_qty)}" 
                               ${currentRequest.status !== 'pending' || isCancelled ? 'disabled' : ''}>
                    </td>
                    <td style="padding:8px;">
                        <input type="number" step="0.01" class="form-control iss-qty" data-id="${i.item_id}" 
                               value="${isCancelled ? 0 : (i.issued_qty || i.approved_qty || i.requested_qty)}" 
                               ${currentRequest.status !== 'approved' || isCancelled ? 'disabled' : ''}>
                    </td>
                    <td style="padding:8px;">
                        ${currentRequest.status === 'pending' && !isCancelled ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="toggleItemCancel(${i.item_id})">إلغاء الصنف</button>
                        ` : ''}
                        ${currentRequest.status === 'pending' && isCancelled ? `
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleItemCancel(${i.item_id})">تراجع عن الإلغاء</button>
                        ` : ''}
                        <input type="hidden" class="item-status" data-id="${i.item_id}" value="${i.status || 'pending'}">
                        <div id="item-cancel-div-${i.item_id}" style="display:${isCancelled ? 'block' : 'none'}; margin-top:5px;">
                            <input type="text" class="form-control form-control-sm item-reason" data-id="${i.item_id}" 
                                   placeholder="سبب إلغاء الصنف..." value="${i.rejection_reason || ''}" ${currentRequest.status !== 'pending' ? 'disabled' : ''}>
                        </div>
                    </td>
                </tr>
            `}).join('')}
        </tbody>
    </table>`;

    content.innerHTML = `
        <div class="grid grid-2 gap-16 mb-16 p-20">
            <div class="p-12 bg-light rounded"><strong>الموظف:</strong> ${currentRequest.requester_name}</div>
            <div class="p-12 bg-light rounded"><strong>الحالة:</strong> ${translateStatus(currentRequest.status)}</div>
            <div class="p-12 bg-light rounded"><strong>تاريخ الطلب:</strong> <span dir="ltr">${formatDate(currentRequest.created_at)}</span></div>
            ${currentRequest.status === 'received' ? `
            <div class="p-12 rounded" style="background:#e0f2f1; color:#00695c; grid-column:1/-1; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-check-double fa-lg"></i>
                <div><strong>تم تأكيد الاستلام بواسطة الموظف</strong><br><span dir="ltr" style="font-size:.85rem">${formatDate(currentRequest.received_at || currentRequest.updated_at)}</span></div>
            </div>` : ''}
        </div>
        <div style="overflow-x:auto; padding:0 20px;">
            ${itemsTable}
        </div>
        <div class="form-group mt-16 p-20" id="reject-reason-group" style="${['rejected', 'cancelled'].includes(currentRequest.status) ? '' : 'display:none'}">
            <label>سبب الإلغاء / الملاحظات (إلزامي عند رفض الطلب بالكامل)</label>
            <textarea id="act-reason" class="form-control" ${['issued', 'rejected', 'cancelled', 'received'].includes(currentRequest.status) ? 'disabled' : ''}>${currentRequest.rejection_reason || ''}</textarea>
        </div>
    `;

    footer.innerHTML = '<button class="btn btn-secondary" onclick="closeModal(\'request-action-modal\')">إغلاق</button>';
    
    if (currentRequest.status === 'pending') {
        footer.innerHTML += `
            <button class="btn btn-danger" onclick="toggleReasonAction('rejected')"><i class="fas fa-times"></i> رفض الطلب بالكامل</button>
            <button class="btn btn-danger hidden" id="confirm-rejected-btn" onclick="updateRequest('rejected')">تأكيد الرفض التام</button>
            <button class="btn btn-primary" onclick="updateRequest('approved')"><i class="fas fa-check"></i> اعتماد الكميات</button>
        `;
    } else if (currentRequest.status === 'approved') {
        footer.innerHTML += `
            <button class="btn btn-danger" onclick="toggleReasonAction('cancelled')"><i class="fas fa-ban"></i> إلغاء الصرف</button>
            <button class="btn btn-danger hidden" id="confirm-cancelled-btn" onclick="updateRequest('cancelled')">تأكيد الإلغاء التام</button>
            <button class="btn btn-success" onclick="updateRequest('issued')"><i class="fas fa-dolly"></i> تأكيد خروج الموارد من المخزن</button>
        `;
    }

    document.getElementById('request-action-modal').classList.remove('hidden');
}

function toggleItemCancel(itemId) {
    const row = document.getElementById('row-item-' + itemId);
    const reasonDiv = document.getElementById('item-cancel-div-' + itemId);
    const statusInput = document.querySelector(`.item-status[data-id="${itemId}"]`);
    const reasonInput = document.querySelector(`.item-reason[data-id="${itemId}"]`);
    const appQtyInput = document.querySelector(`.app-qty[data-id="${itemId}"]`);
    const btn = event.target;

    if (statusInput.value === 'cancelled' || statusInput.value === 'rejected') {
        statusInput.value = 'pending';
        reasonDiv.style.display = 'none';
        row.style.backgroundColor = '';
        appQtyInput.disabled = false;
        appQtyInput.value = appQtyInput.dataset.req;
        btn.textContent = 'إلغاء الصنف';
        btn.className = 'btn btn-sm btn-outline-danger';
    } else {
        statusInput.value = 'cancelled';
        reasonDiv.style.display = 'block';
        row.style.backgroundColor = '#ffebee';
        appQtyInput.value = 0;
        appQtyInput.disabled = true;
        reasonInput.focus();
        btn.textContent = 'تراجع عن الإلغاء';
        btn.className = 'btn btn-sm btn-outline-secondary';
    }
}

function toggleReasonAction(actionType) {
    document.getElementById('reject-reason-group').style.display = 'block';
    event.target.classList.add('hidden');
    document.getElementById('confirm-' + actionType + '-btn').classList.remove('hidden');
    document.getElementById('act-reason').focus();
}

async function updateRequest(status) {
    const actReason = document.getElementById('act-reason').value.trim();
    if ((status === 'rejected' || status === 'cancelled') && !actReason) {
        showToast('يرجى كتابة سبب الإلغاء/الرفض في الملاحظات بالأسفل', 'warning');
        return;
    }

    const items = [];
    let hasValidationError = false;
    let errorMessage = '';

    document.querySelectorAll('.item-status').forEach(el => {
        const itemId = el.dataset.id;
        const itemStatus = el.value;
        const itemReason = document.querySelector(`.item-reason[data-id="${itemId}"]`).value.trim();
        const reqQty = parseFloat(document.querySelector(`.app-qty[data-id="${itemId}"]`).dataset.req);
        const appQty = parseFloat(document.querySelector(`.app-qty[data-id="${itemId}"]`).value) || 0;
        const issQty = parseFloat(document.querySelector(`.iss-qty[data-id="${itemId}"]`).value) || 0;
        
        if (itemStatus === 'cancelled' && !itemReason && status !== 'rejected' && status !== 'cancelled') {
            hasValidationError = true;
            errorMessage = 'يرجى ذكر سبب الإلغاء لكل صنف ملغي';
        }

        if (status === 'approved' && itemStatus !== 'cancelled' && appQty > reqQty) {
            hasValidationError = true;
            errorMessage = 'لا يمكن اعتماد كمية أكبر من الكمية المطلوبة';
        }
        if (status === 'issued' && itemStatus !== 'cancelled' && issQty > appQty) {
            hasValidationError = true;
            errorMessage = 'لا يمكن أن يكون المنصرف فعلياً أكبر من الكمية المعتمدة';
        }

        items.push({ 
            item_id: itemId, 
            approved_qty: appQty, 
            issued_qty: issQty, 
            status: itemStatus, 
            rejection_reason: itemReason 
        });
    });

    if (hasValidationError) {
        showToast(errorMessage, 'danger');
        return;
    }

    const payload = {
        request_id: currentRequest.id,
        status: status,
        reason: actReason,
        items: items
    };

    const res = await apiCall('/api/inventory.php?action=update_request_status', 'POST', payload);
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('request-action-modal');
        loadRequests();
    } else {
        showToast(res.message, 'danger');
    }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', loadRequests);
</script>

<style>
.requests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.order-card {
    height: 100%;
}

.status-pending { background: #fff8e1; color: #ff8f00; }
.status-approved { background: #e3f2fd; color: #1976d2; }
.status-issued { background: #e8f5e9; color: #2e7d32; }
.status-received { background: #e0f2f1; color: #00897b; }
.status-rejected { background: #ffebee; color: #c62828; }
.status-cancelled { background: #f5f5f5; color: #757575; }

.search-box {
    position: relative;
    width: 250px;
}

.search-box i {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-box .form-control {
    padding-right: 35px;
    border-radius: 20px;
}

.bg-light { background: #f8f9fa; }
.rounded { border-radius: 8px; }
.p-12 { padding: 12px; }
.p-20 { padding: 20px; }
</style>

<?php adminFooter(); ?>
