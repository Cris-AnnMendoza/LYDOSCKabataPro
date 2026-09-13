<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

$pdo    = db();
$userId = (int)$_SESSION['user_id'];
$regId  = (int)($_GET['reg'] ?? 0);

if (!$regId) { die('Invalid request.'); }

// Load registration — must belong to this user and be approved/completed
$stmt = $pdo->prepare("
    SELECT r.*, p.name as prog_name, p.type as prog_type, p.location,
           u.first_name, u.last_name, u.barangay
    FROM volunteer_registrations r
    JOIN volunteer_programs p ON p.id = r.program_id
    JOIN youth_users u ON u.id = r.user_id
    WHERE r.id = ? AND r.user_id = ? AND r.status IN ('approved','completed') AND r.certificate_issued = TRUE
    LIMIT 1
");
$stmt->execute([$regId, $userId]);
$reg = $stmt->fetch();

if (!$reg) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c62828">
        <h2>Certificate Not Available</h2>
        <p>This certificate has not been issued yet or does not belong to your account.</p>
        <a href="volunteer.php" style="color:#1565c0">Back to Volunteer Program</a>
    </div>');
}

// Get total hours
$hoursStmt = $pdo->prepare('SELECT COALESCE(SUM(hours),0) FROM volunteer_attendance WHERE registration_id=? AND status="present"');
$hoursStmt->execute([$regId]);
$totalHours = (float)$hoursStmt->fetchColumn() ?: $reg['total_hours'];

$typeLabels = [
    'youth_volunteer'  => 'Youth Volunteer Program',
    'linggo_kabataan'  => 'Linggo ng Kabataan',
    'junior_officials' => 'Junior Officials Program',
];
$progLabel = $typeLabels[$reg['prog_type']] ?? $reg['prog_name'];
$fullName  = strtoupper($reg['first_name'] . ' ' . $reg['last_name']);
$certNo    = 'LYDO-VOL-' . date('Y') . '-' . str_pad($regId, 4, '0', STR_PAD_LEFT);
$issueDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Certificate of Participation – <?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f0f0;display:flex;flex-direction:column;align-items:center;min-height:100vh;padding:20px}

/* Print controls */
.print-bar{background:#1565c0;color:#fff;padding:12px 24px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:14px;width:100%;max-width:900px;flex-wrap:wrap}
.print-bar span{flex:1;font-size:.9rem;font-weight:500}
.btn-print{padding:9px 20px;background:#fff;color:#1565c0;border:none;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.2s}
.btn-print:hover{background:#e3f2fd}
.btn-back{padding:9px 20px;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:7px;transition:.2s}
.btn-back:hover{background:rgba(255,255,255,.25)}

/* Certificate */
.cert-wrap{width:900px;background:#fff;position:relative;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.2)}

/* Border frame */
.cert-border{position:absolute;inset:12px;border:3px solid #1565c0;pointer-events:none;z-index:2}
.cert-border::before{content:'';position:absolute;inset:6px;border:1px solid #c8a84b;pointer-events:none}

/* Background pattern */
.cert-bg{position:absolute;inset:0;background:
  radial-gradient(ellipse at 10% 10%, rgba(21,101,192,.04) 0%, transparent 50%),
  radial-gradient(ellipse at 90% 90%, rgba(46,125,50,.04) 0%, transparent 50%),
  radial-gradient(ellipse at 50% 50%, rgba(200,168,75,.03) 0%, transparent 70%);
}

/* Content */
.cert-content{position:relative;z-index:3;padding:50px 70px;text-align:center}

/* Header */
.cert-header{display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:28px}
.cert-seal{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#0d3b6e,#1565c0);display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;box-shadow:0 4px 16px rgba(21,101,192,.3);flex-shrink:0}
.cert-org{text-align:left}
.cert-org-main{font-size:1.1rem;font-weight:800;color:#0d3b6e;letter-spacing:.02em}
.cert-org-sub{font-size:.78rem;color:#475569;font-weight:500;margin-top:2px}
.cert-org-loc{font-size:.72rem;color:#94a3b8;margin-top:1px}

/* Gold divider */
.gold-line{height:3px;background:linear-gradient(90deg,transparent,#c8a84b,#f0d060,#c8a84b,transparent);margin:20px 0;border-radius:2px}
.thin-line{height:1px;background:linear-gradient(90deg,transparent,#e2e8f0,transparent);margin:16px 0}

/* Title */
.cert-type{font-size:.85rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#c8a84b;margin-bottom:8px}
.cert-title{font-family:'Playfair Display',serif;font-size:2.8rem;font-weight:900;color:#0d3b6e;line-height:1.1;margin-bottom:6px}
.cert-subtitle{font-size:.9rem;color:#475569;font-weight:500}

/* Recipient */
.cert-presented{font-size:.88rem;color:#475569;margin:24px 0 8px;font-style:italic}
.cert-name{font-family:'Playfair Display',serif;font-size:2.4rem;font-weight:700;color:#1565c0;border-bottom:2px solid #c8a84b;display:inline-block;padding-bottom:4px;margin-bottom:8px;min-width:400px}
.cert-barangay{font-size:.85rem;color:#475569}

/* Body text */
.cert-body{font-size:.92rem;color:#475569;line-height:1.8;margin:20px 0;max-width:600px;margin-left:auto;margin-right:auto}
.cert-program{font-weight:700;color:#0d3b6e;font-size:1rem}
.cert-hours{display:inline-block;background:#e3f2fd;color:#1565c0;padding:4px 16px;border-radius:50px;font-weight:700;font-size:.9rem;margin:4px 0}

/* Footer */
.cert-footer{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:36px;align-items:end}
.cert-sig{text-align:center}
.cert-sig-line{height:1px;background:#1565c0;margin-bottom:6px}
.cert-sig-name{font-size:.82rem;font-weight:700;color:#0d3b6e}
.cert-sig-title{font-size:.72rem;color:#475569}
.cert-no{text-align:center;font-size:.72rem;color:#94a3b8;margin-top:20px}
.cert-date-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;text-align:center}
.cert-date-label{font-size:.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em}
.cert-date-val{font-size:.88rem;font-weight:700;color:#0d3b6e;margin-top:2px}

/* Corner ornaments */
.corner{position:absolute;width:40px;height:40px;z-index:4}
.corner-tl{top:20px;left:20px;border-top:3px solid #c8a84b;border-left:3px solid #c8a84b}
.corner-tr{top:20px;right:20px;border-top:3px solid #c8a84b;border-right:3px solid #c8a84b}
.corner-bl{bottom:20px;left:20px;border-bottom:3px solid #c8a84b;border-left:3px solid #c8a84b}
.corner-br{bottom:20px;right:20px;border-bottom:3px solid #c8a84b;border-right:3px solid #c8a84b}

@media print {
  body{background:#fff;padding:0}
  .print-bar{display:none}
  .cert-wrap{box-shadow:none;width:100%}
  @page{size:landscape;margin:0}
}
</style>
</head>
<body>

<!-- Print controls -->
<div class="print-bar">
  <a href="volunteer.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
  <span>Certificate of Participation — <?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></span>
  <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Certificate</button>
  <button class="btn-print" onclick="downloadCert()"><i class="fas fa-download"></i> Save as PDF</button>
</div>

<!-- Certificate -->
<div class="cert-wrap" id="certificate">
  <div class="cert-bg"></div>
  <div class="cert-border"></div>

  <!-- Corner ornaments -->
  <div class="corner corner-tl"></div>
  <div class="corner corner-tr"></div>
  <div class="corner corner-bl"></div>
  <div class="corner corner-br"></div>

  <div class="cert-content">

    <!-- Header -->
    <div class="cert-header">
      <div class="cert-seal" style="background:#fff;border:2px solid #e2e8f0;padding:4px"><img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:100%;height:100%;object-fit:contain"/></div>
      <div class="cert-org">
        <div class="cert-org-main">Local Youth Development Office</div>
        <div class="cert-org-sub">Municipal Government of Sta. Cruz, Laguna</div>
        <div class="cert-org-loc">Sta. Cruz, Laguna 4009 · Philippines</div>
      </div>
      <div class="cert-seal" style="background:linear-gradient(135deg,#2e7d32,#43a047)"><i class="fas fa-award"></i></div>
    </div>

    <div class="gold-line"></div>

    <!-- Title -->
    <div class="cert-type">Certificate of Participation</div>
    <div class="cert-title">This is to Certify That</div>

    <div class="thin-line"></div>

    <!-- Recipient -->
    <div class="cert-presented">This certificate is proudly presented to</div>
    <div class="cert-name"><?= htmlspecialchars($fullName) ?></div>
    <?php if ($reg['barangay']): ?>
    <div class="cert-barangay">of <?= htmlspecialchars($reg['barangay']) ?>, Sta. Cruz, Laguna</div>
    <?php endif; ?>

    <!-- Body -->
    <div class="cert-body">
      has successfully participated and completed the
      <br>
      <span class="cert-program"><?= htmlspecialchars($reg['prog_name']) ?></span>
      <br>
      under the <strong><?= htmlspecialchars($progLabel) ?></strong>
      <br>
      organized by the Local Youth Development Office of Sta. Cruz, Laguna.
      <?php if ($totalHours > 0): ?>
      <br><br>
      Total Volunteer Hours Rendered:
      <span class="cert-hours"><?= number_format($totalHours, 1) ?> hours</span>
      <?php endif; ?>
    </div>

    <div class="gold-line"></div>

    <!-- Footer signatures -->
    <div class="cert-footer">
      <div class="cert-sig">
        <div style="height:40px"></div>
        <div class="cert-sig-line"></div>
        <div class="cert-sig-name">LYDO Coordinator</div>
        <div class="cert-sig-title">Youth Coordinator</div>
        <div class="cert-sig-title">Local Youth Development Office</div>
      </div>

      <div class="cert-date-box">
        <div class="cert-date-label">Certificate No.</div>
        <div class="cert-date-val" style="font-size:.78rem"><?= $certNo ?></div>
        <div style="margin-top:10px">
          <div class="cert-date-label">Date Issued</div>
          <div class="cert-date-val"><?= $issueDate ?></div>
        </div>
      </div>

      <div class="cert-sig">
        <div style="height:40px"></div>
        <div class="cert-sig-line"></div>
        <div class="cert-sig-name">Municipal Mayor</div>
        <div class="cert-sig-title">Municipal Government</div>
        <div class="cert-sig-title">Sta. Cruz, Laguna</div>
      </div>
    </div>

    <div class="cert-no">
      This certificate is issued in recognition of active participation and dedication to youth development and community service.
    </div>

  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<script>
function downloadCert() {
  window.print();
}
</script>
</body>
</html>
