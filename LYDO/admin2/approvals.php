<?php
require_once 'config.php';
require_once __DIR__ . '/../shared/email_config.php';
requireLogin();
if (!hasPermission('view_users')) { header('Location: dashboard.php'); exit; }

$pdo  = db();
$tab  = $_GET['tab'] ?? 'youth';   // youth | staff | president

// ── Handle approve / reject ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['type'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];   // approve | reject
    $type   = $_POST['type'];     // youth | staff | president
    
    // Debug logging
    error_log("Approvals POST: type=$type, action=$action, id=$id");
    
    $status = $action === 'approve' ? 'approved' : 'rejected';

    if ($type === 'youth') {
        $pdo->prepare('UPDATE youth_users SET status = ? WHERE id = ?')->execute([$status, $id]);
        
        // Send email notification if approved
        if ($action === 'approve') {
            // Get user email
            $stmt = $pdo->prepare('SELECT email, first_name, last_name FROM youth_users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user && $user['email']) {
                $to = $user['email'];
                $name = $user['first_name'] . ' ' . $user['last_name'];
                $subject = 'LYDO Account Approved - You Can Now Login';
                
                $message = "
                <html>
                <head>
                  <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; }
                    .header { background: linear-gradient(135deg, #0d3b6e, #1565c0); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { background: #ffffff; padding: 30px; border: 1px solid #e2e8f0; }
                    .success-badge { background: #e8f5e9; color: #2e7d32; padding: 10px 20px; border-radius: 50px; display: inline-block; font-weight: bold; margin: 20px 0; }
                    .btn { display: inline-block; background: #1565c0; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .info-box { background: #f8fafc; padding: 15px; border-left: 4px solid #1565c0; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #64748b; font-size: 14px; }
                    ul { padding-left: 20px; }
                    li { margin: 8px 0; }
                  </style>
                </head>
                <body>
                  <div class='container'>
                    <div class='header'>
                      <h1>✅ Account Approved!</h1>
                      <p style='margin: 10px 0 0 0; font-size: 16px;'>Local Youth Development Office</p>
                    </div>
                    <div class='content'>
                      <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                      
                      <div class='success-badge'>
                        🎉 Your registration has been approved!
                      </div>
                      
                      <p>Congratulations! Your LYDO youth account has been reviewed and approved by our administrator. You can now login and access all youth programs and services.</p>
                      
                      <div class='info-box'>
                        <strong>🔑 Your Login Details:</strong><br/>
                        <strong>Email:</strong> " . htmlspecialchars($to) . "<br/>
                        <strong>Password:</strong> (The password you created during registration)
                      </div>
                      
                      <center>
                        <a href='http://localhost/LYDO/lydo-system/login.php' class='btn'>
                          🚀 Login to Your Account
                        </a>
                      </center>
                      
                      <p><strong>What you can do now:</strong></p>
                      <ul>
                        <li>✅ Access your personal dashboard</li>
                        <li>✅ Register for youth programs and events</li>
                        <li>✅ Apply for scholarships and assistance</li>
                        <li>✅ Track your participation and certificates</li>
                        <li>✅ Connect with other youth members</li>
                      </ul>
                      
                      <p>If you have any questions or need assistance, please contact us at:</p>
                      <p>
                        📧 Email: youth@stacruzlaguna.gov.ph<br/>
                        📞 Phone: (049) 000-0000<br/>
                        📍 Address: Municipal Hall, Sta. Cruz, Laguna 4009
                      </p>
                      
                      <p>Welcome to the LYDO family! 🎊</p>
                    </div>
                    <div class='footer'>
                      <p>© 2026 Local Youth Development Office<br/>
                      Municipal Government of Sta. Cruz, Laguna</p>
                      <p style='font-size: 12px; color: #94a3b8;'>
                        This is an automated message. Please do not reply to this email.
                      </p>
                    </div>
                  </div>
                </body>
                </html>
                ";
                
                // Send email using PHPMailer
                $emailSent = sendEmail($to, $name, $subject, $message);
                
                if ($emailSent) {
                    flash('success', 'Youth registration approved. ✅ Email notification sent successfully.');
                } else {
                    flash('success', 'Youth registration approved.');
                }
            } else {
                flash('success', 'Youth registration approved.');
            }
        } else {
            flash('success', 'Youth registration rejected.');
        }
    } elseif ($type === 'staff' && hasPermission('manage_admins')) {
        // Admin users don't use status field - use is_active instead
        $isActive = ($action === 'approve') ? 'TRUE' : 'FALSE';
        $pdo->prepare('UPDATE admin_users SET is_active = ' . $isActive . ' WHERE id = ?')->execute([$id]);
        flash('success', $action === 'approve' ? 'Staff account activated.' : 'Staff account deactivated.');
    } elseif ($type === 'president') {
        // Organization presidents use is_active field
        $isActive = ($action === 'approve') ? 1 : 0;
        error_log("President update: id=$id, isActive=$isActive");
        $result = $pdo->prepare('UPDATE organization_presidents SET is_active = ? WHERE id = ?')->execute([$isActive, $id]);
        error_log("President update result: " . ($result ? 'success' : 'failed'));
        
        // Send email notification if approved
        if ($action === 'approve') {
            $stmt = $pdo->prepare('SELECT email, full_name FROM organization_presidents WHERE id = ?');
            $stmt->execute([$id]);
            $president = $stmt->fetch();
            
            if ($president && $president['email']) {
                $to = $president['email'];
                $name = $president['full_name'];
                $subject = 'LYDO Organization President Account Approved';
                
                $message = "
                <html>
                <head>
                  <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; }
                    .header { background: linear-gradient(135deg, #7b1fa2, #9c27b0); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { background: #ffffff; padding: 30px; border: 1px solid #e2e8f0; }
                    .success-badge { background: #e8f5e9; color: #2e7d32; padding: 10px 20px; border-radius: 50px; display: inline-block; font-weight: bold; margin: 20px 0; }
                    .btn { display: inline-block; background: #7b1fa2; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .info-box { background: #f8fafc; padding: 15px; border-left: 4px solid #7b1fa2; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #64748b; font-size: 14px; }
                    ul { padding-left: 20px; }
                    li { margin: 8px 0; }
                  </style>
                </head>
                <body>
                  <div class='container'>
                    <div class='header'>
                      <h1>✅ Organization President Account Approved!</h1>
                      <p style='margin: 10px 0 0 0; font-size: 16px;'>Local Youth Development Office</p>
                    </div>
                    <div class='content'>
                      <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                      
                      <div class='success-badge'>
                        🎉 Your organization president account has been approved!
                      </div>
                      
                      <p>Congratulations! Your LYDO organization president account has been reviewed and approved. You can now login and access the organization president dashboard.</p>
                      
                      <div class='info-box'>
                        <strong>🔑 Your Login Details:</strong><br/>
                        <strong>Email:</strong> " . htmlspecialchars($to) . "<br/>
                        <strong>Password:</strong> (The password you created during registration)
                      </div>
                      
                      <center>
                        <a href='http://localhost/LYDO/lydo-system/org-president/login.php' class='btn'>
                          🚀 Login to President Dashboard
                        </a>
                      </center>
                      
                      <p><strong>As an organization president, you can:</strong></p>
                      <ul>
                        <li>✅ View your organization's merit/demerit points</li>
                        <li>✅ See detailed violation and warning records</li>
                        <li>✅ Apply for organization accreditation</li>
                        <li>✅ Receive automatic notifications for consequences</li>
                        <li>✅ Manage organization information</li>
                      </ul>
                      
                      <p>If you have any questions or need assistance, please contact us at:</p>
                      <p>
                        📧 Email: youth@stacruzlaguna.gov.ph<br/>
                        📞 Phone: (049) 000-0000<br/>
                        📍 Address: Municipal Hall, Sta. Cruz, Laguna 4009
                      </p>
                      
                      <p>Welcome to the LYDO organization leadership! 🎊</p>
                    </div>
                    <div class='footer'>
                      <p>© 2026 Local Youth Development Office<br/>
                      Municipal Government of Sta. Cruz, Laguna</p>
                      <p style='font-size: 12px; color: #94a3b8;'>
                        This is an automated message. Please do not reply to this email.
                      </p>
                    </div>
                  </div>
                </body>
                </html>
                ";
                
                $emailSent = sendEmail($to, $name, $subject, $message);
                
                if ($emailSent) {
                    flash('success', 'Organization president account approved. ✅ Email notification sent successfully.');
                } else {
                    flash('success', 'Organization president account approved.');
                }
            } else {
                flash('success', 'Organization president account approved.');
            }
        } else {
            flash('success', 'Organization president account rejected.');
        }
    }
    // Redirect back to the same tab
    header('Location: approvals.php?tab=' . $type); 
    exit;
}

// ── Counts for badges ─────────────────────────────────────
$pendingYouth = (int)$pdo->query("SELECT COUNT(*) FROM youth_users WHERE status='pending'")->fetchColumn();
// Admin users don't have pending status - they are either active or inactive
$pendingStaff = 0;
// Organization presidents pending approval
$pendingPresidents = (int)$pdo->query("SELECT COUNT(*) FROM organization_presidents WHERE is_active = 0")->fetchColumn();

// ── Fetch list ────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'pending';   // pending | approved | rejected | all
$validFilters = ['pending','approved','rejected','all'];
if (!in_array($filter, $validFilters)) $filter = 'pending';

if ($tab === 'youth') {
    $whereStatus = $filter === 'all' ? '' : "WHERE status = '$filter'";
    $rows = $pdo->query("SELECT id,first_name,last_name,email,gender,barangay,youth_classification,status,created_at
                         FROM youth_users $whereStatus ORDER BY created_at DESC")->fetchAll();
} elseif ($tab === 'president') {
    // Organization presidents - use is_active field
    // Note: is_active=0 means pending, there's no separate rejected state
    // So we treat pending and rejected filter the same way
    if ($filter === 'approved') {
        $whereStatus = "WHERE op.is_active = 1";
    } elseif ($filter === 'pending') {
        $whereStatus = "WHERE op.is_active = 0";
    } elseif ($filter === 'rejected') {
        // Show nothing for rejected since presidents don't have rejected state
        $whereStatus = "WHERE 1=0";
    } else {
        $whereStatus = '';
    }
    $rows = $pdo->query("SELECT op.id, op.full_name, op.email, op.contact_number, op.is_active, op.created_at,
                                o.name as organization_name
                         FROM organization_presidents op
                         LEFT JOIN organizations o ON o.id = op.organization_id
                         $whereStatus ORDER BY op.created_at DESC")->fetchAll();
} else {
    // Admin users don't have status - use is_active instead
    if ($filter === 'approved') {
        $whereStatus = "WHERE is_active = TRUE";
    } elseif ($filter === 'rejected' || $filter === 'pending') {
        $whereStatus = "WHERE is_active = FALSE";
    } else {
        $whereStatus = '';
    }
    $rows = $pdo->query("SELECT id,full_name,email,role,barangay,is_active,created_at
                         FROM admin_users $whereStatus ORDER BY created_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Approvals – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content}
.tab-btn{padding:8px 20px;border-radius:8px;border:none;font-family:inherit;font-size:.88rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;display:flex;align-items:center;gap:7px}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.tab-btn .cnt{background:#e53935;color:#fff;border-radius:50px;padding:1px 7px;font-size:.7rem;font-weight:700}
.filter-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.ftab{padding:6px 14px;border-radius:8px;border:1.5px solid #e2e8f0;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;color:#475569;background:#fff;text-decoration:none;transition:.2s}
.ftab:hover,.ftab.active{background:#1565c0;border-color:#1565c0;color:#fff}
.status-pending{background:#fff8e1;color:#f57f17;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.status-approved{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.status-rejected{background:#ffebee;color:#c62828;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.action-btns{display:flex;gap:6px;align-items:center}
.btn-approve{padding:5px 12px;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
.btn-approve:hover{background:#2e7d32;color:#fff;border-color:#2e7d32}
.btn-reject{padding:5px 12px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:7px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
.btn-reject:hover{background:#c62828;color:#fff;border-color:#c62828}
.empty-state{text-align:center;padding:48px 20px;color:#94a3b8}
.empty-state i{font-size:2.5rem;margin-bottom:12px;display:block}
.empty-state p{font-size:.95rem;font-weight:500}
</style>
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
      <h2>Registration Approvals</h2>
      <p>Review and approve or reject pending registrations.</p>
    </div>
  </div>

  <!-- TABS: Youth / President / Staff -->
  <div class="tabs">
    <a href="?tab=youth&filter=<?= $filter ?>" class="tab-btn <?= $tab==='youth'?'active':'' ?>">
      <i class="fas fa-users"></i> Youth Registrations
      <?php if ($pendingYouth > 0): ?><span class="cnt"><?= $pendingYouth ?></span><?php endif; ?>
    </a>
    <a href="?tab=president&filter=<?= $filter ?>" class="tab-btn <?= $tab==='president'?'active':'' ?>">
      <i class="fas fa-user-tie"></i> President Accounts
      <?php if ($pendingPresidents > 0): ?><span class="cnt"><?= $pendingPresidents ?></span><?php endif; ?>
    </a>
    <?php if (hasPermission('manage_admins')): ?>
    <a href="?tab=staff&filter=<?= $filter ?>" class="tab-btn <?= $tab==='staff'?'active':'' ?>">
      <i class="fas fa-user-shield"></i> Staff Accounts
      <?php if ($pendingStaff > 0): ?><span class="cnt"><?= $pendingStaff ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
  </div>

  <!-- FILTER TABS -->
  <div class="filter-tabs">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $f => $label): ?>
      <a href="?tab=<?= $tab ?>&filter=<?= $f ?>" class="ftab <?= $filter===$f?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="table-wrap">
      <?php if ($tab === 'youth'): ?>
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Gender</th><th>Barangay</th><th>Classification</th><th>Registered</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <i class="fas fa-check-double"></i>
              <p>No <?= $filter === 'all' ? '' : $filter ?> youth registrations.</p>
            </div>
          </td></tr>
        <?php else: foreach ($rows as $i => $r):
          $cls = $r['youth_classification'] ? json_decode($r['youth_classification'],true) : [];
          $cls = is_array($cls) ? ($cls[0] ?? '—') : ($r['youth_classification'] ?: '—');
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['gender'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['barangay'] ?: '—') ?></td>
            <td><span class="badge blue"><?= htmlspecialchars($cls) ?></span></td>
            <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <div style="display:flex;gap:6px;">
                  <form method="POST" action="approvals.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                    <input type="hidden" name="type" value="youth"/>
                    <input type="hidden" name="action" value="approve"/>
                    <button type="submit" style="padding:6px 12px;background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:0.8rem;font-weight:700;cursor:pointer;transition:0.2s;">
                      Approve
                    </button>
                  </form>
                  <form method="POST" action="approvals.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                    <input type="hidden" name="type" value="youth"/>
                    <input type="hidden" name="action" value="reject"/>
                    <button type="submit" style="padding:6px 12px;background:#ffebee;color:#c62828;border:2px solid #ef9a9a;border-radius:7px;font-family:inherit;font-size:0.8rem;font-weight:700;cursor:pointer;transition:0.2s;">
                      Reject
                    </button>
                  </form>
                </div>
              <?php elseif ($r['status'] === 'approved'): ?>
                <span style="display:inline-block;padding:6px 14px;background:#e8f5e9;color:#2e7d32;border-radius:8px;font-size:0.85rem;font-weight:700;border:2px solid #a5d6a7;">
                  Approved
                </span>
              <?php else: ?>
                <span style="display:inline-block;padding:6px 14px;background:#ffebee;color:#c62828;border-radius:8px;font-size:0.85rem;font-weight:700;border:2px solid #ef9a9a;">
                  Rejected
                </span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <?php elseif ($tab === 'president'): /* PRESIDENT TAB */ ?>
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Full Name</th><th>Email</th><th>Organization</th><th>Contact</th><th>Registered</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <i class="fas fa-check-double"></i>
              <p>No <?= $filter === 'all' ? '' : ($filter === 'approved' ? 'approved' : 'pending') ?> organization president accounts.</p>
            </div>
          </td></tr>
        <?php else: foreach ($rows as $i => $r):
          $statusDisplay = $r['is_active'] ? 'approved' : 'pending';
          $statusText = $r['is_active'] ? 'Active' : 'Pending';
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><span class="badge purple"><?= htmlspecialchars($r['organization_name'] ?: 'N/A') ?></span></td>
            <td><?= htmlspecialchars($r['contact_number'] ?: '—') ?></td>
            <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td>
              <?php if (!$r['is_active']): ?>
                <div style="display:flex;gap:6px;">
                  <form method="POST" action="approvals.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                    <input type="hidden" name="type" value="president"/>
                    <input type="hidden" name="action" value="approve"/>
                    <button type="submit" style="padding:6px 12px;background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:0.8rem;font-weight:700;cursor:pointer;transition:0.2s;">
                      Approve
                    </button>
                  </form>
                  <form method="POST" action="approvals.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                    <input type="hidden" name="type" value="president"/>
                    <input type="hidden" name="action" value="reject"/>
                    <button type="submit" style="padding:6px 12px;background:#ffebee;color:#c62828;border:2px solid #ef9a9a;border-radius:7px;font-family:inherit;font-size:0.8rem;font-weight:700;cursor:pointer;transition:0.2s;">
                      Reject
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span style="display:inline-block;padding:6px 14px;background:#e8f5e9;color:#2e7d32;border-radius:8px;font-size:0.85rem;font-weight:700;border:2px solid #a5d6a7;">
                  Active
                </span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <?php else: /* STAFF TAB */ ?>
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Full Name</th><th>Email</th><th>Role</th><th>Barangay</th><th>Registered</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <i class="fas fa-check-double"></i>
              <p>No <?= $filter === 'all' ? '' : $filter ?> staff accounts.</p>
            </div>
          </td></tr>
        <?php else: foreach ($rows as $i => $r):
          // Convert is_active to status-like display
          $statusDisplay = $r['is_active'] ? 'approved' : 'rejected';
          $statusText = $r['is_active'] ? 'Active' : 'Inactive';
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= roleBadge($r['role']) ?></td>
            <td><?= htmlspecialchars($r['barangay'] ?: '—') ?></td>
            <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td>
              <?php if (!$r['is_active']): ?>
                <form method="POST" action="approvals.php" style="margin:0;">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                  <input type="hidden" name="type" value="staff"/>
                  <input type="hidden" name="action" value="approve"/>
                  <button type="submit" style="padding:6px 12px;background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:0.8rem;font-weight:700;cursor:pointer;transition:0.2s;">
                    Activate
                  </button>
                </form>
              <?php else: ?>
                <span style="display:inline-block;padding:6px 14px;background:#e8f5e9;color:#2e7d32;border-radius:8px;font-size:0.85rem;font-weight:700;border:2px solid #a5d6a7;">
                  Active
                </span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>
</body>
</html>
