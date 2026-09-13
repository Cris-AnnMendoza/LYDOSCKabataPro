<?php
// Requires $user to be set before including
$current  = basename($_SERVER['PHP_SELF']);
$initials = strtoupper(
    substr($user['first_name'] ?? 'U', 0, 1) .
    substr($user['last_name']  ?? '',  0, 1)
);

// Unread notification count
$notifCount = 0;
try {
    $nc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
    $nc->execute([$_SESSION['user_id']]);
    $notifCount = (int)$nc->fetchColumn();
} catch (Exception $e) {}

function youthNav(string $file, string $icon, string $label, int $badge = 0): void {
    global $current;
    $active = ($current === $file) ? 'active' : '';
    $b = $badge > 0 ? "<span class='y-badge'>$badge</span>" : '';
    echo "<a href='$file' class='y-nav-item $active'><i class='fas fa-$icon'></i><span>$label</span>$b</a>";
}
?>
<aside class="y-sidebar" id="ySidebar">
  <div class="y-sidebar-logo">
    <div class="y-logo-icon" style="background:none;overflow:hidden"><img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:100%;height:100%;object-fit:contain"/></div>
    <div class="y-logo-text">
      <span class="y-logo-main">LYDO</span>
      <span class="y-logo-sub">Sta. Cruz, Laguna</span>
    </div>
    <button class="y-sidebar-close" id="ySidebarClose" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- User card -->
  <div class="y-user-card">
    <div class="y-avatar"><?= $initials ?></div>
    <div class="y-user-info">
      <p class="y-user-name"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
      <p class="y-user-email"><?= htmlspecialchars($user['email'] ?? '') ?></p>
    </div>
  </div>

  <nav class="y-nav">
    <div class="y-nav-label">Main</div>
    <?php youthNav('dashboard.php',  'tachometer-alt',   'Dashboard') ?>
    <?php youthNav('events.php',     'calendar-check',   'Events & Check-in') ?>
    <?php youthNav('volunteer.php',  'user-check',       'Volunteer Program') ?>
    <?php youthNav('scholarship.php','graduation-cap',   'Scholarship') ?>
    <?php youthNav('games.php',      'gamepad',          'Mini Games') ?>

    <div class="y-nav-label">My Account</div>
    <?php youthNav('profile.php',    'user-edit',      'My Profile') ?>
    <?php youthNav('resume.php',     'file-alt',       'Resume Builder') ?>
    <?php youthNav('notifications.php','bell',         'Notifications', $notifCount) ?>
  </nav>

  <div class="y-sidebar-footer">
    <a href="logout.php" class="y-logout">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</aside>

<!-- Overlay for mobile -->
<div class="y-overlay" id="yOverlay"></div>
