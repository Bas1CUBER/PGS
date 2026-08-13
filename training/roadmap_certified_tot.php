<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_page_access('roadmaps');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

$role = $_SESSION['role'] ?? 'guest';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'save_table1') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNumeric = ['y2024','y2025','y2026','y2027','y2028'];
      $allowedText = ['section','personnel','is_head'];
      if (in_array($field, $allowedNumeric, true)) {
        if (!in_array($role, ['admin','employee','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (int)$value;
        $stmt = $pdo->prepare("UPDATE training_tot_personnel SET {$field} = :v WHERE id = :id");
        $stmt->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated " . strtoupper($field) . " in Certified TOT personnel";
        notifyAdmins('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
        notifyFocals('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
        echo json_encode(['ok'=>true]); exit;
      } elseif (in_array($field, $allowedText, true)) {
        if ($role !== 'admin') throw new Exception('Admin only');
        if ($field === 'is_head') {
          $val = ((int)$value) ? 1 : 0;
        } else {
          $val = trim((string)$value);
        }
        $stmt = $pdo->prepare("UPDATE training_tot_personnel SET {$field} = :v WHERE id = :id");
        $stmt->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $notifMsg = "Admin " . $userIdent . " updated " . $field . " in Certified TOT personnel";
        notifyAdmins('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
        notifyFocals('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
        echo json_encode(['ok'=>true]); exit;
      } else {
        throw new Exception('Bad field');
      }
    }
    if ($action === 'add_table1') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $section = trim($_POST['section'] ?? '');
      $personnel = trim($_POST['personnel'] ?? '');
      $is_head = isset($_POST['is_head']) ? 1 : 0;
      $stmt = $pdo->prepare("INSERT INTO training_tot_personnel (section, personnel, is_head, created_by) VALUES (:s,:p,:h,:cb)");
      $stmt->execute([':s'=>$section, ':p'=>$personnel, ':h'=>$is_head, ':cb'=>$userId ?: null]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " added new personnel: " . $personnel . " in Certified TOT";
      notifyAdmins('upload', 'Certified TOT Updated', $notifMsg, $newId, 'certified_tot');
      notifyFocals('upload', 'Certified TOT Updated', $notifMsg, $newId, 'certified_tot');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'add_table2') {
      if (!in_array($role, ['admin','employee','focal'], true)) throw new Exception('Not allowed');
      $serial_no_raw = trim($_POST['serial_no'] ?? '');
      $serial_no = ($serial_no_raw === '') ? null : (int)$serial_no_raw;
      $name = trim($_POST['training_type'] ?? ($_POST['title'] ?? ''));
      $participants = trim($_POST['participants'] ?? '');
      $date_label = trim($_POST['date_label'] ?? '');
      preg_match('/(20\\d{2})/', $date_label, $m);
      $year = isset($m[1]) ? (int)$m[1] : null;
      $stmt = $pdo->prepare("INSERT INTO training_tot_events (serial_no, title, training_type, participants, date_label, year, locked, created_by) VALUES (:sn,:t,:tt,:p,:d,:y,0,:cb)");
      $stmt->execute([':sn'=>$serial_no, ':t'=>$name ?: null, ':tt'=>$name ?: null, ':p'=>$participants ?: null, ':d'=>$date_label ?: null, ':y'=>$year, ':cb'=>$userId]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added training event: " . $name . " in Certified TOT";
      notifyAdmins('upload', 'Certified TOT Updated', $notifMsg, $newId, 'certified_tot');
      notifyFocals('upload', 'Certified TOT Updated', $notifMsg, $newId, 'certified_tot');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'edit_table2') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $id = (int)($_POST['id'] ?? 0);
      $serial_no_raw = trim($_POST['serial_no'] ?? '');
      $serial_no = ($serial_no_raw === '') ? null : (int)$serial_no_raw;
      $name = trim($_POST['name'] ?? '');
      $participants = trim($_POST['participants'] ?? '');
      $date_label = trim($_POST['date_label'] ?? '');
      preg_match('/(20\\d{2})/', $date_label, $m);
      $year = isset($m[1]) ? (int)$m[1] : null;
      $stmt = $pdo->prepare("UPDATE training_tot_events SET serial_no = :sn, title = :t, training_type = :t, participants = :p, date_label = :d, year = :y WHERE id = :id");
      $stmt->execute([':sn'=>$serial_no, ':t'=>$name ?: null, ':p'=>$participants ?: null, ':d'=>$date_label ?: null, ':y'=>$year, ':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " edited training event: " . $name . " in Certified TOT";
      notifyAdmins('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
      notifyFocals('edit', 'Certified TOT Updated', $notifMsg, $id, 'certified_tot');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_table2') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? '';
      $allowed = ['title','training_type','participants','date_label'];
      if (!in_array($field, $allowed, true)) throw new Exception('Bad field');
      $val = trim($value);
      // Keep title and training_type in sync whichever is edited.
      if ($field === 'training_type' || $field === 'title') {
        $stmt = $pdo->prepare("UPDATE training_tot_events SET title = :v, training_type = :v WHERE id = :id");
        $stmt->execute([':v'=>$val, ':id'=>$id]);
      } else {
        $stmt = $pdo->prepare("UPDATE training_tot_events SET {$field} = :v WHERE id = :id");
        $stmt->execute([':v'=>$val, ':id'=>$id]);
      }
      if ($field === 'date_label') {
        preg_match('/(20\\d{2})/', $val, $m);
        $year = isset($m[1]) ? (int)$m[1] : null;
        $pdo->prepare("UPDATE training_tot_events SET year = :y WHERE id = :id")->execute([':y'=>$year, ':id'=>$id]);
      }
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'set_lock_table2') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE training_tot_events SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_table2') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM training_tot_events WHERE id = :id")->execute([':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'focal_add_table1') {
      if ($role !== 'focal') throw new Exception('Focal only');
      $section = trim($_POST['section'] ?? '');
      $personnel = trim($_POST['personnel'] ?? '');
      $is_head = isset($_POST['is_head']) ? 1 : 0;
      $stmt = $pdo->prepare("INSERT INTO training_tot_personnel (section, personnel, is_head, created_by) VALUES (:s,:p,:h,:cb)");
      $stmt->execute([':s'=>$section, ':p'=>$personnel, ':h'=>$is_head, ':cb'=>$userId ?: null]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'focal_delete_table1_person') {
      if ($role !== 'focal') throw new Exception('Focal only');
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('Person required');
      $pdo->prepare("DELETE FROM training_tot_personnel WHERE id = :id")->execute([':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'admin_delete_table1_person') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('Person required');
      $pdo->prepare("DELETE FROM training_tot_personnel WHERE id = :id")->execute([':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

// Setup tables
$pdo->exec("
  CREATE TABLE IF NOT EXISTS training_tot_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(80) NOT NULL,
    personnel VARCHAR(120) NOT NULL,
    is_head TINYINT(1) NOT NULL DEFAULT 0,
    y2024 INT DEFAULT NULL,
    y2025 INT DEFAULT NULL,
    y2026 INT DEFAULT NULL,
    y2027 INT DEFAULT NULL,
    y2028 INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_row (section, personnel)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS training_tot_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_no INT DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    training_type VARCHAR(255) DEFAULT NULL,
    participants TEXT DEFAULT NULL,
    date_label VARCHAR(64) DEFAULT NULL,
    year INT DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
// Ensure columns exist and are nullable for older deployments
try {
  $chk = $pdo->query("SHOW COLUMNS FROM training_tot_events LIKE 'training_type'");
  if ($chk->rowCount() === 0) {
    $pdo->exec("ALTER TABLE training_tot_events ADD COLUMN training_type VARCHAR(255) DEFAULT NULL AFTER title");
  } else {
    $pdo->exec("ALTER TABLE training_tot_events MODIFY COLUMN training_type VARCHAR(255) DEFAULT NULL");
  }
  $chk2 = $pdo->query("SHOW COLUMNS FROM training_tot_events LIKE 'serial_no'");
  if ($chk2->rowCount() === 0) {
    $pdo->exec("ALTER TABLE training_tot_events ADD COLUMN serial_no INT DEFAULT NULL AFTER id");
  }
  // Make key columns nullable
  $pdo->exec("ALTER TABLE training_tot_events 
              MODIFY COLUMN title VARCHAR(255) NULL,
              MODIFY COLUMN participants TEXT NULL,
              MODIFY COLUMN date_label VARCHAR(64) NULL");
} catch (Throwable $e) { /* ignore */ }

// Prefill personnel if empty
$count = (int)$pdo->query("SELECT COUNT(*) FROM training_tot_personnel")->fetchColumn();
if ($count === 0) {
  $prefill = [
    ['Chief Health Program','Brendell Fabia',1],
    ['Medical','April A. Basilio',1],
    ['Medical','Bienvida D. Salangad',0],
    ['Medical','Rachelle Sheng Batalla',0],
    ['Medical','Harvae A. Ordoño',0],
    ['Medical','Shannah W. Tolarba',0],
    ['Medical','Jayson Joseph D. Anglo',0],
    ['Medical','Arzelle Gabriele L. Rubio',0],
    ['Nursing','Cristine N. Casasi',1],
    ['Nursing','Sheila Marie G. Sabado-Calip',0],
    ['Nursing','Caroline N. Ardiente',0],
    ['Nursing','Rowelyn Quinine S. Ordoño',0],
    ['Nursing','Paul Josef F. Piniliw',0],
    ['Nursing','Irish Gene A. Mira',0],
    ['Nursing','Kjenn Rainier A. Gascon',0],
    ['Medical Social Welfare','Elsa M. Saturnino',1],
    ['Medical Social Welfare','Marjorie M. Amkinit',0],
    ['Medical Social Welfare','Jolicei C. Sison',0],
    ['Medical Social Welfare','Shakey Jane A. Sannadan',0],
    ['Medical Social Welfare','Shanten S. Luckey-Flores',0],
    ['Medical Social Welfare','Maricar E. Pimentel',0],
    ['Psychological','Hannah Lyn F. Bautista',1],
    ['Psychological','Kervin E. Kindipan',0],
    ['Psychological','Samantha Ashley C. Pulmano',0],
    ['Psychological','Krisine Joy B. Munar',0],
    ['Psychological','Jossa Vivienne S. Torino',0],
    ['Dormitory','Kristan Noel C. Gallardo',1],
    ['Dormitory','Christopher B. Balanga',0],
    ['Dormitory','April Ross D. Lubong',0],
    ['Dormitory','Rozette S. Rebaja',0],
    ['Nutrition','Pergie Honey B. Austria',1],
    ['Nutrition','Ronwald N. Ubungen',0],
    ['Nutrition','Rodolfo C. De La Cruz',0],
    ['Nutrition','Montano S. Laureta',0],
    ['Nutrition','Michael G. Robosa',0],
    ['Nutrition','Florendel B. Corpuz',0],
    ['Clinical Laboratory','Estela G. Jontillano',1],
    ['Clinical Laboratory','Joline L. Hilado',0],
    ['Clinical Laboratory','Zeno Rene C. Maglaya',0]
  ];
  $ins = $pdo->prepare("INSERT IGNORE INTO training_tot_personnel (section, personnel, is_head, created_by) VALUES (:s,:p,:h,:cb)");
  foreach ($prefill as [$s,$p,$h]) {
    $ins->execute([':s'=>$s, ':p'=>$p, ':h'=>$h, ':cb'=>$userId ?: null]);
  }
}

// Fetch rows
$personnelRows = $pdo->query("SELECT * FROM training_tot_personnel ORDER BY section, is_head DESC, personnel")->fetchAll(PDO::FETCH_ASSOC);
$eventsRows = $pdo->query("SELECT * FROM training_tot_events ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedYear = isset($_GET['t2year']) ? (int)$_GET['t2year'] : (int)date('Y');
if ($selectedYear < 2024 || $selectedYear > 2028) $selectedYear = (int)date('Y');
$eventsFiltered = array_filter($eventsRows, function($er) use ($selectedYear) {
  $y = isset($er['year']) ? (int)$er['year'] : null;
  return $y === $selectedYear;
});
$sectionOptions = array_values(array_unique(array_map(function($r){ return $r['section']; }, $personnelRows)));

// Derive per-person, per-year counts from Table 2 participants
$yearsList = [2024,2025,2026,2027,2028];
$countsByPersonYear = [];
foreach ($personnelRows as $pr) { $countsByPersonYear[(int)$pr['id']] = []; }
foreach ($eventsRows as $er) {
  $y = (int)($er['year'] ?? 0);
  if (!in_array($y, $yearsList, true)) continue;
  $participants = strtolower((string)($er['participants'] ?? ''));
  if ($participants === '') continue;
  foreach ($personnelRows as $pr) {
    $name = strtolower((string)$pr['personnel']);
    if ($name !== '' && strpos($participants, $name) !== false) {
      $pid = (int)$pr['id'];
      $countsByPersonYear[$pid][$y] = ($countsByPersonYear[$pid][$y] ?? 0) + 1;
    }
  }
}

// Aggregation for charts
$years = [2024,2025,2026,2027,2028];
$trainingsByYear = [];
foreach ($years as $y) {
  $cnt = 0;
  foreach ($eventsRows as $er) {
    if ((int)$er['year'] === $y) $cnt++;
  }
  $trainingsByYear[] = $cnt;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Governance Scorecard: No. of Certified TOT on Key Intervention Frameworks'; ?>
<?php $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/training_roadmap_certified_tot.css') . '">';
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<div class="container" pt-110>
  <div class="header-wrap">
    <img src="/PGS/img/training_logo.png" alt="Training" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
    <div class="header-title">
      <h4>Governance Scorecard: No. of Certified TOT on key Intervention Frameworks</h4>
      <small class="muted">Means of Verification: Certified Training Records, Certificates, Event Reports</small>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="card chart-card">
        <div class="card-header">No. of Trainings</div>
        <div class="card-body">
          <canvas id="chartB" height="160"></canvas>
          <div id="noDataB" class="text-center text-muted mt-2">No data yet</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <div class="d-flex align-items-center gap-2">
        <span class="section-title flex-grow-1">Table 1. Number of Certified TOT on Key Intervention Frameworks</span>
        <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Collapse">Expand/Minimize</button>
        <?php if ($role === 'admin'): ?>
          <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#t1AddForm">Add row</button>
          <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#adminDeleteT1Modal">Delete</button>
        <?php endif; ?>
        <?php if ($role === 'focal'): ?>
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#focalAddT1Modal">Add row</button>
          <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#focalDeleteT1Modal">Delete</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <?php if ($role === 'admin'): ?>
      <div id="t1AddForm" class="collapse mb-3">
        <form id="formAddT1" class="row g-2">
          <div class="col-12 col-md-4">
            <input type="text" class="form-control form-control-sm" name="section" placeholder="Section">
          </div>
          <div class="col-12 col-md-6">
            <input type="text" class="form-control form-control-sm" name="personnel" placeholder="Personnel">
          </div>
          <div class="col-6 col-md-2 d-flex align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="addHead" name="is_head">
              <label for="addHead" class="form-check-label">Head</label>
            </div>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-sm btn-success btn-pill" type="submit">Save Row</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
      <div class="table-responsive collapse show" id="t1Collapse">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th class="slim">Section</th>
              <th class="slim">Personnel</th>
              <th class="slim">Head</th>
              <th class="slim">2024</th>
              <th class="slim">2025</th>
              <th class="slim">2026</th>
              <th class="slim">2027</th>
              <th class="slim">2028</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($personnelRows as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><?= htmlspecialchars($r['section']) ?></td>
                <td class="name-cell"><?= htmlspecialchars($r['personnel']) ?></td>
                <td class="text-center"><?= $r['is_head'] ? 'Head' : '' ?></td>
                <?php foreach ([2024,2025,2026,2027,2028] as $yy): $pid=(int)$r['id']; $count=$countsByPersonYear[$pid][$yy] ?? 0; ?>
                  <td class="text-center">
                    <?= $count > 0 ? (int)$count : '' ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!empty($autoDetected)): ?>
              <?php foreach ($autoDetected as $nameLower => $info): ?>
                <tr data-id="0">
                  <td><?= htmlspecialchars($info['section']) ?></td>
                  <td class="name-cell"><?= htmlspecialchars($info['personnel']) ?></td>
                  <td class="text-center"></td>
                  <?php foreach ([2024,2025,2026,2027,2028] as $yy): $cnt = $countsByNameYear[$nameLower][$yy] ?? 0; ?>
                    <td class="text-center"><?= $cnt > 0 ? (int)$cnt : '' ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="small-text">Counts are automatically computed from Table 2 training participants by year. Admin/Focal can add rows.</div>
    </div>
  </div>
  <?php if ($role === 'focal'): ?>
  <div class="modal fade" id="focalAddT1Modal" tabindex="-1">
    <div class="modal-dialog">
      <form id="formFocalAddT1" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Row (Table 1)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Section</label>
            <input type="text" class="form-control form-control-sm" name="section" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Personnel</label>
            <input type="text" class="form-control form-control-sm" name="personnel" required>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="focalHead" name="is_head">
            <label for="focalHead" class="form-check-label">Head</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
  <div class="modal fade" id="focalDeleteT1Modal" tabindex="-1">
    <div class="modal-dialog">
      <form id="formFocalDeleteT1" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Row by Name</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <select class="form-select form-select-sm" name="id" required>
              <option value="">Select Name</option>
              <?php foreach ($personnelRows as $opt): ?>
                <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars($opt['personnel']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="text-muted small">This will delete the selected personnel row only.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($role === 'admin'): ?>
  <div class="modal fade" id="adminDeleteT1Modal" tabindex="-1">
    <div class="modal-dialog">
      <form id="formAdminDeleteT1" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Row by Name</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <select class="form-select form-select-sm" name="id" required>
              <option value="">Select Name</option>
              <?php foreach ($personnelRows as $opt): ?>
                <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars($opt['personnel']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">
      <span class="section-title">Table 2. List of Training of Trainers</span>
      <div class="ms-auto d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2">
          <label class="form-label mb-0">Year:</label>
          <select name="t2year" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach ([2024,2025,2026,2027,2028] as $y): ?>
              <option value="<?= $y ?>" <?= $selectedYear===$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    <div class="card-body">
      <?php if (in_array($role, ['admin','focal'], true)): ?>
      <form id="formAdd" class="row g-2 mb-3">
        <div class="col-12 col-lg-2">
          <input type="number" class="form-control form-control-sm" name="serial_no" placeholder="No.">
        </div>
        <div class="col-12 col-lg-4">
          <input type="text" class="form-control form-control-sm" name="training_type" placeholder="Name of training">
        </div>
        <div class="col-12 col-lg-3">
          <input type="text" class="form-control form-control-sm" name="participants" placeholder="Participants">
        </div>
        <div class="col-12 col-lg-2">
          <input type="text" class="form-control form-control-sm" name="date_label" placeholder="Date (e.g., September 2025)">
        </div>
        <div class="col-12 col-lg-1 d-grid">
          <button class="btn btn-sm btn-success btn-pill" type="submit">Add</button>
        </div>
      </form>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th class="slim" style="width:90px;">No.</th>
              <th class="slim" style="width:55%;">Name of training</th>
              <th class="slim">Participants</th>
              <th class="slim" style="width:220px;">Date</th>
              <?php if ($role === 'admin'): ?>
                <th class="slim" style="white-space:nowrap;width:200px;">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="t2Body">
            <?php foreach ($eventsFiltered as $er): ?>
              <tr data-id="<?= (int)$er['id'] ?>">
                <td class="text-center"><?= htmlspecialchars((string)($er['serial_no'] ?? '')) ?></td>
                <td class="wrap-cell name-cell"><?= htmlspecialchars(($er['training_type'] ?: $er['title']) ?? '') ?></td>
                <td class="wrap-cell"><?= htmlspecialchars($er['participants'] ?? '') ?></td>
                <td class="wrap-cell"><?= htmlspecialchars($er['date_label'] ?? '') ?></td>
                <?php if (in_array($role, ['admin','focal'], true)): ?>
                  <td class="text-center">
                    <button class="btn btn-sm btn-primary js-edit"
                      data-id="<?= (int)$er['id'] ?>"
                      data-serial="<?= htmlspecialchars((string)($er['serial_no'] ?? '')) ?>"
                      data-name="<?= htmlspecialchars(($er['training_type'] ?: $er['title']) ?? '') ?>"
                      data-participants="<?= htmlspecialchars($er['participants'] ?? '') ?>"
                      data-date="<?= htmlspecialchars($er['date_label'] ?? '') ?>"
                    >Edit</button>
                    <?php if ($role === 'admin'): ?>
                      <button class="btn btn-sm <?= $er['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-lock" data-locked="<?= $er['locked']?0:1 ?>"><?= $er['locked']? 'Unlock' : 'Lock' ?></button>
                      <button class="btn btn-sm btn-outline-danger js-del">Delete</button>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="small-text">Employee/Focal can add items. Admin can edit, lock, and delete.</div>
    </div>
  </div>
  <?php if ($role === 'admin'): ?>
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Training Row</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="formEdit" class="row g-3">
            <input type="hidden" name="id">
            <div class="col-12 col-lg-2">
              <label class="form-label">No.</label>
              <input type="number" class="form-control form-control-sm" name="serial_no">
            </div>
            <div class="col-12 col-lg-10">
              <label class="form-label">Name of training</label>
              <textarea class="form-control form-control-sm" name="name" rows="3"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Participants</label>
              <textarea class="form-control form-control-sm" name="participants" rows="3"></textarea>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label">Date</label>
              <input type="text" class="form-control form-control-sm" name="date_label" placeholder="e.g., September 2025">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
          <button type="button" id="btnSaveEdit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include PGS_TEMPLATES . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const ROLE = <?= json_encode($role) ?>;
  // Table 1 is static; counts derive from Table 2. No inline edits.

  // Table 2 add
  const formAdd = document.getElementById('formAdd');
  if (formAdd) {
    formAdd.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdd);
      fd.append('action','add_table2');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Table 1 add
  const formAddT1 = document.getElementById('formAddT1');
  if (formAddT1) {
    formAddT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddT1);
      fd.append('action','add_table1');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add row failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Focal add/delete Table 1
  const formFocalAddT1 = document.getElementById('formFocalAddT1');
  if (formFocalAddT1) {
    formFocalAddT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formFocalAddT1);
      fd.append('action','focal_add_table1');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add row failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  const formFocalDeleteT1 = document.getElementById('formFocalDeleteT1');
  if (formFocalDeleteT1) {
    formFocalDeleteT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formFocalDeleteT1);
      fd.append('action','focal_delete_table1_person');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', text:'This will delete the selected personnel row.', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  const formAdminDeleteT1 = document.getElementById('formAdminDeleteT1');
  if (formAdminDeleteT1) {
    formAdminDeleteT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdminDeleteT1);
      fd.append('action','admin_delete_table1_person');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', text:'This will delete the selected personnel row.', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Admin actions for table 2
  document.querySelectorAll('.js-t2').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const payload = new FormData();
      payload.append('action','save_table2');
      payload.append('id', tr.dataset.id);
      payload.append('field', e.currentTarget.dataset.field);
      payload.append('value', e.currentTarget.value);
      const r = await fetch(location.href, {method:'POST', body:payload});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'});
    });
  });
  document.querySelectorAll('.js-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const locked = e.currentTarget.dataset.locked;
      const fd = new FormData();
      fd.append('action','set_lock_table2');
      fd.append('id', tr.dataset.id);
      fd.append('locked', locked);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'});
      else location.reload();
    });
  });
  document.querySelectorAll('.js-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_table2');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      else tr.remove();
    });
  });

  // Edit modal
  const editModalEl = document.getElementById('editModal');
  let editModal;
  if (editModalEl) {
    editModal = new bootstrap.Modal(editModalEl);
    document.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const serial = btn.dataset.serial || '';
        const name = btn.dataset.name || '';
        const participants = btn.dataset.participants || '';
        const date = btn.dataset.date || '';
        const form = document.getElementById('formEdit');
        form.querySelector('[name="id"]').value = id;
        form.querySelector('[name="serial_no"]').value = serial;
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="participants"]').value = participants;
        form.querySelector('[name="date_label"]').value = date;
        editModal.show();
      });
    });
    const btnSaveEdit = document.getElementById('btnSaveEdit');
    if (btnSaveEdit) {
      btnSaveEdit.addEventListener('click', async () => {
        const form = document.getElementById('formEdit');
        const fd = new FormData(form);
        fd.append('action','edit_table2');
        const r = await fetch(location.href, {method:'POST', body:fd});
        const j = await r.json();
        if (!j.ok) {
          Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'});
        } else {
          location.reload();
        }
      });
    }
  }

  // Charts
  const years = <?= json_encode($years) ?>;
  const trainings = <?= json_encode($trainingsByYear) ?>;
  const hasB = trainings.some(v => Number(v) > 0);

  const ctxB = document.getElementById('chartB').getContext('2d');
  new Chart(ctxB, {
    type:'line',
    data:{
      labels: years,
      datasets:[{
        label: 'Trainings',
        data: trainings,
        tension: .3,
        borderColor: '#0b4aa2',
        backgroundColor: 'rgba(11,74,162,.15)',
        pointRadius: 4,
        pointBackgroundColor: '#0b4aa2',
      }]
    },
    options:{
      plugins:{ legend:{ display:false } },
      scales:{ y:{ beginAtZero:true, ticks:{ stepSize: 1 } } }
    }
  });
  if (!hasB) document.getElementById('noDataB').style.display = 'block'; else document.getElementById('noDataB').style.display='none';
</script>
</body>
</html>
