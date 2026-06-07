<?php
require_once __DIR__ . '/_layout.php';
waiterHeader('طلب موارد من المخزن', 'inventory_requests');
?>

<div class="card mb-16">
    <div class="card-header flex justify-between align-center">
        <h3>طلباتي من المخزن</h3>
        <button class="btn btn-primary" onclick="openNewRequestModal()"><i class="fas fa-plus-circle"></i> طلب جديد</button>
    </div>
    <div class="card-body">
        <div id="my-requests-list" class="requests-grid">
            <div class="text-center p-20" style="grid-column:1/-1"><div class="spinner"></div> جاري تحميل الطلبات...</div>
        </div>
    </div>
</div>

<!-- Modal: New Request -->
<div class="modal-backdrop hidden" id="new-request-modal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3>إنشاء طلب موارد جديد</h3>
            <button class="modal-close" onclick="closeModal('new-request-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>اختر الأصناف المطلوبة</label>
                <div class="flex gap-8 mb-8">
                    <select id="req-item-select" class="form-control" style="flex:1">
                        <!-- Loaded via JS -->
                    </select>
                    <input type="number" id="req-item-qty" class="form-control" style="width:100px" placeholder="الكمية">
                    <button class="btn btn-info" onclick="addItemToRequest()"><i class="fas fa-plus"></i></button>
                </div>
                <table class="table" id="req-items-table">
                    <thead><tr><th>الصنف</th><th>الكمية</th><th>حذف</th></tr></thead>
                    <tbody id="req-items-list">
                        <!-- Selected items will appear here -->
                    </tbody>
                </table>
            </div>
            <div class="form-group">
                <label>ملاحظات إضافية</label>
                <textarea id="req-notes" class="form-control" rows="2" placeholder="مثال: نحتاجه للغداء اليوم..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('new-request-modal')">إلغاء</button>
            <button class="btn btn-primary" onclick="submitRequest()"><i class="fas fa-paper-plane"></i> إرسال الطلب</button>
        </div>
    </div>
</div>

<!-- Request Detail Modal -->
<div class="modal-backdrop hidden" id="request-detail-modal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-list"></i> تفاصيل طلب الموارد <span id="detail-request-number"></span></h3>
            <button class="modal-close" onclick="closeModal('request-detail-modal')">✕</button>
        </div>
        <div class="modal-body" id="request-detail-body"></div>
        <div class="modal-footer" id="request-detail-actions">
            <button class="btn btn-secondary" onclick="closeModal('request-detail-modal')">إغلاق</button>
        </div>
    </div>
</div>

<script>
let inventoryItems = [];
let selectedRequestItems = [];
let allMyRequests = [];

const statusMap = {
    'pending':  { label: 'قيد الانتظار', class: 'status-pending',  icon: 'fa-clock' },
    'approved': { label: 'تمت الموافقة',  class: 'status-approved', icon: 'fa-check' },
    'issued':   { label: 'تم الصرف',      class: 'status-issued',   icon: 'fa-truck-loading' },
    'received': { label: 'تم الاستلام',   class: 'status-received', icon: 'fa-check-double' },
    'rejected': { label: 'مرفوض',         class: 'status-rejected', icon: 'fa-times-circle' },
    'cancelled':{ label: 'ملغي',          class: 'status-cancelled',icon: 'fa-ban' }
};

function translateStatus(s) {
    return statusMap[s]?.label || s;
}

async function loadMyRequests() {
    const res = await apiCall('/api/inventory.php?action=get_requests');
    const container = document.getElementById('my-requests-list');
    if (!res || !res.success) {
        container.innerHTML = '<div class="text-center text-muted" style="grid-column:1/-1; padding:40px;">حدث خطأ أثناء تحميل الطلبات</div>';
        return;
    }
    allMyRequests = res.data;
    if (!res.data.length) {
        container.innerHTML = '<div class="text-center text-muted" style="grid-column:1/-1; padding:40px;">لا توجد طلبات سابقة</div>';
        return;
    }
    container.innerHTML = res.data.map(r => {
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
            </div>
            <div class="order-items-preview" style="flex:1">
                ${(r.items || []).slice(0,3).map(i => `
                    <div class="order-item-line" style="display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; border-bottom:1px dashed var(--border);">
                        <span>${i.item_name}</span>
                        <span style="color:var(--primary); font-weight:bold;">${i.requested_qty} ${i.unit}</span>
                    </div>
                `).join('')}
                ${(r.items||[]).length > 3 ? `<span style="color:var(--primary);font-size:.8rem">+ ${r.items.length-3} أصناف أخرى</span>` : ''}
            </div>
            ${r.status === 'rejected'  ? `<div style="font-size:.8rem;color:var(--danger);margin-top:8px"><i class="fas fa-times-circle"></i> سبب الرفض: ${r.rejection_reason || 'غير محدد'}</div>` : ''}
            ${r.status === 'issued'    ? `<div style="font-size:.8rem;color:var(--success);margin-top:8px"><i class="fas fa-truck-loading"></i> تم الصرف من المخزن، في انتظار تأكيد استلامك.</div>` : ''}
            ${r.status === 'received'  ? `<div style="font-size:.8rem;color:var(--success);margin-top:8px"><i class="fas fa-check-double"></i> تم استلام الكميات وإضافتها للمخزون الفرعي.</div>` : ''}
            <div class="order-card-footer" style="display:flex; justify-content:flex-end; align-items:center; border-top:1px solid var(--border); padding-top:10px; margin-top:10px;">
                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                    <button class="btn btn-secondary btn-sm" onclick="viewRequestDetails(${r.id})"><i class="fas fa-eye"></i> التفاصيل</button>
                    ${r.status === 'pending' ? `<button class="btn btn-warning btn-sm" onclick="cancelRequest(${r.id})"><i class="fas fa-times-circle"></i> إلغاء</button>` : ''}
                    ${r.status === 'issued'  ? `<button class="btn btn-success btn-sm" onclick="confirmReceipt(${r.id})"><i class="fas fa-box-open"></i> تأكيد الاستلام</button>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

function viewRequestDetails(id) {
    const r = allMyRequests.find(req => req.id == id);
    if (!r) return;
    document.getElementById('detail-request-number').textContent = '#' + r.id;
    document.getElementById('request-detail-body').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div><strong>الحالة:</strong> <span class="status-badge status-${r.status}">${translateStatus(r.status)}</span></div>
            <div><strong>تاريخ الإنشاء:</strong> <span dir="ltr">${formatDate(r.created_at)}</span></div>
            ${r.status === 'received' ? `<div style="grid-column:1/-1; background:#e0f2f1; border-radius:8px; padding:10px; display:flex; align-items:center; gap:8px; color:#00695c;">
                <i class="fas fa-check-double" style="font-size:1.1rem;"></i>
                <div>
                    <strong>تم تأكيد الاستلام</strong><br>
                    <span style="font-size:0.85rem;" dir="ltr">${formatDate(r.received_at || r.updated_at)}</span>
                </div>
            </div>` : ''}
        </div>
        ${r.notes ? `<div class="alert alert-info"><i class="fas fa-sticky-note"></i> ${r.notes}</div>` : ''}
        <table class="table">
            <thead><tr><th>الصنف</th><th>المطلوب</th><th>الكمية المعتمدة/المنصرفة</th><th>الحالة</th></tr></thead>
            <tbody>
                ${(r.items||[]).map(i => `<tr>
                    <td>${i.item_name}</td>
                    <td>${i.requested_qty} ${i.unit}</td>
                    <td>${(r.status === 'issued' || r.status === 'received') ? (i.issued_qty || 0) : (r.status === 'approved' ? (i.approved_qty || 0) : '-')} ${i.unit}</td>
                    <td>${(i.status === 'cancelled' || i.status === 'rejected') ? '<span class="badge badge-danger">ملغي</span>' : '<span class="badge badge-success">فعال</span>'}</td>
                </tr>`).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('request-detail-modal').classList.remove('hidden');
}

async function cancelRequest(id) {
    if (!confirmAction('هل أنت متأكد من إلغاء هذا الطلب؟')) return;
    const res = await apiCall('/api/inventory.php?action=update_request_status', 'POST', { request_id: id, status: 'cancelled', reason: 'إلغاء من قبل الموظف', items: [] });
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadMyRequests();
}

async function confirmReceipt(id) {
    if (!confirmAction('هل تؤكد استلام جميع الموارد وإضافتها لمخزون القسم؟')) return;
    const res = await apiCall('/api/inventory.php?action=update_request_status', 'POST', { request_id: id, status: 'received', items: [] });
    if (res.success) {
        showToast('تم تأكيد الاستلام بنجاح', 'success');
        await loadMyRequests();
    } else {
        showToast('خطأ: ' + (res.message || 'فشل التحديث'), 'danger');
        console.error('confirmReceipt error:', res);
    }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

async function openNewRequestModal() {
    if (inventoryItems.length === 0) {
        const res = await apiCall('/api/inventory.php?action=get_items');
        if (res.success) inventoryItems = res.data;
    }
    
    const select = document.getElementById('req-item-select');
    select.innerHTML = '<option value="">-- اختر صنف --</option>' + 
        inventoryItems.map(i => `<option value="${i.id}">${i.name} (${i.unit})</option>`).join('');
        
    selectedRequestItems = [];
    renderSelectedItems();
    document.getElementById('new-request-modal').classList.remove('hidden');
}

function addItemToRequest() {
    const select = document.getElementById('req-item-select');
    const qtyInput = document.getElementById('req-item-qty');
    const itemId = select.value;
    const qty = parseFloat(qtyInput.value);
    
    if (!itemId || isNaN(qty) || qty <= 0) {
        showToast('يرجى اختيار الصنف وتحديد كمية صحيحة', 'warning');
        return;
    }
    
    const item = inventoryItems.find(i => i.id == itemId);
    selectedRequestItems.push({ item_id: itemId, name: item.name, unit: item.unit, quantity: qty });
    
    qtyInput.value = '';
    renderSelectedItems();
}

function renderSelectedItems() {
    const list = document.getElementById('req-items-list');
    list.innerHTML = selectedRequestItems.map((i, index) => `
        <tr>
            <td>${i.name}</td>
            <td>${i.quantity} ${i.unit}</td>
            <td><button class="btn btn-sm btn-danger" onclick="removeItemFromRequest(${index})">✕</button></td>
        </tr>
    `).join('');
}

function removeItemFromRequest(index) {
    selectedRequestItems.splice(index, 1);
    renderSelectedItems();
}

async function submitRequest() {
    if (selectedRequestItems.length === 0) {
        showToast('يرجى إضافة أصناف للطلب أولاً', 'warning');
        return;
    }
    
    const payload = {
        items: selectedRequestItems,
        notes: document.getElementById('req-notes').value
    };
    
    const res = await apiCall('/api/inventory.php?action=create_request', 'POST', payload);
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('new-request-modal');
        loadMyRequests();
    } else {
        showToast(res.message, 'danger');
    }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// SSE listener for status updates
document.addEventListener('DOMContentLoaded', () => {
    loadMyRequests();
    if (typeof onSSE === 'function') {
        onSSE('inventory_request_status_updated', loadMyRequests);
    }
});
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
</style>

<?php waiterFooter(); ?>
