<?php
// ============================================================
// RESTAURANT POS - صفحة التشخيص الشاملة
// تحقق من جميع مكونات النظام
// ============================================================

// Allow access only from localhost or with secret key
$secretKey = 'diag2026';
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$hasKey = (($_GET['key'] ?? '') === $secretKey);

if (!$isLocalhost && !$hasKey) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;text-align:center;margin-top:100px;"><h2>🔒 غير مصرح</h2><p>أضف <code>?key=diag2026</code> إلى الرابط للوصول</p></div>');
}

// Start timing
$startTime = microtime(true);
$results = [];

// ─────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────
function check($name, $status, $message, $detail = '') {
    return compact('name', 'status', 'message', 'detail');
}

// ─────────────────────────────────────────────────────────────
// 1. PHP Environment
// ─────────────────────────────────────────────────────────────
$phpChecks = [];

// PHP Version
$phpVer = phpversion();
$phpOk = version_compare($phpVer, '7.4', '>=');
$phpChecks[] = check('إصدار PHP', $phpOk ? 'ok' : 'error',
    "PHP $phpVer", $phpOk ? 'الإصدار مناسب' : 'يُنصح بـ PHP 7.4 أو أحدث');

// Required Extensions
$requiredExt = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session', 'curl', 'gd', 'zip'];
foreach ($requiredExt as $ext) {
    $loaded = extension_loaded($ext);
    $phpChecks[] = check("امتداد $ext", $loaded ? 'ok' : 'error',
        $loaded ? "✓ مُحمَّل" : "✗ غير مُحمَّل",
        $loaded ? '' : "قم بتفعيل extension=php_$ext في php.ini");
}

// Memory Limit
$memLimit = ini_get('memory_limit');
$phpChecks[] = check('حد الذاكرة', 'info', $memLimit, 'القيمة الحالية لـ memory_limit');

// Max Execution Time
$maxExec = ini_get('max_execution_time');
$phpChecks[] = check('وقت التنفيذ الأقصى', 'info', "{$maxExec} ثانية", '');

// Upload Max Filesize
$uploadMax = ini_get('upload_max_filesize');
$phpChecks[] = check('حجم الرفع الأقصى', 'info', $uploadMax, '');

// Error Reporting
$displayErrors = ini_get('display_errors');
$phpChecks[] = check('عرض الأخطاء', $displayErrors ? 'warning' : 'ok',
    $displayErrors ? 'مفعّل (تحذير في الإنتاج)' : 'مُعطَّل',
    $displayErrors ? 'يُنصح بإيقافه في الاستضافة الحية' : '');

$results['php'] = $phpChecks;

// ─────────────────────────────────────────────────────────────
// 2. Database Connection
// ─────────────────────────────────────────────────────────────
$dbChecks = [];
$pdo = null;

try {
    require_once __DIR__ . '/config/db.php';

    // Test connection
    $connStart = microtime(true);
    $pdo = getDB();
    $connTime = round((microtime(true) - $connStart) * 1000, 2);

    $dbChecks[] = check('الاتصال بقاعدة البيانات', 'ok',
        "✓ متصل في {$connTime}ms", "الخادم: " . DB_HOST . " | القاعدة: " . DB_NAME);

    // MySQL Version
    $mysqlVer = $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbChecks[] = check('إصدار MySQL', 'ok', $mysqlVer, '');

    // Charset
    $charset = $pdo->query("SELECT @@character_set_database")->fetchColumn();
    $charsetOk = stripos($charset, 'utf8') !== false;
    $dbChecks[] = check('ترميز القاعدة', $charsetOk ? 'ok' : 'warning',
        $charset, $charsetOk ? '' : 'يُنصح باستخدام utf8mb4');

    // Check required tables
    $requiredTables = [
        'users', 'orders', 'order_items', 'items', 'categories',
        'tables', 'settings', 'printers', 'wallets', 'offers',
        'print_queue', 'sse_events', 'activity_log'
    ];
    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $detail = '';
        if ($exists) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $detail = "$count سجل";
            } catch (Exception $e) {
                $detail = 'خطأ في القراءة';
            }
        }
        $dbChecks[] = check("جدول: $table", $exists ? 'ok' : 'error',
            $exists ? "✓ موجود ($detail)" : "✗ غير موجود",
            $exists ? '' : 'الجدول مفقود من قاعدة البيانات');
    }

    // Check for wallet_id column in orders
    try {
        $walletCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'wallet_id'")->fetch();
        $dbChecks[] = check('عمود wallet_id في orders', $walletCol ? 'ok' : 'warning',
            $walletCol ? '✓ موجود' : '✗ غير موجود',
            $walletCol ? '' : 'قد تحتاج لتشغيل upgrade_db_v4.php');
    } catch (Exception $e) {}

    // Check print_queue table structure
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM print_queue")->fetchAll(PDO::FETCH_COLUMN);
        $dbChecks[] = check('بنية print_queue', 'info',
            implode(', ', $cols), 'أعمدة جدول طابور الطباعة');
    } catch (Exception $e) {}

    // Pending print jobs
    try {
        $pendingPrint = $pdo->query("SELECT COUNT(*) FROM print_queue WHERE status='pending'")->fetchColumn();
        $statusP = $pendingPrint > 20 ? 'warning' : 'ok';
        $dbChecks[] = check('طوابير الطباعة المعلقة', $statusP,
            "$pendingPrint طلب معلق",
            $pendingPrint > 20 ? 'يوجد عدد كبير من طلبات الطباعة المعلقة' : '');
    } catch (Exception $e) {
        $dbChecks[] = check('طوابير الطباعة', 'warning', 'لا يمكن الفحص', $e->getMessage());
    }

    // SSE Events backlog
    try {
        $sseCount = $pdo->query("SELECT COUNT(*) FROM sse_events WHERE processed = 0")->fetchColumn();
        $sseStatus = $sseCount > 50 ? 'warning' : 'ok';
        $dbChecks[] = check('أحداث SSE غير المعالجة', $sseStatus,
            "$sseCount حدث", $sseCount > 50 ? 'تراكم كبير في الأحداث' : '');
    } catch (Exception $e) {
        $dbChecks[] = check('أحداث SSE', 'info', 'غير متاح', '');
    }

    // Active orders
    try {
        $activeOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
        $dbChecks[] = check('الطلبات النشطة الحالية', 'info', "$activeOrders طلب نشط", '');
    } catch (Exception $e) {}

    // Today's orders
    try {
        $todayOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $todaySales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn();
        $dbChecks[] = check("طلبات اليوم", 'info', "$todayOrders طلب | إجمالي: " . number_format($todaySales, 2) . " ريال", '');
    } catch (Exception $e) {}

} catch (Exception $e) {
    $dbChecks[] = check('الاتصال بقاعدة البيانات', 'error',
        '✗ فشل الاتصال', $e->getMessage());
}

$results['db'] = $dbChecks;

// ─────────────────────────────────────────────────────────────
// 3. File System & Permissions
// ─────────────────────────────────────────────────────────────
$fsChecks = [];

$rootDir = __DIR__;
$checkDirs = [
    '' => 'المجلد الرئيسي',
    '/uploads' => 'مجلد الرفع (uploads)',
    '/images' => 'مجلد الصور',
    '/assets' => 'مجلد الأصول',
    '/config' => 'مجلد الإعدادات',
    '/api' => 'مجلد API',
    '/cashier' => 'مجلد الكاشير',
    '/admin' => 'مجلد الأدمن',
    '/waiter' => 'مجلد الويتر',
    '/station' => 'مجلد المحطة',
];

foreach ($checkDirs as $path => $label) {
    $fullPath = $rootDir . $path;
    $exists = file_exists($fullPath);
    $readable = $exists && is_readable($fullPath);
    $writable = $exists && is_writable($fullPath);
    $status = !$exists ? 'error' : ($writable ? 'ok' : ($readable ? 'warning' : 'error'));
    $msg = !$exists ? '✗ غير موجود' : ($writable ? '✓ قراءة وكتابة' : ($readable ? '⚠ قراءة فقط' : '✗ لا يمكن الوصول'));
    $fsChecks[] = check($label, $status, $msg, $fullPath);
}

// Check critical files
$criticalFiles = [
    '/config/db.php' => 'ملف الإعدادات الرئيسي',
    '/api/orders.php' => 'API الطلبات',
    '/api/auth.php' => 'API المصادقة',
    '/api/print_queue.php' => 'API طابور الطباعة',
    '/api/print_proxy.php' => 'API وكيل الطباعة',
    '/api/reports.php' => 'API التقارير',
    '/cashier/index.php' => 'صفحة الكاشير',
    '/admin/index.php' => 'صفحة الأدمن',
    '/login.php' => 'صفحة تسجيل الدخول',
    '/print_receipt.php' => 'صفحة الطباعة',
];

foreach ($criticalFiles as $path => $label) {
    $fullPath = $rootDir . $path;
    $exists = file_exists($fullPath);
    $size = $exists ? round(filesize($fullPath) / 1024, 1) . ' KB' : '-';
    $fsChecks[] = check($label, $exists ? 'ok' : 'error',
        $exists ? "✓ موجود ($size)" : "✗ غير موجود", $fullPath);
}

$results['fs'] = $fsChecks;

// ─────────────────────────────────────────────────────────────
// 4. Settings & Configuration
// ─────────────────────────────────────────────────────────────
$settingsChecks = [];
if ($pdo) {
    try {
        $settings = getSettings();
        $importantSettings = [
            'restaurant_name' => 'اسم المطعم',
            'currency' => 'العملة',
            'currency_position' => 'موضع العملة',
            'tax_rate' => 'نسبة الضريبة',
            'receipt_footer' => 'تذييل الفاتورة',
        ];
        foreach ($importantSettings as $key => $label) {
            $val = $settings[$key] ?? null;
            $settingsChecks[] = check($label, $val !== null ? 'ok' : 'warning',
                $val !== null ? htmlspecialchars(substr($val, 0, 50)) : 'غير محدد', "المفتاح: $key");
        }

        // Users check
        $users = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
        $userSummary = implode(' | ', array_map(fn($u) => "{$u['role']}: {$u['cnt']}", $users));
        $settingsChecks[] = check('المستخدمون', 'info', $userSummary ?: 'لا يوجد مستخدمون', '');

        // Printers
        try {
            $printers = $pdo->query("SELECT * FROM printers")->fetchAll();
            foreach ($printers as $p) {
                $settingsChecks[] = check("طابعة: {$p['name']}", 'info',
                    "النوع: {$p['type']} | IP: " . ($p['ip_address'] ?? 'N/A'), '');
            }
            if (empty($printers)) {
                $settingsChecks[] = check('الطابعات', 'warning', 'لا توجد طابعات مسجلة', '');
            }
        } catch (Exception $e) {}

        // Wallets
        try {
            $wallets = $pdo->query("SELECT * FROM wallets WHERE is_active = 1")->fetchAll();
            $settingsChecks[] = check('المحافظ المفعّلة', count($wallets) > 0 ? 'ok' : 'warning',
                count($wallets) . ' محفظة: ' . implode(', ', array_column($wallets, 'name')), '');
        } catch (Exception $e) {}

    } catch (Exception $e) {
        $settingsChecks[] = check('الإعدادات', 'error', 'فشل قراءة الإعدادات', $e->getMessage());
    }
}

$results['settings'] = $settingsChecks;

// ─────────────────────────────────────────────────────────────
// 5. API Endpoints Quick Test
// ─────────────────────────────────────────────────────────────
$apiChecks = [];

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = defined('BASE_PATH') ? BASE_PATH : '/restaurant/';
$baseUrl = $protocol . $host . rtrim($basePath, '/');

$apiEndpoints = [
    '/api/auth.php' => 'مصادقة المستخدم',
    '/api/orders.php' => 'الطلبات',
    '/api/items.php' => 'الأصناف',
    '/api/categories.php' => 'التصنيفات',
    '/api/print_queue.php' => 'طابور الطباعة',
    '/api/reports.php' => 'التقارير',
    '/api/wallets.php' => 'المحافظ',
];

foreach ($apiEndpoints as $endpoint => $label) {
    $filePath = $rootDir . $endpoint;
    $exists = file_exists($filePath);
    $size = $exists ? round(filesize($filePath) / 1024, 1) . ' KB' : 0;
    $apiChecks[] = check("API: $label", $exists ? 'ok' : 'error',
        $exists ? "✓ ({$size} KB)" : "✗ الملف غير موجود",
        $baseUrl . $endpoint);
}

$results['api'] = $apiChecks;

// ─────────────────────────────────────────────────────────────
// 6. Recent Errors (PHP Error Log)
// ─────────────────────────────────────────────────────────────
$errorLogChecks = [];
$possibleLogs = [
    ini_get('error_log'),
    __DIR__ . '/error.log',
    __DIR__ . '/../error.log',
    'C:/xampp3/apache/logs/error.log',
    'C:/xampp/apache/logs/error.log',
];

$logFound = false;
foreach ($possibleLogs as $logPath) {
    if ($logPath && file_exists($logPath) && is_readable($logPath)) {
        $logFound = true;
        $size = filesize($logPath);
        $errorLogChecks[] = check('ملف سجل الأخطاء', 'info',
            "موجود (" . round($size/1024, 1) . " KB)", $logPath);

        // Read last 50 lines
        $lines = [];
        $fp = fopen($logPath, 'r');
        if ($fp) {
            fseek($fp, max(0, $size - 10000));
            while (!feof($fp)) {
                $line = fgets($fp);
                if ($line !== false) $lines[] = trim($line);
            }
            fclose($fp);
            $lines = array_filter($lines);
            $lastLines = array_slice($lines, -30);

            // Count error types
            $errorCount = count(array_filter($lastLines, fn($l) => stripos($l, 'PHP Fatal') !== false || stripos($l, 'PHP Error') !== false));
            $warningCount = count(array_filter($lastLines, fn($l) => stripos($l, 'PHP Warning') !== false));
            $noticeCount = count(array_filter($lastLines, fn($l) => stripos($l, 'PHP Notice') !== false));

            $errorLogChecks[] = check('في آخر 30 سطر', $errorCount > 0 ? 'error' : ($warningCount > 0 ? 'warning' : 'ok'),
                "أخطاء: $errorCount | تحذيرات: $warningCount | ملاحظات: $noticeCount", '');
        }
        break;
    }
}

if (!$logFound) {
    $errorLogChecks[] = check('ملف سجل الأخطاء', 'warning', 'لم يُعثر على الملف', 'تحقق من إعدادات error_log في php.ini');
}

$results['errorlog'] = $errorLogChecks;

// ─────────────────────────────────────────────────────────────
// 7. Session Test
// ─────────────────────────────────────────────────────────────
$sessionChecks = [];
try {
    if (function_exists('startSession')) startSession();
    $sessionStatus = session_status();
    $statusMap = [PHP_SESSION_DISABLED => 'معطّل', PHP_SESSION_NONE => 'لم يبدأ', PHP_SESSION_ACTIVE => 'نشط'];
    $sessionChecks[] = check('حالة الجلسة', $sessionStatus === PHP_SESSION_ACTIVE ? 'ok' : 'warning',
        $statusMap[$sessionStatus] ?? 'غير معروف', 'Session ID: ' . (session_id() ?: 'N/A'));

    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $sessionWritable = is_writable($sessionPath);
    $sessionChecks[] = check('مسار حفظ الجلسات', $sessionWritable ? 'ok' : 'error',
        $sessionWritable ? "✓ قابل للكتابة" : "✗ غير قابل للكتابة", $sessionPath);

    $sessionLifetime = ini_get('session.gc_maxlifetime');
    $sessionChecks[] = check('مدة الجلسة', 'info',
        round($sessionLifetime / 86400, 1) . ' يوم (' . $sessionLifetime . ' ثانية)', '');

    // Current user
    $currentUser = function_exists('getCurrentUser') ? getCurrentUser() : ($_SESSION['user'] ?? null);
    if ($currentUser) {
        $sessionChecks[] = check('المستخدم الحالي', 'ok',
            "✓ مسجّل: {$currentUser['name']} ({$currentUser['role']})", '');
    } else {
        $sessionChecks[] = check('المستخدم الحالي', 'warning', 'لا يوجد مستخدم مسجّل', '');
    }
} catch (Exception $e) {
    $sessionChecks[] = check('الجلسة', 'error', 'خطأ في فحص الجلسة', $e->getMessage());
}
$results['session'] = $sessionChecks;

// ─────────────────────────────────────────────────────────────
// Calculate Summary
// ─────────────────────────────────────────────────────────────
$totalChecks = 0;
$okCount = 0;
$warningCount = 0;
$errorCount = 0;

foreach ($results as $group) {
    foreach ($group as $r) {
        $totalChecks++;
        if ($r['status'] === 'ok') $okCount++;
        elseif ($r['status'] === 'warning') $warningCount++;
        elseif ($r['status'] === 'error') $errorCount++;
    }
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);
$systemHealth = $errorCount === 0 ? ($warningCount === 0 ? 'excellent' : 'good') : ($errorCount <= 2 ? 'fair' : 'poor');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 تشخيص النظام - مطعم POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: #2e3347;
            --text: #e8eaf0;
            --text-muted: #8b91a8;
            --ok: #00d68f;
            --ok-bg: rgba(0,214,143,0.1);
            --warning: #ffb347;
            --warning-bg: rgba(255,179,71,0.1);
            --error: #ff5f5f;
            --error-bg: rgba(255,95,95,0.1);
            --info: #5b9cf6;
            --info-bg: rgba(91,156,246,0.1);
            --accent: #6c63ff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1a1d27 0%, #12142a 100%);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .header-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .header h1 { font-size: 1.5rem; font-weight: 700; }
        .header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 2px; }
        .header-meta {
            display: flex; gap: 12px; flex-wrap: wrap;
        }
        .meta-pill {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .meta-pill span { color: var(--text); font-weight: 600; }

        /* ── Health Banner ── */
        .health-banner {
            margin: 24px 32px;
            border-radius: 16px;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .health-banner.excellent { background: var(--ok-bg); border: 1px solid rgba(0,214,143,0.3); }
        .health-banner.good { background: rgba(255,179,71,0.07); border: 1px solid rgba(255,179,71,0.25); }
        .health-banner.fair { background: rgba(255,95,95,0.07); border: 1px solid rgba(255,95,95,0.2); }
        .health-banner.poor { background: var(--error-bg); border: 1px solid rgba(255,95,95,0.4); }

        .health-title { font-size: 1.3rem; font-weight: 700; }
        .health-banner.excellent .health-title { color: var(--ok); }
        .health-banner.good .health-title { color: var(--warning); }
        .health-banner.fair .health-title { color: #ff8a50; }
        .health-banner.poor .health-title { color: var(--error); }

        .health-stats { display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-item {
            text-align: center;
            padding: 12px 18px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            min-width: 90px;
        }
        .stat-number { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        .stat-ok .stat-number { color: var(--ok); }
        .stat-warning .stat-number { color: var(--warning); }
        .stat-error .stat-number { color: var(--error); }
        .stat-info .stat-number { color: var(--info); }

        /* ── Content ── */
        .content { padding: 0 32px 32px; }
        .section {
            margin-bottom: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            user-select: none;
        }
        .section-header:hover { background: rgba(108,99,255,0.08); }
        .section-icon { font-size: 1.2rem; }
        .section-title { font-size: 1rem; font-weight: 700; flex: 1; }
        .section-badge {
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        .badge-ok { background: var(--ok-bg); color: var(--ok); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-error { background: var(--error-bg); color: var(--error); }
        .badge-info { background: var(--info-bg); color: var(--info); }
        .chevron { color: var(--text-muted); transition: transform 0.2s; font-size: 0.8rem; }
        .section.collapsed .chevron { transform: rotate(-90deg); }
        .section.collapsed .section-body { display: none; }

        /* ── Check Rows ── */
        .section-body { padding: 8px 0; }
        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 20px;
            border-bottom: 1px solid rgba(46,51,71,0.5);
            transition: background 0.15s;
        }
        .check-row:last-child { border-bottom: none; }
        .check-row:hover { background: rgba(255,255,255,0.02); }
        .check-icon {
            width: 28px; height: 28px; min-width: 28px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700;
        }
        .icon-ok { background: var(--ok-bg); color: var(--ok); }
        .icon-warning { background: var(--warning-bg); color: var(--warning); }
        .icon-error { background: var(--error-bg); color: var(--error); }
        .icon-info { background: var(--info-bg); color: var(--info); }
        .check-content { flex: 1; min-width: 0; }
        .check-name { font-size: 0.88rem; font-weight: 600; color: var(--text); }
        .check-message {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 2px;
            word-break: break-all;
        }
        .check-detail {
            font-size: 0.75rem;
            color: rgba(139,145,168,0.7);
            margin-top: 3px;
            font-family: monospace;
            word-break: break-all;
        }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
            font-size: 0.82rem;
            border-top: 1px solid var(--border);
            margin: 0 32px;
        }

        /* ── Refresh Button ── */
        .refresh-btn {
            position: fixed;
            bottom: 24px;
            left: 24px;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 24px;
            font-family: 'Cairo', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 24px rgba(108,99,255,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(108,99,255,0.5);
        }

        @media (max-width: 600px) {
            .header, .content { padding: 16px; }
            .health-banner { margin: 16px; }
            .check-row { padding: 10px 14px; }
        }

        /* Spinning animation */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinning { animation: spin 1s linear infinite; display: inline-block; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-title">
        <div class="header-icon">🔍</div>
        <div>
            <h1>تشخيص نظام المطعم</h1>
            <p>فحص شامل لجميع مكونات النظام</p>
        </div>
    </div>
    <div class="header-meta">
        <div class="meta-pill">⏱ <span><?= $elapsed ?>ms</span></div>
        <div class="meta-pill">🕐 <span><?= date('H:i:s') ?></span></div>
        <div class="meta-pill">📅 <span><?= date('Y-m-d') ?></span></div>
        <div class="meta-pill">🌐 <span><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?></span></div>
        <?php if (defined('DB_NAME')): ?>
        <div class="meta-pill">🗄 <span><?= DB_NAME ?></span></div>
        <?php endif; ?>
    </div>
</div>

<!-- Health Banner -->
<div class="health-banner <?= $systemHealth ?>">
    <div>
        <div class="health-title">
            <?php
            $healthLabels = [
                'excellent' => '✅ النظام يعمل بشكل ممتاز!',
                'good' => '⚠️ النظام يعمل مع بعض التحذيرات',
                'fair' => '🔶 يوجد بعض المشاكل تحتاج انتباهاً',
                'poor' => '❌ يوجد أخطاء حرجة تحتاج إصلاح فوري',
            ];
            echo $healthLabels[$systemHealth];
            ?>
        </div>
        <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">
            إجمالي <?= $totalChecks ?> فحص تم تنفيذها
        </div>
    </div>
    <div class="health-stats">
        <div class="stat-item stat-ok">
            <div class="stat-number"><?= $okCount ?></div>
            <div class="stat-label">✓ ناجح</div>
        </div>
        <div class="stat-item stat-warning">
            <div class="stat-number"><?= $warningCount ?></div>
            <div class="stat-label">⚠ تحذير</div>
        </div>
        <div class="stat-item stat-error">
            <div class="stat-number"><?= $errorCount ?></div>
            <div class="stat-label">✗ خطأ</div>
        </div>
        <div class="stat-item stat-info">
            <div class="stat-number"><?= $totalChecks - $okCount - $warningCount - $errorCount ?></div>
            <div class="stat-label">ℹ معلومة</div>
        </div>
    </div>
</div>

<!-- Content -->
<div class="content">

<?php
$sections = [
    'php' => ['icon' => '🐘', 'title' => 'بيئة PHP'],
    'db' => ['icon' => '🗄️', 'title' => 'قاعدة البيانات'],
    'fs' => ['icon' => '📁', 'title' => 'نظام الملفات والصلاحيات'],
    'settings' => ['icon' => '⚙️', 'title' => 'الإعدادات والتهيئة'],
    'api' => ['icon' => '🔌', 'title' => 'نقاط API'],
    'errorlog' => ['icon' => '📋', 'title' => 'سجل الأخطاء'],
    'session' => ['icon' => '🔐', 'title' => 'الجلسات والمصادقة'],
];

$iconMap = ['ok' => '✓', 'warning' => '!', 'error' => '✗', 'info' => 'i'];
$iconClassMap = ['ok' => 'icon-ok', 'warning' => 'icon-warning', 'error' => 'icon-error', 'info' => 'icon-info'];

foreach ($sections as $key => $sec):
    $checks = $results[$key] ?? [];
    $sectionErrors = count(array_filter($checks, fn($c) => $c['status'] === 'error'));
    $sectionWarnings = count(array_filter($checks, fn($c) => $c['status'] === 'warning'));
    $badgeClass = $sectionErrors > 0 ? 'badge-error' : ($sectionWarnings > 0 ? 'badge-warning' : 'badge-ok');
    $badgeText = $sectionErrors > 0 ? "$sectionErrors خطأ" : ($sectionWarnings > 0 ? "$sectionWarnings تحذير" : 'جيد');
    // Auto-collapse sections with no errors/warnings
    $collapsed = ($sectionErrors === 0 && $sectionWarnings === 0 && $key !== 'db') ? 'collapsed' : '';
?>
<div class="section <?= $collapsed ?>" id="section-<?= $key ?>">
    <div class="section-header" onclick="toggleSection('<?= $key ?>')">
        <span class="section-icon"><?= $sec['icon'] ?></span>
        <span class="section-title"><?= $sec['title'] ?></span>
        <span class="section-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
        <span class="chevron">▾</span>
    </div>
    <div class="section-body">
        <?php foreach ($checks as $check): ?>
        <div class="check-row">
            <div class="check-icon <?= $iconClassMap[$check['status']] ?>">
                <?= $iconMap[$check['status']] ?>
            </div>
            <div class="check-content">
                <div class="check-name"><?= htmlspecialchars($check['name']) ?></div>
                <div class="check-message"><?= htmlspecialchars($check['message']) ?></div>
                <?php if (!empty($check['detail'])): ?>
                <div class="check-detail"><?= htmlspecialchars($check['detail']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

</div><!-- /content -->

<!-- Footer -->
<div class="footer">
    🔍 تم إنشاء هذا التقرير في <?= date('Y-m-d H:i:s') ?> | وقت التنفيذ: <?= $elapsed ?>ms
    <br><small style="color: rgba(139,145,168,0.5); margin-top: 4px; display:block;">
        للأمان: احذف هذا الملف أو أضف حماية إضافية قبل النشر على الاستضافة
    </small>
</div>

<!-- Refresh Button -->
<a href="?<?= $hasKey ? 'key='.$secretKey : '' ?>" class="refresh-btn">
    <span id="refresh-icon">🔄</span> تحديث التشخيص
</a>

<script>
function toggleSection(key) {
    const section = document.getElementById('section-' + key);
    section.classList.toggle('collapsed');
}

// Auto-expand sections with errors
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.section').forEach(sec => {
        const badge = sec.querySelector('.section-badge');
        if (badge && (badge.classList.contains('badge-error') || badge.classList.contains('badge-warning'))) {
            sec.classList.remove('collapsed');
        }
    });
});

// Refresh button spin animation
document.querySelector('.refresh-btn').addEventListener('click', function(e) {
    document.getElementById('refresh-icon').classList.add('spinning');
});
</script>

</body>
</html>
