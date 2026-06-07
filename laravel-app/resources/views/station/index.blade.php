<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مراقبة التحضير | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #e67e22;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f1c40f;
            --info: #3498db;
            --bg: #0f0f1a;
            --card: #1a1a2e;
            --border: rgba(255,255,255,0.1);
        }
        body { font-family: 'Tajawal', sans-serif; background: var(--bg); color: #fff; direction: rtl; margin: 0; padding-bottom: 20px; overflow-x: hidden; }
        .topbar { background: var(--card); padding: 15px 25px; border-bottom: 2px solid var(--primary); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .topbar h1 { font-size: 1.2rem; margin: 0; color: var(--primary); font-weight: 800; }
        
        .orders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; padding: 20px; }
        .order-card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); border-top: 5px solid var(--primary); display: flex; flex-direction: column; overflow: hidden; transition: 0.3s; }
        .order-card.new { animation: slideIn 0.5s ease; border-top-color: var(--success); }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .order-header { padding: 12px 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); }
        .order-num { font-weight: 900; font-size: 1.2rem; color: var(--primary); }
        .table-badge { background: var(--primary); color: #fff; padding: 2px 10px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; }

        .items-list { flex: 1; padding: 10px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border); gap: 10px; }
        .item-row:last-child { border-bottom: none; }
        .item-info { flex: 1; }
        .item-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 2px; }
        .item-qty { color: var(--warning); font-weight: 800; font-size: 1.1rem; }
        .item-note { color: var(--danger); font-size: 0.8rem; margin-top: 4px; font-weight: 600; }

        .status-btn { 
            padding: 8px 15px; border-radius: 8px; border: none; cursor: pointer; 
            font-family: inherit; font-weight: 700; font-size: 0.8rem; transition: 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .status-pending { background: rgba(241, 196, 15, 0.1); color: var(--warning); border: 1px solid var(--warning); }
        .status-in_progress { background: rgba(52, 152, 219, 0.1); color: var(--info); border: 1px solid var(--info); }
        .status-ready { background: var(--success); color: #fff; }

        .btn-prep-all { width: 100%; padding: 12px; background: var(--primary); color: #fff; border: none; font-family: inherit; font-weight: 800; cursor: pointer; transition: 0.2s; font-size: 0.9rem; }
        .btn-prep-all:hover { filter: brightness(1.1); }
        
        .empty-state { grid-column: 1/-1; text-align: center; padding: 100px; color: rgba(255,255,255,0.1); }
        .empty-state i { font-size: 4rem; display: block; margin-bottom: 15px; }

        .clock { font-size: 1.2rem; font-weight: 700; color: #fff; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1><i class="fas fa-fire-burner"></i> مراقبة التحضير — {{ auth()->user()->name }}</h1>
        <div class="clock" id="clock">00:00:00</div>
        <div style="display:flex; gap:10px">
            <a href="{{ route('waiter.orders.create') }}" style="color:#fff; text-decoration:none; padding:8px 15px; border-radius:8px; border:1px solid var(--border); font-size:0.9rem"><i class="fas fa-plus"></i> طلب جديد</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf <button style="background:none; border:1px solid var(--danger); color:var(--danger); padding:8px 15px; border-radius:8px; cursor:pointer"><i class="fas fa-sign-out-alt"></i></button>
            </form>
        </div>
    </div>

    <div class="orders-grid" id="orders-grid">
        @forelse($orders as $order)
        <div class="order-card" id="order-{{ $order->id }}">
            <div class="order-header">
                <span class="order-num">#{{ $order->order_number }}</span>
                <span class="table-badge">طاولة {{ $order->table_number }}</span>
            </div>
            <div class="items-list">
                @foreach($order->items as $item)
                <div class="item-row" id="item-{{ $item->id }}">
                    <div class="item-info">
                        <div class="item-name">{{ $item->name_ar }}</div>
                        <small style="color:rgba(255,255,255,0.4)">{{ $item->category->name_ar }}</small>
                        @if($item->notes) <div class="item-note">📝 {{ $item->notes }}</div> @endif
                    </div>
                    <div class="item-qty">x{{ $item->quantity }}</div>
                    <button class="status-btn status-{{ $item->status }}" onclick="cycleStatus({{ $item->id }}, '{{ $item->status }}')">
                        @if($item->status == 'pending') <i class="fas fa-hourglass-start"></i> معلق
                        @elseif($item->status == 'in_progress') <i class="fas fa-fire"></i> يحضر
                        @else <i class="fas fa-check"></i> جاهز @endif
                    </button>
                </div>
                @endforeach
            </div>
            <button class="btn-prep-all" onclick="prepareAll({{ $order->id }})">
                <i class="fas fa-check-double"></i> تحضير الكل
            </button>
        </div>
        @empty
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h2>لا توجد طلبات جارية</h2>
            <p>ستظهر الطلبات الجديدة هنا تلقائياً</p>
        </div>
        @endforelse
    </div>

    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        // Clock
        setInterval(() => {
            document.getElementById('clock').textContent = new Date().toLocaleTimeString('ar-SA');
        }, 1000);

        async function cycleStatus(itemId, current) {
            let next = 'in_progress';
            if (current == 'in_progress') next = 'ready';
            if (current == 'ready') next = 'pending';

            const res = await fetch('/restaurant/laravel-app/public/api/orders/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ item_id: itemId, status: next })
            }).then(r => r.json());

            if (res.success) location.reload();
        }

        async function prepareAll(orderId) {
            const res = await fetch('/restaurant/laravel-app/public/api/orders/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ order_id: orderId, status: 'ready' })
            }).then(r => r.json());

            if (res.success) location.reload();
        }

        // Real-time Updates
        const sse = new EventSource('/restaurant/laravel-app/public/api/sse');
        sse.addEventListener('new_order', () => location.reload());
        sse.addEventListener('station_order', () => location.reload());
    </script>
</body>
</html>
