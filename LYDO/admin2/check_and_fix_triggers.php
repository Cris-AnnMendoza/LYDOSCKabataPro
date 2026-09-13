<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";
echo "<h2 style='color:#FFA726'>🔍 Checking Database Triggers</h2>\n\n";

try {
    $pdo = db();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 1: Checking for triggers\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $triggers = $pdo->query("SHOW TRIGGERS FROM local_youth_development_db")->fetchAll();
    
    if (empty($triggers)) {
        echo "✅ No triggers found\n\n";
    } else {
        echo "<span style='color:#FFA726'>⚠️  Found " . count($triggers) . " triggers:</span>\n\n";
        
        foreach ($triggers as $trigger) {
            echo "Trigger: <span style='color:#64B5F6'>{$trigger['Trigger']}</span>\n";
            echo "  Table: {$trigger['Table']}\n";
            echo "  Event: {$trigger['Event']}\n";
            echo "  Timing: {$trigger['Timing']}\n\n";
        }
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 2: Checking table structure\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Check if merit_logs table exists
    $meritLogsExists = $pdo->query("SHOW TABLES LIKE 'merit_logs'")->rowCount();
    
    if ($meritLogsExists) {
        echo "⚠️  <span style='color:#FFA726'>merit_logs table exists</span>\n";
        
        $columns = $pdo->query("DESCRIBE merit_logs")->fetchAll();
        echo "\nColumns in merit_logs:\n";
        foreach ($columns as $col) {
            $key = $col['Key'] ? " [{$col['Key']}]" : '';
            $null = $col['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
            echo "  • {$col['Field']} ({$col['Type']}) {$null}{$key}\n";
        }
        
        // Check foreign keys
        echo "\nForeign keys on merit_logs:\n";
        $fks = $pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'local_youth_development_db'
            AND TABLE_NAME = 'merit_logs'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll();
        
        if (empty($fks)) {
            echo "  No foreign keys\n";
        } else {
            foreach ($fks as $fk) {
                echo "  • {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
    } else {
        echo "✅ merit_logs table does NOT exist\n";
    }
    
    echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 3: Checking org_merit_logs\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $orgMeritExists = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'")->rowCount();
    
    if ($orgMeritExists) {
        echo "✅ org_merit_logs table exists\n";
        
        $count = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
        echo "Records: {$count}\n\n";
        
        $columns = $pdo->query("DESCRIBE org_merit_logs")->fetchAll();
        echo "Columns:\n";
        foreach ($columns as $col) {
            $key = $col['Key'] ? " [{$col['Key']}]" : '';
            $null = $col['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
            echo "  • {$col['Field']} ({$col['Type']}) {$null}{$key}\n";
        }
    } else {
        echo "❌ org_merit_logs table does NOT exist\n";
    }
    
    echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span style='color:#4CAF50'>STEP 4: RECOMMENDED FIX</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if ($meritLogsExists) {
        echo "<span style='color:#FFA726'>The problem is merit_logs table with user_id foreign key.</span>\n\n";
        echo "Solution: Make user_id column NULLABLE\n\n";
        
        echo "<form method='POST' style='margin-top:10px'>";
        echo "<button type='submit' name='fix' value='1' style='background:#4CAF50;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-family:monospace;font-size:14px'>✅ FIX IT NOW - Make user_id nullable</button>";
        echo "</form>\n";
    }
    
    // Handle fix
    if (isset($_POST['fix'])) {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "<span style='color:#64B5F6'>Applying fix...</span>\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        try {
            // Drop the foreign key constraint
            $pdo->exec("ALTER TABLE merit_logs DROP FOREIGN KEY merit_logs_ibfk_1");
            echo "✅ Dropped foreign key constraint\n";
            
            // Make user_id nullable
            $pdo->exec("ALTER TABLE merit_logs MODIFY user_id INT NULL");
            echo "✅ Made user_id nullable\n\n";
            
            echo "<span style='color:#4CAF50;font-weight:bold'>✅ FIX APPLIED SUCCESSFULLY!</span>\n\n";
            echo "Now you can:\n";
            echo "1. <a href='simple_import_49.php' style='color:#4CAF50;font-weight:bold'>Import 49 organizations</a>\n";
            echo "2. <a href='merit.php' style='color:#2196F3;font-weight:bold'>Use merit system</a>\n";
            
        } catch (Exception $e) {
            echo "<span style='color:#f44336'>❌ Error: {$e->getMessage()}</span>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<span style='color:#f44336'>❌ ERROR: {$e->getMessage()}</span>\n\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>
