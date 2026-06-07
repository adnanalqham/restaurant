<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة الطابعات', 'settings');
?>

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
    <h3><i class="fas fa-print"></i> أجهزة الطباعة (المطبخ، البار، إلخ)</h3>
    <div style="display:flex; gap:10px">
      <button class="btn btn-sm" style="background:#0a0a2e; color:#4fc3f7; border:1px solid #4fc3f7" onclick="pushToLocalServer()">
        <i class="fas fa-sync"></i> مزامنة مع السيرفر المحلي
      </button>
      <button class="btn btn-primary btn-sm" onclick="openPrinterModal()"><i class="fas fa-plus"></i> إضافة طابعة</button>
    </div>
  </div>
  <div class="alert alert-info" style="margin:10px 15px; font-size:0.85rem">
    <i class="fas fa-info-circle"></i> يتم تحديث السيرفر المحلي (localhost:3000) تلقائياً عند الحفظ. تأكد من تشغيل السيرفر على اللابتوب.
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>اسم العرض</th>
            <th>اسم الطابعة (Windows)</th>
            <th>عنوان IP</th>
            <th>النوع</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody id="printers-tbody">
          <tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Printer Modal -->
<div class="modal-backdrop hidden" id="printer-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h3 id="printer-modal-title">إضافة طابعة</h3>
      <button class="modal-close" onclick="closePrinterModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="printer-form">
        <input type="hidden" id="printer-id">
        <div class="form-group">
          <label class="form-label">اسم العرض (وصفي)</label>
          <input type="text" class="form-control" id="printer-name" placeholder="طابعة الكاشير" required>
        </div>
        <div class="form-group">
          <label class="form-label">🖨️ اسم الطابعة في Windows <span style="color:var(--primary)">*</span></label>
          <input type="text" class="form-control" id="printer-windows-name"
            placeholder="مثال: chashier  أو  MNK on 10.0.0.191"
            style="font-family:monospace;direction:ltr;text-align:left">
          <small style="opacity:.6;font-size:.78rem">الاسم كما يظهر في قائمة الطابعات بجهاز Windows</small>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">عنوان IP (اختياري)</label>
            <input type="text" class="form-control" id="printer-ip" placeholder="10.0.0.191">
          </div>
          <div class="form-group">
            <label class="form-label">المنفذ</label>
            <input type="number" class="form-control" id="printer-port" value="9100">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">النوع (الاستخدام)</label>
          <select class="form-control" id="printer-type-internal">
            <option value="cashier">🧾 طابعة الكاشير</option>
            <option value="kitchen">🍳 طابعة المطبخ / الشيف</option>
            <option value="bar">🥤 طابعة البار</option>
            <option value="grill">🔥 طابعة المشويات</option>
            <option value="shisha">💨 طابعة الشيشة</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closePrinterModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="savePrinter()"><i class="fas fa-save"></i> حفظ ومزامنة</button>
    </div>
  </div>
</div>

<script>
let printers = [];
const LOCAL_SERVER_URL = 'http://localhost:3000';

async function loadPrinters() {
  const res = await apiCall('/api/printers.php');
  if (!res.success) return;
  printers = res.data;
  renderPrintersTable();
}

function renderPrintersTable() {
  const tbody = document.getElementById('printers-tbody');
  if (!printers.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px">لا توجد طابعات مضافة بعد</td></tr>';
    return;
  }

  tbody.innerHTML = printers.map(p => `
    <tr>
      <td><strong>${p.name}</strong></td>
      <td><code style="font-size:.8rem;background:rgba(255,255,255,.08);padding:2px 6px;border-radius:4px">${p.windows_name || '<span style="opacity:.4">—</span>'}</code></td>
      <td><code>${p.ip || '—'}</code></td>
      <td><span class="badge badge-info">${p.type}</span></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editPrinter(${p.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deletePrinter(${p.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
}

function openPrinterModal(isEdit = false) {
  document.getElementById('printer-modal-title').textContent = isEdit ? 'تعديل طابعة' : 'إضافة طابعة جديد';
  document.getElementById('printer-modal').classList.remove('hidden');
}

function closePrinterModal() {
  document.getElementById('printer-modal').classList.add('hidden');
  document.getElementById('printer-id').value = '';
  document.getElementById('printer-form').reset();
}

function editPrinter(id) {
  const p = printers.find(x => x.id == id);
  if (!p) return;
  document.getElementById('printer-id').value = p.id;
  document.getElementById('printer-name').value = p.name;
  document.getElementById('printer-windows-name').value = p.windows_name || '';
  document.getElementById('printer-ip').value = p.ip || '';
  document.getElementById('printer-port').value = p.port;
  document.getElementById('printer-type-internal').value = p.type;
  openPrinterModal(true);
}

// ─── Local Server Sync Helper ─────────────────────────────────────────────
async function syncToLocal(action, data) {
  try {
    const method = action === 'delete' ? 'DELETE' : (action === 'update' ? 'PUT' : 'POST');
    const url = method === 'POST' ? `${LOCAL_SERVER_URL}/printers` : `${LOCAL_SERVER_URL}/printers/${data.id}`;
    
    // Prepare local payload
    const payload = {
      id: data.id ? 'p_' + data.id : null,
      name: data.name,
      type: data.type,
      ip: data.ip,
      port: data.port,
      connection: 'network',
      status: 'active'
    };

    const res = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: method !== 'DELETE' ? JSON.stringify(payload) : null
    });
    return res.ok;
  } catch (e) {
    console.warn("Local sync failed (server probably offline):", e);
    return false;
  }
}

async function pushToLocalServer() {
  showToast('بدء المزامنة مع السيرفر المحلي...', 'info');
  let successCount = 0;
  for (const p of printers) {
    const ok = await syncToLocal('create', { ...p, id: 'p_' + p.id });
    if (ok) successCount++;
  }
  showToast(`تمت المزامنة بنجاح: ${successCount} من ${printers.length}`, successCount === printers.length ? 'success' : 'warning');
}

async function savePrinter() {
  const id = document.getElementById('printer-id').value;
  const data = {
    id: id || null,
    name:         document.getElementById('printer-name').value,
    windows_name: document.getElementById('printer-windows-name').value.trim(),
    ip:           document.getElementById('printer-ip').value.trim(),
    port:         document.getElementById('printer-port').value,
    type:         document.getElementById('printer-type-internal').value
  };

  const res = await apiCall('/api/printers.php', 'POST', data);
  if (res.success) {
    showToast(res.message, 'success');
    closePrinterModal();
    loadPrinters();
  } else {
    showToast(res.message, 'danger');
  }
}

async function deletePrinter(id) {
  if (!confirmAction('هل أنت متأكد من حذف هذه الطابعة؟')) return;
  const res = await apiCall('/api/printers.php?action=delete', 'POST', {id: id});
  if (res.success) {
    await syncToLocal('delete', { id: 'p_' + id });
    showToast(res.message + ' (تم الحذف محلياً)', 'success');
    loadPrinters();
  } else {
    showToast(res.message, 'danger');
  }
}

loadPrinters();
</script>

<?php adminFooter(); ?>
