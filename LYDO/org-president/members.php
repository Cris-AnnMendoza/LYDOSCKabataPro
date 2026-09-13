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

// Get organization details
$orgStmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ?');
$orgStmt->execute([$president['organization_id']]);
$organization = $orgStmt->fetch();

// Handle member status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        $memberId = (int)$_POST['member_id'];
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus ? 0 : 1;
        
        $updateStmt = $pdo->prepare('UPDATE organization_members SET is_active = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?');
        $updateStmt->execute([$newStatus, $memberId, $president['organization_id']]);
        
        $_SESSION['flash_success'] = $newStatus ? 'Member activated successfully!' : 'Member deactivated successfully!';
        header('Location: members.php');
        exit;
    }
    
    if ($_POST['action'] === 'remove_member') {
        $memberId = (int)$_POST['member_id'];
        
        $deleteStmt = $pdo->prepare('DELETE FROM organization_members WHERE id = ? AND organization_id = ?');
        $deleteStmt->execute([$memberId, $president['organization_id']]);
        
        $_SESSION['flash_success'] = 'Member removed successfully!';
        header('Location: members.php');
        exit;
    }
    
    if ($_POST['action'] === 'add_member') {
        $userId = (int)$_POST['user_id'];
        $position = trim($_POST['position'] ?? 'Member');
        
        // Check if user is already a member
        $checkStmt = $pdo->prepare('SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ?');
        $checkStmt->execute([$president['organization_id'], $userId]);
        
        if ($checkStmt->fetch()) {
            $_SESSION['flash_error'] = 'This user is already a member of your organization!';
            header('Location: members.php');
            exit;
        }
        
        // Add member
        $insertStmt = $pdo->prepare('INSERT INTO organization_members (organization_id, user_id, role, position, is_active, joined_at) VALUES (?, ?, ?, ?, 1, NOW())');
        $insertStmt->execute([$president['organization_id'], $userId, $position, $position]);
        
        $_SESSION['flash_success'] = 'Member added successfully!';
        header('Location: members.php');
        exit;
    }
}

// Get search/filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$positionFilter = trim($_GET['position'] ?? '');

// Build query
$where = ['om.organization_id = ?'];
$params = [$president['organization_id']];

if ($search) {
    $where[] = "(yu.first_name LIKE ? OR yu.last_name LIKE ? OR yu.email LIKE ? OR om.position LIKE ? OR om.role LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statusFilter === 'active') {
    $where[] = 'om.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'om.is_active = 0';
}

if ($positionFilter) {
    $where[] = '(om.position = ? OR om.role = ?)';
    $params[] = $positionFilter;
    $params[] = $positionFilter;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Get members
$membersStmt = $pdo->prepare("
    SELECT 
        om.id as member_id,
        COALESCE(om.position, om.role) as position,
        om.is_active,
        om.joined_at,
        yu.id as user_id,
        yu.first_name,
        yu.last_name,
        yu.email,
        yu.contact_number,
        yu.barangay
    FROM organization_members om
    JOIN youth_users yu ON om.user_id = yu.id
    {$whereSQL}
    ORDER BY om.is_active DESC, COALESCE(om.position, om.role), yu.last_name, yu.first_name
");
$membersStmt->execute($params);
$members = $membersStmt->fetchAll();

// Get statistics
$totalMembers = count($members);
$activeMembers = count(array_filter($members, fn($m) => $m['is_active']));
$inactiveMembers = $totalMembers - $activeMembers;

// Get unique positions
$positionsStmt = $pdo->prepare('SELECT DISTINCT COALESCE(position, role) as pos FROM organization_members WHERE organization_id = ? AND COALESCE(position, role) IS NOT NULL ORDER BY pos');
$positionsStmt->execute([$president['organization_id']]);
$positions = $positionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get all approved youth users not in the organization (for adding)
$availableUsersStmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, barangay
    FROM youth_users
    WHERE status = 'approved'
    AND id NOT IN (SELECT user_id FROM organization_members WHERE organization_id = ?)
    ORDER BY last_name, first_name
    LIMIT 100
");
$availableUsersStmt->execute([$president['organization_id']]);
$availableUsers = $availableUsersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Members – LYDO President Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="president.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#1565c0;--blue-dark:#0d3b6e;--blue-light:#1e88e5;--blue-pale:#e3f2fd;--green:#2e7d32;--green-light:#43a047;--green-pale:#e8f5e9;--teal:#00796b;--teal-pale:#e0f2f1;--orange:#e65100;--orange-pale:#fff3e0;--red:#c62828;--red-pale:#ffebee;--white:#fff;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-400:#94a3b8;--gray-600:#475569;--gray-800:#1e293b}
body{font-family:Inter,sans-serif;color:var(--gray-800);background:var(--gray-50)}
.topbar{display:none!important}
.content{padding:24px}
.welcome-banner{background:linear-gradient(135deg,var(--blue-dark),var(--blue));border-radius:12px;padding:24px 28px;margin-bottom:20px;box-shadow:0 4px 16px rgba(21,101,192,.2);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.welcome-content{flex:1}
.welcome-title{font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 6px 0}
.welcome-subtitle{font-size:.9rem;color:rgba(255,255,255,.85);margin:0;display:flex;align-items:center;gap:0;flex-wrap:wrap}
.org-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:6px 12px;border-radius:8px;font-weight:600;color:#fff;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2)}
.date-badge{display:flex;align-items:center;gap:6px;font-size:.82rem;padding:6px 12px;border-radius:8px}
.flash{padding:11px 16px;border-radius:10px;font-size:.88rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.flash.success{background:var(--green-pale);color:var(--green);border:1px solid rgba(46,125,50,.2)}
.flash.error{background:var(--red-pale);color:var(--red);border:1px solid rgba(198,40,40,.2)}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid var(--gray-200);transition:.2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.stat-card.blue .stat-icon{background:var(--blue-pale);color:var(--blue)}
.stat-card.green .stat-icon{background:var(--green-pale);color:var(--green)}
.stat-card .stat-icon{background:var(--gray-100);color:var(--gray-600)}
.stat-val{display:block;font-size:1.7rem;font-weight:800;color:var(--gray-800);line-height:1}
.stat-lbl{font-size:.75rem;color:var(--gray-600);font-weight:500;margin-top:3px;display:block}
.toolbar{margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:200px}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:.82rem}
.search-wrap input{width:100%;padding:9px 13px 9px 34px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;outline:none;background:var(--gray-50);transition:.2s}
.search-wrap input:focus{border-color:var(--blue-light);background:#fff}
.filter-sel{padding:9px 13px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;outline:none;background:var(--gray-50);color:var(--gray-800);cursor:pointer}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--blue);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s}
.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px)}
.card{background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.card-header{padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:.88rem;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:7px}
.card-header h3 i{color:var(--blue);font-size:.82rem}
.tbl{width:100%;border-collapse:collapse;font-size:.83rem}
.tbl th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-600);background:var(--gray-50);border-bottom:1px solid var(--gray-200);white-space:nowrap}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--gray-100);color:var(--gray-800);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:var(--gray-50)}
.empty{text-align:center;color:var(--gray-400);padding:28px!important;font-style:italic}
.badge{display:inline-block;padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge.green{background:var(--green-pale);color:var(--green)}
.badge.gray{background:var(--gray-100);color:var(--gray-600)}
.btn-icon{background:none;border:none;cursor:pointer;padding:5px 7px;border-radius:6px;font-size:.82rem;transition:.2s}
.btn-icon.teal{color:var(--teal)}.btn-icon.teal:hover{background:var(--teal-pale)}
.btn-icon.red{color:var(--red)}.btn-icon.red:hover{background:var(--red-pale)}
.btn-icon.orange{color:var(--orange)}.btn-icon.orange:hover{background:var(--orange-pale)}
.modal-overlay{position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.55);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;padding:20px}
.modal-box{background:#fff;border-radius:16px;width:100%;max-width:540px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.2);overflow:hidden}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,var(--blue-dark),var(--blue));color:#fff;flex-shrink:0}
.modal-head h3{font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:.82rem;display:flex;align-items:center;justify-content:center;transition:.2s}
.modal-close:hover{background:rgba(255,255,255,.3)}
.modal-body{padding:20px;overflow-y:auto;flex:1}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fg label{font-size:.82rem;font-weight:600;color:var(--gray-800)}
.fg input,.fg select{padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.88rem;color:var(--gray-800);background:var(--gray-50);outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus{border-color:var(--blue-light);box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.req{color:var(--red)}
.modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);flex-shrink:0}
.btn-secondary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s}
.btn-secondary:hover{background:var(--gray-200)}

/* Enhanced Responsive Design */
@media(max-width:1100px){
  .stats-grid{grid-template-columns:repeat(2,1fr)}
}

@media(max-width:900px){
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .toolbar{flex-direction:column;align-items:stretch}
  .search-wrap{min-width:100%;width:100%}
  .filter-sel{width:100%}
  .welcome-banner{padding:18px;flex-direction:column;align-items:flex-start!important}
  .welcome-content{width:100%}
}

@media(max-width:768px){
  .stats-grid{grid-template-columns:1fr}
  .welcome-banner{padding:16px}
  .welcome-title{font-size:1.1rem}
  .welcome-subtitle{font-size:.82rem;flex-direction:column;align-items:flex-start;gap:6px}
  .org-badge{margin-top:4px}
  
  /* Toolbar */
  .toolbar{gap:8px}
  .search-wrap input,.filter-sel{font-size:.82rem;padding:8px 12px}
  .btn-primary{width:100%;justify-content:center;font-size:.82rem}
  
  /* Stat cards */
  .stat-card{padding:14px;gap:10px}
  .stat-icon{width:40px;height:40px;font-size:1rem}
  .stat-val{font-size:1.5rem}
  .stat-lbl{font-size:.7rem}
  
  /* Table wrapper */
  .card{overflow-x:auto}
  .tbl{min-width:800px;font-size:.78rem}
  .tbl th,.tbl td{padding:8px 11px}
  .tbl th{font-size:.68rem}
}

@media(max-width:600px){
  .stats-grid{grid-template-columns:1fr}
  .welcome-banner{padding:14px;flex-direction:column}
  .welcome-title{font-size:1rem}
  .welcome-subtitle{font-size:.78rem}
  
  /* Very compact stat cards */
  .stat-card{padding:12px;gap:8px;flex-direction:column;text-align:center}
  .stat-icon{margin:0 auto;width:36px;height:36px}
  .stat-val{font-size:1.3rem}
  .stat-lbl{font-size:.68rem}
  
  /* Flash messages */
  .flash{padding:10px 14px;font-size:.82rem}
  
  /* Toolbar very compact */
  .search-wrap input,.filter-sel{font-size:.8rem;padding:7px 10px}
  .search-wrap i{font-size:.75rem;left:10px}
  .search-wrap input{padding-left:30px}
  .btn-primary{font-size:.8rem;padding:8px 14px}
  
  /* Table - reduce minimum width */
  .tbl{min-width:600px;font-size:.75rem}
  .tbl th,.tbl td{padding:7px 9px}
  .tbl th{font-size:.65rem}
  
  /* Hide some columns */
  .tbl th:nth-child(4),.tbl td:nth-child(4),
  .tbl th:nth-child(5),.tbl td:nth-child(5){display:none}
  
  /* Modal adjustments */
  .modal-box{max-width:95vw;margin:10px}
  .modal-head{padding:12px 16px;font-size:.88rem}
  .modal-body{padding:16px}
  .fg label{font-size:.78rem}
  .fg input,.fg select{font-size:.85rem;padding:8px 10px}
  .btn-primary,.btn-secondary{font-size:.8rem;padding:8px 14px}
}

@media(max-width:480px){
  .stats-grid{grid-template-columns:1fr}
  .welcome-banner{padding:12px}
  .welcome-title{font-size:.9rem}
  .welcome-subtitle{font-size:.72rem}
  .org-badge{font-size:.7rem;padding:4px 8px}
  
  /* Ultra compact stats */
  .stat-card{padding:10px}
  .stat-icon{width:32px;height:32px;font-size:.9rem}
  .stat-val{font-size:1.1rem}
  .stat-lbl{font-size:.62rem}
  
  /* Toolbar ultra compact */
  .toolbar{gap:6px}
  .search-wrap input,.filter-sel{font-size:.75rem;padding:6px 9px}
  .search-wrap input{padding-left:28px}
  .search-wrap i{left:9px;font-size:.7rem}
  .btn-primary{font-size:.75rem;padding:7px 12px}
  
  /* Table ultra compact */
  .tbl{min-width:500px;font-size:.7rem}
  .tbl th,.tbl td{padding:5px 7px}
  .tbl th{font-size:.6rem}
  
  /* Hide more columns */
  .tbl th:nth-child(3),.tbl td:nth-child(3){display:none}
  
  /* Button icons smaller */
  .btn-icon{padding:4px 6px;font-size:.75rem}
  .badge{font-size:.62rem;padding:2px 6px}
  
  /* Flash ultra compact */
  .flash{padding:8px 12px;font-size:.78rem}
  
  /* Modal ultra compact */
  .modal-box{max-width:98vw;margin:5px}
  .modal-head{padding:10px 14px;font-size:.82rem}
  .modal-body{padding:14px}
  .fg{gap:4px;margin-bottom:12px}
  .fg label{font-size:.75rem}
  .fg input,.fg select{font-size:.8rem;padding:7px 9px}
  .modal-footer{gap:8px;padding-top:12px}
  .btn-primary,.btn-secondary{font-size:.75rem;padding:7px 12px}
}

/* Touch-friendly */
@media(hover:none) and (pointer:coarse){
  .stat-card,.btn-icon,.btn-primary,.filter-sel{min-height:44px}
}

@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}.toolbar{flex-direction:column;align-items:stretch}.search-wrap{min-width:100%}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr}.page-header{flex-direction:column;align-items:flex-start}.page-header .btn-primary{width:100%;justify-content:center}}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php 
  if (isset($_SESSION['flash_success'])): 
    $msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
  ?>
    <div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  
  <?php 
  if (isset($_SESSION['flash_error'])): 
    $msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
  ?>
    <div class="flash error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-content">
      <h1 class="welcome-title">Members</h1>
      <div class="welcome-subtitle" style="flex-direction:column;align-items:flex-start;gap:6px">
        <span>Manage</span>
        <span class="org-badge">
          <i class="fas fa-users"></i>
          <?= htmlspecialchars($organization['name']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Action Button -->
  <div style="display:flex;justify-content:flex-end;margin-bottom:20px">
    <button class="btn-primary" onclick="document.getElementById('addMemberModal').style.display='flex'">
      <i class="fas fa-plus"></i> Add Member
    </button>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div><span class="stat-val"><?= $totalMembers ?></span><span class="stat-lbl">Total Members</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div><span class="stat-val"><?= $activeMembers ?></span><span class="stat-lbl">Active</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
      <div><span class="stat-val"><?= $inactiveMembers ?></span><span class="stat-lbl">Inactive</span></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="searchInput" placeholder="Search members..." value="<?= htmlspecialchars($search) ?>" onkeyup="applyFilters()">
    </div>
    <select class="filter-sel" id="statusFilter" onchange="applyFilters()">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <select class="filter-sel" id="positionFilter" onchange="applyFilters()">
      <option value="">All Positions</option>
      <?php foreach ($positions as $pos): ?>
        <option value="<?= htmlspecialchars($pos) ?>" <?= $positionFilter === $pos ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-list"></i> Member List (<?= count($members) ?>)</h3>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Contact</th>
          <th>Barangay</th>
          <th>Position</th>
          <th>Status</th>
          <th>Joined</th>
          <th style="text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($members)): ?>
        <tr><td colspan="9" class="empty">No members found.</td></tr>
      <?php else: foreach ($members as $i => $m): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></strong></td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars($m['contact_number'] ?: '—') ?></td>
          <td><?= htmlspecialchars($m['barangay'] ?: '—') ?></td>
          <td><?= htmlspecialchars($m['position'] ?: 'Member') ?></td>
          <td>
            <?php if ($m['is_active']): ?>
              <span class="badge green">Active</span>
            <?php else: ?>
              <span class="badge gray">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
          <td style="text-align:center">
            <form method="POST" style="display:inline" onsubmit="return confirm('<?= $m['is_active'] ? 'Deactivate' : 'Activate' ?> this member?')">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
              <input type="hidden" name="current_status" value="<?= $m['is_active'] ?>">
              <button type="submit" class="btn-icon <?= $m['is_active'] ? 'orange' : 'teal' ?>" title="<?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>">
                <i class="fas fa-<?= $m['is_active'] ? 'pause' : 'play' ?>"></i>
              </button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this member permanently?')">
              <input type="hidden" name="action" value="remove_member">
              <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
              <button type="submit" class="btn-icon red" title="Remove">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</main>
</div>

<!-- Add Member Modal -->
<div id="addMemberModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-user-plus"></i> Add New Member</h3>
      <button class="modal-close" onclick="document.getElementById('addMemberModal').style.display='none'">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_member">
        <div class="fg">
          <label>Select Youth User <span class="req">*</span></label>
          <select name="user_id" required>
            <option value="">-- Select User --</option>
            <?php foreach ($availableUsers as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'].' '.$u['last_name'].' ('.$u['email'].')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Position</label>
          <input type="text" name="position" placeholder="e.g. Member, Officer, Secretary" value="Member">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="document.getElementById('addMemberModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add Member</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function applyFilters() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('statusFilter').value;
  const position = document.getElementById('positionFilter').value;
  const url = new URL(window.location.href);
  url.searchParams.set('search', search);
  url.searchParams.set('status', status);
  url.searchParams.set('position', position);
  window.location.href = url.toString();
}
</script>
</body>
</html>
