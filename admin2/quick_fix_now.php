<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<h2>Quick Fix - Adding Missing Columns NOW</h2><pre>";

try {
    $pdo = db();
    echo "Connected to database\n\n";
    
    // Fix merit_logs table
    echo "Fixing merit_logs table...\n";
    
    // Check if table exists
    $result = $pdo->query("SHOW TABLES LIKE 'merit_logs'")->fetchAll();
    
    if (empty($result)) {
        echo "Creating merit_logs table from scratch...\n";
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
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ merit_logs table created!\n\n";
    } else {
        echo "merit_logs table exists, checking columns...\n";
        
        // Check for organization_id
        $cols = $pdo->query("SHOW COLUMNS FROM merit_logs")->fetchAll(PDO::FETCH_COLUMN);
        echo "Current columns: " . implode(', ', $cols) . "\n\n";
        
        if (!in_array('organization_id', $cols)) {
            echo "Adding organization_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL");
            echo "✅ organization_id added!\n\n";
        } else {
            echo "✅ organization_id already exists\n\n";
        }
        
        if (!in_array('event_id', $cols)) {
            echo "Adding event_id column...\n";
            $pdo->exec("ALTER TABLE merit_logs ADD COLUMN event_id INT UNSIGNED DEFAULT NULL");
            echo "✅ event_id added!\n\n";
        } else {
            echo "✅ event_id already exists\n\n";
        }
    }
    
    // Create/recreate view
    echo "Creating org_merit_logs view...\n";
    $pdo->exec("DROP VIEW IF EXISTS org_merit_logs");
    $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
    echo "✅ org_merit_logs view created!\n\n";
    
    // Create user_merit_logs if missing
    $result = $pdo->query("SHOW TABLES LIKE 'user_merit_logs'")->fetchAll();
    if (empty($result)) {
        echo "Creating user_merit_logs table...\n";
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
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ user_merit_logs created!\n\n";
    }
    
    // Create assistance_requests if missing
    $result = $pdo->query("SHOW TABLES LIKE 'assistance_requests'")->fetchAll();
    if (empty($result)) {
        echo "Creating assistance_requests table...\n";
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
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ assistance_requests created!\n\n";
    }
    
    // Create notifications if missing
    $result = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
    if (empty($result)) {
        echo "Creating notifications table...\n";
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
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ notifications created!\n\n";
    }
    
    // Add qr_token to youth_users
    $cols = $pdo->query("SHOW COLUMNS FROM youth_users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('qr_token', $cols)) {
        echo "Adding qr_token to youth_users...\n";
        $pdo->exec("ALTER TABLE youth_users ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL");
        echo "✅ qr_token added!\n\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ALL FIXES COMPLETED!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "Test youth dashboard now:\n";
    echo "<a href='../shared/youth/dashboard.php'>Click here to test</a>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<a href='../shared/youth/dashboard.php' style='display:inline-block;padding:12px 24px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:10px'>Test Youth Dashboard</a>";
?>
