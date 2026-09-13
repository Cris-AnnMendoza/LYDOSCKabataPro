
<?php
require_once __DIR__ . '/shared/config.php';

$pdo   = db();
$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$tokenData = null;

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_type ENUM('youth','admin') NOT NULL DEFAULT 'youth',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!$token) {
    $error = 'Invalid or missing reset token.';
} else {
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used = FALSE AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();
    if (!$tokenData) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenData) {
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($tokenData['user_type'] === 'youth') {
            $pdo->prepare('UPDATE youth_users SET password = ? WHERE email = ?')
                ->execute([$hash, $tokenData['email']]);
        } else {
            $pdo->prepare('UPDATE admin_users SET password = ? WHERE email = ?')
                ->execute([$hash, $tokenData['email']]);
        }

        // Mark token as used
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
            ->execute([$token]);

        $success = 'Password changed successfully! You can now log in with your new password.';
        $tokenData = null; // Hide form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Reset Password – LYDO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:linear-gradient(160deg,#0d3b6e 0%,#1565c0 55%,#1b5e20 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:420px;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0d3b6e,#1565c0);padding:28px;text-align:center;color:#fff}
.icon-wrap{width:60px;height:60px;border-radius:16px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px}
.card-top h1{font-size:1.2rem;font-weight:800;margin-bottom:4px}
.card-top p{font-size:.82rem;opacity:.75}
.card-body{padding:28px}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:16px}
.fg label{font-size:.85rem;font-weight:600;color:#1e293b}
.pw-wrap{position:relative}
.pw-wrap i.ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem;pointer-events:none}
.pw-wrap input{width:100%;padding:12px 44px 12px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.92rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s}
.pw-wrap input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.1);background:#fff}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.88rem;padding:4px;transition:.2s}
.pw-toggle:hover{color:#1565c0}
.strength-bar{height:4px;border-radius:2px;margin-top:6px;transition:.3s;background:#e2e8f0}
.strength-bar.weak{background:#ef5350;width:33%}
.strength-bar.medium{background:#ffa726;width:66%}
.strength-bar.strong{background:#43a047;width:100%}
.strength-label{font-size:.72rem;margin-top:3px}
.btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;box-shadow:0 4px 14px rgba(21,101,192,.3)}
.btn-submit:hover{transform:translateY(-2px)}
.alert{padding:12px 15px;border-radius:10px;font-size:.86rem;font-weight:500;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px}
.alert.error{background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2)}
.alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid rgba(46,125,50,.2)}
.back-link{display:flex;align-items:center;gap:6px;font-size:.85rem;color:#64748b;text-decoration:none;margin-top:18px;justify-content:center;transition:.2s}
.back-link:hover{color:#1565c0}
.email-badge{background:#e3f2fd;color:#1565c0;padding:6px 14px;border-radius:8px;font-size:.82rem;font-weight:600;text-align:center;margin-bottom:18px;display:flex;align-items:center;gap:7px;justify-content:center}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="icon-wrap"><i class="fas fa-lock-open"></i></div>
    <h1>Reset Password</h1>
    <p>Enter your new password below</p>
  </div>
  <div class="card-body">

    <?php if ($error): ?>
    <div class="alert error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <a href="/LYDO/lydo-system/forgot_password.php" class="btn-submit" style="text-decoration:none;margin-top:0">
      <i class="fas fa-redo"></i> Request New Link
    </a>

    <?php elseif ($success): ?>
    <div class="alert success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <a href="/LYDO/lydo-system/login.php" class="btn-submit" style="text-decoration:none;background:linear-gradient(135deg,#2e7d32,#43a047)">
      <i class="fas fa-sign-in-alt"></i> Go to Login
    </a>

    <?php elseif ($tokenData): ?>
    <div class="email-badge">
      <i class="fas fa-envelope"></i>
      Resetting password for: <strong><?= htmlspecialchars($tokenData['email']) ?></strong>
    </div>

    <form method="POST">
      <div class="fg">
        <label>New Password</label>
        <div class="pw-wrap">
          <i class="fas fa-lock ico"></i>
          <input type="password" id="newPass" name="new_password" placeholder="Min. 8 characters" required
                 oninput="checkStrength(this.value)"/>
          <button type="button" class="pw-toggle" onclick="togglePw('newPass',this)"><i class="fas fa-eye"></i></button>
        </div>
        <div class="strength-bar" id="strengthBar"></div>
        <div class="strength-label" id="strengthLabel" style="color:#94a3b8"></div>
      </div>

      <div class="fg">
        <label>Confirm New Password</label>
        <div class="pw-wrap">
          <i class="fas fa-lock ico"></i>
          <input type="password" id="confirmPass" name="confirm_password" placeholder="Repeat new password" required/>
          <button type="button" class="pw-toggle" onclick="togglePw('confirmPass',this)"><i class="fas fa-eye"></i></button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
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
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const ico = btn.querySelector('i');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

function checkStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (!val) { bar.className = 'strength-bar'; label.textContent = ''; return; }
  const strong = val.length >= 10 && /[A-Z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val);
  const medium = val.length >= 8 && (/[A-Z]/.test(val) || /[0-9]/.test(val));
  if (strong) {
    bar.className = 'strength-bar strong';
    label.textContent = '✅ Strong password';
    label.style.color = '#2e7d32';
  } else if (medium) {
    bar.className = 'strength-bar medium';
    label.textContent = '⚠️ Medium — add numbers or symbols';
    label.style.color = '#f57f17';
  } else {
    bar.className = 'strength-bar weak';
    label.textContent = '❌ Weak — too short';
    label.style.color = '#c62828';
  }
}
</script>
</body>
</html>
