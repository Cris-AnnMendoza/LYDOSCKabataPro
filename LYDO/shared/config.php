<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ══════════════════════════════════════════════════════════════
// DATABASE CONFIGURATION - SUPPORTS BOTH LOCAL AND PRODUCTION
// ══════════════════════════════════════════════════════════════

// Check if running on Railway (production) or localhost (development)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('DATABASE_URL') !== false;

if ($isProduction) {
    // Production: Use Railway environment variables
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl) {
        // Parse DATABASE_URL (format: mysql://user:password@host:port/database)
        $url = parse_url($databaseUrl);
        define('DB_HOST', $url['host'] ?? 'localhost');
        define('DB_PORT', $url['port'] ?? '3306');
        define('DB_USER', $url['user'] ?? 'root');
        define('DB_PASS', $url['pass'] ?? '');
        define('DB_NAME', ltrim($url['path'] ?? '/local_youth_development_db', '/'));
    } else {
        // Fallback to individual environment variables
        define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
        define('DB_PORT', getenv('DB_PORT') ?: '3306');
        define('DB_USER', getenv('DB_USER') ?: 'root');
        define('DB_PASS', getenv('DB_PASS') ?: '');
        define('DB_NAME', getenv('DB_NAME') ?: 'local_youth_development_db');
    }
} else {
    // Development: Use localhost XAMPP MySQL
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'local_youth_development_db');
}

// ── Gmail SMTP Config ─────────────────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'REMOVED_FOR_SECURITY');
define('MAIL_PASSWORD', 'REMOVED_FOR_SECURITY');
define('MAIL_FROM',     'REMOVED_FOR_SECURITY');
define('MAIL_FROM_NAME','LYDO Sta. Cruz, Laguna');

// ═════════════════════════════════════════════════════════════
// AI CONFIGURATION - FOR WELL-BEING ASSISTANT
// ══════════════════════════════════════════════════════════════
// Get free API keys from:
// - Groq: https://console.groq.com (RECOMMENDED - Fast & Free)
// - Gemini: https://makersuite.google.com/app/apikey
// REMOVED FOR SECURITY - Keys now in .env file (not in git)
// putenv('GROQ_API_KEY=REMOVED_FOR_SECURITY');
// putenv('GEMINI_API_KEY=REMOVED_FOR_SECURITY');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        try {
            $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c62828;background:#ffebee;border-radius:10px;max-width:600px;margin:40px auto">
                <h2>Database Connection Failed</h2>
                <p>'.$e->getMessage().'</p>
                <p>Make sure <strong>MySQL is running</strong> in XAMPP Control Panel.</p>
            </div>');
        }
    }
    return $pdo;
}

function flash(string $key, string $msg = ''): string {
    if ($msg) { $_SESSION['flash'][$key] = $msg; return ''; }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}

/**
 * Get the server host that's accessible from mobile devices
 * Converts localhost to actual IP address for QR codes and mobile access
 */
function getAccessibleHost(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // If localhost, try to get server's actual IP
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        // Try Windows ipconfig method first
        if (stristr(PHP_OS, 'WIN')) {
            exec('ipconfig', $output);
            foreach($output as $line) {
                if (strpos($line, 'IPv4') !== false) {
                    $parts = explode(':', $line);
                    if (isset($parts[1])) {
                        $ip = trim($parts[1]);
                        // Check if it's a local network IP
                        if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $ip)) {
                            return $ip;
                        }
                    }
                }
            }
        }
        
        // Fallback: try gethostbyname
        $serverIP = gethostbyname(gethostname());
        if ($serverIP && $serverIP !== gethostname() && $serverIP !== '127.0.0.1') {
            return $serverIP;
        }
    }
    
    return $host;
}

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $host     = MAIL_HOST;
    $port     = MAIL_PORT;
    $username = MAIL_USERNAME;
    $password = MAIL_PASSWORD;
    $from     = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;

    if (empty($username) || empty($password) || $password === 'your_app_password_here') {
        error_log('LYDO Mail: Gmail credentials not configured in shared/config.php');
        return false;
    }

    try {
        $socket = fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("SMTP connect failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = function() use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $data;
        };

        $send = function(string $cmd) use ($socket, $read): string {
            fwrite($socket, $cmd . "\r\n");
            return $read();
        };

        $read();
        $send('EHLO ' . gethostname());
        $resp = $send('STARTTLS');
        if (strpos($resp, '220') === false) {
            fclose($socket);
            error_log('STARTTLS failed: ' . $resp);
            return false;
        }

        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send('EHLO ' . gethostname());
        $send('AUTH LOGIN');
        $send(base64_encode($username));
        $authResp = $send(base64_encode($password));
        if (strpos($authResp, '235') === false) {
            fclose($socket);
            error_log('SMTP AUTH failed: ' . $authResp);
            return false;
        }

        $send("MAIL FROM:<{$from}>");
        $send("RCPT TO:<{$toEmail}>");
        $send('DATA');

        $boundary = md5(uniqid());
        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainText)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $msgResp = $send($headers . "\r\n" . $body . "\r\n.");
        $send('QUIT');
        fclose($socket);

        if (strpos($msgResp, '250') !== false) {
            return true;
        }
        error_log('SMTP send failed: ' . $msgResp);
        return false;

    } catch (Throwable $e) {
        error_log('SMTP exception: ' . $e->getMessage());
        return false;
    }
}
