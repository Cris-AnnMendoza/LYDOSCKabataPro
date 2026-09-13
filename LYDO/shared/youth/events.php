
<?php
// Temporarily enable errors to see what's wrong
error_reporting(E_ALL); 
ini_set('display_errors', 1);
ob_start();
require_once __DIR__.'/../config.php';
if(empty($_SESSION['user_id'])){header('Location: ../../login.php');exit;}
$pdo=db(); $userId=(int)$_SESSION['user_id'];

// Tables (MySQL only - PostgreSQL/Supabase tables already exist)
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
$pdo->exec("CREATE TABLE IF NOT EXISTS event_checkins(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,checked_out_at DATETIME DEFAULT NULL,ip_address VARCHAR(45) DEFAULT NULL,UNIQUE KEY uq_euc(event_id,user_id))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS event_certificates(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,cert_number VARCHAR(50) NOT NULL,generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,merit_awarded TINYINT(1) NOT NULL DEFAULT 0,merit_points TINYINT NOT NULL DEFAULT 2,UNIQUE KEY uq_evc(event_id,user_id),UNIQUE KEY uq_cn(cert_number))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS user_merit_logs(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,points SMALLINT NOT NULL,type ENUM('merit','demerit') NOT NULL,reason VARCHAR(255) NOT NULL,category VARCHAR(100) DEFAULT NULL,event_id INT UNSIGNED DEFAULT NULL,cert_id INT UNSIGNED DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,title VARCHAR(200) NOT NULL,message TEXT NOT NULL,type VARCHAR(50) DEFAULT 'info',is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
foreach(["ALTER TABLE events ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL","ALTER TABLE events ADD COLUMN checkin_open TINYINT(1) NOT NULL DEFAULT 1","ALTER TABLE events ADD COLUMN merit_points TINYINT UNSIGNED NOT NULL DEFAULT 2","ALTER TABLE events ADD COLUMN checkin_code VARCHAR(6) DEFAULT NULL","ALTER TABLE events ADD COLUMN checkout_open TINYINT(1) NOT NULL DEFAULT 0","ALTER TABLE event_checkins ADD COLUMN checked_out_at DATETIME DEFAULT NULL","ALTER TABLE event_checkins ADD COLUMN checkin_photo VARCHAR(255) DEFAULT NULL","ALTER TABLE event_checkins ADD COLUMN checkout_photo VARCHAR(255) DEFAULT NULL"]as $s){try{$pdo->exec($s);}catch(PDOException $e){}}
}

// Auto-generate missing tokens (MySQL syntax)
if (!defined('USE_SUPABASE') || USE_SUPABASE === false) {
    $pdo->exec("UPDATE events SET qr_token=MD5(CONCAT(id,title,created_at)) WHERE(qr_token IS NULL OR qr_token='')AND checkin_open=TRUE");
    $pdo->exec("UPDATE events SET checkin_code=UPPER(SUBSTRING(MD5(CONCAT(id,qr_token)),1,6)) WHERE(checkin_code IS NULL OR checkin_code='')AND qr_token IS NOT NULL");
} else {
    // PostgreSQL version
    $pdo->exec("UPDATE events SET qr_token=MD5(id::text||title||created_at::text) WHERE(qr_token IS NULL OR qr_token='')AND checkin_open=TRUE");
    $pdo->exec("UPDATE events SET checkin_code=UPPER(SUBSTRING(MD5(id::text||qr_token),1,6)) WHERE(checkin_code IS NULL OR checkin_code='')AND qr_token IS NOT NULL");
}

// ── CHECK-IN ──────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['ajax_scan'])){
    ob_clean(); header('Content-Type: application/json');
    $code=strtoupper(trim($_POST['qr_token']??''));
    if(!$code){echo json_encode(['success'=>false,'message'=>'Enter the event code.']);exit;}
    $evAll=$pdo->query('SELECT * FROM events WHERE checkin_open=TRUE')->fetchAll();
    $ev=null;
    foreach($evAll as $c){
        $eid=(int)$c['id']; $tok=$c['qr_token']??''; $sc=strtoupper(trim($c['checkin_code']??''));
        // Static code match
        if($sc&&$code===$sc){$ev=$c;break;}
        // Rotating code match (30-second windows to match admin display)
        if($tok){
            $w=floor(time()/30);
            if($code===strtoupper(substr(md5($eid.$tok.$w.'CHECKIN'),0,6))||$code===strtoupper(substr(md5($eid.$tok.($w-1).'CHECKIN'),0,6))){$ev=$c;break;}
        }
    }
    if(!$ev){echo json_encode(['success'=>false,'message'=>'Invalid code. Use the code shown on screen.']);exit;}
    // ── 15-minute check-in window enforcement ──
    $st=$ev['event_start_time']??null;
    if($st&&strlen($st)>0){
        if(strlen($st)===5)$st.=':00';
        $startTs=strtotime(date('Y-m-d').' '.$st);
        $deadlineTs=$startTs+900; // +15 minutes
        $nowTs=time();
        if($nowTs>$deadlineTs){
            $deadlineStr=date('g:i A',$deadlineTs);
            echo json_encode(['success'=>false,'message'=>'Check-in closed. The 15-minute window ended at '.$deadlineStr.'. Hindi na pwede mag check-in kapag late.']);exit;
        }
    }
    $eid=(int)$ev['id'];
    $chk=$pdo->prepare('SELECT id FROM event_checkins WHERE event_id=? AND user_id=? LIMIT 1');
    $chk->execute([$eid,$userId]);
    if($chk->fetch()){echo json_encode(['success'=>false,'already'=>true,'message'=>'Already checked in to "'.$ev['title'].'".']);exit;}
    $pdo->prepare('INSERT INTO event_checkins(event_id,user_id,ip_address)VALUES(?,?,?)')->execute([$eid,$userId,$_SERVER['REMOTE_ADDR']??null]);
    // Save check-in selfie
    $photoDir=__DIR__.'/../uploads/event_photos/'; if(!is_dir($photoDir))mkdir($photoDir,0755,true);
    if(isset($_FILES['checkin_photo'])&&$_FILES['checkin_photo']['error']===UPLOAD_ERR_OK){
        $ext=strtolower(pathinfo($_FILES['checkin_photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','webp'])&&$_FILES['checkin_photo']['size']<=5*1024*1024){
            $fname='ci_'.$eid.'_'.$userId.'_'.time().'.'.$ext;
            if(move_uploaded_file($_FILES['checkin_photo']['tmp_name'],$photoDir.$fname))
                $pdo->prepare('UPDATE event_checkins SET checkin_photo=? WHERE event_id=? AND user_id=?')->execute([$fname,$eid,$userId]);
        }
    }
    $cn='LYDO-EVT-'.date('Y').'-'.str_pad($eid,4,'0',STR_PAD_LEFT).'-'.str_pad($userId,4,'0',STR_PAD_LEFT);
    try{$pdo->prepare('INSERT INTO event_certificates(event_id,user_id,cert_number,merit_points)VALUES(?,?,?,?)')->execute([$eid,$userId,$cn,$ev['merit_points']?:2]);}catch(PDOException $e){}
    try{$pdo->prepare('INSERT INTO notifications(user_id,title,message,type)VALUES(?,?,?,?)')->execute([$userId,'Checked In: '.$ev['title'],'Checked in to "'.$ev['title'].'". Check out after the event to earn merit points.','success']);}catch(PDOException $e){}
    echo json_encode(['success'=>true,'event'=>$ev['title'],'cert_no'=>$cn,'pts'=>(int)($ev['merit_points']?:2),'event_id'=>$eid]);
    exit;
}

// ── CHECK-OUT ─────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['ajax_checkout'])){
    ob_clean(); header('Content-Type: application/json');
    $code=strtoupper(trim($_POST['qr_token']??''));
    if(!$code){echo json_encode(['success'=>false,'message'=>'Enter the checkout code.']);exit;}
    $evAll=$pdo->query('SELECT * FROM events WHERE checkout_open=TRUE')->fetchAll();
    $ev=null;
    foreach($evAll as $c){
        $eid=(int)$c['id']; $tok=$c['qr_token']??'';
        if(!$tok) continue;
        $w=floor(time()/30);
        if($code===strtoupper(substr(md5($eid.$tok.$w.'CHECKOUT'),0,6))||$code===strtoupper(substr(md5($eid.$tok.($w-1).'CHECKOUT'),0,6))){
            $ev=$c; break;
        }
    }
    if(!$ev){echo json_encode(['success'=>false,'message'=>'Invalid checkout code. Use the checkout code shown on screen.']);exit;}
    $eid=(int)$ev['id'];
    $ci=$pdo->prepare('SELECT * FROM event_checkins WHERE event_id=? AND user_id=? LIMIT 1');
    $ci->execute([$eid,$userId]); $checkin=$ci->fetch();
    if(!$checkin){echo json_encode(['success'=>false,'message'=>'You have not checked in to this event yet.']);exit;}
    if($checkin['checked_out_at']){echo json_encode(['success'=>false,'already'=>true,'message'=>'Already checked out of "'.$ev['title'].'".']);exit;}
    $pdo->prepare('UPDATE event_checkins SET checked_out_at=NOW() WHERE event_id=? AND user_id=?')->execute([$eid,$userId]);
    // Save check-out selfie
    $photoDir=__DIR__.'/../uploads/event_photos/'; if(!is_dir($photoDir))mkdir($photoDir,0755,true);
    if(isset($_FILES['checkout_photo'])&&$_FILES['checkout_photo']['error']===UPLOAD_ERR_OK){
        $ext=strtolower(pathinfo($_FILES['checkout_photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','webp'])&&$_FILES['checkout_photo']['size']<=5*1024*1024){
            $fname='co_'.$eid.'_'.$userId.'_'.time().'.'.$ext;
            if(move_uploaded_file($_FILES['checkout_photo']['tmp_name'],$photoDir.$fname))
                $pdo->prepare('UPDATE event_checkins SET checkout_photo=? WHERE event_id=? AND user_id=?')->execute([$fname,$eid,$userId]);
        }
    }
    $pts=(int)($ev['merit_points']?:2);
    $cs=$pdo->prepare('SELECT * FROM event_certificates WHERE event_id=? AND user_id=? LIMIT 1');
    $cs->execute([$eid,$userId]); $cert=$cs->fetch();
    if(!$cert){
        $cn='LYDO-EVT-'.date('Y').'-'.str_pad($eid,4,'0',STR_PAD_LEFT).'-'.str_pad($userId,4,'0',STR_PAD_LEFT);
        try{$pdo->prepare('INSERT INTO event_certificates(event_id,user_id,cert_number,merit_points,merit_awarded)VALUES(?,?,?,?,1)')->execute([$eid,$userId,$cn,$pts]);$certId=(int)$pdo->lastInsertId();}catch(PDOException $e){$cn='';$certId=0;}
    }else{
        $cn=$cert['cert_number'];$certId=(int)$cert['id'];
        if(!$cert['merit_awarded'])$pdo->prepare('UPDATE event_certificates SET merit_awarded=TRUE WHERE id=?')->execute([$certId]);
    }
    $al=$pdo->prepare('SELECT id FROM user_merit_logs WHERE user_id=? AND event_id=? AND category="event_attendance" LIMIT 1');
    $al->execute([$userId,$eid]);
    if(!$al->fetch())$pdo->prepare('INSERT INTO user_merit_logs(user_id,points,type,reason,category,event_id,cert_id)VALUES(?,?,"merit",?,"event_attendance",?,?)')->execute([$userId,$pts,'Event checkout: '.$cn,$eid,$certId??0]);
    
    // ── Award ORG merit points (only ONCE per org per event) ──
    $userOrg=$user['organization_name']??'';
    if($userOrg){
        $orgRow=$pdo->prepare('SELECT id FROM organizations WHERE name=? LIMIT 1');
        $orgRow->execute([$userOrg]);$orgRow=$orgRow->fetch();
        if($orgRow){
            $orgId=(int)$orgRow['id'];
            // Check if org already got points for this event
            $orgCheck=$pdo->prepare('SELECT id FROM org_merit_logs WHERE organization_id=? AND event_id=? AND category="event_attendance" LIMIT 1');
            $orgCheck->execute([$orgId,$eid]);
            if(!$orgCheck->fetch()){
                $pdo->prepare('INSERT INTO org_merit_logs(organization_id,points,type,reason,category,event_id,created_at)VALUES(?,?,"merit",?,"event_attendance",?,NOW())')
                    ->execute([$orgId,$pts,'Event attendance: '.$ev['title'],$eid]);
            }
        }
    }
    
    try{$pdo->prepare('INSERT INTO notifications(user_id,title,message,type)VALUES(?,?,?,?)')->execute([$userId,'Merit Points Awarded!','+'.$pts.' points for "'.$ev['title'].'". Certificate ready.','success']);}catch(PDOException $e){}
    echo json_encode(['success'=>true,'event'=>$ev['title'],'cert_no'=>$cn,'pts'=>$pts,'event_id'=>$eid,'cert_id'=>$certId??0]);
    exit;
}

// ── Page data ─────────────────────────────────────────────
$uStmt=$pdo->prepare('SELECT * FROM youth_users WHERE id=? LIMIT 1');$uStmt->execute([$userId]);$user=$uStmt->fetch();
$nc=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');$nc->execute([$userId]);$notifCount=(int)$nc->fetchColumn();
$ciS=$pdo->prepare('SELECT c.checked_in_at,c.checked_out_at,e.id as event_id,e.title,e.event_date,e.location,e.merit_points,cert.id as cert_id,cert.cert_number,cert.merit_awarded,cert.merit_points as cert_pts FROM event_checkins c JOIN events e ON e.id=c.event_id LEFT JOIN event_certificates cert ON cert.event_id=c.event_id AND cert.user_id=c.user_id WHERE c.user_id=? ORDER BY c.checked_in_at DESC');
$ciS->execute([$userId]);$checkins=$ciS->fetchAll();
$tmS=$pdo->prepare('SELECT COALESCE(SUM(points),0) FROM user_merit_logs WHERE user_id=? AND type=?');$tmS->execute([$userId, 'merit']);$totalMerit=(int)$tmS->fetchColumn();
try{$upS=$pdo->query('SELECT e.*,o.name as org_name FROM events e LEFT JOIN organizations o ON o.id=e.organization_id WHERE e.checkin_open=TRUE AND e.event_date>=CURRENT_DATE ORDER BY e.event_date ASC LIMIT 10');$upcoming=$upS?$upS->fetchAll():[];}catch(PDOException $e){$upcoming=[];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Events &amp; Check-in – LYDO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.ev-tabs{display:flex;gap:4px;background:var(--gray-100);padding:4px;border-radius:12px;width:fit-content;margin-bottom:22px;flex-wrap:wrap}
.ev-tab{padding:9px 18px;border-radius:9px;border:none;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;color:var(--gray-600);background:transparent;transition:.2s;display:flex;align-items:center;gap:7px}
.ev-tab.active{background:#fff;color:var(--blue);box-shadow:0 1px 4px rgba(0,0,0,.1)}
.merit-banner{background:linear-gradient(135deg,#0d3b6e,#1565c0,#1e88e5);border-radius:16px;padding:20px 24px;display:flex;align-items:center;gap:18px;margin-bottom:22px;box-shadow:0 4px 20px rgba(21,101,192,.25)}
.merit-icon{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;flex-shrink:0}
.ev-card{background:#fff;border-radius:14px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);overflow:hidden;margin-bottom:14px}
.ev-card-header{padding:16px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:10px}
.ev-card-header h3{font-size:.95rem;font-weight:700;color:var(--gray-800);flex:1}
.ev-card-body{padding:20px}
.checkin-wrap{display:flex;flex-direction:column;align-items:center;gap:18px;padding:10px 0}
.code-input{width:100%;max-width:320px;padding:16px 20px;font-family:'Courier New',monospace;font-size:1.8rem;font-weight:700;letter-spacing:.25em;text-align:center;text-transform:uppercase;border:2.5px solid var(--gray-200);border-radius:14px;background:var(--gray-50);color:var(--gray-800);transition:.2s;outline:none}
.code-input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 4px rgba(21,101,192,.1)}
.btn-action{width:100%;max-width:320px;padding:15px;color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s}
.btn-action:disabled{opacity:.6;cursor:not-allowed}
.btn-in{background:linear-gradient(135deg,#0d3b6e,#1565c0);box-shadow:0 4px 16px rgba(21,101,192,.35)}
.btn-out{background:linear-gradient(135deg,#1b5e20,#2e7d32);box-shadow:0 4px 16px rgba(46,125,50,.35)}
.result-box{width:100%;max-width:380px;border-radius:12px;padding:16px 18px;display:none;align-items:flex-start;gap:12px}
.result-box.show{display:flex}
.result-box.success{background:#e8f5e9;border:1.5px solid #a5d6a7;color:#2e7d32}
.result-box.error{background:#ffebee;border:1.5px solid #ef9a9a;color:#c62828}
.result-box.info{background:#e3f2fd;border:1.5px solid #90caf9;color:#1565c0}
.result-title{font-weight:700;font-size:.9rem;margin-bottom:3px}
.result-msg{font-size:.82rem;line-height:1.5}
.ci-item{display:flex;align-items:flex-start;gap:14px;padding:16px 0;border-bottom:1px solid var(--gray-100)}
.ci-item:last-child{border-bottom:none}
.ci-dot{width:42px;height:42px;border-radius:12px;background:var(--blue-pale);display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:1rem;flex-shrink:0}
.ci-dot.done{background:var(--green-pale);color:var(--green)}
.ci-info{flex:1;min-width:0}
.ci-title{font-size:.9rem;font-weight:700;color:var(--gray-800);margin-bottom:3px}
.ci-meta{font-size:.75rem;color:var(--gray-400);display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px}
.badge-merit{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.badge-merit.awarded{background:var(--green-pale);color:var(--green)}
.badge-merit.pending{background:#fff8e1;color:#f57f17}
.upc-item{display:flex;align-items:flex-start;gap:14px;padding:16px 0;border-bottom:1px solid var(--gray-100)}
.upc-item:last-child{border-bottom:none}
.upc-date-box{width:50px;flex-shrink:0;text-align:center;background:linear-gradient(135deg,#0d3b6e,#1565c0);border-radius:10px;padding:8px 4px;color:#fff}
.upc-date-day{font-size:1.3rem;font-weight:800;line-height:1}
.upc-date-mon{font-size:.62rem;font-weight:600;text-transform:uppercase;opacity:.8}
.empty-state{text-align:center;padding:40px 20px;color:var(--gray-400)}
.empty-state i{font-size:2.5rem;margin-bottom:12px;display:block;opacity:.4}
.mode-row{display:flex;gap:10px;margin-bottom:16px}
.mode-btn{flex:1;padding:14px;border-radius:12px;border:2.5px solid #e2e8f0;background:#f8fafc;color:#475569;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.mode-btn.active-in{border-color:#1565c0;background:#e3f2fd;color:#0d3b6e}
.mode-btn.active-out{border-color:#2e7d32;background:#e8f5e9;color:#1b5e20}
.btn-qr-scanner{animation:pulse-glow 2s ease-in-out infinite}
@keyframes pulse-glow{0%,100%{box-shadow:0 6px 20px rgba(21,101,192,.25)}50%{box-shadow:0 8px 30px rgba(21,101,192,.45),0 0 0 4px rgba(21,101,192,.1)}}
@media(max-width:600px){.ev-tab{flex:1;justify-content:center;padding:9px 8px;font-size:.78rem}.code-input{font-size:1.4rem}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<div class="y-page-header">
  <h2><i class="fas fa-calendar-check" style="color:var(--blue);margin-right:8px"></i>Events &amp; Check-in</h2>
  <p>Check in at the start and check out at the end to earn merit points automatically.</p>
</div>

<!-- Merit Banner -->
<div class="merit-banner">
  <div class="merit-icon"><i class="fas fa-star"></i></div>
  <div>
    <div style="font-size:.78rem;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Your Merit Points</div>
    <div style="font-size:2rem;font-weight:800;color:#fff;line-height:1"><?= $totalMerit ?></div>
    <div style="font-size:.78rem;color:rgba(255,255,255,.6);margin-top:3px">Total points earned from events</div>
  </div>
  <div style="margin-left:auto;text-align:right;color:rgba(255,255,255,.7);font-size:.8rem">
    <div style="font-size:1.5rem;font-weight:800;color:#fff"><?= count($checkins) ?></div>
    <div>Events attended</div>
  </div>
</div>

<!-- Tabs -->
<div class="ev-tabs">
  <button class="ev-tab active" data-tab="checkin"><i class="fas fa-sign-in-alt"></i> Check In / Out</button>
  <button class="ev-tab" data-tab="mycheckins"><i class="fas fa-clipboard-list"></i> My Check-ins <span style="background:var(--blue);color:#fff;border-radius:50px;padding:1px 8px;font-size:.7rem;margin-left:2px"><?= count($checkins) ?></span></button>
  <button class="ev-tab" data-tab="upcoming"><i class="fas fa-calendar-alt"></i> Upcoming</button>
</div>

<!-- ── CHECK IN / OUT TAB ── -->
<div id="tab-checkin" class="tab-pane">
  
  <div class="mode-row">
    <button id="btnModeIn" class="mode-btn active-in" onclick="switchMode('in')"><i class="fas fa-sign-in-alt"></i> Check In</button>
    <button id="btnModeOut" class="mode-btn" onclick="switchMode('out')"><i class="fas fa-sign-out-alt"></i> Check Out</button>
  </div>

  <!-- Check In Panel -->
  <div id="panelIn" class="ev-card">
    <div class="ev-card-header" style="background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff">
      <i class="fas fa-qrcode"></i><h3 style="color:#fff">Check In to Event</h3>
    </div>
    <div class="ev-card-body">
      <div class="checkin-wrap">
        <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#0d3b6e,#1565c0);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;box-shadow:0 6px 20px rgba(21,101,192,.3)"><i class="fas fa-qrcode"></i></div>
        <div style="font-size:1.1rem;font-weight:800;color:var(--gray-800)">Scan QR Code to Check In</div>
        <div style="font-size:.85rem;color:var(--gray-600);text-align:center;max-width:380px;line-height:1.6">Scan the <strong style="color:#1565c0">blue QR code</strong> displayed on the event organizer's screen. <strong style="color:#c62828">You only have 15 minutes from event start to check in!</strong></div>
        
        <!-- Photo Upload Section -->
        <div style="width:100%;max-width:380px;margin-top:10px">
          <label style="display:block;font-size:.9rem;font-weight:600;color:var(--gray-700);margin-bottom:8px">
            <i class="fas fa-camera"></i> Upload Attendance Photo (Optional)
          </label>
          <div id="checkinPhotoBox" style="border:2px dashed #cbd5e1;border-radius:12px;padding:20px;text-align:center;background:#f8fafc;cursor:pointer;transition:.2s" onclick="document.getElementById('checkinPhotoInput').click()">
            <div id="checkinPhotoPlaceholder">
              <i class="fas fa-camera" style="font-size:2rem;color:#94a3b8;margin-bottom:8px"></i>
              <div style="font-size:.85rem;color:#64748b;font-weight:500">Click to upload or take a photo</div>
              <div style="font-size:.75rem;color:#94a3b8;margin-top:4px">JPG, PNG or WEBP (Max 5MB)</div>
            </div>
            <div id="checkinPhotoPreview" style="display:none">
              <img id="checkinPhotoImg" style="max-width:100%;max-height:200px;border-radius:8px;margin-bottom:8px" />
              <div style="font-size:.8rem;color:#2e7d32;font-weight:600"><i class="fas fa-check-circle"></i> Photo ready to upload</div>
            </div>
          </div>
          <input type="file" id="checkinPhotoInput" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewCheckinPhoto(event)" style="display:none" />
          <div style="font-size:.75rem;color:#64748b;margin-top:6px;text-align:center">
            <i class="fas fa-info-circle"></i> Taking a photo serves as additional proof of your attendance
          </div>
        </div>
        
        <a href="qr_scanner.php?type=checkin" class="btn-action btn-in btn-qr-scanner" style="text-decoration:none">
          <i class="fas fa-qrcode"></i>
          <span>Open QR Scanner</span>
        </a>
        
        <div style="background:#e3f2fd;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#0d47a1;max-width:380px;text-align:center;line-height:1.6;margin-top:8px">
          <i class="fas fa-info-circle"></i> Point your camera at the <strong>blue QR code</strong> shown by the organizer. Hold steady for automatic scanning.
        </div>
        
        <div style="font-size:.78rem;color:var(--gray-400);text-align:center;max-width:380px;line-height:1.6;margin-top:8px">
          <i class="fas fa-lightbulb"></i> Tip: Ask the organizer to increase screen brightness for better scanning.
        </div>
      </div>
    </div>
  </div>

  <!-- Check Out Panel -->
  <div id="panelOut" class="ev-card" style="display:none">
    <div class="ev-card-header" style="background:linear-gradient(135deg,#1b5e20,#2e7d32);color:#fff">
      <i class="fas fa-qrcode"></i><h3 style="color:#fff">Check Out of Event</h3>
    </div>
    <div class="ev-card-body">
      <div class="checkin-wrap">
        <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#1b5e20,#2e7d32);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;box-shadow:0 6px 20px rgba(46,125,50,.3)"><i class="fas fa-qrcode"></i></div>
        <div style="font-size:1.1rem;font-weight:800;color:var(--gray-800)">Scan QR Code to Check Out</div>
        <div style="font-size:.85rem;color:var(--gray-600);text-align:center;max-width:380px;line-height:1.6">Scan the <strong style="color:#2e7d32">green QR code</strong> displayed by the event organizer after the event ends.</div>

        <!-- Photo Upload Section -->
        <div style="width:100%;max-width:380px;margin-top:10px">
          <label style="display:block;font-size:.9rem;font-weight:600;color:var(--gray-700);margin-bottom:8px">
            <i class="fas fa-camera"></i> Upload Attendance Photo (Optional)
          </label>
          <div id="checkoutPhotoBox" style="border:2px dashed #cbd5e1;border-radius:12px;padding:20px;text-align:center;background:#f8fafc;cursor:pointer;transition:.2s" onclick="document.getElementById('checkoutPhotoInput').click()">
            <div id="checkoutPhotoPlaceholder">
              <i class="fas fa-camera" style="font-size:2rem;color:#94a3b8;margin-bottom:8px"></i>
              <div style="font-size:.85rem;color:#64748b;font-weight:500">Click to upload or take a photo</div>
              <div style="font-size:.75rem;color:#94a3b8;margin-top:4px">JPG, PNG or WEBP (Max 5MB)</div>
            </div>
            <div id="checkoutPhotoPreview" style="display:none">
              <img id="checkoutPhotoImg" style="max-width:100%;max-height:200px;border-radius:8px;margin-bottom:8px" />
              <div style="font-size:.8rem;color:#2e7d32;font-weight:600"><i class="fas fa-check-circle"></i> Photo ready to upload</div>
            </div>
          </div>
          <input type="file" id="checkoutPhotoInput" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewCheckoutPhoto(event)" style="display:none" />
          <div style="font-size:.75rem;color:#64748b;margin-top:6px;text-align:center">
            <i class="fas fa-info-circle"></i> Taking a photo serves as additional proof of your attendance
          </div>
        </div>

        <a href="qr_scanner.php?type=checkout" class="btn-action btn-out btn-qr-scanner" style="text-decoration:none">
          <i class="fas fa-qrcode"></i>
          <span>Open QR Scanner</span>
        </a>

        <div style="background:#e8f5e9;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#2e7d32;max-width:380px;text-align:center;line-height:1.6;margin-top:8px"><i class="fas fa-star"></i> <strong>Merit points are added instantly!</strong> Just scan the green QR code to check out and earn your points.</div>
        
        <div style="font-size:.78rem;color:var(--gray-400);text-align:center;max-width:380px;line-height:1.6;margin-top:8px">
          <i class="fas fa-lightbulb"></i> Tip: Hold your phone steady while scanning the QR code.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── MY CHECK-INS TAB ── -->
<div id="tab-mycheckins" class="tab-pane" style="display:none">
  <div class="ev-card">
    <div class="ev-card-header"><i class="fas fa-clipboard-list" style="color:var(--blue)"></i><h3>My Event Check-ins</h3></div>
    <div class="ev-card-body" style="padding:0 20px">
      <?php if(empty($checkins)): ?>
        <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No check-ins yet. Attend an event and use the code to check in!</p></div>
      <?php else: foreach($checkins as $ci): $hasOut=!empty($ci['checked_out_at']); ?>
        <div class="ci-item">
          <div class="ci-dot <?= $hasOut?'done':'' ?>"><i class="fas fa-<?= $hasOut?'check-double':'sign-in-alt' ?>"></i></div>
          <div class="ci-info">
            <div class="ci-title"><?= htmlspecialchars($ci['title']) ?></div>
            <div class="ci-meta">
              <span><i class="fas fa-calendar"></i> <?= $ci['event_date']?date('M j, Y',strtotime($ci['event_date'])):'—' ?></span>
              <span style="color:#2e7d32"><i class="fas fa-sign-in-alt"></i> In: <?= date('g:i A',strtotime($ci['checked_in_at'])) ?></span>
              <?php if($hasOut): ?><span style="color:#c62828"><i class="fas fa-sign-out-alt"></i> Out: <?= date('g:i A',strtotime($ci['checked_out_at'])) ?></span><?php endif; ?>
              <?php if($ci['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ci['location']) ?></span><?php endif; ?>
            </div>
            <?php if(!empty($ci['cert_number'])): ?>
              <div style="font-size:.75rem;font-family:monospace;font-weight:600;color:var(--blue);background:var(--blue-pale);padding:2px 8px;border-radius:5px;display:inline-block;margin-bottom:8px"><i class="fas fa-certificate"></i> <?= htmlspecialchars($ci['cert_number']) ?></div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <?php if($ci['merit_awarded']): ?>
                <span class="badge-merit awarded"><i class="fas fa-star"></i> +<?= (int)($ci['cert_pts']?:$ci['merit_points']?:2) ?> Merit Points Awarded</span>
              <?php elseif($hasOut): ?>
                <span class="badge-merit awarded"><i class="fas fa-check-circle"></i> Checked Out</span>
              <?php else: ?>
                <span class="badge-merit pending"><i class="fas fa-hourglass-half"></i> Check out to earn merit points</span>
              <?php endif; ?>
              <?php if(!empty($ci['cert_id'])&&$hasOut): ?>
                <a href="/LYDO/lydo-system/event_cert_view.php?cert_id=<?= (int)$ci['cert_id'] ?>" target="_blank"
                   style="font-size:.78rem;color:var(--blue);font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
                  <i class="fas fa-certificate"></i> View Certificate
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- ── UPCOMING TAB ── -->
<div id="tab-upcoming" class="tab-pane" style="display:none">
  <div class="ev-card">
    <div class="ev-card-header"><i class="fas fa-calendar-alt" style="color:var(--blue)"></i><h3>Upcoming Events</h3></div>
    <div class="ev-card-body" style="padding:0 20px">
      <?php if(empty($upcoming)): ?>
        <div class="empty-state"><i class="fas fa-calendar"></i><p>No upcoming events. Check back soon!</p></div>
      <?php else: foreach($upcoming as $ev): ?>
        <div class="upc-item">
          <div class="upc-date-box">
            <div class="upc-date-day"><?= $ev['event_date']?date('j',strtotime($ev['event_date'])):'—' ?></div>
            <div class="upc-date-mon"><?= $ev['event_date']?date('M',strtotime($ev['event_date'])):'' ?></div>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.9rem;font-weight:700;color:var(--gray-800);margin-bottom:4px"><?= htmlspecialchars($ev['title']) ?></div>
            <div style="font-size:.75rem;color:var(--gray-400);display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px">
              <?php if(!empty($ev['location'])): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ev['location']) ?></span><?php endif; ?>
              <?php if(!empty($ev['org_name'])): ?><span><i class="fas fa-building"></i> <?= htmlspecialchars($ev['org_name']) ?></span><?php endif; ?>
            </div>
            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:var(--green-pale);color:var(--green)"><i class="fas fa-star"></i> +<?= (int)($ev['merit_points']?:2) ?> merit pts</span>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

</main>
</div>

<script>
// Tab switching
document.querySelectorAll('.ev-tab').forEach(function(btn){
  btn.addEventListener('click',function(){
    document.querySelectorAll('.ev-tab').forEach(function(b){b.classList.remove('active');});
    document.querySelectorAll('.tab-pane').forEach(function(p){p.style.display='none';});
    btn.classList.add('active');
    document.getElementById('tab-'+btn.dataset.tab).style.display='block';
  });
});

// Sidebar
var ham=document.getElementById('yHamburger'),sb=document.getElementById('ySidebar'),ov=document.getElementById('yOverlay'),cl=document.getElementById('ySidebarClose');
function openSB(){sb.classList.add('open');ov.classList.add('open');}
function closeSB(){sb.classList.remove('open');ov.classList.remove('open');}
if(ham)ham.addEventListener('click',openSB);
if(cl)cl.addEventListener('click',closeSB);
if(ov)ov.addEventListener('click',closeSB);

// Mode switch
function switchMode(m){
  var pi=document.getElementById('panelIn'),po=document.getElementById('panelOut');
  var bi=document.getElementById('btnModeIn'),bo=document.getElementById('btnModeOut');
  if(m==='in'){pi.style.display='block';po.style.display='none';bi.className='mode-btn active-in';bo.className='mode-btn';}
  else{pi.style.display='none';po.style.display='block';bo.className='mode-btn active-out';bi.className='mode-btn';}
}

// Show result
function showR(id,type,icon,title,msg){
  var b=document.getElementById('result'+id);
  b.className='result-box show '+type;
  var prefix=id==='In'?'ri':'ro';
  document.getElementById(prefix+'Icon').className='fas '+icon;
  document.getElementById(prefix+'Title').textContent=title;
  document.getElementById(prefix+'Msg').innerHTML=msg;
}

function esc(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;}

// Photo preview
function previewPhoto(id,input){
  var preview=document.getElementById('photo'+id+'Preview');
  var img=document.getElementById('photo'+id+'Img');
  var label=document.getElementById('photo'+id+'Label');
  if(input.files&&input.files[0]){
    var reader=new FileReader();
    reader.onload=function(e){img.src=e.target.result;preview.style.display='block';label.textContent=input.files[0].name;};
    reader.readAsDataURL(input.files[0]);
  }else{preview.style.display='none';label.textContent='Take or choose a selfie photo';}
}

// Check In
function doCheckin(){
  var code=document.getElementById('codeIn').value.trim().toUpperCase();
  var btn=document.getElementById('btnCheckin');
  var photoFile=document.getElementById('checkinPhotoInput').files[0];
  if(!code||code.length<6){showR('In','error','fa-exclamation-circle','Missing Code','Please enter the full 6-character event code.');return;}
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Checking in…';
  document.getElementById('resultIn').className='result-box';
  var fd=new FormData();fd.append('ajax_scan','1');fd.append('qr_token',code);
  if(photoFile){fd.append('checkin_photo',photoFile);}
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
      btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Check In Now';
      try{
        var d=JSON.parse(t);
        if(d.success){
          document.getElementById('codeIn').value='';
          showR('In','success','fa-check-circle','Checked In!','You are now checked in to <strong>'+esc(d.event)+'</strong>. Check out after the event to earn <strong>+'+d.pts+' merit points</strong>.');
          setTimeout(function(){location.reload();},3000);
        }else if(d.already){showR('In','info','fa-info-circle','Already Checked In',d.message);}
        else{showR('In','error','fa-times-circle','Failed',d.message);}
      }catch(e){showR('In','error','fa-exclamation-triangle','Error','Server response: '+t.substring(0,150));}
    })
    .catch(function(e){btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-in-alt"></i> Check In Now';showR('In','error','fa-wifi','Connection Error','Could not reach server.');});
}

// Check Out
function doCheckout(){
  var code=document.getElementById('codeOut').value.trim().toUpperCase();
  var btn=document.getElementById('btnCheckout');
  var photoFile=document.getElementById('checkoutPhotoInput').files[0];
  if(!code||code.length<6){showR('Out','error','fa-exclamation-circle','Missing Code','Please enter the full 6-character check-out code.');return;}
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Checking out…';
  document.getElementById('resultOut').className='result-box';
  var fd=new FormData();fd.append('ajax_checkout','1');fd.append('qr_token',code);
  if(photoFile){fd.append('checkout_photo',photoFile);}
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){
      btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-out-alt"></i> Check Out &amp; Earn Merit Points';
      try{
        var d=JSON.parse(t);
        if(d.success){
          document.getElementById('codeOut').value='';
          showR('Out','success','fa-star','🎉 +'+d.pts+' Merit Points Awarded!','You have checked out of <strong>'+esc(d.event)+'</strong>. Merit points added to your account!');
          setTimeout(function(){location.reload();},3000);
        }else if(d.already){showR('Out','info','fa-info-circle','Already Checked Out',d.message);}
        else{showR('Out','error','fa-times-circle','Failed',d.message);}
      }catch(e){showR('Out','error','fa-exclamation-triangle','Error','Server response: '+t.substring(0,150));}
    })
    .catch(function(e){btn.disabled=false;btn.innerHTML='<i class="fas fa-sign-out-alt"></i> Check Out &amp; Earn Merit Points';showR('Out','error','fa-wifi','Connection Error','Could not reach server.');});
}

// Enter key
document.getElementById('codeIn').addEventListener('keydown',function(e){if(e.key==='Enter')doCheckin();});
document.getElementById('codeOut').addEventListener('keydown',function(e){if(e.key==='Enter')doCheckout();});
</script>

<!-- Photo Preview Functions -->
<script>
// Check-in photo preview
function previewCheckinPhoto(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  // Validate file size (5MB max)
  if (file.size > 5 * 1024 * 1024) {
    alert('Photo size must be less than 5MB');
    event.target.value = '';
    return;
  }
  
  // Validate file type
  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    alert('Please upload a valid image file (JPG, PNG, or WEBP)');
    event.target.value = '';
    return;
  }
  
  // Preview the photo
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('checkinPhotoImg').src = e.target.result;
    document.getElementById('checkinPhotoPlaceholder').style.display = 'none';
    document.getElementById('checkinPhotoPreview').style.display = 'block';
    document.getElementById('checkinPhotoBox').style.borderColor = '#2e7d32';
    document.getElementById('checkinPhotoBox').style.background = '#e8f5e9';
  };
  reader.readAsDataURL(file);
}

// Check-out photo preview
function previewCheckoutPhoto(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  // Validate file size (5MB max)
  if (file.size > 5 * 1024 * 1024) {
    alert('Photo size must be less than 5MB');
    event.target.value = '';
    return;
  }
  
  // Validate file type
  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    alert('Please upload a valid image file (JPG, PNG, or WEBP)');
    event.target.value = '';
    return;
  }
  
  // Preview the photo
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('checkoutPhotoImg').src = e.target.result;
    document.getElementById('checkoutPhotoPlaceholder').style.display = 'none';
    document.getElementById('checkoutPhotoPreview').style.display = 'block';
    document.getElementById('checkoutPhotoBox').style.borderColor = '#2e7d32';
    document.getElementById('checkoutPhotoBox').style.background = '#e8f5e9';
  };
  reader.readAsDataURL(file);
}

// Store photo data to send with QR scanner
if (typeof(Storage) !== "undefined") {
  // Store photo when selected so QR scanner can access it
  document.getElementById('checkinPhotoInput').addEventListener('change', function(e) {
    if (e.target.files[0]) {
      const reader = new FileReader();
      reader.onload = function(event) {
        sessionStorage.setItem('pendingCheckinPhoto', event.target.result);
        sessionStorage.setItem('pendingCheckinPhotoName', e.target.files[0].name);
      };
      reader.readAsDataURL(e.target.files[0]);
    }
  });
  
  document.getElementById('checkoutPhotoInput').addEventListener('change', function(e) {
    if (e.target.files[0]) {
      const reader = new FileReader();
      reader.onload = function(event) {
        sessionStorage.setItem('pendingCheckoutPhoto', event.target.result);
        sessionStorage.setItem('pendingCheckoutPhotoName', e.target.files[0].name);
      };
      reader.readAsDataURL(e.target.files[0]);
    }
  });
}
</script>

</body>
</html>
