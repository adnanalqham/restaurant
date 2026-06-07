<?php
// Connect without password (skip-grant-tables mode)
echo "Connecting to MySQL in skip-grant-tables mode...\n";
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=mysql;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected!\n";
    
    // Reset root password
    $password = '771603365';
    
    // MariaDB 10.4+ uses mysql_native_password
    try {
        $pdo->exec("FLUSH PRIVILEGES");
        $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY '$password'");
        $pdo->exec("ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$password'");
        $pdo->exec("ALTER USER 'root'@'::1' IDENTIFIED BY '$password'");
        echo "✅ Password reset for root@localhost\n";
    } catch (Exception $e) {
        echo "ALTER failed, trying UPDATE...\n";
        try {
            $pdo->exec("UPDATE mysql.user SET Password=PASSWORD('$password'), authentication_string=PASSWORD('$password'), plugin='mysql_native_password' WHERE User='root'");
            $pdo->exec("FLUSH PRIVILEGES");
            echo "✅ Password reset via UPDATE\n";
        } catch (Exception $e2) {
            echo "❌ Failed: " . $e2->getMessage() . "\n";
        }
    }
    
    // Show users
    $users = $pdo->query("SELECT User, Host, plugin FROM mysql.user")->fetchAll();
    echo "\nCurrent users:\n";
    foreach ($users as $u) {
        echo "  {$u['User']}@{$u['Host']} | plugin: {$u['plugin']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Cannot connect: " . $e->getMessage() . "\n";
}
