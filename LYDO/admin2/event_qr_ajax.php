<?php
/**
 * AJAX endpoint for rotating QR code URL
 * Returns new QR URL every 30 seconds
 */
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$pdo     = db();
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
    exit;
}

// Get event and its QR token
$evStmt = $pdo->prepare('SELECT qr_token FROM events WHERE id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();

if (!$event || !$event['qr_token']) {
    echo json_encode(['success' => false, 'error' => 'Event not found']);
    exit;
}

// Build base check-in URL
$host = $_SERVER['HTTP_HOST'];
if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0) {
    $localIp = '';
    if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1') {
        $localIp = $_SERVER['SERVER_ADDR'];
    }
    if (!$localIp) {
        $hostname = gethostname();
        if ($hostname) {
            $ip = gethostbyname($hostname);
            if ($ip && $ip !== $hostname && $ip !== '127.0.0.1') {
                $localIp = $ip;
            }
        }
    }
    if (!$localIp) {
        $localIp = 'localhost';
    }
    $host = $localIp;
}
$baseUrl = 'http://' . $host . '/LYDO/lydo-system/event_checkin.php?token=' . urlencode($event['qr_token']);

// Generate rotating QR URL (30-second window)
$window = floor(time() / 30);
$rotatingToken = substr(md5($eventId . $event['qr_token'] . $window . 'QR'), 0, 16);
$qrUrl = $baseUrl . '&rt=' . $rotatingToken;

// Calculate seconds until next rotation
$secondsLeft = 30 - (time() % 30);

echo json_encode([
    'success' => true,
    'qr_url' => $qrUrl,
    'seconds_left' => $secondsLeft
]);
