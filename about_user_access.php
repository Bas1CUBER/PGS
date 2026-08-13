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
$jsonFile = $dataDir . '/user_access_matrix.json';

function default_matrix() {
  return [
    'columns' => ['Page','Employee','Focal','Admin'],
    'rows' => [
      ['section' => 'Roadmaps'],
      ['Page'=>'Roadmaps','Employee'=>'Table 1: Access, Table 2: Read Only','Focal'=>'Table 1: Access, Table 2: Read Only','Admin'=>'Table 1: ReadOnly, Table 2: Has Access'],
      ['section' => 'Scorecard'],
      ['Page'=>'Scorecard','Employee'=>'Read Only','Focal'=>'Read Only','Admin'=>'ADD, EDIT, DELETE sub items'],
      ['Page'=>'Added New sub-item','Employee'=>'Table Access','Focal'=>'Has Access','Admin'=>'All access'],
      ['Page'=>'Roadmaps','Employee'=>'Read Only','Focal'=>'','Admin'=>'Edit, (Change Status), Lock, Delete'],
      ['Page'=>'Impact Indicator','Employee'=>'Read Only','Focal'=>'Read Only','Admin'=>'Whole access'],
      ['section' => 'Performance Assessment'],
      ['Page'=>'Operations Review','Employee'=>'Can download and upload document','Focal'=>'Can download and upload document','Admin'=>'Read only and can edit status'],
      ['Page'=>'Strategy Review','Employee'=>'Read Only','Focal'=>'Read Only','Admin'=>'Upload a Document'],
      ['Page'=>'Strategy Refresh','Employee'=>'Read Only','Focal'=>'Read Only','Admin'=>'Upload a Document'],
      ['section' => 'Cascading'],
      ['Page'=>'Communication Plan','Employee'=>'Read only, download and upload PDF','Focal'=>'Add, delete, edit status, DL and upload','Admin'=>'Add, delete, edit status'],
      ['Page'=>'Cascading Activities','Employee'=>'Read only, download and upload PDF','Focal'=>'Read only, download and upload PDF','Admin'=>'Add, delete, edit status, DL and upload'],
      ['Page'=>'Resources','Employee'=>'Read Only','Focal'=>'','Admin'=>'Upload'],
      ['section' => 'Governance'],
      ['Page'=>'Governance Culture','Employee'=>'Upload file: img/pdf','Focal'=>'Upload file: img/pdf','Admin'=>'Edit, Save status'],
      ['Page'=>'Governance Sharing','Employee'=>'Upload file: img/pdf','Focal'=>'Upload file: img/pdf','Admin'=>'Edit, Save status'],
      ['section' => 'Organization'],
      ['Page'=>'Office of Strategy Management','Employee'=>'View/Read Only','Focal'=>'View/Read Only','Admin'=>'Edit'],
      ['Page'=>'PGS Core Team','Employee'=>'View/Read Only','Focal'=>'View/Read Only','Admin'=>'Edit'],
      ['Page'=>'Multi-Sector Governance System','Employee'=>'View/Read Only','Focal'=>'View/Read Only','Admin'=>'Edit'],
    ]
  ];
}

function read_matrix($file) {
  if (!is_file($file)) return default_matrix();
  $raw = @file_get_contents($file);
  $data = json_decode($raw, true);
  if (!$data || !isset($data['columns']) || !isset($data['rows'])) return default_matrix();
  return $data;
}

function save_matrix($file, $data) {
  @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'Invalid or expired form token.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  $matrix = read_matrix($jsonFile);
  $changed = false;
  if ($action === 'edit_cell') {
    $row = (int)($_POST['row'] ?? -1);
    $col = $_POST['col'] ?? '';
    $val = trim($_POST['value'] ?? '');
    if (!in_array($col, $matrix['columns'], true)) {
      $matrix['columns'][] = $col;
      foreach ($matrix['rows'] as &$r) { if (!isset($r['section'])) $r[$col] = $r[$col] ?? ''; }
    }
    if ($row >= 0 && $row < count($matrix['rows']) && $col) {
      if (!isset($matrix['rows'][$row]['section'])) {
        $matrix['rows'][$row][$col] = $val;
        $changed = true;
      } else {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'Cannot edit section header row']);
        exit();
      }
    } else {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'Invalid cell reference']);
      exit();
    }
  } elseif ($action === 'add_row') {
    $new = ['Page'=>'','Employee'=>'','Focal'=>'','Admin'=>''];
    $matrix['rows'][] = $new;
    $changed = true;
  } elseif ($action === 'add_column') {
    $label = trim($_POST['label'] ?? '');
    if ($label && !in_array($label, $matrix['columns'], true)) {
      $matrix['columns'][] = $label;
      foreach ($matrix['rows'] as &$r) { if (!isset($r['section'])) $r[$label] = ''; }
      $changed = true;
    }
  }
  if ($changed) {
    $res = @file_put_contents($jsonFile, json_encode($matrix, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if ($res === false) {
      header('Content-Type: application/json');
      echo json_encode(['ok'=>false,'error'=>'Failed to write data file']);
      exit();
    }
    $userInfo = getUserInfo($userId);
    $userIdent = formatUserIdentifier($userInfo ?: []);
    $title = 'User Access Matrix Updated';
    $message = 'Admin ' . $userIdent . ' updated the User Access table.';
    notifyAdmins('edit', $title, $message, null, 'about_user_access');
    notifyFocals('edit', $title, $message, null, 'about_user_access');
    $empRes = $conn->query("SELECT id FROM users WHERE role = 'employee'");
    while ($empRes && ($row = $empRes->fetch_assoc())) {
      createNotification((int)$row['id'], 'edit', $title, $message, null, 'about_user_access');
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit();
  }
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'No changes or invalid request']);
  exit();
}

$matrix = read_matrix($jsonFile);

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/about_user_access.css') . '">';

$pageScripts = '';
if ($role === 'admin') {
    $csrf = csrf_token();
    $pageScripts = '<script src="' . asset('js/pages/') . '"></script>
';
}

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="container">
    <div class="card shadow-sm">
      <div class="section-title">User Access (Roles & Permissions)</div>
      <div class="p-3">
        <?php if ($role === 'admin'): ?>
        <div class="toolbar mb-3">
          <button class="btn btn-primary btn-sm" id="addRowBtn"><i data-lucide="plus" class="me-1"></i>Add Row</button>
          <button class="btn btn-outline-primary btn-sm" id="addColBtn"><i data-lucide="columns" class="me-1"></i>Add Column</button>
          <small class="text-muted ms-2">Click a cell to edit. Changes save on blur.</small>
        </div>
        <?php else: ?>
        <div class="mb-2"><small class="text-muted">View only</small></div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-sm user-access" id="matrixTable">
            <thead>
              <tr>
                <?php foreach ($matrix['columns'] as $col): ?>
                  <th><?= h($col) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($matrix['rows'] as $idx => $r): ?>
                <?php if (isset($r['section'])): ?>
                  <tr class="section-row"><td colspan="<?= count($matrix['columns']) ?>"><?= h($r['section']) ?></td></tr>
                <?php else: ?>
                  <tr>
                    <?php foreach ($matrix['columns'] as $col): 
                      $val = $r[$col] ?? '';
                      $editable = ($role === 'admin') ? 'true' : 'false';
                    ?>
                      <td contenteditable="<?= $editable ?>" data-row="<?= $idx ?>" data-col="<?= h($col) ?>"><?= h($val) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
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

