<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
$role = $_SESSION['role'] ?? null;

$imgDir = __DIR__ . '/img';
if (!is_dir($imgDir)) {
  mkdir($imgDir, 0755, true);
}
$imgBaseName = 'About Strategy Map';
$uploadMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $uploadMsg = 'error:Invalid or expired form token.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  if ($action === 'replace_image') {
    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
      $file = $_FILES['image'];
      $allowedTypes = ['image/jpeg','image/png','image/webp'];
      $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      $maxSize = 20 * 1024 * 1024;
      if (!in_array($file['type'], $allowedTypes, true)) {
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

$pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/about_strategy_map.css') . '">';

$pageScripts = '<script src="' . asset('js/pages/') . '"></script>
';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="container">
    <div class="card shadow-sm">
      <div class="section-title">Strategy Map</div>
      <div class="p-4">
        <div class="image-wrap">
          <?php if ($imgUrl): ?>
            <img src="<?= h($imgUrl) ?>" alt="Strategy Map" class="map-image" data-bs-toggle="modal" data-bs-target="#zoomModal">
          <?php else: ?>
            <div class="alert alert-warning w-100 text-center" role="alert">
              Image not found. Admin can upload below.
            </div>
          <?php endif; ?>
        </div>
        <?php if ($role === 'admin'): ?>
        <div class="toolbar">
          <button class="btn btn-outline-primary glow-btn" id="editBtn"><i data-lucide="pencil" class="me-1"></i>Edit / Replace</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="edit-form mt-3" id="editForm">
            <?= csrf_field() ?>
          <input type="hidden" name="action" value="replace_image">
          <div class="row g-3 align-items-center">
            <div class="col-12">
              <label class="form-label">Select Image (JPG, PNG, WEBP, max 20MB)</label>
              <input type="file" class="form-control" name="image" id="imageInput" accept=".jpg,.jpeg,.png,.webp" required>
            </div>
            <div class="col-12" id="previewWrap" style="display:none;">
              <img id="previewImg" src="" alt="Preview" style="max-height:200px;border-radius:.5rem;border:2px solid #0b4aa2;">
            </div>
            <div class="col-12 d-flex gap-2 justify-content-end">
              <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn"><i data-lucide="x" class="me-1"></i>Cancel</button>
              <button type="submit" class="btn btn-primary"><i data-lucide="upload" class="me-2"></i>Upload</button>
            </div>
          </div>
        </form>
        <?php endif; ?>
        <?php if (!empty($uploadMsg)): 
          $isErr = str_starts_with($uploadMsg, 'error:');
          $text = substr($uploadMsg, strpos($uploadMsg, ':')+1);
        ?>
          <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?> mt-3"><?= h($text) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content bg-dark">
        <div class="modal-header border-0">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body p-0 d-flex align-items-center justify-content-center">
          <?php if ($imgUrl): ?>
            <img src="<?= h($imgUrl) ?>" alt="Strategy Map" class="fit-viewport">
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

