<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/shared/config.php';

// Already logged in — redirect to correct dashboard
if (!empty($_SESSION['admin_id']))         { header('Location: admin2/dashboard.php');        exit; }
if (!empty($_SESSION['org_president_id'])) { header('Location: org-president/dashboard.php'); exit; }
if (!empty($_SESSION['user_id']))          { header('Location: shared/youth/dashboard.php'); exit; }

$error = '';
$errorType = 'error'; // default: red, can be 'warning' (yellow) or 'info' (orange)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';
    $loginAs  = $_POST['login_as'] ?? 'auto'; // auto, admin, president, youth

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $pdo = db();
        $authenticated = false;

        // ── Try authentication based on login_as selection ───────────
        
        // AUTO MODE: Try all in order (Admin → President → Youth)
        if ($loginAs === 'auto') {
            // 1. Check admin table first
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Admin login success
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin']    = [
                    'id'        => $admin['id'],
                    'full_name' => $admin['full_name'],
                    'email'     => $admin['email'],
                    'role'      => $admin['role'],
                    'barangay'  => $admin['barangay'],
                ];
                $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')
                    ->execute([$admin['id']]);
                header('Location: admin2/dashboard.php');
                exit;
            }

            // 2. Check organization_presidents table
            $stmt = $pdo->prepare('SELECT op.*, o.name as organization_name FROM organization_presidents op JOIN organizations o ON o.id = op.organization_id WHERE op.email = ? AND op.is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $president = $stmt->fetch();

            if ($president && password_verify($password, $president['password'])) {
                // Organization President login success
                session_regenerate_id(true);
                $_SESSION['org_president_id'] = $president['id'];
                $_SESSION['org_president'] = [
                    'id'                => $president['id'],
                    'organization_id'   => $president['organization_id'],
                    'organization_name' => $president['organization_name'],
                    'full_name'         => $president['full_name'],
                    'email'             => $president['email'],
                    'role'              => 'organization_president'
                ];
                $pdo->prepare('UPDATE organization_presidents SET last_login = NOW() WHERE id = ?')
                    ->execute([$president['id']]);
                $pdo->prepare('INSERT INTO organization_president_activity_log (president_id, action, ip_address) VALUES (?, ?, ?)')
                    ->execute([$president['id'], 'Login', $_SERVER['REMOTE_ADDR'] ?? null]);
                header('Location: org-president/dashboard.php');
                exit;
            }

            // 3. Check youth users table
            $stmt2 = $pdo->prepare('SELECT * FROM youth_users WHERE email = ? LIMIT 1');
            $stmt2->execute([$email]);
            $user = $stmt2->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] === 'pending') {
                        $error = 'PENDING APPROVAL - Your registration is waiting for admin approval. You will be notified once approved.';
                        $errorType = 'warning';
                    } elseif ($user['status'] === 'rejected') {
                        $error = 'Your registration has been rejected. Please contact the LYDO office for assistance.';
                    } else {
                        // Youth login success
                        session_regenerate_id(true);
                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['user_email'] = $user['email'];
                        header('Location: shared/youth/dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Incorrect password. Please try again.';
                }
            } else {
                $error = 'NO EXISTING ACCOUNT - Please register first to create your account.';
                $errorType = 'info';
            }
        }
        
        // SPECIFIC LOGIN MODE (admin, president, or youth)
        else if ($loginAs === 'admin') {
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin']    = [
                    'id'        => $admin['id'],
                    'full_name' => $admin['full_name'],
                    'email'     => $admin['email'],
                    'role'      => $admin['role'],
                    'barangay'  => $admin['barangay'],
                ];
                $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
                header('Location: admin2/dashboard.php');
                exit;
            } else {
                $error = 'Admin account not found or incorrect password.';
            }
        }
        
        else if ($loginAs === 'president') {
            $stmt = $pdo->prepare('SELECT op.*, o.name as organization_name FROM organization_presidents op JOIN organizations o ON o.id = op.organization_id WHERE op.email = ? AND op.is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $president = $stmt->fetch();
            
            if ($president && password_verify($password, $president['password'])) {
                session_regenerate_id(true);
                $_SESSION['org_president_id'] = $president['id'];
                $_SESSION['org_president'] = [
                    'id'                => $president['id'],
                    'organization_id'   => $president['organization_id'],
                    'organization_name' => $president['organization_name'],
                    'full_name'         => $president['full_name'],
                    'email'             => $president['email'],
                    'role'              => 'organization_president'
                ];
                $pdo->prepare('UPDATE organization_presidents SET last_login = NOW() WHERE id = ?')->execute([$president['id']]);
                $pdo->prepare('INSERT INTO organization_president_activity_log (president_id, action, ip_address) VALUES (?, ?, ?)')->execute([$president['id'], 'Login', $_SERVER['REMOTE_ADDR'] ?? null]);
                header('Location: org-president/dashboard.php');
                exit;
            } else {
                $error = 'Organization President account not found or incorrect password.';
            }
        }
        
        else if ($loginAs === 'youth') {
            $stmt = $pdo->prepare('SELECT * FROM youth_users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'pending') {
                    $error = 'PENDING APPROVAL - Your registration is waiting for admin approval.';
                    $errorType = 'warning';
                } elseif ($user['status'] === 'rejected') {
                    $error = 'Your registration has been rejected. Please contact the LYDO office.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    header('Location: shared/youth/dashboard.php');
                    exit;
                }
            } else {
                $error = 'Youth member account not found or incorrect password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Login – LYDO Sta. Cruz, Laguna</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:grid;grid-template-columns:1fr 1fr;background:#f8fafc}

/* ── LEFT PANEL ── */
.left{background:linear-gradient(160deg,#0d3b6e 0%,#1565c0 55%,#1b5e20 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;position:relative;overflow:hidden}
.left::before{content:'';position:absolute;width:420px;height:420px;border-radius:50%;background:rgba(255,255,255,.05);top:-120px;right:-100px}
.left::after{content:'';position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.05);bottom:-70px;left:-60px}
.left-content{position:relative;z-index:1;text-align:center}
.logo-wrap{width:110px;height:110px;border-radius:28px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:3rem;color:#fff;margin:0 auto 22px;box-shadow:0 8px 32px rgba(0,0,0,.2)}
.left h1{font-size:2.6rem;font-weight:900;color:#fff;letter-spacing:.04em;margin-bottom:6px}
.left .org{font-size:.9rem;color:rgba(255,255,255,.7);margin-bottom:20px}
.divider{width:40px;height:3px;background:rgba(255,255,255,.4);border-radius:2px;margin:0 auto 14px}
.loc{color:rgba(255,255,255,.8);font-size:.88rem;margin-bottom:10px}
.tagline{font-size:.82rem;color:rgba(255,255,255,.55);font-style:italic;max-width:280px;margin:0 auto 28px;line-height:1.6}
.info-pills{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}
.pill{padding:5px 14px;border-radius:50px;font-size:.75rem;font-weight:600;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:rgba(255,255,255,.85)}
.left-foot{position:absolute;bottom:18px;font-size:.72rem;color:rgba(255,255,255,.3);z-index:1}

/* ── RIGHT PANEL ── */
.right{display:flex;align-items:center;justify-content:center;background:#fff;padding:48px 40px;position:relative}
.back-link{position:absolute;top:24px;left:28px;display:flex;align-items:center;gap:7px;font-size:.85rem;color:#64748b;font-weight:500;text-decoration:none;transition:.2s}
.back-link:hover{color:#1565c0}
.form-box{width:100%;max-width:400px}
.form-icon{width:62px;height:62px;border-radius:16px;background:linear-gradient(135deg,#0d3b6e,#1565c0);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;margin:0 auto 18px}
.form-box h2{font-size:1.8rem;font-weight:800;text-align:center;margin-bottom:6px;color:#1e293b}
.form-sub{text-align:center;color:#64748b;font-size:.88rem;margin-bottom:28px;line-height:1.5}

.fg label{font-size:.85rem;font-weight:600;color:#1e293b}
.iw{position:relative;display:flex;align-items:center}
.iw i.ico{position:absolute;left:13px;color:#94a3b8;font-size:.88rem;pointer-events:none;z-index:1}
.iw input,.iw select{width:100%;padding:12px 42px 12px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.92rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s}
.iw input:focus,.iw select:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12);background:#fff}
.iw select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 12px center;background-size:18px;padding-right:38px}
.alert{padding:12px 16px;border-radius:10px;font-size:.88rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px;background:#ffebee;color:#c62828;border:1px solid rgba(198,40,40,.2)}
.alert.warning{background:#fff8e1;color:#f57c00;border:1px solid rgba(245,124,0,.2)}
.alert.info{background:#fff3e0;color:#e65100;border:1px solid rgba(230,81,0,.2)}
.toggle-pw{position:absolute;right:11px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.88rem;padding:4px;transition:.2s}
.toggle-pw:hover{color:#1565c0}
.opts{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:8px}
.chk-label{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;color:#475569;user-select:none}
.chk-label input{width:16px;height:16px;accent-color:#1565c0;cursor:pointer}
.forgot{font-size:.85rem;color:#1565c0;font-weight:600;text-decoration:none}
.forgot:hover{text-decoration:underline}
.btn-login{width:100%;padding:13px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;box-shadow:0 4px 14px rgba(21,101,192,.35)}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(21,101,192,.45)}
.divider-or{display:flex;align-items:center;gap:12px;margin:20px 0;color:#94a3b8;font-size:.85rem}
.divider-or::before,.divider-or::after{content:'';flex:1;height:1px;background:#e2e8f0}
.register-link{text-align:center;font-size:.88rem;color:#475569}
.register-link a{color:#1565c0;font-weight:700;text-decoration:none}
.register-link a:hover{text-decoration:underline}

@media(max-width:768px){body{grid-template-columns:1fr}.left{display:none}.right{padding:32px 24px}}
</style>
</head>
<body>

<!-- LEFT -->
<div class="left">
  <div class="left-content">
    <div class="logo-wrap"><img src="/LYDO/lydo-logo.png" alt="LYDO" style="width:70%;height:70%;object-fit:contain"/></div>
    <h1>LYDO</h1>
    <p class="org">Local Youth Development Office</p>
    <div class="divider"></div>
    <p class="loc"><i class="fas fa-map-marker-alt" style="color:#a5d6a7;margin-right:5px"></i>Sta. Cruz, Laguna</p>
    <p class="tagline">"Empowering the Youth of Sta. Cruz Laguna"</p>
    <div class="info-pills">
      <span class="pill"><i class="fas fa-users"></i> Youth Members</span>
      <span class="pill"><i class="fas fa-graduation-cap"></i> Programs</span>
      <span class="pill"><i class="fas fa-calendar"></i> Events</span>
      <span class="pill"><i class="fas fa-shield-alt"></i> Admin Portal</span>
    </div>
  </div>
  <p class="left-foot">© 2026 Municipal Government of Sta. Cruz, Laguna</p>
</div>

<!-- RIGHT -->
<div class="right">
        <a href="/LYDO/index.html" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>

  <div class="form-box">
    <div class="form-icon"><i class="fas fa-sign-in-alt"></i></div>
    <h2>Welcome Back!</h2>
    <p class="form-sub">One login for everyone — youth members, organization presidents, and administrators.</p>

    <!-- Login Type Selector -->
    <div class="fg">
      <label for="login_as_select">Login As</label>
      <div class="iw">
        <i class="fas fa-user-tag ico"></i>
        <select id="login_as_select" name="login_as" style="width:100%;padding:12px 42px 12px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.92rem;color:#1e293b;background:#f8fafc;outline:none;transition:.2s;cursor:pointer" required>
          <option value="" disabled selected>Select your account type</option>
          <option value="admin">Admin/Staff</option>
          <option value="president">Organization President</option>
          <option value="youth">Youth Member</option>
        </select>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert <?= $errorType === 'warning' ? 'warning' : ($errorType === 'info' ? 'info' : '') ?>">
        <i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
      <input type="hidden" name="login_as" id="login_as" value="auto">
      <div class="fg">
        <label for="email">Email Address</label>
        <div class="iw">
          <i class="fas fa-envelope ico"></i>
          <input type="email" id="email" name="email" placeholder="Enter your email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email"/>
        </div>
      </div>

      <div class="fg">
        <label for="password">Password</label>
        <div class="iw">
          <i class="fas fa-lock ico"></i>
          <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password"/>
          <button type="button" class="toggle-pw" onclick="togglePw()">
            <i class="fas fa-eye-slash" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="opts">
        <label class="chk-label">
          <input type="checkbox" name="remember"/> Remember Me
        </label>
        <a href="/LYDO/lydo-system/forgot_password.php" class="forgot">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Login
      </button>
    </form>

    <div class="divider-or">or</div>
    <p class="register-link">
      New youth member? <a href="/LYDO/index.html#get-started">Register here</a>
    </p>
  </div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { 
    inp.type = 'text'; 
    ico.className = 'fas fa-eye'; // Show eye without slash when visible
  }
  else { 
    inp.type = 'password'; 
    ico.className = 'fas fa-eye-slash'; // Show eye with slash when hidden
  }
}

// Auto-hide error/warning messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const alert = document.querySelector('.alert');
  if (alert) {
    setTimeout(function() {
      alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(function() {
        alert.style.display = 'none';
      }, 500);
    }, 5000); // 5 seconds
  }
});
</script>
</body>
</html>
