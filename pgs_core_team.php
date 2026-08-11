<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
$role = $_SESSION['role'] ?? null;
$imgDir = __DIR__ . '/img';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}
// Base name without extension; we keep the extension from the uploaded file
$imgBaseName = 'PGS Core team';
$uploadMsg = '';
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $uploadMsg = 'error:Invalid or expired form token.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    $action = $_POST['action'] ?? '';
    if ($action === 'replace_image') {
        if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $maxSize = 20 * 1024 * 1024;
            if (!in_array($file['type'], $allowedTypes)) {
                $uploadMsg = 'error:Only JPG, PNG, or WEBP images are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $uploadMsg = 'error:File exceeds the 20MB size limit.';
            } else {
                $ext = $extMap[$file['type']];
                // Remove any old image files with any extension
                foreach (glob($imgDir . '/' . $imgBaseName . '.*') as $old) {
                    @unlink($old);
                }
                $newName = $imgBaseName . '.' . $ext;
                $newPath = $imgDir . '/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $newPath)) {
                    $uploadMsg = 'success:Image updated successfully.';
                } else {
                    $uploadMsg = 'error:Failed to save the uploaded file. Check folder permissions.';
                }
            }
        } else {
            $uploadMsg = 'error:No file was received. Please select an image.';
        }
    }
}

// Find the current image (any extension)
$imgName = '';
$imgPath = '';
$imgUrl  = '';
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = $imgDir . '/' . $imgBaseName . '.' . $ext;
    if (file_exists($candidate)) {
        $imgName = $imgBaseName . '.' . $ext;
        $imgPath = $candidate;
        $imgUrl  = 'img/' . rawurlencode($imgName) . '?v=' . filemtime($candidate);
        break;
    }
}
$pageTitle = 'PGS Core Team';
$pageStyles = <<<'STYLES'
html, body { height: 100%; }
body { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
.page-container { padding-top: 110px; }
.image-wrap { display: flex; justify-content: center; }
.core-image { max-width: 100%; width: 100%; height: auto; border-radius: 1rem; border: 4px solid #196a6b; box-shadow: 0 0 0 3px rgba(25,106,107,.25), 0 20px 40px rgba(25,106,107,.25); cursor: zoom-in; }
@media (min-width: 992px) { .core-image { width: 70%; } }
.toolbar { display: flex; justify-content: center; gap: .75rem; margin-top: 1rem; }
.edit-form { display: none; max-width: 700px; margin: 0 auto; }
.glow-btn { box-shadow: 0 0 0 2px rgba(25,106,107,.2), 0 10px 24px rgba(25,106,107,.18); }
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

    <main>
        <div class="container page-container">
            <div class="mb-4 text-center">
                <h3 class="fw-bold text-dark mb-1">PGS Core Team</h3>
                <div class="text-muted">Organization</div>
            </div>
            <div class="image-wrap">
                <?php if (file_exists($imgPath)): ?>
                    <img src="<?= h($imgUrl) ?>" alt="PGS Core Team" class="core-image" id="coreImage">
                <?php else: ?>
                    <div class="alert alert-warning w-100 w-lg-75 text-center" role="alert">
                        Image not found at <?= h($imgUrl) ?>.
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($role === 'admin'): ?>
            <div class="toolbar">
                <button id="editBtn" class="btn btn-outline-primary glow-btn"><i data-lucide="pencil" class="me-2"></i>Edit Image</button>
            </div>
            <div class="card shadow-sm edit-form" id="editForm">
                <div class="card-body">
                    <?php if ($uploadMsg): ?>
                        <?php [$msgType, $msgText] = explode(':', $uploadMsg, 2); ?>
                        <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                            <?= h($msgText) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="replace_image">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Replace Image</label>
                            <input type="file" name="image" id="imageInput" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                            <div class="form-text">Accepted: JPG, PNG, WEBP &mdash; max 20MB.</div>
                        </div>
                        <div id="previewWrap" class="col-12 text-center" style="display:none">
                            <img id="previewImg" src="" alt="Preview" style="max-height:200px;border-radius:.5rem;border:2px solid #196a6b;">
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn"><i data-lucide="x" class="me-1"></i>Cancel</button>
                            <button type="submit" class="btn btn-primary"><i data-lucide="upload" class="me-2"></i>Upload</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    <div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"><i data-lucide="x"></i></button>
                </div>
                <div class="modal-body p-0 d-flex align-items-center justify-content-center">
                    <?php if (file_exists($imgPath)): ?>
                        <img src="<?= h($imgUrl) ?>" alt="PGS Core Team" style="max-width: 100%; max-height: 100vh;">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
$pageScripts = <<<'SCRIPTS'
<script>
        document.addEventListener('DOMContentLoaded', function(){
            var img = document.getElementById('coreImage');
            if (img) {
                img.addEventListener('click', function(){
                    var modalEl = document.getElementById('zoomModal');
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });
            }
            var editBtn = document.getElementById('editBtn');
            var editForm = document.getElementById('editForm');
            var cancelBtn = document.getElementById('cancelEditBtn');
            if (editBtn && editForm) {
                editBtn.addEventListener('click', function(){
                    var isHidden = window.getComputedStyle(editForm).display === 'none';
                    editForm.style.display = isHidden ? 'block' : 'none';
                });
            }
            if (cancelBtn && editForm) {
                cancelBtn.addEventListener('click', function(){
                    editForm.style.display = 'none';
                });
            }
            // Image preview before upload
            var imageInput = document.getElementById('imageInput');
            var previewWrap = document.getElementById('previewWrap');
            var previewImg = document.getElementById('previewImg');
            if (imageInput && previewImg) {
                imageInput.addEventListener('change', function(){
                    var file = this.files[0];
                    if (file) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            previewWrap.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewWrap.style.display = 'none';
                    }
                });
            }
SCRIPTS;
if ($uploadMsg) {
    $pageScripts .= <<<'SCRIPTS2'
            if (editForm) editForm.style.display = 'block';
SCRIPTS2;
}
$pageScripts .= <<<'SCRIPTS3'
        });
    </script>
SCRIPTS3;
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

