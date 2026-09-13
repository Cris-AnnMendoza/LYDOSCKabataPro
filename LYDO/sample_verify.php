<?php
/**
 * Sample Certificate Verification Page
 * For testing QR code scanning
 */
require_once __DIR__ . '/shared/config.php';

$pdo = db();

$certNo = trim($_GET['cert'] ?? '');
$hash   = trim($_GET['h'] ?? '');

$verified = false;
$error    = '';

// Sample certificate data
$sampleCertNo = 'LYDO-EVT-2026-SAMPLE-001';

if (empty($certNo)) {
    $error = 'Certificate number is required.';
} elseif ($certNo !== $sampleCertNo) {
    $error = 'This is a test verification page. Only sample certificate can be verified here.';
} else {
    // Verify hash
    $verifySecret = 'LYDO_VERIFY_2026_' . DB_NAME;
    $expectedHash = substr(hash_hmac('sha256', $certNo . '999' . '999', $verifySecret), 0, 16);
    
    if ($hash !== $expectedHash) {
        $error = 'Invalid verification code. This certificate may be fraudulent.';
    } else {
        $verified = true;
    }
}

// Sample data
$sampleData = [
    'name' => 'Juan Dela Cruz',
    'barangay' => 'Barangay Poblacion I',
    'event' => 'Youth Leadership Summit 2026',
    'event_date' => 'September 9, 2026',
    'location' => 'Municipal Hall, Sta. Cruz, Laguna',
    'time_in' => '8:30 AM',
    'time_out' => '5:00 PM',
    'issued' => 'September 9, 2026',
    'merit_points' => 5
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Verify Sample Certificate – LYDO</title>
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
    animation:slideUp 0.4s ease-out;
}
@keyframes slideUp {
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
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
    animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform:scale(1); }
    50% { transform:scale(1.05); }
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
    animation:checkmark 0.5s ease-out;
}
@keyframes checkmark {
    0% { transform:scale(0); }
    50% { transform:scale(1.1); }
    100% { transform:scale(1); }
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
.badge-sample {
    background:#fff8e1;
    border:2px solid #f57f17;
    color:#f57f17;
    padding:12px 16px;
    border-radius:8px;
    margin-bottom:16px;
    text-align:center;
    font-weight:600;
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
            <div class="badge-sample">
                <i class="fas fa-info-circle"></i> SAMPLE CERTIFICATE - For Testing Only
            </div>
            
            <div class="status-box success">
                <div class="status-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="status-title">✓ QR Code Scan Successful!</div>
                <div class="status-msg">
                    🎉 <strong>Congratulations!</strong> Your QR code scanning is working perfectly. 
                    This sample certificate demonstrates that certificates can be verified via QR code.
                </div>
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
                        <?= htmlspecialchars($sampleData['name']) ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-map-marker-alt"></i> Barangay
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($sampleData['barangay']) ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar-check"></i> Event
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($sampleData['event']) ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar"></i> Event Date
                    </div>
                    <div class="cert-info-value">
                        <?= $sampleData['event_date'] ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-map-pin"></i> Location
                    </div>
                    <div class="cert-info-value">
                        <?= htmlspecialchars($sampleData['location']) ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-sign-in-alt"></i> Time In
                    </div>
                    <div class="cert-info-value">
                        <?= $sampleData['time_in'] ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-sign-out-alt"></i> Time Out
                    </div>
                    <div class="cert-info-value">
                        <?= $sampleData['time_out'] ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-calendar-plus"></i> Issued
                    </div>
                    <div class="cert-info-value">
                        <?= $sampleData['issued'] ?>
                    </div>
                </div>
                
                <div class="cert-info-row">
                    <div class="cert-info-label">
                        <i class="fas fa-star"></i> Merit Points
                    </div>
                    <div class="cert-info-value">
                        <span class="badge badge-success">
                            <i class="fas fa-star"></i> 
                            +<?= $sampleData['merit_points'] ?> points awarded
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:16px;border-radius:8px;margin-bottom:16px;color:#1b5e20;font-size:.85rem;line-height:1.6">
                <strong><i class="fas fa-check-circle"></i> Test Successful!</strong><br>
                Your certificate QR codes will work the same way. Youth members can scan certificates to verify their authenticity and view full event details.
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
                <strong><i class="fas fa-exclamation-triangle"></i> Note:</strong> 
                This is a test verification page for the sample certificate only. 
                To test with real certificates, generate sample check-ins first.
            </div>
        <?php endif; ?>
        
        <a href="/LYDO/sample_certificate.php" class="btn-back">
            <i class="fas fa-certificate"></i>
            Back to Sample Certificate
        </a>
        
    </div>
    
    <div class="verify-footer">
        <div class="verify-footer-text">
            This is a test verification page demonstrating certificate authentication via QR code scanning.
            <br>
            Real certificates use the same secure verification system.
        </div>
        <div class="verify-footer-logo">
            🏛️ Local Youth Development Office · Sta. Cruz, Laguna
        </div>
    </div>
</div>

</body>
</html>
