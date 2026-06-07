<?php
require_once __DIR__ . '/_layout.php';
stationHeader('مراقبة التحضير', 'index');
?>
<style>
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

  .station-order.fade-out {
    transform: scale(0.9);
    opacity: 0;
    pointer-events: none;
  }

  @keyframes slideInUp {
    from {
      opacity: 0;
      transform: translateY(20px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  @keyframes pulse-new {

    0%,
    100% {
      box-shadow: var(--shadow);
    }

    50% {
      box-shadow: 0 0 0 5px rgba(39, 174, 96, 0.3);
    }
  }

  .item-station-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    gap: 8px;
  }

  .item-station-row:last-child {
    border-bottom: none;
  }

  .item-status-btn {
    padding: 6px 14px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-family: 'Tajawal', sans-serif;
    font-weight: 600;
    font-size: .82rem;
    transition: all .2s;
  }

  .status-pending {
    background: #fef9e7;
    color: var(--warning);
  }

  .status-in_progress {
    background: #eaf2ff;
    color: var(--info);
  }

  .status-ready {
    background: #eafaf1;
    color: var(--success);
  }

  /* ── SEARCH BAR ── */
  .station-search-wrap {
    position: relative;
    flex: 1;
    max-width: 420px;
  }
  .station-search-wrap input {
    width: 100%;
    padding: 10px 44px 10px 40px;
    border-radius: 25px;
    border: 2px solid var(--border);
    background: var(--bg-card);
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    font-size: .95rem;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  .station-search-wrap input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 230,126,34), .15);
  }
  .station-search-wrap .srch-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: .95rem;
    pointer-events: none;
  }
  .station-search-wrap .srch-clear {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    cursor: pointer;
    font-size: .85rem;
    display: none;
    background: none;
    border: none;
    padding: 2px 4px;
  }
  .station-search-wrap input:not(:placeholder-shown) ~ .srch-clear {
    display: block;
  }
  .search-highlight {
    background: #fff3cd;
    color: #856404;
    border-radius: 3px;
    padding: 0 2px;
  }
  .no-search-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
  }
  .no-search-results .icon { font-size: 2.5rem; margin-bottom: 12px; }
  /* TAP TO PRINT BANNER */
  #print-banner {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: #fff;
    text-align: center;
    padding: 20px 16px;
    box-shadow: 0 -4px 30px rgba(231, 76, 60, 0.6);
    cursor: pointer;
    animation: bannerPulse 1s infinite ease-in-out;
    -webkit-tap-highlight-color: transparent;
  }

  #print-banner h3 {
    margin: 0 0 6px;
    font-size: 1.4rem;
  }

  #print-banner p {
    margin: 0;
    font-size: .9rem;
    opacity: .85;
  }

  #print-banner .dismiss {
    position: absolute;
    top: 8px;
    left: 12px;
    font-size: 1.2rem;
    opacity: .6;
  }

  @keyframes bannerPulse {

    0%,
    100% {
      box-shadow: 0 -4px 30px rgba(231, 76, 60, 0.6);
      transform: translateY(0);
    }

    50% {
      box-shadow: 0 -4px 50px rgba(231, 76, 60, 0.9);
      transform: translateY(-3px);
    }
  }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
  <div class="flex gap-12">
    <h2 style="margin:0"><i class="fas fa-utensils-spoon"></i> مراقبة التحضير</h2>
    <select id="view-mode" class="form-control" style="width:auto" onchange="loadOrders()">
      <option value="active">🍽️ بدأ التحضير</option>
      <option value="ready">✅ أصناف جاهزة للاستلام</option>
      <option value="delivered">📦 طلبات تم تسليمها</option>
    </select>
  </div>
  <?php if (!empty($allowedCats)): ?>
    <div style="font-size:.85rem;color:var(--text-muted)">
      <i class="fas fa-lock"></i> مخصص لفئات محددة
    </div>
  <?php else: ?>
    <div style="font-size:.85rem;color:var(--text-muted)">
      <i class="fas fa-globe"></i> يرى جميع الفئات
    </div>
  <?php endif; ?>
  <div
    style="display:flex;align-items:center;gap:10px;background:#f8f9fa;padding:6px 14px;border-radius:20px;border:1px solid var(--border)">
    <button id="sound-test-btn" onclick="unlockAudio()" class="btn btn-sm"
      style="background:none;border:none;color:var(--primary);cursor:pointer;padding:0" title="اختبار الصوت">
      <i class="fas fa-bell"></i>
    </button>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;margin:0;font-weight:600">
      <input type="checkbox" id="auto-print-toggle" style="width:16px;height:16px;accent-color:var(--primary)"
        onchange="toggleAutoPrint(this.checked)">
      <i class="fas fa-print"></i> طباعة تلقائية
    </label>
  </div>
</div>

<!-- ── شريط البحث ── -->
<div style="margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <div class="station-search-wrap">
    <i class="fas fa-search srch-icon"></i>
    <input type="text" id="station-search"
      placeholder="🔍 بحث برقم الطاولة، رقم الفاتورة، أو اسم الصنف..."
      oninput="filterOrders(this.value)"
      autocomplete="off">
    <button class="srch-clear" onclick="clearSearch()" title="مسح البحث">✕</button>
  </div>
  <div id="search-count" style="font-size:.85rem;color:var(--text-muted);white-space:nowrap"></div>
</div>

<div id="orders-container" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px">
  <div style="grid-column:1/-1;text-align:center;padding:60px">
    <div class="spinner"></div>
  </div>
</div>

<!-- TAP TO PRINT FLOATING BANNER -->
<div id="print-banner" onclick="triggerBannerPrint()">
  <button id="print-banner-dismiss" onclick="event.stopPropagation(); hidePrintBanner()"
    style="position:absolute;top:8px;left:12px;background:none;border:none;color:#fff;cursor:pointer;font-size:1.2rem;opacity:.7">✕</button>
  <h3>🖨️ اضغط هنا للطباعة</h3>
  <p id="print-banner-info">طلب جديد وصل — اضغط لطباعته على الطابعة</p>
</div>

<!-- Hidden iframe target for rawbt: intent — keeps main page from navigating away -->
<iframe name="rawbt-frame" id="rawbt-frame"
  style="display:none;width:0;height:0;border:none;position:fixed;top:-9999px"></iframe>

<script>
  const ALLOWED = window.ALLOWED_CATS || [];
  let stationOrders = [];
  let _searchQuery = '';

  async function loadOrders() {
    var container = document.getElementById('orders-container');
    var viewMode = document.getElementById('view-mode').value;
    var url = '/api/orders.php?limit=100&status=' + viewMode;

    try {
      var res = await apiCall(url);
      if (!res.success) {
        container.innerHTML = '<div class="empty-state" style="grid-column:1/-1; padding: 60px; color: var(--danger)">' +
          '<span class="icon" style="background: var(--danger-light); color: var(--danger)"><i class="fas fa-exclamation-circle"></i></span>' +
          '<h3>خطأ في تحميل البيانات</h3>' +
          '<p>' + (res.message || 'يرجى التحقق من اتصال الإنترنت أو المحاولة لاحقاً') + '</p>' +
          '<button class="btn btn-outline-primary" onclick="loadOrders()" style="margin-top:15px">إعادة المحاولة</button>' +
          '</div>';
        return;
      }

      var orders = res.data.filter(function (o) {
        if (viewMode === 'delivered') return o.status === 'delivered';
        return ['confirmed', 'in_progress', 'paid', 'ready'].indexOf(o.status) !== -1;
      });

      orders.forEach(function (o) {
        o.items = (o.items || []).filter(function (i) {
          var catMatch = ALLOWED.length === 0 || ALLOWED.indexOf(parseInt(i.category_id)) !== -1;
          var statusMatch = false;
          if (viewMode === 'delivered') {
            statusMatch = true;
          } else if (viewMode === 'ready') {
            statusMatch = (i.status === 'ready' || i.status === 'served');
          } else {
            statusMatch = (i.status === 'pending' || i.status === 'in_progress');
          }
          return catMatch && statusMatch;
        });
      });

      orders = orders.filter(function (o) { return o.items.length > 0; });

      stationOrders = orders;
      var activeCountEl = document.getElementById('active-count');
      if (activeCountEl) {
        var totalItems = orders.reduce(function (sum, o) { return sum + o.items.length; }, 0);
        var labels = { active: ' صنف نشط', ready: ' صنف جاهز', delivered: ' صنف تم تسليمه' };
        activeCountEl.textContent = totalItems + (labels[viewMode] || '');
      }
      renderOrders();
      // Re-apply search filter after reload
      if (_searchQuery) filterOrders(_searchQuery, true);
    } catch (err) {
      console.error('loadOrders failed', err);
      container.innerHTML = '<div class="empty-state" style="grid-column:1/-1; padding: 60px; color: var(--danger)">' +
        '<span class="icon" style="background: var(--danger-light); color: var(--danger)"><i class="fas fa-bug"></i></span>' +
        '<h3>حدث خطأ داخلي</h3>' +
        '<p>' + err.message + '</p>' +
        '<button class="btn btn-outline-primary" onclick="loadOrders()" style="margin-top:15px">إعادة المحاولة</button>' +
        '</div>';
    }
  }


  function renderOrders() {
    var container = document.getElementById('orders-container');
    var viewMode = document.getElementById('view-mode').value;

    // Clear initial spinner or empty state
    var stale = container.querySelectorAll('.spinner, .empty-state');
    for (var i = 0; i < stale.length; i++) {
      var el = stale[i];
      if (!el.parentElement || !el.parentElement.classList.contains('station-order')) {
        el.remove();
      }
    }

    if (!stationOrders.length) {
      var config = {
        active: { icon: 'fa-clipboard-check', title: 'لا توجد طلبات جارية', desc: 'ستظهر الطلبات الجديدة هنا تلقائياً' },
        ready: { icon: 'fa-check-circle', title: 'لا توجد أصناف جاهزة حالياً', desc: 'سيظهر هنا ما قمت بتجهيزه اليوم' },
        delivered: { icon: 'fa-history', title: 'لا توجد طلبات مسلّمة', desc: 'سجل الطلبات فارغ حالياً' }
      };
      var c = config[viewMode] || config.active;

      container.innerHTML = '<div class="empty-state" style="grid-column:1/-1; padding: 60px">' +
        '<span class="icon"><i class="fas ' + c.icon + '"></i></span>' +
        '<h3>' + c.title + '</h3>' +
        '<p>' + c.desc + '</p>' +
        '</div>';
      return;
    }

    // Clear empty state
    var emptyState = container.querySelector('.empty-state');
    if (emptyState) emptyState.remove();

    var currentIds = [];
    for (var j = 0; j < stationOrders.length; j++) {
      currentIds.push(String(stationOrders[j].id));
    }

    var existingCards = container.querySelectorAll('.station-order');

    // 1. Remove stale orders
    for (var k = 0; k < existingCards.length; k++) {
      var card = existingCards[k];
      var cardId = card.id.replace('station-order-', '');
      if (currentIds.indexOf(cardId) === -1) {
        card.classList.add('fade-out');
        (function (c) { setTimeout(function () { c.remove(); }, 300); })(card);
      }
    }

    // 2. Add or Update orders incrementally
    stationOrders.forEach(function (o, index) {
      var orderEl = document.getElementById('station-order-' + o.id);
      var isNew = !orderEl;

      var printedKey = 'POS_PRINTED_ORDERS';
      var printedList = JSON.parse(localStorage.getItem(printedKey) || '[]');
      var isPrinted = printedList.indexOf(String(o.id)) !== -1;

      var printBadge = isPrinted
        ? '<span style="background:#d4edda;color:#155724;font-size:.75rem;padding:3px 10px;border-radius:20px;font-weight:600"><i class="fas fa-print"></i> مطبوع</span>'
        : '<span style="background:#fff3cd;color:#856404;font-size:.75rem;padding:3px 10px;border-radius:20px;font-weight:600"><i class="fas fa-print"></i> لم يُطبع</span>';

      var itemsHtml = '';
      var items = o.items || [];
      for (var m = 0; m < items.length; m++) {
        var item = items[m];
        itemsHtml += '<div class="item-station-row" id="item-row-' + item.id + '">' +
          '<div>' +
          '<div style="font-weight:700">' + item.item_name_ar + '</div>' +
          '<small style="color:var(--text-muted)">' + (item.cat_name_ar || '') + ' | الكمية: <strong>' + item.quantity + '</strong></small>' +
          (item.notes ? '<div style="font-size:.75rem;color:var(--warning)"><i class="fas fa-edit"></i> ' + item.notes + '</div>' : '') +
          '</div>' +
          '<div style="display:flex; gap:8px">' +
          '<button class="item-status-btn status-' + item.status + '" onclick="cycleItemStatus(' + item.id + ', \'' + item.status + '\', this)">' +
          itemStatusLabel(item.status) +
          '</button>';

        if (item.status === 'pending') {
          itemsHtml += '<button class="btn btn-danger btn-sm" onclick="rejectItem(' + item.id + ')" title="رفض الطلب (غير متوفر)">' +
            '<i class="fas fa-times"></i>' +
            '</button>';
        }
        itemsHtml += '</div></div>';
      }

      var cardInnerHtml =
        '<div style="border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px">' +
        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px">' +
        '<strong style="color: var(--primary); font-size: 1.05rem">#' + o.order_number + '</strong>' +
        (o.table_number ? '<span class="table-badge" style="background: var(--secondary); padding: 4px 12px; font-size: .85rem">طاولة ' + o.table_number + '</span>' : '') +
        '</div>' +
        '<div style="font-size: .82rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">' +
        '<span><i class="fas fa-cash-register"></i> الكاشير: <strong style="color:var(--text)">' + (o.cashier_name || 'لم يؤكد بعد') + '</strong></span>' +
        '<span><i class="fas fa-user-tag"></i> الويتري: <strong style="color:var(--text)">' + (o.direct_name || o.waiter_name || 'غير محدد') + '</strong></span>' +
        '</div>' +
        '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">' +
        '<div style="display:flex; flex-direction:column; gap:4px; margin-bottom:10px">' +
        '<div style="font-size: .82rem; color: var(--text-muted)">' +
        '<i class="far fa-clock"></i> وصول: <strong style="color:var(--text)">' + formatDate(o.created_at) + '</strong>' +
        '</div>' +
        (o.ready_at ? 
            '<div style="font-size: .82rem; color: var(--success)">' +
            '<i class="fas fa-check-circle"></i> خروج: <strong style="color:var(--success)">' + formatDate(o.ready_at) + '</strong>' +
            '</div>' : ''
        ) +
        '</div>' +
        '<div style="display:flex;align-items:center;gap:6px">' +
        printBadge +
        '<button onclick="printOrder(' + o.id + ', \'kitchen\')" class="btn btn-sm btn-outline-primary" style="padding:4px 10px;font-size:.75rem">' +
        '<i class="fas fa-print"></i> طباعة' +
        '</button>' +
        (function(){
            var hasPending = false, hasInProgress = false;
            for(var i=0; i<items.length; i++) {
                if(items[i].status === 'pending') hasPending = true;
                if(items[i].status === 'in_progress') hasInProgress = true;
            }
            var btns = '';
            if(viewMode !== 'delivered') {
                if(hasPending) {
                    btns += '<button onclick="prepareAll(' + o.id + ', \'in_progress\', this)" class="btn btn-sm" style="padding:4px 10px;font-size:.75rem;background:#3498db;color:#fff;border:none;border-radius:15px;font-weight:700;margin-left:5px">' +
                            '<i class="fas fa-fire-burner"></i> يحضر الكل' +
                            '</button>';
                }
                if(hasInProgress) {
                    btns += '<button onclick="prepareAll(' + o.id + ', \'ready\', this)" class="btn btn-sm" style="padding:4px 10px;font-size:.75rem;background:#2ecc71;color:#fff;border:none;border-radius:15px;font-weight:700">' +
                            '<i class="fas fa-check-double"></i> تحضير الكل' +
                            '</button>';
                }
            }
            return btns;
        })() +
        '</div>' +
        '</div>' +
        '</div>' +
        (o.notes ? '<div class="alert alert-warning" style="padding:8px 12px;margin-bottom:10px;font-size:.85rem"><i class="fas fa-sticky-note"></i> ' + o.notes + '</div>' : '') +
        '<div style="flex:1">' + itemsHtml + '</div>';

      if (isNew) {
        var temp = document.createElement('div');
        temp.id = 'station-order-' + o.id;
        temp.className = 'station-order' + (index === 0 ? ' new' : '');
        temp.innerHTML = cardInnerHtml;

        if (index === 0) {
          container.prepend(temp);
        } else {
          var prevId = 'station-order-' + stationOrders[index - 1].id;
          var prev = document.getElementById(prevId);
          if (prev && prev.nextSibling) container.insertBefore(temp, prev.nextSibling);
          else container.appendChild(temp);
        }
        (function (t) { setTimeout(function () { t.classList.remove('new'); }, 2000); })(temp);
      } else {
        if (orderEl.innerHTML !== cardInnerHtml) {
          orderEl.innerHTML = cardInnerHtml;
        }
      }
    });
  }

  // ============================================================
  // SEARCH / FILTER
  // ============================================================
  function filterOrders(query, silent) {
    _searchQuery = query.trim().toLowerCase();
    var searchInput = document.getElementById('station-search');
    var countEl    = document.getElementById('search-count');

    if (!silent && searchInput && searchInput.value !== query) searchInput.value = query;

    var cards = document.querySelectorAll('#orders-container .station-order');
    var shown = 0;

    // Remove old no-results message
    var noRes = document.getElementById('no-search-results');
    if (noRes) noRes.remove();

    if (!_searchQuery) {
      cards.forEach(function(c) { c.style.display = ''; });
      if (countEl) countEl.textContent = '';
      return;
    }

    cards.forEach(function(card) {
      var orderId = card.id.replace('station-order-', '');
      var order   = stationOrders.find(function(o) { return String(o.id) === orderId; });
      if (!order) { card.style.display = 'none'; return; }

      var tableNum  = String(order.table_number  || '').toLowerCase();
      var orderNum  = String(order.order_number  || '').toLowerCase();
      var itemNames = (order.items || []).map(function(i) { return (i.item_name_ar || '').toLowerCase(); }).join(' ');

      var match = tableNum.indexOf(_searchQuery)  !== -1 ||
                  orderNum.indexOf(_searchQuery)  !== -1 ||
                  itemNames.indexOf(_searchQuery) !== -1;

      card.style.display = match ? '' : 'none';
      if (match) shown++;
    });

    if (countEl) {
      countEl.textContent = shown === 0
        ? 'لا توجد نتائج'
        : 'تم العثور على ' + shown + (shown === 1 ? ' طلب' : ' طلبات');
      countEl.style.color = shown === 0 ? 'var(--danger)' : 'var(--success)';
    }

    // Show empty message if nothing matched
    if (shown === 0 && cards.length > 0) {
      var msg = document.createElement('div');
      msg.id = 'no-search-results';
      msg.className = 'no-search-results';
      msg.style.cssText = 'grid-column:1/-1;text-align:center;padding:50px 20px;color:var(--text-muted)';
      msg.innerHTML = '<div style="font-size:2.5rem;margin-bottom:12px">🔍</div>' +
        '<h3 style="color:var(--text)">لا توجد نتائج لـ "' + escHtml(query) + '"</h3>' +
        '<p>تحقق من رقم الطاولة أو رقم الفاتورة أو اسم الصنف</p>';
      document.getElementById('orders-container').appendChild(msg);
    }
  }

  function clearSearch() {
    var inp = document.getElementById('station-search');
    if (inp) inp.value = '';
    filterOrders('');
    if (inp) inp.focus();
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // Keyboard shortcut: Ctrl+F opens search
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
      var inp = document.getElementById('station-search');
      if (inp) { e.preventDefault(); inp.focus(); inp.select(); }
    }
    if (e.key === 'Escape') clearSearch();
  });

  function itemStatusLabel(s) {
    var map = { pending: '<i class="fas fa-hourglass-start"></i> معلّق', in_progress: '<i class="fas fa-fire-burner"></i> يُحضَّر', ready: '<i class="fas fa-check-circle"></i> جاهز للاستلام', served: '<i class="fas fa-utensils"></i> قُدِّم' };
    return map[s] || s;
  }

  async function cycleItemStatus(itemId, currentStatus, btn) {
    var viewMode = document.getElementById('view-mode').value;
    if (viewMode === 'delivered') return;

    var cycle = { pending: 'in_progress', in_progress: 'ready', ready: 'pending' };
    var next = cycle[currentStatus] || 'ready';

    btn.disabled = true;
    var res = await apiCall('/api/orders.php?action=update_item_status', 'POST', { item_id: itemId, status: next });
    btn.disabled = false;

    if (res.success) {
      if (viewMode === 'active' || viewMode === 'ready') {
        var row = document.getElementById('item-row-' + itemId);
        if (row) {
          row.style.opacity = '0';
          row.style.transform = 'translateX(-20px)';
          row.style.transition = 'all 0.3s ease';
        }
        setTimeout(function () { loadOrders(); }, 300);
      } else {
        loadOrders();
      }
      showToast(next === 'ready' ? 'تم تجهيز الصنف! ✅' : 'تم تحديث الحالة', 'success');
    } else {
      showToast(res.message, 'danger');
    }
  }

  async function prepareAll(orderId, status, btn) {
    btn.disabled = true;
    var originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    var res = await apiCall('/api/orders.php?action=update_order_items_status', 'POST', { order_id: orderId, status: status });

    if (res.success) {
      showToast(status === 'ready' ? 'تم تجهيز جميع الأصناف! ✅' : 'بدأ تحضير جميع الأصناف! 👨‍🍳', 'success');
      if (status === 'ready') {
        var card = document.getElementById('station-order-' + orderId);
        if (card) {
          card.style.opacity = '0.4';
          card.style.transform = 'scale(0.95)';
          card.style.transition = 'all 0.4s ease';
        }
      }
      setTimeout(function () { loadOrders(); }, 400);
    } else {
      showToast(res.message, 'danger');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  function toggleAutoPrint(enabled) {
    localStorage.setItem('POS_STATION_AUTOPRINT', enabled ? '1' : '0');
    if (enabled) showToast('تم تفعيل الطباعة التلقائية 🖨️', 'success');
  }


  function initAutoPrint() {
    var isEnabled = localStorage.getItem('POS_STATION_AUTOPRINT') === '1';
    var toggle = document.getElementById('auto-print-toggle');
    if (toggle) toggle.checked = isEnabled;
  }

  (function waitForApp() {
    if (typeof apiCall === 'function') {
      loadOrders();
      if (typeof onSSE === 'function') {
        initAutoPrint();
        onSSE('station_order', function (data) {
          var targets = data.target_user ? [data.target_user] : [];
          if (targets.length && targets.indexOf(window.POS_USER && window.POS_USER.id) === -1) return;

          playNotificationSound();

          if (localStorage.getItem('POS_STATION_AUTOPRINT') === '1') {
            showToast('🆕 طلب جديد: ' + data.order_number + ' — سيُطبع تلقائياً', 'success', 6000);
          } else {
            showToast('🆕 طلب جديد: ' + data.order_number, 'success', 6000);
          }

          loadOrders();
        });
        onSSE('item_status_changed', loadOrders);
        onSSE('order_status_changed', loadOrders);
      }
    } else { setTimeout(waitForApp, 50); }
  })();

  // ============================================================
  // FINAL AUTO-PRINT ENGINE
  // - Only marks order as printed AFTER confirmed success
  // - Uses named popup window that never breaks the main page
  // ============================================================

  function markPrinted(orderId) {
    var printedKey = 'POS_PRINTED_ORDERS';
    var p = JSON.parse(localStorage.getItem(printedKey) || '[]');
    var id = String(orderId);
    if (p.indexOf(id) === -1) {
      p.push(id);
      if (p.length > 50) p.splice(0, p.length - 50);
      localStorage.setItem(printedKey, JSON.stringify(p));
    }
  }

  // ── Auto Print ────────────────────────────────────────────────────────
  // Printing is handled entirely by the Sheba Print Service app via API polling.
  // The browser does NOT attempt to print — it only shows the order on screen.
  async function autoPrintOrder(orderId, orderNumber) {
    // No-op: The Sheba Print Service app polls /api/print_queue.php every 8s
    // and prints automatically via Bluetooth. No browser action needed.
    console.log('[AutoPrint] Order added to queue, app will print:', orderId);
  }


  // ============================================================
  // TAP-TO-PRINT BANNER FUNCTIONS
  // ============================================================
  var bannerPendingOrderId = null;
  var bannerTimeout = null;

  function showPrintBanner(orderId, orderNumber) {
    bannerPendingOrderId = orderId;
    var banner = document.getElementById('print-banner');
    var info = document.getElementById('print-banner-info');
    if (!banner) return;
    if (info) info.textContent = 'طلب جديد #' + orderNumber + ' — اضغط لطباعته على الطابعة';
    banner.style.display = 'block';

    clearTimeout(bannerTimeout);
    bannerTimeout = setTimeout(function () { hidePrintBanner(); }, 45000);
  }

  function hidePrintBanner() {
    var banner = document.getElementById('print-banner');
    if (banner) banner.style.display = 'none';
    clearTimeout(bannerTimeout);
  }

  async function triggerBannerPrint() {
    var orderId = bannerPendingOrderId;
    if (!orderId) return;
    hidePrintBanner();

    try {
        await printOrder(orderId, 'kitchen');
        markPrinted(orderId);
        renderOrders();
    } catch (e) {
        showToast('❌ فشلت الطباعة: ' + e.message, 'danger');
    }
  }

  // ============================================================
  // GUARANTEED PRINT QUEUE POLLER
  // Checks the server every 10s for ANY unprinted job.
  // This is the fail-safe in case the SSE event was missed.
  // ============================================================
  async function executeAutoPrint(orderId, queueId) {
    // Mark as done immediately to prevent re-processing on the next poll
    apiCall('/api/print_queue.php?action=mark_done', 'POST', { id: queueId });

    try {
        await printOrder(orderId, 'kitchen');
        console.log('✅ Queue print success: order ' + orderId);
    } catch (e) {
        console.error('Queue print error:', e);
    }
  }

  async function checkPrintQueue() {
    if (localStorage.getItem('POS_STATION_AUTOPRINT') !== '1') return;

    try {
      const res = await apiCall('/api/print_queue.php?action=pending');
      if (res.success && res.data && res.data.length > 0) {
        for (const job of res.data) {
          // Deduplicate using localStorage (same as SSE path)
          const printedKey = 'POS_PRINTED_ORDERS';
          let printed = JSON.parse(localStorage.getItem(printedKey) || '[]');
          const orderIdStr = String(job.order_id);

          if (!printed.includes(orderIdStr)) {
            printed.push(orderIdStr);
            if (printed.length > 30) printed = printed.slice(-30);
            localStorage.setItem(printedKey, JSON.stringify(printed));

            console.log("Print queue fallback: printing order " + job.order_id);
            showToast('🖨️ طباعة تلقائية (قائمة الانتظار): طلب ' + job.order_id, 'info', 4000);
            executeAutoPrint(job.order_id, job.id);
          } else {
            // Already printed via SSE, just mark as done in DB
            apiCall('/api/print_queue.php?action=mark_done', 'POST', { id: job.id });
          }
        }
      }
    } catch (e) {
      console.error("Print queue check error:", e);
    }
  }

  // Start polling after 5s delay, and then every 10 seconds
  setTimeout(function () {
    checkPrintQueue();
    setInterval(checkPrintQueue, 10000);
  }, 5000);

  async function rejectItem(itemId) {
    var reason = prompt('سبب رفض الطلب (مثلاً: عدم توفر كمية، المواد نفذت...):');
    if (!reason) return;

    var res = await apiCall('/api/orders.php?action=update_item_status', 'POST', {
      item_id: itemId,
      status: 'rejected',
      reason: reason
    });

    if (res.success) {
      showToast('تم رفض الصنف وإرسال المرتجع للكاشير', 'success');
    } else {
      showToast(res.message, 'danger');
    }
  }
</script>
<?php stationFooter(); ?>