<?php
require_once __DIR__ . '/shared/config.php';
if (!empty($_SESSION['user_id']))  { header('Location: shared/youth/dashboard.php'); exit; }
if (!empty($_SESSION['admin_id'])) { header('Location: admin2/dashboard.php'); exit; }

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS password_otps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    user_type ENUM('youth','admin') NOT NULL DEFAULT 'youth',
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$step  = $_SESSION['fp_step']  ?? 1;
$email = $_SESSION['fp_email'] ?? '';
$error = '';
$success = '';

// STEP 1: Submit email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_email'])) {
    $inputEmail = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    if (!$inputEmail || !filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $youth = $pdo->prepare('SELECT id, first_name FROM youth_users WHERE email=? LIMIT 1');
        $youth->execute([$inputEmail]); $youth = $youth->fetch();
        $admin = $pdo->prepare('SELECT id, full_name FROM admin_users WHERE email=? AND is_active = TRUE LIMIT 1');
        $admin->execute([$inputEmail]); $admin = $admin->fetch();

        if (!$youth && !$admin) {
            $error = 'No account found with that email address.';
        } else {
            $rateCheck = $pdo->prepare('SELECT COUNT(*) FROM password_otps WHERE email=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
            $rateCheck->execute([$inputEmail]);
            if ((int)$rateCheck->fetchColumn() >= 3) {
                $error = 'Too many attempts. Please wait 10 minutes.';
            } else {
                $otp      = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $userType = $youth ? 'youth' : 'admin';
                $name     = $youth ? $youth['first_name'] : $admin['full_name'];

                // Delete old OTPs and insert new one
                // Use MySQL's NOW() for expires_at to avoid PHP/MySQL timezone mismatch
                $pdo->prepare('DELETE FROM password_otps WHERE email=?')->execute([$inputEmail]);
                $pdo->prepare('INSERT INTO password_otps (email, otp, user_type, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')->execute([$inputEmail, $otp, $userType]);

                $emailHtml = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)'>
                  <div style='background:linear-gradient(135deg,#0d3b6e,#1565c0);padding:28px;text-align:center'>
                    <div style='font-size:2.5rem;margin-bottom:8px'>🔐</div>
                    <h1 style='color:#fff;font-size:1.3rem;margin:0'>Password Reset Code</h1>
                    <p style='color:rgba(255,255,255,.75);font-size:.85rem;margin:6px 0 0'>LYDO Youth Portal</p>
                  </div>
                  <div style='padding:32px'>
                    <p style='color:#1e293b;font-size:.95rem;margin-bottom:14px'>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p style='color:#475569;font-size:.9rem;margin-bottom:22px'>Use the code below to reset your password. This code expires in <strong>15 minutes</strong>.</p>
                    <div style='background:#f1f5f9;border:2px dashed #90caf9;border-radius:14px;padding:24px;text-align:center;margin-bottom:22px'>
                      <div style='font-size:2.8rem;font-weight:900;letter-spacing:.35em;color:#0d3b6e;font-family:monospace'>{$otp}</div>
                      <div style='font-size:.75rem;color:#94a3b8;margin-top:8px'>6-digit verification code</div>
                    </div>
                    <p style='color:#94a3b8;font-size:.78rem;line-height:1.6'>If you did not request this, please ignore this email. Your password will not change.</p>
                  </div>
                  <div style='background:#f8fafc;padding:14px;text-align:center;font-size:.72rem;color:#94a3b8;border-top:1px solid #e2e8f0'>
                    &copy; " . date('Y') . " Local Youth Development Office &middot; Sta. Cruz, Laguna
                  </div>
                </div>";

                $sent = sendMail($inputEmail, $name, 'Your LYDO Password Reset Code: ' . $otp, $emailHtml);

                if ($sent) {
                    $_SESSION['fp_step']  = 2;
                    $_SESSION['fp_email'] = $inputEmail;
                    $step  = 2;
                    $email = $inputEmail;
                    $success = 'A 6-digit code was sent to <strong>' . htmlspecialchars($inputEmail) . '</strong>. Check your inbox and spam folder.';
                } else {
                    $error = 'Failed to send email. Please configure Gmail SMTP in shared/config.php first.';
                }
            }
        }
    }
}

// STEP 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    // Use session email, fallback to hidden field if session was lost
    if (empty($email) && !empty($_POST['fp_email'])) {
        $email = trim($_POST['fp_email']);
        $_SESSION['fp_email'] = $email;
        $_SESSION['fp_step']  = 2;
        $step = 2;
    }

    $inputOtp = preg_replace('/\D/', '', trim($_POST['otp'] ?? '')); // digits only
    if (strlen($inputOtp) !== 6) {
        $error = 'Please enter the complete 6-digit code.';
    } elseif (empty($email)) {
        $error = 'Session expired. Please start over.';
        $step  = 1;
        unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
    } else {
        // Check without expiry first to give better error message
        $anyRow = $pdo->prepare('SELECT * FROM password_otps WHERE email=? AND otp=? LIMIT 1');
        $anyRow->execute([$email, $inputOtp]);
        $anyData = $anyRow->fetch();

        if (!$anyData) {
            $error = 'Incorrect code. Please check the code in your email and try again.';
        } elseif ($anyData['verified']) {
            $error = 'This code has already been used. Please request a new one.';
        } else {
            // Check expiry separately
            $expCheck = $pdo->prepare('SELECT * FROM password_otps WHERE email=? AND otp=? AND verified=FALSE AND expires_at > NOW() LIMIT 1');
            $expCheck->execute([$email, $inputOtp]);
            $otpData = $expCheck->fetch();

            if (!$otpData) {
                $error = 'This code has expired. Please request a new one.';
            } else {
                $pdo->prepare('UPDATE password_otps SET verified=1 WHERE id=?')->execute([$otpData['id']]);
                $_SESSION['fp_step']     = 3;
                $_SESSION['fp_verified'] = true;
                $step = 3;
            }
        }
    }
}

// STEP 3: Set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    if (empty($_SESSION['fp_verified'])) {
        $error = 'Session expired. Please start over.';
        $step  = 1;
        unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
    } else {
        $newPass  = $_POST['new_password']     ?? '';
        $confPass = $_POST['confirm_password'] ?? '';
        if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPass !== $confPass) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $otpRow = $pdo->prepare('SELECT user_type FROM password_otps WHERE email=? AND verified=TRUE ORDER BY id DESC LIMIT 1');
            $otpRow->execute([$email]);
            $otpData = $otpRow->fetch();
            $userType = $otpData['user_type'] ?? 'youth';
            if ($userType === 'youth') {
                $pdo->prepare('UPDATE youth_users SET password=? WHERE email=?')->execute([$hash, $email]);
            } else {
                $pdo->prepare('UPDATE admin_users SET password=? WHERE email=?')->execute([$hash, $email]);
            }
            $pdo->prepare('DELETE FROM password_otps WHERE email=?')->execute([$email]);
            unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
            $success = 'Password changed successfully! You can now log in.';
            $step = 'done';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_verified']);
    header('Location: forgot_password.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Forgot Password – LYDO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:linear-gradient(160deg,#0d3b6e 0%,#1565c0 55%,#1b5e20 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:440px;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0d3b6e,#1565c0);padding:28px;text-align:center;color:#fff}
.icon-wrap{width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 14px}
.card-top h1{font-size:1.2rem;font-weight:800;margin-bottom:4px}
.card-top p{font-size:.82rem;opacity:.75}
.card-body{padding:28px}
.steps{display:flex;gap:0;margin-bottom:24px}
.step-item{flex:1;text-align:center;position:relative}
.step-item:not(:last-child)::after{content:'';position:absolute;top:14px;left:60%;width:80%;height:2px;background:#e2e8f0;z-index:0}
.step-circle{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;margin:0 auto 5px;position:relative;z-index:1;transition:.3s}
.step-circle.done{background:#2e7d32;color:#fff}
.step-circle.active{background:#1565c0;color:#fff;box-shadow:0 0 0 4px rgba(21,101,192,.2)}
.step-circle.pending{background:#e2e8f0;color:#94a3b8}
.step-label{font-size:.68rem;font-weight:600;color:#94a3b8}
.step-label.active{color:#1565c0}
.step-label.done{color:#2e7d32}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:16px}
.fg label{font-size:.85rem;font-weight:600;color:#1e293b}
.iw{position:relative}
.iw i.ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem;pointer-events:none}
.iw input{width:100%;padding:12px 13px 12px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.92rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s}
.iw input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.otp-wrap{display:flex;gap:8px;justify-content:center;margin-bottom:20px}
.otp-box{width:46px;height:54px;border:2px solid #e2e8f0;border-radius:10px;font-family:monospace;font-size:1.4rem;font-weight:800;text-align:center;color:#0d3b6e;background:#f8fafc;outline:none;transition:.2s}
.otp-box:focus{border-color:#1565c0;background:#fff;box-shadow:0 0 0 3px rgba(21,101,192,.1)}
.pw-wrap{position:relative}
.pw-wrap i.ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem;pointer-events:none}
.pw-wrap input{width:100%;padding:12px 44px 12px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.92rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s}
.pw-wrap input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.88rem;padding:4px}
.pw-toggle:hover{color:#1565c0}
.strength-bar{height:4px;border-radius:2px;margin-top:5px;background:#e2e8f0;transition:.3s}
.strength-bar.weak{background:#ef5350;width:33%}
.strength-bar.medium{background:#ffa726;width:66%}
.strength-bar.strong{background:#43a047;width:100%}
.strength-label{font-size:.72rem;margin-top:3px}
.btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;box-shadow:0 4px 14px rgba(21,101,192,.3)}
.btn-submit:hover{transform:translateY(-2px)}
.btn-submit.green{background:linear-gradient(135deg,#2e7d32,#43a047);box-shadow:0 4px 14px rgba(46,125,50,.3)}
.alert{padding:12px 15px;border-radius:10px;font-size:.86rem;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px}
.alert.error{background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2)}
.alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2)}
.email-badge{background:#e3f2fd;color:#1565c0;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:600;text-align:center;margin-bottom:18px;display:flex;align-items:center;gap:7px;justify-content:center}
.resend-link{font-size:.8rem;color:#94a3b8;text-align:center;margin-top:12px}
.resend-link button{background:none;border:none;color:#1565c0;font-family:inherit;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:underline}
.back-link{display:flex;align-items:center;gap:6px;font-size:.85rem;color:#64748b;text-decoration:none;margin-top:18px;justify-content:center;transition:.2s}
.back-link:hover{color:#1565c0}
.success-icon{text-align:center;font-size:3rem;margin-bottom:14px}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="icon-wrap" style="background:#fff;padding:4px">
      <img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:100%;height:100%;object-fit:contain"/>
    </div>
    <h1>
      <?php if ($step === 'done'): ?>Password Changed!
      <?php elseif ($step === 3): ?>Set New Password
      <?php elseif ($step === 2): ?>Enter Verification Code
      <?php else: ?>Forgot Password<?php endif; ?>
    </h1>
    <p>
      <?php if ($step === 'done'): ?>You can now log in with your new password
      <?php elseif ($step === 3): ?>Almost done — create your new password
      <?php elseif ($step === 2): ?>Check your email for the 6-digit code
      <?php else: ?>Enter your email to receive a reset code<?php endif; ?>
    </p>
  </div>

  <div class="card-body">

    <!-- Step indicator -->
    <?php if ($step !== 'done'): ?>
    <div class="steps">
      <?php
      $steps = ['Email','Code','Password'];
      $currentStep = is_int($step) ? $step : 3;
      foreach ($steps as $i => $lbl):
        $n = $i + 1;
        $cls = $n < $currentStep ? 'done' : ($n === $currentStep ? 'active' : 'pending');
        $icon = $n < $currentStep ? '<i class="fas fa-check" style="font-size:.65rem"></i>' : $n;
      ?>
      <div class="step-item">
        <div class="step-circle <?= $cls ?>"><?= $icon ?></div>
        <div class="step-label <?= $cls ?>"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success && $step !== 'done'): ?>
    <div class="alert success"><i class="fas fa-check-circle"></i><div><?= $success ?></div></div>
    <?php endif; ?>

    <?php if ($step === 'done'): ?>
    <!-- SUCCESS -->
    <div class="success-icon">✅</div>
    <div class="alert success" style="justify-content:center;text-align:center"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <a href="/LYDO/lydo-system/login.php" class="btn-submit green" style="text-decoration:none">
      <i class="fas fa-sign-in-alt"></i> Go to Login
    </a>

    <?php elseif ($step === 1): ?>
    <!-- STEP 1: EMAIL -->
    <form method="POST">
      <div class="fg">
        <label for="email">Email Address</label>
        <div class="iw">
          <i class="fas fa-envelope ico"></i>
          <input type="email" id="email" name="email" placeholder="Enter your registered email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email"/>
        </div>
      </div>
      <button type="submit" name="submit_email" class="btn-submit">
        <i class="fas fa-paper-plane"></i> Send Verification Code
      </button>
    </form>

    <?php elseif ($step === 2): ?>
    <!-- STEP 2: OTP -->
    <div class="email-badge"><i class="fas fa-envelope"></i>Code sent to: <strong><?= htmlspecialchars($email) ?></strong></div>
    <form method="POST" id="otpForm">
      <div class="fg">
        <label style="text-align:center;display:block;margin-bottom:10px;font-size:.88rem;color:#475569">
          Enter the 6-digit code from your email
        </label>
        <input type="hidden" name="fp_email" value="<?= htmlspecialchars($email) ?>"/>
        <input type="text" name="otp" id="otpSingle"
          maxlength="6" inputmode="numeric" autocomplete="one-time-code"
          placeholder="_ _ _ _ _ _" required
          style="width:100%;padding:16px;border:2px solid #90caf9;border-radius:12px;font-family:monospace;font-size:2rem;font-weight:900;text-align:center;letter-spacing:.4em;color:#0d3b6e;background:#f8fafc;outline:none;transition:.2s"
          oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6);document.getElementById('verifyBtn').disabled=this.value.length<6"/>
      </div>
      <button type="submit" name="verify_otp" class="btn-submit" id="verifyBtn" disabled>
        <i class="fas fa-check-circle"></i> Verify Code
      </button>
    </form>
    <div class="resend-link">
      Didn't receive the code?
      <form method="POST" style="display:inline">
        <button type="submit" name="resend_otp">Resend</button>
      </form>
    </div>

    <?php elseif ($step === 3): ?>
    <!-- STEP 3: NEW PASSWORD -->
    <div class="email-badge"><i class="fas fa-check-circle"></i>Identity verified for: <strong><?= htmlspecialchars($email) ?></strong></div>
    <form method="POST">
      <div class="fg">
        <label>New Password</label>
        <div class="pw-wrap">
          <i class="fas fa-lock ico"></i>
          <input type="password" id="newPass" name="new_password" placeholder="Min. 8 characters" required oninput="checkStrength(this.value)"/>
          <button type="button" class="pw-toggle" onclick="togglePw('newPass',this)"><i class="fas fa-eye"></i></button>
        </div>
        <div class="strength-bar" id="strengthBar"></div>
        <div class="strength-label" id="strengthLabel" style="color:#94a3b8"></div>
      </div>
      <div class="fg">
        <label>Confirm New Password</label>
        <div class="pw-wrap">
          <i class="fas fa-lock ico"></i>
          <input type="password" id="confPass" name="confirm_password" placeholder="Repeat new password" required/>
          <button type="button" class="pw-toggle" onclick="togglePw('confPass',this)"><i class="fas fa-eye"></i></button>
        </div>
      </div>
      <button type="submit" name="set_password" class="btn-submit green">
        <i class="fas fa-save"></i> Save New Password
      </button>
    </form>
    <?php endif; ?>

    <a href="/LYDO/lydo-system/login.php" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Login
    </a>
  </div>
</div>

<script>
// Auto-focus OTP input on step 2
const otpSingle = document.getElementById('otpSingle');
if (otpSingle) { otpSingle.focus(); }

// Password strength
function checkStrength(val) {
  const bar = document.getElementById('strengthBar');
  const lbl = document.getElementById('strengthLabel');
  if (!val) { bar.className = 'strength-bar'; lbl.textContent = ''; return; }
  const strong = val.length >= 10 && /[A-Z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val);
  const medium = val.length >= 8 && (/[A-Z]/.test(val) || /[0-9]/.test(val));
  if (strong)       { bar.className = 'strength-bar strong'; lbl.textContent = '✅ Strong'; lbl.style.color = '#2e7d32'; }
  else if (medium)  { bar.className = 'strength-bar medium'; lbl.textContent = '⚠️ Medium'; lbl.style.color = '#f57f17'; }
  else              { bar.className = 'strength-bar weak';   lbl.textContent = '❌ Weak';   lbl.style.color = '#c62828'; }
}

// Toggle password visibility
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const ico = btn.querySelector('i');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
