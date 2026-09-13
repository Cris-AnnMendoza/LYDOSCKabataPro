<?php
/**
 * Email Configuration using PHPMailer
 * Update these settings with your Gmail credentials
 */

// Import PHPMailer classes at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Gmail SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');  // ← CHANGE THIS to your Gmail
define('SMTP_PASSWORD', 'your-app-password');      // ← CHANGE THIS to your Gmail App Password
define('SMTP_FROM_EMAIL', 'noreply@stacruzlaguna.gov.ph');
define('SMTP_FROM_NAME', 'LYDO Sta. Cruz');

/**
 * Send email using PHPMailer
 */
function sendEmail($to, $toName, $subject, $htmlBody) {
    // Check if PHPMailer is installed
    if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        error_log('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
        return false;
    }
    
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo('youth@stacruzlaguna.gov.ph', 'LYDO Support');
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text version
        
        $mail->send();
        return true;
        
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
        return false;
    }
}
