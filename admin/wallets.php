<?php
ob_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة المحافظ الرقمية', 'wallets');
?>

<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0"><i class="fas fa-wallet"></i> المحافظ الرقمية</h3>
      <?php if ($user['role'] === 'admin'): ?>
      <div class="flex gap-12">
        <button class="btn btn-danger" id="bulk-delete-btn" style="display:none" onclick="executeBulkDelete('/api/wallets.php', 'row-checkbox', 'loadWallets')"><i class="fas fa-trash"></i> حذف المحدّد (<span id="selected-count">0</span>)</button>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> إضافة محفظة</button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 15px 0;">
      <div style="font-size:0.9rem">
        عرض 
        <select class="form-control" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem" onchange="currentLimit=parseInt(this.value); currentPage=1; renderTable();">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        أسطر
      </div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th style="width:40px"><?php if ($user['role'] === 'admin'): ?><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"><?php endif; ?></th><th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب"># <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th><th>اسم المحفظة</th><th>رقم الحساب / النقطة</th><th>الترتيب</th><th>الحالة</th><?php if ($user['role'] === 'admin'): ?><th>إجراءات</th><?php endif; ?></tr></thead>
        <tbody id="wallets-tbody"><tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr></tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<!-- Modal -->
<div class="modal-backdrop hidden" id="wallet-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 id="modal-title">إضافة محفظة</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="wallet-form">
        <input type="hidden" id="wallet-id">
        <div class="form-group">
          <label class="form-label">اسم المحفظة * <small style="color:var(--text-muted)">(مثال: STC Pay، زين كاش)</small></label>
          <input type="text" class="form-control" id="wallet-name" placeholder="STC Pay" required>
        </div>
        <div class="form-group">
          <label class="form-label">رقم الحساب / النقطة *</label>
          <input type="text" class="form-control" id="wallet-number" placeholder="05XXXXXXXX" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الترتيب</label>
            <input type="number" class="form-control" id="wallet-sort" value="0" min="0">
          </div>
          <div class="form-group" id="status-group" style="display:none">
            <label class="form-label">الحالة</label>
            <select class="form-control" id="wallet-status">
              <option value="1">نشط</option>
              <option value="0">معطّل</option>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveWallet()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script>
let wallets = [];
let currentPage = 1;
let currentLimit = 10;
let sortAsc = false;

function toggleSort() {
  sortAsc = !sortAsc;
  document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
  renderTable();
}

async function loadWallets(deletedIds = null) {
  if (deletedIds && Array.isArray(deletedIds) && deletedIds.length) {
    wallets = wallets.filter(w => !deletedIds.includes(w.id.toString()));
    renderTable();
    return;
  }
  const res = await apiCall('/api/wallets.php?all=1');
  if (!res.success) return;
  wallets = res.data;
  renderTable();
}

function renderTable() {
  const tbody = document.getElementById('wallets-tbody');
  const selectAllBtn = document.getElementById('select-all');
  const isAdmin = window.POS_USER && window.POS_USER.role === 'admin';
  
  if (!wallets.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><span class="icon"><i class="fas fa-wallet"></i></span><p>لا توجد محافظ مضافة</p></div></td></tr>';
    document.getElementById('pagination-container').innerHTML = '';
    return;
  }

  let items = [...wallets];
  items.sort((a,b) => sortAsc ? a.id - b.id : b.id - a.id);

  const paginatedWallets = paginateData(items, currentPage, currentLimit);

  // Uncheck select-all when page changes
  if (selectAllBtn) selectAllBtn.checked = false;
  updateBulkDeleteBtn();

  tbody.innerHTML = paginatedWallets.length ? paginatedWallets.map((w, i) => `
    <tr class="${w.is_active ? '' : 'inactive'}" style="${w.is_active ? '' : 'opacity:0.6;background:#f9f9f9'}">
      <td>${isAdmin ? `<input type="checkbox" class="row-checkbox" value="${w.id}" onchange="updateBulkDeleteBtn()">` : ''}</td>
      <td>${(currentPage - 1) * currentLimit + i + 1}</td>
      <td><strong><i class="fas fa-wallet" style="color:var(--primary)"></i> ${w.name}</strong></td>
      <td><code style="background:var(--bg);padding:4px 10px;border-radius:6px;font-size:.9rem">${w.account_number}</code></td>
      <td>${w.sort_order}</td>
      <td><span class="badge ${w.is_active=='1'?'badge-confirmed':'badge-cancelled'}">${w.is_active=='1'?'نشط':'معطّل'}</span></td>
      ${isAdmin ? `
      <td>
        <button class="btn btn-warning btn-sm" onclick="editWallet(${w.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteWallet(${w.id},'${w.name}')"><i class="fas fa-trash"></i></button>
      </td>` : ''}
    </tr>
  `).join('') : '<tr><td colspan="7"><div class="empty-state"><span class="icon"><i class="fas fa-folder-open"></i></span><p>لا توجد محافظ رقمية. قم بإضافة محفظة جديدة.</p></div></td></tr>';

  renderPagination(wallets.length, currentLimit, currentPage, 'pagination-container', 'setPage');
}

function setPage(p) {
  currentPage = p;
  renderTable();
}

function openModal(isEdit = false) {
  document.getElementById('modal-title').textContent = isEdit ? 'تعديل محفظة' : 'إضافة محفظة';
  document.getElementById('status-group').style.display = isEdit ? 'block' : 'none';
  document.getElementById('wallet-modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('wallet-modal').classList.add('hidden');
  document.getElementById('wallet-id').value = '';
  document.getElementById('wallet-form').reset();
  document.getElementById('wallet-sort').value = '0';
}

function editWallet(id) {
  const w = wallets.find(x => x.id == id);
  if (!w) return;
  document.getElementById('wallet-id').value = w.id;
  document.getElementById('wallet-name').value = w.name;
  document.getElementById('wallet-number').value = w.account_number;
  document.getElementById('wallet-sort').value = w.sort_order;
  document.getElementById('wallet-status').value = w.is_active;
  openModal(true);
}

async function saveWallet() {
  const id = document.getElementById('wallet-id').value;
  const fd = new FormData();
  if (id) { fd.append('id', id); }
  fd.append('name', document.getElementById('wallet-name').value);
  fd.append('account_number', document.getElementById('wallet-number').value);
  fd.append('sort_order', document.getElementById('wallet-sort').value);
  if (id) fd.append('is_active', document.getElementById('wallet-status').value);

  const url = id ? '/api/wallets.php?action=update' : '/api/wallets.php';
  const res = await apiCall(url, 'POST', fd);
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) { closeModal(); loadWallets(); }
}

async function deleteWallet(id, name) {
  if(!confirmAction(`هل أنت متأكد من حذف المحفظة '${name}'؟`)) return;
  const res = await apiCall('/api/wallets.php?action=delete', 'POST', {id: id});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadWallets([id.toString()]);
}

(function waitForApp() {
  if (typeof apiCall === 'function') {
    loadWallets();
  } else { setTimeout(waitForApp, 50); }
})();
document.getElementById('wallet-modal').addEventListener('click', function(e) { if(e.target.id==='wallet-modal') closeModal(); });
</script>

<?php adminFooter(); ?>
