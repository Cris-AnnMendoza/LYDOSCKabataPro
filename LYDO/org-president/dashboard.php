<?php
session_start();

// Prevent browser caching - force logout to work properly
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once __DIR__ . '/../shared/config.php';

// Check if logged in as organization president
if (empty($_SESSION['org_president_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$president = $_SESSION['org_president'];

// Get organization details
$orgStmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ?');
$orgStmt->execute([$president['organization_id']]);
$organization = $orgStmt->fetch();

// Get statistics
$memberCountStmt = $pdo->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND is_active = 1');
$memberCountStmt->execute([$president['organization_id']]);
$totalMembers = $memberCountStmt->fetchColumn();

$inactiveMembersStmt = $pdo->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND is_active = 0');
$inactiveMembersStmt->execute([$president['organization_id']]);
$inactiveMembers = $inactiveMembersStmt->fetchColumn();

// Get merit and demerit points
$meritStmt = $pdo->prepare("SELECT COALESCE(SUM(points),0) as total FROM org_merit_logs WHERE organization_id=? AND type='merit'");
$meritStmt->execute([$president['organization_id']]);
$totalMerits = (int)$meritStmt->fetchColumn();

$demeritStmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)),0) as total FROM org_merit_logs WHERE organization_id=? AND type='demerit'");
$demeritStmt->execute([$president['organization_id']]);
$totalDemerits = (int)$demeritStmt->fetchColumn();

$netScore = $totalMerits - $totalDemerits;

// Get warning count
$warningCount = 0;
if ($totalDemerits >= 5) {
    $warningStmt = $pdo->prepare('SELECT COUNT(*) FROM org_warning_letters WHERE organization_id=?');
    $warningStmt->execute([$president['organization_id']]);
    $warningCount = (int)$warningStmt->fetchColumn();
}

// Determine warning level
$warningLevel = null;
$warningMessage = '';
if ($totalDemerits >= 10) {
    $warningLevel = 'critical';
    $warningMessage = '⛔ CRITICAL: Your organization has reached the membership revocation threshold!';
} elseif ($totalDemerits >= 7) {
    $warningLevel = 'severe';
    $warningMessage = '🚨 SEVERE WARNING: Show Cause Order issued. Immediate action required!';
} elseif ($totalDemerits >= 5) {
    $warningLevel = 'warning';
    $warningMessage = '⚠️ WARNING: Your organization has received a formal warning letter.';
}

// Get recent members
$recentMembersStmt = $pdo->prepare("
    SELECT om.*, yu.first_name, yu.last_name, yu.email, COALESCE(om.position, om.role) as position
    FROM organization_members om
    JOIN youth_users yu ON om.user_id = yu.id
    WHERE om.organization_id = ?
    ORDER BY om.joined_at DESC
    LIMIT 6
");
$recentMembersStmt->execute([$president['organization_id']]);
$recentMembers = $recentMembersStmt->fetchAll();

// Get pending assistance requests
$pendingRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM assistance_requests WHERE organization_id = ? AND status IN ('submitted','under_evaluation')");
$pendingRequestsStmt->execute([$president['organization_id']]);
$pendingRequests = (int)$pendingRequestsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Dashboard – LYDO President Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="president.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#1565c0;--blue-dark:#0d3b6e;--blue-light:#1e88e5;--blue-pale:#e3f2fd;--green:#2e7d32;--green-light:#43a047;--green-pale:#e8f5e9;--teal:#00796b;--teal-pale:#e0f2f1;--orange:#e65100;--orange-pale:#fff3e0;--red:#c62828;--red-pale:#ffebee;--white:#fff;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-400:#94a3b8;--gray-600:#475569;--gray-800:#1e293b}
body{font-family:Inter,sans-serif;color:var(--gray-800);background:var(--gray-50)}
.topbar{display:none!important}
.content{padding:24px}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px}
.page-header h2{font-size:1.4rem;font-weight:800;color:var(--gray-800);margin-bottom:3px}
.page-header p{font-size:.85rem;color:var(--gray-600)}
.welcome-banner{background:linear-gradient(135deg,var(--blue-dark),var(--blue));border-radius:12px;padding:24px 28px;margin-bottom:20px;box-shadow:0 4px 16px rgba(21,101,192,.2);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.welcome-content{flex:1}
.welcome-title{font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 6px 0;display:flex;align-items:center;gap:0}
.welcome-subtitle{font-size:.9rem;color:rgba(255,255,255,.85);margin:0 0 0 0;display:flex;align-items:center;gap:0;flex-wrap:wrap}
.org-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:6px 12px;border-radius:8px;font-weight:600;color:#fff;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2)}
.date-badge{display:flex;align-items:center;gap:6px;font-size:.82rem;color:var(--gray-600);background:var(--gray-100);padding:6px 12px;border-radius:8px}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid var(--gray-200);transition:.2s;text-decoration:none}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.stat-card.blue .stat-icon{background:var(--blue-pale);color:var(--blue)}
.stat-card.green .stat-icon{background:var(--green-pale);color:var(--green)}
.stat-card.teal .stat-icon{background:var(--teal-pale);color:var(--teal)}
.stat-card.orange .stat-icon{background:var(--orange-pale);color:var(--orange)}
.stat-card.red .stat-icon{background:var(--red-pale);color:var(--red)}
.stat-val{display:block;font-size:1.7rem;font-weight:800;color:var(--gray-800);line-height:1}
.stat-lbl{font-size:.75rem;color:var(--gray-600);font-weight:500;margin-top:3px;display:block}
.alert-banner{background:var(--red-pale);border:2px solid var(--red);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:.88rem;font-weight:600;color:var(--red)}
.alert-banner.warning{background:var(--orange-pale);border-color:var(--orange);color:var(--orange)}
.card{background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.card-header{padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:.88rem;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:7px}
.card-header h3 i{color:var(--blue);font-size:.82rem}
.card-link{font-size:.8rem;color:var(--blue);font-weight:600;text-decoration:none}
.card-link:hover{text-decoration:underline}
.tbl{width:100%;border-collapse:collapse;font-size:.83rem}
.tbl th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-600);background:var(--gray-50);border-bottom:1px solid var(--gray-200);white-space:nowrap}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--gray-100);color:var(--gray-800);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:var(--gray-50)}
.empty{text-align:center;color:var(--gray-400);padding:28px!important;font-style:italic}
.badge{display:inline-block;padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge.green{background:var(--green-pale);color:var(--green)}
.badge.gray{background:var(--gray-100);color:var(--gray-600)}

/* Enhanced Responsive Design */
@media(max-width:1200px){
  .stats-grid{grid-template-columns:repeat(2,1fr)}
}

@media(max-width:900px){
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .welcome-banner{padding:18px;flex-direction:column;align-items:flex-start!important}
  .welcome-content{width:100%;margin-bottom:10px}
  .date-badge{align-self:flex-start}
  
  /* Table improvements */
  .card{overflow-x:auto}
  .tbl{min-width:600px}
}

@media(max-width:768px){
  .stats-grid{grid-template-columns:1fr}
  .welcome-banner{padding:16px}
  .welcome-title{font-size:1.1rem}
  .welcome-subtitle{font-size:.82rem}
  .org-badge{font-size:.75rem;padding:5px 10px}
  
  /* Compact stat cards */
  .stat-card{padding:14px;gap:10px}
  .stat-icon{width:40px;height:40px;font-size:1rem}
  .stat-val{font-size:1.5rem}
  .stat-lbl{font-size:.7rem}
  
  /* Table adjustments */
  .tbl{font-size:.78rem}
  .tbl th,.tbl td{padding:8px 11px}
  .tbl th{font-size:.68rem}
  
  /* Hide less important columns */
  .tbl th:nth-child(5),.tbl td:nth-child(5){display:none}
  
  /* Alert banner */
  .alert-banner{padding:12px 14px;font-size:.82rem}
}

@media(max-width:600px){
  .content{padding:16px}
  .stats-grid{grid-template-columns:1fr}
  .welcome-banner{padding:14px;flex-direction:column}
  .welcome-title{font-size:1rem}
  .welcome-subtitle{font-size:.78rem;flex-direction:column;align-items:flex-start;gap:4px}
  .org-badge{margin-top:4px;width:fit-content}
  .date-badge{font-size:.75rem;padding:5px 10px}
  
  /* Very compact stat cards */
  .stat-card{padding:12px;gap:8px;flex-direction:column;text-align:center}
  .stat-icon{margin:0 auto;width:36px;height:36px}
  .stat-val{font-size:1.3rem}
  .stat-lbl{font-size:.68rem}
  
  /* Hide more columns */
  .tbl th:nth-child(4),.tbl td:nth-child(4){display:none}
  
  /* Card header */
  .card-header{padding:12px 14px;flex-direction:column;align-items:flex-start;gap:8px}
  .card-header h3{font-size:.82rem}
  .card-link{font-size:.75rem}
}

@media(max-width:480px){
  .content{padding:12px}
  .welcome-banner{padding:12px}
  .welcome-title{font-size:.9rem}
  .welcome-subtitle{font-size:.72rem}
  .org-badge{font-size:.7rem;padding:4px 8px}
  .date-badge{font-size:.7rem;padding:4px 8px}
  
  /* Ultra compact stats */
  .stat-card{padding:10px}
  .stat-icon{width:32px;height:32px;font-size:.9rem}
  .stat-val{font-size:1.1rem}
  .stat-lbl{font-size:.62rem}
  
  /* Tables - show only essential */
  .tbl{font-size:.72rem}
  .tbl th,.tbl td{padding:6px 8px}
  .tbl th{font-size:.62rem}
  .tbl th:nth-child(3),.tbl td:nth-child(3){display:none}
  
  /* Hide badge text on very small screens */
  .badge{font-size:.65rem;padding:2px 6px}
  
  /* Alert banner ultra compact */
  .alert-banner{padding:10px 12px;font-size:.78rem;gap:8px}
  .alert-banner i{font-size:1rem}
}

/* Touch-friendly for mobile */
@media(hover:none) and (pointer:coarse){
  .stat-card,.card-link,.btn-icon{min-height:44px}
}

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

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-content">
      <h1 class="welcome-title">
        Welcome back, <?= htmlspecialchars($president['full_name']) ?>!
      </h1>
      <div class="welcome-subtitle">
        <span style="margin-right:10px">Managing</span>
        <span class="org-badge">
          <i class="fas fa-users"></i>
          <?= htmlspecialchars($organization['name']) ?>
        </span>
      </div>
    </div>
    <div class="date-badge" style="background:rgba(255,255,255,.15);color:#fff;backdrop-filter:blur(10px);align-self:flex-start">
      <i class="fas fa-calendar"></i> <?= date('F j, Y') ?>
    </div>
  </div>

  <?php if ($warningLevel): ?>
  <div class="alert-banner <?= $warningLevel === 'warning' ? 'warning' : '' ?>">
    <i class="fas fa-exclamation-triangle" style="font-size:1.2rem"></i>
    <span><?= $warningMessage ?></span>
  </div>
  <?php endif; ?>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <a href="members.php" style="text-decoration:none">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div><span class="stat-val"><?= number_format($totalMembers) ?></span><span class="stat-lbl">Active Members</span></div>
      </div>
    </a>
    <div class="stat-card" style="border:1px solid var(--gray-200)">
      <div class="stat-icon" style="background:var(--gray-100);color:var(--gray-600)"><i class="fas fa-user-slash"></i></div>
      <div><span class="stat-val"><?= number_format($inactiveMembers) ?></span><span class="stat-lbl">Inactive Members</span></div>
    </div>
    <a href="merit.php" style="text-decoration:none">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div><span class="stat-val" style="color:var(--green)"><?= number_format($totalMerits) ?></span><span class="stat-lbl">Merit Points</span></div>
      </div>
    </a>
    <a href="warnings.php" style="text-decoration:none">
      <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-minus-circle"></i></div>
        <div><span class="stat-val" style="color:var(--red)"><?= number_format($totalDemerits) ?></span><span class="stat-lbl">Demerit Points</span></div>
      </div>
    </a>
  </div>

  <!-- SECONDARY STATS -->
  <div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card teal">
      <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
      <div><span class="stat-val" style="color:<?= $netScore >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $netScore >= 0 ? '+' : '' ?><?= number_format($netScore) ?></span><span class="stat-lbl">Net Score</span></div>
    </div>
    <?php if ($warningCount > 0): ?>
    <a href="warnings.php" style="text-decoration:none">
      <div class="stat-card" style="border:2px solid var(--orange);cursor:pointer">
        <div class="stat-icon" style="background:var(--orange-pale);color:var(--orange)"><i class="fas fa-exclamation-triangle"></i></div>
        <div><span class="stat-val" style="color:var(--orange)"><?= $warningCount ?></span><span class="stat-lbl">Warning Letters</span></div>
      </div>
    </a>
    <?php endif; ?>
    <?php if ($pendingRequests > 0): ?>
    <a href="assistance.php" style="text-decoration:none">
      <div class="stat-card" style="border:2px solid var(--orange);cursor:pointer">
        <div class="stat-icon" style="background:var(--orange-pale);color:var(--orange)"><i class="fas fa-clock"></i></div>
        <div><span class="stat-val" style="color:var(--orange)"><?= $pendingRequests ?></span><span class="stat-lbl">Pending Requests</span></div>
      </div>
    </a>
    <?php endif; ?>
  </div>

  <!-- RECENT MEMBERS -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-users"></i> Recent Members</h3>
      <a href="members.php" class="card-link">View All →</a>
    </div>
    <table class="tbl">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Position</th><th>Status</th><th>Joined</th></tr></thead>
      <tbody>
      <?php if (empty($recentMembers)): ?>
        <tr><td colspan="6" class="empty">No members yet.</td></tr>
      <?php else: foreach ($recentMembers as $i => $m): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></strong></td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars($m['position'] ?: 'Member') ?></td>
          <td>
            <?php if ($m['is_active']): ?>
              <span class="badge green">Active</span>
            <?php else: ?>
              <span class="badge gray">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</main>
</div>

<!-- Wellbeing Assistant Bubble -->
<div id="wellbeingBubble" style="position:fixed;bottom:24px;right:24px;width:64px;height:64px;background:linear-gradient(135deg,#1565c0,#1e88e5);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(21,101,192,.4);cursor:pointer;transition:.3s;z-index:1000" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" onclick="toggleWellbeingChat()">
  <i class="fas fa-brain" style="font-size:1.5rem;color:#fff"></i>
</div>

<!-- Wellbeing Chat Modal -->
<div id="wellbeingModal" style="display:none;position:fixed;bottom:100px;right:24px;width:400px;max-width:calc(100vw - 48px);height:600px;max-height:calc(100vh - 150px);background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);z-index:999;overflow:hidden;display:flex;flex-direction:column">
  <!-- Header -->
  <div style="background:linear-gradient(135deg,#1565c0,#1e88e5);padding:16px 20px;display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <i class="fas fa-brain" style="font-size:1.2rem;color:#fff"></i>
      <div>
        <div style="font-size:.95rem;font-weight:700;color:#fff">Wellbeing Assistant</div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.8)">AI-powered mental health support</div>
      </div>
    </div>
    <button onclick="toggleWellbeingChat()" style="background:transparent;border:none;color:#fff;font-size:1.2rem;cursor:pointer;padding:4px;width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;transition:.2s" onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='transparent'">
      <i class="fas fa-times"></i>
    </button>
  </div>
  
  <!-- Chat iframe -->
  <iframe src="wellbeing_popup.php" style="flex:1;border:none;width:100%;height:100%"></iframe>
</div>

<style>
@media(max-width:768px){
  #wellbeingBubble{width:56px;height:56px;bottom:20px;right:20px}
  #wellbeingBubble i{font-size:1.3rem}
  #wellbeingModal{width:calc(100vw - 40px);height:calc(100vh - 140px);bottom:90px;right:20px;max-width:none}
}
@media(max-width:480px){
  #wellbeingBubble{width:52px;height:52px;bottom:16px;right:16px}
  #wellbeingBubble i{font-size:1.2rem}
  #wellbeingModal{width:calc(100vw - 32px);height:calc(100vh - 120px);bottom:80px;right:16px}
  #wellbeingModal>div:first-child{padding:12px 16px}
  #wellbeingModal>div:first-child>div:first-child{gap:8px}
  #wellbeingModal>div:first-child>div:first-child i{font-size:1rem}
  #wellbeingModal>div:first-child>div:first-child>div>div:first-child{font-size:.85rem}
  #wellbeingModal>div:first-child>div:first-child>div>div:last-child{font-size:.68rem}
}
</style>

<script>
function toggleWellbeingChat() {
  const modal = document.getElementById('wellbeingModal');
  if (modal.style.display === 'none' || !modal.style.display) {
    modal.style.display = 'flex';
  } else {
    modal.style.display = 'none';
  }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
  const modal = document.getElementById('wellbeingModal');
  const bubble = document.getElementById('wellbeingBubble');
  if (modal.style.display === 'flex' && !modal.contains(e.target) && !bubble.contains(e.target)) {
    modal.style.display = 'none';
  }
});

// Prevent back button after logout
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
    window.location.reload();
  }
});
</script>

</body>
</html>
