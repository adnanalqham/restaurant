<?php
/** @noinspection PhpUndefinedFunctionInspection */
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة المخازن والمشتريات', 'inventory');
?>

<div class="tabs-container mb-24">
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('items', this)"><i class="fas fa-boxes"></i> الأصناف والكميات</button>
        <button class="tab-btn" onclick="switchTab('purchases', this)"><i class="fas fa-shopping-cart"></i> المشتريات اليومية</button>
    </div>
</div>

<!-- Tab: Items -->
<div id="tab-items" class="tab-content">
    <div class="card">
        <div class="card-header flex justify-between align-center">
            <h3>قائمة الأصناف بالمخزن</h3>
            <button class="btn btn-primary" onclick="openItemModal()"><i class="fas fa-plus"></i> إضافة صنف جديد</button>
        </div>
        <div class="card-body">
            <table class="table" id="items-table">
                <thead>
                    <tr>
                        <th>رقم الصنف</th>
                        <th>الاسم</th>
                        <th>الوحدة</th>
                        <th>الرصيد الحالي</th>
                        <th>أدنى كمية</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="items-list">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab: Purchases -->
<div id="tab-purchases" class="tab-content" style="display:none">
    <div class="card">
        <div class="card-header flex justify-between align-center">
            <h3>سجل المشتريات والمدخلات</h3>
            <button class="btn btn-success" onclick="openPurchaseModal()"><i class="fas fa-plus"></i> إضافة مشتريات</button>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table" id="purchases-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>رقم الفاتورة</th>
                            <th>الصنف</th>
                            <th>الكمية</th>
                            <th>السعر الإجمالي</th>
                            <th>المورد / المسلم</th>
                            <th>ملاحظات</th>
                            <th>بواسطة</th>
                        </tr>
                    </thead>
                    <tbody id="purchases-list">
                        <tr><td colspan="8" class="text-center text-muted" style="padding:20px">جاري تحميل البيانات...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal-backdrop hidden" id="item-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 id="item-modal-title">إضافة صنف مخزني</h3>
            <button class="modal-close" onclick="closeModal('item-modal')">✕</button>
        </div>
        <form onsubmit="saveItem(event)">
            <div class="modal-body">
                <input type="hidden" id="item_id">
                <div class="form-group">
                    <label>رقم الصنف</label>
                    <input type="text" id="item_number" class="form-control" placeholder="اختياري">
                </div>
                <div class="form-group">
                    <label>اسم الصنف</label>
                    <input type="text" id="item_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>الوحدة</label>
                    <input type="text" id="item_unit" class="form-control" placeholder="كيلو، حبة، كرتون..." required>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:10px">
                    <input type="checkbox" id="item_is_active" checked style="width:20px;height:20px;cursor:pointer">
                    <label for="item_is_active" style="margin:0;cursor:pointer">الصنف متاح ونشط</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('item-modal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ الصنف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop hidden" id="purchase-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3>تسجيل مشتريات / توريد</h3>
            <button class="modal-close" onclick="closeModal('purchase-modal')">✕</button>
        </div>
        <form onsubmit="savePurchase(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label>الصنف</label>
                    <select id="purchase_item_id" class="form-control" required>
                        <!-- Loaded via JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label>الكمية الموردة</label>
                    <div style="display:flex; gap:8px; align-items:center">
                        <input type="number" id="purchase_qty" step="0.01" class="form-control" required style="flex:1">
                        <span id="purchase_unit_label" class="text-muted" style="font-weight:600; min-width:50px; background:#f8f9fa; padding:8px; border-radius:4px; border:1px solid #ddd; text-align:center">--</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>سعر الوحدة</label>
                    <input type="number" id="purchase_price" step="0.01" class="form-control">
                </div>
                <div class="form-group" style="display:flex; gap:10px">
                    <div style="flex:1">
                        <label>المورد / المسلم</label>
                        <input type="text" id="purchase_supplier" class="form-control" placeholder="اسم المورد أو الشخص">
                    </div>
                    <div style="flex:1">
                        <label>رقم الفاتورة</label>
                        <input type="text" id="purchase_invoice" class="form-control" placeholder="رقم السند/الفاتورة">
                    </div>
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea id="purchase_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('purchase-modal')">إلغاء</button>
                <button type="submit" class="btn btn-success">تأكيد التوريد</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Request Approval/Issuing -->
<div class="modal-backdrop hidden" id="request-action-modal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3>معالجة طلب الصرف #<span id="act-request-id"></span></h3>
            <button class="modal-close" onclick="closeModal('request-action-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="request-action-content"></div>
        </div>
        <div class="modal-footer" id="request-action-footer">
            <!-- Buttons dynamically added -->
        </div>
    </div>
</div>

<script>
let allItems = [];

function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    if (btn) btn.classList.add('active');
    
    if (tab === 'items') loadItems();
    if (tab === 'purchases') loadPurchases();
}

async function loadItems() {
    const res = await apiCall('/api/inventory.php?action=get_items');
    if (res.success) {
        allItems = res.data;
        renderItems();
        fillItemSelects();
    }
}

function renderItems() {
    const list = document.getElementById('items-list');
    if (!list) return;
    list.innerHTML = allItems.map(i => {
        const inactiveBadge = (i.is_active != null && i.is_active == 0) ? '<span class="badge badge-warning" style="margin-right:5px">غير متاح</span>' : '';
        return `
        <tr style="${(i.is_active != null && i.is_active == 0) ? 'opacity:0.6' : ''}">
            <td>${i.item_number || '-'}</td>
            <td><strong>${i.name}</strong> ${inactiveBadge}</td>
            <td>${i.unit}</td>
            <td><span class="badge ${parseFloat(i.current_stock) <= parseFloat(i.min_stock) ? 'badge-danger' : 'badge-info'}">${i.current_stock}</span></td>
            <td>${i.min_stock}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="editItem(${i.id})"><i class="fas fa-edit"></i></button>
            </td>
        </tr>
    `}).join('');
}

function fillItemSelects() {
    const select = document.getElementById('purchase_item_id');
    if (select) {
        select.innerHTML = '<option value="">-- اختر الصنف --</option>' + 
            allItems.filter(i => i.is_active != 0).map(i => `<option value="${i.id}">${i.name} (${i.unit})</option>`).join('');
            
        // Update unit label when item changes
        select.onchange = function() {
            const itemId = this.value;
            const item = allItems.find(i => i.id == itemId);
            const label = document.getElementById('purchase_unit_label');
            if (label) {
                label.textContent = item ? item.unit : '--';
                label.style.color = item ? 'var(--primary)' : '#999';
            }
        };
    }
}

function openItemModal() {
    document.getElementById('item-modal-title').textContent = 'إضافة صنف مخزني';
    document.getElementById('item_id').value = '';
    document.getElementById('item_number').value = '';
    document.getElementById('item_name').value = '';
    document.getElementById('item_unit').value = '';
    document.getElementById('item_is_active').checked = true;
    document.getElementById('item-modal').classList.remove('hidden');
}

function editItem(id) {
    const item = allItems.find(i => i.id == id);
    if (!item) return;
    
    document.getElementById('item-modal-title').textContent = 'تعديل صنف مخزني';
    document.getElementById('item_id').value = item.id;
    document.getElementById('item_number').value = item.item_number || '';
    document.getElementById('item_name').value = item.name;
    document.getElementById('item_unit').value = item.unit;
    document.getElementById('item_is_active').checked = (item.is_active == null || item.is_active == 1);
    
    document.getElementById('item-modal').classList.remove('hidden');
}

async function saveItem(e) {
    e.preventDefault();
    const id = document.getElementById('item_id').value;
    const payload = {
        id: id,
        item_number: document.getElementById('item_number').value,
        name: document.getElementById('item_name').value,
        unit: document.getElementById('item_unit').value,
        is_active: document.getElementById('item_is_active').checked ? 1 : 0
    };
    
    const action = id ? 'update_item' : 'add_item';
    const res = await apiCall('/api/inventory.php?action=' + action, 'POST', payload);
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('item-modal');
        loadItems();
    } else {
        showToast(res.message, 'danger');
    }
}

function openPurchaseModal() {
    if (allItems.length === 0) {
        showToast('يرجى إضافة أصناف أولاً', 'warning');
        return;
    }
    document.getElementById('purchase-modal').classList.remove('hidden');
}

async function savePurchase(e) {
    e.preventDefault();
    const payload = {
        item_id: document.getElementById('purchase_item_id').value,
        quantity: document.getElementById('purchase_qty').value,
        price: document.getElementById('purchase_price').value,
        supplier_name: document.getElementById('purchase_supplier').value,
        invoice_number: document.getElementById('purchase_invoice').value,
        notes: document.getElementById('purchase_notes').value
    };
    const res = await apiCall('/api/inventory.php?action=add_purchase', 'POST', payload);
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('purchase-modal');
        loadItems();
        if (document.getElementById('tab-purchases').style.display !== 'none') {
            loadPurchases();
        }
    } else {
        showToast(res.message, 'danger');
    }
}

async function loadPurchases() {
    const res = await apiCall('/api/inventory.php?action=get_purchases');
    const tbody = document.getElementById('purchases-list');
    if (!res.success) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">خطأ في تحميل المشتريات</td></tr>';
        return;
    }
    
    const purchases = res.data;
    if (!purchases.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:20px">لا توجد سجلات مشتريات حتى الآن</td></tr>';
        return;
    }

    tbody.innerHTML = purchases.map(p => {
        const total = (parseFloat(p.quantity) * parseFloat(p.price)).toFixed(2);
        const date = new Date(p.created_at).toLocaleString('en-US', {hour12: true});
        return `
        <tr>
            <td dir="ltr" style="text-align:right">${date}</td>
            <td><span class="badge badge-secondary">${p.invoice_number || '-'}</span></td>
            <td><strong>${p.item_name}</strong></td>
            <td><span class="badge badge-info">${p.quantity} ${p.unit}</span></td>
            <td><strong>${total}</strong></td>
            <td>${p.supplier_name || '-'}</td>
            <td style="color:#666;font-size:0.85rem">${p.notes || '-'}</td>
            <td>${p.added_by_name || '-'}</td>
        </tr>
    `}).join('');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Initial load
document.addEventListener('DOMContentLoaded', loadItems);
</script>

<style>
.tabs-container { background: #fff; padding: 10px; border-radius: 8px; border: 1px solid var(--border); }
.tabs { display: flex; gap: 10px; }
.tab-btn { 
    flex: 1; padding: 12px; border: none; background: #f8f9fa; border-radius: 6px; 
    cursor: pointer; font-weight: 600; color: var(--text-muted); transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.tab-btn:hover { background: #eee; }
.tab-btn.active { background: var(--primary); color: #fff; }

.status-pending { color: var(--warning); font-weight: bold; }
.status-approved { color: var(--primary); font-weight: bold; }
.status-issued { color: var(--success); font-weight: bold; }
.status-rejected { color: var(--danger); font-weight: bold; }
.request-card { transition: background 0.2s; }
.request-card:hover { background: #f9f9f9; }
</style>

<?php adminFooter(); ?>
