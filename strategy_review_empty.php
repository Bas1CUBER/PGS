<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

$pageTitle = 'Strategy Review';

$pageStyles = <<<'STYLES'
<style>
    html, body {
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
    .card {
        border: none;
        border-radius: 1rem;
        background-color: #ffffff;
    }
    .card-body {
        padding: 2rem;
    }
    .section-title {
        background: #196a6b;
        color: #fff;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 14px 16px;
        border-radius: 1rem 1rem 0 0;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    .empty-state i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    .empty-state h5 {
        color: #6c757d;
        font-weight: 500;
    }
</style>
STYLES;

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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=3">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="page-wrapper container my-5" style="padding-top: 70px;">
    <div class="card shadow-sm mb-5">
        <div class="section-title">STRATEGY REVIEW</div>
        <div class="card-body">
            <div class="empty-state">
                <i data-lucide="folder-open"></i>
                <h5>This page has been moved</h5>
                <p>Strategy Review functionality has been moved to Strategy Refresh.</p>
                <p>Please use the "Strategy Refresh" option from the Performance Assessment menu.</p>
            </div>
        </div>
    </div>
</div>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

