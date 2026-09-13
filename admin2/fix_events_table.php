<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<h2>Fixing Events Table</h2><pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    // Check if organization_id exists in events table
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'organization_id'");
    if ($stmt->rowCount() === 0) {
        echo "➜ Adding organization_id column to events table...\n";
        $pdo->exec("ALTER TABLE events ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL AFTER event_id");
        echo "✅ organization_id column added!\n\n";
    } else {
        echo "✅ organization_id column already exists\n\n";
    }
    
    // Show current events table structure
    echo "Current events table structure:\n";
    $cols = $pdo->query("DESCRIBE events")->fetchAll();
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n✅ Events table is now fixed!\n";
    echo "\n<a href='../admin2/organizations.php'>→ Go to Organizations Page</a>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
