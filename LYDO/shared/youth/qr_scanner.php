<?php
/**
 * QR Code Scanner for Event Check-in and Check-out
 * Allows youth to scan QR codes displayed by admins
 */
require_once __DIR__.'/../config.php';
if(empty($_SESSION['user_id'])){
    header('Location: ../../login.php');
    exit;
}

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$scanType = $_GET['type'] ?? 'checkin'; // 'checkin' or 'checkout'

// Load user info
$uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id=? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

// Handle QR scan submission
$scanResult = null;
$scanSuccess = false;
$scanMessage = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scanned_token'])){
    $qrData = trim($_POST['scanned_token']);
    
    if(!$qrData){
        $scanMessage = 'No QR code data received.';
    } else {
        // Parse QR data format: {qr_token}-{window}-{CHECKIN|CHECKOUT}
        $parts = explode('-', $qrData);
        if (count($parts) < 3) {
            $scanMessage = 'Invalid QR code format. Please scan a valid event QR code.';
        } else {
            // Extract parts (last part is the action, second-to-last is window, rest is token)
            $qrAction = array_pop($parts); // CHECKIN or CHECKOUT
            $window = array_pop($parts); // timestamp window
            $qrToken = implode('-', $parts); // remaining parts form the token
            
            // Verify correct QR type
            if ($scanType === 'checkin' && $qrAction !== 'CHECKIN') {
                $scanMessage = 'Wrong QR code! This is a check-out QR code (green). Please scan the check-in QR code (blue).';
            } else if ($scanType === 'checkout' && $qrAction !== 'CHECKOUT') {
                $scanMessage = 'Wrong QR code! This is a check-in QR code (blue). Please scan the check-out QR code (green).';
            } else {
                // Find event by QR token
                $evStmt = $pdo->prepare('SELECT * FROM events WHERE qr_token = ? LIMIT 1');
                $evStmt->execute([$qrToken]);
                $event = $evStmt->fetch();
                
                if(!$event){
                    $scanMessage = 'Event not found. QR code may be invalid.';
                } else {
                    // Verify QR code timestamp is within 30-second window
                    $currentWindow = floor(time() / 30);
                    if ($window != $currentWindow && $window != ($currentWindow - 1)) {
                        $scanMessage = 'QR code expired. Please scan the current QR code displayed on screen.';
                    } else {
                        // Check-in logic
                        if ($scanType === 'checkin') {
                            if (!$event['checkin_open']) {
                                $scanMessage = 'Check-in is currently closed for this event.';
                            } else {
                                // Check if already checked in
                                $ciStmt = $pdo->prepare('SELECT id FROM event_checkins WHERE event_id = ? AND user_id = ? LIMIT 1');
                                $ciStmt->execute([$event['id'], $userId]);
                                if($ciStmt->fetch()){
                                    $scanMessage = 'You have already checked in to this event: ' . htmlspecialchars($event['title']);
                                } else {
                                    // Check 15-minute window if event has start time
                                    $canCheckIn = true;
                                    $st = $event['event_start_time'] ?? null;
                                    if($st && strlen($st) > 0){
                                        if(strlen($st) === 5) $st .= ':00';
                                        $startTs = strtotime(date('Y-m-d') . ' ' . $st);
                                        $deadlineTs = $startTs + 900; // +15 minutes
                                        $nowTs = time();
                                        if($nowTs > $deadlineTs){
                                            $canCheckIn = false;
                                            $deadlineStr = date('g:i A', $deadlineTs);
                                            $scanMessage = 'Check-in closed. The 15-minute window ended at ' . $deadlineStr . '. Hindi na pwede mag check-in kapag late.';
                                        }
                                    }
                                    
                                    if($canCheckIn){
                                        // Record check-in
                                        $pdo->prepare('INSERT INTO event_checkins (event_id, user_id, ip_address) VALUES (?, ?, ?)')
                                            ->execute([$event['id'], $userId, $_SERVER['REMOTE_ADDR'] ?? null]);
                                        
                                        // Generate certificate
                                        $certNo = 'LYDO-EVT-' . date('Y') . '-' . str_pad($event['id'], 4, '0', STR_PAD_LEFT)
                                                . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                                        
                                        try{
                                            $pdo->prepare('INSERT INTO event_certificates (event_id, user_id, cert_number, merit_points) VALUES (?, ?, ?, ?)')
                                                ->execute([$event['id'], $userId, $certNo, $event['merit_points'] ?: 2]);
                                        } catch(PDOException $e){}
                                        
                                        // Send notification
                                        try{
                                            $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)')
                                                ->execute([
                                                    $userId,
                                                    'Checked In: ' . $event['title'],
                                                    'You have successfully checked in to "' . $event['title'] . '". Check out after the event to earn merit points.',
                                                    'success'
                                                ]);
                                        } catch(PDOException $e){}
                                        
                                        $scanSuccess = true;
                                        $scanResult = [
                                            'event' => $event['title'],
                                            'cert_no' => $certNo,
                                            'points' => (int)($event['merit_points'] ?: 2),
                                            'event_date' => $event['event_date'],
                                            'location' => $event['location']
                                        ];
                                        $scanMessage = 'Successfully checked in to: ' . htmlspecialchars($event['title']);
                                    }
                                }
                            }
                        } 
                        // Check-out logic
                        else if ($scanType === 'checkout') {
                            // Check if user checked in
                            $ci = $pdo->prepare('SELECT * FROM event_checkins WHERE event_id=? AND user_id=? LIMIT 1');
                            $ci->execute([$event['id'], $userId]);
                            $checkin = $ci->fetch();
                            
                            if (!$checkin) {
                                $scanMessage = 'You have not checked in to this event yet. Please check in first.';
                            } else if ($checkin['checked_out_at']) {
                                $scanMessage = 'Already checked out of "' . $event['title'] . '".';
                            } else {
                                // Update checkout time
                                $pdo->prepare('UPDATE event_checkins SET checked_out_at=NOW() WHERE event_id=? AND user_id=?')
                                    ->execute([$event['id'], $userId]);
                                
                                $pts = (int)($event['merit_points'] ?: 2);
                                
                                // Get or create certificate
                                $cs = $pdo->prepare('SELECT * FROM event_certificates WHERE event_id=? AND user_id=? LIMIT 1');
                                $cs->execute([$event['id'], $userId]);
                                $cert = $cs->fetch();
                                
                                if (!$cert) {
                                    $cn = 'LYDO-EVT-' . date('Y') . '-' . str_pad($event['id'], 4, '0', STR_PAD_LEFT) 
                                         . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                                    try {
                                        $pdo->prepare('INSERT INTO event_certificates(event_id, user_id, cert_number, merit_points, merit_awarded) VALUES(?,?,?,?,1)')
                                            ->execute([$event['id'], $userId, $cn, $pts]);
                                        $certId = (int)$pdo->lastInsertId();
                                    } catch(PDOException $e) {
                                        $cn = '';
                                        $certId = 0;
                                    }
                                } else {
                                    $cn = $cert['cert_number'];
                                    $certId = (int)$cert['id'];
                                    if (!$cert['merit_awarded']) {
                                        $pdo->prepare('UPDATE event_certificates SET merit_awarded=TRUE WHERE id=?')->execute([$certId]);
                                    }
                                }
                                
                                // Award personal merit points
                                $al = $pdo->prepare('SELECT id FROM user_merit_logs WHERE user_id=? AND event_id=? AND category="event_attendance" LIMIT 1');
                                $al->execute([$userId, $event['id']]);
                                if (!$al->fetch()) {
                                    $pdo->prepare('INSERT INTO user_merit_logs(user_id, points, type, reason, category, event_id, cert_id) VALUES(?,?,"merit",?,"event_attendance",?,?)')
                                        ->execute([$userId, $pts, 'Event checkout: ' . $cn, $event['id'], $certId ?? 0]);
                                }
                                
                                // Award org merit points (only ONCE per org per event)
                                $userOrg = $user['organization_name'] ?? '';
                                if ($userOrg) {
                                    $orgRow = $pdo->prepare('SELECT id FROM organizations WHERE name=? LIMIT 1');
                                    $orgRow->execute([$userOrg]);
                                    $orgRow = $orgRow->fetch();
                                    if ($orgRow) {
                                        $orgId = (int)$orgRow['id'];
                                        $orgCheck = $pdo->prepare('SELECT id FROM org_merit_logs WHERE organization_id=? AND event_id=? AND category="event_attendance" LIMIT 1');
                                        $orgCheck->execute([$orgId, $event['id']]);
                                        if (!$orgCheck->fetch()) {
                                            $pdo->prepare('INSERT INTO org_merit_logs(organization_id, points, type, reason, category, event_id, created_at) VALUES(?,?,"merit",?,"event_attendance",?,NOW())')
                                                ->execute([$orgId, $pts, 'Event attendance: ' . $event['title'], $event['id']]);
                                        }
                                    }
                                }
                                
                                // Send notification
                                try {
                                    $pdo->prepare('INSERT INTO notifications(user_id, title, message, type) VALUES(?,?,?,?)')
                                        ->execute([$userId, 'Merit Points Awarded!', '+' . $pts . ' points for "' . $event['title'] . '". Certificate ready.', 'success']);
                                } catch(PDOException $e) {}
                                
                                $scanSuccess = true;
                                $scanResult = [
                                    'event' => $event['title'],
                                    'cert_no' => $cn,
                                    'points' => $pts,
                                    'event_date' => $event['event_date'],
                                    'location' => $event['location']
                                ];
                                $scanMessage = 'Successfully checked out! +' . $pts . ' merit points earned!';
                            }
                        }
                    }
                }
            }
        }
    }
}

$pageTitle = $scanType === 'checkout' ? 'Check-out Scanner' : 'Check-in Scanner';
$pageColor = $scanType === 'checkout' ? '#2e7d32' : '#1565c0';
$pageColorDark = $scanType === 'checkout' ? '#1b5e20' : '#0d3b6e';
$qrColorText = $scanType === 'checkout' ? 'green' : 'blue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $pageTitle ?> – LYDO Event System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.scanner-container{max-width:600px;margin:0 auto;padding:20px}
.scanner-box{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);overflow:hidden;margin-bottom:20px}
.scanner-header{background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;padding:20px;text-align:center}
.scanner-header h1{font-size:1.3rem;font-weight:800;margin-bottom:6px;display:flex;align-items:center;justify-content:center;gap:10px}
.scanner-header p{font-size:.85rem;opacity:.85}
#qr-video-container{position:relative;width:100%;background:#000;min-height:400px;display:flex;align-items:center;justify-content:center}
#qr-reader{width:100%;border:none;background:#000;min-height:400px}
#qr-reader__dashboard_section{display:none !important}
#qr-reader__scan_region{border:4px solid #2196f3 !important;border-radius:16px !important;box-shadow:0 0 0 4000px rgba(0,0,0,.5) !important}
#qr-reader video{border-radius:12px;width:100% !important;object-fit:cover !important}
#qr-reader__camera_selection{display:none !important}
#qr-reader__dashboard_section_swaplink{display:none !important}
#qr-reader__camera_permission_button{display:none !important}
.scanner-body{padding:20px}
.scanner-controls{display:flex;gap:10px;margin-bottom:20px}
.btn-scanner{flex:1;padding:14px;border-radius:12px;border:none;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.btn-start{background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;box-shadow:0 4px 12px rgba(21,101,192,.3)}
.btn-start:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(21,101,192,.4)}
.btn-stop{background:linear-gradient(135deg,#c62828,#e53935);color:#fff;box-shadow:0 4px 12px rgba(198,40,40,.3)}
.btn-stop:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(198,40,40,.4)}
.btn-manual{background:#f8fafc;color:#475569;border:2px solid #e2e8f0}
.btn-manual:hover{background:#e2e8f0}
.alert-box{padding:16px;border-radius:12px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px;font-size:.9rem;line-height:1.5}
.alert-box i{font-size:1.2rem;margin-top:2px}
.alert-success{background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7}
.alert-error{background:#ffebee;color:#c62828;border:2px solid #ef9a9a}
.alert-info{background:#e3f2fd;color:#1565c0;border:2px solid #90caf9}
.result-card{background:#f8fafc;border-radius:12px;padding:18px;border:2px solid #e2e8f0}
.result-card h3{font-size:1rem;font-weight:800;color:#0d3b6e;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.result-detail{display:flex;align-items:center;gap:8px;font-size:.85rem;color:#475569;margin-bottom:8px}
.result-detail i{width:20px;color:#1565c0}
.cert-number{background:#fff;border-radius:8px;padding:10px;font-family:monospace;font-size:.85rem;font-weight:700;color:#0d3b6e;text-align:center;border:2px dashed #90caf9;margin-top:10px}
.instructions{background:#fff8e1;border-radius:12px;padding:16px;font-size:.85rem;color:#f57f17;line-height:1.6}
.instructions strong{color:#e65100}
#camera-status{text-align:center;padding:20px;color:#94a3b8;font-size:.9rem}
</style>
<!-- QR Code Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="scanner-container">
  
  <div class="scanner-box">
    <div class="scanner-header">
      <h1><i class="fas fa-qrcode"></i> <?= $pageTitle ?></h1>
      <p>Scan the <strong style="color:<?= $pageColor ?>"><?= $qrColorText ?> QR code</strong> displayed by the event organizer</p>
    </div>
    
    <div style="position:relative">
      <div id="camera-status" style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background:#000;z-index:1">
        <div style="text-align:center">
          <i class="fas fa-camera fa-3x" style="opacity:.3;margin-bottom:10px;display:block;color:#fff"></i>
          <p style="color:#fff">Camera not started</p>
        </div>
      </div>
      <div id="qr-reader" style="width:100%;min-height:400px"></div>
    </div>
    
    <div class="scanner-body">
      <?php if($scanMessage): ?>
        <div class="alert-box <?= $scanSuccess ? 'alert-success' : 'alert-error' ?>">
          <i class="fas fa-<?= $scanSuccess ? 'check-circle' : 'exclamation-circle' ?>"></i>
          <div><?= $scanMessage ?></div>
        </div>
      <?php endif; ?>
      
      <?php if($scanSuccess && $scanResult): ?>
        <div class="result-card">
          <h3><i class="fas fa-check-circle"></i> Check-in Successful!</h3>
          <div class="result-detail"><i class="fas fa-calendar-check"></i> <strong><?= htmlspecialchars($scanResult['event']) ?></strong></div>
          <?php if($scanResult['event_date']): ?>
          <div class="result-detail"><i class="fas fa-calendar"></i> <?= date('F j, Y', strtotime($scanResult['event_date'])) ?></div>
          <?php endif; ?>
          <?php if($scanResult['location']): ?>
          <div class="result-detail"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($scanResult['location']) ?></div>
          <?php endif; ?>
          <div class="result-detail"><i class="fas fa-star"></i> <strong style="color:#2e7d32">+<?= $scanResult['points'] ?> merit points</strong> when you check out</div>
          <div class="cert-number">
            <div style="font-size:.7rem;color:#94a3b8;margin-bottom:4px">Certificate Number</div>
            <?= htmlspecialchars($scanResult['cert_no']) ?>
          </div>
        </div>
        <div style="margin-top:16px;text-align:center">
          <a href="events.php" class="btn-scanner btn-start" style="display:inline-flex;max-width:280px">
            <i class="fas fa-arrow-left"></i> Back to Events
          </a>
        </div>
      <?php else: ?>
        <div class="scanner-controls">
          <button id="btn-start" class="btn-scanner btn-start" onclick="startScanner()">
            <i class="fas fa-camera"></i> Start Camera
          </button>
          <button id="btn-stop" class="btn-scanner btn-stop" onclick="stopScanner()" style="display:none">
            <i class="fas fa-stop"></i> Stop Camera
          </button>
        </div>
        
        <div class="instructions">
          <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-info-circle"></i> How to Scan QR Code from Screen:
          </div>
          
          <ol style="margin-left:18px;margin-top:6px;font-size:.88rem;line-height:1.8">
            <li style="margin-bottom:4px">Click <strong>"Start Camera"</strong> button above</li>
            <li style="margin-bottom:4px"><strong>Increase organizer's screen brightness to maximum</strong></li>
            <li style="margin-bottom:4px">Hold your phone <strong>10-15cm (4-6 inches)</strong> away from the screen</li>
            <li style="margin-bottom:4px"><strong>Hold steady</strong> - avoid moving your phone</li>
            <li style="margin-bottom:4px">If glare/reflection appears, <strong>tilt your phone slightly</strong></li>
            <li>Scanner will automatically detect when clear</li>
          </ol>
          
          <div style="margin-top:12px;padding:10px 12px;background:rgba(255,152,0,.1);border-radius:8px;font-size:.78rem;color:#f57f17;line-height:1.6">
            <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Scanning from another screen can be tricky due to glare and moiré patterns. For best results, ask the organizer to maximize brightness and hold your phone steady at arm's length.
          </div>
          
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(245,127,23,.3);font-size:.8rem">
            <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> You only have 15 minutes from event start to check in.
          </div>
          
          <div style="margin-top:10px;padding:8px 12px;background:rgba(255,255,255,.5);border-radius:6px;font-size:.75rem;color:#e65100">
            ⚠️ <strong>Note:</strong> Camera scanning requires HTTPS connection. If camera won't start, check your browser settings or try a different browser.
          </div>
        </div>
      <?php endif; ?>
      
      <div id="scan-result" style="display:none"></div>
    </div>
  </div>
  
</div>

</main>
</div>

<script>
let html5QrCode = null;
let isScanning = false;

async function getCameraId() {
  try {
    const devices = await Html5Qrcode.getCameras();
    console.log('Available cameras:', devices);
    if (devices && devices.length > 0) {
      // Prefer back camera (environment facing)
      const backCamera = devices.find(device => 
        device.label.toLowerCase().includes('back') || 
        device.label.toLowerCase().includes('rear') ||
        device.label.toLowerCase().includes('environment')
      );
      return backCamera ? backCamera.id : devices[0].id;
    }
    return null;
  } catch (err) {
    console.error('Error getting cameras:', err);
    return null;
  }
}

async function startScanner() {
  const cameraStatus = document.getElementById('camera-status');
  const btnStart = document.getElementById('btn-start');
  const btnStop = document.getElementById('btn-stop');
  
  // Show loading status
  cameraStatus.innerHTML = '<div style="text-align:center"><i class="fas fa-spinner fa-spin fa-3x" style="opacity:.5;margin-bottom:10px;display:block;color:#fff"></i><p style="color:#fff">Starting camera...</p></div>';
  btnStart.disabled = true;
  
  // Initialize scanner on qr-reader div
  html5QrCode = new Html5Qrcode("qr-reader");
  
  const config = {
    fps: 30, // High FPS for faster detection
    qrbox: function(viewfinderWidth, viewfinderHeight) {
      // Use even larger scan area - 90% of screen
      let minEdgePercentage = 0.9;
      let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
      let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
      return {
        width: qrboxSize,
        height: qrboxSize
      };
    },
    aspectRatio: 1.0,
    showTorchButtonIfSupported: true,
    useBarCodeDetectorIfSupported: true,
    formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ],
    // Advanced options for better detection of screen QR codes
    experimentalFeatures: {
      useBarCodeDetectorIfSupported: true
    },
    rememberLastUsedCamera: true,
    supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
    // Disable autofocus to help with screen glare
    disableFlip: false,
    videoConstraints: {
      facingMode: "environment",
      advanced: [
        { focusMode: "continuous" },
        { exposureMode: "continuous" },
        { whiteBalanceMode: "continuous" }
      ]
    }
  };
  
  try {
    // Method 1: Try with camera ID
    const cameraId = await getCameraId();
    console.log('Selected camera ID:', cameraId);
    
    if (cameraId) {
      console.log('Attempting to start camera with ID...');
      await html5QrCode.start(
        cameraId,
        config,
        (decodedText, decodedResult) => {
          if(!isScanning) {
            isScanning = true;
            console.log('QR Code detected:', decodedText);
            onScanSuccess(decodedText);
          }
        },
        (errorMessage) => {
          // Ignore scan errors
        }
      );
    } else {
      // Method 2: Try with facing mode constraint
      console.log('No camera ID found, trying facing mode...');
      await html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
          if(!isScanning) {
            isScanning = true;
            console.log('QR Code detected:', decodedText);
            onScanSuccess(decodedText);
          }
        },
        (errorMessage) => {
          // Ignore scan errors
        }
      );
    }
    
    // Camera started successfully
    console.log('Camera started successfully');
    cameraStatus.style.display = 'none';
    btnStart.style.display = 'none';
    btnStop.style.display = 'flex';
    
  } catch (err) {
    // Error starting camera
    console.error('Error starting camera:', err);
    console.error('Error details:', JSON.stringify(err));
    
    let errorMsg = 'Failed to access camera. ';
    
    if (err.message && err.message.includes('NotAllowedError')) {
      errorMsg += 'Camera permission was denied. Please check your browser settings and allow camera access for this site.';
    } else if (err.message && err.message.includes('NotFoundError')) {
      errorMsg += 'No camera found on your device.';
    } else if (err.message && err.message.includes('NotReadableError')) {
      errorMsg += 'Camera is already in use by another application.';
    } else if (err.message && err.message.includes('OverconstrainedError')) {
      errorMsg += 'Camera constraints could not be satisfied.';
    } else if (err.message && err.message.includes('SecurityError')) {
      errorMsg += 'Camera access blocked due to security policy. Try using HTTPS.';
    } else {
      errorMsg += 'Please ensure camera permissions are enabled and no other app is using the camera. Error: ' + (err.message || err);
    }
    
    cameraStatus.innerHTML = '<div style="text-align:center;padding:20px"><i class="fas fa-exclamation-triangle fa-2x" style="color:#ff9800;opacity:.9;margin-bottom:10px;display:block"></i><p style="color:#fff;line-height:1.6;font-size:.85rem">' + errorMsg + '</p><button onclick="location.reload()" style="margin-top:12px;padding:8px 16px;background:#1565c0;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600">Refresh Page</button></div>';
    btnStart.disabled = false;
    btnStart.style.display = 'flex';
    html5QrCode = null;
  }
}

function stopScanner() {
  if(html5QrCode) {
    html5QrCode.stop().then(() => {
      const cameraStatus = document.getElementById('camera-status');
      const btnStart = document.getElementById('btn-start');
      const btnStop = document.getElementById('btn-stop');
      
      cameraStatus.style.display = 'flex';
      cameraStatus.innerHTML = '<div style="text-align:center"><i class="fas fa-camera fa-3x" style="opacity:.3;margin-bottom:10px;display:block;color:#fff"></i><p style="color:#fff">Camera stopped</p></div>';
      btnStart.style.display = 'flex';
      btnStart.disabled = false;
      btnStop.style.display = 'none';
      
      html5QrCode = null;
      isScanning = false;
      console.log('Camera stopped');
    }).catch((err) => {
      console.error('Error stopping camera:', err);
    });
  }
}

function onScanSuccess(decodedText) {
  // Stop scanner
  if(html5QrCode) {
    html5QrCode.stop().catch(err => console.error('Error stopping:', err));
  }
  
  // Show processing message
  const scanResult = document.getElementById('scan-result');
  scanResult.style.display = 'block';
  scanResult.innerHTML = '<div class="alert-box alert-info"><i class="fas fa-spinner fa-spin"></i><div>Processing QR code...</div></div>';
  
  // Extract token from URL if scanned text is a full URL
  let token = decodedText;
  console.log('Raw scanned data:', decodedText);
  
  if(decodedText.includes('event_checkin.php?token=')) {
    try {
      const url = new URL(decodedText);
      token = url.searchParams.get('token');
      console.log('Extracted token:', token);
    } catch(e) {
      console.error('Error parsing URL:', e);
    }
  }
  
  // Create FormData to submit with photo
  const formData = new FormData();
  formData.append('scanned_token', token);
  
  // Check if there's a pending photo in sessionStorage
  if (typeof(Storage) !== "undefined") {
    const checkinPhoto = sessionStorage.getItem('pendingCheckinPhoto');
    const checkinPhotoName = sessionStorage.getItem('pendingCheckinPhotoName');
    const checkoutPhoto = sessionStorage.getItem('pendingCheckoutPhoto');
    const checkoutPhotoName = sessionStorage.getItem('pendingCheckoutPhotoName');
    
    // Convert base64 to File object and add to form
    if (checkinPhoto && checkinPhotoName) {
      const blob = dataURItoBlob(checkinPhoto);
      formData.append('checkin_photo', blob, checkinPhotoName);
      console.log('Added check-in photo to submission');
      // Clear after use
      sessionStorage.removeItem('pendingCheckinPhoto');
      sessionStorage.removeItem('pendingCheckinPhotoName');
    }
    
    if (checkoutPhoto && checkoutPhotoName) {
      const blob = dataURItoBlob(checkoutPhoto);
      formData.append('checkout_photo', blob, checkoutPhotoName);
      console.log('Added check-out photo to submission');
      // Clear after use
      sessionStorage.removeItem('pendingCheckoutPhoto');
      sessionStorage.removeItem('pendingCheckoutPhotoName');
    }
  }
  
  // Submit via fetch to handle FormData
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(html => {
    // Reload page with response
    document.open();
    document.write(html);
    document.close();
  })
  .catch(error => {
    console.error('Submission error:', error);
    scanResult.innerHTML = '<div class="alert-box alert-error"><i class="fas fa-exclamation-circle"></i><div>Error submitting check-in. Please try again.</div></div>';
  });
}

// Helper function to convert base64 to Blob
function dataURItoBlob(dataURI) {
  const byteString = atob(dataURI.split(',')[1]);
  const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
  const ab = new ArrayBuffer(byteString.length);
  const ia = new Uint8Array(ab);
  for (let i = 0; i < byteString.length; i++) {
    ia[i] = byteString.charCodeAt(i);
  }
  return new Blob([ab], { type: mimeString });
}

// Test camera permissions on page load
console.log('User Agent:', navigator.userAgent);
console.log('Page loaded, checking camera availability...');

// Test camera permissions on page load
if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
  console.log('getUserMedia is supported');
  
  // Check permission state if available
  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'camera' }).then(function(result) {
      console.log('Camera permission state:', result.state);
      if (result.state === 'granted') {
        console.log('Camera permission already granted');
      } else if (result.state === 'prompt') {
        console.log('Camera permission will be requested');
      } else if (result.state === 'denied') {
        console.log('Camera permission denied');
        const cameraStatus = document.getElementById('camera-status');
        if (cameraStatus) {
          cameraStatus.innerHTML = '<div style="text-align:center;padding:20px"><i class="fas fa-exclamation-triangle fa-2x" style="color:#ff9800;margin-bottom:10px;display:block"></i><p style="color:#fff;line-height:1.6">Camera access is blocked. Please enable camera permissions in your browser settings for this site.</p><p style="color:#90caf9;font-size:.8rem;margin-top:10px">Settings → Site Settings → Camera</p></div>';
        }
      }
    }).catch(err => {
      console.log('Permission query not supported:', err);
    });
  }
} else {
  console.error('getUserMedia not supported');
}

// Auto-start camera on mobile devices
if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
  console.log('Mobile device detected, auto-starting camera in 1 second');
  setTimeout(() => {
    if(!isScanning && !html5QrCode) {
      startScanner();
    }
  }, 1000);
}
</script>

</body>
</html>
