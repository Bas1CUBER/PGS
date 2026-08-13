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
      if ($role !== 'focal') throw new Exception('Focal only');
      $registry_no = trim($_POST['registry_no'] ?? '');
      $request_date = $_POST['request_date'] ?: null;
      $request_time = $_POST['request_time'] ?: null;
      $released_date = $_POST['released_date'] ?: null;
      $released_time = $_POST['released_time'] ?: null;
      $returned_date = $_POST['returned_date'] ?: null;
      $returned_time = $_POST['returned_time'] ?: null;
      $retrieval_time = $_POST['retrieval_time'] ?: null;
      $stmt = $pdo->prepare("INSERT INTO patient_records_retrieval
        (registry_no, request_date, request_time, released_date, released_time, returned_date, returned_time, retrieval_time, locked, created_by)
        VALUES (:rn,:rqd,:rqt,:rld,:rlt,:rtd,:rtt,:rtv,0,:cb)");
      $stmt->execute([
        ':rn'=>$registry_no?:null, ':rqd'=>$request_date, ':rqt'=>$request_time,
        ':rld'=>$released_date, ':rlt'=>$released_time, ':rtd'=>$returned_date, ':rtt'=>$returned_time,
        ':rtv'=>$retrieval_time, ':cb'=>$userId ?: null
      ]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Focal " . $userIdent . " added patient record retrieval entry: " . ($registry_no ?: 'N/A');
      notifyAdmins('upload', 'Patient Records Updated', $notifMsg, $newId, 'patient_records');
      notifyFocals('upload', 'Patient Records Updated', $notifMsg, $newId, 'patient_records');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'edit_row') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $id = (int)($_POST['id'] ?? 0);
      $payload = [
        'registry_no'=>$_POST['registry_no'] ?? null,
        'request_date'=>$_POST['request_date'] ?: null,
        'request_time'=>$_POST['request_time'] ?: null,
        'released_date'=>$_POST['released_date'] ?: null,
        'released_time'=>$_POST['released_time'] ?: null,
        'returned_date'=>$_POST['returned_date'] ?: null,
        'returned_time'=>$_POST['returned_time'] ?: null,
        'retrieval_time'=>$_POST['retrieval_time'] ?: null
      ];
      $stmt = $pdo->prepare("
        UPDATE patient_records_retrieval
        SET registry_no=:registry_no, request_date=:request_date, request_time=:request_time,
            released_date=:released_date, released_time=:released_time,
            returned_date=:returned_date, returned_time=:returned_time,
            retrieval_time=:retrieval_time
        WHERE id=:id
      ");
      $payload['id']=$id;
      $stmt->execute($payload);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " edited patient record retrieval entry";
      notifyAdmins('edit', 'Patient Records Updated', $notifMsg, $id, 'patient_records');
      notifyFocals('edit', 'Patient Records Updated', $notifMsg, $id, 'patient_records');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'set_lock') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE patient_records_retrieval SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_row') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM patient_records_retrieval WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted patient record retrieval entry";
      notifyAdmins('edit', 'Patient Records Updated', $notifMsg, $id, 'patient_records');
      notifyFocals('edit', 'Patient Records Updated', $notifMsg, $id, 'patient_records');
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

// Setup table
$pdo->exec("
  CREATE TABLE IF NOT EXISTS patient_records_retrieval (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registry_no VARCHAR(32) DEFAULT NULL,
    request_date DATE DEFAULT NULL,
    request_time TIME DEFAULT NULL,
    released_date DATE DEFAULT NULL,
    released_time TIME DEFAULT NULL,
    returned_date DATE DEFAULT NULL,
    returned_time TIME DEFAULT NULL,
    retrieval_time VARCHAR(64) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
try { $pdo->exec("ALTER TABLE patient_records_retrieval MODIFY COLUMN retrieval_time VARCHAR(64) DEFAULT NULL"); } catch (Throwable $e) {}

// Fetch rows
$rows = $pdo->query("SELECT * FROM patient_records_retrieval ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedYear = isset($_GET['tyear']) ? (int)$_GET['tyear'] : (int)date('Y');
if ($selectedYear < 2024 || $selectedYear > 2028) $selectedYear = (int)date('Y');
$rowsFiltered = array_filter($rows, function($r) use ($selectedYear) {
  $dForYear = $r['returned_date'] ?: ($r['request_date'] ?: null);
  if (!$dForYear) return false;
  $y = (int)date('Y', strtotime($dForYear));
  return $y === $selectedYear;
});

// Compute average retrieval time in minutes
function parse_minutes(?string $v): ?float {
  if ($v === null) return null;
  $s = trim(strtolower($v));
  if ($s === '') return null;
  if (preg_match('/^(\\d+(?:\\.\\d+)?)\\s*(m|min|minutes)?$/', $s, $m)) {
    return (float)$m[1];
  }
  if (preg_match('/^(\\d+(?:\\.\\d+)?)\\s*(h|hr|hrs|hour|hours)$/', $s, $m)) {
    return (float)$m[1]*60.0;
  }
  if (preg_match('/^(\\d+):(\\d+)(?::(\\d+))?$/', $s, $m)) {
    $h = (int)$m[1]; $mm = (int)$m[2]; $ss = isset($m[3]) ? (int)$m[3] : 0;
    return ($h*3600 + $mm*60 + $ss) / 60.0;
  }
  return null;
}
function minutes_from_dates(?string $d1, ?string $t1, ?string $d2, ?string $t2): ?float {
  if (!$d1 || !$t1 || !$d2 || !$t2) return null;
  $start = strtotime($d1.' '.$t1);
  $end = strtotime($d2.' '.$t2);
  if ($start === false || $end === false || $end < $start) return null;
  return ($end - $start) / 60.0;
}
$total = 0.0; $count = 0;
foreach ($rows as $r) {
  $mins = parse_minutes($r['retrieval_time'] ?? null);
  if ($mins === null) $mins = minutes_from_dates($r['request_date'] ?? null, $r['request_time'] ?? null, $r['returned_date'] ?? null, $r['returned_time'] ?? null);
  if ($mins !== null) { $total += $mins; $count++; }
}
$avgMinutes = $count > 0 ? round($total / $count, 2) : null;

// Yearly averages for 2024-2028
$years = [2024, 2025, 2026, 2027, 2028];
$yearTotals = array_fill_keys($years, 0.0);
$yearCounts = array_fill_keys($years, 0);
foreach ($rows as $r) {
  $mins = parse_minutes($r['retrieval_time'] ?? null);
  if ($mins === null) $mins = minutes_from_dates($r['request_date'] ?? null, $r['request_time'] ?? null, $r['returned_date'] ?? null, $r['returned_time'] ?? null);
  // Determine the year from returned_date (preferred) or request_date
  $dForYear = $r['returned_date'] ?: ($r['request_date'] ?: null);
  $y = $dForYear ? (int)date('Y', strtotime($dForYear)) : null;
  if ($mins !== null && $y !== null && in_array($y, $years, true)) {
    $yearTotals[$y] += $mins;
    $yearCounts[$y] += 1;
  }
}
$avgByYear = [];
foreach ($years as $y) {
  $avgByYear[$y] = $yearCounts[$y] > 0 ? round($yearTotals[$y] / $yearCounts[$y], 2) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Governance Scorecard: Decreased Turnaround Time for Patient Records Retrieval'; ?>
<?php $pageStyles = page_css('css/pages/technology_roadmap_patient_records_turnaround.css');
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<main class="container flex-grow-1" pt-110>
  <div class="header-wrap">
    <img src="/PGS/img/patientR_logo.png" alt="Patient Records" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
    <div class="header-title">
      <h4>Governance Scorecard: Decreased Turnaround Time for Patient Records Retrieval</h4>
      <small class="muted">Means of Verification: Borrower's Logbook PWUDs</small>
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
      <div class="mt-2 target">Target: 15 Days</div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Yearly Average Retrieval Time (2024–2028)</div>
    <div class="card-body">
      <div class="row text-center g-2">
        <?php foreach ([2024, 2025, 2026, 2027, 2028] as $y): ?>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="fw-bold"><?= (int)$y ?></div>
            <?php if ($avgByYear[$y] === null): ?>
              <div class="text-muted">No data</div>
            <?php else: ?>
              <div class="timer-sm"><?= htmlspecialchars(number_format($avgByYear[$y], 2)) ?> min</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="section-title">Table 1. Patient Records Retrieval</span>
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Wrap">Expand/Minimize</button>
        <form method="get" class="d-flex align-items-center gap-2">
          <label class="form-label mb-0">Year:</label>
          <select name="tyear" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach ([2024,2025,2026,2027,2028] as $y): ?>
              <option value="<?= $y ?>" <?= $selectedYear===$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    <div class="card-body">
      <?php if ($role === 'focal'): ?>
      <form id="formAdd" class="row g-2 mb-3">
        <div class="col-12 col-md-3">
          <input type="text" class="form-control form-control-sm" name="registry_no" placeholder="Patient's Registry No.">
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
          <input type="date" class="form-control form-control-sm" name="returned_date" placeholder="Returned Date">
        </div>
        <div class="col-6 col-md-2">
          <input type="time" class="form-control form-control-sm" name="returned_time" step="1" placeholder="Returned Time">
        </div>
        <div class="col-6 col-md-2">
          <input type="text" class="form-control form-control-sm" name="retrieval_time" placeholder="Retrieval Time (minutes)">
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
              <th>Patient's Registry No.</th>
              <th>Request Date</th>
              <th>Request Time</th>
              <th>Released Date</th>
              <th>Released Time</th>
              <th>Returned Date</th>
              <th>Returned Time</th>
              <th>Retrieval Time</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody id="tbody">
            <?php foreach ($rowsFiltered as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><?= htmlspecialchars($r['registry_no'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['request_date'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['request_time'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['released_date'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['released_time'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['returned_date'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['returned_time'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['retrieval_time'] ?? '') ?></td>
                <?php if (in_array($role, ['admin','focal'], true)): ?>
                <td class="text-nowrap">
                  <button class="btn btn-sm btn-outline-primary me-1 js-edit">Edit</button>
                  <?php if ($role === 'admin'): ?>
                    <button class="btn btn-sm <?= $r['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-lock" data-locked="<?= $r['locked']?0:1 ?>"><?= $r['locked']? 'Unlock' : 'Lock' ?></button>
                    <button class="btn btn-sm btn-outline-danger js-del">Delete</button>
                  <?php endif; ?>
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
<script src="<?= asset('js/pages/technology_roadmap_patient_records_turnaround_1.js') ?>"></script>
</body>
</html>
