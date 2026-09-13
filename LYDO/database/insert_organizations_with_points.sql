-- Insert all Organizations with their Merit/Demerit Points
USE local_youth_development_db;

-- First, insert all organizations
INSERT INTO organizations (name, category, barangay, adviser_phone, adviser_email, is_active) VALUES
('GFTSISHS - ARROWS-CAMPUS MINISTRY', 'School Organization', 'Bubukal', '09363007278', 'gftsishscampusministryarrows@gmail.com', 1),
('GFTSISHS - Barkada Kontra Droga', 'School Organization', 'Bubukal', '09363007278', 'bkdgftsishs@gmail.com', 1),
('GFTSISHS - Felisician Researchers to Empower Students and Promote Health and Well-being in the Educational Environment Organization (FRESH ORG)', 'School Organization', 'Bubukal', '09105700965', 'freshgft51@gmail.com', 1),
('GFTSISHS - Humanities and Social Sciences Alliance of the Youth', 'School Organization', 'Bubukal', '09972979352', 'humss.husayorganization@gmail.com', 1),
('GFTSISHS - School Student''s Leadership Government', 'School Organization', 'Bubukal', '09859364457', 'govftsslg123@gmail.com', 1),
('GFTSISHS - Science Technology Engineering and Mathematics', 'School Organization', 'Bubukal', '09519104372', 'Johnrick292008@gmail.com', 1),
('GFTSISHS - SkillBuilders Club', 'School Organization', 'Bubukal', '09684966241', 'ederlyn.delena@deped.gov.ph', 1),
('GFTSISHS - Supreme Student Council', 'School Organization', 'Bubukal', '09500923851', 'Christarose.coloma@gmail.com', 1),
('GFTSISHS - Tinig ng mga Iskolar na Nagpapahayag ng Talino at Anyo - TINTA', 'School Organization', 'Bubukal', '09198870082', 'tintaorganization@gmail.com', 1),
('LSHS - Supreme Secondary Learner Government', 'School Organization', 'Barangay I (Pob.)', '09296662473', 'anthoniacorazoncadapan@gmail.com', 1),
('LSHS - Youth for Environment in Schools Organization', 'School Organization', 'Barangay I (Pob.)', '09071055935', 'lianbryceamoranto256@gmail.com', 1),
('LSPU SCC - Biological Sciences Society', 'School Organization', 'Bubukal', '09471904075', 'biologicalsocietylspuscc@gmail.com', 1),
('LSPU SCC - Broadcasting Society', 'School Organization', 'Bubukal', '09167662347', 'broadsoc.official@gmail.com', 1),
('LSPU SCC - Criminal Justice Education Society', 'School Organization', 'Bubukal', '09291218398', 'criminaljusticeeducationsociet@gmail.com', 1),
('LSPU SCC - Florence Nightingale''s Club', 'School Organization', 'Bubukal', '09216059887', 'florence.nightingale.lspu@gmail.com', 1),
('LSPU SCC - Junior Financial Executives', 'School Organization', 'Bubukal', '09765418606', 'jfinexlspuscc@gmail.com', 1),
('LSPU SCC - Junior Marketing Association of the Philippines', 'School Organization', 'Bubukal', '09173288813', 'jmaplspuscc@gmail.com', 1),
('LSPU SCC - Junior Philippine Institute of Accountants', 'School Organization', 'Bubukal', '09956428537', 'lspusccbsa@gmail.com', 1),
('LSPU SCC - Junior Philippine Society of Public Administration', 'School Organization', 'Bubukal', '09216886938', 'jpspalspuscc.official@gmail.com', 1),
('LSPU SCC - Philippine Association of Students in Office Administration', 'School Organization', 'Bubukal', '0', 'pasoalspuscc@gmail.com', 1),
('LSPU SCC - Supreme Student Council', 'School Organization', 'Bubukal', '0', 'lspusccssc@gmail.com', 1),
('LSPU SCC - Young Entrepreneurs'' Society', 'School Organization', 'Bubukal', '09620882991', 'cbaa.yes.organization@gmail.com', 1),
('LU - Accounting Information System Society', 'School Organization', 'Bubukal', '09543018851', 'lagunauniversityaissoc@gmail.com', 1),
('LU - Junior Philippine Institute of Accountants', 'School Organization', 'Calios', '0', 'jpia.lu@gmail.com', 1),
('LU - Union of Laguna University Tourism Associates', 'School Organization', 'Calios', '0', 'uluta.lu@gmail.com', 1),
('LU - Young Entrepreneurs Society', 'School Organization', 'Calios', '09770077455', 'luyesorg2023@gmail.com', 1),
('New Gen Volleyball Club', 'Sports Organization', 'Patimbao', '09', 'newgenvolleyballclub1@gmail.com', 1),
('Order of DeMolay - Dr. Roman L. Kamatoy Chapter', 'Service Organization', 'Patimbao', '0', 'demolay.kamatoy@gmail.com', 1),
('PGMNHS - Ang Lagunian', 'School Organization', 'Barangay III (Pob.)', '09291824330', 'pedroguevaramnhsanglagunian@gmail.com', 1),
('PGMNHS - Barkada Kontra Droga', 'School Organization', 'Barangay I (Pob.)', '09952656002', 'bkdclub94@gmail.com', 1),
('PGMNHS - Campaign for Character Education and Tenacity ni Pedro', 'School Organization', 'Barangay I (Pob.)', '09682938622', 'Cachetnipedropgmnhs@gmail.com', 1),
('PGMNHS - English and Forensic Club', 'School Organization', 'Barangay I (Pob.)', '09159461356', 'noelleantonettesoriano@gmail.com', 1),
('PGMNHS - Kabataang Alay ay Paglilingkod ng Walang Alinlangan', 'School Organization', 'Barangay I (Pob.)', '09543491129', 'pgmnhskapwa@gmail.com', 1),
('PGMNHS - Kabataang Pangarap ni Rizal', 'School Organization', 'Barangay I (Pob.)', '09666021739', 'kapariz.pgmnhs@gmail.com', 1),
('PGMNHS - Supreme Secondary Learner Government', 'School Organization', 'Barangay III (Pob.)', '099842662870', 'pgmnhssslg@gmail.com', 1),
('PGMNHS - The Lagunian', 'School Organization', 'Barangay I (Pob.)', '09158402501', 'thelagunian.pgmnhs@gmail.com', 1),
('PGMNHS - Young Historian''s Club', 'School Organization', 'Barangay III (Pob.)', '09068645271', 'younghistoriansclub@gmail.com', 1),
('PGMNHS - Young Technologists'' Club', 'School Organization', 'Barangay I (Pob.)', '09456622120', 'annalynbuenaventura31@gmail.com', 1),
('PGMNHS - Youth for Environment in Schools Organization', 'School Organization', 'Barangay I (Pob.)', '09684966499', 'yesopgmnhs22@gmail.com', 1),
('Rotaract Club of Santa Cruz Pioneer', 'Service Organization', 'Patimbao', '09166583063', 'rotaractclubofsantacruzpioneer@gmail.com', 1),
('Samahan ng mga Indibidwal na Kasangka sa Paglago ng Ating Bayan - Kabataan', 'Community Organization', 'Barangay III (Pob.)', '09295672456', 'jamesivandomingo@gmail.com', 1),
('SBMSCI - Interact Club Of Southbay Montessori School And Colleges Inc.', 'School Organization', 'Pagsawitan', '09362632018', 'interactclubsbmsci@gmail.com', 1),
('SBMSCI - Senior Secondary Learner Government', 'School Organization', 'Pagsawitan', '09053160942', 'sslgofsbrmsci@gmail.com', 1),
('SBMSCI - Student Government Organization', 'School Organization', 'Pagsawitan', '09351444469', 'sbmscisgo@gmail.com', 1),
('Sining Kalinangan Cultural Troupe – Mananayaw Ni Teddy', 'Cultural Organization', 'Calios', '09755744139', 'siningkalinanganculturaltroupe@gmail.com', 1),
('Sitio Youth Organization - Bagumbayan', 'Community Organization', 'Bagumbayan', '0', 'syo.bagumbayan@gmail.com', 1),
('Speak Youth for Jesus Movement', 'Religious Organization', 'Bubukal', '09267362117', 'speaksyjm@gmail.com', 1),
('Youth of Iglesia Filipina Independiente - Bagumbayan', 'Religious Organization', 'Bagumbayan', '09650379647', 'yifibagumbayan2019@gmail.com', 1),
('Youth of Iglesia Filipina Independiente - Gatid', 'Religious Organization', 'Gatid', '09994463032', 'lupenathaniel@gmail.com', 1),
('Sta.Cruz/Pagsanjan Community Based Scouting', 'Community Organization', 'Bagumbayan', '09276333065', 'spcbscouting@gmail.com', 1);

-- Now insert the merit/demerit points for each organization
-- Format: (organization_id is fetched by name, points, type, reason)

-- GFTSISHS - ARROWS-CAMPUS MINISTRY: 20 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 20, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - ARROWS-CAMPUS MINISTRY';

-- GFTSISHS - Barkada Kontra Droga: 30 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 30, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - Barkada Kontra Droga';

-- GFTSISHS - FRESH ORG: 14 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 14, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%FRESH ORG%';

-- GFTSISHS - HUSAY: 16 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 16, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - Humanities and Social Sciences Alliance of the Youth';

-- GFTSISHS - School Student's Leadership Government: 8 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 8, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - School Student''s Leadership Government';

-- GFTSISHS - Science Technology Engineering and Mathematics: 8 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 8, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - Science Technology Engineering and Mathematics';

-- GFTSISHS - SkillBuilders Club: -5 points (DEMERIT)
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, -5, 'demerit', 'Initial demerit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - SkillBuilders Club';

-- GFTSISHS - Supreme Student Council: 10 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 10, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'GFTSISHS - Supreme Student Council';

-- GFTSISHS - TINTA: -2 points (DEMERIT)
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, -2, 'demerit', 'Initial demerit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%TINTA%';

-- LSHS - Supreme Secondary Learner Government: 24 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 24, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSHS - Supreme Secondary Learner Government';

-- LSHS - Youth for Environment: 40 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 40, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSHS - Youth for Environment in Schools Organization';

-- LSPU SCC - Criminal Justice Education Society: 22 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 22, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSPU SCC - Criminal Justice Education Society';

-- LSPU SCC - Florence Nightingale's Club: 6 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 6, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Florence Nightingale%';

-- LSPU SCC - Junior Financial Executives: 1 point
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 1, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSPU SCC - Junior Financial Executives';

-- LSPU SCC - JMAP: 16 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 16, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Junior Marketing%';

-- LSPU SCC - JPIA: 47 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 47, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSPU SCC - Junior Philippine Institute of Accountants';

-- LSPU SCC - JPSPA: 6 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 6, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Public Administration%' AND name LIKE '%LSPU%';

-- LSPU SCC - PASOA: 3 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 3, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Office Administration%';

-- LSPU SCC - Supreme Student Council: -9 points (DEMERIT)
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, -9, 'demerit', 'Initial demerit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LSPU SCC - Supreme Student Council';

-- LU - AISS: 1 point
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 1, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Accounting Information System%';

-- LU - JPIA: 21 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 21, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LU - Junior Philippine Institute of Accountants';

-- LU - ULUTA: 1 point
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 1, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Tourism Associates%';

-- LU - YES: 11 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 11, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'LU - Young Entrepreneurs Society';

-- Order of DeMolay: -7 points (DEMERIT)
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, -7, 'demerit', 'Initial demerit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%DeMolay%';

-- PGMNHS - Ang Lagunian: 5 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 5, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - Ang Lagunian';

-- PGMNHS - BKD: 17 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 17, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - Barkada Kontra Droga';

-- PGMNHS - CACHET: 12 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 12, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%CACHET%' OR name LIKE '%Character Education%';

-- PGMNHS - English and Forensic Club: 24 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 24, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - English and Forensic Club';

-- PGMNHS - KAPWA: 26 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 26, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%KAPWA%' OR name LIKE '%Kabataang Alay%';

-- PGMNHS - KAPARIZ: 34 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 34, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%KAPARIZ%' OR name LIKE '%Kabataang Pangarap%';

-- PGMNHS - SSLG: 66 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 66, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - Supreme Secondary Learner Government';

-- PGMNHS - The Lagunian: 34 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 34, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - The Lagunian';

-- PGMNHS - Young Historian's Club: 36 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 36, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Young Historian%';

-- PGMNHS - Young Technologists' Club: 19 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 19, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Young Technologists%';

-- PGMNHS - YESO: 3 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 3, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'PGMNHS - Youth for Environment in Schools Organization';

-- Rotaract Club: 52 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 52, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Rotaract%';

-- Samahan: 11 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 11, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Samahan%' OR name LIKE '%SIKPAB%';

-- SBMSCI - Interact Club: 25 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 25, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Interact Club%';

-- SBMSCI - SSLG: 16 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 16, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'SBMSCI - Senior Secondary Learner Government';

-- SBMSCI - SGO: 18 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 18, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'SBMSCI - Student Government Organization';

-- Sitio Youth Organization: 10 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 10, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Sitio Youth%';

-- Speak Youth for Jesus Movement: 67 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 67, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name LIKE '%Speak Youth%';

-- YIFI - Bagumbayan: 2 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 2, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'Youth of Iglesia Filipina Independiente - Bagumbayan';

-- YIFI - Gatid: 15 points
INSERT INTO merit_logs (organization_id, points, type, reason, category, awarded_by, created_at)
SELECT id, 15, 'merit', 'Initial merit points from MYDC records', 'initial_import', 1, NOW()
FROM organizations WHERE name = 'Youth of Iglesia Filipina Independiente - Gatid';
