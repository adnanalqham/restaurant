<?php
require_once __DIR__ . '/_layout.php';
adminHeader('الإضافة السريعة للأصناف', 'items');
?>

<style>
.import-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}
.import-textarea {
  width: 100%;
  min-height: 300px;
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg-body);
  color: var(--text-main);
  font-family: inherit;
  font-size: .95rem;
  line-height: 1.9;
  resize: vertical;
  direction: rtl;
  transition: border .2s;
}
.import-textarea:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 191,166,61), 0.15);
}
.import-textarea.en-area {
  direction: ltr;
  text-align: left;
}
.hint-row {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: .82rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}
.preview-table { width: 100%; border-collapse: collapse; }
.preview-table th, .preview-table td {
  padding: 8px 12px;
  border: 1px solid var(--border);
  font-size: .85rem;
  text-align: center;
}
.preview-table th { background: var(--bg); font-weight: 600; }
.preview-table tbody tr:hover { background: var(--bg-body); }
.row-invalid { background: #fff0f0 !important; color: #c0392b; }
.row-valid { }
#import-result li { padding: 4px 0; font-size: .88rem; }
</style>

<div class="card mb-16">
  <div class="card-header">
    <h3><i class="fas fa-bolt"></i> الإضافة السريعة للأصناف بالجملة</h3>
    <a href="items.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> العودة للأصناف</a>
  </div>
  <div class="card-body">

    <!-- Step 1: Category -->
    <div class="mb-16" style="max-width:400px">
      <label class="form-label" style="font-weight:700; font-size:1rem"><i class="fas fa-folder-open" style="color:var(--primary)"></i> الخطوة 1: اختر الفئة</label>
      <select class="form-control" id="bulk-category" style="margin-top:8px">
        <option value="">-- اختر الفئة --</option>
      </select>
    </div>

    <!-- Step 2: Textareas -->
    <label class="form-label" style="font-weight:700; font-size:1rem; display:block; margin-bottom:12px">
      <i class="fas fa-list" style="color:var(--primary)"></i> الخطوة 2: أدخل البيانات — كل سطر = صنف واحد
    </label>
    <div class="import-grid">
      <div>
        <div class="hint-row"><i class="fas fa-globe" style="color:var(--primary)"></i> الأسماء العربية *</div>
        <textarea class="import-textarea" id="names-ar" placeholder="برجر لحم&#10;برجر دجاج&#10;بيتزا مارغريتا&#10;عصير برتقال"></textarea>
      </div>
      <div>
        <div class="hint-row"><i class="fas fa-font" style="color:var(--secondary)"></i> الأسماء الإنجليزية *</div>
        <textarea class="import-textarea en-area" id="names-en" placeholder="Beef Burger&#10;Chicken Burger&#10;Margherita Pizza&#10;Orange Juice"></textarea>
      </div>
      <div>
        <div class="hint-row"><i class="fas fa-tag" style="color:var(--success)"></i> الأسعار (رقم لكل سطر) *</div>
        <textarea class="import-textarea en-area" id="prices" placeholder="25&#10;20&#10;30&#10;15"></textarea>
      </div>
      <div>
        <div class="hint-row"><i class="fas fa-hashtag" style="color:var(--text-muted)"></i> أرقام الأصناف (اختياري)</div>
        <textarea class="import-textarea en-area" id="item-numbers" placeholder="A101&#10;A102&#10;A103&#10;A104"></textarea>
      </div>
    </div>

    <!-- Preview Button -->
    <div class="flex gap-12 mb-16">
      <button class="btn btn-outline" onclick="buildPreview()"><i class="fas fa-eye"></i> معاينة قبل الإضافة</button>
      <button class="btn btn-primary" id="import-btn" onclick="importItems()" style="display:none">
        <i class="fas fa-upload"></i> إضافة كل الأصناف الآن
      </button>
    </div>

  </div>
</div>

<!-- Preview -->
<div class="card mb-16 hidden" id="preview-card">
  <div class="card-header">
    <h3><i class="fas fa-table"></i> معاينة الأصناف</h3>
    <span id="preview-stats" class="badge badge-info"></span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table class="preview-table">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم الصنف</th>
            <th>الاسم العربي</th>
            <th>الاسم الإنجليزي</th>
            <th>السعر</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody id="preview-tbody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Result -->
<div class="card hidden" id="result-card">
  <div class="card-header"><h3><i class="fas fa-check-circle"></i> نتيجة الإضافة</h3></div>
  <div class="card-body" id="import-result"></div>
</div>

<script>
let parsedItems = [];

async function loadCategories() {
  const res = await apiCall('/api/categories.php');
  if (!res.success) return;
  const sel = document.getElementById('bulk-category');
  res.data.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.textContent = c.name_ar + (c.name_en ? ' / ' + c.name_en : '');
    sel.appendChild(opt);
  });
}

function getLines(id) {
  return document.getElementById(id).value.split('\n').map(l => l.trim());
}

function buildPreview() {
  const namesAr  = getLines('names-ar').filter(l => l);
  const namesEn  = getLines('names-en').filter(l => l);
  const prices   = getLines('prices').filter(l => l);
  const itemNums = getLines('item-numbers');

  const count = Math.max(namesAr.length, namesEn.length, prices.length);
  if (count === 0) {
    showToast('الرجاء إدخال البيانات أولاً', 'warning');
    return;
  }

  parsedItems = [];
  let validCount = 0, invalidCount = 0;
  const tbody = document.getElementById('preview-tbody');
  tbody.innerHTML = '';

  for (let i = 0; i < count; i++) {
    const ar  = namesAr[i] || '';
    const en  = namesEn[i] || '';
    const pr  = parseFloat(prices[i]) || 0;
    const num = itemNums[i] || '';

    const isValid = ar.length > 0 && en.length > 0;
    if (isValid) validCount++; else invalidCount++;

    parsedItems.push({ name_ar: ar, name_en: en, price: pr, item_number: num, valid: isValid });

    const tr = document.createElement('tr');
    tr.className = isValid ? 'row-valid' : 'row-invalid';
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${num || '-'}</td>
      <td>${ar || '<span style="color:#e74c3c">⚠ فارغ</span>'}</td>
      <td>${en || '<span style="color:#e74c3c">⚠ Empty</span>'}</td>
      <td>${pr > 0 ? pr.toFixed(2) : '<span style="color:#e67e22">0.00</span>'}</td>
      <td>${isValid ? '<span class="badge badge-success">صحيح ✓</span>' : '<span class="badge badge-danger">ناقص ✗</span>'}</td>`;
    tbody.appendChild(tr);
  }

  document.getElementById('preview-stats').textContent = `${validCount} صنف صحيح${invalidCount > 0 ? ' | ' + invalidCount + ' ناقص' : ''}`;
  document.getElementById('preview-card').classList.remove('hidden');
  document.getElementById('import-btn').style.display = validCount > 0 ? '' : 'none';
  document.getElementById('result-card').classList.add('hidden');

  document.getElementById('preview-card').scrollIntoView({ behavior: 'smooth' });
}

async function importItems() {
  const catId = document.getElementById('bulk-category').value;
  if (!catId) { showToast('يرجى اختيار الفئة أولاً', 'warning'); return; }

  const validItems = parsedItems.filter(i => i.valid);
  if (!validItems.length) { showToast('لا توجد أصناف صحيحة للإضافة', 'warning'); return; }

  const btn = document.getElementById('import-btn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:2px;display:inline-block;margin-left:8px"></div> جارٍ الإضافة...';

  const res = await apiCall('/api/items_bulk.php', 'POST', {
    category_id: parseInt(catId),
    items: validItems
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-upload"></i> إضافة كل الأصناف الآن';

  const resultCard = document.getElementById('result-card');
  const resultDiv  = document.getElementById('import-result');
  resultCard.classList.remove('hidden');

  if (res.success) {
    let html = `<div class="alert alert-success" style="padding:16px;border-radius:10px;background:#eafaf1;border:1px solid #2ecc71;color:#1a7a45;margin-bottom:16px">
      <strong><i class="fas fa-check-circle"></i> تمت الإضافة بنجاح!</strong><br>
      تم إضافة <strong>${res.data.added}</strong> صنف إلى قاعدة البيانات.
    </div>`;
    if (res.data.errors && res.data.errors.length) {
      html += `<p style="color:var(--danger);font-weight:600">أخطاء (${res.data.errors.length}):</p><ul>`;
      res.data.errors.forEach(e => { html += `<li>${e}</li>`; });
      html += '</ul>';
    }
    html += `<a href="items.php" class="btn btn-primary mt-12"><i class="fas fa-list"></i> عرض الأصناف</a>
             <button class="btn btn-outline mt-12" onclick="clearAll()" style="margin-right:8px"><i class="fas fa-redo"></i> إضافة دفعة جديدة</button>`;
    resultDiv.innerHTML = html;

    // Clear form
    ['names-ar','names-en','prices','item-numbers'].forEach(id => document.getElementById(id).value = '');
    parsedItems = [];
    document.getElementById('preview-card').classList.add('hidden');
    document.getElementById('import-btn').style.display = 'none';
  } else {
    resultDiv.innerHTML = `<div style="color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> ${res.message}</div>`;
  }

  resultCard.scrollIntoView({ behavior: 'smooth' });
}

function clearAll() {
  ['names-ar','names-en','prices','item-numbers'].forEach(id => document.getElementById(id).value = '');
  parsedItems = [];
  document.getElementById('preview-card').classList.add('hidden');
  document.getElementById('result-card').classList.add('hidden');
  document.getElementById('import-btn').style.display = 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', loadCategories);
</script>

<?php adminFooter(); ?>
