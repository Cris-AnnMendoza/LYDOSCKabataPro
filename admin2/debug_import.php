<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 CHECKING CURRENT STATUS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Check organizations
    $orgCount = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    echo "Organizations in database: {$orgCount}\n\n";
    
    if ($orgCount > 0) {
        $orgs = $pdo->query("SELECT id, name FROM organizations ORDER BY id")->fetchAll();
        echo "Current organizations:\n";
        foreach ($orgs as $org) {
            echo "  {$org['id']}. {$org['name']}\n";
        }
        echo "\n";
    }
    
    // Check org_merit_logs table
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 CHECKING org_merit_logs TABLE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $tableExists = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'")->rowCount();
    
    if ($tableExists) {
        echo "✅ Table exists\n";
        $logCount = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
        echo "Merit logs in table: {$logCount}\n\n";
    } else {
        echo "❌ Table does NOT exist\n\n";
    }
    
    // Try inserting one test org
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🧪 TESTING INSERT\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    try {
        $testName = 'TEST ORG - ' . time();
        $stmt = $pdo->prepare("INSERT INTO organizations (name, category, barangay, adviser_phone, adviser_email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$testName, 'Test Category', 'Test Barangay', '09123456789', 'test@test.com']);
        $testId = $pdo->lastInsertId();
        
        echo "✅ Test insert successful! (ID: {$testId})\n";
        echo "Organization name: {$testName}\n\n";
        
        // Delete test org
        $pdo->exec("DELETE FROM organizations WHERE id = {$testId}");
        echo "✅ Test org deleted\n\n";
        
    } catch (Exception $e) {
        echo "❌ Test insert FAILED: {$e->getMessage()}\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>
