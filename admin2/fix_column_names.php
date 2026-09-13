<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix Column Names</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50}.success{color:#4CAF50;font-weight:bold}.error{color:#f44336;font-weight:bold}</style>";
echo "</head><body><h2>🔧 Fixing Database Column Names</h2><pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    // ═══════════════════════════════════════════════════════════
    // FIX ASSISTANCE_REQUESTS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "━━━ Fixing assistance_requests table ━━━\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'assistance_requests'");
    if ($stmt->rowCount() > 0) {
        // Check if user_id exists (wrong name)
        $stmt = $pdo->query("SHOW COLUMNS FROM assistance_requests LIKE 'user_id'");
        if ($stmt->rowCount() > 0) {
            echo "➜ Renaming user_id to submitted_by...\n";
            $pdo->exec("ALTER TABLE assistance_requests CHANGE COLUMN user_id submitted_by INT UNSIGNED NOT NULL");
            echo "<span class='success'>✅ Column renamed!</span>\n";
        }
        
        // Check if submitted_by exists
        $stmt = $pdo->query("SHOW COLUMNS FROM assistance_requests LIKE 'submitted_by'");
        if ($stmt->rowCount() > 0) {
            echo "✅ submitted_by column exists\n";
        } else {
            echo "➜ Adding submitted_by column...\n";
            $pdo->exec("ALTER TABLE assistance_requests ADD COLUMN submitted_by INT UNSIGNED NOT NULL AFTER id");
            $pdo->exec("ALTER TABLE assistance_requests ADD FOREIGN KEY (submitted_by) REFERENCES youth_users(id) ON DELETE CASCADE");
            echo "<span class='success'>✅ submitted_by column added!</span>\n";
        }
        
        // Check if 'declined' status exists
        $stmt = $pdo->query("SHOW COLUMNS FROM assistance_requests LIKE 'status'");
        $col = $stmt->fetch();
        if ($col && strpos($col['Type'], 'declined') === false) {
            echo "➜ Adding 'declined' status option...\n";
            $pdo->exec("ALTER TABLE assistance_requests MODIFY COLUMN status ENUM('pending','under_review','approved','rejected','completed','declined') NOT NULL DEFAULT 'pending'");
            echo "<span class='success'>✅ Status updated!</span>\n";
        }
    } else {
        echo "Table doesn't exist yet, creating...\n";
        $pdo->exec("
            CREATE TABLE assistance_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submitted_by INT UNSIGNED NOT NULL,
                request_type VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                amount_requested DECIMAL(10,2) DEFAULT NULL,
                supporting_documents VARCHAR(255) DEFAULT NULL,
                status ENUM('pending','under_review','approved','rejected','completed','declined') NOT NULL DEFAULT 'pending',
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (submitted_by) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ assistance_requests table created!</span>\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // CREATE USER_MERIT_LOGS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━ Checking user_merit_logs table ━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_merit_logs'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating user_merit_logs table...\n";
        $pdo->exec("
            CREATE TABLE user_merit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                points SMALLINT NOT NULL,
                type ENUM('merit','demerit') NOT NULL,
                reason VARCHAR(255) NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                reference_id INT UNSIGNED DEFAULT NULL,
                awarded_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ user_merit_logs table created!</span>\n";
    } else {
        echo "✅ user_merit_logs table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // ADD QR_TOKEN COLUMN TO YOUTH_USERS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━ Checking youth_users.qr_token column ━━━\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM youth_users LIKE 'qr_token'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Adding qr_token column to youth_users...\n";
        $pdo->exec("ALTER TABLE youth_users ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL");
        echo "<span class='success'>✅ qr_token column added!</span>\n";
        
        // Generate tokens for existing users
        echo "➜ Generating QR tokens for existing users...\n";
        $users = $pdo->query("SELECT id, email FROM youth_users WHERE qr_token IS NULL")->fetchAll();
        $updateStmt = $pdo->prepare("UPDATE youth_users SET qr_token = ? WHERE id = ?");
        foreach ($users as $u) {
            $token = md5($u['id'] . $u['email'] . 'LYDO2026');
            $updateStmt->execute([$token, $u['id']]);
        }
        echo "<span class='success'>✅ Generated " . count($users) . " QR tokens!</span>\n";
    } else {
        echo "✅ qr_token column already exists\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='success'>✅ ALL FIXES COMPLETED!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🎯 Youth dashboard should now work!\n";
    echo "   Test: <a href='../shared/youth/dashboard.php'>Youth Dashboard</a>\n\n";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p style='text-align:center'>";
echo "<a href='../shared/youth/dashboard.php' style='display:inline-block;padding:12px 24px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;font-weight:bold'>Test Youth Dashboard</a>";
echo "</p>";
echo "</body></html>";
?>
