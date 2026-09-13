<?php
$admin   = currentAdmin();
$current = basename($_SERVER['PHP_SELF']);
function navItem(string $file, string $icon, string $label, string $perm = ''): void {
    global $current, $admin;
    if ($perm && !hasPermission($perm)) return;
    $active = ($current === $file) ? 'active' : '';
    echo "<a href='$file' class='nav-item $active'><i class='fas fa-$icon'></i> $label</a>";
}
$initials = strtoupper(substr($admin['full_name'],0,1) . (strpos($admin['full_name'],' ')!==false ? substr($admin['full_name'], strpos($admin['full_name'],' ')+1, 1) : ''));
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="s-icon" style="background:none;overflow:hidden;padding:2px"><img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:100%;height:100%;object-fit:contain"/></div>
    <div>
      <span class="s-main">LYDO Admin</span>
      <span class="s-sub">Sta. Cruz, Laguna</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <?php navItem('dashboard.php','tachometer-alt','Dashboard') ?>
    <?php
    // Pending count badge (only for youth users - admins don't have pending status)
    $pendingCount = 0;
    try {
        $pc = db()->query("SELECT COUNT(*) FROM youth_users WHERE status='pending'");
        $pendingCount = (int)($pc->fetchColumn());
    } catch(Exception $e) {}
    $pendingBadge = $pendingCount > 0 ? " <span style='background:#e53935;color:#fff;border-radius:50px;padding:1px 7px;font-size:.68rem;font-weight:700;margin-left:auto'>$pendingCount</span>" : '';
    ?>
    <?php navItem('users.php','users','Youth Users','view_users') ?>
    <?php if (hasPermission('view_users')): ?>
    <a href="approvals.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])==='approvals.php'?'active':'' ?>" style="justify-content:space-between">
      <span style="display:flex;align-items:center;gap:10px"><i class="fas fa-clipboard-check"></i> Approvals</span>
      <?= $pendingBadge ?>
    </a>
    <?php endif; ?>
    <?php navItem('events.php','calendar-alt','Events','manage_events') ?>
    <?php navItem('organizations.php','sitemap','Organizations','manage_events') ?>
    <?php navItem('merit.php','star','Merit & Demerit','view_users') ?>
    <?php navItem('accreditation.php','award','Accreditation','view_users') ?>
    <?php navItem('assistance.php','hands-helping','Assistance Requests','view_users') ?>
    <?php navItem('volunteer_admin.php','user-check','Volunteer Program','view_users') ?>
    <?php navItem('scholarship_admin.php','graduation-cap','Scholarship','view_users') ?>
    <?php navItem('contact_messages.php','envelope','Contact Messages','view_users') ?>

    <?php if (hasPermission('view_reports')): ?>
    <div class="nav-label">Reports</div>
    <?php navItem('reports.php','chart-bar','Reports','view_reports') ?>
    <?php endif; ?>

    <?php if (hasPermission('manage_admins')): ?>
    <div class="nav-label">Administration</div>
    <?php navItem('admins.php','user-shield','Manage Admins','manage_admins') ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="avatar"><?= $initials ?></div>
      <div>
        <p class="a-name"><?= htmlspecialchars($admin['full_name']) ?></p>
        <p class="a-role"><?= str_replace('_',' ',$admin['role']) ?></p>
      </div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</aside>
