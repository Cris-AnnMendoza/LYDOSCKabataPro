<?php
/**
 * Event Attendee Scanner
 * Admin/org head opens this page and scans each attendee's personal QR code.
 * Uses the device camera (jsQR library) — works on phone and laptop.
 */
require_once 'config.php';
requireLogin();

$pdo     = db();
$admin   = currentAdmin();
$eventId = (int)($_GET['id'] ?? 0);

// Ensure tables exist
// Only for MySQL - PostgreSQL/Supabase tables already exist
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
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
    "ALTER TABLE events ADD COLUMN event_type VARCHAR(50) NOT NULL DEFAULT 'official_event'",
    "ALTER TABLE events ADD COLUMN requires_representative TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE youth_users ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL",
] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) {}
}

// Generate missing user QR tokens
$pdo->exec("UPDATE youth_users SET qr_token = MD5(CONCAT(id, email, 'LYDO2026')) WHERE qr_token IS NULL OR qr_token = ''");
} // End MySQL-only migration

if (!$eventId) { header('Location: events.php'); exit; }

$evStmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();
if (!$event) { header('Location: events.php'); exit; }

// ── AJAX: process a scanned QR token ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_checkin'])) {
    header('Content-Type: application/json');

    $scannedToken = trim($_POST['qr_token'] ?? '');
    if (!$scannedToken) {
        echo json_encode(['success' => false, 'message' => 'Empty QR code.']);
        exit;
    }

    // Find user by QR token
    $uStmt = $pdo->prepare('SELECT id, first_name, last_name, barangay FROM youth_users WHERE qr_token = ? LIMIT 1');
    $uStmt->execute([$scannedToken]);
    $user = $uStmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'QR code not recognized. Not a registered LYDO member.']);
        exit;
    }

    $userId = (int)$user['id'];

    // Check if already checked in
    $ciCheck = $pdo->prepare('SELECT id FROM event_checkins WHERE event_id = ? AND user_id = ? LIMIT 1');
    $ciCheck->execute([$eventId, $userId]);
    if ($ciCheck->fetch()) {
        echo json_encode([
            'success'  => false,
            'already'  => true,
            'message'  => $user['first_name'] . ' ' . $user['last_name'] . ' is already checked in.',
            'name'     => $user['first_name'] . ' ' . $user['last_name'],
            'barangay' => $user['barangay'] ?? '',
        ]);
        exit;
    }

    // Record check-in
    $pdo->prepare('INSERT INTO event_checkins (event_id, user_id, ip_address) VALUES (?, ?, ?)')
        ->execute([$eventId, $userId, $_SERVER['REMOTE_ADDR'] ?? null]);

    // Generate certificate
    $certNo = 'LYDO-EVT-' . date('Y') . '-' . str_pad($eventId, 4, '0', STR_PAD_LEFT)
            . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

    try {
        $pdo->prepare('INSERT INTO event_certificates (event_id, user_id, cert_number, merit_points) VALUES (?, ?, ?, ?)')
            ->execute([$eventId, $userId, $certNo, $event['merit_points'] ?: 2]);
    } catch (PDOException $e) {} // duplicate — already exists

    // Notification to user
    try {
        $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)')
            ->execute([
                $userId,
                'Check-in Recorded: ' . $event['title'],
                'You have been checked in to "' . $event['title'] . '" by the organizer. Download your certificate from the Events page.',
                'success'
            ]);
    } catch (PDOException $e) {}

    echo json_encode([
        'success'   => true,
        'message'   => 'Checked in successfully!',
        'name'      => $user['first_name'] . ' ' . $user['last_name'],
        'barangay'  => $user['barangay'] ?? '',
        'cert_no'   => $certNo,
    ]);
    exit;
}

// Load check-in list
$checkinsStmt = $pdo->prepare('
    SELECT c.checked_in_at, u.first_name, u.last_name, u.barangay,
           cert.cert_number, cert.merit_awarded
    FROM event_checkins c
    JOIN youth_users u ON u.id = c.user_id
    LEFT JOIN event_certificates cert ON cert.event_id = c.event_id AND cert.user_id = c.user_id
    WHERE c.event_id = ?
    ORDER BY c.checked_in_at DESC
');
$checkinsStmt->execute([$eventId]);
$checkins = $checkinsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"/>
<title>Scan Attendees – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.scanner-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
.cam-box{background:#000;border-radius:16px;overflow:hidden;position:relative;aspect-ratio:1}
#video{width:100%;height:100%;object-fit:cover;display:block}
.scan-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.scan-frame{width:65%;aspect-ratio:1;border:3px solid #fff;border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,.45);position:relative}
.scan-corner{position:absolute;width:22px;height:22px}
.sc-tl{top:-3px;left:-3px;border-top:4px solid #43a047;border-left:4px solid #43a047;border-radius:4px 0 0 0}
.sc-tr{top:-3px;right:-3px;border-top:4px solid #43a047;border-right:4px solid #43a047;border-radius:0 4px 0 0}
.sc-bl{bottom:-3px;left:-3px;border-bottom:4px solid #43a047;border-left:4px solid #43a047;border-radius:0 0 0 4px}
.sc-br{bottom:-3px;right:-3px;border-bottom:4px solid #43a047;border-right:4px solid #43a047;border-radius:0 0 4px 0}
.scan-line{position:absolute;left:6px;right:6px;height:2px;background:linear-gradient(90deg,transparent,#43a047,transparent);animation:scanline 2s linear infinite}
@keyframes scanline{0%{top:6px}100%{top:calc(100% - 8px)}}
.scan-hint{position:absolute;bottom:14px;left:0;right:0;text-align:center;color:#fff;font-size:.82rem;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.8)}
.cam-status{position:absolute;top:12px;left:12px;background:rgba(0,0,0,.6);color:#fff;padding:5px 12px;border-radius:50px;font-size:.75rem;font-weight:600;display:flex;align-items:center;gap:6px}
.dot-live{width:8px;height:8px;border-radius:50%;background:#43a047;animation:blink 1s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* Result toast */
.toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;max-width:400px;border-radius:14px;padding:16px 18px;box-shadow:0 8px 32px rgba(0,0,0,.2);display:none;animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
.toast.show{display:flex;align-items:flex-start;gap:12px}
.toast.success{background:#e8f5e9;border:1.5px solid #a5d6a7;color:#1b5e20}
.toast.error{background:#ffebee;border:1.5px solid #ef9a9a;color:#b71c1c}
.toast.already{background:#fff8e1;border:1.5px solid #ffe082;color:#e65100}
.toast-icon{font-size:1.4rem;flex-shrink:0;margin-top:2px}
.toast-name{font-size:1rem;font-weight:800;margin-bottom:2px}
.toast-msg{font-size:.82rem;opacity:.85}

/* Checkin list */
.ci-row{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:.83rem;align-items:center}
.ci-row:hover{background:#f8fafc}
.ci-header{background:#f1f5f9;font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#475569;border-radius:8px 8px 0 0}
.badge-sm{padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge-green{background:#e8f5e9;color:#2e7d32}
.badge-blue{background:#e3f2fd;color:#1565c0}
.count-badge{background:#1565c0;color:#fff;border-radius:50px;padding:2px 10px;font-size:.8rem;font-weight:700;margin-left:8px}

/* Camera start button */
.btn-cam{width:100%;padding:16px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;box-shadow:0 4px 16px rgba(21,101,192,.3)}
.btn-cam:hover{transform:translateY(-2px)}
.btn-stop{width:100%;padding:12px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;margin-top:10px}
.btn-stop:hover{background:#c62828;color:#fff}

/* Stats row */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.stat-mini{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center}
.stat-mini .val{font-size:1.8rem;font-weight:800;color:#0d3b6e;line-height:1}
.stat-mini .lbl{font-size:.75rem;color:#94a3b8;margin-top:4px}

@media(max-width:700px){
  .scanner-wrap{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<div class="page-header">
  <div>
    <h2><i class="fas fa-camera" style="color:#1565c0;margin-right:8px"></i>Scan Attendees</h2>
    <p><?= htmlspecialchars($event['title']) ?> · <?= date('F j, Y', strtotime($event['event_date'])) ?></p>
  </div>
  <a href="events.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Stats -->
<div class="stat-row">
  <div class="stat-mini">
    <div class="val" id="statCount"><?= count($checkins) ?></div>
    <div class="lbl"><i class="fas fa-users" style="color:#1565c0"></i> Checked In</div>
  </div>
  <div class="stat-mini">
    <div class="val"><?= (int)($event['merit_points'] ?: 2) ?></div>
    <div class="lbl"><i class="fas fa-star" style="color:#2e7d32"></i> Merit Points</div>
  </div>
  <div class="stat-mini">
    <div class="val" id="statScanned">0</div>
    <div class="lbl"><i class="fas fa-qrcode" style="color:#f57f17"></i> Scanned Today</div>
  </div>
</div>

<div class="scanner-wrap">

  <!-- Camera Scanner -->
  <div>
    <div class="card" style="margin-bottom:14px">
      <div class="card-header"><h3><i class="fas fa-camera"></i> Camera Scanner</h3></div>
      <div style="padding:16px">

        <!-- Camera view (hidden until started) -->
        <div class="cam-box" id="camBox" style="display:none">
          <video id="video" autoplay playsinline muted></video>
          <canvas id="canvas" style="display:none"></canvas>
          <div class="scan-overlay">
            <div class="scan-frame">
              <div class="scan-corner sc-tl"></div>
              <div class="scan-corner sc-tr"></div>
              <div class="scan-corner sc-bl"></div>
              <div class="scan-corner sc-br"></div>
              <div class="scan-line"></div>
            </div>
          </div>
          <div class="cam-status"><span class="dot-live"></span> Scanning...</div>
          <div class="scan-hint">Point camera at attendee's QR code</div>
        </div>

        <!-- Start button -->
        <div id="startWrap">
          <button class="btn-cam" onclick="startCamera()">
            <i class="fas fa-camera"></i> Start Camera Scanner
          </button>
          <p style="font-size:.8rem;color:#94a3b8;text-align:center;margin-top:10px">
            <i class="fas fa-info-circle"></i> Allow camera access when prompted
          </p>
        </div>

        <button class="btn-stop" id="stopBtn" style="display:none" onclick="stopCamera()">
          <i class="fas fa-stop-circle"></i> Stop Camera
        </button>

        <!-- Last scan result -->
        <div id="lastResult" style="display:none;margin-top:14px;padding:14px;border-radius:10px;font-size:.88rem"></div>
      </div>
    </div>

    <!-- Manual entry fallback -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-keyboard"></i> Manual Entry</h3></div>
      <div style="padding:16px">
        <p style="font-size:.82rem;color:#475569;margin-bottom:10px">If camera doesn't work, type the attendee's QR token manually.</p>
        <div style="display:flex;gap:8px">
          <input type="text" id="manualToken" placeholder="Paste QR token here..."
            style="flex:1;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.9rem;outline:none"
            onkeydown="if(event.key==='Enter') manualCheckin()"/>
          <button onclick="manualCheckin()"
            style="padding:10px 18px;background:#1565c0;color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer">
            <i class="fas fa-check"></i> Check In
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Check-in List -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between">
      <h3><i class="fas fa-list-check"></i> Checked-in Attendees <span class="count-badge" id="listCount"><?= count($checkins) ?></span></h3>
    </div>
    <div id="checkinList">
      <?php if (empty($checkins)): ?>
      <div id="emptyMsg" style="padding:32px;text-align:center;color:#94a3b8;font-size:.88rem">
        <i class="fas fa-camera" style="font-size:2rem;display:block;margin-bottom:10px;color:#e2e8f0"></i>
        No check-ins yet. Start scanning attendees.
      </div>
      <?php else: ?>
      <div class="ci-row ci-header"><div>Attendee</div><div>Time</div><div>Certificate</div></div>
      <?php foreach ($checkins as $ci): ?>
      <div class="ci-row">
        <div>
          <strong><?= htmlspecialchars($ci['first_name'] . ' ' . $ci['last_name']) ?></strong>
          <?php if ($ci['barangay']): ?>
            <br><span style="font-size:.72rem;color:#94a3b8"><?= htmlspecialchars($ci['barangay']) ?></span>
          <?php endif; ?>
        </div>
        <div style="color:#475569"><?= date('g:i A', strtotime($ci['checked_in_at'])) ?></div>
        <div>
          <?php if ($ci['cert_number']): ?>
            <span class="badge-sm badge-green"><i class="fas fa-check"></i> Generated</span>
          <?php else: ?>
            <span class="badge-sm badge-blue">Pending</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

</main>
</div>

<!-- Toast notification -->
<div class="toast" id="toast">
  <div class="toast-icon" id="toastIcon"></div>
  <div>
    <div class="toast-name" id="toastName"></div>
    <div class="toast-msg" id="toastMsg"></div>
  </div>
</div>

<!-- jsQR library for QR decoding from camera -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const eventId   = <?= $eventId ?>;
const apiUrl    = 'event_scan.php?id=' + eventId;
let stream      = null;
let scanning    = false;
let scanCount   = 0;
let lastToken   = '';
let lastTokenTime = 0;

// ── Start camera ──────────────────────────────────────────
async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    const video = document.getElementById('video');
    video.srcObject = stream;
    document.getElementById('camBox').style.display = 'block';
    document.getElementById('startWrap').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'flex';
    scanning = true;
    requestAnimationFrame(scanFrame);
  } catch (err) {
    alert('Camera access denied or not available.\n\nPlease allow camera permission or use Manual Entry below.\n\nError: ' + err.message);
  }
}

// ── Stop camera ───────────────────────────────────────────
function stopCamera() {
  scanning = false;
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  document.getElementById('camBox').style.display = 'none';
  document.getElementById('startWrap').style.display = 'block';
  document.getElementById('stopBtn').style.display = 'none';
}

// ── Scan frame loop ───────────────────────────────────────
function scanFrame() {
  if (!scanning) return;
  const video  = document.getElementById('video');
  const canvas = document.getElementById('canvas');

  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });

    if (code && code.data) {
      const now = Date.now();
      // Debounce: don't re-scan same token within 3 seconds
      if (code.data !== lastToken || now - lastTokenTime > 3000) {
        lastToken     = code.data;
        lastTokenTime = now;
        processCheckin(code.data);
      }
    }
  }
  requestAnimationFrame(scanFrame);
}

// ── Manual entry ──────────────────────────────────────────
function manualCheckin() {
  const val = document.getElementById('manualToken').value.trim();
  if (!val) return;
  processCheckin(val);
  document.getElementById('manualToken').value = '';
}

// ── Send check-in to server ───────────────────────────────
function processCheckin(token) {
  const fd = new FormData();
  fd.append('ajax_checkin', '1');
  fd.append('qr_token', token);

  fetch(apiUrl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        scanCount++;
        document.getElementById('statScanned').textContent = scanCount;
        showToast('success', '✓ Checked In', data.name + (data.barangay ? ' · ' + data.barangay : ''));
        addToList(data.name, data.barangay, data.cert_no);
        // Update count
        const cur = parseInt(document.getElementById('statCount').textContent) + 1;
        document.getElementById('statCount').textContent = cur;
        document.getElementById('listCount').textContent = cur;
      } else if (data.already) {
        showToast('already', '⚠ Already In', data.name + ' is already checked in.');
      } else {
        showToast('error', '✗ Not Found', data.message);
      }
    })
    .catch(() => showToast('error', '✗ Error', 'Connection failed. Try again.'));
}

// ── Add row to list ───────────────────────────────────────
function addToList(name, barangay, certNo) {
  const empty = document.getElementById('emptyMsg');
  if (empty) empty.remove();

  const list = document.getElementById('checkinList');
  // Add header if first entry
  if (!list.querySelector('.ci-header')) {
    list.insertAdjacentHTML('afterbegin',
      '<div class="ci-row ci-header"><div>Attendee</div><div>Time</div><div>Certificate</div></div>');
  }

  const now = new Date();
  const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  const row = `<div class="ci-row" style="animation:slideIn .3s ease">
    <div><strong>${escHtml(name)}</strong>${barangay ? '<br><span style="font-size:.72rem;color:#94a3b8">' + escHtml(barangay) + '</span>' : ''}</div>
    <div style="color:#475569">${time}</div>
    <div><span class="badge-sm badge-green"><i class="fas fa-check"></i> Generated</span></div>
  </div>`;
  // Insert after header
  const header = list.querySelector('.ci-header');
  header.insertAdjacentHTML('afterend', row);
}

// ── Toast ─────────────────────────────────────────────────
let toastTimer = null;
function showToast(type, name, msg) {
  const toast = document.getElementById('toast');
  const icons = { success: '✅', error: '❌', already: '⚠️' };
  document.getElementById('toastIcon').textContent = icons[type] || 'ℹ️';
  document.getElementById('toastName').textContent = name;
  document.getElementById('toastMsg').textContent  = msg;
  toast.className = 'toast show ' + type;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);

  // Also show in last result box
  const box = document.getElementById('lastResult');
  const colors = { success: '#e8f5e9', error: '#ffebee', already: '#fff8e1' };
  const textColors = { success: '#2e7d32', error: '#c62828', already: '#e65100' };
  box.style.display = 'block';
  box.style.background = colors[type];
  box.style.color = textColors[type];
  box.innerHTML = '<strong>' + escHtml(name) + '</strong><br>' + escHtml(msg);
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
