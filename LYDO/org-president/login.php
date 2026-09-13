<?php
session_start();
require_once __DIR__ . '/../shared/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $pdo = db();
        
        // Find organization president
        $stmt = $pdo->prepare('
            SELECT op.*, o.name as organization_name 
            FROM organization_presidents op
            JOIN organizations o ON o.id = op.organization_id
            WHERE op.email = ? AND op.is_active = 1
        ');
        $stmt->execute([$email]);
        $president = $stmt->fetch();
        
        if ($president && password_verify($password, $president['password'])) {
            // Set session
            $_SESSION['org_president_id'] = $president['id'];
            $_SESSION['org_president'] = [
                'id' => $president['id'],
                'organization_id' => $president['organization_id'],
                'organization_name' => $president['organization_name'],
                'full_name' => $president['full_name'],
                'email' => $president['email'],
                'role' => 'organization_president'
            ];
            
            // Update last login
            $pdo->prepare('UPDATE organization_presidents SET last_login = NOW() WHERE id = ?')
                ->execute([$president['id']]);
            
            // Log activity
            $pdo->prepare('INSERT INTO organization_president_activity_log (president_id, action, ip_address) VALUES (?, ?, ?)')
                ->execute([$president['id'], 'Login', $_SERVER['REMOTE_ADDR'] ?? null]);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization President Login - LYDO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #7b1fa2, #9c27b0);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .login-header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #7b1fa2;
            box-shadow: 0 0 0 4px rgba(123, 31, 162, 0.1);
        }
        .error-message {
            background: #fee;
            border-left: 4px solid #c62828;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #7b1fa2, #9c27b0);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(123, 31, 162, 0.3);
        }
        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            border-top: 1px solid #f1f5f9;
        }
        .login-footer a {
            color: #7b1fa2;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #94a3b8;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-users-cog"></i>
            <h1>Organization President</h1>
            <p>Local Youth Development Office</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <div class="divider">Or login as</div>
            <a href="/LYDO/lydo-system/login.php"><i class="fas fa-user-shield"></i> LYDO Admin/Staff</a>
            <span style="margin: 0 10px; color: #cbd5e1;">|</span>
            <a href="/LYDO/index.html"><i class="fas fa-user"></i> Youth Member</a>
        </div>
    </div>
</body>
</html>
