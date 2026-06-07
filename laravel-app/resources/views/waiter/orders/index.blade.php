@extends('layouts.admin')

@section('title', 'طلباتي')

@section('content')
<style>
    /* Exact Legacy CSS Variables */
    :root {
        --primary: #e67e22;
        --secondary: #2d3436;
        --success: #27ae60;
        --danger: #e74c3c;
        --warning: #f1c40f;
        --info: #3498db;
        --border: #f1f1f1;
        --text-muted: #95a5a6;
        --bg-card: #ffffff;
        --radius: 8px;
        --shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .orders-page-container {
        padding: 15px;
        background: #f8fafc;
        min-height: calc(100vh - 70px);
        direction: rtl;
    }

    /* Filter Card */
    .filter-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 12px 16px;
        margin-bottom: 16px;
    }
    .filter-flex {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-group { margin: 0; flex: 1; min-width: 200px; }
    .filter-group.small { flex: 0 0 150px; min-width: 150px; }
    .filter-label { display: block; font-weight: bold; font-size: 0.85rem; color: #444; margin-bottom: 6px; }
    .filter-control { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-weight: 700; font-size: 0.9rem; background: #fff; }

    /* Orders Grid */
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
        gap: 18px;
    }

    .order-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 16px;
        border-top: 4px solid var(--primary);
        display: flex;
        flex-direction: column;
        transition: 0.3s;
        height: 100%;
        position: relative;
    }
    .order-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

    .order-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border);
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    .order-number { font-weight: 900; font-size: 1.15rem; color: var(--primary); }
    .table-badge { background: var(--secondary); color: #fff; padding: 3px 15px; font-size: .85rem; border-radius: 20px; font-weight: 900; }
    
    .status-badge { padding: 4px 12px; border-radius: 50px; font-size: 0.72rem; font-weight: 900; }
    .badge-paid { background: #fff; color: #2ecc71; border: 1px solid #eee; }
    .badge-draft { background: #ebf5fb; color: #3498db; border: 1px solid #d4e6f1; }
    .badge-ready { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

    .time-delivered-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .82rem;
        color: var(--text-muted);
        margin-bottom: 12px;
        font-weight: 700;
    }

    .order-items-preview { flex: 1; }
    .order-item-line {
        display: flex;
        justify-content: space-between;
        font-size: .88rem;
        padding: 5px 0;
        border-bottom: 1px dashed var(--border);
        font-weight: 800;
        color: #333;
    }
    .order-item-line:last-child { border-bottom: none; }

    .order-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid var(--border);
        padding-top: 10px;
        margin-top: 10px;
    }
    .total-amount { color: var(--primary); font-weight: 950; font-size: 1.2rem; }

    .btn-group-legacy { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
    .btn-leg {
        padding: 6px 12px; border-radius: 5px; border: none; color: #fff;
        font-weight: 900; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px;
        transition: 0.2s;
    }
    .btn-print { background: #3498db; }
    .btn-deliver { background: #27ae60; }
    .btn-view { background: #bdc3c7; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; padding: 0; }
    .btn-add { background: #f1c40f; }
    .btn-send { background: #e67e22; }
    .btn-delete { background: #e74c3c; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; padding: 0; }

    .btn-leg:hover { filter: brightness(1.1); transform: scale(1.05); }

    .delivered-text { color: var(--success); font-weight: 900; display: flex; align-items: center; gap: 4px; }
</style>

<div class="orders-page-container">
    {{-- Filter Card --}}
    <div class="filter-card">
        <div class="filter-flex">
            <div class="filter-group">
                <label class="filter-label">🔍 بحث (رقم الطلب أو الطاولة)</label>
                <input type="text" id="filter-search" class="filter-control" placeholder="مثال: 5 أو ORD-..." onkeyup="applyFilters()">
            </div>
            <div class="filter-group small">
                <label class="filter-label">📅 التاريخ</label>
                <input type="date" id="filter-date" class="filter-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="filter-group small">
                <label class="filter-label">📊 الحالة</label>
                <select id="filter-status" class="filter-control" onchange="applyFilters()">
                    <option value="all">كل الحالات</option>
                    <option value="pending">معلّق (مسودة)</option>
                    <option value="preparing">قيد التحضير</option>
                    <option value="ready">جاهز للاستلام</option>
                    <option value="completed">تم التسليم</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Orders Grid --}}
    <div class="orders-grid" id="orders-grid">
        @forelse($orders as $order)
        <div class="order-card" data-status="{{ $order->status }}" data-search="#{{ $order->id }} طاولة {{ $order->table_number }}">
            <div class="order-card-header">
                <span class="order-number">#{{ $order->id }}</span>
                @if($order->table_number)
                <span class="table-badge">طاولة {{ $order->table_number }}</span>
                @endif
                
                @if($order->status == 'pending')
                <span class="status-badge badge-draft">أرسل للكاشير</span>
                @elseif($order->status == 'ready')
                <span class="status-badge badge-ready">جاهز</span>
                @else
                <span class="status-badge badge-paid">مدفوع</span>
                @endif
            </div>

            <div class="time-delivered-row">
                <span><i class="far fa-clock"></i> {{ \Carbon\Carbon::parse($order->created_at)->format('H:i:s Y/m/d') }}</span>
                @if($order->delivered_at || $order->status == 'completed')
                <span class="delivered-text"><i class="fas fa-check-double"></i> تم التسليم</span>
                @endif
            </div>

            <div class="order-items-preview">
                @foreach($order->items as $item)
                <div class="order-item-line">
                    <span style="color:#718096; font-weight:600">{{ number_format($item->unit_price * $item->quantity, 2) }}</span>
                    <span>[{{ $item->id }}] {{ $item->item_name_ar }} x{{ $item->quantity }}</span>
                </div>
                @endforeach
            </div>

            @if($order->notes)
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:8px"><i class="fas fa-sticky-note"></i> {{ $order->notes }}</div>
            @endif

            <div class="order-card-footer">
                <strong class="total-amount">{{ number_format($order->total, 2) }} ريال</strong>
                <div class="btn-group-legacy">
                    {{-- Standard Print & View --}}
                    <button class="btn-leg btn-info btn-sm-icon" title="طباعة الفاتورة"><i class="fas fa-print"></i></button>
                    <button class="btn-leg btn-view" title="التفاصيل"><i class="fas fa-eye"></i></button>

                    {{-- Draft Actions --}}
                    @if($order->status == 'pending')
                    <button class="btn-leg btn-add" title="إضافة أصناف جديدة"><i class="fas fa-plus"></i> إضافة</button>
                    <button class="btn-leg btn-send"><i class="fas fa-paper-plane"></i> إرسال</button>
                    <button class="btn-leg btn-delete"><i class="fas fa-trash"></i></button>
                    @endif

                    {{-- Deliver Action - Only if not delivered yet AND status is ready/paid/preparing --}}
                    @if(!$order->delivered_at && $order->status != 'completed' && $order->status != 'pending' && $order->status != 'cancelled')
                    <button class="btn-leg btn-success" onclick="markDelivered({{ $order->id }})"><i class="fas fa-hand-holding-heart"></i> تسليم</button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state" style="grid-column:1/-1; text-align:center; padding: 60px">
            <span class="icon" style="font-size:3rem; color:#ddd"><i class="fas fa-clipboard-list"></i></span>
            <h3 style="color:#aaa; margin-top:15px">لا توجد طلبات اليوم</h3>
        </div>
        @endforelse
    </div>
</div>

<script>
    function applyFilters() {
        const q = document.getElementById('filter-search').value.toLowerCase();
        const s = document.getElementById('filter-status').value;
        const cards = document.querySelectorAll('.order-card');

        cards.forEach(card => {
            const searchData = card.getAttribute('data-search').toLowerCase();
            const statusData = card.getAttribute('data-status');
            const matchesSearch = searchData.includes(q);
            const matchesStatus = (s === 'all' || statusData === s);
            card.style.display = (matchesSearch && matchesStatus) ? 'flex' : 'none';
        });
    }

    function markDelivered(id) {
        if(confirm('هل تم تسليم الطلب للزبون؟')) {
            fetch('{{ route("waiter.orders.deliver", ["order" => "__ID__"]) }}'.replace('__ID__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
            }).then(r => r.json()).then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    alert(res.message || 'حدث خطأ');
                }
            });
        }
    }
</script>
@endsection
