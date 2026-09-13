<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('view_reports')) { header('Location: dashboard.php'); exit; }

$pdo   = db();
$admin = currentAdmin();

// ── Scope for barangay_admin ──────────────────────────────
$scopeWhere  = '';
$scopeParams = [];
if ($admin['role'] === 'barangay_admin' && $admin['barangay']) {
    $scopeWhere    = 'AND barangay = ?';
    $scopeParams[] = $admin['barangay'];
}

// ── Filters from GET ──────────────────────────────────────
$fBarangay  = trim($_GET['barangay']       ?? '');
$fGender    = trim($_GET['gender']         ?? '');
$fClass     = trim($_GET['classification'] ?? '');
$fEduc      = trim($_GET['educational']    ?? '');
$fEmploy    = trim($_GET['employment']     ?? '');
$fStatus    = trim($_GET['status']         ?? '');
$fMonth     = trim($_GET['month']          ?? '');
$fYear      = trim($_GET['year']           ?? '');
$search     = trim($_GET['search']         ?? '');

// Build dynamic WHERE
$where  = ['1=1'];
$params = $scopeParams;

if ($fBarangay) { $where[] = 'barangay = ?';           $params[] = $fBarangay; }
if ($fGender)   { $where[] = 'gender = ?';             $params[] = $fGender; }
if ($fClass)    { $where[] = 'youth_classification LIKE ?'; $params[] = '%'.$fClass.'%'; }
if ($fEduc)     { $where[] = 'educational_status = ?'; $params[] = $fEduc; }
if ($fEmploy)   { $where[] = 'employment_status = ?';  $params[] = $fEmploy; }
if ($fStatus)   { $where[] = 'status = ?';             $params[] = $fStatus; }
if ($fMonth)    { $where[] = 'MONTH(created_at) = ?';  $params[] = $fMonth; }
if ($fYear)     { $where[] = 'YEAR(created_at) = ?';   $params[] = $fYear; }
if ($search)    {
    $s = '%'.$search.'%';
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR barangay LIKE ?)';
    array_push($params, $s, $s, $s, $s);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Paginate the full list ────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM youth_users $whereSQL");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$pages = ceil($totalFiltered / $limit);

$listStmt = $pdo->prepare("SELECT id,first_name,last_name,email,gender,barangay,
    youth_classification,educational_status,employment_status,status,created_at
    FROM youth_users $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$users = $listStmt->fetchAll();

// ── Summary stats (scoped) ────────────────────────────────
$totalAll = (int)$pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE 1=1 $scopeWhere")
    ->execute($scopeParams) ? $pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE 1=1 $scopeWhere") : null;

// Re-run cleanly
$stTotal = $pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE 1=1 $scopeWhere");
$stTotal->execute($scopeParams);
$totalAll = (int)$stTotal->fetchColumn();

$stApproved = $pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE status='approved' $scopeWhere");
$stApproved->execute($scopeParams);
$totalApproved = (int)$stApproved->fetchColumn();

$stPending = $pdo->prepare("SELECT COUNT(*) FROM youth_users WHERE status='pending' $scopeWhere");
$stPending->execute($scopeParams);
$totalPending = (int)$stPending->fetchColumn();

// ── Filter options ────────────────────────────────────────
$barangays  = $pdo->query("SELECT DISTINCT barangay FROM youth_users WHERE barangay IS NOT NULL ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);
$educList   = $pdo->query("SELECT DISTINCT educational_status FROM youth_users WHERE educational_status IS NOT NULL ORDER BY educational_status")->fetchAll(PDO::FETCH_COLUMN);
$employList = $pdo->query("SELECT DISTINCT employment_status FROM youth_users WHERE employment_status IS NOT NULL ORDER BY employment_status")->fetchAll(PDO::FETCH_COLUMN);
$years      = $pdo->query("SELECT DISTINCT YEAR(created_at) as yr FROM youth_users ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);

$classifications = [
    'In School Youth','Out of School Youth','Working Youth','Youth with Disability',
    'Indigenous Youth','Children in Conflict with Law','LGBTQ+ Youth','Other'
];

// ── Analytics (for charts) ────────────────────────────────
$byGender = $pdo->prepare("SELECT gender, COUNT(*) as cnt FROM youth_users WHERE 1=1 $scopeWhere GROUP BY gender ORDER BY cnt DESC");
$byGender->execute($scopeParams);
$genders = $byGender->fetchAll();
$gTotal  = array_sum(array_column($genders,'cnt')) ?: 1;

$byBarangay = $pdo->prepare("SELECT barangay, COUNT(*) as cnt FROM youth_users WHERE 1=1 $scopeWhere GROUP BY barangay ORDER BY cnt DESC LIMIT 10");
$byBarangay->execute($scopeParams);
$topBrgy = $byBarangay->fetchAll();
$maxBrgy = max(array_column($topBrgy,'cnt') ?: [1]);

$byClass = $pdo->prepare("SELECT youth_classification, COUNT(*) as cnt FROM youth_users WHERE youth_classification IS NOT NULL $scopeWhere GROUP BY youth_classification ORDER BY cnt DESC");
$byClass->execute($scopeParams);
$classList = $byClass->fetchAll();
$maxClass  = max(array_column($classList,'cnt') ?: [1]);

$byMonth = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%b %Y') as mo, YEAR(created_at) as yr, MONTH(created_at) as mn, COUNT(*) as cnt FROM youth_users WHERE 1=1 $scopeWhere GROUP BY yr,mn,DATE_FORMAT(created_at,'%b %Y') ORDER BY yr DESC,mn DESC LIMIT 12");
$byMonth->execute($scopeParams);
$monthly = array_reverse($byMonth->fetchAll());
$maxMonth = max(array_column($monthly,'cnt') ?: [1]);

$gColors = ['Male'=>'#1565c0','Female'=>'#e91e63','Non-binary'=>'#9c27b0','Prefer not to say'=>'#78909c'];

// Build export query string
$exportParams = http_build_query(array_filter([
    'barangay'=>$fBarangay,'gender'=>$fGender,'classification'=>$fClass,
    'educational'=>$fEduc,'employment'=>$fEmploy,'status'=>$fStatus,
    'month'=>$fMonth,'year'=>$fYear,'search'=>$search
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Reports – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.filter-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:20px}
.filter-panel h4{font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;gap:7px}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.filter-grid select,.filter-grid input{padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.83rem;color:#1e293b;background:#f8fafc;outline:none;width:100%;transition:.2s}
.filter-grid select:focus,.filter-grid input:focus{border-color:#1e88e5;background:#fff}
.filter-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
.summary-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.sum-card{background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:16px 18px;display:flex;align-items:center;gap:12px}
.sum-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.sum-val{font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1}
.sum-lbl{font-size:.75rem;color:#475569;font-weight:500;margin-top:2px}
.section-title{font-size:.9rem;font-weight:700;color:#1e293b;margin-bottom:12px;display:flex;align-items:center;gap:7px}
.section-title i{color:#1565c0}
.charts-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.result-info{font-size:.82rem;color:#475569;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.export-btns{display:flex;gap:8px;flex-wrap:wrap}
.btn-export{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s;border:none}
.btn-export.csv{background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7}
.btn-export.csv:hover{background:#2e7d32;color:#fff}
.btn-export.all{background:#e3f2fd;color:#1565c0;border:1.5px solid #90caf9}
.btn-export.all:hover{background:#1565c0;color:#fff}
.status-approved{background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700}
.status-pending{background:#fff8e1;color:#f57f17;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700}
.status-rejected{background:#ffebee;color:#c62828;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700}
@media(max-width:900px){.charts-row{grid-template-columns:1fr}.summary-row{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.summary-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <div class="page-header">
    <div><h2>Reports & Analytics</h2><p>View, filter, and export youth registration data.</p></div>
  </div>

  <!-- SUMMARY STATS -->
  <div class="summary-row">
    <div class="sum-card">
      <div class="sum-icon" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-users"></i></div>
      <div><div class="sum-val"><?= number_format($totalAll) ?></div><div class="sum-lbl">Total Registered Youth</div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-check-circle"></i></div>
      <div><div class="sum-val"><?= number_format($totalApproved) ?></div><div class="sum-lbl">Approved Accounts</div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon" style="background:#fff8e1;color:#f57f17"><i class="fas fa-clock"></i></div>
      <div><div class="sum-val"><?= number_format($totalPending) ?></div><div class="sum-lbl">Pending Approval</div></div>
    </div>
  </div>

  <!-- FILTER PANEL -->
  <div class="filter-panel">
    <h4><i class="fas fa-filter" style="color:#1565c0"></i> Filter Youth Records</h4>
    <form method="GET" action="">
      <div class="filter-grid">
        <input type="text" name="search" placeholder="Search name / email..." value="<?= htmlspecialchars($search) ?>"/>

        <select name="barangay">
          <option value="">All Barangays</option>
          <?php foreach ($barangays as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>" <?= $fBarangay===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="gender">
          <option value="">All Genders</option>
          <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
            <option value="<?= $g ?>" <?= $fGender===$g?'selected':'' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>

        <select name="classification">
          <option value="">All Classifications</option>
          <?php foreach ($classifications as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $fClass===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="educational">
          <option value="">All Educational Status</option>
          <?php foreach ($educList as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $fEduc===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="employment">
          <option value="">All Employment Status</option>
          <?php foreach ($employList as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $fEmploy===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="status">
          <option value="">All Statuses</option>
          <option value="pending"  <?= $fStatus==='pending' ?'selected':'' ?>>Pending</option>
          <option value="approved" <?= $fStatus==='approved'?'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $fStatus==='rejected'?'selected':'' ?>>Rejected</option>
        </select>

        <select name="month">
          <option value="">All Months</option>
          <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $fMonth==(string)$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>

        <select name="year">
          <option value="">All Years</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $fYear==(string)$y?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
        <a href="reports.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
      </div>
    </form>
  </div>

  <!-- CHARTS ROW -->
  <div class="charts-row">
    <!-- Gender -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3></div>
      <div class="gender-list">
        <?php foreach ($genders as $g):
          $pct = round($g['cnt']/$gTotal*100);
          $col = $gColors[$g['gender']] ?? '#90a4ae';
        ?>
        <div class="gender-item">
          <div class="g-dot" style="background:<?= $col ?>"></div>
          <span class="g-label"><?= htmlspecialchars($g['gender'] ?: 'Unknown') ?></span>
          <div class="g-bar-track"><div class="g-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
          <span class="g-count"><?= $g['cnt'] ?></span>
          <span class="g-pct"><?= $pct ?>%</span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($genders)): ?><p class="empty">No data.</p><?php endif; ?>
      </div>
    </div>

    <!-- Monthly -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-chart-line"></i> Monthly Registrations</h3></div>
      <div class="brgy-list">
        <?php foreach ($monthly as $m):
          $pct = round($m['cnt']/$maxMonth*100);
        ?>
        <div class="brgy-item">
          <span class="brgy-name" style="width:80px"><?= htmlspecialchars($m['mo']) ?></span>
          <div class="brgy-track"><div class="brgy-fill" style="width:<?= $pct ?>%;background:#43a047"></div></div>
          <span class="brgy-count"><?= $m['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($monthly)): ?><p class="empty" style="padding:16px">No data.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="charts-row">
    <!-- By Barangay -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-map"></i> Top Barangays</h3></div>
      <div class="brgy-list">
        <?php foreach ($topBrgy as $b):
          $pct = round($b['cnt']/$maxBrgy*100);
        ?>
        <div class="brgy-item">
          <span class="brgy-name"><?= htmlspecialchars($b['barangay'] ?: 'Unknown') ?></span>
          <div class="brgy-track"><div class="brgy-fill" style="width:<?= $pct ?>%"></div></div>
          <span class="brgy-count"><?= $b['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topBrgy)): ?><p class="empty" style="padding:16px">No data.</p><?php endif; ?>
      </div>
    </div>

    <!-- By Classification -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-id-card"></i> Youth Classification</h3></div>
      <div class="brgy-list">
        <?php foreach ($classList as $c):
          $label = $c['youth_classification'];
          if (str_starts_with($label,'[')) {
            $arr = json_decode($label,true);
            $label = is_array($arr) ? implode(', ',$arr) : $label;
          }
          $pct = round($c['cnt']/$maxClass*100);
        ?>
        <div class="brgy-item">
          <span class="brgy-name"><?= htmlspecialchars($label) ?></span>
          <div class="brgy-track"><div class="brgy-fill" style="width:<?= $pct ?>%;background:#00796b"></div></div>
          <span class="brgy-count"><?= $c['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($classList)): ?><p class="empty" style="padding:16px">No data.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- FULL LIST WITH EXPORT -->
  <div class="card mt-16">
    <div class="card-header">
      <h3><i class="fas fa-list"></i> Youth Records</h3>
    </div>

    <div style="padding:14px 18px;border-bottom:1px solid #e2e8f0">
      <div class="result-info">
        <span>Showing <strong><?= number_format($totalFiltered) ?></strong> result<?= $totalFiltered!==1?'s':'' ?>
          <?php if ($totalFiltered !== $totalAll): ?>
            (filtered from <?= number_format($totalAll) ?> total)
          <?php endif; ?>
        </span>
        <div class="export-btns">
          <a href="export.php?<?= $exportParams ?>" class="btn-export csv">
            <i class="fas fa-file-csv"></i> Export Filtered (CSV)
          </a>
          <a href="export.php" class="btn-export all">
            <i class="fas fa-download"></i> Export All (CSV)
          </a>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Gender</th>
            <th>Barangay</th><th>Classification</th><th>Education</th>
            <th>Employment</th><th>Status</th><th>Registered</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="11" class="empty">No records match the selected filters.</td></tr>
        <?php else: foreach ($users as $i => $u):
          $cls = $u['youth_classification'] ? json_decode($u['youth_classification'],true) : null;
          $cls = is_array($cls) ? ($cls[0] ?? '—') : ($u['youth_classification'] ?: '—');
        ?>
          <tr>
            <td><?= ($page-1)*$limit+$i+1 ?></td>
            <td><strong><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></strong></td>
            <td style="font-size:.8rem"><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['gender'] ?: '—') ?></td>
            <td><?= htmlspecialchars($u['barangay'] ?: '—') ?></td>
            <td><span class="badge blue"><?= htmlspecialchars($cls) ?></span></td>
            <td style="font-size:.8rem"><?= htmlspecialchars($u['educational_status'] ?: '—') ?></td>
            <td style="font-size:.8rem"><?= htmlspecialchars($u['employment_status'] ?: '—') ?></td>
            <td><span class="status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
            <td style="font-size:.8rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td><a href="view_user.php?id=<?= $u['id'] ?>" class="btn-icon teal" title="View"><i class="fas fa-eye"></i></a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php
      $qBase = http_build_query(array_filter([
          'barangay'=>$fBarangay,'gender'=>$fGender,'classification'=>$fClass,
          'educational'=>$fEduc,'employment'=>$fEmploy,'status'=>$fStatus,
          'month'=>$fMonth,'year'=>$fYear,'search'=>$search
      ]));
      for ($pg=1; $pg<=$pages; $pg++):
      ?>
        <a href="?<?= $qBase ?>&page=<?= $pg ?>" class="page-btn <?= $pg===$page?'active':'' ?>"><?= $pg ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<script>
// Auto-submit on dropdown change
document.querySelectorAll('.filter-grid select').forEach(sel => {
  sel.addEventListener('change', () => document.querySelector('.filter-panel form').submit());
});

// Auto-submit search with debounce (500ms after user stops typing)
let searchTimer;
const searchInput = document.querySelector('.filter-grid input[name="search"]');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.querySelector('.filter-panel form').submit(), 500);
  });
}
</script>
</body>
</html>
