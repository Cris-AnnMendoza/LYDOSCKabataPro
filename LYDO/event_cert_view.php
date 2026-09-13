
<?php
/**
 * Event Participation Certificate
 * Includes: Time In, Time Out, Verification QR Code
 */
require_once __DIR__ . '/shared/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /LYDO/lydo-system/login.php');
    exit;
}

$pdo    = db();
$userId = (int)$_SESSION['user_id'];

$certId  = (int)($_GET['cert_id'] ?? 0);
$eventId = (int)($_GET['event']   ?? 0);

if ($certId) {
    $certStmt = $pdo->prepare('SELECT * FROM event_certificates WHERE id = ? AND user_id = ? LIMIT 1');
    $certStmt->execute([$certId, $userId]);
    $cert = $certStmt->fetch();
} elseif ($eventId) {
    $certStmt = $pdo->prepare('SELECT * FROM event_certificates WHERE event_id = ? AND user_id = ? LIMIT 1');
    $certStmt->execute([$eventId, $userId]);
    $cert = $certStmt->fetch();
} else {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828"><h2>Invalid Request</h2></div>');
}

if (!$cert) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828">
        <h2>Certificate Not Found</h2>
        <p>You have not checked out of this event yet, or the certificate does not exist.</p>
        <a href="/LYDO/lydo-system/shared/youth/events.php" style="color:#1565c0">← Back to Events</a>
    </div>');
}

// Load event
$evStmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$evStmt->execute([$cert['event_id']]);
$event = $evStmt->fetch();

if (!$event) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828"><h2>Event Not Found</h2></div>');
}

// Load user
$uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id = ? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

// Load check-in record (time in / time out)
$ciStmt = $pdo->prepare('SELECT checked_in_at, checked_out_at FROM event_checkins WHERE event_id = ? AND user_id = ? LIMIT 1');
$ciStmt->execute([$cert['event_id'], $userId]);
$checkin = $ciStmt->fetch();

$fullName   = strtoupper(trim($user['first_name'] . ' ' . $user['last_name']));
$issueDate  = date('F j, Y', strtotime($cert['generated_at']));
$eventDate  = date('F j, Y', strtotime($event['event_date']));
$certNo     = $cert['cert_number'];
$timeIn     = $checkin && $checkin['checked_in_at']  ? date('g:i A', strtotime($checkin['checked_in_at']))  : '—';
$timeOut    = $checkin && $checkin['checked_out_at'] ? date('g:i A', strtotime($checkin['checked_out_at'])) : '—';
$timeInFull = $checkin && $checkin['checked_in_at']  ? date('F j, Y g:i A', strtotime($checkin['checked_in_at']))  : '—';
$timeOutFull= $checkin && $checkin['checked_out_at'] ? date('F j, Y g:i A', strtotime($checkin['checked_out_at'])) : '—';

// Generate a verification hash (HMAC so it can't be faked)
$verifySecret = 'LYDO_VERIFY_2026_' . DB_NAME;
$verifyHash   = substr(hash_hmac('sha256', $certNo . $userId . $cert['event_id'], $verifySecret), 0, 16);
$verifyUrl    = 'http://' . $_SERVER['HTTP_HOST'] . '/LYDO/lydo-system/verify_cert.php?cert=' . urlencode($certNo) . '&h=' . $verifyHash;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Certificate – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#e8ecf0;display:flex;flex-direction:column;align-items:center;min-height:100vh;padding:20px}
.print-bar{background:#0d3b6e;color:#fff;padding:12px 24px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:14px;width:100%;max-width:1100px;flex-wrap:wrap}
.print-bar span{flex:1;font-size:.9rem;font-weight:500}
.btn-p{padding:9px 20px;background:#fff;color:#0d3b6e;border:none;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.2s;text-decoration:none}
.btn-p:hover{background:#e3f2fd}
.btn-back{padding:9px 20px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:7px}
.cert-wrap{width:1100px;height:850px;max-width:100%;background:#fff;position:relative;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.2)}
.cert-border{position:absolute;inset:14px;border:3px solid #1565c0;pointer-events:none;z-index:2}
.cert-border::before{content:'';position:absolute;inset:6px;border:1px solid #c8a84b;pointer-events:none}
.cert-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 10% 10%,rgba(21,101,192,.04) 0%,transparent 50%),radial-gradient(ellipse at 90% 90%,rgba(46,125,50,.04) 0%,transparent 50%)}
.cert-content{position:relative;z-index:3;padding:36px 56px;text-align:center;height:100%;display:flex;flex-direction:column;justify-content:space-between}
.cert-header{display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:14px}
.cert-seal{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;box-shadow:0 4px 16px rgba(0,0,0,.2);flex-shrink:0;overflow:hidden}
.cert-org-main{font-size:.95rem;font-weight:800;color:#0d3b6e;letter-spacing:.02em}
.cert-org-sub{font-size:.72rem;color:#475569;font-weight:500;margin-top:2px}
.gold-line{height:2px;background:linear-gradient(90deg,transparent,#c8a84b,#f0d060,#c8a84b,transparent);margin:10px 0;border-radius:2px}
.thin-line{height:1px;background:linear-gradient(90deg,transparent,#e2e8f0,transparent);margin:8px 0}
.cert-type{font-size:.76rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#c8a84b;margin-bottom:4px}
.cert-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:#0d3b6e;line-height:1.1;margin-bottom:3px}
.cert-presented{font-size:.78rem;color:#475569;margin:10px 0 4px;font-style:italic}
.cert-name{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:#1565c0;border-bottom:2px solid #c8a84b;display:inline-block;padding-bottom:2px;margin-bottom:4px;min-width:320px}
.cert-barangay{font-size:.75rem;color:#475569}
.cert-body{font-size:.82rem;color:#475569;line-height:1.6;margin:10px 0;max-width:680px;margin-left:auto;margin-right:auto}
.cert-event{font-weight:700;color:#0d3b6e;font-size:.88rem}

/* Footer */
.cert-footer{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;margin-top:14px;align-items:end}
.cert-sig{text-align:center}
.cert-sig-line{height:1px;background:#1565c0;margin-bottom:4px}
.cert-sig-name{font-size:.72rem;font-weight:700;color:#0d3b6e}
.cert-sig-title{font-size:.62rem;color:#475569;line-height:1.3}
.cert-info-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;text-align:center}
.cert-info-label{font-size:.6rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em}
.cert-info-val{font-size:.75rem;font-weight:700;color:#0d3b6e;margin-top:2px}

/* Verification QR */
.verify-box{display:flex;flex-direction:column;align-items:center;gap:3px}
.verify-box #certQR canvas,
.verify-box #certQR img{border-radius:6px;border:1px solid #e2e8f0}
.verify-label{font-size:.55rem;color:#94a3b8;text-align:center;max-width:80px;line-height:1.3}

.corner{position:absolute;width:36px;height:36px;z-index:4}
.corner-tl{top:22px;left:22px;border-top:3px solid #c8a84b;border-left:3px solid #c8a84b}
.corner-tr{top:22px;right:22px;border-top:3px solid #c8a84b;border-right:3px solid #c8a84b}
.corner-bl{bottom:22px;left:22px;border-bottom:3px solid #c8a84b;border-left:3px solid #c8a84b}
.corner-br{bottom:22px;right:22px;border-bottom:3px solid #c8a84b;border-right:3px solid #c8a84b}
.cert-note{font-size:.65rem;color:#94a3b8;margin-top:8px}

@media(max-width:700px){
  .cert-content{padding:28px 20px}
  .cert-title{font-size:1.6rem}
  .cert-name{font-size:1.4rem;min-width:unset}
  .cert-footer{grid-template-columns:1fr}
  .cert-header{flex-direction:column;gap:10px}
}
@media print{
  html,body{width:279mm;height:216mm;margin:0;padding:0}
  body{background:#fff;padding:0;display:block}
  .print-bar{display:none!important}
  .cert-wrap{box-shadow:none;width:279mm;height:216mm;max-width:none;page-break-after:avoid;page-break-inside:avoid}
  .cert-content{page-break-inside:avoid}
  @page{size:279mm 216mm landscape;margin:0}
}
</style>
</head>
<body>

<div class="print-bar">
  <a href="/LYDO/lydo-system/shared/youth/events.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <span>Certificate — <?= htmlspecialchars($event['title']) ?></span>
  <button class="btn-p" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <button class="btn-p" onclick="window.print()"><i class="fas fa-download"></i> Save as PDF</button>
</div>

<!-- Certificate -->
<div class="cert-wrap" id="certificate">
  <div class="cert-bg"></div>
  <div class="cert-border"></div>
  <div class="corner corner-tl"></div>
  <div class="corner corner-tr"></div>
  <div class="corner corner-bl"></div>
  <div class="corner corner-br"></div>

  <div class="cert-content">

    <!-- Header -->
    <div class="cert-header">
      <div class="cert-seal" style="background:linear-gradient(135deg,#0d3b6e,#1565c0)">
        <img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:80%;height:80%;object-fit:contain"/>
      </div>
      <div>
        <div class="cert-org-main">Local Youth Development Office</div>
        <div class="cert-org-sub">Municipal Government of Sta. Cruz, Laguna</div>
        <div style="font-size:.62rem;color:#94a3b8;margin-top:1px">Sta. Cruz, Laguna 4009 · Philippines</div>
      </div>
      <div class="cert-seal" style="background:linear-gradient(135deg,#2e7d32,#43a047)"><i class="fas fa-award"></i></div>
    </div>

    <div class="gold-line"></div>

    <div class="cert-type">Certificate of Participation</div>
    <div class="cert-title">This is to Certify That</div>

    <div class="thin-line"></div>

    <div class="cert-presented">This certificate is proudly presented to</div>
    <div class="cert-name"><?= htmlspecialchars($fullName) ?></div>
    <?php if ($user['barangay']): ?>
    <div class="cert-barangay">of <?= htmlspecialchars($user['barangay']) ?>, Sta. Cruz, Laguna</div>
    <?php endif; ?>

    <div class="cert-body">
      has successfully attended and participated in the
      <br>
      <span class="cert-event"><?= htmlspecialchars($event['title']) ?></span>
      <br>
      held on <strong><?= $eventDate ?></strong>
      <?php if ($event['location']): ?>
        at <strong><?= htmlspecialchars($event['location']) ?></strong>
      <?php endif; ?>
      <br>
      organized by the Local Youth Development Office of Sta. Cruz, Laguna.
    </div>

    <div class="gold-line"></div>

    <!-- Footer -->
    <div class="cert-footer">
      <div class="cert-sig">
        <div style="height:28px"></div>
        <div class="cert-sig-line"></div>
        <div class="cert-sig-name">LYDO Coordinator</div>
        <div class="cert-sig-title">Youth Coordinator<br>Local Youth Development Office</div>
      </div>

      <!-- Center: cert info + verification QR -->
      <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
        <div class="cert-info-box">
          <div class="cert-info-label">Certificate No.</div>
          <div class="cert-info-val" style="font-size:.72rem;font-family:monospace"><?= htmlspecialchars($certNo) ?></div>
          <div style="margin-top:6px">
            <div class="cert-info-label">Date Issued</div>
            <div class="cert-info-val"><?= $issueDate ?></div>
          </div>
        </div>
        <!-- Verification QR -->
        <div class="verify-box">
          <div id="certQR"></div>
          <div class="verify-label">Scan to verify authenticity</div>
        </div>
      </div>

      <div class="cert-sig">
        <div style="height:28px"></div>
        <div class="cert-sig-line"></div>
        <div class="cert-sig-name">Municipal Mayor</div>
        <div class="cert-sig-title">Municipal Government<br>Sta. Cruz, Laguna</div>
      </div>
    </div>

  </div>
</div>

<script>
// Generate verification QR code
new QRCode(document.getElementById('certQR'), {
  text: <?= json_encode($verifyUrl) ?>,
  width: 70,
  height: 70,
  colorDark: '#0d3b6e',
  colorLight: '#ffffff',
  correctLevel: QRCode.CorrectLevel.H
});
</script>
</body>
</html>
