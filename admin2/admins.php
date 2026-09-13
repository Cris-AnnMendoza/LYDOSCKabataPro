<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('manage_admins')) { header('Location: dashboard.php'); exit; }

$pdo = db();

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? '';
        $brgy  = trim($_POST['barangay'] ?? '');
        if (!$name || !$email || !$pass || !$role) {
            flash('error', 'All required fields must be filled.');
        } else {
            $check = $pdo->prepare('SELECT id FROM admin_users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                flash('error', 'Email already exists.');
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare('INSERT INTO admin_users (full_name,email,password,role,barangay) VALUES (?,?,?,?,?)')
                    ->execute([$name, $email, $hash, $role, $brgy ?: null]);
                flash('success', "Admin '$name' created successfully.");
            }
        }
        header('Location: admins.php'); exit;
    }
    if ($_POST['action'] === 'toggle') {
        $id  = (int)$_POST['admin_id'];
        $cur = $pdo->prepare('SELECT is_active FROM admin_users WHERE id = ?');
        $cur->execute([$id]);
        $row = $cur->fetch();
        if ($row) {
            $pdo->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?')
                ->execute([$row['is_active'] ? 0 : 1, $id]);
            flash('success', 'Admin status updated.');
        }
        header('Location: admins.php'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['admin_id'];
        if ($id === (int)$_SESSION['admin_id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            flash('success', 'Admin deleted.');
        }
        header('Location: admins.php'); exit;
    }
}

$admins = $pdo->query('SELECT * FROM admin_users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Admins – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php if ($msg = flash('success')): ?>
    <div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($msg = flash('error')): ?>
    <div class="flash error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <div><h2>Manage Admins</h2><p>Create and manage admin accounts and roles.</p></div>
    <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
      <i class="fas fa-plus"></i> Add Admin
    </button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Barangay</th><th>Last Login</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $i => $a): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
            <td><?= htmlspecialchars($a['email']) ?></td>
            <td><?= roleBadge($a['role']) ?></td>
            <td><?= htmlspecialchars($a['barangay'] ?: '—') ?></td>
            <td><?= $a['last_login'] ? date('M j, Y g:i A', strtotime($a['last_login'])) : 'Never' ?></td>
            <td>
              <span class="badge <?= $a['is_active'] ? 'green' : 'red' ?>">
                <?= $a['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle"/>
                <input type="hidden" name="admin_id" value="<?= $a['id'] ?>"/>
                <button type="submit" class="btn-icon <?= $a['is_active'] ? 'red' : 'green' ?>" title="<?= $a['is_active'] ? 'Deactivate' : 'Activate' ?>">
                  <i class="fas fa-<?= $a['is_active'] ? 'ban' : 'check' ?>"></i>
                </button>
              </form>
              <?php if ($a['id'] !== (int)$_SESSION['admin_id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this admin?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="admin_id" value="<?= $a['id'] ?>"/>
                <button type="submit" class="btn-icon red" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>

<!-- ADD ADMIN MODAL -->
<div id="addModal" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-user-plus"></i> Add Admin Account</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="action" value="add"/>
      <div class="form-row-2">
        <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" required placeholder="Juan dela Cruz"/></div>
        <div class="fg"><label>Email <span class="req">*</span></label><input type="email" name="email" required placeholder="admin@lydo.gov.ph"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Password <span class="req">*</span></label><input type="password" name="password" required placeholder="Min. 8 characters"/></div>
        <div class="fg">
          <label>Role <span class="req">*</span></label>
          <select name="role" required onchange="document.getElementById('brgyField').style.display=this.value==='barangay_admin'?'block':'none'">
            <option value="">Select role</option>
            <option value="youth_coordinator">Youth Coordinator</option>
            <option value="barangay_admin">Barangay Admin</option>
            <option value="staff_encoder">Staff Encoder</option>
          </select>
        </div>
      </div>
      <div class="fg" id="brgyField" style="display:none">
        <label>Assigned Barangay</label>
        <input type="text" name="barangay" placeholder="e.g. Barangay 1 - Poblacion"/>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Admin</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
