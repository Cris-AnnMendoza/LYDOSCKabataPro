<?php
require_once 'config.php';
requireLogin();
$admin = currentAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Programs – LYDO Admin</title>
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
    <div><h2>Programs</h2><p>Manage youth programs and activities.</p></div>
  </div>
  <div class="card">
    <div class="coming-soon" style="padding:80px">
      <i class="fas fa-graduation-cap" style="font-size:3rem;color:#94a3b8;display:block;margin-bottom:16px"></i>
      <p style="font-size:1rem;font-weight:600;color:#94a3b8">Programs module coming soon.</p>
      <p style="font-size:.85rem;color:#94a3b8;margin-top:6px">This section will allow you to manage all LYDO youth programs.</p>
    </div>
  </div>
</main>
</div>
</body>
</html>
