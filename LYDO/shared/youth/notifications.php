<?php
require_once __DIR__ . "/../config.php";
if (empty($_SESSION["user_id"])) { header("Location: ../../login.php"); exit; }
$pdo    = db();
$userId = (int)$_SESSION["user_id"];
$uStmt  = $pdo->prepare("SELECT * FROM youth_users WHERE id=? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read=TRUE WHERE user_id=? AND is_read=FALSE")->execute([$userId]);

$nc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE");
$nc->execute([$userId]);
$notifCount = 0; // just marked all read

// Load all notifications - no filtering
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();

$catColors = [
    "general"     => ["#475569","#f1f5f9","fa-bell"],
    "event"       => ["#1565c0","#e3f2fd","fa-calendar-alt"],
    "scholarship" => ["#7b1fa2","#f3e5f5","fa-graduation-cap"],
    "approval"    => ["#2e7d32","#e8f5e9","fa-check-circle"],
    "assistance"  => ["#00796b","#e0f2f1","fa-hands-helping"],
    "volunteer"   => ["#f57f17","#fff8e1","fa-user-check"],
    "system"      => ["#c62828","#ffebee","fa-cog"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Notifications  LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.filter-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.ftab{padding:6px 14px;border-radius:8px;border:1.5px solid #e2e8f0;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;color:#475569;background:#fff;text-decoration:none;transition:.2s;display:flex;align-items:center;gap:6px}
.ftab:hover,.ftab.active{background:#1565c0;border-color:#1565c0;color:#fff}
.notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border-bottom:1px solid #f1f5f9;transition:.2s}
.notif-item:last-child{border-bottom:none}
.notif-item:hover{background:#f8fafc}
.notif-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.notif-content{flex:1}
.notif-title{font-size:.9rem;font-weight:700;color:#1e293b;margin-bottom:3px}
.notif-msg{font-size:.85rem;color:#475569;line-height:1.5}
.notif-time{font-size:.75rem;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:5px}
.notif-unread{background:#e3f2fd}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:12px}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-bell" style="color:#1565c0;margin-right:8px"></i>Notifications</h2>
  <p>Stay updated with the latest announcements from LYDO.</p>
</div>

<div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <i class="fas fa-bell-slash"></i>
      <p style="font-size:.95rem;font-weight:500">No notifications yet.</p>
      <p style="font-size:.82rem;margin-top:6px">You will be notified about events, scholarship updates, and more.</p>
    </div>
  <?php else: foreach ($notifications as $n):
    $category = $n['category'] ?? 'general';
    [$color,$bg,$icon] = $catColors[$category] ?? ['#475569','#f1f5f9','fa-bell'];
  ?>
  <div class="notif-item <?=$n['is_read']?'':'notif-unread'?>">
    <div class="notif-icon" style="background:<?=$bg?>;color:<?=$color?>">
      <i class="fas <?=$icon?>"></i>
    </div>
    <div class="notif-content">
      <?php if (!empty($n['title'])): ?>
        <div class="notif-title"><?=htmlspecialchars($n['title'])?></div>
      <?php endif; ?>
      <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
      <div class="notif-time">
        <i class="fas fa-clock"></i>
        <?=date('M j, Y g:i A', strtotime($n['created_at']))?>
        <span style="background:<?=$bg?>;color:<?=$color?>;padding:1px 8px;border-radius:50px;font-size:.68rem;font-weight:700;margin-left:4px"><?=ucfirst($category)?></span>
      </div>
      <?php if (!empty($n['link'])): ?>
        <a href="<?=htmlspecialchars($n['link'])?>" style="font-size:.8rem;color:#1565c0;font-weight:600;margin-top:5px;display:inline-flex;align-items:center;gap:4px">
          View Details <i class="fas fa-arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

</main>
</div>
<script>
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',function(){ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
</script>
</body></html>
