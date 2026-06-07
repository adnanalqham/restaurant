<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إعدادات النظام', 'settings');
?>

<div class="settings-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(350px, 1fr));gap:20px;margin-top:20px">
  
  <!-- System Reset Section -->
  <div class="card" style="border:1px solid #fee2e2">
    <div class="card-header" style="background:#fee2e2;color:#991b1b">
      <h3 style="margin:0"><i class="fas fa-exclamation-triangle"></i> تصفير النظام (حذف العمليات)</h3>
    </div>
    <div class="card-body" style="padding:20px">
      <p style="color:#666;font-size:.9rem;line-height:1.6">
        سيقوم هذا الإجراء بحذف جميع <strong>الطلبات</strong> و <strong>تفاصيل الطلبات</strong> بشكل نهائي.
        <br>
        <span style="color:#e74c3c;font-weight:700">تنبيه: يتطلب هذا الإجراء كلمة مرور المدير!</span>
      </p>
      <ul style="font-size:.85rem;color:#777;margin:15px 0;padding-right:20px">
        <li>حذف سجل المبيعات والتقارير.</li>
        <li>تصفير عداد الطلبات.</li>
        <li><span style="color:var(--success)">استثناء:</span> ستبقى الأصناف، الفئات، المستخدمون، المحافظ، و <strong>سجل مراقبة النظام</strong> كما هي.</li>
      </ul>
      <button class="btn btn-danger btn-block" onclick="confirmResetSystem()" style="margin-top:10px">
        <i class="fas fa-trash-alt"></i> تنفيذ تصفير النظام الآن
      </button>
    </div>
  </div>

  <!-- Database Backup Section -->
  <div class="card">
    <div class="card-header">
      <h3 style="margin:0"><i class="fas fa-database"></i> النسخة الاحتياطية</h3>
    </div>
    <div class="card-body" style="padding:20px">
      <p style="color:#666;font-size:.9rem;line-height:1.6">
        قم بتحميل نسخة كاملة من قاعدة البيانات (SQL) بجميع محتوياتها لتخزينها في مكان آمن.
      </p>
      <div style="background:var(--bg);padding:15px;border-radius:8px;margin:15px 0;border:1px dashed var(--border)">
        <small style="display:block;margin-bottom:5px;opacity:.7">آخر عملية نسخ احتياطي:</small>
        <strong>غير معروف</strong>
      </div>
      <button class="btn btn-primary btn-block" onclick="downloadBackup()">
        <i class="fas fa-download"></i> تحميل نسخة احتياطية (SQL)
      </button>
    </div>
  </div>

  <!-- Print Server Section -->
  <div class="card">
    <div class="card-header" style="background:#e0f2fe;color:#0369a1">
      <h3 style="margin:0"><i class="fas fa-print"></i> إعدادات خادم الطباعة</h3>
    </div>
    <div class="card-body" style="padding:20px">
      <div class="form-group mb-3">
        <label class="form-label">عنوان خادم الطباعة (Local URL)</label>
        <input type="text" class="form-control" id="setting-print_server_url" value="<?= htmlspecialchars($settings['print_server_url'] ?? 'http://localhost:3000') ?>">
        <small class="text-muted">مثلاً: http://192.168.1.100:3000</small>
      </div>
      <div class="form-group mb-3">
        <label class="form-label">مفتاح الأمان (API Key)</label>
        <input type="password" class="form-control" id="setting-print_server_key" value="<?= htmlspecialchars($settings['print_server_key'] ?? '') ?>">
      </div>
      <div style="display:flex; flex-direction:column; gap:10px; margin:15px 0">
        <label style="display:flex; align-items:center; gap:10px; cursor:pointer">
          <input type="checkbox" id="setting-auto_print_kitchen" <?= ($settings['auto_print_kitchen'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>طباعة تلقائية للمطبخ عند الطلب</span>
        </label>
        <label style="display:flex; align-items:center; gap:10px; cursor:pointer">
          <input type="checkbox" id="setting-auto_print_receipt" <?= ($settings['auto_print_receipt'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>طباعة تلقائية للفاتورة عند الدفع</span>
        </label>
      </div>
      <button class="btn btn-block" style="background:#0369a1; color:#fff" onclick="savePrintSettings()">
        <i class="fas fa-save"></i> حفظ إعدادات الطباعة
      </button>
    </div>
  </div>

  <!-- Kitchen App API Key Section -->
  <div class="card" style="border:1px solid #d1fae5">
    <div class="card-header" style="background:#d1fae5;color:#065f46">
      <h3 style="margin:0"><i class="fas fa-mobile-alt"></i> مفتاح تطبيق المطبخ (Kitchen App API Key)</h3>
    </div>
    <div class="card-body" style="padding:20px">
      <p style="color:#666;font-size:.9rem;line-height:1.6">
        هذا المفتاح يستخدمه تطبيق <strong>Sheba Print Service</strong> للاتصال بالنظام وسحب الطلبات تلقائياً عبر البلوتوث.
      </p>
      <div class="form-group mb-3">
        <label class="form-label">API Key الخاص بتطبيق المطبخ</label>
        <div style="display:flex;gap:10px;align-items:center">
          <input type="text" class="form-control" id="setting-kitchen_api_key"
            value="<?= htmlspecialchars($settings['kitchen_api_key'] ?? '') ?>"
            placeholder="اضغط 'توليد مفتاح' لإنشاء مفتاح آمن"
            style="font-family:monospace;letter-spacing:1px">
          <button class="btn btn-sm" style="background:#065f46;color:#fff;white-space:nowrap" onclick="generateApiKey()">
            <i class="fas fa-key"></i> توليد مفتاح
          </button>
        </div>
        <small class="text-muted">أدخل هذا المفتاح في إعدادات التطبيق في حقل "API Key"</small>
      </div>
      <div style="background:#f0fdf4;border:1px dashed #86efac;border-radius:8px;padding:12px;margin:10px 0;font-size:.85rem;color:#166534">
        <strong><i class="fas fa-info-circle"></i> رابط النظام للتطبيق (Base URL):</strong><br>
        <code style="font-size:.9rem;word-break:break-all"><?= 'https://' . $_SERVER['HTTP_HOST'] . BASE_PATH ?></code>
      </div>
      <button class="btn btn-block" style="background:#065f46;color:#fff" onclick="saveKitchenApiKey()">
        <i class="fas fa-save"></i> حفظ مفتاح التطبيق
      </button>
    </div>
  </div>

  <!-- Stock Tracking Section -->
  <div class="card" style="border:1px solid #dbeafe">
    <div class="card-header" style="background:#dbeafe;color:#1e40af">
      <h3 style="margin:0"><i class="fas fa-layer-group"></i> رصيد الأصناف</h3>
    </div>
    <div class="card-body" style="padding:20px">
      <p style="color:#666;font-size:.9rem;line-height:1.6">
        عند التفعيل، لا يمكن بيع أي صنف بكمية أكبر من رصيده المتوفر.<br>
        <span style="color:#1e40af;font-weight:600">إيقاف الخيار يعيد النظام للعمل بدون قيود كما كان.</span>
      </p>
      <div style="display:flex;align-items:center;gap:16px;background:var(--bg);padding:16px;border-radius:10px;margin:15px 0;border:1px dashed var(--border)">
        <label class="toggle-switch" style="position:relative;display:inline-block;width:56px;height:28px;cursor:pointer">
          <input type="checkbox" id="setting-enable_stock_tracking"
            <?= ($settings['enable_stock_tracking'] ?? '0') === '1' ? 'checked' : '' ?>
            style="opacity:0;width:0;height:0"
            onchange="saveStockSetting()">
          <span style="
            position:absolute;top:0;left:0;right:0;bottom:0;
            background: <?= ($settings['enable_stock_tracking'] ?? '0') === '1' ? 'var(--success)' : 'var(--danger)' ?>;
            border-radius:28px;
            transition:.3s;
          " id="stock-toggle-bg"></span>
          <span style="
            position:absolute;top:3px;left:3px;
            width:22px;height:22px;
            background:#fff;border-radius:50%;
            transition:.3s;
            transform: <?= ($settings['enable_stock_tracking'] ?? '0') === '1' ? 'translateX(28px)' : 'translateX(0)' ?>;
          " id="stock-toggle-dot"></span>
        </label>
        <span id="stock-toggle-label" style="font-weight:700;font-size:1rem">
          <?= ($settings['enable_stock_tracking'] ?? '0') === '1' ? '<span style="color:var(--success)">مُفعَّل — البيع محدود بالرصيد</span>' : '<span style="color:var(--danger)">مُعطَّل — البيع بدون قيود</span>' ?>
        </span>
      </div>
      <small class="text-muted"><i class="fas fa-info-circle"></i> يمكن إدارة أرصدة الأصناف من: <a href="<?= BASE_PATH ?>admin/item_stock.php">إدارة رصيد الأصناف</a></small>
    </div>
  </div>

</div>

<script>
async function confirmResetSystem() {
  if (!confirm('⚠️ هل أنت متأكد تماماً من رغبتك في تصفير النظام؟\nسيتم حذف جميع سجلات المبيعات والطلبات نهائياً!')) return;
  
  const pwd = prompt('لتأكيد الحذف، يرجى إدخال كلمة مرور المدير الخاصة بك:');
  if (!pwd) return;

  showToast('جاري البدء في تصفير النظام...', 'info');
  const res = await apiCall('/api/admin_actions.php?action=reset_system', 'POST', { password: pwd });
  if (res.success) {
    showToast(res.message, 'success');
    setTimeout(() => location.reload(), 2000);
  } else {
    showToast(res.message, 'danger');
  }
}

async function savePrintSettings() {
  const data = {
    print_server_url: document.getElementById('setting-print_server_url').value,
    print_server_key: document.getElementById('setting-print_server_key').value,
    auto_print_kitchen: document.getElementById('setting-auto_print_kitchen').checked ? '1' : '0',
    auto_print_receipt: document.getElementById('setting-auto_print_receipt').checked ? '1' : '0'
  };

  showToast('جاري حفظ إعدادات الطباعة...', 'info');
  const res = await apiCall('/api/admin_actions.php?action=update_settings', 'POST', data);
  if (res.success) {
    showToast('تم حفظ إعدادات الطباعة بنجاح', 'success');
  } else {
    showToast(res.message || 'فشل الحفظ', 'danger');
  }
}

function generateApiKey() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let key = 'sheba-';
  for (let i = 0; i < 32; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
  document.getElementById('setting-kitchen_api_key').value = key;
}

async function saveKitchenApiKey() {
  const key = document.getElementById('setting-kitchen_api_key').value.trim();
  if (!key) { showToast('يرجى توليد أو إدخال مفتاح API', 'warning'); return; }
  const res = await apiCall('/api/admin_actions.php?action=update_settings', 'POST', { kitchen_api_key: key });
  if (res.success) {
    showToast('✅ تم حفظ مفتاح التطبيق. أدخله في التطبيق الآن.', 'success');
  } else {
    showToast(res.message || 'فشل الحفظ', 'danger');
  }
}

function downloadBackup() {
  showToast('جاري تجهيز النسخة الاحتياطية...', 'info');
  window.location.href = window.POS_BASE_PATH + 'api/admin_actions.php?action=backup_db';
}

async function saveStockSetting() {
  const enabled = document.getElementById('setting-enable_stock_tracking').checked;
  const bg  = document.getElementById('stock-toggle-bg');
  const dot = document.getElementById('stock-toggle-dot');
  const lbl = document.getElementById('stock-toggle-label');

  if (enabled) {
    bg.style.background  = 'var(--success)';
    dot.style.transform  = 'translateX(28px)';
    lbl.innerHTML = '<span style="color:var(--success)">مُفعَّل — البيع محدود بالرصيد</span>';
  } else {
    bg.style.background  = 'var(--danger)';
    dot.style.transform  = 'translateX(0)';
    lbl.innerHTML = '<span style="color:var(--danger)">مُعطَّل — البيع بدون قيود</span>';
  }

  const res = await apiCall('/api/admin_actions.php?action=update_settings', 'POST', {
    enable_stock_tracking: enabled ? '1' : '0'
  });
  showToast(res.success ? (enabled ? '✅ تم تفعيل نظام رصيد الأصناف' : '⛔ تم تعطيل نظام رصيد الأصناف') : res.message,
            res.success ? 'success' : 'danger');
}
</script>

<?php adminFooter(); ?>
