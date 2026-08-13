<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

$pageTitle = 'Strategy Review';

$pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/strategy_review_empty.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="page-wrapper container my-5" pt-70>
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

