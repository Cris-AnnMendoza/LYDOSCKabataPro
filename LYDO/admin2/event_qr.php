<?php
/**
 * Event QR Code Display Page
 * Admin/org head opens this and shows the QR on screen for attendees to scan.
 */

// Prevent caching - force fresh data every time
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config.php';
requireLogin();

$pdo     = db();
$admin   = currentAdmin();
$eventId = (int)($_GET['id'] ?? 0);

// ── Handle POST actions FIRST (before fetching event) ────
// Toggle check-in open/closed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_checkin'])) {
    error_log("Toggle checkin clicked for event $eventId");
    $currentState = $pdo->prepare('SELECT checkin_open FROM events WHERE id = ?');
    $currentState->execute([$eventId]);
    $current = $currentState->fetchColumn();
    error_log("Current checkin_open: $current");
    $newState = $current ? 0 : 1;
    error_log("New state will be: $newState");
    $result = $pdo->prepare('UPDATE events SET checkin_open = ? WHERE id = ?')->execute([$newState, $eventId]);
    error_log("Update result: " . ($result ? 'success' : 'failed'));
    // Add timestamp to force browser to reload fresh data
    header('Location: event_qr.php?id=' . $eventId . '&_=' . time());
    exit;
}

// Toggle checkout manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_checkout'])) {
    error_log("Toggle checkout clicked for event $eventId");
    $currentState = $pdo->prepare('SELECT checkout_open FROM events WHERE id = ?');
    $currentState->execute([$eventId]);
    $current = $currentState->fetchColumn();
    error_log("Current checkout_open: " . ($current ?: 'NULL/0'));
    $newState = empty($current) ? 1 : 0;
    error_log("New checkout state will be: $newState");
    try {
        $result = $pdo->prepare('UPDATE events SET checkout_open = ? WHERE id = ?')->execute([$newState, $eventId]);
        error_log("Checkout update result: " . ($result ? 'success' : 'failed'));
    } catch (PDOException $e) {
        error_log("Checkout column missing, adding it: " . $e->getMessage());
        // column may not exist yet — add it
        $pdo->exec("ALTER TABLE events ADD COLUMN checkout_open TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->prepare('UPDATE events SET checkout_open = ? WHERE id = ?')->execute([$newState, $eventId]);
        error_log("Added column and updated");
    }
    // Add timestamp to force browser to reload fresh data
    header('Location: event_qr.php?id=' . $eventId . '&_=' . time());
    exit;
}

// ── Now fetch event data ──────────────────────────────────

// ── Ensure required tables exist ─────────────────────────
// Only for MySQL - PostgreSQL/Supabase tables already exist
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
// No foreign keys — avoids FK constraint failures on older MySQL
$pdo->exec("CREATE TABLE IF NOT EXISTS event_checkins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    checked_in_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    UNIQUE KEY uq_event_user_checkin (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS event_certificates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    cert_number     VARCHAR(50)  NOT NULL,
    generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_at     DATETIME     DEFAULT NULL,
    upload_filename VARCHAR(255) DEFAULT NULL,
    upload_original VARCHAR(255) DEFAULT NULL,
    merit_awarded   TINYINT(1)   NOT NULL DEFAULT 0,
    merit_points    TINYINT      NOT NULL DEFAULT 2,
    UNIQUE KEY uq_event_user_cert (event_id, user_id),
    UNIQUE KEY uq_cert_number (cert_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_merit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    points     SMALLINT     NOT NULL,
    type       ENUM('merit','demerit') NOT NULL,
    reason     VARCHAR(255) NOT NULL,
    category   VARCHAR(100) DEFAULT NULL,
    event_id   INT UNSIGNED DEFAULT NULL,
    cert_id    INT UNSIGNED DEFAULT NULL,
    awarded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    message    TEXT         NOT NULL,
    type       VARCHAR(50)  DEFAULT 'info',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns to events if missing (duplicate column error = already exists, safe to ignore)
foreach ([
    "ALTER TABLE events ADD COLUMN event_type VARCHAR(50) NOT NULL DEFAULT 'official_event'",
    "ALTER TABLE events ADD COLUMN requires_representative TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE events ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN checkin_open TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE events ADD COLUMN merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2",
    "ALTER TABLE events ADD COLUMN event_start_time TIME DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN event_end_time TIME DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN checkout_open TINYINT(1) NOT NULL DEFAULT 0",
] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* column already exists */ }
}
} // End MySQL-only migration

if (!$eventId) { header('Location: events.php'); exit; }

$evStmt = $pdo->prepare('SELECT e.*, o.name as org_name FROM events e LEFT JOIN organizations o ON o.id = e.organization_id WHERE e.id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();

if (!$event) { header('Location: events.php'); exit; }

// Generate token if missing
if (!$event['qr_token']) {
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE events SET qr_token = ? WHERE id = ?')->execute([$token, $eventId]);
    $event['qr_token'] = $token;
}

// Build check-in URL using the real server IP (not localhost)
// so phones on the same WiFi can scan and reach the page
$host = $_SERVER['HTTP_HOST'];
if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0) {
    // Get the actual local network IP without sockets extension
    $localIp = '';
    // Method 1: SERVER_ADDR (works when accessed via IP already)
    if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1') {
        $localIp = $_SERVER['SERVER_ADDR'];
    }
    // Method 2: gethostbyname (works on most systems)
    if (!$localIp) {
        $hostname = gethostname();
        if ($hostname) {
            $ip = gethostbyname($hostname);
            if ($ip && $ip !== $hostname && $ip !== '127.0.0.1') {
                $localIp = $ip;
            }
        }
    }
    // Method 3: fallback to localhost if nothing works
    if (!$localIp) {
        $localIp = 'localhost';
    }
    $host = $localIp;
}
$checkinUrl = 'http://' . $host . '/LYDO/lydo-system/event_checkin.php?token=' . urlencode($event['qr_token']);

// ── Rotating QR URL (changes every 30 seconds) ──
function getRotatingQRUrl(string $baseUrl, int $eventId, string $qrToken): string {
    $window = floor(time() / 30); // 30-second windows
    $rotatingToken = substr(md5($eventId . $qrToken . $window . 'QR'), 0, 16);
    return $baseUrl . '&rt=' . $rotatingToken;
}

function getSecondsUntilNextRotation(): int {
    return 30 - (time() % 30);
}

$rotatingQRUrl = getRotatingQRUrl($checkinUrl, $eventId, $event['qr_token']);
$secsLeft = getSecondsUntilNextRotation();

// ── Code availability logic ───────────────────────────────
$now       = date('H:i:s');
$startTime = $event['event_start_time'] ?? null;
$endTime   = $event['event_end_time']   ?? null;

// Normalize H:i → H:i:s
if ($startTime && strlen($startTime) === 5) $startTime .= ':00';
if ($endTime   && strlen($endTime)   === 5) $endTime   .= ':00';

// Check-in: available from start_time until start_time + 15 minutes
$checkinAvailable = false;
$checkinDeadline  = null;
if ($startTime && $event['checkin_open']) {
    $startTs         = strtotime(date('Y-m-d') . ' ' . $startTime);
    $checkinDeadline = date('H:i:s', $startTs + 900); // +15 min
    $checkinAvailable = $now >= $startTime && $now < $checkinDeadline;
} elseif ($event['checkin_open'] && !$startTime) {
    // No start time set — always open while checkin_open=1
    $checkinAvailable = true;
}

// Check-out: available from end_time onwards OR if manually opened
$checkoutAvailable = false;
if ($event['checkin_open']) {
    if ($endTime && $now >= $endTime) {
        $checkoutAvailable = true; // auto: end time reached
    } elseif (!empty($event['checkout_open'])) {
        $checkoutAvailable = true; // manual: admin opened it
    }
}

// Seconds until check-in closes (for JS countdown)
$secsUntilCheckinClose = 0;
if ($checkinAvailable && $checkinDeadline) {
    $secsUntilCheckinClose = strtotime(date('Y-m-d') . ' ' . $checkinDeadline) - time();
    if ($secsUntilCheckinClose < 0) $secsUntilCheckinClose = 0;
}

// Seconds until checkout opens (for JS auto-reload)
$secsUntilEnd = 0;
if ($endTime && $now < $endTime) {
    $secsUntilEnd = strtotime(date('Y-m-d') . ' ' . $endTime) - time();
    if ($secsUntilEnd < 0) $secsUntilEnd = 0;
}

// Load check-in stats
$totalCheckins = (int)$pdo->prepare('SELECT COUNT(*) FROM event_checkins WHERE event_id = ?')
    ->execute([$eventId]) ? 0 : 0;
$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM event_checkins WHERE event_id = ?');
$cntStmt->execute([$eventId]);
$totalCheckins = (int)$cntStmt->fetchColumn();

$certStmt = $pdo->prepare('SELECT COUNT(*) FROM event_certificates WHERE event_id = ? AND uploaded_at IS NOT NULL');
$certStmt->execute([$eventId]);
$totalUploaded = (int)$certStmt->fetchColumn();

$meritStmt = $pdo->prepare('SELECT COUNT(*) FROM event_certificates WHERE event_id = ? AND merit_awarded = TRUE');
$meritStmt->execute([$eventId]);
$totalMerit = (int)$meritStmt->fetchColumn();

// Recent check-ins (include photos)
$recentStmt = $pdo->prepare('
    SELECT c.checked_in_at, c.checked_out_at, c.checkin_photo, c.checkout_photo,
           u.id as user_id, u.first_name, u.last_name, u.barangay, u.email,
           cert.cert_number, cert.merit_awarded, cert.uploaded_at
    FROM event_checkins c
    JOIN youth_users u ON u.id = c.user_id
    LEFT JOIN event_certificates cert ON cert.event_id = c.event_id AND cert.user_id = c.user_id
    WHERE c.event_id = ?
    ORDER BY c.checked_in_at DESC
    LIMIT 50
');
$recentStmt->execute([$eventId]);
$checkins = $recentStmt->fetchAll();

$eventDate = date('F j, Y', strtotime($event['event_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Event QR Code – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<!-- QR code library (pure JS, no server dependency) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
.qr-section{display:grid;grid-template-columns:auto 1fr;gap:28px;align-items:start}
.qr-box{background:#fff;border:2px solid #e2e8f0;border-radius:16px;padding:24px;text-align:center;min-width:260px}
#qrcode{display:flex;justify-content:center;margin-bottom:14px}
#qrcode canvas,#qrcode img{border-radius:8px}
.qr-url{font-size:.72rem;color:#94a3b8;word-break:break-all;margin-top:8px;padding:8px;background:#f8fafc;border-radius:6px;font-family:monospace}
.qr-status{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:50px;font-size:.8rem;font-weight:700;margin-bottom:12px}
.qr-open{background:#e8f5e9;color:#2e7d32}
.qr-closed{background:#ffebee;color:#c62828}
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.stat-mini{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center}
.stat-mini .val{font-size:1.6rem;font-weight:800;color:#0d3b6e;line-height:1}
.stat-mini .lbl{font-size:.75rem;color:#94a3b8;margin-top:4px;font-weight:500}
.checkin-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:.83rem;align-items:center}
.checkin-row:hover{background:#f8fafc}
.checkin-header{background:#f1f5f9;font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#475569;border-radius:8px 8px 0 0}
.badge-sm{padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge-green{background:#e8f5e9;color:#2e7d32}
.badge-orange{background:#fff8e1;color:#f57f17}
.badge-blue{background:#e3f2fd;color:#1565c0}
.btn-toggle-open{padding:9px 18px;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s}
.btn-toggle-open:hover{background:#2e7d32;color:#fff}
.btn-toggle-close{padding:9px 18px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s}
.btn-toggle-close:hover{background:#c62828;color:#fff}
.btn-fullscreen{padding:9px 18px;background:#e3f2fd;color:#1565c0;border:1.5px solid #90caf9;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s}
.btn-fullscreen:hover{background:#1565c0;color:#fff}
/* Fullscreen QR overlay */
.fs-overlay{display:none;position:fixed;inset:0;background:#0d3b6e;z-index:9999;flex-direction:column;align-items:center;justify-content:center;gap:20px}
.fs-overlay.open{display:flex}
.fs-overlay h2{color:#fff;font-size:1.8rem;font-weight:800;text-align:center}
.fs-overlay p{color:rgba(255,255,255,.7);font-size:1rem;text-align:center}
#qrcode-fs canvas,#qrcode-fs img{border-radius:12px}
.fs-close{position:absolute;top:20px;right:24px;background:rgba(255,255,255,.15);border:none;color:#fff;width:40px;height:40px;border-radius:10px;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center}
.fs-close:hover{background:rgba(255,255,255,.25)}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(255,255,255,.3)}50%{box-shadow:0 0 0 20px rgba(255,255,255,0)}}
/* Auto-refresh badge */
.live-badge{display:inline-flex;align-items:center;gap:5px;background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.live-dot{width:7px;height:7px;border-radius:50%;background:#2e7d32;animation:blink 1.2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
/* Photo Modal */
.photo-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10000;align-items:center;justify-content:center;padding:20px}
.photo-modal.open{display:flex}
.photo-modal-content{background:#fff;border-radius:16px;max-width:900px;width:100%;max-height:90vh;overflow-y:auto;position:relative}
.photo-modal-header{padding:20px 24px;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
.photo-modal-body{padding:24px}
.photo-modal-close{background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.photo-modal-close:hover{background:#f1f5f9;color:#475569}
.photo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px}
.photo-card{background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;overflow:hidden}
.photo-card-header{padding:12px 16px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:8px}
.photo-card-body{padding:16px;text-align:center}
.photo-card img{max-width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.photo-placeholder{padding:60px 20px;background:#f1f5f9;border:2px dashed #cbd5e1;border-radius:8px;text-align:center;color:#94a3b8}
.user-info{background:#f8fafc;border-radius:10px;padding:16px;margin-bottom:20px}
.user-info-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #e2e8f0}
.user-info-row:last-child{border-bottom:none}
.user-info-label{font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;font-weight:600;width:120px;flex-shrink:0}
.user-info-value{font-size:.9rem;color:#1e293b;font-weight:600}
.clickable-name{cursor:pointer;color:#1565c0;transition:.2s}
.clickable-name:hover{color:#0d3b6e;text-decoration:underline}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<div class="page-header">
  <div>
    <h2><i class="fas fa-qrcode" style="color:#1565c0;margin-right:8px"></i>Event QR Check-in</h2>
    <p><?= htmlspecialchars($event['title']) ?> · <?= $eventDate ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="events.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Events</a>
    <button class="btn-fullscreen" onclick="openFullscreen()"><i class="fas fa-expand"></i> Fullscreen QR</button>
    
    <!-- Combined Check-in/Check-out toggle -->
    <form method="POST" style="display:inline">
      <input type="hidden" name="toggle_checkin" value="1"/>
      <?php if ($event['checkin_open']): ?>
        <button type="submit" class="btn-toggle-close"><i class="fas fa-lock"></i> CLOSE CHECK IN/CHECK OUT</button>
      <?php else: ?>
        <button type="submit" class="btn-toggle-open"><i class="fas fa-lock-open"></i> OPEN CHECK IN/CHECK OUT</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Stats -->
<div class="stat-row">
  <div class="stat-mini">
    <div class="val"><?= $totalCheckins ?></div>
    <div class="lbl"><i class="fas fa-qrcode" style="color:#1565c0"></i> Checked In</div>
  </div>
  <div class="stat-mini">
    <div class="val"><?= $totalUploaded ?></div>
    <div class="lbl"><i class="fas fa-upload" style="color:#f57f17"></i> Certs Uploaded</div>
  </div>
  <div class="stat-mini">
    <div class="val"><?= $totalMerit ?></div>
    <div class="lbl"><i class="fas fa-star" style="color:#2e7d32"></i> Merit Awarded</div>
  </div>
</div>

<!-- QR + Info -->
<div class="qr-section">

  <!-- QR Code -->
  <div class="qr-box">
    <div class="qr-status <?= $event['checkin_open'] ? 'qr-open' : 'qr-closed' ?>">
      <i class="fas fa-<?= $event['checkin_open'] ? 'lock-open' : 'lock' ?>"></i>
      <?= $event['checkin_open'] ? 'Check-in Open' : 'Check-in Closed' ?>
    </div>
    <div id="qrcode"></div>
    <div style="font-size:.82rem;font-weight:600;color:#0d3b6e;margin-bottom:6px">Scan to Check In</div>

    <!-- 30-second countdown timer -->
    <div style="margin:10px 0;padding:12px;background:#e3f2fd;border-radius:10px;border:1.5px solid #90caf9;text-align:center">
      <div style="font-size:.7rem;font-weight:700;color:#1565c0;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">
        <i class="fas fa-clock"></i> QR Code Updates In
      </div>
      <div style="font-family:monospace;font-size:2rem;font-weight:900;color:#0d3b6e" id="countdown"><?= $secsLeft ?></div>
      <div style="font-size:.7rem;color:#64b5f6;margin-top:2px">seconds</div>
    </div>

    <button class="btn-fullscreen" style="width:100%;margin-top:12px;justify-content:center" onclick="openFullscreen()">
      <i class="fas fa-expand"></i> Show Fullscreen
    </button>
  </div>

  <!-- Event Details + Instructions -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Event Details</h3></div>
      <div style="padding:16px 18px;display:flex;flex-direction:column;gap:10px;font-size:.88rem;color:#475569">
        <div style="display:flex;gap:10px"><i class="fas fa-heading" style="color:#1565c0;width:16px;margin-top:2px"></i><div><strong style="color:#1e293b"><?= htmlspecialchars($event['title']) ?></strong></div></div>
        <div style="display:flex;gap:10px"><i class="fas fa-calendar" style="color:#1565c0;width:16px;margin-top:2px"></i><div><?= $eventDate ?><?= $event['event_time'] ? ' · ' . date('g:i A', strtotime($event['event_time'])) : '' ?></div></div>
        <?php if ($event['location']): ?>
        <div style="display:flex;gap:10px"><i class="fas fa-map-marker-alt" style="color:#1565c0;width:16px;margin-top:2px"></i><div><?= htmlspecialchars($event['location']) ?></div></div>
        <?php endif; ?>
        <div style="display:flex;gap:10px"><i class="fas fa-star" style="color:#2e7d32;width:16px;margin-top:2px"></i><div><strong style="color:#2e7d32">+<?= (int)($event['merit_points'] ?: 2) ?> merit points</strong> per certificate upload</div></div>
        <?php if ($event['org_name']): ?>
        <div style="display:flex;gap:10px"><i class="fas fa-sitemap" style="color:#1565c0;width:16px;margin-top:2px"></i><div><?= htmlspecialchars($event['org_name']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fas fa-info-circle"></i> How It Works</h3></div>
      <div style="padding:16px 18px">
        <div style="display:flex;flex-direction:column;gap:12px">
          <?php
          $steps = [
            ['qrcode','Show this QR code on screen or projector for attendees to scan','1565c0'],
            ['mobile-alt','Attendees scan with their phone — they must be logged in to the Youth Portal','1565c0'],
            ['check-circle','Check-in is recorded and a certificate is auto-generated for each attendee','2e7d32'],
            ['upload','Attendees download and upload their certificate in the Youth Portal','f57f17'],
            ['star','Merit points are automatically awarded upon certificate upload','2e7d32'],
          ];
          foreach ($steps as $i => [$icon,$text,$color]):
          ?>
          <div style="display:flex;align-items:flex-start;gap:12px;font-size:.85rem;color:#475569">
            <div style="width:28px;height:28px;border-radius:50%;background:#<?= $color ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></div>
            <div style="margin-top:4px"><?= $text ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Anti-cheat info -->
<div style="background:#fff8e1;border:1.5px solid #ffe082;border-radius:12px;padding:14px 18px;margin-top:16px;display:flex;align-items:flex-start;gap:12px">
  <i class="fas fa-shield-alt" style="color:#f57f17;font-size:1.2rem;margin-top:2px;flex-shrink:0"></i>
  <div>
    <div style="font-size:.85rem;font-weight:700;color:#e65100;margin-bottom:4px">Anti-Cheat: Rotating QR System</div>
    <div style="font-size:.8rem;color:#795548;line-height:1.6">
      The QR code <strong>rotates every 30 seconds</strong>. Youth who share screenshots with absent members are wasting their time — the QR will be invalid before they can use it. Each youth can only check in <strong>once per event</strong>.
    </div>
  </div>
</div>

<!-- Check-in List -->
<div class="card" style="margin-top:20px">
  <div class="card-header" style="justify-content:space-between">
    <h3><i class="fas fa-list-check"></i> Check-in List</h3>
    <div style="display:flex;align-items:center;gap:10px">
      <span class="live-badge"><span class="live-dot"></span> Live</span>
      <span style="font-size:.82rem;color:#94a3b8"><?= $totalCheckins ?> attendees</span>
    </div>
  </div>
  <?php if (empty($checkins)): ?>
    <div style="padding:32px;text-align:center;color:#94a3b8;font-size:.9rem">
      <i class="fas fa-qrcode" style="font-size:2rem;margin-bottom:10px;display:block"></i>
      No check-ins yet. Share the QR code with attendees.
    </div>
  <?php else: ?>
    <div class="checkin-row checkin-header">
      <div>Attendee</div><div>Checked In</div><div>Certificate</div><div>Merit</div>
    </div>
    <?php foreach ($checkins as $ci): 
      $photoData = [
        'name' => $ci['first_name'] . ' ' . $ci['last_name'],
        'email' => $ci['email'],
        'barangay' => $ci['barangay'],
        'checkin_time' => date('M j, Y g:i A', strtotime($ci['checked_in_at'])),
        'checkout_time' => $ci['checked_out_at'] ? date('M j, Y g:i A', strtotime($ci['checked_out_at'])) : null,
        'checkin_photo' => $ci['checkin_photo'],
        'checkout_photo' => $ci['checkout_photo'],
        'cert_number' => $ci['cert_number']
      ];
    ?>
    <div class="checkin-row">
      <div>
        <strong class="clickable-name" onclick='showPhotos(<?= json_encode($photoData) ?>)'>
          <?= htmlspecialchars($ci['first_name'] . ' ' . $ci['last_name']) ?>
        </strong>
        <?php if ($ci['barangay']): ?>
          <br><span style="font-size:.75rem;color:#94a3b8"><?= htmlspecialchars($ci['barangay']) ?></span>
        <?php endif; ?>
      </div>
      <div style="color:#475569"><?= date('g:i A', strtotime($ci['checked_in_at'])) ?></div>
      <div>
        <?php if ($ci['uploaded_at']): ?>
          <span class="badge-sm badge-green"><i class="fas fa-check"></i> Uploaded</span>
        <?php elseif ($ci['cert_number']): ?>
          <span class="badge-sm badge-blue"><i class="fas fa-certificate"></i> Generated</span>
        <?php else: ?>
          <span class="badge-sm" style="background:#f1f5f9;color:#94a3b8">Pending</span>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($ci['merit_awarded']): ?>
          <span class="badge-sm badge-green"><i class="fas fa-star"></i> Awarded</span>
        <?php else: ?>
          <span class="badge-sm badge-orange">Pending</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</main>
</div>

<!-- Fullscreen QR Overlay -->
<div class="fs-overlay" id="fsOverlay">
  <button class="fs-close" onclick="closeFullscreen()"><i class="fas fa-times"></i></button>
  <h2><?= htmlspecialchars($event['title']) ?></h2>
  <p><?= $eventDate ?><?= $event['location'] ? ' · ' . htmlspecialchars($event['location']) : '' ?></p>
  <div id="qrcode-fs" class="pulse"></div>
  <p style="font-size:1.1rem;font-weight:700">Scan to Check In</p>
  
  <!-- 30-second countdown in fullscreen -->
  <div style="background:rgba(255,255,255,.15);border-radius:14px;padding:16px 28px;text-align:center;margin-top:12px">
    <div style="font-size:.8rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">
      <i class="fas fa-clock"></i> QR Updates In
    </div>
    <div style="font-family:monospace;font-size:3rem;font-weight:900;color:#fff" id="countdownFs"><?= $secsLeft ?></div>
    <div style="font-size:.8rem;color:rgba(255,255,255,.6);margin-top:4px">seconds</div>
  </div>
  
  <p style="font-size:.85rem">You must be logged in to the LYDO Youth Portal</p>
</div>

<!-- Photo Modal -->
<div class="photo-modal" id="photoModal" onclick="if(event.target===this) closePhotoModal()">
  <div class="photo-modal-content">
    <div class="photo-modal-header">
      <h3 style="color:#0d3b6e;margin:0"><i class="fas fa-camera"></i> Event Photos</h3>
      <button class="photo-modal-close" onclick="closePhotoModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="photo-modal-body">
      <!-- User Info -->
      <div class="user-info">
        <div class="user-info-row">
          <div class="user-info-label"><i class="fas fa-user"></i> Name</div>
          <div class="user-info-value" id="modalName">—</div>
        </div>
        <div class="user-info-row">
          <div class="user-info-label"><i class="fas fa-envelope"></i> Email</div>
          <div class="user-info-value" id="modalEmail">—</div>
        </div>
        <div class="user-info-row">
          <div class="user-info-label"><i class="fas fa-map-marker-alt"></i> Barangay</div>
          <div class="user-info-value" id="modalBarangay">—</div>
        </div>
        <div class="user-info-row">
          <div class="user-info-label"><i class="fas fa-certificate"></i> Certificate</div>
          <div class="user-info-value" id="modalCert">—</div>
        </div>
      </div>

      <!-- Photos Grid -->
      <div class="photo-grid">
        <!-- Check-in Photo -->
        <div class="photo-card">
          <div class="photo-card-header">
            <i class="fas fa-sign-in-alt"></i> Check-in Photo
            <span style="margin-left:auto;font-size:.75rem;opacity:.8" id="checkinTime">—</span>
          </div>
          <div class="photo-card-body" id="checkinPhotoContainer">
            <div class="photo-placeholder">
              <i class="fas fa-image" style="font-size:2.5rem;margin-bottom:10px;display:block"></i>
              <div style="font-size:.85rem">No photo uploaded</div>
            </div>
          </div>
        </div>

        <!-- Check-out Photo -->
        <div class="photo-card">
          <div class="photo-card-header" style="background:linear-gradient(135deg,#1b5e20,#2e7d32)">
            <i class="fas fa-sign-out-alt"></i> Check-out Photo
            <span style="margin-left:auto;font-size:.75rem;opacity:.8" id="checkoutTime">—</span>
          </div>
          <div class="photo-card-body" id="checkoutPhotoContainer">
            <div class="photo-placeholder">
              <i class="fas fa-image" style="font-size:2.5rem;margin-bottom:10px;display:block"></i>
              <div style="font-size:.85rem">No photo uploaded</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const eventId = <?= $eventId ?>;
const baseUrl = <?= json_encode($checkinUrl) ?>;
let qrInstance = null;
let qrInstanceFs = null;
let countdown = <?= $secsLeft ?>;

// Initialize QR codes
function initQRCodes(url) {
  // Clear existing QR codes
  document.getElementById('qrcode').innerHTML = '';
  document.getElementById('qrcode-fs').innerHTML = '';
  
  // Generate new QR codes
  qrInstance = new QRCode(document.getElementById('qrcode'), {
    text: url, width: 200, height: 200,
    colorDark: '#0d3b6e', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });
  
  qrInstanceFs = new QRCode(document.getElementById('qrcode-fs'), {
    text: url, width: 320, height: 320,
    colorDark: '#ffffff', colorLight: '#0d3b6e',
    correctLevel: QRCode.CorrectLevel.H
  });
}

// Fetch new rotating QR URL via AJAX
function refreshQRCode() {
  fetch('event_qr_ajax.php?id=' + eventId)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.qr_url) {
        // Update QR codes without page reload
        initQRCodes(data.qr_url);
        countdown = data.seconds_left || 30;
      }
    })
    .catch(error => {
      console.error('QR refresh error:', error);
      // Retry after 5 seconds on error
      setTimeout(refreshQRCode, 5000);
    });
}

// Update countdown timer
function updateCountdown() {
  document.getElementById('countdown').textContent = countdown;
  document.getElementById('countdownFs').textContent = countdown;
  
  countdown--;
  
  if (countdown < 0) {
    // Time to refresh QR code
    refreshQRCode();
  }
}

// Initialize with first QR code
initQRCodes(<?= json_encode($rotatingQRUrl) ?>);

// Update countdown every second
setInterval(updateCountdown, 1000);

function openFullscreen() {
  document.getElementById('fsOverlay').classList.add('open');
}
function closeFullscreen() {
  document.getElementById('fsOverlay').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFullscreen(); });

// Photo modal functions
function showPhotos(data) {
  // Populate user info
  document.getElementById('modalName').textContent = data.name || '—';
  document.getElementById('modalEmail').textContent = data.email || '—';
  document.getElementById('modalBarangay').textContent = data.barangay || '—';
  document.getElementById('modalCert').innerHTML = data.cert_number 
    ? '<code style="font-size:.85rem;background:#e3f2fd;padding:2px 8px;border-radius:4px">' + data.cert_number + '</code>'
    : '—';
  
  // Update times
  document.getElementById('checkinTime').textContent = data.checkin_time || '—';
  document.getElementById('checkoutTime').textContent = data.checkout_time || 'Not checked out';
  
  // Check-in photo
  const checkinContainer = document.getElementById('checkinPhotoContainer');
  if (data.checkin_photo) {
    checkinContainer.innerHTML = '<img src="/LYDO/lydo-system/shared/uploads/event_photos/' + data.checkin_photo + '" alt="Check-in photo" style="max-width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)" onerror="this.parentElement.innerHTML=\'<div class=\\\'photo-placeholder\\\'><i class=\\\'fas fa-exclamation-triangle\\\' style=\\\'font-size:2.5rem;margin-bottom:10px;display:block;color:#ef9a9a\\\'></i><div style=\\\'font-size:.85rem;color:#c62828\\\'>Photo file not found</div></div>\'">';
  } else {
    checkinContainer.innerHTML = '<div class="photo-placeholder"><i class="fas fa-image" style="font-size:2.5rem;margin-bottom:10px;display:block"></i><div style="font-size:.85rem">No photo uploaded</div></div>';
  }
  
  // Check-out photo
  const checkoutContainer = document.getElementById('checkoutPhotoContainer');
  if (data.checkout_photo) {
    checkoutContainer.innerHTML = '<img src="/LYDO/lydo-system/shared/uploads/event_photos/' + data.checkout_photo + '" alt="Check-out photo" style="max-width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)" onerror="this.parentElement.innerHTML=\'<div class=\\\'photo-placeholder\\\'><i class=\\\'fas fa-exclamation-triangle\\\' style=\\\'font-size:2.5rem;margin-bottom:10px;display:block;color:#ef9a9a\\\'></i><div style=\\\'font-size:.85rem;color:#c62828\\\'>Photo file not found</div></div>\'">';
  } else {
    checkoutContainer.innerHTML = '<div class="photo-placeholder"><i class="fas fa-image" style="font-size:2.5rem;margin-bottom:10px;display:block"></i><div style="font-size:.85rem">No photo uploaded</div></div>';
  }
  
  // Show modal
  document.getElementById('photoModal').classList.add('open');
}

function closePhotoModal() {
  document.getElementById('photoModal').classList.remove('open');
}

// Close modal on ESC key
document.addEventListener('keydown', e => { 
  if (e.key === 'Escape') {
    closeFullscreen();
    closePhotoModal();
  }
});
</script>
</body>
</html>
