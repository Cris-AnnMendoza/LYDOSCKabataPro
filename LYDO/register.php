<?php
require_once __DIR__ . '/shared/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$pdo = db();

// ── Required fields ───────────────────────────────────────
$required = ['first_name','last_name','gender','birthdate','age',
             'civil_status','contact_number','email','password','barangay'];

foreach ($required as $f) {
    if (empty($_POST[$f])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_',' ',$f)) . ' is required.']);
        exit;
    }
}

$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$password = $_POST['password'];
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

// Enhanced password strength validation
$hasLowercase = preg_match('/[a-z]/', $password);
$hasUppercase = preg_match('/[A-Z]/', $password);
$hasNumber = preg_match('/[0-9]/', $password);
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

$strengthScore = 0;
if ($hasLowercase) $strengthScore++;
if ($hasUppercase) $strengthScore++;
if ($hasNumber) $strengthScore++;
if ($hasSpecial) $strengthScore++;

if ($strengthScore < 2) {
    http_response_code(422);
    echo json_encode([
        'success' => false, 
        'message' => 'Password is too weak. Please include a mix of uppercase letters, lowercase letters, numbers, and special characters.'
    ]);
    exit;
}

if ($password !== ($_POST['confirm_password'] ?? '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

// ── Check duplicate email ─────────────────────────────────
$check = $pdo->prepare('SELECT id FROM youth_users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This email is already registered. Please login.']);
    exit;
}

// ── Hash password ─────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ── Get registration role ─────────────────────────────────
$registerAs = $_POST['register_as'] ?? 'youth_member';
$organizationId = null;
$youthOrganizationId = null;

if ($registerAs === 'organization_president') {
    // Organization President Registration
    if (empty($_POST['organization_id'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please select your organization.']);
        exit;
    }
    
    $organizationId = (int)$_POST['organization_id'];
    
    // Check if organization already has a president
    $checkPres = $pdo->prepare('SELECT id FROM organization_presidents WHERE organization_id = ? AND is_active = 1 LIMIT 1');
    $checkPres->execute([$organizationId]);
    if ($checkPres->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This organization already has a registered president.']);
        exit;
    }
} else {
    // Youth Member with optional organization affiliation
    if (!empty($_POST['youth_organization_id'])) {
        $youthOrganizationId = (int)$_POST['youth_organization_id'];
    }
}

// ── Classification / programs ─────────────────────────────
$classification = isset($_POST['youth_classification'])
    ? json_encode((array)$_POST['youth_classification'])
    : null;

$programs = isset($_POST['programs_interested'])
    ? json_encode((array)$_POST['programs_interested'])
    : null;

// ── Insert ────────────────────────────────────────────────
$sql = 'INSERT INTO youth_users (
    first_name, middle_name, last_name, suffix, gender, birthdate, age,
    civil_status, contact_number, email, password,
    house_number, street, barangay, municipality, province, zip_code,
    status, youth_classification, educational_status, school_name, course_or_grade,
    employment_status, organization_name, organization_type, organization_role,
    years_membership, skills, interests, programs_interested, volunteer_availability
) VALUES (
    :first_name,:middle_name,:last_name,:suffix,:gender,:birthdate,:age,
    :civil_status,:contact_number,:email,:password,
    :house_number,:street,:barangay,:municipality,:province,:zip_code,
    :status,:youth_classification,:educational_status,:school_name,:course_or_grade,
    :employment_status,:organization_name,:organization_type,:organization_role,
    :years_membership,:skills,:interests,:programs_interested,:volunteer_availability
)';

$pdo->prepare($sql)->execute([
    ':first_name'           => trim($_POST['first_name']),
    ':middle_name'          => trim($_POST['middle_name'] ?? ''),
    ':last_name'            => trim($_POST['last_name']),
    ':suffix'               => trim($_POST['suffix'] ?? ''),
    ':gender'               => $_POST['gender'],
    ':birthdate'            => $_POST['birthdate'],
    ':age'                  => (int)$_POST['age'],
    ':civil_status'         => $_POST['civil_status'],
    ':contact_number'       => trim($_POST['contact_number']),
    ':email'                => $email,
    ':password'             => $hash,
    ':house_number'         => trim($_POST['house_number'] ?? ''),
    ':street'               => trim($_POST['street'] ?? ''),
    ':barangay'             => $_POST['barangay'],
    ':municipality'         => $_POST['municipality'] ?? 'Sta. Cruz',
    ':province'             => $_POST['province']     ?? 'Laguna',
    ':zip_code'             => $_POST['zip_code']     ?? '4009',
    ':status'               => 'pending',
    ':youth_classification' => $classification,
    ':educational_status'   => $_POST['educational_status']   ?? null,
    ':school_name'          => $_POST['school_name']          ?? null,
    ':course_or_grade'      => $_POST['course_or_grade']      ?? null,
    ':employment_status'    => $_POST['employment_status']    ?? null,
    ':organization_name'    => $_POST['organization_name']    ?? null,
    ':organization_type'    => $_POST['organization_type']    ?? null,
    ':organization_role'    => $_POST['organization_role']    ?? null,
    ':years_membership'     => (int)($_POST['years_membership'] ?? 0),
    ':skills'               => $_POST['skills']               ?? null,
    ':interests'            => $_POST['interests']            ?? null,
    ':programs_interested'  => $programs,
    ':volunteer_availability' => $_POST['volunteer_availability'] ?? null,
]);

$userId = $pdo->lastInsertId();

// ── Create Organization President Record ──────────────────
if ($registerAs === 'organization_president' && $organizationId) {
    $fullName = trim($_POST['first_name']) . ' ' . 
                trim($_POST['middle_name'] ?? '') . ' ' . 
                trim($_POST['last_name']);
    $fullName = preg_replace('/\s+/', ' ', $fullName); // Remove extra spaces
    
    $presidentSql = 'INSERT INTO organization_presidents (
        organization_id, user_id, email, password, full_name, 
        contact_number, is_active, created_at
    ) VALUES (
        :organization_id, :user_id, :email, :password, :full_name,
        :contact_number, 0, NOW()
    )';
    
    $pdo->prepare($presidentSql)->execute([
        ':organization_id' => $organizationId,
        ':user_id'         => $userId,
        ':email'           => $email,
        ':password'        => $hash,
        ':full_name'       => $fullName,
        ':contact_number'  => trim($_POST['contact_number'])
    ]);
    
    // Update organization with president_id
    $updateOrg = $pdo->prepare('UPDATE organizations SET president_id = ? WHERE id = ?');
    $updateOrg->execute([$userId, $organizationId]);
    
    $message = 'Organization President registration submitted! Your account is pending approval. You will be notified once approved.';
} else {
    $message = 'Registration submitted! Your account is pending approval by the LYDO office. You will be notified once approved.';
}

echo json_encode([
    'success' => true,
    'message' => $message,
]);
