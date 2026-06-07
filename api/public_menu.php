<?php
/**
 * api/public_menu.php
 * Public JSON API — no authentication required.
 * Returns all active categories with their available items.
 * Used by the hotel website to display the restaurant menu in real-time.
 *
 * GET https://shebahotel.com/restaurant0/api/public_menu.php
 */

// ── CORS: allow hotel website to fetch this ───────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();

    // Image base URL — uses UPLOAD_URL defined in config/db.php
    // e.g. https://shebahotel.com/restaurant0/uploads/
    $imageBase = rtrim(UPLOAD_URL, '/') . '/';

    // ── Fetch active categories ───────────────────────────────────────────────
    $catStmt = $db->query("
        SELECT id, name_ar, name_en, icon, sort_order
        FROM categories
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Fetch available items ─────────────────────────────────────────────────
    $itemStmt = $db->query("
        SELECT id, category_id, name_ar, name_en, price, description_ar, description_en, image, sort_order
        FROM items
        WHERE is_available = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Group items by category ───────────────────────────────────────────────
    $itemsByCategory = [];
    foreach ($items as $item) {
        $catId = $item['category_id'];
        $item['image_url'] = $item['image']
            ? $imageBase . $item['image']
            : null;
        unset($item['image']); // keep response clean
        $itemsByCategory[$catId][] = $item;
    }

    // ── Build response ────────────────────────────────────────────────────────
    $result = [];
    foreach ($categories as $cat) {
        $cat['items'] = $itemsByCategory[$cat['id']] ?? [];
        if (!empty($cat['items'])) {
            $result[] = $cat;
        }
    }

    echo json_encode([
        'success'    => true,
        'updated_at' => date('Y-m-d H:i:s'),
        'categories' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
