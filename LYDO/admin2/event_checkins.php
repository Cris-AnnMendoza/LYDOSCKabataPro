<?php
/**
 * Event Check-ins Review Page
 * Admin can view selfie photos submitted during check-in/check-out
 * and revoke merit points if the youth wasn't actually at the event.
 */
require_once 'config.php';
requireLogin();

$pdo     = db();
$admin   = currentAdmin();
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

$evStmt = $pdo->prepare('SELECT e.*, o.name as org_name FROM events e LEFT JOIN organizations o ON o.id = e.organization_id WHERE e.id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();
if (!$event) { header('Location: events.php'); exit; }

// Handle revoke merit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'revoke_merit') {
        $userId = (int)$_POST['user_id'];
        $reason = trim($_POST['reason'] ?? 'Photo verification failed - not confirmed at event location');
        
        // Remove merit log
        $pdo->prepare('DELETE FROM user_merit_logs WHERE user_id=? AND event_id=? AND category="event_attendance"')->execute([$userId, $eventId]);
        
        // Mark certificate as not awarded
        $pdo->prepare('UPDATE event_certificates SET merit_awarded=0 WHERE event_id=? AND user_id=?')->execute([$eventId, $userId]);
        
        // Add demerit for cheating
        $pdo->prepare('INSERT INTO user_merit_logs(user_id,points,type,reason,category,event_id,awarded_by,created_at) VALUES(?,?,"demerit",?,"photo_verification_failed",?,?,CURRENT_TIMESTAMP)')
            ->execute([$userId, -2, $reason, $eventId, $admin['id']]);
        
        // Notify the youth
        $pdo->prepare('INSERT INTO notifications(user_id,title,message,type) VALUES(?,?,?,?)')
            ->execute([$userId, 'Merit Points Revoked', 'Your merit points for "'.htmlspecialchars($event['title']).'" have been revoked. Reason: '.$reason, 'warning']);
        
        flash('success', 'Merit points revoked and demerit applied.');
        header('Location: event_checkins.php?id=' . $eventId); exit;
    }
    
    if ($_POST['action'] === 'approve') {
        $userId = (int)$_POST['user_id'];
        // Mark as verified (add a flag)
        try { $pdo->exec("ALTER TABLE event_checkins ADD COLUMN photo_verified TINYINT(1) NOT NULL DEFAULT 0"); } catch(PDOException $e) {}
        $pdo->prepare('UPDATE event_checkins SET photo_verified=1 WHERE event_id=? AND user_id=?')->execute([$eventId, $userId]);
        flash('success', 'Photo verified and approved.');
        header('Location: event_checkins.php?id=' . $eventId); exit;
    }
}

// Add photo_verified column if not exists
try { $pdo->exec("ALTER TABLE event_checkins ADD COLUMN photo_verified TINYINT(1) NOT NULL DEFAULT 0"); } catch(PDOException $e) {}

// Load all check-ins with photos
$stmt = $pdo->prepare('
    SELECT c.*, u.first_name, u.last_name, u.barangay, u.email,
           cert.cert_number, cert.merit_awarded, cert.merit_points as cert_pts
    FROM event_checkins c
    JOIN youth_users u ON u.id = c.user_id
    LEFT JOIN event_certificates cert ON cert.event_id = c.event_id AND cert.user_id = c.user_id
    WHERE c.event_id = ?
    ORDER BY c.checked_in_at DESC
');
$stmt->execute([$eventId]);
$checkins = $stmt->fetchAll();

$eventDate = date('F j, Y', strtotime($event['event_date']));
$photoBase = '/LYDO/lydo-system/shared/uploads/event_photos/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Review Check-in Photos – <?= htmlspecialchars($event['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
<style>
.photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;margin-top:16px}
.photo-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;overflow:hidden;transition:.2s}
.photo-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);border-color:#90caf9}
.photo-card.flagged{border-color:#ef9a9a;background:#fff5f5}
.photo-card.verified{border-color:#a5d6a7;background:#f8fff8}
.pc-header{padding:14px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px}
.pc-avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#0d3b6e,#1565c0);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0}
.pc-name{font-weight:700;font-size:.9rem;color:#1e293b}
.pc-meta{font-size:.75rem;color:#94a3b8}
.pc-photos{display:grid;grid-template-columns:1fr 1fr;gap:2px;padding:2px}
.pc-photos img{width:100%;height:160px;object-fit:cover;cursor:pointer;transition:.2s}
.pc-photos img:hover{opacity:.85}
.pc-photo-label{position:absolute;bottom:6px;left:6px;background:rgba(0,0,0,.7);color:#fff;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:4px}
.pc-photo-wrap{position:relative}
.pc-footer{padding:12px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pc-time{font-size:.78rem;color:#475569;display:flex;align-items:center;gap:5px}
.btn-approve{padding:6px 14px;background:#e8f5e9;color:#2e7d32;border:1.5px solid #a5d6a7;border-radius:7px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.2s}
.btn-approve:hover{background:#2e7d32;color:#fff}
.btn-revoke{padding:6px 14px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:7px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.2s}
.btn-revoke:hover{background:#c62828;color:#fff}
.badge-verified{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:#e8f5e9;color:#2e7d32}
.badge-revoked{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:#ffebee;color:#c62828}
.badge-pending{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:#fff8e1;color:#f57f17}
.no-photo{width:100%;height:160px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.8rem;flex-direction:column;gap:4px}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat-mini{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center}
.stat-mini .val{font-size:1.5rem;font-weight:800;color:#0d3b6e;line-height:1}
.stat-mini .lbl{font-size:.75rem;color:#94a3b8;margin-top:4px;font-weight:500}
/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;cursor:pointer}
.lightbox.open{display:flex}
.lightbox img{max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
@media(max-width:600px){.photo-grid{grid-template-columns:1fr}.stat-row{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

<?php if ($msg=flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2><i class="fas fa-camera" style="color:#1565c0;margin-right:8px"></i>Review Check-in Photos</h2>
    <p><?= htmlspecialchars($event['title']) ?> · <?= $eventDate ?><?= $event['location'] ? ' · ' . htmlspecialchars($event['location']) : '' ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="event_qr.php?id=<?= $eventId ?>" class="btn-secondary"><i class="fas fa-qrcode"></i> QR Page</a>
    <a href="events.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Events</a>
  </div>
</div>

<!-- Stats -->
<?php
$totalCI = count($checkins);
$withPhotos = 0; $verified = 0; $revoked = 0;
foreach ($checkins as $ci) {
    if (!empty($ci['checkin_photo']) || !empty($ci['checkout_photo'])) $withPhotos++;
    if (!empty($ci['photo_verified'])) $verified++;
    if (!empty($ci['cert_number']) && empty($ci['merit_awarded'])) $revoked++;
}
?>
<div class="stat-row">
  <div class="stat-mini"><div class="val"><?= $totalCI ?></div><div class="lbl"><i class="fas fa-users" style="color:#1565c0"></i> Total Check-ins</div></div>
  <div class="stat-mini"><div class="val"><?= $withPhotos ?></div><div class="lbl"><i class="fas fa-camera" style="color:#7b1fa2"></i> With Photos</div></div>
  <div class="stat-mini"><div class="val"><?= $verified ?></div><div class="lbl"><i class="fas fa-check-circle" style="color:#2e7d32"></i> Verified</div></div>
  <div class="stat-mini"><div class="val"><?= $revoked ?></div><div class="lbl"><i class="fas fa-ban" style="color:#c62828"></i> Revoked</div></div>
</div>

<!-- Info banner -->
<div style="background:#e3f2fd;border:1.5px solid #90caf9;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px">
  <i class="fas fa-info-circle" style="color:#1565c0;font-size:1.1rem;margin-top:2px;flex-shrink:0"></i>
  <div style="font-size:.85rem;color:#1e293b;line-height:1.6">
    <strong>Photo Verification:</strong> Review the selfie photos submitted by youth during check-in and check-out. 
    If a photo doesn't confirm they were at the event location, you can <strong style="color:#c62828">revoke their merit points</strong> and apply a demerit.
  </div>
</div>

<?php if (empty($checkins)): ?>
  <div class="card" style="padding:40px;text-align:center;color:#94a3b8">
    <i class="fas fa-camera" style="font-size:2.5rem;margin-bottom:12px;display:block;opacity:.4"></i>
    <p>No check-ins yet for this event.</p>
  </div>
<?php else: ?>

<!-- Photo Grid -->
<div class="photo-grid">
<?php foreach ($checkins as $ci): 
    $hasCheckinPhoto = !empty($ci['checkin_photo']);
    $hasCheckoutPhoto = !empty($ci['checkout_photo']);
    $isVerified = !empty($ci['photo_verified']);
    $meritAwarded = !empty($ci['merit_awarded']);
    $cardClass = $isVerified ? 'verified' : (!$meritAwarded && !empty($ci['checked_out_at']) ? 'flagged' : '');
?>
<div class="photo-card <?= $cardClass ?>">
  <div class="pc-header">
    <div class="pc-avatar"><?= strtoupper(substr($ci['first_name'],0,1).substr($ci['last_name'],0,1)) ?></div>
    <div>
      <div class="pc-name"><?= htmlspecialchars($ci['first_name'].' '.$ci['last_name']) ?></div>
      <div class="pc-meta"><?= htmlspecialchars($ci['barangay'] ?: $ci['email'] ?: '—') ?></div>
    </div>
    <div style="margin-left:auto">
      <?php if ($isVerified): ?>
        <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
      <?php elseif (!$meritAwarded && !empty($ci['checked_out_at'])): ?>
        <span class="badge-revoked"><i class="fas fa-ban"></i> Revoked</span>
      <?php elseif ($meritAwarded): ?>
        <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Pending Review</span>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="pc-photos">
    <div class="pc-photo-wrap">
      <?php if ($hasCheckinPhoto): ?>
        <img src="<?= $photoBase . htmlspecialchars($ci['checkin_photo']) ?>" alt="Check-in selfie" onclick="openLightbox(this.src)"/>
        <div class="pc-photo-label"><i class="fas fa-sign-in-alt"></i> Check-in</div>
      <?php else: ?>
        <div class="no-photo"><i class="fas fa-image" style="font-size:1.2rem"></i><span>No check-in photo</span></div>
      <?php endif; ?>
    </div>
    <div class="pc-photo-wrap">
      <?php if ($hasCheckoutPhoto): ?>
        <img src="<?= $photoBase . htmlspecialchars($ci['checkout_photo']) ?>" alt="Check-out selfie" onclick="openLightbox(this.src)"/>
        <div class="pc-photo-label"><i class="fas fa-sign-out-alt"></i> Check-out</div>
      <?php else: ?>
        <div class="no-photo"><i class="fas fa-image" style="font-size:1.2rem"></i><span>No check-out photo</span></div>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="pc-footer">
    <div style="flex:1;display:flex;flex-direction:column;gap:4px">
      <div class="pc-time"><i class="fas fa-sign-in-alt" style="color:#2e7d32"></i> In: <?= date('g:i A', strtotime($ci['checked_in_at'])) ?></div>
      <?php if (!empty($ci['checked_out_at'])): ?>
        <div class="pc-time"><i class="fas fa-sign-out-alt" style="color:#c62828"></i> Out: <?= date('g:i A', strtotime($ci['checked_out_at'])) ?></div>
      <?php else: ?>
        <div class="pc-time" style="color:#f57f17"><i class="fas fa-hourglass-half"></i> Not yet checked out</div>
      <?php endif; ?>
      <?php if (!empty($ci['cert_number'])): ?>
        <div class="pc-time"><i class="fas fa-certificate" style="color:#1565c0"></i> <?= htmlspecialchars($ci['cert_number']) ?></div>
      <?php endif; ?>
    </div>
    
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php if (!$isVerified && $meritAwarded): ?>
        <!-- Approve -->
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="approve"/>
          <input type="hidden" name="user_id" value="<?= $ci['user_id'] ?>"/>
          <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
        </form>
        <!-- Revoke -->
        <button class="btn-revoke" onclick="openRevoke(<?= $ci['user_id'] ?>,'<?= htmlspecialchars($ci['first_name'].' '.$ci['last_name'], ENT_QUOTES) ?>')">
          <i class="fas fa-ban"></i> Revoke
        </button>
      <?php elseif (!$isVerified && !$meritAwarded && !empty($ci['checked_out_at'])): ?>
        <span style="font-size:.75rem;color:#c62828;font-weight:600"><i class="fas fa-ban"></i> Merit already revoked</span>
      <?php elseif ($isVerified): ?>
        <span style="font-size:.75rem;color:#2e7d32;font-weight:600"><i class="fas fa-check-circle"></i> Approved</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</main>
</div>

<!-- Lightbox for full-size photo -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('open')">
  <img id="lightboxImg" src="" alt="Full size photo"/>
</div>

<!-- Revoke Modal -->
<div id="revokeModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-head" style="background:linear-gradient(135deg,#c62828,#e53935)">
      <h3><i class="fas fa-ban"></i> Revoke Merit Points</h3>
      <button onclick="document.getElementById('revokeModal').style.display='none'" class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="modal-body" style="padding:20px">
      <input type="hidden" name="action" value="revoke_merit"/>
      <input type="hidden" name="user_id" id="revoke_user_id"/>
      
      <div style="background:#ffebee;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;color:#c62828;line-height:1.6">
        <i class="fas fa-exclamation-triangle"></i> You are about to revoke merit points from <strong id="revoke_name"></strong>. 
        This will also apply a <strong>-2 demerit</strong> for failed photo verification.
      </div>
      
      <div class="fg">
        <label>Reason for Revocation <span class="req">*</span></label>
        <textarea name="reason" rows="3" required placeholder="e.g. Photo does not show the youth at the event location. Background doesn't match venue."></textarea>
      </div>
      
      <div class="modal-footer" style="padding:0;margin-top:16px;border-top:1px solid #e2e8f0;padding-top:14px">
        <button type="button" class="btn-secondary" onclick="document.getElementById('revokeModal').style.display='none'">Cancel</button>
        <button type="submit" style="padding:10px 20px;background:#c62828;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
          <i class="fas fa-ban"></i> Revoke & Apply Demerit
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
}

function openRevoke(userId, name) {
  document.getElementById('revoke_user_id').value = userId;
  document.getElementById('revoke_name').textContent = name;
  document.getElementById('revokeModal').style.display = 'flex';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('revokeModal').style.display = 'none';
  }
});
</script>
</body>
</html>
