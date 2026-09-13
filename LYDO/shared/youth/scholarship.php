<?php
require_once __DIR__ . "/../config.php";
if (empty($_SESSION["user_id"])) { header("Location: ../../login.php"); exit; }
$pdo    = db();
$userId = (int)$_SESSION["user_id"];
$uStmt  = $pdo->prepare("SELECT * FROM youth_users WHERE id=? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
$nc   = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE");
$nc->execute([$userId]);
$notifCount = (int)$nc->fetchColumn();

$uploadDir = __DIR__ . "/../uploads/scholarship/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$error = ""; $success = "";
$tab = $_GET["tab"] ?? "batches";

$docLabels = [
    "application_form"   => "Scholarship Application Form",
    "birth_certificate"  => "Birth Certificate",
    "grades"             => "Copy of Grades (Latest)",
    "school_id"          => "School ID",
    "income_proof"       => "Proof of Household Income / Certificate of Indigency",
];

$statusColors = [
    "submitted"    => ["#1565c0","#e3f2fd"],
    "under_review" => ["#f57f17","#fff8e1"],
    "for_exam"     => ["#7b1fa2","#f3e5f5"],
    "passed"       => ["#2e7d32","#e8f5e9"],
    "failed"       => ["#c62828","#ffebee"],
    "approved"     => ["#00796b","#e0f2f1"],
    "rejected"     => ["#c62828","#ffebee"],
    "beneficiary"  => ["#1565c0","#e3f2fd"],
];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    if ($action === "apply") {
        $batchId = (int)$_POST["batch_id"];
        $batch   = $pdo->prepare("SELECT * FROM scholarship_batches WHERE id=? AND status=?");
        $batch->execute([$batchId, "open"]);
        $b = $batch->fetch();
        if (!$b) { $error = "This batch is no longer accepting applications."; }
        else {
            $dup = $pdo->prepare("SELECT id FROM scholarship_applications WHERE user_id=? AND batch_id=? LIMIT 1");
            $dup->execute([$userId, $batchId]);
            if ($dup->fetch()) { $error = "You already applied for this batch."; }
            else {
                $pdo->prepare("INSERT INTO scholarship_applications (batch_id, user_id, scholarship_type, school_name, course, year_level, gwa, family_income, siblings_count, parent_occupation, financial_need, status) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending')")
                    ->execute([
                        $batchId,
                        $userId,
                        'Iskolar ng Bayan',
                        trim($_POST["school_name"]),
                        trim($_POST["course"]),
                        trim($_POST["year_level"]),
                        $_POST["gwa"] ?: null,
                        $_POST["household_income"] ?: null,
                        (int)($_POST["siblings"] ?? 0),
                        trim($_POST["parent_occupation"] ?? ""),
                        trim($_POST["financial_need"] ?? "I am applying for this scholarship to support my education.")
                    ]);
                $appId   = (int)$pdo->lastInsertId();
                $allowed = ["pdf","doc","docx","jpg","jpeg","png"];
                foreach ($docLabels as $type => $label) {
                    if (!isset($_FILES[$type]) || $_FILES[$type]["error"] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($_FILES[$type]["name"], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed) || $_FILES[$type]["size"] > 10*1024*1024) continue;
                    $fname = uniqid($type."_", true).".".$ext;
                    move_uploaded_file($_FILES[$type]["tmp_name"], $uploadDir.$fname);
                    $pdo->prepare("INSERT INTO scholarship_documents (application_id, doc_type, file_path, original_name, file_size, status) VALUES (?,?,?,?,?, 'pending')")
                        ->execute([$appId, $type, $fname, $_FILES[$type]["name"], $_FILES[$type]["size"]]);
                }
                $success = "Application submitted! Application ID: #".$appId.". You will be notified of updates.";
                $tab = "my_applications";
            }
        }
    }
}

$batches = $pdo->query("SELECT * FROM scholarship_batches ORDER BY created_at DESC")->fetchAll();
$myApps  = $pdo->prepare("SELECT a.*,b.name as batch_name,b.exam_date,b.exam_venue,b.exam_time,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id) as doc_count,(SELECT COUNT(*) FROM scholarship_documents d WHERE d.application_id=a.id AND d.status=\"verified\") as verified_count FROM scholarship_applications a JOIN scholarship_batches b ON b.id=a.batch_id WHERE a.user_id=? ORDER BY a.created_at DESC");
$myApps->execute([$userId]);
$myApplications = $myApps->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Iskolar ng Bayan  LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.batch-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
.batch-header{padding:16px 20px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff}
.batch-title{font-size:1rem;font-weight:800;margin-bottom:4px}
.batch-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:.8rem;opacity:.85;margin-top:8px}
.batch-meta span{display:flex;align-items:center;gap:5px}
.batch-body{padding:16px 20px}
.status-open{background:#e8f5e9;color:#2e7d32;padding:4px 12px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-closed{background:#ffebee;color:#c62828;padding:4px 12px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-evaluation{background:#fff8e1;color:#f57f17;padding:4px 12px;border-radius:50px;font-size:.75rem;font-weight:700}
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.doc-item{border:1.5px dashed #e2e8f0;border-radius:10px;padding:12px;position:relative;background:#f8fafc;transition:.2s;cursor:pointer}
.doc-item:hover{border-color:#1565c0;background:#e3f2fd}
.doc-item.has-file{border-color:#2e7d32;background:#e8f5e9}
.doc-item label{display:flex;flex-direction:column;gap:4px;cursor:pointer}
.doc-item .di{font-size:1.2rem;color:#94a3b8}
.doc-item.has-file .di{color:#2e7d32}
.doc-item .dn{font-size:.82rem;font-weight:600;color:#1e293b}
.doc-item .dh{font-size:.72rem;color:#475569}
.doc-item input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:13px}
.fg label{font-size:.82rem;font-weight:600;color:#1e293b}
.fg input,.fg select,.fg textarea{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus{border-color:#1e88e5;background:#fff}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.req{color:#e53935}
.sec-title{font-size:.85rem;font-weight:700;color:#0d3b6e;margin:16px 0 12px;padding-bottom:7px;border-bottom:2px solid #e3f2fd;display:flex;align-items:center;gap:7px}
.btn-apply{width:100%;padding:12px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.92rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;box-shadow:0 4px 14px rgba(21,101,192,.3);margin-top:8px}
.btn-apply:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,101,192,.4)}
.app-card{border:1.5px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-bottom:12px;background:#fff}
.doc-status-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:.83rem}
.doc-status-row:last-child{border-bottom:none}
.exam-box{background:linear-gradient(135deg,#7b1fa2,#9c27b0);color:#fff;border-radius:12px;padding:16px 18px;margin-top:10px}
@media(max-width:600px){.form-row-2,.form-row-3,.doc-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-graduation-cap" style="color:#1565c0;margin-right:8px"></i>Iskolar ng Bayan Scholarship Program</h2>
  <p>Apply for the LYDO scholarship grant for qualified youth residents of Sta. Cruz, Laguna.</p>
</div>

<?php if ($error): ?><div style="padding:12px 16px;border-radius:10px;background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if ($success): ?><div style="padding:12px 16px;border-radius:10px;background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-check-circle"></i><?=htmlspecialchars($success)?></div><?php endif; ?>

<div class="tabs">
  <a href="?tab=batches"         class="tab-btn <?=$tab==='batches'?'active':''?>"><i class="fas fa-list"></i> Open Batches</a>
  <a href="?tab=my_applications" class="tab-btn <?=$tab==='my_applications'?'active':''?>"><i class="fas fa-file-alt"></i> My Applications</a>
</div>

<?php if ($tab === 'batches'): ?>
<?php if (empty($batches)): ?>
  <div style="text-align:center;padding:48px;color:#94a3b8"><i class="fas fa-graduation-cap" style="font-size:2.5rem;display:block;margin-bottom:12px"></i><p>No scholarship batches available at this time.</p></div>
<?php else: foreach ($batches as $b):
  $alreadyApplied = false;
  foreach ($myApplications as $a) { if ($a['batch_id']==$b['id']) { $alreadyApplied=true; break; } }
?>
<div class="batch-card">
  <div class="batch-header">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div>
        <div class="batch-title"><?=htmlspecialchars($b['name'])?></div>
        <div style="font-size:.8rem;opacity:.8"><?=htmlspecialchars($b['school_year'])?>  <?=htmlspecialchars($b['semester']??'')?></div>
      </div>
      <span class="status-<?=$b['status']?>"><?=ucfirst($b['status'])?></span>
    </div>
    <div class="batch-meta">
      <span><i class="fas fa-users"></i> <?=$b['slots']?> slots</span>
      <span><i class="fas fa-star"></i> Min GWA: <?=$b['gwa_required']?></span>
      <span><i class="fas fa-peso-sign"></i> Max Income: <?=number_format($b['income_limit'],2)?></span>
      <?php if ($b['app_end']): ?><span><i class="fas fa-calendar"></i> Deadline: <?=date('M j, Y',strtotime($b['app_end']))?></span><?php endif; ?>
    </div>
  </div>
  <div class="batch-body">
    <?php if ($b['description']): ?><p style="font-size:.85rem;color:#475569;line-height:1.6;margin-bottom:14px"><?=htmlspecialchars($b['description'])?></p><?php endif; ?>
    <?php if ($b['exam_date']): ?>
    <div style="background:#f3e5f5;border-radius:8px;padding:10px 14px;font-size:.83rem;color:#7b1fa2;font-weight:600;margin-bottom:14px">
      <i class="fas fa-calendar-check"></i> Examination: <?=date('F j, Y',strtotime($b['exam_date']))?><?=$b['exam_time']?' at '.date('g:i A',strtotime($b['exam_time'])):''?><?=$b['exam_venue']?'  '.htmlspecialchars($b['exam_venue']):''?>
    </div>
    <?php endif; ?>
    <?php if ($alreadyApplied): ?>
      <div style="padding:10px 14px;background:#e3f2fd;color:#1565c0;border-radius:8px;font-size:.85rem;font-weight:600"><i class="fas fa-check"></i> You have already applied for this batch.</div>
    <?php elseif ($b['status']==='open'): ?>
      <button class="btn-apply" style="width:auto;padding:10px 22px" onclick="openApplyModal(<?=$b['id']?>,<?=htmlspecialchars(json_encode($b['name']),ENT_QUOTES)?>)">
        <i class="fas fa-paper-plane"></i> Apply Now
      </button>
    <?php else: ?>
      <div style="padding:10px 14px;background:#f1f5f9;color:#475569;border-radius:8px;font-size:.85rem;font-weight:600"><i class="fas fa-lock"></i> Applications closed.</div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<?php elseif ($tab === 'my_applications'): ?>
<?php if (empty($myApplications)): ?>
  <div style="text-align:center;padding:48px;color:#94a3b8"><i class="fas fa-file-alt" style="font-size:2.5rem;display:block;margin-bottom:12px"></i><p>No applications yet.</p></div>
<?php else: foreach ($myApplications as $app):
  [$sc,$sb] = $statusColors[$app['status']] ?? ['#475569','#f1f5f9'];
  $docs = $pdo->prepare('SELECT * FROM scholarship_documents WHERE application_id=?');
  $docs->execute([$app['id']]);
  $documents = $docs->fetchAll();
?>
<div class="app-card">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <div>
      <div style="font-weight:800;font-size:.95rem;margin-bottom:3px"><?=htmlspecialchars($app['batch_name'])?></div>
      <div style="font-size:.78rem;color:#94a3b8">Application #<?=$app['id']?>  Submitted <?=date('M j, Y',strtotime($app['created_at']))?></div>
    </div>
    <span style="background:<?=$sb?>;color:<?=$sc?>;padding:4px 12px;border-radius:50px;font-size:.75rem;font-weight:700"><?=ucwords(str_replace('_',' ',$app['status']))?></span>
  </div>

  <?php if ($app['status']==='for_exam' && $app['exam_scheduled']): ?>
  <div class="exam-box">
    <div style="font-size:.82rem;opacity:.85;margin-bottom:4px"><i class="fas fa-calendar-check"></i> Examination Schedule</div>
    <div style="font-weight:700"><?=date('F j, Y',strtotime($app['exam_scheduled']))?></div>
    <?php if ($app['exam_venue']): ?><div style="font-size:.82rem;opacity:.85;margin-top:3px"><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($app['exam_venue'])?></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($app['exam_score'] !== null): ?>
  <div style="background:#e8f5e9;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#2e7d32;font-weight:600;margin-top:10px">
    <i class="fas fa-star"></i> Exam Score: <?=$app['exam_score']?>/100
    <?php if ($app['qualification_score']): ?>  Qualification Score: <?=$app['qualification_score']?><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($app['rejection_reason']): ?>
  <div style="background:#ffebee;border-radius:8px;padding:10px 14px;font-size:.83rem;color:#c62828;margin-top:10px"><strong>Reason:</strong> <?=htmlspecialchars($app['rejection_reason'])?></div>
  <?php endif; ?>

  <div style="margin-top:12px">
    <div style="font-size:.78rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">Documents (<?=$app['verified_count']?>/<?=$app['doc_count']?> verified)</div>
    <?php foreach ($documents as $doc):
      $dColors = ['verified'=>['#2e7d32','#e8f5e9','fa-check-circle'],'rejected'=>['#c62828','#ffebee','fa-times-circle'],'pending'=>['#f57f17','#fff8e1','fa-clock']];
      [$dc,$db,$di] = $dColors[$doc['status']] ?? ['#475569','#f1f5f9','fa-file'];
    ?>
    <div class="doc-status-row">
      <i class="fas <?=$di?>" style="color:<?=$dc?>;width:16px;flex-shrink:0"></i>
      <span style="flex:1"><?=htmlspecialchars($docLabels[$doc['doc_type']]??$doc['doc_type'])?></span>
      <span style="background:<?=$db?>;color:<?=$dc?>;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700"><?=ucfirst($doc['status'])?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; endif; ?>
<?php endif; ?>

</main>
</div>

<!-- APPLICATION MODAL -->
<div id="applyModal" style="position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(5px);z-index:500;display:none;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:680px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.2)">
    <div style="padding:16px 22px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
      <div><div style="font-size:.95rem;font-weight:800"><i class="fas fa-graduation-cap" style="margin-right:7px"></i>Scholarship Application</div><div id="applyBatchName" style="font-size:.78rem;opacity:.8"></div></div>
      <button onclick="document.getElementById('applyModal').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:7px;cursor:pointer;font-size:.88rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="padding:22px">
      <input type="hidden" name="action" value="apply"/>
      <input type="hidden" name="batch_id" id="applyBatchId"/>

      <div class="sec-title"><i class="fas fa-user"></i> Personal Information</div>
      <div class="form-row-2">
        <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" id="full_name" name="full_name" required value="<?=htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??''))?>"/></div>
        <div class="fg"><label>Birthdate <span class="req">*</span></label><input type="date" name="birthdate" required value="<?=htmlspecialchars($user['birthdate']??'')?>" onchange="calcAge(this)"/></div>
      </div>
      <div class="form-row-3">
        <div class="fg"><label>Age <span class="req">*</span></label><input type="number" name="age" id="schAge" required value="<?=$user['age']??''?>"/></div>
        <div class="fg"><label>Gender <span class="req">*</span></label>
          <select name="gender" required>
            <option value="">Select</option>
            <?php foreach(['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
            <option value="<?=$g?>" <?=($user['gender']??'')===$g?'selected':''?>><?=$g?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label>Contact Number <span class="req">*</span></label><input type="tel" id="contact_number" name="contact_number" required value="<?=htmlspecialchars($user['contact_number']??'')?>"/></div>
      </div>
      <div class="fg"><label>Complete Address <span class="req">*</span></label><input type="text" id="address" name="address" required value="<?=htmlspecialchars(trim(($user['house_number']??'').' '.($user['street']??'').' '.($user['barangay']??'').' '.($user['municipality']??'')))?>"/></div>
      <div class="fg"><label>Email Address <span class="req">*</span></label><input type="email" id="email" name="email" required value="<?=htmlspecialchars($user['email']??'')?>"/></div>

      <div class="sec-title"><i class="fas fa-graduation-cap"></i> Academic Information</div>
      <div class="form-row-2">
        <div class="fg"><label>School Name <span class="req">*</span></label><input type="text" id="school_name" name="school_name" required value="<?=htmlspecialchars($user['school_name']??'')?>"/></div>
        <div class="fg"><label>Course / Program <span class="req">*</span></label><input type="text" id="course" name="course" required value="<?=htmlspecialchars($user['course_or_grade']??'')?>"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Year Level <span class="req">*</span></label>
          <select id="year_level" name="year_level" required>
            <option value="">Select</option>
            <?php foreach(['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate'] as $y): ?><option value="<?=$y?>"><?=$y?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label>General Weighted Average (GWA)</label><input type="number" name="gwa" step="0.01" min="1" max="5" placeholder="e.g. 1.50"/></div>
      </div>

      <div class="sec-title"><i class="fas fa-home"></i> Family & Financial Information</div>
      <div class="form-row-2">
        <div class="fg"><label>Parent / Guardian Name</label><input type="text" name="parent_name" placeholder="Full name"/></div>
        <div class="fg"><label>Parent / Guardian Occupation</label><input type="text" name="parent_occupation" placeholder="e.g. Farmer, Vendor"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Monthly Household Income ()</label><input type="number" name="household_income" step="0.01" min="0" placeholder="e.g. 12000"/></div>
        <div class="fg"><label>Number of Siblings</label><input type="number" name="siblings" min="0" value="0"/></div>
      </div>

      <div class="sec-title"><i class="fas fa-paperclip"></i> Required Documents <span style="font-weight:400;color:#94a3b8;font-size:.78rem">(PDF, DOC, JPG, PNG  max 10MB)</span></div>
      <div class="doc-grid">
        <?php foreach ($docLabels as $type => $label): ?>
        <div class="doc-item" id="sdw_<?=$type?>">
          <label>
            <i class="fas fa-file-upload di" id="sdi_<?=$type?>"></i>
            <span class="dn"><?=htmlspecialchars($label)?></span>
            <span class="dh" id="sdh_<?=$type?>">Click to upload</span>
            <input type="file" id="<?=$type?>" name="<?=$type?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="markSchDoc('<?=$type?>',this)"/>
          </label>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Check panel -->
      <div id="schCheckPanel" style="display:none;margin-top:12px"></div>

      <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
        <button type="button" id="schCheckBtn" style="width:100%;padding:11px;background:#f1f5f9;color:#1565c0;border:1.5px solid #90caf9;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background='#f1f5f9'">
          <i class="fas fa-search"></i> Check My Submission First
        </button>
        <div style="display:flex;justify-content:flex-end;gap:10px">
          <button type="button" onclick="document.getElementById('applyModal').style.display='none'" style="padding:10px 20px;background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:600;cursor:pointer">Cancel</button>
          <button type="submit" class="btn-apply" id="schSubmitBtn" style="width:auto;padding:10px 24px"><i class="fas fa-paper-plane"></i> Submit Application</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',function(){ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
function openApplyModal(id,name){
  document.getElementById('applyBatchId').value=id;
  document.getElementById('applyBatchName').textContent=name;
  document.getElementById('applyModal').style.display='flex';
}
function calcAge(input){
  const dob=new Date(input.value),today=new Date();
  let age=today.getFullYear()-dob.getFullYear();
  if(today.getMonth()<dob.getMonth()||(today.getMonth()===dob.getMonth()&&today.getDate()<dob.getDate()))age--;
  document.getElementById('schAge').value=age>=0?age:'';
}
function markSchDoc(type,input){
  if(input.files[0]){
    document.getElementById('sdw_'+type).classList.add('has-file');
    document.getElementById('sdi_'+type).className='fas fa-check-circle di';
    document.getElementById('sdh_'+type).textContent=input.files[0].name;
  }
}
document.getElementById('applyModal').addEventListener('click',e=>{if(e.target===document.getElementById('applyModal'))document.getElementById('applyModal').style.display='none'});

// Re-init checker each time modal opens (fields are inside modal)
const _origOpenApply = openApplyModal;
openApplyModal = function(id, name) {
  _origOpenApply(id, name);
  setTimeout(() => {
    FormChecker.init({
      formId:      'schApplyForm',
      checkBtnId:  'schCheckBtn',
      submitBtnId: 'schSubmitBtn',
      panelId:     'schCheckPanel',
      requiredFields: [
        { id: 'full_name',       label: 'Full Name' },
        { id: 'schAge',          label: 'Age' },
        { id: 'school_name',     label: 'School Name' },
        { id: 'course',          label: 'Course / Program' },
        { id: 'year_level',      label: 'Year Level' },
        { id: 'contact_number',  label: 'Contact Number' },
        { id: 'email',           label: 'Email Address' },
        { id: 'address',         label: 'Complete Address' },
      ],
      requiredFiles: [
        { id: 'application_form',  label: 'Scholarship Application Form' },
        { id: 'birth_certificate', label: 'Birth Certificate' },
        { id: 'grades',            label: 'Copy of Grades' },
        { id: 'school_id',         label: 'School ID' },
        { id: 'income_proof',      label: 'Proof of Household Income' },
      ],
    });
  }, 100);
};
</script>
<script src="form_checker.js"></script>
</body></html>
