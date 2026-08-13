<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

require_page_access('performance_assessment');

$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

// Ensure uploads table exists with status column
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'operations_review_uploads'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}
if (!$tableExists) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS operations_review_uploads (
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
        )
    ");
}
// Ensure status_updated_at column exists for older installs
try {
    $colCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations_review_uploads' AND COLUMN_NAME = 'status_updated_at'");
    $colRow = $colCheck ? $colCheck->fetch_assoc() : null;
    if ($colRow && (int)$colRow['c'] === 0) {
        $conn->query("ALTER TABLE operations_review_uploads ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }
} catch (Throwable $e) {}

// Load uploads
$uploads = [];
try {
    $q = $conn->query("
        SELECT o.id, o.employee_id, o.filename, o.original_name, o.file_size, o.mime_type, o.uploaded_at, o.status, o.status_updated_at, u.email AS uploader_email
        FROM operations_review_uploads o
        JOIN users u ON o.employee_id = u.id
        ORDER BY o.uploaded_at DESC
    ");
    while ($q && ($r = $q->fetch_assoc())) { $uploads[] = $r; }
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'error'=>'Invalid or expired form token.']);
    exit();
}

// Handle template upload by admin
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_or_template') {
        $imgDir = __DIR__ . '/img';
        if (!is_dir($imgDir)) { @mkdir($imgDir, 0755, true); }
        $msg = '';
        if (isset($_FILES['or_template']) && is_uploaded_file($_FILES['or_template']['tmp_name'])) {
            $file = $_FILES['or_template'];
            $allowed = ['application/pdf'];
            $maxSize = 20 * 1024 * 1024;
            if (!in_array($file['type'], $allowed, true)) {
                $msg = 'error:Only PDF files are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'error:File exceeds 20MB.';
            } else {
                $dest = $imgDir . '/operations_review_template.pdf';
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $msg = 'success:Template updated.';
                    $userInfo = getUserInfo($userId);
                    $userIdent = formatUserIdentifier($userInfo ?: []);
                    $title = 'Operations Review Template Updated';
                    $message = 'Admin ' . $userIdent . ' updated the Operations Review template.';
                    notifyAdmins('edit', $title, $message, null, 'operations_review_template');
                    notifyFocals('edit', $title, $message, null, 'operations_review_template');
                    $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
                    while ($empRes && ($row = $empRes->fetch_assoc())) {
                        createNotification((int)$row['id'], 'edit', $title, $message, null, 'operations_review_template');
                    }
                } else {
                    $msg = 'error:Failed to save uploaded file.';
                }
            }
        } else {
            $msg = 'error:No file selected.';
        }
        $_SESSION['or_template_msg'] = $msg;
        header('Location: operations_review_new.php');
        exit();
    }
    // Update soft-copy link (admin)
    if ($action === 'update_softlink') {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
        $softLinkFile = $dataDir . '/operations_review_softlink.json';
        $link = trim($_POST['soft_link'] ?? '');
        if ($link !== '' && (stripos($link, 'http://') === 0 || stripos($link, 'https://') === 0)) {
            @file_put_contents($softLinkFile, json_encode(['soft_link' => $link], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['softlink_msg'] = 'success:Soft-copy link updated.';
        } else {
            $_SESSION['softlink_msg'] = 'error:Please enter a valid URL.';
        }
        header('Location: operations_review_new.php');
        exit();
    }
    if ($action === 'delete_upload') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit(); }
        try {
            $stmt = $conn->prepare("SELECT filename FROM operations_review_uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if (!$row) { echo json_encode(['success' => false, 'error' => 'Record not found']); exit(); }
            $filePath = __DIR__ . '/uploads/operations_review/' . $row['filename'];
            if (is_file($filePath)) { @unlink($filePath); }
            $del = $conn->prepare("DELETE FROM operations_review_uploads WHERE id = ?");
            $del->bind_param("i", $id);
            $ok = $del->execute();
            echo json_encode(['success' => $ok]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Delete failed']);
        }
        exit();
    }
}

// Resolve template URL for preview (prefer new, fallback to legacy)
$orTemplateUrl = '';
$newT = __DIR__ . '/img/operations_review_template.pdf';
$legacyT = __DIR__ . '/img/TRC-LU OPERATIONS REVIEW TEMPLATE AND PROCESS FLOW.pdf';
if (is_file($newT)) {
    $orTemplateUrl = 'img/operations_review_template.pdf?v=' . filemtime($newT);
} elseif (is_file($legacyT)) {
    $orTemplateUrl = 'img/TRC-LU%20OPERATIONS%20REVIEW%20TEMPLATE%20AND%20PROCESS%20FLOW.pdf?v=' . filemtime($legacyT);
}
// DOCX template for download (Word format for editing)
$orTemplateDocxUrl = '';
$newDocx = __DIR__ . '/img/operations_review_template.docx';
$legacyDocx = __DIR__ . '/img/TRC-LU OPERATIONS REVIEW TEMPLATE AND PROCESS FLOW.docx';
if (is_file($newDocx)) {
    $orTemplateDocxUrl = 'img/operations_review_template.docx?v=' . filemtime($newDocx);
} elseif (is_file($legacyDocx)) {
    $orTemplateDocxUrl = 'img/TRC-LU%20OPERATIONS%20REVIEW%20TEMPLATE%20AND%20PROCESS%20FLOW.docx?v=' . filemtime($legacyDocx);
}
// Load current soft-copy link
$softLink = 'https://docs.google.com/document/d/1sPsezkkZb-f_RPHFoq1NqnCx1E4b5Mv3/edit?rtpof=true&tab=t.0';
try {
    $dataDir = __DIR__ . '/data';
    $softLinkFile = $dataDir . '/operations_review_softlink.json';
    if (is_file($softLinkFile)) {
        $raw = @file_get_contents($softLinkFile);
        $data = json_decode($raw, true);
        if ($data && !empty($data['soft_link'])) {
            $softLink = (string)$data['soft_link'];
        }
    }
} catch (Throwable $e) {}

$pageTitle = 'Operations Review';

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/operations_review_new.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="container page-wrapper">
  <div class="card shadow-sm mb-5">
    <div class="section-title">PERFORMANCE ASSESSMENT: OPERATIONS REVIEW</div>
    <div class="card-body">
      <h4 class="mb-3">Operations Review Template</h4>
      <?php if (!empty($_SESSION['or_template_msg'])): 
            $m = $_SESSION['or_template_msg']; unset($_SESSION['or_template_msg']);
            $isErr = str_starts_with($m, 'error:'); $txt = substr($m, strpos($m, ':')+1);
      ?>
        <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?> py-2"><?= h($txt) ?></div>
      <?php endif; ?>
      <?php if ($orTemplateUrl): ?>
        <div class="pdf-preview mb-3">
          <iframe src="<?= h($orTemplateUrl) ?>#view=FitH" width="100%" height="600px" style="border:none;"></iframe>
        </div>
        <?php if ($orTemplateDocxUrl): ?>
        <a href="<?= h($orTemplateDocxUrl) ?>" class="btn btn-outline-primary mb-4" download>
          <i data-lucide="download" class="me-2"></i> Download Template (.docx)
        </a>
        <?php else: ?>
        <a href="<?= h($orTemplateUrl) ?>" class="btn btn-outline-primary mb-4" download>
          <i data-lucide="download" class="me-2"></i> Download Template
        </a>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-warning">No Operations Review template found. Please upload a PDF.</div>
      <?php endif; ?>
      <?php if ($role === 'admin'): ?>
        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_or_template">
          <div class="col-sm-8"><input type="file" name="or_template" class="form-control" accept=".pdf" required></div>
          <div class="col-sm-4"><button type="submit" class="btn btn-primary-custom w-100"><i data-lucide="upload" class="me-2"></i> Upload Template</button></div>
          <div class="col-12"><small class="text-muted">PDF only, up to 20MB. Replaces the current template.</small></div>
        </form>
      <?php endif; ?>
      
      <div class="card border-0 mb-4" style="box-shadow:0 6px 16px rgba(11,74,162,.12);">
        <div class="card-header" style="background:#0b4aa2; color:#fff; font-weight:700;">
          For soft-copy editing, here's the link
        </div>
        <div class="card-body">
          <?php if (!empty($_SESSION['softlink_msg'])):
            $m = $_SESSION['softlink_msg']; unset($_SESSION['softlink_msg']);
            $isErr = str_starts_with($m,'error:'); $txt = substr($m, strpos($m, ':')+1);
          ?>
            <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?> py-2"><?= h($txt) ?></div>
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-3">
              <thead>
                <tr>
                  <th>Soft-copy Link</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <?php if (!empty($softLink)): ?>
                      <a href="<?= h($softLink) ?>" target="_blank" rel="noopener">Open Document</a>
                    <?php else: ?>
                      <span class="text-muted">No link set.</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php if ($role === 'admin'): ?>
          <form method="POST" class="row g-2 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_softlink">
            <div class="col-sm-9">
              <input type="url" name="soft_link" class="form-control" placeholder="https://..." value="<?= h($softLink) ?>">
            </div>
            <div class="col-sm-3">
              <button type="submit" class="btn btn-primary w-100"><i data-lucide="save" class="me-1"></i> Save Link</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (in_array($role, ['employee','focal'], true)): ?>
        <hr class="my-4">
        <h4 class="mb-3">Submit Completed Form</h4>
        <div class="upload-area mb-3" id="uploadArea">
          <i data-lucide="cloud-upload" width="2em" height="2em" class="text-primary mb-2"></i>
          <div>Drop your completed form here or click to browse</div>
          <div class="text-muted small">PDF, JPG, PNG up to 10MB</div>
          <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
        </div>
        <div id="uploadProgress" class="mb-4" style="display:none;">
          <div class="progress"><div class="progress-bar" role="progressbar" style="width:0%"></div></div>
        </div>
        <?php
          $employeeUploads = array_filter($uploads, function($u) use ($userId) { return (int)$u['employee_id'] === $userId; });
        ?>
        <?php if (!empty($employeeUploads)): ?>
          <h5 class="mb-2">Your Uploaded Documents</h5>
          <p> Please follow the file name format before uploading:  
          </p>
          <p class="mb-2" style="font-size:0.95rem; font-weight:700; color:#000;">
            Date-Section-Head/Focal. Example: 120326-HIMS-LJTV.
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
                <?php foreach ($employeeUploads as $u): ?>
                <tr>
                  <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                  <td><?= h($u['original_name']) ?></td>
                  <td><?= number_format($u['file_size']/1024, 2) ?> KB</td>
                  <td>
                    <span class="badge
                      <?php
                        echo $u['status']==='Approved' ? 'bg-success' : ($u['status']==='Returned' ? 'bg-danger' : 'bg-warning text-dark');
                      ?>">
                      <?= h($u['status']) ?>
                    </span>
                  </td>
                  <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                  <td>
                    <a href="operations_review_view?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                      <i data-lucide="eye" class="me-1"></i> View
                    </a>
                    <a href="uploads/operations_review/<?= h($u['filename']) ?>" class="btn btn-sm btn-outline-success" download>
                      <i data-lucide="download" class="me-1"></i> Download
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <hr class="my-4">
        <h4 class="mb-3">Uploaded Documents</h4>
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
                <tr><td colspan="7" class="text-center text-muted">No documents uploaded yet.</td></tr>
              <?php else: foreach ($uploads as $u): ?>
                <tr>
                  <td><?= h($u['uploader_email']) ?></td>
                  <td><?= h(date('Y-m-d H:i:s', strtotime($u['uploaded_at']))) ?></td>
                  <td><?= h($u['original_name']) ?></td>
                  <td><?= number_format($u['file_size']/1024, 2) ?> KB</td>
                  <td>
                    <select class="form-select form-select-sm status-select" data-id="<?= (int)$u['id'] ?>" style="min-width:120px;">
                      <option value="Pending" <?= $u['status']==='Pending'?'selected':'' ?>>Pending</option>
                      <option value="Approved" <?= $u['status']==='Approved'?'selected':'' ?>>Approved</option>
                      <option value="Returned" <?= $u['status']==='Returned'?'selected':'' ?>>Returned</option>
                    </select>
                  </td>
                  <td><?= !empty($u['status_updated_at']) ? h(date('M d, Y g:i A', strtotime($u['status_updated_at']))) : '<span class="text-muted">â€”</span>' ?></td>
                  <td>
                    <a href="operations_review_view?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary me-2" target="_blank">
                      <i data-lucide="eye" class="me-1"></i> View
                    </a>
                    <a href="uploads/operations_review/<?= h($u['filename']) ?>" class="btn btn-sm btn-outline-success" download>
                      <i data-lucide="download" class="me-1"></i> Download
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$u['id'] ?>">
                      <i data-lucide="trash-2" class="me-1"></i> Delete
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (in_array($role, ['employee','focal'], true)): ?>
<script>
  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');
  const uploadProgress = document.getElementById('uploadProgress');
  uploadArea.addEventListener('click', ()=> fileInput.click());
  uploadArea.addEventListener('dragover', (e)=> { e.preventDefault(); uploadArea.classList.add('dragover'); });
  uploadArea.addEventListener('dragleave', ()=> uploadArea.classList.remove('dragover'));
  uploadArea.addEventListener('drop', (e)=> { e.preventDefault(); uploadArea.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
  fileInput.addEventListener('change', (e)=> handleFiles(e.target.files));
  function handleFiles(files){
    if (!files || !files.length) return;
    const f = files[0];
    const allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
    if (!allowed.includes(f.type)) { alert('Please upload PDF, JPG or PNG'); return; }
    if (f.size > 10*1024*1024) { alert('Max size 10MB'); return; }
    const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('file', f);
    uploadProgress.style.display = 'block';
    const bar = uploadProgress.querySelector('.progress-bar'); bar.style.width = '0%';
    fetch('operations_review_upload.php', { method:'POST', body: fd })
      .then(r=>r.json()).then(data=>{
        uploadProgress.style.display='none'; fileInput.value='';
        if (data && data.success) { location.reload(); }
        else { alert(data && data.error ? data.error : 'Upload failed'); }
      }).catch(()=>{ uploadProgress.style.display='none'; alert('Upload failed'); });
    let p=0; const iv=setInterval(()=>{ p+=10; bar.style.width=p+'%'; if (p>=90) clearInterval(iv); },200);
  }
</script>
<?php endif; ?>
<?php if ($role === 'admin'): ?>
<script>
  document.querySelectorAll('.status-select').forEach(sel=>{
    sel.setAttribute('data-original', sel.value);
    sel.addEventListener('change', function(){
      const id = this.dataset.id; const status = this.value;
      fetch('operations_review_update_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id:id, status: status, _token: '<?= csrf_token() ?>' })
      }).then(r=>r.json()).then(d=>{
        if (!(d && d.success)) { alert(d && d.error? d.error : 'Update failed'); this.value=this.getAttribute('data-original')||'Pending'; }
        else { this.setAttribute('data-original', status); }
      }).catch(()=>{ alert('Update failed'); this.value=this.getAttribute('data-original')||'Pending'; });
    });
  });
  document.querySelectorAll('.btn-delete').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.getAttribute('data-id');
      if (!id) return;
      if (!confirm('Delete this document?')) return;
      const fd = new FormData();
      fd.append('_token','<?= csrf_token() ?>');
      fd.append('action', 'delete_upload');
      fd.append('id', id);
      fetch('operations_review_new.php', { method:'POST', body: fd })
        .then(r=>r.json()).then(d=>{
          if (d && d.success) { location.reload(); }
          else { alert(d && d.error ? d.error : 'Delete failed'); }
        }).catch(()=> alert('Delete failed'));
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
