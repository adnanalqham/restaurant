<?php
require_once __DIR__ . '/_layout.php';
waiterHeader('طلباتي', 'my_orders');
?>

<div class="card mb-16">
  <div class="card-body" style="padding: 12px 16px">
    <div class="flex gap-16" style="flex-wrap: wrap">
      <div class="form-group" style="margin:0; flex:1; min-width: 200px">
        <label class="form-label">🔍 بحث (رقم الطلب أو الطاولة)</label>
        <input type="text" id="filter-search" class="form-control" placeholder="مثال: 5 أو ORD-..." oninput="loadOrders()">
      </div>
      <div class="form-group" style="margin:0; width: 150px">
        <label class="form-label">📅 التاريخ</label>
        <input type="date" id="filter-date" class="form-control" onchange="loadOrders()">
      </div>
      <div class="form-group" style="margin:0; width: 180px">
        <label class="form-label">📊 الحالة</label>
        <select id="filter-status" class="form-control" onchange="loadOrders()">
          <option value="">كل الحالات</option>
          <option value="pending">معلّق (مسودة)</option>
          <option value="sent_to_cashier">أُرسل للكاشير</option>
          <option value="confirmed">بدأ التحضير</option>
          <option value="in_progress">قيد التحضير</option>
          <option value="ready">جاهز للاستلام</option>
          <option value="paid">تم الدفع</option>
          <option value="delivered">تم التسليم للزبون</option>
          <option value="cancelled">ملغي</option>
        </select>
      </div>
    </div>
  </div>
</div>

<style>
/* Responsive grid for waiter orders */
.orders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
/* Matches Admin Orders Design */
.order-card {
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  min-height: 200px;
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px;
  border-top: 4px solid var(--primary);
}
.order-card.fade-out {
  transform: scale(0.9); opacity: 0; pointer-events: none;
}
.order-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  padding-bottom: 10px;
  margin-bottom: 10px;
}
.order-card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border);
  padding-top: 10px;
  margin-top: 10px;
}
.order-items-preview {
  margin-bottom: 8px;
}
.order-item-line {
  display: flex;
  justify-content: space-between;
  font-size: .85rem;
  padding: 5px 0;
  border-bottom: 1px dashed var(--border);
}
.order-item-line:last-child {
  border-bottom: none;
}
</style>

<div class="orders-grid" id="orders-grid">
  <div style="grid-column:1/-1;text-align:center;padding:60px"><div class="spinner"></div></div>
</div>

<!-- Order Detail Modal -->
<div class="modal-backdrop hidden" id="detail-modal">
  <div class="modal" style="max-width:550px">
    <div class="modal-header">
      <h3 id="detail-num">تفاصيل الطلب</h3>
      <button class="modal-close" onclick="closeDetail()">✕</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<script>
var myOrders = [];
var loadOrdersTimer = null;

function debouncedLoadOrders() {
  clearTimeout(loadOrdersTimer);
  loadOrdersTimer = setTimeout(loadOrders, 300);
}

function loadOrders() {
  var status = document.getElementById('filter-status').value;
  var search = document.getElementById('filter-search').value;
  var date   = document.getElementById('filter-date').value;
  
  var url = '/api/orders.php?limit=100';
  if (status) url += '&status=' + status;
  if (date)   url += '&date=' + date;
  if (search) url += '&search=' + encodeURIComponent(search);

  apiCall(url).then(function(res) {
    if (!res.success) return;
    myOrders = res.data;
    renderOrders();
  });
}

// Set initial date to today
document.getElementById('filter-date').value = new Date().toLocaleDateString('en-CA');

function renderOrders() {
  var grid = document.getElementById('orders-grid');
  if (!myOrders.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1; padding: 60px"><span class="icon"><i class="fas fa-clipboard-list"></i></span><h3>لا توجد طلبات اليوم</h3></div>';
    return;
  }

  // Clear empty state
  var emptyState = grid.querySelector('.empty-state');
  if (emptyState) emptyState.remove();

  var currentIds = [];
  for (var i = 0; i < myOrders.length; i++) {
    currentIds.push(String(myOrders[i].id));
  }
  
  var existingCards = grid.querySelectorAll('.order-card');

  // 1. Remove old cards
  for (var k = 0; k < existingCards.length; k++) {
    var c = existingCards[k];
    var cId = c.id.replace('order-card-', '');
    if (currentIds.indexOf(cId) === -1) {
      c.classList.add('fade-out');
      (function(cardToRem) {
        setTimeout(function() { cardToRem.remove(); }, 300);
      })(c);
    }
  }

  // 2. Add or Update cards incrementally
  myOrders.forEach(function(o, index) {
    var card = document.getElementById('order-card-' + o.id);
    var isNew = !card;
    
    // Status badge local check
    var getBadge = function(s) {
      return (typeof statusBadge === 'function') ? statusBadge(s) : '<span class="badge">' + s + '</span>';
    };

    var limit = 3;
    var itemsCount = o.items ? o.items.length : 0;
    var previewItems = (o.items || []).slice(0, limit);
    var itemsHtml = '';
    for (var m = 0; m < previewItems.length; m++) {
      var itm = previewItems[m];
      itemsHtml += '<div class="order-item-line" style="display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; border-bottom:1px dashed var(--border);">' +
        '<span>' + (itm.item_number ? '[' + itm.item_number + '] ' : '') + itm.item_name_ar + ' x' + itm.quantity + '</span>' +
        '<span>' + parseFloat(itm.subtotal).toFixed(2) + '</span>' +
      '</div>';
    }
    
    var moreItemsStr = '';
    if (itemsCount > limit) {
      moreItemsStr = '<span style="color:var(--primary);font-size:.8rem">+' + (itemsCount - limit) + ' أصناف أخرى</span>';
    }
    
    // Delivery and delete logic
    var isFullyReady = false;
    if (itemsCount > 0) {
      isFullyReady = true;
      for (var f = 0; f < o.items.length; f++) {
        if (o.items[f].status !== 'ready' && o.items[f].status !== 'served' && o.items[f].status !== 'rejected') {
          isFullyReady = false;
          break;
        }
      }
    }
    
    var blockedStatus = (o.status === 'cancelled' || o.status === 'pending' || o.status === 'sent_to_cashier');
    var validDeliveredSrc = (o.status === 'ready' || o.status === 'refunded' || o.status === 'partially_refunded' || isFullyReady);
    var canDeliver = (!o.delivered_at && o.status !== 'delivered' && validDeliveredSrc && !blockedStatus);

    var cardInnerHtml = 
      '<div class="order-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:12px">' +
        '<span class="order-number" style="font-weight:bold;font-size:1.1rem;color:var(--primary)">#' + o.order_number + '</span>' +
        (o.table_number ? '<span class="table-badge" style="background:var(--secondary);color:#fff;padding:4px 12px;font-size:.85rem;border-radius:20px;">طاولة ' + o.table_number + '</span>' : '') +
        getBadge(o.status) +
      '</div>' +
      '<div style="display:flex; justify-content:space-between; align-items:center; font-size:.82rem; color:var(--text-muted); margin-bottom:12px">' +
        '<span><i class="far fa-clock"></i> ' + formatDate(o.created_at) + '</span>' +
        (o.delivered_at ? '<span style="color:var(--success)"><i class="fas fa-check-double"></i> تم التسليم</span>' : '') +
      '</div>' +
      '<div class="order-items-preview" style="flex:1">' +
        itemsHtml + moreItemsStr +
      '</div>' +
      (o.notes ? '<div style="font-size:.8rem;color:var(--text-muted);margin-top:8px"><i class="fas fa-sticky-note"></i> ' + o.notes + '</div>' : '') +
      '<div class="order-card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:10px; margin-top:10px;">' +
        '<strong style="color:var(--primary)">' + parseFloat(o.total).toFixed(2) + ' ريال</strong>' +
        '<div class="flex gap-8" style="flex-wrap:wrap;justify-content:flex-end">' +
          '<button class="btn btn-secondary btn-sm" onclick="viewOrder(' + o.id + ')" title="التفاصيل"><i class="fas fa-eye"></i></button>' +
          (o.status !== 'delivered' && o.status !== 'cancelled' && o.status !== 'paid' ? '<button class="btn btn-warning btn-sm" onclick="appendItemsToOrder(' + o.id + ')" title="إضافة أصناف جديدة"><i class="fas fa-plus"></i> إضافة</button>' : '') +
          (canDeliver ? '<button class="btn btn-success btn-sm" onclick="deliverOrder(' + o.id + ')"><i class="fas fa-hand-holding-heart"></i> تسليم</button>' : '') +
          (o.status === 'pending' || o.status === 'sent_to_cashier' ? 
            '<button class="btn btn-primary btn-sm" onclick="sendOrder(' + o.id + ')"><i class="fas fa-paper-plane"></i> إرسال</button>' +
            '<button class="btn btn-danger btn-sm" onclick="deleteOrder(' + o.id + ')"><i class="fas fa-trash"></i></button>' : '') +
          (window.POS_USER && parseInt(window.POS_USER.can_print) === 1 ? '<button class="btn btn-info btn-sm" onclick="printReceipt(' + o.id + ')" title="طباعة الفاتورة"><i class="fas fa-print"></i> طباعة</button>' : '') +
        '</div>' +
      '</div>';

    if (isNew) {
      var temp = document.createElement('div');
      temp.id = 'order-card-' + o.id;
      temp.className = 'order-card';
      temp.innerHTML = cardInnerHtml;
      
      if (index === 0) grid.prepend(temp);
      else {
        var prevId = 'order-card-' + myOrders[index-1].id;
        var prev = document.getElementById(prevId);
        if (prev && prev.nextSibling) grid.insertBefore(temp, prev.nextSibling);
        else grid.appendChild(temp);
      }
    } else {
      if (card.innerHTML !== cardInnerHtml) {
        card.innerHTML = cardInnerHtml;
      }
    }
  });
}

function deliverOrder(id) {
  apiCall('/api/orders.php?action=update_status', 'POST', {order_id: id, status: 'delivered'}).then(function(res) {
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadOrders();
  });
}

function sendOrder(id) {
  apiCall('/api/orders.php?action=update_status', 'POST', {order_id: id, status: 'sent_to_cashier'}).then(function(res) {
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadOrders();
  });
}

function deleteOrder(id) {
  if (!confirmAction('حذف هذا الطلب؟')) return;
  apiCall('/api/orders.php?action=delete', 'POST', {id: id}).then(function(res) {
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadOrders();
  });
}

function viewOrder(id) {
  apiCall('/api/orders.php?action=single&id=' + id).then(function(res) {
    if (!res.success) return;
    var o = res.data;
    var getBadge = function(s) { return typeof statusBadge === 'function' ? statusBadge(s) : s; };
    
    document.getElementById('detail-num').innerHTML = 'تفاصيل الطلب #' + o.order_number;
    
    var itemsHtml = o.items.map(function(i) {
      var isRej = i.status === 'rejected';
      return '<tr>' +
        '<td>' + (i.item_number ? '[' + i.item_number + '] ' : '') + i.item_name_ar + (isRej ? ' <span class="badge badge-danger" style="font-size:.7rem">مرفوض</span>' : '') + '</td>' +
        '<td>' + i.quantity + '</td>' +
        '<td>' + parseFloat(i.unit_price).toFixed(2) + '</td>' +
        '<td style="' + (isRej ? 'text-decoration:line-through;color:var(--danger)' : '') + '">' + parseFloat(i.subtotal).toFixed(2) + '</td>' +
        '<td>' + getBadge(i.status) + '</td>' +
      '</tr>';
    }).join('');

    document.getElementById('detail-body').innerHTML = 
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">' +
        '<div><strong>الطاولة:</strong> ' + (o.table_number || 'غير محدد') + '</div>' +
        '<div><strong>الحالة:</strong> ' + getBadge(o.status) + '</div>' +
        '<div><strong>التاريخ:</strong> ' + formatDate(o.created_at) + '</div>' +
      '</div>' +
      (o.notes ? '<div class="alert alert-info"><i class="fas fa-sticky-note"></i> ' + o.notes + '</div>' : '') +
      '<table>' +
        '<thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th><th>الحالة</th></tr></thead>' +
        '<tbody>' + itemsHtml + '</tbody>' +
      '</table>' +
      '<div class="receipt-row bold mt-12" style="font-size:1.1rem;justify-content:flex-end;gap:5px;flex-direction:column;align-items:flex-end">' +
        '<div>' +
          '<span>إجمالي الفاتورة الأصلية:</span>' +
          '<span style="' + (o.refund_amount > 0 ? 'text-decoration:line-through;color:var(--text-muted)' : 'color:var(--primary)') + '">' + parseFloat(o.total).toFixed(2) + ' ريال</span>' +
        '</div>' +
        (o.refund_amount > 0 ? 
        '<div style="color:var(--danger)">' +
          '<span>المرتجع:</span>' +
          '<span>-' + parseFloat(o.refund_amount).toFixed(2) + ' ريال</span>' +
        '</div>' +
        '<div style="font-size:1.3rem;font-weight:900;color:var(--primary);margin-top:5px;border-top:2px dashed var(--border);padding-top:5px">' +
          '<span>الصافي:</span>' +
          '<span>' + parseFloat(o.total - o.refund_amount).toFixed(2) + ' ريال</span>' +
        '</div>' : '') +
      '</div>';
      
    document.getElementById('detail-footer').innerHTML = 
      '<button class="btn btn-secondary" onclick="closeDetail()">إغلاق</button>' +
      (window.POS_USER && parseInt(window.POS_USER.can_print) === 1 ? '<button class="btn btn-primary" onclick="printReceipt(' + o.id + ')"><i class="fas fa-print"></i> للطباعة</button>' : '');
      
    document.getElementById('detail-modal').classList.remove('hidden');
  });
}

function appendItemsToOrder(id) {
  window.location.href = './index.php?append_to=' + id;
}

function closeDetail() { document.getElementById('detail-modal').classList.add('hidden'); }

document.getElementById('filter-status').addEventListener('change', loadOrders);

(function waitForApp() {
  if (typeof apiCall === 'function') {
    loadOrders();
    if (typeof onSSE === 'function') {
      onSSE('order_status_changed', function(data) {
        debouncedLoadOrders();
        if (data.status === 'refunded' || data.status === 'partially_refunded') {
          showToast('تم التسليم (إرجاع مبلغ) للطلب #' + data.order_number, 'success', 5000);
        }
      });
      onSSE('item_status_changed', function(data) {
        debouncedLoadOrders();
        if (data.status === 'rejected') {
          showToast('⚠️ تم رفض صنف في الطلب #' + data.order_number, 'danger', 5000);
        }
      });
      onSSE('new_order', function() { debouncedLoadOrders(); });
      onSSE('menu_updated', function() { debouncedLoadOrders(); });
    }
  } else { setTimeout(waitForApp, 50); }
})();
</script>
<?php waiterFooter(); ?>
