<?php
require_once 'config.php';
requireLogin();

$pdo = db();
$eventId = 5; // Leadership Training event

// Get event details
$event = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$event->execute([$eventId]);
$event = $event->fetch();

// Get all youth users for testing
$users = $pdo->query('SELECT id, first_name, last_name, email FROM youth_users LIMIT 10')->fetchAll();

// Handle test actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];
    
    if ($action === 'checkin') {
        // Simulate check-in
        try {
            $pdo->prepare('INSERT INTO event_checkins (event_id, user_id, ip_address) VALUES (?, ?, ?)')->execute([$eventId, $userId, '127.0.0.1']);
            $message = "✅ User ID $userId checked in successfully!";
        } catch (Exception $e) {
            $message = "⚠️ Check-in failed: " . $e->getMessage();
        }
    } elseif ($action === 'checkout') {
        // Check if already checked in
        $checkin = $pdo->prepare('SELECT * FROM event_checkins WHERE event_id = ? AND user_id = ?');
        $checkin->execute([$eventId, $userId]);
        $checkin = $checkin->fetch();
        
        if (!$checkin) {
            $message = "❌ Cannot checkout - user not checked in yet!";
        } else {
            // Generate certificate
            $certNumber = 'CERT-' . $eventId . '-' . $userId . '-' . time();
            try {
                $pdo->prepare('INSERT INTO event_certificates (event_id, user_id, cert_number, uploaded_at, upload_filename, upload_original) VALUES (?, ?, ?, NOW(), ?, ?)')
                    ->execute([$eventId, $userId, $certNumber, 'test_cert.pdf', 'Test Certificate.pdf']);
                $message = "✅ User ID $userId checked out! Certificate generated: $certNumber";
            } catch (Exception $e) {
                $message = "⚠️ Checkout/Certificate failed: " . $e->getMessage();
            }
        }
    } elseif ($action === 'award_merit') {
        // Award merit points
        $cert = $pdo->prepare('SELECT * FROM event_certificates WHERE event_id = ? AND user_id = ?');
        $cert->execute([$eventId, $userId]);
        $cert = $cert->fetch();
        
        if (!$cert) {
            $message = "❌ Cannot award merit - no certificate found!";
        } elseif ($cert['merit_awarded']) {
            $message = "⚠️ Merit already awarded!";
        } else {
            // Award merit
            $pdo->prepare('UPDATE event_certificates SET merit_awarded = 1, merit_points = 2 WHERE id = ?')->execute([$cert['id']]);
            
            // Add to merit log
            $pdo->prepare('INSERT INTO user_merit_logs (user_id, points, type, reason, category, event_id, cert_id) VALUES (?, 2, "merit", "Event attendance certificate uploaded", "Event Participation", ?, ?)')
                ->execute([$userId, $eventId, $cert['id']]);
            
            $message = "✅ User ID $userId awarded 2 merit points!";
        }
    } elseif ($action === 'reset') {
        // Reset user's data for this event
        $pdo->prepare('DELETE FROM event_checkins WHERE event_id = ? AND user_id = ?')->execute([$eventId, $userId]);
        $pdo->prepare('DELETE FROM event_certificates WHERE event_id = ? AND user_id = ?')->execute([$eventId, $userId]);
        $pdo->prepare('DELETE FROM user_merit_logs WHERE event_id = ? AND user_id = ?')->execute([$eventId, $userId]);
        $message = "🔄 User ID $userId reset - all event data cleared!";
    }
}

// Get current status for all users
$statusData = [];
foreach ($users as $user) {
    $uid = $user['id'];
    
    // Check-in status
    $checkin = $pdo->prepare('SELECT checked_in_at FROM event_checkins WHERE event_id = ? AND user_id = ?');
    $checkin->execute([$eventId, $uid]);
    $checkin = $checkin->fetch();
    
    // Certificate status
    $cert = $pdo->prepare('SELECT cert_number, merit_awarded, merit_points FROM event_certificates WHERE event_id = ? AND user_id = ?');
    $cert->execute([$eventId, $uid]);
    $cert = $cert->fetch();
    
    // Merit points total
    $merit = $pdo->prepare('SELECT SUM(points) FROM user_merit_logs WHERE user_id = ? AND type = "merit"');
    $merit->execute([$uid]);
    $totalMerit = (int)$merit->fetchColumn();
    
    $statusData[$uid] = [
        'checkin' => $checkin,
        'cert' => $cert,
        'total_merit' => $totalMerit
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>QR Check-in/Check-out Flow Test</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { color: #1565c0; margin-bottom: 10px; }
.event-info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1565c0; }
.message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
.message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
.message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th { background: #1565c0; color: white; padding: 12px; text-align: left; font-weight: 600; }
td { padding: 12px; border-bottom: 1px solid #e0e0e0; }
tr:hover { background: #f5f5f5; }
.status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
.status-checkin { background: #e8f5e9; color: #2e7d32; }
.status-checkout { background: #fff3e0; color: #f57f17; }
.status-merit { background: #e1bee7; color: #6a1b9a; }
.status-none { background: #f5f5f5; color: #757575; }
button { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; margin: 2px; transition: 0.2s; }
.btn-checkin { background: #e8f5e9; color: #2e7d32; border: 1.5px solid #a5d6a7; }
.btn-checkin:hover { background: #2e7d32; color: white; }
.btn-checkout { background: #fff3e0; color: #f57f17; border: 1.5px solid #ffb74d; }
.btn-checkout:hover { background: #f57f17; color: white; }
.btn-merit { background: #e1bee7; color: #6a1b9a; border: 1.5px solid #ba68c8; }
.btn-merit:hover { background: #6a1b9a; color: white; }
.btn-reset { background: #ffebee; color: #c62828; border: 1.5px solid #ef9a9a; }
.btn-reset:hover { background: #c62828; color: white; }
.flow-diagram { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
.flow-step { display: inline-block; padding: 10px 20px; background: #e3f2fd; border-radius: 8px; margin: 5px; font-weight: 600; color: #1565c0; }
.arrow { display: inline-block; margin: 0 10px; color: #1565c0; font-size: 1.5rem; }
</style>
</head>
<body>

<div class="container">
    <h1>🧪 QR Check-in/Check-out Flow Test</h1>
    <p style="color: #757575; margin-bottom: 20px;">Test the complete flow: Check-in → Check-out → Certificate → Merit Points</p>
    
    <div class="event-info">
        <strong>📅 Event:</strong> <?= htmlspecialchars($event['title']) ?><br>
        <strong>📍 Location:</strong> <?= htmlspecialchars($event['location']) ?><br>
        <strong>🔓 Check-in Status:</strong> <?= $event['checkin_open'] ? '<span style="color:#2e7d32">OPEN</span>' : '<span style="color:#c62828">CLOSED</span>' ?>
    </div>
    
    <div class="flow-diagram">
        <strong>Expected Flow:</strong><br><br>
        <span class="flow-step">1. Check-in</span>
        <span class="arrow">→</span>
        <span class="flow-step">2. Check-out</span>
        <span class="arrow">→</span>
        <span class="flow-step">3. Certificate Generated</span>
        <span class="arrow">→</span>
        <span class="flow-step">4. Merit Awarded (2 pts)</span>
    </div>
    
    <?php if ($message): ?>
    <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Check-in Status</th>
                <th>Certificate</th>
                <th>Merit (Event)</th>
                <th>Total Merit</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): 
            $status = $statusData[$user['id']];
        ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong></td>
                <td>
                    <?php if ($status['checkin']): ?>
                        <span class="status-badge status-checkin">
                            ✓ Checked in<br>
                            <small style="font-size:0.75rem"><?= date('h:i A', strtotime($status['checkin']['checked_in_at'])) ?></small>
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-none">Not checked in</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($status['cert']): ?>
                        <span class="status-badge status-checkout">
                            📜 <?= htmlspecialchars($status['cert']['cert_number']) ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-none">No certificate</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($status['cert'] && $status['cert']['merit_awarded']): ?>
                        <span class="status-badge status-merit">
                            ⭐ <?= $status['cert']['merit_points'] ?> points
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-none">Not awarded</span>
                    <?php endif; ?>
                </td>
                <td><strong style="color:#6a1b9a"><?= $status['total_merit'] ?> pts</strong></td>
                <td>
                    <form method="POST" style="display:inline-block">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        
                        <?php if (!$status['checkin']): ?>
                        <button type="submit" name="action" value="checkin" class="btn-checkin">
                            📥 Check-in
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($status['checkin'] && !$status['cert']): ?>
                        <button type="submit" name="action" value="checkout" class="btn-checkout">
                            📤 Check-out + Cert
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($status['cert'] && !$status['cert']['merit_awarded']): ?>
                        <button type="submit" name="action" value="award_merit" class="btn-merit">
                            ⭐ Award Merit
                        </button>
                        <?php endif; ?>
                        
                        <button type="submit" name="action" value="reset" class="btn-reset" onclick="return confirm('Reset all data for this user?')">
                            🔄 Reset
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 30px; padding: 20px; background: #fff3e0; border-radius: 8px; border-left: 4px solid #f57f17;">
        <strong>💡 How to Test:</strong>
        <ol style="margin: 10px 0;">
            <li><strong>Check-in:</strong> Click "📥 Check-in" to simulate QR scan check-in</li>
            <li><strong>Check-out:</strong> Click "📤 Check-out + Cert" to simulate checkout and auto-generate certificate</li>
            <li><strong>Award Merit:</strong> Click "⭐ Award Merit" to award 2 merit points</li>
            <li><strong>Reset:</strong> Click "🔄 Reset" to clear all data and start over</li>
        </ol>
    </div>
    
    <div style="margin-top: 20px; text-align: center;">
        <a href="event_qr.php?id=<?= $eventId ?>" style="padding: 12px 24px; background: #1565c0; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
            ← Back to Event QR Page
        </a>
    </div>
</div>

</body>
</html>
