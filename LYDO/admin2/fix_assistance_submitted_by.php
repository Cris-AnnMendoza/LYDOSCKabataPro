<?php
require_once 'config.php';

$pdo = db();

try {
    // Make submitted_by nullable to allow president submissions
    $pdo->exec("ALTER TABLE assistance_requests MODIFY submitted_by INT UNSIGNED NULL");
    
    // Also drop the foreign key constraint if needed
    try {
        $pdo->exec("ALTER TABLE assistance_requests DROP FOREIGN KEY assistance_requests_ibfk_1");
    } catch (PDOException $e) {
        // Constraint might not exist, continue
    }
    
    echo "✓ Database fixed! Redirecting...";
    header("refresh:2;url=../org-president/assistance.php");
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>
