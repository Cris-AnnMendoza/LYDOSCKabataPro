<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<h2>🔧 Fixing Missing Database Columns & Tables</h2>";
echo "<pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    // ═══════════════════════════════════════════════════════════
    // 1. FIX YOUTH_USERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "━━━ YOUTH USERS TABLE ━━━\n";
    
    // Check if status column exists in youth_users
    $stmt = $pdo->query("SHOW COLUMNS FROM youth_users LIKE 'status'");
    if ($stmt->rowCount() === 0) {
        echo "Adding 'status' column to youth_users table...\n";
        $pdo->exec("ALTER TABLE youth_users ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved' AFTER profile_picture");
        echo "✅ Status column added!\n";
    } else {
        echo "✅ Status column exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 2. FIX MERIT_LOGS TABLE (check both names)
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━ MERIT LOGS TABLE ━━━\n";
    
    // Check if merit_logs exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'merit_logs'");
    $hasMeritLogs = $stmt->rowCount() > 0;
    
    // Check if org_merit_logs exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'");
    $hasOrgMeritLogs = $stmt->rowCount() > 0;
    
    if (!$hasMeritLogs && !$hasOrgMeritLogs) {
        echo "Creating 'merit_logs' table...\n";
        $pdo->exec("
            CREATE TABLE merit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                organization_id INT UNSIGNED DEFAULT NULL,
                points SMALLINT NOT NULL,
                type ENUM('merit','demerit') NOT NULL,
                reason VARCHAR(255) NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                reference_id INT UNSIGNED DEFAULT NULL,
                awarded_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ merit_logs table created!\n";
        
        // Create alias view for org_merit_logs
        $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
        echo "✅ org_merit_logs view created!\n";
    } elseif ($hasMeritLogs && !$hasOrgMeritLogs) {
        // Create view
        $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
        echo "✅ org_merit_logs view created from merit_logs!\n";
    } elseif (!$hasMeritLogs && $hasOrgMeritLogs) {
        // Rename table
        $pdo->exec("RENAME TABLE org_merit_logs TO merit_logs");
        $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
        echo "✅ Renamed org_merit_logs to merit_logs and created view!\n";
    } else {
        echo "✅ Merit logs tables already exist\n";
    }
    
    // Check if organization_id column exists in merit_logs
    $stmt = $pdo->query("SHOW COLUMNS FROM merit_logs LIKE 'organization_id'");
    if ($stmt->rowCount() === 0) {
        echo "Adding 'organization_id' column to merit_logs...\n";
        $pdo->exec("ALTER TABLE merit_logs ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER user_id");
        $pdo->exec("ALTER TABLE merit_logs ADD FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
        echo "✅ organization_id column added!\n";
    } else {
        echo "✅ organization_id column exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 3. FIX EXPLANATION LETTERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━ EXPLANATION LETTERS TABLE ━━━\n";
    
    // Check if explanation_letters exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'explanation_letters'");
    $hasExplanationLetters = $stmt->rowCount() > 0;
    
    // Check if org_explanation_letters exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'org_explanation_letters'");
    $hasOrgExplanationLetters = $stmt->rowCount() > 0;
    
    if (!$hasExplanationLetters && !$hasOrgExplanationLetters) {
        echo "Creating 'explanation_letters' table...\n";
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
        echo "✅ explanation_letters table created!\n";
        
        // Create alias view
        $pdo->exec("CREATE VIEW org_explanation_letters AS SELECT * FROM explanation_letters");
        echo "✅ org_explanation_letters view created!\n";
    } elseif ($hasExplanationLetters && !$hasOrgExplanationLetters) {
        $pdo->exec("CREATE VIEW org_explanation_letters AS SELECT * FROM explanation_letters");
        echo "✅ org_explanation_letters view created!\n";
    } elseif (!$hasExplanationLetters && $hasOrgExplanationLetters) {
        $pdo->exec("RENAME TABLE org_explanation_letters TO explanation_letters");
        $pdo->exec("CREATE VIEW org_explanation_letters AS SELECT * FROM explanation_letters");
        echo "✅ Renamed and created view!\n";
    } else {
        echo "✅ Explanation letters tables already exist\n";
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ALL DATABASE FIXES COMPLETED!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "🎯 Dashboard should now work!\n";
    echo "   http://localhost/LYDO/lydo-system/admin2/dashboard.php\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='dashboard.php'>← Go to Dashboard</a></p>";
?>
