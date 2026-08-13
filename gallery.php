<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
$role = $_SESSION['role'] ?? null;

// Create tables if not exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS gallery_albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS gallery_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        album_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        caption TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Upload directory
$uploadDir = __DIR__ . '/gallery_uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo "Invalid or expired form token.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add Album (admin and focal)
    if ($action === 'add_album' && in_array($role, ['admin', 'focal'])) {
        $name = trim($_POST['album_name'] ?? '');
        $description = trim($_POST['album_description'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO gallery_albums (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            $message = 'Album created successfully.';
            $messageType = 'success';
        } else {
            $message = 'Album name is required.';
            $messageType = 'danger';
        }
    }

    // Edit Album (admin and focal)
    if ($action === 'update_album' && in_array($role, ['admin', 'focal'])) {
        $albumId = (int)($_POST['album_id'] ?? 0);
        $name = trim($_POST['album_name'] ?? '');
        $description = trim($_POST['album_description'] ?? '');
        if ($albumId > 0 && $name !== '') {
            $stmt = $pdo->prepare('UPDATE gallery_albums SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description !== '' ? $description : null, $albumId]);
            $message = 'Album updated successfully.';
            $messageType = 'success';
        } else {
            $message = 'Album name is required.';
            $messageType = 'danger';
        }
    }
    
    // Delete Album (admin only)
    if ($action === 'delete_album' && $role === 'admin') {
        $albumId = (int)($_POST['album_id'] ?? 0);
        if ($albumId > 0) {
            // Get photos to delete files
            $stmt = $pdo->prepare('SELECT filename FROM gallery_photos WHERE album_id = ?');
            $stmt->execute([$albumId]);
            $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($photos as $filename) {
                $filePath = $uploadDir . '/' . $filename;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            // Delete album (photos will cascade delete)
            $stmt = $pdo->prepare('DELETE FROM gallery_albums WHERE id = ?');
            $stmt->execute([$albumId]);
            $message = 'Album deleted successfully.';
            $messageType = 'success';
        }
    }
    
    // Upload Photos (admin and focal)
    if ($action === 'upload_photos' && in_array($role, ['admin', 'focal'])) {
        $albumId = (int)($_POST['album_id'] ?? 0);
        if ($albumId > 0 && isset($_FILES['photos'])) {
            $captions = $_POST['captions'] ?? [];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $extMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];
            $maxSize = 20 * 1024 * 1024;
            $uploadCount = 0;
            
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmpName) {
                if (is_uploaded_file($tmpName)) {
                    $fileType = $_FILES['photos']['type'][$i];
                    $fileSize = $_FILES['photos']['size'][$i];
                    
                    if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                        $ext = $extMap[$fileType];
                        $filename = uniqid('img_') . '.' . $ext;
                        $destPath = $uploadDir . '/' . $filename;
                        
                        if (move_uploaded_file($tmpName, $destPath)) {
                            $caption = trim($captions[$i] ?? '');
                            $stmt = $pdo->prepare('INSERT INTO gallery_photos (album_id, filename, caption) VALUES (?, ?, ?)');
                            $stmt->execute([$albumId, $filename, $caption !== '' ? $caption : null]);
                            $uploadCount++;
                        }
                    }
                }
            }
            $message = "$uploadCount photo(s) uploaded successfully.";
            $messageType = 'success';
        }
    }
    
    // Delete Photo (admin only)
    if ($action === 'delete_photo' && $role === 'admin') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        if ($photoId > 0) {
            $stmt = $pdo->prepare('SELECT filename FROM gallery_photos WHERE id = ?');
            $stmt->execute([$photoId]);
            $filename = $stmt->fetchColumn();
            if ($filename) {
                $filePath = $uploadDir . '/' . $filename;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $stmt = $pdo->prepare('DELETE FROM gallery_photos WHERE id = ?');
                $stmt->execute([$photoId]);
                $message = 'Photo deleted successfully.';
                $messageType = 'success';
            }
        }
    }
    
    // Update Caption (admin and focal)
    if ($action === 'update_caption' && in_array($role, ['admin', 'focal'])) {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        if ($photoId > 0) {
            $stmt = $pdo->prepare('UPDATE gallery_photos SET caption = ? WHERE id = ?');
            $stmt->execute([$caption !== '' ? $caption : null, $photoId]);
            $message = 'Caption updated successfully.';
            $messageType = 'success';
        }
    }
}

// Fetch albums
$stmt = $pdo->query('SELECT * FROM gallery_albums ORDER BY created_at DESC');
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch photos for selected album
$selectedAlbum = null;
$photos = [];
$albumId = isset($_GET['album_id']) ? (int)$_GET['album_id'] : 0;
if ($albumId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM gallery_albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $selectedAlbum = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare('SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$albumId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$pageTitle = 'Gallery - PGS';
$pageStyles = <<<'STYLES'
html, body { height: 100%; }
body { display: flex; flex-direction: column; min-height: 100vh; font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f5f7fa; }
main { flex: 1; }
.page-container { padding-top: 90px; }
.album-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
.album-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; cursor: pointer; position: relative; }
.album-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.15); }
.album-cover { height: 200px; background: linear-gradient(135deg, #1e88e5 0%, #0b4aa2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; }
.album-cover img { width: 100%; height: 100%; object-fit: cover; }
.album-info { padding: 1rem; }
.album-info h5 { margin: 0; font-weight: 600; color: #2c3e50; }
.album-info p { margin: 0.5rem 0 0; color: #6c757d; font-size: 0.875rem; }
.album-count { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; }
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
.photo-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: all 0.3s ease; }
.photo-card:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.photo-img { width: 100%; height: 200px; object-fit: cover; cursor: zoom-in; }
.photo-caption { padding: 0.75rem; font-size: 0.875rem; color: #495057; min-height: 50px; }
.photo-actions { padding: 0.5rem 0.75rem; background: #f8f9fa; display: flex; gap: 0.5rem; }
.back-btn { margin-bottom: 1.5rem; }
.toolbar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.modal-img { max-width: 100%; max-height: 70vh; border-radius: 8px; }
.modal-caption { padding: 1rem; background: #f8f9fa; border-radius: 8px; margin-top: 1rem; font-size: 1rem; color: #495057; }
.upload-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; max-height: 400px; overflow-y: auto; padding: 1rem; background: #f8f9fa; border-radius: 8px; }
.preview-item { position: relative; }
.preview-item img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; }
.preview-item textarea { width: 100%; margin-top: 0.5rem; font-size: 0.75rem; border-radius: 4px; border: 1px solid #dee2e6; padding: 0.25rem; resize: none; height: 50px; }
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo h($messageType); ?> alert-dismissible fade show" role="alert">
                    <?php echo h($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($selectedAlbum): ?>
                <!-- Photo View -->
                <button class="btn btn-outline-secondary back-btn" onclick="window.location.href='gallery'">
                    <i data-lucide="arrow-left" class="me-2"></i>Back to Albums
                </button>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1"><?php echo h($selectedAlbum['name']); ?></h3>
                        <?php if ($selectedAlbum['description']): ?>
                            <p class="text-muted mb-0"><?php echo h($selectedAlbum['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array($role, ['admin', 'focal'])): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i data-lucide="upload" class="me-2"></i>Upload Photos
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($photos)): ?>
                    <div class="text-center py-5">
                        <i data-lucide="images" width="4em" height="4em" class="text-muted mb-3"></i>
                        <h5 class="text-muted">No photos yet</h5>
                        <?php if (in_array($role, ['admin', 'focal'])): ?>
                            <p class="text-muted">Click "Upload Photos" to add photos to this album.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="photo-grid">
                        <?php foreach ($photos as $photo): ?>
                            <div class="photo-card">
                                <img src="gallery_uploads/<?php echo h($photo['filename']); ?>" 
                                     alt="Photo" class="photo-img"
                                     data-filename="<?php echo h($photo['filename']); ?>"
                                     data-caption="<?php echo h($photo['caption'] ?? ''); ?>"
                                     style="cursor: zoom-in;">
                                <?php if ($photo['caption']): ?>
                                    <div class="photo-caption"><?php echo h($photo['caption']); ?></div>
                                <?php endif; ?>
                                <?php if (in_array($role, ['admin', 'focal'])): ?>
                                    <div class="photo-actions">
                                        <button class="btn btn-sm btn-outline-primary edit-caption-btn"
                                                data-photo-id="<?php echo h($photo['id']); ?>"
                                                data-caption="<?php echo h($photo['caption'] ?? ''); ?>">
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <?php if ($role === 'admin'): ?>
                                        <button class="btn btn-sm btn-outline-danger delete-photo-btn"
                                                data-photo-id="<?php echo h($photo['id']); ?>">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Album Grid View -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">Gallery</h3>
                    <?php if (in_array($role, ['admin', 'focal'])): ?>
                        <div class="toolbar">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAlbumModal">
                                <i data-lucide="plus" class="me-2"></i>Add Album
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($albums)): ?>
                    <div class="text-center py-5">
                        <i data-lucide="folder-open" width="4em" height="4em" class="text-muted mb-3"></i>
                        <h5 class="text-muted">No albums yet</h5>
                        <?php if (in_array($role, ['admin', 'focal'])): ?>
                            <p class="text-muted">Click "Add Album" to create your first album.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="album-grid">
                        <?php foreach ($albums as $album): ?>
                            <?php
                            // Get photo count and cover image
                            $stmt = $pdo->prepare('SELECT COUNT(*) as cnt, filename FROM gallery_photos WHERE album_id = ? ORDER BY uploaded_at DESC LIMIT 1');
                            $stmt->execute([$album['id']]);
                            $photoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                            $photoCount = $photoInfo['cnt'] ?? 0;
                            $coverImage = $photoInfo['filename'] ?? null;
                            ?>
                            <div class="album-card" onclick="window.location.href='gallery?album_id=<?php echo h($album['id']); ?>'">
                                <div class="album-cover">
                                    <?php if ($coverImage && file_exists($uploadDir . '/' . $coverImage)): ?>
                                        <img src="gallery_uploads/<?php echo h($coverImage); ?>" alt="Cover">
                                    <?php else: ?>
                                        <i data-lucide="images"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="album-count"><?php echo h($photoCount); ?> photo<?php echo $photoCount !== 1 ? 's' : ''; ?></span>
                                <div class="album-info">
                                    <h5><?php echo h($album['name']); ?></h5>
                                    <?php if ($album['description']): ?>
                                        <p><?php echo h(mb_strimwidth($album['description'], 0, 80, '...')); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (in_array($role, ['admin', 'focal'])): ?>
                                    <div class="position-absolute bottom-0 end-0 p-2">
                                        <button class="btn btn-sm btn-warning me-1 edit-album-btn"
                                                data-album-id="<?php echo (int)$album['id']; ?>"
                                                data-album-name="<?php echo h($album['name']); ?>"
                                                data-album-description="<?php echo h($album['description'] ?? ''); ?>"
                                                onclick="event.stopPropagation();">
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <?php if ($role === 'admin'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); deleteAlbum(<?php echo h($album['id']); ?>)">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php
$pageScripts = '<script src="' . asset('js/pages/gallery_1.js') . '"></script>';
?>

<!-- Add Album Modal -->
<?php if (in_array($role, ['admin', 'focal'])): ?>
<div class="modal fade" id="addAlbumModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="gallery" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_album">
      <div class="modal-header">
        <h5 class="modal-title">Add Album</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Album Name</label>
          <input type="text" name="album_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Description (optional)</label>
          <textarea name="album_description" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Album</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Album Modal -->
<div class="modal fade" id="editAlbumModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="gallery" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_album">
      <input type="hidden" name="album_id" id="editAlbumId">
      <div class="modal-header">
        <h5 class="modal-title">Edit Album</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Album Name</label>
          <input type="text" name="album_name" id="editAlbumName" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Description (optional)</label>
          <textarea name="album_description" id="editAlbumDescription" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Upload Photos Modal -->
<?php if (in_array($role, ['admin', 'focal'])): ?>
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="gallery" enctype="multipart/form-data" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_photos">
      <input type="hidden" name="album_id" value="<?= (int)$albumId ?>">
      <div class="modal-header">
        <h5 class="modal-title">Upload Photos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Select Photos (JPG, PNG, WebP, GIF — max 20MB each)</label>
          <input type="file" name="photos[]" id="photoInput" class="form-control" accept="image/*" multiple required>
        </div>
        <div id="previewArea" style="display:none;">
          <label class="form-label">Captions (optional)</label>
          <div id="previewGrid" class="upload-preview"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload Photos</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Caption Modal -->
<?php if (in_array($role, ['admin', 'focal'])): ?>
<div class="modal fade" id="captionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="gallery" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_caption">
      <input type="hidden" name="photo_id" id="captionPhotoId">
      <div class="modal-header">
        <h5 class="modal-title">Edit Caption</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Caption</label>
          <textarea name="caption" id="captionText" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Caption</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Delete Album Form (hidden) -->
<?php if ($role === 'admin'): ?>
<form method="POST" action="gallery" id="deleteAlbumForm" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete_album">
  <input type="hidden" name="album_id" id="deleteAlbumId">
</form>

<!-- Delete Photo Form (hidden) -->
<form method="POST" action="gallery" id="deletePhotoForm" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete_photo">
  <input type="hidden" name="photo_id" id="deletePhotoId">
</form>
<?php endif; ?>

<!-- Zoom Modal -->
<div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Photo Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" alt="Zoomed Photo" id="zoomImg" class="modal-img">
        <div id="zoomCaption" class="modal-caption" style="display:none;"></div>
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

