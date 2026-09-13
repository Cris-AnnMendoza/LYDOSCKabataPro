<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Final Complete Database Fix</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;line-height:1.6}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50;box-shadow:0 2px 4px rgba(0,0,0,0.1)}h2{color:#333;margin-bottom:10px}.success{color:#4CAF50;font-weight:bold}.error{color:#f44336;font-weight:bold}.warning{color:#ff9800;font-weight:bold}.info{color:#2196F3}</style>";
echo "</head><body>";
echo "<h2>🔧 FINAL COMPLETE DATABASE FIX - ALL ISSUES</h2>";
echo "<pre>";

try {
    $pdo = db();
    echo "✅ Connected to database: local_youth_development_db\n\n";
    
    $fixCount = 0;
    
    // ═══════════════════════════════════════════════════════════
    // 1. DROP AND RECREATE ORG_MERIT_LOGS VIEW
    // ═══════════════════════════════════════════════════════════
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 1: Fixing Merit Logs System\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Drop existing views
    try {
        $pdo->exec("DROP VIEW IF EXISTS org_merit_logs");
        echo "➜ Dropped old org_merit_logs view\n";
    } catch (Exception $e) {}
    
    // Check if merit_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'merit_logs'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating merit_logs table with all required columns...\n";
        $pdo->exec("
            CREATE TABLE merit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED DEFAULT NULL,
                organization_id INT UNSIGNED DEFAULT NULL,
                event_id INT UNSIGNED DEFAULT NULL,
                points SMALLINT NOT NULL,
                type ENUM('merit','demerit') NOT NULL,
                reason VARCHAR(255) NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                reference_id INT UNSIGNED DEFAULT NULL,
                awarded_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ merit_logs table created with all columns!</span>\n";
        $fixCount++;
    } else {
        echo "✅ merit_logs table exists\n";
        
        // Add missing columns if needed
        $stmt = $pdo->query("SHOW COLUMNS FROM merit_logs LIKE 'organization_id'");
        if ($stmt->rowCount() === 0) {
            echo "➜ Adding organization_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER user_id");
            try {
                $pdo->exec("ALTER TABLE merit_logs ADD FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
            } catch (Exception $e) {}
            echo "<span class='success'>✅ organization_id added!</span>\n";
            $fixCount++;
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM merit_logs LIKE 'event_id'");
        if ($stmt->rowCount() === 0) {
            echo "➜ Adding event_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN event_id INT UNSIGNED DEFAULT NULL AFTER organization_id");
            try {
                $pdo->exec("ALTER TABLE merit_logs ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL");
            } catch (Exception $e) {}
            echo "<span class='success'>✅ event_id added!</span>\n";
            $fixCount++;
        }
    }
    
    // Create view
    echo "➜ Creating org_merit_logs view...\n";
    $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
    echo "<span class='success'>✅ org_merit_logs view created!</span>\n";
    
    // ═══════════════════════════════════════════════════════════
    // 2. CREATE USER_MERIT_LOGS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 2: User Merit Logs (Individual Points)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
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
        echo "<span class='success'>✅ user_merit_logs created!</span>\n";
        $fixCount++;
    } else {
        echo "✅ user_merit_logs already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 3. FIX ASSISTANCE_REQUESTS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 3: Assistance Requests Table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'assistance_requests'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating assistance_requests table...\n";
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
        echo "<span class='success'>✅ assistance_requests created!</span>\n";
        $fixCount++;
    } else {
        // Fix column name if wrong
        $stmt = $pdo->query("SHOW COLUMNS FROM assistance_requests LIKE 'user_id'");
        if ($stmt->rowCount() > 0) {
            echo "➜ Renaming user_id to submitted_by...\n";
            $pdo->exec("ALTER TABLE assistance_requests CHANGE COLUMN user_id submitted_by INT UNSIGNED NOT NULL");
            echo "<span class='success'>✅ Column renamed!</span>\n";
            $fixCount++;
        } else {
            echo "✅ assistance_requests has correct columns\n";
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // 4. ADD QR_TOKEN TO YOUTH_USERS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 4: QR Token for Youth Users\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM youth_users LIKE 'qr_token'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Adding qr_token column...\n";
        $pdo->exec("ALTER TABLE youth_users ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL");
        echo "<span class='success'>✅ qr_token column added!</span>\n";
        
        // Generate tokens
        echo "➜ Generating QR tokens for users...\n";
        $users = $pdo->query("SELECT id, email FROM youth_users WHERE qr_token IS NULL")->fetchAll();
        if (count($users) > 0) {
            $updateStmt = $pdo->prepare("UPDATE youth_users SET qr_token = ? WHERE id = ?");
            foreach ($users as $u) {
                $token = md5($u['id'] . $u['email'] . 'LYDO2026');
                $updateStmt->execute([$token, $u['id']]);
            }
            echo "<span class='success'>✅ Generated " . count($users) . " QR tokens!</span>\n";
        }
        $fixCount++;
    } else {
        echo "✅ qr_token column exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 5. ENSURE OTHER REQUIRED TABLES EXIST
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 5: Other Required Tables\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Notifications
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating notifications table...\n";
        $pdo->exec("
            CREATE TABLE notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info','success','warning','error','announcement') NOT NULL DEFAULT 'info',
                reference_type VARCHAR(50) DEFAULT NULL,
                reference_id INT UNSIGNED DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ notifications table created!</span>\n";
        $fixCount++;
    } else {
        echo "✅ notifications table exists\n";
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='success'>🎉 ALL FIXES COMPLETED!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📊 Total fixes applied: {$fixCount}\n\n";
    echo "🎯 <strong>Your system is now ready!</strong>\n\n";
    echo "📍 Test URLs:\n";
    echo "   • <a href='dashboard.php'>Admin Dashboard</a>\n";
    echo "   • <a href='../shared/youth/dashboard.php'>Youth Dashboard</a>\n";
    echo "   • <a href='../../login.html'>Login Page</a>\n\n";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p style='text-align:center'>";
echo "<a href='dashboard.php' style='display:inline-block;padding:12px 24px;margin:5px;background:#4CAF50;color:white;text-decoration:none;border-radius:6px;font-weight:bold'>Admin Dashboard</a>";
echo "<a href='../shared/youth/dashboard.php' style='display:inline-block;padding:12px 24px;margin:5px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;font-weight:bold'>Youth Dashboard</a>";
echo "</p>";
echo "</body></html>";
?>
