<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Load query helper functions
require_once __DIR__ . '/query_helper.php';

// ══════════════════════════════════════════════════════════════
// SUPABASE CONFIGURATION
// ══════════════════════════════════════════════════════════════
// Get these values from your Supabase project dashboard:
// https://app.supabase.com/project/YOUR_PROJECT/settings/api

define('SUPABASE_URL', 'REMOVED_FOR_SECURITY');  
define('SUPABASE_KEY', 'REMOVED_FOR_SECURITY');                 
define('SUPABASE_SERVICE_KEY', 'REMOVED_FOR_SECURITY');

// PostgreSQL Direct Connection (for PDO)
// Format: postgresql://user:password@host:port/database
// Use the connection pooler (port 6543) for better performance and connection management
define('SUPABASE_DB_HOST', 'REMOVED_FOR_SECURITY');
define('SUPABASE_DB_PORT', '6543');
define('SUPABASE_DB_NAME', 'postgres');
define('SUPABASE_DB_USER', 'REMOVED_FOR_SECURITY');
define('SUPABASE_DB_PASS', 'REMOVED_FOR_SECURITY');

// ── Gmail SMTP Config (unchanged) ─────────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'mendozacrisann14@gmail.com');
define('MAIL_PASSWORD', 'ngtx zdua glyt zlzh');
define('MAIL_FROM',     'mendozacrisann14@gmail.com');
define('MAIL_FROM_NAME','LYDO Sta. Cruz, Laguna');

/**
 * Get PostgreSQL PDO connection to Supabase
 * @return PDO
 */
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        try {
            $dsn = 'pgsql:host='.SUPABASE_DB_HOST.';port='.SUPABASE_DB_PORT.';dbname='.SUPABASE_DB_NAME;
            $pdo = new PDO($dsn, SUPABASE_DB_USER, SUPABASE_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c62828;background:#ffebee;border-radius:10px;max-width:600px;margin:40px auto">
                <h2>Supabase Database Connection Failed</h2>
                <p>'.$e->getMessage().'</p>
                <p>Check your <strong>supabase_config.php</strong> settings:</p>
                <ul style="text-align:left">
                    <li>SUPABASE_DB_HOST</li>
                    <li>SUPABASE_DB_USER</li>
                    <li>SUPABASE_DB_PASS</li>
                </ul>
            </div>');
        }
    }
    return $pdo;
}

/**
 * Flash message helper (unchanged)
 */
function flash(string $key, string $msg = ''): string {
    if ($msg) { $_SESSION['flash'][$key] = $msg; return ''; }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}

/**
 * Send email via Gmail SMTP (unchanged from original)
 */
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

// ══════════════════════════════════════════════════════════════
// POSTGRESQL COMPATIBILITY HELPERS
// ══════════════════════════════════════════════════════════════

/**
 * Get last inserted ID (PostgreSQL compatible)
 * In PostgreSQL, we use RETURNING id instead of LAST_INSERT_ID()
 * This helper provides backwards compatibility
 */
function getLastInsertId(PDO $pdo, string $sequence = null): int {
    if ($sequence) {
        return (int) $pdo->lastInsertId($sequence);
    }
    // For queries with RETURNING id, the ID is already returned
    return (int) $pdo->lastInsertId();
}

/**
 * Convert boolean for PostgreSQL
 * PostgreSQL uses TRUE/FALSE, MySQL uses 1/0
 */
function pgBool($value): string {
    return $value ? 'TRUE' : 'FALSE';
}

/**
 * Escape LIKE pattern for PostgreSQL
 */
function pgLike(string $pattern): string {
    return str_replace(['%', '_'], ['\%', '\_'], $pattern);
}

/**
 * Format datetime for PostgreSQL
 * PostgreSQL is more strict about datetime formats
 */
function pgDatetime($datetime): string {
    if (empty($datetime)) return 'NULL';
    if ($datetime instanceof DateTime) {
        return $datetime->format('Y-m-d H:i:s');
    }
    return date('Y-m-d H:i:s', strtotime($datetime));
}

/**
 * PostgreSQL LIMIT/OFFSET helper
 * Usage: SELECT * FROM table {$limit}
 */
function pgLimit(int $limit, int $offset = 0): string {
    $sql = "LIMIT $limit";
    if ($offset > 0) $sql .= " OFFSET $offset";
    return $sql;
}

/**
 * Check if query needs RETURNING clause
 * For INSERT/UPDATE queries that need the ID back
 */
function needsReturning(string $sql): bool {
    $sql = strtoupper(trim($sql));
    return (
        strpos($sql, 'INSERT') === 0 || 
        strpos($sql, 'UPDATE') === 0
    ) && strpos($sql, 'RETURNING') === false;
}

/**
 * Add RETURNING clause if needed
 * INSERT INTO table ... RETURNING id
 */
function addReturning(string $sql, string $column = 'id'): string {
    if (needsReturning($sql) && strpos(strtoupper($sql), 'INSERT') === 0) {
        return rtrim($sql, ';') . " RETURNING $column";
    }
    return $sql;
}
