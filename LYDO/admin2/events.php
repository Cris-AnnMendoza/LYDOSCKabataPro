<?php
require_once 'config.php';
require_once 'merit_engine.php';
requireLogin();

$pdo   = db();
$admin = currentAdmin();
$tab   = $_GET['tab'] ?? 'list';

// ── FORCE CREATE event_attendance table (CRITICAL) ──────────
try {
    // First, check if table exists but missing organization_id column
    $checkCol = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'organization_id'");
    if ($checkCol && $checkCol->rowCount() === 0) {
        // Table exists but missing column - ALTER it
        $pdo->exec("ALTER TABLE event_attendance ADD COLUMN organization_id INT UNSIGNED NOT NULL AFTER event_id");
        $pdo->exec("ALTER TABLE event_attendance ADD UNIQUE KEY uq_event_org (event_id, organization_id)");
    }
} catch (PDOException $e) {
    // Table doesn't exist - create it
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS event_attendance (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id        INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NOT NULL,
            status          ENUM('present','absent','excused','not_recorded') NOT NULL DEFAULT 'not_recorded',
            recorded_by     INT UNSIGNED DEFAULT NULL,
            recorded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_org (event_id, organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e2) {
        die("CRITICAL ERROR: Cannot create event_attendance table: " . $e2->getMessage());
    }
}

// ── Auto-migrate: add missing columns and tables ──────────
// Only for MySQL - PostgreSQL/Supabase tables already exist
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
// Using separate exec() calls — no FK constraints to avoid silent failures

// Tables (no foreign keys for compatibility)
$pdo->exec("CREATE TABLE IF NOT EXISTS event_checkins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    checked_in_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    UNIQUE KEY uq_event_user_checkin (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS event_certificates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    cert_number     VARCHAR(50)  NOT NULL,
    generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_at     DATETIME     DEFAULT NULL,
    upload_filename VARCHAR(255) DEFAULT NULL,
    upload_original VARCHAR(255) DEFAULT NULL,
    merit_awarded   TINYINT(1)   NOT NULL DEFAULT 0,
    merit_points    TINYINT      NOT NULL DEFAULT 2,
    UNIQUE KEY uq_event_user_cert (event_id, user_id),
    UNIQUE KEY uq_cert_number (cert_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_merit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    points     SMALLINT     NOT NULL,
    type       ENUM('merit','demerit') NOT NULL,
    reason     VARCHAR(255) NOT NULL,
    category   VARCHAR(100) DEFAULT NULL,
    event_id   INT UNSIGNED DEFAULT NULL,
    cert_id    INT UNSIGNED DEFAULT NULL,
    awarded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    message    TEXT         NOT NULL,
    type       VARCHAR(50)  DEFAULT 'info',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS org_merit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    points          SMALLINT     NOT NULL,
    type            ENUM('merit','demerit') NOT NULL,
    reason          VARCHAR(255) NOT NULL,
    category        VARCHAR(100) DEFAULT NULL,
    event_id        INT UNSIGNED DEFAULT NULL,
    awarded_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS org_warning_letters (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    reason          TEXT         NOT NULL,
    level           ENUM('warning','show_cause','revocation_flag') NOT NULL DEFAULT 'warning',
    issued_by       INT UNSIGNED DEFAULT NULL,
    issued_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS org_explanation_letters (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    content         TEXT         NOT NULL,
    status          ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by     INT UNSIGNED DEFAULT NULL,
    reviewed_at     DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS event_attendance (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    status          ENUM('present','absent','excused','not_recorded') NOT NULL DEFAULT 'not_recorded',
    recorded_by     INT UNSIGNED DEFAULT NULL,
    recorded_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_org (event_id, organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

} // End MySQL-only migration

// Force create event_attendance for BOTH MySQL and Supabase environments (if missing)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_attendance (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id        INT UNSIGNED NOT NULL,
        organization_id INT UNSIGNED NOT NULL,
        status          ENUM('present','absent','excused','not_recorded') NOT NULL DEFAULT 'not_recorded',
        recorded_by     INT UNSIGNED DEFAULT NULL,
        recorded_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_org (event_id, organization_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Show error for debugging
    error_log("Failed to create event_attendance table: " . $e->getMessage());
}

// MySQL-specific: Auto-generate checkin_code for events that don't have one
// Columns (duplicate = already exists, safe to ignore)
foreach ([
    "ALTER TABLE events ADD COLUMN event_type VARCHAR(50) NOT NULL DEFAULT 'official_event'",
    "ALTER TABLE events ADD COLUMN requires_representative TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE events ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN checkin_open TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE events ADD COLUMN merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2",
    "ALTER TABLE events ADD COLUMN checkin_code VARCHAR(6) DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN event_start_time TIME DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN event_end_time TIME DEFAULT NULL",
    "ALTER TABLE events ADD COLUMN checkout_open TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE youth_users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'approved'",
] as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* already exists */ }
}

// Auto-generate checkin_code for events that don't have one (MySQL only)
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
    $pdo->exec("UPDATE events SET checkin_code = UPPER(SUBSTRING(MD5(CONCAT(id, IFNULL(qr_token,'x'))), 1, 6)) WHERE checkin_code IS NULL OR checkin_code = ''");
}

// ── Event handlers ───────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_event') {
        // Generate unique QR token and 6-char checkin code
        $qrToken     = bin2hex(random_bytes(16));
        $checkinCode = strtoupper(substr(md5($qrToken), 0, 6));
        $pdo->prepare(
            'INSERT INTO events (title, event_type, requires_representative, description, event_date, location, organization_id, merit_points, quarter, year, created_by, qr_token, checkin_open, checkin_code, event_start_time, event_end_time, checkout_open)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,TRUE,?,?,?,FALSE)'
        )->execute([
            trim($_POST['title']),
            $_POST['event_type'] ?? 'official_event',
            isset($_POST['requires_representative']) ? TRUE : FALSE,
            trim($_POST['description'] ?? ''),
            $_POST['event_date'],
            trim($_POST['location'] ?? ''),
            $_POST['organization_id'] ?: null,
            (int)($_POST['merit_points'] ?? 2),
            $_POST['quarter'] ?: null,
            $_POST['year'] ?: date('Y'),
            $admin['id'],
            $qrToken,
            $checkinCode,
            $_POST['event_start_time'] ?: null,
            $_POST['event_end_time'] ?: null,
        ]);
        flash('success', 'Event created successfully. Check-in code is ready.');
        header('Location: events.php'); exit;
    }

    if ($action === 'edit_event') {
        $eid = (int)$_POST['event_id'];
        $pdo->prepare(
            'UPDATE events SET title=?, event_type=?, requires_representative=?, description=?, event_date=?, location=?, organization_id=?, merit_points=?, quarter=?, year=?, event_start_time=?, event_end_time=? WHERE id=?'
        )->execute([
            trim($_POST['title']),
            $_POST['event_type'] ?? 'official_event',
            isset($_POST['requires_representative']) ? TRUE : FALSE,
            trim($_POST['description'] ?? ''),
            $_POST['event_date'],
            trim($_POST['location'] ?? ''),
            $_POST['organization_id'] ?: null,
            (int)($_POST['merit_points'] ?? 2),
            $_POST['quarter'] ?: null,
            $_POST['year'] ?: date('Y'),
            $_POST['event_start_time'] ?: null,
            $_POST['event_end_time'] ?: null,
            $eid,
        ]);
        flash('success', 'Event updated successfully.');
        header('Location: events.php'); exit;
    }

    if ($action === 'delete_event') {
        $eid = (int)$_POST['event_id'];
        $pdo->prepare('DELETE FROM events WHERE id=?')->execute([$eid]);
        flash('success', 'Event deleted.');
        header('Location: events.php'); exit;
    }

    if ($action === 'toggle_checkin') {
        $eid = (int)$_POST['event_id'];
        $cur = $pdo->prepare('SELECT checkin_open FROM events WHERE id=?');
        $cur->execute([$eid]);
        $row = $cur->fetch();
        $newState = $row ? (!$row['checkin_open'] ? TRUE : FALSE) : TRUE;
        $pdo->prepare('UPDATE events SET checkin_open=? WHERE id=?')->execute([$newState, $eid]);
        flash('success', 'Check-in ' . ($newState ? 'opened' : 'closed') . '.');
        header('Location: events.php'); exit;
    }

    if ($action === 'toggle_checkout') {
        $eid = (int)$_POST['event_id'];
        $cur = $pdo->prepare('SELECT checkout_open FROM events WHERE id=?');
        $cur->execute([$eid]);
        $row = $cur->fetch();
        $newState = $row ? (!$row['checkout_open'] ? TRUE : FALSE) : TRUE;
        $pdo->prepare('UPDATE events SET checkout_open=? WHERE id=?')->execute([$newState, $eid]);
        flash('success', 'Check-out ' . ($newState ? 'opened' : 'closed') . '.');
        header('Location: events.php'); exit;
    }

    if ($action === 'record_attendance') {
        $eventId    = (int)$_POST['event_id'];
        $attendance = $_POST['attendance'] ?? [];  // [user_id => status]

        // Handle "no representative" for orgs that didn't send anyone
        $noRepOrgs = $_POST['no_rep_orgs'] ?? [];
        foreach ($noRepOrgs as $userId) {
            $attendance[(int)$userId] = 'absent';
            // Also apply no-representative demerit
            awardPoints($pdo, (int)$userId, 'no_representative', $admin['id'], $eventId);
        }

        $result = processEventAttendance($pdo, $eventId, $attendance, $admin['id']);

        $triggered = [];
        foreach ($result['results'] ?? [] as $r) {
            $triggered = array_merge($triggered, $r['consequences'] ?? []);
        }

        $msg = 'Attendance recorded. Points applied automatically.';
        if ($triggered) {
            $msg .= ' Auto-sanctions triggered: ' . implode(', ', array_unique($triggered)) . '.';
        }
        flash('success', $msg);
        header('Location: events.php?tab=attendance&event_id=' . $eventId); exit;
    }

    if ($action === 'award_manual') {
        $orgId   = (int)$_POST['org_id'];
        $ruleKey = $_POST['rule_key'] ?? '';
        if ($orgId && $ruleKey && isset(MERIT_RULES[$ruleKey])) {
            $result = awardOrgPoints($pdo, $orgId, $ruleKey, $admin['id']);
            $msg = 'Points applied: ' . MERIT_RULES[$ruleKey]['label']
                 . ' (' . ($result['points'] > 0 ? '+' : '') . $result['points'] . ')';
            if (!empty($result['consequences'])) {
                $msg .= ' | Auto-sanctions: ' . implode(', ', $result['consequences']);
            }
            flash('success', $msg);
        }
        header('Location: events.php?tab=manual'); exit;
    }
}

// ── Data ──────────────────────────────────────────────────
$events = $pdo->query(
    'SELECT e.*, o.name as org_name,
     (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id = e.id) as recorded,
     (SELECT COUNT(*) FROM event_checkins c WHERE c.event_id = e.id) as checkin_count
     FROM events e LEFT JOIN organizations o ON o.id = e.organization_id
     ORDER BY e.event_date DESC'
)->fetchAll();

$orgs      = $pdo->query('SELECT id, name FROM organizations WHERE is_active = TRUE ORDER BY name')->fetchAll();
$youthList = $pdo->query("SELECT id, first_name, last_name FROM youth_users WHERE status='approved' ORDER BY first_name")->fetchAll();

// Attendance tab
$selectedEvent = null;
$attendanceList = [];
if ($tab === 'attendance' && isset($_GET['event_id'])) {
    $eid = (int)$_GET['event_id'];
    $selectedEvent = $pdo->prepare('SELECT * FROM events WHERE id=?');
    $selectedEvent->execute([$eid]);
    $selectedEvent = $selectedEvent->fetch();

    // Get individual youth members who checked in/out for this event
    $attendanceList = $pdo->prepare(
        'SELECT y.id, y.first_name, y.last_name, y.organization_name,
                c.checked_in_at, c.checked_out_at,
                c.checkin_photo, c.checkout_photo,
                o.id as org_id, o.name as org_name_formal, o.category as org_category,
                cert.cert_number, cert.merit_awarded,
                (SELECT created_at FROM org_merit_logs WHERE organization_id=o.id AND event_id=? AND category="event_attendance" LIMIT 1) as org_merit_awarded_at
         FROM event_checkins c
         JOIN youth_users y ON y.id = c.user_id
         LEFT JOIN organizations o ON o.name = y.organization_name
         LEFT JOIN event_certificates cert ON cert.event_id = c.event_id AND cert.user_id = y.id
         WHERE c.event_id = ?
         ORDER BY c.checked_out_at DESC, o.name ASC, y.last_name ASC'
    );
    $attendanceList->execute([$eid, $eid]);
    $attendanceList = $attendanceList->fetchAll();
    
    // Get summary stats
    $checkinCount = $pdo->prepare('SELECT COUNT(*) FROM event_checkins WHERE event_id=?');
    $checkinCount->execute([$eid]);
    $totalCheckins = (int)$checkinCount->fetchColumn();
    
    $checkoutCount = $pdo->prepare('SELECT COUNT(*) FROM event_checkins WHERE event_id=? AND checked_out_at IS NOT NULL');
    $checkoutCount->execute([$eid]);
    $totalCheckouts = (int)$checkoutCount->fetchColumn();
    
    $orgCount = $pdo->prepare('SELECT COUNT(DISTINCT o.id) FROM event_checkins c JOIN youth_users y ON y.id=c.user_id LEFT JOIN organizations o ON o.name=y.organization_name WHERE c.event_id=? AND o.id IS NOT NULL');
    $orgCount->execute([$eid]);
    $totalOrgs = (int)$orgCount->fetchColumn();
}

$eventTypeLabels = [
    'official_event'  => 'Official MYDC/LYDO Event',
    'meeting_patawag' => 'Meeting / Patawag',
    'invitation'      => 'Fellow Org Invitation',
    'other'           => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Events & Attendance – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.att-row{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.att-row:last-child{border-bottom:none}
.att-row:hover{background:#f8fafc}
.att-header{background:#f1f5f9;font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#475569;border-radius:8px 8px 0 0}
.att-radio{display:flex;gap:8px;flex-wrap:wrap}
.att-radio label{display:flex;align-items:center;gap:4px;cursor:pointer;font-size:.8rem;padding:4px 8px;border-radius:6px;border:1.5px solid #e2e8f0;transition:.2s}
.att-radio input{display:none}
.att-radio input:checked+span{font-weight:700}
.att-radio label:has(input[value="present"]:checked){background:#e8f5e9;border-color:#a5d6a7;color:#2e7d32}
.att-radio label:has(input[value="absent"]:checked){background:#ffebee;border-color:#ef9a9a;color:#c62828}
.att-radio label:has(input[value="excused"]:checked){background:#fff8e1;border-color:#ffe082;color:#f57f17}
.att-radio label:has(input[value="late"]:checked){background:#e3f2fd;border-color:#90caf9;color:#1565c0}
.att-radio label:has(input[value="representative"]:checked){background:#f3e5f5;border-color:#ce93d8;color:#7b1fa2}
.rule-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
.rule-pts{font-size:1.1rem;font-weight:800;min-width:40px;text-align:right}
.pts-pos{color:#2e7d32}
.pts-neg{color:#c62828}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="page-header">
  <div><h2><i class="fas fa-calendar-alt" style="color:#1565c0;margin-right:8px"></i>Events Management</h2><p>Create and manage LYDO events. Youth members use the check-in code to attend.</p></div>
  <?php if ($tab==='list'): ?>
  <button class="btn-primary" onclick="document.getElementById('addEventModal').style.display='flex'">
    <i class="fas fa-plus"></i> Add Event
  </button>
  <?php endif; ?>
</div>

<div class="tabs">
  <a href="?tab=list"       class="tab-btn <?=$tab==='list'?'active':''?>"><i class="fas fa-calendar-alt"></i> Events</a>
  <a href="?tab=attendance" class="tab-btn <?=$tab==='attendance'?'active':''?>"><i class="fas fa-clipboard-check"></i> Record Attendance</a>
  <a href="?tab=manual"     class="tab-btn <?=$tab==='manual'?'active':''?>"><i class="fas fa-hand-pointer"></i> Manual Award</a>
  <a href="?tab=rules"      class="tab-btn <?=$tab==='rules'?'active':''?>"><i class="fas fa-book"></i> Point Rules</a>
</div>

<?php if ($tab === 'list'): ?>
<!-- EVENTS LIST -->
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Event Title</th>
          <th>Type</th>
          <th>Date</th>
          <th>Location</th>
          <th>Merit Pts</th>
          <th>Check-ins</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($events)): ?>
        <tr><td colspan="9" class="empty">No events yet. Click <strong>Add Event</strong> to create one.</td></tr>
      <?php else: foreach ($events as $i => $ev): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td>
            <strong><?= htmlspecialchars($ev['title']) ?></strong>
            <?php if ($ev['description']): ?>
              <br><small style="color:#94a3b8"><?= htmlspecialchars(substr($ev['description'],0,50)) ?>...</small>
            <?php endif; ?>
          </td>
          <td><span class="badge blue"><?= htmlspecialchars($eventTypeLabels[$ev['event_type']] ?? $ev['event_type']) ?></span></td>
          <td><?= date('M j, Y', strtotime($ev['event_date'])) ?><?= $ev['event_time'] ? '<br><small style="color:#94a3b8">'.date('g:i A',strtotime($ev['event_time'])).'</small>' : '' ?></td>
          <td><?= htmlspecialchars($ev['location'] ?: '—') ?></td>
          <td><strong style="color:#2e7d32">+<?= $ev['merit_points'] ?></strong></td>
          <td>
            <strong><?= (int)($ev['checkin_count'] ?? 0) ?></strong>
            <span style="font-size:.75rem;color:#94a3b8"> attendees</span>
          </td>
          <td>
            <?php if ($ev['checkin_open']): ?>
              <span class="badge green">Open</span>
            <?php else: ?>
              <span class="badge gray">Closed</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <!-- Show QR Codes (Check-in & Check-out) -->
            <button class="btn-icon teal" title="Show QR Codes" onclick="showQRModal(<?= $ev['id'] ?>, '<?= htmlspecialchars($ev['title']) ?>', '<?= $ev['qr_token'] ?>', <?= $ev['checkin_open'] ? 'true' : 'false' ?>, <?= ($ev['checkout_open'] ?? false) ? 'true' : 'false' ?>)">
              <i class="fas fa-qrcode"></i>
            </button>
            <!-- Toggle check-in -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_checkin"/>
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>"/>
              <button type="submit" class="btn-icon <?= $ev['checkin_open'] ? 'orange' : 'green' ?>" title="<?= $ev['checkin_open'] ? 'Close Check-in' : 'Open Check-in' ?>">
                <i class="fas fa-<?= $ev['checkin_open'] ? 'lock-open' : 'lock' ?>"></i>
              </button>
            </form>
            <!-- Toggle check-out -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_checkout"/>
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>"/>
              <button type="submit" class="btn-icon <?= ($ev['checkout_open'] ?? false) ? 'red' : '' ?>" style="<?= !($ev['checkout_open'] ?? false) ? 'color:#f57f17;background:#fff8e1' : '' ?>" title="<?= ($ev['checkout_open'] ?? false) ? 'Close Check-out' : 'Open Check-out Now' ?>">
                <i class="fas fa-sign-out-alt"></i>
              </button>
            </form>
            <!-- Edit -->
            <button class="btn-icon edit" title="Edit Event"
              onclick="openEditEvent(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)">
              <i class="fas fa-edit"></i>
            </button>
            <!-- Delete -->
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this event? All check-ins and certificates will also be deleted.')">
              <input type="hidden" name="action" value="delete_event"/>
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>"/>
              <button type="submit" class="btn-icon red" title="Delete Event"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'attendance'): ?>
<!-- ATTENDANCE RECORDING -->
<?php if (!$selectedEvent): ?>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Select an Event</h3></div>
    <div style="padding:16px 18px;display:flex;flex-direction:column;gap:8px">
      <?php foreach ($events as $ev): ?>
        <a href="?tab=attendance&event_id=<?=$ev['id']?>" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:10px;text-decoration:none;color:#1e293b;transition:.2s" onmouseover="this.style.borderColor='#1565c0'" onmouseout="this.style.borderColor='#e2e8f0'">
          <div><strong><?=htmlspecialchars($ev['title'])?></strong> <span class="badge blue" style="margin-left:8px"><?=htmlspecialchars($eventTypeLabels[$ev['event_type']]??'')?></span></div>
          <span style="font-size:.82rem;color:#94a3b8"><?=date('M j, Y',strtotime($ev['event_date']))?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header" style="background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff">
      <h3 style="color:#fff"><i class="fas fa-users"></i> <?=htmlspecialchars($selectedEvent['title'])?> - Attendance List</h3>
      <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)"><?=htmlspecialchars($eventTypeLabels[$selectedEvent['event_type']]??'')?></span>
    </div>
    <div style="padding:12px 18px;display:flex;gap:24px;flex-wrap:wrap;font-size:.85rem;color:#475569;background:#f8fafc">
      <span><i class="fas fa-calendar" style="margin-right:5px;color:#1565c0"></i><?=date('F j, Y',strtotime($selectedEvent['event_date']))?></span>
      <span><i class="fas fa-map-marker-alt" style="margin-right:5px;color:#1565c0"></i><?=htmlspecialchars($selectedEvent['location']?:'—')?></span>
      <span><i class="fas fa-star" style="margin-right:5px;color:#2e7d32"></i>+<?=$selectedEvent['merit_points']?> merit pts</span>
    </div>
    
    <!-- Summary Stats -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:16px 18px;background:#fff;border-top:1px solid #e2e8f0">
      <div style="text-align:center;padding:12px;background:#e3f2fd;border-radius:10px">
        <div style="font-size:1.8rem;font-weight:800;color:#1565c0"><?=$totalCheckins?></div>
        <div style="font-size:.78rem;color:#0d47a1;font-weight:600">Total Check-ins</div>
      </div>
      <div style="text-align:center;padding:12px;background:#e8f5e9;border-radius:10px">
        <div style="font-size:1.8rem;font-weight:800;color:#2e7d32"><?=$totalCheckouts?></div>
        <div style="font-size:.78rem;color:#1b5e20;font-weight:600">Checked Out</div>
      </div>
      <div style="text-align:center;padding:12px;background:#f3e5f5;border-radius:10px">
        <div style="font-size:1.8rem;font-weight:800;color:#7b1fa2"><?=$totalOrgs?></div>
        <div style="font-size:.78rem;color:#6a1b9a;font-weight:600">Organizations</div>
      </div>
    </div>
  </div>

    <div class="card">
      <div class="att-row att-header">
        <div style="grid-column:span 2">Name</div><div>Organization</div><div>Status & Photos</div>
      </div>
      <?php if (empty($attendanceList)): ?>
        <div style="padding:40px;text-align:center;color:#94a3b8">
          <i class="fas fa-info-circle" style="font-size:2rem;margin-bottom:12px;display:block"></i>
          <strong style="display:block;margin-bottom:6px;color:#475569">No attendance records yet</strong>
          <p>Youth members will appear here after they check in using the event code.</p>
        </div>
      <?php else: ?>
      <?php 
        $lastOrg = null;
        foreach ($attendanceList as $u): 
          $fullName = trim($u['first_name'] . ' ' . $u['last_name']);
          $orgName = $u['organization_name'] ?: 'No Organization';
          $hasCheckedOut = !empty($u['checked_out_at']);
          $hasOrgMerit = !empty($u['org_merit_awarded_at']);
          
          // Show org header if changed
          if ($lastOrg !== $orgName && $u['org_id']):
            if ($lastOrg !== null) echo '<div style="height:1px;background:#e2e8f0;margin:8px 16px"></div>';
            $lastOrg = $orgName;
      ?>
        <div style="padding:10px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
          <div>
            <strong style="color:#1565c0;font-size:.9rem">
              <i class="fas fa-building"></i> <?=htmlspecialchars($orgName)?>
            </strong>
            <?php if ($u['org_category']): ?>
              <span class="badge blue" style="margin-left:8px;font-size:.68rem"><?=htmlspecialchars($u['org_category'])?></span>
            <?php endif; ?>
          </div>
          <?php if ($hasOrgMerit): ?>
            <span class="badge green" style="font-size:.72rem">
              <i class="fas fa-check-circle"></i> Org Merit Awarded
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      
      <div class="att-row">
        <div style="grid-column:span 2">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#1e88e5);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">
              <?=strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))?>
            </div>
            <div>
              <strong style="font-size:.9rem"><?=htmlspecialchars($fullName)?></strong>
              <div style="font-size:.72rem;color:#94a3b8;margin-top:2px">
                <i class="fas fa-clock"></i> In: <?=date('g:i A', strtotime($u['checked_in_at']))?>
                <?php if ($hasCheckedOut): ?>
                  | Out: <?=date('g:i A', strtotime($u['checked_out_at']))?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div>
          <?php if ($orgName === 'No Organization'): ?>
            <span style="color:#94a3b8;font-size:.82rem;font-style:italic">No organization</span>
          <?php else: ?>
            <span style="color:#475569;font-size:.82rem"><?=htmlspecialchars($orgName)?></span>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($hasCheckedOut): ?>
            <span class="badge green"><i class="fas fa-check-double"></i> Completed</span>
            <?php if ($u['cert_number']): ?>
              <div style="font-size:.68rem;color:#2e7d32;margin-top:4px;font-weight:600">
                <i class="fas fa-certificate"></i> <?=htmlspecialchars($u['cert_number'])?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge orange"><i class="fas fa-hourglass-half"></i> Checked In</span>
            <div style="font-size:.68rem;color:#f57f17;margin-top:4px">Waiting for checkout</div>
          <?php endif; ?>
          
          <!-- Photo Display -->
          <div style="display:flex;gap:8px;margin-top:8px">
            <?php if (!empty($u['checkin_photo'])): ?>
              <div style="text-align:center">
                <div style="font-size:.65rem;color:#64748b;margin-bottom:2px;font-weight:600">Check-in</div>
                <img src="../uploads/event_photos/<?=htmlspecialchars($u['checkin_photo'])?>" 
                     alt="Check-in photo" 
                     style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0;cursor:pointer;transition:.2s"
                     onclick="showPhotoModal(this.src)"
                     onmouseover="this.style.borderColor='#1565c0'"
                     onmouseout="this.style.borderColor='#e2e8f0'">
              </div>
            <?php endif; ?>
            
            <?php if (!empty($u['checkout_photo'])): ?>
              <div style="text-align:center">
                <div style="font-size:.65rem;color:#64748b;margin-bottom:2px;font-weight:600">Check-out</div>
                <img src="../uploads/event_photos/<?=htmlspecialchars($u['checkout_photo'])?>" 
                     alt="Check-out photo" 
                     style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0;cursor:pointer;transition:.2s"
                     onclick="showPhotoModal(this.src)"
                     onmouseover="this.style.borderColor='#1565c0'"
                     onmouseout="this.style.borderColor='#e2e8f0'">
              </div>
            <?php endif; ?>
            
            <?php if (empty($u['checkin_photo']) && empty($u['checkout_photo'])): ?>
              <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#f8fafc;border-radius:6px;font-size:.72rem;color:#94a3b8">
                <i class="fas fa-image"></i> No photos
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:12px;padding:16px;margin-top:16px;display:flex;align-items:start;gap:12px">
      <i class="fas fa-info-circle" style="color:#1565c0;font-size:1.2rem;flex-shrink:0;margin-top:2px"></i>
      <div style="font-size:.85rem;color:#0d47a1;line-height:1.6">
        <strong style="display:block;margin-bottom:4px">Real-time Attendance Tracking</strong>
        <ul style="margin:0;padding-left:20px">
          <li>List automatically updates when youth members check in/out using event codes</li>
          <li><strong>Organization merit points</strong> are automatically awarded when the first member checks out</li>
          <li>Individual youth members earn their personal merit points on checkout</li>
        </ul>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px">
      <a href="?tab=list" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Events</a>
    </div>
<?php endif; ?>

<?php elseif ($tab === 'manual'): ?>
<!-- MANUAL AWARD -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-hand-pointer"></i> Manual Point Award</h3></div>
  <form method="POST" class="modal-body" style="padding:20px">
    <input type="hidden" name="action" value="award_manual"/>
    <div class="form-row-2">
      <div class="fg"><label>Organization <span class="req">*</span></label>
        <select name="org_id" required>
          <option value="">Select organization...</option>
          <?php foreach ($orgs as $o): ?>
            <option value="<?=$o['id']?>"><?=htmlspecialchars($o['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label>Rule / Reason <span class="req">*</span></label>
        <select name="rule_key" required>
          <option value="">Select rule...</option>
          <optgroup label="Merit (+)">
            <?php foreach (MERIT_RULES as $key => $rule): if ($rule['type']==='merit'): ?>
              <option value="<?=$key?>"><?=$rule['label']?> (+<?=$rule['points']?>)</option>
            <?php endif; endforeach; ?>
          </optgroup>
          <optgroup label="Demerit (-)">
            <?php foreach (MERIT_RULES as $key => $rule): if ($rule['type']==='demerit'): ?>
              <option value="<?=$key?>"><?=$rule['label']?> (<?=$rule['points']?>)</option>
            <?php endif; endforeach; ?>
          </optgroup>
        </select>
      </div>
    </div>
    <button type="submit" class="btn-primary" style="width:auto;padding:11px 28px">
      <i class="fas fa-bolt"></i> Apply Points
    </button>
  </form>
</div>

<?php elseif ($tab === 'rules'): ?>
<!-- POINT RULES REFERENCE -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-star" style="color:#2e7d32"></i> Merit Rules</h3></div>
    <div style="padding:14px 16px">
      <?php foreach (MERIT_RULES as $key => $rule): if ($rule['type']==='merit'): ?>
      <div class="rule-card">
        <div>
          <div style="font-weight:600;font-size:.88rem"><?=htmlspecialchars($rule['label'])?></div>
          <div style="font-size:.75rem;color:#94a3b8;margin-top:2px">Rule: <?=$key?></div>
        </div>
        <div class="rule-pts pts-pos">+<?=$rule['points']?></div>
      </div>
      <?php endif; endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-minus-circle" style="color:#c62828"></i> Demerit Rules</h3></div>
    <div style="padding:14px 16px">
      <?php foreach (MERIT_RULES as $key => $rule): if ($rule['type']==='demerit'): ?>
      <div class="rule-card">
        <div>
          <div style="font-weight:600;font-size:.88rem"><?=htmlspecialchars($rule['label'])?></div>
          <div style="font-size:.75rem;color:#94a3b8;margin-top:2px">Rule: <?=$key?></div>
        </div>
        <div class="rule-pts pts-neg"><?=$rule['points']?></div>
      </div>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div>
<div class="card mt-16">
  <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:#f57f17"></i> Automated Consequences</h3></div>
  <div style="padding:14px 16px">
    <?php foreach (DEMERIT_THRESHOLDS as $pts => $c): ?>
    <div class="rule-card">
      <div>
        <div style="font-weight:700;font-size:.9rem"><?=htmlspecialchars($c['label'])?></div>
        <div style="font-size:.78rem;color:#94a3b8;margin-top:2px">Auto-generated when total demerit reaches <?=$pts?> points</div>
      </div>
      <div class="rule-pts pts-neg"><?=$pts?>pts</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</main>
</div>

<!-- ADD EVENT MODAL -->
<div id="addEventModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:620px;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head"><h3><i class="fas fa-calendar-plus"></i> Add Event</h3><button onclick="document.getElementById('addEventModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body" style="overflow-y:auto;flex:1;padding:20px">
      <input type="hidden" name="action" value="add_event"/>
      <div class="form-row-2">
        <div class="fg"><label>Event Title <span class="req">*</span></label><input type="text" name="title" required placeholder="e.g. Youth Leadership Summit 2026"/></div>
        <div class="fg"><label>Event Type <span class="req">*</span></label>
          <select name="event_type" required>
            <?php foreach ($eventTypeLabels as $val => $lbl): ?>
              <option value="<?=$val?>"><?=$lbl?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Date <span class="req">*</span></label><input type="date" name="event_date" required value="<?=date('Y-m-d')?>"/></div>
        <div class="fg"><label>Time</label><input type="time" name="event_time"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Event Start Time <span class="req">*</span></label><input type="time" name="event_start_time" required/></div>
        <div class="fg"><label>Event End Time <span class="req">*</span></label><input type="time" name="event_end_time" required/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Location</label><input type="text" name="location" placeholder="e.g. Municipal Hall"/></div>
        <div class="fg"><label>Organization</label>
          <select name="organization_id">
            <option value="">All / General</option>
            <?php foreach ($orgs as $o): ?><option value="<?=$o['id']?>"><?=htmlspecialchars($o['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Quarter</label>
          <select name="quarter"><option value="">—</option><?php for($q=1;$q<=4;$q++): ?><option value="<?=$q?>">Q<?=$q?></option><?php endfor; ?></select>
        </div>
        <div class="fg"><label>Year</label><input type="number" name="year" value="<?=date('Y')?>" min="2020" max="2030"/></div>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" rows="2" placeholder="Brief description..."></textarea></div>
      <div class="form-row-2">
        <div class="fg"><label>Merit Points <span class="req">*</span></label><input type="number" name="merit_points" value="2" min="1" max="10" required/></div>
        <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:2px"><label class="chk-label"><input type="checkbox" name="requires_representative"/><span class="chk"></span> Requires official representative</label></div>
      </div>
      <div class="modal-footer" style="padding:0;margin-top:16px;border-top:1px solid #e2e8f0;padding-top:14px">
        <button type="button" class="btn-secondary" onclick="document.getElementById('addEventModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Event</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT EVENT MODAL -->
<div id="editEventModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:620px;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head" style="background:linear-gradient(135deg,#2e7d32,#43a047)">
      <h3><i class="fas fa-edit"></i> Edit Event</h3>
      <button onclick="document.getElementById('editEventModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="modal-body" style="overflow-y:auto;flex:1;padding:20px">
      <input type="hidden" name="action" value="edit_event"/>
      <input type="hidden" name="event_id" id="edit_event_id"/>

      <div class="form-row-2">
        <div class="fg"><label>Event Title <span class="req">*</span></label><input type="text" name="title" id="edit_title" required/></div>
        <div class="fg"><label>Event Type</label>
          <select name="event_type" id="edit_event_type">
            <?php foreach ($eventTypeLabels as $val => $lbl): ?>
              <option value="<?=$val?>"><?=$lbl?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Date <span class="req">*</span></label><input type="date" name="event_date" id="edit_event_date" required/></div>
        <div class="fg"><label>Time</label><input type="time" name="event_time" id="edit_event_time"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Event Start Time <span class="req">*</span></label><input type="time" name="event_start_time" id="edit_event_start_time" required/></div>
        <div class="fg"><label>Event End Time <span class="req">*</span></label><input type="time" name="event_end_time" id="edit_event_end_time" required/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Location</label><input type="text" name="location" id="edit_location" placeholder="e.g. Municipal Hall"/></div>
        <div class="fg"><label>Organization</label>
          <select name="organization_id" id="edit_organization_id">
            <option value="">All / General</option>
            <?php foreach ($orgs as $o): ?><option value="<?=$o['id']?>"><?=htmlspecialchars($o['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Quarter</label>
          <select name="quarter" id="edit_quarter">
            <option value="">—</option>
            <?php for($q=1;$q<=4;$q++): ?><option value="<?=$q?>">Q<?=$q?></option><?php endfor; ?>
          </select>
        </div>
        <div class="fg"><label>Year</label><input type="number" name="year" id="edit_year" min="2020" max="2030"/></div>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" id="edit_description" rows="2"></textarea></div>
      <div class="form-row-2">
        <div class="fg"><label>Merit Points <span class="req">*</span></label><input type="number" name="merit_points" id="edit_merit_points" min="1" max="10" required/></div>
        <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:2px">
          <label class="chk-label"><input type="checkbox" name="requires_representative" id="edit_requires_rep"/><span class="chk"></span> Requires official representative</label>
        </div>
      </div>
      <div class="modal-footer" style="padding:0;margin-top:16px;border-top:1px solid #e2e8f0;padding-top:14px">
        <button type="button" class="btn-secondary" onclick="document.getElementById('editEventModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-primary" style="background:linear-gradient(135deg,#2e7d32,#43a047)"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- QR CODE MODAL (Check-in & Check-out) -->
<div id="qrModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:900px">
    <div class="modal-head" style="background:linear-gradient(135deg,#0d3b6e,#1565c0)">
      <h3><i class="fas fa-qrcode"></i> <span id="qrEventTitle">Event QR Codes</span></h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button onclick="toggleFullscreen()" class="btn-icon" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)" title="View Fullscreen">
          <i class="fas fa-expand"></i>
        </button>
        <button onclick="closeQRModal()" class="modal-close"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="modal-body" style="padding:24px">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <!-- Check-in QR Code -->
        <div id="qrCheckinBox" style="background:linear-gradient(135deg,#e3f2fd,#bbdefb);border-radius:12px;padding:20px;border:2px solid #1565c0">
          <div style="font-size:.85rem;font-weight:600;color:#0d47a1;margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em;text-align:center">
            <i class="fas fa-sign-in-alt"></i> Check-In QR Code
          </div>
          
          <!-- Unlocked State -->
          <div id="qrCheckinUnlocked" style="display:none;text-align:center">
            <div id="qrCheckinCanvas" style="background:#fff;padding:15px;border-radius:10px;display:inline-block;margin-bottom:12px"></div>
            <div style="font-size:.75rem;color:#1565c0">
              Scan to check in
            </div>
          </div>
          
          <!-- Locked State -->
          <div id="qrCheckinLocked" style="display:none;text-align:center">
            <div style="font-size:3rem;color:#94a3b8;margin:20px 0">
              <i class="fas fa-lock"></i>
            </div>
            <div style="font-size:.85rem;font-weight:600;color:#64748b">
              Check-in LOCKED
            </div>
            <div style="font-size:.7rem;color:#94a3b8;margin-top:6px">
              Click lock button to unlock
            </div>
          </div>
        </div>

        <!-- Check-out QR Code -->
        <div id="qrCheckoutBox" style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:12px;padding:20px;border:2px solid #2e7d32">
          <div style="font-size:.85rem;font-weight:600;color:#1b5e20;margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em;text-align:center">
            <i class="fas fa-sign-out-alt"></i> Check-Out QR Code
          </div>
          
          <!-- Unlocked State -->
          <div id="qrCheckoutUnlocked" style="display:none;text-align:center">
            <div id="qrCheckoutCanvas" style="background:#fff;padding:15px;border-radius:10px;display:inline-block;margin-bottom:12px"></div>
            <div style="font-size:.75rem;color:#2e7d32">
              Scan to check out
            </div>
          </div>
          
          <!-- Locked State -->
          <div id="qrCheckoutLocked" style="display:none;text-align:center">
            <div style="font-size:3rem;color:#94a3b8;margin:20px 0">
              <i class="fas fa-lock"></i>
            </div>
            <div style="font-size:.85rem;font-weight:600;color:#64748b">
              Check-out LOCKED
            </div>
            <div style="font-size:.7rem;color:#94a3b8;margin-top:6px">
              Click check-out button to unlock
            </div>
          </div>
        </div>
      </div>

      <!-- Countdown Timer -->
      <div id="qrCountdownBox" style="background:#fff3e0;border-radius:10px;padding:14px;display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid #ffb74d;margin-bottom:16px">
        <i class="fas fa-sync-alt fa-spin" style="color:#f57f17;font-size:1.2rem"></i>
        <span style="font-size:.9rem;font-weight:600;color:#e65100">
          QR Codes refresh in: <span id="qrCountdown" style="font-size:1.1rem;color:#f57f17">30s</span>
        </span>
      </div>

      <!-- Real-time Attendance List -->
      <div id="realtimeAttendance" style="background:#f8fafc;border-radius:12px;padding:16px;border:1px solid #e2e8f0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <h4 style="font-size:.95rem;font-weight:700;color:#1e293b;margin:0">
            <i class="fas fa-users"></i> Real-time Attendance
          </h4>
          <button onclick="refreshAttendance()" class="btn-icon" style="padding:6px 12px;font-size:.75rem" title="Refresh">
            <i class="fas fa-sync-alt"></i>
          </button>
        </div>
        <div id="attendanceList" style="max-height:300px;overflow-y:auto">
          <div style="text-align:center;padding:20px;color:#94a3b8">
            <i class="fas fa-spinner fa-spin"></i> Loading...
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0">
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:#1565c0" id="checkinTotal">0</div>
            <div style="font-size:.7rem;color:#475569">Check-ins</div>
          </div>
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:#2e7d32" id="checkoutTotal">0</div>
            <div style="font-size:.7rem;color:#475569">Check-outs</div>
          </div>
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:#7b1fa2" id="orgTotal">0</div>
            <div style="font-size:.7rem;color:#475569">Organizations</div>
          </div>
        </div>
      </div>

      <!-- Instructions -->
      <div style="background:#e3f2fd;border-radius:10px;padding:14px;margin-top:16px;text-align:left;font-size:.82rem;color:#0d47a1;line-height:1.6">
        <div style="font-weight:700;margin-bottom:6px">
          <i class="fas fa-info-circle"></i> How it works:
        </div>
        <ul style="margin:0;padding-left:20px">
          <li>Display QR codes for youth to scan with their phones</li>
          <li>QR codes <strong>rotate every 30 seconds</strong> for security</li>
          <li>Attendance list <strong>updates automatically</strong> every 5 seconds</li>
          <li>Youth can check in during the event and check out after</li>
        </ul>
      </div>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary" onclick="closeQRModal()">Close</button>
    </div>
  </div>
</div>

<!-- FULLSCREEN QR VIEW -->
<div id="qrFullscreen" style="display:none;position:fixed;top:0;left:0;width:100%;height:100vh;background:#1a2332;z-index:10000;overflow:hidden">
  
  <!-- Close Button -->
  <button type="button" id="fullscreenCloseBtn" style="position:absolute;top:30px;right:30px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);color:#fff;width:50px;height:50px;border-radius:50%;font-size:1.3rem;cursor:pointer;transition:.2s;z-index:10001" onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
    <i class="fas fa-times"></i>
  </button>

  <!-- Main Content - Centered -->
  <div style="display:flex;flex-direction:column;justify-content:center;align-items:center;height:100vh;gap:30px">
    
    <!-- Event Title -->
    <h1 id="fullscreenEventTitle" style="color:#fff;font-size:1.5rem;font-weight:600;margin:0;letter-spacing:.02em"></h1>

    <!-- QR Code Container (Clean, No Background) -->
    <div style="text-align:center">
      
      <!-- Check-in QR -->
      <div id="fullscreenCheckinBox" style="display:none">
        <div style="margin-bottom:20px">
          <div style="color:#2196f3;font-size:1.2rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em">
            <i class="fas fa-sign-in-alt"></i> CHECK-IN
          </div>
        </div>
        <div id="fullscreenCheckinCanvas" style="display:inline-block;padding:30px;background:#fff;border-radius:20px"></div>
      </div>

      <!-- Check-out QR -->
      <div id="fullscreenCheckoutBox" style="display:none">
        <div style="margin-bottom:20px">
          <div style="color:#4caf50;font-size:1.2rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em">
            <i class="fas fa-sign-out-alt"></i> CHECK-OUT
          </div>
        </div>
        <div id="fullscreenCheckoutCanvas" style="display:inline-block;padding:30px;background:#fff;border-radius:20px"></div>
      </div>

      <!-- Nothing Open -->
      <div id="fullscreenNothingOpen" style="display:none">
        <div style="color:rgba(255,255,255,.3);font-size:3rem;margin-bottom:20px">
          <i class="fas fa-lock"></i>
        </div>
        <div style="color:rgba(255,255,255,.5);font-size:1.3rem;font-weight:600">
          No QR Code Available
        </div>
      </div>

    </div>

    <!-- Timer - Below QR -->
    <div id="fullscreenTimer">
      <div style="text-align:center">
        <div style="color:rgba(255,255,255,.5);font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px">
          Refreshes in
        </div>
        <div id="fullscreenCountdown" style="color:#ff9800;font-size:2rem;font-weight:800;font-family:'Inter',monospace">
          30s
        </div>
      </div>
    </div>

  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// QR Code Modal Management
var qrModalTimer = null;
var qrModalEventId = null;
var qrModalQrToken = null;
var qrModalCheckinOpen = false;
var qrModalCheckoutOpen = false;
var qrCheckinInstance = null;
var qrCheckoutInstance = null;
var attendanceRefreshTimer = null;
var isFullscreenMode = false;
var fullscreenCheckinInstance = null;
var fullscreenCheckoutInstance = null;

function showQRModal(eventId, eventTitle, qrToken, checkinOpen, checkoutOpen) {
  qrModalEventId = eventId;
  qrModalQrToken = qrToken;
  qrModalCheckinOpen = checkinOpen;
  qrModalCheckoutOpen = checkoutOpen;
  
  document.getElementById('qrEventTitle').textContent = eventTitle;
  document.getElementById('fullscreenEventTitle').textContent = eventTitle;
  document.getElementById('qrModal').style.display = 'flex';
  
  // Show/hide check-in locked/unlocked states
  if (checkinOpen) {
    document.getElementById('qrCheckinUnlocked').style.display = 'block';
    document.getElementById('qrCheckinLocked').style.display = 'none';
    document.getElementById('qrCheckinBox').style.opacity = '1';
  } else {
    document.getElementById('qrCheckinUnlocked').style.display = 'none';
    document.getElementById('qrCheckinLocked').style.display = 'block';
    document.getElementById('qrCheckinBox').style.opacity = '0.7';
  }
  
  // Show/hide check-out locked/unlocked states
  if (checkoutOpen) {
    document.getElementById('qrCheckoutUnlocked').style.display = 'block';
    document.getElementById('qrCheckoutLocked').style.display = 'none';
    document.getElementById('qrCheckoutBox').style.opacity = '1';
  } else {
    document.getElementById('qrCheckoutUnlocked').style.display = 'none';
    document.getElementById('qrCheckoutLocked').style.display = 'block';
    document.getElementById('qrCheckoutBox').style.opacity = '0.7';
  }
  
  // Show countdown only if at least one is unlocked
  if (checkinOpen || checkoutOpen) {
    document.getElementById('qrCountdownBox').style.display = 'flex';
    generateQRCodes();
    startQRCountdown();
  } else {
    document.getElementById('qrCountdownBox').style.display = 'none';
  }
  
  // Load initial attendance
  refreshAttendance();
  
  // Auto-refresh attendance every 5 seconds
  attendanceRefreshTimer = setInterval(refreshAttendance, 5000);
}

function toggleFullscreen() {
  isFullscreenMode = true;
  document.getElementById('qrModal').style.display = 'none';
  document.getElementById('qrFullscreen').style.display = 'block';
  
  // Show only the QR code that is open
  var checkinBox = document.getElementById('fullscreenCheckinBox');
  var checkoutBox = document.getElementById('fullscreenCheckoutBox');
  var nothingOpen = document.getElementById('fullscreenNothingOpen');
  
  // Hide all first
  checkinBox.style.display = 'none';
  checkoutBox.style.display = 'none';
  nothingOpen.style.display = 'none';
  
  if (qrModalCheckinOpen && !qrModalCheckoutOpen) {
    // Only check-in is open
    checkinBox.style.display = 'block';
  } else if (!qrModalCheckinOpen && qrModalCheckoutOpen) {
    // Only check-out is open
    checkoutBox.style.display = 'block';
  } else if (qrModalCheckinOpen && qrModalCheckoutOpen) {
    // Both are open - show check-in first (or you can prioritize check-out)
    checkinBox.style.display = 'block';
  } else {
    // Nothing is open
    nothingOpen.style.display = 'block';
  }
  
  // Generate fullscreen QR codes
  generateFullscreenQRCodes();
  
  // Refresh attendance to update stats
  refreshAttendance();
}

function exitFullscreen() {
  isFullscreenMode = false;
  var fullscreen = document.getElementById('qrFullscreen');
  var modal = document.getElementById('qrModal');
  
  if (fullscreen) fullscreen.style.display = 'none';
  if (modal) modal.style.display = 'flex';
  
  // Regenerate modal QR codes
  generateQRCodes();
}

function generateQRCodes() {
  if (!qrModalQrToken || isFullscreenMode) return;
  
  // Add timestamp for rotation every 30 seconds
  var window = Math.floor(Date.now() / 1000 / 30);
  
  // Generate Check-in QR Code
  if (qrModalCheckinOpen) {
    var checkinContainer = document.getElementById('qrCheckinCanvas');
    checkinContainer.innerHTML = '';
    var checkinData = qrModalQrToken + '-' + window + '-CHECKIN';
    
    try {
      qrCheckinInstance = new QRCode(checkinContainer, {
        text: checkinData,
        width: 200,
        height: 200,
        colorDark: "#0d3b6e",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });
    } catch (e) {
      console.error('Check-in QR generation error:', e);
      checkinContainer.innerHTML = '<div style="color:#c62828;padding:20px;font-size:.8rem">Failed to generate QR</div>';
    }
  }
  
  // Generate Check-out QR Code
  if (qrModalCheckoutOpen) {
    var checkoutContainer = document.getElementById('qrCheckoutCanvas');
    checkoutContainer.innerHTML = '';
    var checkoutData = qrModalQrToken + '-' + window + '-CHECKOUT';
    
    try {
      qrCheckoutInstance = new QRCode(checkoutContainer, {
        text: checkoutData,
        width: 200,
        height: 200,
        colorDark: "#1b5e20",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });
    } catch (e) {
      console.error('Check-out QR generation error:', e);
      checkoutContainer.innerHTML = '<div style="color:#c62828;padding:20px;font-size:.8rem">Failed to generate QR</div>';
    }
  }
}

function generateFullscreenQRCodes() {
  if (!qrModalQrToken) return;
  
  // Add timestamp for rotation every 30 seconds
  var window = Math.floor(Date.now() / 1000 / 30);
  
  // Generate Fullscreen Check-in QR Code (only if check-in is open)
  if (qrModalCheckinOpen) {
    var checkinContainer = document.getElementById('fullscreenCheckinCanvas');
    checkinContainer.innerHTML = '';
    var checkinData = qrModalQrToken + '-' + window + '-CHECKIN';
    
    try {
      fullscreenCheckinInstance = new QRCode(checkinContainer, {
        text: checkinData,
        width: 450,
        height: 450,
        colorDark: "#0d3b6e",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });
    } catch (e) {
      console.error('Fullscreen Check-in QR generation error:', e);
    }
  }
  
  // Generate Fullscreen Check-out QR Code (only if check-out is open)
  if (qrModalCheckoutOpen) {
    var checkoutContainer = document.getElementById('fullscreenCheckoutCanvas');
    checkoutContainer.innerHTML = '';
    var checkoutData = qrModalQrToken + '-' + window + '-CHECKOUT';
    
    try {
      fullscreenCheckoutInstance = new QRCode(checkoutContainer, {
        text: checkoutData,
        width: 450,
        height: 450,
        colorDark: "#1b5e20",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });
    } catch (e) {
      console.error('Fullscreen Check-out QR generation error:', e);
    }
  }
}

function startQRCountdown() {
  if (qrModalTimer) clearInterval(qrModalTimer);
  
  function updateTimer() {
    var secsLeft = 30 - (Math.floor(Date.now() / 1000) % 30);
    
    // Update modal countdown
    var countdownEl = document.getElementById('qrCountdown');
    if (countdownEl) {
      countdownEl.textContent = secsLeft + 's';
      countdownEl.style.color = secsLeft < 10 ? '#c62828' : '#f57f17';
    }
    
    // Update fullscreen countdown
    var fullscreenCountdownEl = document.getElementById('fullscreenCountdown');
    if (fullscreenCountdownEl) {
      fullscreenCountdownEl.textContent = secsLeft + 's';
    }
    
    if (secsLeft === 30) {
      // New rotation - regenerate QR codes
      if (isFullscreenMode) {
        generateFullscreenQRCodes();
      } else {
        generateQRCodes();
      }
    }
  }
  
  updateTimer();
  qrModalTimer = setInterval(updateTimer, 1000);
}

function refreshAttendance() {
  if (!qrModalEventId) return;
  
  fetch('get_realtime_attendance.php?event_id=' + qrModalEventId)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update modal totals only
        document.getElementById('checkinTotal').textContent = data.totals.checkins;
        document.getElementById('checkoutTotal').textContent = data.totals.checkouts;
        document.getElementById('orgTotal').textContent = data.totals.orgs;
        
        // Update attendance list (only in modal)
        var listHtml = '';
        if (data.attendees.length === 0) {
          listHtml = '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:.85rem">No check-ins yet</div>';
        } else {
          data.attendees.forEach(function(att) {
            var statusBadge = att.checked_out_at 
              ? '<span class="badge green" style="font-size:.7rem"><i class="fas fa-check-double"></i> Completed</span>'
              : '<span class="badge orange" style="font-size:.7rem"><i class="fas fa-hourglass-half"></i> Checked In</span>';
            
            var timeInfo = 'In: ' + att.checked_in_time;
            if (att.checked_out_at) {
              timeInfo += ' | Out: ' + att.checked_out_time;
            }
            
            listHtml += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #e2e8f0;font-size:.82rem">';
            listHtml += '<div style="flex:1"><strong style="color:#1e293b">' + att.name + '</strong><div style="font-size:.7rem;color:#94a3b8;margin-top:2px">' + att.organization + ' • ' + timeInfo + '</div></div>';
            listHtml += '<div>' + statusBadge + '</div>';
            listHtml += '</div>';
          });
        }
        document.getElementById('attendanceList').innerHTML = listHtml;
      }
    })
    .catch(error => {
      console.error('Attendance refresh error:', error);
    });
}

function closeQRModal() {
  document.getElementById('qrModal').style.display = 'none';
  document.getElementById('qrFullscreen').style.display = 'none';
  isFullscreenMode = false;
  
  if (qrModalTimer) {
    clearInterval(qrModalTimer);
    qrModalTimer = null;
  }
  if (attendanceRefreshTimer) {
    clearInterval(attendanceRefreshTimer);
    attendanceRefreshTimer = null;
  }
  qrCheckinInstance = null;
  qrCheckoutInstance = null;
  fullscreenCheckinInstance = null;
  fullscreenCheckoutInstance = null;
}

// Close modal on overlay click
document.getElementById('qrModal').addEventListener('click', function(e) {
  if (e.target === this && !qrModalCheckinOpen && !qrModalCheckoutOpen) {
    closeQRModal();
  }
});

// Open Edit Event modal and populate fields
function openEditEvent(ev) {
  document.getElementById('edit_event_id').value        = ev.id;
  document.getElementById('edit_title').value           = ev.title || '';
  document.getElementById('edit_event_type').value      = ev.event_type || 'official_event';
  document.getElementById('edit_event_date').value      = ev.event_date || '';
  document.getElementById('edit_event_time').value      = ev.event_time || '';
  document.getElementById('edit_event_start_time').value = ev.event_start_time || '';
  document.getElementById('edit_event_end_time').value   = ev.event_end_time || '';
  document.getElementById('edit_location').value        = ev.location || '';
  document.getElementById('edit_organization_id').value = ev.organization_id || '';
  document.getElementById('edit_quarter').value         = ev.quarter || '';
  document.getElementById('edit_year').value            = ev.year || '<?= date('Y') ?>';
  document.getElementById('edit_description').value     = ev.description || '';
  document.getElementById('edit_merit_points').value    = ev.merit_points || 2;
  document.getElementById('edit_requires_rep').checked  = ev.requires_representative == 1;
  document.getElementById('editEventModal').style.display = 'flex';
}

// Close modals on overlay click
['addEventModal','editEventModal'].forEach(function(id) {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
  });
});

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    // Check if fullscreen is open first
    if (document.getElementById('qrFullscreen').style.display === 'block') {
      exitFullscreen();
      return;
    }
    
    document.getElementById('addEventModal').style.display = 'none';
    document.getElementById('editEventModal').style.display = 'none';
    // Only close QR modal if check-in is locked
    if (document.getElementById('qrModal').style.display === 'flex') {
      if (!qrModalCheckinOpen) {
        closeQRModal();
      }
    }
  }
});

// Add click handler for fullscreen close button
document.addEventListener('DOMContentLoaded', function() {
  var closeBtn = document.getElementById('fullscreenCloseBtn');
  if (closeBtn) {
    closeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Close button clicked');
      exitFullscreen();
    });
  }
});

// Photo modal function
function showPhotoModal(photoSrc) {
  var modal = document.getElementById('photoModal');
  if (!modal) {
    // Create modal if it doesn't exist
    modal = document.createElement('div');
    modal.id = 'photoModal';
    modal.style.cssText = 'display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);align-items:center;justify-content:center;padding:20px';
    modal.innerHTML = `
      <div style="position:relative;max-width:90%;max-height:90vh;display:flex;align-items:center;justify-content:center">
        <button onclick="closePhotoModal()" style="position:absolute;top:-40px;right:0;background:transparent;border:none;color:#fff;font-size:2rem;cursor:pointer;padding:10px;line-height:1;transition:.2s" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
          <i class="fas fa-times"></i>
        </button>
        <img id="photoModalImg" src="" alt="Event Photo" style="max-width:100%;max-height:90vh;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.5)">
      </div>
    `;
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closePhotoModal();
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        closePhotoModal();
      }
    });
  }
  
  document.getElementById('photoModalImg').src = photoSrc;
  modal.style.display = 'flex';
}

function closePhotoModal() {
  var modal = document.getElementById('photoModal');
  if (modal) modal.style.display = 'none';
}
</script>

</body>
</html>
