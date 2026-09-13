<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <i class="fas fa-users-cog"></i>
      <div class="logo-text">
        <span class="logo-main">LYDO</span>
        <span class="logo-sub">President Portal</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?= $current === 'dashboard' ? 'active' : '' ?>">
      <i class="fas fa-home"></i>
      <span>Dashboard</span>
    </a>
    
    <a href="assistance.php" class="nav-item <?= $current === 'assistance' ? 'active' : '' ?>">
      <i class="fas fa-hands-helping"></i>
      <span>Assistance Request</span>
    </a>
    
    <?php if (!empty($_SESSION['org_president'])): 
        $pdo = db();
        $demeritStmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)),0) as total FROM org_merit_logs WHERE organization_id=? AND type='demerit'");
        $demeritStmt->execute([$_SESSION['org_president']['organization_id']]);
        $totalDemerits = (int)$demeritStmt->fetchColumn();
    ?>
    
    <a href="warnings.php" class="nav-item <?= $current === 'warnings' ? 'active' : '' ?>">
      <i class="fas fa-exclamation-triangle"></i>
      <span>Warnings & Violations</span>
      <?php if ($totalDemerits >= 5): ?>
        <span class="badge red"><?= $totalDemerits ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    
    <a href="members.php" class="nav-item <?= $current === 'members' ? 'active' : '' ?>">
      <i class="fas fa-users"></i>
      <span>Members</span>
    </a>
    
    <a href="accreditation.php" class="nav-item <?= $current === 'accreditation' ? 'active' : '' ?>">
      <i class="fas fa-award"></i>
      <span>Accreditation</span>
    </a>
    
    <a href="events.php" class="nav-item <?= $current === 'events' ? 'active' : '' ?>">
      <i class="fas fa-calendar"></i>
      <span>Events</span>
    </a>
    
    <a href="reports.php" class="nav-item <?= $current === 'reports' ? 'active' : '' ?>">
      <i class="fas fa-chart-bar"></i>
      <span>Reports</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <?php if (!empty($_SESSION['org_president'])): ?>
    <div class="admin-info">
      <div class="avatar">
        <?= strtoupper(substr($_SESSION['org_president']['full_name'], 0, 2)) ?>
      </div>
      <div>
        <div class="a-name"><?= htmlspecialchars($_SESSION['org_president']['full_name']) ?></div>
        <div class="a-role">President</div>
      </div>
    </div>
    <?php endif; ?>
    <a href="logout.php" class="btn-logout">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>
