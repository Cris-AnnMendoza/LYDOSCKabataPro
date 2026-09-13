<?php
session_start();
require_once __DIR__ . '/../shared/config.php';

// Check if logged in as organization president
if (empty($_SESSION['org_president_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$president = $_SESSION['org_president'];
$orgId = $president['organization_id'];

// Get organization info
$orgStmt = $pdo->prepare("SELECT * FROM organizations WHERE id = ? LIMIT 1");
$orgStmt->execute([$orgId]);
$org = $orgStmt->fetch();

if (!$org) {
    die('No organization found for your account.');
}

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_application') {
        $orgName = trim($_POST['organization_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');

        if (!$orgName || !$category || !$contactPerson || !$contactEmail || !$contactPhone) {
            $_SESSION['flash_error'] = 'All required fields must be filled.';
            header('Location: accreditation.php');
            exit;
        }

        // Add columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN IF NOT EXISTS contact_person VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE organizations ADD COLUMN IF NOT EXISTS contact_email VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE organizations ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(50) DEFAULT NULL");
            
            // Make submitted_by nullable for president submissions
            $pdo->exec("ALTER TABLE accreditation_applications MODIFY COLUMN submitted_by INT UNSIGNED DEFAULT NULL");
        } catch (PDOException $e) {
            // Columns might already exist, continue
        }

        // Update organization info
        $pdo->prepare('UPDATE organizations SET name=?, category=?, barangay=?, contact_person=?, contact_email=?, contact_phone=? WHERE id=?')
            ->execute([$orgName, $category, $barangay, $contactPerson, $contactEmail, $contactPhone, $orgId]);

        // Create accreditation application (check if exists first)
        $checkApp = $pdo->prepare('SELECT id FROM accreditation_applications WHERE organization_id = ? ORDER BY created_at DESC LIMIT 1');
        $checkApp->execute([$orgId]);
        $existingApp = $checkApp->fetch();

        if ($existingApp) {
            $appId = $existingApp['id'];
            // Update existing application
            $pdo->prepare('UPDATE accreditation_applications SET organization_name=?, category=?, barangay=?, contact_person=?, contact_email=?, contact_phone=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$orgName, $category, $barangay, $contactPerson, $contactEmail, $contactPhone, $appId]);
        } else {
            // Create new application
            $pdo->prepare('INSERT INTO accreditation_applications (organization_id, organization_name, category, barangay, contact_person, contact_email, contact_phone, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, "submitted", CURRENT_TIMESTAMP)')
                ->execute([$orgId, $orgName, $category, $barangay, $contactPerson, $contactEmail, $contactPhone]);
            
            $appId = (int)$pdo->lastInsertId();

            // Create workflow steps
            $steps = ['Document Verification', 'Background Check', 'Field Inspection', 'Board Review', 'Final Approval'];
            foreach ($steps as $step) {
                $pdo->prepare('INSERT INTO accreditation_workflow (application_id, step, status) VALUES (?, ?, "pending")')
                    ->execute([$appId, $step]);
            }
        }

        // Handle document uploads
        $docTypes = ['letter_of_intent', 'nyc_form', 'officers_list', 'constitution', 'lydo_form'];
        $uploadDir = __DIR__ . '/../shared/uploads/accreditation/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Ensure accreditation_documents table has correct columns
        try {
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS organization_id INT UNSIGNED DEFAULT NULL");
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS doc_type VARCHAR(50) DEFAULT NULL");
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS file_size INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE accreditation_documents ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'");
        } catch (PDOException $e) {
            // Columns might already exist
        }

        foreach ($docTypes as $docType) {
            if (isset($_FILES[$docType]) && $_FILES[$docType]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$docType];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $docType . '_' . $orgId . '_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Insert or update if duplicate
                    $pdo->prepare('INSERT INTO accreditation_documents (organization_id, doc_type, file_path, original_name, file_size, status, uploaded_at) VALUES (?, ?, ?, ?, ?, "pending", CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), original_name=VALUES(original_name), file_size=VALUES(file_size), status="pending", uploaded_at=CURRENT_TIMESTAMP')
                        ->execute([$orgId, $docType, $filename, $file['name'], $file['size']]);
                }
            }
        }

        $_SESSION['flash_success'] = 'Accreditation application submitted successfully! Application #' . $appId;
        header('Location: accreditation.php');
        exit;
    }
}

// ── Get organization's applications ───────────────────────────────
$myAppsStmt = $pdo->prepare('SELECT * FROM accreditation_applications WHERE organization_id = ? ORDER BY created_at DESC');
$myAppsStmt->execute([$orgId]);
$myApplications = $myAppsStmt->fetchAll();

$statusColors = [
    'submitted'    => ['#1565c0', '#e3f2fd'],
    'under_review' => ['#f57f17', '#fff8e1'],
    'approved'     => ['#2e7d32', '#e8f5e9'],
    'rejected'     => ['#c62828', '#ffebee'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Accreditation – President Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="president.css"/>
<style>
.status-badge{display:inline-block;padding:4px 12px;border-radius:50px;font-size:.72rem;font-weight:700}
.app-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:12px;transition:.2s}
.app-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
.app-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:10px}
.app-title{font-size:1rem;font-weight:700;color:#1e293b;word-break:break-word}
.app-meta{display:flex;gap:12px;font-size:.8rem;color:#64748b;flex-wrap:wrap}
.form-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px}
.form-section h3{margin:0 0 16px;font-size:1.1rem;color:#0d3b6e}
.fg{display:flex;flex-direction:column;gap:6px;margin-bottom:0}
.fg label{font-size:.85rem;font-weight:600;color:#475569}
.fg input,.fg select,.fg textarea{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.9rem;outline:none;transition:.2s;width:100%}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#1565c0;background:#f8fafc}
.fg-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-bottom:16px}
.btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(21,101,192,.3)}
.file-upload{position:relative;cursor:pointer}
.file-upload input[type="file"]{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
.file-upload-label{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px dashed #cbd5e1;border-radius:8px;background:#f8fafc;font-size:.85rem;color:#475569;transition:.2s;cursor:pointer}
.file-upload:hover .file-upload-label{border-color:#1565c0;background:#e3f2fd}
.file-name{font-size:.75rem;color:#1565c0;margin-top:4px;word-break:break-all}
.page-header{margin-bottom:24px}
.flash{padding:14px 18px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:.9rem}
.flash.success{background:#e8f5e9;color:#2e7d32;border:1px solid #2e7d32}
.flash.error{background:#ffebee;color:#c62828;border:1px solid #c62828}
.flash i{font-size:1.1rem}

@media(max-width:768px){
  .fg-2{grid-template-columns:1fr}
  .form-section{padding:16px 18px}
  .app-header{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php if (isset($_SESSION['flash_success'])): ?>
  <div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']) ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_error'])): ?>
  <div class="flash error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <h1 style="font-size:1.6rem;font-weight:800;color:#0d3b6e;margin-bottom:6px">
      <i class="fas fa-certificate" style="color:#1565c0;margin-right:8px"></i>Organization Accreditation
    </h1>
    <p style="font-size:.9rem;color:#64748b">Apply for official LYDO accreditation for your youth organization</p>
  </div>

  <!-- My Applications -->
  <?php if (!empty($myApplications)): ?>
  <div style="margin-bottom:24px">
    <h2 style="font-size:1.1rem;font-weight:700;color:#0d3b6e;margin-bottom:12px">My Applications</h2>
    <?php foreach ($myApplications as $app):
      [$sc, $bg] = $statusColors[$app['status']] ?? ['#475569', '#f1f5f9'];
      
      // Get document count
      $docStmt = $pdo->prepare('SELECT COUNT(*) FROM accreditation_documents WHERE organization_id=?');
      $docStmt->execute([$app['organization_id']]);
      $docCount = (int)$docStmt->fetchColumn();
    ?>
    <div class="app-card">
      <div class="app-header">
        <div class="app-title"><?= htmlspecialchars($app['organization_name']) ?></div>
        <span class="status-badge" style="background:<?= $bg ?>;color:<?= $sc ?>"><?= ucwords(str_replace('_', ' ', $app['status'])) ?></span>
      </div>
      <div class="app-meta">
        <span><i class="fas fa-hashtag"></i> Application #<?= $app['id'] ?></span>
        <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($app['created_at'])) ?></span>
        <span><i class="fas fa-folder"></i> <?= $app['category'] ?></span>
        <span><i class="fas fa-paperclip"></i> <?= $docCount ?> documents</span>
      </div>
      <?php if ($app['certificate_no']): ?>
      <div style="margin-top:10px;padding:10px;background:#e8f5e9;border-radius:8px;font-size:.85rem;color:#2e7d32">
        <strong>Certificate No:</strong> <?= htmlspecialchars($app['certificate_no']) ?> · 
        <strong>Valid Until:</strong> <?= date('F j, Y', strtotime($app['valid_until'])) ?>
      </div>
      <?php endif; ?>
      <?php if ($app['rejection_reason']): ?>
      <div style="margin-top:10px;padding:10px;background:#ffebee;border-radius:8px;font-size:.85rem;color:#c62828">
        <strong>Rejection Reason:</strong> <?= htmlspecialchars($app['rejection_reason']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Application Form -->
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="submit_application"/>

    <div class="form-section">
      <h3><i class="fas fa-building"></i> Organization Information</h3>
      <div class="fg-2">
        <div class="fg">
          <label>Organization Name <span style="color:#c62828">*</span></label>
          <input type="text" name="organization_name" value="<?= htmlspecialchars($org['name']) ?>" required placeholder="e.g. Santa Cruz Youth Council"/>
        </div>
        <div class="fg">
          <label>Category <span style="color:#c62828">*</span></label>
          <select name="category" required>
            <option value="">Select category...</option>
            <option value="Barangay-Based" <?= ($org['category'] ?? '') === 'Barangay-Based' ? 'selected' : '' ?>>Barangay-Based</option>
            <option value="School-Based" <?= ($org['category'] ?? '') === 'School-Based' ? 'selected' : '' ?>>School-Based</option>
            <option value="Church-Based" <?= ($org['category'] ?? '') === 'Church-Based' ? 'selected' : '' ?>>Church-Based</option>
            <option value="Special Interest" <?= ($org['category'] ?? '') === 'Special Interest' ? 'selected' : '' ?>>Special Interest</option>
          </select>
        </div>
      </div>
      <div class="fg-2">
        <div class="fg">
          <label>Barangay</label>
          <input type="text" name="barangay" value="<?= htmlspecialchars($org['barangay'] ?? '') ?>" placeholder="e.g. Barangay 1"/>
        </div>
        <div class="fg">
          <label>Contact Person <span style="color:#c62828">*</span></label>
          <input type="text" name="contact_person" value="<?= htmlspecialchars($org['contact_person'] ?? '') ?>" required placeholder="President / Representative"/>
        </div>
      </div>
      <div class="fg-2">
        <div class="fg">
          <label>Contact Email <span style="color:#c62828">*</span></label>
          <input type="email" name="contact_email" value="<?= htmlspecialchars($org['contact_email'] ?? '') ?>" required placeholder="organization@email.com"/>
        </div>
        <div class="fg">
          <label>Contact Phone <span style="color:#c62828">*</span></label>
          <input type="tel" name="contact_phone" value="<?= htmlspecialchars($org['contact_phone'] ?? '') ?>" required placeholder="09XX XXX XXXX"/>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h3><i class="fas fa-paperclip"></i> Required Documents</h3>
      <p style="font-size:.85rem;color:#64748b;margin-bottom:16px">Upload the following documents (PDF, DOC, DOCX, JPG, PNG - max 5MB each)</p>

      <div class="fg">
        <label>Letter of Intent</label>
        <div class="file-upload">
          <input type="file" name="letter_of_intent" id="letter_of_intent" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
          <label for="letter_of_intent" class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i> Choose file...
          </label>
          <div class="file-name" id="name_letter_of_intent"></div>
        </div>
      </div>

      <div class="fg">
        <label>NYC Accreditation Form (YORP)</label>
        <div class="file-upload">
          <input type="file" name="nyc_form" id="nyc_form" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
          <label for="nyc_form" class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i> Choose file...
          </label>
          <div class="file-name" id="name_nyc_form"></div>
        </div>
      </div>

      <div class="fg">
        <label>Officers and Members List</label>
        <div class="file-upload">
          <input type="file" name="officers_list" id="officers_list" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
          <label for="officers_list" class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i> Choose file...
          </label>
          <div class="file-name" id="name_officers_list"></div>
        </div>
      </div>

      <div class="fg">
        <label>Constitution and By-Laws</label>
        <div class="file-upload">
          <input type="file" name="constitution" id="constitution" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
          <label for="constitution" class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i> Choose file...
          </label>
          <div class="file-name" id="name_constitution"></div>
        </div>
      </div>

      <div class="fg">
        <label>LYDO Accreditation Form</label>
        <div class="file-upload">
          <input type="file" name="lydo_form" id="lydo_form" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
          <label for="lydo_form" class="file-upload-label">
            <i class="fas fa-cloud-upload-alt"></i> Choose file...
          </label>
          <div class="file-name" id="name_lydo_form"></div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      <i class="fas fa-paper-plane"></i> Submit Accreditation Application
    </button>
  </form>

</main>
</div>

<script>
// File upload filename display
['letter_of_intent', 'nyc_form', 'officers_list', 'constitution', 'lydo_form'].forEach(function(id) {
  document.getElementById(id).addEventListener('change', function(e) {
    var fileName = e.target.files[0] ? e.target.files[0].name : '';
    document.getElementById('name_' + id).textContent = fileName;
  });
});
</script>
</body>
</html>


<style>
/* Enhanced Responsive Design for Accreditation Page */
@media(max-width:900px){
  .page-header h1{font-size:1.3rem}
  .page-header p{font-size:.82rem}
  .fg-2{grid-template-columns:1fr}
  .form-section{padding:16px 18px}
  .app-card{padding:14px 16px}
  .app-header{flex-direction:column;align-items:flex-start;gap:8px}
}

@media(max-width:768px){
  .fg-2{grid-template-columns:1fr;gap:12px}
  .form-section{padding:14px 16px;margin-bottom:14px}
  .form-section h3{font-size:.95rem}
  .fg label{font-size:.8rem}
  .fg input,.fg select,.fg textarea{font-size:.85rem;padding:9px 11px}
  .app-card{padding:12px 14px}
  .app-title{font-size:.9rem}
  .status-badge{font-size:.68rem}
  .app-meta{font-size:.72rem;gap:8px;flex-wrap:wrap}
  .file-upload-label{font-size:.8rem;padding:9px 11px}
  .btn-submit{font-size:.85rem;padding:10px}
  .flash{padding:12px 14px;font-size:.85rem}
}

@media(max-width:600px){
  .content{padding:16px}
  .page-header h1{font-size:1.05rem}
  .page-header p{font-size:.78rem}
  .form-section{padding:12px 14px}
  .form-section h3{font-size:.88rem}
  .fg-2{gap:10px}
  .fg{margin-bottom:10px}
  .fg label{font-size:.78rem}
  .fg input,.fg select,.fg textarea{font-size:.82rem;padding:8px 10px}
  .app-card{padding:10px 12px}
  .app-title{font-size:.85rem}
  .status-badge{font-size:.65rem}
  .app-meta{font-size:.7rem}
  .file-upload-label{font-size:.78rem;padding:8px 10px}
  .btn-submit{font-size:.82rem;padding:9px}
  .flash{padding:10px 12px;font-size:.82rem}
}

@media(max-width:480px){
  .content{padding:12px}
  .page-header h1{font-size:.95rem}
  .page-header p{font-size:.75rem}
  .form-section{padding:10px 12px}
  .form-section h3{font-size:.82rem}
  .fg label{font-size:.75rem}
  .fg input,.fg select,.fg textarea{font-size:.8rem;padding:7px 9px}
  .app-card{padding:9px 11px}
  .app-title{font-size:.8rem}
  .status-badge{font-size:.62rem}
  .app-meta{font-size:.68rem;gap:5px}
  .file-upload-label{font-size:.75rem;padding:7px 9px}
  .btn-submit{font-size:.78rem;padding:8px}
  .flash{padding:8px 10px;font-size:.78rem}
}

@media(hover:none) and (pointer:coarse){
  .btn-submit,.file-upload-label{min-height:44px}
  input,select,textarea{min-height:40px}
}
</style>
