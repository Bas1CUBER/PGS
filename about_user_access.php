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

$pageStyles = '<style>
    body { background-color:#f5f7fa; color:#2c3e50; }
    .page-wrapper { min-height:100vh; padding-top:100px; }
    .section-title { background:#0b4aa2; color:#fff; text-align:center; font-weight:700; letter-spacing:.04em; padding:14px 16px; border-radius:1rem 1rem 0 0; }
    .card { border:none; border-radius:1rem; background:#fff; }
    table.user-access { border:1px solid #e5e7eb; }
    table.user-access th { background:#0b4aa2; color:#fff; white-space:nowrap; }
    table.user-access td, table.user-access th { border:1px solid #e5e7eb; vertical-align:middle; }
    .section-row td { background:#e6f0ff; font-weight:700; color:#0b4aa2; }
    .toolbar { display:flex; gap:.5rem; }
    .toolbar .btn { min-width: 120px; }
    td[contenteditable="true"] { outline: 2px dashed transparent; }
    td[contenteditable="true"]:focus { outline-color: #9dbbff; background:#f0f7ff; }
</style>';

$pageScripts = '';
if ($role === 'admin') {
    $csrf = csrf_token();
    $pageScripts = <<<EOSCRIPT
<script>
(function(){
  const CSRF_TOKEN = '{$csrf}';
  function p(params) {
    params.set('_token', CSRF_TOKEN);
    return params;
  }
  const table = document.getElementById('matrixTable');
  table.addEventListener('focusout', function(e){
    const td = e.target;
    if (td && td.tagName === 'TD' && td.hasAttribute('contenteditable') && td.getAttribute('contenteditable') === 'true') {
      const row = td.getAttribute('data-row');
      const col = td.getAttribute('data-col');
      const value = td.textContent.trim();
      fetch('about_user_access.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: p(new URLSearchParams({ action:'edit_cell', row: row, col: col, value: value }))
      }).then(async r=>{
        let data=null; try { data = await r.json(); } catch(e){}
        if (!(data && data.ok)) {
          alert((data && data.error) ? data.error : 'Save failed');
        }
      }).catch(()=>alert('Save failed'));
    }
  });
  document.getElementById('addRowBtn')?.addEventListener('click', function(){
    fetch('about_user_access.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: p(new URLSearchParams({ action:'add_row' }))
    }).then(async r=>{
      let d=null; try { d = await r.json(); } catch(e){}
      if (d && d.ok) location.reload(); else alert((d && d.error) || 'Add row failed');
    }).catch(()=>alert('Add row failed'));
  });
  document.getElementById('addColBtn')?.addEventListener('click', function(){
    const label = prompt('Enter new column label:');
    if (!label) return;
    fetch('about_user_access.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: p(new URLSearchParams({ action:'add_column', label: label }))
    }).then(async r=>{
      let d=null; try { d = await r.json(); } catch(e){}
      if (d && d.ok) location.reload(); else alert((d && d.error) || 'Add column failed');
    }).catch(()=>alert('Add column failed'));
  });
})();
</script>
EOSCRIPT;
}

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

