@extends('layouts.admin')
@section('title', 'إدارة المستخدمين')

@section('content')
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

{{-- ─── Users List ─── --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users-cog"></i> المستخدمون</h3>
        <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-danger btn-sm" id="bulk-delete-btn" style="display:none" onclick="bulkDelete()">
                <i class="fas fa-trash"></i> حذف المحدّد (<span id="selected-count">0</span>)
            </button>
            <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-user-plus"></i> إضافة مستخدم</button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 15px">
            <div style="font-size:.9rem">
                عرض
                <select class="form-control" style="width:auto;display:inline-block;padding:2px 30px 2px 10px;height:30px" onchange="perPage=+this.value;page=1;render()">
                    <option>10</option><option>20</option><option>50</option><option>100</option>
                </select>
                أسطر
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                    <th>#</th><th>الاسم</th><th>الاسم بالإنجليزي</th><th>اسم المستخدم</th>
                    <th>الدور</th><th>الحالة</th><th><i class="fas fa-print"></i></th><th>إجراءات</th>
                </tr></thead>
                <tbody id="users-tbody">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
        <div id="pagination-container" style="padding:12px 16px"></div>
    </div>
</div>

{{-- ─── Category Permissions Panel ─── --}}
<div class="card" id="perms-panel" style="display:none">
    <div class="card-header">
        <h3><i class="fas fa-key"></i> صلاحيات الفئات</h3>
        <small id="perms-user-name" style="color:var(--text-muted)"></small>
    </div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:12px">اختر الفئات التي يراها هذا المستخدم (فارغ = يرى الكل)</p>
        <div id="perms-cats"></div>
        <button class="btn btn-success" style="width:100%;margin-top:12px" onclick="savePerms()">
            <i class="fas fa-save"></i> حفظ الصلاحيات
        </button>
    </div>
</div>

</div>{{-- end grid --}}

{{-- ─── Add/Edit Modal ─── --}}
<div class="modal-backdrop hidden" id="user-modal">
    <div class="modal" style="max-width:520px;max-height:90vh;overflow-y:auto">
        <div class="modal-header">
            <h3 id="modal-title">إضافة مستخدم</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="u-id">
            <div class="form-group"><label class="form-label">الاسم الكامل *</label><input type="text" class="form-control" id="u-name" required></div>
            <div class="form-group"><label class="form-label">الاسم بالإنجليزي (للفاتورة)</label><input type="text" class="form-control" id="u-name-en"></div>
            <div class="form-group"><label class="form-label">اسم المستخدم *</label><input type="text" class="form-control" id="u-username" required></div>
            <div class="form-group" id="pass-new-group"><label class="form-label">كلمة المرور *</label><input type="password" class="form-control" id="u-pass" minlength="6"><div class="form-hint">6 أحرف على الأقل</div></div>
            <div class="form-group" id="pass-edit-group" style="display:none"><label class="form-label">كلمة مرور جديدة (فارغ = لا تغيير)</label><input type="password" class="form-control" id="u-newpass" minlength="6"></div>
            <div class="form-group">
                <label class="form-label">الدور *</label>
                <select class="form-control" id="u-role" onchange="toggleMacField()">
                    @foreach($roles as $role)
                    <option value="{{ $role->id }}" data-name="{{ $role->name }}">{{ $role->name_ar }} ({{ $role->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" id="printer-mac-group" style="display:none">
                <label class="form-label">عنوان طابعة البلوتوث (MAC Address)</label>
                <input type="text" class="form-control" id="u-printer-mac" placeholder="00:11:22:33:44:55">
                <small style="color:var(--text-muted)">خاص بالويترز حصراً</small>
            </div>
            <div class="form-group" id="status-group" style="display:none">
                <label class="form-label">الحالة</label>
                <select class="form-control" id="u-status"><option value="1">نشط</option><option value="0">معطّل</option></select>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="u-can-print" checked><span>صلاحية الطباعة</span>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">الصلاحيات المخصصة (اختياري)</label>
                <p style="font-size:.8rem;color:var(--text-muted);margin-top:0">تحديد خيارات هنا يلغي صلاحيات الدور الافتراضية</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;background:var(--bg);padding:15px;border-radius:8px;border:1px solid var(--border)">
                    @php
                    $permGroupNames = [
                        'dashboard'=>'الرئيسية',
                        'categories'=>'قائمة الطعام',
                        'items'=>'قائمة الطعام',
                        'offers'=>'العمليات والطلبات',
                        'wallets'=>'العمليات والطلبات',
                        'orders'=>'العمليات والطلبات',
                        'users'=>'إدارة النظام',
                        'reports'=>'إدارة المالية',
                        'printers'=>'إدارة النظام',
                        'activity_log'=>'إدارة النظام',
                        'item_audit_logs'=>'إدارة النظام',
                        'settings'=>'إدارة النظام',
                        'inventory'=>'إدارة المخزون',
                        'inventory_report'=>'إدارة المخزون',
                        'inventory_requests_manage'=>'إدارة المخزون',
                        'inventory_requests_create'=>'إدارة المخزون',
                    ];
                    $permOptions = [
                        'dashboard'=>'الرئيسية','categories'=>'الفئات','items'=>'الأصناف',
                        'offers'=>'العروض والتخفيضات','wallets'=>'المحافظ','orders'=>'إدارة الطلبات',
                        'users'=>'المستخدمين','reports'=>'التقارير','printers'=>'الطابعات',
                        'activity_log'=>'مراقبة النظام','item_audit_logs'=>'مراقبة الأسعار',
                        'settings'=>'الإعدادات','inventory'=>'إدخال المخزون',
                        'inventory_report'=>'تقارير المخزون','inventory_requests_manage'=>'إدارة طلبات الصرف',
                        'inventory_requests_create'=>'تقديم طلبات مخزن',
                    ];
                    @endphp
                    @foreach($permOptions as $val => $label)
                    <div style="display:flex; flex-direction:column; gap:2px">
                        <small style="color:var(--text-muted); font-size:0.75rem; display:block">{{ $permGroupNames[$val] ?? '' }}</small>
                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;margin:0">
                            <input type="checkbox" name="u_perm" value="{{ $val }}"> {{ $label }}
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="saveUser()"><i class="fas fa-save"></i> حفظ</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let allUsers = [], allRoles = [], allCats = [], currentPermUser = null;
let page = 1, perPage = 10, sortAsc = false;

async function loadUsers() {
    const res = await fetch('{{ url("/admin/api/users") }}', {headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}}).then(r=>r.json());
    if (!res.success) return;
    allUsers = res.data.users;
    allRoles = res.data.roles;
    page = 1;
    render();
}

function render() {
    const sorted = [...allUsers].sort((a,b) => sortAsc ? a.id-b.id : b.id-a.id);
    const total = sorted.length;
    const start = (page-1)*perPage;
    const paged = sorted.slice(start, start+perPage);

    document.getElementById('select-all').checked = false;
    document.getElementById('bulk-delete-btn').style.display = 'none';
    document.getElementById('selected-count').textContent = '0';

    document.getElementById('users-tbody').innerHTML = paged.length
        ? paged.map((u,i) => `<tr>
            <td>${u.id != 1 ? `<input type="checkbox" class="row-cb" value="${u.id}" onchange="updateBulkBtn()">` : ''}</td>
            <td>${start+i+1}</td>
            <td><strong>${u.name}</strong></td>
            <td>${u.name_en||'-'}</td>
            <td style="font-family:monospace">${u.username}</td>
            <td><span class="badge badge-info">${u.role_name_ar||u.role_name||''}</span></td>
            <td><span class="badge badge-${u.is_active=='1'||u.is_active===1?'success':'danger'}">${u.is_active=='1'||u.is_active===1?'نشط':'معطّل'}</span></td>
            <td>${(u.can_print=='1'||u.can_print===1)?'<i class="fas fa-print" style="color:var(--success)"></i>':'<i class="fas fa-print" style="opacity:.2"></i>'}</td>
            <td style="display:flex;gap:4px">
                <button class="btn btn-warning btn-sm" onclick="editUser(${u.id})"><i class="fas fa-user-edit"></i></button>
                ${['chef','juice_bar'].includes(u.role_name)?`<button class="btn btn-info btn-sm" onclick="openPerms(${u.id})"><i class="fas fa-key"></i></button>`:''}
                ${u.id!=1?`<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id},'${u.name}')"><i class="fas fa-trash"></i></button>`:''}
            </td>
        </tr>`).join('')
        : '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">لا يوجد مستخدمون</td></tr>';

    renderPagination(total, perPage, page, 'pagination-container');
}

function renderPagination(total, perPage, currentPage, containerId) {
    const pages = Math.ceil(total/perPage);
    if (pages <= 1) { document.getElementById(containerId).innerHTML=''; return; }
    let html = '<div style="display:flex;gap:6px;align-items:center">';
    for(let i=1;i<=pages;i++) {
        html += `<button onclick="page=${i};render()" style="padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:${i===currentPage?'var(--primary)':'transparent'};color:${i===currentPage?'#fff':'inherit'};cursor:pointer">${i}</button>`;
    }
    html += '</div>';
    document.getElementById(containerId).innerHTML = html;
}

function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-cb:checked').length;
    document.getElementById('selected-count').textContent = checked;
    document.getElementById('bulk-delete-btn').style.display = checked>0?'':'none';
}

function toggleAll(cb) {
    document.querySelectorAll('.row-cb').forEach(c => c.checked = cb.checked);
    updateBulkBtn();
}

function openModal(isEdit=false) {
    document.getElementById('modal-title').textContent = isEdit ? 'تعديل مستخدم' : 'إضافة مستخدم';
    document.getElementById('pass-new-group').style.display = isEdit?'none':'block';
    document.getElementById('pass-edit-group').style.display = isEdit?'block':'none';
    document.getElementById('status-group').style.display = isEdit?'block':'none';
    document.getElementById('user-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('user-modal').classList.add('hidden');
    document.getElementById('u-id').value = '';
    ['u-name','u-name-en','u-username','u-pass','u-newpass','u-printer-mac'].forEach(id => document.getElementById(id).value='');
    document.getElementById('u-can-print').checked = true;
    document.getElementById('printer-mac-group').style.display='none';
    document.querySelectorAll('input[name="u_perm"]').forEach(c=>c.checked=false);
}

function editUser(id) {
    const u = allUsers.find(u=>u.id==id); if(!u) return;
    document.getElementById('u-id').value = u.id;
    document.getElementById('u-name').value = u.name||'';
    document.getElementById('u-name-en').value = u.name_en||'';
    document.getElementById('u-username').value = u.username||'';
    document.getElementById('u-role').value = u.role_id||'';
    document.getElementById('u-status').value = u.is_active||1;
    document.getElementById('u-can-print').checked = u.can_print=='1'||u.can_print===1;
    document.getElementById('u-printer-mac').value = u.printer_mac||'';
    toggleMacField();
    document.querySelectorAll('input[name="u_perm"]').forEach(c=>c.checked=false);
    try {
        const perms = typeof u.permissions==='string' ? JSON.parse(u.permissions) : (u.permissions||[]);
        if(Array.isArray(perms)) perms.forEach(p=>{const cb=document.querySelector(`input[name="u_perm"][value="${p}"]`);if(cb)cb.checked=true;});
    } catch(e){}
    openModal(true);
}

function toggleMacField() {
    const sel = document.getElementById('u-role');
    const roleName = sel.options[sel.selectedIndex]?.dataset?.name||'';
    document.getElementById('printer-mac-group').style.display = roleName==='waiter'?'block':'none';
}

async function saveUser() {
    const id = document.getElementById('u-id').value;
    const perms = Array.from(document.querySelectorAll('input[name="u_perm"]:checked')).map(c=>c.value);
    const body = {
        name: document.getElementById('u-name').value,
        name_en: document.getElementById('u-name-en').value,
        username: document.getElementById('u-username').value,
        role_id: document.getElementById('u-role').value,
        is_active: document.getElementById('u-status').value||'1',
        can_print: document.getElementById('u-can-print').checked?'1':'0',
        printer_mac: document.getElementById('u-printer-mac').value,
        permissions: perms.length ? perms : null,
    };
    if (id) { body.password = document.getElementById('u-newpass').value; }
    else { body.password = document.getElementById('u-pass').value; }

    const url = id
        ? '{{ url("/admin/api/users") }}/'+id
        : '{{ url("/admin/api/users") }}';
    const method = id ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':window.CSRF_TOKEN},
        body: JSON.stringify(body)
    }).then(r=>r.json());

    if (res.success) { closeModal(); loadUsers(); showToast(res.message, 'success'); }
    else { showToast(res.message||'حدث خطأ', 'danger'); }
}

async function deleteUser(id, name) {
    if (!confirm(`حذف المستخدم "${name}"؟`)) return;
    const res = await fetch(`{{ url("/admin/api/users") }}/${id}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}
    }).then(r=>r.json());
    if (res.success) { allUsers = allUsers.filter(u=>u.id!=id); render(); showToast(res.message,'success'); }
    else showToast(res.message,'danger');
}

async function bulkDelete() {
    const ids = Array.from(document.querySelectorAll('.row-cb:checked')).map(c=>c.value);
    if (!ids.length || !confirm(`حذف ${ids.length} مستخدم؟`)) return;
    for(const id of ids) await fetch(`{{ url("/admin/api/users") }}/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}}).then(r=>r.json());
    loadUsers();
}

// Category permissions
async function openPerms(userId) {
    const u = allUsers.find(u=>u.id==userId); if(!u) return;
    currentPermUser = userId;
    document.getElementById('perms-user-name').textContent = u.name;
    document.getElementById('perms-panel').style.display='block';
    if (!allCats.length) {
        const r = await fetch('{{ url("/admin/api/categories") }}',{headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}}).then(r=>r.json());
        if(r.success) allCats = r.data;
    }
    const currentPerms = u.category_ids||[];
    document.getElementById('perms-cats').innerHTML = allCats.map(c=>`
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;cursor:pointer">
            <input type="checkbox" value="${c.id}" ${currentPerms.includes(c.id)||currentPerms.includes(String(c.id))?'checked':''}>
            <span>${c.name_ar}</span>
        </label>`).join('');
}

async function savePerms() {
    if (!currentPermUser) return;
    const catIds = Array.from(document.querySelectorAll('#perms-cats input:checked')).map(c=>+c.value);
    const res = await fetch('{{ url("/admin/api/users/permissions") }}', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':window.CSRF_TOKEN},
        body: JSON.stringify({user_id:currentPermUser, category_ids:catIds})
    }).then(r=>r.json());
    showToast(res.message, res.success?'success':'danger');
    if(res.success) loadUsers();
}

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:10px;color:#fff;z-index:9999;background:${type==='success'?'#27ae60':'#e74c3c'}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(), 3000);
}

loadUsers();
</script>
@endpush
