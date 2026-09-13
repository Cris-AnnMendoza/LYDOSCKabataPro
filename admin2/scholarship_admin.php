<?php
require_once "config.php";
requireLogin();
$pdo   = db();
$admin = currentAdmin();
$tab   = $_GET['tab'] ?? 'applications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['app_id'] ?? 0);

    if ($action === 'update_status' && $appId) {
        $status     = $_POST['status'] ?? '';
        $examScore  = $_POST['exam_score'] !== '' ? (float)$_POST['exam_score'] : null;
        $qualScore  = $_POST['qualification_score'] !== '' ? (float)$_POST['qualification_score'] : null;
        $reason     = trim($_POST['rejection_reason'] ?? '');
        $examSched  = $_POST['exam_scheduled'] ?: null;
        $pdo->prepare('UPDATE scholarship_applications SET status=?,exam_score=?,qualification_score=?,rejection_reason=?,exam_scheduled=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$status, $examScore, $qualScore, $reason ?: null, $examSched, $admin['id'], $appId]);
        flash('success', 'Application #'.$appId.' updated to: ' . ucwords(str_replace('_',' ',$status)));
        header('Location: scholarship_admin.php?tab=applications'); exit;
    }
    if ($action === 'verify_doc') {
        $docId  = (int)$_POST['doc_id'];
        $status = $_POST['doc_status'] ?? 'verified';
        $pdo->prepare('UPDATE scholarship_documents SET status=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$admin['id'],$docId]);
        flash('success', 'Document status updated.');
        header('Location: scholarship_admin.php?view=' . $appId); exit;
    }
    if ($action === 'add_batch') {
        $pdo->prepare('INSERT INTO scholarship_batches (name,school_year,semester,slots,gwa_required,income_limit,app_start,app_end,exam_date,exam_venue,exam_time,description,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([trim($_POST['name']),$_POST['school_year'],$_POST['semester'],(int)$_POST['slots'],(float)$_POST['gwa_required'],(float)$_POST['income_limit'],$_POST['app_start']?:null,$_POST['app_end']?:null,$_POST['exam_date']?:null,trim($_POST['exam_venue']??''),$_POST['exam_time']?:null,trim($_POST['description']??''),$admin['id']]);
        flash('success', 'Scholarship batch created.');
        header('Location: scholarship_admin.php?tab=batches'); exit;
    }
    if ($action === 'update_batch_status') {
        $batchId = (int)$_POST['batch_id'];
        $pdo->prepare('UPDATE scholarship_batches SET status=? WHERE id=?')->execute([$_POST['batch_status'],$batchId]);
        flash('success', 'Batch status updated.');
        header('Location: scholarship_admin.php?tab=batches'); exit;
    }
}

$viewId  = (int)($_GET['view'] ?? 0);
$viewApp = null;
if ($viewId) {
    $s = $pdo->prepare('SELECT a.*,u.first_name,u.last_name,u.email as user_email,b.name as batch_name FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id WHERE a.id=?');
    $s->execute([$viewId]);
    $viewApp = $s->fetch();
}

$filterStatus = $_GET['status'] ?? '';
$where  = $filterStatus ? 'WHERE a.status=?' : '';
$params = $filterStatus ? [$filterStatus] : [];
$apps = $pdo->prepare('SELECT a.*,u.first_name,u.last_name,b.name as batch_name,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id) as doc_count,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id AND d.status=\'verified\') as verified_count FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id '.$where.' ORDER BY a.created_at DESC');
$apps->execute($params);
$applications = $apps->fetchAll();

$batches = $pdo->query('SELECT b.*,(SELECT COUNT(*) FROM scholarship_applications a WHERE a.batch_id=b.id) as app_count FROM scholarship_batches b ORDER BY b.created_at DESC')->fetchAll();

$statusColors = ['pending'=>['#64748b','#f1f5f9'],'under_review'=>['#f57f17','#fff8e1'],'shortlisted'=>['#7b1fa2','#f3e5f5'],'approved'=>['#2e7d32','#e8f5e9'],'rejected'=>['#c62828','#ffebee']];
$docLabels = ['application_form'=>'Application Form','birth_certificate'=>'Birth Certificate','grades'=>'Copy of Grades','school_id'=>'School ID','income_proof'=>'Income Proof'];

$counts = [];
foreach (array_keys($statusColors) as $s) {
    $cs = $pdo->prepare('SELECT COUNT(*) FROM scholarship_applications WHERE status=?');
    $cs->execute([$s]);
    $counts[$s] = (int)$cs->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Scholarship Admin  LYDO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.filter-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.ftab{padding:6px 14px;border-radius:8px;border:1.5px solid #e2e8f0;font-family:inherit;font-size:.8rem;font-weight:600;cursor:pointer;color:#475569;background:#fff;text-decoration:none;transition:.2s}
.ftab:hover,.ftab.active{background:#1565c0;border-color:#1565c0;color:#fff}
.info-row{display:flex;gap:8px;padding:8px 18px;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-label{width:160px;flex-shrink:0;color:#475569;font-weight:500}
.info-val{color:#1e293b;font-weight:500}
.doc-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.doc-row:last-child{border-bottom:none}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;border:1.5px solid;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s}
.act-ok{background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7}.act-ok:hover{background:#2e7d32;color:#fff}
.act-no{background:#ffebee;color:#c62828;border-color:#ef9a9a}.act-no:hover{background:#c62828;color:#fff}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">
<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<?php if ($viewApp): ?>
<!-- SINGLE APPLICATION VIEW -->
<?php
$docs = $pdo->prepare('SELECT * FROM scholarship_documents WHERE application_id=?');
$docs->execute([$viewApp['id']]);
$documents = $docs->fetchAll();
[$sc,$sb] = $statusColors[$viewApp['status']] ?? ['#475569','#f1f5f9'];
?>
<div class="page-header">
  <div>
    <h2><?=htmlspecialchars($viewApp['first_name'].' '.$viewApp['last_name'])?></h2>
    <p>Application #<?=$viewApp['id']?>  <?=htmlspecialchars($viewApp['batch_name'])?>  Submitted <?=date('M j, Y',strtotime($viewApp['created_at']))?></p>
  </div>
  <a href="scholarship_admin.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px">
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-user"></i> Applicant Details</h3><span style="background:<?=$sb?>;color:<?=$sc?>;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700"><?=ucwords(str_replace('_',' ',$viewApp['status']))?></span></div>
      <div style="padding:4px 0">
        <?php
        $fields = ['Full Name'=>$viewApp['first_name'].' '.$viewApp['last_name'],'Email'=>$viewApp['user_email'],'School'=>$viewApp['school_name'],'Course'=>$viewApp['course'],'Year Level'=>$viewApp['year_level'],'GWA'=>$viewApp['gwa'],'Family Income'=>$viewApp['family_income']?'₱'.number_format($viewApp['family_income'],2):'N/A','Parent Occupation'=>$viewApp['parent_occupation']??'N/A','Siblings'=>$viewApp['siblings_count']??0,'Financial Need'=>$viewApp['financial_need']??'N/A'];
        foreach ($fields as $l => $v): ?>
        <div class="info-row"><span class="info-label"><?=$l?></span><span class="info-val"><?=htmlspecialchars($v??'')?></span></div>
        <?php endforeach; ?>
        <?php if ($viewApp['exam_score']!==null): ?>
        <div class="info-row"><span class="info-label">Exam Score</span><span class="info-val" style="color:#2e7d32;font-weight:700"><?=$viewApp['exam_score']?>/100</span></div>
        <?php endif; ?>
        <?php if ($viewApp['qualification_score']!==null): ?>
        <div class="info-row"><span class="info-label">Qualification Score</span><span class="info-val" style="color:#1565c0;font-weight:700"><?=$viewApp['qualification_score']?></span></div>
        <?php endif; ?>
        <?php if ($viewApp['rejection_reason']): ?>
        <div style="padding:10px 18px;background:#ffebee;font-size:.83rem;color:#c62828"><strong>Rejection Reason:</strong> <?=htmlspecialchars($viewApp['rejection_reason'])?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fas fa-paperclip"></i> Documents (<?=count(array_filter($documents,fn($d)=>$d['status']==='verified'))?>/<?=count($documents)?> verified)</h3></div>
      <div style="padding:8px 18px">
        <?php foreach ($documents as $doc):
          $dColors = ['verified'=>['#2e7d32','#e8f5e9','fa-check-circle'],'rejected'=>['#c62828','#ffebee','fa-times-circle'],'pending'=>['#f57f17','#fff8e1','fa-clock']];
          [$dc,$db,$di] = $dColors[$doc['status']] ?? ['#475569','#f1f5f9','fa-file'];
        ?>
        <div class="doc-row">
          <i class="fas <?=$di?>" style="color:<?=$dc?>;width:16px;flex-shrink:0"></i>
          <div style="flex:1">
            <div style="font-weight:600"><?=htmlspecialchars($docLabels[$doc['doc_type']]??$doc['doc_type'])?></div>
            <div style="font-size:.75rem;color:#94a3b8"><?=htmlspecialchars($doc['original_name'])?></div>
          </div>
          <a href="view_scholarship_file.php?doc_id=<?=$doc['id']?>&inline=1" target="_blank" class="act-btn" style="background:#e3f2fd;color:#1565c0;border-color:#90caf9;text-decoration:none" title="View Document">
            <i class="fas fa-eye"></i> View
          </a>
          <a href="view_scholarship_file.php?doc_id=<?=$doc['id']?>" class="act-btn" style="background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7;text-decoration:none" title="Download">
            <i class="fas fa-download"></i>
          </a>
          <span style="background:<?=$db?>;color:<?=$dc?>;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700"><?=ucfirst($doc['status'])?></span>
          <?php if (!in_array($viewApp['status'],['approved','rejected','beneficiary'])): ?>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="verify_doc"/><input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/><input type="hidden" name="doc_id" value="<?=$doc['id']?>"/><input type="hidden" name="doc_status" value="verified"/><button type="submit" class="act-btn act-ok" title="Verify"><i class="fas fa-check"></i></button></form>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="verify_doc"/><input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/><input type="hidden" name="doc_id" value="<?=$doc['id']?>"/><input type="hidden" name="doc_status" value="rejected"/><button type="submit" class="act-btn act-no" title="Reject"><i class="fas fa-times"></i></button></form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="fas fa-edit"></i> Update Application</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="update_status"/>
      <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
      <div class="fg"><label>Status</label>
        <select name="status" class="filter-sel" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;background:#f8fafc">
          <?php foreach (array_keys($statusColors) as $s): ?>
          <option value="<?=$s?>" <?=$viewApp['status']===$s?'selected':''?>><?=ucwords(str_replace('_',' ',$s))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="fg"><label>Exam Score (/100)</label><input type="number" name="exam_score" step="0.01" min="0" max="100" value="<?=$viewApp['exam_score']??''?>" style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;width:100%;background:#f8fafc"/></div>
        <div class="fg"><label>Qualification Score</label><input type="number" name="qualification_score" step="0.01" value="<?=$viewApp['qualification_score']??''?>" style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;width:100%;background:#f8fafc"/></div>
      </div>
      <div class="fg"><label>Exam Schedule</label><input type="date" name="exam_scheduled" value="<?=$viewApp['exam_scheduled']??''?>" style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;width:100%;background:#f8fafc"/></div>
      <div class="fg"><label>Rejection Reason (if rejected)</label><textarea name="rejection_reason" rows="2" style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;width:100%;background:#f8fafc;resize:vertical"><?=htmlspecialchars($viewApp['rejection_reason']??'')?></textarea></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
        <a href="scholarship_admin.php" style="padding:10px 20px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:600;text-decoration:none">Cancel</a>
        <button type="submit" style="padding:10px 22px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;box-shadow:0 3px 10px rgba(21,101,192,.3)"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<div class="page-header">
  <div><h2><i class="fas fa-graduation-cap" style="color:#1565c0;margin-right:8px"></i>Iskolar ng Bayan Scholarship</h2><p>Manage applications, batches, and beneficiaries.</p></div>
  <?php if ($tab==='batches'): ?>
  <button class="btn-primary" onclick="document.getElementById('addBatchModal').style.display='flex'"><i class="fas fa-plus"></i> New Batch</button>
  <?php endif; ?>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <?php foreach (['pending'=>'Pending','under_review'=>'Under Review','shortlisted'=>'Shortlisted','approved'=>'Approved'] as $s=>$l):
    [$sc,$sb] = $statusColors[$s];
  ?>
  <a href="?status=<?=$s?>" style="text-decoration:none">
    <div style="background:#fff;border-radius:10px;border:1.5px solid <?=$filterStatus===$s?$sc:'#e2e8f0'?>;padding:14px;text-align:center;transition:.2s;cursor:pointer">
      <div style="font-size:1.5rem;font-weight:800;color:<?=$sc?>"><?=$counts[$s]?></div>
      <div style="font-size:.75rem;color:#475569;font-weight:600;margin-top:3px"><?=$l?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<div class="tabs">
  <a href="?tab=applications" class="tab-btn <?=$tab==='applications'?'active':''?>"><i class="fas fa-file-alt"></i> Applications</a>
  <a href="?tab=batches"      class="tab-btn <?=$tab==='batches'?'active':''?>"><i class="fas fa-list"></i> Batches</a>
  <a href="?tab=beneficiaries" class="tab-btn <?=$tab==='beneficiaries'?'active':''?>"><i class="fas fa-users"></i> Beneficiaries</a>
  <a href="scholarship_export.php" class="tab-btn"><i class="fas fa-file-export"></i> Export</a>
</div>

<?php if ($tab === 'applications'): ?>
<div class="filter-tabs">
  <a href="scholarship_admin.php" class="ftab <?=!$filterStatus?'active':''?>">All</a>
  <?php foreach (array_keys($statusColors) as $s): [$sc,$sb]=$statusColors[$s]; ?>
  <a href="?status=<?=$s?>" class="ftab <?=$filterStatus===$s?'active':''?>" style="<?=$filterStatus===$s?'':'border-color:'.$sc.';color:'.$sc?>"><?=ucwords(str_replace('_',' ',$s))?> <span style="<?=$filterStatus===$s?'':'background:'.$sb.';color:'.$sc?>;padding:1px 6px;border-radius:50px;font-size:.68rem;font-weight:700"><?=$counts[$s]?></span></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Applicant</th><th>Batch</th><th>School</th><th>GWA</th><th>Income</th><th>Docs</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (empty($applications)): ?>
        <tr><td colspan="10" class="empty">No applications found.</td></tr>
      <?php else: foreach ($applications as $i => $a):
        [$sc,$sb] = $statusColors[$a['status']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($a['first_name'].' '.$a['last_name'])?></strong></td>
          <td style="font-size:.8rem"><?=htmlspecialchars($a['batch_name'])?></td>
          <td style="font-size:.8rem"><?=htmlspecialchars($a['school_name'])?></td>
          <td><?=$a['gwa']??''?></td>
          <td><?=$a['family_income']?'₱'.number_format($a['family_income'],0):''?></td>
          <td><span style="font-size:.82rem"><strong><?=$a['verified_count']?></strong>/<?=$a['doc_count']?></span></td>
          <td><span style="background:<?=$sb?>;color:<?=$sc?>;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700"><?=ucwords(str_replace('_',' ',$a['status']))?></span></td>
          <td style="font-size:.8rem"><?=date('M j, Y',strtotime($a['created_at']))?></td>
          <td><a href="?view=<?=$a['id']?>" class="btn-primary" style="padding:6px 12px;font-size:.8rem"><i class="fas fa-eye"></i> Review</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'batches'): ?>
<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Batch Name</th><th>School Year</th><th>Slots</th><th>Applications</th><th>Deadline</th><th>Exam Date</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($batches as $i => $b): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($b['name'])?></strong></td>
          <td><?=htmlspecialchars($b['school_year'])?></td>
          <td><?=$b['slots']?></td>
          <td><strong><?=$b['app_count']?></strong></td>
          <td><?=$b['app_end']?date('M j, Y',strtotime($b['app_end'])):''?></td>
          <td><?=$b['exam_date']?date('M j, Y',strtotime($b['exam_date'])):''?></td>
          <td><span class="badge <?=$b['status']==='open'?'green':($b['status']==='completed'?'blue':'gray')?>"><?=ucfirst($b['status'])?></span></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="update_batch_status"/>
              <input type="hidden" name="batch_id" value="<?=$b['id']?>"/>
              <select name="batch_status" onchange="this.form.submit()" style="padding:5px 8px;border:1.5px solid #e2e8f0;border-radius:7px;font-family:inherit;font-size:.78rem;outline:none;cursor:pointer">
                <?php foreach(['open','closed','evaluation','completed'] as $s): ?>
                <option value="<?=$s?>" <?=$b['status']===$s?'selected':''?>><?=ucfirst($s)?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'beneficiaries'): ?>
<?php
$bens = $pdo->query('SELECT a.*,u.first_name,u.last_name,b.name as batch_name FROM scholarship_applications a JOIN youth_users u ON u.id=a.user_id JOIN scholarship_batches b ON b.id=a.batch_id WHERE a.status IN (\'approved\',\'beneficiary\') ORDER BY a.updated_at DESC')->fetchAll();
?>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-users"></i> Scholarship Beneficiaries (<?=count($bens)?>)</h3></div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Name</th><th>School</th><th>Course</th><th>Year</th><th>GWA</th><th>Batch</th><th>Exam Score</th></tr></thead>
      <tbody>
      <?php if (empty($bens)): ?>
        <tr><td colspan="8" class="empty">No beneficiaries yet.</td></tr>
      <?php else: foreach ($bens as $i => $b): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($b['first_name'].' '.$b['last_name'])?></strong></td>
          <td><?=htmlspecialchars($b['school_name'])?></td>
          <td><?=htmlspecialchars($b['course'])?></td>
          <td><?=htmlspecialchars($b['year_level'])?></td>
          <td><?=$b['gwa']??''?></td>
          <td style="font-size:.8rem"><?=htmlspecialchars($b['batch_name'])?></td>
          <td><?=$b['exam_score']!==null?$b['exam_score'].'/100':''?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

</main>
</div>

<!-- ADD BATCH MODAL -->
<div id="addBatchModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-head"><h3><i class="fas fa-plus"></i> New Scholarship Batch</h3><button onclick="document.getElementById('addBatchModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="action" value="add_batch"/>
      <div class="form-row-2">
        <div class="fg"><label>Batch Name <span class="req">*</span></label><input type="text" name="name" required placeholder="e.g. Iskolar ng Bayan 2026"/></div>
        <div class="fg"><label>School Year <span class="req">*</span></label><input type="text" name="school_year" required placeholder="e.g. 2026-2027"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Semester</label><select name="semester"><option value="1st Semester">1st Semester</option><option value="2nd Semester">2nd Semester</option><option value="Full Year">Full Year</option></select></div>
        <div class="fg"><label>Slots</label><input type="number" name="slots" value="50" min="1"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Min GWA Required</label><input type="number" name="gwa_required" step="0.01" value="1.75" min="1" max="5"/></div>
        <div class="fg"><label>Max Household Income ()</label><input type="number" name="income_limit" step="0.01" value="15000"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Application Start</label><input type="date" name="app_start"/></div>
        <div class="fg"><label>Application End</label><input type="date" name="app_end"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Exam Date</label><input type="date" name="exam_date"/></div>
        <div class="fg"><label>Exam Time</label><input type="time" name="exam_time"/></div>
      </div>
      <div class="fg"><label>Exam Venue</label><input type="text" name="exam_venue" placeholder="e.g. Sta. Cruz Municipal Hall"/></div>
      <div class="fg"><label>Description</label><textarea name="description" rows="2" placeholder="Brief description..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="document.getElementById('addBatchModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Batch</button>
      </div>
    </form>
  </div>
</div>
</body></html>
