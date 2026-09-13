<?php
/**
 * CHECK-IN/CHECK-OUT SYSTEM VERIFICATION SCRIPT
 * 
 * Run this file para makita kung complete ang setup
 * Access: http://localhost/LYDO/lydo-system/admin2/test_checkin_setup.php
 */

require_once 'config.php';

$pdo = db();
$results = [];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Check-in/Check-out System Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .content {
            padding: 30px;
        }
        .test-item {
            background: #f8fafc;
            border-left: 4px solid #cbd5e1;
            padding: 16px 20px;
            margin-bottom: 16px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .test-item.success {
            border-left-color: #22c55e;
            background: #f0fdf4;
        }
        .test-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .test-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .test-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .test-desc {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }
        .icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .icon.success { background: #22c55e; color: #fff; }
        .icon.error { background: #ef4444; color: #fff; }
        .icon.warning { background: #f59e0b; color: #fff; }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .summary-card.success { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .summary-card.error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .summary-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .summary-label {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 600;
        }
        code {
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #0f172a;
        }
        .btn {
            display: inline-block;
            background: #1565c0;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0d47a1;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔍 Check-in/Check-out System Test</h1>
            <p>Verifying database tables, columns, and configuration</p>
        </div>
        <div class='content'>";

// Test 1: event_checkins table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'event_checkins'");
    if ($stmt->rowCount() > 0) {
        $results[] = ['success', 'event_checkins Table', 'Table exists in database'];
    } else {
        $results[] = ['error', 'event_checkins Table', 'Table does NOT exist! Run database migration.'];
    }
} catch (Exception $e) {
    $results[] = ['error', 'event_checkins Table', 'Error: ' . $e->getMessage()];
}

// Test 2: Check columns in event_checkins
try {
    $columns = $pdo->query("SHOW COLUMNS FROM event_checkins")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['id', 'event_id', 'user_id', 'checked_in_at', 'checked_out_at', 'checkin_photo', 'checkout_photo'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (empty($missingColumns)) {
        $results[] = ['success', 'event_checkins Columns', 'All required columns exist: ' . implode(', ', $requiredColumns)];
    } else {
        $results[] = ['error', 'event_checkins Columns', 'Missing columns: ' . implode(', ', $missingColumns)];
    }
} catch (Exception $e) {
    $results[] = ['error', 'event_checkins Columns', 'Error: ' . $e->getMessage()];
}

// Test 3: events table check-in/check-out columns
try {
    $columns = $pdo->query("SHOW COLUMNS FROM events")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['checkin_code', 'checkin_open', 'checkout_open', 'qr_token'];
    $missingColumns = array_diff($requiredColumns, $columnNames);
    
    if (empty($missingColumns)) {
        $results[] = ['success', 'events Table Columns', 'Check-in/out columns exist: ' . implode(', ', $requiredColumns)];
    } else {
        $results[] = ['warning', 'events Table Columns', 'Missing columns: ' . implode(', ', $missingColumns) . ' (Will be auto-created)'];
    }
} catch (Exception $e) {
    $results[] = ['error', 'events Table Columns', 'Error: ' . $e->getMessage()];
}

// Test 4: Check if uploads directory exists
$uploadsPath = dirname(__DIR__) . '/uploads/event_photos';
if (file_exists($uploadsPath) && is_dir($uploadsPath)) {
    if (is_writable($uploadsPath)) {
        $results[] = ['success', 'Upload Directory', 'Directory exists and is writable: ' . $uploadsPath];
    } else {
        $results[] = ['warning', 'Upload Directory', 'Directory exists but is NOT writable. Change permissions to 775 or 755'];
    }
} else {
    $results[] = ['warning', 'Upload Directory', 'Directory does not exist. Will be auto-created on first upload: ' . $uploadsPath];
}

// Test 5: Check if there are any events with check-in codes
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE checkin_code IS NOT NULL AND checkin_code != ''");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        $results[] = ['success', 'Events with Check-in Codes', "$count event(s) have check-in codes generated"];
    } else {
        $results[] = ['warning', 'Events with Check-in Codes', 'No events have check-in codes yet. Create an event to test.'];
    }
} catch (Exception $e) {
    $results[] = ['error', 'Events with Check-in Codes', 'Error: ' . $e->getMessage()];
}

// Test 6: Check if there are any check-ins
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_checkins");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        $results[] = ['success', 'Check-in Records', "$count check-in record(s) found in database"];
        
        // Check how many have photos
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_checkins WHERE checkin_photo IS NOT NULL OR checkout_photo IS NOT NULL");
        $photoCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($photoCount > 0) {
            $results[] = ['success', 'Photo Records', "$photoCount check-in(s) have photos uploaded"];
        } else {
            $results[] = ['warning', 'Photo Records', 'No photos uploaded yet. Test photo upload feature.'];
        }
    } else {
        $results[] = ['warning', 'Check-in Records', 'No check-ins yet. Test the check-in feature.'];
    }
} catch (Exception $e) {
    $results[] = ['error', 'Check-in Records', 'Error: ' . $e->getMessage()];
}

// Calculate summary
$successCount = count(array_filter($results, fn($r) => $r[0] === 'success'));
$errorCount = count(array_filter($results, fn($r) => $r[0] === 'error'));
$warningCount = count(array_filter($results, fn($r) => $r[0] === 'warning'));
$totalTests = count($results);

// Display summary
echo "<div class='summary'>
    <div class='summary-card success'>
        <div class='summary-value'>$successCount</div>
        <div class='summary-label'>PASSED</div>
    </div>
    <div class='summary-card error'>
        <div class='summary-value'>$errorCount</div>
        <div class='summary-label'>FAILED</div>
    </div>
    <div class='summary-card'>
        <div class='summary-value'>$warningCount</div>
        <div class='summary-label'>WARNINGS</div>
    </div>
</div>";

// Display all test results
foreach ($results as $result) {
    [$type, $title, $desc] = $result;
    $iconSymbol = $type === 'success' ? '✓' : ($type === 'error' ? '✗' : '!');
    
    echo "<div class='test-item $type'>
        <div class='test-title'>
            <span class='icon $type'>$iconSymbol</span>
            $title
        </div>
        <div class='test-desc'>$desc</div>
    </div>";
}

// Display conclusion
if ($errorCount === 0) {
    echo "<div style='background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px'>
        <h2 style='margin-bottom: 8px'>✅ System Ready!</h2>
        <p style='opacity: 0.9'>Check-in/Check-out system is properly configured. You can now test the features.</p>
        <a href='events.php' class='btn' style='background: #fff; color: #16a34a; margin-top: 12px'>Go to Events Management</a>
    </div>";
} else {
    echo "<div style='background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px'>
        <h2 style='margin-bottom: 8px'>⚠️ Issues Found</h2>
        <p style='opacity: 0.9'>$errorCount error(s) need to be fixed before the system can work properly.</p>
    </div>";
}

echo "<div style='margin-top: 20px; padding: 16px; background: #f1f5f9; border-radius: 8px; font-size: 0.85rem; color: #475569'>
    <strong>📖 Next Steps:</strong><br>
    1. Review the test results above<br>
    2. Fix any errors (usually database migration needed)<br>
    3. Read the testing guide: <code>TESTING_CHECKIN_CHECKOUT.md</code><br>
    4. Create a test event and try checking in<br>
    5. Upload a photo during check-out<br>
    6. Verify photos display in admin attendance list
</div>";

echo "        </div>
    </div>
</body>
</html>";
?>
