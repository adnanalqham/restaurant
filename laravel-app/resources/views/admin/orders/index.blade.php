@extends('layouts.admin')
@section('title', 'إدارة الطلبات')

@push('styles')
<style>
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    .order-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        padding: 18px;
        border-top: 5px solid var(--primary);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #eee;
        position: relative;
    }
    .order-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .order-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 12px;
        margin-bottom: 14px;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    .item-line {
        display: flex;
        justify-content: space-between;
        font-size: 0.88rem;
        padding: 5px 0;
        border-bottom: 1px dashed #eee;
        color: #444;
    }
    .item-line:last-child { border-bottom: none; }
    .order-footer {
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>
@endpush

@section('content')

{{-- Top Actions & Filters --}}
<div class="card mb-20">
    <div class="card-body" style="padding: 15px 20px">
        <form method="GET" id="filter-form" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
            <select name="status" class="form-control" style="width:auto; min-width:180px" onchange="this.form.submit()">
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>كل الطلبات النشطة</option>
                <option value="" {{ request('status') == '' ? 'selected' : '' }}>كل الحالات</option>
                @foreach(['pending'=>'معلق','sent_to_cashier'=>'أُرسل للكاشير','confirmed'=>'بدأ التحضير','in_progress'=>'قيد التحضير','ready'=>'جاهز للاستلام','paid'=>'مدفوع','delivered'=>'تم التسليم','cancelled'=>'ملغي'] as $v => $l)
                <option value="{{ $v }}" {{ request('status') == $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>

            <div style="display:flex; gap:8px; align-items:center">
                <label style="font-size:0.9rem">من:</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control" style="width:auto" onchange="this.form.submit()">
                <label style="font-size:0.9rem">إلى:</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control" style="width:auto" onchange="this.form.submit()">
            </div>

            <button type="button" class="btn btn-outline" onclick="location.reload()"><i class="fas fa-sync-alt"></i> تحديث</button>
            <span class="badge badge-info" style="font-size:0.95rem; padding:8px 15px">{{ $orders->total() }} طلب</span>

            <div style="flex-grow:1"></div>

            <form action="{{ route('admin.orders.deliver_all_active') }}" method="POST" onsubmit="return confirm('هل أنت متأكد من تسليم جميع الطلبات النشطة حالياً؟')">
                @csrf
                <button type="submit" class="btn btn-success"><i class="fas fa-magic"></i> تسليم ذكي (الكل)</button>
            </form>
        </form>
    </div>
</div>

{{-- Orders Grid --}}
<div class="orders-grid">
    @foreach($orders as $o)
    @php
        $statusColors = [
            'pending' => '#f39c12', 'sent_to_cashier' => '#3498db', 'confirmed' => '#2980b9',
            'in_progress' => '#e67e22', 'ready' => '#27ae60', 'paid' => '#2ecc71',
            'delivered' => '#27ae60', 'cancelled' => '#e74c3c'
        ];
        $statusLabels = [
            'pending' => 'معلق', 'sent_to_cashier' => 'للكاشير', 'confirmed' => 'مؤكد',
            'in_progress' => 'جاري', 'ready' => 'جاهز', 'paid' => 'مدفوع',
            'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'
        ];
        $color = $statusColors[$o->status] ?? '#95a5a6';
        $label = $statusLabels[$o->status] ?? $o->status;
    @endphp
    <div class="order-card" style="border-top-color: {{ $color }}">
        <div class="order-card-header">
            <div style="display:flex; align-items:center; gap:8px">
                <strong style="color:{{ $color }}; font-size:1.1rem">#{{ $o->order_number }}</strong>
                @if($o->table_number)
                <span class="badge badge-secondary">طاولة {{ $o->table_number }}</span>
                @endif
            </div>
            <span class="status-badge" style="background:{{ $color }}20; color:{{ $color }}; border:1px solid {{ $color }}40">{{ $label }}</span>
        </div>

        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:#888; margin-bottom:12px">
            <span><i class="fas fa-clock"></i> {{ \Carbon\Carbon::parse($o->created_at)->format('H:i Y/m/d') }}</span>
            <span><i class="fas fa-user-tag"></i> {{ $o->waiter_name ?? '---' }}</span>
        </div>

        <div class="items-list" style="flex:1">
            @foreach($o->items->take(5) as $item)
            <div class="item-line">
                <span>{{ $item->item_name_ar }} x{{ (int)$item->quantity }}</span>
                <span style="font-weight:600">{{ number_format($item->subtotal, 2) }}</span>
            </div>
            @endforeach
            @if($o->items->count() > 5)
            <div style="color:var(--primary); font-size:0.8rem; margin-top:5px">+ {{ $o->items->count() - 5 }} أصناف أخرى</div>
            @endif
        </div>

        @if($o->notes)
        <div style="font-size:0.8rem; color:#d35400; background:#fff5e6; padding:6px; border-radius:6px; margin-top:10px">
            <i class="fas fa-sticky-note"></i> {{ $o->notes }}
        </div>
        @endif

        <div class="order-footer">
            <strong style="color:var(--primary); font-size:1.2rem">{{ number_format($o->total, 2) }} <small>ريال</small></strong>
            <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end">
                <button class="btn btn-secondary btn-sm" onclick="printOrder('receipt', {{ $o->id }})" title="طباعة فورية (كاشير)"><i class="fas fa-bolt"></i></button>
                <button class="btn btn-warning btn-sm" onclick="printOrder('kitchen', {{ $o->id }})" title="طباعة فورية (مطبخ)" style="color:#fff"><i class="fas fa-fire"></i></button>
                <a href="{{ route('admin.orders.print', $o->id) }}" target="_blank" class="btn btn-outline btn-sm" title="طباعة المتصفح (PDF)"><i class="fas fa-print"></i></a>
                <button class="btn btn-info btn-sm" onclick="viewDetails({{ $o->id }})"><i class="fas fa-eye"></i></button>
                @if(!in_array($o->status, ['paid', 'cancelled']))
                <form action="{{ route('admin.orders.status', $o->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn-warning btn-sm" title="إلغاء" onclick="return confirm('إلغاء الطلب؟')"><i class="fas fa-times"></i></button>
                </form>
                @endif
                <form action="{{ route('admin.orders.destroy', $o->id) }}" method="POST" onsubmit="return confirm('حذف نهائي؟')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div style="margin-top:25px">
    {{ $orders->links() }}
</div>

{{-- Details Modal --}}
<div class="modal-backdrop hidden" id="details-modal">
    <div class="modal" style="max-width:650px">
        <div class="modal-header">
            <h3>تفاصيل الطلب</h3>
            <button class="btn-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modal-body">
            {{-- Loaded via JS --}}
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function viewDetails(id) {
    const modal = document.getElementById('details-modal');
    const body = document.getElementById('modal-body');
    
    modal.classList.remove('hidden');
    body.innerHTML = '<div style="text-align:center; padding:40px"><i class="fas fa-spinner fa-spin fa-2x"></i><p>جاري تحميل التفاصيل...</p></div>';

    fetch(`{{ url('admin/orders') }}/${id}/details`)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحميل البيانات</div>';
        });
}
function closeModal() {
    document.getElementById('details-modal').classList.add('hidden');
}

async function printOrder(action, id) {
    const btn = event.target.closest('button');
    if (!btn) return;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const res = await fetch(`{{ url('admin/print') }}/${action}/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await res.json();
        if(typeof showToast === 'function') {
            showToast(data.message, data.success ? 'success' : 'danger');
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert('حدث خطأ في الاتصال بخدمة الطباعة');
    }
    
    btn.innerHTML = oldHtml;
    btn.disabled = false;
}
</script>
@endpush
