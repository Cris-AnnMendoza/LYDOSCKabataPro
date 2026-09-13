<?php
// Prevent browser caching - force logout to work properly
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config.php';
requireLogin();
$admin = currentAdmin();

// ── Stats ─────────────────────────────────────────────────
try {
    $pdo = db();
    $where = '';
    $params = [];
    if ($admin['role'] === 'barangay_admin' && $admin['barangay']) {
        $where = 'WHERE barangay = ?';
        $params[] = $admin['barangay'];
    }

    $total      = $pdo->prepare("SELECT COUNT(*) FROM youth_users $where");
    $total->execute($params);
    $totalCount = $total->fetchColumn();

    $month = $pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE EXTRACT(MONTH FROM created_at)=EXTRACT(MONTH FROM CURRENT_TIMESTAMP) AND EXTRACT(YEAR FROM created_at)=EXTRACT(YEAR FROM CURRENT_TIMESTAMP) " . ($where ? "AND barangay=?" : ""));
    $month->execute($params);
    $monthCount = $month->fetchColumn();

    $brgyQ = $pdo->prepare("SELECT COUNT(DISTINCT barangay) FROM youth_users $where");
    $brgyQ->execute($params);
    $brgyCount = $brgyQ->fetchColumn();

    $genderQ = $pdo->prepare("SELECT gender, COUNT(*) as cnt FROM youth_users $where GROUP BY gender");
    $genderQ->execute($params);
    $genders = $genderQ->fetchAll();

    $brgyTop = $pdo->prepare("SELECT barangay, COUNT(*) as cnt FROM youth_users $where GROUP BY barangay ORDER BY cnt DESC LIMIT 8");
    $brgyTop->execute($params);
    $topBarangays = $brgyTop->fetchAll();
    $maxBrgy = max(array_column($topBarangays, 'cnt') ?: [1]);

    $recentQ = $pdo->prepare("SELECT id,first_name,last_name,email,barangay,created_at FROM youth_users $where ORDER BY created_at DESC LIMIT 6");
    $recentQ->execute($params);
    $recent = $recentQ->fetchAll();

    // Get upcoming events
    $eventsQ = $pdo->query("
        SELECT 
            id,
            title,
            event_date,
            event_time,
            event_type,
            location as venue,
            merit_points,
            organization_id
        FROM events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC, event_time ASC
        LIMIT 50
    ");
    $upcomingEvents = $eventsQ->fetchAll();

} catch (Exception $e) {
    $totalCount = $monthCount = $brgyCount = 0;
    $genders = $topBarangays = $recent = $upcomingEvents = [];
    $maxBrgy = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Dashboard – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<style>
/* Modern Analytics Cards */
.analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:24px}
.analytics-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #e2e8f0;transition:.3s}
.analytics-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateY(-2px)}
.analytics-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.analytics-title{font-size:.95rem;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px}
.analytics-title i{color:#1565c0;font-size:.85rem}
.chart-container{position:relative;height:280px;display:flex;align-items:center;justify-content:center}
.chart-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9}
.legend-item{display:flex;align-items:center;gap:8px;font-size:.82rem}
.legend-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0}
.legend-label{color:#475569;font-weight:500;flex:1}
.legend-value{color:#1e293b;font-weight:700}
.stat-summary{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:16px}
.stat-summary-item{background:#f8fafc;padding:12px;border-radius:10px;text-align:center}
.stat-summary-val{display:block;font-size:1.4rem;font-weight:800;color:#1e293b;margin-bottom:4px}
.stat-summary-lbl{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.no-data{text-align:center;color:#94a3b8;padding:40px 20px;font-size:.9rem}
.no-data i{font-size:2.5rem;margin-bottom:12px;opacity:.5;display:block}

/* Compact Table */
.compact-table{margin-top:24px}
.compact-table .tbl{font-size:.85rem}
.compact-table .tbl th{background:#f8fafc;padding:12px 14px;font-size:.75rem}
.compact-table .tbl td{padding:12px 14px}
.compact-table .tbl tr:hover{background:#f8fafc}

/* Calendar Styles */
.calendar-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #e2e8f0}
.calendar-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#1565c0;border-radius:12px 12px 0 0;margin:-24px -24px 20px -24px}
.calendar-title{font-size:1.1rem;font-weight:700;color:#ffffff;display:flex;align-items:center;gap:10px;margin:0}
.calendar-title i{color:#ffffff;font-size:1rem}
.calendar-header .card-link{color:#ffffff;font-size:.8rem;font-weight:600;text-decoration:none;opacity:.95;transition:.2s}
.calendar-header .card-link:hover{opacity:1;text-decoration:underline}
.calendar-container{min-height:600px}
.fc{font-family:Inter,sans-serif!important}
.fc .fc-button{background:#1565c0;border:none;text-transform:capitalize;font-weight:600;padding:8px 16px;border-radius:8px;transition:.2s}
.fc .fc-button:hover{background:#0d3b6e;transform:translateY(-1px)}
.fc .fc-button-primary:not(:disabled).fc-button-active{background:#0d3b6e}
.fc .fc-toolbar-title{font-size:1.3rem;font-weight:800;color:#1e293b}
.fc .fc-daygrid-day-number{color:#1e293b;font-weight:600}
.fc .fc-col-header-cell-cushion{color:#64748b;font-weight:700;text-transform:uppercase;font-size:.75rem;letter-spacing:.05em}
.fc .fc-daygrid-day.fc-day-today{background:rgba(21,101,192,.08)!important}
.fc .fc-event{border:none;border-radius:6px;padding:4px 8px;font-size:.8rem;font-weight:600;margin-bottom:2px;cursor:pointer;transition:.2s}
.fc .fc-event:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.fc .fc-event-seminar{background:#1565c0;color:#fff}
.fc .fc-event-workshop{background:#2e7d32;color:#fff}
.fc .fc-event-training{background:#00796b;color:#fff}
.fc .fc-event-activity{background:#f57f17;color:#fff}
.fc .fc-event-meeting{background:#7b1fa2;color:#fff}
.fc .fc-event-default{background:#475569;color:#fff}
.fc .fc-daygrid-event-dot{display:none}
.fc .fc-h-event .fc-event-title-container{padding:2px}
.fc .fc-event-title{font-weight:600;white-space:normal;overflow:visible}
.fc .fc-daygrid-event{white-space:normal!important}
.fc .fc-event-main{padding:2px 4px}

/* Calendar Day Cells */
.fc .fc-daygrid-day{transition:.2s}
.fc .fc-daygrid-day:hover{background:#f8fafc}
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number{background:#1565c0;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:700}

/* Calendar Navigation */
.fc .fc-button-group{gap:4px}
.fc .fc-button{box-shadow:0 2px 4px rgba(0,0,0,.1)}
.fc .fc-button:active{transform:scale(0.98)}

/* More Events Link */
.fc .fc-daygrid-more-link{color:#1565c0;font-weight:600;text-decoration:none;padding:2px 6px;border-radius:4px;background:#e3f2fd;margin-top:2px;display:inline-block}
.fc .fc-daygrid-more-link:hover{background:#1565c0;color:#fff}

/* Event Legend */
.event-legend{display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding:14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0}
.event-legend-item{display:flex;align-items:center;gap:6px;font-size:.8rem}
.event-legend-dot{width:12px;height:12px;border-radius:3px}
.event-legend-label{color:#475569;font-weight:600}

/* Upcoming Events List */
.upcoming-list{max-height:620px;overflow-y:auto;padding-right:4px}
.upcoming-event{display:flex;align-items:flex-start;gap:14px;padding:14px;border-radius:10px;background:#f8fafc;margin-bottom:10px;transition:.2s;border-left:4px solid transparent}
.upcoming-event:hover{background:#e3f2fd;border-left-color:#1565c0;transform:translateX(4px)}
.upcoming-event-date{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:60px;padding:10px;background:#fff;border-radius:10px;border:2px solid #e2e8f0}
.upcoming-event-month{font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.upcoming-event-day{font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1}
.upcoming-event-year{font-size:.7rem;color:#94a3b8;font-weight:600}
.upcoming-event-info{flex:1}
.upcoming-event-title{font-size:.9rem;font-weight:700;color:#1e293b;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.upcoming-event-badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.7rem;font-weight:700;text-transform:capitalize}
.upcoming-event-details{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:.75rem;color:#64748b;margin-top:6px}
.upcoming-event-details i{width:14px;text-align:center}
.upcoming-no-events{text-align:center;color:#94a3b8;padding:40px 20px;font-size:.9rem}
.upcoming-no-events i{font-size:2.5rem;margin-bottom:12px;opacity:.5;display:block}

@media(max-width:768px){
  .calendar-container{min-height:400px}
  .fc .fc-toolbar{flex-direction:column;gap:10px}
  .fc .fc-toolbar-chunk{width:100%;text-align:center}
  .event-legend{gap:10px}
  .upcoming-event{flex-direction:column;align-items:flex-start}
  .upcoming-event-date{flex-direction:row;gap:10px;width:100%;justify-content:flex-start}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php if ($msg = flash('success')): ?>
    <div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($msg = flash('error')): ?>
    <div class="flash error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h2>Dashboard</h2>
      <p>Welcome back, <strong><?= htmlspecialchars($admin['full_name']) ?></strong>! Here's the overview.</p>
    </div>
    <span class="date-badge"><i class="fas fa-calendar"></i> <?= date('F j, Y') ?></span>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div><span class="stat-val"><?= number_format($totalCount) ?></span><span class="stat-lbl">Total Registered Youth</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
      <div><span class="stat-val"><?= number_format($monthCount) ?></span><span class="stat-lbl">New This Month</span></div>
    </div>
    <div class="stat-card teal">
      <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
      <div><span class="stat-val"><?= $brgyCount ?></span><span class="stat-lbl">Barangays Covered</span></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
      <div><span class="stat-val">30+</span><span class="stat-lbl">Active Programs</span></div>
    </div>
    <?php
    $pendingYouth   = (int)$pdo->query("SELECT COUNT(*) FROM youth_users WHERE status='pending'")->fetchColumn();
    $totalMerit     = (int)$pdo->query("SELECT COALESCE(SUM(points),0) FROM org_merit_logs WHERE type='merit'")->fetchColumn();
    $totalDemerit   = (int)$pdo->query("SELECT COALESCE(SUM(ABS(points)),0) FROM org_merit_logs WHERE type='demerit'")->fetchColumn();
    $pendingLetters = (int)$pdo->query("SELECT COUNT(*) FROM org_explanation_letters WHERE status='pending'")->fetchColumn();
    $activeOrgs     = (int)$pdo->query("SELECT COUNT(*) FROM organizations WHERE is_active = TRUE")->fetchColumn();
    $inactiveOrgs   = (int)$pdo->query("SELECT COUNT(*) FROM organizations WHERE is_active = FALSE")->fetchColumn();
    ?>
    <?php if ($pendingYouth > 0): ?>
    <a href="approvals.php" style="text-decoration:none">
    <div class="stat-card" style="border:2px solid #f57f17;cursor:pointer">
      <div class="stat-icon" style="background:#fff8e1;color:#f57f17"><i class="fas fa-clock"></i></div>
      <div><span class="stat-val" style="color:#f57f17"><?= $pendingYouth ?></span><span class="stat-lbl">Pending Approvals</span></div>
    </div>
    </a>
    <?php endif; ?>
  </div>

  <!-- MERIT & ORG STATS -->
  <div class="stats-grid" style="margin-bottom:20px">
    <a href="organizations.php" style="text-decoration:none">
      <div class="stat-card green" style="cursor:pointer">
        <div class="stat-icon"><i class="fas fa-sitemap"></i></div>
        <div><span class="stat-val"><?= $activeOrgs ?></span><span class="stat-lbl">Active Organizations</span></div>
      </div>
    </a>
    <div class="stat-card" style="border:1px solid #e2e8f0">
      <div class="stat-icon" style="background:#f1f5f9;color:#475569"><i class="fas fa-pause-circle"></i></div>
      <div><span class="stat-val"><?= $inactiveOrgs ?></span><span class="stat-lbl">Inactive Organizations</span></div>
    </div>
    <a href="merit.php" style="text-decoration:none">
      <div class="stat-card" style="border:1px solid #e2e8f0;cursor:pointer">
        <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-star"></i></div>
        <div><span class="stat-val" style="color:#2e7d32"><?= number_format($totalMerit) ?></span><span class="stat-lbl">Total Merit Points</span></div>
      </div>
    </a>
    <a href="merit.php" style="text-decoration:none">
      <div class="stat-card" style="border:1px solid #e2e8f0;cursor:pointer">
        <div class="stat-icon" style="background:#ffebee;color:#c62828"><i class="fas fa-minus-circle"></i></div>
        <div><span class="stat-val" style="color:#c62828"><?= number_format($totalDemerit) ?></span><span class="stat-lbl">Total Demerit Points</span></div>
      </div>
    </a>
    <?php if ($pendingLetters > 0): ?>
    <a href="merit.php?tab=letters" style="text-decoration:none">
      <div class="stat-card" style="border:2px solid #e65100;cursor:pointer">
        <div class="stat-icon" style="background:#fff3e0;color:#e65100"><i class="fas fa-envelope-open-text"></i></div>
        <div><span class="stat-val" style="color:#e65100"><?= $pendingLetters ?></span><span class="stat-lbl">Pending Explanation Letters</span></div>
      </div>
    </a>
    <?php endif; ?>
  </div>

  <!-- ANALYTICS SECTION -->
  <div class="analytics-grid">
    
    <!-- Gender Distribution Pie Chart -->
    <div class="analytics-card">
      <div class="analytics-header">
        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Gender Distribution</h3>
      </div>
      <?php if (!empty($genders)): ?>
      <div class="chart-container">
        <canvas id="genderChart"></canvas>
      </div>
      <div class="chart-legend">
        <?php
        $gColors = ['Male'=>'#1565c0','Female'=>'#e91e63','Non-binary'=>'#9c27b0','Prefer not to say'=>'#78909c'];
        $gTotal  = array_sum(array_column($genders,'cnt')) ?: 1;
        foreach ($genders as $g):
          $pct = round($g['cnt']/$gTotal*100);
          $col = $gColors[$g['gender']] ?? '#90a4ae';
        ?>
        <div class="legend-item">
          <div class="legend-dot" style="background:<?= $col ?>"></div>
          <span class="legend-label"><?= htmlspecialchars($g['gender'] ?: 'Unknown') ?></span>
          <span class="legend-value"><?= $g['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="no-data">
        <i class="fas fa-chart-pie"></i>
        <p>No gender data available yet</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Top Barangays Chart -->
    <div class="analytics-card">
      <div class="analytics-header">
        <h3 class="analytics-title"><i class="fas fa-map-marked-alt"></i> Top Barangays</h3>
      </div>
      <?php if (!empty($topBarangays)): ?>
      <div class="chart-container">
        <canvas id="barangayChart"></canvas>
      </div>
      <div class="stat-summary">
        <div class="stat-summary-item">
          <span class="stat-summary-val"><?= count($topBarangays) ?></span>
          <span class="stat-summary-lbl">Active Barangays</span>
        </div>
        <div class="stat-summary-item">
          <span class="stat-summary-val"><?= $topBarangays[0]['cnt'] ?? 0 ?></span>
          <span class="stat-summary-lbl">Highest Registration</span>
        </div>
      </div>
      <?php else: ?>
      <div class="no-data">
        <i class="fas fa-map-marked-alt"></i>
        <p>No barangay data available yet</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Organization Status Pie Chart -->
    <div class="analytics-card">
      <div class="analytics-header">
        <h3 class="analytics-title"><i class="fas fa-sitemap"></i> Organizations Status</h3>
      </div>
      <?php if ($activeOrgs > 0 || $inactiveOrgs > 0): ?>
      <div class="chart-container">
        <canvas id="orgChart"></canvas>
      </div>
      <div class="chart-legend">
        <div class="legend-item">
          <div class="legend-dot" style="background:#10b981"></div>
          <span class="legend-label">Active</span>
          <span class="legend-value"><?= $activeOrgs ?></span>
        </div>
        <div class="legend-item">
          <div class="legend-dot" style="background:#94a3b8"></div>
          <span class="legend-label">Inactive</span>
          <span class="legend-value"><?= $inactiveOrgs ?></span>
        </div>
      </div>
      <?php else: ?>
      <div class="no-data">
        <i class="fas fa-sitemap"></i>
        <p>No organization data available yet</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Merit vs Demerit Chart -->
    <div class="analytics-card">
      <div class="analytics-header">
        <h3 class="analytics-title"><i class="fas fa-balance-scale"></i> Merit System Overview</h3>
      </div>
      <?php if ($totalMerit > 0 || $totalDemerit > 0): ?>
      <div class="chart-container">
        <canvas id="meritChart"></canvas>
      </div>
      <div class="stat-summary">
        <div class="stat-summary-item" style="background:#e8f5e9">
          <span class="stat-summary-val" style="color:#2e7d32"><?= number_format($totalMerit) ?></span>
          <span class="stat-summary-lbl" style="color:#2e7d32">Total Merit</span>
        </div>
        <div class="stat-summary-item" style="background:#ffebee">
          <span class="stat-summary-val" style="color:#c62828"><?= number_format($totalDemerit) ?></span>
          <span class="stat-summary-lbl" style="color:#c62828">Total Demerit</span>
        </div>
      </div>
      <?php else: ?>
      <div class="no-data">
        <i class="fas fa-balance-scale"></i>
        <p>No merit/demerit data available yet</p>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- EVENTS CALENDAR SECTION -->
  <?php 
  // Debug: Show event count
  $eventCount = count($upcomingEvents);
  ?>
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:24px">
    
    <!-- Interactive Calendar -->
    <div class="calendar-card">
      <div class="calendar-header">
        <h3 class="calendar-title"><i class="fas fa-calendar-alt"></i> Events Calendar</h3>
        <a href="events.php" class="card-link">Manage Events →</a>
      </div>
      
      <!-- Event Type Legend -->
      <div class="event-legend">
        <div class="event-legend-item">
          <div class="event-legend-dot" style="background:#1565c0"></div>
          <span class="event-legend-label">Seminar</span>
        </div>
        <div class="event-legend-item">
          <div class="event-legend-dot" style="background:#2e7d32"></div>
          <span class="event-legend-label">Workshop</span>
        </div>
        <div class="event-legend-item">
          <div class="event-legend-dot" style="background:#00796b"></div>
          <span class="event-legend-label">Training</span>
        </div>
        <div class="event-legend-item">
          <div class="event-legend-dot" style="background:#f57f17"></div>
          <span class="event-legend-label">Activity</span>
        </div>
        <div class="event-legend-item">
          <div class="event-legend-dot" style="background:#7b1fa2"></div>
          <span class="event-legend-label">Meeting</span>
        </div>
      </div>
      
      <div id="calendar" class="calendar-container"></div>
    </div>

    <!-- Upcoming Events List -->
    <div class="calendar-card">
      <div class="calendar-header">
        <h3 class="calendar-title"><i class="fas fa-list-ul"></i> Upcoming Events</h3>
      </div>
      
      <div class="upcoming-list">
        <?php if (empty($upcomingEvents)): ?>
          <div class="upcoming-no-events">
            <i class="fas fa-calendar-times"></i>
            <p>No upcoming events scheduled</p>
            <a href="events.php" style="display:inline-block;margin-top:12px;padding:10px 20px;background:#1565c0;color:#fff;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600">
              <i class="fas fa-plus"></i> Create Event
            </a>
          </div>
        <?php else: ?>
          <div style="font-size:.75rem;color:#64748b;padding:8px 12px;background:#f8fafc;border-radius:8px;margin-bottom:12px;display:flex;align-items:center;gap:6px">
            <i class="fas fa-info-circle"></i>
            <span>Showing <?= count($upcomingEvents) ?> upcoming event<?= count($upcomingEvents) > 1 ? 's' : '' ?></span>
          </div>
          <?php
          $eventTypeColors = [
            'seminar' => ['#1565c0', '#e3f2fd'],
            'workshop' => ['#2e7d32', '#e8f5e9'],
            'training' => ['#00796b', '#e0f2f1'],
            'activity' => ['#f57f17', '#fff8e1'],
            'meeting' => ['#7b1fa2', '#f3e5f5'],
            'default' => ['#475569', '#f1f5f9']
          ];
          
          foreach ($upcomingEvents as $event):
            $eventType = strtolower($event['event_type'] ?? 'activity');
            [$textColor, $bgColor] = $eventTypeColors[$eventType] ?? $eventTypeColors['default'];
            $eventDate = new DateTime($event['event_date']);
          ?>
          <div class="upcoming-event">
            <div class="upcoming-event-date">
              <span class="upcoming-event-month"><?= $eventDate->format('M') ?></span>
              <span class="upcoming-event-day"><?= $eventDate->format('d') ?></span>
              <span class="upcoming-event-year"><?= $eventDate->format('Y') ?></span>
            </div>
            <div class="upcoming-event-info">
              <div class="upcoming-event-title">
                <?= htmlspecialchars($event['title']) ?>
                <span class="upcoming-event-badge" style="background:<?= $bgColor ?>;color:<?= $textColor ?>">
                  <?= ucfirst($eventType) ?>
                </span>
              </div>
              <div class="upcoming-event-details">
                <?php if ($event['event_time']): ?>
                  <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($event['event_time'])) ?></span>
                <?php endif; ?>
                <?php if ($event['venue']): ?>
                  <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['venue']) ?></span>
                <?php endif; ?>
                <?php if ($event['merit_points']): ?>
                  <span style="color:#2e7d32"><i class="fas fa-star"></i> +<?= $event['merit_points'] ?> Merit</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- RECENT REGISTRATIONS -->
  <div class="compact-table">
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-clock"></i> Recent Registrations</h3>
        <a href="users.php" class="card-link">View All →</a>
      </div>
      <table class="tbl">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Barangay</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="5" class="empty">No registrations yet.</td></tr>
        <?php else: foreach ($recent as $i => $u): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></strong></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['barangay'] ?: '—') ?></td>
            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>

<script>
// Chart.js Configuration
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#64748b';

// FullCalendar Initialization
document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('calendar');
  
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listWeek'
    },
    height: 'auto',
    events: [
      <?php 
      $eventsList = [];
      foreach ($upcomingEvents as $event): 
        $eventType = strtolower($event['event_type'] ?? 'activity');
        $eventClass = 'fc-event-' . $eventType;
        $eventDateTime = $event['event_date'];
        if (!empty($event['event_time'])) {
          $eventDateTime .= 'T' . $event['event_time'];
        }
        
        $eventData = [
          'id' => $event['id'],
          'title' => addslashes($event['title']),
          'start' => $eventDateTime,
          'className' => $eventClass,
          'venue' => addslashes($event['venue'] ?? ''),
          'merit' => $event['merit_points'] ?? 0,
          'type' => $eventType
        ];
        
        echo json_encode([
          'id' => (string)$event['id'],
          'title' => $event['title'],
          'start' => $eventDateTime,
          'className' => $eventClass,
          'extendedProps' => [
            'venue' => $event['venue'] ?? '',
            'merit' => (int)($event['merit_points'] ?? 0),
            'type' => $eventType
          ]
        ]);
        
        if ($event !== end($upcomingEvents)) echo ',';
      endforeach; 
      ?>
    ],
    eventClick: function(info) {
      const event = info.event;
      let details = '📅 ' + event.title + '\n\n';
      
      details += '📆 Date: ' + event.start.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      }) + '\n';
      
      if (event.start.getHours() > 0 || event.start.getMinutes() > 0) {
        details += '⏰ Time: ' + event.start.toLocaleTimeString('en-US', { 
          hour: '2-digit', 
          minute: '2-digit' 
        }) + '\n';
      }
      
      if (event.extendedProps.venue) {
        details += '📍 Venue: ' + event.extendedProps.venue + '\n';
      }
      
      if (event.extendedProps.merit > 0) {
        details += '⭐ Merit Points: +' + event.extendedProps.merit + '\n';
      }
      
      details += '🏷️ Type: ' + event.extendedProps.type.charAt(0).toUpperCase() + event.extendedProps.type.slice(1);
      
      alert(details);
    },
    eventMouseEnter: function(info) {
      info.el.style.transform = 'scale(1.05)';
      info.el.style.zIndex = '1000';
    },
    eventMouseLeave: function(info) {
      info.el.style.transform = 'scale(1)';
      info.el.style.zIndex = 'auto';
    },
    eventDidMount: function(info) {
      // Add tooltip on hover
      info.el.title = info.event.title + 
        (info.event.extendedProps.venue ? '\nVenue: ' + info.event.extendedProps.venue : '') +
        (info.event.extendedProps.merit > 0 ? '\n+' + info.event.extendedProps.merit + ' Merit Points' : '');
    }
  });
  
  calendar.render();
  
  console.log('Calendar loaded with <?= count($upcomingEvents) ?> events');
});

<?php if (!empty($genders)): ?>
// Gender Distribution Pie Chart - SIMPLIFIED
new Chart(document.getElementById('genderChart'), {
  type: 'doughnut',
  data: {
    labels: [<?php foreach($genders as $g) echo "'".addslashes($g['gender'] ?: 'Unknown')."',"; ?>],
    datasets: [{
      data: [<?php foreach($genders as $g) echo $g['cnt'].','; ?>],
      backgroundColor: [
        <?php
        foreach($genders as $g) {
          $col = $gColors[$g['gender']] ?? '#90a4ae';
          echo "'$col',";
        }
        ?>
      ],
      borderWidth: 3,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        padding: 10,
        bodyFont: { size: 13 },
        displayColors: false,
        callbacks: {
          label: function(ctx) {
            let total = ctx.dataset.data.reduce((a,b) => a+b, 0);
            let pct = Math.round(ctx.parsed / total * 100);
            return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
          }
        }
      }
    },
    cutout: '65%'
  }
});
<?php endif; ?>

<?php if (!empty($topBarangays)): ?>
// Top Barangays Bar Chart - SIMPLIFIED
new Chart(document.getElementById('barangayChart'), {
  type: 'bar',
  data: {
    labels: [<?php foreach($topBarangays as $b) echo "'".addslashes($b['barangay'])."',"; ?>],
    datasets: [{
      data: [<?php foreach($topBarangays as $b) echo $b['cnt'].','; ?>],
      backgroundColor: '#1565c0',
      borderRadius: 6
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        padding: 10,
        bodyFont: { size: 13 },
        displayColors: false,
        callbacks: {
          label: function(ctx) {
            return ctx.parsed.x + ' registrations';
          }
        }
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: { 
          precision: 0,
          font: { size: 11 }
        },
        grid: { color: '#f1f5f9' },
        border: { display: false }
      },
      y: {
        ticks: { font: { size: 11, weight: '600' } },
        grid: { display: false },
        border: { display: false }
      }
    }
  }
});
<?php endif; ?>

<?php if ($activeOrgs > 0 || $inactiveOrgs > 0): ?>
// Organizations Status Chart - SIMPLIFIED
new Chart(document.getElementById('orgChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active', 'Inactive'],
    datasets: [{
      data: [<?= $activeOrgs ?>, <?= $inactiveOrgs ?>],
      backgroundColor: ['#10b981', '#e2e8f0'],
      borderWidth: 3,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        padding: 10,
        bodyFont: { size: 13 },
        displayColors: false,
        callbacks: {
          label: function(ctx) {
            return ctx.label + ': ' + ctx.parsed;
          }
        }
      }
    },
    cutout: '65%'
  }
});
<?php endif; ?>

<?php if ($totalMerit > 0 || $totalDemerit > 0): ?>
// Merit System Chart - SIMPLIFIED
new Chart(document.getElementById('meritChart'), {
  type: 'pie',
  data: {
    labels: ['Merit Points', 'Demerit Points'],
    datasets: [{
      data: [<?= $totalMerit ?>, <?= $totalDemerit ?>],
      backgroundColor: ['#2e7d32', '#c62828'],
      borderWidth: 3,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        padding: 10,
        bodyFont: { size: 13 },
        displayColors: false,
        callbacks: {
          label: function(ctx) {
            return ctx.label + ': ' + ctx.parsed.toLocaleString();
          }
        }
      }
    }
  }
});
<?php endif; ?>

// Prevent back button after logout
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
    window.location.reload();
  }
});
</script>
</body>
</html>
