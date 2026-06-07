@extends('layouts.admin')
@section('title', 'إعدادات الطابعات')

@section('content')
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 style="margin:0"><i class="fas fa-print"></i> أجهزة الطباعة (المطبخ، الكاشير، إلخ)</h3>
        <div style="display:flex; gap:10px">
            <button class="btn btn-sm" style="background:#0a0a2e; color:#4fc3f7; border:1px solid #4fc3f7" onclick="pushToLocalServer()">
                <i class="fas fa-sync"></i> مزامنة مع السيرفر المحلي
            </button>
            <button class="btn btn-primary" onclick="openPrinterModal()"><i class="fas fa-plus"></i> إضافة طابعة</button>
        </div>
    </div>
    <div class="alert alert-info" style="margin:10px 15px; font-size:0.85rem">
        <i class="fas fa-info-circle"></i> الطباعة تعتمد على <strong>PowerShell + winspool.drv</strong> وتعمل فقط على جهاز الكمبيوتر الذي يشغّل الخادم. تأكد من إدخال اسم الطابعة كما يظهر في <strong>قائمة الطابعات بـ Windows</strong>.
    </div>

    @if(session('success'))
    <div class="alert alert-success" style="margin:0 15px 10px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم العرض</th>
                        <th>اسم الطابعة (Windows) 🖨️</th>
                        <th>عنوان IP</th>
                        <th>النوع</th>
                        <th>اختبار</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($printers as $printer)
                    <tr>
                        <td>{{ $printer->id }}</td>
                        <td><strong>{{ $printer->name }}</strong></td>
                        <td>
                            <code style="font-size:.8rem; background:rgba(0,0,0,.08); padding:2px 8px; border-radius:4px; direction:ltr; display:inline-block">
                                {{ $printer->windows_name ?: '—' }}
                            </code>
                        </td>
                        <td><code>{{ $printer->ip ?: '—' }}</code></td>
                        <td>
                            @php
                                $typeLabels = ['cashier'=>['label'=>'كاشير','class'=>'badge-success'],
                                               'kitchen'=>['label'=>'مطبخ','class'=>'badge-warning'],
                                               'bar'    =>['label'=>'بار','class'=>'badge-info'],
                                               'grill'  =>['label'=>'مشويات','class'=>'badge-danger'],
                                               'shisha' =>['label'=>'شيشة','class'=>'badge-secondary']];
                                $tl = $typeLabels[$printer->type] ?? ['label'=>$printer->type,'class'=>'badge-secondary'];
                            @endphp
                            <span class="badge {{ $tl['class'] }}">{{ $tl['label'] }}</span>
                        </td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="testPrinter('{{ addslashes($printer->windows_name) }}')" title="اختبار الطباعة">
                                <i class="fas fa-vial"></i>
                            </button>
                        </td>
                        <td>
                            <div style="display:flex; gap:5px">
                                <button class="btn btn-info btn-sm" onclick='editPrinter(@json($printer))' title="تعديل"><i class="fas fa-edit"></i></button>
                                <form action="{{ route('admin.printers.destroy', $printer->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الطابعة؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:#888">
                            <i class="fas fa-print" style="font-size:2rem; margin-bottom:10px; display:block; opacity:.3"></i>
                            لا توجد طابعات مضافة حالياً.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add / Edit Printer Modal --}}
<div class="modal-backdrop hidden" id="printer-modal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 id="printer-modal-title"><i class="fas fa-print"></i> إضافة طابعة</h3>
            <button class="modal-close" onclick="closePrinterModal()">✕</button>
        </div>
        <form id="printer-form" action="{{ route('admin.printers.store') }}" method="POST">
            @csrf
            <input type="hidden" name="_method_override" id="form-method" value="POST">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">اسم العرض (وصفي) *</label>
                    <input type="text" name="name" id="p-name" class="form-control" placeholder="مثال: طابعة الكاشير" required>
                </div>

                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">🖨️ اسم الطابعة في Windows *</label>
                    <input type="text" name="windows_name" id="p-windows-name" class="form-control"
                        placeholder="مثال: chashier  أو  MNK on 10.0.0.191"
                        style="font-family:monospace; direction:ltr; text-align:left" required>
                    <small style="opacity:.6; font-size:.78rem">الاسم كما يظهر في قائمة الطابعات بجهاز Windows</small>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px">
                    <div class="form-group">
                        <label class="form-label">عنوان IP (اختياري)</label>
                        <input type="text" name="ip_address" id="p-ip" class="form-control" placeholder="10.0.0.191" style="direction:ltr">
                    </div>
                    <div class="form-group">
                        <label class="form-label">المنفذ</label>
                        <input type="number" name="port" id="p-port" class="form-control" value="9100" style="direction:ltr">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">النوع (الاستخدام) *</label>
                    <select name="type" id="p-type" class="form-control">
                        <option value="cashier">🧾 طابعة الكاشير</option>
                        <option value="kitchen">🍳 طابعة المطبخ / الشيف</option>
                        <option value="bar">🥤 طابعة البار</option>
                        <option value="grill">🔥 طابعة المشويات</option>
                        <option value="shisha">💨 طابعة الشيشة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الفئات المطبوعة (للمطبخ) — اتركها فارغة للكل</label>
                    <div style="background:var(--bg-card2,#f8f9fa); padding:12px; border-radius:8px; border:1px solid var(--border); display:grid; grid-template-columns:1fr 1fr; gap:8px; max-height:180px; overflow-y:auto">
                        @foreach($categories as $cat)
                        <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-size:.85rem">
                            <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" class="cat-checkbox"> {{ $cat->name_ar }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:10px">
                <button type="button" class="btn btn-outline" onclick="closePrinterModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ ومزامنة</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const PRINT_URL = '{{ url("admin/print") }}';
const LOCAL_SERVER = 'http://localhost:3000';
const CSRF = '{{ csrf_token() }}';

// ── Modal open/close ────────────────────────────────────────────────────────
function openPrinterModal(edit = false) {
    if (!edit) {
        document.getElementById('printer-modal-title').innerHTML = '<i class="fas fa-plus"></i> إضافة طابعة جديدة';
        document.getElementById('printer-form').action = '{{ route("admin.printers.store") }}';
        document.getElementById('form-method').value = 'POST';
        document.getElementById('printer-form').reset();
    }
    document.getElementById('printer-modal').classList.remove('hidden');
}

function closePrinterModal() {
    document.getElementById('printer-modal').classList.add('hidden');
}

function editPrinter(p) {
    document.getElementById('printer-modal-title').innerHTML = '<i class="fas fa-edit"></i> تعديل الطابعة';
    document.getElementById('printer-form').action = `/restaurant/laravel-app/public/admin/printers/${p.id}/update`;
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('p-name').value          = p.name || '';
    document.getElementById('p-windows-name').value  = p.windows_name || '';
    document.getElementById('p-ip').value            = p.ip || '';
    document.getElementById('p-port').value          = p.port || '9100';
    document.getElementById('p-type').value          = p.type || 'cashier';

    // Uncheck all then check matching
    const catIds = p.category_ids ? JSON.parse(p.category_ids) : [];
    document.querySelectorAll('.cat-checkbox').forEach(cb => {
        cb.checked = catIds.includes(parseInt(cb.value));
    });

    openPrinterModal(true);
}

// ── Test printer ────────────────────────────────────────────────────────────
async function testPrinter(windowsName) {
    if (!windowsName) { alert('لا يوجد اسم طابعة!'); return; }
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const res = await fetch(`${PRINT_URL}/test`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
        });
        const data = await res.json();
        if (typeof showToast === 'function') {
            showToast(data.message, data.success ? 'success' : 'danger');
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert('فشل الاتصال: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-vial"></i>';
}

// ── Sync all printers to local Node.js server ──────────────────────────────
async function pushToLocalServer() {
    if (!confirm('سيتم إرسال جميع الطابعات إلى سيرفر الطباعة المحلي (localhost:3000). هل أنت متأكد؟')) return;

    const rows = document.querySelectorAll('tbody tr');
    let count = 0;

    for (const row of rows) {
        const cells = row.querySelectorAll('td');
        if (cells.length < 5) continue;
        const name = cells[1].textContent.trim();
        const ip   = cells[3].textContent.trim();
        if (!name) continue;
        try {
            await fetch(`${LOCAL_SERVER}/printers`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, ip, connection: 'network', status: 'active' })
            });
            count++;
        } catch(e) { /* silent fail if server offline */ }
    }

    if (typeof showToast === 'function') {
        showToast(`تمت المزامنة: ${count} طابعة`, 'success');
    } else {
        alert(`تمت المزامنة: ${count} طابعة`);
    }
}
</script>
@endpush
