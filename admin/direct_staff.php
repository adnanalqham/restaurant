<?php
require_once __DIR__ . '/_layout.php';
adminHeader('المباشرون / الويترز', 'direct_staff');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="margin:0"><i class="fas fa-user-tag"></i> إدارة المباشرين / الويترز</h2>
    <p style="margin:4px 0 0;color:var(--text-muted);font-size:.9rem">
      الأسماء هنا تظهر للكاشير عند تأكيد الطلبات، وتُطبع في الفاتورة
    </p>
  </div>
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="fas fa-plus"></i> إضافة مباشر جديد
  </button>
</div>

<!-- Stats -->
<div id="stats-row" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap"></div>

<!-- Table -->
<div class="card">
  <div class="card-body" style="padding:0">
    <table class="table" id="staff-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>الاسم</th>
          <th style="width:80px;text-align:center">الترتيب</th>
          <th style="width:100px;text-align:center">الحالة</th>
          <th style="width:120px;text-align:center">إجراءات</th>
        </tr>
      </thead>
      <tbody id="staff-tbody">
        <tr><td colspan="5" style="text-align:center;padding:40px">
          <div class="spinner"></div>
        </td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-backdrop hidden" id="staff-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 id="modal-title">إضافة مباشر</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-id">
      <div class="form-group">
        <label class="form-label">الاسم <span style="color:var(--danger)">*</span></label>
        <input type="text" id="staff-name" class="form-control" placeholder="مثال: أحمد محمد" autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">ترتيب الظهور <small style="color:var(--text-muted)">(الأصغر يظهر أولاً)</small></label>
        <input type="number" id="staff-sort" class="form-control" value="0" min="0">
      </div>
    </div>
    <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border)">
      <button class="btn btn-outline" onclick="closeModal()">إلغاء</button>
      <button class="btn btn-primary" id="save-btn" onclick="saveStaff()">
        <i class="fas fa-save"></i> حفظ
      </button>
    </div>
  </div>
</div>

<script>
let allStaff = [];

async function loadStaff() {
  const res = await apiCall('/api/direct_staff.php?action=list&active=0');
  if (!res.success) { 
    showToast(res.message, 'danger'); 
    const tbody = document.getElementById('staff-tbody');
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--danger)">
      <i class="fas fa-exclamation-triangle" style="font-size:2rem;margin-bottom:10px;display:block"></i>
      ${esc(res.message || 'فشل تحميل البيانات')}
    </td></tr>`;
    return; 
  }
  allStaff = res.data || [];
  renderTable();
  renderStats();
}

function renderStats() {
  const active   = allStaff.filter(s => s.is_active == 1).length;
  const inactive = allStaff.length - active;
  document.getElementById('stats-row').innerHTML = `
    <div class="card" style="padding:14px 20px;display:flex;gap:10px;align-items:center;min-width:140px">
      <span style="font-size:1.8rem;color:var(--primary)">👤</span>
      <div><div style="font-size:1.5rem;font-weight:800">${allStaff.length}</div><div style="color:var(--text-muted);font-size:.85rem">الإجمالي</div></div>
    </div>
    <div class="card" style="padding:14px 20px;display:flex;gap:10px;align-items:center;min-width:140px">
      <span style="font-size:1.8rem;color:var(--success)">✅</span>
      <div><div style="font-size:1.5rem;font-weight:800">${active}</div><div style="color:var(--text-muted);font-size:.85rem">نشط</div></div>
    </div>
    <div class="card" style="padding:14px 20px;display:flex;gap:10px;align-items:center;min-width:140px">
      <span style="font-size:1.8rem;color:var(--text-muted)">⏸️</span>
      <div><div style="font-size:1.5rem;font-weight:800">${inactive}</div><div style="color:var(--text-muted);font-size:.85rem">موقوف</div></div>
    </div>
  `;
}

function renderTable() {
  const tbody = document.getElementById('staff-tbody');
  if (!allStaff.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:50px;color:var(--text-muted)">
      <i class="fas fa-user-slash" style="font-size:2rem;margin-bottom:10px;display:block"></i>
      لا توجد أسماء مضافة بعد — ابدأ بإضافة أول مباشر
    </td></tr>`;
    return;
  }

  tbody.innerHTML = allStaff.map((s, i) => `
    <tr style="${s.is_active != 1 ? 'opacity:.5' : ''}">
      <td style="color:var(--text-muted)">${i + 1}</td>
      <td>
        <strong>${esc(s.name)}</strong>
        ${s.is_active != 1 ? '<span style="font-size:.75rem;background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:20px;margin-right:8px">موقوف</span>' : ''}
      </td>
      <td style="text-align:center;color:var(--text-muted)">${s.sort_order}</td>
      <td style="text-align:center">
        <span style="padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:600;
          background:${s.is_active == 1 ? 'var(--success-light, #d4edda)' : '#f8d7da'};
          color:${s.is_active == 1 ? 'var(--success, #155724)' : '#721c24'}">
          ${s.is_active == 1 ? '✓ نشط' : '✗ موقوف'}
        </span>
      </td>
      <td style="text-align:center">
        <div style="display:flex;gap:6px;justify-content:center">
          <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(${s.id})" title="تعديل">
            <i class="fas fa-edit"></i>
          </button>
          <button class="btn btn-sm" onclick="toggleStaff(${s.id})"
            style="background:${s.is_active == 1 ? '#ffc107' : '#28a745'};color:#fff;border:none;border-radius:8px;padding:4px 10px"
            title="${s.is_active == 1 ? 'إيقاف' : 'تفعيل'}">
            <i class="fas fa-${s.is_active == 1 ? 'pause' : 'play'}"></i>
          </button>
          <button class="btn btn-sm btn-danger" onclick="deleteStaff(${s.id}, '${esc(s.name)}')" title="حذف">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function openAddModal() {
  document.getElementById('modal-title').textContent = 'إضافة مباشر جديد';
  document.getElementById('edit-id').value = '';
  document.getElementById('staff-name').value = '';
  document.getElementById('staff-sort').value = '0';
  document.getElementById('staff-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('staff-name').focus(), 100);
}

function openEditModal(id) {
  const s = allStaff.find(x => x.id == id);
  if (!s) return;
  document.getElementById('modal-title').textContent = 'تعديل: ' + s.name;
  document.getElementById('edit-id').value = s.id;
  document.getElementById('staff-name').value = s.name;
  document.getElementById('staff-sort').value = s.sort_order;
  document.getElementById('staff-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('staff-name').focus(), 100);
}

function closeModal() {
  document.getElementById('staff-modal').classList.add('hidden');
}

async function saveStaff() {
  const id   = document.getElementById('edit-id').value;
  const name = document.getElementById('staff-name').value.trim();
  const sort = parseInt(document.getElementById('staff-sort').value) || 0;

  if (!name) { showToast('الرجاء إدخال الاسم', 'warning'); return; }

  const btn = document.getElementById('save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

  const action = id ? 'edit' : 'add';
  const payload = { name, sort_order: sort };
  if (id) payload.id = id;

  const res = await apiCall(`/api/direct_staff.php?action=${action}`, 'POST', payload);
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> حفظ';

  if (res.success) {
    showToast(res.message, 'success');
    closeModal();
    loadStaff();
  } else {
    showToast(res.message, 'danger');
  }
}

async function toggleStaff(id) {
  const res = await apiCall('/api/direct_staff.php?action=toggle', 'POST', { id });
  if (res.success) { loadStaff(); showToast('تم تحديث الحالة', 'success'); }
  else showToast(res.message, 'danger');
}

async function deleteStaff(id, name) {
  if (!confirm(`هل تريد حذف "${name}"؟`)) return;
  const res = await apiCall('/api/direct_staff.php?action=delete', 'POST', { id });
  if (res.success) { loadStaff(); showToast('تم الحذف', 'success'); }
  else showToast(res.message, 'danger');
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modal on backdrop click
document.getElementById('staff-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Enter key saves
document.getElementById('staff-name').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') saveStaff();
});

(function waitForApp() {
  if (typeof apiCall === 'function') {
    loadStaff();
  } else {
    setTimeout(waitForApp, 50);
  }
})();
</script>

<?php adminFooter(); ?>
