@extends('layouts.admin')

@section('title', 'مراقبة التحضير (المطبخ)')

@section('content')
<style>
    /* Legacy Station Styles ported for 1:1 Parity */
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

    .station-container {
        padding: 20px;
        background: #f8fafc;
        min-height: calc(100vh - 70px);
        direction: rtl;
    }

    .station-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .station-order {
        background: var(--bg-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border-top: 5px solid var(--primary);
        padding: 16px;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        min-height: 250px;
        display: flex;
        flex-direction: column;
    }

    .station-order.new {
        border-top-color: var(--success);
        animation: slideInUp .5s ease backwards, pulse-new 2s infinite;
    }

    @keyframes slideInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes pulse-new {
        0%, 100% { box-shadow: var(--shadow); }
        50% { box-shadow: 0 0 0 5px rgba(39, 174, 96, 0.3); }
    }

    .item-station-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
        gap: 8px;
    }
    .item-station-row:last-child { border-bottom: none; }

    .item-status-btn {
        padding: 6px 14px;
        border-radius: 20px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: .82rem;
        transition: all .2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .status-pending { background: #fef9e7; color: var(--warning); }
    .status-in_progress { background: #eaf2ff; color: var(--info); }
    .status-ready { background: #eafaf1; color: var(--success); }

    .table-badge { background: var(--secondary); color: #fff; padding: 4px 12px; font-size: .85rem; border-radius: 20px; font-weight: bold; }

    /* Print Banner */
    #print-banner {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 20px;
        right: 20px;
        z-index: 9999;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
        text-align: center;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
        cursor: pointer;
        animation: bannerPulse 1.5s infinite;
    }
    @keyframes bannerPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }

    .badge-printed { background:#d4edda; color:#155724; font-size:.75rem; padding:3px 10px; border-radius:20px; font-weight:600; }
    .badge-not-printed { background:#fff3cd; color:#856404; font-size:.75rem; padding:3px 10px; border-radius:20px; font-weight:600; }
</style>

<div class="station-container">
    <div class="station-header-row">
        <div style="display:flex; gap:15px; align-items:center">
            <h2 style="margin:0"><i class="fas fa-utensils-spoon"></i> مراقبة التحضير</h2>
            <select id="view-mode" class="form-control" style="width:auto; border-radius:8px" onchange="loadOrders()">
                <option value="active">🍽️ بدأ التحضير</option>
                <option value="ready">✅ أصناف جاهزة للاستلام</option>
                <option value="delivered">📦 طلبات تم تسليمها</option>
            </select>
        </div>
        
        <div style="display:flex; align-items:center; gap:12px">
            <span id="active-count" class="badge badge-warning" style="font-size:.9rem; padding:6px 14px">0 صنف نشط</span>
            <div style="display:flex; align-items:center; gap:10px; background:#fff; padding:6px 14px; border-radius:20px; border:1px solid var(--border); box-shadow:var(--shadow)">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.85rem; margin:0; font-weight:600">
                    <input type="checkbox" id="auto-print-toggle" style="width:16px; height:16px" onchange="toggleAutoPrint(this.checked)">
                    <i class="fas fa-print"></i> طباعة تلقائية
                </label>
                <button class="btn btn-sm btn-outline-primary" onclick="loadOrders()" style="border:none; padding:0 5px"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>
    </div>

    <div id="orders-container" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(330px, 1fr)); gap:20px">
        {{-- Filled by JS --}}
        <div style="grid-column:1/-1; text-align:center; padding:100px; color:#ccc">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>جاري تحميل الطلبات...</p>
        </div>
    </div>
</div>

{{-- Tap to Print Banner --}}
<div id="print-banner" onclick="triggerBannerPrint()">
    <h3 style="margin:0">🖨️ اضغط هنا للطباعة</h3>
    <p id="print-banner-info" style="margin:5px 0 0; opacity:0.9">طلب جديد وصل — اضغط لطباعته على الطابعة</p>
</div>

@endsection

@push('scripts')
<script>
    let stationOrders = [];
    const allowedCats = @json($allowedCats);

    async function loadOrders() {
        const mode = document.getElementById('view-mode').value;
        const container = document.getElementById('orders-container');
        
        try {
            const res = await fetch(`{{ url('api/orders') }}?status=${mode}`).then(r => r.json());
            if (res.success) {
                stationOrders = res.data.map(o => {
                    o.items = (o.items || []).filter(i => allowedCats.length === 0 || allowedCats.includes(i.category_id));
                    return o;
                }).filter(o => o.items.length > 0);

                // Update count
                const totalItems = stationOrders.reduce((sum, o) => sum + o.items.length, 0);
                const labels = { active: ' صنف نشط', ready: ' صنف جاهز', delivered: ' صنف تم تسليمه' };
                document.getElementById('active-count').textContent = totalItems + (labels[mode] || '');

                renderOrders();
            }
        } catch(e) { console.error(e); }
    }

    function renderOrders() {
        const container = document.getElementById('orders-container');
        const viewMode = document.getElementById('view-mode').value;

        if (stationOrders.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="grid-column:1/-1; text-align:center; padding: 100px; color:#ccc">
                    <i class="fas fa-clipboard-check fa-4x" style="opacity:0.2"></i>
                    <h3>لا توجد طلبات حالياً</h3>
                    <p>ستظهر الطلبات الجديدة هنا تلقائياً</p>
                </div>`;
            return;
        }

        container.innerHTML = stationOrders.map((o, idx) => {
            const printedKey = 'POS_PRINTED_ORDERS';
            const printedList = JSON.parse(localStorage.getItem(printedKey) || '[]');
            const isPrinted = printedList.includes(String(o.id));

            const itemsHtml = o.items.map(i => `
                <div class="item-station-row" id="item-row-${i.id}">
                    <div>
                        <div style="font-weight:800; font-size:1rem; color:#333">${i.item_name_ar}</div>
                        <small style="color:var(--text-muted); font-weight:700">الكمية: <strong style="color:var(--primary)">${i.quantity}</strong> ${i.size_name ? `| ${i.size_name}` : ''}</small>
                        ${i.notes ? `<div style="font-size:.75rem; color:var(--warning); font-weight:700"><i class="fas fa-edit"></i> ${i.notes}</div>` : ''}
                    </div>
                    <div style="display:flex; gap:8px">
                        <button class="item-status-btn status-${i.status || 'pending'}" ${viewMode === 'delivered' ? 'disabled' : ''} onclick="cycleItemStatus(${i.id}, '${i.status || 'pending'}', this)">
                            ${statusLabel(i.status || 'pending')}
                        </button>
                        ${(viewMode !== 'delivered' && (i.status || 'pending') === 'pending') ? `
                        <button class="btn btn-danger btn-sm" style="width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center" onclick="rejectItem(${i.id})">
                            <i class="fas fa-times"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');

            return `
                <div class="station-order" id="station-order-${o.id}">
                    <div style="border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px">
                            <strong style="color: var(--primary); font-size: 1.15rem; font-weight:950">#${o.order_number}</strong>
                            ${o.table_number ? `<span class="table-badge">طاولة ${o.table_number}</span>` : ''}
                        </div>
                        <div style="font-size: .85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px">
                            <i class="fas fa-user-tag"></i> المباشر: <span style="color:#333">${o.waiter_name || 'غير محدد'}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
                            <div style="display:flex; flex-direction:column; gap:4px">
                                <div style="font-size: .82rem; color: var(--text-muted); font-weight:700">
                                    <i class="far fa-clock"></i> وصول: <strong style="color:#333">${new Date(o.created_at).toLocaleTimeString('ar-YE', {hour:'2-digit', minute:'2-digit'})}</strong>
                                </div>
                                ${o.ready_at ? `
                                <div style="font-size: .82rem; color: var(--success); font-weight:700">
                                    <i class="fas fa-check-circle"></i> خروج: <strong style="color:var(--success)">${new Date(o.ready_at).toLocaleTimeString('ar-YE', {hour:'2-digit', minute:'2-digit'})}</strong>
                                </div>
                                ` : ''}
                            </div>
                            <div style="display:flex; align-items:center; gap:8px">
                                ${isPrinted ? '<span class="badge-printed"><i class="fas fa-print"></i> مطبوع</span>' : '<span class="badge-not-printed"><i class="fas fa-print"></i> لم يطبع</span>'}
                                <button onclick="printKitchen(${o.id})" class="btn btn-sm btn-outline-primary" style="padding:4px 12px; font-size:.75rem; border-radius:20px; font-weight:800"><i class="fas fa-print"></i> طباعة</button>
                            </div>
                        </div>
                    </div>
                    
                    ${o.notes ? `<div style="background:#fff3cd; color:#856404; padding:10px 15px; margin-bottom:12px; font-size:.9rem; font-weight:800; border-radius:8px; border-right:5px solid #ffeeba"><i class="fas fa-sticky-note"></i> ${o.notes}</div>` : ''}
                    
                    <div style="flex:1">
                        ${itemsHtml}
                    </div>

                    ${viewMode !== 'delivered' ? `
                    <div style="margin-top:15px; border-top:1px solid var(--border); padding-top:15px; display:flex; gap:10px">
                        <button onclick="prepareAll(${o.id}, 'in_progress', this)" class="btn btn-sm" style="flex:1; background:var(--info); color:#fff; border-radius:15px; font-weight:900"><i class="fas fa-fire-burner"></i> يحضر الكل</button>
                        <button onclick="prepareAll(${o.id}, 'ready', this)" class="btn btn-sm" style="flex:1; background:var(--success); color:#fff; border-radius:15px; font-weight:900"><i class="fas fa-check-double"></i> تجهيز الكل</button>
                    </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function statusLabel(s) {
        const map = { pending: '<i class="fas fa-hourglass-start"></i> معلّق', in_progress: '<i class="fas fa-fire-burner"></i> يُحضَّر', ready: '<i class="fas fa-check-circle"></i> جاهز للاستلام', served: '<i class="fas fa-utensils"></i> قُدِّم' };
        return map[s] || s;
    }

    async function cycleItemStatus(itemId, current, btn) {
        const nextMap = { pending: 'in_progress', in_progress: 'ready', ready: 'pending' };
        const next = nextMap[current] || 'ready';
        
        btn.disabled = true;
        const res = await fetch(`{{ url('api/items/update-status') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ id: itemId, status: next })
        }).then(r => r.json());

        if (res.success) {
            loadOrders();
        } else {
            alert(res.message);
            btn.disabled = false;
        }
    }

    async function prepareAll(orderId, status, btn) {
        btn.disabled = true;
        const res = await fetch(`{{ url('api/orders/prepare-all') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ order_id: orderId, status: status })
        }).then(r => r.json());

        if (res.success) {
            loadOrders();
        } else {
            alert(res.message);
            btn.disabled = false;
        }
    }

    async function rejectItem(itemId) {
        const reason = prompt('سبب رفض الصنف:');
        if (!reason) return;
        const res = await fetch(`{{ url('api/items/update-status') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ id: itemId, status: 'rejected', rejection_reason: reason })
        }).then(r => r.json());
        if (res.success) loadOrders();
    }

    function printKitchen(id) {
        fetch(`{{ url('admin/print/kitchen') }}/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(r => r.json()).then(res => {
            // Mark as printed locally
            const printedKey = 'POS_PRINTED_ORDERS';
            let list = JSON.parse(localStorage.getItem(printedKey) || '[]');
            if(!list.includes(String(id))) list.push(String(id));
            localStorage.setItem(printedKey, JSON.stringify(list));
            loadOrders();
        });
    }

    function toggleAutoPrint(enabled) {
        localStorage.setItem('pos_station_autoprint', enabled ? '1' : '0');
    }

    // SSE Integration
    const sse = new EventSource(`{{ url('api/sse') }}`);
    sse.addEventListener('new_order', (e) => {
        const data = JSON.parse(e.data);
        showPrintBanner(data.order_id, data.order_number);
        loadOrders();
    });
    sse.addEventListener('order_status_changed', loadOrders);
    
    function showPrintBanner(id, num) {
        const banner = document.getElementById('print-banner');
        document.getElementById('print-banner-info').textContent = `طلب جديد #${num} — اضغط لطباعته`;
        banner.style.display = 'block';
        banner.dataset.id = id;
        setTimeout(() => banner.style.display = 'none', 30000);
    }
    
    function triggerBannerPrint() {
        const id = document.getElementById('print-banner').dataset.id;
        printKitchen(id);
        document.getElementById('print-banner').style.display = 'none';
    }

    // Initial load
    loadOrders();
    setInterval(loadOrders, 30000);
</script>
@endpush
