<?php
/**
 * Secure file viewer for uploaded assistance documents.
 * Only accessible by logged-in admins.
 */
require_once 'config.php';
requireLogin();

$pdo      = db();
$docId    = (int)($_GET['doc_id'] ?? 0);
$inline   = isset($_GET['inline']); // view inline vs download

if (!$docId) {
    http_response_code(400);
    die('Invalid request.');
}

// Load document record
$stmt = $pdo->prepare('SELECT d.*, r.submitted_by FROM assistance_documents d JOIN assistance_requests r ON r.id = d.request_id WHERE d.id = ? LIMIT 1');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

// Get filename - support both 'filename' and 'file_path' columns
$filename = $doc['filename'] ?? $doc['file_path'] ?? null;
if (!$filename) {
    http_response_code(404);
    die('File path not found in database.');
}

// Build file path - try multiple possible locations
$possiblePaths = [
    __DIR__ . '/../shared/uploads/assistance/' . $filename,
    __DIR__ . '/../../shared/uploads/assistance/' . $filename,
    __DIR__ . '/../uploads/assistance/' . $filename,
];

$filePath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $filePath = $path;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    die('File not found on server. Path checked: ' . htmlspecialchars($filename));
}

// Determine MIME type
$ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'        => 'application/pdf',
    'jpg','jpeg' => 'image/jpeg',
    'png'        => 'image/png',
    'gif'        => 'image/gif',
    'doc'        => 'application/msword',
    'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'        => 'application/vnd.ms-excel',
    'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    default      => 'application/octet-stream',
};

// Get original name - support both column names
$originalName = $doc['original_name'] ?? $doc['filename'] ?? 'document.' . $ext;

// Serve the file
$disposition = $inline ? 'inline' : 'attachment';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $originalName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;

