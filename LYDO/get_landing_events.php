<?php
// API endpoint to fetch upcoming events for landing page
header('Content-Type: application/json');
require_once 'lydo-system/shared/config.php';

try {
    $pdo = db();
    
    // Get next 3 upcoming events, or if none, get recent past events
    $stmt = $pdo->prepare("
        SELECT 
            title,
            event_date,
            location,
            description,
            event_type
        FROM events 
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC
        LIMIT 3
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no upcoming events, get recent past events
    if (empty($events)) {
        $stmt = $pdo->prepare("
            SELECT 
                title,
                event_date,
                location,
                description,
                event_type
            FROM events 
            ORDER BY event_date DESC
            LIMIT 3
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format events for display
    $formattedEvents = [];
    foreach ($events as $event) {
        $date = new DateTime($event['event_date']);
        $formattedEvents[] = [
            'day' => $date->format('d'),
            'month' => $date->format('M'),
            'name' => $event['title'],
            'location' => $event['location'] ?: 'TBA',
            'type' => $event['event_type'] ?: 'Event',
            'description' => $event['description'] ?: ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $formattedEvents
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'events' => []
    ]);
}
