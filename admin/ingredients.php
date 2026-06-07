<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة المكونات', 'ingredients');
?>

<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="search-box">
        <span class="search-icon"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="search-input" placeholder="بحث عن مكون...">
      </div>
      <select class="form-control" id="filter-unit" style="width:auto">
        <option value="">كل الوحدات</option>
        <option value="gram">جرام</option>
        <option value="kg">كيلو</option>
        <option value="piece">حبة</option>
        <option value="liter">لتر</option>
        <option value="ml">مل</option>
        <option value="cup">كوب</option>
        <option value="tablespoon">ملعقة</option>
        <option value="other">أخرى</option>
      </select>
      <a href="bulk_import_ingredients.php" class="btn btn-success">
        <i class="fas fa-bolt"></i> إضافة سريعة بالجملة
      </a>
      <button class="btn btn-primary" onclick="openModal()">
        <i class="fas fa-plus"></i> إضافة مكون
      </button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <h3><i class="fas fa-boxes"></i> قائمة المكونات</h3>
    <span id="count-badge" class="badge badge-info"></span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>رقم المكون</th>
            <th>اسم المكون</th>
            <th>الوحدة</th>
            <th>ملاحظات</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody id="ingredients-tbody">
          <tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
        </tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-backdrop hidden" id="ing-modal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 id="modal-title">إضافة مكون</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ing-id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">رقم المكون</label>
          <input type="text" class="form-control" id="ing-number" placeholder="مثال: ING001">
        </div>
        <div class="form-group">
          <label class="form-label">الوحدة *</label>
          <select class="form-control" id="ing-unit">
            <option value="gram">جرام</option>
            <option value="kg">كيلوغرام</option>
            <option value="piece">حبة / قطعة</option>
            <option value="liter">لتر</option>
            <option value="ml">مل</option>
            <option value="cup">كوب</option>
            <option value="tablespoon">ملعقة</option>
            <option value="other">أخرى</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">اسم المكون *</label>
        <input type="text" class="form-control" id="ing-name" placeholder="مثال: دجاج مشوي، جبن موزاريلا، طحين...">
      </div>
      <div class="form-group">
        <label class="form-label">ملاحظات</label>
        <textarea class="form-control" id="ing-notes" rows="2" placeholder="أي ملاحظات إضافية..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveIngredient()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>



<script>
let allIngredients = [];
let currentPage = 1;
const currentLimit = 20;

const UNIT_LABELS = {
  gram:'جرام', kg:'كيلو', piece:'حبة/قطعة', liter:'لتر',
  ml:'مل', cup:'كوب', tablespoon:'ملعقة', other:'أخرى'
};

async function loadIngredients() {
  const res = await apiCall('/api/ingredients.php');
  if (!res.success) return;
  allIngredients = res.data;
  render();
}

function render() {
  const q    = document.getElementById('search-input').value.toLowerCase();
  const unit = document.getElementById('filter-unit').value;
  let list   = allIngredients;
  if (q)    list = list.filter(i => i.name.toLowerCase().includes(q) || (i.ingredient_number||'').toLowerCase().includes(q));
  if (unit) list = list.filter(i => i.unit === unit);

  document.getElementById('count-badge').textContent = list.length + ' مكون';
  const paginated = paginateData(list, currentPage, currentLimit);
  const tbody = document.getElementById('ingredients-tbody');

  if (!paginated.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><span class="icon"><i class="fas fa-box-open"></i></span><p>لا توجد مكونات</p></div></td></tr>';
    document.getElementById('pagination-container').innerHTML = '';
    return;
  }

  tbody.innerHTML = paginated.map((ing, idx) => `
    <tr>
      <td>${(currentPage-1)*currentLimit + idx + 1}</td>
      <td><span class="badge badge-secondary">${ing.ingredient_number || '-'}</span></td>
      <td><strong>${ing.name}</strong></td>
      <td><span class="badge badge-info">${UNIT_LABELS[ing.unit] || ing.unit}</span></td>
      <td style="color:var(--text-muted);font-size:.85rem">${ing.notes || '-'}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editIng(${ing.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteIng(${ing.id},'${ing.name}')"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');

  renderPagination(list.length, currentLimit, currentPage, 'pagination-container', 'setPage');
}

function setPage(p) { currentPage = p; render(); }

function openModal(isEdit=false) {
  document.getElementById('modal-title').textContent = isEdit ? 'تعديل مكون' : 'إضافة مكون';
  document.getElementById('ing-modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('ing-modal').classList.add('hidden');
  document.getElementById('ing-id').value = '';
  document.getElementById('ing-name').value = '';
  document.getElementById('ing-number').value = '';
  document.getElementById('ing-unit').value = 'gram';
  document.getElementById('ing-notes').value = '';
}

function editIng(id) {
  const ing = allIngredients.find(i => i.id == id);
  if (!ing) return;
  document.getElementById('ing-id').value      = ing.id;
  document.getElementById('ing-name').value    = ing.name;
  document.getElementById('ing-number').value  = ing.ingredient_number || '';
  document.getElementById('ing-unit').value    = ing.unit;
  document.getElementById('ing-notes').value   = ing.notes || '';
  openModal(true);
}

async function saveIngredient() {
  const id   = document.getElementById('ing-id').value;
  const name = document.getElementById('ing-name').value.trim();
  const unit = document.getElementById('ing-unit').value;
  const num  = document.getElementById('ing-number').value.trim();
  const notes = document.getElementById('ing-notes').value.trim();

  if (!name) { showToast('اسم المكون مطلوب', 'danger'); return; }

  const url  = id ? '/api/ingredients.php?action=update' : '/api/ingredients.php';
  const payload = { id, name, unit, ingredient_number: num, notes };
  const res  = await apiCall(url, 'POST', payload);
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) { closeModal(); loadIngredients(); }
}



async function deleteIng(id, name) {
  if (!confirmAction(`هل تريد حذف المكون "${name}"؟`)) return;
  const res = await apiCall('/api/ingredients.php?action=delete', 'POST', { id });
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadIngredients();
}

document.addEventListener('DOMContentLoaded', loadIngredients);
document.getElementById('search-input').addEventListener('input', () => { currentPage=1; render(); });
document.getElementById('filter-unit').addEventListener('change', () => { currentPage=1; render(); });
document.getElementById('ing-modal').addEventListener('click', e => { if(e.target.id==='ing-modal') closeModal(); });
</script>

<?php adminFooter(); ?>
