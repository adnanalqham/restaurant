<?php
require_once __DIR__ . '/config/db.php';
startSession();

// If already logged in, redirect
$user = getCurrentUser();
if ($user) {
    $map = [
        'admin' => 'admin/',
        'accountant' => 'admin/',
        'waiter' => 'waiter/',
        'cashier' => 'cashier/',
        'chef' => 'station/',
        'juice_bar' => 'station/',
        'waiter_juice' => 'station/',
        'inventory_monitor' => 'admin/inventory.php',
        'warehouse_manager' => 'admin/inventory.php',
        'request_coordinator' => 'admin/inventory_requests.php'
    ];
    if (!empty($user['permissions'])) {
        header('Location: ' . APP_URL . '/admin/');
        exit;
    }
    if ($user['role'] === 'accountant') {
        header('Location: ' . APP_URL . '/admin/reports.php');
        exit;
    }
    $redirectPath = $map[$user['role']] ?? 'login.php?error=unauthorized';
    if ($redirectPath === 'login.php?error=unauthorized') {
        // Unmap roles should not create an infinite loop. They should be forced to logout or see error.
        session_destroy();
        header('Location: ' . APP_URL . '/' . $redirectPath);
        exit;
    }
    header('Location: ' . APP_URL . '/' . $redirectPath);
    exit;
}

$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول |
        <?= htmlspecialchars($restName) ?>
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="<?= BASE_PATH ?>images/Sheba%20Hotel%20(3)%20(3).png" type="image/x-icon">
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?= BASE_PATH . str_replace(' ', '%20', ltrim($settings['logo'], '/')) ?>" alt="Logo"
                        style="height: 80px; width: 80px; object-fit: contain; margin-bottom: 20px; border-radius: 12px">
                <?php else: ?>
                    <span class="icon">🍽️</span>
                <?php endif; ?>
                <h1>
                    <?= htmlspecialchars($restName) ?>
                </h1>
                <p>نظام إدارة المطعم</p>
            </div>

            <?php if ($error === 'login_failed'): ?>
                <div class="alert alert-danger">اسم المستخدم أو كلمة المرور غير صحيحة.</div>
            <?php elseif ($error === 'unauthorized'): ?>
                <div class="alert alert-danger">ليس لديك صلاحية للوصول إلى هذه الصفحة.</div>
            <?php endif; ?>

            <form id="login-form" method="POST" action="<?= BASE_PATH ?>api/auth.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label class="form-label" for="username">اسم المستخدم</label>
                    <input type="text" id="username" name="username" class="form-control"
                        placeholder="أدخل اسم المستخدم" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <div style="position:relative">
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="أدخل كلمة المرور" required autocomplete="current-password"
                            style="padding-left:45px">
                        <span id="toggle-password"
                            style="position:absolute;left:15px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);z-index:5">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <div class="form-group"
                    style="display:flex;align-items:center;gap:10px;margin-bottom:20px;cursor:pointer">
                    <input type="checkbox" id="remember" name="remember" style="width:18px;height:18px;cursor:pointer">
                    <label for="remember" style="margin:0;cursor:pointer;font-size:.9rem;color:var(--text-muted)">تذكرني
                        على هذا الجهاز</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
                    🔐 تسجيل الدخول
                </button>
            </form>

            <p style="text-align:center;margin-top:20px;font-size:.8rem;color:var(--text-muted)">
                جميع الحقوق محفوظة لصالح شركة ترمنال © <?= date('Y') ?>
            </p>
        </div>
    </div>

    <script>
        // Password Toggle Logic
        document.getElementById('toggle-password').addEventListener('click', function () {
            const pwdInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Optional: Simple visual feedback on submit
        document.getElementById('login-form').addEventListener('submit', function () {
            const btn = document.getElementById('login-btn');
            btn.disabled = true;
            btn.innerHTML = '⏳ جاري الدخول...';
        });
    </script>
</body>

</html>