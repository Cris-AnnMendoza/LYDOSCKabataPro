<?php
/**
 * Create MySQL Admin User
 * Run this: http://localhost/LYDO/lydo-system/database/create_admin_mysql.php
 */

$mysqli = new mysqli('127.0.0.1', 'root', '', 'local_youth_development_db');

if ($mysqli->connect_error) {
    die("❌ Connection failed: " . $mysqli->connect_error);
}

echo "<h1>👤 Creating Admin User for MySQL</h1>";

// Password: Admin@1234
$password = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);

// Delete existing admin
$mysqli->query("DELETE FROM admin_users WHERE email = 'admin@lydo.gov.ph'");

// Insert new admin
$stmt = $mysqli->prepare("INSERT INTO admin_users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
$fullName = 'Super Administrator';
$email = 'admin@lydo.gov.ph';
$role = 'super_admin';
$isActive = 1;

$stmt->bind_param('ssssi', $fullName, $email, $password, $role, $isActive);

if ($stmt->execute()) {
    echo "<h2>✅ Admin Created Successfully!</h2>";
    echo "<div style='background:#e8f5e9;padding:20px;border-radius:10px;border:2px solid #4caf50'>";
    echo "<h3>🔐 Admin Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> admin@lydo.gov.ph</p>";
    echo "<p><strong>Password:</strong> Admin@1234</p>";
    echo "</div>";
    echo "<br><p><a href='../admin2/index.php' style='background:#1565c0;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block'>Login to Admin Panel</a></p>";
} else {
    echo "<h2>❌ Error: " . $stmt->error . "</h2>";
}

$stmt->close();
$mysqli->close();
