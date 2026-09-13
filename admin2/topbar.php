<?php
$admin   = currentAdmin();
$current = basename($_SERVER['PHP_SELF']);
$titles  = [
    'dashboard.php'=>'Dashboard','users.php'=>'Youth Users',
    'programs.php'=>'Programs','events.php'=>'Events',
    'reports.php'=>'Reports','admins.php'=>'Manage Admins',
    'logs.php'=>'Activity Logs',
];
$title = $titles[$current] ?? 'Dashboard';
$roleColors = [
    'super_admin'=>'#f57f17','youth_coordinator'=>'#1565c0',
    'barangay_admin'=>'#2e7d32','staff_encoder'=>'#475569',
];
$roleBgs = [
    'super_admin'=>'#fff8e1','youth_coordinator'=>'#e3f2fd',
    'barangay_admin'=>'#e8f5e9','staff_encoder'=>'#f1f5f9',
];
$rc = $roleColors[$admin['role']] ?? '#475569';
$rb = $roleBgs[$admin['role']]   ?? '#f1f5f9';
?>
<header class="topbar">
  <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <i class="fas fa-bars"></i>
  </button>
  <span class="topbar-title"><?= $title ?></span>
  <div class="topbar-right">
    <span style="font-size:.85rem;color:#475569;font-weight:500"><?= htmlspecialchars($admin['full_name']) ?></span>
    <span style="background:<?= $rb ?>;color:<?= $rc ?>;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700">
      <?= str_replace('_',' ',$admin['role']) ?>
    </span>
  </div>
</header>
