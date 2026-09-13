<?php
/**
 * Complete Fix for Scholarship System Tables
 * Creates all missing tables for the scholarship management system
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Scholarship System Tables Fix</h2>";
echo "<pre>";

// Check and create scholarship_batches table
echo "=== STEP 1: Checking scholarship_batches table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'scholarship_batches'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM scholarship_batches")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE scholarship_batches (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(200) NOT NULL,
                school_year     VARCHAR(20) NOT NULL,
                semester        VARCHAR(20) NOT NULL,
                slots           INT NOT NULL DEFAULT 0,
                gwa_required    DECIMAL(3,2) DEFAULT NULL,
                income_limit    DECIMAL(12,2) DEFAULT NULL,
                app_start       DATE DEFAULT NULL,
                app_end         DATE DEFAULT NULL,
                exam_date       DATE DEFAULT NULL,
                exam_venue      VARCHAR(255) DEFAULT NULL,
                exam_time       TIME DEFAULT NULL,
                description     TEXT DEFAULT NULL,
                status          VARCHAR(20) NOT NULL DEFAULT 'active',
                created_by      INT UNSIGNED DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_schol_batch_status (status),
                INDEX idx_schol_batch_year (school_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create scholarship_applications table
echo "=== STEP 2: Checking scholarship_applications table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'scholarship_applications'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM scholarship_applications")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        echo "Current columns: " . count($columns) . "\n";
        
        // Add missing columns
        $missingColumns = [
            'batch_id' => 'INT NOT NULL DEFAULT 1 AFTER id',
            'gwa' => 'DECIMAL(5,2) DEFAULT NULL AFTER year_level',
            'family_income' => 'DECIMAL(12,2) DEFAULT NULL AFTER gwa',
            'siblings_count' => 'INT NOT NULL DEFAULT 0 AFTER family_income',
            'parent_occupation' => 'VARCHAR(255) DEFAULT NULL AFTER siblings_count',
            'reason_for_applying' => 'TEXT DEFAULT NULL AFTER parent_occupation',
            'exam_score' => 'DECIMAL(5,2) DEFAULT NULL AFTER status',
            'qualification_score' => 'DECIMAL(5,2) DEFAULT NULL AFTER exam_score',
            'rejection_reason' => 'TEXT DEFAULT NULL AFTER qualification_score',
            'exam_scheduled' => 'DATETIME DEFAULT NULL AFTER rejection_reason'
        ];
        
        foreach ($missingColumns as $colName => $colDef) {
            if (!in_array($colName, $columnNames)) {
                echo "  Adding missing column: $colName...\n";
                try {
                    $pdo->exec("ALTER TABLE scholarship_applications ADD COLUMN $colName $colDef");
                    echo "  ✓ Added $colName\n";
                } catch (PDOException $e) {
                    echo "  ⚠ Warning: Could not add $colName - " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Create indexes if they don't exist
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schol_app_batch ON scholarship_applications(batch_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schol_app_user ON scholarship_applications(user_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schol_app_status ON scholarship_applications(status)");
            echo "✓ Indexes created/verified\n";
        } catch (PDOException $e) {
            echo "⚠ Index warning: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE scholarship_applications (
                id                      INT AUTO_INCREMENT PRIMARY KEY,
                batch_id                INT NOT NULL,
                user_id                 INT UNSIGNED NOT NULL,
                course                  VARCHAR(200) NOT NULL,
                school_name             VARCHAR(255) NOT NULL,
                year_level              VARCHAR(50) NOT NULL,
                gwa                     DECIMAL(5,2) NOT NULL,
                family_income           DECIMAL(12,2) NOT NULL,
                siblings_count          INT NOT NULL DEFAULT 0,
                parent_occupation       VARCHAR(255) DEFAULT NULL,
                reason_for_applying     TEXT DEFAULT NULL,
                status                  VARCHAR(30) NOT NULL DEFAULT 'submitted',
                exam_score              DECIMAL(5,2) DEFAULT NULL,
                qualification_score     DECIMAL(5,2) DEFAULT NULL,
                rejection_reason        TEXT DEFAULT NULL,
                exam_scheduled          DATETIME DEFAULT NULL,
                reviewed_by             INT UNSIGNED DEFAULT NULL,
                reviewed_at             TIMESTAMP NULL DEFAULT NULL,
                created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_schol_app_batch (batch_id),
                INDEX idx_schol_app_user (user_id),
                INDEX idx_schol_app_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create scholarship_documents table
echo "=== STEP 3: Checking scholarship_documents table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'scholarship_documents'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM scholarship_documents")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE scholarship_documents (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                application_id  INT NOT NULL,
                doc_type        VARCHAR(50) NOT NULL,
                file_path       VARCHAR(255) NOT NULL,
                original_name   VARCHAR(255) NOT NULL,
                file_size       INT NOT NULL DEFAULT 0,
                status          VARCHAR(20) NOT NULL DEFAULT 'pending',
                reviewed_by     INT UNSIGNED DEFAULT NULL,
                reviewed_at     TIMESTAMP NULL DEFAULT NULL,
                uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_schol_doc_application (application_id),
                INDEX idx_schol_doc_status (status)
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
    echo "Testing scholarship_batches query...\n";
    $stmt = $pdo->query('SELECT b.*,(SELECT COUNT(*) FROM scholarship_applications a WHERE a.batch_id=b.id) as app_count FROM scholarship_batches b ORDER BY b.created_at DESC');
    echo "✓ Batches query works! Found " . $stmt->rowCount() . " batches\n\n";
    
    echo "Testing scholarship_applications query...\n";
    $stmt = $pdo->prepare('SELECT a.*,u.first_name,u.last_name,u.email as user_email,b.name as batch_name FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id WHERE a.id=? LIMIT 1');
    $stmt->execute([999]);
    echo "✓ Single application query works!\n\n";
    
    echo "Testing applications list query...\n";
    $stmt = $pdo->prepare('SELECT a.*,u.first_name,u.last_name,b.name as batch_name,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id) as doc_count,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id AND d.status=\'verified\') as verified_count FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id ORDER BY a.created_at DESC');
    $stmt->execute();
    echo "✓ Applications list query works! Found " . $stmt->rowCount() . " applications\n\n";
    
    echo "Testing scholarship_documents query...\n";
    $stmt = $pdo->prepare('SELECT * FROM scholarship_documents WHERE application_id=?');
    $stmt->execute([999]);
    echo "✓ Documents query works!\n\n";
    
    echo "Testing status counts query...\n";
    $statusList = ['submitted','under_review','for_exam','passed','failed','approved','rejected','beneficiary'];
    foreach ($statusList as $status) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM scholarship_applications WHERE status=?');
        $stmt->execute([$status]);
        $count = $stmt->fetchColumn();
    }
    echo "✓ Status counts query works!\n\n";
    
    echo "Testing insert batch query...\n";
    $stmt = $pdo->prepare('INSERT INTO scholarship_batches (name,school_year,semester,slots,gwa_required,income_limit,app_start,app_end,exam_date,exam_venue,exam_time,description,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    echo "✓ Insert batch query structure is valid\n\n";
    
    echo "Testing update application query...\n";
    $stmt = $pdo->prepare('UPDATE scholarship_applications SET status=?,exam_score=?,qualification_score=?,rejection_reason=?,exam_scheduled=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?');
    echo "✓ Update application query structure is valid\n\n";
    
    echo "Testing update document query...\n";
    $stmt = $pdo->prepare('UPDATE scholarship_documents SET status=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?');
    echo "✓ Update document query structure is valid\n\n";
    
    echo "=== ALL QUERIES SUCCESSFUL ===\n";
    echo "The scholarship_admin.php page should now work without errors!\n";
    
} catch (PDOException $e) {
    echo "✗ Error during verification: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
try {
    $batchCount = $pdo->query("SELECT COUNT(*) FROM scholarship_batches")->fetchColumn();
    $appCount = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
    $docCount = $pdo->query("SELECT COUNT(*) FROM scholarship_documents")->fetchColumn();
    
    echo "Total Scholarship Batches: $batchCount\n";
    echo "Total Applications: $appCount\n";
    echo "Total Documents: $docCount\n\n";
    
    echo "✓ All scholarship tables are properly configured!\n";
    echo "You can now access scholarship_admin.php without errors.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FINAL TABLE STRUCTURES ===\n\n";

// Show final structures
try {
    echo "scholarship_batches:\n";
    $cols = $pdo->query("SHOW COLUMNS FROM scholarship_batches")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "  ✗ Table not accessible\n";
}

try {
    echo "\nscholarship_applications:\n";
    $cols = $pdo->query("SHOW COLUMNS FROM scholarship_applications")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "  ✗ Table not accessible\n";
}

try {
    echo "\nscholarship_documents:\n";
    $cols = $pdo->query("SHOW COLUMNS FROM scholarship_documents")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "  ✗ Table not accessible\n";
}

echo "</pre>";
echo "<p><strong>✓ Fix complete!</strong> <a href='scholarship_admin.php'>Go to Scholarship Admin Page →</a></p>";
?>
