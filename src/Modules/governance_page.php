<?php

declare(strict_types=1);

/**
 * Shared governance upload page (culture / sharing), driven by $govType.
 * Entry points: governance_culture.php / governance_sharing.php
 */

if (!isset($govType)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
global $conn;
$govModules = require __DIR__ . '/governance_config.php';
if (!isset($govModules[$govType])) {
    die('Invalid governance module');
}
$gov = $govModules[$govType];

if (!isset($_SESSION['user_id']) || !in_array(session_get('role'), ['admin', 'employee', 'focal'], true)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
require_page_access('governance');
$role = session_get('role');
$userId = (int)(session_get('user_id') ?? 0);
$table = $gov['table'];
$pageUrl = BASE_URL . '/' . $gov['page'];
$viewPage = $gov['view_page'];
$uploadDir = dirname(__DIR__, 2) . '/' . $gov['upload_dir'];
$webUploadDir = $gov['upload_dir'];

// Table bootstrap (idempotent for older installs)
try {
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}
if (!$tableExists) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            employee_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            doc_type ENUM('PDF','Image') NOT NULL,
            status ENUM('In Progress','Approved','Returned') DEFAULT 'In Progress',
            status_updated_at TIMESTAMP NULL DEFAULT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
}
try {
    $colCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status_updated_at'");
    $colRow = $colCheck ? $colCheck->fetch_assoc() : null;
    if ($colRow && (int)$colRow['c'] === 0) {
        $conn->query("ALTER TABLE {$table} ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }
} catch (Throwable $e) {
}
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header('Location: ' . BASE_URL . '/login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_pdf' || $action === 'upload_image') {
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            header('Location: ' . $pageUrl);
            exit();
        }
        $file = $_FILES['file'];
        $isPdf = ($action === 'upload_pdf');
        $allowedTypes = $isPdf ? ['application/pdf'] : ['image/jpeg','image/jpg','image/png'];
        $maxSize = 20 * 1024 * 1024;
        if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
            header('Location: ' . $pageUrl);
            exit();
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique = $gov['unique_prefix'] . $userId . '_' . time() . '.' . $ext;
        $path = $uploadDir . $unique;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $docType = $isPdf ? 'PDF' : 'Image';
            $stmt = $conn->prepare("INSERT INTO {$table} (title, description, employee_id, filename, original_name, file_size, mime_type, doc_type) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                'ssississ',
                $_POST['title'],
                $_POST['description'],
                $userId,
                $unique,
                $file['name'],
                $file['size'],
                $file['type'],
                $docType
            );
            $stmt->execute();
            $uploadId = $conn->insert_id;

            $uploaderInfo = getUserInfo($userId);
            $uploaderId = formatUserIdentifier($uploaderInfo);
            $title = $_POST['title'];
            $notifMsg = $uploaderId . ' uploaded a file: "' . $title . '"';
            notifyAdmins('upload', 'New File Uploaded', $notifMsg, $uploadId, $gov['notify_type']);
            notifyFocals('upload', 'New File Uploaded', $notifMsg, $uploadId, $gov['notify_type']);
        }
        header('Location: ' . $pageUrl);
        exit();
    }
    if ($role === 'admin' && $action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'In Progress';
        if ($id > 0 && in_array($status, ['In Progress','Approved','Returned'], true)) {
            $stmt = $conn->prepare("SELECT g.employee_id, g.title, u.email AS uploader_email FROM {$table} g JOIN users u ON g.employee_id = u.id WHERE g.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $uploadInfo = $stmt->get_result()->fetch_assoc();

            $stmt = $conn->prepare("UPDATE {$table} SET status=?, status_updated_at=NOW() WHERE id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();

            if ($uploadInfo) {
                $uploaderId = strtoupper(explode('@', $uploadInfo['uploader_email'])[0]);
                $title = $uploadInfo['title'];

                if ($status === 'Approved') {
                    createNotification(
                        $uploadInfo['employee_id'],
                        'approved',
                        'File Approved',
                        $uploaderId . ', your file "' . $title . '" has been approved by the admin.',
                        $id,
                        $gov['notify_type']
                    );
                    notifyFocals('approved', 'File Approved', 'Admin has approved the file uploaded by ' . $uploaderId . ': "' . $title . '"', $id, $gov['notify_type']);
                } elseif ($status === 'Returned') {
                    createNotification(
                        $uploadInfo['employee_id'],
                        'returned',
                        'File Returned',
                        $uploaderId . ', your file "' . $title . '" has been returned by the admin.',
                        $id,
                        $gov['notify_type']
                    );
                    notifyFocals('returned', 'File Returned', 'Admin has returned the file uploaded by ' . $uploaderId . ': "' . $title . '"', $id, $gov['notify_type']);
                }
            }
        }
        header('Location: ' . $pageUrl);
        exit();
    }
}
$uploads = [];
$q = $conn->query("SELECT g.*, u.email AS uploader_email FROM {$table} g JOIN users u ON g.employee_id = u.id ORDER BY g.uploaded_at DESC");
while ($q && ($r = $q->fetch_assoc())) {
    $uploads[] = $r;
}
$counts = [
    'total' => 0,
    'pdf' => 0,
    'image' => 0,
    'approved' => 0,
    'in_progress' => 0,
    'returned' => 0
];
foreach ($uploads as $u) {
    $counts['total']++;
    if ($u['doc_type'] === 'PDF') $counts['pdf']++;
    if ($u['doc_type'] === 'Image') $counts['image']++;
    if ($u['status'] === 'Approved') $counts['approved']++;
    if ($u['status'] === 'In Progress') $counts['in_progress']++;
    if ($u['status'] === 'Returned') $counts['returned']++;
}

$pageTitle = $gov['title'];
$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/' . $gov['css']) . '">';
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<main>
<div class="container page-container">
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['filter' => 'all', 'icon' => 'list', 'label' => 'Total', 'key' => 'total'],
            ['filter' => 'pdf', 'icon' => 'file-text', 'label' => 'PDFs', 'key' => 'pdf'],
            ['filter' => 'image', 'icon' => 'image', 'label' => 'Images', 'key' => 'image'],
            ['filter' => 'approved', 'icon' => 'check-circle', 'label' => 'Approved', 'key' => 'approved'],
            ['filter' => 'in_progress', 'icon' => 'hourglass', 'label' => 'In Progress', 'key' => 'in_progress'],
            ['filter' => 'returned', 'icon' => 'undo-2', 'label' => 'Returned', 'key' => 'returned'],
        ] as $stat): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="<?= $stat['filter'] ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><?= ui_icon($stat['icon']) ?></div>
                            <div class="stat-title"><?= h($stat['label']) ?></div>
                        </div>
                        <div class="stat-value"><?= $counts[$stat['key']] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><?= ui_icon('search', 16, 'text-muted') ?></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search documents...">
                        <button class="btn btn-outline-secondary" type="button" id="clearSearch"><?= ui_icon('x') ?></button>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select id="searchFilter" class="form-select">
                        <option value="all">Search All Fields</option>
                        <option value="details">Details</option>
                        <option value="uploaded_by">Uploaded By</option>
                        <option value="type">Type</option>
                        <option value="file">File</option>
                        <option value="status">Status</option>
                    </select>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">Showing <span id="visibleCount">0</span> of <span id="totalCount">0</span> documents</small>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Uploads</h5>
                <div>
                    <?= ui_btn('Upload Image', ['icon' => 'image', 'variant' => 'outline-primary', 'size' => 'sm', 'extra' => 'me-2', 'data' => ['bs-toggle' => 'modal', 'bs-target' => '#uploadImageModal']]) ?>
                    <?= ui_btn('Upload PDF', ['icon' => 'file-text', 'variant' => 'outline-danger', 'size' => 'sm', 'data' => ['bs-toggle' => 'modal', 'bs-target' => '#uploadPdfModal']]) ?>
                </div>
            </div>
            <div class="table-responsive">
                <?php if (in_array(session_get('role'), ['employee','focal'], true)): ?>
                <p class="text-muted fs-09">
                    Please follow the file name format before uploading: <strong>Date-Section-Head/Focal</strong>. Example: <strong>120326-HIMS-LJTV</strong>.
                </p>
                <?php endif; ?>
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Details</th>
                            <th>Uploaded By</th>
                            <th>Date Uploaded</th>
                            <th>Type</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Date Approved/Returned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="uploads-body">
                        <?php if (empty($uploads)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No uploads yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($uploads as $u): ?>
                            <tr data-doc-type="<?= strtolower($u['doc_type']) ?>" data-status="<?= strtolower(str_replace(' ', '_', $u['status'])) ?>">
                                <td>
                                    <div class="fw-semibold"><?= h($u['title']) ?></div>
                                    <div class="text-muted small"><?= h($u['description']) ?></div>
                                </td>
                                <td><?= h($u['uploader_email']) ?></td>
                                <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                <td><?= h($u['doc_type']) ?></td>
                                <td><?= h($u['original_name']) ?></td>
                                <td>
                                    <?php if ($role === 'admin'): ?>
                                        <form method="POST" class="d-flex align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" style="min-width: 140px;">
                                                <option value="In Progress" <?= $u['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="Approved" <?= $u['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="Returned" <?= $u['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                            </select>
                                            <?= ui_btn('Save', ['size' => 'sm', 'variant' => 'outline-primary', 'extra' => 'ms-2', 'type' => 'submit']) ?>
                                        </form>
                                    <?php else: ?>
                                        <?= ui_badge($u['status']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <?= ui_btn('View', ['href' => BASE_URL . '/' . $viewPage . '?id=' . $u['id'], 'icon' => 'eye', 'variant' => 'outline-primary', 'size' => 'sm', 'extra' => 'me-2', 'target' => '_blank']) ?>
                                    <?= ui_btn('Download', ['href' => $webUploadDir . $u['filename'], 'icon' => 'download', 'variant' => 'outline-success', 'size' => 'sm', 'download' => true]) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>
<script src="<?= asset('js/pages/' . $gov['js']) ?>"></script>
<div class="modal fade" id="uploadImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_image">
                    <div class="mb-3">
                        <label class="form-label">Details</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image File</label>
                        <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="uploadPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_pdf">
                    <div class="mb-3">
                        <label class="form-label">Details</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PDF File</label>
                        <input type="file" name="file" class="form-control" accept=".pdf" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
