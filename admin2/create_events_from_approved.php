<?php
/**
 * One-time script: Create events from already-approved assistance requests
 * Run this once to create events for requests that were approved before the auto-creation feature
 */
require_once 'config.php';
requireLogin();

$pdo = db();
$admin = currentAdmin();

// Get all approved requests that don't have events yet
$approvedRequests = $pdo->query("
    SELECT r.*, o.name as org_name 
    FROM assistance_requests r 
    JOIN organizations o ON o.id = r.organization_id 
    WHERE r.status = 'approved'
    ORDER BY r.id
")->fetchAll();

$created = 0;
$skipped = 0;
$errors = [];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Create Events</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:40px;max-width:900px;margin:0 auto}";
echo ".success{background:#e8f5e9;color:#2e7d32;padding:12px 16px;border-radius:8px;margin:8px 0}";
echo ".error{background:#ffebee;color:#c62828;padding:12px 16px;border-radius:8px;margin:8px 0}";
echo ".info{background:#e3f2fd;color:#1565c0;padding:12px 16px;border-radius:8px;margin:8px 0}";
echo "h1{color:#1565c0}</style></head><body>";
echo "<h1>🎉 Create Events from Approved Requests</h1>";

foreach ($approvedRequests as $request) {
    $reqId = $request['id'];
    $eventTitle = $request['title'];
    $eventDesc = $request['description'] ?? '';
    $eventDate = $request['scheduled_date'];
    $orgId = $request['organization_id'];
    $requestType = $request['request_type'] ?? 'official_event';
    
    echo "<div class='info'><strong>Request #{$reqId}:</strong> {$eventTitle} ({$request['org_name']})</div>";
    
    // Check if event already exists for this request
    $existingEvent = $pdo->prepare("
        SELECT e.id 
        FROM events e 
        JOIN assistance_timeline t ON t.note LIKE CONCAT('Event #', e.id, '%')
        WHERE t.request_id = ?
        LIMIT 1
    ");
    $existingEvent->execute([$reqId]);
    $existing = $existingEvent->fetch();
    
    if ($existing) {
        echo "<div style='padding-left:20px;color:#94a3b8'>⏭️ Event #{$existing['id']} already exists. Skipped.</div>";
        $skipped++;
        continue;
    }
    
    // Create event
    try {
        // Generate QR token for check-in
        $qrToken = bin2hex(random_bytes(16));
        $checkinCode = strtoupper(substr(md5($qrToken), 0, 6));
        
        $pdo->prepare('INSERT INTO events 
            (organization_id, title, description, event_date, event_type, created_by, merit_points, qr_token, checkin_code, checkin_open, created_at) 
            VALUES (?,?,?,?,?,?,2,?,?,TRUE,CURRENT_TIMESTAMP)')
            ->execute([$orgId, $eventTitle, $eventDesc, $eventDate, $requestType, $admin['id'], $qrToken, $checkinCode]);
        
        $eventId = (int)$pdo->lastInsertId();
        
        // Add note to timeline about event creation
        $pdo->prepare('INSERT INTO assistance_timeline (request_id,status,note,done_by,created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)')
            ->execute([$reqId, 'Event Created', 'Event #' . $eventId . ' created in Events Management with check-in code: ' . $checkinCode, $admin['id']]);
        
        echo "<div class='success'>✅ Created Event #{$eventId} with check-in code: <strong>{$checkinCode}</strong></div>";
        $created++;
        
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        echo "<div class='error'>❌ Failed: {$errorMsg}</div>";
        $errors[] = "Request #{$reqId}: {$errorMsg}";
    }
}

echo "<hr style='margin:30px 0;border:none;border-top:2px solid #e2e8f0'>";
echo "<h2>Summary</h2>";
echo "<div class='success'>✅ Events created: <strong>{$created}</strong></div>";
echo "<div class='info'>⏭️ Already existed (skipped): <strong>{$skipped}</strong></div>";

if ($errors) {
    echo "<div class='error'>❌ Errors: <strong>" . count($errors) . "</strong>";
    echo "<ul>";
    foreach ($errors as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul></div>";
}

echo "<div style='margin-top:30px'>";
echo "<a href='events.php' style='display:inline-block;background:#1565c0;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600'>📅 View Events</a> ";
echo "<a href='assistance.php' style='display:inline-block;background:#2e7d32;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600'>📋 View Assistance Requests</a>";
echo "</div>";

echo "</body></html>";
