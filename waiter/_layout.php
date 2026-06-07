<?php
// Waiter Layout Helper
require_once __DIR__ . '/../config/db.php';
requireAuth(['waiter', 'cashier', 'admin', 'chef', 'juice_bar']);

$user = getCurrentUser();
$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';

function waiterHeader(string $title, string $activePage = '')
{
    global $restName, $user, $settings;

    // Dynamic sidebar based on role
    $pages = [];
    if (in_array($user['role'], ['cashier', 'admin'])) {
        $pages['monitoring'] = ['icon' => 'fas fa-cash-register', 'label' => 'مراقبة الطلبات', 'href' => BASE_PATH . 'cashier/'];
    }

    if (in_array($user['role'], ['waiter', 'cashier', 'admin'])) {
        $pages['new_order'] = ['icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد', 'href' => BASE_PATH . 'waiter/'];
        $pages['my_orders'] = ['icon' => 'fas fa-clipboard-list', 'label' => 'طلباتي', 'href' => BASE_PATH . 'waiter/orders.php'];
    }
    if (in_array($user['role'], ['chef', 'juice_bar'])) {
        $pages['station_back'] = ['icon' => 'fas fa-arrow-right', 'label' => 'العودة للمطبخ', 'href' => BASE_PATH . 'station/'];
    }
    $userPerms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    if (in_array($user['role'], ['admin']) || in_array('inventory_requests_create', $userPerms)) {
        $pages['inv_req'] = ['icon' => 'fas fa-boxes', 'label' => 'طلبات المخزن', 'href' => BASE_PATH . 'waiter/inventory_requests.php'];
    }

    if (in_array($user['role'], ['cashier', 'admin'])) {
        $pages['reports'] = ['icon' => 'fas fa-chart-pie', 'label' => 'التقارير اليومية', 'href' => BASE_PATH . 'cashier/reports.php'];
    }

    // Append custom admin section links
    if (!empty($user['permissions'])) {
        $userPerms = json_decode($user['permissions'], true) ?? [];
        $adminSectionDefs = [
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
        foreach ($adminSectionDefs as $key => $page) {
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
        <title>
            <?= htmlspecialchars($title) ?> |
            <?= htmlspecialchars($restName) ?>
        </title>
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
            window.POS_USER = <?= json_encode(['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'can_print' => (int) ($user['can_print'] ?? 1)]) ?>;
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
                        <span class="logo-icon"><i class="fas fa-utensils"></i></span>
                    <?php endif; ?>
                    <h2 style="font-size: 1.1rem; line-height: 1.4">
                        <?= htmlspecialchars($restName) ?>
                    </h2>
                </div>
                <nav class="sidebar-nav">
                    <?php foreach ($pages as $key => $page): ?>
                        <a href="<?= $page['href'] ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                            <span class="icon"><i class="<?= $page['icon'] ?>"></i></span>
                            <span class="nav-label">
                                <?= $page['label'] ?>
                            </span>
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
                    <span>
                        <?= $user['name'] ?>
                    </span>
                    <small style="opacity:.6"><?php
                    $roleMap = [
                        'admin' => 'مدير النظام',
                        'accountant' => 'المحاسب المسؤول',
                        'waiter' => 'ويتر / مباشر',
                        'cashier' => 'كاشير / مشرف',
                        'chef' => 'شيف المطبخ',
                        'juice_bar' => 'شيف العصائر'
                    ];
                    echo $roleMap[$user['role']] ?? $user['role'];
                    ?></small>
                </div>
            </aside>
            <div class="main-content">
                <div class="topbar">
                    <div class="flex gap-12">
                        <button id="sidebar-toggle" class="sidebar-toggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="topbar-title">
                            <?= htmlspecialchars($title) ?>
                        </span>
                    </div>
                    <div class="topbar-actions" style="display:flex; gap:12px; align-items:center;">
                        <a href="<?= BASE_PATH ?>waiter/orders.php?status=ready" id="ready-fab"
                            style="width:40px; height:40px; border-radius:50%; background:var(--warning); color:var(--text); display:flex; align-items:center; justify-content:center; text-decoration:none; position:relative; box-shadow:0 2px 5px rgba(0,0,0,0.1);"
                            title="الطلبات الجاهزة للاستلام">
                            <i class="fas fa-concierge-bell" style="font-size:1.1rem"></i>
                            <span id="ready-badge"
                                style="position:absolute; top:-4px; right:-4px; background:var(--danger); color:#fff; border-radius:50%; width:18px; height:18px; font-size:11px; font-weight:bold; display:none; align-items:center; justify-content:center;">0</span>
                        </a>
                        <button id="sound-fab" onclick="toggleNotifications()"
                            style="width:40px; height:40px; border-radius:50%; background:#f8f9fa; color:var(--primary); display:flex; align-items:center; justify-content:center; border:1px solid var(--border); position:relative; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.05);"
                            title="تفعيل/إلغاء التنبيهات الصوتية">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button id="chat-fab"
                            style="width:40px; height:40px; border-radius:50%; background:var(--secondary); color:#fff; display:flex; align-items:center; justify-content:center; border:none; position:relative; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1);"
                            title="المراسلة الداخلية">
                            <i class="fas fa-comment-dots" style="font-size:1.1rem"></i>
                            <span class="unread-dot"
                                style="position:absolute; top:-4px; right:-4px; background:var(--danger); border:2px solid #fff; border-radius:50%; width:14px; height:14px; display:none;"></span>
                        </button>
                    </div>
                </div>
                <div class="content"
                    style="padding: 0; display: flex; flex-direction: column; height: calc(100vh - var(--header-height)); overflow: hidden;">
                    <?php
}

function waiterFooter()
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
        <script>
            // Logic to handle the Ready Orders notification bell
            async function fetchReadyCount() {
                const badge = document.getElementById('ready-badge');
                if (!badge) return;
                const lastCount = parseInt(badge.textContent) || 0;
                // Fetch waiter's ready orders
                const res = await apiCall('/api/orders.php?status=ready&limit=100');
                if (res.success && res.data) {
                    const count = res.data.length;
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'flex';
                        // Play sound if count increased
                        if (count > lastCount) {
                            playNotificationSound(false); // Friendly ding-dong for waiter
                        }
                        // Play a small animation to attract attention
                        const fab = document.getElementById('ready-fab');
                        fab.style.animation = 'pulse 1s;';
                        setTimeout(() => { fab.style.animation = ''; }, 1000);
                    } else {
                        badge.style.display = 'none';
                        badge.textContent = '0';
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                // Wait for apiCall to be available
                setTimeout(() => {
                    if (typeof apiCall === 'function') {
                        fetchReadyCount();
                        if (typeof onSSE === 'function') {
                            onSSE('order_status_changed', fetchReadyCount);
                            onSSE('item_status_changed', fetchReadyCount);
                        }
                    }
                }, 500);
            });
        </script>
        <script src="<?= BASE_PATH ?>js/native-bridge.js?v=<?= time() ?>"></script>
    </body>

    </html>
    <?php
}
