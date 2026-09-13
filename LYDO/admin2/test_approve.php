<?php
require_once 'config.php';
requireLogin();

$pdo = db();

// Test update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $type = $_POST['type'];
    
    echo "<h3>POST Data Received:</h3>";
    echo "ID: $id<br>";
    echo "Action: $action<br>";
    echo "Type: $type<br><br>";
    
    if ($type === 'president') {
        $isActive = ($action === 'approve') ? 1 : 0;
        
        echo "<h3>Attempting to update:</h3>";
        echo "UPDATE organization_presidents SET is_active = $isActive WHERE id = $id<br><br>";
        
        try {
            $stmt = $pdo->prepare('UPDATE organization_presidents SET is_active = ? WHERE id = ?');
            $result = $stmt->execute([$isActive, $id]);
            
            echo "<h3>Result:</h3>";
            echo "Execute returned: " . ($result ? 'TRUE' : 'FALSE') . "<br>";
            echo "Rows affected: " . $stmt->rowCount() . "<br><br>";
            
            // Verify the update
            $check = $pdo->prepare('SELECT id, full_name, is_active FROM organization_presidents WHERE id = ?');
            $check->execute([$id]);
            $row = $check->fetch();
            
            echo "<h3>Current value in database:</h3>";
            echo "ID: {$row['id']}<br>";
            echo "Name: {$row['full_name']}<br>";
            echo "is_active: {$row['is_active']}<br>";
            
        } catch (Exception $e) {
            echo "<h3>ERROR:</h3>";
            echo $e->getMessage();
        }
    }
    
    echo "<br><br><a href='test_approve.php'>Back</a>";
    exit;
}

// Show test form
$presidents = $pdo->query('SELECT id, full_name, is_active FROM organization_presidents')->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Test Approval</title>
</head>
<body>
<h2>Test President Approval</h2>

<?php foreach ($presidents as $p): ?>
<div style="border:1px solid #ccc; padding:10px; margin:10px 0;">
    <strong><?= htmlspecialchars($p['full_name']) ?></strong> 
    (ID: <?= $p['id'] ?>, Current is_active: <?= $p['is_active'] ?>)
    
    <form method="POST" style="display:inline; margin-left:20px;">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="hidden" name="type" value="president">
        <input type="hidden" name="action" value="approve">
        <button type="submit">Approve (set to 1)</button>
    </form>
    
    <form method="POST" style="display:inline;">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="hidden" name="type" value="president">
        <input type="hidden" name="action" value="reject">
        <button type="submit">Reject (set to 0)</button>
    </form>
</div>
<?php endforeach; ?>

</body>
</html>
