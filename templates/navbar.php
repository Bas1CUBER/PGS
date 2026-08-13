<?php
global $pdo;
$role = session_get('role') ?? 'guest';
$userId = (int)(session_get('user_id') ?? 0);

// Dashboard link (clean URLs)
$dashboardLinks = ['admin' => 'admin_dashboard', 'focal' => 'focal_dashboard', 'employee' => 'employee_dashboard'];
$dashboardLink = BASE_URL . '/' . ($dashboardLinks[$role] ?? 'employee_dashboard');

// Page access for non-admin (session-cached 60s)
$pageAccess = [
  'roadmaps' => 1,
  'scorecard' => 1,
  'performance_assessment' => 1,
  'cascading' => 1,
  'governance' => 1,
];
if ($role !== 'admin' && $userId > 0) {
    $accessCache = 'pgs_access_' . $userId;
    if (!isset($_SESSION[$accessCache]) || $_SESSION[$accessCache]['t'] < time() - 60) {
        try {
            $stmt = $pdo->prepare('SELECT roadmaps, scorecard, performance_assessment, cascading, governance FROM user_page_access WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $pageAccess = array_map('intval', $row);
            }
            $_SESSION[$accessCache] = ['t' => time(), 'data' => $pageAccess];
        } catch (Throwable $e) {
        }
    } else {
        $pageAccess = $_SESSION[$accessCache]['data'];
    }
}

// Deadline banner context (non-admin roles, session-cached 60s)
$deadline = null;
if (in_array($role, ['employee', 'focal'], true)) {
    $deadlineCache = 'pgs_deadline_' . $role;
    if (!isset($_SESSION[$deadlineCache]) || $_SESSION[$deadlineCache]['t'] < time() - 60) {
        try {
            $stmt = $pdo->prepare('SELECT enabled, end_time, message FROM deadline_controls WHERE role = :r');
            $stmt->execute([':r' => $role]);
            $deadline = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $_SESSION[$deadlineCache] = ['t' => time(), 'data' => $deadline];
        } catch (Throwable $e) {
        }
    } else {
        $deadline = $_SESSION[$deadlineCache]['data'];
    }
}

// Current page (for aria-current)
$currentPage = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

// Navigation config — single source of truth for every role
$menus = [
  ['key' => 'roadmaps', 'label' => 'Roadmaps', 'gated' => true, 'items' => [
      ['label' => 'Collaborative Healthcare Management', 'url' => 'collab/collab'],
      ['label' => 'Research', 'url' => 'research/research'],
      ['label' => 'Training', 'url' => 'training/training'],
      ['label' => 'Culture of Organization', 'url' => 'culture/culture'],
      ['label' => 'Resilience', 'url' => 'resilience/resilience'],
      ['label' => 'Technology', 'url' => 'technology/technology'],
      ['label' => 'Revenue', 'url' => 'revenue/revenue'],
  ]],
  ['key' => 'scorecard', 'label' => 'Scorecard', 'gated' => true, 'items' => [
      ['label' => 'Roadmap', 'url' => 'roadmap'],
      ['label' => 'Impact Indicator', 'url' => 'impact_indicator'],
  ]],
  ['key' => 'performance_assessment', 'label' => 'Performance Assessment', 'gated' => true, 'items' => [
      ['label' => 'Operations Review', 'url' => 'operations_review'],
      ['label' => 'Strategy Review', 'url' => 'strategy_review'],
      ['label' => 'Strategy Refresh', 'url' => 'strategy_refresh'],
  ]],
  ['key' => 'cascading', 'label' => 'Cascading', 'gated' => true, 'items' => [
      ['label' => 'Communication Plan', 'url' => 'communication_plan'],
      ['label' => 'Cascading Activities', 'url' => 'cascading_activities'],
      ['label' => 'Resources', 'url' => 'resources'],
      ['label' => 'Gallery', 'url' => 'gallery'],
  ]],
  ['key' => 'governance', 'label' => 'Governance', 'gated' => true, 'items' => [
      ['label' => 'Governance Culture', 'url' => 'governance_culture'],
      ['label' => 'Governance Sharing', 'url' => 'governance_sharing'],
  ]],
  ['key' => 'organization', 'label' => 'Organization', 'gated' => false, 'items' => [
      ['label' => 'Office for Strategy Management', 'url' => 'office_for_strategy_management'],
      ['label' => 'PGS Core Team', 'url' => 'pgs_core_team'],
      ['label' => 'Multi-Sector Governance System', 'url' => 'multi_sector_governance_system'],
  ]],
  ['key' => 'about', 'label' => 'About', 'gated' => false, 'items' => [
      ['label' => 'Charter Statements', 'url' => 'about_charter_statements'],
      ['label' => 'Strategic Position', 'url' => 'about_strategic_position'],
      ['label' => 'Strategy Map', 'url' => 'about_strategy_map'],
      ['label' => 'PGS Pathway', 'url' => 'about_pgs_pathway'],
      ['label' => 'User Access', 'url' => 'about_user_access'],
  ]],
];
if ($role === 'admin') {
    $menus[] = ['key' => 'others', 'label' => 'Others', 'gated' => false, 'items' => [
        ['label' => 'Deadline Controls', 'url' => 'admin_deadline'],
        ['label' => 'Notice', 'url' => 'notice'],
        ['label' => 'User Management', 'url' => 'user_management'],
        ['label' => 'Backup and Restore', 'url' => 'admin_backup_restore'],
        ['label' => 'Survey', 'url' => 'survey'],
    ]];
}
?>

<nav class="navbar navbar-expand-xl navbar-dark sticky-top bg-primary px-4 py-2">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center me-4" href="<?= h($dashboardLink) ?>">
      <img src="<?= BASE_URL ?>/img/final_logo1.png" alt="TRC DOH Logo" width="189" height="56">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav d-flex align-items-center gap-3 mb-0">
        <?php foreach ($menus as $menu):
            if ($menu['gated'] && ($pageAccess[$menu['key']] ?? 0) !== 1) {
                continue;
            } ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?= h($menu['label']) ?></a>
          <ul class="dropdown-menu">
            <?php foreach ($menu['items'] as $item):
                $itemPage = basename($item['url']);
                $isCurrent = ($currentPage === $itemPage); ?>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/<?= h($item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>><?= h($item['label']) ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endforeach; ?>

        <?php if (in_array($role, ['employee', 'focal'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/survey"<?= $currentPage === 'survey' ? ' aria-current="page"' : '' ?>>Survey</a>
        </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto d-flex align-items-center gap-3 mb-0">
        <li class="nav-item dropdown" id="notificationDropdown">
          <a class="nav-link text-white position-relative bell-link" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
            <span class="bell-icon-wrap"><i data-lucide="bell"></i></span>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">0</span>
          </a>
          <div class="dropdown-menu dropdown-menu-end notification-dropdown">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light sticky-top">
              <h6 class="mb-0"><i data-lucide="bell" class="me-2"></i>Notifications</h6>
              <button class="btn btn-sm btn-link text-decoration-none p-0" id="markAllReadBtn">Mark all as read</button>
            </div>
            <div id="notificationList" class="notification-list">
              <div class="text-center text-muted py-4">
                <i data-lucide="bell-off" width="2em" height="2em" class="mb-2"></i>
                <p class="mb-0 small">No notifications yet</p>
              </div>
            </div>
            <div class="px-3 py-2 border-top bg-light text-center small text-muted">
              Showing 30 most recent notifications
            </div>
          </div>
        </li>
        <li class="nav-item d-flex align-items-center" aria-hidden="true">
          <span class="vr text-white-50"></span>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= BASE_URL ?>/logout"><?= ui_icon('log-out', 16, 'me-1') ?> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<script>
  window.PGS = { baseUrl: '<?= BASE_URL ?>', csrf: '<?= csrf_token() ?>' };
</script>
<?php if ($deadline && (int)$deadline['enabled'] === 1):
    $endTs = $deadline['end_time'] ? strtotime($deadline['end_time']) : null;
    $remaining = ($endTs !== false && $endTs) ? max(0, $endTs - time()) : 0;
    $msg = $deadline['message'] ?? 'Please comply with the submission requirements before the deadline.';
    ?>
<div id="deadlineBanner" class="deadline-banner" data-remaining="<?= (int)$remaining ?>">
  <span><?= h($msg) ?></span>
  <span> &bull; Time left: <span id="deadlineCountdown"><?= (int)$remaining ?></span></span>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])):
    $offset = (isset($deadline) && (int)$deadline['enabled'] === 1) ? 56 : 16;
    ?>
<div class="flash-toast" data-duration="6000" style="right:16px; bottom: <?= $offset ?>px;">
  <div class="alert alert-danger py-2 px-3 shadow-sm mb-0">
    <?= h($_SESSION['flash_error']) ?>
  </div>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<script src="<?= asset('js/app.js') ?>"></script>
