<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Ensure surveys table exists
try {
  $conn->query("
    CREATE TABLE IF NOT EXISTS surveys (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      url VARCHAR(1024) NOT NULL,
      status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      archived_at TIMESTAMP NULL DEFAULT NULL,
      FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}

// Ensure survey completions table exists
try {
  $conn->query("
    CREATE TABLE IF NOT EXISTS surveys_done (
      survey_id INT NOT NULL,
      user_id INT NOT NULL,
      done_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (survey_id, user_id),
      FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'msg'=>'Invalid or expired form token.']);
    exit();
}

// Admin actions
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    if ($title === '' || $url === '' || !(stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0)) {
      echo json_encode(['ok'=>false,'msg'=>'Please provide a valid title and URL (http/https).']); exit;
    }
    $stmt = $conn->prepare("INSERT INTO surveys (title, url, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $url, $userId);
    $ok = $stmt->execute();
    if ($ok) {
      $newId = $conn->insert_id;
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifTitle = 'New Survey Link';
      $notifMsg = 'Admin ' . $userIdent . ' added a new survey: ' . $title;
      notifyFocals('upload', $notifTitle, $notifMsg, $newId, 'survey');
      $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
      while ($empRes && ($row = $empRes->fetch_assoc())) {
        createNotification((int)$row['id'], 'upload', $notifTitle, $notifMsg, $newId, 'survey');
      }
    }
    echo json_encode(['ok'=>$ok]); exit;
  }
  if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    if ($id <= 0 || $title === '' || $url === '' || !(stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0)) {
      echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
    }
    $stmt = $conn->prepare("UPDATE surveys SET title = ?, url = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $url, $id);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok]); exit;
  }
  if ($action === 'archive') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid id']); exit; }
    $ok = $conn->query("UPDATE surveys SET status = 'Archived', archived_at = CURRENT_TIMESTAMP WHERE id = {$id}");
    echo json_encode(['ok'=> (bool)$ok ]); exit;
  }
  if ($action === 'delete_archived') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid id']); exit; }
    $ok = $conn->query("DELETE FROM surveys WHERE id = {$id} AND status = 'Archived'");
    echo json_encode(['ok'=> (bool)$ok ]); exit;
  }
  if ($action === 'get_done_list') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid id']); exit; }
    $list = [];
    $stmt = $conn->prepare("SELECT u.id, u.email FROM surveys_done d JOIN users u ON u.id = d.user_id WHERE d.survey_id = ? ORDER BY u.email ASC");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) { $list[] = $row; }
      echo json_encode(['ok'=>true,'users'=>$list]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Query failed']); exit;
  }
  echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

// Employee/Focal actions for done toggle
if (in_array($role, ['employee','focal'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  if ($action === 'mark_done') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid id']); exit; }
    $stmt = $conn->prepare("INSERT IGNORE INTO surveys_done (survey_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $id, $userId);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok]); exit;
  }
  if ($action === 'unmark_done') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid id']); exit; }
    $stmt = $conn->prepare("DELETE FROM surveys_done WHERE survey_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $ok = $stmt->execute();
    echo json_encode(['ok'=>$ok]); exit;
  }
}

$active = [];
$archived = [];
try {
  $q = $conn->query("SELECT id, title, url, created_at FROM surveys WHERE status = 'Active' ORDER BY created_at DESC");
  while ($q && ($r = $q->fetch_assoc())) { $active[] = $r; }
  $qa = $conn->query("SELECT id, title, url, archived_at FROM surveys WHERE status = 'Archived' ORDER BY archived_at DESC, id DESC");
  while ($qa && ($ra = $qa->fetch_assoc())) { $archived[] = $ra; }
  // Enrich with done counts and user flag
  foreach ($active as &$row) {
    $sid = (int)$row['id'];
    $cnt = 0; $row['done_count'] = 0; $row['user_done'] = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM surveys_done WHERE survey_id = ?");
    $stmt->bind_param("i", $sid);
    if ($stmt->execute()) { $rs = $stmt->get_result()->fetch_assoc(); $cnt = (int)($rs['c'] ?? 0); }
    $row['done_count'] = $cnt;
    if ($role === 'employee' || $role === 'focal') {
      $stmt2 = $conn->prepare("SELECT 1 FROM surveys_done WHERE survey_id = ? AND user_id = ? LIMIT 1");
      $stmt2->bind_param("ii", $sid, $userId);
      if ($stmt2->execute()) { $r2 = $stmt2->get_result(); $row['user_done'] = $r2 && $r2->num_rows > 0 ? 1 : 0; }
    }
  } unset($row);
} catch (Throwable $e) {}
$pageTitle = 'Survey';
$pageStyles = <<<'STYLES'
html, body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f5f7fa; color: #2c3e50; height: 100%; margin: 0; padding-top: 20px; }
.card { border: none; border-radius: 1rem; background: #fff; }
.section-title { background:#0b4aa2; color:#fff; text-align:center; font-weight:700; letter-spacing:.04em; padding:14px 16px; border-radius:1rem 1rem 0 0; }
.table th { background:#f0f2f5; color:#34495e; }
STYLES;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <div class="card shadow-sm mb-4">
      <div class="section-title d-flex justify-content-between align-items-center">
        <span>Survey Links</span>
        <?php if ($role === 'admin'): ?>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
          <i data-lucide="plus" class="me-1"></i> Add Link
        </button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (empty($active)): ?>
          <div class="alert alert-info mb-0">No active surveys at the moment.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th style="width:40%;">Title</th>
                <th>Link</th>
                <?php if ($role === 'admin'): ?>
                  <th style="width:120px;">Done</th>
                  <th style="width:160px;">Actions</th>
                <?php else: ?>
                  <th style="width:150px;">Mark as Done</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($active as $row): ?>
              <tr>
                <td><?= h($row['title']) ?></td>
                <td><a href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['url']) ?></a></td>
                <?php if ($role === 'admin'): ?>
                <td>
                  <button class="btn btn-sm btn-outline-secondary btn-done-list" data-id="<?= (int)$row['id'] ?>">
                    <?= (int)($row['done_count'] ?? 0) ?> Done
                  </button>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-primary me-2 btn-edit"
                          data-id="<?= (int)$row['id'] ?>"
                          data-title="<?= h($row['title']) ?>"
                          data-url="<?= h($row['url']) ?>">
                    <i data-lucide="pencil" class="me-1"></i>Edit
                  </button>
                  <button class="btn btn-sm btn-outline-warning btn-archive" data-id="<?= (int)$row['id'] ?>">
                    <i data-lucide="archive" class="me-1"></i>Archive
                  </button>
                </td>
                <?php endif; ?>
                <?php if ($role !== 'admin'): ?>
                <td>
                  <div class="form-check">
                    <input class="form-check-input done-checkbox" type="checkbox"
                           data-id="<?= (int)$row['id'] ?>" <?= !empty($row['user_done']) ? 'checked' : '' ?>>
                    <label class="form-check-label">Done</label>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($role === 'admin'): ?>
    <div class="card shadow-sm mb-5">
      <div class="section-title">Archived Surveys</div>
      <div class="card-body">
        <?php if (empty($archived)): ?>
          <div class="alert alert-light border mb-0">No archived surveys.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th style="width:40%;">Title</th>
                <th>Link</th>
                <th style="width:180px;">Archived At</th>
                <th style="width:120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archived as $row): ?>
              <tr>
                <td><?= h($row['title']) ?></td>
                <td><a href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['url']) ?></a></td>
                <td><?= h($row['archived_at'] ?? '') ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$row['id'] ?>">
                    <i data-lucide="trash-2" class="me-1"></i>Delete
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" id="addForm">
          <div class="modal-header">
            <h5 class="modal-title">Add Survey Link</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Survey Title</label>
              <input type="text" class="form-control" name="title" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Survey Link (Google Forms)</label>
              <input type="url" class="form-control" name="url" placeholder="https://..." required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" id="editForm">
          <input type="hidden" name="id" id="editId">
          <div class="modal-header">
            <h5 class="modal-title">Edit Survey Link</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Survey Title</label>
              <input type="text" class="form-control" name="title" id="editTitle" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Survey Link</label>
              <input type="url" class="form-control" name="url" id="editUrl" placeholder="https://..." required>
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
    <?php if ($role === 'admin'): ?>
    <!-- Done List Modal -->
    <div class="modal fade" id="doneListModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Users Marked as Done</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <ul class="list-group" id="doneList"></ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
<?php
$pageScripts = '';
if ($role === 'admin') {
    $pageScripts .= '<script src="' . asset('js/pages/survey_1.js') . '"></script>';
}
if ($role !== 'admin') {
    $pageScripts .= '<script src="' . asset('js/pages/survey_2.js') . '"></script>';
}
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

