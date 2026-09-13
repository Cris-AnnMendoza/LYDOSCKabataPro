<?php
require_once 'config.php';

$pdo = db();

echo "<h2>Organizations Table Structure</h2>";
echo "<pre>";

try {
    echo "=== ORGANIZATIONS TABLE STRUCTURE ===\n\n";
    
    $columns = $pdo->query("DESCRIBE organizations")->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-25s %-20s %-10s %-10s %-20s %-10s\n", 
        "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 95) . "\n";
    
    foreach ($columns as $col) {
        printf("%-25s %-20s %-10s %-10s %-20s %-10s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Key'],
            $col['Default'] ?? 'NULL',
            $col['Extra']
        );
    }
    
    echo "\n\n=== INDEXES ===\n\n";
    $indexes = $pdo->query("SHOW INDEXES FROM organizations")->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-30s %-20s %-20s\n", "Key Name", "Column", "Unique");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($indexes as $idx) {
        printf("%-30s %-20s %-20s\n",
            $idx['Key_name'],
            $idx['Column_name'],
            $idx['Non_unique'] == 0 ? 'YES' : 'NO'
        );
    }
    
    echo "\n\n=== FOREIGN KEYS ===\n\n";
    $fks = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'local_youth_development_db'
        AND TABLE_NAME = 'organizations'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($fks)) {
        echo "No foreign keys found.\n";
    } else {
        printf("%-30s %-20s %-25s %-20s\n", 
            "Constraint", "Column", "Referenced Table", "Referenced Column");
        echo str_repeat("-", 95) . "\n";
        
        foreach ($fks as $fk) {
            printf("%-30s %-20s %-25s %-20s\n",
                $fk['CONSTRAINT_NAME'],
                $fk['COLUMN_NAME'],
                $fk['REFERENCED_TABLE_NAME'],
                $fk['REFERENCED_COLUMN_NAME']
            );
        }
    }
    
    echo "\n\n=== SAMPLE DATA ===\n\n";
    $count = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    echo "Total organizations: $count\n\n";
    
    if ($count > 0) {
        $samples = $pdo->query("
            SELECT id, name, category, is_active, created_at 
            FROM organizations 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        printf("%-5s %-40s %-20s %-10s %-20s\n", 
            "ID", "Name", "Category", "Active", "Created");
        echo str_repeat("-", 95) . "\n";
        
        foreach ($samples as $org) {
            printf("%-5s %-40s %-20s %-10s %-20s\n",
                $org['id'],
                substr($org['name'], 0, 40),
                substr($org['category'] ?? 'N/A', 0, 20),
                $org['is_active'] ? 'Yes' : 'No',
                date('Y-m-d H:i', strtotime($org['created_at']))
            );
        }
    }
    
    echo "\n\n=== CHECKING FOR PRESIDENT_ID COLUMN ===\n\n";
    $hasPresidentId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'president_id') {
            $hasPresidentId = true;
            echo "✓ president_id column EXISTS\n";
            echo "  Type: {$col['Type']}\n";
            echo "  Null: {$col['Null']}\n";
            echo "  Default: " . ($col['Default'] ?? 'NULL') . "\n";
            break;
        }
    }
    
    if (!$hasPresidentId) {
        echo "✗ president_id column DOES NOT EXIST\n";
        echo "  Run add_organization_president_role.php to add it.\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a> | ";
echo "<a href='add_organization_president_role.php'>Setup Organization President Role →</a></p>";
?>
