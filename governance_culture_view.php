<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('governance');
if (!isset($_GET['id'])) {
    header("Location: governance_culture.php");
    exit();
}
$stmt = $conn->prepare("SELECT g.*, u.email AS uploader_email FROM governance_culture_uploads g JOIN users u ON g.employee_id = u.id WHERE g.id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header("Location: governance_culture.php");
    exit();
}
$file = $res->fetch_assoc();
$filePath = __DIR__ . '/uploads/governance_culture/' . $file['filename'];
if (!file_exists($filePath)) {
    header("Location: governance_culture.php");
    exit();
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);
if (strpos($mime, 'pdf') !== false) {
    $pageTitle = 'View Document';
    $pageStyles = '<style>
        body { margin: 0; padding: 0; font-family: \'Inter\', \'Segoe UI\', sans-serif; }
        .header { background: #196a6b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .pdf-viewer { height: calc(100vh - 70px); width: 100%; border: none; }
    </style>';
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
            <a href="uploads/governance_culture/<?= h($file['filename']) ?>" class="btn btn-light btn-sm me-2" download>
                <i data-lucide="download" class="me-1"></i> Download
            </a>
            <a href="governance_culture.php" class="btn btn-outline-light btn-sm">
                <i data-lucide="arrow-left" class="me-1"></i> Back
            </a>
        </div>
    </div>
    <iframe src="uploads/governance_culture/<?= h($file['filename']) ?>" class="pdf-viewer"></iframe>
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
    $pageStyles = '<style>
        body { margin: 0; padding: 20px; font-family: \'Inter\', \'Segoe UI\', sans-serif; background-color: #f5f7fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .image-container { background: white; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .image-container img { max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    </style>';
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
                    <a href="uploads/governance_culture/<?= h($file['filename']) ?>" class="btn btn-primary btn-sm me-2" download>
                        <i data-lucide="download" class="me-1"></i> Download
                    </a>
                    <a href="governance_culture.php" class="btn btn-outline-secondary btn-sm">
                        <i data-lucide="arrow-left" class="me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
        <div class="image-container">
            <img src="uploads/governance_culture/<?= h($file['filename']) ?>" alt="<?= h($file['original_name']) ?>">
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
