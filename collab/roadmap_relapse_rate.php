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
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rr_summary_yearly (
      id INT AUTO_INCREMENT PRIMARY KEY,
      year INT NOT NULL,
      grads_opd INT NOT NULL DEFAULT 0,
      grads_res INT NOT NULL DEFAULT 0,
      grads_after INT NOT NULL DEFAULT 0,
      relapse_opd INT NOT NULL DEFAULT 0,
      relapse_res INT NOT NULL DEFAULT 0,
      relapse_after INT NOT NULL DEFAULT 0,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      is_deleted TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_rr_summary_year (year),
      CONSTRAINT fk_rrsy_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rr_graduates (
      id INT AUTO_INCREMENT PRIMARY KEY,
      program VARCHAR(100) NOT NULL,
      grads INT NOT NULL DEFAULT 0,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_rrg_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rr_relapse_list (
      id INT AUTO_INCREMENT PRIMARY KEY,
      registry_no VARCHAR(50) NOT NULL,
      program VARCHAR(100) NOT NULL,
      date_positive DATE DEFAULT NULL,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_rrl_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rr_relapse_rate (
      id INT AUTO_INCREMENT PRIMARY KEY,
      program VARCHAR(100) NOT NULL,
      relapse_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_rrr_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}
$action = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
  header("Content-Type: application/json");
  try {
    if ($action === 'save_summary_row' && in_array($role, ['admin','focal'], true)) {
      $year = (int)($_POST['year'] ?? 0);
      $grads_opd = max(0, (int)($_POST['grads_opd'] ?? 0));
      $grads_res = max(0, (int)($_POST['grads_res'] ?? 0));
      $grads_after = max(0, (int)($_POST['grads_after'] ?? 0));
      // relapse_* are now calculated dynamically, so we don't update them here.

      if ($year < 2000 || $year > 2100) {
        echo json_encode(['success'=>false,'error'=>'Invalid input']); exit();
      }

      // First, check if row exists (even if deleted or locked)
      $check = $pdo->prepare("SELECT id, row_locked, is_deleted FROM rr_summary_yearly WHERE year = :y");
      $check->execute([':y'=>$year]);
      $row = $check->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        // Insert new row
        $stmt = $pdo->prepare("INSERT INTO rr_summary_yearly (year, grads_opd, grads_res, grads_after, created_by) VALUES (:y, :go, :gr, :ga, :uid)");
        $stmt->execute([
          ':y'=>$year,
          ':go'=>$grads_opd, ':gr'=>$grads_res, ':ga'=>$grads_after,
          ':uid'=>$userId
        ]);
        $newId = (int)$pdo->lastInsertId();
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " added relapse rate summary for year " . $year;
        notifyAdmins('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
        notifyFocals('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      } else {
        // Row exists
        // Allow overwriting if row is deleted OR if user is admin
        $is_locked = (int)($row['row_locked'] ?? 0) === 1;
        $is_deleted = (int)($row['is_deleted'] ?? 0) === 1;
        
        if ($is_locked && !$is_deleted && $role !== 'admin') {
          echo json_encode(['success'=>false, 'error'=>'Row is locked']); exit();
        }
        
        // Update values and ensure is_deleted=0 (undelete if needed)
        // If we are un-deleting a locked row, we might want to unlock it? 
        // Or keep it locked but allow THIS edit?
        // Let's just update values.
        $stmt = $pdo->prepare("UPDATE rr_summary_yearly SET 
          grads_opd=:go, grads_res=:gr, grads_after=:ga,
          is_deleted=0
          WHERE id=:id");
        $stmt->execute([
          ':go'=>$grads_opd, ':gr'=>$grads_res, ':ga'=>$grads_after,
          ':id'=>$row['id']
        ]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated relapse rate summary for year " . $year;
        notifyAdmins('edit', 'Relapse Rate Updated', $notifMsg, $row['id'], 'relapse_rate');
        notifyFocals('edit', 'Relapse Rate Updated', $notifMsg, $row['id'], 'relapse_rate');
      }
      
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'lock_summary' && $role === 'admin') {
      $year = (int)($_POST['year'] ?? 0);
      $locked = (int)($_POST['row_locked'] ?? 0) ? 1 : 0;
      
      // Check if row exists (even deleted)
      $chk = $pdo->prepare("SELECT id FROM rr_summary_yearly WHERE year = :y");
      $chk->execute([':y'=>$year]);
      $id = $chk->fetchColumn();

      if ($id) {
        // Update existing row - also un-delete it so it becomes visible/locked
        $pdo->prepare("UPDATE rr_summary_yearly SET row_locked = :l, is_deleted = 0 WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      } else {
        // Insert new locked row
        $pdo->prepare("INSERT INTO rr_summary_yearly (year, row_locked, created_by) VALUES (:y, :l, :uid)")->execute([':y'=>$year, ':l'=>$locked, ':uid'=>$userId]);
      }
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'delete_summary' && $role === 'admin') {
      $year = (int)($_POST['year'] ?? 0);
      $pdo->prepare("UPDATE rr_summary_yearly SET is_deleted = 1 WHERE year = :y")->execute([':y'=>$year]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'add_grad' && in_array($role, ['admin','focal'], true)) {
      $stmt = $pdo->prepare("INSERT INTO rr_graduates (program,grads,created_by) VALUES (:program,:grads,:uid)");
      $stmt->execute([':program'=>trim($_POST['program'] ?? ''), ':grads'=>(int)($_POST['grads'] ?? 0), ':uid'=>$userId]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added graduate program: " . trim($_POST['program'] ?? '');
      notifyAdmins('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      notifyFocals('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'add_list' && in_array($role, ['admin','focal'], true)) {
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number']); exit(); }
      $date = trim($_POST['date_positive'] ?? '');
      $dateVal = $date ? date('Y-m-d', strtotime($date)) : null;
      $stmt = $pdo->prepare("INSERT INTO rr_relapse_list (registry_no,program,date_positive,created_by) VALUES (:reg,:program,:datep,:uid)");
      $stmt->execute([':reg'=>$reg, ':program'=>trim($_POST['program'] ?? ''), ':datep'=>$dateVal, ':uid'=>$userId]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added relapse case: " . $reg;
      notifyAdmins('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      notifyFocals('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'add_rate' && in_array($role, ['admin','focal'], true)) {
      $rate = (float)($_POST['relapse_rate'] ?? 0);
      if ($rate < 0 || $rate > 100) { echo json_encode(['success'=>false,'error'=>'Invalid rate']); exit(); }
      $stmt = $pdo->prepare("INSERT INTO rr_relapse_rate (program,relapse_rate,created_by) VALUES (:program,:rate,:uid)");
      $stmt->execute([':program'=>trim($_POST['program'] ?? ''), ':rate'=>$rate, ':uid'=>$userId]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added relapse rate: " . trim($_POST['program'] ?? '');
      notifyAdmins('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      notifyFocals('upload', 'Relapse Rate Updated', $notifMsg, $newId, 'relapse_rate');
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'edit_grad' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $pdo->prepare("UPDATE rr_graduates SET program=:program, grads=:grads WHERE id=:id");
      $stmt->execute([':program'=>trim($_POST['program'] ?? ''), ':grads'=>(int)($_POST['grads'] ?? 0), ':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'edit_list' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $reg = trim($_POST['registry_no'] ?? '');
      if (!preg_match('/^\d{4}-\d{4}$/', $reg)) { echo json_encode(['success'=>false,'error'=>'Invalid registry number']); exit(); }
      $date = trim($_POST['date_positive'] ?? '');
      $dateVal = $date ? date('Y-m-d', strtotime($date)) : null;
      $stmt = $pdo->prepare("UPDATE rr_relapse_list SET registry_no=:reg, program=:program, date_positive=:datep WHERE id=:id");
      $stmt->execute([':reg'=>$reg, ':program'=>trim($_POST['program'] ?? ''), ':datep'=>$dateVal, ':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'edit_rate' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $rate = (float)($_POST['relapse_rate'] ?? 0);
      if ($rate < 0 || $rate > 100) { echo json_encode(['success'=>false,'error'=>'Invalid rate']); exit(); }
      $stmt = $pdo->prepare("UPDATE rr_relapse_rate SET program=:program, relapse_rate=:rate WHERE id=:id");
      $stmt->execute([':program'=>trim($_POST['program'] ?? ''), ':rate'=>$rate, ':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'toggle_lock' && $role === 'admin') {
      $table = $_POST['table'] ?? '';
      $id = (int)($_POST['id'] ?? 0);
      $val = isset($_POST['row_locked']) ? (int)($_POST['row_locked'] ? 1 : 0) : 0;
      if ($table === 'grad') {
        $pdo->prepare("UPDATE rr_graduates SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      } elseif ($table === 'list') {
        $pdo->prepare("UPDATE rr_relapse_list SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      } elseif ($table === 'rate') {
        $pdo->prepare("UPDATE rr_relapse_rate SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      } else {
        echo json_encode(['success'=>false]); exit();
      }
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'delete_row' && $role === 'admin') {
      $table = $_POST['table'] ?? '';
      $id = (int)($_POST['id'] ?? 0);
      if ($table === 'grad') {
        $pdo->prepare("DELETE FROM rr_graduates WHERE id=:id")->execute([':id'=>$id]);
      } elseif ($table === 'list') {
        $pdo->prepare("DELETE FROM rr_relapse_list WHERE id=:id")->execute([':id'=>$id]);
      } elseif ($table === 'rate') {
        $pdo->prepare("DELETE FROM rr_relapse_rate WHERE id=:id")->execute([':id'=>$id]);
      } else {
        echo json_encode(['success'=>false]); exit();
      }
      echo json_encode(['success'=>true]); exit();
    }
    echo json_encode(['success'=>false,'error'=>'Invalid action']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'Server error']);
  }
  exit();
}
$canInput = in_array($role, ['admin','focal'], true);
$summaryYears = [2024,2025,2026,2027,2028];
$summaryRows = [];
$grads = [];
$list = [];
$rates = [];
try {
  $ins = $pdo->prepare("INSERT IGNORE INTO rr_summary_yearly (year, created_by) VALUES (:y, :uid)");
  foreach ($summaryYears as $y) { 
    $ins->execute([':y'=>$y, ':uid'=>$userId]); 
  }
  // Force a re-fetch of fresh data to ensure we see the latest updates
  $summaryRows = $pdo->query("SELECT * FROM rr_summary_yearly WHERE is_deleted = 0 ORDER BY year ASC")->fetchAll(PDO::FETCH_ASSOC);
  $grads = $pdo->query("SELECT * FROM rr_graduates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  $list = $pdo->query("SELECT * FROM rr_relapse_list ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  $rates = $pdo->query("SELECT * FROM rr_relapse_rate ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
function findSummaryRow($rows, $year) {
  foreach ($rows as $r) { if ((int)($r['year'] ?? 0) === (int)$year) return $r; }
  return null;
}
function findRate($rates, $program) {
  foreach ($rates as $r) {
    if (strcasecmp(trim($r['program']), $program) === 0) return (float)$r['relapse_rate'];
  }
  return 0.0;
}
$rateResidential = findRate($rates, 'Residential Program');
$rateOPD = findRate($rates, 'OPD Program');
$rateAftercare = findRate($rates, 'Aftercare Program');
$sumGrads = 0;
foreach ($grads as $g) { $sumGrads += (int)($g['grads'] ?? 0); }
$now = new DateTime('now');
$prevMonthStart = (clone $now)->modify('first day of last month')->setTime(0,0,0);
$prevMonthEnd = (clone $prevMonthStart)->modify('last day of this month')->setTime(23,59,59);
$prevPrevMonthStart = (clone $now)->modify('first day of -2 month')->setTime(0,0,0);
$prevPrevMonthEnd = (clone $prevPrevMonthStart)->modify('last day of this month')->setTime(23,59,59);
$currYear = (int)$now->format('Y');
$countPrevMonth = 0; $countPrevPrevMonth = 0; $countYear = 0;
foreach ($list as $r) {
  $d = $r['date_positive'] ?? null;
  if (!$d) continue;
  $ts = strtotime($d);
  if ($ts >= $prevMonthStart->getTimestamp() && $ts <= $prevMonthEnd->getTimestamp()) $countPrevMonth++;
  if ($ts >= $prevPrevMonthStart->getTimestamp() && $ts <= $prevPrevMonthEnd->getTimestamp()) $countPrevPrevMonth++;
  if ((int)date('Y', $ts) === $currYear) $countYear++;
}
$ratePrevMonth = $sumGrads>0 ? round(($countPrevMonth / $sumGrads)*100, 2) : 0.0;
$ratePrevPrevMonth = $sumGrads>0 ? round(($countPrevPrevMonth / $sumGrads)*100, 2) : 0.0;
$rateYear = $sumGrads>0 ? round(($countYear / $sumGrads)*100, 2) : 0.0;

// Pre-calculate relapse counts from $list
$relapseCounts = [];
foreach ($list as $r) {
  $d = $r['date_positive'] ?? null;
  $prog = trim($r['program'] ?? '');
  if (!$d) continue;
  $y = (int)date('Y', strtotime($d));
  if (!isset($relapseCounts[$y])) {
    $relapseCounts[$y] = ['opd'=>0, 'res'=>0, 'aft'=>0];
  }
  if (strcasecmp($prog, 'OPD Program') === 0) $relapseCounts[$y]['opd']++;
  elseif (strcasecmp($prog, 'Residential Program') === 0) $relapseCounts[$y]['res']++;
  elseif (strcasecmp($prog, 'Aftercare Program') === 0) $relapseCounts[$y]['aft']++;
}

$annualRates = [];
foreach ($summaryYears as $y) {
  $row = findSummaryRow($summaryRows, $y) ?: ['grads_opd'=>0,'grads_res'=>0,'grads_after'=>0];
  $gOpd = (int)($row['grads_opd'] ?? 0);
  $gRes = (int)($row['grads_res'] ?? 0);
  $gAft = (int)($row['grads_after'] ?? 0);
  $gTotal = $gOpd + $gRes + $gAft;

  $rOpd = (int)($relapseCounts[$y]['opd'] ?? 0);
  $rRes = (int)($relapseCounts[$y]['res'] ?? 0);
  $rAft = (int)($relapseCounts[$y]['aft'] ?? 0);
  $rTotal = $rOpd + $rRes + $rAft;

  $annualRates[$y] = $gTotal > 0 ? round(($rTotal / $gTotal) * 100, 2) : 0.0;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'GOVERNANCE SCORECARD: <5 RELAPSE RATE'; ?>
<?php $pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/collab_roadmap_relapse_rate.css') . '">';
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <main class="page-container container">
    <div class="header-wrap">
      <img src="/PGS/img/roadmap1.png" alt="Roadmap" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
      <div class="header-title">
        <h4>GOVERNANCE SCORECARD: &lt;5 RELAPSE RATE</h4>
        <small>Means of Verification</small>
        <small>Daily Census Form 3 - OPD, ACP and ABI</small>
        <small>Daily Census Form 6 - Laboratory Section</small>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center"></div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="section-title">Dashboard: Annual Overview</span>
      </div>
      <div class="card-body">
        <div class="row justify-content-center text-center">
          <div class="col-6 col-md-2"><div class="fw-bold">2024</div><canvas id="donutY2024" height="120"></canvas></div>
          <div class="col-6 col-md-2"><div class="fw-bold">2025</div><canvas id="donutY2025" height="120"></canvas></div>
          <div class="col-6 col-md-2"><div class="fw-bold">2026</div><canvas id="donutY2026" height="120"></canvas></div>
          <div class="col-6 col-md-2"><div class="fw-bold">2027</div><canvas id="donutY2027" height="120"></canvas></div>
          <div class="col-6 col-md-2"><div class="fw-bold">2028</div><canvas id="donutY2028" height="120"></canvas></div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="section-title">Table 1. Number of Graduates, Relapse, and Relapse Rate</span>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead>
                  <tr class="text-center group-header">
                    <th rowspan="2">Year</th>
                    <th colspan="4">Number of Graduates</th>
                    <th colspan="4">Relapse</th>
                    <th colspan="1">Relapse Rate</th>
                    <?php if ($role === 'admin'): ?><th rowspan="2">Action</th><?php endif; ?>
                  </tr>
                  <tr class="text-center group-header">
                    <th>OPD Program</th>
                    <th>Residential Program</th>
                    <th>Aftercare Program</th>
                    <th>Total</th>
                    <th>OPD Program</th>
                    <th>Residential Program</th>
                    <th>Aftercare Program</th>
                    <th>Total</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($summaryYears as $y): ?>
                    <?php $row = findSummaryRow($summaryRows, $y) ?: ['year'=>$y,'grads_opd'=>0,'grads_res'=>0,'grads_after'=>0,'relapse_opd'=>0,'relapse_res'=>0,'relapse_after'=>0,'row_locked'=>0]; ?>
                    <?php
                      $gOpd = (int)($row['grads_opd'] ?? 0);
                      $gRes = (int)($row['grads_res'] ?? 0);
                      $gAft = (int)($row['grads_after'] ?? 0);
                      
                      // Calculate relapse from Table 2 ($relapseCounts)
                      $rOpd = (int)($relapseCounts[$y]['opd'] ?? 0);
                      $rRes = (int)($relapseCounts[$y]['res'] ?? 0);
                      $rAft = (int)($relapseCounts[$y]['aft'] ?? 0);

                      $gTotal = $gOpd + $gRes + $gAft;
                      $rTotal = $rOpd + $rRes + $rAft;
                      $rateTotal2 = $gTotal > 0 ? round(($rTotal / $gTotal) * 100, 2) : null;
                      $locked = (int)($row['row_locked'] ?? 0) === 1;
                    ?>
                    <tr class="<?= $locked ? 'table-secondary' : '' ?>">
                      <td class="text-center fw-semibold"><?= (int)$y ?></td>
                      <td class="text-center"><input type="number" min="0" class="form-control form-control-sm js-sum-inp" data-year="<?= $y ?>" data-field="grads_opd" value="<?= $gOpd ?>" disabled></td>
                      <td class="text-center"><input type="number" min="0" class="form-control form-control-sm js-sum-inp" data-year="<?= $y ?>" data-field="grads_res" value="<?= $gRes ?>" disabled></td>
                      <td class="text-center"><input type="number" min="0" class="form-control form-control-sm js-sum-inp" data-year="<?= $y ?>" data-field="grads_after" value="<?= $gAft ?>" disabled></td>
                      <td class="text-center fw-semibold"><?= $gTotal ?></td>
                      <td class="text-center fw-semibold"><?= $rOpd ?></td>
                      <td class="text-center fw-semibold"><?= $rRes ?></td>
                      <td class="text-center fw-semibold"><?= $rAft ?></td>
                      <td class="text-center fw-semibold"><?= $rTotal ?></td>
                      <td class="text-center fw-semibold"><?= $rateTotal2 !== null ? number_format((float)$rateTotal2, 2) . '%' : '' ?></td>
                      <?php if (in_array($role, ['admin','focal'], true)): ?>
                        <td class="text-nowrap text-center">
                          <?php if ($role === 'admin'): ?>
                            <button class="btn btn-sm <?= $locked ? 'btn-danger' : 'btn-success' ?> js-summary-lock" data-year="<?= (int)$y ?>" data-locked="<?= $locked ? 1 : 0 ?>"><?= $locked ? 'Unlock' : 'Lock' ?></button>
                          <?php endif; ?>
                          <?php if (!$locked || $role === 'admin'): ?>
                            <button class="btn btn-sm btn-outline-primary ms-1 js-summary-edit" data-year="<?= (int)$y ?>">Edit</button>
                            <button class="btn btn-sm btn-primary ms-1 d-none js-summary-save" data-year="<?= (int)$y ?>">Save</button>
                          <?php endif; ?>
                          <?php if ($role === 'admin'): ?>
                            <button class="btn btn-sm btn-outline-danger ms-1 js-summary-del" data-year="<?= (int)$y ?>">Delete</button>
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
      </div>


      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="section-title">Table 2. List of PWUDs with relapse</span>
            <div>
              <button class="btn btn-sm btn-outline-primary glow-btn" data-bs-toggle="collapse" data-bs-target="#t2Wrap">Expand/Minimize</button>
              <?php if ($canInput): ?><button id="addT2" class="btn btn-sm btn-primary glow-btn">Add Row</button><?php endif; ?>
            </div>
          </div>
          <div id="t2Wrap" class="collapse">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered align-middle">
                  <thead>
                    <tr class="text-center group-header"><th>Patient Registry No.</th><th>Program</th><th>Date of Positive Drug Testing</th><?php if ($role==='admin'): ?><th>Action</th><?php endif; ?></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($list as $r): ?>
                      <tr>
                        <td><?= h($r['registry_no'] ?? '') ?></td>
                        <td><?= h($r['program'] ?? '') ?></td>
                        <td><?= h($r['date_positive'] ?? '') ?></td>
                        <?php if ($role==='admin'): ?>
                          <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary me-1 edit-list" data-id="<?= (int)$r['id'] ?>">Edit</button>
                            <button class="btn btn-sm <?= ((int)$r['row_locked']===1)?'btn-danger':'btn-success' ?> lock-list" data-id="<?= (int)$r['id'] ?>" data-locked="<?= (int)$r['row_locked'] ?>"><?= ((int)$r['row_locked']===1)?'Unlock':'Lock' ?></button>
                            <button class="btn btn-sm btn-outline-danger ms-1 del-list" data-id="<?= (int)$r['id'] ?>">Delete</button>
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

  <div class="modal fade" id="t1Modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form id="t1Form" class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Graduates per Program</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="id" id="t1_id">
          <div class="mb-3"><label class="form-label">Program</label><input type="text" class="form-control" name="program" id="t1_program"></div>
          <div class="mb-3"><label class="form-label">No. of Graduates</label><input type="number" min="0" class="form-control" name="grads" id="t1_grads"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
  <div class="modal fade" id="t2Modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form id="t2Form" class="modal-content">
        <div class="modal-header"><h5 class="modal-title">PWUDs with relapse/lapse</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="id" id="t2_id">
          <div class="mb-3"><label class="form-label">Patient Registry No. (YYYY-XXXX)</label><input type="text" class="form-control" name="registry_no" id="t2_registry"></div>
          <div class="mb-3">
            <label class="form-label">Program</label>
            <select class="form-select" name="program" id="t2_program">
              <option value="OPD Program">OPD Program</option>
              <option value="Residential Program">Residential Program</option>
              <option value="Aftercare Program">Aftercare Program</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Date of Positive Drug Testing</label><input type="date" class="form-control" name="date_positive" id="t2_date"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php $pgsPage = ['annualRates' => $annualRates]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/collab_roadmap_relapse_rate_1.js') ?>"></script>
</body>
</html>
