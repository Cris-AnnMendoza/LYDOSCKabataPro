<?php
require_once "config.php";
requireLogin();
$pdo   = db();
$admin = currentAdmin();
$tab   = $_GET['tab'] ?? 'registrations';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['reg_id'] ?? 0);

    if ($action === 'update_status' && $regId) {
        $status  = $_POST['status'] ?? 'pending';
        $oriDate = $_POST['orientation_date'] ?: null;
        $notes   = trim($_POST['notes'] ?? '');
        $pdo->prepare('UPDATE volunteer_registrations SET status=?,orientation_date=?,notes=? WHERE id=?')
            ->execute([$status, $oriDate, $notes, $regId]);
        flash('success', 'Registration updated.');
        header('Location: volunteer_admin.php?tab=registrations'); exit;
    }
    if ($action === 'record_attendance' && $regId) {
        $eventName = trim($_POST['event_name'] ?? '');
        $eventDate = $_POST['event_date'] ?? date('Y-m-d');
        $hours     = (float)($_POST['hours'] ?? 0);
        $status    = $_POST['att_status'] ?? 'present';
        if ($eventName) {
            $pdo->prepare('INSERT INTO volunteer_attendance (registration_id,event_name,event_date,hours,status,recorded_by) VALUES (?,?,?,?,?,?)')
                ->execute([$regId, $eventName, $eventDate, $hours, $status, $admin['id']]);
            // Update total hours
            $pdo->prepare('UPDATE volunteer_registrations SET total_hours=total_hours+? WHERE id=?')
                ->execute([$hours, $regId]);
            flash('success', 'Attendance recorded.');
        }
        header('Location: volunteer_admin.php?tab=attendance'); exit;
    }
    if ($action === 'issue_certificate' && $regId) {
        $pdo->prepare('UPDATE volunteer_registrations SET certificate_issued=1 WHERE id=?')->execute([$regId]);
        flash('success', 'Certificate issued.');
        header('Location: volunteer_admin.php?tab=registrations'); exit;
    }
    if ($action === 'add_program') {
        $pdo->prepare('INSERT INTO volunteer_programs (name,type,description,min_age,max_age,slots,start_date,end_date,location,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([trim($_POST['name']),trim($_POST['type']),trim($_POST['description']??''),(int)$_POST['min_age'],(int)$_POST['max_age'],(int)$_POST['slots'],$_POST['start_date']?:null,$_POST['end_date']?:null,trim($_POST['location']??''),$admin['id']]);
        flash('success', 'Program added.');
        header('Location: volunteer_admin.php?tab=programs'); exit;
    }
}

$programs = $pdo->query('SELECT p.*,(SELECT COUNT(*) FROM volunteer_registrations r WHERE r.program_id=p.id) as reg_count FROM volunteer_programs p ORDER BY p.created_at DESC')->fetchAll();
$regs = $pdo->prepare('SELECT r.*,u.first_name,u.last_name,u.email,p.name as prog_name,p.type as prog_type FROM volunteer_registrations r JOIN youth_users u ON u.id=r.user_id JOIN volunteer_programs p ON p.id=r.program_id ORDER BY r.created_at DESC');
$regs->execute();
$registrations = $regs->fetchAll();
$attendance = $pdo->query('SELECT a.*,u.first_name,u.last_name,p.name as prog_name FROM volunteer_attendance a JOIN volunteer_registrations r ON r.id=a.registration_id JOIN youth_users u ON u.id=r.user_id JOIN volunteer_programs p ON p.id=r.program_id ORDER BY a.event_date DESC LIMIT 100')->fetchAll();
$leaderboard = $pdo->query('SELECT r.user_id,u.first_name,u.last_name,u.barangay,SUM(r.total_hours) as total_hours,COUNT(r.id) as programs FROM volunteer_registrations r JOIN youth_users u ON u.id=r.user_id WHERE r.status IN (\'approved\',\'completed\') GROUP BY r.user_id, u.first_name, u.last_name, u.barangay ORDER BY total_hours DESC LIMIT 20')->fetchAll();
$statusColors = ['pending'=>['#f57f17','#fff8e1'],'approved'=>['#2e7d32','#e8f5e9'],'rejected'=>['#c62828','#ffebee'],'completed'=>['#00796b','#e0f2f1']];
$typeLabels = ['youth_volunteer'=>'Youth Volunteer','linggo_kabataan'=>'Linggo ng Kabataan','junior_officials'=>'Junior Officials'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Volunteer Program  LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.tab-btn:hover:not(.active){background:rgba(255,255,255,.6)}
/* Action button group in table */
.action-group{display:flex;align-items:center;gap:6px;flex-wrap:nowrap}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;border:1.5px solid;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap}
.act-btn-edit{background:#e3f2fd;color:#1565c0;border-color:#90caf9}
.act-btn-edit:hover{background:#1565c0;color:#fff;border-color:#1565c0}
.act-btn-cert{background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7}
.act-btn-cert:hover{background:#2e7d32;color:#fff;border-color:#2e7d32}
/* Modal footer */
.vol-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;margin:16px -22px -22px;border-radius:0 0 16px 16px}
.vol-btn-cancel{padding:9px 20px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:600;cursor:pointer;transition:.2s}
.vol-btn-cancel:hover{background:#e2e8f0}
.vol-btn-save{padding:9px 20px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s;box-shadow:0 3px 10px rgba(21,101,192,.3)}
.vol-btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(21,101,192,.4)}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">
<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="page-header">
  <div><h2>Volunteer Program Management</h2><p>Manage programs, registrations, attendance, and certificates.</p></div>
  <?php if ($tab==='programs'): ?>
  <button class="btn-primary" onclick="document.getElementById('addProgModal').style.display='flex'"><i class="fas fa-plus"></i> Add Program</button>
  <?php endif; ?>
</div>

<div class="tabs">
  <a href="?tab=registrations" class="tab-btn <?=$tab==='registrations'?'active':''?>"><i class="fas fa-user-check"></i> Registrations</a>
  <a href="?tab=programs"      class="tab-btn <?=$tab==='programs'?'active':''?>"><i class="fas fa-list"></i> Programs</a>
</div>

<?php if ($tab === 'registrations'): ?>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Name</th><th>Program</th><th>Age</th><th>Contact</th><th>Hours</th><th>Status</th><th>Certificate</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($registrations)): ?>
        <tr><td colspan="9" class="empty">No registrations yet.</td></tr>
      <?php else: foreach ($registrations as $i => $r):
        [$sc,$sb] = $statusColors[$r['status']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></strong><br><small style="color:#94a3b8"><?=htmlspecialchars($r['email'])?></small></td>
          <td><span class="badge blue"><?=htmlspecialchars($typeLabels[$r['prog_type']]??$r['prog_type'])?></span><br><small><?=htmlspecialchars($r['prog_name'])?></small></td>
          <td><?=htmlspecialchars($r['age'] ?? '—')?></td>
          <td><?=htmlspecialchars($r['contact_number'] ?? '—')?></td>
          <td><strong><?=$r['total_hours']?></strong> hrs</td>
          <td><span class="badge" style="background:<?=$sb?>;color:<?=$sc?>"><?=ucfirst($r['status'])?></span></td>
          <td><?=$r['certificate_issued']?'<span class="badge green">Issued</span>':'<span class="badge gray">No</span>'?></td>
          <td>
            <div class="action-group">
              <button class="act-btn act-btn-edit" onclick="openUpdateModal(<?=$r['id']?>,<?=htmlspecialchars(json_encode($r),ENT_QUOTES)?>)">
                <i class="fas fa-edit"></i> Update
              </button>
              <?php if ($r['status']==='approved' && !$r['certificate_issued']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="issue_certificate"/>
                <input type="hidden" name="reg_id" value="<?=$r['id']?>"/>
                <button type="submit" class="act-btn act-btn-cert">
                  <i class="fas fa-certificate"></i> Issue Cert
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'programs'): ?>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Program</th><th>Type</th><th>Age Range</th><th>Slots</th><th>Registered</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($programs as $i => $p): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($p['name'])?></strong><br><small style="color:#94a3b8"><?=htmlspecialchars($p['location']?:'')?></small></td>
          <td><span class="badge blue"><?=htmlspecialchars($typeLabels[$p['type']]??$p['type'])?></span></td>
          <td><?=$p['min_age']?>-<?=$p['max_age']?> yrs</td>
          <td><?=$p['slots']?></td>
          <td><strong><?=$p['reg_count']?></strong>/<?=$p['slots']?></td>
          <td><span class="badge <?=($p['is_active'] ?? 1)?'green':'gray'?>"><?=($p['is_active'] ?? 1)?'Active':'Inactive'?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

</main>
</div>

<!-- UPDATE REGISTRATION MODAL -->
<div id="updateModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-height:none;overflow:visible">
    <div class="modal-head"><h3><i class="fas fa-edit"></i> Update Registration</h3><button onclick="document.getElementById('updateModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body" style="overflow:visible;max-height:none">
      <input type="hidden" name="action" value="update_status"/>
      <input type="hidden" name="reg_id" id="upd_reg_id"/>
      <div class="fg"><label>Status</label>
        <select name="status" id="upd_status">
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div class="fg"><label>Orientation Date</label><input type="date" name="orientation_date" id="upd_ori"/></div>
      <div class="fg"><label>Notes</label><textarea name="notes" id="upd_notes" rows="2"></textarea></div>
      <div class="vol-modal-footer">
        <button type="button" class="vol-btn-cancel" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
        <button type="submit" class="vol-btn-save"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD PROGRAM MODAL -->
<div id="addProgModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:580px">
    <div class="modal-head"><h3><i class="fas fa-plus"></i> Add Program</h3><button onclick="document.getElementById('addProgModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body" style="overflow:visible">
      <input type="hidden" name="action" value="add_program"/>
      <div class="form-row-2">
        <div class="fg"><label>Program Name <span class="req">*</span></label><input type="text" name="name" required/></div>
        <div class="fg"><label>Type <span class="req">*</span></label>
          <select name="type" required>
            <option value="youth_volunteer">Youth Volunteer Program</option>
            <option value="linggo_kabataan">Linggo ng Kabataan</option>
            <option value="junior_officials">Junior Officials Program</option>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Min Age</label><input type="number" name="min_age" value="15" min="1" max="100"/></div>
        <div class="fg"><label>Max Age</label><input type="number" name="max_age" value="30" min="1" max="100"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Slots</label><input type="number" name="slots" value="50" min="1"/></div>
        <div class="fg"><label>Location</label><input type="text" name="location" placeholder="e.g. Municipal Hall"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Start Date</label><input type="date" name="start_date"/></div>
        <div class="fg"><label>End Date</label><input type="date" name="end_date"/></div>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" rows="2"></textarea></div>
      <div class="vol-modal-footer">
        <button type="button" class="vol-btn-cancel" onclick="document.getElementById('addProgModal').style.display='none'">Cancel</button>
        <button type="submit" class="vol-btn-save"><i class="fas fa-save"></i> Add Program</button>
      </div>
    </form>
  </div>
</div>

<!-- ATTENDANCE MODAL -->
<div id="attModal" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-calendar-check"></i> Record Attendance</h3><button onclick="document.getElementById('attModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="action" value="record_attendance"/>
      <div class="fg"><label>Volunteer <span class="req">*</span></label>
        <select name="reg_id" required>
          <option value="">Select volunteer...</option>
          <?php foreach ($registrations as $r): if ($r['status']==='approved'): ?>
          <option value="<?=$r['id']?>"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?>  <?=htmlspecialchars($r['prog_name'])?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Event Name <span class="req">*</span></label><input type="text" name="event_name" required placeholder="e.g. Coastal Cleanup"/></div>
        <div class="fg"><label>Event Date</label><input type="date" name="event_date" value="<?=date('Y-m-d')?>"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Hours</label><input type="number" name="hours" value="2" min="0" step="0.5"/></div>
        <div class="fg"><label>Status</label>
          <select name="att_status">
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="excused">Excused</option>
          </select>
        </div>
      </div>
      <div class="vol-modal-footer">
        <button type="button" class="vol-btn-cancel" onclick="document.getElementById('attModal').style.display='none'">Cancel</button>
        <button type="submit" class="vol-btn-save"><i class="fas fa-save"></i> Record</button>
      </div>
      </div>
    </form>
  </div>
</div>

<script>
function openUpdateModal(id, r) {
  document.getElementById('upd_reg_id').value = id;
  document.getElementById('upd_status').value = r.status;
  document.getElementById('upd_ori').value    = r.orientation_date || '';
  document.getElementById('upd_notes').value  = r.notes || '';
  document.getElementById('updateModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Override all modal close buttons
document.addEventListener('DOMContentLoaded', function() {
  // Get all modal close buttons and update their onclick
  document.querySelectorAll('.modal-close').forEach(function(btn) {
    const modal = btn.closest('.modal-overlay');
    if (modal) {
      btn.onclick = function() {
        closeModal(modal.id);
      };
    }
  });
  
  // Get all cancel buttons
  document.querySelectorAll('.vol-btn-cancel').forEach(function(btn) {
    const modal = btn.closest('.modal-overlay');
    if (modal) {
      btn.onclick = function() {
        closeModal(modal.id);
      };
    }
  });
  
  // Close on overlay click
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal(this.id);
      }
    });
  });
  
  // Close on ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        if (modal.style.display === 'flex') {
          closeModal(modal.id);
        }
      });
    }
  });
  
  // Update Add Program button
  const addProgBtn = document.querySelector('button[onclick*="addProgModal"]');
  if (addProgBtn) {
    addProgBtn.onclick = function() {
      document.getElementById('addProgModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    };
  }
});
</script>
</body></html>
