<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('cascading');
if (!isset($_GET['id'])) {
    header("Location: resources.php");
    exit();
}
$stmt = $conn->prepare("SELECT r.*, u.email AS uploader_email FROM resources_uploads r JOIN users u ON r.uploaded_by = u.id WHERE r.id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header("Location: resources.php");
    exit();
}
$file = $res->fetch_assoc();
$filePath = __DIR__ . '/uploads/resources/' . $file['filename'];
if (!file_exists($filePath)) {
    header("Location: resources.php");
    exit();
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);
$pageTitle = 'View Resource';
$pageStyles = <<<'STYLES'
body { margin: 0; padding: 0; font-family: 'Inter', 'Segoe UI', sans-serif; }
.header { background: #196a6b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
.viewer { height: calc(100vh - 70px); width: 100%; border: none; }
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

    <div class="header">
        <div>
            <h5 class="mb-0"><i data-lucide="file-text" class="me-2"></i><?= h($file['title']) ?></h5>
            <small>Uploaded by: <?= h($file['uploader_email']) ?> | <?= date('Y-m-d H:i:s', strtotime($file['uploaded_at'])) ?></small>
        </div>
        <div>
            <a href="uploads/resources/<?= h($file['filename']) ?>" class="btn btn-light btn-sm me-2" download>
                <i data-lucide="download" class="me-1"></i> Download
            </a>
            <a href="resources.php" class="btn btn-outline-light btn-sm">
                <i data-lucide="arrow-left" class="me-1"></i> Back
            </a>
        </div>
    </div>
    <iframe src="uploads/resources/<?= h($file['filename']) ?>#view=FitH" class="viewer"></iframe>
<?php
$pageScripts = '';
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

