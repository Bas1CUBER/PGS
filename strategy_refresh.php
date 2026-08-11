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

$pageStyles = <<<'STYLES'
<style>
    html, body {
        background-color: #f5f7fa;
        color: #2c3e50;
        height: 100%;
        margin: 0;
        padding-top: 20px;
    }
    .page-wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    main {
        flex: 1;
    }
    .card {
        border: none;
        border-radius: 1rem;
        background-color: #ffffff;
    }
    .card-body {
        padding: 2rem;
    }
    .section-title {
        background: #0b4aa2;
        color: #fff;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 14px 16px;
        border-radius: 1rem 1rem 0 0;
    }
    .btn-primary-custom {
        background-color: #0b4aa2;
        border-color: #0b4aa2;
        color: #fff;
    }
    .btn-primary-custom:hover {
        background-color: #083a7f;
        border-color: #083a7f;
        color: #fff;
    }
    .table th {
        background-color: #f0f2f5;
        color: #34495e;
        font-weight: 600;
        border-color: #e9ecef;
    }
    .upload-btn {
        background: linear-gradient(135deg, #0b4aa2, #083a7f);
        border: none;
        color: white;
        padding: 12px 30px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(11, 74, 162, 0.3);
        transition: all 0.3s ease;
    }
    .upload-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 74, 162, 0.4);
        background: linear-gradient(135deg, #083a7f, #0b4aa2);
    }
</style>
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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="page-wrapper container my-5" style="padding-top: 70px;">
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
                                            <a href="strategy_refresh_view.php?id=<?=  h($u['id']) ?>" 
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
                                            <a href="strategy_refresh_view.php?id=<?=  h($u['id']) ?>" 
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
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
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

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const title = formData.get('title');
            const file = formData.get('file');
            
            if (!title || !file) {
                Swal.fire('Error', 'Please fill in all fields', 'error');
                return;
            }
            
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                Swal.fire('Error', 'File size must be less than 10MB', 'error');
                return;
            }
            
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = uploadProgress.querySelector('.progress-bar');
            uploadProgress.style.display = 'block';
            
            fetch('strategy_refresh_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadProgress.style.display = 'none';
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'STRATEGY REFRESH Document uploaded successfully',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    this.reset();
                    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                    
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.error || 'Upload failed', 'error');
                }
            })
            .catch(error => {
                uploadProgress.style.display = 'none';
                Swal.fire('Error', 'Upload failed', 'error');
            });
            
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                progressBar.style.width = progress + '%';
                if (progress >= 90) clearInterval(interval);
            }, 200);
        });
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                if (!id) return;
                Swal.fire({
                    title: 'Delete Document',
                    text: 'Are you sure you want to delete this document?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('_token','<?= csrf_token() ?>');
                    fd.append('action', 'delete_upload');
                    fd.append('id', id);
                    fetch('strategy_refresh.php', { method: 'POST', body: fd })
                        .then(r => r.json()).then(d => {
                            if (d && d.success) {
                                Swal.fire({ title: 'Deleted', icon: 'success', timer: 1200, showConfirmButton: false });
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                Swal.fire('Error', d && d.error ? d.error : 'Delete failed', 'error');
                            }
                        }).catch(() => Swal.fire('Error', 'Delete failed', 'error'));
                });
            });
        });
    </script>
<?php endif; ?>

  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
