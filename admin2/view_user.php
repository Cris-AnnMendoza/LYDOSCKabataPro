<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('view_users')) { header('Location: dashboard.php'); exit; }

$id   = (int)($_GET['id'] ?? 0);
$user = db()->prepare('SELECT * FROM youth_users WHERE id = ?');
$user->execute([$id]);
$u = $user->fetch();
if (!$u) { flash('error','User not found.'); header('Location: users.php'); exit; }

$cls  = $u['youth_classification'] ? json_decode($u['youth_classification'],true) : [];
$prgs = $u['programs_interested']  ? json_decode($u['programs_interested'],true)  : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>View User – LYDO Admin</title>
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
    <div>
      <h2><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></h2>
      <p>Registered: <?= date('F j, Y', strtotime($u['created_at'])) ?></p>
    </div>
    <a href="users.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  <div class="profile-grid">
    <!-- Personal -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-user"></i> Personal Information</h3></div>
      <div class="info-list">
        <?php
        $fields = [
          'Full Name'      => $u['first_name'].' '.($u['middle_name']?$u['middle_name'].' ':'').$u['last_name'].($u['suffix']?' '.$u['suffix']:''),
          'Gender'         => $u['gender'],
          'Birthdate'      => $u['birthdate'] ? date('F j, Y', strtotime($u['birthdate'])) : '—',
          'Age'            => $u['age'],
          'Civil Status'   => $u['civil_status'],
          'Contact Number' => $u['contact_number'],
          'Email'          => $u['email'],
        ];
        foreach ($fields as $label => $val): ?>
        <div class="info-row">
          <span class="info-label"><?= $label ?></span>
          <span class="info-val"><?= htmlspecialchars($val ?: '—') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Address -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-map-marker-alt"></i> Address</h3></div>
      <div class="info-list">
        <?php
        $addr = [
          'House / Street' => $u['house_number'].' '.$u['street'],
          'Barangay'       => $u['barangay'],
          'Municipality'   => $u['municipality'],
          'Province'       => $u['province'],
          'ZIP Code'       => $u['zip_code'],
        ];
        foreach ($addr as $label => $val): ?>
        <div class="info-row">
          <span class="info-label"><?= $label ?></span>
          <span class="info-val"><?= htmlspecialchars(trim($val) ?: '—') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Classification -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-id-card"></i> Youth Classification</h3></div>
      <div class="badge-list">
        <?php if ($cls): foreach ((array)$cls as $c): ?>
          <span class="badge blue"><?= htmlspecialchars($c) ?></span>
        <?php endforeach; else: ?>
          <span class="empty">Not specified</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Education -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-graduation-cap"></i> Education & Employment</h3></div>
      <div class="info-list">
        <?php
        $edu = [
          'Educational Status' => $u['educational_status'],
          'School Name'        => $u['school_name'],
          'Course / Grade'     => $u['course_or_grade'],
          'Employment Status'  => $u['employment_status'],
          'Organization'       => $u['organization_name'],
          'Org. Type'          => $u['organization_type'],
          'Position / Role'    => $u['organization_role'],
          'Years of Membership'=> $u['years_membership'],
        ];
        foreach ($edu as $label => $val): ?>
        <div class="info-row">
          <span class="info-label"><?= $label ?></span>
          <span class="info-val"><?= htmlspecialchars($val ?: '—') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Additional -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-star"></i> Additional Info</h3></div>
      <div class="info-list">
        <div class="info-row"><span class="info-label">Skills</span><span class="info-val"><?= htmlspecialchars($u['skills'] ?: '—') ?></span></div>
        <div class="info-row"><span class="info-label">Interests</span><span class="info-val"><?= htmlspecialchars($u['interests'] ?: '—') ?></span></div>
        <div class="info-row"><span class="info-label">Volunteer Availability</span><span class="info-val"><?= htmlspecialchars($u['volunteer_availability'] ?: '—') ?></span></div>
      </div>
      <?php if ($prgs): ?>
      <div style="padding:0 20px 16px">
        <p style="font-size:.8rem;font-weight:600;color:#475569;margin-bottom:8px">Programs Interested In:</p>
        <div class="badge-list">
          <?php foreach ((array)$prgs as $p): ?>
            <span class="badge green"><?= htmlspecialchars($p) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>
</body>
</html>
