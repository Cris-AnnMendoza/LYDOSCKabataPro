<?php
/**
 * Reset to MySQL - Clean Database Setup
 * Run this: http://localhost/LYDO/lydo-system/database/reset_to_mysql.php
 */

echo "<h1>🔄 Resetting to MySQL Database</h1>";
echo "<p>This will recreate all tables in MySQL format...</p>";

// Connect to MySQL
$mysqli = new mysqli('127.0.0.1', 'root', '', 'local_youth_development_db');

if ($mysqli->connect_error) {
    die("❌ Connection failed: " . $mysqli->connect_error);
}

echo "<h2>✅ Connected to MySQL</h2>";

// Read and execute the MySQL schema
$sqlFile = __DIR__ . '/local_youth_development_db.sql';

if (!file_exists($sqlFile)) {
    die("❌ SQL file not found: $sqlFile");
}

echo "<h2>📄 Loading SQL file...</h2>";

$sql = file_get_contents($sqlFile);

// Split by semicolons and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) continue;
    
    if ($mysqli->query($statement)) {
        $success++;
    } else {
        $errors++;
        echo "<div style='color:orange'>⚠️ " . substr($statement, 0, 100) . "... - " . $mysqli->error . "</div>";
    }
}

echo "<h2>📊 Results</h2>";
echo "<p>✅ Successful: $success statements</p>";
echo "<p>⚠️ Errors: $errors statements</p>";

// Show tables
echo "<h2>📋 Tables Created:</h2>";
$result = $mysqli->query("SHOW TABLES");
echo "<ul>";
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_assoc()['cnt'];
    echo "<li><strong>$table</strong> ($count rows)</li>";
}
echo "</ul>";

$mysqli->close();

echo "<h2>✅ Done! MySQL Database Reset Complete</h2>";
echo "<p><a href='../admin2/dashboard.php'>Go to Admin Dashboard</a></p>";
echo "<p><a href='../shared/youth/events.php'>Go to Youth Events</a></p>";
