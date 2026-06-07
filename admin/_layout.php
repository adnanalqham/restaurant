<?php
// Admin Layout Helper - included at the top of every admin page
require_once __DIR__ . '/../config/db.php';

// --- AUTH GATE ---
// Works for admin/accountant natively, and for any role with custom permissions
$user = getCurrentUser();
if (!$user) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$userPermsRaw = !empty($user['permissions']) ? json_decode($user['permissions'], true) : null;
$hasCustomPerms = is_array($userPermsRaw);
$isAdminRole = in_array($user['role'], ['admin', 'accountant', 'inventory_monitor', 'request_coordinator', 'warehouse_manager']);

// If user is neither an admin-role nor has custom permissions => kick out
if (!$isAdminRole && !$hasCustomPerms) {
    header('Location: ' . BASE_PATH . 'login.php?error=unauthorized');
    exit;
}

// --- BUILD ALLOWED KEYS ---
$allAdminKeys = ['dashboard', 'categories', 'items', 'wallets', 'orders', 'offers', 'users', 'reports', 'financial_revenues', 'printers', 'activity_log', 'item_audit_logs', 'settings', 'ingredients', 'inventory', 'inventory_report', 'sales_stats', 'item_times', 'inventory_requests_manage', 'item_stock', 'warehouses', 'direct_staff'];

if ($user['role'] === 'admin') {
    $allowedKeys = $allAdminKeys; // Admin sees everything
} elseif ($user['role'] === 'accountant') {
    $allowedKeys = ['dashboard', 'reports', 'financial_revenues', 'wallets', 'activity_log', 'item_audit_logs']; // Accountant base
} elseif ($user['role'] === 'inventory_monitor') {
    $allowedKeys = ['ingredients', 'inventory', 'inventory_report', 'sales_stats', 'item_times']; // Inventory monitor base
} elseif ($user['role'] === 'request_coordinator') {
    $allowedKeys = ['inventory_requests_manage'];
} elseif ($user['role'] === 'warehouse_manager') {
    $allowedKeys = ['inventory', 'inventory_requests_manage', 'inventory_report'];
} else {
    $allowedKeys = []; // Non-admin roles have no default admin access
}

// If custom permissions are set, they override role defaults entirely (allowing hiding items)
if ($hasCustomPerms) {
    $allowedKeys = $userPermsRaw;
}

// --- PAGE ACCESS GUARD ---
$pageKeyMap = [
    'index.php' => 'dashboard',
    'categories.php' => 'categories',
    'items.php' => 'items',
    'wallets.php' => 'wallets',
    'orders.php' => 'orders',
    'offers.php' => 'offers',
    'users.php' => 'users',
    'reports.php' => 'reports',
    'financial_revenues.php' => 'financial_revenues',
    'printers.php' => 'printers',
    'activity_log.php' => 'activity_log',
    'settings.php' => 'settings',
    'ingredients.php' => 'ingredients',
    'inventory.php' => 'inventory',
    'inventory_report.php' => 'inventory_report',
    'sales_stats.php' => 'sales_stats',
    'item_times.php' => 'item_times',
    'inventory_requests.php' => 'inventory_requests_manage',
    'item_audit_logs.php' => 'item_audit_logs',
    'item_stock.php' => 'item_stock',
    'warehouses.php' => 'warehouses',
    'direct_staff.php' => 'direct_staff',
];

$currentPage = basename($_SERVER['PHP_SELF']);
if (isset($pageKeyMap[$currentPage])) {
    $requiredKey = $pageKeyMap[$currentPage];
    $hasAccess = false;
    if ($currentPage === 'item_stock.php') {
        $hasAccess = in_array('item_stock', $allowedKeys) || in_array('stock_management', $allowedKeys) || in_array('show_stock', $allowedKeys);
    } else {
        $hasAccess = in_array($requiredKey, $allowedKeys);
    }
    
    if (!$hasAccess) {
        // Find the first page user IS allowed to see
        $fallback = BASE_PATH . 'profile.php';
        foreach ($pageKeyMap as $file => $key) {
            $ok = ($file === 'item_stock.php') ? (in_array('item_stock', $allowedKeys) || in_array('stock_management', $allowedKeys) || in_array('show_stock', $allowedKeys)) : in_array($key, $allowedKeys);
            if ($ok) {
                $fallback = BASE_PATH . 'admin/' . $file;
                break;
            }
        }
        header('Location: ' . $fallback);
        exit;
    }
}

$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';

function adminHeader(string $title, string $activePage = '')
{
    global $restName, $user, $allowedKeys, $pageKeyMap, $hasCustomPerms;

    $roleMap = [
        'admin' => 'مدير النظام',
        'accountant' => 'المحاسب المسؤول',
        'waiter' => 'ويتر / مباشر',
        'cashier' => 'كاشير / مشرف',
        'chef' => 'شيف المطبخ',
        'juice_bar' => 'شيف العصائر',
        'inventory_monitor' => 'مراقب المخزون',
    ];

    // All possible admin sidebar pages (in display order)
    $allPages = [
        'dashboard' => ['icon' => 'fas fa-chart-line', 'label' => 'لوحة التحكم', 'href' => BASE_PATH . 'admin/'],
        'categories' => ['icon' => 'fas fa-folder', 'label' => 'الفئات', 'href' => BASE_PATH . 'admin/categories.php'],
        'items' => ['icon' => 'fas fa-hamburger', 'label' => 'الأصناف', 'href' => BASE_PATH . 'admin/items.php'],
        'wallets' => ['icon' => 'fas fa-wallet', 'label' => 'المحافظ الرقمية', 'href' => BASE_PATH . 'admin/wallets.php'],
        'orders' => ['icon' => 'fas fa-clipboard-list', 'label' => 'إدارة الطلبات', 'href' => BASE_PATH . 'admin/orders.php'],
        'offers' => ['icon' => 'fas fa-gift', 'label' => 'العروض والتخفيضات', 'href' => BASE_PATH . 'admin/offers.php'],
        'users' => ['icon' => 'fas fa-users-cog', 'label' => 'المستخدمون', 'href' => BASE_PATH . 'admin/users.php'],
        'reports' => ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'التقارير المالية', 'href' => BASE_PATH . 'admin/reports.php'],
        'financial_revenues' => ['icon' => 'fas fa-cash-register', 'label' => 'الإيرادات اليومية', 'href' => BASE_PATH . 'admin/financial_revenues.php'],
        'activity_log' => ['icon' => 'fas fa-history', 'label' => 'مراقبة النظام', 'href' => BASE_PATH . 'admin/activity_log.php'],
        'item_audit_logs' => ['icon' => 'fas fa-search-dollar', 'label' => 'مراقبة الأسعار', 'href' => BASE_PATH . 'admin/item_audit_logs.php'],
        'printers' => ['icon' => 'fas fa-print', 'label' => 'إعدادات الطابعات', 'href' => BASE_PATH . 'admin/printers.php'],
        'direct_staff' => ['icon' => 'fas fa-user-tag', 'label' => 'المباشرون / الويترز', 'href' => BASE_PATH . 'admin/direct_staff.php'],
        'settings' => ['icon' => 'fas fa-cog', 'label' => 'الإعدادات', 'href' => BASE_PATH . 'admin/settings.php'],
        // ── Inventory Module ──────────────────────────────────────────────────
        'ingredients' => ['icon' => 'fas fa-boxes', 'label' => 'المكونات', 'href' => BASE_PATH . 'admin/ingredients.php', 'section' => 'inventory'],
        'warehouses' => ['icon' => 'fas fa-building', 'label' => 'اسماء المخازن', 'href' => BASE_PATH . 'admin/warehouses.php', 'section' => 'inventory'],
        'inventory' => ['icon' => 'fas fa-clipboard-check', 'label' => 'إدخال المخزون', 'href' => BASE_PATH . 'admin/inventory.php', 'section' => 'inventory'],
        'inventory_report' => ['icon' => 'fas fa-balance-scale', 'label' => 'تقرير المخزون', 'href' => BASE_PATH . 'admin/inventory_report.php', 'section' => 'inventory'],
        'sales_stats' => ['icon' => 'fas fa-chart-bar', 'label' => 'إحصائيات المبيعات', 'href' => BASE_PATH . 'admin/sales_stats.php', 'section' => 'inventory'],
        'item_times' => ['icon' => 'fas fa-stopwatch', 'label' => 'أوقات الأصناف', 'href' => BASE_PATH . 'admin/item_times.php', 'section' => 'system'],
        'inventory_requests_manage' => ['icon' => 'fas fa-tasks', 'label' => 'طلبات الصرف', 'href' => BASE_PATH . 'admin/inventory_requests.php', 'section' => 'inventory'],
        'item_stock' => ['icon' => 'fas fa-layer-group', 'label' => 'رصيد الأصناف', 'href' => BASE_PATH . 'admin/item_stock.php', 'section' => 'inventory'],
    ];

    // Base role links (shown FIRST - must match order in waiter/cashier/station layouts)
    $baseRoleLinks = [];
    if ($hasCustomPerms && $user['role'] !== 'admin' && $user['role'] !== 'accountant') {
        if ($user['role'] === 'waiter') {
            $baseRoleLinks = [
                'waiter_new' => ['icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد', 'href' => BASE_PATH . 'waiter/'],
                'waiter_orders' => ['icon' => 'fas fa-clipboard-list', 'label' => 'طلباتي', 'href' => BASE_PATH . 'waiter/orders.php'],
            ];
        } elseif ($user['role'] === 'cashier') {
            $baseRoleLinks = [
                'cashier_home' => ['icon' => 'fas fa-cash-register', 'label' => 'مراقبة الطلبات', 'href' => BASE_PATH . 'cashier/'],
                'waiter_new' => ['icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد', 'href' => BASE_PATH . 'waiter/'],
            ];
        } elseif (in_array($user['role'], ['chef', 'juice_bar'])) {
            $baseRoleLinks = [
                'station_home' => ['icon' => 'fas fa-utensils', 'label' => 'المطبخ', 'href' => BASE_PATH . 'station/'],
            ];
        }
        // inventory_monitor: handled by accordion sections (no baseRoleLinks needed)
    }

    // Filter admin pages by allowed keys
    $adminPages = [];
    foreach ($allPages as $key => $page) {
        $allowed = false;
        if ($key === 'item_stock') {
            $allowed = in_array('item_stock', $allowedKeys) || in_array('stock_management', $allowedKeys) || in_array('show_stock', $allowedKeys);
        } else {
            $allowed = in_array($key, $allowedKeys);
        }
        if ($allowed) {
            $adminPages[$key] = $page;
        }
    }
    // FIXED ORDER: Base role links first, then admin section pages
    $pages = array_merge($baseRoleLinks, $adminPages);

    // ── Sidebar accordion sections definition ────────────────────────────────
    $sidebarSections = [
        [
            'type' => 'standalone',
            'key' => 'dashboard',
            'icon' => 'fas fa-chart-line',
            'label' => 'لوحة التحكم',
        ],
        [
            'type' => 'group',
            'key' => 'menu_mgmt',
            'icon' => 'fas fa-utensils',
            'label' => 'القائمة',
            'children' => ['categories', 'items'],
        ],
        [
            'type' => 'group',
            'key' => 'transactions',
            'icon' => 'fas fa-receipt',
            'label' => 'العمليات',
            'children' => ['wallets', 'orders', 'offers'],
        ],
        [
            'type' => 'group',
            'key' => 'financial_mgmt',
            'icon' => 'fas fa-file-invoice-dollar',
            'label' => 'إدارة المالية',
            'children' => ['reports', 'financial_revenues'],
        ],
        [
            'type' => 'group',
            'key' => 'system_mgmt',
            'icon' => 'fas fa-cogs',
            'label' => 'إدارة النظام',
            'children' => ['users', 'activity_log', 'item_audit_logs', 'printers', 'direct_staff', 'item_times', 'settings'],
        ],
        [
            'type' => 'group',
            'key' => 'inventory_mgmt',
            'icon' => 'fas fa-boxes',
            'label' => 'إدارة المخزون',
            'children' => ['ingredients', 'warehouses', 'inventory', 'inventory_report', 'sales_stats', 'inventory_requests_manage', 'item_stock'],
        ],
    ];
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
                    <span class="logo-icon"><i class="fas fa-utensils"></i></span>
                    <h2><?= htmlspecialchars($restName) ?></h2>
                </div>

                <style>
                    /* Accordion nav */
                    .nav-group-header {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 10px 18px;
                        cursor: pointer;
                        color: rgba(255, 255, 255, .72);
                        font-size: .875rem;
                        font-weight: 600;
                        border-right: 3px solid transparent;
                        transition: all .2s;
                        user-select: none;
                    }

                    .nav-group-header:hover {
                        color: #fff;
                        background: rgba(255, 255, 255, .05);
                    }

                    .nav-group-header.open {
                        color: #fff;
                        border-right-color: var(--primary);
                        background: rgba(255, 255, 255, .06);
                    }

                    .nav-group-header .gh-icon {
                        font-size: 1.1rem;
                        min-width: 24px;
                        text-align: center;
                    }

                    .nav-group-header .gh-label {
                        flex: 1;
                    }

                    .nav-group-header .gh-chevron {
                        font-size: .7rem;
                        opacity: .5;
                        transition: transform .25s;
                        margin-right: auto;
                    }

                    .nav-group-header.open .gh-chevron {
                        transform: rotate(-180deg);
                        opacity: .9;
                    }

                    .nav-group-body {
                        overflow: hidden;
                        max-height: 0;
                        transition: max-height .3s ease;
                    }

                    .nav-group-body.open {
                        max-height: 400px;
                    }

                    .nav-group-body .nav-item {
                        padding-right: 36px;
                        font-size: .855rem;
                        color: rgba(255, 255, 255, .6);
                    }

                    .nav-group-body .nav-item:hover,
                    .nav-group-body .nav-item.active {
                        color: #fff;
                        background: rgba(255, 255, 255, .07);
                        border-right-color: var(--primary);
                    }
                </style>

                <nav class="sidebar-nav">
                    <?php
                    // ── Base role links (non-grouped: waiter, cashier special links) ─────
                    foreach ($baseRoleLinks as $key => $page):
                        ?>
                        <a href="<?= $page['href'] ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                            <span class="icon"><i class="<?= $page['icon'] ?>"></i></span>
                            <span class="nav-label"><?= $page['label'] ?></span>
                        </a>
                        <?php
                    endforeach;

                    // ── Accordion sections ────────────────────────────────────────────────
                    foreach ($sidebarSections as $section):
                        $sKey = $section['key'];

                        if ($section['type'] === 'standalone'):
                            if (!isset($adminPages[$sKey]))
                                continue;
                            $pg = $allPages[$sKey];
                            ?>
                            <a href="<?= $pg['href'] ?>" class="nav-item <?= $activePage === $sKey ? 'active' : '' ?>">
                                <span class="icon"><i class="<?= $pg['icon'] ?>"></i></span>
                                <span class="nav-label"><?= $pg['label'] ?></span>
                            </a>
                            <?php
                        else:
                            // Group — only render if user has access to at least one child
                            $visibleChildren = array_filter($section['children'], fn($c) => isset($adminPages[$c]));
                            if (empty($visibleChildren))
                                continue;
                            // Auto-open if active page is in this group
                            $isOpen = in_array($activePage, $section['children']);
                            ?>
                            <div class="nav-group-header <?= $isOpen ? 'open' : '' ?>" data-gkey="<?= $sKey ?>">
                                <span class="gh-icon"><i class="<?= $section['icon'] ?>"></i></span>
                                <span class="gh-label"><?= $section['label'] ?></span>
                                <i class="fas fa-chevron-down gh-chevron"></i>
                            </div>
                            <div class="nav-group-body <?= $isOpen ? 'open' : '' ?>" id="ng-<?= $sKey ?>">
                                <?php foreach ($visibleChildren as $cKey):
                                    $cp = $allPages[$cKey];
                                    ?>
                                    <a href="<?= $cp['href'] ?>" class="nav-item <?= $activePage === $cKey ? 'active' : '' ?>">
                                        <span class="icon"><i class="<?= $cp['icon'] ?>"></i></span>
                                        <span class="nav-label"><?= $cp['label'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php
                        endif;
                    endforeach;
                    ?>

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
                    <small style="opacity:.6"><?= $roleMap[$user['role']] ?? $user['role'] ?></small>
                </div>
            </aside>
            <div class="main-content">
                <div class="topbar">
                    <button id="sidebar-toggle" class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="topbar-title">
                        <?= htmlspecialchars($title) ?>
                    </span>
                    <div class="topbar-actions">
                        <button class="chat-fab" id="chat-fab"
                            style="position:relative;width:34px;height:34px;font-size:1.1rem;box-shadow:none;margin-top:30px;background:var(--bg);color:var(--secondary);border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-comment-dots"></i> <span class="unread-dot" style="display:none"></span>
                        </button>
                    </div>
                </div>
                <div class="content">
                    <?php
}

function adminFooter()
{
    ?>
                </div><!-- .content -->
            </div><!-- .main-content -->
        </div><!-- .layout -->

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
        <script src="<?= BASE_PATH ?>js/native-bridge.js?v=<?= time() ?>"></script>
        <script>
            // ── Sidebar Accordion ────────────────────────────────────────────
            document.querySelectorAll('.nav-group-header').forEach(function (hdr) {
                hdr.addEventListener('click', function () {
                    var key = hdr.getAttribute('data-gkey');
                    var body = document.getElementById('ng-' + key);
                    var open = hdr.classList.toggle('open');
                    if (body) body.classList.toggle('open', open);
                    try { localStorage.setItem('nav-grp-' + key, open ? '1' : '0'); } catch (e) { }
                });
            });
            // Restore localStorage state (PHP already opens active section, this adds user preference)
            document.querySelectorAll('.nav-group-header').forEach(function (hdr) {
                var key = hdr.getAttribute('data-gkey');
                var body = document.getElementById('ng-' + key);
                // Don't override server-side open (active page)
                if (hdr.classList.contains('open')) return;
                try {
                    if (localStorage.getItem('nav-grp-' + key) === '1') {
                        hdr.classList.add('open');
                        if (body) body.classList.add('open');
                    }
                } catch (e) { }
            });

            // ── Preserve Sidebar Scroll Position ─────────────────────────────
            (function () {
                var sidebar = document.querySelector('.sidebar');
                if (!sidebar) return;

                // Restore scroll position
                var scrollPos = sessionStorage.getItem('sidebar-scroll');
                if (scrollPos) {
                    sidebar.scrollTop = parseInt(scrollPos);
                }

                // Save scroll position on scroll (debounced slightly for performance)
                var scrollTimeout;
                sidebar.addEventListener('scroll', function () {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function () {
                        sessionStorage.setItem('sidebar-scroll', sidebar.scrollTop);
                    }, 100);
                });
            })();
        </script>
    </body>

    </html>
    <?php
}
