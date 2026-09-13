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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_explanation') {
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (!$subject || !$content) {
            $_SESSION['flash_error'] = 'Subject and explanation are required.';
            header('Location: warnings.php');
            exit;
        }
        
        // Handle file upload if present
        $uploadedFile = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                $_SESSION['flash_error'] = 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG are allowed.';
                header('Location: warnings.php');
                exit;
            }
            
            if ($file['size'] > $maxSize) {
                $_SESSION['flash_error'] = 'File size exceeds 5MB limit.';
                header('Location: warnings.php');
                exit;
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../uploads/explanation_letters/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'explanation_' . $orgId . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadedFile = $filename;
            } else {
                $_SESSION['flash_error'] = 'Failed to upload file.';
                header('Location: warnings.php');
                exit;
            }
        }
        
        // Insert explanation letter
        try {
            // Get user_id from org president session or use NULL if not available
            $userId = $president['user_id'] ?? null;
            
            if ($userId) {
                $pdo->prepare('INSERT INTO org_explanation_letters (user_id, organization_id, subject, content, attachment, status) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, $orgId, $subject, $content, $uploadedFile, 'pending']);
            } else {
                // If no user_id, make user_id nullable and insert NULL
                $pdo->prepare('INSERT INTO org_explanation_letters (user_id, organization_id, subject, content, attachment, status) VALUES (NULL, ?, ?, ?, ?, ?)')
                    ->execute([$orgId, $subject, $content, $uploadedFile, 'pending']);
            }
            
            $_SESSION['flash_success'] = 'Explanation letter submitted successfully! The LYDO admin will review it soon.';
        } catch (PDOException $e) {
            // If still fails due to foreign key, try to make user_id nullable first
            if (strpos($e->getMessage(), 'user_id') !== false && strpos($e->getMessage(), 'foreign key') !== false) {
                try {
                    // Make user_id nullable
                    $pdo->exec("ALTER TABLE org_explanation_letters MODIFY user_id INT(10) UNSIGNED NULL");
                    // Retry insert with NULL
                    $pdo->prepare('INSERT INTO org_explanation_letters (user_id, organization_id, subject, content, attachment, status) VALUES (NULL, ?, ?, ?, ?, ?)')
                        ->execute([$orgId, $subject, $content, $uploadedFile, 'pending']);
                    $_SESSION['flash_success'] = 'Explanation letter submitted successfully! The LYDO admin will review it soon.';
                } catch (PDOException $e2) {
                    error_log("Failed to submit explanation letter after retry: " . $e2->getMessage());
                    $_SESSION['flash_error'] = 'Failed to submit explanation letter. Please contact LYDO admin.';
                }
            } else {
                error_log("Failed to submit explanation letter: " . $e->getMessage());
                $_SESSION['flash_error'] = 'Failed to submit explanation letter. Error: ' . $e->getMessage();
            }
        }
        
        header('Location: warnings.php');
        exit;
    }
}

// If not POST or invalid action, redirect back
header('Location: warnings.php');
exit;
