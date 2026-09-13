<?php
require_once __DIR__ . '/../shared/config.php';

$newPassword = 'Admin@1234';
$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = db()->prepare("UPDATE admin_users SET password = ? WHERE email = 'admin@lydo.gov.ph'");
$stmt->execute([$hash]);

echo "<div style='font-family:sans-serif;padding:40px;max-width:500px;margin:40px auto;background:#e8f5e9;border-radius:12px;border:1px solid #a5d6a7'>";
echo "<h2 style='color:#2e7d32'>✓ Password Reset Successfully</h2>";
echo "<p style='margin-top:12px'>Admin password has been updated.</p>";
echo "<p style='margin-top:8px'><strong>Email:</strong> admin@lydo.gov.ph</p>";
echo "<p><strong>Password:</strong> Admin@1234</p>";
echo "<p style='margin-top:16px'><a href='../login.php' style='color:#1565c0;font-weight:700'>→ Go to Login</a></p>";

// Self-delete for security
unlink(__FILE__);
echo "<p style='color:#888;font-size:.8rem;margin-top:12px'>This file has been deleted for security.</p>";
echo "</div>";
