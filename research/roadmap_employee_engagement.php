<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_page_access('scorecard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Engagement Rating (Research)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel='stylesheet' href='<?= BASE_URL ?>/assets/css/app.css'>
    <style>
        body { background-color: #f5f7fa; color: #2c3e50; }
        .card { border: none; border-radius: 1rem; background-color: #ffffff; }
        .card-header { background: #0b4aa2; color: #fff; border-radius: 1rem 1rem 0 0; font-weight: 700; letter-spacing: .04em; text-align: center; padding: 14px 16px; }
    </style>
    </head>
<body>
    <?php include PGS_TEMPLATES . '/navbar.php'; ?>
    <div class="container" style="padding-top:110px;">
        <div class="card shadow-sm">
            <div class="card-header">Employee Engagement Rating</div>
            <div class="card-body">
                <p>Content will be added later.</p>
            </div>
        </div>
    </div>
    <?php include PGS_TEMPLATES . '/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
