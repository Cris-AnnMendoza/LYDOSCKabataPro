<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('manage_admins')) { header('Location: dashboard.php'); exit; }

$pdo  = db();
$logs = $pdo->query(
    'SELECT l.*, a.full_name, a.email, a.role
     FROM admin_activity_log l
     JOIN admin_users a ON a.id = l.admin_id
     ORDER BY l.created_at DESC LIMIT 200'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Activity Logs – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">
  <div class="page-header">
    <div><h2>Activity Logs</h2><p>All admin actions are recorded here.</p></div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Admin</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th><th>Date & Time</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="7" class="empty">No activity logs yet.</td></tr>
        <?php else: foreach ($logs as $i => $l): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($l['full_name']) ?></strong><br><small style="color:#94a3b8"><?= htmlspecialchars($l['email']) ?></small></td>
            <td><?= htmlspecialchars(str_replace('_',' ',$l['role'])) ?></td>
            <td><span class="badge blue"><?= htmlspecialchars($l['action']) ?></span></td>
            <td style="max-width:280px;font-size:.82rem"><?= htmlspecialchars($l['details'] ?: '—') ?></td>
            <td style="font-size:.8rem;color:#94a3b8"><?= htmlspecialchars($l['ip_address'] ?: '—') ?></td>
            <td style="font-size:.8rem"><?= date('M j, Y g:i A', strtotime($l['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>
</body>
</html>
