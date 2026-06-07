<?php
require_once __DIR__ . '/config/db.php';
requireAuth(); // Require user to be logged in
$user = getCurrentUser();
$role = $user['role'];

// Load the layout specific to the user's role
if (in_array($role, ['admin', 'accountant'])) {
    require_once __DIR__ . '/admin/_layout.php';
    adminHeader('إعدادات الحساب', 'profile');
} elseif ($role === 'cashier') {
    require_once __DIR__ . '/cashier/_layout.php';
    cashierHeader('إعدادات الحساب', 'profile');
} elseif ($role === 'waiter') {
    require_once __DIR__ . '/waiter/_layout.php';
    waiterHeader('إعدادات الحساب', 'profile');
} elseif (in_array($role, ['kitchen', 'chef', 'juice_bar'])) {
    require_once __DIR__ . '/station/_layout.php';
    stationHeader('إعدادات الحساب', 'profile');
} else {
    die("Unsupported Role");
}
?>

<div class="header-box">
  <h2><i class="fas fa-user-cog"></i> إعدادات الحساب</h2>
</div>

<div style="max-width: 500px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--shadow)">
  <h3 style="margin-top:0"><i class="fas fa-key"></i> تغيير كلمة المرور</h3>
  <hr style="margin: 15px 0; border: 0; border-top: 1px solid var(--border)">
  
  <form id="pwd-form" onsubmit="changePassword(event)">
    <div class="form-group">
      <label>كلمة المرور القديمة</label>
      <input type="password" id="old_pwd" class="form-control" required>
    </div>
    <div class="form-group">
      <label>كلمة المرور الجديدة</label>
      <input type="password" id="new_pwd" class="form-control" minlength="6" required>
    </div>
    <div class="form-group">
      <label>تأكيد كلمة المرور الجديدة</label>
      <input type="password" id="confirm_pwd" class="form-control" minlength="6" required>
    </div>
    <div style="margin-top: 20px">
      <button type="submit" class="btn btn-primary" style="width: 100%"><i class="fas fa-save"></i> حفظ التغييرات</button>
    </div>
  </form>
</div>

<script>
async function changePassword(e) {
  e.preventDefault();
  const old_pwd = document.getElementById('old_pwd').value;
  const new_pwd = document.getElementById('new_pwd').value;
  const confirm_pwd = document.getElementById('confirm_pwd').value;
  
  if (new_pwd !== confirm_pwd) {
    showToast('كلمة المرور الجديدة غير متطابقة', 'danger');
    return;
  }
  
  if (new_pwd.length < 6) {
    showToast('كلمة المرور الجديدة قصيرة جداً', 'danger');
    return;
  }
  
  const res = await apiCall('/api/profile.php?action=change_password', 'POST', {
    old_password: old_pwd,
    new_password: new_pwd
  });
  
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) {
    document.getElementById('pwd-form').reset();
  }
}
</script>

<?php
if (in_array($role, ['admin', 'accountant'])) {
    adminFooter();
} elseif ($role === 'cashier') {
    cashierFooter();
} elseif ($role === 'waiter') {
    if (function_exists('waiterFooter')) waiterFooter();
    else echo "</div></div></body></html>";
} elseif (in_array($role, ['kitchen', 'chef', 'juice_bar'])) {
    if (function_exists('stationFooter')) stationFooter();
    else echo "</div></div></body></html>";
}
?>
