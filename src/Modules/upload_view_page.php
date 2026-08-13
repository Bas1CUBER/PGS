<?php

declare(strict_types=1);

/**
 * Shared "view uploaded document" page (PDF inline / image inline / forced download).
 * Entry points: *_view.php one-liners set $viewType, then require this file.
 */

if (!isset($viewType)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
global $conn;
$viewModules = require __DIR__ . '/upload_view_config.php';
if (!isset($viewModules[$viewType])) {
    die('Invalid view module');
}
$view = $viewModules[$viewType];

if (!isset($_SESSION['user_id']) || !in_array(session_get('role'), ['admin', 'employee', 'focal'], true)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
require_page_access($view['access']);

$backUrl = BASE_URL . '/' . $view['back_page'];
if (!isset($_GET['id'])) {
    header('Location: ' . $backUrl);
    exit();
}

$alias = $view['alias'];
$stmt = $conn->prepare("SELECT {$alias}.*, u.email AS uploader_email FROM {$view['table']} {$alias} JOIN users u ON {$alias}.{$view['join_col']} = u.id WHERE {$alias}.id = ?");
$stmt->bind_param('i', $_GET['id']);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: ' . $backUrl);
    exit();
}
$file = $res->fetch_assoc();
$filePath = dirname(__DIR__, 2) . '/' . $view['upload_dir'] . $file['filename'];
if (!file_exists($filePath)) {
    header('Location: ' . $backUrl);
    exit();
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);

$webDir = $view['upload_dir'];

if (strpos($mime, 'pdf') !== false) {
    $pageTitle = 'View Document';
    $pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/view.css') . '">';
    ?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <div class="header">
        <div>
            <h5 class="mb-0"><?= ui_icon('file-text', 16, 'me-2') ?><?= h($file['original_name']) ?></h5>
            <small>Uploaded by: <?= h($file['uploader_email']) ?> | <?= date('Y-m-d H:i:s', strtotime($file['uploaded_at'])) ?></small>
        </div>
        <div>
            <?= ui_btn('Download', ['href' => $webDir . $file['filename'], 'icon' => 'download', 'variant' => 'light', 'size' => 'sm', 'extra' => 'me-2', 'download' => true]) ?>
            <?= ui_btn('Back', ['href' => $backUrl, 'icon' => 'arrow-left', 'variant' => 'outline-light', 'size' => 'sm']) ?>
        </div>
    </div>
    <iframe src="<?= $webDir . h($file['filename']) ?>" class="pdf-viewer"></iframe>
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
    $pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/view.css') . '">';
    ?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-2"><?= ui_icon('file-image', 16, 'me-2') ?><?= h($file['original_name']) ?></h4>
                    <p class="mb-0 text-muted">Uploaded by: <?= h($file['uploader_email']) ?> | <?= date('Y-m-d H:i:s', strtotime($file['uploaded_at'])) ?> | Size: <?= number_format($file['file_size'] / 1024, 2) ?> KB</p>
                </div>
                <div>
                    <?= ui_btn('Download', ['href' => $webDir . $file['filename'], 'icon' => 'download', 'variant' => 'primary', 'size' => 'sm', 'extra' => 'me-2', 'download' => true]) ?>
                    <?= ui_btn('Back', ['href' => $backUrl, 'icon' => 'arrow-left', 'variant' => 'outline-secondary', 'size' => 'sm']) ?>
                </div>
            </div>
        </div>
        <div class="image-container">
            <img src="<?= $webDir . h($file['filename']) ?>" alt="<?= h($file['original_name']) ?>">
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
