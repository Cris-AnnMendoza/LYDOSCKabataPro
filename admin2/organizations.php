<?php
require_once 'config.php';
requireLogin();

$pdo = db();
$admin = currentAdmin();

// ── Handle POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'name'          => trim($_POST['name'] ?? ''),
            'category'      => trim($_POST['category'] ?? ''),
            'description'   => trim($_POST['description'] ?? ''),
            'adviser_name'  => trim($_POST['adviser_name'] ?? ''),
            'adviser_email' => trim($_POST['adviser_email'] ?? ''),
            'adviser_phone' => trim($_POST['adviser_phone'] ?? ''),
            'barangay'      => trim($_POST['barangay'] ?? ''),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!$data['name']) { flash('error','Organization name is required.'); header('Location: organizations.php'); exit; }

        if ($action === 'add') {
            $pdo->prepare('INSERT INTO organizations (name,category,description,adviser_name,adviser_email,adviser_phone,barangay,is_active) VALUES (?,?,?,?,?,?,?,?)')
                ->execute(array_values($data));
            flash('success','Organization added successfully.');
        } else {
            $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
            $vals = array_values($data);
            $vals[] = $id;
            $pdo->prepare("UPDATE organizations SET $sets WHERE id=?")->execute($vals);
            flash('success','Organization updated.');
        }
        header('Location: organizations.php'); exit;
    }

    if ($action === 'toggle') {
        $id  = (int)$_POST['id'];
        $cur = $pdo->prepare('SELECT is_active FROM organizations WHERE id=?');
        $cur->execute([$id]);
        $row = $cur->fetch();
        if ($row) {
            $pdo->prepare('UPDATE organizations SET is_active=? WHERE id=?')->execute([$row['is_active']?0:1, $id]);
            flash('success','Organization status updated.');
        }
        header('Location: organizations.php'); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([(int)$_POST['id']]);
        flash('success','Organization deleted.');
        header('Location: organizations.php'); exit;
    }
}

$orgs = $pdo->query('SELECT o.*, (SELECT COUNT(*) FROM organization_members m WHERE m.organization_id=o.id AND m.is_active = TRUE) as member_count FROM organizations o ORDER BY o.name')->fetchAll();
$totalActive   = count(array_filter($orgs, fn($o) => $o['is_active']));
$totalInactive = count($orgs) - $totalActive;

$categories = ['Sangguniang Kabataan','Youth NGO','Religious Organization','Sports Club','Academic Organization','Community Group','Cultural Group','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Organizations – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if ($msg=flash('error')):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="page-header">
  <div><h2>Organizations</h2><p>Manage youth organizations and their members.</p></div>
  <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
    <i class="fas fa-plus"></i> Add Organization
  </button>
</div>

<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">
  <div class="stat-card blue"><div class="stat-icon"><i class="fas fa-sitemap"></i></div><div><span class="stat-val"><?=count($orgs)?></span><span class="stat-lbl">Total Organizations</span></div></div>
  <div class="stat-card green"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div><span class="stat-val"><?=$totalActive?></span><span class="stat-lbl">Active</span></div></div>
  <div class="stat-card" style="border:1px solid #e2e8f0"><div class="stat-icon" style="background:#f1f5f9;color:#475569"><i class="fas fa-pause-circle"></i></div><div><span class="stat-val"><?=$totalInactive?></span><span class="stat-lbl">Inactive</span></div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Organization</th><th>Category</th><th>Adviser</th><th>Barangay</th><th>Members</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($orgs)): ?>
        <tr><td colspan="8" class="empty">No organizations yet. Add one to get started.</td></tr>
      <?php else: foreach ($orgs as $i => $o): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($o['name'])?></strong><?php if($o['description']): ?><br><small style="color:#94a3b8"><?=htmlspecialchars(substr($o['description'],0,60))?>...</small><?php endif; ?></td>
          <td><span class="badge blue"><?=htmlspecialchars($o['category']?:'—')?></span></td>
          <td><?=htmlspecialchars($o['adviser_name']?:'—')?></td>
          <td><?=htmlspecialchars($o['barangay']?:'—')?></td>
          <td><strong><?=$o['member_count']?></strong></td>
          <td><span class="badge <?=$o['is_active']?'green':'gray'?>"><?=$o['is_active']?'Active':'Inactive'?></span></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this organization?')">
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="id" value="<?=$o['id']?>"/>
              <button type="submit" class="btn-icon red" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-head"><h3><i class="fas fa-plus"></i> Add Organization</h3><button onclick="document.getElementById('addModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="action" value="add"/>
      <div class="form-row-2">
        <div class="fg"><label>Organization Name <span class="req">*</span></label><input type="text" name="name" required placeholder="e.g. SK Barangay 1"/></div>
        <div class="fg"><label>Category</label>
          <select name="category"><?php foreach($categories as $c): ?><option value="<?=$c?>"><?=$c?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Adviser Name</label><input type="text" name="adviser_name" placeholder="Full name"/></div>
        <div class="fg"><label>Adviser Email</label><input type="email" name="adviser_email" placeholder="email@example.com"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Adviser Phone</label><input type="text" name="adviser_phone" placeholder="09XX-XXX-XXXX"/></div>
        <div class="fg"><label>Barangay</label><input type="text" name="barangay" placeholder="e.g. Barangay 1 - Poblacion"/></div>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" rows="2" placeholder="Brief description..."></textarea></div>
      <label class="chk-label" style="margin-bottom:16px"><input type="checkbox" name="is_active" checked/><span class="chk"></span> Active</label>
      <div class="modal-footer"><button type="button" class="btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button><button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save</button></div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-head"><h3><i class="fas fa-edit"></i> Edit Organization</h3><button onclick="document.getElementById('editModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body" id="editForm">
      <input type="hidden" name="action" value="edit"/>
      <input type="hidden" name="id" id="edit_id"/>
      <div class="form-row-2">
        <div class="fg"><label>Organization Name <span class="req">*</span></label><input type="text" name="name" id="edit_name" required/></div>
        <div class="fg"><label>Category</label>
          <select name="category" id="edit_category"><?php foreach($categories as $c): ?><option value="<?=$c?>"><?=$c?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Adviser Name</label><input type="text" name="adviser_name" id="edit_adviser_name"/></div>
        <div class="fg"><label>Adviser Email</label><input type="email" name="adviser_email" id="edit_adviser_email"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Adviser Phone</label><input type="text" name="adviser_phone" id="edit_adviser_phone"/></div>
        <div class="fg"><label>Barangay</label><input type="text" name="barangay" id="edit_barangay"/></div>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" id="edit_description" rows="2"></textarea></div>
      <label class="chk-label" style="margin-bottom:16px"><input type="checkbox" name="is_active" id="edit_is_active"/><span class="chk"></span> Active</label>
      <div class="modal-footer"><button type="button" class="btn-secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button><button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button></div>
    </form>
  </div>
</div>

<script>
function openEdit(o) {
  document.getElementById('edit_id').value           = o.id;
  document.getElementById('edit_name').value         = o.name;
  document.getElementById('edit_category').value     = o.category || '';
  document.getElementById('edit_adviser_name').value = o.adviser_name || '';
  document.getElementById('edit_adviser_email').value= o.adviser_email || '';
  document.getElementById('edit_adviser_phone').value= o.adviser_phone || '';
  document.getElementById('edit_barangay').value     = o.barangay || '';
  document.getElementById('edit_description').value  = o.description || '';
  document.getElementById('edit_is_active').checked  = o.is_active == 1;
  document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>
