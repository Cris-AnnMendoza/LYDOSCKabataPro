<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Log the logout activity if session exists
if (!empty($_SESSION['org_president_id'])) {
    try {
        require_once __DIR__ . '/../shared/config.php';
        $pdo = db();
        $pdo->prepare('INSERT INTO organization_president_activity_log (president_id, action, ip_address) VALUES (?, ?, ?)')
            ->execute([$_SESSION['org_president_id'], 'Logout', $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {
        // Silent fail - just logout anyway
    }
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login
header('Location: /LYDO/lydo-system/login.php');
exit;
?>
