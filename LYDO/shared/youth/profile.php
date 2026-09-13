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

$success = ""; $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "update_personal") {
        $pdo->prepare("UPDATE youth_users SET contact_number=?,civil_status=?,gender=?,barangay=?,municipality=?,province=?,zip_code=?,house_number=?,street=?,updated_at=NOW() WHERE id=?")
            ->execute([trim($_POST["contact_number"]??""),trim($_POST["civil_status"]??""),trim($_POST["gender"]??""),trim($_POST["barangay"]??""),trim($_POST["municipality"]??""),trim($_POST["province"]??""),trim($_POST["zip_code"]??""),trim($_POST["house_number"]??""),trim($_POST["street"]??""),$userId]);
        $success = "Personal information updated successfully.";
    }

    if ($action === "update_education") {
        $pdo->prepare("UPDATE youth_users SET educational_status=?,school_name=?,course_or_grade=?,employment_status=?,organization_name=?,organization_type=?,organization_role=?,years_membership=?,updated_at=NOW() WHERE id=?")
            ->execute([trim($_POST["educational_status"]??""),trim($_POST["school_name"]??""),trim($_POST["course_or_grade"]??""),trim($_POST["employment_status"]??""),trim($_POST["organization_name"]??""),trim($_POST["organization_type"]??""),trim($_POST["organization_role"]??""),(int)($_POST["years_membership"]??0),$userId]);
        $success = "Education & employment information updated.";
    }

    if ($action === "update_skills") {
        $cls = isset($_POST["youth_classification"]) ? json_encode(array_values($_POST["youth_classification"])) : null;
        $prg = isset($_POST["programs_interested"]) ? json_encode(array_values($_POST["programs_interested"])) : null;
        $pdo->prepare("UPDATE youth_users SET skills=?,interests=?,volunteer_availability=?,youth_classification=?,programs_interested=?,updated_at=NOW() WHERE id=?")
            ->execute([trim($_POST["skills"]??""),trim($_POST["interests"]??""),trim($_POST["volunteer_availability"]??""),$cls,$prg,$userId]);
        $success = "Skills & interests updated.";
    }

    if ($action === "change_password") {
        $current = $_POST["current_password"] ?? "";
        $new     = $_POST["new_password"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";
        if (!password_verify($current, $user["password"])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $pdo->prepare("UPDATE youth_users SET password=?,updated_at=NOW() WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT, ["cost"=>12]),$userId]);
            $success = "Password changed successfully.";
        }
    }

    // Reload user after update
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();
}

$cls  = $user["youth_classification"] ? json_decode($user["youth_classification"], true) : [];
$prgs = $user["programs_interested"]  ? json_decode($user["programs_interested"],  true) : [];
$classifications = ["In School Youth","Out of School Youth","Working Youth","Youth with Disability","Indigenous Youth","Children in Conflict with Law","LGBTQ+ Youth","Other"];
$programsList    = ["Education & Scholarship","Livelihood & Skills","Health & Wellness","Leadership Development","Arts & Culture","Environment & Community"];
$tab = $_GET["tab"] ?? "personal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Profile  LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.tabs{display:flex;gap:4px;margin-bottom:22px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.tab-btn.active{background:#fff;color:#1565c0;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.profile-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px}
.profile-card-header{padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff}
.profile-card-header h3{font-size:.92rem;font-weight:700}
.profile-card-body{padding:22px}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fg label{font-size:.82rem;font-weight:600;color:#1e293b}
.fg input,.fg select,.fg textarea{padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.9rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.fg input[readonly]{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.btn-save{padding:11px 24px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s;box-shadow:0 3px 10px rgba(21,101,192,.3)}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(21,101,192,.4)}
.sec-title{font-size:.85rem;font-weight:700;color:#0d3b6e;margin:16px 0 12px;padding-bottom:7px;border-bottom:2px solid #e3f2fd;display:flex;align-items:center;gap:7px}
.sec-title:first-child{margin-top:0}
.chk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px}
.chk-item{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;transition:.2s;font-size:.85rem;font-weight:500;color:#475569}
.chk-item:hover{border-color:#1565c0;background:#e3f2fd;color:#1565c0}
.chk-item input{display:none}
.chk-item.checked{border-color:#1565c0;background:#e3f2fd;color:#1565c0;font-weight:600}
.avatar-wrap{display:flex;align-items:center;gap:20px;margin-bottom:22px;padding:18px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0}
.avatar-big{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#43a047);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.8rem;font-weight:800;flex-shrink:0}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.9rem;padding:4px}
.pw-toggle:hover{color:#1565c0}
@media(max-width:600px){.form-row-2,.form-row-3{grid-template-columns:1fr}.chk-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-user-edit" style="color:#1565c0;margin-right:8px"></i>My Profile</h2>
  <p>Update your personal information, education, skills, and account settings.</p>
</div>

<?php if ($success): ?><div style="padding:12px 16px;border-radius:10px;background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-check-circle"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if ($error): ?><div style="padding:12px 16px;border-radius:10px;background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2);font-size:.88rem;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div><?php endif; ?>

<!-- Avatar + name -->
<div class="avatar-wrap">
  <div class="avatar-big"><?=strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1))?></div>
  <div>
    <div style="font-size:1.2rem;font-weight:800;color:#1e293b"><?=htmlspecialchars($user['first_name'].' '.$user['last_name'])?></div>
    <div style="font-size:.85rem;color:#475569;margin-top:3px"><?=htmlspecialchars($user['email'])?></div>
    <div style="font-size:.78rem;color:#94a3b8;margin-top:2px">Member since <?=date('F Y',strtotime($user['created_at']))?></div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <a href="?tab=personal"  class="tab-btn <?=$tab==='personal'?'active':''?>"><i class="fas fa-user"></i> Personal</a>
  <a href="?tab=address"   class="tab-btn <?=$tab==='address'?'active':''?>"><i class="fas fa-map-marker-alt"></i> Address</a>
  <a href="?tab=education" class="tab-btn <?=$tab==='education'?'active':''?>"><i class="fas fa-graduation-cap"></i> Education</a>
  <a href="?tab=skills"    class="tab-btn <?=$tab==='skills'?'active':''?>"><i class="fas fa-star"></i> Skills & Interests</a>
  <a href="?tab=password"  class="tab-btn <?=$tab==='password'?'active':''?>"><i class="fas fa-lock"></i> Password</a>
</div>

<?php if ($tab === 'personal'): ?>
<div class="profile-card">
  <div class="profile-card-header"><i class="fas fa-user"></i><h3>Personal Information</h3></div>
  <div class="profile-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="update_personal"/>
      <div class="form-row-3">
        <div class="fg"><label>First Name</label><input type="text" value="<?=htmlspecialchars($user['first_name']??'')?>" readonly/></div>
        <div class="fg"><label>Middle Name</label><input type="text" value="<?=htmlspecialchars($user['middle_name']??'')?>" readonly/></div>
        <div class="fg"><label>Last Name</label><input type="text" value="<?=htmlspecialchars($user['last_name']??'')?>" readonly/></div>
      </div>
      <p style="font-size:.75rem;color:#94a3b8;margin:-8px 0 14px"><i class="fas fa-info-circle"></i> Name changes require admin approval. Contact the LYDO office.</p>
      <div class="form-row-2">
        <div class="fg"><label>Gender</label>
          <select name="gender">
            <?php foreach(['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
            <option value="<?=$g?>" <?=($user['gender']??'')===$g?'selected':''?>><?=$g?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label>Civil Status</label>
          <select name="civil_status">
            <?php foreach(['Single','Married','Widowed','Separated'] as $s): ?>
            <option value="<?=$s?>" <?=($user['civil_status']??'')===$s?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Contact Number</label><input type="tel" name="contact_number" value="<?=htmlspecialchars($user['contact_number']??'')?>" placeholder="09XX-XXX-XXXX"/></div>
        <div class="fg"><label>Email Address</label><input type="email" value="<?=htmlspecialchars($user['email']??'')?>" readonly/></div>
      </div>
      <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'address'): ?>
<div class="profile-card">
  <div class="profile-card-header"><i class="fas fa-map-marker-alt"></i><h3>Address Information</h3></div>
  <div class="profile-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="update_personal"/>
      <div class="form-row-2">
        <div class="fg"><label>House Number</label><input type="text" name="house_number" value="<?=htmlspecialchars($user['house_number']??'')?>" placeholder="e.g. 123"/></div>
        <div class="fg"><label>Street</label><input type="text" name="street" value="<?=htmlspecialchars($user['street']??'')?>" placeholder="e.g. Rizal St."/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Barangay</label>
          <select name="barangay">
            <option value="">Select barangay</option>
            <?php foreach(['Barangay 1 - Poblacion','Barangay 2 - Poblacion','Barangay 3 - Poblacion','Barangay 4 - Poblacion','Barangay 5 - Poblacion','Barangay 6 - Poblacion','Barangay 7 - Poblacion','Barangay 8 - Poblacion','Bubukal','Calios','Duhat','Gatid','Jasaan','Labuin','Luciano','Malinao','Palayan','Pook','Pulong Bayabas','Saguimsim','Sampaloc','San Antonio','San Juan','Sirang Lupa','Tabuco','Talaga','Sto. Angel Norte','Sto. Angel Sur'] as $b): ?>
            <option value="<?=$b?>" <?=($user['barangay']??'')===$b?'selected':''?>><?=$b?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label>Municipality</label><input type="text" name="municipality" value="<?=htmlspecialchars($user['municipality']??'Sta. Cruz')?>"/></div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Province</label><input type="text" name="province" value="<?=htmlspecialchars($user['province']??'Laguna')?>"/></div>
        <div class="fg"><label>ZIP Code</label><input type="text" name="zip_code" value="<?=htmlspecialchars($user['zip_code']??'4009')?>"/></div>
      </div>
      <!-- Keep other fields unchanged -->
      <input type="hidden" name="contact_number" value="<?=htmlspecialchars($user['contact_number']??'')?>"/>
      <input type="hidden" name="civil_status" value="<?=htmlspecialchars($user['civil_status']??'')?>"/>
      <input type="hidden" name="gender" value="<?=htmlspecialchars($user['gender']??'')?>"/>
      <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Address</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'education'): ?>
<div class="profile-card">
  <div class="profile-card-header"><i class="fas fa-graduation-cap"></i><h3>Education & Employment</h3></div>
  <div class="profile-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="update_education"/>
      <div class="sec-title"><i class="fas fa-book"></i> Educational Information</div>
      <div class="form-row-2">
        <div class="fg"><label>Educational Status</label>
          <select name="educational_status">
            <option value="">Select status</option>
            <?php foreach(['Elementary Level','Elementary Graduate','High School Level','High School Graduate','Senior High School Level','Senior High School Graduate','College Level','College Graduate','Vocational / Technical','Post Graduate','Not Attending School'] as $e): ?>
            <option value="<?=$e?>" <?=($user['educational_status']??'')===$e?'selected':''?>><?=$e?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label>School Name</label><input type="text" name="school_name" value="<?=htmlspecialchars($user['school_name']??'')?>" placeholder="e.g. Sta. Cruz National High School"/></div>
      </div>
      <div class="fg"><label>Course / Grade Level</label><input type="text" name="course_or_grade" value="<?=htmlspecialchars($user['course_or_grade']??'')?>" placeholder="e.g. Grade 12 / BS Computer Science"/></div>
      <div class="sec-title"><i class="fas fa-briefcase"></i> Employment</div>
      <div class="fg"><label>Employment Status</label>
        <select name="employment_status">
          <option value="">Select status</option>
          <?php foreach(['Employed (Full-time)','Employed (Part-time)','Self-employed','Unemployed','Student','Freelancer'] as $e): ?>
          <option value="<?=$e?>" <?=($user['employment_status']??'')===$e?'selected':''?>><?=$e?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sec-title"><i class="fas fa-users"></i> Organization</div>
      <div class="form-row-2">
        <div class="fg"><label>Organization Name</label><input type="text" name="organization_name" value="<?=htmlspecialchars($user['organization_name']??'')?>" placeholder="e.g. SK Barangay 1"/></div>
        <div class="fg"><label>Organization Type</label>
          <select name="organization_type">
            <option value="">Select type</option>
            <?php foreach(['Sangguniang Kabataan','Youth NGO','Religious Organization','Sports Club','Academic Organization','Community Group','Other'] as $t): ?>
            <option value="<?=$t?>" <?=($user['organization_type']??'')===$t?'selected':''?>><?=$t?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="fg"><label>Position / Role</label><input type="text" name="organization_role" value="<?=htmlspecialchars($user['organization_role']??'')?>" placeholder="e.g. Secretary"/></div>
        <div class="fg"><label>Years of Membership</label><input type="number" name="years_membership" value="<?=htmlspecialchars($user['years_membership']??0)?>" min="0" max="20"/></div>
      </div>
      <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'skills'): ?>
<div class="profile-card">
  <div class="profile-card-header"><i class="fas fa-star"></i><h3>Skills, Interests & Classification</h3></div>
  <div class="profile-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="update_skills"/>
      <div class="fg"><label>Skills / Talents</label><input type="text" name="skills" value="<?=htmlspecialchars($user['skills']??'')?>" placeholder="e.g. Public Speaking, Graphic Design, Music"/></div>
      <div class="fg"><label>Interests / Hobbies</label><input type="text" name="interests" value="<?=htmlspecialchars($user['interests']??'')?>" placeholder="e.g. Reading, Sports, Volunteering"/></div>
      <div class="fg"><label>Volunteer Availability</label>
        <select name="volunteer_availability">
          <option value="">Select availability</option>
          <?php foreach(['Weekdays','Weekends','Both Weekdays & Weekends','Not Available'] as $v): ?>
          <option value="<?=$v?>" <?=($user['volunteer_availability']??'')===$v?'selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sec-title"><i class="fas fa-id-card"></i> Youth Classification</div>
      <div class="chk-grid">
        <?php foreach($classifications as $c): $checked=in_array($c,(array)$cls); ?>
        <label class="chk-item <?=$checked?'checked':''?>" onclick="this.classList.toggle('checked')">
          <input type="checkbox" name="youth_classification[]" value="<?=$c?>" <?=$checked?'checked':''?>/><?=$c?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="sec-title" style="margin-top:18px"><i class="fas fa-graduation-cap"></i> Programs Interested In</div>
      <div class="chk-grid">
        <?php foreach($programsList as $p): $checked=in_array($p,(array)$prgs); ?>
        <label class="chk-item <?=$checked?'checked':''?>" onclick="this.classList.toggle('checked')">
          <input type="checkbox" name="programs_interested[]" value="<?=$p?>" <?=$checked?'checked':''?>/><?=$p?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:18px"><button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button></div>
    </form>
  </div>
</div>

<?php elseif ($tab === 'password'): ?>
<div class="profile-card">
  <div class="profile-card-header"><i class="fas fa-lock"></i><h3>Change Password</h3></div>
  <div class="profile-card-body">
    <form method="POST">
      <input type="hidden" name="action" value="change_password"/>
      <div class="fg"><label>Current Password</label>
        <div class="pw-wrap"><input type="password" id="pw0" name="current_password" placeholder="Enter current password" required/><button type="button" class="pw-toggle" onclick="togglePw('pw0',this)"><i class="fas fa-eye"></i></button></div>
      </div>
      <div class="fg"><label>New Password</label>
        <div class="pw-wrap"><input type="password" id="pw1" name="new_password" placeholder="Min. 8 characters" required/><button type="button" class="pw-toggle" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button></div>
      </div>
      <div class="fg"><label>Confirm New Password</label>
        <div class="pw-wrap"><input type="password" id="pw2" name="confirm_password" placeholder="Repeat new password" required/><button type="button" class="pw-toggle" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button></div>
      </div>
      <button type="submit" class="btn-save"><i class="fas fa-key"></i> Change Password</button>
    </form>
  </div>
</div>
<?php endif; ?>

</main>
</div>
<script>
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',function(){ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
function togglePw(id,btn){
  const inp=document.getElementById(id);
  const ico=btn.querySelector('i');
  if(inp.type==='password'){inp.type='text';ico.className='fas fa-eye-slash';}
  else{inp.type='password';ico.className='fas fa-eye';}
}
</script>
</body></html>
