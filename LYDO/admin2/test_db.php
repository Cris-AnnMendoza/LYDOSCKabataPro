<?php
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=local_youth_development_db;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $admin = $pdo->query("SELECT full_name, email, role FROM admin_users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "<h2 style='color:green'>✓ Database connected successfully!</h2>";
    echo "<p>Admin found: <strong>{$admin['full_name']}</strong> ({$admin['email']}) — {$admin['role']}</p>";
    echo "<p><a href='index.php'>→ Go to Admin Login</a></p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>✗ Connection failed</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<p>Check that MySQL is running in XAMPP Control Panel.</p>";
}
