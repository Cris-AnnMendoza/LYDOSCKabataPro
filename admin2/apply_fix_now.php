<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";
echo "<h2 style='color:#4CAF50'>🔧 Fixing merit_logs Table</h2>\n\n";

try {
    $pdo = db();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Applying fix to merit_logs table...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Drop the foreign key constraint
    try {
        $pdo->exec("ALTER TABLE merit_logs DROP FOREIGN KEY merit_logs_ibfk_1");
        echo "✅ Dropped foreign key constraint: merit_logs_ibfk_1\n";
    } catch (Exception $e) {
        echo "⚠️  Foreign key might not exist (that's okay): {$e->getMessage()}\n";
    }
    
    // Make user_id nullable
    $pdo->exec("ALTER TABLE merit_logs MODIFY user_id INT NULL");
    echo "✅ Made user_id column NULLABLE\n\n";
    
    // Verify the change
    $columns = $pdo->query("DESCRIBE merit_logs")->fetchAll();
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Verified - merit_logs structure:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($columns as $col) {
        $null = $col['Null'] == 'YES' ? '<span style="color:#4CAF50">NULL</span>' : '<span style="color:#f44336">NOT NULL</span>';
        if ($col['Field'] == 'user_id') {
            echo "  • <span style='color:#64B5F6;font-weight:bold'>{$col['Field']}</span> ({$col['Type']}) {$null} ← FIXED!\n";
        } else {
            echo "  • {$col['Field']} ({$col['Type']}) {$null}\n";
        }
    }
    
    echo "\n\n<span style='color:#4CAF50;font-weight:bold;font-size:16px'>✅ FIX APPLIED SUCCESSFULLY!</span>\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span style='color:#64B5F6;font-weight:bold'>🎯 NEXT STEPS:</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "1. <a href='simple_import_49.php' style='color:#4CAF50;font-weight:bold;text-decoration:none'>→ Import 49 Organizations</a>\n";
    echo "2. <a href='organizations.php' style='color:#64B5F6;font-weight:bold;text-decoration:none'>→ View Organizations</a>\n";
    echo "3. <a href='merit.php' style='color:#2196F3;font-weight:bold;text-decoration:none'>→ View Merit Leaderboard</a>\n\n";
    
} catch (Exception $e) {
    echo "<span style='color:#f44336;font-weight:bold'>❌ ERROR:</span>\n\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>
