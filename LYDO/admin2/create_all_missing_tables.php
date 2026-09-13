<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Create All Missing Tables</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;line-height:1.6}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50;box-shadow:0 2px 4px rgba(0,0,0,0.1)}h2{color:#333;margin-bottom:10px}.success{color:#4CAF50;font-weight:bold}.error{color:#f44336;font-weight:bold}.warning{color:#ff9800;font-weight:bold}.info{color:#2196F3}</style>";
echo "</head><body>";
echo "<h2>🔧 Creating All Missing Database Tables</h2>";
echo "<pre>";

try {
    $pdo = db();
    echo "✅ Connected to database: local_youth_development_db\n\n";
    
    $tablesCreated = 0;
    
    // ═══════════════════════════════════════════════════════════
    // ASSISTANCE_REQUESTS
    // ═══════════════════════════════════════════════════════════
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: assistance_requests\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'assistance_requests'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating assistance_requests table...\n";
        $pdo->exec("
            CREATE TABLE assistance_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                request_type VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                amount_requested DECIMAL(10,2) DEFAULT NULL,
                supporting_documents VARCHAR(255) DEFAULT NULL,
                status ENUM('pending','under_review','approved','rejected','completed') NOT NULL DEFAULT 'pending',
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ assistance_requests table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ assistance_requests table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // VOLUNTEER_APPLICATIONS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: volunteer_applications\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'volunteer_applications'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating volunteer_applications table...\n";
        $pdo->exec("
            CREATE TABLE volunteer_applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                availability TEXT DEFAULT NULL,
                skills TEXT DEFAULT NULL,
                motivation TEXT NOT NULL,
                experience TEXT DEFAULT NULL,
                hours_per_week TINYINT UNSIGNED DEFAULT NULL,
                preferred_activities TEXT DEFAULT NULL,
                status ENUM('pending','approved','rejected','active','completed') NOT NULL DEFAULT 'pending',
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ volunteer_applications table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ volunteer_applications table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // SCHOLARSHIP_APPLICATIONS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: scholarship_applications\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'scholarship_applications'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating scholarship_applications table...\n";
        $pdo->exec("
            CREATE TABLE scholarship_applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                scholarship_type VARCHAR(100) NOT NULL,
                school_name VARCHAR(200) NOT NULL,
                course VARCHAR(150) NOT NULL,
                year_level VARCHAR(50) NOT NULL,
                gpa DECIMAL(3,2) DEFAULT NULL,
                financial_need TEXT NOT NULL,
                achievements TEXT DEFAULT NULL,
                essay TEXT DEFAULT NULL,
                supporting_documents TEXT DEFAULT NULL,
                status ENUM('pending','under_review','shortlisted','approved','rejected') NOT NULL DEFAULT 'pending',
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ scholarship_applications table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ scholarship_applications table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // PROGRAMS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: programs\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'programs'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating programs table...\n";
        $pdo->exec("
            CREATE TABLE programs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                objectives TEXT DEFAULT NULL,
                target_participants VARCHAR(200) DEFAULT NULL,
                duration VARCHAR(100) DEFAULT NULL,
                status ENUM('active','inactive','upcoming','completed') NOT NULL DEFAULT 'active',
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                coordinator_name VARCHAR(150) DEFAULT NULL,
                coordinator_contact VARCHAR(50) DEFAULT NULL,
                max_participants INT UNSIGNED DEFAULT NULL,
                requirements TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ programs table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ programs table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: notifications\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
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
                FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE,
                INDEX idx_user_read (user_id, is_read),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ notifications table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ notifications table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // ANNOUNCEMENTS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: announcements\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating announcements table...\n";
        $pdo->exec("
            CREATE TABLE announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                content TEXT NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                target_audience ENUM('all','youth','organizations','barangay_specific') NOT NULL DEFAULT 'all',
                target_barangay VARCHAR(100) DEFAULT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 1,
                publish_date DATETIME DEFAULT NULL,
                expire_date DATETIME DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_published (is_published, publish_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ announcements table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ announcements table already exists\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // ACCREDITATION_APPLICATIONS
    // ═══════════════════════════════════════════════════════════
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CHECKING: accreditation_applications\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'accreditation_applications'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating accreditation_applications table...\n";
        $pdo->exec("
            CREATE TABLE accreditation_applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id INT UNSIGNED NOT NULL,
                application_type ENUM('new','renewal') NOT NULL DEFAULT 'new',
                officers_list TEXT NOT NULL,
                members_count INT UNSIGNED NOT NULL,
                constitution_bylaws VARCHAR(255) DEFAULT NULL,
                action_plan TEXT DEFAULT NULL,
                financial_statement VARCHAR(255) DEFAULT NULL,
                other_documents TEXT DEFAULT NULL,
                status ENUM('pending','under_review','approved','rejected','expired') NOT NULL DEFAULT 'pending',
                valid_from DATE DEFAULT NULL,
                valid_until DATE DEFAULT NULL,
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='success'>✅ accreditation_applications table created!</span>\n";
        $tablesCreated++;
    } else {
        echo "✅ accreditation_applications table already exists\n";
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    if ($tablesCreated > 0) {
        echo "<span class='success'>✅ COMPLETED! Created {$tablesCreated} missing table(s)!</span>\n";
    } else {
        echo "<span class='info'>ℹ️  All tables already exist - no changes needed</span>\n";
    }
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "🎯 <strong>Your system should now work properly!</strong>\n\n";
    echo "📍 Test these URLs:\n";
    echo "   • Admin Dashboard: <a href='dashboard.php'>dashboard.php</a>\n";
    echo "   • Youth Dashboard: <a href='../shared/youth/dashboard.php'>../shared/youth/dashboard.php</a>\n\n";
    
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
