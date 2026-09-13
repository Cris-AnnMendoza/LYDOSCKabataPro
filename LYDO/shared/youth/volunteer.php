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

$error = ""; $success = "";
$tab = $_GET["tab"] ?? "programs";

// Age from profile
$userAge = (int)($user["age"] ?? 0);
if (!$userAge && !empty($user["birthdate"])) {
    $dob = new DateTime($user["birthdate"]);
    $userAge = (int)$dob->diff(new DateTime())->y;
}

// Handle registration
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "register") {
    $programId = (int)$_POST["program_id"];
    $prog = $pdo->prepare("SELECT * FROM volunteer_programs WHERE id=?");
    $prog->execute([$programId]);
    $program = $prog->fetch();

    if (!$program) { $error = "Program not found."; }
    else {
        $age = (int)$_POST["age"];
        if ($age < $program["min_age"] || $age > $program["max_age"]) {
            $error = "Age not eligible. This program requires age " . $program["min_age"] . "�" . $program["max_age"] . ".";
        } else {
            $dup = $pdo->prepare("SELECT id FROM volunteer_registrations WHERE user_id=? AND program_id=? LIMIT 1");
            $dup->execute([$userId, $programId]);
            if ($dup->fetch()) { $error = "You are already registered for this program."; }
            else {
                $pdo->prepare("INSERT INTO volunteer_registrations (user_id, program_id, status) VALUES (?, ?, 'pending')")
                    ->execute([$userId, $programId]);
                $success = "Registration submitted! Pending admin approval.";
                $tab = "my_registrations";
            }
        }
    }
}

$programs = $pdo->query("SELECT * FROM volunteer_programs ORDER BY type")->fetchAll();
$myRegs   = $pdo->prepare("SELECT r.*,p.name as prog_name,p.type as prog_type,p.location FROM volunteer_registrations r JOIN volunteer_programs p ON p.id=r.program_id WHERE r.user_id=? ORDER BY r.created_at DESC");
$myRegs->execute([$userId]);
$myRegistrations = $myRegs->fetchAll();

$typeLabels = ["youth_volunteer"=>"Youth Volunteer Program","linggo_kabataan"=>"Linggo ng Kabataan","junior_officials"=>"Junior Officials Program"];
$typeColors = ["youth_volunteer"=>["#1565c0","#e3f2fd"],"linggo_kabataan"=>["#2e7d32","#e8f5e9"],"junior_officials"=>["#f57f17","#fff8e1"]];
$statusColors = ["pending"=>["#f57f17","#fff8e1"],"approved"=>["#2e7d32","#e8f5e9"],"rejected"=>["#c62828","#ffebee"],"completed"=>["#00796b","#e0f2f1"]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Volunteer Program  LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.prog-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
.prog-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.prog-title{font-size:1rem;font-weight:800;color:#1e293b}
.prog-body{padding:0 20px 18px}
.prog-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:.82rem;color:#475569;margin-bottom:12px}
.prog-meta span{display:flex;align-items:center;gap:5px}
.eligibility-bar{padding:10px 14px;border-radius:8px;font-size:.82rem;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.elig-ok{background:#e8f5e9;color:#2e7d32}
.elig-no{background:#ffebee;color:#c62828}
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.reg-card{border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.hours-badge{background:#e3f2fd;color:#1565c0;padding:4px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(5px);z-index:500;display:flex;align-items:center;justify-content:center;padding:16px}
.modal-box{background:#fff;border-radius:16px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.2)}
.modal-head{padding:16px 22px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0}
.modal-body-inner{padding:22px}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fg label{font-size:.83rem;font-weight:600;color:#1e293b}
.fg input,.fg select,.fg textarea{padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.9rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus{border-color:#1e88e5;background:#fff}
.fg input.err{border-color:#c62828}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.req{color:#e53935}
.btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.92rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,101,192,.35)}
.btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none}
.leaderboard-row{display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:.88rem}
.leaderboard-row:last-child{border-bottom:none}
.lb-rank{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0}
.cert-btn{padding:6px 14px;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:.2s}
.cert-btn:hover{background:#2e7d32;color:#fff}
@media(max-width:600px){.form-row-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-hands-helping" style="color:#1565c0;margin-right:8px"></i>Youth Volunteer Program</h2>
  <p>Register for volunteer programs, track your hours, and earn certificates.</p>
</div>

<?php if ($error): ?><div style="padding:12px 16px;border-radius:10px;background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if ($success): ?><div style="padding:12px 16px;border-radius:10px;background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-check-circle"></i><?=htmlspecialchars($success)?></div><?php endif; ?>

<div class="tabs">
  <a href="?tab=programs"         class="tab-btn <?=$tab==='programs'?'active':''?>"><i class="fas fa-list"></i> Programs</a>
  <a href="?tab=my_registrations" class="tab-btn <?=$tab==='my_registrations'?'active':''?>"><i class="fas fa-user-check"></i> My Registrations</a>
  <a href="?tab=attendance"       class="tab-btn <?=$tab==='attendance'?'active':''?>"><i class="fas fa-calendar-check"></i> Attendance</a>
  <a href="?tab=leaderboard"      class="tab-btn <?=$tab==='leaderboard'?'active':''?>"><i class="fas fa-trophy"></i> Leaderboard</a>
</div>

<?php if ($tab === 'programs'): ?>
<?php foreach ($programs as $prog):
  [$tc,$tb] = $typeColors[$prog['type']] ?? ['#475569','#f1f5f9'];
  $eligible = $userAge >= $prog['min_age'] && $userAge <= $prog['max_age'];
  $alreadyReg = false;
  foreach ($myRegistrations as $r) { if ($r['program_id'] == $prog['id']) { $alreadyReg = true; break; } }
  $regCount = (int)$pdo->prepare('SELECT COUNT(*) FROM volunteer_registrations WHERE program_id=?')->execute([$prog['id']]) ? 0 : 0;
  $rcStmt = $pdo->prepare('SELECT COUNT(*) FROM volunteer_registrations WHERE program_id=?');
  $rcStmt->execute([$prog['id']]);
  $regCount = (int)$rcStmt->fetchColumn();
?>
<div class="prog-card">
  <div class="prog-header">
    <div>
      <span style="background:<?=$tb?>;color:<?=$tc?>;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;display:inline-block;margin-bottom:6px"><?=$typeLabels[$prog['type']]?></span>
      <div class="prog-title"><?=htmlspecialchars($prog['name'])?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:.8rem;color:#94a3b8"><?=$regCount?>/<?=$prog['slots']?> slots</div>
      <div style="width:100px;height:6px;background:#e2e8f0;border-radius:50px;margin-top:4px;overflow:hidden"><div style="height:100%;width:<?=min(100,round($regCount/$prog['slots']*100))?>%;background:<?=$tc?>;border-radius:50px"></div></div>
    </div>
  </div>
  <div class="prog-body">
    <div class="prog-meta">
      <span><i class="fas fa-users"></i> Age <?=$prog['min_age']?><?=$prog['max_age']?></span>
      <?php if ($prog['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($prog['location'])?></span><?php endif; ?>
      <?php if ($prog['start_date']): ?><span><i class="fas fa-calendar"></i> <?=date('M j, Y',strtotime($prog['start_date']))?></span><?php endif; ?>
    </div>
    <?php if ($prog['description']): ?><p style="font-size:.85rem;color:#475569;line-height:1.6;margin-bottom:12px"><?=htmlspecialchars($prog['description'])?></p><?php endif; ?>
    <?php if ($userAge > 0): ?>
    <div class="eligibility-bar <?=$eligible?'elig-ok':'elig-no'?>">
      <i class="fas fa-<?=$eligible?'check-circle':'times-circle'?>"></i>
      <?php if ($eligible): ?>You are eligible (Age <?=$userAge?>)<?php else: ?>Not eligible  requires age <?=$prog['min_age']?><?=$prog['max_age']?> (Your age: <?=$userAge?>)<?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($alreadyReg): ?>
      <div style="padding:8px 14px;background:#e3f2fd;color:#1565c0;border-radius:8px;font-size:.83rem;font-weight:600"><i class="fas fa-check"></i> Already registered</div>
    <?php elseif ($eligible && $regCount < $prog['slots']): ?>
      <button class="btn-submit" style="width:auto;padding:9px 20px" onclick="openRegModal(<?=$prog['id']?>,<?=htmlspecialchars(json_encode($prog['name']),ENT_QUOTES)?>)">
        <i class="fas fa-user-plus"></i> Register Now
      </button>
    <?php elseif ($regCount >= $prog['slots']): ?>
      <div style="padding:8px 14px;background:#ffebee;color:#c62828;border-radius:8px;font-size:.83rem;font-weight:600"><i class="fas fa-ban"></i> Slots full</div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php elseif ($tab === 'my_registrations'): ?>
<?php if (empty($myRegistrations)): ?>
  <div style="text-align:center;padding:48px;color:#94a3b8"><i class="fas fa-clipboard-list" style="font-size:2.5rem;display:block;margin-bottom:12px"></i><p>No registrations yet. Browse programs to get started.</p></div>
<?php else: foreach ($myRegistrations as $r):
  [$sc,$sb] = $statusColors[$r['status']] ?? ['#475569','#f1f5f9'];
  [$tc,$tb] = $typeColors[$r['prog_type']] ?? ['#475569','#f1f5f9'];
?>
<div class="reg-card">
  <div style="flex:1">
    <div style="font-weight:700;font-size:.92rem;margin-bottom:4px"><?=htmlspecialchars($r['prog_name'])?></div>
    <div style="font-size:.78rem;color:#475569;display:flex;gap:12px;flex-wrap:wrap">
      <span><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($r['location']?:'')?></span>
      <span><i class="fas fa-clock"></i> <?=$r['total_hours']?> hrs</span>
      <?php if ($r['orientation_date']): ?><span><i class="fas fa-calendar"></i> Orientation: <?=date('M j, Y',strtotime($r['orientation_date']))?></span><?php endif; ?>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
    <span style="background:<?=$sb?>;color:<?=$sc?>;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700"><?=ucfirst($r['status'])?></span>
    <?php if ($r['certificate_issued']): ?>
      <a href="certificate.php?reg=<?=$r['id']?>" class="cert-btn" target="_blank"><i class="fas fa-certificate"></i> Certificate</a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<?php elseif ($tab === 'attendance'): ?>
<?php
$attStmt = $pdo->prepare('SELECT a.*,r.prog_name FROM volunteer_attendance a JOIN (SELECT r2.id,p.name as prog_name FROM volunteer_registrations r2 JOIN volunteer_programs p ON p.id=r2.program_id WHERE r2.user_id=?) r ON r.id=a.registration_id ORDER BY a.event_date DESC');
$attStmt->execute([$userId]);
$attendance = $attStmt->fetchAll();
?>
<?php if (empty($attendance)): ?>
  <div style="text-align:center;padding:48px;color:#94a3b8"><i class="fas fa-calendar-times" style="font-size:2.5rem;display:block;margin-bottom:12px"></i><p>No attendance records yet.</p></div>
<?php else: ?>
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.85rem">
    <thead><tr style="background:#f8fafc"><th style="padding:11px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#475569;border-bottom:1px solid #e2e8f0">Event</th><th style="padding:11px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#475569;border-bottom:1px solid #e2e8f0">Program</th><th style="padding:11px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#475569;border-bottom:1px solid #e2e8f0">Date</th><th style="padding:11px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#475569;border-bottom:1px solid #e2e8f0">Hours</th><th style="padding:11px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#475569;border-bottom:1px solid #e2e8f0">Status</th></tr></thead>
    <tbody>
    <?php foreach ($attendance as $a):
      $aColors = ['present'=>['#2e7d32','#e8f5e9'],'absent'=>['#c62828','#ffebee'],'excused'=>['#f57f17','#fff8e1']];
      [$ac,$ab] = $aColors[$a['status']] ?? ['#475569','#f1f5f9'];
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
      <td style="padding:10px 16px;font-weight:600"><?=htmlspecialchars($a['event_name'])?></td>
      <td style="padding:10px 16px;color:#475569"><?=htmlspecialchars($a['prog_name'])?></td>
      <td style="padding:10px 16px"><?=date('M j, Y',strtotime($a['event_date']))?></td>
      <td style="padding:10px 16px"><span class="hours-badge"><?=$a['hours']?> hrs</span></td>
      <td style="padding:10px 16px"><span style="background:<?=$ab?>;color:<?=$ac?>;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700"><?=ucfirst($a['status'])?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'leaderboard'): ?>
<?php
$lb = $pdo->query('SELECT r.user_id,u.first_name,u.last_name,u.barangay,SUM(r.total_hours) as total_hours,COUNT(r.id) as programs FROM volunteer_registrations r JOIN youth_users u ON u.id=r.user_id WHERE r.status IN ("approved","completed") GROUP BY r.user_id ORDER BY total_hours DESC LIMIT 20')->fetchAll();
$rankColors = ['#f57f17','#607d8b','#795548'];
?>
<div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:.9rem;font-weight:700;display:flex;align-items:center;gap:7px"><i class="fas fa-trophy" style="color:#f57f17"></i> Volunteer Leaderboard</div>
  <?php if (empty($lb)): ?>
    <div style="text-align:center;padding:40px;color:#94a3b8">No data yet.</div>
  <?php else: foreach ($lb as $i => $v): $rank=$i+1; ?>
  <div class="leaderboard-row">
    <div class="lb-rank" style="background:<?=$rankColors[min($rank-1,2)]??'#e2e8f0'?>;color:<?=$rank<=3?'#fff':'#475569'?>"><?=$rank<=3?['','',''][$rank-1]:$rank?></div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:.88rem"><?=htmlspecialchars($v['first_name'].' '.$v['last_name'])?></div>
      <div style="font-size:.75rem;color:#94a3b8"><?=htmlspecialchars($v['barangay']?:'')?>  <?=$v['programs']?> program<?=$v['programs']!=1?'s':''?></div>
    </div>
    <span class="hours-badge" style="font-size:.82rem"><?=$v['total_hours']?> hrs</span>
  </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>

</main>
</div>

<!-- REGISTRATION MODAL -->
<div id="regModal" class="modal-bg" style="display:none">
  <div class="modal-box">
    <div class="modal-head">
      <h3 style="font-size:.95rem;font-weight:700"><i class="fas fa-user-plus" style="margin-right:7px"></i>Volunteer Registration</h3>
      <button onclick="document.getElementById('regModal').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body-inner">
      <div id="regProgName" style="background:#e3f2fd;color:#1565c0;padding:8px 14px;border-radius:8px;font-size:.85rem;font-weight:700;margin-bottom:16px"></div>
      <form method="POST">
        <input type="hidden" name="action" value="register"/>
        <input type="hidden" name="program_id" id="regProgId"/>
        <div style="font-size:.85rem;font-weight:700;color:#0d3b6e;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #e3f2fd"><i class="fas fa-user" style="margin-right:6px"></i>Personal Information</div>
        <div class="form-row-2">
          <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" required value="<?=htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??''))?>"/></div>
          <div class="fg"><label>Age <span class="req">*</span></label><input type="number" name="age" id="regAge" required min="1" max="100" value="<?=$userAge?>" oninput="checkEligibility()"/></div>
        </div>
        <div class="form-row-2">
          <div class="fg"><label>Birthdate <span class="req">*</span></label><input type="date" name="birthdate" required value="<?=htmlspecialchars($user['birthdate']??'')?>" onchange="calcAge(this)"/></div>
          <div class="fg"><label>Gender <span class="req">*</span></label>
            <select name="gender" required>
              <option value="">Select</option>
              <?php foreach(['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
              <option value="<?=$g?>" <?=($user['gender']??'')===$g?'selected':''?>><?=$g?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="fg"><label>Address <span class="req">*</span></label><input type="text" name="address" required value="<?=htmlspecialchars(trim(($user['house_number']??'').' '.($user['street']??'').' '.($user['barangay']??'').' '.($user['municipality']??'')))?>"/></div>
        <div class="form-row-2">
          <div class="fg"><label>Contact Number <span class="req">*</span></label><input type="tel" name="contact_number" required value="<?=htmlspecialchars($user['contact_number']??'')?>"/></div>
          <div class="fg"><label>Email <span class="req">*</span></label><input type="email" name="email" required value="<?=htmlspecialchars($user['email']??'')?>"/></div>
        </div>
        <div class="fg"><label>School / Organization</label><input type="text" name="school_org" value="<?=htmlspecialchars($user['school_name']??'')?>"/></div>
        <div style="font-size:.85rem;font-weight:700;color:#0d3b6e;margin:14px 0 12px;padding-bottom:8px;border-bottom:2px solid #e3f2fd"><i class="fas fa-phone" style="margin-right:6px"></i>Emergency Contact</div>
        <div class="form-row-2">
          <div class="fg"><label>Emergency Contact Person <span class="req">*</span></label><input type="text" name="emergency_name" required placeholder="Full name"/></div>
          <div class="fg"><label>Emergency Contact Number <span class="req">*</span></label><input type="tel" name="emergency_number" required placeholder="09XX-XXX-XXXX"/></div>
        </div>
        <div id="eligMsg" style="margin-bottom:12px"></div>

        <!-- Check panel -->
        <div id="volCheckPanel" style="display:none"></div>

        <button type="button" id="volCheckBtn" style="width:100%;padding:11px;background:#f1f5f9;color:#1565c0;border:1.5px solid #90caf9;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:10px;transition:.2s" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background='#f1f5f9'">
          <i class="fas fa-search"></i> Check My Submission First
        </button>
        <button type="submit" class="btn-submit" id="regSubmitBtn"><i class="fas fa-paper-plane"></i> Submit Registration</button>
      </form>
    </div>
  </div>
</div>

<script>
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',function(){ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});

let currentProgMin=15,currentProgMax=30;
function openRegModal(id,name){
  document.getElementById('regProgId').value=id;
  document.getElementById('regProgName').textContent='Program: '+name;
  const prog=<?=json_encode(array_column($programs,null,'id'))?>;
  if(prog[id]){currentProgMin=prog[id].min_age;currentProgMax=prog[id].max_age;}
  checkEligibility();
  document.getElementById('regModal').style.display='flex';
}
function calcAge(input){
  const dob=new Date(input.value),today=new Date();
  let age=today.getFullYear()-dob.getFullYear();
  if(today.getMonth()<dob.getMonth()||(today.getMonth()===dob.getMonth()&&today.getDate()<dob.getDate()))age--;
  document.getElementById('regAge').value=age>=0?age:'';
  checkEligibility();
}
function checkEligibility(){
  const age=parseInt(document.getElementById('regAge').value)||0;
  const msg=document.getElementById('eligMsg');
  const btn=document.getElementById('regSubmitBtn');
  if(age>=currentProgMin&&age<=currentProgMax){
    msg.innerHTML='<div style="padding:8px 14px;background:#e8f5e9;color:#2e7d32;border-radius:8px;font-size:.83rem;font-weight:600"><i class="fas fa-check-circle"></i> Age '+age+' is eligible ('+currentProgMin+''+currentProgMax+')</div>';
    btn.disabled=false;
  } else if(age>0){
    msg.innerHTML='<div style="padding:8px 14px;background:#ffebee;color:#c62828;border-radius:8px;font-size:.83rem;font-weight:600"><i class="fas fa-times-circle"></i> Age '+age+' is NOT eligible. Requires '+currentProgMin+''+currentProgMax+'</div>';
    btn.disabled=true;
  } else { msg.innerHTML=''; btn.disabled=false; }
}
document.getElementById('regModal').addEventListener('click',e=>{if(e.target===document.getElementById('regModal'))document.getElementById('regModal').style.display='none'});

// Check My Submission First � Volunteer
// Re-init each time modal opens since fields are inside modal
const origOpenRegModal = openRegModal;
window.openRegModal = function(id, name) {
  origOpenRegModal(id, name);
  setTimeout(() => {
    FormChecker.init({
      formId:      'volRegForm',
      checkBtnId:  'volCheckBtn',
      submitBtnId: 'regSubmitBtn',
      panelId:     'volCheckPanel',
      requiredFields: [
        { id: 'full_name',        label: 'Full Name' },
        { id: 'regAge',           label: 'Age' },
        { id: 'contact_number',   label: 'Contact Number' },
        { id: 'address',          label: 'Address' },
        { id: 'email',            label: 'Email' },
        { id: 'emergency_name',   label: 'Emergency Contact Person' },
        { id: 'emergency_number', label: 'Emergency Contact Number' },
      ],
      requiredFiles: [],
    });
  }, 100);
};
</script>
<script src="form_checker.js"></script>
</body></html>
