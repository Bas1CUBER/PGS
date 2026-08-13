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
$imgBaseName = 'Multi-Sector Governance System';
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
$pageTitle = 'Multi-Sector Governance System';
$pageStyles = <<<'STYLES'
html, body { height: 100%; }
body { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
.page-container { padding-top: 110px; }
.image-wrap { display: flex; justify-content: center; }
.osm-image { max-width: 100%; width: 100%; height: auto; border-radius: 1rem; border: 4px solid #196a6b; box-shadow: 0 0 0 3px rgba(25,106,107,.25), 0 20px 40px rgba(25,106,107,.25); cursor: zoom-in; }
@media (min-width: 992px) { .osm-image { width: 70%; } }
.toolbar { display: flex; justify-content: center; gap: .75rem; margin-top: 1rem; }
.edit-form { display: none; max-width: 700px; margin: 0 auto; }
.glow-btn { box-shadow: 0 0 0 2px rgba(25,106,107,.2), 0 10px 24px rgba(25,106,107,.18); }
STYLES;
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <main>
        <div class="container page-container">
            <div class="mb-4 text-center">
                <h3 class="fw-bold text-dark mb-1">Multi-Sector Governance System</h3>
                <div class="text-muted">Organization</div>
            </div>
            <div class="image-wrap">
                <?php if (file_exists($imgPath)): ?>
                    <img src="<?= h($imgUrl) ?>" alt="Multi-Sector Governance System" class="osm-image" id="osmImage">
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
                        <img src="<?= h($imgUrl) ?>" alt="Multi-Sector Governance System" class="fit-viewport">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
$pageScripts = '<script src="' . asset('js/pages/multi_sector_governance_system_1.js') . '"></script>';
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

