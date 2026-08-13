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
    $res = $conn->query("SHOW TABLES LIKE 'cascading_activities'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}
if (!$tableExists) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS cascading_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
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
$uploadDir = __DIR__ . '/uploads/cascading_activities/';
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
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            $maxSize = 10 * 1024 * 1024;
            if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
                header("Location: cascading_activities.php");
                exit();
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique = 'cascading_' . $userId . '_' . time() . '.' . $ext;
            $path = $uploadDir . $unique;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $stmt = $conn->prepare("INSERT INTO cascading_activities (title, description, filename, original_name, file_size, mime_type, uploaded_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param(
                    "ssssisi",
                    $_POST['title'],
                    $_POST['description'],
                    $unique,
                    $file['name'],
                    $file['size'],
                    $file['type'],
                    $userId
                );
                $stmt->execute();
            }
        }
        header("Location: cascading_activities.php");
        exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $hasNewFile = isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name']);
            if ($hasNewFile) {
                $file = $_FILES['file'];
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                $maxSize = 10 * 1024 * 1024;
                if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
                    header("Location: cascading_activities.php");
                    exit();
                }
                $cur = $conn->prepare("SELECT filename FROM cascading_activities WHERE id=?");
                $cur->bind_param("i", $id);
                $cur->execute();
                $cres = $cur->get_result();
                $old = $cres && $cres->num_rows ? $cres->fetch_assoc() : null;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique = 'cascading_' . $userId . '_' . time() . '.' . $ext;
                $path = $uploadDir . $unique;
                if (move_uploaded_file($file['tmp_name'], $path)) {
                    if ($old && !empty($old['filename'])) {
                        $oldPath = $uploadDir . $old['filename'];
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $stmt = $conn->prepare("UPDATE cascading_activities SET title=?, description=?, filename=?, original_name=?, file_size=?, mime_type=? WHERE id=?");
                    $stmt->bind_param(
                        "ssssisi",
                        $title,
                        $description,
                        $unique,
                        $file['name'],
                        $file['size'],
                        $file['type'],
                        $id
                    );
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare("UPDATE cascading_activities SET title=?, description=? WHERE id=?");
                $stmt->bind_param("ssi", $title, $description, $id);
                $stmt->execute();
            }
        }
        header("Location: cascading_activities.php");
        exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur = $conn->prepare("SELECT filename FROM cascading_activities WHERE id=?");
            $cur->bind_param("i", $id);
            $cur->execute();
            $res = $cur->get_result();
            $row = $res && $res->num_rows ? $res->fetch_assoc() : null;
            if ($row && !empty($row['filename'])) {
                $p = $uploadDir . $row['filename'];
                if (is_file($p)) {
                    unlink($p);
                }
            }
            $del = $conn->prepare("DELETE FROM cascading_activities WHERE id=?");
            $del->bind_param("i", $id);
            $del->execute();
        }
        header("Location: cascading_activities.php");
        exit();
    }
}
$activities = [];
$q = $conn->query("SELECT id, title, description, filename, original_name, file_size, mime_type, uploaded_at FROM cascading_activities ORDER BY uploaded_at DESC");
while ($q && ($r = $q->fetch_assoc())) {
    $activities[] = $r;
}

$pageTitle = 'Cascading Activities';

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/cascading_activities.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<main>
<div class="container cascading-container">
    <div class="card shadow-sm mb-4 cascading-card">
        <div class="card-body">
            <h4 class="mb-4">Cascading activities</h4>
            <?php if ($role === 'admin'): ?>
            <div class="mb-4">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">File</label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i data-lucide="upload" class="me-2"></i>Upload</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Date Uploaded</th>
                            <th>File</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No activities available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activities as $a): ?>
                            <tr>
                                <td><?= h($a['title']) ?></td>
                                <td><?= h($a['description']) ?></td>
                                <td><?= h(date('Y-m-d H:i:s', strtotime($a['uploaded_at']))) ?></td>
                                <td><?= h($a['original_name']) ?></td>
                                <td>
                                    <?php if ($role === 'admin'): ?>
                                        <button 
                                            class="btn btn-sm btn-outline-primary me-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal"
                                            data-id="<?= h($a['id']) ?>"
                                            data-title="<?= h($a['title']) ?>"
                                            data-description="<?= h($a['description']) ?>"
                                        >
                                            <i data-lucide="pencil" class="me-1"></i> Edit
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= h($a['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i data-lucide="trash-2" class="me-1"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="cascading_activities_view?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                            <i data-lucide="eye" class="me-1"></i> View
                                        </a>
                                        <a href="uploads/cascading_activities/<?= h($a['filename']) ?>" class="btn btn-sm btn-outline-success" download>
                                            <i data-lucide="download" class="me-1"></i> Download
                                        </a>
                                    <?php endif; ?>
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
<?php if ($role === 'admin'): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Activity</h5>
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
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="edit-description" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Replace File (optional)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
<script src="<?= asset('js/pages/cascading_activities_1.js') ?>"></script>
<?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
