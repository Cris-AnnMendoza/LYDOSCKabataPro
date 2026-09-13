<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Import Organizations with Points - FINAL</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;line-height:1.8}pre{background:#2d2d2d;padding:20px;border-radius:8px;border-left:4px solid #4CAF50}.success{color:#4CAF50;font-weight:bold}.error{color:#f44336}.info{color:#64B5F6}</style>";
echo "</head><body><h2 style='color:#4CAF50'>📋 Importing 49 Organizations with Merit Points</h2><pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    // Step 1: Ensure org_merit_logs table exists
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='info'>STEP 1: Checking/Creating org_merit_logs table</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'org_merit_logs'")->rowCount();
    
    if ($tableCheck === 0) {
        echo "⚠️  Table org_merit_logs doesn't exist. Creating it...\n";
        
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
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
                FOREIGN KEY (awarded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
                INDEX idx_org_id (organization_id),
                INDEX idx_type (type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "✅ Table org_merit_logs created successfully!\n\n";
    } else {
        echo "✅ Table org_merit_logs already exists\n\n";
    }
    
    // Step 2: Import organizations with points
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='info'>STEP 2: Importing 49 Organizations</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Organizations with their points from the PDF
    $orgsWithPoints = [
        ['name' => 'GFTSISHS - ARROWS-CAMPUS MINISTRY', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09363007278', 'email' => 'gftsishscampusministryarrows@gmail.com', 'points' => 20],
        ['name' => 'GFTSISHS - Barkada Kontra Droga', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09363007278', 'email' => 'bkdgftsishs@gmail.com', 'points' => 30],
        ['name' => 'GFTSISHS - Felisician Researchers to Empower Students and Promote Health and Well-being in the Educational Environment Organization (FRESH ORG)', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09105700965', 'email' => 'freshgft51@gmail.com', 'points' => 14],
        ['name' => 'GFTSISHS - Humanities and Social Sciences Alliance of the Youth', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09972979352', 'email' => 'humss.husayorganization@gmail.com', 'points' => 16],
        ['name' => 'GFTSISHS - School Student\'s Leadership Government', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09859364457', 'email' => 'govftsslg123@gmail.com', 'points' => 8],
        ['name' => 'GFTSISHS - Science Technology Engineering and Mathematics', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09519104372', 'email' => 'Johnrick292008@gmail.com', 'points' => 8],
        ['name' => 'GFTSISHS - SkillBuilders Club', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09684966241', 'email' => 'ederlyn.delena@deped.gov.ph', 'points' => -5],
        ['name' => 'GFTSISHS - Supreme Student Council', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09500923851', 'email' => 'Christarose.coloma@gmail.com', 'points' => 10],
        ['name' => 'GFTSISHS - Tinig ng mga Iskolar na Nagpapahayag ng Talino at Anyo - TINTA', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09198870082', 'email' => 'tintaorganization@gmail.com', 'points' => -2],
        ['name' => 'LSHS - Supreme Secondary Learner Government', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09296662473', 'email' => 'anthoniacorazoncadapan@gmail.com', 'points' => 24],
        ['name' => 'LSHS - Youth for Environment in Schools Organization', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09071055935', 'email' => 'lianbryceamoranto256@gmail.com', 'points' => 40],
        ['name' => 'LSPU SCC - Biological Sciences Society', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09471904075', 'email' => 'biologicalsocietylspuscc@gmail.com', 'points' => 0],
        ['name' => 'LSPU SCC - Broadcasting Society', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09167662347', 'email' => 'broadsoc.official@gmail.com', 'points' => 0],
        ['name' => 'LSPU SCC - Criminal Justice Education Society', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09291218398', 'email' => 'criminaljusticeeducationsociet@gmail.com', 'points' => 22],
        ['name' => 'LSPU SCC - Florence Nightingale\'s Club', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09216059887', 'email' => 'florence.nightingale.lspu@gmail.com', 'points' => 6],
        ['name' => 'LSPU SCC - Junior Financial Executives', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09765418606', 'email' => 'jfinexlspuscc@gmail.com', 'points' => 1],
        ['name' => 'LSPU SCC - Junior Marketing Association of the Philippines', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09173288813', 'email' => 'jmaplspuscc@gmail.com', 'points' => 16],
        ['name' => 'LSPU SCC - Junior Philippine Institute of Accountants', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09956428537', 'email' => 'lspusccbsa@gmail.com', 'points' => 47],
        ['name' => 'LSPU SCC - Junior Philippine Society of Public Administration', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09216886938', 'email' => 'jpspalspuscc.official@gmail.com', 'points' => 6],
        ['name' => 'LSPU SCC - Philippine Association of Students in Office Administration', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '0', 'email' => 'pasoalspuscc@gmail.com', 'points' => 3],
        ['name' => 'LSPU SCC - Supreme Student Council', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '0', 'email' => 'lspusccssc@gmail.com', 'points' => -9],
        ['name' => 'LSPU SCC - Young Entrepreneurs\' Society', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09620882991', 'email' => 'cbaa.yes.organization@gmail.com', 'points' => 0],
        ['name' => 'LU - Accounting Information System Society', 'category' => 'School Organization', 'barangay' => 'Bubukal', 'phone' => '09543018851', 'email' => 'lagunauniversityaissoc@gmail.com', 'points' => 1],
        ['name' => 'LU - Junior Philippine Institute of Accountants', 'category' => 'School Organization', 'barangay' => 'Calios', 'phone' => '0', 'email' => 'jpia.lu@gmail.com', 'points' => 21],
        ['name' => 'LU - Union of Laguna University Tourism Associates', 'category' => 'School Organization', 'barangay' => 'Calios', 'phone' => '0', 'email' => 'uluta.lu@gmail.com', 'points' => 1],
        ['name' => 'LU - Young Entrepreneurs Society', 'category' => 'School Organization', 'barangay' => 'Calios', 'phone' => '09770077455', 'email' => 'luyesorg2023@gmail.com', 'points' => 11],
        ['name' => 'New Gen Volleyball Club', 'category' => 'Sports Organization', 'barangay' => 'Patimbao', 'phone' => '09', 'email' => 'newgenvolleyballclub1@gmail.com', 'points' => 0],
        ['name' => 'Order of DeMolay - Dr. Roman L. Kamatoy Chapter', 'category' => 'Service Organization', 'barangay' => 'Patimbao', 'phone' => '0', 'email' => 'demolay.kamatoy@gmail.com', 'points' => -7],
        ['name' => 'PGMNHS - Ang Lagunian', 'category' => 'School Organization', 'barangay' => 'Barangay III (Pob.)', 'phone' => '09291824330', 'email' => 'pedroguevaramnhsanglagunian@gmail.com', 'points' => 5],
        ['name' => 'PGMNHS - Barkada Kontra Droga', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09952656002', 'email' => 'bkdclub94@gmail.com', 'points' => 17],
        ['name' => 'PGMNHS - Campaign for Character Education and Tenacity ni Pedro', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09682938622', 'email' => 'Cachetnipedropgmnhs@gmail.com', 'points' => 12],
        ['name' => 'PGMNHS - English and Forensic Club', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09159461356', 'email' => 'noelleantonettesoriano@gmail.com', 'points' => 24],
        ['name' => 'PGMNHS - Kabataang Alay ay Paglilingkod ng Walang Alinlangan', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09543491129', 'email' => 'pgmnhskapwa@gmail.com', 'points' => 26],
        ['name' => 'PGMNHS - Kabataang Pangarap ni Rizal', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09666021739', 'email' => 'kapariz.pgmnhs@gmail.com', 'points' => 34],
        ['name' => 'PGMNHS - Supreme Secondary Learner Government', 'category' => 'School Organization', 'barangay' => 'Barangay III (Pob.)', 'phone' => '099842662870', 'email' => 'pgmnhssslg@gmail.com', 'points' => 66],
        ['name' => 'PGMNHS - The Lagunian', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09158402501', 'email' => 'thelagunian.pgmnhs@gmail.com', 'points' => 34],
        ['name' => 'PGMNHS - Young Historian\'s Club', 'category' => 'School Organization', 'barangay' => 'Barangay III (Pob.)', 'phone' => '09068645271', 'email' => 'younghistoriansclub@gmail.com', 'points' => 36],
        ['name' => 'PGMNHS - Young Technologists\' Club', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09456622120', 'email' => 'annalynbuenaventura31@gmail.com', 'points' => 19],
        ['name' => 'PGMNHS - Youth for Environment in Schools Organization', 'category' => 'School Organization', 'barangay' => 'Barangay I (Pob.)', 'phone' => '09684966499', 'email' => 'yesopgmnhs22@gmail.com', 'points' => 3],
        ['name' => 'Rotaract Club of Santa Cruz Pioneer', 'category' => 'Service Organization', 'barangay' => 'Patimbao', 'phone' => '09166583063', 'email' => 'rotaractclubofsantacruzpioneer@gmail.com', 'points' => 52],
        ['name' => 'Samahan ng mga Indibidwal na Kasangka sa Paglago ng Ating Bayan - Kabataan', 'category' => 'Community Organization', 'barangay' => 'Barangay III (Pob.)', 'phone' => '09295672456', 'email' => 'jamesivandomingo@gmail.com', 'points' => 11],
        ['name' => 'SBMSCI - Interact Club Of Southbay Montessori School And Colleges Inc.', 'category' => 'School Organization', 'barangay' => 'Pagsawitan', 'phone' => '09362632018', 'email' => 'interactclubsbmsci@gmail.com', 'points' => 25],
        ['name' => 'SBMSCI - Senior Secondary Learner Government', 'category' => 'School Organization', 'barangay' => 'Pagsawitan', 'phone' => '09053160942', 'email' => 'sslgofsbrmsci@gmail.com', 'points' => 16],
        ['name' => 'SBMSCI - Student Government Organization', 'category' => 'School Organization', 'barangay' => 'Pagsawitan', 'phone' => '09351444469', 'email' => 'sbmscisgo@gmail.com', 'points' => 18],
        ['name' => 'Sining Kalinangan Cultural Troupe – Mananayaw Ni Teddy', 'category' => 'Cultural Organization', 'barangay' => 'Calios', 'phone' => '09755744139', 'email' => 'siningkalinanganculturaltroupe@gmail.com', 'points' => 0],
        ['name' => 'Sitio Youth Organization - Bagumbayan', 'category' => 'Community Organization', 'barangay' => 'Bagumbayan', 'phone' => '0', 'email' => 'syo.bagumbayan@gmail.com', 'points' => 10],
        ['name' => 'Speak Youth for Jesus Movement', 'category' => 'Religious Organization', 'barangay' => 'Bubukal', 'phone' => '09267362117', 'email' => 'speaksyjm@gmail.com', 'points' => 67],
        ['name' => 'Youth of Iglesia Filipina Independiente - Bagumbayan', 'category' => 'Religious Organization', 'barangay' => 'Bagumbayan', 'phone' => '09650379647', 'email' => 'yifibagumbayan2019@gmail.com', 'points' => 2],
        ['name' => 'Youth of Iglesia Filipina Independiente - Gatid', 'category' => 'Religious Organization', 'barangay' => 'Gatid', 'phone' => '09994463032', 'email' => 'lupenathaniel@gmail.com', 'points' => 15],
        ['name' => 'Sta.Cruz/Pagsanjan Community Based Scouting', 'category' => 'Community Organization', 'barangay' => 'Bagumbayan', 'phone' => '09276333065', 'email' => 'spcbscouting@gmail.com', 'points' => 0]
    ];
    
    $orgStmt = $pdo->prepare("INSERT INTO organizations (name, category, barangay, adviser_phone, adviser_email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $pointStmt = $pdo->prepare("INSERT INTO org_merit_logs (organization_id, points, type, reason, category, created_at) VALUES (?, ?, ?, 'Initial merit points from MYDC records', 'initial_import', NOW())");
    
    $added = 0;
    $pointsAdded = 0;
    $skipped = 0;
    
    foreach ($orgsWithPoints as $org) {
        // Check if org already exists
        $check = $pdo->prepare("SELECT id FROM organizations WHERE name = ?");
        $check->execute([$org['name']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $orgId = $existing['id'];
            echo "⚠️  <span style='color:#FFA726'>{$org['name']}</span> - already exists (skipping)\n";
            $skipped++;
        } else {
            $orgStmt->execute([$org['name'], $org['category'], $org['barangay'], $org['phone'], $org['email']]);
            $orgId = $pdo->lastInsertId();
            $added++;
            echo "✅ <span style='color:#66BB6A'>{$org['name']}</span>\n";
        }
        
        // Add points if not zero (whether org is new or existing, we add the points)
        if ($org['points'] != 0) {
            $type = $org['points'] > 0 ? 'merit' : 'demerit';
            
            // Check if points already added
            $checkPoints = $pdo->prepare("SELECT COUNT(*) FROM org_merit_logs WHERE organization_id = ? AND category = 'initial_import'");
            $checkPoints->execute([$orgId]);
            $pointsExist = $checkPoints->fetchColumn();
            
            if ($pointsExist == 0) {
                $pointStmt->execute([$orgId, $org['points'], $type]);
                $pointsAdded++;
                $sign = $org['points'] > 0 ? '+' : '';
                $color = $org['points'] > 0 ? '#4CAF50' : '#f44336';
                echo "   └─ Points: <span style='color:{$color};font-weight:bold'>{$sign}{$org['points']}</span>\n";
            }
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='success'>✅ IMPORT COMPLETE!</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📊 Summary:\n";
    echo "   • Organizations added: <span class='success'>{$added}</span>\n";
    echo "   • Organizations skipped (already exist): <span style='color:#FFA726'>{$skipped}</span>\n";
    echo "   • Points records added: <span class='success'>{$pointsAdded}</span>\n\n";
    
    // Final verification
    $totalOrgs = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    $totalPoints = $pdo->query("SELECT COUNT(*) FROM org_merit_logs")->fetchColumn();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "<span class='info'>DATABASE STATUS:</span>\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "   • Total organizations in database: <span class='success'>{$totalOrgs}</span>\n";
    echo "   • Total merit log records: <span class='success'>{$totalPoints}</span>\n\n";
    
    echo "🎯 <span style='color:#64B5F6;font-weight:bold'>NEXT STEPS:</span>\n\n";
    echo "   1. <a href='organizations.php' style='color:#4CAF50;font-weight:bold;text-decoration:none'>→ View Organizations List</a>\n";
    echo "   2. <a href='merit.php' style='color:#2196F3;font-weight:bold;text-decoration:none'>→ View Merit Leaderboard</a>\n\n";
    
} catch (Exception $e) {
    echo "\n<span class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}

echo "</pre></body></html>";
?>
