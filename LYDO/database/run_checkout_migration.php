<?php
/**
 * Add missing columns to event_checkins table
 * Run this once: http://localhost/LYDO/lydo-system/database/run_checkout_migration.php
 */

require_once __DIR__ . '/../shared/config.php';

$pdo = db();

echo "<h2>Adding missing columns to event_checkins...</h2>";

try {
    // Add checked_out_at column
    $pdo->exec("ALTER TABLE event_checkins ADD COLUMN IF NOT EXISTS checked_out_at TIMESTAMP DEFAULT NULL");
    echo "✅ Added checked_out_at column<br>";
} catch (PDOException $e) {
    echo "⚠️ checked_out_at: " . $e->getMessage() . "<br>";
}

try {
    // Add checkin_photo column
    $pdo->exec("ALTER TABLE event_checkins ADD COLUMN IF NOT EXISTS checkin_photo VARCHAR(255) DEFAULT NULL");
    echo "✅ Added checkin_photo column<br>";
} catch (PDOException $e) {
    echo "⚠️ checkin_photo: " . $e->getMessage() . "<br>";
}

try {
    // Add checkout_photo column
    $pdo->exec("ALTER TABLE event_checkins ADD COLUMN IF NOT EXISTS checkout_photo VARCHAR(255) DEFAULT NULL");
    echo "✅ Added checkout_photo column<br>";
} catch (PDOException $e) {
    echo "⚠️ checkout_photo: " . $e->getMessage() . "<br>";
}

echo "<br><h3>Verifying columns...</h3>";

// Verify
$stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'event_checkins' ORDER BY ordinal_position");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td></tr>";
}
echo "</table>";

echo "<br><h3>✅ Migration Complete!</h3>";
echo "<p><a href='../shared/youth/events.php'>Go to Events Page</a></p>";
