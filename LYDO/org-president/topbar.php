<?php
$president = $_SESSION['org_president'] ?? null;
if (!$president) { header('Location: login.php'); exit; }

// Get notifications count (if notifications system exists)
$pdo = db();
$notifCount = 0;
try {
    $notifStmt = $pdo->prepare('SELECT COUNT(*) FROM org_president_notifications WHERE president_id = ? AND is_read = 0');
    $notifStmt->execute([$president['id']]);
    $notifCount = (int)$notifStmt->fetchColumn();
} catch (Exception $e) {
    // Table doesn't exist yet
}
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="hamburger" id="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">
      <i class="fas fa-bars"></i>
    </button>
  </div>
  
  <div class="topbar-right">
    <!-- All topbar elements removed -->
  </div>
</header>
