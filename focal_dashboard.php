<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'focal') {
  header("Location: " . BASE_URL . "/login");
  exit();
}
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
$pageTitle = 'Focal Dashboard';
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

    .hero {
      background: linear-gradient(90deg, #1e88e5 0%, #0d5bd1 50%, #0b4aa2 100%);
      color: white;
      padding: 40px 20px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      gap: -15px;
    }

    .hero-lamp {
      flex-shrink: 0;
      margin-left: 20px;
    }

    .hero-lamp img {
      height: 200px;
      width: auto;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    }

    .hero-content {
      flex: 1;
      text-align: center;
    }

    .hero h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .hero p {
      font-size: 1.1rem;
      margin: 0 auto;
      max-width: 600px;
    }

    .notice-card {
      background: linear-gradient(145deg,rgb(192, 192, 192), #f8f9fa);
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08), 
                  inset 0 0 12px rgba(240, 240, 245, 0.9);
      transition: all 0.35s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border: 1px solid rgba(220, 220, 230, 0.7);
    }

    .notice-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.14);
      background: linear-gradient(135deg, #0d5bd1 0%, #0b4aa2 50%, #062f66 100%);
      color: white !important;
      border-color: #0b4aa2;
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

    #noticeModal img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 10px 0;
      border-radius: 6px;
    }

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
      .hero h1 {
        font-size: 2rem;
      }
      .hero p {
        font-size: 1rem;
      }
    }';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'PGS — TRC DOH') ?></title>
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<section class="hero">
      <div class="hero-lamp">
        <img src="img/lamp.png" alt="PGS Lamp">
      </div>
      <div class="hero-content">
        <h1>Welcome, Focal!</h1>
        <p>Stay updated with company notices, images, and video links.</p>
      </div>
    </section>
    <!-- Recent Notices -->
    <div class="container my-5">
      <h3 class="mb-4 fw-bold">Recent Notices</h3>
      <?php if (!empty($notices)): ?>
        <div class="row g-4">
          <?php foreach ($notices as $notice): ?>
            <div class="col-md-4">
              <div class="card notice-card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                  <div>
                    <h5 class="card-title"><?= h($notice['title']) ?></h5>
                    <small class="text-muted mb-2 d-block">
                      <i data-lucide="calendar" class="me-1"></i>
                      <?= date("F j, Y, g:i a", strtotime($notice['created_at'])) ?>
                    </small>
                    <p class="card-text"><?= nl2br(h(mb_strimwidth($notice['description'], 0, 80, "..."))) ?></p>
                    <?php if (!empty($notice['image'])): ?>
                      <?php if (preg_match('/\.pdf$/i', $notice['image'])): ?>
                        <a href="<?= h($notice['image']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                          <i data-lucide="file-text" class="me-1"></i> View PDF Document
                        </a>
                      <?php else: ?>
                        <img src="<?= h($notice['image']) ?>" alt="Notice Image">
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($notice['video'])): ?>
                      <p>Video Link: <a href="<?= h($notice['video']) ?>" target="_blank"><?= h($notice['video']) ?></a></p>
                    <?php endif; ?>
                  </div>
                  <button class="btn btn-outline-secondary btn-sm mt-auto"
                    onclick='viewNotice(
                      <?= json_encode($notice["title"]) ?>, 
                      <?= json_encode(nl2br($notice["description"] ?? '')) ?>,
                      <?= json_encode($notice["image"]) ?>,
                      <?= json_encode($notice["video"]) ?>
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
<script>
    function viewNotice(title, description, image, video) {
      document.getElementById('modalTitle').innerText = title;
      let modalBody = document.getElementById('modalBody');
      let html = `<p class="text-secondary">${description}</p>`;
      if (image) {
        if (image.toLowerCase().endsWith('.pdf')) {
          html += `<a href="${image}" target="_blank" class="btn btn-primary mt-2"><i data-lucide="file-text" class="me-1"></i> View PDF Document</a>`;
        } else {
          html += `<img src="${image}" alt="Notice Image">`;
        }
      }
      if (video) {
        html += `<p>Video Link: <a href="${video}" target="_blank">${video}</a></p>`;
      }
      modalBody.innerHTML = html;
      let noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));
      noticeModal.show();
    }
  </script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

