<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إحصائيات المبيعات والمقارنة', 'sales_stats');
?>

<style>
/* ── Searchable Select ───────────────────────────────────── */
.ss-wrap { position:relative; z-index:9999; }
.ss-input {
    width:100%; padding:8px 12px; border-radius:8px;
    border:1.5px solid var(--border-color, #3a3a4a);
    background:var(--input-bg, var(--card-bg, #1e1e2e));
    color:var(--text-primary); font-size:.875rem;
    cursor:pointer; outline:none;
    transition: border-color .2s;
    box-shadow: inset 0 1px 3px rgba(0,0,0,.15);
    /* Match form-control height */
    height: 40px;
    line-height: 1.4;
    text-align: right;
}
.ss-input:hover { border-color:var(--primary, #f59e0b); }
.ss-input:focus, .ss-input.active { border-color:var(--primary, #f59e0b); }
.ss-dropdown {
    display:none; position:absolute; z-index:9999; top:calc(100% + 4px);
    left:0; right:0; max-height:240px; overflow-y:auto;
    background:var(--card-bg); border:1px solid var(--border-color);
    border-radius:8px; box-shadow:0 12px 32px rgba(0,0,0,.35);
}
.ss-dropdown.open { display:block; }
.ss-search {
    padding:8px 10px; border-bottom:1px solid var(--border-color);
    position:sticky; top:0; background:var(--card-bg);
}
.ss-search input {
    width:100%; padding:6px 10px; border-radius:6px;
    border:1px solid var(--border-color);
    background:var(--bg, #111); color:var(--text-primary);
    font-size:.82rem; outline:none;
}
.ss-option {
    padding:8px 14px; font-size:.84rem; cursor:pointer;
    color:var(--text-primary); transition:.15s;
}
.ss-option:hover, .ss-option.focused { background:var(--primary); color:#fff; }
.ss-option.hidden { display:none; }
.ss-placeholder { color:var(--text-muted); }

/* ── Mode Cards ─────────────────────────────────────────── */
.mode-cards { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.mode-card {
    border:2px solid var(--border-color); border-radius:10px;
    padding:18px 16px; cursor:pointer; transition:.2s;
    text-align:center; background:var(--card-bg); user-select:none;
}
.mode-card:hover { border-color:var(--primary); }
.mode-card.selected { border-color:var(--primary); background:rgba(255,165,0,.07); }
.mode-card .mc-icon { font-size:1.8rem; margin-bottom:6px; }
.mode-card h4 { margin:0 0 4px; font-size:.9rem; }
.mode-card p  { margin:0; font-size:.76rem; color:var(--text-muted); }

/* ── Entry Form ─────────────────────────────────────────── */
.entry-row {
    display:grid;
    grid-template-columns: 1fr 110px 160px auto;
    gap:10px; align-items:flex-end;
    overflow:visible;
}
.entry-row .form-group { overflow:visible; }
@media(max-width:640px) { .entry-row { grid-template-columns:1fr; } }

/* prevent cards from clipping dropdown */
.card { overflow:visible !important; }
.card-body { overflow:visible !important; }

/* ── Comparison Table ───────────────────────────────────── */
.comp-table th, .comp-table td { padding:9px 12px; font-size:.83rem; vertical-align:middle; }
.comp-table thead th { background:var(--table-header-bg, rgba(255,255,255,.04)); font-weight:600; }
.diff-ok    { color:#10b981; font-weight:bold; }
.diff-over  { color:#f59e0b; font-weight:bold; }
.diff-under { color:#ef4444; font-weight:bold; }
.badge-ok    { background:#10b981; color:#fff; padding:2px 9px; border-radius:20px; font-size:.72rem; white-space:nowrap; }
.badge-over  { background:#f59e0b; color:#fff; padding:2px 9px; border-radius:20px; font-size:.72rem; white-space:nowrap; }
.badge-under { background:#ef4444; color:#fff; padding:2px 9px; border-radius:20px; font-size:.72rem; white-space:nowrap; }
.badge-nd    { background:#6b7280; color:#fff; padding:2px 9px; border-radius:20px; font-size:.72rem; white-space:nowrap; }

/* ── Sales List ─────────────────────────────────────────── */
.sale-row td { padding:8px 12px; font-size:.84rem; }
.empty-state { text-align:center; padding:32px; color:var(--text-muted); font-size:.9rem; }
</style>

<!-- ── Toolbar ─────────────────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:.82rem">📅 التاريخ</label>
        <input type="date" class="form-control" id="sel-date"
               value="<?= date('Y-m-d') ?>" onchange="resetAll()"
               style="max-width:180px">
      </div>
      <div style="padding-bottom:3px;color:var(--text-muted);font-size:.82rem">
        اختر التاريخ ثم حدد طريقة إدخال المبيعات ↓
      </div>
    </div>
  </div>
</div>

<!-- ── Mode Selection ──────────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-header"><h3 class="card-title">📌 طريقة بيانات المبيعات</h3></div>
  <div class="card-body">
    <div class="mode-cards" style="max-width:520px">
      <div class="mode-card" id="mc-system" onclick="selectMode('system')">
        <div class="mc-icon">🔄</div>
        <h4>من النظام تلقائياً</h4>
        <p>سحب مبيعات اليوم من الطلبات المسجلة</p>
      </div>
      <div class="mode-card" id="mc-manual" onclick="selectMode('manual')">
        <div class="mc-icon">✍️</div>
        <h4>إدخال يدوي للمبيعات</h4>
        <p>إدخال الأصناف والكميات يدوياً</p>
      </div>
    </div>
  </div>
</div>

<!-- ── System Section ──────────────────────────────────────────── -->
<div id="sec-system" style="display:none">
  <div class="card mb-16">
    <div class="card-header">
      <h3 class="card-title">📊 مبيعات النظام</h3>
      <button class="btn btn-sm btn-outline" onclick="loadSystemSales()">🔄 تحديث</button>
    </div>
    <div class="card-body">
      <div id="sys-content"><div class="empty-state">⏳ جاري التحميل...</div></div>
    </div>
  </div>
  <div id="cmp-system"></div>
</div>

<!-- ── Manual Section ─────────────────────────────────────────── -->
<div id="sec-manual" style="display:none">
  <div class="card mb-16">
    <div class="card-header"><h3 class="card-title">✍️ إدخال المبيعات اليدوي</h3></div>
    <div class="card-body">

      <!-- Entry Form -->
      <div class="entry-row mb-16">
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:.82rem">الصنف</label>
          <div class="ss-wrap" id="ss-wrap">
            <input class="ss-input" id="ss-display" readonly placeholder="اختر الصنف..." onclick="toggleSS(event)">
            <input type="hidden" id="ss-value">
            <div class="ss-dropdown" id="ss-dropdown">
              <div class="ss-search">
                <input type="text" id="ss-search" placeholder="🔍 بحث عن صنف..." oninput="filterSS(this.value)" autocomplete="off">
              </div>
              <div id="ss-list"></div>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:.82rem">الكمية</label>
          <input type="number" class="form-control" id="m-qty" min="0.5" step="0.5" value="1">
        </div>

        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:.82rem">ملاحظة (اختياري)</label>
          <input type="text" class="form-control" id="m-notes" placeholder="...">
        </div>

        <button class="btn btn-primary" onclick="addRow()" style="white-space:nowrap">
          + إضافة
        </button>
      </div>

      <!-- List -->
      <div id="manual-list"><div class="empty-state">لا توجد إدخالات لهذا اليوم.</div></div>
    </div>
  </div>
  <div id="cmp-manual"></div>
</div>

<script>
var BASE = '<?= BASE_PATH ?>';
var allItems = [], ssOpen = false;

// ── Load items for searchable select ─────────────────────────────────────────
fetch(BASE + 'api/sales_stats.php?action=items_list')
  .then(r => r.json()).then(d => {
    allItems = d.items || [];
    var list  = document.getElementById('ss-list');
    allItems.forEach(it => {
      var label = (it.name_ar || it.name_en) + (it.name_en ? ' — ' + it.name_en : '');
      list.insertAdjacentHTML('beforeend',
        `<div class="ss-option" data-id="${it.id}" data-label="${label}" onclick="pickItem(this)">${label}</div>`);
    });
  });

// Searchable Select helpers
function toggleSS(e) {
  e.stopPropagation();
  var dd = document.getElementById('ss-dropdown');
  ssOpen = !ssOpen;
  dd.classList.toggle('open', ssOpen);
  if (ssOpen) { setTimeout(() => document.getElementById('ss-search').focus(), 50); }
}
function filterSS(q) {
  q = q.trim().toLowerCase();
  document.querySelectorAll('.ss-option').forEach(o => {
    o.classList.toggle('hidden', q && !o.dataset.label.toLowerCase().includes(q));
  });
}
function pickItem(el) {
  document.getElementById('ss-value').value   = el.dataset.id;
  document.getElementById('ss-display').value = el.dataset.label;
  document.getElementById('ss-display').classList.remove('ss-placeholder');
  closeSSDropdown();
}
function closeSSDropdown() {
  ssOpen = false;
  document.getElementById('ss-dropdown').classList.remove('open');
  document.getElementById('ss-search').value = '';
  filterSS('');
}
document.addEventListener('click', e => {
  if (!document.getElementById('ss-wrap').contains(e.target)) closeSSDropdown();
});

// ── Utils ─────────────────────────────────────────────────────────────────────
function getDate() { return document.getElementById('sel-date').value; }

function resetAll() {
  ['system','manual'].forEach(m => {
    document.getElementById('mc-' + m).classList.remove('selected');
    document.getElementById('sec-' + m).style.display = 'none';
  });
}

function selectMode(mode) {
  resetAll();
  document.getElementById('mc-'   + mode).classList.add('selected');
  document.getElementById('sec-' + mode).style.display = 'block';
  mode === 'system' ? loadSystemSales() : loadManualSales();
}

// ── System Sales ──────────────────────────────────────────────────────────────
function loadSystemSales() {
  var el = document.getElementById('sys-content');
  el.innerHTML = '<div class="empty-state">⏳ جاري التحميل...</div>';
  fetch(BASE + 'api/sales_stats.php?action=system_sales&date=' + getDate())
    .then(r => r.json()).then(d => {
      if (!d.sales?.length) {
        el.innerHTML = '<div class="empty-state">لا توجد مبيعات مسجلة في النظام لهذا اليوم.</div>';
        document.getElementById('cmp-system').innerHTML = '';
        return;
      }
      var html = `<div style="overflow-x:auto"><table class="table comp-table">
        <thead><tr><th>#</th><th>الصنف</th><th>الكمية المباعة</th></tr></thead><tbody>`;
      d.sales.forEach((s, i) => {
        html += `<tr><td style="color:var(--text-muted)">${i+1}</td>
          <td>${s.item_number ? '<span style="color:var(--text-muted);font-size:.8rem">('+s.item_number+') </span>' : ''}${s.name_ar || s.name_en}</td>
          <td><strong>${parseFloat(s.qty_sold)}</strong></td></tr>`;
      });
      html += '</tbody></table></div>';
      el.innerHTML = html;
      loadComparison('system');
    });
}

// ── Manual Sales ──────────────────────────────────────────────────────────────
function loadManualSales() {
  fetch(BASE + 'api/sales_stats.php?action=manual_sales_get&date=' + getDate())
    .then(r => r.json()).then(d => renderList(d.sales || []));
}

function renderList(sales) {
  var el = document.getElementById('manual-list');
  if (!sales.length) {
    el.innerHTML = '<div class="empty-state">لا توجد إدخالات يدوية لهذا اليوم.<br><small>استخدم النموذج أعلاه لإضافة مبيعات.</small></div>';
    document.getElementById('cmp-manual').innerHTML = '';
    return;
  }
  var html = `<div style="overflow-x:auto"><table class="table comp-table">
    <thead><tr><th>#</th><th>الصنف</th><th>الكمية</th><th>ملاحظة</th><th></th></tr></thead><tbody>`;
  sales.forEach((s, i) => {
    html += `<tr class="sale-row">
      <td style="color:var(--text-muted)">${i+1}</td>
      <td>${s.item_number ? '<span style="color:var(--text-muted);font-size:.8rem">('+s.item_number+') </span>' : ''}${s.name_ar || s.name_en}</td>
      <td><strong>${parseFloat(s.quantity)}</strong></td>
      <td style="color:var(--text-muted)">${s.notes || '—'}</td>
      <td><button class="btn btn-sm btn-danger" onclick="delRow(${s.id})">🗑</button></td>
    </tr>`;
  });
  html += '</tbody></table></div>';
  el.innerHTML = html;
  loadComparison('manual');
}

function addRow() {
  var itemId = document.getElementById('ss-value').value;
  var qty    = parseFloat(document.getElementById('m-qty').value);
  var notes  = document.getElementById('m-notes').value.trim();
  if (!itemId)        { alert('اختر الصنف أولاً'); return; }
  if (!qty || qty<=0) { alert('أدخل كمية صحيحة');  return; }
  fetch(BASE + 'api/sales_stats.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'manual_sale_save', item_id:itemId, quantity:qty, date:getDate(), notes})
  }).then(r => r.json()).then(d => {
    if (d.success) {
      document.getElementById('ss-value').value   = '';
      document.getElementById('ss-display').value = '';
      document.getElementById('m-qty').value   = '1';
      document.getElementById('m-notes').value = '';
      loadManualSales();
    }
  });
}

function delRow(id) {
  if (!confirm('حذف هذا السطر؟')) return;
  fetch(BASE + 'api/sales_stats.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'manual_sale_delete', id})
  }).then(() => loadManualSales());
}

// ── Comparison ────────────────────────────────────────────────────────────────
function loadComparison(mode) {
  var el = document.getElementById('cmp-' + mode);
  el.innerHTML = '<div class="card mb-16"><div class="card-body"><div class="empty-state">⏳ جاري حساب المقارنة...</div></div></div>';
  fetch(BASE + 'api/sales_stats.php?action=comparison&date=' + getDate() + '&mode=' + mode)
    .then(r => r.json()).then(d => {
      if (!d.comparison?.length) {
        el.innerHTML = `<div class="card mb-16"><div class="card-body">
          <div class="empty-state">⚠️ لا توجد بيانات مكونات للمقارنة — تأكد من إدخال مكونات الأصناف أولاً.</div>
        </div></div>`;
        return;
      }
      var statusCls = {ok:'badge-ok',over:'badge-over',under:'badge-under',no_data:'badge-nd'};
      var statusLbl = {ok:'✅ مطابق',over:'⬆️ زيادة',under:'⬇️ نقص',no_data:'—'};
      var diffCls   = {ok:'diff-ok',over:'diff-over',under:'diff-under',no_data:''};
      var html = `<div class="card mb-16">
        <div class="card-header">
          <h3 class="card-title">⚖️ مقارنة المخزون — ${d.date}</h3>
        </div>
        <div class="card-body" style="overflow-x:auto">
        <table class="table comp-table">
          <thead><tr>
            <th>المكون</th><th>الوحدة</th>
            <th>الاستهلاك النظري</th>
            <th>الاستهلاك الفعلي</th>
            <th>الفرق</th><th>الحالة</th>
          </tr></thead><tbody>`;
      d.comparison.forEach(r => {
        var st   = r.status || 'no_data';
        var diff = r.diff !== null ? (r.diff > 0 ? '+' + r.diff : r.diff) : '—';
        html += `<tr>
          <td>${r.name_ar || r.name_en}</td>
          <td style="color:var(--text-muted)">${r.unit || '—'}</td>
          <td>${r.theoretical}</td>
          <td>${r.actual ?? '<span style="color:var(--text-muted)">—</span>'}</td>
          <td class="${diffCls[st]}">${diff}</td>
          <td><span class="${statusCls[st]}">${statusLbl[st]}</span></td>
        </tr>`;
      });
      html += '</tbody></table></div></div>';
      el.innerHTML = html;
    });
}
</script>

<?php adminFooter(); ?>
