<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Complete Merit System Fix</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;line-height:1.8}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50}.success{color:#4CAF50;font-weight:bold}.error{color:#f44336}.info{color:#2196F3}</style>";
echo "</head><body><h2>🔧 Fixing Merit System Tables</h2><pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    // ═══════════════════════════════════════════════════════════
    // 1. CREATE ORG_WARNING_LETTERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "━━━ Step 1: org_warning_letters ━━━\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'org_warning_letters'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Creating org_warning_letters table...\n";
        $pdo->exec("
            CREATE TABLE org_warning_letters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id INT UNSIGNED NOT NULL,
                reason TEXT NOT NULL,
                level ENUM('warning','final_warning','revocation') NOT NULL DEFAULT 'warning',
                issued_by INT UNSIGNED DEFAULT NULL,
                issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<span class='success'>✅ org_warning_letters created!</span>\n\n";
    } else {
        echo "✅ org_warning_letters exists\n\n";
    }
    
    // ═══════════════════════════════════════════════════════════
    // 2. FIX ORG_EXPLANATION_LETTERS TABLE
    // ═══════════════════════════════════════════════════════════
    echo "━━━ Step 2: org_explanation_letters ━━━\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'org_explanation_letters'");
    if ($stmt->rowCount() > 0) {
        // Check if organization_id exists
        $cols = $pdo->query("SHOW COLUMNS FROM org_explanation_letters")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('organization_id', $cols)) {
            echo "➜ Adding organization_id to org_explanation_letters...\n";
            $pdo->exec("ALTER TABLE org_explanation_letters ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER user_id");
            echo "<span class='success'>✅ organization_id added!</span>\n\n";
        } else {
            echo "✅ organization_id exists\n\n";
        }
    } else {
        echo "➜ Creating org_explanation_letters table...\n";
        $pdo->exec("
            CREATE TABLE org_explanation_letters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED DEFAULT NULL,
                organization_id INT UNSIGNED DEFAULT NULL,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<span class='success'>✅ org_explanation_letters created!</span>\n\n";
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='success'>✅ MERIT SYSTEM TABLES FIXED!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "<span class='info'>Now you can add organizations!</span>\n\n";
    echo "Next step: <a href='add_all_organizations.php' style='color:#2196F3;font-weight:bold'>→ Add All 45 Organizations</a>\n";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p style='text-align:center'>";
echo "<a href='add_all_organizations.php' style='display:inline-block;padding:12px 24px;background:#4CAF50;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:5px'>Add Organizations</a>";
echo "<a href='organizations.php' style='display:inline-block;padding:12px 24px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:5px'>View Organizations</a>";
echo "</p>";
echo "</body></html>";
?>
