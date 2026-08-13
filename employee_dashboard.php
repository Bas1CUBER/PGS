<?php
require_once __DIR__ . '/src/bootstrap.php';

// Restrict access to employees and focal
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}

// Fetch notices including image and video (link)
$sql = "SELECT notice_id, title, description, image, video, created_at 
        FROM notices 
        ORDER BY created_at DESC 
        LIMIT 12";
$stmt = $pdo->query($sql);

$notices = [];
if ($stmt && $stmt->rowCount() > 0) {
  $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<?php
$pageTitle = 'Employee Dashboard';
$pageStyles = 'html,
    body {
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

    .notice-card .card-body {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .notice-card h5 {
      font-weight: 700;
      color: #2c3e50;
    }

    .notice-card p,
    .notice-card a {
      color: #6c757d;
      margin-bottom: 10px;
      word-break: break-word;
    }

    .notice-card a {
      text-decoration: underline;
    }

    /* Modal image fix */
    #noticeModal img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 10px 0;
      border-radius: 6px;
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
        <h1>Welcome, Employee!</h1>
        <p>Stay updated with company notices, images, and video links.</p>
      </div>
    </section>

    <!-- Notices Section -->

    <!-- Notices Section -->
    <div class="container my-5">
      <h3 class="mb-4 fw-bold">Recent Notices</h3>

      <?php if (!empty($notices)): ?>
        <div class="row g-4">
          <?php foreach ($notices as $notice): ?>
            <div class="col-md-4">
              <div class="card notice-card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                  <div>
                    <h5 class="card-title"><?php echo h($notice['title']); ?></h5>
                    <small class="text-muted mb-2 d-block">
                      <i data-lucide="calendar" class="me-1"></i>
                      <?php echo date("F j, Y, g:i a", strtotime($notice['created_at'])); ?>
                    </small>
                    <p class="card-text"><?php echo nl2br(h(mb_strimwidth($notice['description'], 0, 80, "..."))); ?></p>

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
                      <p>Video Link: <a href="<?php echo h($notice['video']); ?>" target="_blank">
                        <?php echo h($notice['video']); ?></a></p>
                    <?php endif; ?>
                  </div>

                  <button class="btn btn-outline-secondary btn-sm mt-auto"
                    onclick='viewNotice(
                      <?php echo json_encode($notice["title"]); ?>, 
                      <?php echo json_encode(nl2br($notice["description"] ?? '')); ?>,
                      <?php echo json_encode($notice["image"]); ?>,
                      <?php echo json_encode($notice["video"]); ?>
                    )'>
                    <i data-lucide="eye" class="me-1"></i> View
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
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<script src="<?= asset('js/pages/employee_dashboard_1.js') ?>"></script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

