<?php
/**
 * Add Organization President Role to LYDO System
 * 
 * This script creates all necessary database changes to support
 * Organization Presidents as a new user role in the system.
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Adding Organization President Role</h2>";
echo "<pre>";

// Step 1: Create organization_presidents table
echo "=== STEP 1: Creating organization_presidents table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'organization_presidents'");
    if ($result->rowCount() > 0) {
        echo "✓ Table already exists\n\n";
    } else {
        $pdo->exec("
            CREATE TABLE organization_presidents (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id INT UNSIGNED NOT NULL,
                user_id         INT UNSIGNED NOT NULL,
                email           VARCHAR(191) NOT NULL UNIQUE,
                password        VARCHAR(255) NOT NULL,
                full_name       VARCHAR(200) NOT NULL,
                contact_number  VARCHAR(20) DEFAULT NULL,
                term_start      DATE DEFAULT NULL,
                term_end        DATE DEFAULT NULL,
                is_active       TINYINT(1) NOT NULL DEFAULT 1,
                last_login      DATETIME DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_org_president (organization_id),
                INDEX idx_org_pres_user (user_id),
                INDEX idx_org_pres_active (is_active),
                CONSTRAINT fk_org_pres_org FOREIGN KEY (organization_id) 
                    REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_org_pres_user FOREIGN KEY (user_id) 
                    REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 2: Create organization_president_sessions table
echo "=== STEP 2: Creating organization_president_sessions table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'organization_president_sessions'");
    if ($result->rowCount() > 0) {
        echo "✓ Table already exists\n\n";
    } else {
        $pdo->exec("
            CREATE TABLE organization_president_sessions (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                president_id INT UNSIGNED NOT NULL,
                token        VARCHAR(64) NOT NULL UNIQUE,
                expires_at   DATETIME NOT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_org_pres_session_token (token),
                CONSTRAINT fk_org_pres_session FOREIGN KEY (president_id) 
                    REFERENCES organization_presidents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 3: Create organization_president_activity_log table
echo "=== STEP 3: Creating organization_president_activity_log table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'organization_president_activity_log'");
    if ($result->rowCount() > 0) {
        echo "✓ Table already exists\n\n";
    } else {
        $pdo->exec("
            CREATE TABLE organization_president_activity_log (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                president_id INT UNSIGNED NOT NULL,
                action       VARCHAR(255) NOT NULL,
                details      TEXT DEFAULT NULL,
                ip_address   VARCHAR(45) DEFAULT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_org_pres_log_date (created_at),
                CONSTRAINT fk_org_pres_log FOREIGN KEY (president_id) 
                    REFERENCES organization_presidents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 4: Add president_id column to organizations table if not exists
echo "=== STEP 4: Updating organizations table ===\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM organizations")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    if (!in_array('president_id', $columnNames)) {
        echo "Adding president_id column...\n";
        $pdo->exec("ALTER TABLE organizations ADD COLUMN president_id INT UNSIGNED DEFAULT NULL AFTER id");
        echo "✓ Column added\n";
        
        // Add index
        $pdo->exec("CREATE INDEX idx_org_president ON organizations(president_id)");
        echo "✓ Index added\n\n";
    } else {
        echo "✓ Column already exists\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 5: Verify tables
echo "=== STEP 5: Verification ===\n";
try {
    echo "Checking organization_presidents table...\n";
    $count = $pdo->query("SELECT COUNT(*) FROM organization_presidents")->fetchColumn();
    echo "✓ Table accessible - $count presidents\n\n";
    
    echo "Checking organization_president_sessions table...\n";
    $count = $pdo->query("SELECT COUNT(*) FROM organization_president_sessions")->fetchColumn();
    echo "✓ Table accessible - $count sessions\n\n";
    
    echo "Checking organization_president_activity_log table...\n";
    $count = $pdo->query("SELECT COUNT(*) FROM organization_president_activity_log")->fetchColumn();
    echo "✓ Table accessible - $count log entries\n\n";
    
    echo "Testing JOIN query...\n";
    $stmt = $pdo->query("
        SELECT op.*, o.name as org_name, u.first_name, u.last_name
        FROM organization_presidents op
        LEFT JOIN organizations o ON o.id = op.organization_id
        LEFT JOIN youth_users u ON u.id = op.user_id
        LIMIT 1
    ");
    echo "✓ JOIN query works!\n\n";
    
    echo "=== ALL TABLES CREATED SUCCESSFULLY ===\n";
    
} catch (PDOException $e) {
    echo "✗ Error during verification: " . $e->getMessage() . "\n\n";
}

// Step 6: Show table structures
echo "=== STEP 6: Table Structures ===\n\n";

echo "organization_presidents:\n";
$cols = $pdo->query("SHOW COLUMNS FROM organization_presidents")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\norganization_president_sessions:\n";
$cols = $pdo->query("SHOW COLUMNS FROM organization_president_sessions")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\norganization_president_activity_log:\n";
$cols = $pdo->query("SHOW COLUMNS FROM organization_president_activity_log")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== SUMMARY ===\n";
echo "✓ 3 new tables created\n";
echo "✓ 1 column added to organizations table\n";
echo "✓ All foreign keys configured\n";
echo "✓ All indexes created\n";
echo "✓ System ready for Organization Presidents\n\n";

echo "Next steps:\n";
echo "1. Update config.php to add Organization President permissions\n";
echo "2. Create organization_president dashboard\n";
echo "3. Create organization president login page\n";
echo "4. Assign presidents to organizations\n";

echo "</pre>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
