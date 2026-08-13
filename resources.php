<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('cascading');
$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'resources_uploads'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}
if (!$tableExists) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS resources_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
}
$uploadDir = __DIR__ . '/uploads/resources/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $file = $_FILES['file'];
            $allowedTypes = ['application/pdf'];
            $maxSize = 20 * 1024 * 1024;
            if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
                header("Location: resources.php");
                exit();
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique = 'resources_' . $userId . '_' . time() . '.' . $ext;
            $path = $uploadDir . $unique;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $title = $_POST['title'];
                $stmt = $conn->prepare("INSERT INTO resources_uploads (title, filename, original_name, file_size, mime_type, uploaded_by) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param(
                    "sssssi",
                    $title,
                    $unique,
                    $file['name'],
                    $file['size'],
                    $file['type'],
                    $userId
                );
                $stmt->execute();
                $resourceId = $conn->insert_id;
                
                // Create notification for all users about new OSM document
                $userInfo = getUserInfo($userId);
                $userIdent = formatUserIdentifier($userInfo);
                $notifMsg = "Admin " . $userIdent . " uploaded new OSM document: " . $title;
                notifyAdmins('upload', 'New OSM Document', $notifMsg, $resourceId, 'resources');
                notifyFocals('upload', 'New OSM Document', $notifMsg, $resourceId, 'resources');
                // Also notify all employees
                $empResult = $conn->query("SELECT id FROM users WHERE role = 'employee'");
                while ($empRow = $empResult->fetch_assoc()) {
                    createNotification((int)$empRow['id'], 'upload', 'New OSM Document', $notifMsg, $resourceId, 'resources');
                }
                
                // Auto-create Notice entry
                $noticeTitle = $title;
                $noticeDesc = "A new OSM document titled \"" . $title . "\" has been uploaded and is now available in the Resources section.";
                $noticeImagePath = 'uploads/resources/' . $unique; // PDF file path
                $noticeStmt = $conn->prepare("INSERT INTO notices (title, description, image, video, created_at) VALUES (?, ?, ?, '', NOW())");
                $noticeStmt->bind_param("sss", $noticeTitle, $noticeDesc, $noticeImagePath);
                $noticeStmt->execute();
            }
        }
        header("Location: resources.php");
        exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $title = $_POST['title'] ?? '';
            $hasNewFile = isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name']);
            if ($hasNewFile) {
                $file = $_FILES['file'];
                $allowedTypes = ['application/pdf'];
                $maxSize = 20 * 1024 * 1024;
                if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
                    header("Location: resources.php");
                    exit();
                }
                $cur = $conn->prepare("SELECT filename FROM resources_uploads WHERE id=?");
                $cur->bind_param("i", $id);
                $cur->execute();
                $cres = $cur->get_result();
                $old = $cres && $cres->num_rows ? $cres->fetch_assoc() : null;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique = 'resources_' . $userId . '_' . time() . '.' . $ext;
                $path = $uploadDir . $unique;
                if (move_uploaded_file($file['tmp_name'], $path)) {
                    if ($old && !empty($old['filename'])) {
                        $oldPath = $uploadDir . $old['filename'];
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $stmt = $conn->prepare("UPDATE resources_uploads SET title=?, filename=?, original_name=?, file_size=?, mime_type=? WHERE id=?");
                    $stmt->bind_param(
                        "sssssi",
                        $title,
                        $unique,
                        $file['name'],
                        $file['size'],
                        $file['type'],
                        $id
                    );
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare("UPDATE resources_uploads SET title=? WHERE id=?");
                $stmt->bind_param("si", $title, $id);
                $stmt->execute();
            }
        }
        header("Location: resources.php");
        exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get current filename to remove the physical file
            $stmt = $conn->prepare("SELECT filename FROM resources_uploads WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows) {
                $row = $res->fetch_assoc();
                if (!empty($row['filename'])) {
                    $path = $uploadDir . $row['filename'];
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
            // Delete database record
            $del = $conn->prepare("DELETE FROM resources_uploads WHERE id=?");
            $del->bind_param("i", $id);
            $del->execute();
        }
        header("Location: resources.php");
        exit();
    }
}
$uploads = [];
$q = $conn->query("SELECT id, title, filename, original_name, file_size, mime_type, uploaded_at FROM resources_uploads ORDER BY uploaded_at DESC");
while ($q && ($r = $q->fetch_assoc())) {
    $uploads[] = $r;
}
if (empty($uploads)) {
    $examples = [
        ['path' => __DIR__ . '/img/TRC-LU NEWSLETTER (2ND QTR).pdf', 'title' => 'TRC-LU NEWSLETTER (2ND QTR)'],
        ['path' => __DIR__ . '/img/TRC-LU NEWSLETTERS (1ST QTR 2025).pdf', 'title' => 'TRC-LU NEWSLETTER (1ST QTR 2025)'],
    ];
    foreach ($examples as $ex) {
        if (is_file($ex['path'])) {
            $orig = basename($ex['path']);
            $unique = 'resources_seed_' . time() . '_' . mt_rand(1000,9999) . '.pdf';
            $dest = $uploadDir . $unique;
            if (@copy($ex['path'], $dest)) {
                $size = filesize($dest);
                $mime = 'application/pdf';
                $stmt = $conn->prepare("INSERT INTO resources_uploads (title, filename, original_name, file_size, mime_type, uploaded_by) VALUES (?,?,?,?,?,?)");
                $uploaderId = $userId ?: 1;
                $stmt->bind_param("sssssi", $ex['title'], $unique, $orig, $size, $mime, $uploaderId);
                $stmt->execute();
            }
        }
    }
    $uploads = [];
    $q = $conn->query("SELECT id, title, filename, original_name, file_size, mime_type, uploaded_at FROM resources_uploads ORDER BY uploaded_at DESC");
    while ($q && ($r = $q->fetch_assoc())) {
        $uploads[] = $r;
    }
}
$pageTitle = 'Resources';
$pageStyles = <<<'STYLES'
html, body { height: 100%; }
body { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
.resources-container { padding-top: 110px; }
.grid-card .card-title { font-size: 1rem; }
.pdf-thumb { width: 100%; height: 220px; border: none; }
.grid-card { transition: transform .1s ease-in-out; }
.grid-card:hover { transform: translateY(-3px); }
STYLES;
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <main>
    <div class="container resources-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Resources</h4>
            <?php if ($role === 'admin'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i data-lucide="upload" class="me-2"></i>Upload PDF</button>
            <?php endif; ?>
        </div>
        <?php if (empty($uploads)): ?>
            <div class="alert alert-info">No resources available.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($uploads as $u): ?>
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <div class="card grid-card">
                        <div class="card-body p-2">
                            <div class="card-title text-truncate" title="<?= h($u['title']) ?>"><?= h($u['title']) ?></div>
                            <iframe src="uploads/resources/<?= h($u['filename']) ?>#view=FitH" class="pdf-thumb"></iframe>
                            <div class="d-grid mt-2">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewerModal" data-filename="<?= h($u['filename']) ?>" data-title="<?= h($u['title']) ?>">
                                    <i data-lucide="eye" class="me-1"></i> View
                                </button>
                                <a href="resources_view?id=<?= h($u['id']) ?>" class="btn btn-sm btn-outline-secondary mt-2" target="_blank">
                                    <i data-lucide="external-link" class="me-1"></i> Open
                                </a>
                                <?php if ($role === 'admin'): ?>
                                <button class="btn btn-sm btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= h($u['id']) ?>" data-title="<?= h($u['title']) ?>">
                                    <i data-lucide="pencil" class="me-1"></i> Edit
                                </button>
                                <form method="POST" class="mt-2" onsubmit="return confirm('Delete this resource? This action cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i data-lucide="trash-2" class="me-1"></i> Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    </main>
    <?php if ($role === 'admin'): ?>
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
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
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Resource</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" id="edit-title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Replace PDF (optional)</label>
                            <input type="file" name="file" class="form-control" accept=".pdf">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="modal fade" id="viewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewer-title">View PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="viewer-frame" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
<?php
$pageScripts = <<<'SCRIPTS'
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editModal = document.getElementById('editModal');
        editModal && editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit-id').value = button.getAttribute('data-id');
            document.getElementById('edit-title').value = button.getAttribute('data-title');
        });
        var viewerModal = document.getElementById('viewerModal');
        viewerModal && viewerModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var filename = button.getAttribute('data-filename');
            var title = button.getAttribute('data-title');
            document.getElementById('viewer-title').textContent = title;
            document.getElementById('viewer-frame').src = 'uploads/resources/' + filename + '#view=FitH';
        });
    });
    </script>
SCRIPTS;
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

