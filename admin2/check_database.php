<?php
require_once 'config.php';

$pdo = db();

echo "<h2>Database Structure Check</h2>";
echo "<pre>";

// Check accreditation_workflow table
echo "=== ACCREDITATION_WORKFLOW TABLE ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_workflow'");
    if ($result->rowCount() > 0) {
        echo "✓ Table exists\n\n";
        $columns = $pdo->query("SHOW COLUMNS FROM accreditation_workflow")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo sprintf("  %-20s %-15s %-8s %-10s %s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'], 
                $col['Key'],
                $col['Default'] ?? 'NULL'
            );
        }
        
        // Try to select from it
        echo "\nTest query:\n";
        $test = $pdo->query("SELECT * FROM accreditation_workflow LIMIT 1");
        echo "✓ Query successful - " . $test->rowCount() . " rows\n";
    } else {
        echo "✗ Table does NOT exist\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== ACCREDITATION_APPLICATIONS TABLE ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_applications'");
    if ($result->rowCount() > 0) {
        echo "✓ Table exists\n\n";
        $columns = $pdo->query("SHOW COLUMNS FROM accreditation_applications")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo sprintf("  %-25s %-20s %-8s %-10s %s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'], 
                $col['Key'],
                $col['Default'] ?? 'NULL'
            );
        }
        
        // Count rows
        $count = $pdo->query("SELECT COUNT(*) FROM accreditation_applications")->fetchColumn();
        echo "\nTotal records: $count\n";
    } else {
        echo "✗ Table does NOT exist\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== ACCREDITATION_DOCUMENTS TABLE ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_documents'");
    if ($result->rowCount() > 0) {
        echo "✓ Table exists\n\n";
        $columns = $pdo->query("SHOW COLUMNS FROM accreditation_documents")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo sprintf("  %-20s %-15s %-8s %-10s %s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'], 
                $col['Key'],
                $col['Default'] ?? 'NULL'
            );
        }
        
        // Count rows
        $count = $pdo->query("SELECT COUNT(*) FROM accreditation_documents")->fetchColumn();
        echo "\nTotal records: $count\n";
    } else {
        echo "✗ Table does NOT exist\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Now test the exact query from line 85
echo "\n=== TESTING LINE 85 QUERY ===\n";
try {
    $viewId = 1; // Test with ID 1
    $s = $pdo->prepare('SELECT a.*, u.first_name, u.last_name, u.email as user_email FROM accreditation_applications a JOIN youth_users u ON u.id=a.submitted_by WHERE a.id=?');
    $s->execute([$viewId]);
    echo "✓ Query executed successfully\n";
    $result = $s->fetch();
    if ($result) {
        echo "✓ Found application record\n";
    } else {
        echo "⚠ No application found with ID $viewId (but query works)\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test the workflow query that's likely failing
echo "\n=== TESTING WORKFLOW QUERY ===\n";
try {
    $wfStmt = $pdo->prepare('SELECT * FROM accreditation_workflow WHERE application_id=? ORDER BY id');
    $wfStmt->execute([1]);
    echo "✓ Workflow query executed successfully\n";
    echo "✓ Found " . $wfStmt->rowCount() . " workflow steps\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "This is likely the failing query!\n";
}

echo "</pre>";
?>
