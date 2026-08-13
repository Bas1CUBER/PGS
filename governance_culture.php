<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('governance');
$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'governance_culture_uploads'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}
if (!$tableExists) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS governance_culture_uploads (
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
// Ensure status_updated_at column exists for older installs
try {
    $colCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'governance_culture_uploads' AND COLUMN_NAME = 'status_updated_at'");
    $colRow = $colCheck ? $colCheck->fetch_assoc() : null;
    if ($colRow && (int)$colRow['c'] === 0) {
        $conn->query("ALTER TABLE governance_culture_uploads ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }
} catch (Throwable $e) {}
$uploadDir = __DIR__ . '/uploads/governance_culture/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_pdf' || $action === 'upload_image') {
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            header("Location: governance_culture.php");
            exit();
        }
        $file = $_FILES['file'];
        $isPdf = ($action === 'upload_pdf');
        $allowedTypes = $isPdf ? ['application/pdf'] : ['image/jpeg','image/jpg','image/png'];
        $maxSize = 20 * 1024 * 1024;
        if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
            header("Location: governance_culture.php");
            exit();
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique = 'gov_culture_' . $userId . '_' . time() . '.' . $ext;
        $path = $uploadDir . $unique;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $docType = $isPdf ? 'PDF' : 'Image';
            $stmt = $conn->prepare("INSERT INTO governance_culture_uploads (title, description, employee_id, filename, original_name, file_size, mime_type, doc_type) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                "ssississ",
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
            $notifMsg = $uploaderId . " uploaded a file: \"" . $title . "\"";
            notifyAdmins('upload', 'New File Uploaded', $notifMsg, $uploadId, 'governance_culture');
            notifyFocals('upload', 'New File Uploaded', $notifMsg, $uploadId, 'governance_culture');
        }
        header("Location: governance_culture.php");
        exit();
    }
    if ($role === 'admin' && $action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'In Progress';
        if ($id > 0 && in_array($status, ['In Progress','Approved','Returned'], true)) {
            $stmt = $conn->prepare("SELECT g.employee_id, g.title, u.email AS uploader_email FROM governance_culture_uploads g JOIN users u ON g.employee_id = u.id WHERE g.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $uploadInfo = $stmt->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE governance_culture_uploads SET status=?, status_updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si", $status, $id);
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
                        'governance_culture'
                    );
                    notifyFocals('approved', 'File Approved', 'Admin has approved the file uploaded by ' . $uploaderId . ': "' . $title . '"', $id, 'governance_culture');
                } elseif ($status === 'Returned') {
                    createNotification(
                        $uploadInfo['employee_id'],
                        'returned',
                        'File Returned',
                        $uploaderId . ', your file "' . $title . '" has been returned by the admin.',
                        $id,
                        'governance_culture'
                    );
                    notifyFocals('returned', 'File Returned', 'Admin has returned the file uploaded by ' . $uploaderId . ': "' . $title . '"', $id, 'governance_culture');
                }
            }
        }
        header("Location: governance_culture.php");
        exit();
    }
}
$uploads = [];
$q = $conn->query("SELECT g.*, u.email AS uploader_email FROM governance_culture_uploads g JOIN users u ON g.employee_id = u.id ORDER BY g.uploaded_at DESC");
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

$pageTitle = 'Governance Culture';

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/governance_culture.css') . '">';

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
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="all">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="list"></i></div>
                            <div class="stat-title">Total</div>
                        </div>
                        <div class="stat-value"><?= $counts['total'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="pdf">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="file-text"></i></div>
                            <div class="stat-title">PDFs</div>
                        </div>
                        <div class="stat-value"><?= $counts['pdf'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="image">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="image"></i></div>
                            <div class="stat-title">Images</div>
                        </div>
                        <div class="stat-value"><?= $counts['image'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="approved">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="check-circle"></i></div>
                            <div class="stat-title">Approved</div>
                        </div>
                        <div class="stat-value"><?= $counts['approved'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="in_progress">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="hourglass"></i></div>
                            <div class="stat-title">In Progress</div>
                        </div>
                        <div class="stat-value"><?= $counts['in_progress'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" data-filter="returned">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3"><i data-lucide="undo-2"></i></div>
                            <div class="stat-title">Returned</div>
                        </div>
                        <div class="stat-value"><?= $counts['returned'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i data-lucide="search" class="text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search documents...">
                        <button class="btn btn-outline-secondary" type="button" id="clearSearch"><i data-lucide="x"></i></button>
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
                    <button class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#uploadImageModal"><i data-lucide="image" class="me-2"></i>Upload Image</button>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPdfModal"><i data-lucide="file-text" class="me-2"></i>Upload PDF</button>
                </div>
            </div>
            <div class="table-responsive">
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['employee','focal'], true)): ?>
                <p class="text-muted" fs-09>
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
                                    <div class="fw-semibold"><?= htmlspecialchars($u['title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($u['description']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($u['uploader_email']) ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                <td><?= htmlspecialchars($u['doc_type']) ?></td>
                                <td><?= htmlspecialchars($u['original_name']) ?></td>
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
                                            <button class="btn btn-sm btn-outline-primary ms-2">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($u['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($u['status_updated_at']) ? htmlspecialchars(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <a href="governance_culture_view?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank"><i data-lucide="eye" class="me-1"></i> View</a>
                                    <a href="uploads/governance_culture/<?= htmlspecialchars($u['filename']) ?>" class="btn btn-sm btn-outline-success" download><i data-lucide="download" class="me-1"></i> Download</a>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cards = document.querySelectorAll('.stat-card');
    var tbody = document.getElementById('uploads-body');
    var rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
    var searchInput = document.getElementById('searchInput');
    var searchFilter = document.getElementById('searchFilter');
    var clearSearch = document.getElementById('clearSearch');
    var visibleCountEl = document.getElementById('visibleCount');
    var totalCountEl = document.getElementById('totalCount');
    var currentCardFilter = 'all';
    var currentSearchTerm = '';
    var currentSearchField = 'all';

    var dataRows = rows.filter(function(r) { return !r.querySelector('td[colspan]'); });
    totalCountEl.textContent = dataRows.length;

    function getCellText(row, field) {
        var tds = row.querySelectorAll('td');
        if (!tds.length) return '';
        switch(field) {
            case 'details':
                return (tds[0]?.textContent || '').toLowerCase();
            case 'uploaded_by':
                return (tds[1]?.textContent || '').toLowerCase();
            case 'type':
                return (tds[3]?.textContent || '').toLowerCase();
            case 'file':
                return (tds[4]?.textContent || '').toLowerCase();
            case 'status':
                var statusTd = tds[5];
                if (statusTd) {
                    var sel = statusTd.querySelector('select');
                    if (sel) return sel.value.toLowerCase();
                    return (statusTd.textContent || '').toLowerCase();
                }
                return '';
            default:
                return row.textContent.toLowerCase();
        }
    }

    function applyFilters() {
        var visibleCount = 0;
        rows.forEach(function(row) {
            if (row.querySelector('td[colspan]')) {
                row.style.display = (dataRows.length === 0 || (currentCardFilter === 'all' && currentSearchTerm === '')) ? '' : 'none';
                return;
            }

            var dt = row.dataset.docType || '';
            var st = row.dataset.status || '';
            var showByCard = true;
            var showBySearch = true;

            if (currentCardFilter === 'pdf') showByCard = dt === 'pdf';
            else if (currentCardFilter === 'image') showByCard = dt === 'image';
            else if (currentCardFilter === 'approved') showByCard = st === 'approved';
            else if (currentCardFilter === 'in_progress') showByCard = st === 'in_progress';
            else if (currentCardFilter === 'returned') showByCard = st === 'returned';

            if (currentSearchTerm) {
                var searchText = currentSearchField === 'all' 
                    ? row.textContent.toLowerCase() 
                    : getCellText(row, currentSearchField);
                showBySearch = searchText.indexOf(currentSearchTerm) > -1;
            }

            var show = showByCard && showBySearch;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        visibleCountEl.textContent = visibleCount;
    }

    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            cards.forEach(function(c){ c.classList.remove('active'); });
            card.classList.add('active');
            currentCardFilter = card.dataset.filter || 'all';
            applyFilters();
        });
    });

    searchInput.addEventListener('input', function() {
        currentSearchTerm = this.value.toLowerCase().trim();
        applyFilters();
    });

    searchFilter.addEventListener('change', function() {
        currentSearchField = this.value;
        applyFilters();
    });

    clearSearch.addEventListener('click', function() {
        searchInput.value = '';
        currentSearchTerm = '';
        applyFilters();
    });

    document.querySelectorAll('select[name="status"]').forEach(function(sel){
        sel.addEventListener('change', function(){
            var tr = sel.closest('tr');
            if (tr) {
                tr.dataset.status = sel.value.toLowerCase().replace(' ', '_');
                applyFilters();
            }
        });
    });

    applyFilters();
});
</script>
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
<?php
