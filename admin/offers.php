<?php
require_once __DIR__ . '/_layout.php';
adminHeader('العروض والتخفيضات', 'offers');
?>

<style>
    /* Tabs Integration within Card */
    .tabs-container { 
        display: flex; 
        gap: 5px; 
    }
    .tab-btn {
        padding: 12px 20px;
        border: none;
        background: none;
        color: var(--text-muted);
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        border-bottom: 3px solid transparent;
    }
    .tab-btn:hover { background: rgba(0,0,0,0.02); }
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: rgba(230,126,34,0.05);
    }
    
    .tab-section { animation: fadeIn 0.3sease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    
    /* Combo Item Selector Helper */
    .item-add-row {
        display: grid;
        grid-template-columns: 1fr 100px 90px;
        gap: 10px;
        background: #fafafa;
        padding: 12px;
        border-radius: 10px;
        border: 1px dashed var(--border);
    }
</style>

<!-- Page Header Card -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0"><i class="fas fa-gift"></i> نظام العروض والتخفيضات</h3>
      <div id="section-actions">
          <!-- Action buttons will be placed here or inside tabs depending on context -->
      </div>
    </div>
  </div>
</div>

<!-- Main Content Card -->
<div class="card">
    <div class="card-header" style="padding-bottom:0; display:block">
        <div class="tabs-container">
            <button class="tab-btn active" id="tab-combos" onclick="switchTab('combos')">
                <i class="fas fa-box-open"></i> الباقات (Combos)
            </button>
            <button class="tab-btn" id="tab-item-discounts" onclick="switchTab('item-discounts')">
                <i class="fas fa-tag"></i> تخفيضات الأصناف
            </button>
            <button class="tab-btn" id="tab-cat-discounts" onclick="switchTab('cat-discounts')">
                <i class="fas fa-folder-open"></i> تخفيضات الفئات
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        
        <!-- ============================================ -->
        <!-- TAB: COMBOS                                  -->
        <!-- ============================================ -->
        <div id="section-combos" class="tab-section">
            <div style="padding:15px; border-bottom:1px solid var(--border); background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span class="text-muted" style="font-size:.9rem">إدارة باقات الوجبات والعروض المجمعة</span>
                <button class="btn btn-primary btn-sm" onclick="openComboModal()">
                    <i class="fas fa-plus"></i> باقة جديدة
                </button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>اسم الباقة</th>
                            <th>السعر</th>
                            <th>محتويات الباقة</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="combosTableTbody">
                        <tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB: ITEM DISCOUNTS                          -->
        <!-- ============================================ -->
        <div id="section-item-discounts" class="tab-section" style="display:none;">
            <div style="padding:15px; border-bottom:1px solid var(--border); background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span class="text-muted" style="font-size:.9rem">تطبيق خصومات مباشرة على أصناف محددة</span>
                <button class="btn btn-primary btn-sm" onclick="openDiscountModal('item')">
                    <i class="fas fa-plus"></i> تخفيض جديد لصنف
                </button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>الصنف المستهدف</th>
                            <th>نوع التخفيض</th>
                            <th>قيمة التخفيض</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="itemDiscountsTableTbody">
                        <tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB: CATEGORY DISCOUNTS                      -->
        <!-- ============================================ -->
        <div id="section-cat-discounts" class="tab-section" style="display:none;">
            <div style="padding:15px; border-bottom:1px solid var(--border); background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span class="text-muted" style="font-size:.9rem">تطبيق خصم مئوي أو ثابت على فئة كاملة</span>
                <button class="btn btn-primary btn-sm" onclick="openDiscountModal('category')">
                    <i class="fas fa-plus"></i> تخفيض جديد لفئة كاملة
                </button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>الفئة المستهدفة</th>
                            <th>نوع التخفيض</th>
                            <th>قيمة التخفيض</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="catDiscountsTableTbody">
                        <tr><td colspan="5" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>


<!-- Modal: Add Combo -->
<div class="modal-backdrop hidden" id="comboModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>إضافة باقة جديدة</h3>
            <button class="modal-close" onclick="closeComboModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">اسم الباقة (مثال: عرض الغداء)</label>
                <input type="text" id="comboName" class="form-control" placeholder="أدخل اسم الباقة...">
            </div>
            <div class="form-group">
                <label class="form-label">سعر الباقة (السعر النهائي)</label>
                <input type="number" id="comboPrice" class="form-control" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
                <label class="form-label">محتويات الباقة</label>
                <div class="item-add-row">
                    <select id="comboItemSelect" class="form-control"></select>
                    <input type="number" id="comboItemQty" class="form-control" placeholder="الكمية" value="1" min="1">
                    <button class="btn btn-secondary" onclick="addComboItem()"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            
            <div class="table-wrapper" style="margin-top: 15px; border: 1px solid var(--border); border-radius: 8px;">
                <table>
                    <thead>
                        <tr><th>الصنف</th><th>الكمية</th><th>إزالة</th></tr>
                    </thead>
                    <tbody id="comboSelectedItemsTbody">
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeComboModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="saveCombo()"><i class="fas fa-save"></i> حفظ الباقة</button>
        </div>
    </div>
</div>

<!-- Modal: Add Discount -->
<div class="modal-backdrop hidden" id="discountModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="discountModalTitle">إضافة تخفيض</h3>
            <button class="modal-close" onclick="closeDiscountModal()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="discountTypeField">
            <div class="form-group">
                <label class="form-label" id="discountTargetLabel">الهدف</label>
                <select id="discountTargetSelect" class="form-control"></select>
            </div>
            <div class="form-group">
                <label class="form-label">نوع التخفيض</label>
                <select id="discountCalcType" class="form-control" onchange="updateDiscountSuffix()">
                    <option value="fixed">مبلغ ثابت (ريال)</option>
                    <option value="percent">نسبة مئوية (%)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">قيمة التخفيض <span id="discountSuffix"></span></label>
                <input type="number" id="discountValue" class="form-control" step="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDiscountModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="saveDiscount()"><i class="fas fa-save"></i> حفظ التخفيض</button>
        </div>
    </div>
</div>

<script>
let g_items = [];
let g_categories = [];
let g_comboItems = []; // [{id, name, qty}]

function switchTab(tabId) {
    document.querySelectorAll('.tab-section').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('section-' + tabId).style.display = 'block';
    document.getElementById('tab-' + tabId).classList.add('active');
    
    if (tabId === 'combos') loadCombos();
    else loadDiscounts();
}

async function loadCombos() {
    try {
        const res = await apiCall('/api/offers.php?type=combos');
        if (!res.success) return;
        
        g_items = res.data.items;
        
        const tbody = document.getElementById('combosTableTbody');
        if (res.data.combos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">لا توجد باقات حالياً</td></tr>';
            return;
        }
        
        tbody.innerHTML = res.data.combos.map(o => `
            <tr>
                <td><strong>${o.name_ar}</strong></td>
                <td><span style="color:var(--primary); font-weight:bold;">${parseFloat(o.price).toFixed(2)}</span></td>
                <td>
                    <ul style="margin:0; padding-right:15px; font-size:0.9rem">
                        ${o.items.map(i => `<li>${i.quantity}x ${i.item_name}</li>`).join('')}
                    </ul>
                </td>
                <td>
                    <span class="badge ${o.is_active==1 ? 'badge-success' : 'badge-danger'}">
                        ${o.is_active==1 ? 'فعال' : 'متوقف'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleCombo(${o.id}, ${o.is_active==1 ? 0 : 1})">
                        ${o.is_active==1 ? 'إيقاف' : 'تفعيل'}
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCombo(${o.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (e) { console.error(e); }
}

async function loadDiscounts() {
    try {
        const res = await apiCall('/api/offers.php?type=discounts');
        if (!res.success) return;
        
        g_items = res.data.items;
        g_categories = res.data.categories;
        
        const itemTb = document.getElementById('itemDiscountsTableTbody');
        const catTb = document.getElementById('catDiscountsTableTbody');
        
        const itemDiscounts = res.data.discounts.filter(d => d.type === 'item');
        const catDiscounts = res.data.discounts.filter(d => d.type === 'category');
        
        renderDiscountsTable(itemDiscounts, itemTb);
        renderDiscountsTable(catDiscounts, catTb);
        
    } catch (e) { console.error(e); }
}

function renderDiscountsTable(arr, tbody) {
    if (arr.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">لا توجد تخفيضات</td></tr>';
        return;
    }
    tbody.innerHTML = arr.map(d => `
        <tr>
            <td><strong>${d.target_name}</strong></td>
            <td>${d.discount_type === 'percent' ? 'نسبة مئوية' : 'مبلغ ثابت'}</td>
            <td><span style="color:var(--danger); font-weight:bold;">-${parseFloat(d.discount_value)} ${d.discount_type==='percent'?'%':'ريال'}</span></td>
            <td>
                <span class="badge ${d.is_active==1 ? 'badge-success' : 'badge-danger'}">
                    ${d.is_active==1 ? 'فعال' : 'متوقف'}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleDiscount(${d.id}, ${d.is_active==1 ? 0 : 1})">
                    ${d.is_active==1 ? 'إيقاف' : 'تفعيل'}
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteDiscount(${d.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function openComboModal() {
    document.getElementById('comboName').value = '';
    document.getElementById('comboPrice').value = '';
    g_comboItems = [];
    renderSelectedComboItems();
    
    const sel = document.getElementById('comboItemSelect');
    sel.innerHTML = g_items.map(i => `<option value="${i.id}">${i.name_ar}</option>`).join('');
    
    document.getElementById('comboModal').classList.remove('hidden');
}

function closeComboModal() {
    document.getElementById('comboModal').classList.add('hidden');
}

function addComboItem() {
    const sel = document.getElementById('comboItemSelect');
    const id = sel.value;
    const name = sel.options[sel.selectedIndex].text;
    const qty = parseInt(document.getElementById('comboItemQty').value) || 1;
    
    const exist = g_comboItems.find(i => i.id == id);
    if(exist) exist.qty += qty;
    else g_comboItems.push({id, name, qty});
    
    renderSelectedComboItems();
}

function removeComboItem(idx) {
    g_comboItems.splice(idx, 1);
    renderSelectedComboItems();
}

function renderSelectedComboItems() {
    const tb = document.getElementById('comboSelectedItemsTbody');
    if(g_comboItems.length === 0) { tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted">لم يتم إضافة أصناف</td></tr>'; return; }
    
    tb.innerHTML = g_comboItems.map((i, idx) => `
        <tr>
            <td>${i.name}</td>
            <td>${i.qty}</td>
            <td><button class="btn btn-sm btn-danger" onclick="removeComboItem(${idx})"><i class="fas fa-times"></i></button></td>
        </tr>
    `).join('');
}

async function saveCombo() {
    const payload = {
        name_ar: document.getElementById('comboName').value,
        price: parseFloat(document.getElementById('comboPrice').value),
        items: g_comboItems
    };
    
    const res = await apiCall('/api/offers.php?action=create_combo', 'POST', payload);
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) {
        closeComboModal();
        loadCombos();
    }
}

async function deleteCombo(id) {
    if(!confirm('حذف هذه الباقة بشكل نهائي؟')) return;
    const res = await apiCall('/api/offers.php?action=delete_combo', 'POST', {id});
    showToast(res.message, res.success ? 'success' : 'danger');
    if(res.success) loadCombos();
}

async function toggleCombo(id, status) {
    const res = await apiCall('/api/offers.php?action=toggle_combo', 'POST', {id, status});
    showToast(res.message, res.success ? 'success' : 'danger');
    if(res.success) loadCombos();
}

// Discount logic
function openDiscountModal(type) {
    document.getElementById('discountTypeField').value = type;
    document.getElementById('discountModalTitle').textContent = type === 'item' ? 'إضافة تخفيض لصنف' : 'إضافة تخفيض لفئة';
    document.getElementById('discountTargetLabel').textContent = type === 'item' ? 'اختر الصنف' : 'اختر الفئة';
    
    const sel = document.getElementById('discountTargetSelect');
    if (type === 'item') {
        sel.innerHTML = g_items.map(i => `<option value="${i.id}">${i.name_ar}</option>`).join('');
    } else {
        sel.innerHTML = g_categories.map(c => `<option value="${c.id}">${c.name_ar}</option>`).join('');
    }
    
    document.getElementById('discountValue').value = '';
    updateDiscountSuffix();
    document.getElementById('discountModal').classList.remove('hidden');
}

function closeDiscountModal() { document.getElementById('discountModal').classList.add('hidden'); }

function updateDiscountSuffix() {
    const type = document.getElementById('discountCalcType').value;
    document.getElementById('discountSuffix').textContent = type === 'percent' ? '(%)' : '(ريال)';
}

async function saveDiscount() {
    const payload = {
        type: document.getElementById('discountTypeField').value,
        target_id: document.getElementById('discountTargetSelect').value,
        discount_type: document.getElementById('discountCalcType').value,
        discount_value: document.getElementById('discountValue').value
    };
    const res = await apiCall('/api/offers.php?action=create_discount', 'POST', payload);
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) {
        closeDiscountModal();
        loadDiscounts();
    }
}

async function deleteDiscount(id) {
    if(!confirm('حذف هذا التخفيض؟')) return;
    const res = await apiCall('/api/offers.php?action=delete_discount', 'POST', {id});
    showToast(res.message, res.success ? 'success' : 'danger');
    if(res.success) loadDiscounts();
}

async function toggleDiscount(id, status) {
    const res = await apiCall('/api/offers.php?action=toggle_discount', 'POST', {id, status});
    showToast(res.message, res.success ? 'success' : 'danger');
    if(res.success) loadDiscounts();
}

// Init
window.addEventListener('DOMContentLoaded', () => {
    loadCombos();
});
</script>

<?php adminFooter(); ?>
