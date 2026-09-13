<?php
require_once 'config.php';
requireLogin();

$admin = currentAdmin();
$pdo = db();

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)$_POST['message_id'];
    $pdo->prepare('UPDATE contact_messages SET status="read" WHERE id=?')->execute([$id]);
    flash('success', 'Message marked as read.');
    header('Location: contact_messages.php'); exit;
}

// Get all messages
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();

// Count unread
$unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Contact Messages – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg = flash('success')): ?>
<div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2><i class="fas fa-envelope"></i> Contact Messages</h2>
    <p>Messages from the landing page contact form. <strong><?= $unreadCount ?></strong> unread.</p>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th>
          <th>Date</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($messages)): ?>
        <tr><td colspan="8" class="empty">No messages yet.</td></tr>
      <?php else: foreach ($messages as $i => $m):
        $statusColors = ['unread'=>['#1565c0','#e3f2fd'],'read'=>['#475569','#f1f5f9'],'replied'=>['#2e7d32','#e8f5e9']];
        [$sc,$sb] = $statusColors[$m['status']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr style="<?= $m['status']==='unread'?'background:#f0f9ff':'' ?>">
          <td><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></strong></td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars($m['subject']) ?></td>
          <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['message']) ?></td>
          <td><?= date('M j, Y g:i A', strtotime($m['created_at'])) ?></td>
          <td><span class="badge" style="background:<?= $sb ?>;color:<?= $sc ?>"><?= ucfirst($m['status']) ?></span></td>
          <td>
            <?php if ($m['status'] === 'unread'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="message_id" value="<?= $m['id'] ?>"/>
              <button type="submit" name="mark_read" class="btn-icon blue" title="Mark as Read">
                <i class="fas fa-check"></i>
              </button>
            </form>
            <?php endif; ?>
            <a href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=Re: <?= urlencode($m['subject']) ?>" class="btn-icon green" title="Reply via Email">
              <i class="fas fa-reply"></i>
            </a>
          </td>
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
