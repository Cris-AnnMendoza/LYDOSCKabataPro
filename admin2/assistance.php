<?php
require_once 'config.php';
requireLogin();
$pdo   = db();
$admin = currentAdmin();

//  Helpers 
function notifyUser(PDO $pdo, int $userId, string $type, string $msg, string $link = ''): void {
    // Notifications table structure: user_id, title, message, type, reference_type, reference_id, is_read
    // We'll use message for the notification text and type for the notification type
    try {
        $pdo->prepare('INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)')
            ->execute([$userId, $type, 'Assistance Update', $msg]);
    } catch (PDOException $e) {
        // If notifications fail, just log it and continue
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

$statusLabels = ['submitted'=>'Submitted','under_evaluation'=>'Under Evaluation','for_coordination'=>'For Coordination','approved'=>'Approved','completed'=>'Completed','declined'=>'Declined'];
$statusColors = ['submitted'=>['#1565c0','#e3f2fd'],'under_evaluation'=>['#f57f17','#fff8e1'],'for_coordination'=>['#7b1fa2','#f3e5f5'],'approved'=>['#2e7d32','#e8f5e9'],'completed'=>['#00796b','#e0f2f1'],'declined'=>['#c62828','#ffebee']];

//  Handle POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reqId  = (int)($_POST['req_id'] ?? 0);

    if ($action === 'update_status' && $reqId) {
        $newStatus = $_POST['new_status'] ?? '';
        $note      = trim($_POST['note'] ?? '');
        $schedDate = $_POST['scheduled_date'] ?? null;
        $decReason = trim($_POST['decline_reason'] ?? '');

        // Validate new status
        if (empty($newStatus) || !isset($statusLabels[$newStatus])) {
            flash('error', 'Please select a valid status.');
            header("Location: assistance.php?view=$reqId");
            exit;
        }

        // Update request status - handle both with and without updated_at column
        try {
            $updateStmt = $pdo->prepare('UPDATE assistance_requests SET status=?,scheduled_date=?,decline_reason=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $updateStmt->execute([$newStatus, $schedDate ?: null, $decReason ?: null, $reqId]);
        } catch (PDOException $e) {
            // If updated_at doesn't exist, try without it
            $updateStmt = $pdo->prepare('UPDATE assistance_requests SET status=?,scheduled_date=?,decline_reason=? WHERE id=?');
            $updateStmt->execute([$newStatus, $schedDate ?: null, $decReason ?: null, $reqId]);
        }

        // Check if update was successful
        if ($updateStmt->rowCount() === 0) {
            error_log("ASSISTANCE UPDATE WARNING: No rows affected for request #$reqId");
        }

        // Add to timeline
        $pdo->prepare('INSERT INTO assistance_timeline (request_id,status,note,done_by) VALUES (?,?,?,?)')
            ->execute([$reqId, $statusLabels[$newStatus] ?? $newStatus, $note ?: 'Status updated', $admin['id']]);

        // If status is approved, create event in events management
        if ($newStatus === 'approved') {
            // Get request details
            $req = $pdo->prepare('SELECT r.*, o.name as org_name FROM assistance_requests r JOIN organizations o ON o.id=r.organization_id WHERE r.id=?');
            $req->execute([$reqId]);
            $request = $req->fetch();
            
            if ($request) {
                // Create event
                $eventTitle = $request['title'];
                $eventDesc = $request['description'];
                $eventDate = $schedDate ?: $request['scheduled_date'];
                $orgId = $request['organization_id'];
                
                // Insert into events table (no 'status' column - events table doesn't have it)
                try {
                    // Generate QR token for check-in
                    $qrToken = bin2hex(random_bytes(16));
                    $checkinCode = strtoupper(substr(md5($qrToken), 0, 6));
                    
                    $pdo->prepare('INSERT INTO events 
                        (organization_id, title, description, event_date, event_type, created_by, merit_points, qr_token, checkin_code, checkin_open) 
                        VALUES (?,?,?,?,?,?,2,?,?,TRUE)')
                        ->execute([$orgId, $eventTitle, $eventDesc, $eventDate, $request['request_type'], $admin['id'], $qrToken, $checkinCode]);
                    
                    $eventId = (int)$pdo->lastInsertId();
                    
                    // Add note to timeline about event creation
                    $pdo->prepare('INSERT INTO assistance_timeline (request_id,status,note,done_by) VALUES (?,?,?,?)')
                        ->execute([$reqId, 'Event Created', 'Event #' . $eventId . ' created in Events Management with check-in code: ' . $checkinCode, $admin['id']]);
                } catch (PDOException $e) {
                    // Log error but continue
                    error_log("Failed to create event from approved request: " . $e->getMessage());
                }
            }
        }

        // Notify submitter (only if it's a youth user, not a president)
        $sub = $pdo->prepare('SELECT submitted_by FROM assistance_requests WHERE id=?');
        $sub->execute([$reqId]);
        $row = $sub->fetch();
        if ($row && $row['submitted_by']) {
            notifyUser($pdo, $row['submitted_by'], 'assistance', "Your request #$reqId status updated to: " . ($statusLabels[$newStatus] ?? $newStatus), "assistance_view.php?id=$reqId");
        }

        flash('success', "Request #$reqId status updated to: " . ($statusLabels[$newStatus] ?? $newStatus));
        
        // Debug: Log the update
        error_log("ASSISTANCE STATUS UPDATE: Request #$reqId changed to $newStatus by admin #{$admin['id']}");
        
        header("Location: assistance.php?view=$reqId"); 
        exit;
    }

    if ($action === 'comment' && $reqId) {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment) {
            $pdo->prepare('INSERT INTO assistance_comments (request_id,author_id,author_type,comment) VALUES (?,?,?,?)')->execute([$reqId,$admin['id'],'admin',$comment]);
            $sub = $pdo->prepare('SELECT submitted_by FROM assistance_requests WHERE id=?');
            $sub->execute([$reqId]);
            $row = $sub->fetch();
            if ($row && $row['submitted_by']) notifyUser($pdo, $row['submitted_by'], 'comment', "Admin added a comment on your request #$reqId.", "assistance_view.php?id=$reqId");
            flash('success', 'Comment added.');
        }
        header("Location: assistance.php?view=$reqId"); exit;
    }
}

//  View single request 
$viewId  = (int)($_GET['view'] ?? 0);
$viewReq = null;
if ($viewId) {
    $s = $pdo->prepare('SELECT r.*,o.name as org_name,u.first_name,u.last_name,u.email as user_email FROM assistance_requests r JOIN organizations o ON o.id=r.organization_id LEFT JOIN youth_users u ON u.id=r.submitted_by WHERE r.id=?');
    $s->execute([$viewId]);
    $viewReq = $s->fetch();
}

//  Stats 
$counts = [];
foreach (array_keys($statusLabels) as $s) {
    $cs = $pdo->prepare('SELECT COUNT(*) FROM assistance_requests WHERE status=?');
    $cs->execute([$s]);
    $counts[$s] = (int)$cs->fetchColumn();
}
$totalReqs = array_sum($counts);

//  List 
$filterStatus = $_GET['status'] ?? '';
$where  = $filterStatus ? 'WHERE r.status=?' : '';
$params = $filterStatus ? [$filterStatus] : [];
$reqs = $pdo->prepare("SELECT r.*,o.name as org_name,u.first_name,u.last_name,(SELECT COUNT(*) FROM assistance_documents d WHERE d.request_id=r.id) as doc_count FROM assistance_requests r JOIN organizations o ON o.id=r.organization_id LEFT JOIN youth_users u ON u.id=r.submitted_by $where ORDER BY r.created_at DESC");
$reqs->execute($params);
$requests = $reqs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Assistance Requests  LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.filter-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.ftab{padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;color:#475569;background:#fff;text-decoration:none;transition:.2s;display:flex;align-items:center;gap:6px}
.ftab:hover,.ftab.active{background:#1565c0;border-color:#1565c0;color:#fff}
.ftab .cnt{background:rgba(255,255,255,.25);border-radius:50px;padding:1px 7px;font-size:.7rem}
.timeline{display:flex;flex-direction:column;gap:0}
.tl-item{display:flex;gap:12px;padding:10px 0;position:relative}
.tl-item:not(:last-child)::after{content:'';position:absolute;left:13px;top:32px;bottom:0;width:2px;background:#e2e8f0}
.tl-dot{width:28px;height:28px;border-radius:50%;background:#1565c0;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;z-index:1}
.tl-label{font-size:.85rem;font-weight:600;color:#1e293b;margin-top:4px}
.tl-note{font-size:.78rem;color:#94a3b8;margin-top:2px}
.comment-item{padding:12px 0;border-bottom:1px solid #f1f5f9}
.comment-item:last-child{border-bottom:none}
.comment-author{font-size:.78rem;font-weight:700;color:#1565c0;margin-bottom:4px}
.comment-author.youth{color:#2e7d32}
.comment-text{font-size:.88rem;color:#1e293b;line-height:1.6}
.comment-time{font-size:.72rem;color:#94a3b8;margin-top:3px}
.doc-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.doc-row:last-child{border-bottom:none}
.status-sel{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;outline:none;background:#f8fafc;color:#1e293b;cursor:pointer;width:100%}
.status-sel:focus{border-color:#1e88e5;background:#fff}
.detail-2col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.info-row{display:flex;gap:8px;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-label{width:140px;flex-shrink:0;color:#475569;font-weight:500}
.info-val{color:#1e293b;font-weight:500}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">
<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<?php if ($viewReq): ?>
<!--  SINGLE REQUEST VIEW  -->
<?php
$docs = $pdo->prepare('SELECT * FROM assistance_documents WHERE request_id=?');
$docs->execute([$viewReq['id']]);
$documents = $docs->fetchAll();

$timeline = $pdo->prepare('SELECT t.*,a.full_name as admin_name FROM assistance_timeline t LEFT JOIN admin_users a ON a.id=t.done_by WHERE t.request_id=? ORDER BY t.created_at ASC');
$timeline->execute([$viewReq['id']]);
$timelineItems = $timeline->fetchAll();

$comments = $pdo->prepare('SELECT c.*,CASE WHEN c.author_type="admin" THEN (SELECT full_name FROM admin_users WHERE id=c.author_id) ELSE (SELECT CONCAT(first_name," ",last_name) FROM youth_users WHERE id=c.author_id) END as author_name FROM assistance_comments c WHERE c.request_id=? ORDER BY c.created_at ASC');
$comments->execute([$viewReq['id']]);
$commentList = $comments->fetchAll();

[$sc,$bg] = $statusColors[$viewReq['status']] ?? ['#475569','#f1f5f9'];
$docTypeLabels = ['request_letter'=>'Request Letter','project_proposal'=>'Project Proposal','participant_list'=>'Participant List','sk_endorsement'=>'SK Endorsement','other'=>'Other'];
?>
<div class="page-header">
  <div>
    <h2><?=htmlspecialchars($viewReq['title'])?></h2>
    <p><?=htmlspecialchars($viewReq['org_name'])?>  Submitted by <?=htmlspecialchars($viewReq['first_name'] ? $viewReq['first_name'].' '.$viewReq['last_name'] : 'Organization President')?>  <?=date('M j, Y',strtotime($viewReq['created_at']))?></p>
  </div>
  <a href="assistance.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:16px;margin-bottom:16px">
  <!-- LEFT -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <!-- Details -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-info-circle"></i> Request Details</h3><span style="background:<?=$bg?>;color:<?=$sc?>;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700"><?=$statusLabels[$viewReq['status']] ?? 'Unknown'?></span></div>
      <div style="padding:4px 0">
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Organization</span><span class="info-val"><?=htmlspecialchars($viewReq['org_name'])?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Activity Type</span><span class="info-val"><?=htmlspecialchars($viewReq['request_type'] ?? $viewReq['activity_type'] ?? 'N/A')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Target Date</span><span class="info-val"><?=isset($viewReq['scheduled_date']) && $viewReq['scheduled_date'] ? date('F j, Y',strtotime($viewReq['scheduled_date'])) : (isset($viewReq['target_date']) && $viewReq['target_date'] ? date('F j, Y',strtotime($viewReq['target_date'])) : 'Not set')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Venue</span><span class="info-val"><?=htmlspecialchars($viewReq['target_venue'] ?? 'N/A')?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Participants</span><span class="info-val"><?=isset($viewReq['participants']) ? number_format($viewReq['participants']) : 'N/A'?></span></div>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Budget Requested</span><span class="info-val"><?=isset($viewReq['budget_requested']) ? number_format($viewReq['budget_requested'],2) : '0.00'?></span></div>
        <?php if (!empty($viewReq['scheduled_date'])): ?>
        <div class="info-row" style="padding:9px 18px"><span class="info-label">Scheduled Date</span><span class="info-val" style="color:#2e7d32;font-weight:700"><?=date('F j, Y',strtotime($viewReq['scheduled_date']))?></span></div>
        <?php endif; ?>
      </div>
      <div style="padding:14px 18px;border-top:1px solid #e2e8f0;background:#f8fafc">
        <p style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:6px">Description</p>
        <p style="font-size:.88rem;color:#1e293b;line-height:1.7"><?=nl2br(htmlspecialchars($viewReq['description'] ?? 'No description provided'))?></p>
      </div>
      <?php if (!empty($viewReq['decline_reason'])): ?>
      <div style="padding:12px 18px;background:#ffebee;font-size:.83rem;color:#c62828"><strong>Decline Reason:</strong> <?=htmlspecialchars($viewReq['decline_reason'])?></div>
      <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-paperclip"></i> Documents (<?=count($documents)?>)</h3></div>
      <div style="padding:8px 18px">
        <?php if (empty($documents)): ?>
          <p style="color:#94a3b8;font-size:.85rem;padding:12px 0;text-align:center">No documents uploaded.</p>
        <?php else: foreach ($documents as $doc):
          $ext = strtolower(pathinfo($doc['file_path'] ?? $doc['filename'] ?? '', PATHINFO_EXTENSION));
          $icon = match($ext) {
            'pdf'        => ['fa-file-pdf',   '#c62828', '#ffebee'],
            'doc','docx' => ['fa-file-word',  '#1565c0', '#e3f2fd'],
            'jpg','jpeg',
            'png'        => ['fa-file-image', '#2e7d32', '#e8f5e9'],
            default      => ['fa-file-alt',   '#475569', '#f1f5f9'],
          };
          $viewUrl     = 'view_file.php?doc_id=' . $doc['id'] . '&inline=1';
          $downloadUrl = 'view_file.php?doc_id=' . $doc['id'];
        ?>
        <div class="doc-row" style="padding:10px 0;border-bottom:1px solid #f1f5f9">
          <div style="width:36px;height:36px;border-radius:9px;background:<?=$icon[2]?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas <?=$icon[0]?>" style="color:<?=$icon[1]?>;font-size:.95rem"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.85rem;font-weight:600;color:#1e293b"><?=htmlspecialchars($docTypeLabels[$doc['doc_type']]??$doc['doc_type'])?></div>
            <div style="font-size:.75rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($doc['original_name'])?> · <?=round($doc['file_size']/1024)?>KB</div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="<?=$viewUrl?>" target="_blank"
               style="padding:5px 11px;background:#e3f2fd;color:#1565c0;border-radius:6px;font-size:.75rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;transition:.2s"
               onmouseover="this.style.background='#1565c0';this.style.color='#fff'"
               onmouseout="this.style.background='#e3f2fd';this.style.color='#1565c0'">
              <i class="fas fa-eye"></i> View
            </a>
            <a href="<?=$downloadUrl?>" download
               style="padding:5px 11px;background:#e8f5e9;color:#2e7d32;border-radius:6px;font-size:.75rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;transition:.2s"
               onmouseover="this.style.background='#2e7d32';this.style.color='#fff'"
               onmouseout="this.style.background='#e8f5e9';this.style.color='#2e7d32'">
              <i class="fas fa-download"></i> Download
            </a>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-history"></i> Timeline</h3></div>
      <div style="padding:14px 18px">
        <?php if (empty($timelineItems)): ?>
          <p style="color:#94a3b8;font-size:.85rem;text-align:center">No timeline entries yet.</p>
        <?php else: ?>
        <div class="timeline">
          <?php foreach ($timelineItems as $tl): ?>
          <div class="tl-item">
            <div class="tl-dot"><i class="fas fa-check"></i></div>
            <div>
              <div class="tl-label"><?=htmlspecialchars($tl['status'])?></div>
              <?php if ($tl['note']): ?><div class="tl-note"><?=htmlspecialchars($tl['note'])?></div><?php endif; ?>
              <div class="tl-note"><?=date('M j, Y g:i A',strtotime($tl['created_at']))?><?=$tl['admin_name']?'  by '.htmlspecialchars($tl['admin_name']):''?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <!-- Update Status -->
    <?php if (!in_array($viewReq['status'],['completed','declined'])): ?>
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-edit"></i> Update Status</h3></div>
      <form method="POST" class="modal-body" style="padding:18px">
        <input type="hidden" name="action" value="update_status"/>
        <input type="hidden" name="req_id" value="<?=$viewReq['id']?>"/>
        <div class="fg">
          <label>New Status <span style="color:#c62828">*</span></label>
          <select name="new_status" class="status-sel" id="statusSel" onchange="toggleFields(this.value)" required>
            <option value="">-- Select New Status --</option>
            <?php foreach ($statusLabels as $val => $lbl): if ($val===$viewReq['status']) continue; ?>
              <option value="<?=$val?>"><?=$lbl?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg" id="schedField" style="display:none">
          <label>Scheduled Date</label>
          <input type="date" name="scheduled_date" value="<?=htmlspecialchars($viewReq['scheduled_date']??'')?>"/>
        </div>
        <div class="fg" id="declineField" style="display:none">
          <label>Decline Reason</label>
          <textarea name="decline_reason" rows="2" placeholder="Explain why this request is declined..."></textarea>
        </div>
        <div class="fg">
          <label>Note / Recommendation</label>
          <textarea name="note" rows="2" placeholder="Add a note or recommendation..."></textarea>
        </div>
        <button type="submit" style="padding:10px 20px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.2s">
          <i class="fas fa-save"></i> Update Status
        </button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Comments -->
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-comments"></i> Comments & Recommendations</h3></div>
      <div style="padding:8px 18px;max-height:320px;overflow-y:auto">
        <?php if (empty($commentList)): ?>
          <p style="color:#94a3b8;font-size:.85rem;padding:12px 0;text-align:center">No comments yet.</p>
        <?php else: foreach ($commentList as $c): ?>
        <div class="comment-item">
          <div class="comment-author <?=$c['author_type']?>"><i class="fas fa-<?=$c['author_type']==='admin'?'user-shield':'user'?>"></i> <?=htmlspecialchars($c['author_name'])?> <span style="font-weight:400;color:#94a3b8">(<?=ucfirst($c['author_type'])?>)</span></div>
          <div class="comment-text"><?=nl2br(htmlspecialchars($c['comment']))?></div>
          <div class="comment-time"><?=date('M j, Y g:i A',strtotime($c['created_at']))?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <div style="padding:14px 18px;border-top:1px solid #e2e8f0">
        <form method="POST">
          <input type="hidden" name="action" value="comment"/>
          <input type="hidden" name="req_id" value="<?=$viewReq['id']?>"/>
          <textarea name="comment" rows="2" placeholder="Add a comment or recommendation..." style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;outline:none;resize:vertical;margin-bottom:8px" required></textarea>
          <button type="submit" style="padding:8px 18px;background:#1565c0;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
            <i class="fas fa-paper-plane"></i> Post Comment
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleFields(val) {
  document.getElementById('schedField').style.display  = val==='approved'?'block':'none';
  document.getElementById('declineField').style.display = val==='declined'?'block':'none';
}
toggleFields(document.getElementById('statusSel')?.value||'');
</script>

<?php else: ?>
<!--  LIST VIEW  -->
<div class="page-header">
  <div><h2>Assistance Requests</h2><p>Youth Organization Assistance Program  manage and evaluate requests.</p></div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px">
  <?php foreach ($statusLabels as $s => $l):
    [$sc,$bg] = $statusColors[$s];
  ?>
  <a href="?status=<?=$s?>" style="text-decoration:none">
    <div style="background:#fff;border:1.5px solid <?=$filterStatus===$s?$sc:'#e2e8f0'?>;border-radius:10px;padding:14px 12px;text-align:center;transition:.2s;cursor:pointer">
      <div style="font-size:1.4rem;font-weight:800;color:<?=$sc?>"><?=$counts[$s]?></div>
      <div style="font-size:.72rem;color:#475569;font-weight:600;margin-top:3px"><?=$l?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="filter-tabs">
  <a href="assistance.php" class="ftab <?=!$filterStatus?'active':''?>">All <span class="cnt"><?=$totalReqs?></span></a>
  <?php foreach ($statusLabels as $s => $l): [$sc,$bg]=$statusColors[$s]; ?>
  <a href="?status=<?=$s?>" class="ftab <?=$filterStatus===$s?'active':''?>" style="<?=$filterStatus===$s?'':'border-color:'.$sc.';color:'.$sc?>"><?=$l?> <span class="cnt" style="<?=$filterStatus===$s?'':'background:'.$bg.';color:'.$sc?>"><?=$counts[$s]?></span></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>#</th><th>Title</th><th>Organization</th><th>Activity Type</th><th>Target Date</th><th>Docs</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (empty($requests)): ?>
        <tr><td colspan="9" class="empty">No assistance requests found.</td></tr>
      <?php else: foreach ($requests as $i => $r):
        [$sc,$bg] = $statusColors[$r['status']] ?? ['#475569','#f1f5f9'];
      ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($r['title'])?></strong></td>
          <td><?=htmlspecialchars($r['org_name'])?></td>
          <td><?=htmlspecialchars($r['request_type'] ?? $r['activity_type'] ?? 'N/A')?></td>
          <td><?=isset($r['scheduled_date']) && $r['scheduled_date'] ? date('M j, Y',strtotime($r['scheduled_date'])) : (isset($r['target_date']) && $r['target_date'] ? date('M j, Y',strtotime($r['target_date'])) : 'Not set')?></td>
          <td><span style="font-size:.82rem"><strong><?=$r['doc_count']?></strong> docs</span></td>
          <td><span style="background:<?=$bg?>;color:<?=$sc?>;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700"><?=$statusLabels[$r['status']] ?? 'Unknown'?></span></td>
          <td style="font-size:.8rem"><?=date('M j, Y',strtotime($r['created_at']))?></td>
          <td><a href="?view=<?=$r['id']?>" class="btn-primary" style="padding:6px 12px;font-size:.8rem"><i class="fas fa-eye"></i> Review</a></td>
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
