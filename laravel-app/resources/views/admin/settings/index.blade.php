@extends('layouts.admin')
@section('title', 'إعدادات النظام')
@section('content')

<div class="settings-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(350px, 1fr)); gap:20px; margin-top:20px">
    
    <!-- Print Server Settings -->
    <div class="card">
        <div class="card-header" style="background:#e0f2fe; color:#0369a1">
            <h3 style="margin:0"><i class="fas fa-print"></i> إعدادات خادم الطباعة</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <div class="form-group mb-12">
                    <label class="form-label">اسم طابعة USB (Windows)</label>
                    <input type="text" name="usb_printer_name" class="form-control" value="{{ $settings['usb_printer_name'] ?? 'Xprinter XP-58' }}">
                </div>
                <div class="form-group mb-12">
                    <label class="form-label">عنوان خادم الطباعة (Local URL)</label>
                    <input type="text" name="print_server_url" class="form-control" value="{{ $settings['print_server_url'] ?? 'http://localhost:3000' }}">
                </div>
                <div class="form-group mb-12">
                    <label class="form-label">مفتاح الأمان (API Key)</label>
                    <input type="password" name="print_server_key" class="form-control" value="{{ $settings['print_server_key'] ?? '' }}">
                </div>
                <div style="margin:15px 0">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                        <input type="checkbox" name="auto_print_kitchen" value="1" {{ ($settings['auto_print_kitchen'] ?? '1') == '1' ? 'checked' : '' }}>
                        <span>طباعة تلقائية للمطبخ عند الطلب</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:8px">
                        <input type="checkbox" name="auto_print_receipt" value="1" {{ ($settings['auto_print_receipt'] ?? '1') == '1' ? 'checked' : '' }}>
                        <span>طباعة تلقائية للفاتورة عند الدفع</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-block" style="background:#0369a1; color:#fff"><i class="fas fa-save"></i> حفظ إعدادات الطباعة</button>
            </form>
        </div>
    </div>

    <!-- Kitchen App API Key -->
    <div class="card" style="border:1px solid #d1fae5">
        <div class="card-header" style="background:#d1fae5; color:#065f46">
            <h3 style="margin:0"><i class="fas fa-mobile-alt"></i> مفتاح تطبيق المطبخ</h3>
        </div>
        <div class="card-body">
            <p style="font-size:.9rem; color:#666; margin-bottom:15px">هذا المفتاح يستخدمه تطبيق <strong>Sheba Print Service</strong> للاتصال بالنظام.</p>
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">API Key الخاص بالتطبيق</label>
                    <div style="display:flex; gap:8px">
                        <input type="text" name="kitchen_api_key" id="kitchen-key" class="form-control" value="{{ $settings['kitchen_api_key'] ?? '' }}" style="font-family:monospace">
                        <button type="button" class="btn btn-secondary" onclick="generateKey()">توليد</button>
                    </div>
                </div>
                <div style="background:#f0fdf4; border:1px dashed #86efac; border-radius:8px; padding:12px; margin:15px 0; font-size:.85rem; color:#166534">
                    <strong>رابط النظام للتطبيق (Base URL):</strong><br>
                    <code>{{ url('/') }}</code>
                </div>
                <button type="submit" class="btn btn-block" style="background:#065f46; color:#fff"><i class="fas fa-save"></i> حفظ المفتاح</button>
            </form>
        </div>
    </div>

    <!-- System Reset -->
    <div class="card" style="border:1px solid #fee2e2">
        <div class="card-header" style="background:#fee2e2; color:#991b1b">
            <h3 style="margin:0"><i class="fas fa-exclamation-triangle"></i> تصفير النظام</h3>
        </div>
        <div class="card-body">
            <p style="font-size:.9rem; color:#666">سيتم حذف جميع <strong>الطلبات</strong> و <strong>سجل المبيعات</strong> بشكل نهائي.</p>
            <div class="alert alert-danger" style="margin:15px 0; padding:10px; font-size:.85rem">تنبيه: لا يمكن التراجع عن هذا الإجراء!</div>
            <button class="btn btn-danger btn-block" onclick="resetSystem()"><i class="fas fa-trash-alt"></i> تصفير العمليات الآن</button>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function generateKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let key = 'sheba-';
    for (let i = 0; i < 32; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('kitchen-key').value = key;
}

async function resetSystem() {
    const pwd = prompt('⚠️ سيتم حذف جميع الطلبات! أدخل كلمة مرور المدير لتأكيد الحذف:');
    if (!pwd) return;

    if (!confirm('هل أنت متأكد تماماً؟ سيتم تصفير عداد الطلبات وسجل المبيعات.')) return;

    const res = await fetch("{{ route('admin.settings.reset') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ password: pwd })
    }).then(r => r.json());

    if (res.success) {
        alert(res.message);
        location.reload();
    } else {
        alert(res.message);
    }
}
</script>
@endpush
