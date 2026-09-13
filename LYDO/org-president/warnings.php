<?php
session_start();
require_once __DIR__ . '/../shared/config.php';

// Check if logged in as organization president
if (empty($_SESSION['org_president_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$president = $_SESSION['org_president'];
$orgId = $president['organization_id'];

// Get organization details
$orgStmt = $pdo->prepare('SELECT * FROM organizations WHERE id=? LIMIT 1');
$orgStmt->execute([$orgId]);
$org = $orgStmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    die('No organization found.');
}

// Get warning letters
$warningsStmt = $pdo->prepare('
    SELECT w.*, a.full_name as issued_by_name
    FROM org_warning_letters w
    LEFT JOIN admin_users a ON a.id = w.issued_by
    WHERE w.organization_id = ?
    ORDER BY w.issued_at DESC
');
$warningsStmt->execute([$orgId]);
$warnings = $warningsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current demerit points
$demeritsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(ABS(points)),0) as total
    FROM org_merit_logs 
    WHERE organization_id=? AND type='demerit'
");
$demeritsStmt->execute([$orgId]);
$totalDemerits = (int)$demeritsStmt->fetchColumn();

// Get all violations (demerit logs) with details
$violationsStmt = $pdo->prepare("
    SELECT l.*, e.title as event_title, e.event_date, e.location,
           a.full_name as admin_name
    FROM org_merit_logs l
    LEFT JOIN events e ON e.id = l.event_id
    LEFT JOIN admin_users a ON a.id = l.awarded_by
    WHERE l.organization_id = ? AND l.type = 'demerit'
    ORDER BY l.created_at DESC
");
$violationsStmt->execute([$orgId]);
$violations = $violationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get merit points
$meritsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(points),0) as total
    FROM org_merit_logs 
    WHERE organization_id=? AND type='merit'
");
$meritsStmt->execute([$orgId]);
$totalMerits = (int)$meritsStmt->fetchColumn();

$netScore = $totalMerits - $totalDemerits;

// Determine status based on demerits
$status = 'good';
$statusLabel = 'Good Standing';
$statusColor = 'var(--green)';
$statusBg = 'var(--green-pale)';

if ($totalDemerits >= 10) {
    $status = 'critical';
    $statusLabel = 'Critical - Revocation Risk';
    $statusColor = 'var(--red)';
    $statusBg = 'var(--red-pale)';
} elseif ($totalDemerits >= 7) {
    $status = 'severe';
    $statusLabel = 'Severe Warning';
    $statusColor = 'var(--orange)';
    $statusBg = 'var(--orange-pale)';
} elseif ($totalDemerits >= 5) {
    $status = 'warning';
    $statusLabel = 'Under Warning';
    $statusColor = '#f57f17';
    $statusBg = '#fff8e1';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Warnings & Violations – LYDO President Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="president.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#1565c0;--blue-dark:#0d3b6e;--blue-light:#1e88e5;--blue-pale:#e3f2fd;--green:#2e7d32;--green-light:#43a047;--green-pale:#e8f5e9;--teal:#00796b;--teal-pale:#e0f2f1;--orange:#e65100;--orange-pale:#fff3e0;--red:#c62828;--red-pale:#ffebee;--purple:#7b1fa2;--purple-pale:#f3e5f5;--white:#fff;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-400:#94a3b8;--gray-600:#475569;--gray-800:#1e293b}
body{font-family:Inter,sans-serif;color:var(--gray-800);background:var(--gray-50)}
.topbar{display:none!important}
.content{padding:24px}
.welcome-banner{background:linear-gradient(135deg,var(--blue-dark),var(--blue));border-radius:12px;padding:24px 28px;margin-bottom:20px;box-shadow:0 4px 16px rgba(21,101,192,.2);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.welcome-content{flex:1}
.welcome-title{font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 6px 0}
.welcome-subtitle{font-size:.9rem;color:rgba(255,255,255,.85);margin:0;display:flex;align-items:center;gap:0;flex-wrap:wrap}
.org-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:6px 12px;border-radius:8px;font-weight:600;color:#fff;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2)}
.alert-banner{padding:14px 18px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:.88rem;font-weight:600;border:2px solid}
.alert-banner.critical{background:var(--red-pale);border-color:var(--red);color:var(--red)}
.alert-banner.severe{background:var(--orange-pale);border-color:var(--orange);color:var(--orange)}
.alert-banner.warning{background:#fff8e1;border-color:#f57f17;color:#f57f17}
.alert-banner i{font-size:1.2rem}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid var(--gray-200);transition:.2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.stat-card.red .stat-icon{background:var(--red-pale);color:var(--red)}
.stat-card.green .stat-icon{background:var(--green-pale);color:var(--green)}
.stat-card.teal .stat-icon{background:var(--teal-pale);color:var(--teal)}
.stat-card.orange .stat-icon{background:var(--orange-pale);color:var(--orange)}
.stat-val{display:block;font-size:1.7rem;font-weight:800;color:var(--gray-800);line-height:1}
.stat-lbl{font-size:.75rem;color:var(--gray-600);font-weight:500;margin-top:3px;display:block}
.card{background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
.card-header{padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:.88rem;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:7px}
.card-header h3 i{color:var(--blue);font-size:.82rem}
.threshold-list{padding:14px 18px}
.threshold-item{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:var(--gray-50);border:1px solid var(--gray-200);margin-bottom:8px}
.threshold-item:last-child{margin-bottom:0}
.threshold-item.reached{background:var(--red-pale);border-color:var(--red)}
.threshold-label{font-size:.85rem;font-weight:600;color:var(--gray-800);display:flex;align-items:center;gap:8px}
.threshold-item.reached .threshold-label{color:var(--red)}
.threshold-icon{width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.7rem}
.threshold-item .threshold-icon{background:var(--gray-200);color:var(--gray-600)}
.threshold-item.reached .threshold-icon{background:var(--red);color:#fff}
.violation-item{background:#fff;border:1px solid var(--gray-200);border-left:3px solid var(--red);border-radius:8px;padding:14px 16px;margin-bottom:12px}
.violation-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px}
.violation-points{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:var(--red-pale);color:var(--red);border-radius:6px;font-size:.75rem;font-weight:700}
.violation-reason{font-size:.88rem;color:var(--gray-800);margin-bottom:10px;line-height:1.5;font-weight:500}
.violation-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:.75rem;color:var(--gray-600);padding-top:10px;border-top:1px solid var(--gray-200)}
.violation-meta i{color:var(--gray-400);font-size:.7rem}
.warning-letter{background:#fff;border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:12px}
.warning-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px}
.warning-type{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:.75rem;font-weight:700}
.warning-type.warning{background:#fff8e1;color:#f57f17}
.warning-type.show_cause{background:var(--orange-pale);color:var(--orange)}
.warning-type.revocation{background:var(--red-pale);color:var(--red)}
.warning-content{font-size:.85rem;color:var(--gray-700);line-height:1.6;margin-bottom:10px}
.warning-footer{display:flex;justify-content:space-between;align-items:center;font-size:.75rem;color:var(--gray-600);padding-top:10px;border-top:1px solid var(--gray-200)}
.empty{text-align:center;color:var(--gray-400);padding:28px!important;font-style:italic}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--blue);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none}
.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px)}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.stats-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php if (isset($_SESSION['flash_success'])): ?>
  <div style="background:#e8f5e9;color:#2e7d32;padding:14px 18px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px;border:1.5px solid #a5d6a7">
    <i class="fas fa-check-circle" style="font-size:1.1rem"></i>
    <span style="font-weight:600"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
  </div>
  <?php unset($_SESSION['flash_success']); endif; ?>

  <?php if (isset($_SESSION['flash_error'])): ?>
  <div style="background:#ffebee;color:#c62828;padding:14px 18px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px;border:1.5px solid #ef9a9a">
    <i class="fas fa-exclamation-circle" style="font-size:1.1rem"></i>
    <span style="font-weight:600"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
  </div>
  <?php unset($_SESSION['flash_error']); endif; ?>

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-content">
      <h1 class="welcome-title">Warnings & Violations</h1>
      <div class="welcome-subtitle">
        <span style="margin-right:10px">Monitor demerit points for</span>
        <span class="org-badge">
          <i class="fas fa-exclamation-triangle"></i>
          <?= htmlspecialchars($org['name']) ?>
        </span>
      </div>
    </div>
  </div>

  <?php if ($status === 'critical'): ?>
  <div class="alert-banner critical">
    <i class="fas fa-ban"></i>
    <span>⛔ CRITICAL: Your organization has reached the membership revocation threshold (10+ demerits)!</span>
  </div>
  <?php elseif ($status === 'severe'): ?>
  <div class="alert-banner severe">
    <i class="fas fa-exclamation-circle"></i>
    <span>🚨 SEVERE WARNING: Show Cause Order issued. Immediate action required! (7+ demerits)</span>
  </div>
  <?php elseif ($status === 'warning'): ?>
  <div class="alert-banner warning">
    <i class="fas fa-exclamation-triangle"></i>
    <span>⚠️ WARNING: Your organization has received a formal warning letter (5+ demerits)</span>
  </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-card red">
      <div class="stat-icon"><i class="fas fa-minus-circle"></i></div>
      <div><span class="stat-val" style="color:var(--red)"><?= $totalDemerits ?></span><span class="stat-lbl">Demerit Points</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-star"></i></div>
      <div><span class="stat-val" style="color:var(--green)"><?= $totalMerits ?></span><span class="stat-lbl">Merit Points</span></div>
    </div>
    <div class="stat-card teal">
      <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
      <div><span class="stat-val" style="color:<?= $netScore >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $netScore >= 0 ? '+' : '' ?><?= $netScore ?></span><span class="stat-lbl">Net Score</span></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
      <div><span class="stat-val" style="color:var(--orange)"><?= count($warnings) ?></span><span class="stat-lbl">Warning Letters</span></div>
    </div>
  </div>

  <!-- Threshold Progress -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-tasks"></i> Demerit Thresholds</h3>
    </div>
    <div class="threshold-list">
      <div class="threshold-item <?= $totalDemerits >= 5 ? 'reached' : '' ?>">
        <div class="threshold-label">
          <span class="threshold-icon">
            <?= $totalDemerits >= 5 ? '<i class="fas fa-check"></i>' : '1' ?>
          </span>
          5 Points - Warning Letter
        </div>
        <div style="font-size:.75rem;font-weight:600;color:<?= $totalDemerits >= 5 ? 'var(--red)' : 'var(--gray-600)' ?>">
          <?= $totalDemerits >= 5 ? 'REACHED' : ($totalDemerits.'/5') ?>
        </div>
      </div>
      <div class="threshold-item <?= $totalDemerits >= 7 ? 'reached' : '' ?>">
        <div class="threshold-label">
          <span class="threshold-icon">
            <?= $totalDemerits >= 7 ? '<i class="fas fa-check"></i>' : '2' ?>
          </span>
          7 Points - Show Cause Order
        </div>
        <div style="font-size:.75rem;font-weight:600;color:<?= $totalDemerits >= 7 ? 'var(--red)' : 'var(--gray-600)' ?>">
          <?= $totalDemerits >= 7 ? 'REACHED' : ($totalDemerits.'/7') ?>
        </div>
      </div>
      <div class="threshold-item <?= $totalDemerits >= 10 ? 'reached' : '' ?>">
        <div class="threshold-label">
          <span class="threshold-icon">
            <?= $totalDemerits >= 10 ? '<i class="fas fa-check"></i>' : '3' ?>
          </span>
          10 Points - Membership Revocation
        </div>
        <div style="font-size:.75rem;font-weight:600;color:<?= $totalDemerits >= 10 ? 'var(--red)' : 'var(--gray-600)' ?>">
          <?= $totalDemerits >= 10 ? 'REACHED' : ($totalDemerits.'/10') ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Violation History -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-list"></i> Violation History (<?= count($violations) ?>)</h3>
    </div>
    <div style="padding:14px 18px">
      <?php if (empty($violations)): ?>
        <div class="empty">No violations recorded</div>
      <?php else: ?>
        <?php foreach ($violations as $v): ?>
        <div class="violation-item">
          <div class="violation-header">
            <div style="flex:1">
              <div class="violation-reason"><?= htmlspecialchars($v['reason']) ?></div>
              <div class="violation-meta">
                <?php if ($v['event_title']): ?>
                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($v['event_title']) ?></span>
                <?php endif; ?>
                <?php if ($v['admin_name']): ?>
                <span><i class="fas fa-user"></i> Issued by <?= htmlspecialchars($v['admin_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($v['created_at'])) ?></span>
              </div>
            </div>
            <div class="violation-points">
              <i class="fas fa-minus-circle"></i> <?= abs($v['points']) ?> pts
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Contact LYDO Section -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-envelope"></i> Submit Explanation Letter</h3>
    </div>
    <div style="padding:18px">
      <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:16px">
        If you need to submit an explanation regarding warnings or violations, you can upload your explanation letter below. The LYDO admin will review your submission.
      </p>
      
      <form method="POST" action="submit_explanation.php" enctype="multipart/form-data" style="background:var(--gray-50);border:1.5px solid var(--gray-200);border-radius:10px;padding:16px">
        <input type="hidden" name="action" value="submit_explanation"/>
        <input type="hidden" name="organization_id" value="<?= $orgId ?>"/>
        
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:.85rem;font-weight:600;color:var(--gray-800);margin-bottom:6px">
            Subject <span style="color:var(--red)">*</span>
          </label>
          <input type="text" name="subject" required 
            placeholder="e.g. Explanation for Absence at Community Meeting"
            style="width:100%;padding:10px 12px;border:1.5px solid var(--gray-300);border-radius:8px;font-family:inherit;font-size:.88rem;outline:none;transition:.2s"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--gray-300)'"/>
        </div>
        
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:.85rem;font-weight:600;color:var(--gray-800);margin-bottom:6px">
            Explanation / Content <span style="color:var(--red)">*</span>
          </label>
          <textarea name="content" rows="5" required 
            placeholder="Provide a detailed explanation..."
            style="width:100%;padding:10px 12px;border:1.5px solid var(--gray-300);border-radius:8px;font-family:inherit;font-size:.88rem;outline:none;resize:vertical;transition:.2s"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--gray-300)'"></textarea>
        </div>
        
        <div style="margin-bottom:16px">
          <label style="display:block;font-size:.85rem;font-weight:600;color:var(--gray-800);margin-bottom:6px">
            Attach Document (Optional)
          </label>
          <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
            style="width:100%;padding:10px 12px;border:1.5px solid var(--gray-300);border-radius:8px;font-family:inherit;font-size:.85rem;background:#fff;cursor:pointer"/>
          <small style="display:block;margin-top:4px;font-size:.75rem;color:var(--gray-600)">
            <i class="fas fa-info-circle"></i> Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 5MB)
          </small>
        </div>
        
        <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:11px 20px">
          <i class="fas fa-paper-plane"></i> Submit Explanation Letter
        </button>
      </form>
    </div>
  </div>

</main>
</div>

</body>
</html>
