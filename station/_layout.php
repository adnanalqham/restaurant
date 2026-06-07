<?php
// Station Layout Helper
require_once __DIR__ . '/../config/db.php';
requireAuth(['chef', 'juice_bar']);

$user = getCurrentUser();
$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';

// Fetch category permissions globally for stations
$allowedCats = $user ? getUserCategoryPermissions($user['id']) : [];

function stationHeader(string $title, string $activePage = '')
{
    global $restName, $user, $settings, $allowedCats;

    $roleLabels = ['chef' => 'شيف المطبخ', 'juice_bar' => 'شيف العصائر'];
    $roleIcons = ['chef' => 'fas fa-hat-chef', 'juice_bar' => 'fas fa-blender'];
    $roleLabel = ($roleLabels[$user['role']] ?? 'محطة');
    $roleIcon = ($roleIcons[$user['role']] ?? 'fas fa-terminal');

    $pages = [
        'index' => ['icon' => 'fas fa-utensils-spoon', 'label' => 'الطلبات الواردة', 'href' => BASE_PATH . 'station/'],
    ];

    $userPerms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    if (in_array($user['role'], ['admin']) || in_array('inventory_requests_create', $userPerms)) {
        $pages['inventory_requests'] = ['icon' => 'fas fa-boxes', 'label' => 'طلبات المخزن', 'href' => BASE_PATH . 'waiter/inventory_requests.php'];
    }

    // Add custom admin permissions for Station Roles
    if (!empty($user['permissions'])) {
        $userPerms = json_decode($user['permissions'], true) ?? [];
        $adminPages = [
            'dashboard' => ['icon' => 'fas fa-chart-line', 'label' => 'لوحة التحكم', 'href' => BASE_PATH . 'admin/'],
            'categories' => ['icon' => 'fas fa-folder', 'label' => 'الفئات', 'href' => BASE_PATH . 'admin/categories.php'],
            'items' => ['icon' => 'fas fa-hamburger', 'label' => 'الأصناف', 'href' => BASE_PATH . 'admin/items.php'],
            'wallets' => ['icon' => 'fas fa-wallet', 'label' => 'المحافظ الرقمية', 'href' => BASE_PATH . 'admin/wallets.php'],
            'orders' => ['icon' => 'fas fa-clipboard-list', 'label' => 'إدارة الطلبات', 'href' => BASE_PATH . 'admin/orders.php'],
            'users' => ['icon' => 'fas fa-users-cog', 'label' => 'المستخدمون', 'href' => BASE_PATH . 'admin/users.php'],
            'reports' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'التقارير المالية', 'href' => BASE_PATH . 'admin/reports.php'],
            'activity_log' => ['icon' => 'fas fa-eye', 'label' => 'مراقبة النظام', 'href' => BASE_PATH . 'admin/activity_log.php'],
            'settings' => ['icon' => 'fas fa-cog', 'label' => 'الإعدادات', 'href' => BASE_PATH . 'admin/settings.php'],
        ];
        foreach ($adminPages as $key => $page) {
            if (in_array($key, $userPerms)) {
                $pages['admin_' . $key] = $page;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($restName) ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.css?v=<?= time() ?>">
        <style>
            body {
                animation: fadeIn .1s ease-out;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0.8;
                }

                to {
                    opacity: 1;
                }
            }

            .nav-item.active {
                background: rgba(255, 255, 255, .1) !important;
                border-right-color: var(--primary) !important;
                color: #fff !important;
            }
        </style>
        <script>
            window.POS_BASE_PATH = '<?= BASE_PATH ?>';
            window.POS_USER = <?= json_encode(['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]) ?>;
            window.ALLOWED_CATS = <?= json_encode(array_map('intval', $allowedCats)) ?>;
        </script>
    </head>

    <body>
        <div class="layout">
            <aside class="sidebar">
                <button class="sidebar-close">✕</button>
                <div class="sidebar-logo">
                    <?php if (!empty($settings['logo'])): ?>
                        <img src="<?= BASE_PATH . str_replace(' ', '%20', ltrim($settings['logo'], '/')) ?>" alt="Logo"
                            style="height: 48px; width: 48px; object-fit: contain; margin-bottom: 10px; border-radius: 8px">
                    <?php else: ?>
                        <span class="logo-icon"><i class="<?= $roleIcon ?>"></i></span>
                    <?php endif; ?>
                    <h2 style="font-size: 1rem; line-height: 1.4"><?= htmlspecialchars($restName) ?><br><small
                            style="font-size: .8rem; opacity: .7"><?= $roleLabel ?></small></h2>
                </div>
                <nav class="sidebar-nav">
                    <?php foreach ($pages as $key => $page): ?>
                        <a href="<?= $page['href'] ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                            <span class="icon"><i class="<?= $page['icon'] ?>"></i></span>
                            <span class="nav-label"><?= $page['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                    <div style="margin-top:auto"></div>
                    <a href="<?= BASE_PATH ?>profile.php" class="nav-item <?= $activePage === 'profile' ? 'active' : '' ?>"
                        style="color:var(--text-main)">
                        <span class="icon"><i class="fas fa-user-cog"></i></span>
                        <span class="nav-label">إعدادات الحساب</span>
                    </a>
                    <a href="<?= BASE_PATH ?>logout.php" class="nav-item" style="color:#e74c3c">
                        <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="nav-label">تسجيل الخروج</span>
                    </a>
                </nav>
                <div class="sidebar-user">
                    <span><?= $user['name'] ?></span>
                    <small style="opacity:.6"><?= $roleLabel ?></small>
                </div>
            </aside>
            <div class="main-content">
                <div class="topbar">
                    <div class="flex gap-12">
                        <button id="sidebar-toggle" class="sidebar-toggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="topbar-title"><?= htmlspecialchars($title) ?></span>
                    </div>
                    <div class="topbar-actions" style="display:flex;gap:12px;align-items:center">
                        <span id="active-count" class="badge badge-warning" style="font-size:.9rem;padding:6px 14px"></span>
                        <button id="sound-fab" onclick="toggleNotifications()"
                            style="width:36px; height:36px; border-radius:50%; background:#f8f9fa; color:var(--primary); display:flex; align-items:center; justify-content:center; border:1px solid var(--border); cursor:pointer;"
                            title="تفعيل/إلغاء التنبيهات الصوتية">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="chat-fab" id="chat-fab"
                            style="position:relative;width:36px;height:36px;font-size:1rem;box-shadow:none">
                            <i class="fas fa-comment-dots"></i> <span class="unread-dot" style="display:none"></span>
                        </button>
                    </div>
                </div>
                <div class="content">
                    <?php
}

function stationFooter()
{
    ?>
                </div>
            </div>
        </div>

        <!-- Chat Panel -->
        <div class="chat-panel hidden" id="chat-panel">
            <div class="chat-header">
                <i class="fas fa-comments"></i> المراسلة الداخلية
                <button id="chat-close"
                    style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.1rem">✕</button>
            </div>
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-input-area">
                <input type="text" class="chat-input" id="chat-input" placeholder="اكتب رسالة...">
                <button class="chat-send-btn" id="chat-send">➤</button>
            </div>
        </div>

        <div id="toast-container"></div>
        <script src="<?= BASE_PATH ?>assets/js/app.js?v=<?= time() ?>"></script>
    </body>

    </html>
    <?php
}
