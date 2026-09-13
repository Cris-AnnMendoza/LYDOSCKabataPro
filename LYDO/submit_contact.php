<?php
// Handle contact form submission
header('Content-Type: application/json');
require_once 'lydo-system/shared/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate
    if (empty($firstName) || empty($lastName) || empty($email) || empty($subject) || empty($message)) {
        throw new Exception('All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    $pdo = db();
    
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (first_name, last_name, email, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$firstName, $lastName, $email, $subject, $message]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for contacting us! We will get back to you soon.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
