<?php
global $pdo;
$dashboardLink = BASE_URL . '/employee_dashboard.php';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $dashboardLink = BASE_URL . '/admin_dashboard.php';
    } elseif ($_SESSION['role'] === 'focal') {
        $dashboardLink = BASE_URL . '/focal_dashboard.php';
    }
}
// Load page access for current user (non-admin)
$pageAccess = [
  'roadmaps' => 1,
  'scorecard' => 1,
  'performance_assessment' => 1,
  'cascading' => 1,
  'governance' => 1,
];
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    try {
        $stmt = $pdo->prepare('SELECT roadmaps, scorecard, performance_assessment, cascading, governance FROM user_page_access WHERE user_id = :id');
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pageAccess = array_map(function ($v) {
                return (int)$v;
            }, $row);
        }
    } catch (Throwable $e) {
    }
}
// Deadline banner context (for non-admin roles)
$deadline = null;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)) {
    try {
        $pdo->exec("
      CREATE TABLE IF NOT EXISTS deadline_controls (
        role ENUM('employee','focal') PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        end_time DATETIME DEFAULT NULL,
        message VARCHAR(255) DEFAULT 'Please comply with the submission requirements before the deadline.',
        updated_by INT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
        $stmt = $pdo->prepare('SELECT enabled, end_time, message FROM deadline_controls WHERE role = :r');
        $stmt->execute([':r' => $_SESSION['role']]);
        $deadline = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
    }
}
?>

<!-- Updated Navbar HTML with Example Nested Submenu -->
<nav class="navbar navbar-expand-xl navbar-dark sticky-top bg-primary px-4 py-2">
  <div class="container-fluid">
    <!-- Logo and Brand -->
    <a class="navbar-brand d-flex align-items-center me-4" href="<?php echo h($dashboardLink); ?>">
      <img src="<?= BASE_URL ?>/img/final_logo1.png" alt="TRC DOH Logo">
    </a>

    <!-- Mobile Toggle Button -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navigation Menu -->
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <!-- Left-aligned Links -->
      <ul class="navbar-nav d-flex align-items-center gap-3 mb-0">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['roadmaps'] ?>">Roadmaps</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/collab/collab">Collaborative Healthcare Management</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/research/research">Research</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/training/training">Training</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/culture/culture">Culture of Organization</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/resilience/resilience">Resilience</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/technology/technology">Technology</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/revenue/revenue">Revenue</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['scorecard'] ?>">Scorecard</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/roadmap">Roadmap</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/impact_indicator">Impact Indicator</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['performance_assessment'] ?>">Performance Assessment</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/operations_review">Operations Review</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/strategy_review">Strategy Review</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/strategy_refresh">Strategy Refresh</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['cascading'] ?>">Cascading</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/communication_plan">Communication Plan</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/cascading_activities">Cascading Activities</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/resources">Resources</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/gallery">Gallery</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['governance'] ?>">Governance</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/governance_culture">Governance Culture</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/governance_sharing">Governance Sharing</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Organization</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/office_for_strategy_management">Office for Strategy Management</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/pgs_core_team">PGS Core Team</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/multi_sector_governance_system">Multi-Sector Governance System</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">About</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_charter_statements">Charter Statements</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_strategic_position">Strategic Position</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_strategy_map">Strategy Map</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_pgs_pathway">PGS Pathway</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_user_access">User Access</a>
            </li>
          </ul>
        </li>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/survey">Survey</a>
        </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Others</a>
            <ul class="dropdown-menu">
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/admin_deadline">Deadline Controls</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/notice">Notice</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/user_management">User Management</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/admin_backup_restore">Backup and Restore</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/survey">Survey</a>
              </li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>

      <!-- Right-aligned Notifications and Logout -->
      <ul class="navbar-nav ms-auto d-flex align-items-center gap-3 mb-0">
        <li class="nav-item dropdown" id="notificationDropdown">
          <a class="nav-link text-white position-relative bell-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="bell-icon-wrap"><i data-lucide="bell"></i></span>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">
              0
            </span>
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
          <a class="nav-link text-white" href="<?= BASE_URL ?>/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<script>
  window.PGS = { baseUrl: '<?= BASE_URL ?>' };
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
