<?php
/**
 * Certificate Verification Page
 * Scanned from QR code on certificates
 */
require_once __DIR__ . '/shared/config.php';

$pdo = db();

$certNo = trim($_GET['cert'] ?? '');
$hash   = trim($_GET['h'] ?? '');

$verified = false;
$cert     = null;
$event    = null;
$user     = null;
$checkin  = null;
$error    = '';

if (empty($certNo)) {
    $error = 'Certificate number is required.';
} else {
    // Load certificate
    $certStmt = $pdo->prepare('SELECT * FROM event_certificates WHERE cert_number = ? LIMIT 1');
    $certStmt->execute([$certNo]);
    $cert = $certStmt->fetch();
    
    if (!$cert) {
        $error = 'Certificate not found in our database.';
    } else {
        // Verify hash
        $verifySecret = 'LYDO_VERIFY_2026_' . DB_NAME;
        $expectedHash = substr(hash_hmac('sha256', $certNo . $cert['user_id'] . $cert['event_id'], $verifySecret), 0, 16);
        
        if ($hash !== $expectedHash) {
            $error = 'Invalid verification code. This certificate may be fraudulent.';
        } else {
            $verified = true;
            
            // Load event
            $evStmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
            $evStmt->execute([$cert['event_id']]);
            $event = $evStmt->fetch();
            
            // Load user
            $uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id = ? LIMIT 1');
            $uStmt->execute([$cert['user_id']]);
            $user = $uStmt->fetch();
            
            // Load check-in record
            $ciStmt = $pdo->prepare('SELECT * FROM event_checkins WHERE event_id = ? AND user_id = ? LIMIT 1');
            $ciStmt->execute([$cert['event_id'], $cert['user_id']]);
            $checkin = $ciStmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Verify Certificate – LYDO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
* {box-sizing:border-box;margin:0;padding:0}
body {
    font-family:'Inter',sans-serif;
    background:linear-gradient(135deg,#0d3b6e 0%,#1565c0 50%,#1e88e5 100%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.verify-container {
    max-width:580px;
    width:100%;
    background:#fff;
    border-radius:20px;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
    overflow:hidden;
}
.verify-header {
    background:linear-gradient(135deg,#0d3b6e,#1565c0);
    color:#fff;
    padding:32px 28px;
    text-align:center;
    position:relative;
}
.verify-header::before {
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity:0.3;
}
.verify-icon {
    width:80px;
    height:80px;
    margin:0 auto 16px;
    background:rgba(255,255,255,.15);
    border:3px solid rgba(255,255,255,.3);
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:2rem;
    position:relative;
}
.verify-header h1 {
    font-size:1.6rem;
    font-weight:800;
    margin-bottom:6px;
    position:relative;
}
.verify-header p {
    font-size:.9rem;
    opacity:.9;
    position:relative;
}
.verify-body {
    padding:32px 28px;
}
.status-box {
    text-align:center;
    padding:24px;
    border-radius:14px;
    margin-bottom:28px;
}
.status-box.success {
    background:#e8f5e9;
    border:2px solid #2e7d32;
}
.status-box.error {
    background:#ffebee;
    border:2px solid #c62828;
}
.status-icon {
    width:64px;
    height:64px;
    margin:0 auto 12px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.8rem;
}
.status-box.success .status-icon {
    background:#2e7d32;
    color:#fff;
}
.status-box.error .status-icon {
    background:#c62828;
    color:#fff;
}
.status-title {
    font-size:1.3rem;
    font-weight:800;
    margin-bottom:6px;
}
.status-box.success .status-title {
    color:#1b5e20;
}
.status-box.error .status-title {
    color:#c62828;
}
.status-msg {
    font-size:.9rem;
    line-height:1.6;
}
.status-box.success .status-msg {
    color:#2e7d32;
}
.status-box.error .status-msg {
    color:#c62828;
}
.cert-info {
    background:#f8fafc;
    border-radius:12px;
    padding:20px;
    margin-bottom:16px;
}
.cert-info-row {
    display:flex;
    padding:10px 0;
    border-bottom:1px solid #e2e8f0;
}
.cert-info-row:last-child {
    border-bottom:none;
}
.cert-info-label {
    font-size:.78rem;
    color:#64748b;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.05em;
    min-width:120px;
    display:flex;
    align-items:center;
    gap:6px;
}
.cert-info-value {
    font-size:.9rem;
    color:#0f172a;
    font-weight:600;
    flex:1;
}
.cert-number-box {
    background:linear-gradient(135deg,#0d3b6e,#1565c0);
    color:#fff;
    padding:16px;
    border-radius:10px;
    text-align:center;
    margin-bottom:20px;
}
.cert-number-label {
    font-size:.7rem;
    opacity:.8;
    text-transform:uppercase;
    letter-spacing:.1em;
    margin-bottom:4px;
}
.cert-number {
    font-family:'Courier New',monospace;
    font-size:1.1rem;
    font-weight:700;
    letter-spacing:.05em;
}
.btn-back {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:12px 24px;
    background:#f1f5f9;
    color:#475569;
    border:none;
    border-radius:10px;
    font-family:inherit;
    font-size:.9rem;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    transition:.2s;
    width:100%;
}
.btn-back:hover {
    background:#e2e8f0;
}
.verify-footer {
    background:#f8fafc;
    padding:20px 28px;
    text-align:center;
    border-top:1px solid #e2e8f0;
}
.verify-footer-text {
    font-size:.75rem;
    color:#64748b;
    line-height:1.6;
}
.verify-footer-logo {
    margin-top:12px;
    font-size:.7rem;
    color:#94a3b8;
    font-weight:600;
}
.badge {
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:4px 12px;
    border-radius:50px;
    font-size:.75rem;
    font-weight:700;
}
.badge-success {
    background:#e8f5e9;
    color:#2e7d32;
}
@media(max-width:600px){
    .cert-info-row {
        flex-direction:column;
        gap:4px;
    }
    .cert-info-label {
        min-width:unset;
    }
}
</style>
</head>
<body>

<div class="verify-container">
    <div class="verify-header">
        <div class="verify-icon">
            <i class="fas fa-shield-check"></i>
        </div>
        <h1>Certificate Verification</h1>
        <p>Local Youth Development Office</p>
    </div>
    
    <div class="verify-body">
        
        <?php if ($verified): ?>
            <!-- VERIFIED -->
            <div class="status-box success">
                <div class="status-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-title">✓ Certificate Verified</div>
                <div class="status-msg">This certificate is authentic and has been issued by the Local Youth Development Office of Sta. Cruz, Laguna.</div>
            </div>
            
            <div class="cert-number-box">
                <div class="cert-number-label">Certificate Number</div>
                <div class="cert-number"><?= htmlspecialchars($certNo) ?></div>
            </div>
            
            <div class="cert-info">
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-user"></i> Recipient
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                    </div>
                </div>
                
                <?php if ($event): ?>
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar-check"></i> Event
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($event['title']) ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar"></i> Event Date
                    </div>
                    <div class="cert-info-value">
                        <?= date('F j, Y', strtotime($event['event_date'])) ?>
                    </div>
                </div>
                
                <?php if ($event['location']): ?>
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-map-marker-alt"></i> Location
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($event['location']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($checkin): ?>
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-sign-in-alt"></i> Time In
                    </div>
                    <div class="cert-info-value">
                        <?= date('g:i A', strtotime($checkin['checked_in_at'])) ?>
                    </div>
                </div>
                
                <?php if ($checkin['checked_out_at']): ?>
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-sign-out-alt"></i> Time Out
                    </div>
                    <div class="cert-info-value">
                        <?= date('g:i A', strtotime($checkin['checked_out_at'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar-plus"></i> Issued
                    </div>
                    <div class="cert-info-value">
                        <?= date('F j, Y', strtotime($cert['generated_at'])) ?>
                    </div>
                </div>
                
                <?php if ($cert['merit_awarded']): ?>
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-star"></i> Merit Points
                    </div>
                    <div class="cert-info-value">
                        <span class="badge badge-success">
                            <i class="fas fa-star"></i> 
                            +<?= (int)$cert['merit_points'] ?> points awarded
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- NOT VERIFIED / ERROR -->
            <div class="status-box error">
                <div class="status-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="status-title">✗ Verification Failed</div>
                <div class="status-msg"><?= htmlspecialchars($error) ?></div>
            </div>
            
            <?php if (!empty($certNo)): ?>
            <div class="cert-number-box" style="background:#c62828">
                <div class="cert-number-label">Certificate Number</div>
                <div class="cert-number"><?= htmlspecialchars($certNo) ?></div>
            </div>
            <?php endif; ?>
            
            <div style="background:#fff8e1;border:2px solid #f57f17;border-radius:10px;padding:16px;margin-bottom:20px;color:#f57f17;font-size:.85rem;line-height:1.6">
                <strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong> If you believe this certificate should be valid, please contact the Local Youth Development Office immediately. This may indicate a fraudulent certificate.
            </div>
        <?php endif; ?>
        
        <a href="/LYDO/" class="btn-back">
            <i class="fas fa-home"></i>
            Back to LYDO Portal
        </a>
        
    </div>
    
    <div class="verify-footer">
        <div class="verify-footer-text">
            This verification page confirms the authenticity of certificates issued by the Local Youth Development Office.
            <br>
            For inquiries, contact LYDO Sta. Cruz, Laguna.
        </div>
        <div class="verify-footer-logo">
            🏛️ Local Youth Development Office · Sta. Cruz, Laguna
        </div>
    </div>
</div>

</body>
</html>
