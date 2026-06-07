<?php
require_once __DIR__ . '/_layout.php';
adminHeader('الإضافة السريعة للمكونات', 'ingredients');
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
    <h3><i class="fas fa-bolt"></i> الإضافة السريعة للمكونات بالجملة</h3>
    <a href="ingredients.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> العودة للمكونات</a>
  </div>
  <div class="card-body">

    <!-- Step 1: Default Unit -->
    <div class="mb-16" style="max-width:400px">
      <label class="form-label" style="font-weight:700; font-size:1rem"><i class="fas fa-weight" style="color:var(--primary)"></i> الخطوة 1: اختر الوحدة الافتراضية</label>
      <select class="form-control" id="bulk-unit" style="margin-top:8px">
        <option value="gram">جرام</option>
        <option value="kg">كيلوغرام</option>
        <option value="piece">حبة / قطعة</option>
        <option value="liter">لتر</option>
        <option value="ml">مل</option>
        <option value="cup">كوب</option>
        <option value="tablespoon">ملعقة</option>
        <option value="other">أخرى</option>
      </select>
    </div>

    <!-- Step 2: Textareas -->
    <label class="form-label" style="font-weight:700; font-size:1rem; display:block; margin-bottom:12px">
      <i class="fas fa-list" style="color:var(--primary)"></i> الخطوة 2: أدخل البيانات — كل سطر = مكون واحد
    </label>
    <div class="import-grid">
      <div>
        <div class="hint-row"><i class="fas fa-globe" style="color:var(--primary)"></i> أسماء المكونات *</div>
        <textarea class="import-textarea" id="names" placeholder="طماطم&#10;بصل&#10;ثوم&#10;لحم مفروم"></textarea>
      </div>
      <div>
        <div class="hint-row"><i class="fas fa-hashtag" style="color:var(--text-muted)"></i> أرقام المكونات (اختياري)</div>
        <textarea class="import-textarea en-area" id="item-numbers" placeholder="ING-01&#10;ING-02&#10;ING-03&#10;ING-04"></textarea>
      </div>
    </div>

    <!-- Preview Button -->
    <div class="flex gap-12 mb-16">
      <button class="btn btn-outline" onclick="buildPreview()"><i class="fas fa-eye"></i> معاينة قبل الإضافة</button>
      <button class="btn btn-primary" id="import-btn" onclick="importItems()" style="display:none">
        <i class="fas fa-upload"></i> إضافة كل المكونات الآن
      </button>
    </div>

  </div>
</div>

<!-- Preview -->
<div class="card mb-16 hidden" id="preview-card">
  <div class="card-header">
    <h3><i class="fas fa-table"></i> معاينة المكونات</h3>
    <span id="preview-stats" class="badge badge-info"></span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table class="preview-table">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم المكون</th>
            <th>الاسم</th>
            <th>الوحدة</th>
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
const UNIT_LABELS = {
  gram:'جرام', kg:'كيلو', piece:'حبة/قطعة', liter:'لتر',
  ml:'مل', cup:'كوب', tablespoon:'ملعقة', other:'أخرى'
};

function getLines(id) {
  return document.getElementById(id).value.split('\n').map(l => l.trim());
}

function buildPreview() {
  const names = getLines('names').filter(l => l);
  const itemNums = getLines('item-numbers');
  const unit = document.getElementById('bulk-unit').value;
  const unitLabel = UNIT_LABELS[unit] || unit;

  const count = names.length;
  if (count === 0) {
    showToast('الرجاء إدخال أسماء المكونات أولاً', 'warning');
    return;
  }

  parsedItems = [];
  let validCount = 0, invalidCount = 0;
  const tbody = document.getElementById('preview-tbody');
  tbody.innerHTML = '';

  for (let i = 0; i < count; i++) {
    const name = names[i] || '';
    const num = itemNums[i] || '';

    const isValid = name.length > 0;
    if (isValid) validCount++; else invalidCount++;

    parsedItems.push({ name: name, ingredient_number: num, unit: unit, valid: isValid });

    const tr = document.createElement('tr');
    tr.className = isValid ? 'row-valid' : 'row-invalid';
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${num || '-'}</td>
      <td>${name || '<span style="color:#e74c3c">⚠ فارغ</span>'}</td>
      <td><span class="badge badge-info">${unitLabel}</span></td>
      <td>${isValid ? '<span class="badge badge-success">صحيح ✓</span>' : '<span class="badge badge-danger">ناقص ✗</span>'}</td>`;
    tbody.appendChild(tr);
  }

  document.getElementById('preview-stats').textContent = `${validCount} مكون صحيح${invalidCount > 0 ? ' | ' + invalidCount + ' ناقص' : ''}`;
  document.getElementById('preview-card').classList.remove('hidden');
  document.getElementById('import-btn').style.display = validCount > 0 ? '' : 'none';
  document.getElementById('result-card').classList.add('hidden');

  document.getElementById('preview-card').scrollIntoView({ behavior: 'smooth' });
}

async function importItems() {
  const validItems = parsedItems.filter(i => i.valid);
  if (!validItems.length) { showToast('لا توجد مكونات صحيحة للإضافة', 'warning'); return; }

  const btn = document.getElementById('import-btn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:2px;display:inline-block;margin-left:8px"></div> جارٍ الإضافة...';

  // We send the array of items to api/ingredients.php?action=bulk_import
  const res = await apiCall('/api/ingredients.php?action=bulk_import', 'POST', {
    items: validItems
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-upload"></i> إضافة كل المكونات الآن';

  const resultCard = document.getElementById('result-card');
  const resultDiv  = document.getElementById('import-result');
  resultCard.classList.remove('hidden');

  if (res.success) {
    let html = `<div class="alert alert-success" style="padding:16px;border-radius:10px;background:#eafaf1;border:1px solid #2ecc71;color:#1a7a45;margin-bottom:16px">
      <strong><i class="fas fa-check-circle"></i> تمت الإضافة بنجاح!</strong><br>
      تم إضافة <strong>${res.data.added}</strong> مكون إلى قاعدة البيانات.
    </div>`;
    if (res.data.errors && res.data.errors.length) {
      html += `<p style="color:var(--danger);font-weight:600">أخطاء والتجاهلات (${res.data.errors.length}):</p><ul>`;
      res.data.errors.forEach(e => { html += `<li>${e}</li>`; });
      html += '</ul>';
    }
    html += `<a href="ingredients.php" class="btn btn-primary mt-12"><i class="fas fa-list"></i> عرض المكونات</a>
             <button class="btn btn-outline mt-12" onclick="clearAll()" style="margin-right:8px"><i class="fas fa-redo"></i> إضافة دفعة جديدة</button>`;
    resultDiv.innerHTML = html;

    // Clear form
    ['names','item-numbers'].forEach(id => document.getElementById(id).value = '');
    parsedItems = [];
    document.getElementById('preview-card').classList.add('hidden');
    document.getElementById('import-btn').style.display = 'none';
  } else {
    resultDiv.innerHTML = `<div style="color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> ${res.message}</div>`;
  }

  resultCard.scrollIntoView({ behavior: 'smooth' });
}

function clearAll() {
  ['names','item-numbers'].forEach(id => document.getElementById(id).value = '');
  parsedItems = [];
  document.getElementById('preview-card').classList.add('hidden');
  document.getElementById('result-card').classList.add('hidden');
  document.getElementById('import-btn').style.display = 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php adminFooter(); ?>
