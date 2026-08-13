<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
$jsonFile = $dataDir . '/pgs_pathway.json';
$imgDir = __DIR__ . '/img';
if (!is_dir($imgDir)) { @mkdir($imgDir, 0755, true); }

function default_panels() {
  $items = [];
  for ($i=0;$i<4;$i++) {
    $items[] = ['type'=>'none','text'=>'','image'=>'','title'=>'Panel '.($i+1),'status'=>'N/A'];
  }
  return $items;
}

function load_panels($file) {
  if (!is_file($file)) return default_panels();
  $raw = @file_get_contents($file);
  $data = json_decode($raw, true);
  if (!$data || !is_array($data)) return default_panels();
  $data = array_values($data);
  for ($i=0;$i<4;$i++) {
    if (!isset($data[$i])) $data[$i] = ['type'=>'none','text'=>'','image'=>'','title'=>'Panel '.($i+1),'status'=>'N/A'];
    $data[$i] = array_merge(['type'=>'none','text'=>'','image'=>'','title'=>'Panel '.($i+1),'status'=>'N/A'],$data[$i]);
  }
  return $data;
}

function save_panels($file, $data) {
  return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) !== false;
}

$savedMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Invalid or expired form token.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  if ($action === 'save_panel') {
    $panels = load_panels($jsonFile);
    $idx = (int)($_POST['index'] ?? -1);
    $ctype = $_POST['content_type'] ?? 'none';
    $title = trim($_POST['title'] ?? '');
    $status = trim($_POST['status'] ?? '');
    if ($idx >=0 && $idx < 4) {
      if ($ctype === 'text') {
        $text = trim($_POST['text'] ?? '');
        $panels[$idx]['type'] = 'text';
        $panels[$idx]['text'] = $text;
        $panels[$idx]['image'] = '';
      } elseif ($ctype === 'image') {
        if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
          $file = $_FILES['image'];
          $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
          $maxSize = 20 * 1024 * 1024;
          if (isset($allowed[$file['type']]) && $file['size'] <= $maxSize) {
            foreach (glob($imgDir.'/pgs_pathway_panel_'.$idx.'.*') as $old) { @unlink($old); }
            $ext = $allowed[$file['type']];
            $newName = 'pgs_pathway_panel_'.$idx.'.'.$ext;
            $dest = $imgDir.'/'.$newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
              $panels[$idx]['type'] = 'image';
              $panels[$idx]['image'] = $newName;
              $panels[$idx]['text'] = '';
            } else {
              $savedMsg = 'error:Failed to save image.';
            }
          } else {
            $savedMsg = 'error:Invalid image. JPG, PNG, WEBP up to 20MB.';
          }
        } else {
          $savedMsg = 'error:No image selected.';
        }
      } else {
        $panels[$idx]['type'] = 'none';
        $panels[$idx]['text'] = '';
        $panels[$idx]['image'] = '';
      }
      $panels[$idx]['title'] = $title ?: ('Panel '.($idx+1));
      $panels[$idx]['status'] = $status ?: 'N/A';
      if (empty($savedMsg)) {
        if (save_panels($jsonFile, $panels)) {
          $savedMsg = 'success:Panel updated.';
          $userInfo = getUserInfo($userId);
          $userIdent = formatUserIdentifier($userInfo ?: []);
          $notifTitle = 'PGS Pathway Updated';
          $notifMsg = 'Admin '.$userIdent.' updated PGS Pathway panel '.($idx+1).'.';
          notifyAdmins('edit', $notifTitle, $notifMsg, null, 'pgs_pathway');
          notifyFocals('edit', $notifTitle, $notifMsg, null, 'pgs_pathway');
          $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
          while ($empRes && ($row = $empRes->fetch_assoc())) {
            createNotification((int)$row['id'], 'edit', $notifTitle, $notifMsg, null, 'pgs_pathway');
          }
        } else {
          $savedMsg = 'error:Failed to write data.';
        }
      }
    } else {
      $savedMsg = 'error:Invalid panel.';
    }
  }
}

$panels = load_panels($jsonFile);
function panel_image_url($p) {
  if ($p['type'] !== 'image' || empty($p['image'])) return '';
  $path = __DIR__.'/img/'.$p['image'];
  if (!is_file($path)) return '';
  return 'img/'.rawurlencode($p['image']).'?v='.filemtime($path);
}

$pageStyles = '<style>
    body { background-color:#f5f7fa; color:#2c3e50; }
    .page-wrapper { min-height:100vh; padding-top:100px; }
    .section-title { background:#0b4aa2; color:#fff; text-align:center; font-weight:800; letter-spacing:.05em; padding:18px 22px; border-radius:1rem 1rem 0 0; font-size:1.4rem; }
    .card { border:none; border-radius:1rem; background:#fff; }
    .panel-card { border-radius:16px; background:#fff; box-shadow:0 12px 28px rgba(11,74,162,.12); padding:12px; }
    .panel-box { height:240px; border:2px dashed #cbd5e1; border-radius:12px; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer; }
    .panel-box img { max-width:100%; max-height:100%; object-fit:contain; }
    .panel-title { font-weight:700; margin-top:10px; }
    .panel-status { font-size:.9rem; color:#64748b; }
    .edit-btn { position:absolute; top:10px; right:10px; }
</style>';

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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=3">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="container">
    <div class="card shadow-sm mb-4">
      <div class="section-title">PGS Pathway</div>
      <div class="p-3">
        <?php if (!empty($savedMsg)):
          $isErr = str_starts_with($savedMsg, 'error:');
          $text = substr($savedMsg, strpos($savedMsg, ':')+1);
        ?>
          <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?>"><?= h($text) ?></div>
        <?php endif; ?>
        <div class="row g-4">
          <?php for ($i=0;$i<4;$i++):
            $p = $panels[$i];
            $imgUrl = panel_image_url($p);
          ?>
          <div class="col-md-6">
            <div class="panel-card position-relative">
              <?php if ($role === 'admin'): ?>
              <button class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-index="<?= $i ?>"><i data-lucide="pencil"></i></button>
              <?php endif; ?>
              <div class="panel-box" data-bs-toggle="modal" data-bs-target="#viewModal" data-index="<?= $i ?>">
                <?php if ($p['type']==='image' && $imgUrl): ?>
                  <img src="<?= h($imgUrl) ?>" alt="Panel <?= $i+1 ?>">
                <?php elseif ($p['type']==='text' && !empty($p['text'])): ?>
                  <div class="text-center px-3">
                    <div class="fw-semibold"><?= nl2br(h($p['text'])) ?></div>
                  </div>
                <?php else: ?>
                  <?php if ($role === 'admin'): ?>
                  <div class="text-center text-muted">
                    <div><i data-lucide="plus" width="2em" height="2em" class="mb-2"></i></div>
                    <div>Add text or image</div>
                  </div>
                  <?php else: ?>
                  <div class="text-center text-muted">No content</div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="panel-title"><?= h($p['title']) ?></div>
              <div class="panel-status"><?= h($p['status']) ?></div>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header" style="background:#0b4aa2;color:#fff;">
          <h5 class="modal-title" id="viewTitle"></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="viewContent" class="text-center"></div>
          <div class="text-center mt-2"><span id="viewStatus" class="text-muted"></span></div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($role === 'admin'): ?>
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Panel</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save_panel">
          <input type="hidden" name="index" id="edit-index">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" class="form-control" name="title" id="edit-title" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" name="status" id="edit-status" placeholder="e.g., In Progress, Complete">
          </div>
          <div class="mb-3">
            <label class="form-label">Content Type</label>
            <select class="form-select" name="content_type" id="edit-type">
              <option value="text">Text</option>
              <option value="image">Image</option>
            </select>
          </div>
          <div class="mb-3" id="text-wrap">
            <label class="form-label">Text</label>
            <textarea class="form-control" name="text" id="edit-text" rows="5"></textarea>
          </div>
          <div class="mb-3" id="image-wrap" style="display:none;">
            <label class="form-label">Image (JPG, PNG, WEBP up to 20MB)</label>
            <input type="file" class="form-control" name="image" id="edit-image" accept=".jpg,.jpeg,.png,.webp">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function(){
      var panels = <?= json_encode($panels) ?>;
      var viewModal = document.getElementById('viewModal');
      viewModal.addEventListener('show.bs.modal', function (e) {
        var idx = e.relatedTarget.getAttribute('data-index');
        var p = panels[idx];
        document.getElementById('viewTitle').textContent = p.title || ('Panel ' + (parseInt(idx)+1));
        document.getElementById('viewStatus').textContent = p.status || '';
        var cont = document.getElementById('viewContent');
        cont.innerHTML = '';
        if (p.type === 'image' && p.image) {
          var img = document.createElement('img');
          img.src = 'img/' + encodeURIComponent(p.image) + '?v=' + Date.now();
          img.style.maxWidth = '100%';
          img.style.maxHeight = '70vh';
          cont.appendChild(img);
        } else if (p.type === 'text' && p.text) {
          var div = document.createElement('div');
          div.style.fontSize = '1.15rem';
          div.style.lineHeight = '1.75';
          div.textContent = p.text;
          cont.appendChild(div);
        } else {
          cont.textContent = 'No content';
        }
      });
      <?php if ($role === 'admin'): ?>
      var editModal = document.getElementById('editModal');
      var typeSel = document.getElementById('edit-type');
      var textWrap = document.getElementById('text-wrap');
      var imageWrap = document.getElementById('image-wrap');
      typeSel.addEventListener('change', function(){
        var v = this.value;
        textWrap.style.display = v==='text' ? 'block' : 'none';
        imageWrap.style.display = v==='image' ? 'block' : 'none';
      });
      editModal.addEventListener('show.bs.modal', function (e) {
        var idx = e.relatedTarget.getAttribute('data-index');
        var p = panels[idx];
        document.getElementById('edit-index').value = idx;
        document.getElementById('edit-title').value = p.title || '';
        document.getElementById('edit-status').value = p.status || '';
        document.getElementById('edit-text').value = p.type==='text' ? (p.text||'') : '';
        typeSel.value = p.type==='image' ? 'image' : 'text';
        typeSel.dispatchEvent(new Event('change'));
      });
      <?php endif; ?>
    });
  </script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

