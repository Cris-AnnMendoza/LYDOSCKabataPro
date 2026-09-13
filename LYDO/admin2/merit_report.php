<?php
require_once 'config.php';
require_once 'merit_engine.php';
requireLogin();

$pdo   = db();
$admin = currentAdmin();

// ── Filters ───────────────────────────────────────────────
$filterOrg  = (int)($_GET['org_id']   ?? 0);
$filterType = trim($_GET['type']      ?? '');   // merit | demerit | ''
$filterFrom = trim($_GET['date_from'] ?? '');
$filterTo   = trim($_GET['date_to']   ?? '');
$format     = trim($_GET['format']    ?? '');   // excel | print | ''

// ── Build WHERE clause ────────────────────────────────────
$where  = [];
$params = [];

if ($filterOrg) {
    $where[]  = 'm.organization_id = ?';
    $params[] = $filterOrg;
}
if ($filterType) {
    $where[]  = 'm.type = ?';
    $params[] = $filterType;
}
if ($filterFrom) {
    $where[]  = 'DATE(m.created_at) >= ?';
    $params[] = $filterFrom;
}
if ($filterTo) {
    $where[]  = 'DATE(m.created_at) <= ?';
    $params[] = $filterTo;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Leaderboard summary ───────────────────────────────────
$leaderboardSQL = "
    SELECT o.id, o.name, o.category, o.barangay, o.is_active,
           COALESCE(SUM(CASE WHEN m.type='merit'   THEN m.points  ELSE 0 END),0) AS merit_pts,
           COALESCE(SUM(CASE WHEN m.type='demerit' THEN ABS(m.points) ELSE 0 END),0) AS demerit_pts,
           COALESCE(SUM(m.points),0) AS net_pts,
           COUNT(CASE WHEN m.type='merit'   THEN 1 END) AS merit_count,
           COUNT(CASE WHEN m.type='demerit' THEN 1 END) AS demerit_count
    FROM organizations o
    LEFT JOIN org_merit_logs m ON m.organization_id = o.id
    $whereSQL
    GROUP BY o.id
    ORDER BY net_pts DESC, merit_pts DESC
";
$leaderboardStmt = $pdo->prepare($leaderboardSQL);
$leaderboardStmt->execute($params);
$leaderboard = $leaderboardStmt->fetchAll();

// ── Detailed logs (with same filters as leaderboard) ──────
$logsStmt = $pdo->prepare("
    SELECT m.*, o.name AS org_name, o.category, o.barangay,
           a.full_name AS admin_name,
           e.title AS event_title
    FROM org_merit_logs m
    JOIN organizations o ON o.id = m.organization_id
    LEFT JOIN admin_users a ON a.id = m.awarded_by
    LEFT JOIN events e ON e.id = m.event_id
    $whereSQL
    ORDER BY m.created_at DESC
    LIMIT 1000
");
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

// ── Totals ────────────────────────────────────────────────
$totalMerit   = array_sum(array_column(array_filter($logs, fn($l) => $l['type']==='merit'),   'points'));
$totalDemerit = array_sum(array_map('abs', array_column(array_filter($logs, fn($l) => $l['type']==='demerit'), 'points')));

$orgs = $pdo->query('SELECT id, name FROM organizations ORDER BY name')->fetchAll();

// ── EXCEL EXPORT ──────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="LYDO_Merit_Demerit_Report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"/></head><body>';

    // Sheet 1: Leaderboard
    echo '<table border="1">';
    echo '<tr><td colspan="8" style="font-size:16pt;font-weight:bold;background:#0d3b6e;color:#fff">LYDO Merit & Demerit Report — Organization Leaderboard</td></tr>';
    echo '<tr><td colspan="8" style="color:#666">Generated: ' . date('F j, Y g:i A') . ' | By: ' . htmlspecialchars($admin['full_name']) . '</td></tr>';
    echo '<tr></tr>';
    echo '<tr style="background:#1565c0;color:#fff;font-weight:bold">';
    echo '<td>Rank</td><td>Organization</td><td>Category</td><td>Barangay</td>';
    echo '<td>Merit Points</td><td>Demerit Points</td><td>Net Score</td><td>Status</td>';
    echo '</tr>';
    foreach ($leaderboard as $i => $r) {
        $bg = $i === 0 ? '#fff8e1' : ($i === 1 ? '#f5f5f5' : '#fff');
        echo '<tr style="background:' . $bg . '">';
        echo '<td>' . ($i+1) . '</td>';
        echo '<td>' . htmlspecialchars($r['name']) . '</td>';
        echo '<td>' . htmlspecialchars($r['category'] ?: '—') . '</td>';
        echo '<td>' . htmlspecialchars($r['barangay'] ?: '—') . '</td>';
        echo '<td style="color:green;font-weight:bold">+' . $r['merit_pts'] . '</td>';
        echo '<td style="color:red;font-weight:bold">-' . $r['demerit_pts'] . '</td>';
        echo '<td style="font-weight:bold">' . ($r['net_pts'] >= 0 ? '+' : '') . $r['net_pts'] . '</td>';
        echo '<td>' . ($r['is_active'] ? 'Active' : 'Inactive') . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<br><br>';

    // Sheet 2: Detailed Logs
    echo '<table border="1">';
    echo '<tr><td colspan="8" style="font-size:14pt;font-weight:bold;background:#0d3b6e;color:#fff">Detailed Merit/Demerit Logs</td></tr>';
    echo '<tr style="background:#1565c0;color:#fff;font-weight:bold">';
    echo '<td>#</td><td>Organization</td><td>Type</td><td>Points</td><td>Reason</td><td>Event</td><td>Awarded By</td><td>Date & Time</td>';
    echo '</tr>';
    foreach ($logs as $i => $l) {
        $color = $l['type'] === 'merit' ? '#e8f5e9' : '#ffebee';
        echo '<tr style="background:' . $color . '">';
        echo '<td>' . ($i+1) . '</td>';
        echo '<td>' . htmlspecialchars($l['org_name']) . '</td>';
        echo '<td>' . ucfirst($l['type']) . '</td>';
        echo '<td>' . ($l['points'] > 0 ? '+' : '') . $l['points'] . '</td>';
        echo '<td>' . htmlspecialchars($l['reason']) . '</td>';
        echo '<td>' . htmlspecialchars($l['event_title'] ?: '—') . '</td>';
        echo '<td>' . htmlspecialchars($l['admin_name'] ?: 'System') . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($l['created_at'])) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Merit & Demerit Report – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
/* ── FILTER BAR ── */
.filter-bar{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;align-items:end}
.filter-bar .fg{display:flex;flex-direction:column;gap:4px}
.filter-bar label{font-size:.75rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em}
.filter-bar select,.filter-bar input{padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;width:100%;height:40px!important;box-sizing:border-box}
.filter-bar select:focus,.filter-bar input:focus{border-color:#1e88e5;background:#fff}
.btn-filter{padding:9px 18px;background:#1565c0;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;height:40px!important;min-width:130px;box-sizing:border-box}
.btn-filter:hover{background:#0d3b6e}
.btn-reset{padding:9px 14px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;height:40px!important;min-width:130px;box-sizing:border-box}
.btn-reset:hover{background:#e2e8f0}

/* ── ACTION BUTTONS ── */
.action-bar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.btn-excel{padding:10px 20px;background:linear-gradient(135deg,#1b5e20,#2e7d32);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s;text-decoration:none;box-shadow:0 3px 10px rgba(46,125,50,.3)}
.btn-excel:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(46,125,50,.4)}
.btn-print{padding:10px 20px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s;box-shadow:0 3px 10px rgba(21,101,192,.3)}
.btn-print:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(21,101,192,.4)}

/* ── SUMMARY CARDS ── */
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.sum-card{background:#fff;border-radius:12px;padding:16px 18px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px}
.sum-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.sum-val{font-size:1.6rem;font-weight:800;line-height:1}
.sum-lbl{font-size:.72rem;color:#94a3b8;font-weight:500;margin-top:3px}

/* ── TABLES ── */
.section-title{font-size:.88rem;font-weight:700;color:#0d3b6e;margin:20px 0 12px;padding-bottom:8px;border-bottom:2px solid #e3f2fd;display:flex;align-items:center;gap:7px}
.rank-1{color:#f57f17;font-weight:800}
.rank-2{color:#607d8b;font-weight:700}
.rank-3{color:#795548;font-weight:700}
.pts-merit{color:#2e7d32;font-weight:700}
.pts-demerit{color:#c62828;font-weight:700}
.pts-net-pos{color:#1565c0;font-weight:800}
.pts-net-neg{color:#c62828;font-weight:800}

/* ── PRINT STYLES ── */
@media print {
  .no-print,.filter-bar,.action-bar,.sidebar,.topbar,.main-wrap > .topbar { display:none !important }
  .main-wrap { margin-left:0 !important }
  .content { padding:0 !important }
  .print-header { display:block !important }
  body { background:#fff }
  .card { box-shadow:none !important; border:1px solid #ccc !important }
  .summary-grid { grid-template-columns:repeat(4,1fr) }
  @page { size:landscape; margin:10mm }
}
.print-header {
  display:none;
  text-align:center;
  margin-bottom:20px;
  padding-bottom:14px;
  border-bottom:2px solid #0d3b6e;
}
.print-header h1{font-size:1.4rem;font-weight:800;color:#0d3b6e;margin-bottom:4px}
.print-header p{font-size:.85rem;color:#475569}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<!-- Print Header (hidden on screen, shown on print) -->
<div class="print-header">
  <h1>LYDO Merit & Demerit Report</h1>
  <p>Local Youth Development Office · Sta. Cruz, Laguna</p>
  <p>Generated: <?= date('F j, Y g:i A') ?> · By: <?= htmlspecialchars($admin['full_name']) ?></p>
  <?php if ($filterFrom || $filterTo): ?>
  <p>Period: <?= $filterFrom ?: 'All time' ?> to <?= $filterTo ?: 'Present' ?></p>
  <?php endif; ?>
</div>

<div class="page-header no-print">
  <div>
    <h2><i class="fas fa-chart-bar" style="color:#1565c0;margin-right:8px"></i>Merit & Demerit Report</h2>
    <p>Generate and export organization merit/demerit reports.</p>
  </div>
  <a href="merit.php" class="btn-secondary no-print"><i class="fas fa-arrow-left"></i> Back to Merit</a>
</div>

<!-- Filter Bar -->
<form method="GET" class="filter-bar no-print">
  <div class="fg">
    <label>Organization</label>
    <select name="org_id">
      <option value="">All Organizations</option>
      <?php foreach ($orgs as $o): ?>
        <option value="<?=$o['id']?>" <?=$filterOrg==$o['id']?'selected':''?>><?=htmlspecialchars($o['name'])?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Type</label>
    <select name="type">
      <option value="">All Types</option>
      <option value="merit"   <?=$filterType==='merit'?'selected':''?>>Merit Only</option>
      <option value="demerit" <?=$filterType==='demerit'?'selected':''?>>Demerit Only</option>
    </select>
  </div>
  <div class="fg">
    <label>Date From</label>
    <input type="date" name="date_from" value="<?=htmlspecialchars($filterFrom)?>"/>
  </div>
  <div class="fg">
    <label>Date To</label>
    <input type="date" name="date_to" value="<?=htmlspecialchars($filterTo)?>"/>
  </div>
  <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
  <a href="merit_report.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
</form>

<!-- Action Buttons -->
<div class="action-bar no-print">
  <?php
  $exportParams = http_build_query([
    'org_id'    => $filterOrg,
    'type'      => $filterType,
    'date_from' => $filterFrom,
    'date_to'   => $filterTo,
    'format'    => 'excel',
  ]);
  ?>
  <a href="merit_report.php?<?= $exportParams ?>" class="btn-excel">
    <i class="fas fa-file-excel"></i> Download Excel
  </a>
  <button class="btn-print" onclick="window.print()">
    <i class="fas fa-print"></i> Print Report
  </button>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
  <div class="sum-card">
    <div class="sum-icon" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-star"></i></div>
    <div><div class="sum-val" style="color:#2e7d32"><?= number_format($totalMerit) ?></div><div class="sum-lbl">Total Merit Points</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#ffebee;color:#c62828"><i class="fas fa-minus-circle"></i></div>
    <div><div class="sum-val" style="color:#c62828"><?= number_format($totalDemerit) ?></div><div class="sum-lbl">Total Demerit Points</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-sitemap"></i></div>
    <div><div class="sum-val" style="color:#1565c0"><?= count($leaderboard) ?></div><div class="sum-lbl">Organizations</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f3e5f5;color:#7b1fa2"><i class="fas fa-list"></i></div>
    <div><div class="sum-val" style="color:#7b1fa2"><?= count($logs) ?></div><div class="sum-lbl">Log Entries</div></div>
  </div>
</div>

<!-- Leaderboard Table -->
<div class="section-title"><i class="fas fa-trophy" style="color:#f57f17"></i> Organization Leaderboard</div>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Organization</th>
          <th>Category</th>
          <th>Barangay</th>
          <th>Merit Pts</th>
          <th>Demerit Pts</th>
          <th>Net Score</th>
          <th>Transactions</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($leaderboard)): ?>
        <tr><td colspan="9" class="empty">No data found.</td></tr>
      <?php else: foreach ($leaderboard as $i => $r):
        $rank = $i + 1;
        $medal = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':$rank));
        $netClass = $r['net_pts'] >= 0 ? 'pts-net-pos' : 'pts-net-neg';
      ?>
        <tr>
          <td class="<?=$rank<=3?'rank-'.$rank:''?>"><?=$medal?></td>
          <td><strong><?=htmlspecialchars($r['name'])?></strong></td>
          <td><span class="badge blue"><?=htmlspecialchars($r['category']?:'—')?></span></td>
          <td><?=htmlspecialchars($r['barangay']?:'—')?></td>
          <td class="pts-merit">+<?=$r['merit_pts']?></td>
          <td class="pts-demerit">-<?=$r['demerit_pts']?></td>
          <td class="<?=$netClass?>"><?=($r['net_pts']>=0?'+':'').$r['net_pts']?></td>
          <td style="font-size:.82rem;color:#475569">
            <span style="color:#2e7d32"><?=$r['merit_count']?> merit</span> /
            <span style="color:#c62828"><?=$r['demerit_count']?> demerit</span>
          </td>
          <td><span class="badge <?=$r['is_active']?'green':'gray'?>"><?=$r['is_active']?'Active':'Inactive'?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detailed Logs Table -->
<div class="section-title" style="margin-top:24px"><i class="fas fa-history" style="color:#1565c0"></i> Detailed Transaction Logs</div>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Organization</th>
          <th>Type</th>
          <th>Points</th>
          <th>Reason</th>
          <th>Event</th>
          <th>Awarded By</th>
          <th>Date & Time</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="8" class="empty">No log entries found for the selected filters.</td></tr>
      <?php else: foreach ($logs as $i => $l): ?>
        <tr>
          <td><?=$i+1?></td>
          <td>
            <strong><?=htmlspecialchars($l['org_name'])?></strong>
            <?php if ($l['barangay']): ?><br><small style="color:#94a3b8"><?=htmlspecialchars($l['barangay'])?></small><?php endif; ?>
          </td>
          <td><span class="badge <?=$l['type']==='merit'?'green':'red'?>"><?=ucfirst($l['type'])?></span></td>
          <td class="<?=$l['type']==='merit'?'pts-merit':'pts-demerit'?>"><?=($l['points']>0?'+':'').$l['points']?></td>
          <td style="max-width:220px;font-size:.82rem">
            <?php if ($l['category']==='lydo_head_bonus'): ?>
              <span style="background:#e3f2fd;color:#1565c0;padding:1px 7px;border-radius:50px;font-size:.7rem;font-weight:700;margin-right:4px">LYDO Head</span>
            <?php endif; ?>
            <?=htmlspecialchars($l['reason'])?>
          </td>
          <td style="font-size:.82rem"><?=htmlspecialchars($l['event_title']?:'—')?></td>
          <td style="font-size:.82rem"><?=htmlspecialchars($l['admin_name']?:'System')?></td>
          <td style="font-size:.8rem;white-space:nowrap"><?=date('M j, Y',strtotime($l['created_at']))?><br><small style="color:#94a3b8"><?=date('g:i A',strtotime($l['created_at']))?></small></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Footer note -->
<div style="text-align:center;font-size:.75rem;color:#94a3b8;margin-top:20px;padding:12px">
  Report generated on <?= date('F j, Y \a\t g:i A') ?> by <?= htmlspecialchars($admin['full_name']) ?> · LYDO Sta. Cruz, Laguna
</div>

</main>
</div>
</body>
</html>
