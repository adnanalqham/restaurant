<?php
/**
 * أداة تصدير قائمة الطعام والمشروبات (المنيو)
 * من نظام إدارة المطعم (POS) إلى موقع فندق سبأ (Laravel)
 */

require_once __DIR__ . '/config/db.php';

// التأكد من تسجيل الدخول كمدير باستخدام الدالة المعتمدة في النظام
requireAuth(['admin']);

try {
    $db = getDB();

    // إعداد ترويسة الصفحة لتحميل الملف وليس عرضه
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="hotel_menu_export_' . date('Y-m-d') . '.sql"');

    echo "-- ==========================================================\n";
    echo "-- ملف التصدير الخاص بنقل المنيو من نظام الكاشير إلى الموقع\n";
    echo "-- تم التوليد في: " . date('Y-m-d H:i:s') . "\n";
    echo "-- ==========================================================\n\n";

    echo "-- أولاً: تنظيف الجداول القديمة في موقع الفندق لتجنب تكرار الأصناف\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "DELETE FROM `restaurant_foods`;\n";
    echo "DELETE FROM `restaurant_categories`;\n";
    echo "SET FOREIGN_KEY_CHECKS = 1;\n\n";

    // ---------------------------------------------------------
    // 1- تصدير الفئات (المرادف لـ Categories في الكاشير)
    // ---------------------------------------------------------
    $cats = $db->query("SELECT * FROM categories")->fetchAll();

    echo "-- ثانياً: إدراج الأقسام والفئات\n";
    if (!empty($cats)) {
        echo "INSERT INTO `restaurant_categories` (`id`, `name_ar`, `name_en`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES \n";
        $catValues = [];
        foreach ($cats as $c) {
            $id = (int)$c['id'];
            $nameAr = $db->quote($c['name_ar']);
            // إذا لم يكن هناك اسم إنجليزي، نستخدم الاسم العربي
            $nameEn = $db->quote(!empty($c['name_en']) ? $c['name_en'] : $c['name_ar']);
            $status = (int)$c['is_active'];
            $sortOrder = (int)$c['sort_order'];
            $createdAt = $db->quote($c['created_at']);
            
            $catValues[] = "($id, $nameAr, $nameEn, $status, $sortOrder, $createdAt, $createdAt)";
        }
        echo implode(",\n", $catValues) . ";\n\n";
    }

    // ---------------------------------------------------------
    // 2- تصدير الأصناف (المرادف لـ Foods في موقع الفندق)
    // ---------------------------------------------------------
    $items = $db->query("SELECT * FROM items")->fetchAll();

    echo "-- ثالثاً: إدراج الأصناف والمنتجات\n";
    if (!empty($items)) {
        echo "INSERT INTO `restaurant_foods` (`id`, `category_id`, `name_ar`, `name_en`, `price`, `discount`, `image`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES \n";
        $itemValues = [];
        foreach ($items as $i) {
            $id = (int)$i['id'];
            $catId = (int)$i['category_id'];
            $nameAr = $db->quote($i['name_ar']);
            $nameEn = $db->quote(!empty($i['name_en']) ? $i['name_en'] : $i['name_ar']);
            $price = (float)$i['price'];
            $discount = 0.00; // الخصم افتراضياً صفر لأن نظام POS لا يخزن خصم الصنف
            $image = !empty($i['image']) ? $db->quote($i['image']) : 'NULL';
            $status = (int)$i['is_available'];
            $sortOrder = (int)$i['sort_order'];
            $createdAt = $db->quote($i['created_at']);
            
            $itemValues[] = "($id, $catId, $nameAr, $nameEn, $price, $discount, $image, $status, $sortOrder, $createdAt, $createdAt)";
        }
        echo implode(",\n", $itemValues) . ";\n\n";
    }

    echo "-- اكتمل التصدير بنجاح!\n";

} catch (Exception $e) {
    // إرجاع خطأ بداخل التعليق في الداتابيز لكي لا يخرب الملف
    echo "-- حدث خطأ أثناء محاولة التصدير: " . $e->getMessage() . "\n";
}
