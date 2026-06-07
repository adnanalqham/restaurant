<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الكاشير | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#e67e22;--success:#27ae60;--danger:#e74c3c;--warning:#f39c12;--info:#3498db;--border:#e0e0e0;--bg:#f0f2f5;}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Tajawal',sans-serif;background:var(--bg);direction:rtl;}
        .topbar{background:#1a1a2e;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:12px;}
        .topbar h1{flex:1;font-size:1rem;}
        .stat-mini{background:rgba(255,255,255,.1);padding:6px 14px;border-radius:8px;font-size:.85rem;}
        .btn-sm-nav{padding:7px 14px;border:1px solid rgba(255,255,255,.3);border-radius:8px;background:transparent;color:#fff;cursor:pointer;font-family:'Tajawal',sans-serif;font-size:.85rem;text-decoration:none;}
        .orders-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:16px;}
        .order-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;transition:all .3s;}
        .order-card-header{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);}
        .order-num{font-weight:800;font-size:1.1rem;}
        .order-table{color:#666;font-size:.85rem;}
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;}
        .badge-warning{background:rgba(243,156,18,.12);color:var(--warning);}
        .badge-info{background:rgba(52,152,219,.12);color:var(--info);}
        .badge-success{background:rgba(39,174,96,.12);color:var(--success);}
        .badge-primary{background:rgba(230,126,34,.12);color:var(--primary);}
        .badge-danger{background:rgba(231,76,60,.12);color:var(--danger);}
        .order-items{padding:12px 16px;max-height:160px;overflow-y:auto;}
        .order-item-row{display:flex;justify-content:space-between;padding:4px 0;font-size:.875rem;border-bottom:1px solid #f5f5f5;}
        .order-footer{padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
        .order-total{font-weight:800;font-size:1.2rem;color:var(--primary);}
        .btn-pay{padding:10px 20px;background:var(--success);color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:'Tajawal',sans-serif;font-weight:700;font-size:.95rem;transition:all .2s;}
        .btn-pay:hover{background:#219a52;transform:translateY(-1px);}
        .btn-cancel{padding:8px 14px;background:transparent;color:var(--danger);border:1px solid var(--danger);border-radius:8px;cursor:pointer;font-family:'Tajawal',sans-serif;font-size:.85rem;}
        .empty-state{text-align:center;padding:80px 20px;color:#bbb;}
        .empty-state i{font-size:3rem;margin-bottom:16px;}
        /* Discount form */
        .discount-row{padding:8px 16px;display:flex;gap:8px;align-items:center;background:#f8f9fa;}
        .discount-row select,.discount-row input{padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-family:'Tajawal',sans-serif;font-size:.8rem;}
        .discount-row input{width:80px;}
        .btn-discount{padding:5px 12px;background:var(--info);color:#fff;border:none;border-radius:6px;cursor:pointer;font-family:'Tajawal',sans-serif;font-size:.8rem;}
    </style>
</head>
<body>
<div class="topbar">
    <h1><i class="fas fa-cash-register"></i> مراقبة الطلبات — {{ auth()->user()->name }}</h1>
    <div class="stat-mini">اليوم: {{ $todayCount }} طلب</div>
    <div class="stat-mini">الإيراد: {{ number_format($todayRevenue, 0) }}</div>
    <a href="{{ route('waiter.orders.create') }}" class="btn-sm-nav"><i class="fas fa-plus"></i> طلب جديد</a>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button class="btn-sm-nav"><i class="fas fa-sign-out-alt"></i></button>
    </form>
</div>

<div class="orders-grid" id="ordersGrid">
@forelse($orders as $order)
@php
    $statusMap = ['pending'=>['warning','معلق'],'sent_to_cashier'=>['primary','للكاشير'],'confirmed'=>['info','مؤكد'],'in_progress'=>['warning','جاري'],'ready'=>['success','جاهز'],'paid'=>['success','مدفوع']];
    $s = $statusMap[$order->status] ?? ['primary',$order->status];
@endphp
<div class="order-card" id="order-{{ $order->id }}">
    <div class="order-card-header">
        <div>
            <div class="order-num">#{{ $order->order_number }}</div>
            <div class="order-table"><i class="fas fa-chair"></i> {{ $order->table_number }} &nbsp;|&nbsp; {{ $order->waiter_name }}</div>
        </div>
        <span class="badge badge-{{ $s[0] }}">{{ $s[1] }}</span>
    </div>
    <div class="order-items">
        @foreach($order->items as $item)
        <div class="order-item-row">
            <span>{{ $item->name_ar }} x{{ $item->quantity }}</span>
            <span>{{ number_format($item->price * $item->quantity, 2) }}</span>
        </div>
        @endforeach
    </div>
    @if(!in_array($order->status, ['paid','cancelled']))
    <div class="discount-row">
        <select id="dtype-{{ $order->id }}">
            <option value="percent">%</option>
            <option value="fixed">مبلغ ثابت</option>
        </select>
        <input type="number" id="dval-{{ $order->id }}" placeholder="قيمة الخصم" min="0" step="0.01">
        <button class="btn-discount" onclick="applyDiscount({{ $order->id }})">خصم</button>
    </div>
    @endif
    <div class="order-footer">
        <div class="order-total">{{ number_format($order->total, 2) }} <small style="font-size:.7rem;color:#999">ريال</small></div>
        <div style="display:flex;gap:8px">
            @if(!in_array($order->status, ['paid','cancelled']))
            <button class="btn-cancel" onclick="cancelOrder({{ $order->id }})">إلغاء</button>
            <button class="btn-pay" onclick="payOrder({{ $order->id }}, {{ $order->total }})"><i class="fas fa-check"></i> دفع</button>
            @endif
        </div>
    </div>
</div>
@empty
<div class="empty-state" style="grid-column:1/-1">
    <i class="fas fa-check-circle" style="color:var(--success)"></i>
    <p>لا توجد طلبات نشطة حالياً</p>
</div>
@endforelse
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function payOrder(id, total) {
    if (!confirm('تأكيد استلام الدفع للطلب؟')) return;
    const res = await fetch('/restaurant/laravel-app/public/api/orders/update-status', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ order_id: id, status: 'paid', total })
    }).then(r => r.json());
    if (res.success) { document.getElementById('order-'+id)?.remove(); }
    else alert(res.message);
}

async function cancelOrder(id) {
    if (!confirm('إلغاء الطلب؟')) return;
    const res = await fetch('/restaurant/laravel-app/public/api/orders/update-status', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ order_id: id, status: 'cancelled' })
    }).then(r => r.json());
    if (res.success) { document.getElementById('order-'+id)?.remove(); }
    else alert(res.message);
}

async function applyDiscount(id) {
    const type  = document.getElementById('dtype-'+id).value;
    const value = parseFloat(document.getElementById('dval-'+id).value);
    if (!value || value <= 0) { alert('أدخل قيمة الخصم'); return; }
    const res = await fetch('/restaurant/laravel-app/public/api/orders/apply-discount', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ order_id: id, discount_type: type, discount_value: value })
    }).then(r => r.json());
    if (res.success) { location.reload(); }
    else alert(res.message);
}

// SSE for real-time updates
const sse = new EventSource('/restaurant/laravel-app/public/api/sse');
sse.addEventListener('new_order', () => location.reload());
sse.addEventListener('order_status_changed', () => location.reload());
</script>
</body>
</html>
