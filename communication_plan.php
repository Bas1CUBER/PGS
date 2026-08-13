<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('cascading');
$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);
$conn->query("CREATE TABLE IF NOT EXISTS communication_plan_roadmap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    objective TEXT NOT NULL,
    target_audience TEXT,
    message TEXT,
    channel VARCHAR(255),
    timeframe VARCHAR(255),
    requirements TEXT,
    responsible_person VARCHAR(255),
    status ENUM('Not Accomplished/Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Accomplished/Started',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");
try {
    $colRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communication_plan_roadmap' AND COLUMN_NAME = 'status'");
    $colCount = 1;
    if ($colRes && ($colRow = $colRes->fetch_assoc())) {
        $colCount = (int)($colRow['c'] ?? 1);
    }
    if ($colCount === 0) {
        $conn->query("ALTER TABLE communication_plan_roadmap ADD COLUMN status ENUM('Not Accomplished/Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Accomplished/Started'");
    }
} catch (Throwable $e) {
}
$conn->query("CREATE TABLE IF NOT EXISTS communication_plan_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    status ENUM('Pending','Approved','Returned') DEFAULT 'Pending',
    status_updated_at TIMESTAMP NULL DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
)");
// Ensure status_updated_at column exists for older installs
try {
    $colRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communication_plan_uploads' AND COLUMN_NAME = 'status_updated_at'");
    $colRow = $colRes ? $colRes->fetch_assoc() : null;
    if ($colRow && (int)$colRow['c'] === 0) {
        $conn->query("ALTER TABLE communication_plan_uploads ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }
} catch (Throwable $e) {}
// PDF template for preview (iframe requires PDF)
$tplPdfRel = "img/display.pdf";
if (!is_file(__DIR__ . '/img/display.pdf')) {
    $tplPdfRel = "img/communication_plan_template.pdf";
    if (!is_file(__DIR__ . '/img/communication_plan_template.pdf')) {
        $tplPdfRel = "img/display.docx"; // legacy fallback
    }
}
$tplVersion = @filemtime(__DIR__ . '/' . $tplPdfRel) ?: time();
// DOCX template for download (Word format for editing)
$tplDocxRel = '';
$customDocx = __DIR__ . '/img/COMMUNICATION PLAN TEMPLATE.docx';
if (is_file($customDocx)) {
    $tplDocxRel = 'img/COMMUNICATION%20PLAN%20TEMPLATE.docx?v=' . filemtime($customDocx);
}
$roadmapEntries = [];
$rres = $conn->query("SELECT * FROM communication_plan_roadmap ORDER BY created_at ASC, id ASC");
while ($rres && ($row = $rres->fetch_assoc())) {
    $roadmapEntries[] = $row;
}
$uploads = [];
$ures = $conn->query("SELECT o.id, o.employee_id, o.filename, o.original_name, o.file_size, o.mime_type, o.uploaded_at, o.status, o.status_updated_at, u.email AS uploader_email FROM communication_plan_uploads o JOIN users u ON o.employee_id = u.id ORDER BY o.uploaded_at DESC");
while ($ures && ($row = $ures->fetch_assoc())) {
    $uploads[] = $row;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

if (in_array($role, ['admin','focal'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_entry') {
        $status = $_POST['status'] ?? 'Not Accomplished/Started';
        if (!in_array($status, ['Not Accomplished/Started','Ongoing','Completed'], true)) {
            $status = 'Not Accomplished/Started';
        }
        $stmt = $conn->prepare("INSERT INTO communication_plan_roadmap (objective,target_audience,message,channel,timeframe,requirements,responsible_person,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "ssssssssi",
            $_POST['objective'],
            $_POST['target_audience'],
            $_POST['message'],
            $_POST['channel'],
            $_POST['timeframe'],
            $_POST['requirements'],
            $_POST['responsible_person'],
            $status,
            $userId
        );
        $stmt->execute();
        header("Location: communication_plan.php");
        exit();
    }
    if ($action === 'edit_entry') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $status = $_POST['status'] ?? 'Not Accomplished/Started';
            if (!in_array($status, ['Not Accomplished/Started','Ongoing','Completed'], true)) {
                $status = 'Not Accomplished/Started';
            }
            $stmt = $conn->prepare("UPDATE communication_plan_roadmap SET objective=?,target_audience=?,message=?,channel=?,timeframe=?,requirements=?,responsible_person=?,status=? WHERE id=?");
            $stmt->bind_param(
                "ssssssssi",
                $_POST['objective'],
                $_POST['target_audience'],
                $_POST['message'],
                $_POST['channel'],
                $_POST['timeframe'],
                $_POST['requirements'],
                $_POST['responsible_person'],
                $status,
                $id
            );
            $stmt->execute();
        }
        header("Location: communication_plan.php");
        exit();
    }
    if ($action === 'delete_entry') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM communication_plan_roadmap WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        header("Location: communication_plan.php");
        exit();
    }
    if ($role === 'admin' && $action === 'delete_upload') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT filename FROM communication_plan_uploads WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($row && !empty($row['filename'])) {
                $filePath = __DIR__ . '/uploads/communication_plan/' . $row['filename'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $del = $conn->prepare("DELETE FROM communication_plan_uploads WHERE id=?");
            $del->bind_param("i", $id);
            $del->execute();
        }
        header("Location: communication_plan.php");
        exit();
    }
    if ($role === 'admin' && $action === 'upload_template_pdf') {
        $msg = '';
        if (isset($_FILES['template_pdf']) && is_uploaded_file($_FILES['template_pdf']['tmp_name'])) {
            $file = $_FILES['template_pdf'];
            if ($file['type'] !== 'application/pdf') {
                $msg = 'Only PDF files are allowed.';
            } elseif ($file['size'] > 20 * 1024 * 1024) {
                $msg = 'File exceeds 20MB size limit.';
            } else {
                $dest = __DIR__ . '/img/communication_plan_template.pdf';
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $msg = 'Template updated successfully.';
                    $adminInfo = getUserInfo($userId);
                    $adminIdent = formatUserIdentifier($adminInfo ?: []);
                    $notifMsg = $adminIdent . " updated the Communication Plan template.";
                    notifyAdmins('edit', 'Communication Plan Template Updated', $notifMsg, null, 'communication_plan_template');
                    notifyFocals('edit', 'Communication Plan Template Updated', $notifMsg, null, 'communication_plan_template');
                    $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
                    while ($empRes && ($row = $empRes->fetch_assoc())) {
                        createNotification((int)$row['id'], 'edit', 'Communication Plan Template Updated', $notifMsg, null, 'communication_plan_template');
                    }
                } else {
                    $msg = 'Failed to save uploaded file.';
                }
            }
        } else {
            $msg = 'No file selected.';
        }
        $_SESSION['comm_tpl_msg'] = $msg;
        header("Location: communication_plan.php");
        exit();
    }
}

$pageTitle = 'Communication Plan';

$pageStyles = page_css('css/pages/communication_plan.css');

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="page-wrapper container my-5" pt-70>
    <div class="card shadow-sm mb-5">
        <div class="section-title">COMMUNICATION PLAN</div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold mb-0 me-3">Roadmap</h5>
                <?php if (in_array($role, ['admin','focal'], true)): ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                        <i data-lucide="plus-circle" class="me-1"></i> Add Entry
                    </button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEntryModal">
                        <i data-lucide="trash-2" class="me-1"></i> Delete Entry
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle roadmap-table">
                    <thead>
                        <tr>
                            <th class="th-left">Objective</th>
                            <th class="th-center">Target Audience</th>
                            <th class="th-center">Message</th>
                            <th class="th-center">Channel</th>
                            <th class="th-center">Timeframe</th>
                            <th class="th-center">Requirements</th>
                            <th class="th-center">Responsible Person</th>
                            <th class="th-center">Status</th>
                            <?php if (in_array($role, ['admin','focal'], true)): ?>
                            <th class="th-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roadmapEntries)): ?>
                            <tr>
                                <td colspan="<?= in_array($role, ['admin','focal'], true) ? 9 : 8 ?>" class="text-center text-muted">No entries yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roadmapEntries as $e): ?>
                            <?php $roadmapStatus = $e['status'] ?? 'Not Accomplished/Started'; ?>
                            <tr>
                                <td class="align-left text-cell"><?= h($e['objective']) ?></td>
                                <td class="align-center compact-cell"><?= h($e['target_audience']) ?></td>
                                <td class="align-left text-cell"><?= h($e['message']) ?></td>
                                <td class="align-left text-cell"><?= h($e['channel']) ?></td>
                                <td class="align-center compact-cell"><?= h($e['timeframe']) ?></td>
                                <td class="align-center compact-cell"><?= h($e['requirements']) ?></td>
                                <td class="align-left text-cell"><?= h($e['responsible_person']) ?></td>
                                <td class="align-center compact-cell">
                                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                                        <select class="form-select form-select-sm roadmap-status-select" data-id="<?= (int)$e['id'] ?>">
                                            <option value="Not Accomplished/Started" <?= $roadmapStatus === 'Not Accomplished/Started' ? 'selected' : '' ?>>Not Accomplished/Started</option>
                                            <option value="Ongoing" <?= $roadmapStatus === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                            <option value="Completed" <?= $roadmapStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    <?php else: ?>
                                        <?php $statusClass = $roadmapStatus === 'Completed' ? 'status-completed' : ($roadmapStatus === 'Ongoing' ? 'status-ongoing' : 'status-pending'); ?>
                                        <span class="status-pill <?=  h($statusClass) ?>"><?= h($roadmapStatus) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($role, ['admin','focal'], true)): ?>
                                <td class="roadmap-actions align-center">
                                    <button class="btn btn-sm btn-warning me-2 edit-btn"
                                        data-id="<?=  h($e['id']) ?>"
                                        data-objective="<?= h($e['objective']) ?>"
                                        data-target_audience="<?= h($e['target_audience']) ?>"
                                        data-message="<?= h($e['message']) ?>"
                                        data-channel="<?= h($e['channel']) ?>"
                                        data-timeframe="<?= h($e['timeframe']) ?>"
                                        data-requirements="<?= h($e['requirements']) ?>"
                                        data-responsible_person="<?= h($e['responsible_person']) ?>"
                                        data-status="<?= h($roadmapStatus) ?>">
                                        <i data-lucide="pencil"></i> Edit
                                    </button>
                                    <form method="POST" action="communication_plan" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_entry">
                                        <input type="hidden" name="id" value="<?=  h($e['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i data-lucide="trash-2"></i> Delete
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($role === 'admin'): ?>
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="mb-3">COMMUNICATION PLAN TEMPLATE</h4>
                <?php if (!empty($_SESSION['comm_tpl_msg'])): ?>
                    <?php $tmp = $_SESSION['comm_tpl_msg']; unset($_SESSION['comm_tpl_msg']); ?>
                    <div class="alert <?= stripos($tmp,'success')!==false ? 'alert-success' : 'alert-info' ?>"><?= h($tmp) ?></div>
                <?php endif; ?>
                <div class="pdf-preview mb-3">
                    <iframe src="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>#view=FitH" width="100%" height="600px" class="border-0">
                        <p><a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" download>Download the file</a></p>
                    </iframe>
                </div>
                <div class="d-flex justify-content-center gap-2">
                    <?php if ($tplDocxRel): ?>
                    <a href="<?= h($tplDocxRel) ?>" class="btn btn-primary-custom" download>
                        <i data-lucide="download" class="me-2"></i> Download Template (.docx)
                    </a>
                    <?php else: ?>
                    <a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" class="btn btn-primary-custom" download>
                        <i data-lucide="download" class="me-2"></i> Download Template
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#tplUploadCollapse"><i data-lucide="upload" class="me-2"></i> Upload/Replace</button>
                </div>
                <div id="tplUploadCollapse" class="collapse mt-3">
                    <form method="POST" enctype="multipart/form-data" class="row g-2 justify-content-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_template_pdf">
                        <div class="col-12 col-md-6">
                            <input type="file" name="template_pdf" accept=".pdf" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i data-lucide="save" class="me-1"></i> Save</button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">PDF only, up to 20MB. Replaces the current template.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($role === 'employee'): ?>
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="mb-3">COMMUNICATION PLAN TEMPLATE</h4>
                <div class="pdf-preview mb-3">
                    <iframe src="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>#view=FitH" width="100%" height="600px" class="border-0">
                        <p><a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" download>Download the file</a></p>
                    </iframe>
                </div>
                <?php if ($tplDocxRel): ?>
                <a href="<?= h($tplDocxRel) ?>" class="btn btn-primary-custom" download>
                    <i data-lucide="download" class="me-2"></i> Download Template (.docx)
                </a>
                <?php else: ?>
                <a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" class="btn btn-primary-custom" download>
                    <i data-lucide="download" class="me-2"></i> Download Template
                </a>
                <?php endif; ?>
            </div>
            <div class="mt-4">
                <h5 class="mb-3">Submit Completed Communication Plan</h5>
                <div class="upload-area" id="uploadArea">
                    <i data-lucide="cloud-upload" width="3em" height="3em" class="text-primary mb-3"></i>
                    <h5>Drop your completed file here or click to browse</h5>
                    <p class="text-muted">Supported formats: PDF, JPG, PNG (Max size: 10MB)</p>
                    <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                </div>
                <div id="uploadProgress" class="mt-3" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" class="w-0"></div>
                    </div>
                </div>
                <p class="text-muted mt-3" fs-09>
                    Please follow the file name format before uploading: <strong>Date-Section-Head/Focal</strong>. Example: <strong>120326-HIMS-LJTV</strong>.
                </p>
            </div>
            <?php if (!empty($uploads)): ?>
            <div class="mt-5">
                <h5 class="mb-3">Your Uploaded Documents</h5>
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
                            $employeeUploads = array_filter($uploads, function($u) use ($userId) {
                                return isset($u['employee_id']) && (int)$u['employee_id'] === $userId;
                            });
                            foreach ($employeeUploads as $u): ?>
                            <tr>
                                <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                <td><?= h($u['original_name']) ?></td>
                                <td><?= number_format($u['file_size'] / 1024, 2) ?> KB</td>
                                <td>
                                    <span class="badge <?= $u['status'] === 'Approved' ? 'bg-success' : ($u['status'] === 'Returned' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                        <?= h($u['status']) ?>
                                    </span>
                                </td>
                                <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                                <td>
                                    <a href="communication_plan_view?id=<?=  h($u['id']) ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                        <i data-lucide="eye" class="me-1"></i> View
                                    </a>
                                    <a href="uploads/communication_plan/<?= h($u['filename']) ?>" class="btn btn-sm btn-outline-success" download>
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
    </div>
    <?php else: ?>
    <?php if ($role === 'focal'): ?>
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="mb-3">COMMUNICATION PLAN TEMPLATE</h4>
                <div class="pdf-preview mb-3">
                    <iframe src="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>#view=FitH" width="100%" height="600px" class="border-0">
                        <p><a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" download>Download the file</a></p>
                    </iframe>
                </div>
                <?php if ($tplDocxRel): ?>
                <a href="<?= h($tplDocxRel) ?>" class="btn btn-primary-custom" download>
                    <i data-lucide="download" class="me-2"></i> Download Template (.docx)
                </a>
                <?php else: ?>
                <a href="<?= h($tplPdfRel) ?>?v=<?= (int)$tplVersion ?>" class="btn btn-primary-custom" download>
                    <i data-lucide="download" class="me-2"></i> Download Template
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card shadow-sm mb-5">
        <div class="card-body">
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
                            <td colspan="7" class="text-center text-muted">No documents uploaded yet.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($uploads as $u): ?>
                            <tr>
                                <td><?= h($u['uploader_email']) ?></td>
<td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                                <td><?= h($u['original_name']) ?></td>
                                <td><?= number_format($u['file_size'] / 1024, 2) ?> KB</td>
                                <td>
                                    <?php if ($role === 'admin'): ?>
                                        <select class="form-select form-select-sm status-select" data-id="<?= h($u['id']) ?>" style="min-width: 120px;">
                                            <option value="Pending" <?= $u['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Approved" <?= $u['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="Returned" <?= $u['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge <?= $u['status'] === 'Approved' ? 'bg-success' : ($u['status'] === 'Returned' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                            <?= h($u['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                                <td>
                                    <a href="communication_plan_view?id=<?=  h($u['id']) ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                        <i data-lucide="eye" class="me-1"></i> View
                                    </a>
                                    <a href="uploads/communication_plan/<?= h($u['filename']) ?>" class="btn btn-sm btn-outline-success" download>
                                        <i data-lucide="download" class="me-1"></i> Download
                                    </a>
                                    <?php if ($role === 'admin'): ?>
                                        <form method="POST" action="communication_plan" class="d-inline upload-delete-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_upload">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
<i data-lucide="trash-2" class="me-1"></i> Delete
                                            </button>
                                        </form>
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
    <?php endif; ?>
</div>

<?php if (in_array($role, ['admin','focal'], true)): ?>
<div class="modal fade" id="addEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="communication_plan">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Roadmap Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_entry">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Objective</label>
                            <textarea class="form-control" name="objective" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Audience</label>
                            <textarea class="form-control" name="target_audience" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Channel</label>
                            <input type="text" class="form-control" name="channel">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeframe</label>
                            <input type="text" class="form-control" name="timeframe">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea class="form-control" name="requirements" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsible Person</label>
                            <input type="text" class="form-control" name="responsible_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Not Accomplished/Started">Not Accomplished/Started</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary-custom"><i data-lucide="save" class="me-1"></i> Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="editEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="communication_plan">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Roadmap Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_entry">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Objective</label>
                            <textarea class="form-control" name="objective" id="edit-objective" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Audience</label>
                            <textarea class="form-control" name="target_audience" id="edit-target_audience" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" id="edit-message" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Channel</label>
                            <input type="text" class="form-control" name="channel" id="edit-channel">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeframe</label>
                            <input type="text" class="form-control" name="timeframe" id="edit-timeframe">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea class="form-control" name="requirements" id="edit-requirements" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsible Person</label>
                            <input type="text" class="form-control" name="responsible_person" id="edit-responsible_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit-status">
                                <option value="Not Accomplished/Started">Not Accomplished/Started</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary-custom"><i data-lucide="save" class="me-1"></i> Update</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="deleteEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="communication_plan" id="deleteEntryTopForm">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Roadmap Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_entry">
                    <div class="mb-2">
                        <label class="form-label">Select Entry</label>
                        <select class="form-select" name="id" required>
                            <option value="">Choose an entry to delete</option>
                            <?php foreach ($roadmapEntries as $entry): ?>
                                <?php
                                    $objPreview = trim((string)($entry['objective'] ?? ''));
                                    if (strlen($objPreview) > 90) {
                                        $objPreview = substr($objPreview, 0, 90) . '...';
                                    }
                                ?>
                                <option value="<?= (int)$entry['id'] ?>">
                                    #<?= (int)$entry['id'] ?> - <?= h($objPreview) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <small class="text-muted">This action permanently deletes the selected roadmap entry.</small>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="trash-2" class="me-1"></i> Delete Selected
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="<?= asset('js/pages/communication_plan_1.js') ?>"></script>
<?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/communication_plan_1.js') ?>"></script>
<?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/communication_plan_2.js') ?>"></script>
<?php if ($role === 'admin'): ?>
<script src="<?= asset('js/pages/communication_plan_4.js') ?>"></script>
<?php endif; ?>
<?php endif; ?>
<?php if ($role === 'employee'): ?>
<?php $pgsPage = ['csrf' => csrf_token()]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/communication_plan_3.js') ?>"></script>
<?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
