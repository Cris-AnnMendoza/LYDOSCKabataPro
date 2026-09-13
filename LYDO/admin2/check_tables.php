<?php
require_once __DIR__ . '/../shared/config.php';

echo "<pre style='font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;border-radius:8px'>";
echo "🔍 Checking Database Tables...\n\n";

try {
    $pdo = db();
    
    // Check if org_merit_logs exists
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Table: org_merit_logs\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'");
    if ($result->rowCount() > 0) {
        echo "✅ Table exists\n\n";
        
        $columns = $pdo->query("DESCRIBE org_merit_logs")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo "  • {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Extra']}\n";
        }
    } else {
        echo "❌ Table does NOT exist\n";
        echo "💡 We need to create it!\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Table: merit_logs\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'merit_logs'");
    if ($result->rowCount() > 0) {
        echo "✅ Table exists\n\n";
        
        $columns = $pdo->query("DESCRIBE merit_logs")->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($columns as $col) {
            echo "  • {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Extra']}\n";
        }
    } else {
        echo "❌ Table does NOT exist\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Table: organizations\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $count = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    echo "✅ Table exists\n";
    echo "Current records: {$count}\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
