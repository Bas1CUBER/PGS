<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('performance_assessment');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'error'=>'Invalid or expired form token.']);
    exit();
}

// Admin: handle Strategy Refresh template upload (PDF)
if (($_SESSION['role'] ?? null) === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_refresh_template') {
        $imgDir = __DIR__ . '/img';
        if (!is_dir($imgDir)) {
            @mkdir($imgDir, 0755, true);
        }
        $msg = '';
        if (isset($_FILES['refresh_template']) && is_uploaded_file($_FILES['refresh_template']['tmp_name'])) {
            $file = $_FILES['refresh_template'];
            $allowed = ['application/pdf'];
            $maxSize = 20 * 1024 * 1024;
            if (!in_array($file['type'], $allowed, true)) {
                $msg = 'error:Only PDF files are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'error:File exceeds 20MB.';
            } else {
                $dest = $imgDir . '/strategy_refresh_template.pdf';
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $msg = 'success:Template updated.';
                } else {
                    $msg = 'error:Failed to save uploaded file.';
                }
            }
        } else {
            $msg = 'error:No file selected.';
        }
        $_SESSION['refresh_template_msg'] = $msg;
        header('Location: strategy_refresh.php');
        exit();
    }
    if ($action === 'delete_upload') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit(); }
        try {
            $stmt = $conn->prepare("SELECT filename FROM strategy_refresh_uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if (!$row) { echo json_encode(['success' => false, 'error' => 'Record not found']); exit(); }
            $filePath = __DIR__ . '/uploads/strategy_refresh/' . $row['filename'];
            if (is_file($filePath)) { @unlink($filePath); }
            $del = $conn->prepare("DELETE FROM strategy_refresh_uploads WHERE id = ?");
            $del->bind_param("i", $id);
            $ok = $del->execute();
            echo json_encode(['success' => $ok]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Delete failed']);
        }
        exit();
    }
}

// Resolve Strategy Refresh template URL for preview (if available)
$refreshTemplateUrl = '';
$refreshNew = __DIR__ . '/img/strategy_refresh_template.pdf';
if (is_file($refreshNew)) {
    $refreshTemplateUrl = 'img/strategy_refresh_template.pdf?v=' . filemtime($refreshNew);
}

// Check if strategy_refresh_uploads table exists
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'strategy_refresh_uploads'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}

// Create table if it doesn't exist
if (!$tableExists) {
    $createTable = "
    CREATE TABLE IF NOT EXISTS strategy_refresh_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        employee_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($createTable);
}

$uploads = [];
if ($tableExists) {
    $q = $conn->query("
        SELECT o.id, o.title, o.filename, o.original_name, o.file_size, o.mime_type, o.uploaded_at, u.email AS uploader_email
        FROM strategy_refresh_uploads o
        JOIN users u ON o.employee_id = u.id
        ORDER BY o.uploaded_at DESC
    ");
    while ($q && ($r = $q->fetch_assoc())) {
        $uploads[] = $r;
    }
}

$pageTitle = 'Strategy Refresh';

$pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/strategy_refresh.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="page-wrapper container my-5" pt-70>
    <div class="card shadow-sm mb-5">
        <div class="section-title">STRATEGY REFRESH - OSM</div>
        <div class="card-body">
            <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
                <?php if ($refreshTemplateUrl || isset($_SESSION['refresh_template_msg'])): ?>
                <div class="mb-5">
                    <h4 class="mb-3">STRATEGY REFRESH TEMPLATE (PDF)</h4>
                    <?php if (!empty($_SESSION['refresh_template_msg'])): 
                        $m = $_SESSION['refresh_template_msg']; unset($_SESSION['refresh_template_msg']);
                        $isErr = str_starts_with($m, 'error:'); $text = substr($m, strpos($m, ':')+1);
                    ?>
                        <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?> py-2"><?= h($text) ?></div>
                    <?php endif; ?>
                    <?php if ($refreshTemplateUrl): ?>
                    <div class="mb-3" style="border:1px solid #ddd;border-radius:8px;overflow:auto;background:#fff;">
                        <iframe src="<?= h($refreshTemplateUrl) ?>#view=FitH" width="100%" height="600px" style="border:none;"></iframe>
                    </div>
                    <a href="<?= h($refreshTemplateUrl) ?>" class="btn btn-primary-custom mb-3" download>
                        <i data-lucide="download" class="me-2"></i> Download Template
                    </a>
                    <?php else: ?>
                    <div class="alert alert-warning">No Strategy Refresh template uploaded yet.</div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_refresh_template">
                        <div class="row g-2 align-items-center">
                            <div class="col-sm-8">
                                <input type="file" name="refresh_template" class="form-control" accept=".pdf" required>
                            </div>
                            <div class="col-sm-4">
                                <button type="submit" class="btn btn-primary-custom w-100">
                                    <i data-lucide="upload" class="me-2"></i> Upload Template
                                </button>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">PDF only, up to 20MB. Replaces the current template.</small>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">OSM Documents</h4>
                    <button class="btn upload-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i data-lucide="upload" class="me-2"></i> Upload Document
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date Uploaded</th>
                                <th>Uploaded By</th>
                                <th>File Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($uploads)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        No documents uploaded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($uploads as $u): ?>
                                    <tr>
                                        <td><?= h($u['title']) ?></td>
                                        <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                        <td><?= h($u['uploader_email']) ?></td>
                                        <td><?= number_format($u['file_size'] / 1024, 2) ?> KB</td>
                                        <td>
                                            <a href="strategy_refresh_view?id=<?=  h($u['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                <i data-lucide="eye" class="me-1"></i> View
                                            </a>
                                            <a href="uploads/strategy_refresh/<?= h($u['filename']) ?>" 
                                               class="btn btn-sm btn-outline-success" download>
                                                <i data-lucide="download" class="me-1"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?=  h($u['id']) ?>">
                                                <i data-lucide="trash-2" class="me-1"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <h4 class="mb-4">OSM Documents</h4>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($uploads)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        No documents available yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($uploads as $u): ?>
                                    <tr>
                                        <td><?= h($u['title']) ?></td>
                                        <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                        <td>
                                            <a href="strategy_refresh_view?id=<?=  h($u['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                <i data-lucide="eye" class="me-1"></i> View
                                            </a>
                                            <a href="uploads/strategy_refresh/<?= h($u['filename']) ?>" 
                                               class="btn btn-sm btn-outline-success" download>
                                                <i data-lucide="download" class="me-1"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Modal (Admin Only) -->
<?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="uploadForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload STRATEGY REFRESH Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="documentTitle" class="form-label">Document Title</label>
                        <input type="text" class="form-control" id="documentTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="documentFile" class="form-label">Select Document</label>
                        <input type="file" class="form-control" id="documentFile" name="file" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" required>
                        <div class="form-text">
                            Supported formats: PDF, Word, Excel, PowerPoint, Images (Max size: 10MB)
                        </div>
                    </div>
                    <div id="uploadProgress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" class="w-0"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/strategy_refresh_1.js') ?>"></script>
<?php endif; ?>

  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
