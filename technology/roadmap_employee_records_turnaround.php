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
    if ($action === 'add_row') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $staff_name = trim($_POST['staff_name'] ?? '');
      $request_date = $_POST['request_date'] ?: null;
      $request_time = $_POST['request_time'] ?: null;
      $released_date = $_POST['released_date'] ?: null;
      $released_time = $_POST['released_time'] ?: null;
      $rt_raw = trim($_POST['retrieval_time'] ?? '');
      $retrieval_time = ($rt_raw === '' ? null : (float)$rt_raw);
      $stmt = $pdo->prepare("INSERT INTO employee_records_retrieval
        (staff_name, request_date, request_time, released_date, released_time, retrieval_time, locked, created_by)
        VALUES (:sn,:rqd,:rqt,:rld,:rlt,:rtv,0,:cb)");
      $stmt->execute([
        ':sn'=>$staff_name?:null, ':rqd'=>$request_date, ':rqt'=>$request_time,
        ':rld'=>$released_date, ':rlt'=>$released_time, ':rtv'=>$retrieval_time,
        ':cb'=>$userId ?: null
      ]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added employee record retrieval entry: " . ($staff_name ?: 'N/A');
      notifyAdmins('upload', 'Employee Records Updated', $notifMsg, $newId, 'employee_records');
      notifyFocals('upload', 'Employee Records Updated', $notifMsg, $newId, 'employee_records');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'edit_row') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $payload = [
        'staff_name'=>$_POST['staff_name'] ?? null,
        'request_date'=>$_POST['request_date'] ?: null,
        'request_time'=>$_POST['request_time'] ?: null,
        'released_date'=>$_POST['released_date'] ?: null,
        'released_time'=>$_POST['released_time'] ?: null,
        'retrieval_time'=>($_POST['retrieval_time'] === '' ? null : (float)$_POST['retrieval_time'])
      ];
      $stmt = $pdo->prepare("
        UPDATE employee_records_retrieval
        SET staff_name=:staff_name, request_date=:request_date, request_time=:request_time,
            released_date=:released_date, released_time=:released_time,
            retrieval_time=:retrieval_time
        WHERE id=:id
      ");
      $payload['id']=$id;
      $stmt->execute($payload);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " edited employee record retrieval entry";
      notifyAdmins('edit', 'Employee Records Updated', $notifMsg, $id, 'employee_records');
      notifyFocals('edit', 'Employee Records Updated', $notifMsg, $id, 'employee_records');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'set_lock') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE employee_records_retrieval SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_row') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM employee_records_retrieval WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted employee record retrieval entry";
      notifyAdmins('edit', 'Employee Records Updated', $notifMsg, $id, 'employee_records');
      notifyFocals('edit', 'Employee Records Updated', $notifMsg, $id, 'employee_records');
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

// Setup table (minutes only for retrieval_time)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS employee_records_retrieval (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_name VARCHAR(120) DEFAULT NULL,
    request_date DATE DEFAULT NULL,
    request_time TIME DEFAULT NULL,
    released_date DATE DEFAULT NULL,
    released_time TIME DEFAULT NULL,
    retrieval_time DECIMAL(10,2) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
try { $pdo->exec("ALTER TABLE employee_records_retrieval MODIFY COLUMN retrieval_time DECIMAL(10,2) DEFAULT NULL"); } catch (Throwable $e) {}

// Fetch rows
$rows = $pdo->query("SELECT * FROM employee_records_retrieval ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

function minutes_from_dates(?string $d1, ?string $t1, ?string $d2, ?string $t2): ?float {
  if (!$d1 || !$t1 || !$d2 || !$t2) return null;
  $start = strtotime($d1.' '.$t1);
  $end = strtotime($d2.' '.$t2);
  if ($start === false || $end === false || $end < $start) return null;
  return ($end - $start) / 60.0;
}
$total = 0.0; $count = 0;
foreach ($rows as $r) {
  $mins = $r['retrieval_time'] !== null ? (float)$r['retrieval_time'] : null;
  if ($mins === null) $mins = minutes_from_dates($r['request_date'] ?? null, $r['request_time'] ?? null, $r['released_date'] ?? null, $r['released_time'] ?? null);
  if ($mins !== null) { $total += $mins; $count++; }
}
$avgMinutes = $count > 0 ? round($total / $count, 2) : null;
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Governance Scorecard: Decreased Turnaround Time for Employee Records Retrieval'; ?>
<?php $pageStyles = page_css('css/pages/technology_roadmap_employee_records_turnaround.css');
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body class="d-flex flex-column min-vh-100">
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <main class="container flex-grow-1" pt-110>
    <div class="header-wrap">
      <img src="/PGS/img/patientR_logo.png" alt="Employee Records" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
      <div class="header-title">
        <h4>Governance Scorecard: Decreased Turnaround Time for Employee Records Retrieval</h4>
        <small class="muted">Means of Verification:</small>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Average Retrieval Time</div>
      <div class="card-body text-center">
        <?php if ($avgMinutes === null): ?>
          <div class="text-muted">No data yet</div>
        <?php else: ?>
          <div class="timer"><?= htmlspecialchars(number_format($avgMinutes, 2)) ?> min</div>
        <?php endif; ?>
        <div class="mt-2 target">Target: 1 Day</div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span class="section-title">Table 1. Employee Records Retrieval</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Wrap">Expand/Minimize</button>
        </div>
      </div>
      <div class="card-body">
        <?php if (in_array($role, ['admin','focal'], true)): ?>
        <form id="formAdd" class="row g-2 mb-3">
          <div class="col-12 col-md-3">
            <input type="text" class="form-control form-control-sm" name="staff_name" placeholder="Staff Name">
          </div>
          <div class="col-6 col-md-2">
            <input type="date" class="form-control form-control-sm" name="request_date" placeholder="Request Date">
          </div>
          <div class="col-6 col-md-2">
            <input type="time" class="form-control form-control-sm" name="request_time" step="1" placeholder="Request Time">
          </div>
          <div class="col-6 col-md-2">
            <input type="date" class="form-control form-control-sm" name="released_date" placeholder="Released Date">
          </div>
          <div class="col-6 col-md-2">
            <input type="time" class="form-control form-control-sm" name="released_time" step="1" placeholder="Released Time">
          </div>
          <div class="col-6 col-md-2">
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="retrieval_time" placeholder="Minutes">
          </div>
          <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-success btn-sm">Add Row</button>
          </div>
        </form>
        <?php endif; ?>
        <div class="table-responsive collapse show" id="t1Wrap">
          <table class="table table-bordered align-middle">
            <thead>
              <tr class="text-center">
                <th>Staff Name</th>
                <th>Request Date</th>
                <th>Request Time</th>
                <th>Released Date</th>
                <th>Released Time</th>
                <th>Retrieval Time (min)</th>
                <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="tbody">
              <?php foreach ($rows as $r): ?>
                <tr data-id="<?= (int)$r['id'] ?>">
                  <td><?= htmlspecialchars($r['staff_name'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['request_date'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['request_time'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['released_date'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['released_time'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['retrieval_time'] !== null ? number_format((float)$r['retrieval_time'], 2) : '') ?></td>
                  <?php if ($role === 'admin'): ?>
                  <td class="text-nowrap">
                    <button class="btn btn-sm btn-outline-primary me-1 js-edit">Edit</button>
                    <button class="btn btn-sm <?= $r['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-lock" data-locked="<?= $r['locked']?0:1 ?>"><?= $r['locked']? 'Unlock' : 'Lock' ?></button>
                    <button class="btn btn-sm btn-outline-danger js-del">Delete</button>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= asset('js/pages/technology_roadmap_employee_records_turnaround_1.js') ?>"></script>
</body>
</html>
