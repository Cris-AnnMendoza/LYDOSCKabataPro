<?php
/**
 * Public Event Check-in Page
 * Accessed via QR code: /lydo-system/event_checkin.php?token=XXXX
 * Youth must be logged in to check in; if not, redirected to login first.
 */
require_once __DIR__ . '/shared/config.php';

$pdo = db();

// ── Auto-migrate: ensure tables exist (no FK constraints) ─
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

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    message    TEXT         NOT NULL,
    type       VARCHAR(50)  DEFAULT 'info',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

foreach ([
    "ALTER TABLE events ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN checkin_open TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE events ADD COLUMN merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2",
] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* already exists */ }
}

$token = trim($_GET['token'] ?? '');
if (!$token) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828"><h2>Invalid QR Code</h2><p>No event token provided.</p></div>');
}

// Load event by token
$evStmt = $pdo->prepare('SELECT * FROM events WHERE qr_token = ? LIMIT 1');
$evStmt->execute([$token]);
$event = $evStmt->fetch();

if (!$event) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828"><h2>Event Not Found</h2><p>This QR code is not linked to any event.</p></div>');
}

if (!$event['checkin_open']) {
    $closed = true;
}

// If not logged in, redirect to login then back here
if (empty($_SESSION['user_id'])) {
    $_SESSION['checkin_redirect'] = '/LYDO/lydo-system/event_checkin.php?token=' . urlencode($token);
    header('Location: /LYDO/lydo-system/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Load user
$uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id = ? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

$message     = '';
$messageType = '';
$alreadyIn   = false;
$certNumber  = '';
$justCheckedIn = false;

// Check if already checked in
$ciStmt = $pdo->prepare('SELECT * FROM event_checkins WHERE event_id = ? AND user_id = ? LIMIT 1');
$ciStmt->execute([$event['id'], $userId]);
$existingCheckin = $ciStmt->fetch();

// Check if certificate already generated
$certStmt = $pdo->prepare('SELECT * FROM event_certificates WHERE event_id = ? AND user_id = ? LIMIT 1');
$certStmt->execute([$event['id'], $userId]);
$existingCert = $certStmt->fetch();

if ($existingCheckin) {
    $alreadyIn  = true;
    $certNumber = $existingCert['cert_number'] ?? '';
    $message    = 'You have already checked in to this event.';
    $messageType = 'info';
}

// Handle check-in POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_checkin']) && !$alreadyIn && empty($closed)) {
    // Record check-in
    $pdo->prepare('INSERT INTO event_checkins (event_id, user_id, ip_address) VALUES (?, ?, ?)')
        ->execute([$event['id'], $userId, $_SERVER['REMOTE_ADDR'] ?? null]);

    // Generate certificate number
    $certNo = 'LYDO-EVT-' . date('Y') . '-' . str_pad($event['id'], 4, '0', STR_PAD_LEFT)
            . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

    // Insert certificate record
    $pdo->prepare(
        'INSERT INTO event_certificates (event_id, user_id, cert_number, merit_points)
         VALUES (?, ?, ?, ?)'
    )->execute([$event['id'], $userId, $certNo, $event['merit_points'] ?: 2]);

    $certId = (int)$pdo->lastInsertId();

    // Send notification
    $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)'
    )->execute([
        $userId,
        'Check-in Confirmed: ' . $event['title'],
        'You have successfully checked in to "' . $event['title'] . '". Your certificate has been generated. Please download and upload it to earn merit points.',
        'success'
    ]);

    $alreadyIn     = true;
    $justCheckedIn = true;
    $certNumber    = $certNo;
    $message       = 'Check-in successful! Your certificate is ready to download.';
    $messageType   = 'success';

    // Reload cert
    $certStmt->execute([$event['id'], $userId]);
    $existingCert = $certStmt->fetch();
}

$eventDate = date('F j, Y', strtotime($event['event_date']));
$eventTime = $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Event Check-in – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:linear-gradient(160deg,#0d3b6e 0%,#1565c0 55%,#1b5e20 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:480px;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0d3b6e,#1565c0);padding:28px 28px 24px;text-align:center;color:#fff}
.logo{width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 14px}
.card-top h1{font-size:1.1rem;font-weight:800;letter-spacing:.04em;margin-bottom:4px}
.card-top p{font-size:.8rem;opacity:.7}
.card-body{padding:28px}
.event-title{font-size:1.3rem;font-weight:800;color:#0d3b6e;margin-bottom:12px;line-height:1.3}
.event-meta{display:flex;flex-direction:column;gap:7px;margin-bottom:20px}
.meta-row{display:flex;align-items:center;gap:9px;font-size:.88rem;color:#475569}
.meta-row i{width:16px;color:#1565c0;font-size:.85rem}
.divider{height:1px;background:#e2e8f0;margin:20px 0}
.user-card{display:flex;align-items:center;gap:12px;padding:14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:20px}
.avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#43a047);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;font-weight:700;flex-shrink:0}
.user-name{font-size:.95rem;font-weight:700;color:#1e293b}
.user-email{font-size:.78rem;color:#94a3b8;margin-top:2px}
.btn-checkin{width:100%;padding:15px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;box-shadow:0 4px 16px rgba(21,101,192,.35)}
.btn-checkin:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(21,101,192,.45)}
.btn-cert{width:100%;padding:13px;background:linear-gradient(135deg,#2e7d32,#43a047);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;box-shadow:0 4px 16px rgba(46,125,50,.3);text-decoration:none;margin-top:10px}
.btn-cert:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(46,125,50,.4)}
.btn-upload{width:100%;padding:13px;background:linear-gradient(135deg,#f57f17,#fb8c00);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;box-shadow:0 4px 16px rgba(245,127,23,.3);text-decoration:none;margin-top:10px}
.btn-upload:hover{transform:translateY(-2px)}
.alert{padding:14px 16px;border-radius:10px;font-size:.88rem;font-weight:500;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px}
.alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2)}
.alert.info{background:#e3f2fd;color:#1565c0;border:1px solid rgba(21,101,192,.2)}
.alert.error{background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2)}
.alert.closed{background:#fff8e1;color:#f57f17;border:1px solid rgba(245,127,23,.2)}
.cert-box{background:#f8fafc;border:1.5px dashed #90caf9;border-radius:12px;padding:16px;text-align:center;margin-top:16px}
.cert-no{font-size:.78rem;color:#94a3b8;margin-bottom:4px}
.cert-val{font-size:.95rem;font-weight:700;color:#0d3b6e;font-family:monospace}
.steps{display:flex;flex-direction:column;gap:10px;margin-top:16px}
.step{display:flex;align-items:flex-start;gap:10px;font-size:.85rem;color:#475569}
.step-num{width:24px;height:24px;border-radius:50%;background:#e3f2fd;color:#1565c0;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;margin-top:1px}
.step-num.done{background:#e8f5e9;color:#2e7d32}
.footer{text-align:center;padding:16px;border-top:1px solid #e2e8f0;font-size:.75rem;color:#94a3b8}
</style>
</head>
<body>

<div class="card">
  <div class="card-top">
    <div class="logo"><img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:70%;height:70%;object-fit:contain"/></div>
    <h1>LYDO Event Check-in</h1>
    <p>Local Youth Development Office · Sta. Cruz, Laguna</p>
  </div>

  <div class="card-body">

    <?php if (!empty($closed)): ?>
      <div class="alert closed"><i class="fas fa-lock"></i><div><strong>Check-in Closed</strong><br>The organizer has closed check-in for this event.</div></div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert <?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'info' ? 'info-circle' : 'exclamation-circle') ?>"></i>
        <div><?= htmlspecialchars($message) ?></div>
      </div>
    <?php endif; ?>

    <!-- Event Info -->
    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
    <div class="event-meta">
      <div class="meta-row"><i class="fas fa-calendar"></i><?= $eventDate ?><?= $eventTime ? ' · ' . $eventTime : '' ?></div>
      <?php if ($event['location']): ?>
      <div class="meta-row"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($event['location']) ?></div>
      <?php endif; ?>
      <div class="meta-row"><i class="fas fa-star"></i><?= (int)($event['merit_points'] ?: 2) ?> merit points upon certificate upload</div>
    </div>

    <div class="divider"></div>

    <!-- User Info -->
    <?php
    $initials = strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1));
    ?>
    <div class="user-card">
      <div class="avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
      </div>
      <i class="fas fa-check-circle" style="color:#2e7d32;margin-left:auto;font-size:1.1rem"></i>
    </div>

    <?php if (!$alreadyIn && empty($closed)): ?>
      <!-- Check-in button -->
      <form method="POST">
        <input type="hidden" name="do_checkin" value="1"/>
        <button type="submit" class="btn-checkin">
          <i class="fas fa-qrcode"></i> Confirm Check-in
        </button>
      </form>

      <div class="steps">
        <div class="step"><div class="step-num">1</div><div>Tap <strong>Confirm Check-in</strong> to register your attendance</div></div>
        <div class="step"><div class="step-num">2</div><div>Download your auto-generated certificate</div></div>
        <div class="step"><div class="step-num">3</div><div>Upload the certificate in your Youth Portal to earn merit points</div></div>
      </div>

    <?php elseif ($alreadyIn): ?>
      <!-- Already checked in — show cert options -->
      <div class="cert-box">
        <div class="cert-no">Your Certificate Number</div>
        <div class="cert-val"><?= htmlspecialchars($certNumber) ?></div>
      </div>

      <a href="event_cert_view.php?event=<?= $event['id'] ?>&user=<?= $userId ?>&token=<?= urlencode($token) ?>"
         class="btn-cert" target="_blank">
        <i class="fas fa-certificate"></i> View & Download Certificate
      </a>

      <?php if ($existingCert && !$existingCert['merit_awarded']): ?>
      <a href="shared/youth/events.php#upload-<?= $event['id'] ?>" class="btn-upload">
        <i class="fas fa-upload"></i> Upload Certificate for Merit Points
      </a>
      <?php elseif ($existingCert && $existingCert['merit_awarded']): ?>
      <div class="alert success" style="margin-top:12px">
        <i class="fas fa-star"></i>
        <div><strong>Merit points awarded!</strong> +<?= $existingCert['merit_points'] ?> points added to your account.</div>
      </div>
      <?php endif; ?>

      <div class="steps" style="margin-top:16px">
        <div class="step"><div class="step-num done"><i class="fas fa-check" style="font-size:.6rem"></i></div><div>Checked in <?= $justCheckedIn ? 'just now' : date('M j, Y g:i A', strtotime($existingCheckin['checked_in_at'] ?? 'now')) ?></div></div>
        <div class="step"><div class="step-num <?= $existingCert ? 'done' : '' ?>">2</div><div>Download your certificate</div></div>
        <div class="step"><div class="step-num <?= ($existingCert && $existingCert['merit_awarded']) ? 'done' : '' ?>">3</div><div>Upload certificate to earn <strong><?= (int)($event['merit_points'] ?: 2) ?> merit points</strong></div></div>
      </div>
    <?php endif; ?>

  </div>

  <div class="footer">© 2026 Municipal Government of Sta. Cruz, Laguna · LYDO</div>
</div>

</body>
</html>
