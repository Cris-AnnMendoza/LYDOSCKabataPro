<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;line-height:1.8'>";
echo "<h2 style='color:#4CAF50'>📋 Simple Import: 49 Organizations</h2>\n\n";

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database\n\n";
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✅ Foreign key checks disabled\n\n";
    
    // Clear tables
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 1: Clearing existing data\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $pdo->exec("TRUNCATE TABLE organizations");
    echo "✅ Organizations table cleared\n";
    
    // Check if org_merit_logs exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'")->rowCount();
    if ($tableCheck > 0) {
        $pdo->exec("TRUNCATE TABLE org_merit_logs");
        echo "✅ org_merit_logs table cleared\n\n";
    } else {
        // Create the table
        echo "⚠️  Creating org_merit_logs table...\n";
        $pdo->exec("
            CREATE TABLE org_merit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                organization_id INT NOT NULL,
                points INT NOT NULL,
                type ENUM('merit', 'demerit') NOT NULL,
                reason TEXT,
                category VARCHAR(100),
                event_id INT NULL,
                awarded_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_org_id (organization_id),
                INDEX idx_type (type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ org_merit_logs table created\n\n";
    }
    
    // Re-enable foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Foreign key checks re-enabled\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "STEP 2: Importing 49 Organizations\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // All 49 organizations with points
    $organizations = [
        ['Speak Youth for Jesus Movement', 'Religious Organization', 'Bubukal', '09267362117', 'speaksyjm@gmail.com', 67],
        ['PGMNHS - Supreme Secondary Learner Government', 'School Organization', 'Barangay III (Pob.)', '099842662870', 'pgmnhssslg@gmail.com', 66],
        ['Rotaract Club of Santa Cruz Pioneer', 'Service Organization', 'Patimbao', '09166583063', 'rotaractclubofsantacruzpioneer@gmail.com', 52],
        ['LSPU SCC - Junior Philippine Institute of Accountants', 'School Organization', 'Bubukal', '09956428537', 'lspusccbsa@gmail.com', 47],
        ['LSHS - Youth for Environment in Schools Organization', 'School Organization', 'Barangay I (Pob.)', '09071055935', 'lianbryceamoranto256@gmail.com', 40],
        ['PGMNHS - Young Historian\'s Club', 'School Organization', 'Barangay III (Pob.)', '09068645271', 'younghistoriansclub@gmail.com', 36],
        ['PGMNHS - Kabataang Pangarap ni Rizal', 'School Organization', 'Barangay I (Pob.)', '09666021739', 'kapariz.pgmnhs@gmail.com', 34],
        ['PGMNHS - The Lagunian', 'School Organization', 'Barangay I (Pob.)', '09158402501', 'thelagunian.pgmnhs@gmail.com', 34],
        ['GFTSISHS - Barkada Kontra Droga', 'School Organization', 'Bubukal', '09363007278', 'bkdgftsishs@gmail.com', 30],
        ['PGMNHS - Kabataang Alay ay Paglilingkod ng Walang Alinlangan', 'School Organization', 'Barangay I (Pob.)', '09543491129', 'pgmnhskapwa@gmail.com', 26],
        ['SBMSCI - Interact Club Of Southbay Montessori School And Colleges Inc.', 'School Organization', 'Pagsawitan', '09362632018', 'interactclubsbmsci@gmail.com', 25],
        ['LSHS - Supreme Secondary Learner Government', 'School Organization', 'Barangay I (Pob.)', '09296662473', 'anthoniacorazoncadapan@gmail.com', 24],
        ['PGMNHS - English and Forensic Club', 'School Organization', 'Barangay I (Pob.)', '09159461356', 'noelleantonettesoriano@gmail.com', 24],
        ['LSPU SCC - Criminal Justice Education Society', 'School Organization', 'Bubukal', '09291218398', 'criminaljusticeeducationsociet@gmail.com', 22],
        ['LU - Junior Philippine Institute of Accountants', 'School Organization', 'Calios', '0', 'jpia.lu@gmail.com', 21],
        ['GFTSISHS - ARROWS-CAMPUS MINISTRY', 'School Organization', 'Bubukal', '09363007278', 'gftsishscampusministryarrows@gmail.com', 20],
        ['PGMNHS - Young Technologists\' Club', 'School Organization', 'Barangay I (Pob.)', '09456622120', 'annalynbuenaventura31@gmail.com', 19],
        ['SBMSCI - Student Government Organization', 'School Organization', 'Pagsawitan', '09351444469', 'sbmscisgo@gmail.com', 18],
        ['PGMNHS - Barkada Kontra Droga', 'School Organization', 'Barangay I (Pob.)', '09952656002', 'bkdclub94@gmail.com', 17],
        ['GFTSISHS - Humanities and Social Sciences Alliance of the Youth', 'School Organization', 'Bubukal', '09972979352', 'humss.husayorganization@gmail.com', 16],
        ['LSPU SCC - Junior Marketing Association of the Philippines', 'School Organization', 'Bubukal', '09173288813', 'jmaplspuscc@gmail.com', 16],
        ['SBMSCI - Senior Secondary Learner Government', 'School Organization', 'Pagsawitan', '09053160942', 'sslgofsbrmsci@gmail.com', 16],
        ['Youth of Iglesia Filipina Independiente - Gatid', 'Religious Organization', 'Gatid', '09994463032', 'lupenathaniel@gmail.com', 15],
        ['GFTSISHS - Felisician Researchers to Empower Students and Promote Health and Well-being in the Educational Environment Organization (FRESH ORG)', 'School Organization', 'Bubukal', '09105700965', 'freshgft51@gmail.com', 14],
        ['PGMNHS - Campaign for Character Education and Tenacity ni Pedro', 'School Organization', 'Barangay I (Pob.)', '09682938622', 'Cachetnipedropgmnhs@gmail.com', 12],
        ['LU - Young Entrepreneurs Society', 'School Organization', 'Calios', '09770077455', 'luyesorg2023@gmail.com', 11],
        ['Samahan ng mga Indibidwal na Kasangka sa Paglago ng Ating Bayan - Kabataan', 'Community Organization', 'Barangay III (Pob.)', '09295672456', 'jamesivandomingo@gmail.com', 11],
        ['GFTSISHS - Supreme Student Council', 'School Organization', 'Bubukal', '09500923851', 'Christarose.coloma@gmail.com', 10],
        ['Sitio Youth Organization - Bagumbayan', 'Community Organization', 'Bagumbayan', '0', 'syo.bagumbayan@gmail.com', 10],
        ['GFTSISHS - School Student\'s Leadership Government', 'School Organization', 'Bubukal', '09859364457', 'govftsslg123@gmail.com', 8],
        ['GFTSISHS - Science Technology Engineering and Mathematics', 'School Organization', 'Bubukal', '09519104372', 'Johnrick292008@gmail.com', 8],
        ['LSPU SCC - Florence Nightingale\'s Club', 'School Organization', 'Bubukal', '09216059887', 'florence.nightingale.lspu@gmail.com', 6],
        ['LSPU SCC - Junior Philippine Society of Public Administration', 'School Organization', 'Bubukal', '09216886938', 'jpspalspuscc.official@gmail.com', 6],
        ['PGMNHS - Ang Lagunian', 'School Organization', 'Barangay III (Pob.)', '09291824330', 'pedroguevaramnhsanglagunian@gmail.com', 5],
        ['LSPU SCC - Philippine Association of Students in Office Administration', 'School Organization', 'Bubukal', '0', 'pasoalspuscc@gmail.com', 3],
        ['PGMNHS - Youth for Environment in Schools Organization', 'School Organization', 'Barangay I (Pob.)', '09684966499', 'yesopgmnhs22@gmail.com', 3],
        ['Youth of Iglesia Filipina Independiente - Bagumbayan', 'Religious Organization', 'Bagumbayan', '09650379647', 'yifibagumbayan2019@gmail.com', 2],
        ['LSPU SCC - Junior Financial Executives', 'School Organization', 'Bubukal', '09765418606', 'jfinexlspuscc@gmail.com', 1],
        ['LU - Accounting Information System Society', 'School Organization', 'Bubukal', '09543018851', 'lagunauniversityaissoc@gmail.com', 1],
        ['LU - Union of Laguna University Tourism Associates', 'School Organization', 'Calios', '0', 'uluta.lu@gmail.com', 1],
        ['LSPU SCC - Biological Sciences Society', 'School Organization', 'Bubukal', '09471904075', 'biologicalsocietylspuscc@gmail.com', 0],
        ['LSPU SCC - Broadcasting Society', 'School Organization', 'Bubukal', '09167662347', 'broadsoc.official@gmail.com', 0],
        ['LSPU SCC - Young Entrepreneurs\' Society', 'School Organization', 'Bubukal', '09620882991', 'cbaa.yes.organization@gmail.com', 0],
        ['New Gen Volleyball Club', 'Sports Organization', 'Patimbao', '09', 'newgenvolleyballclub1@gmail.com', 0],
        ['Sining Kalinangan Cultural Troupe – Mananayaw Ni Teddy', 'Cultural Organization', 'Calios', '09755744139', 'siningkalinanganculturaltroupe@gmail.com', 0],
        ['Sta.Cruz/Pagsanjan Community Based Scouting', 'Community Organization', 'Bagumbayan', '09276333065', 'spcbscouting@gmail.com', 0],
        ['GFTSISHS - Tinig ng mga Iskolar na Nagpapahayag ng Talino at Anyo - TINTA', 'School Organization', 'Bubukal', '09198870082', 'tintaorganization@gmail.com', -2],
        ['GFTSISHS - SkillBuilders Club', 'School Organization', 'Bubukal', '09684966241', 'ederlyn.delena@deped.gov.ph', -5],
        ['Order of DeMolay - Dr. Roman L. Kamatoy Chapter', 'Service Organization', 'Patimbao', '0', 'demolay.kamatoy@gmail.com', -7],
        ['LSPU SCC - Supreme Student Council', 'School Organization', 'Bubukal', '0', 'lspusccssc@gmail.com', -9]
    ];
    
    $success = 0;
    $failed = 0;
    
    foreach ($organizations as $i => $org) {
        try {
            // Insert organization
            $stmt = $pdo->prepare("INSERT INTO organizations (name, category, barangay, adviser_phone, adviser_email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$org[0], $org[1], $org[2], $org[3], $org[4]]);
            $orgId = $pdo->lastInsertId();
            
            // Insert points if not zero
            if ($org[5] != 0) {
                $type = $org[5] > 0 ? 'merit' : 'demerit';
                $pointStmt = $pdo->prepare("INSERT INTO org_merit_logs (organization_id, points, type, reason, category) VALUES (?, ?, ?, 'Initial merit points from MYDC records', 'initial_import')");
                $pointStmt->execute([$orgId, $org[5], $type]);
                
                $sign = $org[5] > 0 ? '+' : '';
                $color = $org[5] > 0 ? '#4CAF50' : '#f44336';
                echo sprintf("%02d. <span style='color:#66BB6A'>%s</span> <span style='color:%s;font-weight:bold'>(%s%d pts)</span>\n", 
                    $i + 1, $org[0], $color, $sign, $org[5]);
            } else {
                echo sprintf("%02d. <span style='color:#66BB6A'>%s</span> <span style='color:#78909C'>(0 pts)</span>\n", 
                    $i + 1, $org[0]);
            }
            
            $success++;
            
        } catch (Exception $e) {
            echo sprintf("%02d. <span style='color:#f44336'>FAILED: %s</span> - %s\n", 
                $i + 1, $org[0], $e->getMessage());
            $failed++;
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span style='color:#4CAF50;font-weight:bold'>✅ IMPORT COMPLETE!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "✅ Successful: {$success}\n";
    echo "❌ Failed: {$failed}\n\n";
    
    // Verify
    $total = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    $totalPoints = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
    
    echo "Database now has:\n";
    echo "  • Organizations: {$total}\n";
    echo "  • Merit records: {$totalPoints}\n\n";
    
    echo "<a href='organizations.php' style='color:#4CAF50;font-weight:bold'>→ View Organizations</a> | ";
    echo "<a href='merit.php' style='color:#2196F3;font-weight:bold'>→ View Leaderboard</a>\n";
    
} catch (Exception $e) {
    echo "<span style='color:#f44336'>❌ ERROR: {$e->getMessage()}</span>\n\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>
