<?php
/**
 * Complete Fix for Assistance System Tables
 * Creates all missing tables for the assistance request system
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Assistance System Tables Fix</h2>";
echo "<pre>";

// Check and create assistance_requests table
echo "=== STEP 1: Checking assistance_requests table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'assistance_requests'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM assistance_requests")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        echo "Current columns: " . count($columns) . "\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}\n";
        }
        
        // Add missing columns
        $missingColumns = [
            'organization_id' => 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER id',
            'title' => 'VARCHAR(200) NOT NULL DEFAULT "" AFTER request_type',
            'scheduled_date' => 'DATE DEFAULT NULL AFTER status',
            'decline_reason' => 'TEXT DEFAULT NULL AFTER scheduled_date'
        ];
        
        foreach ($missingColumns as $colName => $colDef) {
            if (!in_array($colName, $columnNames)) {
                echo "  Adding missing column: $colName...\n";
                $pdo->exec("ALTER TABLE assistance_requests ADD COLUMN $colName $colDef");
                echo "  ✓ Added $colName\n";
            }
        }
        
        // Create indexes if they don't exist
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_assist_req_status ON assistance_requests(status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_assist_req_org_id ON assistance_requests(organization_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_assist_req_submitted_by ON assistance_requests(submitted_by)");
            echo "✓ Indexes created/verified\n";
        } catch (PDOException $e) {
            echo "⚠ Index warning: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE assistance_requests (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                organization_id   INT UNSIGNED NOT NULL,
                submitted_by      INT UNSIGNED NOT NULL,
                request_type      VARCHAR(100) NOT NULL,
                title             VARCHAR(200) NOT NULL,
                description       TEXT NOT NULL,
                status            VARCHAR(30) NOT NULL DEFAULT 'submitted',
                scheduled_date    DATE DEFAULT NULL,
                decline_reason    TEXT DEFAULT NULL,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_assist_req_status (status),
                INDEX idx_assist_req_org_id (organization_id),
                INDEX idx_assist_req_submitted_by (submitted_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create assistance_documents table
echo "=== STEP 2: Checking assistance_documents table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'assistance_documents'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM assistance_documents")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}\n";
        }
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE assistance_documents (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                request_id      INT NOT NULL,
                doc_type        VARCHAR(50) NOT NULL,
                file_path       VARCHAR(255) NOT NULL,
                original_name   VARCHAR(255) NOT NULL,
                file_size       INT NOT NULL DEFAULT 0,
                uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_assist_docs_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully (without FK constraint)\n";
        
        // Try to add foreign key if possible
        try {
            $pdo->exec("
                ALTER TABLE assistance_documents 
                ADD CONSTRAINT fk_assist_doc_request 
                FOREIGN KEY (request_id) REFERENCES assistance_requests(id) ON DELETE CASCADE
            ");
            echo "✓ Foreign key constraint added\n";
        } catch (PDOException $e) {
            echo "⚠ Warning: Could not add FK constraint: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create assistance_timeline table
echo "=== STEP 3: Checking assistance_timeline table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'assistance_timeline'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM assistance_timeline")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}\n";
        }
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE assistance_timeline (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                request_id  INT NOT NULL,
                status      VARCHAR(100) NOT NULL,
                note        TEXT DEFAULT NULL,
                done_by     INT UNSIGNED DEFAULT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_assist_timeline_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully (without FK constraint)\n";
        
        try {
            $pdo->exec("
                ALTER TABLE assistance_timeline 
                ADD CONSTRAINT fk_assist_timeline_request 
                FOREIGN KEY (request_id) REFERENCES assistance_requests(id) ON DELETE CASCADE
            ");
            echo "✓ Foreign key constraint added\n";
        } catch (PDOException $e) {
            echo "⚠ Warning: Could not add FK constraint: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Check and create assistance_comments table
echo "=== STEP 4: Checking assistance_comments table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'assistance_comments'");
    $exists = $result->rowCount() > 0;
    
    if ($exists) {
        echo "✓ Table exists\n";
        $columns = $pdo->query("SHOW COLUMNS FROM assistance_comments")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns: " . count($columns) . "\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}\n";
        }
    } else {
        echo "✗ Table does NOT exist - Creating...\n";
        $pdo->exec("
            CREATE TABLE assistance_comments (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                request_id  INT NOT NULL,
                author_id   INT UNSIGNED NOT NULL,
                author_type VARCHAR(20) NOT NULL,
                comment     TEXT NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_assist_comments_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully (without FK constraint)\n";
        
        try {
            $pdo->exec("
                ALTER TABLE assistance_comments 
                ADD CONSTRAINT fk_assist_comment_request 
                FOREIGN KEY (request_id) REFERENCES assistance_requests(id) ON DELETE CASCADE
            ");
            echo "✓ Foreign key constraint added\n";
        } catch (PDOException $e) {
            echo "⚠ Warning: Could not add FK constraint: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Verify all queries work
echo "=== VERIFICATION ===\n";
try {
    echo "Testing assistance_requests query...\n";
    $stmt = $pdo->prepare('SELECT r.*,o.name as org_name,u.first_name,u.last_name,u.email as user_email FROM assistance_requests r JOIN organizations o ON o.id=r.organization_id JOIN youth_users u ON u.id=r.submitted_by WHERE r.id=? LIMIT 1');
    $stmt->execute([999]);
    echo "✓ Main query works!\n\n";
    
    echo "Testing assistance_documents query...\n";
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM assistance_documents WHERE request_id=?');
    $stmt->execute([999]);
    echo "✓ Documents query works!\n\n";
    
    echo "Testing assistance_timeline query...\n";
    $stmt = $pdo->prepare('SELECT * FROM assistance_timeline WHERE request_id=? ORDER BY created_at DESC');
    $stmt->execute([999]);
    echo "✓ Timeline query works!\n\n";
    
    echo "Testing assistance_comments query...\n";
    $stmt = $pdo->prepare('SELECT * FROM assistance_comments WHERE request_id=? ORDER BY created_at DESC');
    $stmt->execute([999]);
    echo "✓ Comments query works!\n\n";
    
    echo "Testing list query with document count...\n";
    $stmt = $pdo->prepare("SELECT r.*,o.name as org_name,u.first_name,u.last_name,(SELECT COUNT(*) FROM assistance_documents d WHERE d.request_id=r.id) as doc_count FROM assistance_requests r JOIN organizations o ON o.id=r.organization_id JOIN youth_users u ON u.id=r.submitted_by ORDER BY r.created_at DESC LIMIT 10");
    $stmt->execute();
    echo "✓ List query works!\n\n";
    
    echo "=== ALL QUERIES SUCCESSFUL ===\n";
    echo "The assistance.php page should now work without errors!\n";
    
} catch (PDOException $e) {
    echo "✗ Error during verification: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
try {
    $reqCount = $pdo->query("SELECT COUNT(*) FROM assistance_requests")->fetchColumn();
    $docCount = $pdo->query("SELECT COUNT(*) FROM assistance_documents")->fetchColumn();
    $timelineCount = $pdo->query("SELECT COUNT(*) FROM assistance_timeline")->fetchColumn();
    $commentCount = $pdo->query("SELECT COUNT(*) FROM assistance_comments")->fetchColumn();
    
    echo "Total Assistance Requests: $reqCount\n";
    echo "Total Documents: $docCount\n";
    echo "Total Timeline Entries: $timelineCount\n";
    echo "Total Comments: $commentCount\n\n";
    
    echo "✓ All assistance tables are properly configured!\n";
    echo "You can now access assistance.php without errors.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>✓ Fix complete!</strong> <a href='assistance.php'>Go to Assistance Page →</a></p>";
?>
