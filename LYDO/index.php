<?php
// On Railway, serve the index.html directly
if (file_exists(__DIR__ . '/index.html')) {
    readfile(__DIR__ . '/index.html');
    exit;
}

// Fallback for local XAMPP environment
header('Location: /LYDO/index.html');
exit;
