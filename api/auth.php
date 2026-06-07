<?php
require_once __DIR__ . '/../config/db.php';
startSession();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkAuth();
        break;
    default:
        jsonResponse(false, null, 'إجراء غير معروف', 400);
}
function handleLogin() {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        jsonResponse(false, null, 'يرجى إدخال اسم المستخدم وكلمة المرور', 400);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.name as role 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.username = ? AND u.is_active = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        if (isApiRequest()) {
            jsonResponse(false, null, 'اسم المستخدم أو كلمة المرور غير صحيحة', 401);
        } else {
            header('Location: ' . BASE_PATH . 'login.php?error=login_failed');
            exit;
        }
    }

    if ($remember) {
        $lifetime = 30 * 24 * 60 * 60; // 30 days
        ini_set('session.gc_maxlifetime', $lifetime);
    }

    $_SESSION['user'] = [
        'id'         => $user['id'],
        'name'       => $user['name'],
        'username'   => $user['username'],
        'role'       => $user['role'],
        'role_id'    => $user['role_id'],
        'can_print'  => $user['can_print'],
        'print_type' => $user['print_type'] ?? 'network',
        'permissions' => $user['permissions'] ?? null,
    ];

    // Determine redirect based on role (Relative to BASE_PATH)
    $redirectMap = [
        'admin'     => BASE_PATH . 'admin/',
        'waiter'    => BASE_PATH . 'waiter/',
        'cashier'   => BASE_PATH . 'cashier/',
        'chef'      => BASE_PATH . 'station/',
        'juice_bar' => BASE_PATH . 'station/',
    ];
    $redirect = $redirectMap[$user['role']] ?? BASE_PATH . 'login.php';
    if (!empty($user['permissions'])) {
        $redirect = BASE_PATH . 'admin/';
    }

    if (isApiRequest()) {
        jsonResponse(true, ['redirect' => $redirect, 'user' => $_SESSION['user']], 'مرحباً ' . $user['name']);
    } else {
        header('Location: ' . $redirect);
        exit;
    }
}

function handleLogout() {
    startSession();
    session_destroy();
    jsonResponse(true, ['redirect' => BASE_PATH . 'login.php'], 'تم تسجيل الخروج');
}

function checkAuth() {
    $user = getCurrentUser();
    if ($user) {
        jsonResponse(true, $user);
    } else {
        jsonResponse(false, null, 'غير مسجل الدخول', 401);
    }
}
