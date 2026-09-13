<?php
/**
 * Fix Accreditation Workflow Table
 * Diagnoses and fixes the accreditation_workflow table structure
 */

require_once 'config.php';

$pdo = db();

echo "<h2>Accreditation Workflow Table Fix</h2>";
echo "<pre>";

// Step 1: Check if table exists
echo "=== STEP 1: Checking if accreditation_workflow table exists ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_workflow'");
    $exists = $result->rowCount() > 0;
    echo $exists ? "✓ Table exists\n\n" : "✗ Table does NOT exist\n\n";
    
    if (!$exists) {
        echo "Creating accreditation_workflow table...\n";
        
        // First, let's check if accreditation_applications exists
        $appTableExists = $pdo->query("SHOW TABLES LIKE 'accreditation_applications'")->rowCount() > 0;
        if (!$appTableExists) {
            echo "✗ accreditation_applications table doesn't exist. Creating it first...\n";
            // Will be created in Step 3
        }
        
        // Create without foreign key first
        $pdo->exec("
            CREATE TABLE accreditation_workflow (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                application_id  INT NOT NULL,
                step            VARCHAR(100) NOT NULL,
                status          VARCHAR(20) NOT NULL DEFAULT 'pending',
                done_at         TIMESTAMP NULL DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully (without constraints)\n";
        
        // Create index
        $pdo->exec("CREATE INDEX idx_accred_workflow_app_id ON accreditation_workflow(application_id)");
        echo "✓ Index created\n";
        
        // Try to add foreign key if parent table exists
        if ($appTableExists) {
            try {
                $pdo->exec("
                    ALTER TABLE accreditation_workflow 
                    ADD CONSTRAINT fk_workflow_app 
                    FOREIGN KEY (application_id) REFERENCES accreditation_applications(id) ON DELETE CASCADE
                ");
                echo "✓ Foreign key constraint added\n\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: Could not add foreign key constraint: " . $e->getMessage() . "\n\n";
            }
        }
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 2: Check table structure
echo "=== STEP 2: Checking table structure ===\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM accreditation_workflow")->fetchAll(PDO::FETCH_ASSOC);
    echo "Current columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
    echo "\n";
    
    // Check if application_id exists
    $hasAppId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'application_id') {
            $hasAppId = true;
            break;
        }
    }
    
    if (!$hasAppId) {
        echo "✗ Missing application_id column! Attempting to add it...\n";
        $pdo->exec("ALTER TABLE accreditation_workflow ADD COLUMN application_id INT NOT NULL AFTER id");
        echo "✓ application_id column added\n";
        
        // Add foreign key
        $pdo->exec("
            ALTER TABLE accreditation_workflow 
            ADD CONSTRAINT fk_workflow_app 
            FOREIGN KEY (application_id) REFERENCES accreditation_applications(id) ON DELETE CASCADE
        ");
        echo "✓ Foreign key constraint added\n\n";
    } else {
        echo "✓ application_id column exists\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 3: Check accreditation_applications table
echo "=== STEP 3: Checking accreditation_applications table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_applications'");
    $exists = $result->rowCount() > 0;
    echo $exists ? "✓ Table exists\n" : "✗ Table does NOT exist\n";
    
    if ($exists) {
        // Check current columns
        $columns = $pdo->query("SHOW COLUMNS FROM accreditation_applications")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current columns:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']}\n";
        }
        echo "\n";
        
        // Check for required columns and add if missing
        $columnNames = array_column($columns, 'Field');
        $requiredColumns = [
            'organization_name' => "VARCHAR(200) NOT NULL DEFAULT ''",
            'category' => "VARCHAR(100) NOT NULL DEFAULT ''",
            'barangay' => "VARCHAR(100) DEFAULT NULL",
            'contact_person' => "VARCHAR(200) DEFAULT NULL",
            'contact_email' => "VARCHAR(200) DEFAULT NULL",
            'contact_phone' => "VARCHAR(50) DEFAULT NULL",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'submitted'",
            'certificate_no' => "VARCHAR(50) DEFAULT NULL",
            'valid_until' => "DATE DEFAULT NULL",
            'rejection_reason' => "TEXT DEFAULT NULL",
            'reviewed_by' => "INT DEFAULT NULL",
            'reviewed_at' => "TIMESTAMP NULL DEFAULT NULL"
        ];
        
        foreach ($requiredColumns as $colName => $colDef) {
            if (!in_array($colName, $columnNames)) {
                echo "  Adding missing column: $colName...\n";
                $pdo->exec("ALTER TABLE accreditation_applications ADD COLUMN $colName $colDef");
                echo "  ✓ Added $colName\n";
            }
        }
        echo "\n";
        
    } else {
        echo "Creating accreditation_applications table...\n";
        $pdo->exec("
            CREATE TABLE accreditation_applications (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                organization_name   VARCHAR(200) NOT NULL,
                category            VARCHAR(100) NOT NULL,
                barangay            VARCHAR(100),
                contact_person      VARCHAR(200),
                contact_email       VARCHAR(200),
                contact_phone       VARCHAR(50),
                submitted_by        INT NOT NULL,
                status              VARCHAR(20) NOT NULL DEFAULT 'submitted',
                certificate_no      VARCHAR(50),
                valid_until         DATE,
                rejection_reason    TEXT,
                reviewed_by         INT,
                reviewed_at         TIMESTAMP NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 4: Check accreditation_documents table
echo "=== STEP 4: Checking accreditation_documents table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'accreditation_documents'");
    $exists = $result->rowCount() > 0;
    echo $exists ? "✓ Table exists\n\n" : "✗ Table does NOT exist\n\n";
    
    if (!$exists) {
        echo "Creating accreditation_documents table...\n";
        $pdo->exec("
            CREATE TABLE accreditation_documents (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                application_id  INT NOT NULL,
                doc_type        VARCHAR(50) NOT NULL,
                file_path       VARCHAR(255) NOT NULL,
                original_name   VARCHAR(255) NOT NULL,
                file_size       INT NOT NULL DEFAULT 0,
                status          VARCHAR(20) NOT NULL DEFAULT 'pending',
                reviewed_by     INT,
                reviewed_at     TIMESTAMP NULL,
                uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_accred_doc_app FOREIGN KEY (application_id) 
                    REFERENCES accreditation_applications(id) ON DELETE CASCADE,
                CONSTRAINT fk_accred_doc_reviewer FOREIGN KEY (reviewed_by) 
                    REFERENCES admin_users(id) ON DELETE SET NULL,
                CONSTRAINT chk_accred_doc_status CHECK (status IN ('pending','verified','rejected'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created successfully\n\n";
        
        // Create index
        $pdo->exec("CREATE INDEX idx_accred_docs_app_id ON accreditation_documents(application_id)");
        echo "✓ Index created\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 5: Initialize workflow steps for existing applications
echo "=== STEP 5: Initializing workflow steps ===\n";
try {
    // Check if we have applications without workflow steps
    $appsWithoutWorkflow = $pdo->query("
        SELECT a.id, a.organization_name 
        FROM accreditation_applications a
        LEFT JOIN accreditation_workflow w ON w.application_id = a.id
        WHERE w.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($appsWithoutWorkflow)) {
        echo "✓ All applications have workflow steps\n\n";
    } else {
        echo "Found " . count($appsWithoutWorkflow) . " applications without workflow steps\n";
        
        $workflowSteps = [
            'Document Verification',
            'Compliance Review',
            'Field Validation',
            'Final Assessment',
            'Certificate Generation'
        ];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO accreditation_workflow (application_id, step, status, created_at) 
            VALUES (?, ?, 'pending', CURRENT_TIMESTAMP)
        ");
        
        foreach ($appsWithoutWorkflow as $app) {
            echo "  Initializing workflow for Application #{$app['id']} ({$app['organization_name']})...\n";
            foreach ($workflowSteps as $step) {
                $insertStmt->execute([$app['id'], $step]);
            }
        }
        echo "✓ Workflow steps initialized for all applications\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Step 6: Summary
echo "=== SUMMARY ===\n";
try {
    $appCount = $pdo->query("SELECT COUNT(*) FROM accreditation_applications")->fetchColumn();
    $workflowCount = $pdo->query("SELECT COUNT(*) FROM accreditation_workflow")->fetchColumn();
    $docCount = $pdo->query("SELECT COUNT(*) FROM accreditation_documents")->fetchColumn();
    
    echo "Total Applications: $appCount\n";
    echo "Total Workflow Steps: $workflowCount\n";
    echo "Total Documents: $docCount\n\n";
    
    echo "✓ All accreditation tables are properly configured!\n";
    echo "You can now access accreditation.php without errors.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='accreditation.php'>← Go to Accreditation Page</a></p>";
?>
