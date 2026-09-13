<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";
echo "<h2 style='color:#4CAF50'>🔧 Creating Missing Database Tables</h2>\n\n";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Creating missing tables...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Create accreditation_documents table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accreditation_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            reviewed_by INT UNSIGNED NULL,
            reviewed_at TIMESTAMP NULL,
            notes TEXT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ accreditation_documents table created\n";
    
    // Create event_checkins table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_checkins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checked_out_at DATETIME DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            checkin_photo VARCHAR(255) DEFAULT NULL,
            checkout_photo VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY uq_event_user (event_id, user_id),
            INDEX idx_event_id (event_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ event_checkins table created\n";
    
    // Create event_certificates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_certificates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            cert_number VARCHAR(50) NOT NULL,
            generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            merit_awarded TINYINT(1) NOT NULL DEFAULT 0,
            merit_points TINYINT NOT NULL DEFAULT 2,
            UNIQUE KEY uq_event_user_cert (event_id, user_id),
            UNIQUE KEY uq_cert_number (cert_number),
            INDEX idx_event_id (event_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ event_certificates table created\n";
    
    // Create user_merit_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_merit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            points SMALLINT NOT NULL,
            type ENUM('merit', 'demerit') NOT NULL,
            reason VARCHAR(255) NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            event_id INT UNSIGNED DEFAULT NULL,
            cert_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ user_merit_logs table created\n";
    
    // Create org_merit_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS org_merit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL,
            points INT NOT NULL,
            type ENUM('merit', 'demerit') NOT NULL,
            reason TEXT,
            category VARCHAR(100),
            event_id INT NULL,
            awarded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_org_id (organization_id),
            INDEX idx_type (type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ org_merit_logs table created\n";
    
    // Create org_warning_letters table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS org_warning_letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL,
            reason TEXT NOT NULL,
            level ENUM('warning', 'show_cause', 'revocation_flag') NOT NULL,
            issued_by INT NULL,
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_org_id (organization_id),
            INDEX idx_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ org_warning_letters table created\n";
    
    // Create org_explanation_letters table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS org_explanation_letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_org_id (organization_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ org_explanation_letters table created\n";
    
    // Create notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ notifications table created\n";
    
    // Create event_attendance table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            organization_id INT NOT NULL,
            status ENUM('present', 'absent', 'excused', 'late', 'representative') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_org (event_id, organization_id),
            INDEX idx_event_id (event_id),
            INDEX idx_org_id (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ event_attendance table created\n";
    
    // Add missing columns to events table
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Adding missing columns to events table...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $columns = [
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS qr_token VARCHAR(64) DEFAULT NULL",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS checkin_open TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS checkin_code VARCHAR(6) DEFAULT NULL",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS checkout_open TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type VARCHAR(50) DEFAULT 'official_event'",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS organization_id INT NULL"
    ];
    
    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ " . substr($sql, 0, 80) . "...\n";
        } catch (PDOException $e) {
            // Column might already exist
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "ℹ️  Column already exists (skipped)\n";
            } else {
                echo "⚠️  {$e->getMessage()}\n";
            }
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span style='color:#4CAF50;font-weight:bold'>✅ ALL TABLES CREATED!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Verify tables exist
    echo "Verifying tables...\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $required = [
        'organizations',
        'org_merit_logs',
        'org_warning_letters',
        'org_explanation_letters',
        'events',
        'event_checkins',
        'event_certificates',
        'event_attendance',
        'user_merit_logs',
        'notifications',
        'accreditation_documents',
        'youth_users',
        'admin_users'
    ];
    
    foreach ($required as $table) {
        if (in_array($table, $tables)) {
            echo "✅ {$table}\n";
        } else {
            echo "<span style='color:#FFA726'>⚠️  {$table} - MISSING!</span>\n";
        }
    }
    
    echo "\n<span style='color:#64B5F6;font-weight:bold'>🎯 NEXT STEPS:</span>\n\n";
    echo "1. <a href='simple_import_49.php' style='color:#4CAF50;font-weight:bold'>→ Import 49 Organizations</a>\n";
    echo "2. <a href='organizations.php' style='color:#64B5F6;font-weight:bold'>→ View Organizations</a>\n";
    echo "3. <a href='merit.php' style='color:#2196F3;font-weight:bold'>→ View Merit Leaderboard</a>\n";
    echo "4. <a href='accreditation.php' style='color:#9C27B0;font-weight:bold'>→ Test Accreditation Page</a>\n\n";
    
} catch (Exception $e) {
    echo "<span style='color:#f44336;font-weight:bold'>❌ ERROR:</span>\n\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>
