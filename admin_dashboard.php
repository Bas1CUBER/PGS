<?php
require_once __DIR__ . '/src/bootstrap.php';

// Optional: restrict access if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: " . BASE_URL . "/login");
  exit();
}

// Fetch all notices using PDO including image and video link
$sql = "SELECT notice_id, title, description, image, video, created_at 
        FROM notices ORDER BY created_at DESC LIMIT 12";
$stmt = $pdo->query($sql);

$notices = [];
if ($stmt && $stmt->rowCount() > 0) {
  $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$roadmapApprovals = [];
$perfGovApprovals = [];
try {
  $moduleMap = [
    'collab' => ['label' => 'Collaborative Healthcare Management', 'link' => 'collab/collab.php'],
    'research' => ['label' => 'Research', 'link' => 'research/research.php'],
    'training' => ['label' => 'Training', 'link' => 'training/training.php'],
    'culture' => ['label' => 'Culture of Organization', 'link' => 'culture/culture.php'],
    'resilience' => ['label' => 'Resilience', 'link' => 'resilience/resilience.php'],
    'technology' => ['label' => 'Technology', 'link' => 'technology/technology.php'],
    'revenue' => ['label' => 'Revenue', 'link' => 'revenue/revenue.php']
  ];
  $modules = array_keys($moduleMap);
  $inPlaceholders = implode(',', array_fill(0, count($modules), '?'));
  $stmtP = $pdo->prepare("
    SELECT p.*, u.email AS submitter_email
    FROM progress_pending_changes p
    JOIN users u ON p.submitted_by = u.id
    WHERE p.decision = 'Pending' AND p.module IN ($inPlaceholders)
    ORDER BY p.submitted_at DESC
    LIMIT 100
  ");
  foreach ($modules as $i => $m) {
    $stmtP->bindValue($i + 1, $m, PDO::PARAM_STR);
  }
  if ($stmtP->execute()) {
    foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $email = $r['submitter_email'] ?? '';
      $uploader = strtoupper(explode('@', $email)[0] ?? 'USER');
      $mm = $moduleMap[$r['module']] ?? null;
      if (!$mm) continue;
      $roadmapApprovals[] = [
        'deliverable' => (string)($mm['label'] ?? ''),
        'time' => $r['submitted_at'] ?? null,
        'user' => $uploader,
        'link' => $mm['link']
      ];
    }
  }
} catch (Throwable $e) {}
try {
  $stmtO = $pdo->query("
    SELECT o.id, o.original_name, o.uploaded_at, u.email AS uploader_email
    FROM operations_review_uploads o
    JOIN users u ON o.employee_id = u.id
    WHERE o.status = 'Pending'
    ORDER BY o.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtO ? $stmtO->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Operations Review',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'operations_review_new.php'
    ];
  }
} catch (Throwable $e) {}
try {
  $stmtS = $pdo->query("
    SELECT o.id, o.original_name, o.uploaded_at, u.email AS uploader_email
    FROM strategy_review_uploads o
    JOIN users u ON o.employee_id = u.id
    WHERE o.status = 'Pending'
    ORDER BY o.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtS ? $stmtS->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Strategy Review',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'strategy_review.php'
    ];
  }
} catch (Throwable $e) {}
try {
  $stmtC = $pdo->query("
    SELECT o.id, o.original_name, o.uploaded_at, u.email AS uploader_email
    FROM communication_plan_uploads o
    JOIN users u ON o.employee_id = u.id
    WHERE o.status = 'Pending'
    ORDER BY o.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtC ? $stmtC->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Communication Plan',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'communication_plan.php'
    ];
  }
} catch (Throwable $e) {}
try {
  $stmtCA = $pdo->query("
    SELECT c.id, c.title, c.uploaded_at, u.email AS uploader_email
    FROM cascading_activities c
    JOIN users u ON c.uploaded_by = u.id
    ORDER BY c.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtCA ? $stmtCA->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Cascading Activities',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'cascading_activities.php'
    ];
  }
} catch (Throwable $e) {}
try {
  $stmtGC = $pdo->query("
    SELECT g.id, g.uploaded_at, u.email AS uploader_email
    FROM governance_culture_uploads g
    JOIN users u ON g.employee_id = u.id
    WHERE g.status = 'In Progress'
    ORDER BY g.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtGC ? $stmtGC->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Governance: Culture',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'governance_culture.php'
    ];
  }
} catch (Throwable $e) {}
try {
  $stmtGS = $pdo->query("
    SELECT g.id, g.uploaded_at, u.email AS uploader_email
    FROM governance_sharing_uploads g
    JOIN users u ON g.employee_id = u.id
    WHERE g.status = 'In Progress'
    ORDER BY g.uploaded_at DESC
    LIMIT 100
  ");
  foreach ($stmtGS ? $stmtGS->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
    $uploader = strtoupper(explode('@', (string)($r['uploader_email'] ?? ''))[0] ?? 'USER');
    $perfGovApprovals[] = [
      'page' => 'Governance: Sharing',
      'time' => $r['uploaded_at'] ?? null,
      'user' => $uploader,
      'link' => 'governance_sharing.php'
    ];
  }
} catch (Throwable $e) {}
$pendingCount = count($roadmapApprovals) + count($perfGovApprovals);
?>


<?php
$pageTitle = 'Admin Dashboard';
$pageStyles = 'html, body {
      background-color: #f5f7fa;
      color: #2c3e50;
      height: 100%;
      margin: 0;
      padding-top: 20px;
    }

    .page-wrapper {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    main {
      flex: 1;
    }

    .bg-green {
      background-color: #0b4aa2 !important;
    }

    /* Hero Section */
    .hero {
      background: linear-gradient(90deg, #1e88e5 0%, #0d5bd1 50%, #0b4aa2 100%);
      color: white;
      padding: 34px 24px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 24px;
      position: relative;
      min-height: 210px;
      margin-top: 78px;
      margin-left: 24px;
      margin-right: 24px;
    }

    /* Lamp: subtle left-side decoration, fully inside the hero */
    .hero-lamp {
      position: absolute;
      left: 48px;
      top: 50%;
      transform: translateY(-50%);
      flex-shrink: 0;
      opacity: 1;
      line-height: 0;
    }

    .hero-lamp img {
      height: 160px;
      width: auto;
      display: block;
      filter: none;
    }

    .hero-content {
      text-align: center;
    }

    .hero h1 {
      font-size: 2.25rem;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .hero p {
      font-size: 1.05rem;
      margin: 0 auto;
      max-width: 600px;
      opacity: 0.96;
    }

    /* Notices Section */
    .notice-card {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e9edf2;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .notice-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.10);
      border-color: #d0d7de;
      background: #ffffff;
    }
    /* Preserve exact normal text colors on hover (scoped to notice card only) */
    .notice-card:hover h5,
    .notice-card:hover .card-title {
      color: #2c3e50;
    }
    .notice-card:hover p,
    .notice-card:hover small,
    .notice-card:hover .text-muted {
      color: #6c757d;
    }
    .notice-card:hover a {
      color: #0d6efd;
    }

    .notice-card img {
      max-width: 100%;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .notice-card a {
      text-decoration: underline;
      color: #0d6efd;
      word-break: break-word;
    }

    .notice-card h5 {
      font-weight: 700;
      color: #2c3e50;
    }

    .notice-card p {
      color: #6c757d;
      margin-bottom: 10px;
    }

    .approvals-card {
      border: none;
      border-radius: 12px;
      background: #ffffff;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }
    .approvals-header {
      background: linear-gradient(90deg, #1e88e5 0%, #0d5bd1 50%, #0b4aa2 100%);
      color: #fff;
      border-radius: 12px 12px 0 0;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .approvals-header .title {
      font-weight: 700;
      letter-spacing: .02em;
    }
    .approvals-header .badge {
      background: #fff;
      color: #0b4aa2
    }
    .approvals-table th {
      background-color: #f0f2f5;
      color: #34495e;
      font-weight: 600;
    }
    .approvals-table td {
      vertical-align: middle;
    }
    .truncate {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    /* Empty state */
    .empty-panel {
      background: linear-gradient(90deg, #f1f3f5 0%, #e9ecef 50%, #f1f3f5 100%);
      background-size: 200% 100%;
      border: 1px solid #e2e6ea;
      border-radius: 16px;
      padding: 36px 18px;
      animation: shimmer 6s ease-in-out infinite;
      color: #36454f;
    }
    @keyframes shimmer {
      0% { background-position: 0% 0; }
      50% { background-position: 100% 0; }
      100% { background-position: 0% 0; }
    }
    .empty-icon {
      color: #6c757d;
      animation: floaty 4s ease-in-out infinite;
    }
    @keyframes floaty {
      0% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
      100% { transform: translateY(0); }
    }

    /* Modal image fix */
    #noticeModal img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 10px 0;
      border-radius: 6px;
    }

    @media (max-width: 768px) {
      .hero {
        padding: 26px 16px;
        min-height: 150px;
        margin-top: 70px;
        margin-left: 12px;
        margin-right: 12px;
      }
      .hero h1 {
        font-size: 1.6rem;
      }
      .hero p {
        font-size: 0.9rem;
      }
      .hero-lamp {
        left: 16px;
        opacity: 1;
      }
      .hero-lamp img {
        height: 100px;
      }
    }';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<!-- Hero Section -->
    <section class="hero">
      <div class="hero-lamp">
        <img src="img/lamp.png" alt="PGS Lamp">
      </div>
      <div class="hero-content">
        <h1>Welcome, Admin!</h1>
        <p>Manage notices, view images, and share video links with employees.</p>
      </div>
    </section>

    <div class="container my-5">
      <div class="card approvals-card">
        <div class="approvals-header">
          <div class="title"><i data-lucide="bell" class="me-2"></i>Pending Validation</div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill"><?php echo (int)$pendingCount; ?></span>
            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#approvalsCollapse" aria-expanded="false" aria-controls="approvalsCollapse">
<i data-lucide="chevron-down" class="me-1"></i> Show
            </button>
          </div>
        </div>
        <div class="collapse" id="approvalsCollapse">
          <div class="card-body">
            <?php if ($pendingCount === 0): ?>
              <div class="text-center text-muted py-4">
                <i data-lucide="check-circle" width="2em" height="2em" class="mb-2" style="color:#2ecc71;"></i>
                <div>No items require your approval right now.</div>
              </div>
            <?php else: ?>
            <div class="mb-4">
              <h5 class="fw-semibold mb-2">Roadmaps</h5>
              <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle approvals-table">
                  <thead>
                    <tr>
                      <th class="w-30">Deliverables</th>
                      <th class="w-30">Date/Time</th>
                      <th class="w-25">User</th>
                      <th style="width: 15%;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($roadmapApprovals)): ?>
                      <tr><td colspan="4" class="text-center text-muted">No pending deliverables.</td></tr>
                    <?php else: foreach ($roadmapApprovals as $item): ?>
                      <tr>
                        <td class="fw-semibold">
                          <i data-lucide="list-checks" class="me-2 text-primary"></i>
                          <?php echo h($item['deliverable']); ?>
                        </td>
                        <td><?php echo $item['time'] ? h(date('Y-m-d H:i:s', strtotime($item['time']))) : 'Ã¢â‚¬â€'; ?></td>
                        <td><span class="badge bg-secondary"><?php echo h($item['user']); ?></span></td>
                        <td>
                          <?= ui_btn('Go To Page', ['href' => $item['link'], 'icon' => 'arrow-right', 'variant' => 'outline-primary', 'size' => 'sm']) ?>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div>
              <h5 class="fw-semibold mb-2">Performance Assessment, Cascading, and Governance</h5>
              <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle approvals-table">
                  <thead>
                    <tr>
                      <th class="w-30">Deliverables</th>
                      <th class="w-30">Date/Time</th>
                      <th class="w-25">User</th>
                      <th style="width: 15%;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($perfGovApprovals)): ?>
                      <tr><td colspan="4" class="text-center text-muted">No pending items.</td></tr>
                    <?php else: foreach ($perfGovApprovals as $item): ?>
                      <tr>
                        <td class="fw-semibold">
                          <i data-lucide="file-check" class="me-2 text-primary"></i>
                          <?php echo h($item['page']); ?>
                        </td>
                        <td><?php echo $item['time'] ? h(date('Y-m-d H:i:s', strtotime($item['time']))) : 'Ã¢â‚¬â€'; ?></td>
                        <td><span class="badge bg-secondary"><?php echo h($item['user']); ?></span></td>
                        <td>
                          <?= ui_btn('Go To Page', ['href' => $item['link'], 'icon' => 'arrow-right', 'variant' => 'outline-primary', 'size' => 'sm']) ?>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Notices Section -->
    <div class="container my-5">
      <h3 class="mb-4 fw-bold">Recent Notices</h3>
      <?php if (!empty($notices)): ?>
        <div class="row g-4">
          <?php foreach ($notices as $notice): ?>
            <div class="col-md-4">
              <div class="card notice-card shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                  <div>
                    <h5 class="card-title"><?php echo h($notice['title']); ?></h5>
                    <p class="text-muted small mb-2">
                      <i data-lucide="calendar" class="me-1"></i>
                      <?php echo date("F j, Y, g:i a", strtotime($notice['created_at'])); ?>
                    </p>
                    <p class="card-text mb-2"><?php echo nl2br(h(mb_strimwidth(($notice['description'] ?? ''), 0, 80, "..."))); ?></p>

                    <?php if (!empty($notice['image'])): ?>
                      <?php if (preg_match('/\.pdf$/i', $notice['image'])): ?>
                        <a href="<?php echo h($notice['image']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                          <i data-lucide="file-text" class="me-1"></i> View PDF Document
                        </a>
                      <?php else: ?>
                        <img src="<?php echo h($notice['image']); ?>" alt="Notice Image">
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($notice['video'])): ?>
                      <p>Video Link: <a href="<?php echo h($notice['video']); ?>" target="_blank"><?php echo h($notice['video']); ?></a></p>
                    <?php endif; ?>
                  </div>
                  <button class="btn btn-outline-secondary btn-sm mt-auto"
                    onclick='viewNotice(<?php echo json_encode($notice["title"]); ?>, <?php echo json_encode(nl2br($notice["description"] ?? '')); ?>, <?php echo json_encode($notice["image"]); ?>, <?php echo json_encode($notice["video"]); ?>)'>
                    <i data-lucide="eye" class="me-1"></i> View
                  </button>
                  <button class="btn btn-outline-danger btn-sm mt-2"
                    onclick='deleteNotice(<?php echo (int)$notice["notice_id"]; ?>)'>
                    <i data-lucide="trash-2" class="me-1"></i> Delete
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-panel text-center">
          <div class="mb-2">
            <i data-lucide="coffee" width="3em" height="3em" class="empty-icon"></i>
          </div>
          <h5 class="fw-semibold mb-1">No notices for today</h5>
          <p class="mb-0 text-muted">There are currently no posted notices. Please proceed with your tasks and have a productive day.</p>
        </div>
      <?php endif; ?>
    </div>
<!-- Modal -->
  <div class="modal fade" id="noticeModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-green text-white">
          <h5 class="modal-title fw-bold" id="modalTitle"></h5>
          <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4" id="modalBody"></div>
        <div class="modal-footer">
          <!-- Back button added -->
          <button type="button" class="btn btn-primary" onclick="window.location.href='admin_dashboard'">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Dashboard
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/admin_dashboard_1.js') ?>"></script>
  <script src="<?= asset('js/pages/admin_dashboard_2.js') ?>"></script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

