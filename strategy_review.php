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

// Admin: handle Strategy Review template upload (PDF)
if (($_SESSION['role'] ?? null) === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_review_template') {
        $imgDir = __DIR__ . '/img';
        if (!is_dir($imgDir)) {
            @mkdir($imgDir, 0755, true);
        }
        $msg = '';
        if (isset($_FILES['review_template']) && is_uploaded_file($_FILES['review_template']['tmp_name'])) {
            $file = $_FILES['review_template'];
            $allowed = ['application/pdf'];
            $maxSize = 20 * 1024 * 1024;
            if (!in_array($file['type'], $allowed, true)) {
                $msg = 'error:Only PDF files are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'error:File exceeds 20MB.';
            } else {
                $dest = $imgDir . '/strategy_review_template.pdf';
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $msg = 'success:Template updated.';
                    $userInfo = getUserInfo((int)($_SESSION['user_id'] ?? 0));
                    $userIdent = formatUserIdentifier($userInfo ?: []);
                    $notifTitle = 'Strategy Review Template Updated';
                    $notifMsg = 'Admin ' . $userIdent . ' updated the Strategy Review template.';
                    notifyAdmins('edit', $notifTitle, $notifMsg, null, 'strategy_review_template');
                    notifyFocals('edit', $notifTitle, $notifMsg, null, 'strategy_review_template');
                    $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
                    while ($empRes && ($row = $empRes->fetch_assoc())) {
                        createNotification((int)$row['id'], 'edit', $notifTitle, $notifMsg, null, 'strategy_review_template');
                    }
                } else {
                    $msg = 'error:Failed to save uploaded file.';
                }
            }
        } else {
            $msg = 'error:No file selected.';
        }
        $_SESSION['review_template_msg'] = $msg;
        header('Location: strategy_review.php');
        exit();
    }
    if ($action === 'delete_upload') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit(); }
        try {
            $stmt = $conn->prepare("SELECT filename FROM strategy_review_uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if (!$row) { echo json_encode(['success' => false, 'error' => 'Record not found']); exit(); }
            $filePath = __DIR__ . '/uploads/strategy_review/' . $row['filename'];
            if (is_file($filePath)) { @unlink($filePath); }
            $del = $conn->prepare("DELETE FROM strategy_review_uploads WHERE id = ?");
            $del->bind_param("i", $id);
            $ok = $del->execute();
            echo json_encode(['success' => $ok]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Delete failed']);
        }
        exit();
    }
}

// Resolve Strategy Review template URL for preview
$reviewTemplateUrl = '';
$legacyName = __DIR__ . '/img/TRC-LU STRATEGY REVIEW TEMPLATE AND PROCESS FLOW.pdf';
$newName = __DIR__ . '/img/strategy_review_template.pdf';
if (is_file($newName)) {
    $reviewTemplateUrl = 'img/strategy_review_template.pdf?v=' . filemtime($newName);
} elseif (is_file($legacyName)) {
    $reviewTemplateUrl = 'img/TRC-LU%20STRATEGY%20REVIEW%20TEMPLATE%20AND%20PROCESS%20FLOW.pdf?v=' . filemtime($legacyName);
}
// DOCX template for download (Word format for editing)
$reviewTemplateDocxUrl = '';
$newDocx = __DIR__ . '/img/strategy_review_template.docx';
if (is_file($newDocx)) {
    $reviewTemplateDocxUrl = 'img/strategy_review_template.docx?v=' . filemtime($newDocx);
}

// Check if strategy_review_uploads table exists
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'strategy_review_uploads'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}

// Create table if it doesn't exist
if (!$tableExists) {
    $createTable = "
    CREATE TABLE IF NOT EXISTS strategy_review_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        status ENUM('Pending', 'Approved', 'Returned') DEFAULT 'Pending',
        status_updated_at TIMESTAMP NULL DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($createTable);
} else {
    $columnCheck = $conn->query("SHOW COLUMNS FROM strategy_review_uploads LIKE 'status'");
    if ($columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE strategy_review_uploads ADD COLUMN status ENUM('Pending', 'Approved', 'Returned') DEFAULT 'Pending'");
    }
    $colCheck2 = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'strategy_review_uploads' AND COLUMN_NAME = 'status_updated_at'");
    $colRow2 = $colCheck2 ? $colCheck2->fetch_assoc() : null;
    if ($colRow2 && (int)$colRow2['c'] === 0) {
        $conn->query("ALTER TABLE strategy_review_uploads ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }
}

$uploads = [];
if ($tableExists) {
    $q = $conn->query("
        SELECT o.id, o.employee_id, o.filename, o.original_name, o.file_size, o.mime_type, o.uploaded_at, o.status, o.status_updated_at, u.email AS uploader_email
        FROM strategy_review_uploads o
        JOIN users u ON o.employee_id = u.id
        ORDER BY o.uploaded_at DESC
    ");
    while ($q && ($r = $q->fetch_assoc())) {
        $uploads[] = $r;
    }
}

$pageTitle = 'Strategy Review';

$pageStyles = page_css('css/pages/strategy_review.css');

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
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)): ?>
                <div class="text-center mb-5">
                    <h4 class="mb-4">TRC-LU STRATEGY REVIEW TEMPLATE</h4>
                    
                    <?php if ($reviewTemplateUrl): ?>
                        <div class="mb-4">
                            <div class="pdf-preview">
                                <iframe src="<?= h($reviewTemplateUrl) ?>#view=FitH" 
                                        width="100%" height="600px" class="border-0">
                                    <p>Your browser does not support PDF viewing. 
                                       <a href="<?= h($reviewTemplateUrl) ?>" download>
                                           Download the PDF
                                       </a>
                                    </p>
                                </iframe>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No Strategy Review template available.</div>
                    <?php endif; ?>
                    
                    <?php if ($reviewTemplateDocxUrl): ?>
                    <a href="<?= h($reviewTemplateDocxUrl) ?>"
                       class="btn download-btn" download>
                        <i data-lucide="download" class="me-2"></i> Download Template (.docx)
                    </a>
                    <?php elseif ($reviewTemplateUrl): ?>
                    <a href="<?= h($reviewTemplateUrl) ?>"
                       class="btn download-btn" download>
                        <i data-lucide="download" class="me-2"></i> Download Template
                    </a>
                    <?php endif; ?>
                    
                    <div class="mt-5">
                        <h5 class="mb-3">Submit Completed Form</h5>
                        <div class="upload-area" id="uploadArea">
                            <i data-lucide="cloud-upload" width="3em" height="3em" class="text-primary mb-3"></i>
                            <h5>Drop your completed form here or click to browse</h5>
                            <p class="text-muted">Supported formats: PDF, JPG, PNG (Max size: 10MB)</p>
                            <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                        </div>
                        <div id="uploadProgress" class="mt-3" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" class="w-0"></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($uploads)): ?>
                        <div class="mt-5">
                            <h5 class="mb-3">Your Uploaded Documents</h5>
                            <p class="text-muted mb-3" fs-09>
                                Please follow the file name format before uploading: <strong>Date-Section-Head/Focal</strong>. Example: <strong>120326-HIMS-LJTV</strong>.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Document Name</th>
                                            <th>File Size</th>
                                            <th>Status</th>
                                            <th>Date Approved/Returned</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $employeeUploads = array_filter($uploads, function($u) {
                                            return isset($u['employee_id']) && $u['employee_id'] == $_SESSION['user_id'];
                                        });
                                        foreach ($employeeUploads as $u): ?>
                                            <tr>
                                                <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                                <td><?= h($u['original_name']) ?></td>
                                                <td><?= number_format($u['file_size'] / 1024, 2) ?> KB</td>
                                                <td>
                                                    <span class="badge
                                                        <?php
                                                        switch($u['status']) {
                                                            case 'Approved':
                                                                echo 'bg-success';
                                                                break;
                                                            case 'Returned':
                                                                echo 'bg-danger';
                                                                break;
                                                            default:
                                                                echo 'bg-warning text-dark';
                                                        }
                                                        ?>">
                                                        <?= h($u['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                                                <td>
                                                    <a href="strategy_review_view?id=<?=  h($u['id']) ?>" 
                                                       class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                        <i data-lucide="eye" class="me-1"></i> View
                                                    </a>
                                                    <a href="uploads/strategy_review/<?= h($u['filename']) ?>" 
                                                       class="btn btn-sm btn-outline-success" download>
                                                        <i data-lucide="download" class="me-1"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mb-5">
                    <h4 class="mb-3">TRC-LU STRATEGY REVIEW TEMPLATE</h4>
                    <?php if (!empty($_SESSION['review_template_msg'])): 
                        $m = $_SESSION['review_template_msg']; unset($_SESSION['review_template_msg']);
                        $isErr = str_starts_with($m, 'error:'); $text = substr($m, strpos($m, ':')+1);
                    ?>
                        <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?> py-2"><?= h($text) ?></div>
                    <?php endif; ?>
                    <?php if ($reviewTemplateUrl): ?>
                    <div class="pdf-preview mb-3">
                        <iframe src="<?= h($reviewTemplateUrl) ?>#view=FitH"
                                width="100%" height="600px" style="border:none;"></iframe>
                    </div>
                    <?php if ($reviewTemplateDocxUrl): ?>
                    <a href="<?= h($reviewTemplateDocxUrl) ?>" class="btn download-btn" download>
                        <i data-lucide="download" class="me-2"></i> Download Template (.docx)
                    </a>
                    <?php else: ?>
                    <a href="<?= h($reviewTemplateUrl) ?>" class="btn download-btn" download>
                        <i data-lucide="download" class="me-2"></i> Download Template
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="alert alert-warning">No template found. Please upload a PDF template.</div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_review_template">
                        <div class="row g-2 align-items-center">
                            <div class="col-sm-8">
                                <input type="file" name="review_template" class="form-control" accept=".pdf" required>
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
                <h4 class="mb-4">Uploaded Documents</h4>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Uploaded By</th>
                                <th>Date & Time</th>
                                <th>Document Name</th>
                                <th>File Size</th>
                                <th>Status</th>
                                <th>Date Approved/Returned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($uploads)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        No documents uploaded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($uploads as $u): ?>
                                    <tr>
                                        <td><?= h($u['uploader_email']) ?></td>
                                        <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                        <td><?= h($u['original_name']) ?></td>
                                        <td><?= number_format($u['file_size'] / 1024, 2) ?> KB</td>
                                        <td>
                                            <select class="form-select form-select-sm status-select"
                                                    data-id="<?=  h($u['id']) ?>"
                                                    style="min-width: 120px;">
                                                <option value="Pending" <?= $u['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Approved" <?= $u['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="Returned" <?= $u['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                            </select>
                                        </td>
                                        <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                                        <td>
                                            <a href="strategy_review_view?id=<?=  h($u['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                <i data-lucide="eye" class="me-1"></i> View
                                            </a>
                                            <a href="uploads/strategy_review/<?= h($u['filename']) ?>" 
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
    <?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/strategy_review_1.js') ?>"></script>
<?php endif; ?>

<?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)): ?>
    <?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/strategy_review_2.js') ?>"></script>
<?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
