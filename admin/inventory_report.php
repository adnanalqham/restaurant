<?php
require_once __DIR__ . '/_layout.php';
adminHeader('تقرير حالة المخزون', 'inventory_report');
?>

<div class="card mb-16">
    <div class="card-body" style="padding:15px; display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <label class="form-label mb-5" style="display:block">اختر المخزن</label>
            <select id="warehouse-select" class="form-control" onchange="loadReport()">
                <option value="main">المخزن الرئيسي</option>
                <option value="kitchen">المطبخ</option>
                <option value="bar">البار</option>
                <option value="shisha">الشيشة / عصائر</option>
                <option value="hall">الصالة</option>
            </select>
        </div>
        <div style="flex:1; min-width:150px;">
            <label class="form-label mb-5" style="display:block">من تاريخ</label>
            <input type="date" id="from-date" class="form-control" onchange="loadReport()">
        </div>
        <div style="flex:1; min-width:150px;">
            <label class="form-label mb-5" style="display:block">إلى تاريخ</label>
            <input type="date" id="to-date" class="form-control" onchange="loadReport()">
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end; padding-top:25px;">
            <button class="btn btn-sm" onclick="exportExcel()" style="background:#27ae60; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600"><i class="fas fa-file-excel"></i> تصدير Excel</button>
            <button class="btn btn-sm" onclick="window.print()" style="background:#e74c3c; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600"><i class="fas fa-file-pdf"></i> تصدير PDF</button>
        </div>
    </div>
</div>

<div id="report-grid" class="inventory-grid">
    <!-- Loaded via JS -->
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    // Set default dates (from 1st of month to today)
    const today = new Date();
    document.getElementById('to-date').value = today.toISOString().split('T')[0];
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from-date').value = firstDay.toISOString().split('T')[0];
    
    // Fetch warehouses
    const res = await apiCall('/api/inventory_report.php?action=get_warehouses');
    if (res.success) {
        const select = document.getElementById('warehouse-select');
        select.innerHTML = res.data.map(w => `<option value="${w.id}">${w.name}</option>`).join('');
    }

    loadReport();
});

async function loadReport() {
    const warehouse = document.getElementById('warehouse-select').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;
    
    const grid = document.getElementById('report-grid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#666">جاري التحميل...</div>';

    const res = await apiCall(`/api/inventory_report.php?action=get_warehouse_stock&warehouse=${warehouse}&from_date=${fromDate}&to_date=${toDate}`);
    if (res.success) {
        if (!res.data || res.data.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#666">لا توجد بيانات لهذا المخزن في هذه الفترة.</div>';
            return;
        }

        grid.innerHTML = res.data.map(i => {
            let lastDate = i.last_receipt_date ? new Date(i.last_receipt_date).toLocaleString('en-US', {hour12:true}) : 'لا يوجد';
            return `
            <div class="inv-card" style="cursor:pointer" onclick="showItemDetails(${i.id}, '${i.name.replace(/'/g, "\\'")}', '${i.unit}')">
                <div class="inv-card-header">
                    <h4>${i.name}</h4>
                    <span class="badge badge-secondary">${i.item_number || '-'}</span>
                </div>
                <div class="inv-card-body">
                    <div class="inv-stat">
                        <span class="stat-label">${warehouse === 'main' ? 'المورد بالفترة' : 'المستلم بالفترة'}</span>
                        <span class="stat-value text-primary">${i.received_in_period} <small>${i.unit}</small></span>
                    </div>
                    <div class="inv-stat">
                        <span class="stat-label">المتبقي (الرصيد)</span>
                        <span class="stat-value ${i.current_balance <= 0 ? 'text-danger' : 'text-success'}">${i.current_balance} <small>${i.unit}</small></span>
                    </div>
                </div>
                <div class="inv-card-footer">
                    <i class="far fa-clock"></i> آخر توريد/استلام: <br> <strong>${lastDate}</strong>
                </div>
            </div>
            `;
        }).join('');
    } else {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:red">خطأ: ${res.message}</div>`;
    }
}

async function showItemDetails(itemId, itemName, unit) {
    const warehouse = document.getElementById('warehouse-select').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;
    
    document.getElementById('log-modal-title').textContent = itemName;
    document.getElementById('log-modal-subtitle').textContent = `من ${fromDate} إلى ${toDate}`;
    document.getElementById('log-modal-body').innerHTML = `<tr><td colspan="4" style="text-align:center">جاري التحميل...</td></tr>`;
    document.getElementById('item-log-modal').classList.remove('hidden');
    document.body.classList.add('modal-open');
    
    // Set up global adjust variables
    window.currentAdjustItemId = itemId;
    window.currentAdjustUnit = unit;
    window.currentAdjustItemName = itemName;
    
    const adjustSection = document.getElementById('adjust-stock-section');
    if (warehouse !== 'main') {
        adjustSection.classList.remove('hidden');
        document.getElementById('adjust-unit').textContent = unit;
        document.getElementById('new-balance-input').value = '';
        document.getElementById('adjust-notes-input').value = '';
    } else {
        adjustSection.classList.add('hidden');
    }
    
    // Set up export button
    document.getElementById('btn-export-log').onclick = () => {
        window.location.href = `../api/export_item_log.php?item_id=${itemId}&warehouse=${warehouse}&from_date=${fromDate}&to_date=${toDate}`;
    };

    const res = await apiCall(`/api/inventory_report.php?action=get_item_log&item_id=${itemId}&warehouse=${warehouse}&from_date=${fromDate}&to_date=${toDate}`);
    
    const tBody = document.getElementById('log-modal-body');
    if (res.success) {
        if (res.data.length === 0) {
            tBody.innerHTML = `<tr><td colspan="4" style="text-align:center">لا توجد حركات لهذا الصنف في هذه الفترة</td></tr>`;
            return;
        }
        
        tBody.innerHTML = res.data.map(log => {
            let badgeClass = log.type === 'addition' ? 'badge-success' : 'badge-danger';
            let qtyPrefix = log.type === 'addition' ? '+' : '-';
            let formattedDate = new Date(log.date).toLocaleString('en-US', {hour12:true});
            return `
            <tr>
                <td>${formattedDate}</td>
                <td><span class="badge ${badgeClass}">${log.label}</span></td>
                <td style="font-weight:bold" class="${log.type === 'addition' ? 'text-success' : 'text-danger'}">${qtyPrefix}${log.qty} <small style="font-weight:normal;color:#888">${unit}</small></td>
                <td><small>${log.details || ''} ${log.ref ? `(مرجع: ${log.ref})` : ''}</small></td>
            </tr>
            `;
        }).join('');
    } else {
        tBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:red">خطأ: ${res.message}</td></tr>`;
    }
}

async function submitStockAdjustment() {
    const warehouse = document.getElementById('warehouse-select').value;
    const itemId = window.currentAdjustItemId;
    const unit = window.currentAdjustUnit;
    const itemName = window.currentAdjustItemName;
    
    const newBalanceVal = document.getElementById('new-balance-input').value;
    const notesVal = document.getElementById('adjust-notes-input').value.trim();
    
    if (newBalanceVal === '') {
        showToast('يرجى إدخال الرصيد الفعلي الجديد', 'danger');
        return;
    }
    
    const newBalance = parseFloat(newBalanceVal);
    if (isNaN(newBalance) || newBalance < 0) {
        showToast('يرجى إدخال رصيد صحيح أكبر من أو يساوي الصفر', 'danger');
        return;
    }
    
    const confirmMsg = `هل أنت متأكد من تسوية رصيد الصنف (${itemName}) إلى ${newBalance} ${unit}؟`;
    if (!confirm(confirmMsg)) {
        return;
    }
    
    const btn = document.getElementById('btn-submit-adjust');
    btn.disabled = true;
    btn.textContent = 'جاري الحفظ...';
    
    const res = await apiCall('/api/inventory.php?action=adjust_sub_stock', 'POST', {
        warehouse: warehouse,
        item_id: itemId,
        new_balance: newBalance,
        notes: notesVal
    });
    
    btn.disabled = false;
    btn.textContent = 'حفظ التسوية';
    
    if (res.success) {
        showToast(res.message || 'تم تحديث الرصيد بنجاح', 'success');
        // Clear fields
        document.getElementById('new-balance-input').value = '';
        document.getElementById('adjust-notes-input').value = '';
        // Reload details and report list
        showItemDetails(itemId, itemName, unit);
        loadReport();
    } else {
        showToast('خطأ: ' + res.message, 'danger');
    }
}

function closeLogModal() {
    document.getElementById('item-log-modal').classList.add('hidden');
    document.body.classList.remove('modal-open');
}

function exportExcel() {
    const warehouse = document.getElementById('warehouse-select').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;
    window.location.href = `../api/export_warehouse_stock.php?warehouse=${warehouse}&from_date=${fromDate}&to_date=${toDate}`;
}
</script>

<style>
.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.inv-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    overflow: hidden;
    border: 1px solid var(--border);
    transition: transform 0.2s;
}
.inv-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.inv-card-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.inv-card-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-dark);
}
.inv-card-body {
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.inv-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fbfbfc;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px dashed #e2e8f0;
}
.stat-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}
.stat-value {
    font-size: 1.2rem;
    font-weight: bold;
}
.stat-value small {
    font-size: 0.8rem;
    font-weight: normal;
    color: #888;
}
.inv-card-footer {
    padding: 12px 15px;
    background: #fdfdfd;
    border-top: 1px solid var(--border);
    font-size: 0.85rem;
    color: var(--text-muted);
    text-align: right;
}
.inv-card-footer strong {
    color: var(--text-dark);
}

@media print {
    .sidebar, .topbar, .btn, .tabs-container, .card.mb-16 { display: none !important; }
    .main-content { margin: 0; padding: 0; }
    body { background: #fff; }
    
    /* If modal is open, hide grid and only show modal */
    body.modal-open #report-grid { display: none !important; }
    body.modal-open .modal-backdrop { 
        position: static; 
        background: none; 
        display: block !important; 
    }
    body.modal-open .modal { 
        box-shadow: none; 
        width: 100%; 
        max-width: none; 
        padding: 0; 
        border: none;
    }
    body.modal-open .modal-header .modal-close { display: none; }
    body.modal-open .modal-footer { display: none; }
    
    /* Otherwise, normal grid print */
    body:not(.modal-open) .inventory-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    body:not(.modal-open) .inv-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
}
</style>

<!-- Modal Details -->
<div class="modal-backdrop hidden" id="item-log-modal">
    <div class="modal" style="max-width:800px; width:95%">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0" id="log-modal-title">تفاصيل الصنف</h3>
                <small id="log-modal-subtitle" style="color:var(--text-muted)"></small>
            </div>
            <button class="modal-close" onclick="closeLogModal()">✕</button>
        </div>
        <div class="modal-body" style="max-height:60vh; overflow-y:auto; padding:0;">
            <!-- Section for stock adjustment (only visible for sub-warehouses) -->
            <div id="adjust-stock-section" class="hidden" style="padding:15px; background:#fcf8e3; border-bottom:1px solid #faebcc;">
                <h5 style="margin-top:0; color:#c0392b; display:flex; align-items:center; gap:8px; font-size:1rem; margin-bottom:10px;">
                    <i class="fas fa-sliders-h"></i> تسوية وجرد المخزون يدوياً (المخزن الفرعي)
                </h5>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:150px;">
                        <label class="form-label mb-5" style="display:block; font-size:0.85rem; font-weight:600; color:#555;">الرصيد الفعلي الجديد (<span id="adjust-unit"></span>)</label>
                        <input type="number" step="0.01" min="0" id="new-balance-input" class="form-control" placeholder="أدخل الرصيد المتبقي الفعلي" style="height:38px; border-radius:6px;">
                    </div>
                    <div style="flex:2; min-width:200px;">
                        <label class="form-label mb-5" style="display:block; font-size:0.85rem; font-weight:600; color:#555;">ملاحظات / سبب التسوية</label>
                        <input type="text" id="adjust-notes-input" class="form-control" placeholder="مثال: جرد نهاية اليوم، تالف، استهلاك إضافي..." style="height:38px; border-radius:6px;">
                    </div>
                    <div>
                        <button class="btn" id="btn-submit-adjust" onclick="submitStockAdjustment()" style="background:#f39c12; color:#fff; border:none; padding:8px 20px; border-radius:10px; cursor:pointer; font-weight:600; height:38px; transition:all 0.2s;">حفظ التسوية</button>
                    </div>
                </div>
            </div>

            <table class="table" style="margin:0; width:100%">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>العملية</th>
                        <th>الكمية</th>
                        <th>التفاصيل/المرجع</th>
                    </tr>
                </thead>
                <tbody id="log-modal-body">
                    <!-- Logs injected here -->
                </tbody>
            </table>
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:10px;">
                <button id="btn-export-log" class="btn btn-sm" style="background:#27ae60; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600"><i class="fas fa-file-excel"></i> تصدير Excel</button>
                <button class="btn btn-sm" onclick="window.print()" style="background:#e74c3c; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600"><i class="fas fa-file-pdf"></i> تصدير PDF</button>
            </div>
            <button class="btn btn-secondary" onclick="closeLogModal()">إغلاق</button>
        </div>
    </div>
</div>

<?php adminFooter(); ?>
