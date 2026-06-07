<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة المستخدمين', 'users');
?><style>
.users-layout-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 992px) {
  .users-layout-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="users-layout-grid">

<!-- Users List -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-users-cog"></i> المستخدمون</h3>
    <div class="flex gap-12">
      <button class="btn btn-danger" id="bulk-delete-btn" style="display:none" onclick="executeBulkDelete('/api/users.php', 'row-checkbox', 'loadUsers')"><i class="fas fa-trash"></i> حذف المحدّد (<span id="selected-count">0</span>)</button>
      <button class="btn btn-primary btn-sm" onclick="openUserModal()"><i class="fas fa-user-plus"></i> إضافة مستخدم</button>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 15px 0;">
      <div style="font-size:0.9rem">
        عرض 
        <select class="form-control" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem" onchange="currentLimit=parseInt(this.value); currentPage=1; renderUsersTable();">
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
        <thead><tr><th style="width:40px"><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"></th><th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب"># <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th><th style="white-space:nowrap">الاسم</th><th style="white-space:nowrap">الاسم بالإنجليزي</th><th style="white-space:nowrap">اسم المستخدم</th><th style="white-space:nowrap">الدور</th><th style="width:80px; white-space:nowrap">الحالة</th><th style="width:60px; white-space:nowrap"><i class="fas fa-print"></i></th><th style="width:130px; white-space:nowrap">إجراءات</th></tr></thead>
        <tbody id="users-tbody"><tr><td colspan="9" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr></tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<!-- Permissions Panel -->
<div class="card" id="perms-panel" style="display:none">
  <div class="card-header">
    <h3><i class="fas fa-key"></i> صلاحيات الفئات</h3>
    <small id="perms-user-name" class="text-muted"></small>
  </div>
  <div class="card-body">
    <p class="text-muted" style="font-size:.85rem;margin-bottom:12px">اختر الفئات التي يراها هذا المستخدم (فارغ = يرى الكل)</p>
    <div id="perms-cats"></div>
    <button class="btn btn-success btn-block mt-12" onclick="savePermissions()"><i class="fas fa-save"></i> حفظ الصلاحيات</button>
  </div>
</div>

</div>

<!-- Add/Edit User Modal -->
<div class="modal-backdrop hidden" id="user-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="user-modal-title">إضافة مستخدم</h3>
      <button class="modal-close" onclick="closeUserModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="user-id">
      <div class="form-group">
        <label class="form-label">الاسم الكامل *</label>
        <input type="text" class="form-control" id="user-name" required>
      </div>
      <div class="form-group">
        <label class="form-label">الاسم بالإنجليزي (للفاتورة)</label>
        <input type="text" class="form-control" id="user-name-en">
      </div>
      <div class="form-group">
        <label class="form-label">اسم المستخدم *</label>
        <input type="text" class="form-control" id="user-username" required>
      </div>
      <div class="form-group" id="pass-group">
        <label class="form-label">كلمة المرور *</label>
        <input type="password" class="form-control" id="user-pass" minlength="6">
        <div class="form-hint">6 أحرف على الأقل</div>
      </div>
      <div class="form-group" id="newpass-group" style="display:none">
        <label class="form-label">كلمة مرور جديدة (اتركه فارغاً لعدم التغيير)</label>
        <input type="password" class="form-control" id="user-newpass" minlength="6">
      </div>
      <div class="form-group">
        <label class="form-label">الدور *</label>
        <select class="form-control" id="user-role"></select>
      </div>
      <div class="form-group">
        <label class="form-label">المخزن المرتبط بالمستخدم (اختياري)</label>
        <select class="form-control" id="user-warehouse">
          <option value="">-- بدون مخزن مخصص --</option>
        </select>
        <small class="text-muted">اربط هذا المستخدم بمخزن لكي تُسجل طلباته باسم هذا المخزن.</small>
      </div>
      <div class="form-group" id="printer-mac-group" style="display:none">
        <label class="form-label"><i class="fab fa-bluetooth-b" style="color:#2980b9"></i> عنوان طابعة البلوتوث (MAC Address)</label>
        <input type="text" class="form-control" id="user-printer-mac" placeholder="AA:BB:CC:DD:EE:FF">
        <small class="text-muted">عنوان MAC للطابعة البلوتوث المرتبطة بهذا المستخدم.</small>
      </div>
      <div class="form-group" id="status-group-user" style="display:none">
        <label class="form-label">الحالة</label>
        <select class="form-control" id="user-status">
          <option value="1">نشط</option>
          <option value="0">معطّل</option>
        </select>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="user-can-print" checked>
          <span>صلاحية الطباعة</span>
        </label>
      </div>

      <!-- Print Type: visible for cashier, chef, juice_bar -->
      <div class="form-group" id="print-type-group" style="display:none">
        <label class="form-label"><i class="fas fa-print"></i> نوع الطباعة عند تأكيد الدفع</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:10px;background:var(--bg-body);padding:14px;border-radius:8px;border:1px solid var(--border)">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;border-radius:8px;border:2px solid var(--border)" id="label-network">
            <input type="radio" name="print_type" value="network" id="pt-network" checked>
            <span>
              <i class="fas fa-wifi" style="color:#3498db"></i>
              <strong>شبكة (Network)</strong><br>
              <small style="color:var(--text-muted)">كمبيوتر ← طابعة IP</small>
            </span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;border-radius:8px;border:2px solid var(--border)" id="label-bluetooth">
            <input type="radio" name="print_type" value="bluetooth" id="pt-bluetooth">
            <span>
              <i class="fab fa-bluetooth-b" style="color:#2980b9"></i>
              <strong>بلوتوث — فاتورة كاملة</strong><br>
              <small style="color:var(--text-muted)">نسخة الكاشير (مع الأسعار)</small>
            </span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;border-radius:8px;border:2px solid var(--border)" id="label-chef">
            <input type="radio" name="print_type" value="chef" id="pt-chef">
            <span>
              <i class="fas fa-hat-chef" style="color:#e67e22"></i>
              <strong>بلوتوث — تذكرة شيف</strong><br>
              <small style="color:var(--text-muted)">نسخة واحدة — أصناف الشيف فقط بدون أسعار</small>
            </span>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;width:100%">
          <span>الصلاحيات المخصصة للنظام (اختياري)</span>
          <a href="#" onclick="event.preventDefault(); resetPermissionsToRoleDefault()" style="font-size:0.8rem;color:var(--primary);text-decoration:none;font-weight:normal"><i class="fas fa-undo-alt"></i> استعادة الافتراضي للدور</a>
        </label>
        <p class="text-muted" style="font-size:0.8rem;margin-top:0">تحديد خيارات من هنا سيلغي الصلاحيات الافتراضية للدور ويعطي المستخدم وصولاً فقط للخيارات المحددة.</p>
        <div style="display:flex; flex-direction:column; gap:15px; background:var(--bg-card); padding:15px; border-radius:8px; border:1px solid var(--border)">
          <!-- Group 1: Dashboard -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-chart-line"></i> الرئيسية</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">الرئيسية</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="dashboard"> الرئيسية</label>
              </div>
            </div>
          </div>

          <!-- Group 2: Menu Management -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-utensils"></i> قائمة الطعام</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">قائمة الطعام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="categories"> الفئات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">قائمة الطعام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="items"> الأصناف</label>
              </div>
            </div>
          </div>

          <!-- Group 3: Transactions -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-receipt"></i> العمليات والطلبات</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">العمليات والطلبات</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="orders"> إدارة الطلبات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">العمليات والطلبات</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="wallets"> المحافظ الرقمية</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">العمليات والطلبات</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="offers"> العروض والتخفيضات</label>
              </div>
            </div>
          </div>

          <!-- Group 4: Financial Management -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-file-invoice-dollar"></i> إدارة المالية</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المالية</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="reports"> التقارير المالية</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المالية</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="financial_revenues"> الإيرادات اليومية</label>
              </div>
            </div>
          </div>

          <!-- Group 5: Inventory Management -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-boxes"></i> إدارة المخزون</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="ingredients"> المكونات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="warehouses"> اسماء المخازن</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="inventory"> إدخال المخزون والمشتريات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="inventory_report"> تقارير المخزون</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="sales_stats"> إحصائيات المبيعات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="inventory_requests_manage"> إدارة طلبات الصرف</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="inventory_requests_create"> تقديم طلبات مخزن</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="stock_management"> رصيد الأصناف (إدارة)</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة المخزون</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="show_stock"> رصيد الأصناف (عرض فقط)</label>
              </div>
            </div>
          </div>

          <!-- Group 6: System Management -->
          <div style="border:1px solid var(--border); padding:10px; border-radius:6px; background:var(--bg)">
            <div style="font-weight:bold; margin-bottom:8px; color:var(--primary); font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:4px"><i class="fas fa-cogs"></i> إدارة النظام</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px">
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="users"> المستخدمين</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="printers"> الطابعات</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="activity_log"> مراقبة النظام</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="item_audit_logs"> مراقبة الأسعار</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="direct_staff"> المباشرون / الويترز</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="item_times"> أوقات الأصناف</label>
              </div>
              <div style="display:flex; flex-direction:column; gap:2px">
                <small style="color:var(--text-muted); font-size:0.75rem; display:block">إدارة النظام</small>
                <label style="display:flex;align-items:center;gap:5px;margin:0;cursor:pointer"><input type="checkbox" name="u_perm" value="settings"> الإعدادات</label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeUserModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveUser()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script>
let allUsers = [], allRoles = [], allCatsForPerms = [], currentPermUser = null;
let currentPage = 1;
let currentLimit = 10;
let sortAsc = false;

function toggleSort() {
  sortAsc = !sortAsc;
  document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
  renderUsersTable();
}

async function loadUsers(deletedIds = null) {
  if (deletedIds && Array.isArray(deletedIds) && deletedIds.length) {
    allUsers = allUsers.filter(u => !deletedIds.includes(u.id.toString()));
    renderUsersTable();
    return;
  }
  const res = await apiCall('/api/users.php');
  if (!res.success) return;
  allUsers = res.data.users;
  allRoles = res.data.roles;

  // Fill roles in modal
  document.getElementById('user-role').innerHTML = allRoles.map(r=>`<option value="${r.id}">${r.name_ar} (${r.name})</option>`).join('');

  // Fetch and fill warehouses
  const wRes = await apiCall('/api/warehouses.php?action=get_all');
  if (wRes.success) {
    document.getElementById('user-warehouse').innerHTML = '<option value="">-- بدون مخزن مخصص --</option>' + 
      wRes.data.map(w => `<option value="${w.id}">${w.name} (${w.type === 'main' ? 'رئيسي' : 'فرعي'})</option>`).join('');
  }

  renderUsersTable();
}

function renderUsersTable() {
  const tbody = document.getElementById('users-tbody');
  
  if (!allUsers.length) {
    tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><p>لا يوجد مستخدمين</p></div></td></tr>';
    document.getElementById('pagination-container').innerHTML = '';
    return;
  }

  let items = [...allUsers];
  items.sort((a,b) => sortAsc ? a.id - b.id : b.id - a.id);

  const paginatedUsers = paginateData(items, currentPage, currentLimit);

  // Uncheck select-all when page changes
  const selectAllBtn = document.getElementById('select-all');
  if (selectAllBtn) selectAllBtn.checked = false;
  updateBulkDeleteBtn();

  tbody.innerHTML = paginatedUsers.map((u,i) => `
    <tr>
      <td>${u.id != 1 ? `<input type="checkbox" class="row-checkbox" value="${u.id}" onclick="updateBulkDeleteBtn()">` : ''}</td>
      <td style="white-space:nowrap">${(currentPage - 1) * currentLimit + i + 1}</td>
      <td style="white-space:nowrap"><strong>${u.name}</strong></td>
      <td style="white-space:nowrap">${u.name_en || '-'}</td>
      <td style="font-family:monospace; white-space:nowrap">${u.username}</td>
      <td style="white-space:nowrap"><span class="badge badge-info">${u.role_ar}</span></td>
      <td style="white-space:nowrap"><span class="badge ${u.is_active=='1'?'badge-confirmed':'badge-cancelled'}">${u.is_active=='1'?'نشط':'معطّل'}</span></td>
      <td style="white-space:nowrap">
        ${u.can_print=='1' ? '<i class="fas fa-print text-success" title="صلاحية الطباعة"></i>' : '<i class="fas fa-print text-muted" style="opacity:.3" title="لا توجد صلاحية"></i>'}
        ${u.role === 'cashier' ? `<small style="font-size:.7rem;display:block;color:${u.print_type==='bluetooth'?'#2980b9':'#27ae60'}">${u.print_type==='bluetooth'?'<i class="fab fa-bluetooth-b"></i>BT':'<i class="fas fa-wifi"></i>NET'}</small>` : ''}
      </td>
      <td style="white-space:nowrap">
        <button class="btn btn-warning btn-sm" onclick="editUser(${u.id})"><i class="fas fa-user-edit"></i></button>
        ${['chef','juice_bar'].includes(u.role) ? `<button class="btn btn-info btn-sm" onclick="openPermissions(${u.id})"><i class="fas fa-key"></i></button>` : ''}
        ${u.id != 1 ? `<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id},'${u.name}')"><i class="fas fa-trash"></i></button>` : ''}
      </td>
    </tr>
  `).join('');

  renderPagination(items.length, currentLimit, currentPage, 'pagination-container', 'setPage');
}

function setPage(p) {
  currentPage = p;
  renderUsersTable();
}

async function openPermissions(userId) {
  const user = allUsers.find(u => u.id == userId);
  if (!user) return;
  currentPermUser = userId;

  document.getElementById('perms-user-name').textContent = user.name;
  document.getElementById('perms-panel').style.display = 'block';

  // Load categories if not loaded
  if (!allCatsForPerms.length) {
    const cRes = await apiCall('/api/categories.php?all=1');
    if (cRes.success) allCatsForPerms = cRes.data;
  }

  const currentPerms = user.category_ids || [];
  const container = document.getElementById('perms-cats');
  container.innerHTML = allCatsForPerms.map(c => `
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;cursor:pointer">
      <input type="checkbox" value="${c.id}" ${currentPerms.includes(c.id.toString()) || currentPerms.includes(c.id) ? 'checked' : ''}>
      <span>${c.icon && c.icon.includes('fa-') ? `<i class="${c.icon}"></i>` : c.icon} ${c.name_ar}</span>
    </label>
  `).join('');
}

async function savePermissions() {
  if (!currentPermUser) return;
  const checkboxes = document.querySelectorAll('#perms-cats input[type=checkbox]');
  const catIds = Array.from(checkboxes).filter(c=>c.checked).map(c=>parseInt(c.value));
  const res = await apiCall('/api/users.php?action=permissions', 'POST', {user_id: currentPermUser, category_ids: catIds});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadUsers();
}

const ROLE_DEFAULTS = {
  'admin': ['dashboard', 'categories', 'items', 'offers', 'wallets', 'orders', 'users', 'reports', 'financial_revenues', 'printers', 'activity_log', 'item_audit_logs', 'settings', 'ingredients', 'warehouses', 'inventory', 'inventory_report', 'sales_stats', 'item_times', 'inventory_requests_manage', 'inventory_requests_create', 'stock_management', 'show_stock', 'direct_staff'],
  'waiter': [],
  'cashier': [],
  'chef': [],
  'juice_bar': [],
  'accountant': ['dashboard', 'reports', 'financial_revenues', 'wallets', 'activity_log', 'item_audit_logs'],
  'inventory_monitor': ['ingredients', 'inventory', 'inventory_report', 'sales_stats', 'item_times'],
  'request_coordinator': ['inventory_requests_manage'],
  'warehouse_manager': ['inventory', 'inventory_requests_manage', 'inventory_report']
};

function applyRoleDefaultPermissions(roleName) {
  const defaults = ROLE_DEFAULTS[roleName] || [];
  document.querySelectorAll('input[name="u_perm"]').forEach(cb => {
    cb.checked = defaults.includes(cb.value);
  });
}

function resetPermissionsToRoleDefault() {
  const roleId = document.getElementById('user-role').value;
  const role = allRoles.find(r => r.id == roleId);
  if (role) {
    applyRoleDefaultPermissions(role.name);
  }
}

function openUserModal(isEdit=false) {
  document.getElementById('user-modal-title').textContent = isEdit ? 'تعديل مستخدم' : 'إضافة مستخدم';
  document.getElementById('pass-group').style.display = isEdit ? 'none' : 'block';
  document.getElementById('newpass-group').style.display = isEdit ? 'block' : 'none';
  document.getElementById('status-group-user').style.display = isEdit ? 'block' : 'none';
  
  if (!isEdit) {
    const roleId = document.getElementById('user-role').value;
    const role = allRoles.find(r => r.id == roleId);
    if (role) {
      applyRoleDefaultPermissions(role.name);
    }
  }
  
  document.getElementById('user-modal').classList.remove('hidden');
}

function closeUserModal() {
  document.getElementById('user-modal').classList.add('hidden');
  document.getElementById('user-id').value = '';
  document.getElementById('user-name').value = '';
  document.getElementById('user-name-en').value = '';
  document.getElementById('user-username').value = '';
  document.getElementById('user-pass').value = '';
  document.getElementById('user-newpass').value = '';
  document.getElementById('user-warehouse').value = '';
  document.getElementById('user-can-print').checked = true;
  document.getElementById('user-printer-mac').value = '';
  document.getElementById('printer-mac-group').style.display = 'none';
  document.getElementById('print-type-group').style.display = 'none';
  document.getElementById('pt-network').checked = true;
  document.querySelectorAll('input[name="u_perm"]').forEach(cb => cb.checked = false);
}

function editUser(id) {
  const user = allUsers.find(u => u.id == id);
  if (!user) return;
  document.getElementById('user-id').value = user.id;
  document.getElementById('user-name').value = user.name;
  document.getElementById('user-name-en').value = user.name_en || '';
  document.getElementById('user-username').value = user.username;
  document.getElementById('user-role').value = user.role_id;
  document.getElementById('user-warehouse').value = user.warehouse_id || '';
  document.getElementById('user-status').value = user.is_active;
  document.getElementById('user-can-print').checked = user.can_print == 1;
  document.getElementById('user-printer-mac').value = user.printer_mac || '';

  // Show printer mac group if role is waiter, chef, or juice_bar
  const role = allRoles.find(r => r.id == user.role_id);
  const showMac = role && ['waiter', 'chef', 'juice_bar'].includes(role.name);
  document.getElementById('printer-mac-group').style.display = showMac ? 'block' : 'none';

  // Show print_type group if role is cashier, chef, or juice_bar
  const showPrintType = role && ['cashier', 'chef', 'juice_bar'].includes(role.name);
  document.getElementById('print-type-group').style.display = showPrintType ? 'block' : 'none';
  const ptVal = user.print_type || 'network';
  const ptRadio = document.querySelector(`input[name="print_type"][value="${ptVal}"]`);
  if (ptRadio) ptRadio.checked = true;

  document.querySelectorAll('input[name="u_perm"]').forEach(cb => cb.checked = false);
  let hasCustom = false;
  if (user.permissions !== null && user.permissions !== undefined && user.permissions !== "") {
    hasCustom = true;
    try {
      const perms = JSON.parse(user.permissions);
      if (Array.isArray(perms)) {
        perms.forEach(p => {
          const cb = document.querySelector(`input[name="u_perm"][value="${p}"]`);
          if (cb) cb.checked = true;
        });
      }
    } catch(e) {}
  }
  
  if (!hasCustom && role) {
    applyRoleDefaultPermissions(role.name);
  }
  
  openUserModal(true);
}

async function saveUser() {
  const id = document.getElementById('user-id').value;
  const fd = new FormData();
  fd.append('name',     document.getElementById('user-name').value);
  fd.append('name_en',  document.getElementById('user-name-en').value);
  fd.append('username', document.getElementById('user-username').value);
  fd.append('role_id',  document.getElementById('user-role').value);
  fd.append('warehouse_id', document.getElementById('user-warehouse').value);
  fd.append('can_print', document.getElementById('user-can-print').checked ? '1' : '0');
  fd.append('printer_mac', document.getElementById('user-printer-mac').value);
  const selectedPrintType = document.querySelector('input[name="print_type"]:checked');
  fd.append('print_type', selectedPrintType ? selectedPrintType.value : 'network');
  
  const permsCb = Array.from(document.querySelectorAll('input[name="u_perm"]:checked')).map(cb => cb.value);
  if (permsCb.length > 0) {
    fd.append('permissions', JSON.stringify(permsCb));
  } else {
    // If none are checked, we pass empty string or null so it defaults to role base,
    // OR if user explicitly wants an empty array representing 'no access', 
    // we can pass '[]'. Let's differentiate: if they unchecked them all, 
    // we assume they want role defaults back. So pass empty string.
    fd.append('permissions', ''); 
  }

  if (id) {
    fd.append('id', id);
    fd.append('is_active', document.getElementById('user-status').value);
    const newPass = document.getElementById('user-newpass').value;
    if (newPass.length >= 6) {
      const pRes = await apiCall('/api/users.php?action=reset_pass', 'POST', {id, password:newPass});
      if (!pRes.success) { showToast(pRes.message, 'danger'); return; }
    }
  } else {
    fd.append('password', document.getElementById('user-pass').value);
  }
  const url = '/api/users.php' + (id ? '?action=update' : '');
  const data = await apiCall(url, 'POST', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) { closeUserModal(); loadUsers(); }
}

async function deleteUser(id, name) {
  if (!confirmAction(`هل أنت متأكد من حذف المستخدم '${name}'؟`)) return;
  const res = await apiCall('/api/users.php?action=delete', 'POST', {id: id});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadUsers([id.toString()]);
}

document.addEventListener('DOMContentLoaded', loadUsers);
document.getElementById('user-modal').addEventListener('click', e => { if(e.target.id==='user-modal') closeUserModal(); });
// Watch role change to show/hide printer mac + print_type fields and update permissions checklist
document.getElementById('user-role').addEventListener('change', function() {
  const role = allRoles.find(r => r.id == this.value);
  const showMac = role && ['waiter', 'chef', 'juice_bar'].includes(role.name);
  document.getElementById('printer-mac-group').style.display = showMac ? 'block' : 'none';
  const showPrintType = role && ['cashier', 'chef', 'juice_bar'].includes(role.name);
  document.getElementById('print-type-group').style.display = showPrintType ? 'block' : 'none';
  
  if (role) {
    applyRoleDefaultPermissions(role.name);
  }
});

// Highlight selected print_type card
document.querySelectorAll('input[name="print_type"]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.getElementById('label-network').style.borderColor   = this.value === 'network'   ? 'var(--primary)' : 'var(--border)';
    document.getElementById('label-bluetooth').style.borderColor = this.value === 'bluetooth' ? '#2980b9'        : 'var(--border)';
    document.getElementById('label-chef').style.borderColor      = this.value === 'chef'      ? '#e67e22'        : 'var(--border)';
  });
});
</script>

<?php adminFooter(); ?>
