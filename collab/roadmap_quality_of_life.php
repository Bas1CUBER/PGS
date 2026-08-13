<?php
require_once __DIR__ . '/../src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
require_page_access('roadmaps');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

$role = $_SESSION['role'] ?? 'employee';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Lock state (admin can toggle)
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS roadmap_quality_life_lock (
      id TINYINT PRIMARY KEY,
      is_locked TINYINT(1) NOT NULL DEFAULT 0,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
  ");
  $exists = $pdo->query("SELECT id FROM roadmap_quality_life_lock WHERE id=1")->fetchColumn();
  if (!$exists) {
    $pdo->prepare("INSERT INTO roadmap_quality_life_lock (id, is_locked) VALUES (1, 0)")->execute();
  }
} catch (Throwable $e) {}

try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS qli_employment_rows (
      id INT AUTO_INCREMENT PRIMARY KEY,
      registry_no VARCHAR(50) NOT NULL,
      name VARCHAR(255) NOT NULL,
      program VARCHAR(100) NOT NULL,
      entry_employment VARCHAR(100) DEFAULT NULL,
      entry_occupation VARCHAR(100) DEFAULT NULL,
      after_employment VARCHAR(100) DEFAULT NULL,
      after_occupation VARCHAR(100) DEFAULT NULL,
      remarks TEXT DEFAULT NULL,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_qli_emp_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS qli_health_rows (
      id INT AUTO_INCREMENT PRIMARY KEY,
      registry_no VARCHAR(50) NOT NULL,
      program VARCHAR(100) NOT NULL,
      overall_during VARCHAR(50) DEFAULT NULL,
      overall_after VARCHAR(50) DEFAULT NULL,
      physical_during VARCHAR(50) DEFAULT NULL,
      physical_after VARCHAR(50) DEFAULT NULL,
      mental_during VARCHAR(50) DEFAULT NULL,
      mental_after VARCHAR(50) DEFAULT NULL,
      social_during VARCHAR(50) DEFAULT NULL,
      social_after VARCHAR(50) DEFAULT NULL,
      environment_during VARCHAR(50) DEFAULT NULL,
      environment_after VARCHAR(50) DEFAULT NULL,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_qli_health_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock' && $role === 'admin') {
  $val = isset($_POST['is_locked']) ? (int)($_POST['is_locked'] ? 1 : 0) : 0;
  try {
    $pdo->prepare("UPDATE roadmap_quality_life_lock SET is_locked=:v WHERE id=1")->execute([':v' => $val]);
    header("Content-Type: application/json");
    echo json_encode(['success' => true, 'is_locked' => $val]);
  } catch (Throwable $e) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false]);
  }
  exit();
}

$postAction = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction) {
  header("Content-Type: application/json");
  try {
    if ($postAction === 'add_employment' && in_array($role, ['employee','focal'], true)) {
      if ($isLocked === 1) { echo json_encode(['success'=>false,'error'=>'Locked by admin']); exit(); }
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number format']); exit(); }
      $stmt = $pdo->prepare("
        INSERT INTO qli_employment_rows
        (registry_no,name,program,entry_employment,entry_occupation,after_employment,after_occupation,remarks,created_by)
        VALUES
        (:registry_no,:name,:program,:entry_employment,:entry_occupation,:after_employment,:after_occupation,:remarks,:created_by)
      ");
      $stmt->execute([
        ':registry_no'=>$reg,
        // Name is confidential; store empty string
        ':name'=>'',
        ':program'=>trim($_POST['program'] ?? ''),
        ':entry_employment'=>trim($_POST['entry_employment'] ?? ''),
        ':entry_occupation'=>trim($_POST['entry_occupation'] ?? ''),
        ':after_employment'=>trim($_POST['after_employment'] ?? ''),
        ':after_occupation'=>trim($_POST['after_occupation'] ?? ''),
        ':remarks'=>trim($_POST['remarks'] ?? ''),
        ':created_by'=>$userId
      ]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added employment record: " . $reg . " in Quality of Life Index";
      notifyAdmins('upload', 'Quality of Life Updated', $notifMsg, $newId, 'quality_of_life');
      notifyFocals('upload', 'Quality of Life Updated', $notifMsg, $newId, 'quality_of_life');
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'add_health' && in_array($role, ['employee','focal'], true)) {
      if ($isLocked === 1) { echo json_encode(['success'=>false,'error'=>'Locked by admin']); exit(); }
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number format']); exit(); }
      $stmt = $pdo->prepare("
        INSERT INTO qli_health_rows
        (registry_no,program,overall_during,overall_after,physical_during,physical_after,mental_during,mental_after,social_during,social_after,environment_during,environment_after,created_by)
        VALUES
        (:registry_no,:program,:overall_during,:overall_after,:physical_during,:physical_after,:mental_during,:mental_after,:social_during,:social_after,:environment_during,:environment_after,:created_by)
      ");
      $stmt->execute([
        ':registry_no'=>$reg,
        ':program'=>trim($_POST['program'] ?? ''),
        ':overall_during'=>trim($_POST['overall_during'] ?? ''),
        ':overall_after'=>trim($_POST['overall_after'] ?? ''),
        ':physical_during'=>trim($_POST['physical_during'] ?? ''),
        ':physical_after'=>trim($_POST['physical_after'] ?? ''),
        ':mental_during'=>trim($_POST['mental_during'] ?? ''),
        ':mental_after'=>trim($_POST['mental_after'] ?? ''),
        ':social_during'=>trim($_POST['social_during'] ?? ''),
        ':social_after'=>trim($_POST['social_after'] ?? ''),
        ':environment_during'=>trim($_POST['environment_during'] ?? ''),
        ':environment_after'=>trim($_POST['environment_after'] ?? ''),
        ':created_by'=>$userId
      ]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added health record: " . $reg . " in Quality of Life Index";
      notifyAdmins('upload', 'Quality of Life Updated', $notifMsg, $newId, 'quality_of_life');
      notifyFocals('upload', 'Quality of Life Updated', $notifMsg, $newId, 'quality_of_life');
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'edit_employment' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number format']); exit(); }
      $stmt = $pdo->prepare("
        UPDATE qli_employment_rows SET
        registry_no=:registry_no,program=:program,
        entry_employment=:entry_employment,entry_occupation=:entry_occupation,
        after_employment=:after_employment,after_occupation=:after_occupation,remarks=:remarks
        WHERE id=:id
      ");
      $stmt->execute([
        ':registry_no'=>$reg,
        ':program'=>trim($_POST['program'] ?? ''),
        ':entry_employment'=>trim($_POST['entry_employment'] ?? ''),
        ':entry_occupation'=>trim($_POST['entry_occupation'] ?? ''),
        ':after_employment'=>trim($_POST['after_employment'] ?? ''),
        ':after_occupation'=>trim($_POST['after_occupation'] ?? ''),
        ':remarks'=>trim($_POST['remarks'] ?? ''),
        ':id'=>$id
      ]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " edited employment record: " . $reg . " in Quality of Life Index";
      notifyAdmins('edit', 'Quality of Life Updated', $notifMsg, $id, 'quality_of_life');
      notifyFocals('edit', 'Quality of Life Updated', $notifMsg, $id, 'quality_of_life');
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'edit_health' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number format']); exit(); }
      $stmt = $pdo->prepare("
        UPDATE qli_health_rows SET
        registry_no=:registry_no,program=:program,
        overall_during=:overall_during,overall_after=:overall_after,
        physical_during=:physical_during,physical_after=:physical_after,
        mental_during=:mental_during,mental_after=:mental_after,
        social_during=:social_during,social_after=:social_after,
        environment_during=:environment_during,environment_after=:environment_after
        WHERE id=:id
      ");
      $stmt->execute([
        ':registry_no'=>$reg,
        ':program'=>trim($_POST['program'] ?? ''),
        ':overall_during'=>trim($_POST['overall_during'] ?? ''),
        ':overall_after'=>trim($_POST['overall_after'] ?? ''),
        ':physical_during'=>trim($_POST['physical_during'] ?? ''),
        ':physical_after'=>trim($_POST['physical_after'] ?? ''),
        ':mental_during'=>trim($_POST['mental_during'] ?? ''),
        ':mental_after'=>trim($_POST['mental_after'] ?? ''),
        ':social_during'=>trim($_POST['social_during'] ?? ''),
        ':social_after'=>trim($_POST['social_after'] ?? ''),
        ':environment_during'=>trim($_POST['environment_during'] ?? ''),
        ':environment_after'=>trim($_POST['environment_after'] ?? ''),
        ':id'=>$id
      ]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'toggle_row_lock' && $role === 'admin') {
      $table = $_POST['table'] ?? '';
      $id = (int)($_POST['id'] ?? 0);
      $val = isset($_POST['row_locked']) ? (int)($_POST['row_locked'] ? 1 : 0) : 0;
      if ($table === 'employment') {
        $pdo->prepare("UPDATE qli_employment_rows SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      } elseif ($table === 'health') {
        $pdo->prepare("UPDATE qli_health_rows SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      } else {
        echo json_encode(['success'=>false]); exit();
      }
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'delete_employment' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM qli_employment_rows WHERE id=:id")->execute([':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($postAction === 'delete_health' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM qli_health_rows WHERE id=:id")->execute([':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    echo json_encode(['success'=>false,'error'=>'Invalid action']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'Server error']);
  }
  exit();
}

$isLocked = 0;
try {
  $isLocked = (int)$pdo->query("SELECT is_locked FROM roadmap_quality_life_lock WHERE id=1")->fetchColumn();
} catch (Throwable $e) {}

$canEdit = true;
if ($role !== 'admin') {
  $canEdit = ($isLocked === 0);
}

$canInput = (in_array($role, ['admin','focal'], true) && $isLocked === 0);
$employmentRows = [];
$healthRows = [];
try {
  $employmentRows = $pdo->query("SELECT * FROM qli_employment_rows ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  $healthRows = $pdo->query("SELECT * FROM qli_health_rows ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$chartYears = [2024,2025,2026,2027,2028,2029];
$chartCounts = array_fill(0, count($chartYears), 0);
foreach ($employmentRows as $r) {
  $reg = $r['registry_no'] ?? '';
  if (preg_match('/^(\d{4})-/', $reg, $m)) {
    $yy = (int)$m[1];
    $idx = array_search($yy, $chartYears, true);
    if ($idx !== false) { $chartCounts[$idx]++; }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Quality of Life Index'; ?>
<?php $pageStyles = page_css('css/pages/collab_roadmap_quality_of_life.css');
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <main class="page-container container">
    <div class="header-wrap">
      <img src="/PGS/img/roadmap1.png" alt="Roadmap" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
      <div class="header-title">
        <h4>Quality of Life Index (Employment Rate, Physical Health)</h4>
        <small>Means of Verification, Hims Registration Forms, Kamustahan</small>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center"></div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h6 class="section-title mb-3">Employment Rate</h6>
        <canvas id="barChart" height="120"></canvas>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="section-title">Table 1. Employment Tracker of Graduated Clients</span>
            <div>
              <button class="btn btn-sm btn-outline-primary glow-btn" data-bs-toggle="collapse" data-bs-target="#table1Wrap">Expand/Minimize</button>
              <?php if ($canInput): ?>
                <button id="addRow1" class="btn btn-sm btn-primary glow-btn">Add Row</button>
              <?php endif; ?>
            </div>
          </div>
          <div id="table1Wrap" class="collapse">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered align-middle">
                  <thead>
                    <tr class="group-header text-center">
                      <th colspan="2">Graduated Patients/Clients</th>
                      <th colspan="2">Upon Entry</th>
                      <th colspan="3">Aftercare</th>
                      <?php if ($role === 'admin'): ?><th>Action</th><?php endif; ?>
                    </tr>
                    <tr class="text-center">
                      <th>Patient's Registry No.</th>
                      <th>Program</th>
                      <th>Employment</th>
                      <th>Occupation</th>
                      <th>Employment</th>
                      <th>Occupation</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody id="tbody1">
                    <?php foreach ($employmentRows as $r): ?>
                      <tr>
                        <td><?= h($r['registry_no'] ?? '') ?></td>
                        <td><?= h($r['program'] ?? '') ?></td>
                        <td><?= h($r['entry_employment'] ?? '') ?></td>
                        <td><?= h($r['entry_occupation'] ?? '') ?></td>
                        <td><?= h($r['after_employment'] ?? '') ?></td>
                        <td><?= h($r['after_occupation'] ?? '') ?></td>
                        <td><?= h($r['remarks'] ?? '') ?></td>
                        <?php if ($role === 'admin'): ?>
                          <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary me-1 edit-emp" data-id="<?= (int)$r['id'] ?>">Edit</button>
                            <button class="btn btn-sm <?= ((int)$r['row_locked']===1)?'btn-danger':'btn-success' ?> lock-emp" data-id="<?= (int)$r['id'] ?>" data-locked="<?= (int)$r['row_locked'] ?>"><?= ((int)$r['row_locked']===1)?'Unlock':'Lock' ?></button>
                            <button class="btn btn-sm btn-outline-danger ms-1 delete-emp" data-id="<?= (int)$r['id'] ?>">Delete</button>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="section-title">Table 2. Health Score of Graduated Clients</span>
            <div>
              <button class="btn btn-sm btn-outline-primary glow-btn" data-bs-toggle="collapse" data-bs-target="#table2Wrap">Expand/Minimize</button>
              <?php if ($canInput): ?>
                <button id="addRow2" class="btn btn-sm btn-primary glow-btn">Add Row</button>
              <?php endif; ?>
            </div>
          </div>
          <div id="table2Wrap" class="collapse">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered align-middle">
                  <thead>
                    <tr class="group-header text-center">
                      <th rowspan="2" colspan="2">Graduated Patients/Clients</th>
                      <th colspan="2">Overall</th>
                      <th colspan="2">Physical Health</th>
                      <th colspan="2">Mental and Emotional</th>
                      <th colspan="2">Social Relationship</th>
                      <th colspan="2">Environment and Lifestyle</th>
                      <?php if ($role === 'admin'): ?><th rowspan="2">Action</th><?php endif; ?>
                    </tr>
                    <tr class="text-center">
                      <th>During</th><th>Aftercare</th>
                      <th>During</th><th>Aftercare</th>
                      <th>During</th><th>Aftercare</th>
                      <th>During</th><th>Aftercare</th>
                      <th>During</th><th>Aftercare</th>
                    </tr>
                    <tr class="text-center">
                      <th>Patients Registry No.</th>
                      <th>Program</th>
                      <th colspan="10"></th>
                    </tr>
                  </thead>
                  <tbody id="tbody2">
                    <?php foreach ($healthRows as $r): ?>
                      <tr>
                        <td><?= h($r['registry_no'] ?? '') ?></td>
                        <td><?= h($r['program'] ?? '') ?></td>
                        <td><?= h($r['overall_during'] ?? '') ?></td>
                        <td><?= h($r['overall_after'] ?? '') ?></td>
                        <td><?= h($r['physical_during'] ?? '') ?></td>
                        <td><?= h($r['physical_after'] ?? '') ?></td>
                        <td><?= h($r['mental_during'] ?? '') ?></td>
                        <td><?= h($r['mental_after'] ?? '') ?></td>
                        <td><?= h($r['social_during'] ?? '') ?></td>
                        <td><?= h($r['social_after'] ?? '') ?></td>
                        <td><?= h($r['environment_during'] ?? '') ?></td>
                        <td><?= h($r['environment_after'] ?? '') ?></td>
                        <?php if ($role === 'admin'): ?>
                          <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary me-1 edit-health" data-id="<?= (int)$r['id'] ?>">Edit</button>
                            <button class="btn btn-sm <?= ((int)$r['row_locked']===1)?'btn-danger':'btn-success' ?> lock-health" data-id="<?= (int)$r['id'] ?>" data-locked="<?= (int)$r['row_locked'] ?>"><?= ((int)$r['row_locked']===1)?'Unlock':'Lock' ?></button>
                            <button class="btn btn-sm btn-outline-danger ms-1 delete-health" data-id="<?= (int)$r['id'] ?>">Delete</button>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form id="empForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Employment Tracker</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="emp_id">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Registry No.</label><input type="text" class="form-control" name="registry_no" id="emp_registry"></div>
            <div class="col-md-4"><label class="form-label">Program</label><input type="text" class="form-control" name="program" id="emp_program"></div>
            <div class="col-md-4"><label class="form-label">Entry Employment</label><input type="text" class="form-control" name="entry_employment" id="emp_entry_emp"></div>
            <div class="col-md-4"><label class="form-label">Entry Occupation</label><input type="text" class="form-control" name="entry_occupation" id="emp_entry_occ"></div>
            <div class="col-md-4"><label class="form-label">Aftercare Employment</label><input type="text" class="form-control" name="after_employment" id="emp_after_emp"></div>
            <div class="col-md-4"><label class="form-label">Aftercare Occupation</label><input type="text" class="form-control" name="after_occupation" id="emp_after_occ"></div>
            <div class="col-md-8"><label class="form-label">Remarks</label><input type="text" class="form-control" name="remarks" id="emp_remarks"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
  <div class="modal fade" id="healthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form id="healthForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Health Score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="health_id">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Registry No.</label><input type="text" class="form-control" name="registry_no" id="health_registry"></div>
            <div class="col-md-4"><label class="form-label">Program</label><input type="text" class="form-control" name="program" id="health_program"></div>
            <div class="col-md-4"></div>
            <div class="col-md-4"><label class="form-label">Overall During</label><input type="text" class="form-control" name="overall_during" id="overall_during"></div>
            <div class="col-md-4"><label class="form-label">Overall Aftercare</label><input type="text" class="form-control" name="overall_after" id="overall_after"></div>
            <div class="col-md-4"></div>
            <div class="col-md-4"><label class="form-label">Physical During</label><input type="text" class="form-control" name="physical_during" id="physical_during"></div>
            <div class="col-md-4"><label class="form-label">Physical Aftercare</label><input type="text" class="form-control" name="physical_after" id="physical_after"></div>
            <div class="col-md-4"></div>
            <div class="col-md-4"><label class="form-label">Mental During</label><input type="text" class="form-control" name="mental_during" id="mental_during"></div>
            <div class="col-md-4"><label class="form-label">Mental Aftercare</label><input type="text" class="form-control" name="mental_after" id="mental_after"></div>
            <div class="col-md-4"></div>
            <div class="col-md-4"><label class="form-label">Social During</label><input type="text" class="form-control" name="social_during" id="social_during"></div>
            <div class="col-md-4"><label class="form-label">Social Aftercare</label><input type="text" class="form-control" name="social_after" id="social_after"></div>
            <div class="col-md-4"></div>
            <div class="col-md-4"><label class="form-label">Environment During</label><input type="text" class="form-control" name="environment_during" id="environment_during"></div>
            <div class="col-md-4"><label class="form-label">Environment Aftercare</label><input type="text" class="form-control" name="environment_after" id="environment_after"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php $pgsPage = ['canEdit' => $canEdit, 'canInput' => $canInput, 'chartYears' => $chartYears, 'chartCounts' => $chartCounts, 'isLocked' => $isLocked]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/collab_roadmap_quality_of_life_1.js') ?>"></script>
</body>
</html>
