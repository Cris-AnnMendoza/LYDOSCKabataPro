<?php
/**
 * One-time migration runner.
 * Visit: http://localhost/LYDO/lydo-system/admin2/run_migration.php
 * Delete this file after running.
 */
require_once 'config.php';
requireLogin();

$pdo = db();
$results = [];

function runSQL(PDO $pdo, string $label, string $sql): array {
    try {
        $pdo->exec($sql);
        return ['label' => $label, 'status' => 'ok', 'msg' => 'Done'];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Duplicate column / already exists = OK
        if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists') || str_contains($msg, '1060') || str_contains($msg, '1061')) {
            return ['label' => $label, 'status' => 'skip', 'msg' => 'Already exists'];
        }
        return ['label' => $label, 'status' => 'error', 'msg' => $msg];
    }
}

// ── 1. Add columns to events ──────────────────────────────
$results[] = runSQL($pdo, 'events.event_type',
    "ALTER TABLE events ADD COLUMN event_type VARCHAR(50) NOT NULL DEFAULT 'official_event'");

$results[] = runSQL($pdo, 'events.requires_representative',
    "ALTER TABLE events ADD COLUMN requires_representative TINYINT(1) NOT NULL DEFAULT 0");

$results[] = runSQL($pdo, 'events.qr_token',
    "ALTER TABLE events ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL");

$results[] = runSQL($pdo, 'events.checkin_open',
    "ALTER TABLE events ADD COLUMN checkin_open TINYINT(1) NOT NULL DEFAULT 1");

$results[] = runSQL($pdo, 'events.merit_points',
    "ALTER TABLE events ADD COLUMN merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2");

// Unique index on qr_token (ignore if exists)
$results[] = runSQL($pdo, 'events.uq_qr_token index',
    "ALTER TABLE events ADD UNIQUE KEY uq_qr_token (qr_token)");

// ── 2. Add status to youth_users ──────────────────────────
$results[] = runSQL($pdo, 'youth_users.status',
    "ALTER TABLE youth_users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'approved'");

// ── 3. event_checkins ─────────────────────────────────────
$results[] = runSQL($pdo, 'CREATE event_checkins',
    "CREATE TABLE IF NOT EXISTS event_checkins (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id        INT UNSIGNED NOT NULL,
        user_id         INT UNSIGNED NOT NULL,
        checked_in_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address      VARCHAR(45)  DEFAULT NULL,
        UNIQUE KEY uq_event_user_checkin (event_id, user_id),
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 4. event_certificates ────────────────────────────────
$results[] = runSQL($pdo, 'CREATE event_certificates',
    "CREATE TABLE IF NOT EXISTS event_certificates (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id        INT UNSIGNED NOT NULL,
        user_id         INT UNSIGNED NOT NULL,
        cert_number     VARCHAR(50)  NOT NULL,
        generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        uploaded_at     TIMESTAMP    DEFAULT NULL,
        upload_filename VARCHAR(255) DEFAULT NULL,
        upload_original VARCHAR(255) DEFAULT NULL,
        merit_awarded   TINYINT(1)   NOT NULL DEFAULT 0,
        merit_points    TINYINT      NOT NULL DEFAULT 2,
        UNIQUE KEY uq_event_user_cert (event_id, user_id),
        UNIQUE KEY uq_cert_number (cert_number),
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 5. user_merit_logs ───────────────────────────────────
$results[] = runSQL($pdo, 'CREATE user_merit_logs',
    "CREATE TABLE IF NOT EXISTS user_merit_logs (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        points      SMALLINT     NOT NULL,
        type        ENUM('merit','demerit') NOT NULL,
        reason      VARCHAR(255) NOT NULL,
        category    VARCHAR(100) DEFAULT NULL,
        event_id    INT UNSIGNED DEFAULT NULL,
        cert_id     INT UNSIGNED DEFAULT NULL,
        awarded_by  INT UNSIGNED DEFAULT NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 6. notifications ─────────────────────────────────────
$results[] = runSQL($pdo, 'CREATE notifications',
    "CREATE TABLE IF NOT EXISTS notifications (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        title      VARCHAR(200) NOT NULL,
        message    TEXT         NOT NULL,
        type       VARCHAR(50)  DEFAULT 'info',
        is_read    TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 7. org_merit_logs ────────────────────────────────────
$results[] = runSQL($pdo, 'CREATE org_merit_logs',
    "CREATE TABLE IF NOT EXISTS org_merit_logs (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organization_id INT UNSIGNED NOT NULL,
        points          SMALLINT     NOT NULL,
        type            ENUM('merit','demerit') NOT NULL,
        reason          VARCHAR(255) NOT NULL,
        category        VARCHAR(100) DEFAULT NULL,
        event_id        INT UNSIGNED DEFAULT NULL,
        awarded_by      INT UNSIGNED DEFAULT NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 8. org_warning_letters ───────────────────────────────
$results[] = runSQL($pdo, 'CREATE org_warning_letters',
    "CREATE TABLE IF NOT EXISTS org_warning_letters (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organization_id INT UNSIGNED NOT NULL,
        reason          TEXT         NOT NULL,
        level           ENUM('warning','show_cause','revocation_flag') NOT NULL DEFAULT 'warning',
        issued_by       INT UNSIGNED DEFAULT NULL,
        issued_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── 9. org_explanation_letters ───────────────────────────
$results[] = runSQL($pdo, 'CREATE org_explanation_letters',
    "CREATE TABLE IF NOT EXISTS org_explanation_letters (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organization_id INT UNSIGNED NOT NULL,
        subject         VARCHAR(255) NOT NULL,
        content         TEXT         NOT NULL,
        status          ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by     INT UNSIGNED DEFAULT NULL,
        reviewed_at     DATETIME     DEFAULT NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$hasError = array_filter($results, fn($r) => $r['status'] === 'error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>LYDO Migration Runner</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);width:100%;max-width:640px;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0d3b6e,#1565c0);padding:24px 28px;color:#fff}
.card-top h1{font-size:1.3rem;font-weight:800;margin-bottom:4px}
.card-top p{font-size:.85rem;opacity:.75}
.card-body{padding:24px 28px}
.row{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-radius:8px;margin-bottom:6px;font-size:.88rem}
.row-ok{background:#e8f5e9;color:#2e7d32}
.row-skip{background:#f1f5f9;color:#475569}
.row-error{background:#ffebee;color:#c62828}
.row-label{font-weight:600}
.row-msg{font-size:.78rem;opacity:.8}
.summary{margin-top:20px;padding:16px;border-radius:10px;font-size:.9rem;font-weight:600;text-align:center}
.summary.ok{background:#e8f5e9;color:#2e7d32}
.summary.error{background:#ffebee;color:#c62828}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:9px;font-family:inherit;font-size:.9rem;font-weight:700;text-decoration:none;transition:.2s;margin-top:16px;border:none;cursor:pointer}
.btn-primary{background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;box-shadow:0 4px 12px rgba(21,101,192,.3)}
.btn-primary:hover{transform:translateY(-1px)}
.btn-danger{background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a}
.btn-danger:hover{background:#c62828;color:#fff}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1><i class="fas fa-database"></i> LYDO Database Migration</h1>
    <p>Running schema updates for the QR Check-in & Certificate system</p>
  </div>
  <div class="card-body">

    <?php foreach ($results as $r): ?>
    <div class="row row-<?= $r['status'] ?>">
      <span class="row-label">
        <i class="fas fa-<?= $r['status']==='ok' ? 'check-circle' : ($r['status']==='skip' ? 'minus-circle' : 'times-circle') ?>"></i>
        <?= htmlspecialchars($r['label']) ?>
      </span>
      <span class="row-msg"><?= htmlspecialchars($r['msg']) ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (empty($hasError)): ?>
    <div class="summary ok">
      <i class="fas fa-check-circle"></i> Migration completed successfully! All tables and columns are ready.
    </div>
    <?php else: ?>
    <div class="summary error">
      <i class="fas fa-exclamation-circle"></i> Some steps failed. Check the errors above.
    </div>
    <?php endif; ?>

    <div class="actions">
      <a href="events.php" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Go to Events</a>
      <a href="dashboard.php" class="btn btn-primary" style="background:linear-gradient(135deg,#2e7d32,#43a047)"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <?php if (empty($hasError)): ?>
      <a href="?delete=1" class="btn btn-danger" onclick="return confirm('Delete this migration file?')"><i class="fas fa-trash"></i> Delete This File</a>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php
// Self-delete after successful migration
if (isset($_GET['delete']) && empty($hasError)) {
    @unlink(__FILE__);
    echo '<script>alert("Migration file deleted."); window.location="events.php";</script>';
}
?>
</body>
</html>
