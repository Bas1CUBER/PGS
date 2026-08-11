<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
$role = $_SESSION['role'] ?? null;

$imgDir = __DIR__ . '/img';
if (!is_dir($imgDir)) { mkdir($imgDir, 0755, true); }
$imgBaseName = 'About Strategic Position';
$uploadMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $uploadMsg = 'error:Invalid or expired form token.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  if ($action === 'replace_image') {
    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
      $file = $_FILES['image'];
      $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      $maxSize = 20 * 1024 * 1024;
      if (!isset($allowed[$file['type']])) {
        $uploadMsg = 'error:Only JPG, PNG, or WEBP images are allowed.';
      } elseif ($file['size'] > $maxSize) {
        $uploadMsg = 'error:File exceeds the 20MB size limit.';
      } else {
        foreach (glob($imgDir . '/' . $imgBaseName . '.*') as $old) { @unlink($old); }
        $ext = $allowed[$file['type']];
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
$imgUrl  = '';
foreach (['jpg','jpeg','png','webp'] as $ext) {
  $candidate = $imgDir . '/' . $imgBaseName . '.' . $ext;
  if (file_exists($candidate)) {
    $imgName = $imgBaseName . '.' . $ext;
    $imgUrl  = 'img/' . rawurlencode($imgName) . '?v=' . filemtime($candidate);
    break;
  }
}

$pageStyles = '<style>
    body { background-color:#f5f7fa; color:#2c3e50; }
    .page-wrapper { min-height:100vh; padding-top:100px; }
    .section-title { background:#0b4aa2; color:#fff; text-align:center; font-weight:700; letter-spacing:.04em; padding:14px 16px; border-radius:1rem 1rem 0 0; }
    .card { border:none; border-radius:1rem; background:#fff; }
    .image-wrap { display:flex; justify-content:center; }
    .map-image { max-width:100%; width:100%; height:auto; border-radius:1rem; border:4px solid #0b4aa2; box-shadow:0 0 0 3px rgba(11,74,162,.25), 0 20px 40px rgba(11,74,162,.25); cursor:zoom-in; }
    @media (min-width: 992px) { .map-image { width:70%; } }
    .toolbar { display:flex; justify-content:center; gap:.75rem; margin-top:1rem; }
    .edit-form { display:none; max-width:700px; margin:0 auto; }
</style>';

$pageScripts = <<<'EOSCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function(){
  var editBtn = document.getElementById('editBtn');
  var editForm = document.getElementById('editForm');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var imageInput = document.getElementById('imageInput');
  var previewImg = document.getElementById('previewImg');
  var previewWrap = document.getElementById('previewWrap');
  if (editBtn && editForm) {
    editBtn.addEventListener('click', function(){ editForm.style.display = editForm.style.display === 'block' ? 'none' : 'block'; });
  }
  if (cancelBtn && editForm) {
    cancelBtn.addEventListener('click', function(){ editForm.style.display = 'none'; previewWrap.style.display='none'; imageInput.value=''; });
  }
  if (imageInput) {
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
});
</script>
EOSCRIPT;

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

<div class="container">
    <div class="card shadow-sm">
      <div class="section-title">Strategic Position</div>
      <div class="p-4">
        <div class="image-wrap">
          <?php if ($imgUrl): ?>
            <img src="<?= h($imgUrl) ?>" alt="Strategic Position" class="map-image" data-bs-toggle="modal" data-bs-target="#zoomModal">
          <?php else: ?>
            <div class="alert alert-warning w-100 text-center" role="alert">
              Image not found. Admin can upload below.
            </div>
          <?php endif; ?>
        </div>
        <?php if ($role === 'admin'): ?>
        <div class="toolbar">
          <button class="btn btn-outline-primary" id="editBtn"><i data-lucide="pencil" class="me-1"></i>Edit / Replace</button>
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
            <img src="<?= h($imgUrl) ?>" alt="Strategic Position" style="max-width:100%; max-height:100vh;">
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

