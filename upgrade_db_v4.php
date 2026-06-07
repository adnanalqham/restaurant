<?php
/**
 * Database Upgrade v4: Inventory Management System
 * Creates tables for: ingredients, item_ingredients,
 * inventory_departments, inventory_transactions
 * Adds role: inventory_monitor
 */
require_once __DIR__ . '/config/db.php';

echo "<h2>ترقية قاعدة البيانات (v4) - نظام إدارة المخزون</h2><hr>";

try {
    $db = getDB();
    $db->exec("SET FOREIGN_KEY_CHECKS=0;");

    // ─── 1. ingredients ───────────────────────────────────────────────────────
    echo "إنشاء جدول المكونات...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS ingredients (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_number VARCHAR(30)  DEFAULT NULL,
        name              VARCHAR(200) NOT NULL,
        unit              VARCHAR(50)  NOT NULL DEFAULT 'gram'
                          COMMENT 'gram|kg|piece|liter|ml|cup|tablespoon|other',
        notes             TEXT         DEFAULT NULL,
        created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ جدول المكونات جاهز.<br>";

    // ─── 2. item_ingredients ──────────────────────────────────────────────────
    echo "إنشاء جدول مكونات الأصناف...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS item_ingredients (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        item_id              INT          NOT NULL,
        ingredient_id        INT          NOT NULL,
        quantity_per_portion DECIMAL(10,3) NOT NULL DEFAULT 0,
        notes                VARCHAR(255)  DEFAULT NULL,
        UNIQUE KEY uq_item_ingredient (item_id, ingredient_id),
        INDEX idx_item_id (item_id),
        INDEX idx_ingredient_id (ingredient_id),
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ جدول مكونات الأصناف جاهز.<br>";

    // ─── 3. inventory_departments ─────────────────────────────────────────────
    echo "إنشاء جدول الأقسام...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_departments (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        is_active   TINYINT(1)   DEFAULT 1,
        sort_order  INT          DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insert default departments if empty
    $deptCount = (int)$db->query("SELECT COUNT(*) FROM inventory_departments")->fetchColumn();
    if ($deptCount === 0) {
        $db->exec("INSERT INTO inventory_departments (name, description, sort_order) VALUES
            ('المطبخ الرئيسي', 'قسم الوجبات الرئيسية', 1),
            ('الحلويات', 'قسم الحلويات والمعجنات', 2),
            ('الكافي', 'قسم المشروبات والقهوة', 3),
            ('الشيشة', 'قسم الشيشة والمعسل', 4)");
        echo "✅ تم إضافة الأقسام الافتراضية (مطبخ، حلويات، كافي، شيشة).<br>";
    }
    echo "✅ جدول الأقسام جاهز.<br>";

    // ─── 4. inventory_transactions ────────────────────────────────────────────
    echo "إنشاء جدول إدخالات المخزون...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        department_id   INT            NOT NULL,
        ingredient_id   INT            NOT NULL,
        quantity        DECIMAL(10,3)  NOT NULL DEFAULT 0,
        transaction_date DATE          NOT NULL,
        notes           VARCHAR(500)   DEFAULT NULL,
        created_by      INT            DEFAULT NULL,
        created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (transaction_date),
        INDEX idx_dept (department_id),
        INDEX idx_ingredient (ingredient_id),
        FOREIGN KEY (department_id)  REFERENCES inventory_departments(id) ON DELETE CASCADE,
        FOREIGN KEY (ingredient_id)  REFERENCES ingredients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ جدول إدخالات المخزون جاهز.<br>";

    // ─── 5. Add inventory_monitor role ────────────────────────────────────────
    echo "إضافة دور مراقب المخزون...<br>";
    $roleCheck = $db->query("SHOW COLUMNS FROM roles LIKE 'name'")->fetch();
    if ($roleCheck) {
        // roles table exists — check if role exists
        $exists = $db->prepare("SELECT id FROM roles WHERE name = 'inventory_monitor'");
        $exists->execute();
        if (!$exists->fetch()) {
            $db->exec("INSERT INTO roles (name) VALUES ('inventory_monitor')");
            echo "✅ تم إضافة دور 'inventory_monitor' لجدول الأدوار.<br>";
        } else {
            echo "⚠️ الدور موجود بالفعل.<br>";
        }
    } else {
        echo "⚠️ جدول الأدوار لا يحتوي على عمود 'name' — الدور يُضاف كقيمة في users.role مباشرة.<br>";
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "<br><h3 style='color:green'>✅ اكتملت الترقية بنجاح! نظام المخزون جاهز.</h3>";
    echo "<p><a href='admin/ingredients.php'>الذهاب لإدارة المكونات</a> | <a href='admin/inventory.php'>إدخال المخزون</a></p>";

} catch (Exception $e) {
    $db->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "<h3 style='color:red'>خطأ: " . $e->getMessage() . "</h3>";
}
