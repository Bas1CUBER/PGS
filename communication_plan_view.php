<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('cascading');
if (!isset($_GET['id'])) {
    header("Location: communication_plan.php");
    exit();
}
$stmt = $conn->prepare("SELECT o.*, u.email AS uploader_email FROM communication_plan_uploads o JOIN users u ON o.employee_id = u.id WHERE o.id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header("Location: communication_plan.php");
    exit();
}
$file = $res->fetch_assoc();
$filePath = __DIR__ . '/uploads/communication_plan/' . $file['filename'];
if (!file_exists($filePath)) {
    header("Location: communication_plan.php");
    exit();
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);
if (strpos($mime, 'pdf') !== false) {
    $pageTitle = 'View Document';
    $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/view.css') . '">';
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

    <div class="header">
        <div>
            <h5 class="mb-0"><i data-lucide="file-text" class="me-2"></i><?= h($file['original_name']) ?></h5>
            <small>Uploaded by: <?= h($file['uploader_email']) ?> | <?= date('Y-m-d H:i:s', strtotime($file['uploaded_at'])) ?></small>
        </div>
        <div>
            <a href="uploads/communication_plan/<?= h($file['filename']) ?>" class="btn btn-light btn-sm me-2" download>
                <i data-lucide="download" class="me-1"></i> Download
            </a>
            <a href="communication_plan.php" class="btn btn-outline-light btn-sm">
                <i data-lucide="arrow-left" class="me-1"></i> Back
            </a>
        </div>
    </div>
    <iframe src="uploads/communication_plan/<?= h($file['filename']) ?>" class="pdf-viewer"></iframe>
      </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

    exit();
}
if (strpos($mime, 'image') !== false) {
    $pageTitle = 'View Document';
    $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/view.css') . '">';
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

    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-2"><i data-lucide="file-image" class="me-2"></i><?= h($file['original_name']) ?></h4>
                    <p class="mb-0 text-muted">Uploaded by: <?= h($file['uploader_email']) ?> | <?= date('Y-m-d H:i:s', strtotime($file['uploaded_at'])) ?> | Size: <?= number_format($file['file_size'] / 1024, 2) ?> KB</p>
                </div>
                <div>
                    <a href="uploads/communication_plan/<?= h($file['filename']) ?>" class="btn btn-primary btn-sm me-2" download>
                        <i data-lucide="download" class="me-1"></i> Download
                    </a>
                    <a href="communication_plan.php" class="btn btn-outline-secondary btn-sm">
                        <i data-lucide="arrow-left" class="me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
        <div class="image-container">
            <img src="uploads/communication_plan/<?= h($file['filename']) ?>" alt="<?= h($file['original_name']) ?>">
        </div>
    </div>
      </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

    exit();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
header('Content-Length: ' . $file['file_size']);
readfile($filePath);
