<?php
require_once __DIR__ . '/_layout.php';

// Check stock_management permission
$hasStockPerm = ($user['role'] === 'admin') ||
    (is_array($userPermsRaw) && in_array('stock_management', $userPermsRaw));

if (!$hasStockPerm) {
    header('Location: ' . BASE_PATH . 'admin/');
    exit;
}

adminHeader('رصيد الأصناف', 'item_stock');
?>
<style>
  .stock-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: .82rem;
    font-weight: 700;
  }
  .stock-high   { background: #d1fae5; color: #065f46; }
  .stock-medium { background: #fef3c7; color: #92400e; }
  .stock-low    { background: #fee2e2; color: #991b1b; }
  .stock-zero   { background: #f1f5f9; color: #64748b; }
  .tab-btn { padding: 8px 20px; border: none; background: var(--bg); border-radius: 8px; cursor: pointer; font-size: .9rem; }
  .tab-btn.active { background: var(--primary); color: #fff; }
</style>

<!-- Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px">
  <button class="tab-btn active" id="tab-stock" onclick="switchTab('stock')">
    <i class="fas fa-boxes"></i> إدارة الأرصدة
  </button>
  <button class="tab-btn" id="tab-log" onclick="switchTab('log')">
    <i class="fas fa-history"></i> سجل التعديلات
  </button>
</div>

<!-- STOCK TAB -->
<div id="panel-stock">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-boxes"></i> رصيد الأصناف</h3>
      <div style="display:flex;gap:10px;align-items:center">
        <input type="text" class="form-control" id="stock-search" placeholder="بحث عن صنف..." style="width:220px" oninput="filterStockTable()">
        <select class="form-control" id="stock-cat-filter" style="width:auto" onchange="filterStockTable()">
          <option value="">كل الفئات</option>
        </select>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrapper">
        <table id="stock-table">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم الصنف</th>
              <th>الفئة</th>
              <th>السعر</th>
              <th>الرصيد الحالي</th>
              <th>إجراء</th>
            </tr>
          </thead>
          <tbody id="stock-tbody">
            <tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- LOG TAB -->
<div id="panel-log" style="display:none">
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <h3><i class="fas fa-history"></i> سجل تعديلات الرصيد</h3>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="date" id="log-from" class="form-control" value="<?= date('Y-m-d') ?>" style="width:140px">
        <span style="color:var(--text-muted)">إلى</span>
        <input type="date" id="log-to" class="form-control" value="<?= date('Y-m-d') ?>" style="width:140px">
        <button class="btn btn-sm btn-secondary" onclick="loadLog()"><i class="fas fa-sync"></i> عرض</button>
        <button class="btn btn-sm btn-success" onclick="exportStockLog('excel')"><i class="fas fa-file-excel"></i> إكسل</button>
        <button class="btn btn-sm btn-danger" onclick="exportStockLog('pdf')" style="background:#e3342f;border-color:#e3342f"><i class="fas fa-file-pdf"></i> PDF</button>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>الصنف</th>
              <th>نوع العملية</th>
              <th>قبل</th>
              <th>التغيير</th>
              <th>بعد</th>
              <th>الملاحظة</th>
              <th>بواسطة</th>
              <th>الوقت</th>
            </tr>
          </thead>
          <tbody id="log-tbody">
            <tr><td colspan="9" style="text-align:center;padding:30px">اضغط "تحديث" لعرض السجل</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Edit Stock Modal -->
<div class="modal-backdrop hidden" id="stock-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 id="stock-modal-title"><i class="fas fa-edit"></i> تعديل رصيد الصنف</h3>
      <button class="modal-close" onclick="closeStockModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="s-item-id">
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px">
        <div style="font-weight:700;font-size:1rem" id="s-item-name"></div>
        <div style="color:var(--text-muted);font-size:.85rem">الرصيد الحالي: <strong id="s-current-qty" style="color:var(--primary)"></strong></div>
      </div>
      <div class="form-group">
        <label class="form-label">نوع العملية</label>
        <select id="s-action-type" class="form-control" onchange="updateStockModalLabel()">
          <option value="set">تعيين رصيد محدد</option>
          <option value="add">إضافة كمية للرصيد</option>
          <option value="deduct">خصم كمية من الرصيد</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" id="s-qty-label">الرصيد الجديد</label>
        <input type="number" id="s-qty" class="form-control" min="0" step="0.5" placeholder="0">
      </div>
      <div class="form-group">
        <label class="form-label">ملاحظة (اختياري)</label>
        <input type="text" id="s-note" class="form-control" placeholder="مثال: استلام شحنة جديدة">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeStockModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="submitStockUpdate()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script>
let allStockData = [];

// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(tab) {
  document.getElementById('panel-stock').style.display = tab === 'stock' ? '' : 'none';
  document.getElementById('panel-log').style.display = tab === 'log' ? '' : 'none';
  document.getElementById('tab-stock').classList.toggle('active', tab === 'stock');
  document.getElementById('tab-log').classList.toggle('active', tab === 'log');
  if (tab === 'log') loadLog();
}

// ── Load stock list ────────────────────────────────────────────────────────
async function loadStock() {
  const res = await apiCall('/api/item_stock.php?action=list');
  if (!res.success) { showToast(res.message, 'danger'); return; }
  allStockData = res.data;

  // Populate category filter
  const cats = [...new Set(allStockData.map(r => r.category_name))].sort();
  const catSel = document.getElementById('stock-cat-filter');
  catSel.innerHTML = '<option value="">كل الفئات</option>' + cats.map(c => `<option value="${c}">${c}</option>`).join('');

  renderStockTable(allStockData);
}

function renderStockTable(data) {
  const tbody = document.getElementById('stock-tbody');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><p>لا توجد أصناف</p></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.map((r, i) => {
    const qty = parseFloat(r.stock_qty);
    let badgeClass = qty === 0 ? 'stock-zero' : qty <= 3 ? 'stock-low' : qty <= 10 ? 'stock-medium' : 'stock-high';
    return `
    <tr>
      <td class="num">${i + 1}</td>
      <td><strong>${r.name_ar}</strong><br><small style="color:var(--text-muted)">${r.name_en || ''}</small></td>
      <td>${r.category_name}</td>
      <td class="money">${parseFloat(r.price).toFixed(2)}</td>
      <td style="text-align:center">
        <span class="stock-badge ${badgeClass}">${qty % 1 === 0 ? qty : qty.toFixed(1)}</span>
      </td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openStockModal(${r.id}, '${r.name_ar.replace(/'/g,"\\'")}', ${qty})">
          <i class="fas fa-edit"></i> تعديل
        </button>
      </td>
    </tr>`;
  }).join('');
}

function filterStockTable() {
  const q   = document.getElementById('stock-search').value.toLowerCase();
  const cat = document.getElementById('stock-cat-filter').value;
  const filtered = allStockData.filter(r =>
    (!q || r.name_ar.toLowerCase().includes(q) || (r.name_en || '').toLowerCase().includes(q)) &&
    (!cat || r.category_name === cat)
  );
  renderStockTable(filtered);
}

// ── Modal ──────────────────────────────────────────────────────────────────
function openStockModal(itemId, nameAr, currentQty) {
  document.getElementById('s-item-id').value      = itemId;
  document.getElementById('s-item-name').textContent = nameAr;
  document.getElementById('s-current-qty').textContent = currentQty % 1 === 0 ? currentQty : currentQty.toFixed(1);
  document.getElementById('s-action-type').value  = 'set';
  document.getElementById('s-qty').value           = currentQty;
  document.getElementById('s-note').value          = '';
  updateStockModalLabel();
  document.getElementById('stock-modal').classList.remove('hidden');
}

function closeStockModal() {
  document.getElementById('stock-modal').classList.add('hidden');
}

function updateStockModalLabel() {
  const type = document.getElementById('s-action-type').value;
  const labels = { set: 'الرصيد الجديد', add: 'الكمية المضافة', deduct: 'الكمية المخصومة' };
  document.getElementById('s-qty-label').textContent = labels[type] || 'الكمية';
}

async function submitStockUpdate() {
  const itemId     = document.getElementById('s-item-id').value;
  const qty        = parseFloat(document.getElementById('s-qty').value);
  const actionType = document.getElementById('s-action-type').value;
  const note       = document.getElementById('s-note').value.trim();

  if (!itemId || isNaN(qty) || qty < 0) {
    showToast('يرجى إدخال كمية صحيحة', 'warning');
    return;
  }

  const res = await apiCall('/api/item_stock.php?action=set', 'POST', {
    item_id: parseInt(itemId), qty, action_type: actionType, note
  });

  if (res.success) {
    showToast(`✅ ${res.message}`, 'success');
    closeStockModal();
    loadStock();
  } else {
    showToast(res.message, 'danger');
  }
}

// ── Log ──────────────────────────────────────────────────────────────────
async function loadLog() {
  const tbody = document.getElementById('log-tbody');
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px"><div class="spinner"></div></td></tr>';

  const from = document.getElementById('log-from').value;
  const to = document.getElementById('log-to').value;

  const res = await apiCall(`/api/item_stock.php?action=log&from=${from}&to=${to}`);
  if (!res.success) { showToast(res.message, 'danger'); return; }

  const typeLabels = { set: 'تعيين', add: 'إضافة', deduct: 'خصم', order_deduct: 'خصم (طلب)' };
  const typeColors = { set: '#2563eb', add: '#059669', deduct: '#dc2626', order_deduct: '#7c3aed' };

  if (!res.data.length) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px">لا توجد سجلات في هذه الفترة</td></tr>';
    return;
  }

  tbody.innerHTML = res.data.map((r, i) => {
    const change = parseFloat(r.qty_change);
    const changeStr = change >= 0 ? `+${change}` : change;
    const changeColor = change >= 0 ? '#059669' : '#dc2626';
    return `
    <tr>
      <td class="num">${i + 1}</td>
      <td><strong>${r.item_name_ar}</strong></td>
      <td><span style="color:${typeColors[r.action_type] || '#666'};font-weight:700">${typeLabels[r.action_type] || r.action_type}</span></td>
      <td class="num">${parseFloat(r.qty_before).toFixed(1)}</td>
      <td class="num" style="color:${changeColor};font-weight:700">${changeStr}</td>
      <td class="num"><strong>${parseFloat(r.qty_after).toFixed(1)}</strong></td>
      <td style="font-size:.85rem;color:var(--text-muted)">${r.note || '-'}</td>
      <td>${r.user_name || '-'}</td>
      <td class="num" style="font-size:.82rem">${new Date(r.created_at).toLocaleString('ar-SA')}</td>
    </tr>`;
  }).join('');
}

function exportStockLog(format) {
  const from = document.getElementById('log-from').value;
  const to = document.getElementById('log-to').value;
  if (!from || !to) {
    showToast('الرجاء تحديد نطاق التاريخ', 'warning');
    return;
  }
  const url = `<?= BASE_PATH ?>api/export_stock_log.php?from=${from}&to=${to}&format=${format}`;
  window.open(url, '_blank');
}

document.addEventListener('DOMContentLoaded', loadStock);
document.getElementById('stock-modal').addEventListener('click', e => { if (e.target.id === 'stock-modal') closeStockModal(); });
</script>

<?php adminFooter(); ?>
