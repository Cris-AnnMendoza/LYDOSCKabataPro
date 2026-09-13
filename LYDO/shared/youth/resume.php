
<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

$pdo    = db();
$userId = (int)$_SESSION['user_id'];

// ── Ensure resume_data table exists ──────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS resume_data (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL UNIQUE,
    objective    TEXT DEFAULT NULL,
    extra_skills TEXT DEFAULT NULL,
    languages    VARCHAR(255) DEFAULT NULL,
    references_text TEXT DEFAULT NULL,
    work_experience TEXT DEFAULT NULL,
    achievements TEXT DEFAULT NULL,
    seminars     TEXT DEFAULT NULL,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load user
$uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id=? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

$nc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
$nc->execute([$userId]);
$notifCount = (int)$nc->fetchColumn();

// Load or init resume data
$rdStmt = $pdo->prepare('SELECT * FROM resume_data WHERE user_id=? LIMIT 1');
$rdStmt->execute([$userId]);
$rd = $rdStmt->fetch() ?: [];

$success = ''; $error = '';

// ── Handle save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $objective   = trim($_POST['objective']   ?? '');
    $extraSkills = trim($_POST['extra_skills'] ?? '');
    $languages   = trim($_POST['languages']   ?? '');
    $references  = trim($_POST['references_text'] ?? '');
    $workExp     = trim($_POST['work_experience'] ?? '');
    $achievements= trim($_POST['achievements'] ?? '');
    $seminars    = trim($_POST['seminars']     ?? '');

    if ($rd) {
        $pdo->prepare('UPDATE resume_data SET objective=?,extra_skills=?,languages=?,references_text=?,work_experience=?,achievements=?,seminars=? WHERE user_id=?')
            ->execute([$objective,$extraSkills,$languages,$references,$workExp,$achievements,$seminars,$userId]);
    } else {
        $pdo->prepare('INSERT INTO resume_data (user_id,objective,extra_skills,languages,references_text,work_experience,achievements,seminars) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$userId,$objective,$extraSkills,$languages,$references,$workExp,$achievements,$seminars]);
    }
    // Reload
    $rdStmt->execute([$userId]);
    $rd = $rdStmt->fetch() ?: [];
    $success = 'Resume saved successfully!';
}

// Helpers
$fullName  = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'][0].'. ' : '') . $user['last_name'] . ($user['suffix'] ? ' '.$user['suffix'] : ''));
$address   = trim(implode(', ', array_filter([$user['house_number'].' '.$user['street'], $user['barangay'], $user['municipality'], $user['province']])));
$cls       = $user['youth_classification'] ? json_decode($user['youth_classification'], true) : [];
$prgs      = $user['programs_interested']  ? json_decode($user['programs_interested'],  true) : [];
$skills    = array_filter(array_map('trim', explode(',', ($user['skills'] ?? '') . ',' . ($rd['extra_skills'] ?? ''))));
$age       = $user['age'] ?? (date('Y') - date('Y', strtotime($user['birthdate'] ?? 'now')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Resume Builder – LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
/* ── LAYOUT ── */
.rb-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
.rb-form-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden;position:sticky;top:70px}
.rb-form-header{padding:14px 18px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;display:flex;align-items:center;gap:8px}
.rb-form-header h3{font-size:.95rem;font-weight:700}
.rb-form-body{padding:18px;max-height:calc(100vh - 160px);overflow-y:auto}
.sec-title{font-size:.82rem;font-weight:700;color:#0d3b6e;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 10px;padding-bottom:6px;border-bottom:2px solid #e3f2fd;display:flex;align-items:center;gap:6px}
.sec-title:first-child{margin-top:0}
.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px}
.fg label{font-size:.78rem;font-weight:600;color:#1e293b}
.fg input,.fg select,.fg textarea{padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#1e88e5;background:#fff;box-shadow:0 0 0 3px rgba(30,136,229,.08)}
.fg input[readonly]{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn-save{width:100%;padding:12px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.92rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;box-shadow:0 4px 12px rgba(21,101,192,.3);margin-top:4px}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,101,192,.4)}
.btn-print{padding:10px 20px;background:linear-gradient(135deg,#2e7d32,#43a047);color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.2s;box-shadow:0 3px 10px rgba(46,125,50,.3)}
.btn-print:hover{transform:translateY(-1px)}
.alert{padding:11px 14px;border-radius:9px;font-size:.85rem;font-weight:500;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2)}

/* ── RESUME PREVIEW ── */
.resume-preview{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.resume-preview-header{padding:12px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:10px}
.resume-preview-header span{font-size:.85rem;font-weight:700;color:#475569}

/* ── RESUME PAPER ── */
#resumePaper{padding:36px 40px;font-family:'Inter',sans-serif;font-size:9.5pt;color:#1e293b;line-height:1.5;background:#fff}
.r-name{font-family:'Playfair Display',serif;font-size:22pt;font-weight:900;color:#0d3b6e;letter-spacing:.01em;margin-bottom:2px}
.r-tagline{font-size:9pt;color:#1565c0;font-weight:600;margin-bottom:8px;letter-spacing:.04em;text-transform:uppercase}
.r-contact{display:flex;flex-wrap:wrap;gap:12px;font-size:8.5pt;color:#475569;margin-bottom:14px;padding-bottom:12px;border-bottom:2.5px solid #0d3b6e}
.r-contact span{display:flex;align-items:center;gap:4px}
.r-contact i{color:#1565c0;font-size:8pt}
.r-section{margin-bottom:16px}
.r-section-title{font-size:9pt;font-weight:800;color:#0d3b6e;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;padding-bottom:4px;border-bottom:1.5px solid #e3f2fd;display:flex;align-items:center;gap:6px}
.r-section-title i{color:#1565c0;font-size:9pt}
.r-body{font-size:9pt;color:#1e293b;line-height:1.7}
.r-item{margin-bottom:10px}
.r-item-title{font-weight:700;font-size:9.5pt;color:#0d3b6e}
.r-item-sub{font-size:8.5pt;color:#475569;margin-top:1px}
.r-item-desc{font-size:8.5pt;color:#1e293b;margin-top:3px;line-height:1.6}
.r-skills{display:flex;flex-wrap:wrap;gap:5px}
.r-skill{background:#e3f2fd;color:#1565c0;padding:2px 9px;border-radius:50px;font-size:8pt;font-weight:600}
.r-two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.r-badge{display:inline-block;background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:50px;font-size:7.5pt;font-weight:700;margin:2px}

@media(max-width:900px){.rb-wrap{grid-template-columns:1fr}.rb-form-card{position:static}}
@media print{
  body{background:#fff}
  .y-sidebar,.y-topbar,.rb-form-card,.resume-preview-header,.y-page-header,.alert{display:none !important}
  .rb-wrap{grid-template-columns:1fr;gap:0}
  .resume-preview{border:none;box-shadow:none;border-radius:0}
  #resumePaper{padding:20px 24px}
  @page{size:A4;margin:10mm}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-file-alt" style="color:#1565c0;margin-right:8px"></i>Resume Builder</h2>
  <p>Build your professional resume using your LYDO profile. Edit and add more details below.</p>
</div>

<?php if ($success): ?>
<div class="alert success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="rb-wrap">

  <!-- ── LEFT: EDIT FORM ── -->
  <div class="rb-form-card">
    <div class="rb-form-header"><i class="fas fa-edit"></i><h3>Edit Resume Details</h3></div>
    <div class="rb-form-body">
      <form method="POST" id="resumeForm">
        <input type="hidden" name="save_resume" value="1"/>

        <div class="sec-title"><i class="fas fa-user"></i> Personal Info <small style="font-weight:400;color:#94a3b8;text-transform:none;letter-spacing:0">(from profile)</small></div>
        <div class="form-row-2">
          <div class="fg"><label>Full Name</label><input type="text" value="<?= htmlspecialchars($fullName) ?>" readonly/></div>
          <div class="fg"><label>Email</label><input type="text" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly/></div>
        </div>
        <div class="form-row-2">
          <div class="fg"><label>Contact</label><input type="text" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>" readonly/></div>
          <div class="fg"><label>Address</label><input type="text" value="<?= htmlspecialchars($address) ?>" readonly/></div>
        </div>
        <p style="font-size:.72rem;color:#94a3b8;margin-bottom:12px"><i class="fas fa-info-circle"></i> To update personal info, go to <a href="profile.php" style="color:#1565c0">My Profile</a></p>

        <div class="sec-title"><i class="fas fa-bullseye"></i> Career Objective</div>
        <div class="fg">
          <textarea name="objective" rows="3" placeholder="e.g. A dedicated and passionate youth leader seeking opportunities to contribute to community development and youth empowerment programs..."><?= htmlspecialchars($rd['objective'] ?? '') ?></textarea>
        </div>

        <div class="sec-title"><i class="fas fa-briefcase"></i> Work Experience</div>
        <div class="fg">
          <textarea name="work_experience" rows="4" placeholder="Job Title | Company/Organization | Year&#10;- Responsibility 1&#10;- Responsibility 2&#10;&#10;Job Title | Company | Year&#10;- Responsibility"><?= htmlspecialchars($rd['work_experience'] ?? '') ?></textarea>
          <span style="font-size:.72rem;color:#94a3b8">Format: Job Title | Company | Year, then bullet points</span>
        </div>

        <div class="sec-title"><i class="fas fa-graduation-cap"></i> Education <small style="font-weight:400;color:#94a3b8;text-transform:none;letter-spacing:0">(from profile)</small></div>
        <div class="fg"><label>Educational Status</label><input type="text" value="<?= htmlspecialchars($user['educational_status'] ?? '') ?>" readonly/></div>
        <div class="form-row-2">
          <div class="fg"><label>School</label><input type="text" value="<?= htmlspecialchars($user['school_name'] ?? '') ?>" readonly/></div>
          <div class="fg"><label>Course / Grade</label><input type="text" value="<?= htmlspecialchars($user['course_or_grade'] ?? '') ?>" readonly/></div>
        </div>

        <div class="sec-title"><i class="fas fa-star"></i> Skills</div>
        <div class="fg"><label>From Profile</label><input type="text" value="<?= htmlspecialchars($user['skills'] ?? '') ?>" readonly/></div>
        <div class="fg"><label>Additional Skills <small style="font-weight:400;color:#94a3b8">(comma separated)</small></label>
          <input type="text" name="extra_skills" placeholder="e.g. Graphic Design, Public Speaking, MS Office" value="<?= htmlspecialchars($rd['extra_skills'] ?? '') ?>"/>
        </div>

        <div class="sec-title"><i class="fas fa-trophy"></i> Achievements & Awards</div>
        <div class="fg">
          <textarea name="achievements" rows="3" placeholder="e.g. Best Youth Leader Award 2025 – LYDO Sta. Cruz&#10;Regional Youth Summit Delegate 2024&#10;SK Barangay Secretary 2023-2025"><?= htmlspecialchars($rd['achievements'] ?? '') ?></textarea>
        </div>

        <div class="sec-title"><i class="fas fa-chalkboard-teacher"></i> Seminars & Trainings</div>
        <div class="fg">
          <textarea name="seminars" rows="3" placeholder="e.g. Youth Leadership Training | LYDO | June 2025&#10;Digital Skills Workshop | DICT | March 2025"><?= htmlspecialchars($rd['seminars'] ?? '') ?></textarea>
        </div>

        <div class="sec-title"><i class="fas fa-sitemap"></i> Organization <small style="font-weight:400;color:#94a3b8;text-transform:none;letter-spacing:0">(from profile)</small></div>
        <div class="form-row-2">
          <div class="fg"><label>Organization</label><input type="text" value="<?= htmlspecialchars($user['organization_name'] ?? '') ?>" readonly/></div>
          <div class="fg"><label>Role</label><input type="text" value="<?= htmlspecialchars($user['organization_role'] ?? '') ?>" readonly/></div>
        </div>

        <div class="sec-title"><i class="fas fa-language"></i> Languages</div>
        <div class="fg">
          <input type="text" name="languages" placeholder="e.g. Filipino, English, Tagalog" value="<?= htmlspecialchars($rd['languages'] ?? 'Filipino, English') ?>"/>
        </div>

        <div class="sec-title"><i class="fas fa-address-book"></i> Character References</div>
        <div class="fg">
          <textarea name="references_text" rows="3" placeholder="Name | Position | Contact&#10;e.g. Juan dela Cruz | LYDO Coordinator | 09XX-XXX-XXXX"><?= htmlspecialchars($rd['references_text'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Resume</button>
      </form>
    </div>
  </div>

  <!-- ── RIGHT: RESUME PREVIEW ── -->
  <div>
    <div class="resume-preview">
      <div class="resume-preview-header">
        <span><i class="fas fa-eye" style="margin-right:5px;color:#1565c0"></i>Live Preview</span>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
      </div>

      <div id="resumePaper">

        <!-- Header -->
        <div class="r-name"><?= htmlspecialchars(strtoupper($fullName)) ?></div>
        <div class="r-tagline">
          <?= htmlspecialchars(implode(' · ', array_filter([
            $user['employment_status'] ?? '',
            $user['educational_status'] ?? '',
            $user['organization_role'] ? $user['organization_role'] . ' – ' . ($user['organization_name'] ?? '') : ''
          ]))) ?>
        </div>
        <div class="r-contact">
          <?php if ($user['contact_number']): ?><span><i class="fas fa-phone"></i><?= htmlspecialchars($user['contact_number']) ?></span><?php endif; ?>
          <?php if ($user['email']): ?><span><i class="fas fa-envelope"></i><?= htmlspecialchars($user['email']) ?></span><?php endif; ?>
          <?php if ($address): ?><span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($address) ?></span><?php endif; ?>
          <?php if ($user['birthdate']): ?><span><i class="fas fa-birthday-cake"></i><?= date('F j, Y', strtotime($user['birthdate'])) ?> (<?= $age ?> yrs)</span><?php endif; ?>
          <?php if ($user['civil_status']): ?><span><i class="fas fa-heart"></i><?= htmlspecialchars($user['civil_status']) ?></span><?php endif; ?>
          <?php if ($user['gender']): ?><span><i class="fas fa-user"></i><?= htmlspecialchars($user['gender']) ?></span><?php endif; ?>
        </div>

        <!-- Objective -->
        <?php if (!empty($rd['objective'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-bullseye"></i> Career Objective</div>
          <div class="r-body"><?= nl2br(htmlspecialchars($rd['objective'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Work Experience -->
        <?php if (!empty($rd['work_experience'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-briefcase"></i> Work Experience</div>
          <?php
          $jobs = array_filter(explode("\n\n", trim($rd['work_experience'])));
          foreach ($jobs as $job):
            $lines = array_filter(explode("\n", trim($job)));
            $header = array_shift($lines);
            $parts  = array_map('trim', explode('|', $header));
          ?>
          <div class="r-item">
            <div class="r-item-title"><?= htmlspecialchars($parts[0] ?? '') ?></div>
            <div class="r-item-sub"><?= htmlspecialchars(implode(' · ', array_filter(array_slice($parts, 1)))) ?></div>
            <?php if ($lines): ?>
            <div class="r-item-desc">
              <?php foreach ($lines as $l): ?>
                <?= htmlspecialchars(trim($l)) ?><br>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Education -->
        <?php if ($user['educational_status'] || $user['school_name']): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-graduation-cap"></i> Education</div>
          <div class="r-item">
            <div class="r-item-title"><?= htmlspecialchars($user['educational_status'] ?? '') ?></div>
            <?php if ($user['school_name']): ?>
            <div class="r-item-sub"><?= htmlspecialchars($user['school_name']) ?><?= $user['course_or_grade'] ? ' · ' . htmlspecialchars($user['course_or_grade']) : '' ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Skills -->
        <?php if (!empty($skills)): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-star"></i> Skills & Competencies</div>
          <div class="r-skills">
            <?php foreach ($skills as $s): ?>
              <span class="r-skill"><?= htmlspecialchars($s) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Achievements -->
        <?php if (!empty($rd['achievements'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-trophy"></i> Achievements & Awards</div>
          <div class="r-body">
            <?php foreach (array_filter(explode("\n", $rd['achievements'])) as $a): ?>
              <div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:3px"><span style="color:#1565c0;margin-top:2px">▸</span><?= htmlspecialchars(trim($a)) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Seminars -->
        <?php if (!empty($rd['seminars'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-chalkboard-teacher"></i> Seminars & Trainings</div>
          <?php
          $sems = array_filter(explode("\n", trim($rd['seminars'])));
          foreach ($sems as $sem):
            $sp = array_map('trim', explode('|', $sem));
          ?>
          <div class="r-item" style="margin-bottom:5px">
            <div class="r-item-title" style="font-size:9pt"><?= htmlspecialchars($sp[0] ?? '') ?></div>
            <?php if (count($sp) > 1): ?>
            <div class="r-item-sub"><?= htmlspecialchars(implode(' · ', array_slice($sp, 1))) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Organization -->
        <?php if ($user['organization_name']): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-sitemap"></i> Organization Affiliation</div>
          <div class="r-item">
            <div class="r-item-title"><?= htmlspecialchars($user['organization_name']) ?></div>
            <div class="r-item-sub">
              <?= htmlspecialchars(implode(' · ', array_filter([$user['organization_type'] ?? '', $user['organization_role'] ?? '', $user['years_membership'] ? $user['years_membership'].' yr(s) membership' : '']))) ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Youth Classification -->
        <?php if (!empty($cls)): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-id-card"></i> Youth Classification</div>
          <div><?php foreach ((array)$cls as $c): ?><span class="r-badge"><?= htmlspecialchars($c) ?></span><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <!-- Languages -->
        <?php if (!empty($rd['languages'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-language"></i> Languages</div>
          <div class="r-skills">
            <?php foreach (array_filter(array_map('trim', explode(',', $rd['languages']))) as $lang): ?>
              <span class="r-skill"><?= htmlspecialchars($lang) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- References -->
        <?php if (!empty($rd['references_text'])): ?>
        <div class="r-section">
          <div class="r-section-title"><i class="fas fa-address-book"></i> Character References</div>
          <?php
          $refs = array_filter(explode("\n", trim($rd['references_text'])));
          foreach ($refs as $ref):
            $rp = array_map('trim', explode('|', $ref));
          ?>
          <div class="r-item" style="margin-bottom:5px">
            <div class="r-item-title" style="font-size:9pt"><?= htmlspecialchars($rp[0] ?? '') ?></div>
            <?php if (count($rp) > 1): ?>
            <div class="r-item-sub"><?= htmlspecialchars(implode(' · ', array_slice($rp, 1))) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="margin-top:20px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:7.5pt;color:#94a3b8;text-align:center">
          Generated via LYDO Youth Portal · Local Youth Development Office · Sta. Cruz, Laguna · <?= date('F j, Y') ?>
        </div>

      </div><!-- end resumePaper -->
    </div><!-- end resume-preview -->
  </div>

</div><!-- end rb-wrap -->

</main>
</div>

<script>
// Live preview update on form change
document.getElementById('resumeForm').addEventListener('input', function() {
  clearTimeout(window._previewTimer);
  window._previewTimer = setTimeout(updatePreview, 600);
});

function updatePreview() {
  const objective = document.querySelector('[name=objective]').value;
  const workExp   = document.querySelector('[name=work_experience]').value;
  const extraSkills = document.querySelector('[name=extra_skills]').value;
  const achievements = document.querySelector('[name=achievements]').value;
  const seminars  = document.querySelector('[name=seminars]').value;
  const languages = document.querySelector('[name=languages]').value;
  const refs      = document.querySelector('[name=references_text]').value;

  // Update objective
  const objSec = document.querySelector('.r-section');
  // Simple live updates for text areas
  updateSection('objective-preview', objective);
  updateSection('workexp-preview', workExp);
  updateSection('achievements-preview', achievements);
  updateSection('seminars-preview', seminars);
}

function updateSection(id, val) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = val.replace(/\n/g, '<br>');
}

// Sidebar toggle
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',()=>{ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',()=>{ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',()=>{ySb.classList.remove('open');yOv.classList.remove('open');});
</script>
</body>
</html>
