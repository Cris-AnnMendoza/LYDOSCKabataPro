<?php
/**
 * Complete Fix for Accreditation Tables
 * Adds all missing columns and fixes structure mismatches
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Complete Accreditation Tables Fix</h2>";
echo "<pre>";

// Fix accreditation_applications table
echo "=== FIXING ACCREDITATION_APPLICATIONS TABLE ===\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM accreditation_applications")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $columnNames) . "\n\n";
    
    // Add submitted_by if missing
    if (!in_array('submitted_by', $columnNames)) {
        echo "Adding 'submitted_by' column...\n";
        $pdo->exec("ALTER TABLE accreditation_applications ADD COLUMN submitted_by INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER organization_id");
        echo "✓ Added submitted_by column\n";
    } else {
        echo "✓ submitted_by column already exists\n";
    }
    
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Fix accreditation_documents table
echo "=== FIXING ACCREDITATION_DOCUMENTS TABLE ===\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM accreditation_documents")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $columnNames) . "\n\n";
    
    // Add application_id if missing
    if (!in_array('application_id', $columnNames)) {
        echo "Adding 'application_id' column...\n";
        $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN application_id INT NOT NULL DEFAULT 0 AFTER id");
        echo "✓ Added application_id column\n";
    } else {
        echo "✓ application_id column already exists\n";
    }
    
    // Add doc_type if missing (maps to document_type)
    if (!in_array('doc_type', $columnNames)) {
        echo "Adding 'doc_type' column...\n";
        $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN doc_type VARCHAR(50) NOT NULL DEFAULT '' AFTER application_id");
        echo "✓ Added doc_type column\n";
    } else {
        echo "✓ doc_type column already exists\n";
    }
    
    // Add original_name if missing (maps to file_name)
    if (!in_array('original_name', $columnNames)) {
        echo "Adding 'original_name' column...\n";
        $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN original_name VARCHAR(255) NOT NULL DEFAULT '' AFTER file_path");
        echo "✓ Added original_name column\n";
    } else {
        echo "✓ original_name column already exists\n";
    }
    
    // Add file_size if missing
    if (!in_array('file_size', $columnNames)) {
        echo "Adding 'file_size' column...\n";
        $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN file_size INT NOT NULL DEFAULT 0 AFTER original_name");
        echo "✓ Added file_size column\n";
    } else {
        echo "✓ file_size column already exists\n";
    }
    
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Verify the fixes
echo "=== VERIFICATION ===\n";
try {
    // Test query from line 85
    echo "Testing accreditation_applications query...\n";
    $testStmt = $pdo->prepare('SELECT a.*, u.first_name, u.last_name, u.email as user_email FROM accreditation_applications a JOIN youth_users u ON u.id=a.submitted_by WHERE a.id=? LIMIT 1');
    $testStmt->execute([999]); // Use non-existent ID
    echo "✓ Query works! (no records found is OK)\n\n";
    
    echo "Testing accreditation_workflow query...\n";
    $wfStmt = $pdo->prepare('SELECT * FROM accreditation_workflow WHERE application_id=? ORDER BY id');
    $wfStmt->execute([999]);
    echo "✓ Query works!\n\n";
    
    echo "Testing accreditation_documents query...\n";
    $docStmt = $pdo->prepare('SELECT * FROM accreditation_documents WHERE application_id=?');
    $docStmt->execute([999]);
    echo "✓ Query works!\n\n";
    
    echo "=== ALL QUERIES SUCCESSFUL ===\n";
    echo "The accreditation.php page should now work without errors!\n";
    
} catch (PDOException $e) {
    echo "✗ Error during verification: " . $e->getMessage() . "\n";
}

echo "\n=== FINAL TABLE STRUCTURES ===\n\n";

// Show final structure
echo "accreditation_applications:\n";
$cols = $pdo->query("SHOW COLUMNS FROM accreditation_applications")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']}\n";
}

echo "\naccreditation_workflow:\n";
$cols = $pdo->query("SHOW COLUMNS FROM accreditation_workflow")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']}\n";
}

echo "\naccreditation_documents:\n";
$cols = $pdo->query("SHOW COLUMNS FROM accreditation_documents")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']}\n";
}

echo "</pre>";
echo "<p><strong>✓ Fix complete!</strong> <a href='accreditation.php'>Go to Accreditation Page →</a></p>";
?>
