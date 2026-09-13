<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";
echo "<h2 style='color:#4CAF50'>🔧 Fixing Accreditation Tables</h2>\n\n";

try {
    $pdo = db();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Creating/Fixing Accreditation Tables\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Create accreditation_applications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accreditation_applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            barangay VARCHAR(100),
            contact_person VARCHAR(200),
            contact_email VARCHAR(255),
            contact_phone VARCHAR(50),
            submitted_by INT UNSIGNED NOT NULL,
            status ENUM('submitted', 'under_review', 'approved', 'rejected') DEFAULT 'submitted',
            certificate_no VARCHAR(50) NULL,
            valid_until DATE NULL,
            rejection_reason TEXT NULL,
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_submitted_by (submitted_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ accreditation_applications table created/verified\n";
    
    // Create accreditation_documents table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accreditation_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            doc_type VARCHAR(100) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_application_id (application_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ accreditation_documents table created/verified\n";
    
    // Create accreditation_workflow table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accreditation_workflow (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            step VARCHAR(100) NOT NULL,
            status ENUM('pending', 'completed') DEFAULT 'pending',
            done_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_application_id (application_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ accreditation_workflow table created/verified\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Verifying table structure\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Verify columns exist
    $tables = [
        'accreditation_applications' => ['id', 'organization_name', 'status', 'submitted_by'],
        'accreditation_documents' => ['id', 'application_id', 'doc_type', 'status'],
        'accreditation_workflow' => ['id', 'application_id', 'step', 'status']
    ];
    
    foreach ($tables as $table => $expectedColumns) {
        $columns = $pdo->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Table: <span style='color:#64B5F6'>{$table}</span>\n";
        $allPresent = true;
        foreach ($expectedColumns as $col) {
            if (in_array($col, $columns)) {
                echo "  ✅ {$col}\n";
            } else {
                echo "  <span style='color:#f44336'>❌ {$col} - MISSING!</span>\n";
                $allPresent = false;
            }
        }
        
        if ($allPresent) {
            echo "  <span style='color:#4CAF50'>All required columns present</span>\n";
        }
        echo "\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span style='color:#4CAF50;font-weight:bold'>✅ ACCREDITATION TABLES FIXED!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "Now you can use:\n";
    echo "<a href='accreditation.php' style='color:#4CAF50;font-weight:bold'>→ Accreditation Page</a>\n\n";
    
} catch (Exception $e) {
    echo "<span style='color:#f44336'>❌ ERROR: {$e->getMessage()}</span>\n\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>
