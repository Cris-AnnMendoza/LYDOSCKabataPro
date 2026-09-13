<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('view_reports')) { header('Location: dashboard.php'); exit; }

$pdo   = db();
$admin = currentAdmin();

// ── Scope ─────────────────────────────────────────────────
$scopeWhere  = '';
$scopeParams = [];
if ($admin['role'] === 'barangay_admin' && $admin['barangay']) {
    $scopeWhere    = 'AND barangay = ?';
    $scopeParams[] = $admin['barangay'];
}

// ── Filters ───────────────────────────────────────────────
$fBarangay = trim($_GET['barangay']       ?? '');
$fGender   = trim($_GET['gender']         ?? '');
$fClass    = trim($_GET['classification'] ?? '');
$fEduc     = trim($_GET['educational']    ?? '');
$fEmploy   = trim($_GET['employment']     ?? '');
$fStatus   = trim($_GET['status']         ?? '');
$fMonth    = trim($_GET['month']          ?? '');
$fYear     = trim($_GET['year']           ?? '');
$search    = trim($_GET['search']         ?? '');

$where  = ['1=1'];
$params = $scopeParams;

if ($fBarangay) { $where[] = 'barangay = ?';                $params[] = $fBarangay; }
if ($fGender)   { $where[] = 'gender = ?';                  $params[] = $fGender; }
if ($fClass)    { $where[] = 'youth_classification LIKE ?'; $params[] = '%'.$fClass.'%'; }
if ($fEduc)     { $where[] = 'educational_status = ?';      $params[] = $fEduc; }
if ($fEmploy)   { $where[] = 'employment_status = ?';       $params[] = $fEmploy; }
if ($fStatus)   { $where[] = 'status = ?';                  $params[] = $fStatus; }
if ($fMonth)    { $where[] = 'MONTH(created_at) = ?';       $params[] = $fMonth; }
if ($fYear)     { $where[] = 'YEAR(created_at) = ?';        $params[] = $fYear; }
if ($search) {
    $s = '%'.$search.'%';
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR barangay LIKE ?)';
    array_push($params, $s, $s, $s, $s);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Build filename based on active filters ────────────────
$parts = ['lydo_youth'];
if ($fBarangay)  $parts[] = 'brgy_'.preg_replace('/[^a-z0-9]/i','_',$fBarangay);
if ($fGender)    $parts[] = strtolower($fGender);
if ($fClass)     $parts[] = preg_replace('/[^a-z0-9]/i','_',strtolower($fClass));
if ($fStatus)    $parts[] = $fStatus;
if ($fYear)      $parts[] = $fYear;
if ($fMonth)     $parts[] = date('F',mktime(0,0,0,(int)$fMonth,1));
$filename = implode('_', $parts) . '_' . date('Y-m-d') . '.csv';

// ── Fetch ─────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT first_name, middle_name, last_name, suffix, gender, birthdate, age,
           civil_status, contact_number, email, status,
           house_number, street, barangay, municipality, province, zip_code,
           youth_classification, educational_status, school_name, course_or_grade,
           employment_status, organization_name, organization_type, organization_role,
           years_membership, skills, interests, programs_interested,
           volunteer_availability, created_at
    FROM youth_users $whereSQL ORDER BY created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Output CSV ────────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'First Name','Middle Name','Last Name','Suffix','Gender','Birthdate','Age',
    'Civil Status','Contact Number','Email','Account Status',
    'House/Street','Barangay','Municipality','Province','ZIP Code',
    'Youth Classification','Educational Status','School Name','Course/Grade',
    'Employment Status','Organization Name','Org. Type','Position/Role',
    'Years of Membership','Skills','Interests','Programs Interested',
    'Volunteer Availability','Date Registered'
]);

foreach ($rows as $r) {
    // Decode JSON arrays to readable string
    $cls  = $r['youth_classification'] ? implode('; ', (array)json_decode($r['youth_classification'],true)) : '';
    $prgs = $r['programs_interested']  ? implode('; ', (array)json_decode($r['programs_interested'],true))  : '';

    fputcsv($out, [
        $r['first_name'], $r['middle_name'], $r['last_name'], $r['suffix'],
        $r['gender'], $r['birthdate'], $r['age'],
        $r['civil_status'], $r['contact_number'], $r['email'], $r['status'],
        trim(($r['house_number'] ?? '').' '.($r['street'] ?? '')),
        $r['barangay'], $r['municipality'], $r['province'], $r['zip_code'],
        $cls, $r['educational_status'], $r['school_name'], $r['course_or_grade'],
        $r['employment_status'], $r['organization_name'], $r['organization_type'],
        $r['organization_role'], $r['years_membership'],
        $r['skills'], $r['interests'], $prgs,
        $r['volunteer_availability'],
        date('Y-m-d H:i', strtotime($r['created_at'])),
    ]);
}

fclose($out);
exit;
