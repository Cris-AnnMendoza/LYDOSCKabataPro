<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../shared/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Add Organizations</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5}pre{background:#fff;padding:20px;border-radius:8px;border-left:4px solid #4CAF50}.success{color:#4CAF50;font-weight:bold}</style>";
echo "</head><body><h2>Adding All Organizations to Database</h2><pre>";

try {
    $pdo = db();
    echo "✅ Connected to database\n\n";
    
    $organizations = [
        [
            'name' => 'LSPU SCC - Florence Nightingale\'s Club',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09216059887',
            'email' => 'florence.nightingale.lspu@gmail.com',
            'members' => 338,
            'established' => '2019-09-01'
        ],
        [
            'name' => 'BARKADA KONTRA DROGA (BKD) of Governor. Felicisimo T. San Luis Integrated Senior High School',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09363007278',
            'email' => 'bkdgftsishs@gmail.com',
            'members' => 19,
            'established' => '2022-08-29'
        ],
        [
            'name' => 'Tinig ng mga Iskolar na Nagpapahayag ng Talino at Anyo - TINTA',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09198870082',
            'email' => 'tintaorganization@gmail.com',
            'members' => 22,
            'established' => '2025-04-04'
        ],
        [
            'name' => 'Senior Secondary Learner Government - Southbay Montessori School and Colleges, Inc.',
            'category' => 'School Organization',
            'barangay' => 'Pagsawitan',
            'contact' => '09053160942',
            'email' => 'sslgofsbrmsci@gmail.com',
            'members' => 30,
            'established' => '2007-10-01'
        ],
        [
            'name' => 'Junior Philippine Society of Public Administration – LSPU Santa Cruz Main Campus',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09216886938',
            'email' => 'jpspalspuscc.official@gmail.com',
            'members' => 30,
            'established' => '2023-12-14'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - (CACHET) Campaign for Character Education and Tenacity ni Pedro Organization',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09682938622',
            'email' => 'Cachetnipedropgmnhs@gmail.com',
            'members' => 19,
            'established' => '2023-08-24'
        ],
        [
            'name' => 'Senior High School - Supreme Student Council GFTSLSHS',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09500923851',
            'email' => 'Christarose.coloma@gmail.com',
            'members' => 17,
            'established' => '2024-09-01'
        ],
        [
            'name' => 'Young Entrepreneurs Society Laguna State Polytechnic University-Main Campus',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09620882991',
            'email' => 'cbaa.yes.organization@gmail.com',
            'members' => 329,
            'established' => '2012-09-26'
        ],
        [
            'name' => 'Youth of Iglesia Filipina Independiente - Gatid (YIFI GATID)',
            'category' => 'Religious Organization',
            'barangay' => 'Gatid',
            'contact' => '09994463032',
            'email' => 'lupenathaniel@gmail.com',
            'members' => 46,
            'established' => '2009-01-01'
        ],
        [
            'name' => 'Junior Philippine Institute of Accountants LSPU - SCC',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09956428537',
            'email' => 'lspusccbsa@gmail.com',
            'members' => 268,
            'established' => '2021-10-23'
        ],
        [
            'name' => 'Laguna University - Young Entrepreneurs Society',
            'category' => 'School Organization',
            'barangay' => 'Calios',
            'contact' => '09770077455',
            'email' => 'luyesorg2023@gmail.com',
            'members' => 643,
            'established' => '2006-06-01'
        ],
        [
            'name' => 'SITIO YOUTH ORGANIZATION OF BAGUMBAYAN',
            'category' => 'Community Organization',
            'barangay' => 'Bagumbayan',
            'contact' => '0',
            'email' => 'syo.bagumbayan@gmail.com',
            'members' => 33,
            'established' => '2019-10-12'
        ],
        [
            'name' => 'SAMAHAN NG MGA INDIBIDWAL NA KASANGKA SA PAGLAGO NG ATING BAYAN-KABATAAN',
            'category' => 'Community Organization',
            'barangay' => 'Barangay III (Pob.)',
            'contact' => '09295672456',
            'email' => 'jamesivandomingo@gmail.com',
            'members' => 36,
            'established' => '2022-01-26'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - Kabataang Pangarap ni Rizal (KAPARIZ)',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09666021739',
            'email' => 'kapariz.pgmnhs@gmail.com',
            'members' => 123,
            'established' => '2016-01-01'
        ],
        [
            'name' => 'GFTSISHS SCHOOLS STUDENT LEADERSHIP GOVERNMENT - SSLG',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09859364457',
            'email' => 'govftsslg123@gmail.com',
            'members' => 18,
            'established' => '2014-01-01'
        ],
        [
            'name' => 'Sining Kalinangan Cultural Troupe - Mananayaw ni Teddy',
            'category' => 'Cultural Organization',
            'barangay' => 'Calios',
            'contact' => '09755744139',
            'email' => 'siningkalinanganculturaltroupe@gmail.com',
            'members' => 47,
            'established' => '2013-01-01'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - Youth for Environment in Schools Organization',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09684966499',
            'email' => 'yesopgmnhs22@gmail.com',
            'members' => 106,
            'established' => '2023-03-01'
        ],
        [
            'name' => 'Southbay Montessori School and College, Inc. - Student Government Organization',
            'category' => 'School Organization',
            'barangay' => 'Pagsawitan',
            'contact' => '09351444469',
            'email' => 'sbmscisgo@gmail.com',
            'members' => 68,
            'established' => '2021-02-01'
        ],
        [
            'name' => 'JUNIOR FINANCIAL EXECUTIVES',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09765418606',
            'email' => 'jfinexlspuscc@gmail.com',
            'members' => 26,
            'established' => '2025-09-13'
        ],
        [
            'name' => 'LSHS - Supreme Secondary Learner Government',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09296662473',
            'email' => 'anthoniacorazoncadapan@gmail.com',
            'members' => 11,
            'established' => '2016-06-01'
        ],
        [
            'name' => 'Broadcasting Society',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09167662347',
            'email' => 'broadsoc.official@gmail.com',
            'members' => 26,
            'established' => '2009-02-08'
        ],
        [
            'name' => 'SkillBuilders Club (GFTSISHS - TLE Department)',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09684966241',
            'email' => 'ederlyn.delena@deped.gov.ph',
            'members' => 21,
            'established' => '2025-12-06'
        ],
        [
            'name' => 'Kabataang Alay ay Paglilingkod ng Walang Alinlangan',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09543491129',
            'email' => 'pgmnhskapwa@gmail.com',
            'members' => 55,
            'established' => '2019-01-01'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - Young Historians\' Club',
            'category' => 'School Organization',
            'barangay' => 'Barangay III (Pob.)',
            'contact' => '09068645271',
            'email' => 'younghistoriansclub@gmail.com',
            'members' => 15,
            'established' => '2000-01-01'
        ],
        [
            'name' => 'Laguna Senior High School - Youth for Environment in Schools Organization',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09071055935',
            'email' => 'lianbryceamoranto256@gmail.com',
            'members' => 14,
            'established' => '2003-09-01'
        ],
        [
            'name' => 'Rotaract Club of Sta. Cruz Pioneer',
            'category' => 'Service Organization',
            'barangay' => 'Patimbao',
            'contact' => '09166583063',
            'email' => 'rotaractclubofsantacruzpioneer@gmail.com',
            'members' => 39,
            'established' => '2020-01-07'
        ],
        [
            'name' => 'Humanities and Social Sciences Alliance of the Youth (HUSAY)',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09972979352',
            'email' => 'humss.husayorganization@gmail.com',
            'members' => 12,
            'established' => '2025-07-14'
        ],
        [
            'name' => 'Barkada Kontra Droga-Pedro Guevarra Memorial National High School',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09952656002',
            'email' => 'bkdclub94@gmail.com',
            'members' => 55,
            'established' => '2022-09-16'
        ],
        [
            'name' => 'Biological Sciences Society - LSPU SCC',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09471904075',
            'email' => 'biologicalsocietylspuscc@gmail.com',
            'members' => 29,
            'established' => '2009-08-24'
        ],
        [
            'name' => 'SPEAK YOUTH FOR JESUS MOVEMENT - SYJM',
            'category' => 'Religious Organization',
            'barangay' => 'Bubukal',
            'contact' => '09267362117',
            'email' => 'speaksyjm@gmail.com',
            'members' => 65,
            'established' => '2005-01-04'
        ],
        [
            'name' => 'Sta.Cruz/Pagsanjan Community Based Scouting',
            'category' => 'Community Organization',
            'barangay' => 'Bagumbayan',
            'contact' => '09276333065',
            'email' => 'spcbscouting@gmail.com',
            'members' => 5545,
            'established' => '2019-01-01'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - English and Forensic Club',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09159461356',
            'email' => 'noelleantonettesoriano@gmail.com',
            'members' => 172,
            'established' => '2000-08-01'
        ],
        [
            'name' => 'Laguna University - Accounting Information System Society',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09543018851',
            'email' => 'lagunauniversityaissoc@gmail.com',
            'members' => 271,
            'established' => '2023-06-01'
        ],
        [
            'name' => 'ARROWS-CAMPUS MINISTRY of Gov Felicisimo T San Luis Integrated Senior High School',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09363007278',
            'email' => 'gftsishscampusministryarrows@gmail.com',
            'members' => 17,
            'established' => '2024-07-29'
        ],
        [
            'name' => 'Interact Club of Southbay Montessori School and Colleges, Inc.',
            'category' => 'School Organization',
            'barangay' => 'Pagsawitan',
            'contact' => '09362632018',
            'email' => 'interactclubsbmsci@gmail.com',
            'members' => 30,
            'established' => '2016-09-17'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - Ang Lagunian',
            'category' => 'School Organization',
            'barangay' => 'Barangay III (Pob.)',
            'contact' => '09291824330',
            'email' => 'pedroguevaramnhsanglagunian@gmail.com',
            'members' => 55,
            'established' => '1950-08-15'
        ],
        [
            'name' => 'Youth of Iglesia Filipina Independiente',
            'category' => 'Religious Organization',
            'barangay' => 'Bagumbayan',
            'contact' => '09650379647',
            'email' => 'yifibagumbayan2019@gmail.com',
            'members' => 13,
            'established' => '2019-01-01'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High - The Lagunian School',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09158402501',
            'email' => 'thelagunian.pgmnhs@gmail.com',
            'members' => 75,
            'established' => '1950-08-15'
        ],
        [
            'name' => 'Pedro Guevara Memorial National High School - Supreme Secondary Learner Government',
            'category' => 'School Organization',
            'barangay' => 'Barangay III (Pob.)',
            'contact' => '099842662870',
            'email' => 'pgmnhssslg@gmail.com',
            'members' => 23,
            'established' => '2005-01-01'
        ],
        [
            'name' => 'Felisician Researchers to Empower Students and Promote Health and Well-being in the Educational Environment Organization (FRESH ORG)',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09105700965',
            'email' => 'freshgft51@gmail.com',
            'members' => 27,
            'established' => '2022-12-06'
        ],
        [
            'name' => 'JUNIOR MARKETING ASSOCIATION OF THE PHILIPPINES - LSPU STA. CRUZ CAMPUS',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09173288813',
            'email' => 'jmaplspuscc@gmail.com',
            'members' => 370,
            'established' => '2022-11-14'
        ],
        [
            'name' => 'Science Technology Engineering and Mathematics GFTSLISHS',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09519104372',
            'email' => 'Johnrick292008@gmail.com',
            'members' => 21,
            'established' => '2025-06-16'
        ],
        [
            'name' => 'Young Technologists\' Club (YTC)',
            'category' => 'School Organization',
            'barangay' => 'Barangay I (Pob.)',
            'contact' => '09456622120',
            'email' => 'annalynbuenaventura31@gmail.com',
            'members' => 98,
            'established' => '2016-06-01'
        ],
        [
            'name' => 'Criminal Justice Education Society (CJES)',
            'category' => 'School Organization',
            'barangay' => 'Bubukal',
            'contact' => '09291218398',
            'email' => 'criminaljusticeeducationsociet@gmail.com',
            'members' => 436,
            'established' => '2023-09-01'
        ],
        [
            'name' => 'New Gen Volleyball Club',
            'category' => 'Sports Organization',
            'barangay' => 'Patimbao',
            'contact' => '09',
            'email' => 'newgenvolleyballclub1@gmail.com',
            'members' => 30,
            'established' => '2017-01-08'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO organizations (name, category, barangay, adviser_phone, adviser_email, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
        category = VALUES(category),
        barangay = VALUES(barangay),
        adviser_phone = VALUES(adviser_phone),
        adviser_email = VALUES(adviser_email)
    ");
    
    $added = 0;
    foreach ($organizations as $org) {
        $stmt->execute([
            $org['name'],
            $org['category'],
            $org['barangay'],
            $org['contact'],
            $org['email']
        ]);
        $added++;
        echo ".";
        if ($added % 10 == 0) echo " {$added}\n";
    }
    
    echo "\n\n";
    echo "<span class='success'>✅ Successfully added/updated {$added} organizations!</span>\n\n";
    
    // Show summary
    $categories = $pdo->query("SELECT category, COUNT(*) as count FROM organizations GROUP BY category")->fetchAll();
    echo "Organizations by category:\n";
    foreach ($categories as $cat) {
        echo "  - {$cat['category']}: {$cat['count']}\n";
    }
    
    echo "\n<a href='organizations.php'>→ View Organizations in Admin Panel</a>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre></body></html>";
?>
