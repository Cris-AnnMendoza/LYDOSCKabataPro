<?php
// Test Admin Login Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<h2>Testing Admin Login</h2>";
echo "<pre>";

// Test database connection
try {
    $pdo = db();
    echo "✅ Database connection successful\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Check if admin_users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ admin_users table exists\n\n";
    } else {
        echo "❌ admin_users table does NOT exist\n";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "\n";
    exit;
}

// Check admin records
try {
    $stmt = $pdo->query("SELECT id, full_name, email, role, is_active FROM admin_users");
    $admins = $stmt->fetchAll();
    
    echo "📋 Admin Users Found: " . count($admins) . "\n\n";
    
    if (count($admins) === 0) {
        echo "❌ No admin users found in database!\n";
        echo "Creating default admin...\n\n";
        
        // Create default admin
        $defaultPassword = 'Admin@1234';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $pdo->prepare("INSERT INTO admin_users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['System Administrator', 'admin@lydo.gov.ph', $hashedPassword, 'super_admin', 1]);
        
        echo "✅ Default admin created!\n";
        echo "   Email: admin@lydo.gov.ph\n";
        echo "   Password: Admin@1234\n\n";
    } else {
        foreach ($admins as $admin) {
            echo "Admin ID: {$admin['id']}\n";
            echo "Name: {$admin['full_name']}\n";
            echo "Email: {$admin['email']}\n";
            echo "Role: {$admin['role']}\n";
            echo "Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
            echo "---\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking admins: " . $e->getMessage() . "\n";
    exit;
}

// Test password verification
echo "\n🔐 Testing Password Verification:\n\n";
$testEmail = 'admin@lydo.gov.ph';
$testPassword = 'Admin@1234';

try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
    $stmt->execute([$testEmail]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin account found: {$admin['email']}\n";
        echo "Stored password hash: " . substr($admin['password'], 0, 50) . "...\n\n";
        
        if (password_verify($testPassword, $admin['password'])) {
            echo "✅ PASSWORD VERIFICATION SUCCESSFUL!\n";
            echo "   The password 'Admin@1234' works correctly.\n\n";
            
            echo "🎯 You can now login with:\n";
            echo "   URL: http://localhost/LYDO/lydo-system/login.php\n";
            echo "   Email: admin@lydo.gov.ph\n";
            echo "   Password: Admin@1234\n";
        } else {
            echo "❌ PASSWORD VERIFICATION FAILED!\n";
            echo "   The stored password hash doesn't match 'Admin@1234'\n\n";
            echo "Fixing password...\n";
            
            $newHash = password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE email = ?");
            $stmt->execute([$newHash, $testEmail]);
            
            echo "✅ Password has been reset to: Admin@1234\n";
            echo "   Try logging in again!\n";
        }
    } else {
        echo "❌ Admin account not found or inactive: {$testEmail}\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing login: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='index.php'>← Back to Admin Login</a></p>";
?>
