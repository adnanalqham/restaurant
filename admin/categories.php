<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة الفئات', 'categories');
?>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> الفئات</h3>
    <div class="flex gap-12">
      <button class="btn btn-danger" id="bulk-delete-btn" style="display:none" onclick="executeBulkDelete('/api/categories.php', 'row-checkbox', 'loadCategories')"><i class="fas fa-trash"></i> حذف المحدّد (<span id="selected-count">0</span>)</button>
      <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-plus"></i> إضافة فئة</button>
    </div>
  </div>
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
        <thead><tr><th style="width:40px"><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"></th><th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب"># <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th><th>الأيقونة</th><th>الاسم العربي</th><th>الاسم الإنجليزي</th><th>الترتيب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody id="cat-tbody"><tr><td colspan="7" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr></tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<!-- Modal -->
<div class="modal-backdrop hidden" id="modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">إضافة فئة</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="cat-form">
        <input type="hidden" id="cat-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الاسم العربي *</label>
            <input type="text" class="form-control" id="cat-name-ar" placeholder="مثال: وجبات سريعة" required>
          </div>
          <div class="form-group">
            <label class="form-label">الاسم الإنجليزي *</label>
            <input type="text" class="form-control" id="cat-name-en" placeholder="Fast Food" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الأيقونة (Font Awesome class)</label>
            <input type="text" class="form-control" id="cat-icon" placeholder="fas fa-hamburger" value="fas fa-utensils">
          </div>
          <div class="form-group">
            <label class="form-label">الترتيب</label>
            <input type="number" class="form-control" id="cat-sort" placeholder="0" min="0" value="0">
          </div>
        </div>
        <div class="form-group" id="status-group" style="display:none">
          <label class="form-label">الحالة</label>
          <select class="form-control" id="cat-status">
            <option value="1">نشط</option>
            <option value="0">معطّل</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveCategory()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script>
let categories = [];
let currentPage = 1;
let currentLimit = 10;
let sortAsc = false;

function toggleSort() {
  sortAsc = !sortAsc;
  document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
  renderTable();
}

async function loadCategories(deletedIds = null) {
  if (deletedIds && Array.isArray(deletedIds) && deletedIds.length) {
    categories = categories.filter(c => !deletedIds.includes(c.id.toString()));
    renderTable();
    return;
  }
  const res = await apiCall('/api/categories.php?all=1');
  if (!res.success) return;
  categories = res.data;
  renderTable();
}

function renderTable() {
  const tbody = document.getElementById('cat-tbody');
  
  if (!categories.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><span class="icon"><i class="fas fa-folder-open"></i></span><p>لا توجد فئات</p></div></td></tr>';
    document.getElementById('pagination-container').innerHTML = '';
    return;
  }

  let items = [...categories];
  items.sort((a,b) => sortAsc ? a.id - b.id : b.id - a.id);

  const paginatedCategories = paginateData(items, currentPage, currentLimit);

  // Uncheck select-all when page changes
  const selectAllBtn = document.getElementById('select-all');
  if (selectAllBtn) selectAllBtn.checked = false;
  updateBulkDeleteBtn();

  tbody.innerHTML = paginatedCategories.map((c, i) => `
    <tr>
      <td><input type="checkbox" class="row-checkbox" value="${c.id}" onclick="updateBulkDeleteBtn()"></td>
      <td>${(currentPage - 1) * currentLimit + i + 1}</td>
      <td style="font-size:1.2rem">${c.icon && c.icon.includes('fa-') ? `<i class="${c.icon}"></i>` : (c.icon || '')}</td>
      <td><strong>${c.name_ar}</strong></td>
      <td>${c.name_en}</td>
      <td>${c.sort_order}</td>
      <td><span class="badge ${c.is_active=='1'?'badge-confirmed':'badge-cancelled'}">${c.is_active=='1'?'نشط':'معطّل'}</span></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editCategory(${c.id})"><i class="fas fa-edit"></i> تعديل</button>
        <button class="btn btn-danger btn-sm" onclick="deleteCategory(${c.id}, '${c.name_ar}')"><i class="fas fa-trash"></i> حذف</button>
      </td>
    </tr>
  `).join('');

  renderPagination(categories.length, currentLimit, currentPage, 'pagination-container', 'setPage');
}

function setPage(p) {
  currentPage = p;
  renderTable();
}

function openModal(isEdit = false) {
  document.getElementById('modal-title').textContent = isEdit ? 'تعديل فئة' : 'إضافة فئة';
  document.getElementById('status-group').style.display = isEdit ? 'block' : 'none';
  document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('modal').classList.add('hidden');
  document.getElementById('cat-id').value = '';
  document.getElementById('cat-form').reset();
  document.getElementById('cat-icon').value = 'fas fa-utensils';
  document.getElementById('cat-sort').value = '0';
}

function editCategory(id) {
  const cat = categories.find(c => c.id == id);
  if (!cat) return;
  document.getElementById('cat-id').value = cat.id;
  document.getElementById('cat-name-ar').value = cat.name_ar;
  document.getElementById('cat-name-en').value = cat.name_en;
  document.getElementById('cat-icon').value = cat.icon;
  document.getElementById('cat-sort').value = cat.sort_order;
  document.getElementById('cat-status').value = cat.is_active;
  openModal(true);
}

async function saveCategory() {
  const id = document.getElementById('cat-id').value;
  const fd = new FormData();
  if (id) { fd.append('action', 'update'); fd.append('id', id); }
  fd.append('name_ar', document.getElementById('cat-name-ar').value);
  fd.append('name_en', document.getElementById('cat-name-en').value);
  fd.append('icon', document.getElementById('cat-icon').value);
  fd.append('sort_order', document.getElementById('cat-sort').value);
  if (id) fd.append('is_active', document.getElementById('cat-status').value);

  const url = id ? '/api/categories.php?action=update' : '/api/categories.php';
  const res = await apiCall(url, 'POST', fd);
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) { closeModal(); loadCategories(); }
}

async function deleteCategory(id, name) {
  if (!confirmAction(`هل أنت متأكد من حذف الفئة '${name}'؟ سيتم حذف جميع أصنافها أيضاً.`)) return;
  const res = await apiCall('/api/categories.php?action=delete', 'POST', {id: id});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadCategories([id.toString()]);
}


(function waitForApp() {
  if (typeof apiCall === 'function') {
    loadCategories();
    if (typeof onSSE === 'function') onSSE('category_updated', loadCategories);
  } else { setTimeout(waitForApp, 50); }
})();
document.getElementById('modal').addEventListener('click', function(e) { if(e.target===document.getElementById('modal')) closeModal(); });

</script>

<?php adminFooter(); ?>
