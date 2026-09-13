<?php
require_once 'config.php';
requireLogin();
if (!hasPermission('view_users')) { header('Location: dashboard.php'); exit; }

$admin  = currentAdmin();
$pdo    = db();
$search = trim($_GET['search'] ?? '');
$brgy   = trim($_GET['barangay'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && hasPermission('delete_users')) {
    $pdo->prepare('DELETE FROM youth_users WHERE id = ?')->execute([(int)$_POST['delete_id']]);
    flash('success', 'User deleted successfully.');
    header('Location: users.php'); exit;
}

// Build query
$where  = [];
$params = [];
if ($admin['role'] === 'barangay_admin' && $admin['barangay']) {
    $where[]  = 'barangay = ?';
    $params[] = $admin['barangay'];
}
if ($search) {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR barangay LIKE ?)';
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s);
}
if ($brgy && $admin['role'] !== 'barangay_admin') {
    $where[]  = 'barangay = ?';
    $params[] = $brgy;
}
$sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM youth_users $sql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = ceil($total / $limit);

$stmt = $pdo->prepare("SELECT * FROM youth_users $sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Barangay list for filter
$brgyList = $pdo->query("SELECT DISTINCT barangay FROM youth_users WHERE barangay IS NOT NULL ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Youth Users – LYDO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin.css"/>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-wrap">
<?php include 'topbar.php'; ?>
<main class="content">

  <?php if ($msg = flash('success')): ?>
    <div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <div><h2>Youth Users</h2><p>Total: <strong><?= number_format($total) ?></strong> registered youth</p></div>
  </div>

  <!-- SEARCH & FILTER -->
  <div class="toolbar">
    <form method="GET" class="toolbar-form" id="searchForm">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="search" id="searchInput" placeholder="Search name, email, barangay..." value="<?= htmlspecialchars($search) ?>" autocomplete="off" autofocus/>
      </div>
      <?php if ($admin['role'] !== 'barangay_admin'): ?>
      <select name="barangay" class="filter-sel" id="barangayFilter">
        <option value="">All Barangays</option>
        <?php foreach ($brgyList as $b): ?>
          <option value="<?= htmlspecialchars($b) ?>" <?= $brgy===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <?php if ($search || $brgy): ?>
        <a href="users.php" class="btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Gender</th>
            <th>Barangay</th><th>Classification</th><th>Registered</th>
            <?php if (hasPermission('delete_users')): ?><th>Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" class="empty">No users found.</td></tr>
        <?php else: foreach ($users as $i => $u):
          $cls = $u['youth_classification'] ? json_decode($u['youth_classification'],true) : null;
          $cls = is_array($cls) ? $cls[0] : ($u['youth_classification'] ?: '—');
        ?>
          <tr>
            <td><?= ($page-1)*$limit+$i+1 ?></td>
            <td>
              <strong><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></strong>
              <?php if ($u['suffix']): ?><small><?= htmlspecialchars($u['suffix']) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['gender'] ?: '—') ?></td>
            <td><?= htmlspecialchars($u['barangay'] ?: '—') ?></td>
            <td><span class="badge blue"><?= htmlspecialchars($cls) ?></span></td>
            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <?php if (hasPermission('delete_users')): ?>
            <td>
              <a href="view_user.php?id=<?= $u['id'] ?>" class="btn-icon teal" title="View"><i class="fas fa-eye"></i></a>
              <button type="button" class="btn-icon red" title="Delete" onclick="showDeleteModal_User(<?= $u['id'] ?>, '<?= htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES) ?>')">
                <i class="fas fa-trash"></i>
              </button>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1; $i<=$pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&barangay=<?= urlencode($brgy) ?>"
           class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<?php include 'delete_modal.php'; ?>

<script>
function showDeleteModal_User(userId, userName) {
  showDeleteModal({
    title: 'Delete User',
    message: `Are you sure you want to delete <strong style="color:#e53935;">${userName}</strong>?`,
    warning: 'All data associated with this user will be permanently removed from the system.',
    buttonText: 'Delete User',
    formData: { delete_id: userId }
  });
}

// Auto-search as user types
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const barangayFilter = document.getElementById('barangayFilter');

function performSearch() {
  const searchValue = searchInput.value;
  const barangayValue = barangayFilter ? barangayFilter.value : '';
  
  // Save cursor position before reload
  localStorage.setItem('searchCursorPos', searchInput.selectionStart);
  
  // Update URL and reload
  const url = new URL(window.location);
  url.searchParams.set('search', searchValue);
  if (barangayFilter) {
    url.searchParams.set('barangay', barangayValue);
  }
  url.searchParams.set('page', '1');
  
  window.location.href = url.toString();
}

if (searchInput) {
  // Restore cursor position after page load
  const savedPos = localStorage.getItem('searchCursorPos');
  if (savedPos !== null) {
    searchInput.setSelectionRange(savedPos, savedPos);
    localStorage.removeItem('searchCursorPos');
  }
  
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 500);
  });
}

if (barangayFilter) {
  barangayFilter.addEventListener('change', performSearch);
}
</script>

</body>
</html>
