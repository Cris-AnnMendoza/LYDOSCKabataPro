<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<h2>Fixing org_merit_logs View Issue</h2><pre>";

try {
    $pdo = db();
    echo "Connected to database\n\n";
    
    // First, check what exists
    echo "Checking what exists...\n";
    
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n\n";
    
    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Views: " . implode(', ', $views) . "\n\n";
    
    // Drop the existing table/view if it's causing issues
    if (in_array('org_merit_logs', $tables)) {
        echo "org_merit_logs exists as a TABLE - this is the problem!\n";
        echo "We need it to be a VIEW instead.\n\n";
        
        // Check if there's data in it
        $count = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
        echo "org_merit_logs has {$count} records\n";
        
        if ($count > 0) {
            echo "Copying data to merit_logs...\n";
            $pdo->exec("INSERT IGNORE INTO merit_logs SELECT * FROM org_merit_logs");
            echo "✅ Data copied!\n\n";
        }
        
        echo "Dropping org_merit_logs table...\n";
        $pdo->exec("DROP TABLE org_merit_logs");
        echo "✅ Table dropped!\n\n";
    }
    
    if (in_array('org_merit_logs', $views)) {
        echo "Dropping existing org_merit_logs view...\n";
        $pdo->exec("DROP VIEW org_merit_logs");
        echo "✅ View dropped!\n\n";
    }
    
    // Now create the view fresh
    echo "Creating org_merit_logs as a VIEW pointing to merit_logs...\n";
    $pdo->exec("CREATE VIEW org_merit_logs AS SELECT * FROM merit_logs");
    echo "✅ View created successfully!\n\n";
    
    // Verify it works
    echo "Testing the view...\n";
    $test = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
    echo "✅ org_merit_logs view works! Has {$test} records.\n\n";
    
    // Check merit_logs structure
    echo "merit_logs table structure:\n";
    $cols = $pdo->query("DESCRIBE merit_logs")->fetchAll();
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ FIX COMPLETED!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "Now test youth dashboard:\n";
    echo "<a href='../shared/youth/dashboard.php'>Click here to test</a>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<a href='../shared/youth/dashboard.php' style='display:inline-block;padding:12px 24px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:10px'>Test Youth Dashboard</a>";
echo "<a href='dashboard.php' style='display:inline-block;padding:12px 24px;background:#4CAF50;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:10px'>Admin Dashboard</a>";
?>
