<?php
require_once 'config.php';
requireLogin();

$pdo   = db();
$admin = currentAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['app_id'] ?? 0);

    // Verify / reject a single document
    if ($action === 'verify_doc') {
        $docId  = (int)$_POST['doc_id'];
        $status = $_POST['doc_status'] ?? 'verified';
        $pdo->prepare('UPDATE accreditation_documents SET status=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$status, $admin['id'], $docId]);
        flash('success', 'Document status updated.');
        header("Location: accreditation.php?view=$appId"); exit;
    }

    // Advance workflow step
    if ($action === 'advance_step') {
        $step = trim($_POST['step'] ?? '');
        $pdo->prepare('UPDATE accreditation_workflow SET status="completed",done_at=CURRENT_TIMESTAMP WHERE application_id=? AND step=?')
            ->execute([$appId, $step]);
        flash('success', "Step \"$step\" marked as completed.");
        header("Location: accreditation.php?view=$appId"); exit;
    }

    // Approve application
    if ($action === 'approve') {
        $certNo    = 'LYDO-' . date('Y') . '-' . str_pad($appId, 4, '0', STR_PAD_LEFT);
        $validUntil= date('Y-m-d', strtotime('+1 year'));
        $pdo->prepare('UPDATE accreditation_applications SET status="approved",certificate_no=?,valid_until=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$certNo, $validUntil, $admin['id'], $appId]);
        // Complete all remaining workflow steps
        $pdo->prepare('UPDATE accreditation_workflow SET status="completed",done_at=CURRENT_TIMESTAMP WHERE application_id=? AND status="pending"')
            ->execute([$appId]);
        flash('success', "Application #$appId approved. Certificate No: $certNo");
        header("Location: accreditation.php?view=$appId"); exit;
    }

    // Reject application
    if ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        $pdo->prepare('UPDATE accreditation_applications SET status="rejected",rejection_reason=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$reason, $admin['id'], $appId]);
        flash('success', "Application #$appId rejected.");
        header('Location: accreditation.php'); exit;
    }

    // Set under review
    if ($action === 'under_review') {
        $pdo->prepare('UPDATE accreditation_applications SET status="under_review" WHERE id=?')->execute([$appId]);
        // Advance to Document Verification step
        $pdo->prepare('UPDATE accreditation_workflow SET status="completed",done_at=CURRENT_TIMESTAMP WHERE application_id=? AND step="Document Verification"')
            ->execute([$appId]);
        flash('success', 'Application moved to Under Review.');
        header("Location: accreditation.php?view=$appId"); exit;
    }
}

// ── View single application ───────────────────────────────
$viewId = (int)($_GET['view'] ?? 0);
$viewApp = null;
if ($viewId) {
    $s = $pdo->prepare('SELECT a.*, u.first_name, u.last_name, u.email as user_email FROM accreditation_applications a JOIN youth_users u ON u.id=a.submitted_by WHERE a.id=?');
    $s->execute([$viewId]);
    $viewApp = $s->fetch();
}

// ── List all applications ─────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$where  = $filterStatus ? 'WHERE a.status=?' : '';
$params = $filterStatus ? [$filterStatus] : [];

$apps = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name,
     (SELECT COUNT(*) FROM accreditation_documents WHERE organization_id=a.organization_id) as doc_count
     FROM accreditation_applications a
     JOIN youth_users u ON u.id=a.submitted_by
     $where ORDER BY a.created_at DESC"
);
$apps->execute($params);
$applications = $apps->fetchAll();

// Counts per status
$counts = [];
foreach (['submitted','under_review','approved','rejected'] as $s) {
    $cs = $pdo->prepare("SELECT COUNT(*) FROM accreditation_applications WHERE status=?");
    $cs->execute([$s]);
    $counts[$s] = (int)$cs->fetchColumn();
}

$docLabels = [
    'letter_of_intent' => 'Letter of Intent',
    'nyc_form'         => 'NYC Accreditation Form',
    'officers_list'    => 'Officers and Members List',
    'constitution'     => 'Constitution and By-Laws',
    'lydo_form'        => 'LYDO Accreditation Form',
];

$statusColors = [
    'submitted'    => ['#1565c0','#e3f2fd'],
    'under_review' => ['#f57f17','#fff8e1'],
    'approved'     => ['#2e7d32','#e8f5e9'],
    'rejected'     => ['#c62828','#ffebee'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Accreditation – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.filter-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.ftab{padding:7px 16px;border-radius:8px;border:1.5px solid #e2e8f0;font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer;color:#475569;background:#fff;text-decoration:none;transition:.2s;display:flex;align-items:center;gap:6px}
.ftab:hover,.ftab.active{background:#1565c0;border-color:#1565c0;color:#fff}
.ftab .cnt{background:rgba(255,255,255,.3);border-radius:50px;padding:1px 7px;font-size:.7rem}
.ftab.active .cnt{background:rgba(255,255,255,.25)}
.workflow{display:flex;flex-direction:column;gap:0}
.wf-step{display:flex;align-items:flex-start;gap:12px;padding:10px 0;position:relative}
.wf-step:not(:last-child)::after{content:'';position:absolute;left:14px;top:34px;bottom:0;width:2px;background:#e2e8f0}
.wf-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;z-index:1}
.wf-dot.done{background:#2e7d32;color:#fff}
.wf-dot.active{background:#1565c0;color:#fff}
.wf-dot.pending{background:#e2e8f0;color:#94a3b8}
.wf-label{font-size:.85rem;font-weight:600;color:#1e293b;margin-top:4px}
.wf-sub{font-size:.75rem;color:#94a3b8;margin-top:1px}
.doc-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9}
.doc-row:last-child{border-bottom:none}
.status-badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.info-row{display:flex;gap:8px;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-label{width:140px;flex-shrink:0;color:#475569;font-weight:500}
.info-val{color:#1e293b;font-weight:500}
.action-bar{display:flex;gap:10px;flex-wrap:wrap;padding:16px 18px;border-top:1px solid #e2e8f0;background:#f8fafc}
.btn-action{padding:9px 18px;border:none;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s}
.btn-review{background:#fff8e1;color:#f57f17;border:1.5px solid #ffe082}
.btn-review:hover{background:#f57f17;color:#fff}
.btn-approve{background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7}
.btn-approve:hover{background:#2e7d32;color:#fff}
.btn-reject-action{background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a}
.btn-reject-action:hover{background:#c62828;color:#fff}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<?php if ($viewApp): ?>
<!-- ── SINGLE APPLICATION VIEW ── -->
<div class="page-header">
  <div>
    <h2><?=htmlspecialchars($viewApp['organization_name'])?></h2>
    <p>Application #<?=$viewApp['id']?> · Submitted by <?=htmlspecialchars($viewApp['first_name'].' '.$viewApp['last_name'])?> · <?=date('M j, Y',strtotime($viewApp['created_at']))?></p>
  </div>
  <a href="accreditation.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
</div>

<?php
$wfStmt = $pdo->prepare('SELECT * FROM accreditation_workflow WHERE application_id=? ORDER BY id');
$wfStmt->execute([$viewApp['id']]);
$workflow = $wfStmt->fetchAll();

$docStmt = $pdo->prepare('SELECT * FROM accreditation_documents WHERE organization_id=?');
$docStmt->execute([$viewApp['organization_id']]);
$docs = $docStmt->fetchAll();

[$sc,$bg] = $statusColors[$viewApp['status']] ?? ['#475569','#f1f5f9'];
?>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px;margin-bottom:16px">

  <!-- LEFT: Info + Workflow -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-info-circle"></i> Organization Details</h3></div>
      <div style="padding:4px 0 0">
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Status</span><span class="info-val"><span class="status-badge" style="background:<?=$bg?>;color:<?=$sc?>"><?=ucwords(str_replace('_',' ',$viewApp['status']))?></span></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Category</span><span class="info-val"><?=htmlspecialchars($viewApp['category']?:'—')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Barangay</span><span class="info-val"><?=htmlspecialchars($viewApp['barangay']?:'—')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Contact Person</span><span class="info-val"><?=htmlspecialchars($viewApp['contact_person']?:'—')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Email</span><span class="info-val"><?=htmlspecialchars($viewApp['contact_email']?:'—')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Phone</span><span class="info-val"><?=htmlspecialchars($viewApp['contact_phone']?:'—')?></span></div>
        <?php if ($viewApp['certificate_no']): ?>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Certificate No.</span><span class="info-val" style="color:#2e7d32;font-weight:700"><?=htmlspecialchars($viewApp['certificate_no'])?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Valid Until</span><span class="info-val"><?=date('F j, Y',strtotime($viewApp['valid_until']))?></span></div>
        <?php endif; ?>
        <?php if ($viewApp['rejection_reason']): ?>
        <div style="padding:12px 18px;background:#ffebee;font-size:.83rem;color:#c62828"><strong>Rejection Reason:</strong> <?=htmlspecialchars($viewApp['rejection_reason'])?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fas fa-tasks"></i> Application Workflow</h3></div>
      <div style="padding:14px 18px">
        <div class="workflow">
          <?php
          $doneCount = count(array_filter($workflow, fn($w) => $w['status']==='completed'));
          foreach ($workflow as $idx => $wf):
            $isDone   = $wf['status'] === 'completed';
            $isActive = $idx === $doneCount;
            $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
            $icon     = $isDone ? 'fa-check' : ($isActive ? 'fa-circle-notch fa-spin' : 'fa-circle');
          ?>
          <div class="wf-step">
            <div class="wf-dot <?=$dotClass?>"><i class="fas <?=$icon?>"></i></div>
            <div style="flex:1;display:flex;align-items:center;justify-content:space-between">
              <div>
                <div class="wf-label"><?=htmlspecialchars($wf['step'])?></div>
                <?php if ($isDone && $wf['done_at']): ?>
                  <div class="wf-sub">Done <?=date('M j, Y',strtotime($wf['done_at']))?></div>
                <?php elseif ($isActive): ?>
                  <div class="wf-sub" style="color:#1565c0">In progress</div>
                <?php endif; ?>
              </div>
              <?php if ($isActive && !in_array($viewApp['status'],['approved','rejected'])): ?>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="advance_step"/>
                <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
                <input type="hidden" name="step" value="<?=htmlspecialchars($wf['step'])?>"/>
                <button type="submit" style="padding:4px 10px;background:#e3f2fd;color:#1565c0;border:1.5px solid #90caf9;border-radius:6px;font-family:inherit;font-size:.75rem;font-weight:700;cursor:pointer">
                  <i class="fas fa-check"></i> Mark Done
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Documents -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between">
      <h3><i class="fas fa-paperclip"></i> Submitted Documents</h3>
      <span style="font-size:.8rem;color:#94a3b8"><?=count(array_filter($docs,fn($d)=>$d['status']==='verified'))?>/<?=count($docs)?> verified</span>
    </div>
    <div style="padding:8px 18px">
      <?php if (empty($docs)): ?>
        <p style="color:#94a3b8;font-size:.85rem;padding:16px 0;text-align:center">No documents uploaded.</p>
      <?php else: foreach ($docs as $doc):
        $dColors = ['verified'=>['#2e7d32','#e8f5e9','fa-check-circle'],'rejected'=>['#c62828','#ffebee','fa-times-circle'],'pending'=>['#f57f17','#fff8e1','fa-clock']];
        [$dc,$db,$di] = $dColors[$doc['status']] ?? ['#475569','#f1f5f9','fa-file'];
      ?>
      <div class="doc-row">
        <i class="fas <?=$di?>" style="color:<?=$dc?>;font-size:1rem;width:18px;flex-shrink:0"></i>
        <div style="flex:1">
          <div style="font-size:.85rem;font-weight:600"><?=htmlspecialchars($docLabels[$doc['doc_type']]??$doc['doc_type'])?></div>
          <div style="font-size:.75rem;color:#94a3b8"><?=htmlspecialchars($doc['original_name'])?> · <?=round($doc['file_size']/1024)?>KB</div>
        </div>
        <span class="status-badge" style="background:<?=$db?>;color:<?=$dc?>"><?=ucfirst($doc['status'])?></span>
        <?php if (!in_array($viewApp['status'],['approved','rejected'])): ?>
        <div style="display:flex;gap:5px">
          <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="verify_doc"/>
            <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
            <input type="hidden" name="doc_id" value="<?=$doc['id']?>"/>
            <input type="hidden" name="doc_status" value="verified"/>
            <button type="submit" style="padding:4px 9px;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit" title="Verify"><i class="fas fa-check"></i></button>
          </form>
          <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="verify_doc"/>
            <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
            <input type="hidden" name="doc_id" value="<?=$doc['id']?>"/>
            <input type="hidden" name="doc_status" value="rejected"/>
            <button type="submit" style="padding:4px 9px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit" title="Reject"><i class="fas fa-times"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Action Bar -->
    <?php if (!in_array($viewApp['status'],['approved','rejected'])): ?>
    <div class="action-bar">
      <?php if ($viewApp['status'] === 'submitted'): ?>
      <form method="POST">
        <input type="hidden" name="action" value="under_review"/>
        <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
        <button type="submit" class="btn-action btn-review"><i class="fas fa-search"></i> Move to Under Review</button>
      </form>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="approve"/>
        <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
        <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approve this application?')"><i class="fas fa-certificate"></i> Approve & Issue Certificate</button>
      </form>
      <button class="btn-action btn-reject-action" onclick="document.getElementById('rejectModal').style.display='flex'"><i class="fas fa-times"></i> Reject</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-head" style="background:linear-gradient(135deg,#b71c1c,#c62828)">
      <h3><i class="fas fa-times-circle"></i> Reject Application</h3>
      <button onclick="document.getElementById('rejectModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="action" value="reject"/>
      <input type="hidden" name="app_id" value="<?=$viewApp['id']?>"/>
      <div class="fg"><label>Rejection Reason <span class="req">*</span></label>
        <textarea name="rejection_reason" rows="3" required placeholder="Explain why this application is being rejected..."></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
        <button type="button" class="btn-cancel" onclick="document.getElementById('rejectModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-danger"><i class="fas fa-times"></i> Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── APPLICATION LIST ── -->
<div class="page-header">
  <div><h2>Accreditation Applications</h2><p>Review and process organization accreditation requests.</p></div>
</div>

<!-- Status filter tabs -->
<div class="filter-tabs">
  <a href="accreditation.php" class="ftab <?=!$filterStatus?'active':''?>">All <span class="cnt"><?=array_sum($counts)?></span></a>
  <?php foreach (['submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected'] as $s=>$l):
    [$sc,$bg] = $statusColors[$s];
  ?>
  <a href="?status=<?=$s?>" class="ftab <?=$filterStatus===$s?'active':''?>" style="<?=$filterStatus===$s?'':'border-color:'.$sc.';color:'.$sc?>">
    <?=$l?> <span class="cnt" style="<?=$filterStatus===$s?'':'background:'.$bg.';color:'.$sc?>"><?=$counts[$s]?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr><th>#</th><th>Organization</th><th>Category</th><th>Submitted By</th><th>Documents</th><th>Status</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (empty($applications)): ?>
        <tr><td colspan="8" class="empty">No applications found.</td></tr>
      <?php else: foreach ($applications as $i => $app):
        [$sc,$bg] = $statusColors[$app['status']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($app['organization_name'])?></strong></td>
          <td><span class="badge blue"><?=htmlspecialchars($app['category']?:'—')?></span></td>
          <td><?=htmlspecialchars($app['first_name'].' '.$app['last_name'])?></td>
          <td>
            <span style="font-size:.82rem">
              <span style="color:#2e7d32;font-weight:700"><?=$app['doc_count']?></span>
              <span style="color:#94a3b8"> documents</span>
            </span>
          </td>
          <td><span class="status-badge" style="background:<?=$bg?>;color:<?=$sc?>"><?=ucwords(str_replace('_',' ',$app['status']))?></span></td>
          <td><?=date('M j, Y',strtotime($app['created_at']))?></td>
          <td><a href="?view=<?=$app['id']?>" class="btn-primary" style="padding:6px 12px;font-size:.8rem"><i class="fas fa-eye"></i> Review</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</main>
</div>
</body>
</html>
