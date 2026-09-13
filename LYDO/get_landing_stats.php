<?php
// API endpoint to fetch landing page statistics
header('Content-Type: application/json');
require_once 'lydo-system/shared/config.php';

try {
    $pdo = db();
    
    // Count registered youth
    $youthCount = $pdo->query("SELECT COUNT(*) FROM youth_users")->fetchColumn();
    
    // Count volunteer programs
    $programsCount = $pdo->query("SELECT COUNT(*) FROM volunteer_programs WHERE status='active'")->fetchColumn();
    
    // Count events held (past events with at least one check-in)
    $eventsCount = $pdo->query("SELECT COUNT(DISTINCT e.id) FROM events e WHERE e.event_date < CURDATE()")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'youth' => (int)$youthCount,
        'programs' => (int)$programsCount,
        'events' => (int)$eventsCount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'youth' => 0,
        'programs' => 0,
        'events' => 0
    ]);
}
