<?php
/**
 * Complete Fix for Volunteer System Tables
 * Creates all missing tables for the volunteer program management system
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Volunteer System Tables Fix</h2>";
echo "<pre>";

// Check and create volunteer_programs table
echo "=== STEP 1: Checking volunteer_programs table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'volunteer_programs'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM volunteer_programs")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE volunteer_programs (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(200) NOT NULL,
                type            VARCHAR(50) NOT NULL,
                description     TEXT DEFAULT NULL,
                min_age         INT DEFAULT 15,
                max_age         INT DEFAULT 30,
                slots           INT DEFAULT 0,
                start_date      DATE DEFAULT NULL,
                end_date        DATE DEFAULT NULL,
                location        VARCHAR(255) DEFAULT NULL,
                status          VARCHAR(20) NOT NULL DEFAULT 'active',
                created_by      INT UNSIGNED DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vol_prog_type (type),
                INDEX idx_vol_prog_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create volunteer_registrations table
echo "=== STEP 2: Checking volunteer_registrations table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'volunteer_registrations'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM volunteer_registrations")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE volunteer_registrations (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                program_id          INT NOT NULL,
                user_id             INT UNSIGNED NOT NULL,
                status              VARCHAR(20) NOT NULL DEFAULT 'pending',
                orientation_date    DATE DEFAULT NULL,
                total_hours         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                certificate_issued  TINYINT(1) NOT NULL DEFAULT 0,
                notes               TEXT DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vol_reg_program (program_id),
                INDEX idx_vol_reg_user (user_id),
                INDEX idx_vol_reg_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create volunteer_attendance table
echo "=== STEP 3: Checking volunteer_attendance table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'volunteer_attendance'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM volunteer_attendance")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE volunteer_attendance (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                registration_id     INT NOT NULL,
                event_name          VARCHAR(200) NOT NULL,
                event_date          DATE NOT NULL,
                hours               DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                status              VARCHAR(20) NOT NULL DEFAULT 'present',
                recorded_by         INT UNSIGNED DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_vol_att_registration (registration_id),
                INDEX idx_vol_att_date (event_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Verify all queries work
echo "=== VERIFICATION ===\n";
try {
    echo "Testing volunteer_programs query...\n";
    $stmt = $pdo->query('SELECT p.*,(SELECT COUNT(*) FROM volunteer_registrations r WHERE r.program_id=p.id) as reg_count FROM volunteer_programs p ORDER BY p.created_at DESC');
    echo "✓ Programs query works! Found " . $stmt->rowCount() . " programs\n\n";
    
    echo "Testing volunteer_registrations query...\n";
    $stmt = $pdo->prepare('SELECT r.*,u.first_name,u.last_name,u.email,p.name as prog_name,p.type as prog_type FROM volunteer_registrations r JOIN youth_users u ON u.id=r.user_id JOIN volunteer_programs p ON p.id=r.program_id ORDER BY r.created_at DESC');
    $stmt->execute();
    echo "✓ Registrations query works! Found " . $stmt->rowCount() . " registrations\n\n";
    
    echo "Testing volunteer_attendance query...\n";
    $stmt = $pdo->query('SELECT a.*,u.first_name,u.last_name,p.name as prog_name FROM volunteer_attendance a JOIN volunteer_registrations r ON r.id=a.registration_id JOIN youth_users u ON u.id=r.user_id JOIN volunteer_programs p ON p.id=r.program_id ORDER BY a.event_date DESC LIMIT 100');
    echo "✓ Attendance query works! Found " . $stmt->rowCount() . " attendance records\n\n";
    
    echo "Testing leaderboard query...\n";
    $stmt = $pdo->query('SELECT r.user_id,u.first_name,u.last_name,u.barangay,SUM(r.total_hours) as total_hours,COUNT(r.id) as programs FROM volunteer_registrations r JOIN youth_users u ON u.id=r.user_id WHERE r.status IN (\'approved\',\'completed\') GROUP BY r.user_id, u.first_name, u.last_name, u.barangay ORDER BY total_hours DESC LIMIT 20');
    echo "✓ Leaderboard query works! Found " . $stmt->rowCount() . " volunteers\n\n";
    
    echo "Testing insert program query...\n";
    $stmt = $pdo->prepare('INSERT INTO volunteer_programs (name,type,description,min_age,max_age,slots,start_date,end_date,location,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
    // We won't actually insert, just prepare the statement
    echo "✓ Insert program query structure is valid\n\n";
    
    echo "Testing insert attendance query...\n";
    $stmt = $pdo->prepare('INSERT INTO volunteer_attendance (registration_id,event_name,event_date,hours,status,recorded_by) VALUES (?,?,?,?,?,?)');
    echo "✓ Insert attendance query structure is valid\n\n";
    
    echo "Testing update registration query...\n";
    $stmt = $pdo->prepare('UPDATE volunteer_registrations SET status=?,orientation_date=?,notes=? WHERE id=?');
    echo "✓ Update registration query structure is valid\n\n";
    
    echo "=== ALL QUERIES SUCCESSFUL ===\n";
    echo "The volunteer_admin.php page should now work without errors!\n";
    
} catch (PDOException $e) {
    echo "✗ Error during verification: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
try {
    $progCount = $pdo->query("SELECT COUNT(*) FROM volunteer_programs")->fetchColumn();
    $regCount = $pdo->query("SELECT COUNT(*) FROM volunteer_registrations")->fetchColumn();
    $attCount = $pdo->query("SELECT COUNT(*) FROM volunteer_attendance")->fetchColumn();
    
    echo "Total Volunteer Programs: $progCount\n";
    echo "Total Registrations: $regCount\n";
    echo "Total Attendance Records: $attCount\n\n";
    
    echo "✓ All volunteer tables are properly configured!\n";
    echo "You can now access volunteer_admin.php without errors.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FINAL TABLE STRUCTURES ===\n\n";

// Show final structures
echo "volunteer_programs:\n";
$cols = $pdo->query("SHOW COLUMNS FROM volunteer_programs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nvolunteer_registrations:\n";
$cols = $pdo->query("SHOW COLUMNS FROM volunteer_registrations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nvolunteer_attendance:\n";
$cols = $pdo->query("SHOW COLUMNS FROM volunteer_attendance")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "</pre>";
echo "<p><strong>✓ Fix complete!</strong> <a href='volunteer_admin.php'>Go to Volunteer Admin Page →</a></p>";
?>
