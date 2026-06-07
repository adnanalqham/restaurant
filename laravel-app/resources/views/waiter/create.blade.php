<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب جديد | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#e67e22; --bg:#f0f2f5; --card:#fff; --border:#e0e0e0; --text:#2c3e50; --success:#27ae60; }
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Tajawal',sans-serif; background:var(--bg); color:var(--text); direction:rtl; }

        .pos-layout { display:grid; grid-template-columns:1fr 360px; height:100vh; overflow:hidden; }

        /* Left - Menu */
        .menu-panel { display:flex; flex-direction:column; overflow:hidden; }
        .menu-header { padding:12px 16px; background:var(--card); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .menu-header h2 { font-size:1rem; flex:1; }
        .cats-bar { display:flex; gap:8px; padding:12px 16px; background:var(--card); border-bottom:1px solid var(--border); overflow-x:auto; }
        .cat-btn { padding:7px 18px; border:2px solid var(--border); border-radius:20px; background:transparent; cursor:pointer; font-family:'Tajawal',sans-serif; font-size:.85rem; white-space:nowrap; transition:all .2s; }
        .cat-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .items-grid { flex:1; overflow-y:auto; padding:16px; display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:12px; }
        .item-card { background:var(--card); border-radius:10px; overflow:hidden; cursor:pointer; transition:all .2s; box-shadow:0 2px 8px rgba(0,0,0,.06); border:2px solid transparent; }
        .item-card:hover { transform:translateY(-2px); border-color:var(--primary); box-shadow:0 6px 20px rgba(230,126,34,.2); }
        .item-img { width:100%; height:100px; object-fit:cover; background:#f5f5f5; }
        .item-img-ph { width:100%; height:100px; background:linear-gradient(135deg,#f5f7fa,#c3cfe2); display:flex; align-items:center; justify-content:center; font-size:2rem; color:#bbb; }
        .item-info { padding:10px; }
        .item-name { font-weight:700; font-size:.85rem; }
        .item-price { color:var(--primary); font-weight:700; font-size:.9rem; margin-top:4px; }

        /* Right - Cart */
        .cart-panel { background:var(--card); border-right:1px solid var(--border); display:flex; flex-direction:column; }
        .cart-header { padding:16px; border-bottom:1px solid var(--border); }
        .cart-header h3 { font-size:1rem; font-weight:700; }
        .table-select { margin-top:10px; display:flex; gap:8px; align-items:center; }
        .table-select select, .table-select input { flex:1; padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-family:'Tajawal',sans-serif; }
        .cart-items { flex:1; overflow-y:auto; padding:12px; }
        .cart-item { display:flex; align-items:center; gap:10px; padding:10px; border-radius:8px; background:#f8f9fa; margin-bottom:8px; }
        .cart-item-name { flex:1; font-size:.9rem; font-weight:600; }
        .cart-item-price { color:var(--primary); font-weight:700; font-size:.9rem; }
        .qty-ctrl { display:flex; align-items:center; gap:6px; }
        .qty-btn { width:26px; height:26px; border:1px solid var(--border); border-radius:6px; background:var(--card); cursor:pointer; font-size:.9rem; display:flex; align-items:center; justify-content:center; }
        .qty-val { width:28px; text-align:center; font-weight:700; }
        .remove-btn { color:#e74c3c; background:none; border:none; cursor:pointer; font-size:1rem; }
        .cart-footer { padding:16px; border-top:1px solid var(--border); }
        .total-row { display:flex; justify-content:space-between; margin-bottom:12px; }
        .total-row .label { color:#666; }
        .total-row .value { font-weight:700; font-size:1.1rem; }
        .btn-submit { width:100%; padding:14px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-family:'Tajawal',sans-serif; font-size:1.1rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .btn-submit:hover { background:#d35400; }
        .btn-submit:disabled { opacity:.5; cursor:not-allowed; }

        /* Top bar */
        .topbar { display:flex; align-items:center; gap:12px; }
        .btn-nav { padding:7px 14px; border:1px solid var(--border); border-radius:8px; background:transparent; cursor:pointer; font-family:'Tajawal',sans-serif; text-decoration:none; color:var(--text); font-size:.85rem; }

        /* Toast */
        .toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#2c3e50; color:#fff; padding:12px 24px; border-radius:10px; font-size:.9rem; opacity:0; transition:opacity .3s; z-index:999; pointer-events:none; }
        .toast.show { opacity:1; }

        @media(max-width:768px){
            .pos-layout { grid-template-columns:1fr; grid-template-rows:1fr 50vh; }
            .cart-panel { border-right:none; border-top:1px solid var(--border); }
        }
    </style>
</head>
<body>
<div class="pos-layout">

    <!-- ===== Menu Panel ===== -->
    <div class="menu-panel">
        <div class="menu-header">
            <div class="topbar">
                <a href="{{ route('waiter.orders.index') }}" class="btn-nav"><i class="fas fa-list"></i> طلباتي</a>
                <a href="{{ route('admin.dashboard') }}" class="btn-nav"><i class="fas fa-tachometer-alt"></i> الإدارة</a>
            </div>
            <h2>{{ config('app.name') }}</h2>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf <button class="btn-nav" style="color:#e74c3c"><i class="fas fa-sign-out-alt"></i></button>
            </form>
        </div>

        <!-- Categories -->
        <div class="cats-bar">
            <button class="cat-btn active" data-cat="all">الكل</button>
            @foreach($categories as $cat)
            <button class="cat-btn" data-cat="{{ $cat->id }}">{{ $cat->name_ar }}</button>
            @endforeach
        </div>

        <!-- Items Grid -->
        <div class="items-grid" id="itemsGrid">
            @foreach($items as $catId => $catItems)
            @foreach($catItems as $item)
            <div class="item-card" data-cat="{{ $item->category_id }}"
                 onclick="addToCart({id:{{ $item->id }}, category_id:{{ $item->category_id }}, name_ar:'{{ addslashes($item->name_ar) }}', price:{{ $item->price }}})">
                @if($item->image)
                <img src="{{ asset('storage/uploads/'.$item->image) }}" class="item-img" alt="{{ $item->name_ar }}">
                @else
                <div class="item-img-ph"><i class="fas fa-hamburger"></i></div>
                @endif
                <div class="item-info">
                    <div class="item-name">{{ $item->name_ar }}</div>
                    <div class="item-price">{{ number_format($item->price, 2) }}</div>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
    </div>

    <!-- ===== Cart Panel ===== -->
    <div class="cart-panel">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> سلة الطلب</h3>
            <div class="table-select">
                <select id="tableNumber">
                    <option value="">-- الطاولة --</option>
                    @for($t = 1; $t <= $tables; $t++)
                    <option value="{{ $t }}">طاولة {{ $t }}</option>
                    @endfor
                    <option value="takeaway">خارجي / تيك أواي</option>
                </select>
            </div>
            <div class="table-select" style="margin-top:8px">
                <input type="text" id="orderNotes" placeholder="ملاحظات على الطلب...">
            </div>
        </div>

        <div class="cart-items" id="cartItems">
            <p style="text-align:center; color:#bbb; margin-top:40px">
                <i class="fas fa-cart-plus" style="font-size:2rem"></i><br>اضغط على الصنف لإضافته
            </p>
        </div>

        <div class="cart-footer">
            <div class="total-row">
                <span class="label">عدد الأصناف:</span>
                <span id="itemCount">0</span>
            </div>
            <div class="total-row">
                <span class="label">الإجمالي:</span>
                <span class="value" id="totalAmount">0.00</span>
            </div>
            <button class="btn-submit" id="submitBtn" disabled onclick="submitOrder()">
                <i class="fas fa-paper-plane"></i> إرسال الطلب
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let cart = [];

// Category filter
document.querySelectorAll('.cat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        document.querySelectorAll('.item-card').forEach(card => {
            card.style.display = (cat === 'all' || card.dataset.cat === cat) ? '' : 'none';
        });
    });
});

function addToCart(item) {
    const idx = cart.findIndex(i => i.id === item.id);
    if (idx > -1) { cart[idx].qty++; }
    else { cart.push({ ...item, qty: 1, notes: '' }); }
    renderCart();
    showToast('تمت إضافة ' + item.name_ar);
}

function renderCart() {
    const el = document.getElementById('cartItems');
    if (cart.length === 0) {
        el.innerHTML = '<p style="text-align:center;color:#bbb;margin-top:40px"><i class="fas fa-cart-plus" style="font-size:2rem"></i><br>اضغط على الصنف لإضافته</p>';
        document.getElementById('itemCount').textContent = '0';
        document.getElementById('totalAmount').textContent = '0.00';
        document.getElementById('submitBtn').disabled = true;
        return;
    }

    let html = '', total = 0, count = 0;
    cart.forEach((item, i) => {
        const lineTotal = item.price * item.qty;
        total += lineTotal; count += item.qty;
        html += `<div class="cart-item">
            <div style="flex:1">
                <div class="cart-item-name">${item.name_ar}</div>
                <div class="cart-item-price">${lineTotal.toFixed(2)}</div>
                <input type="text" placeholder="ملاحظة..." value="${item.notes}"
                       oninput="cart[${i}].notes=this.value"
                       style="font-size:.8rem;border:none;border-bottom:1px solid var(--border);background:transparent;width:100%;margin-top:4px;font-family:Tajawal,sans-serif">
            </div>
            <div class="qty-ctrl">
                <button class="qty-btn" onclick="changeQty(${i}, -1)">−</button>
                <span class="qty-val">${item.qty}</span>
                <button class="qty-btn" onclick="changeQty(${i}, 1)">+</button>
            </div>
            <button class="remove-btn" onclick="removeItem(${i})"><i class="fas fa-times"></i></button>
        </div>`;
    });

    el.innerHTML = html;
    document.getElementById('itemCount').textContent = count;
    document.getElementById('totalAmount').textContent = total.toFixed(2);
    document.getElementById('submitBtn').disabled = false;
}

function changeQty(i, delta) {
    cart[i].qty += delta;
    if (cart[i].qty <= 0) cart.splice(i, 1);
    renderCart();
}
function removeItem(i) { cart.splice(i, 1); renderCart(); }

async function submitOrder() {
    const table = document.getElementById('tableNumber').value;
    if (!table) { showToast('يرجى اختيار الطاولة!', true); return; }
    if (cart.length === 0) return;

    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'جاري الإرسال...';

    const items = cart.map(i => ({ id: i.id, category_id: i.category_id, name_ar: i.name_ar, price: i.price, qty: i.qty, notes: i.notes }));

    const res = await fetch('{{ route("waiter.orders.store") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ table_number: table, notes: document.getElementById('orderNotes').value, items })
    }).then(r => r.json());

    if (res.success) {
        cart = [];
        renderCart();
        document.getElementById('tableNumber').value = '';
        document.getElementById('orderNotes').value = '';
        showToast('✅ تم إرسال الطلب #' + res.order_number);
    } else {
        showToast('خطأ: ' + res.message, true);
    }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال الطلب';
}

function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = isError ? '#e74c3c' : '#2c3e50';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>
