<?php
// Prevent browser caching - force logout to work properly
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

$pdo  = db();
$stmt = $pdo->prepare('SELECT * FROM youth_users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header('Location: ../../login.php'); exit; }

// Get latest announcements from events
$announcementsStmt = $pdo->prepare("
    SELECT 
        e.*,
        o.name as org_name
    FROM events e
    LEFT JOIN organizations o ON o.id = e.organization_id
    WHERE e.event_date >= CURDATE()
    ORDER BY e.created_at DESC
    LIMIT 5
");
$announcementsStmt->execute();
$announcements = $announcementsStmt->fetchAll();

$cls  = $user['youth_classification'] ? json_decode($user['youth_classification'], true) : [];
$prgs = $user['programs_interested']  ? json_decode($user['programs_interested'],  true) : [];

// Pending assistance requests
$rStmt = $pdo->prepare('SELECT COUNT(*) FROM assistance_requests WHERE submitted_by=? AND status NOT IN (?, ?)');
$rStmt->execute([$user['id'], 'completed', 'declined']);
$pendingReqs = (int)$rStmt->fetchColumn();

// Unread notifications
$nc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
$nc->execute([$user['id']]);
$notifCount = (int)$nc->fetchColumn();

// User's personal merit points
$myMeritStmt = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM user_merit_logs WHERE user_id=? AND type=?');
$myMeritStmt->execute([$user['id'], 'merit']);
$myMerit = (int)$myMeritStmt->fetchColumn();

// Organization merit points (match by org name)
$orgName = $user['organization_name'] ?? '';
$orgMerit = 0; $orgDemerit = 0; $orgData = null;
if ($orgName) {
    $orgStmt = $pdo->prepare('SELECT o.*, COALESCE(SUM(CASE WHEN m.type="merit" THEN m.points ELSE 0 END),0) as total_merit, COALESCE(SUM(CASE WHEN m.type="demerit" THEN ABS(m.points) ELSE 0 END),0) as total_demerit FROM organizations o LEFT JOIN org_merit_logs m ON m.organization_id=o.id WHERE o.name=? GROUP BY o.id LIMIT 1');
    $orgStmt->execute([$orgName]);
    $orgData = $orgStmt->fetch();
    if ($orgData) { $orgMerit = (int)$orgData['total_merit']; $orgDemerit = (int)$orgData['total_demerit']; }
}

// Ensure user has a QR token
if (empty($user['qr_token'])) {
    try { $pdo->exec("ALTER TABLE youth_users ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL"); } catch (PDOException $e) {}
    $token = md5($user['id'] . $user['email'] . 'LYDO2026');
    $pdo->prepare('UPDATE youth_users SET qr_token = ? WHERE id = ?')->execute([$token, $user['id']]);
    $user['qr_token'] = $token;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Dashboard – LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
/* Enhanced responsive styles */
@media(max-width:1024px){
  .stat-grid-4{grid-template-columns:repeat(2,1fr) !important}
  div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}
}

@media(max-width:768px){
  .stat-grid-4{grid-template-columns:repeat(2,1fr) !important}
  .org-banner,.welcome-banner{flex-direction:column !important;align-items:flex-start !important}
  .org-scores{width:100%;justify-content:space-between;flex-wrap:wrap}
  
  /* Adjust card content */
  div[style*="display:grid;grid-template-columns:1fr 1fr"]{
    grid-template-columns:1fr !important;
    gap:14px !important;
  }
  
  /* Make tables scrollable */
  .tbl{display:block;overflow-x:auto;white-space:nowrap}
  
  /* Adjust announcements */
  div[style*="padding:12px 18px"][style*="border-bottom"]{
    flex-direction:column !important;
    align-items:flex-start !important;
  }
  div[style*="font-size:.78rem;color:var(--gray-600)"][style*="display:flex"]{
    flex-wrap:wrap !important;
  }
}

@media(max-width:600px){
  .stat-grid-4{grid-template-columns:1fr !important}
  
  /* Compact stat cards */
  div[style*="padding:18px"][style*="display:flex;align-items:center;gap:14px"]{
    padding:12px !important;
    gap:10px !important;
  }
  div[style*="width:44px;height:44px"]{
    width:38px !important;
    height:38px !important;
    font-size:1rem !important;
  }
  
  /* Adjust font sizes */
  span[style*="font-size:1.6rem"]{font-size:1.3rem !important}
  span[style*="font-size:.75rem"]{font-size:.72rem !important}
  
  /* Warning boxes */
  div[style*="padding:18px 22px"][style*="margin-bottom:18px"]{
    padding:12px 14px !important;
  }
  
  /* Org merit card */
  div[style*="padding:10px 18px"]{
    padding:8px 12px !important;
  }
}

@media(max-width:480px){
  /* Ultra compact mode */
  .stat-grid-4{grid-template-columns:1fr !important}
  
  /* Very small cards */
  div[style*="padding:12px"]{padding:10px !important}
  div[style*="font-size:1.3rem"]{font-size:1.1rem !important}
  div[style*="font-size:1.5rem"]{font-size:1.2rem !important}
  
  /* Hide less important info on mobile */
  span[style*="font-size:.68rem;color:var(--gray-400)"]{display:none}
}

/* Chatbot Bubble Button */
.chatbot-bubble {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: linear-gradient(135deg, #1565c0, #1e88e5);
  color: #fff;
  padding: 14px 28px;
  border-radius: 50px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(21, 101, 192, 0.4);
  transition: all 0.3s ease;
  z-index: 998;
  font-weight: 600;
  font-size: 0.95rem;
}

.chatbot-bubble:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 25px rgba(21, 101, 192, 0.5);
}

.chatbot-bubble i {
  font-size: 1.1rem;
}

/* Chatbot Widget Window */
.chatbot-modal {
  position: fixed;
  bottom: 100px;
  right: 30px;
  width: 360px;
  height: 550px;
  z-index: 999;
  opacity: 0;
  visibility: hidden;
  transform: translateY(20px) scale(0.95);
  transition: all 0.3s ease;
}

.chatbot-modal.active {
  opacity: 1;
  visibility: visible;
  transform: translateY(0) scale(1);
}

.chatbot-container {
  background: #fff;
  width: 100%;
  height: 100%;
  border-radius: 16px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.chatbot-header {
  background: linear-gradient(135deg, #1565c0, #1e88e5);
  color: #fff;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 16px 16px 0 0;
}

.chatbot-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.chatbot-header-left i {
  font-size: 1.2rem;
}

.chatbot-header-left span {
  font-weight: 600;
  font-size: 1rem;
}

.chatbot-close {
  background: transparent;
  border: none;
  color: #fff;
  width: 28px;
  height: 28px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  transition: all 0.2s ease;
  opacity: 0.9;
}

.chatbot-close:hover {
  opacity: 1;
  transform: scale(1.1);
}

.chatbot-iframe-wrapper {
  flex: 1;
  overflow: hidden;
  position: relative;
  background: #f5f5f5;
}

.chatbot-iframe-wrapper iframe {
  width: 100%;
  height: 100%;
  border: none;
}

@media(max-width: 768px) {
  .chatbot-bubble {
    bottom: 20px;
    right: 20px;
    padding: 12px 24px;
    font-size: 0.9rem;
  }
  
  .chatbot-modal {
    width: calc(100% - 40px);
    height: 500px;
    bottom: 85px;
    right: 20px;
  }
}

@media(max-width: 480px) {
  .chatbot-modal {
    width: calc(100% - 20px);
    height: 450px;
    bottom: 75px;
    right: 10px;
  }
  
  .chatbot-bubble {
    bottom: 15px;
    right: 15px;
    padding: 10px 20px;
    font-size: 0.85rem;
  }
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

  <!-- WELCOME BANNER -->
  <div style="background:linear-gradient(135deg,var(--blue-dark),var(--blue));border-radius:16px;padding:24px 28px;color:#fff;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:5px">Welcome back, <?= htmlspecialchars($user['first_name']) ?>! </h2>
      <p style="font-size:.88rem;opacity:.85">You're logged in to the LYDO Youth Portal of Sta. Cruz, Laguna.</p>
    </div>
    <div style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:12px 20px;text-align:center">
      <span style="display:block;font-size:.72rem;opacity:.75;margin-bottom:2px">Member since</span>
      <strong style="font-size:1rem"><?= date('M Y', strtotime($user['created_at'])) ?></strong>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid-4" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px">
    <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)">
      <div style="width:44px;height:44px;border-radius:11px;background:var(--green-pale);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><i class="fas fa-star"></i></div>
      <div><span style="display:block;font-size:1.6rem;font-weight:800;color:var(--green);line-height:1"><?= $myMerit ?></span><span style="font-size:.75rem;color:var(--gray-600);font-weight:500">My Merit Points</span></div>
    </div>
    <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)">
      <div style="width:44px;height:44px;border-radius:11px;background:var(--blue-pale);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><i class="fas fa-users"></i></div>
      <div>
        <span style="display:block;font-size:1.6rem;font-weight:800;color:var(--blue);line-height:1"><?= $orgMerit ?></span>
        <span style="font-size:.75rem;color:var(--gray-600);font-weight:500">Org Merit Points</span>
        <?php if ($orgData): ?><span style="font-size:.68rem;color:var(--gray-400);display:block"><?= htmlspecialchars($orgData['name']) ?></span><?php endif; ?>
      </div>
    </div>
    <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)">
      <div style="width:44px;height:44px;border-radius:11px;background:var(--blue-pale);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><i class="fas fa-hands-helping"></i></div>
      <div><span style="display:block;font-size:1.6rem;font-weight:800;color:var(--gray-800);line-height:1"><?= $pendingReqs ?></span><span style="font-size:.75rem;color:var(--gray-600);font-weight:500">Active Requests</span></div>
    </div>
    <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);padding:18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm)">
      <div style="width:44px;height:44px;border-radius:11px;background:#fff8e1;color:#f57f17;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><i class="fas fa-bell"></i></div>
      <div><span style="display:block;font-size:1.6rem;font-weight:800;color:var(--gray-800);line-height:1"><?= $notifCount ?></span><span style="font-size:.75rem;color:var(--gray-600);font-weight:500">Notifications</span></div>
    </div>
  </div>

  <!-- ORG MERIT DETAILS (if user belongs to an org) -->
  <?php if ($orgData): ?>
    <?php
    // Check for warnings based on demerit points
    $showWarning = false;
    $warningLevel = '';
    $warningMessage = '';
    $warningColor = '';
    $warningBg = '';
    
    if ($orgDemerit >= 10) {
        $showWarning = true;
        $warningLevel = 'critical';
        $warningMessage = '⛔ CRITICAL: Your organization has reached the membership revocation threshold!';
        $warningColor = '#b71c1c';
        $warningBg = '#ffebee';
    } elseif ($orgDemerit >= 7) {
        $showWarning = true;
        $warningLevel = 'severe';
        $warningMessage = '🚨 SEVERE WARNING: Show Cause Order issued. Immediate action required!';
        $warningColor = '#e65100';
        $warningBg = '#fff3e0';
    } elseif ($orgDemerit >= 5) {
        $showWarning = true;
        $warningLevel = 'warning';
        $warningMessage = '⚠️ WARNING: Your organization has received a formal warning letter.';
        $warningColor = '#f57f17';
        $warningBg = '#fff8e1';
    }
    ?>
    
    <?php if ($showWarning): ?>
    <div style="background:<?=$warningBg?>;border:2px solid <?=$warningColor?>;border-radius:12px;padding:18px 22px;margin-bottom:18px;box-shadow:0 4px 12px rgba(0,0,0,.1)">
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="width:48px;height:48px;background:<?=$warningColor?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;flex-shrink:0">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div style="flex:1">
          <h3 style="font-size:1.1rem;font-weight:800;color:<?=$warningColor?>;margin-bottom:8px"><?=$warningMessage?></h3>
          <p style="font-size:.9rem;color:var(--gray-700);line-height:1.6;margin-bottom:14px">
            Your organization <strong><?=htmlspecialchars($orgData['name'])?></strong> has accumulated <strong><?=$orgDemerit?> demerit points</strong>. 
            This requires immediate attention from your organization president.
          </p>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:120px;background:white;border-radius:8px;padding:10px 14px;text-align:center">
              <div style="font-size:1.3rem;font-weight:800;color:<?=$warningColor?>"><?=$orgDemerit?></div>
              <div style="font-size:.7rem;color:var(--gray-600);font-weight:600">DEMERIT POINTS</div>
            </div>
            <div style="flex:1;min-width:120px;background:white;border-radius:8px;padding:10px 14px;text-align:center">
              <div style="font-size:1.3rem;font-weight:800;color:var(--green)"><?=$orgMerit?></div>
              <div style="font-size:.7rem;color:var(--gray-600);font-weight:600">MERIT POINTS</div>
            </div>
            <div style="flex:1;min-width:120px;background:white;border-radius:8px;padding:10px 14px;text-align:center">
              <div style="font-size:1.3rem;font-weight:800;color:<?=$orgMerit - $orgDemerit >= 0 ? 'var(--green)' : 'var(--red)'?>"><?=$orgMerit - $orgDemerit?></div>
              <div style="font-size:.7rem;color:var(--gray-600);font-weight:600">NET SCORE</div>
            </div>
          </div>
          <div style="margin-top:14px;padding:12px 14px;background:white;border-radius:8px;border-left:3px solid <?=$warningColor?>">
            <p style="font-size:.85rem;color:var(--gray-700);line-height:1.5">
              <i class="fas fa-info-circle" style="color:<?=$warningColor?>;margin-right:6px"></i>
              <strong>Action Required:</strong> Your organization president needs to address these violations immediately. 
              Contact your president or LYDO office for details on how to resolve these issues.
            </p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
  <div style="background:linear-gradient(135deg,#1565c0,#1e88e5);border-radius:14px;padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;box-shadow:0 4px 16px rgba(21,101,192,.2)">
    <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;flex-shrink:0"><i class="fas fa-building"></i></div>
    <div style="flex:1;min-width:0">
      <div style="font-size:.72rem;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">My Organization</div>
      <div style="font-size:1.1rem;font-weight:800;color:#fff"><?= htmlspecialchars($orgData['name']) ?></div>
      <?php if (!empty($orgData['category'])): ?><div style="font-size:.78rem;color:rgba(255,255,255,.7)"><?= htmlspecialchars($orgData['category']) ?></div><?php endif; ?>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="text-align:center;background:rgba(255,255,255,.12);border-radius:10px;padding:10px 18px">
        <div style="font-size:1.5rem;font-weight:800;color:#a5d6a7"><?= $orgMerit ?></div>
        <div style="font-size:.7rem;color:rgba(255,255,255,.7);font-weight:600">Merit</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.12);border-radius:10px;padding:10px 18px<?=$orgDemerit >= 5 ? ';border:2px solid #ef5350' : ''?>">
        <div style="font-size:1.5rem;font-weight:800;color:#ef9a9a"><?= $orgDemerit ?></div>
        <div style="font-size:.7rem;color:rgba(255,255,255,.7);font-weight:600">Demerit</div>
        <?php if ($orgDemerit >= 5): ?>
        <div style="font-size:.65rem;color:#ffcdd2;margin-top:4px;font-weight:700">⚠️ WARNING</div>
        <?php endif; ?>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.12);border-radius:10px;padding:10px 18px">
        <div style="font-size:1.5rem;font-weight:800;color:#fff"><?= $orgMerit - $orgDemerit ?></div>
        <div style="font-size:.7rem;color:rgba(255,255,255,.7);font-weight:600">Net Score</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

    <!-- DAILY MOTIVATION -->
    <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);overflow:hidden">
      <div style="padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:7px">
        <i class="fas fa-quote-left" style="color:var(--blue);font-size:.85rem"></i>
        <span style="font-size:.9rem;font-weight:700">Daily Motivation</span>
      </div>
      <div style="padding:20px 22px">
        <div style="font-size:.72rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px"><?= date('l, F j, Y') ?></div>
        <?php
        // Array of motivational quotes for youth
        $quotes = [
          "Your future is created by what you do today, not tomorrow.",
          "The only way to do great work is to love what you do.",
          "Believe in yourself and all that you are.",
          "Success is not final, failure is not fatal: it is the courage to continue that counts.",
          "Dream big, work hard, stay focused, and surround yourself with good people.",
          "Your only limit is you. Be brave and fearless.",
          "The future belongs to those who believe in the beauty of their dreams.",
          "Every accomplishment starts with the decision to try.",
          "Don't watch the clock; do what it does. Keep going.",
          "The harder you work for something, the greater you'll feel when you achieve it.",
          "Education is the most powerful weapon which you can use to change the world.",
          "Be the change you wish to see in the world.",
          "Your time is limited, don't waste it living someone else's life.",
          "Strive for progress, not perfection.",
          "The best way to predict the future is to create it.",
        ];
        // Get quote based on day of year (changes daily)
        $dayOfYear = (int)date('z');
        $quote = $quotes[$dayOfYear % count($quotes)];
        ?>
        <div style="font-size:1.05rem;font-weight:600;color:var(--gray-800);line-height:1.6;font-style:italic;margin-bottom:16px;padding-left:12px;border-left:3px solid var(--blue)">"<?= $quote ?>"</div>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--blue-pale);border-radius:8px;border:1px solid rgba(21,101,192,.1)">
          <i class="fas fa-lightbulb" style="color:var(--blue);font-size:1rem"></i>
          <span style="font-size:.8rem;color:var(--gray-700);line-height:1.4">Start your day with positivity! Every small step counts toward your dreams.</span>
        </div>
      </div>
    </div>

    <!-- GAMES & CLASSIFICATION -->
    <div style="display:flex;flex-direction:column;gap:14px">
      <!-- Games Card -->
      <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:7px">
          <i class="fas fa-gamepad" style="color:var(--blue);font-size:.85rem"></i>
          <span style="font-size:.9rem;font-weight:700">Mini Games</span>
        </div>
        <div style="padding:20px 22px">
          <div style="font-size:.72rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Take a Break</div>
          <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:16px;line-height:1.6">Take a quick break and play some fun games! Relax your mind while earning achievements.</p>
          <a href="games.php" style="display:inline-flex;align-items:center;gap:10px;padding:11px 18px;background:var(--blue);color:#fff;border-radius:9px;font-size:.88rem;font-weight:600;text-decoration:none;transition:.2s;border:1.5px solid var(--blue)" onmouseover="this.style.background='var(--blue-dark)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(21,101,192,.3)'" onmouseout="this.style.background='var(--blue)';this.style.transform='';this.style.boxShadow=''">
            <i class="fas fa-play-circle"></i>
            <span>Play Games Now</span>
          </a>
        </div>
      </div>

      <!-- Classification -->
      <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:7px">
          <i class="fas fa-id-card" style="color:var(--blue);font-size:.85rem"></i>
          <span style="font-size:.9rem;font-weight:700">My Classification</span>
        </div>
        <div style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:7px">
          <?php if ($cls): foreach ((array)$cls as $c): ?>
            <span style="background:var(--blue-pale);color:var(--blue);padding:4px 11px;border-radius:50px;font-size:.75rem;font-weight:700"><?= htmlspecialchars($c) ?></span>
          <?php endforeach; else: ?>
            <span style="color:var(--gray-400);font-size:.85rem">Not specified</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ANNOUNCEMENTS -->
  <div style="background:#fff;border-radius:12px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);overflow:hidden;margin-top:16px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:7px">
      <i class="fas fa-bullhorn" style="color:var(--blue);font-size:.85rem"></i>
      <span style="font-size:.9rem;font-weight:700">Latest Announcements</span>
    </div>
    <div style="padding:4px 0">
      <?php if (empty($announcements)): ?>
        <div style="padding:28px 18px;text-align:center;color:var(--gray-400)">
          <i class="fas fa-info-circle" style="font-size:2rem;margin-bottom:12px;opacity:.5"></i>
          <p style="font-size:.88rem">No upcoming events at the moment</p>
        </div>
      <?php else: ?>
        <?php
        $tagColors = [
          'seminar'=>['#1565c0','#e3f2fd'],
          'workshop'=>['#2e7d32','#e8f5e9'],
          'training'=>['#00796b','#e0f2f1'],
          'activity'=>['#f57f17','#fff8e1'],
          'meeting'=>['#7b1fa2','#f3e5f5'],
          'default'=>['#475569','#f1f5f9']
        ];
        foreach ($announcements as $event):
          $eventType = strtolower($event['event_type'] ?? 'activity');
          [$tc,$tb] = $tagColors[$eventType] ?? $tagColors['default'];
          $isNew = strtotime($event['created_at']) > strtotime('-24 hours');
        ?>
        <div style="padding:12px 18px;border-bottom:1px solid var(--gray-100);display:flex;align-items:flex-start;gap:12px">
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <div style="font-size:.88rem;font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($event['title']) ?></div>
              <?php if ($isNew): ?>
                <span style="background:#ef5350;color:#fff;padding:2px 7px;border-radius:50px;font-size:.65rem;font-weight:700;text-transform:uppercase">NEW</span>
              <?php endif; ?>
            </div>
            <div style="font-size:.78rem;color:var(--gray-600);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
              <span><i class="fas fa-calendar" style="margin-right:4px"></i><?= date('M j, Y', strtotime($event['event_date'])) ?></span>
              <?php if ($event['org_name']): ?>
                <span><i class="fas fa-users" style="margin-right:4px"></i><?= htmlspecialchars($event['org_name']) ?></span>
              <?php endif; ?>
              <span><i class="fas fa-star" style="margin-right:4px;color:var(--green)"></i>+<?= $event['merit_points'] ?> Merit Points</span>
            </div>
          </div>
          <span style="background:<?= $tb ?>;color:<?= $tc ?>;padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700;flex-shrink:0"><?= ucfirst($eventType) ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($announcements)): ?>
    <div style="padding:12px 18px;border-top:1px solid var(--gray-200);background:var(--gray-50);text-align:center">
      <a href="events.php" style="font-size:.82rem;color:var(--blue);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:.2s" onmouseover="this.style.color='var(--blue-dark)'" onmouseout="this.style.color='var(--blue)'">
        <span>View All Events</span>
        <i class="fas fa-arrow-right" style="font-size:.75rem"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<!-- Chatbot Bubble Button -->
<div class="chatbot-bubble" id="chatbotBubble">
  <i class="fas fa-comment"></i>
  <span>Chat</span>
</div>

<!-- Chatbot Widget Window -->
<div class="chatbot-modal" id="chatbotModal">
  <div class="chatbot-container">
    <div class="chatbot-header">
      <div class="chatbot-header-left">
        <i class="fas fa-robot"></i>
        <span>LYDO Wellbeing Assistant</span>
      </div>
      <button class="chatbot-close" id="chatbotClose">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="chatbot-iframe-wrapper">
      <iframe id="chatbotIframe" src="wellbeing_ai.html"></iframe>
    </div>
  </div>
</div>

<script>
const hamburger = document.getElementById('yHamburger');
const sidebar   = document.getElementById('ySidebar');
const overlay   = document.getElementById('yOverlay');
const closeBtn  = document.getElementById('ySidebarClose');

function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('open'); }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }

hamburger.addEventListener('click', openSidebar);
closeBtn.addEventListener('click',  closeSidebar);
overlay.addEventListener('click',   closeSidebar);

// Chatbot functionality
const chatbotBubble = document.getElementById('chatbotBubble');
const chatbotModal = document.getElementById('chatbotModal');
const chatbotClose = document.getElementById('chatbotClose');
const chatbotIframe = document.getElementById('chatbotIframe');

let isChatbotOpen = false;

function toggleChatbot() {
  isChatbotOpen = !isChatbotOpen;
  if (isChatbotOpen) {
    chatbotModal.classList.add('active');
    // Reload iframe on first open
    if (!chatbotIframe.src.includes('wellbeing_ai.html')) {
      chatbotIframe.src = 'wellbeing_ai.html';
    }
  } else {
    chatbotModal.classList.remove('active');
  }
}

function closeChatbot() {
  isChatbotOpen = false;
  chatbotModal.classList.remove('active');
}

chatbotBubble.addEventListener('click', toggleChatbot);
chatbotClose.addEventListener('click', closeChatbot);

// Close widget with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && chatbotModal.classList.contains('active')) {
    closeChatbot();
  }
});

// Prevent back button after logout
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
    // Page was loaded from cache (back button) - force reload
    window.location.reload();
  }
});
</script>
</body>
</html>
