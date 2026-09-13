<?php
require_once 'config.php';
requireLogin();
$pdo   = db();
$admin = currentAdmin();

// Handle broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'broadcast') {
        $title    = trim($_POST['title'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $link     = trim($_POST['link'] ?? '');
        $target   = $_POST['target'] ?? 'all'; // all | approved | barangay

        if ($title && $message) {
            // Build target user list
            $where = "WHERE status='approved'";
            $params = [];
            if ($target === 'barangay' && !empty($_POST['barangay'])) {
                $where .= " AND barangay=?";
                $params[] = $_POST['barangay'];
            }
            $users = $pdo->prepare("SELECT id FROM youth_users $where");
            $users->execute($params);
            $userIds = $users->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id,type,title,category,message,link,is_read,created_at) VALUES (?,?,?,?,?,?,0,CURRENT_TIMESTAMP)'
            );
            foreach ($userIds as $uid) {
                $stmt->execute([$uid, 'broadcast', $title, $category, $message, $link ?: null]);
            }
            flash('success', 'Notification sent to ' . count($userIds) . ' youth users.');
            header('Location: broadcast.php'); exit;
        }
    }
}

// Recent broadcasts
$recent = $pdo->query(
    'SELECT n.title, n.message, n.category, n.created_at, COUNT(*) as sent_to
     FROM notifications n WHERE n.type="broadcast"
     GROUP BY n.title, n.message, n.category, n.created_at
     ORDER BY n.created_at DESC LIMIT 20'
)->fetchAll();

$barangays = $pdo->query("SELECT DISTINCT barangay FROM youth_users WHERE barangay IS NOT NULL ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);

$catColors = [
    'general'     => ['#475569','#f1f5f9'],
    'event'       => ['#1565c0','#e3f2fd'],
    'scholarship' => ['#7b1fa2','#f3e5f5'],
    'approval'    => ['#2e7d32','#e8f5e9'],
    'assistance'  => ['#00796b','#e0f2f1'],
    'volunteer'   => ['#f57f17','#fff8e1'],
    'system'      => ['#c62828','#ffebee'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Broadcast Notifications – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="page-header">
  <div><h2><i class="fas fa-broadcast-tower" style="color:#1565c0;margin-right:8px"></i>Broadcast Notifications</h2>
  <p>Send announcements to youth users about events, scholarships, and updates.</p></div>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:20px;align-items:start">

  <!-- Send form -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-paper-plane"></i> Send Notification</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="broadcast"/>
      <div class="fg"><label>Title <span class="req">*</span></label>
        <input type="text" name="title" required placeholder="e.g. New Scholarship Batch Open"/>
      </div>
      <div class="fg"><label>Message <span class="req">*</span></label>
        <textarea name="message" rows="3" required placeholder="Write your announcement here..." style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;width:100%;resize:vertical;background:#f8fafc"></textarea>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Category</label>
          <select name="category">
            <option value="general">General</option>
            <option value="event">Event</option>
            <option value="scholarship">Scholarship</option>
            <option value="approval">Approval</option>
            <option value="assistance">Assistance</option>
            <option value="volunteer">Volunteer</option>
            <option value="system">System</option>
          </select>
        </div>
        <div class="fg"><label>Target Audience</label>
          <select name="target" id="targetSel" onchange="document.getElementById('barangayRow').style.display=this.value==='barangay'?'block':'none'">
            <option value="all">All Approved Youth</option>
            <option value="barangay">Specific Barangay</option>
          </select>
        </div>
      </div>
      <div class="fg" id="barangayRow" style="display:none">
        <label>Barangay</label>
        <select name="barangay">
          <option value="">Select barangay</option>
          <?php foreach ($barangays as $b): ?>
          <option value="<?=htmlspecialchars($b)?>"><?=htmlspecialchars($b)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label>Link (optional)</label>
        <input type="text" name="link" placeholder="e.g. scholarship.php or leave blank"/>
      </div>
      <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:11px">
        <i class="fas fa-broadcast-tower"></i> Send to All Youth
      </button>
    </form>
  </div>

  <!-- Quick templates -->
  <div style="display:flex;flex-direction:column;gap:14px">
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-bolt"></i> Quick Templates</h3></div>
      <div style="padding:14px 18px;display:flex;flex-direction:column;gap:8px">
        <?php
        $templates = [
          ['New Scholarship Batch Open','A new Iskolar ng Bayan scholarship batch is now open for applications. Check the Scholarship section to apply.','scholarship','scholarship.php'],
          ['Upcoming Event Reminder','There is an upcoming LYDO event. Check the Events section for details and register now.','event','volunteer.php'],
          ['Application Status Update','Your application status has been updated. Please check your dashboard for details.','approval',''],
          ['Volunteer Program Open','New volunteer program slots are now available. Visit the Volunteer Program section to register.','volunteer','volunteer.php'],
          ['Assistance Request Update','Your assistance request has been reviewed. Check the Assistance section for updates.','assistance','assistance.php'],
        ];
        foreach ($templates as [$t,$m,$c,$l]):
          [$tc,$tb] = $catColors[$c];
        ?>
        <button onclick="fillTemplate(<?=htmlspecialchars(json_encode($t),ENT_QUOTES)?>,<?=htmlspecialchars(json_encode($m),ENT_QUOTES)?>,<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>,<?=htmlspecialchars(json_encode($l),ENT_QUOTES)?>)"
          style="padding:10px 14px;background:<?=$tb?>;color:<?=$tc?>;border:1.5px solid <?=$tc?>33;border-radius:9px;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;text-align:left;transition:.2s;display:flex;align-items:center;gap:8px"
          onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
          <i class="fas fa-<?=$catColors[$c][1]==='#e3f2fd'?'calendar-alt':($c==='scholarship'?'graduation-cap':($c==='approval'?'check-circle':($c==='volunteer'?'user-check':'hands-helping')))?>"></i>
          <?=htmlspecialchars($t)?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent broadcasts -->
<div class="card mt-16">
  <div class="card-header"><h3><i class="fas fa-history"></i> Recent Broadcasts</h3></div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Title</th><th>Category</th><th>Message</th><th>Sent To</th><th>Date</th></tr></thead>
      <tbody>
      <?php if (empty($recent)): ?>
        <tr><td colspan="5" class="empty">No broadcasts sent yet.</td></tr>
      <?php else: foreach ($recent as $r):
        [$tc,$tb] = $catColors[$r['category']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr>
          <td><strong><?=htmlspecialchars($r['title'])?></strong></td>
          <td><span style="background:<?=$tb?>;color:<?=$tc?>;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700"><?=ucfirst($r['category'])?></span></td>
          <td style="font-size:.82rem;max-width:280px"><?=htmlspecialchars(substr($r['message'],0,80))?>...</td>
          <td><strong><?=$r['sent_to']?></strong> users</td>
          <td style="font-size:.8rem"><?=date('M j, Y g:i A',strtotime($r['created_at']))?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>
</div>
<script>
function fillTemplate(title, message, category, link) {
  document.querySelector('[name="title"]').value   = title;
  document.querySelector('textarea[name="message"]').value = message;
  document.querySelector('[name="category"]').value = category;
  document.querySelector('[name="link"]').value    = link;
}
</script>
</body>
</html>
