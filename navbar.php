<?php require_once __DIR__ . '/config.php'; ?>
<?php
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
  'governance' => 1
];
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
  require_once __DIR__ . '/db.php';
  try {
    $stmt = $pdo->prepare("SELECT roadmaps, scorecard, performance_assessment, cascading, governance FROM user_page_access WHERE user_id = :id");
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $pageAccess = array_map(function($v){ return (int)$v; }, $row);
    }
  } catch (Throwable $e) {}
}
// Deadline banner context (for non-admin roles)
$deadline = null;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)) {
  require_once __DIR__ . '/db.php';
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
    $stmt = $pdo->prepare("SELECT enabled, end_time, message FROM deadline_controls WHERE role = :r");
    $stmt->execute([':r'=>$_SESSION['role']]);
    $deadline = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $e) {}
}
?>

<!-- Updated Navbar HTML with Example Nested Submenu -->
<nav class="navbar navbar-expand-xl navbar-dark sticky-top">
  <div class="container-fluid">
    <!-- Logo and Brand -->
    <a class="navbar-brand d-flex align-items-center me-4" href="<?php echo $dashboardLink; ?>">
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
      <ul class="navbar-nav mb-2 mb-xl-0">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['roadmaps'] ?>">Roadmaps</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/collab/collab.php">Collaborative Healthcare Management</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/research/research.php">Research</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/training/training.php">Training</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/culture/culture.php">Culture of Organization</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/resilience/resilience.php">Resilience</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/technology/technology.php">Technology</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/revenue/revenue.php">Revenue</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['scorecard'] ?>">Scorecard</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/roadmap.php">Roadmap</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/impact_indicator.php">Impact Indicator</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['performance_assessment'] ?>">Performance Assessment</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/operations_review.php">Operations Review</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/strategy_review.php">Strategy Review</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/strategy_refresh.php">Strategy Refresh</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['cascading'] ?>">Cascading</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/communication_plan.php">Communication Plan</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/cascading_activities.php">Cascading Activities</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/resources.php">Resources</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/gallery.php">Gallery</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" data-allowed="<?= (int)$pageAccess['governance'] ?>">Governance</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/governance_culture.php">Governance Culture</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/governance_sharing.php">Governance Sharing</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Organization</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/office_for_strategy_management.php">Office for Strategy Management</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/pgs_core_team.php">PGS Core Team</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/multi_sector_governance_system.php">Multi-Sector Governance System</a>
            </li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">About</a>
          <ul class="dropdown-menu">
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_charter_statements.php">Charter Statements</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_strategic_position.php">Strategic Position</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_strategy_map.php">Strategy Map</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_pgs_pathway.php">PGS Pathway</a>
            </li>
            <li class="dropdown-submenu">
              <a class="dropdown-item" href="<?= BASE_URL ?>/about_user_access.php">User Access</a>
            </li>
          </ul>
        </li>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/survey.php">Survey</a>
        </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Others</a>
            <ul class="dropdown-menu">
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/admin_deadline.php">Deadline Controls</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/notice.php">Notice</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/user_management.php">User Management</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/admin_backup_restore.php">Backup and Restore</a>
              </li>
              <li class="dropdown-submenu">
                <a class="dropdown-item" href="<?= BASE_URL ?>/survey.php">Survey</a>
              </li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>

      <!-- Right-aligned Notifications and Logout -->
      <ul class="navbar-nav">
        <li class="nav-item dropdown" id="notificationDropdown">
          <a class="nav-link text-white position-relative bell-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="bell-icon-wrap"><i data-lucide="bell"></i></span>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">
              0
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 360px; max-height: 480px; overflow-y: auto;">
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
          <span class="vr text-white-50" style="height: 1.4em;"></span>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<script>
  // Click-based toggling for submenus and nested submenus
  document.querySelectorAll('.dropdown-submenu').forEach(function(submenu) {
    const dropdownItem = submenu.querySelector('.dropdown-item');
    const dropdownMenu = submenu.querySelector('.dropdown-menu');

    if (dropdownItem && dropdownMenu) {
      dropdownItem.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default link behavior
        e.stopPropagation(); // Prevent parent dropdown from closing

        // Toggle 'show' class on the current submenu
        dropdownMenu.classList.toggle('show');

        // Close other open submenus at the same level
        submenu.parentElement.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
          if (openMenu !== dropdownMenu) {
            openMenu.classList.remove('show');
          }
        });
      });
    }
  });

  // Gate top-level dropdowns by access
  document.querySelectorAll('.nav-item.dropdown > .nav-link.dropdown-toggle').forEach(function(link) {
    link.addEventListener('click', function(e) {
      var allowed = this.getAttribute('data-allowed');
      if (allowed === '0') {
        e.preventDefault();
        e.stopPropagation();
        alert("You don't have access to this site. Contact administrator.");
      }
    });
  });

  // Block submenu item navigation when parent category is disallowed
  document.querySelectorAll('.nav-item.dropdown .dropdown-menu a.dropdown-item[href]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      var topItem = this.closest('.nav-item.dropdown');
      if (!topItem) return;
      var toggle = topItem.querySelector(':scope > .nav-link.dropdown-toggle');
      if (!toggle) return;
      var allowed = toggle.getAttribute('data-allowed');
      if (allowed === '0') {
        e.preventDefault();
        e.stopPropagation();
        alert("You don't have access to this site. Contact administrator.");
      }
    });
  });

  // Close all submenus when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-submenu') && !e.target.closest('.dropdown-toggle')) {
      document.querySelectorAll('.dropdown-submenu .dropdown-menu.show').forEach(function(menu) {
        menu.classList.remove('show');
      });
    }
  });

  // Ensure Bootstrap dropdowns close properly
  document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
    menu.addEventListener('click', function(e) {
      e.stopPropagation(); // Prevent dropdown from closing when clicking inside
    });
  });

  // Notification System
  (function() {
    var badge = document.querySelector('.notification-badge');
    var notifList = document.getElementById('notificationList');
    var markAllReadBtn = document.getElementById('markAllReadBtn');
    var notifDropdown = document.getElementById('notificationDropdown');
    var isOpen = false;

    function getTypeIcon(type) {
      var icons = {
        'upload': '<i data-lucide="upload"></i>',
        'approved': '<i data-lucide="check"></i>',
        'returned': '<i data-lucide="undo-2"></i>',
        'edit': '<i data-lucide="pencil"></i>'
      };
      return icons[type] || '<i data-lucide="bell"></i>';
    }

    function updateBadge(count) {
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'block';
      } else {
        badge.style.display = 'none';
      }
    }

    function renderNotifications(notifications) {
      if (!notifications.length) {
        notifList.innerHTML = '<div class="text-center text-muted py-4"><i data-lucide="bell-off" width="2em" height="2em" class="mb-2"></i><p class="mb-0 small">No notifications yet</p></div>';
        return;
      }

      var html = '';
      notifications.forEach(function(n) {
        var unreadClass = n.is_read ? '' : 'unread';
        html += '<div class="notification-item ' + unreadClass + '" data-id="' + n.id + '">' +
          '<div class="d-flex align-items-start">' +
            '<div class="notification-type-icon ' + n.type + ' me-3">' + getTypeIcon(n.type) + '</div>' +
            '<div class="flex-grow-1">' +
              '<div class="notification-title">' + escapeHtml(n.title) + '</div>' +
              '<div class="notification-message">' + escapeHtml(n.message) + '</div>' +
              '<div class="notification-time">' + n.time_ago + (n.related_type_display ? ' â€¢ ' + n.related_type_display : '') + '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
      });
      notifList.innerHTML = html;

      // Add click handlers to mark as read
      notifList.querySelectorAll('.notification-item').forEach(function(item) {
        item.addEventListener('click', function() {
          var id = this.dataset.id;
          markAsRead(id);
          this.classList.remove('unread');
        });
      });
    }

    function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function fetchUnreadCount() {
      fetch('<?= BASE_URL ?>/notifications_api.php?action=get_unread_count')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) updateBadge(data.count);
        })
        .catch(function() {});
    }

    function fetchNotifications() {
      fetch('<?= BASE_URL ?>/notifications_api.php?action=get_notifications')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) {
            renderNotifications(data.notifications);
            // Update badge with unread count from notifications
            var unreadCount = data.notifications.filter(function(n) { return !n.is_read; }).length;
            updateBadge(unreadCount);
          }
        })
        .catch(function() {});
    }

    function markAsRead(id) {
      var fd = new FormData();
      fd.append('action', 'mark_read');
      fd.append('id', id);
      fetch('<?= BASE_URL ?>/notifications_api.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) fetchUnreadCount();
        })
        .catch(function() {});
    }

    function markAllRead() {
      var fd = new FormData();
      fd.append('action', 'mark_all_read');
      fetch('<?= BASE_URL ?>/notifications_api.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.ok) {
            fetchNotifications();
            fetchUnreadCount();
          }
        })
        .catch(function() {});
    }

    // Event listeners
    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        markAllRead();
      });
    }

    // Fetch notifications when dropdown opens
    if (notifDropdown) {
      notifDropdown.addEventListener('shown.bs.dropdown', function() {
        isOpen = true;
        fetchNotifications();
      });
      notifDropdown.addEventListener('hidden.bs.dropdown', function() {
        isOpen = false;
      });
    }

    // Initial fetch
    fetchUnreadCount();

    // Poll for new notifications every 30 seconds
    setInterval(fetchUnreadCount, 30000);
  })();
</script>
<?php if ($deadline && (int)$deadline['enabled'] === 1): 
  $endTs = $deadline['end_time'] ? strtotime($deadline['end_time']) : null;
  $remaining = ($endTs !== false && $endTs) ? max(0, $endTs - time()) : 0;
  $msg = $deadline['message'] ?? 'Please comply with the submission requirements before the deadline.';
?>
<div id="deadlineBanner" style="position:fixed;bottom:0;left:0;right:0;z-index:1040;background:rgba(245,158,11,.85);color:#111827;padding:8px 12px;text-align:center;font-weight:700;box-shadow:0 -2px 6px rgba(0,0,0,.08);pointer-events:none;font-size:.95rem;">
  <span><?= htmlspecialchars($msg) ?></span>
  <span> â€¢ Time left: <span id="deadlineCountdown"><?= (int)$remaining ?></span></span>
</div>
<script>
  (function(){
    var rem = <?= json_encode((int)$remaining) ?>;
    var countdownEl = document.getElementById('deadlineCountdown');
    var banner = document.getElementById('deadlineBanner');
    // Add bottom padding so banner does not cover content
    function padBottom() {
      var bh = banner ? banner.offsetHeight : 32;
      document.body.style.paddingBottom = (bh + 8) + 'px';
    }
    padBottom();
    window.addEventListener('resize', padBottom);
    // Format function
    function fmt(s) {
      var d = Math.floor(s / 86400);
      s %= 86400;
      var h = Math.floor(s / 3600);
      s %= 3600;
      var m = Math.floor(s / 60);
      var sec = s % 60;
      var parts = [];
      if (d) parts.push(d+'d');
      parts.push(String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0'));
      return parts.join(' ');
    }
    function render() { countdownEl.textContent = fmt(rem); }
    function tick() {
      if (rem > 0) {
        rem--; render();
        if (rem === 0) onExpire();
      }
    }
    function onExpire() {
      banner.style.background = 'rgba(185,28,28,.85)';
      banner.style.color = '#fff';
      banner.innerHTML = 'Submission window has closed. You can browse pages but actions are disabled.';
      disableInteractions();
    }
    function disableInteractions() {
      var sel = 'button, .btn, a.btn, input:not([type=hidden]), select, textarea, [contenteditable=true]';
      document.querySelectorAll(sel).forEach(function(el){
        try { el.setAttribute('disabled','disabled'); } catch(e){}
        el.classList.add('disabled');
        el.style.pointerEvents = 'none';
      });
      document.querySelectorAll('form').forEach(function(f){
        f.addEventListener('submit', function(e){ e.preventDefault(); alert('Submission window has closed.'); });
      });
    }
    if (rem <= 0) { onExpire(); } else { render(); setInterval(tick, 1000); }
  })();
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): 
  $offset = (isset($deadline) && $deadline && (int)$deadline['enabled'] === 1) ? 56 : 16;
?>
<div class="position-fixed" style="right:16px; bottom: <?= $offset ?>px; z-index:1051;">
  <div class="alert alert-danger py-2 px-3 shadow-sm mb-0">
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
  </div>
  <script>
    setTimeout(function(){
      var el = document.querySelector('.position-fixed .alert.alert-danger');
      if (el) el.remove();
    }, 6000);
  </script>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>
