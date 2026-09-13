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

// Get all approved assistance requests for this organization
$eventsStmt = $pdo->prepare("
    SELECT ar.*
    FROM assistance_requests ar
    WHERE ar.organization_id = ?
    AND ar.status IN ('approved', 'completed')
    ORDER BY ar.created_at DESC
");
$eventsStmt->execute([$president['organization_id']]);
$events = $eventsStmt->fetchAll();

// Map the fields we need
foreach ($events as &$event) {
    $event['request_id'] = $event['id'];
    $event['request_status'] = $event['status'];
    $event['event_date'] = $event['scheduled_date'] ?? null;
    $event['event_title'] = $event['title'];
    $event['venue'] = $event['target_venue'] ?? '';
    $event['target_participants'] = $event['participants'] ?? 0;
    $event['budget_amount'] = $event['budget_requested'] ?? 0;
}

// Get statistics
$totalEvents = count($events);
$approvedEvents = count(array_filter($events, fn($e) => $e['request_status'] === 'approved'));
$completedEvents = count(array_filter($events, fn($e) => $e['request_status'] === 'completed'));
$upcomingEvents = count(array_filter($events, fn($e) => $e['request_status'] === 'approved' && $e['event_date'] && strtotime($e['event_date']) >= time()));

// Filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Apply filters
$filteredEvents = $events;

if ($statusFilter !== 'all') {
    $filteredEvents = array_filter($filteredEvents, fn($e) => $e['request_status'] === $statusFilter);
}

if ($search) {
    $filteredEvents = array_filter($filteredEvents, function($e) use ($search) {
        return stripos($e['event_title'], $search) !== false || 
               stripos($e['activity_type'], $search) !== false ||
               stripos($e['venue'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Events – LYDO President Portal</title>
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
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid var(--gray-200);transition:.2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.stat-card.blue .stat-icon{background:var(--blue-pale);color:var(--blue)}
.stat-card.green .stat-icon{background:var(--green-pale);color:var(--green)}
.stat-card.orange .stat-icon{background:var(--orange-pale);color:var(--orange)}
.stat-card.purple .stat-icon{background:var(--purple-pale);color:var(--purple)}
.stat-val{display:block;font-size:1.7rem;font-weight:800;color:var(--gray-800);line-height:1}
.stat-lbl{font-size:.75rem;color:var(--gray-600);font-weight:500;margin-top:3px;display:block}
.toolbar{background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.search-wrap{position:relative;flex:1;min-width:200px}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:.82rem}
.search-wrap input{width:100%;padding:9px 13px 9px 34px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;outline:none;background:var(--gray-50);transition:.2s}
.search-wrap input:focus{border-color:var(--blue-light);background:#fff}
.filter-sel{padding:9px 13px;border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;outline:none;background:var(--gray-50);color:var(--gray-800);cursor:pointer}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--blue);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none}
.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px)}
.btn-secondary{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:var(--gray-100);color:var(--gray-700);border:1.5px solid var(--gray-200);border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none}
.btn-secondary:hover{background:var(--gray-200)}
.event-card{background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:18px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:.2s}
.event-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);border-color:var(--gray-400)}
.event-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:12px}
.event-title{font-size:1.05rem;font-weight:700;color:var(--gray-800);margin:0 0 8px 0}
.event-meta{display:flex;gap:14px;flex-wrap:wrap;font-size:.8rem;color:var(--gray-600)}
.event-meta-item{display:flex;align-items:center;gap:5px}
.event-meta-item i{color:var(--gray-400);font-size:.75rem}
.badge{display:inline-block;padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700}
.badge.green{background:var(--green-pale);color:var(--green)}
.badge.purple{background:var(--purple-pale);color:var(--purple)}
.event-actions{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
.view-btn{padding:7px 14px;background:#fff;color:var(--blue);border:1.5px solid var(--blue);border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:.2s}
.view-btn:hover{background:var(--blue-pale)}
.event-description{margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);font-size:.82rem;color:var(--gray-600);line-height:1.5}
.event-description strong{color:var(--gray-800)}
.empty{text-align:center;color:var(--gray-400);padding:60px 28px;background:#fff;border:1px solid var(--gray-200);border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.empty i{font-size:3rem;margin-bottom:12px;opacity:.3}
.empty h3{font-size:1.05rem;font-weight:700;color:var(--gray-600);margin:0 0 8px 0}
.empty p{font-size:.85rem;color:var(--gray-500);margin:0}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.toolbar{flex-direction:column;align-items:stretch}.search-wrap{min-width:100%}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr}.event-header{flex-direction:column;align-items:flex-start}.event-actions{flex-direction:row;width:100%;justify-content:space-between}}
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
      <h1 class="welcome-title">Events</h1>
      <div class="welcome-subtitle">
        <span style="margin-right:10px">View approved assistance requests for</span>
        <span class="org-badge">
          <i class="fas fa-calendar"></i>
          <?= htmlspecialchars($organization['name']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fas fa-calendar"></i></div>
      <div><span class="stat-val"><?= $totalEvents ?></span><span class="stat-lbl">Total Events</span></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
      <div><span class="stat-val"><?= $upcomingEvents ?></span><span class="stat-lbl">Upcoming</span></div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div><span class="stat-val"><?= $approvedEvents ?></span><span class="stat-lbl">Approved</span></div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
      <div><span class="stat-val"><?= $completedEvents ?></span><span class="stat-lbl">Completed</span></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" style="display:contents">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search events...">
      </div>
      <select name="status" class="filter-sel">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
      </select>
      <button type="submit" class="btn-primary">
        <i class="fas fa-filter"></i> Filter
      </button>
      <?php if ($search || $statusFilter !== 'all'): ?>
      <a href="events.php" class="btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Events List -->
  <?php if (empty($filteredEvents)): ?>
    <div class="empty">
      <i class="fas fa-calendar"></i>
      <h3>No events found</h3>
      <p>
        <?php if ($search || $statusFilter !== 'all'): ?>
          Try adjusting your filters or search terms.
        <?php else: ?>
          Approved assistance requests will appear here as events.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($filteredEvents as $event): ?>
      <div class="event-card">
        <div class="event-header">
          <div style="flex:1">
            <h3 class="event-title"><?= htmlspecialchars($event['event_title'] ?? '') ?></h3>
            <div class="event-meta">
              <div class="event-meta-item">
                <i class="fas fa-tag"></i>
                <span><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></span>
              </div>
              <?php if ($event['event_date'] ?? null): ?>
              <div class="event-meta-item">
                <i class="fas fa-calendar"></i>
                <span><?= date('F j, Y', strtotime($event['event_date'])) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($event['venue'] ?? null): ?>
              <div class="event-meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($event['venue']) ?></span>
              </div>
              <?php endif; ?>
              <div class="event-meta-item">
                <i class="fas fa-users"></i>
                <span><?= htmlspecialchars($event['target_participants'] ?? '0') ?> participants</span>
              </div>
              <?php if ($event['budget_amount'] ?? null): ?>
              <div class="event-meta-item">
                <i class="fas fa-peso-sign"></i>
                <span>₱<?= number_format($event['budget_amount'], 2) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="event-actions">
            <?php if (($event['request_status'] ?? '') === 'approved'): ?>
              <span class="badge green">Approved</span>
            <?php elseif (($event['request_status'] ?? '') === 'completed'): ?>
              <span class="badge purple">Completed</span>
            <?php endif; ?>
            <a href="assistance.php?view=<?= $event['request_id'] ?? 0 ?>" class="view-btn">
              <i class="fas fa-eye"></i> View Details
            </a>
          </div>
        </div>
        
        <?php if ($event['objectives'] ?? null): ?>
        <div class="event-description">
          <strong>Objectives:</strong> 
          <?= htmlspecialchars(substr($event['objectives'], 0, 200)) ?>
          <?= strlen($event['objectives']) > 200 ? '...' : '' ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>
</div>

</body>
</html>
