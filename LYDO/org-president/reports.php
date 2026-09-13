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

// Get date range filters
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get section filters - only show if explicitly checked OR if no filter has been applied yet
$filterApplied = isset($_GET['filter_applied']);
$showMembers = isset($_GET['show_members']) && $_GET['show_members'] === '1';
$showEvents = isset($_GET['show_events']) && $_GET['show_events'] === '1';
$showMerit = isset($_GET['show_merit']) && $_GET['show_merit'] === '1';
$showDemerit = isset($_GET['show_demerit']) && $_GET['show_demerit'] === '1';

// If no filter applied yet, show all sections by default
if (!$filterApplied) {
    $showMembers = $showEvents = $showMerit = $showDemerit = true;
}

// Members Statistics
$totalMembersStmt = $pdo->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ?');
$totalMembersStmt->execute([$president['organization_id']]);
$totalMembers = (int)$totalMembersStmt->fetchColumn();

$activeMembersStmt = $pdo->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND is_active = 1');
$activeMembersStmt->execute([$president['organization_id']]);
$activeMembers = (int)$activeMembersStmt->fetchColumn();

// Events Statistics - from events table
$totalEventsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM events 
    WHERE organization_id = ? 
    AND event_date BETWEEN ? AND ?
");
$totalEventsStmt->execute([$president['organization_id'], $startDate, $endDate]);
$totalEvents = (int)$totalEventsStmt->fetchColumn();

$completedEventsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM events 
    WHERE organization_id = ? 
    AND event_date < NOW()
    AND event_date BETWEEN ? AND ?
");
$completedEventsStmt->execute([$president['organization_id'], $startDate, $endDate]);
$completedEvents = (int)$completedEventsStmt->fetchColumn();

// Merit/Demerit Statistics
$meritStmt = $pdo->prepare("
    SELECT COALESCE(SUM(points), 0) as total 
    FROM org_merit_logs 
    WHERE organization_id = ? 
    AND type = 'merit'
    AND created_at BETWEEN ? AND ?
");
$meritStmt->execute([$president['organization_id'], $startDate, $endDate]);
$totalMerits = (int)$meritStmt->fetchColumn();

$demeritStmt = $pdo->prepare("
    SELECT COALESCE(SUM(ABS(points)), 0) as total 
    FROM org_merit_logs 
    WHERE organization_id = ? 
    AND type = 'demerit'
    AND created_at BETWEEN ? AND ?
");
$demeritStmt->execute([$president['organization_id'], $startDate, $endDate]);
$totalDemerits = (int)$demeritStmt->fetchColumn();

// Assistance Requests Summary
$assistanceStatsStmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM assistance_requests
    WHERE organization_id = ?
    AND created_at BETWEEN ? AND ?
    GROUP BY status
");
$assistanceStatsStmt->execute([$president['organization_id'], $startDate, $endDate]);
$assistanceStats = $assistanceStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent Activities
$recentActivitiesStmt = $pdo->prepare("
    SELECT * FROM assistance_requests
    WHERE organization_id = ?
    AND created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 10
");
$recentActivitiesStmt->execute([$president['organization_id'], $startDate, $endDate]);
$recentActivities = $recentActivitiesStmt->fetchAll();

// Member Position Distribution
$positionDistStmt = $pdo->prepare("
    SELECT 
        COALESCE(position, role) as position,
        COUNT(*) as count
    FROM organization_members
    WHERE organization_id = ?
    AND COALESCE(position, role) IS NOT NULL
    GROUP BY COALESCE(position, role)
    ORDER BY count DESC
");
$positionDistStmt->execute([$president['organization_id']]);
$positionDist = $positionDistStmt->fetchAll();

// Get detailed members list
$membersListStmt = $pdo->prepare("
    SELECT 
        om.*,
        yu.first_name,
        yu.last_name,
        yu.email,
        yu.contact_number,
        yu.barangay,
        COALESCE(om.position, om.role) as position
    FROM organization_members om
    JOIN youth_users yu ON om.user_id = yu.id
    WHERE om.organization_id = ?
    ORDER BY om.is_active DESC, yu.last_name, yu.first_name
    LIMIT 50
");
$membersListStmt->execute([$president['organization_id']]);
$membersList = $membersListStmt->fetchAll();

// Get detailed events from events table
$eventsListStmt = $pdo->prepare("
    SELECT 
        e.*,
        (SELECT COUNT(*) FROM event_checkins ec WHERE ec.event_id = e.id) as total_checkins,
        a.full_name as created_by_name
    FROM events e
    LEFT JOIN admin_users a ON a.id = e.created_by
    WHERE e.organization_id = ?
    AND e.event_date BETWEEN ? AND ?
    ORDER BY e.event_date DESC
    LIMIT 50
");
$eventsListStmt->execute([$president['organization_id'], $startDate, $endDate]);
$eventsList = $eventsListStmt->fetchAll();

// Get merit logs with reasons
$meritLogsStmt = $pdo->prepare("
    SELECT 
        l.*,
        e.title as event_title,
        a.full_name as admin_name
    FROM org_merit_logs l
    LEFT JOIN events e ON e.id = l.event_id
    LEFT JOIN admin_users a ON a.id = l.awarded_by
    WHERE l.organization_id = ?
    AND l.type = 'merit'
    AND l.created_at BETWEEN ? AND ?
    ORDER BY l.created_at DESC
    LIMIT 20
");
$meritLogsStmt->execute([$president['organization_id'], $startDate, $endDate]);
$meritLogs = $meritLogsStmt->fetchAll();

// Get demerit logs with reasons
$demeritLogsStmt = $pdo->prepare("
    SELECT 
        l.*,
        e.title as event_title,
        a.full_name as admin_name
    FROM org_merit_logs l
    LEFT JOIN events e ON e.id = l.event_id
    LEFT JOIN admin_users a ON a.id = l.awarded_by
    WHERE l.organization_id = ?
    AND l.type = 'demerit'
    AND l.created_at BETWEEN ? AND ?
    ORDER BY l.created_at DESC
    LIMIT 20
");
$demeritLogsStmt->execute([$president['organization_id'], $startDate, $endDate]);
$demeritLogs = $demeritLogsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Reports – LYDO President Portal</title>
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
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--blue);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s}
.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px)}
.toolbar{background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:16px 18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.fg{display:flex;flex-direction:column;gap:5px}
.fg label{font-size:.82rem;font-weight:600;color:var(--gray-800)}
.fg input{padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.88rem;color:var(--gray-800);background:var(--gray-50);outline:none;transition:.2s;width:100%}
.fg input:focus{border-color:var(--blue-light);box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid var(--gray-200);transition:.2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.stat-card.blue .stat-icon{background:var(--blue-pale);color:var(--blue)}
.stat-card.green .stat-icon{background:var(--green-pale);color:var(--green)}
.stat-card.orange .stat-icon{background:var(--orange-pale);color:var(--orange)}
.stat-card.purple .stat-icon{background:var(--purple-pale);color:var(--purple)}
.stat-card.red .stat-icon{background:var(--red-pale);color:var(--red)}
.stat-val{display:block;font-size:1.7rem;font-weight:800;color:var(--gray-800);line-height:1}
.stat-lbl{font-size:.75rem;color:var(--gray-600);font-weight:500;margin-top:3px;display:block}
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.card{background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.card-header{padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:.88rem;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:7px}
.card-header h3 i{color:var(--blue);font-size:.82rem}
.data-row{display:flex;justify-content:space-between;align-items:center;padding:9px 18px;border-bottom:1px solid var(--gray-100)}
.data-row:last-child{border-bottom:none}
.data-label{font-size:.82rem;color:var(--gray-700);font-weight:500}
.data-val{font-size:1.05rem;font-weight:700;color:var(--gray-800)}
.tbl{width:100%;border-collapse:collapse;font-size:.83rem}
.tbl th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-600);background:var(--gray-50);border-bottom:1px solid var(--gray-200);white-space:nowrap}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--gray-100);color:var(--gray-800);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:var(--gray-50)}
.empty{text-align:center;color:var(--gray-400);padding:28px!important;font-style:italic}
.badge{display:inline-block;padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge.green{background:var(--green-pale);color:var(--green)}
.badge.blue{background:var(--blue-pale);color:var(--blue)}
.badge.gray{background:var(--gray-100);color:var(--gray-600)}
.print-header{display:none;text-align:center;margin-bottom:32px;padding-bottom:24px;border-bottom:2px solid var(--gray-200)}
.print-header h1{font-size:1.75rem;font-weight:800;color:var(--gray-800);margin:0 0 8px 0}
.print-header p{font-size:.95rem;color:var(--gray-600);margin:0}
.print-footer{display:none;margin-top:48px;padding-top:24px;border-top:2px solid var(--gray-200);text-align:center}
.print-footer p{font-size:.82rem;color:var(--gray-600);margin:0}
@media print{
  .no-print{display:none!important}
  .print-header,.print-footer{display:block!important}
  body{background:#fff}
  .card{page-break-inside:avoid}
  .stat-card{box-shadow:none}
}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.dash-grid{grid-template-columns:1fr}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr}.page-header{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <!-- Welcome Banner -->
  <div class="welcome-banner no-print">
    <div class="welcome-content">
      <h1 class="welcome-title">Reports</h1>
      <div class="welcome-subtitle">
        <span style="margin-right:10px">View statistics and reports for</span>
        <span class="org-badge">
          <i class="fas fa-chart-bar"></i>
          <?= htmlspecialchars($organization['name']) ?>
        </span>
      </div>
    </div>
    <button onclick="window.print()" class="btn-primary" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(10px)">
      <i class="fas fa-print"></i> Print Report
    </button>
  </div>

  <!-- Date Range Filter -->
  <div class="toolbar no-print">
    <form method="GET" id="filterForm">
      <input type="hidden" name="filter_applied" value="1">
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
        <div class="fg">
          <label>Start Date</label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="fg">
          <label>End Date</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div style="position:relative">
          <button type="button" class="btn-primary" onclick="toggleFilterDropdown()" style="height:42px">
            <i class="fas fa-filter"></i> Filter Sections
            <i class="fas fa-chevron-down" style="font-size:.7rem;margin-left:2px"></i>
          </button>
          <div id="filterDropdown" style="display:none;position:absolute;top:48px;right:0;background:#fff;border:1px solid var(--gray-200);border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.15);padding:14px 16px;min-width:220px;z-index:100">
            <div style="font-size:.8rem;font-weight:700;color:var(--gray-700);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-200)">
              <i class="fas fa-eye"></i> Show Sections
            </div>
            <label style="display:flex;align-items:center;gap:8px;padding:7px 4px;font-size:.85rem;color:var(--gray-700);cursor:pointer;border-radius:6px;transition:.15s">
              <input type="checkbox" name="show_members" value="1" <?= $showMembers ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer">
              <i class="fas fa-users" style="color:var(--blue);font-size:.8rem;width:16px"></i>
              <span>Members List</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:7px 4px;font-size:.85rem;color:var(--gray-700);cursor:pointer;border-radius:6px;transition:.15s">
              <input type="checkbox" name="show_events" value="1" <?= $showEvents ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer">
              <i class="fas fa-calendar-alt" style="color:var(--purple);font-size:.8rem;width:16px"></i>
              <span>Events Details</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:7px 4px;font-size:.85rem;color:var(--gray-700);cursor:pointer;border-radius:6px;transition:.15s">
              <input type="checkbox" name="show_merit" value="1" <?= $showMerit ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer">
              <i class="fas fa-star" style="color:var(--green);font-size:.8rem;width:16px"></i>
              <span>Merit Points</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:7px 4px;font-size:.85rem;color:var(--gray-700);cursor:pointer;border-radius:6px;transition:.15s">
              <input type="checkbox" name="show_demerit" value="1" <?= $showDemerit ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer">
              <i class="fas fa-exclamation-triangle" style="color:var(--red);font-size:.8rem;width:16px"></i>
              <span>Demerit Points</span>
            </label>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--gray-200)">
              <button type="submit" class="btn-primary" style="width:100%;justify-content:center;font-size:.82rem;padding:8px">
                <i class="fas fa-check"></i> Apply Filter
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <script>
  function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  }
  
  // Close dropdown when clicking outside
  document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('filterDropdown');
    const button = e.target.closest('button');
    if (dropdown && !dropdown.contains(e.target) && (!button || !button.textContent.includes('Filter Sections'))) {
      dropdown.style.display = 'none';
    }
  });
  
  // Hover effect for labels
  document.querySelectorAll('#filterDropdown label').forEach(label => {
    label.addEventListener('mouseenter', function() {
      this.style.background = 'var(--gray-50)';
    });
    label.addEventListener('mouseleave', function() {
      this.style.background = 'transparent';
    });
  });
  </script>

  <!-- Print Header -->
  <div class="print-header">
    <h1><?= htmlspecialchars($organization['name']) ?></h1>
    <p>Organization Report - <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?></p>
  </div>

  <!-- Statistics Overview -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div><span class="stat-val"><?= $totalMembers ?></span><span class="stat-lbl">Total Members</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      <div><span class="stat-val"><?= $activeMembers ?></span><span class="stat-lbl">Active Members</span></div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon"><i class="fas fa-calendar"></i></div>
      <div><span class="stat-val"><?= $totalEvents ?></span><span class="stat-lbl">Total Events</span></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div><span class="stat-val"><?= $completedEvents ?></span><span class="stat-lbl">Completed Events</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-trophy"></i></div>
      <div><span class="stat-val" style="color:var(--green)">+<?= $totalMerits ?></span><span class="stat-lbl">Merit Points</span></div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
      <div><span class="stat-val" style="color:var(--red)">-<?= $totalDemerits ?></span><span class="stat-lbl">Demerit Points</span></div>
    </div>
  </div>

  <!-- Members Information -->
  <?php if ($showMembers): ?>
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-users"></i> Members List (<?= count($membersList) ?>)</h3>
    </div>
    <?php if (empty($membersList)): ?>
      <div class="empty">No members found</div>
    <?php else: ?>
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
          </tr>
        </thead>
        <tbody>
          <?php foreach ($membersList as $i => $m): ?>
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
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Events Information -->
  <?php if ($showEvents && !empty($eventsList)): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-header">
      <h3><i class="fas fa-calendar-alt"></i> Events Details (<?= count($eventsList) ?>)</h3>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Event Title</th>
          <th>Event Type</th>
          <th>Event Date</th>
          <th>Check-ins</th>
          <th>Merit Points</th>
          <th>Check-in Code</th>
          <th>Created By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($eventsList as $i => $ev): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
          <td><?= htmlspecialchars($ev['event_type'] ?: '—') ?></td>
          <td>
            <?php 
            $eventDate = strtotime($ev['event_date']);
            $isCompleted = $eventDate < time();
            ?>
            <?= date('M j, Y', $eventDate) ?>
            <?php if ($isCompleted): ?>
              <span class="badge green" style="margin-left:6px;font-size:.65rem">Completed</span>
            <?php else: ?>
              <span class="badge blue" style="margin-left:6px;font-size:.65rem">Upcoming</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <strong style="color:var(--blue)"><?= number_format($ev['total_checkins']) ?></strong>
          </td>
          <td style="text-align:center">
            <span class="badge green">+<?= $ev['merit_points'] ?></span>
          </td>
          <td>
            <?php if ($ev['checkin_code']): ?>
              <code style="background:var(--gray-100);padding:4px 8px;border-radius:6px;font-weight:700;color:var(--blue)"><?= htmlspecialchars($ev['checkin_code']) ?></code>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem"><?= htmlspecialchars($ev['created_by_name'] ?: 'LYDO Admin') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Merit Points Details -->
  <?php if ($showMerit && !empty($meritLogs)): ?>
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-star"></i> Merit Points History (<?= count($meritLogs) ?>)</h3>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Points</th>
          <th>Reason</th>
          <th>Event</th>
          <th>Awarded By</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($meritLogs as $i => $log): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge green">+<?= $log['points'] ?></span></td>
          <td><?= htmlspecialchars($log['reason']) ?></td>
          <td><?= htmlspecialchars($log['event_title'] ?: '—') ?></td>
          <td><?= htmlspecialchars($log['admin_name'] ?: 'LYDO Admin') ?></td>
          <td><?= date('M j, Y', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Demerit Points Details -->
  <?php if ($showDemerit && !empty($demeritLogs)): ?>
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-exclamation-triangle"></i> Demerit Points History (<?= count($demeritLogs) ?>)</h3>
    </div>
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Points</th>
          <th>Violation Reason</th>
          <th>Event</th>
          <th>Issued By</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($demeritLogs as $i => $log): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge" style="background:var(--red-pale);color:var(--red)">-<?= abs($log['points']) ?></span></td>
          <td><strong><?= htmlspecialchars($log['reason']) ?></strong></td>
          <td><?= htmlspecialchars($log['event_title'] ?: '—') ?></td>
          <td><?= htmlspecialchars($log['admin_name'] ?: 'LYDO Admin') ?></td>
          <td><?= date('M j, Y', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Print Footer -->
  <div class="print-footer">
    <p>Generated on <?= date('F j, Y g:i A') ?> | LYDO Organization Management System</p>
  </div>

</main>
</div>

</body>
</html>
