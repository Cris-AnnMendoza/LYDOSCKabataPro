<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Database Fix</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50}h2{color:#333}.success{color:#4CAF50}.error{color:#f44336}.warning{color:#ff9800}</style>";
echo "</head><body>";
echo "<h2>🔧 Complete Database Structure Fix</h2>";
echo "<pre>";

try {
    $pdo = db();
    echo "✅ Connected to database: local_youth_development_db\n\n";
    
    // ═══════════════════════════════════════════════════════════
    // 1. FIX YOUTH_USERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "FIXING: youth_users table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM youth_users LIKE 'status'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Adding 'status' column...\n";
        $pdo->exec("ALTER TABLE youth_users ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'");
        echo "<span class='success'>✅ Status column added!</span>\n";
    } else {
        echo "✅ Status column already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 2. FIX/CREATE MERIT_LOGS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "FIXING: merit_logs table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Drop views first if they exist
    $pdo->exec("DROP VIEW IF EXISTS org_merit_logs");
    
    // Check if merit_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'merit_logs'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating merit_logs table...\n";
        $pdo->exec("
            CREATE TABLE merit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
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
        echo "<span class='success'>✅ merit_logs table created!</span>\n";
    } else {
        echo "✅ merit_logs table exists\n";
        
        // Check and add organization_id if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM merit_logs LIKE 'organization_id'");
        if ($stmt->rowCount() === 0) {
            echo "➜ Adding organization_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER user_id");
            try {
                $pdo->exec("ALTER TABLE merit_logs ADD FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
            } catch (Exception $e) {
                echo "<span class='warning'>⚠ Foreign key skipped (may already exist)</span>\n";
            }
            echo "<span class='success'>✅ organization_id column added!</span>\n";
        } else {
            echo "✅ organization_id column exists\n";
        }
        
        // Check and add event_id if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM merit_logs LIKE 'event_id'");
        if ($stmt->rowCount() === 0) {
            echo "➜ Adding event_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN event_id INT UNSIGNED DEFAULT NULL AFTER organization_id");
            try {
                $pdo->exec("ALTER TABLE merit_logs ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL");
            } catch (Exception $e) {
                echo "<span class='warning'>⚠ Foreign key skipped (may already exist)</span>\n";
            }
            echo "<span class='success'>✅ event_id column added!</span>\n";
        } else {
            echo "✅ event_id column exists\n";
        }
    }
    
    // Create view for backward compatibility
    echo "➜ Creating org_merit_logs view...\n";
    $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
    echo "<span class='success'>✅ org_merit_logs view created!</span>\n";
    
    // ═══════════════════════════════════════════════════════════
    // 3. FIX/CREATE EXPLANATION_LETTERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "FIXING: explanation_letters table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Drop view first
    $pdo->exec("DROP VIEW IF EXISTS org_explanation_letters");
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'explanation_letters'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating explanation_letters table...\n";
        $pdo->exec("
            CREATE TABLE explanation_letters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ explanation_letters table created!</span>\n";
    } else {
        echo "✅ explanation_letters table exists\n";
    }
    
    // Create view
    echo "➜ Creating org_explanation_letters view...\n";
    $pdo->exec("CREATE VIEW org_explanation_letters AS SELECT * FROM explanation_letters");
    echo "<span class='success'>✅ org_explanation_letters view created!</span>\n";
    
    // ═══════════════════════════════════════════════════════════
    // 4. VERIFY EVENT_ATTENDANCE TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: event_attendance table\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'event_attendance'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating event_attendance table...\n";
        $pdo->exec("
            CREATE TABLE event_attendance (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                organization_id INT UNSIGNED DEFAULT NULL,
                status ENUM('present','absent','excused','late') NOT NULL DEFAULT 'present',
                remarks VARCHAR(255) DEFAULT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ event_attendance table created!</span>\n";
    } else {
        echo "✅ event_attendance table exists\n";
        
        // Check for organization_id column
        $stmt = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'organization_id'");
        if ($stmt->rowCount() === 0) {
            echo "➜ Adding organization_id column to event_attendance...\n";
            $pdo->exec("ALTER TABLE event_attendance ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER user_id");
            try {
                $pdo->exec("ALTER TABLE event_attendance ADD FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
            } catch (Exception $e) {
                echo "<span class='warning'>⚠ Foreign key skipped</span>\n";
            }
            echo "<span class='success'>✅ organization_id added to event_attendance!</span>\n";
        } else {
            echo "✅ organization_id exists in event_attendance\n";
        }
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='success'>✅ ALL DATABASE FIXES COMPLETED SUCCESSFULLY!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🎯 <strong>Your admin dashboard should now work!</strong>\n\n";
    echo "📍 Dashboard URL:\n";
    echo "   <a href='dashboard.php'>http://localhost/LYDO/lydo-system/admin2/dashboard.php</a>\n\n";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p style='text-align:center'>";
echo "<a href='dashboard.php' style='display:inline-block;padding:12px 24px;background:#4CAF50;color:white;text-decoration:none;border-radius:6px;font-weight:bold'>← Go to Dashboard</a>";
echo "</p>";
echo "</body></html>";
?>
