<?php
/**
 * Test Script: Create Sample Events for Calendar Testing
 * 
 * Run this once to populate the database with test events
 * Then check the dashboard to see the calendar with events
 */

require_once 'config.php';
requireLogin();

$pdo = db();

// Sample events to create
$sampleEvents = [
    [
        'title' => 'Youth Leadership Seminar',
        'event_date' => date('Y-m-d', strtotime('+5 days')),
        'event_time' => '09:00:00',
        'event_type' => 'seminar',
        'venue' => 'Municipal Hall, Sta. Cruz',
        'description' => 'Leadership training for youth leaders',
        'merit_points' => 10,
        'organization_id' => 1
    ],
    [
        'title' => 'Skills Workshop: Web Development',
        'event_date' => date('Y-m-d', strtotime('+7 days')),
        'event_time' => '13:00:00',
        'event_type' => 'workshop',
        'venue' => 'LYDO Computer Lab',
        'description' => 'Learn web development basics',
        'merit_points' => 15,
        'organization_id' => 1
    ],
    [
        'title' => 'Community Clean-Up Activity',
        'event_date' => date('Y-m-d', strtotime('+10 days')),
        'event_time' => '07:00:00',
        'event_type' => 'activity',
        'venue' => 'Barangay 1 - Poblacion',
        'description' => 'Volunteer activity for clean environment',
        'merit_points' => 20,
        'organization_id' => 1
    ],
    [
        'title' => 'Mental Health Awareness Training',
        'event_date' => date('Y-m-d', strtotime('+14 days')),
        'event_time' => '10:00:00',
        'event_type' => 'training',
        'venue' => 'Social Welfare Office',
        'description' => 'Mental health and wellness training',
        'merit_points' => 12,
        'organization_id' => 1
    ],
    [
        'title' => 'Monthly Youth Council Meeting',
        'event_date' => date('Y-m-d', strtotime('+3 days')),
        'event_time' => '15:00:00',
        'event_type' => 'meeting',
        'venue' => 'LYDO Office',
        'description' => 'Regular monthly meeting',
        'merit_points' => 5,
        'organization_id' => 1
    ],
    [
        'title' => 'Sports Fest 2026',
        'event_date' => date('Y-m-d', strtotime('+21 days')),
        'event_time' => '08:00:00',
        'event_type' => 'activity',
        'venue' => 'Municipal Gymnasium',
        'description' => 'Annual sports competition',
        'merit_points' => 25,
        'organization_id' => 1
    ],
    [
        'title' => 'Career Guidance Seminar',
        'event_date' => date('Y-m-d', strtotime('+15 days')),
        'event_time' => '14:00:00',
        'event_type' => 'seminar',
        'venue' => 'Town Plaza',
        'description' => 'Career planning and guidance',
        'merit_points' => 10,
        'organization_id' => 1
    ],
    [
        'title' => 'Arts and Culture Workshop',
        'event_date' => date('Y-m-d', strtotime('+28 days')),
        'event_time' => '10:00:00',
        'event_type' => 'workshop',
        'venue' => 'Cultural Center',
        'description' => 'Traditional arts and crafts',
        'merit_points' => 15,
        'organization_id' => 1
    ]
];

$created = 0;

foreach ($sampleEvents as $event) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO events (
                title, event_date, event_time, event_type, 
                venue, description, merit_points, organization_id,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        
        $stmt->execute([
            $event['title'],
            $event['event_date'],
            $event['event_time'],
            $event['event_type'],
            $event['venue'],
            $event['description'],
            $event['merit_points'],
            $event['organization_id']
        ]);
        
        $created++;
    } catch (Exception $e) {
        // Skip if event already exists
        continue;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Test Events Created</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
body{font-family:Inter,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.container{background:#fff;border-radius:20px;padding:40px;max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
h1{color:#667eea;margin-bottom:20px;font-size:2rem}
.success{background:#e8f5e9;border-left:4px solid #2e7d32;padding:20px;border-radius:10px;margin:20px 0}
.success h2{color:#2e7d32;margin-bottom:10px}
.success p{color:#1b5e20;line-height:1.6}
.event-list{margin:20px 0}
.event-item{background:#f8fafc;padding:12px 16px;border-radius:8px;margin-bottom:8px;border-left:3px solid #1565c0}
.event-title{font-weight:600;color:#1e293b;margin-bottom:4px}
.event-date{font-size:.85rem;color:#64748b}
.btn{display:inline-block;padding:12px 24px;background:#1565c0;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;margin-top:20px;transition:.2s}
.btn:hover{background:#0d3b6e;transform:translateY(-2px)}
</style>
</head>
<body>
<div class="container">
    <h1>✅ Test Events Created!</h1>
    
    <div class="success">
        <h2>Success!</h2>
        <p><strong><?= $created ?></strong> sample events have been created in the database.</p>
    </div>

    <p style="color:#64748b;margin:20px 0">The following events are now available:</p>

    <div class="event-list">
        <?php foreach ($sampleEvents as $event): ?>
        <div class="event-item">
            <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
            <div class="event-date">
                📅 <?= date('F j, Y', strtotime($event['event_date'])) ?> at 
                <?= date('g:i A', strtotime($event['event_time'])) ?> • 
                📍 <?= htmlspecialchars($event['venue']) ?> • 
                ⭐ <?= $event['merit_points'] ?> points
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="color:#64748b;margin:20px 0;padding:16px;background:#fff8e1;border-radius:8px;border-left:3px solid #f57f17">
        <strong>Note:</strong> These are test events for calendar demonstration. 
        You can delete them later from the Events page if needed.
    </p>

    <a href="dashboard.php" class="btn">
        <i class="fas fa-arrow-left"></i> Go to Dashboard
    </a>
    
    <a href="events.php" style="margin-left:10px" class="btn">
        <i class="fas fa-calendar"></i> Manage Events
    </a>
</div>
</body>
</html>
