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

// Fixed year per request
$year = isset($_GET['year']) ? (int)$_GET['year'] : 2024;
if (!in_array($year, [2024, 2025, 2026, 2027], true)) {
  $year = 2024;
}

try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS client_satisfaction_values (
      id INT AUTO_INCREMENT PRIMARY KEY,
      table_key VARCHAR(3) NOT NULL,
      division_key VARCHAR(80) NOT NULL,
      year INT NOT NULL,
      annual DECIMAL(7,3) DEFAULT NULL,
      january DECIMAL(7,3) DEFAULT NULL,
      february DECIMAL(7,3) DEFAULT NULL,
      march DECIMAL(7,3) DEFAULT NULL,
      april DECIMAL(7,3) DEFAULT NULL,
      may DECIMAL(7,3) DEFAULT NULL,
      june DECIMAL(7,3) DEFAULT NULL,
      july DECIMAL(7,3) DEFAULT NULL,
      august DECIMAL(7,3) DEFAULT NULL,
      september DECIMAL(7,3) DEFAULT NULL,
      october DECIMAL(7,3) DEFAULT NULL,
      november DECIMAL(7,3) DEFAULT NULL,
      december DECIMAL(7,3) DEFAULT NULL,
      locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_row (table_key, division_key, year),
      CONSTRAINT fk_csv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}
// Try to add 'annual' column if table exists from earlier version
try { $pdo->exec("ALTER TABLE client_satisfaction_values ADD COLUMN annual DECIMAL(7,3) DEFAULT NULL"); } catch (Throwable $e) {}

function slugify_div($s) {
  $s = strtolower(trim($s));
  $s = preg_replace('/[^a-z0-9]+/','_',$s);
  return trim($s, '_');
}

$divisionsTable1 = [
  'Office of the Chief of Hospital',
  'OCOH - PHU',
  'OCOH - OCOH',
  'OCOH - LEGAL',
  'OCOH - ICT',
  'OCOH - QSMO',
  'OCOH - HIMS',
  'OCOH - Primary Care',
  'OCOH - Planning',
  'Financial and Administrative Division',
  'FAD - CAO',
  'FAD - GSS',
  'FAD - MSS',
  'FAD - HRMS',
  'FAD - PACD',
  'FAD - Budget',
  'FAD - Accounting',
  'FAD - Billing',
  'FAD - Cashier',
  'FAD - Procurement',
  'Treatment and Rehabilitation Division',
  'TRD - Nutrition/Dietary',
  'TRD - Medical',
  'TRD - Nursing',
  'TRD - Social Works',
  'TRD - Psychological',
  'TRD - Laboratory',
  'TRD - Dormitory',
  'Other',
  'DATRC-LA UNION RATING'
];
$divisionsTable2 = [
  'Office of the Chief of Hospital',
  'OCOH - PHU',
  'OCOH - OCOH',
  'OCOH - LEGAL',
  'OCOH - ICT',
  'OCOH - QSMO',
  'OCOH - HIMS',
  'OCOH - Primary Care',
  'OCOH - Planning',
  'Financial and Administrative Division',
  'FAD - CAO',
  'FAD - GSS',
  'FAD - MMS',
  'FAD - HRMS',
  'FAD - PACD',
  'FAD - Budget',
  'FAD - Accounting',
  'FAD - Billing',
  'FAD - Cashier',
  'FAD - Procurement',
  'Treatment and Rehabilitation Division',
  'TRD - Nutrition/Dietary',
  'TRD - Medical',
  'TRD - Nursing',
  'TRD - Social Works',
  'TRD - Psychological',
  'TRD - Laboratory',
  'TRD - Dormitory',
  'Other',
  'DATRC-LA UNION RATING'
];
$divisionMapByTable = [
  '1' => array_reduce($divisionsTable1, function($acc,$d){ $acc[slugify_div($d)]=$d; return $acc; }, []),
  '2' => array_reduce($divisionsTable2, function($acc,$d){ $acc[slugify_div($d)]=$d; return $acc; }, []),
];

$tables = [
  '1' => 'Table 1. (' . $year . ') Client Satisfaction Rate by, Section, Division, Month and Year',
  '2' => 'Table 2. (' . $year . ') Number of Clients by Section, Division, Month and Year'
];

$tableSubtitles = [
  '1' => '(Source: ARTA Monthly CSM Report as of August 30, ' . $year . ')',
  '2' => '(Source: ARTA Monthly CSM Report as of August 30, ' . $year . ')'
];

$action = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
  header("Content-Type: application/json");
  try {
    if ($action === 'save_cell') {
      $tableKey = strtoupper(trim($_POST['table_key'] ?? ''));
      $divKey = trim($_POST['division_key'] ?? '');
      $month = strtolower(trim($_POST['month'] ?? ''));
      $value = $_POST['value'] ?? null;
      if (!isset($tables[$tableKey]) || !isset($divisionMapByTable[$tableKey][$divKey])) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid parameters']); exit;
      }
      $allowedMonths = ['january','february','march','april','may','june','july','august','september','october','november','december'];
      if (!in_array($month, $allowedMonths, true)) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid month']); exit;
      }
      $val = null;
      if ($value !== '' && $value !== null) {
        $val = floatval($value);
      }
      // Check lock
      $stmt = $pdo->prepare("SELECT locked FROM client_satisfaction_values WHERE table_key=:t AND division_key=:d AND year=:y");
      $stmt->execute([':t'=>$tableKey, ':d'=>$divKey, ':y'=>$year]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $isLocked = $row ? (int)$row['locked'] === 1 : 0;
      if ($isLocked && $role !== 'admin') {
        echo json_encode(['ok'=>false,'msg'=>'Row is locked']); exit;
      }
      if (!in_array($role, ['admin','focal'], true)) {
        echo json_encode(['ok'=>false,'msg'=>'Not allowed']); exit;
      }
      // Upsert
      $sql = "
        INSERT INTO client_satisfaction_values (table_key, division_key, year, {$month}, created_by)
        VALUES (:t, :d, :y, :v, :u)
        ON DUPLICATE KEY UPDATE {$month} = VALUES({$month}), updated_at = CURRENT_TIMESTAMP
      ";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':t'=>$tableKey, ':d'=>$divKey, ':y'=>$year, ':v'=>$val, ':u'=>$userId]);
      
      // Notify all users about the change
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $divName = $divisionMapByTable[$tableKey][$divKey] ?? $divKey;
      $monthLabel = ucfirst($month);
      $notifMsg = $roleLabel . " " . $userIdent . " updated " . $monthLabel . " value for \"" . $divName . "\" in Client Satisfaction (Table " . $tableKey . ", " . $year . ")";
      notifyAdmins('edit', 'Client Satisfaction Updated', $notifMsg, null, 'client_satisfaction');
      notifyFocals('edit', 'Client Satisfaction Updated', $notifMsg, null, 'client_satisfaction');
      
      echo json_encode(['ok'=>true]); exit;
    }
    if ($role === 'admin' && $action === 'set_lock') {
      $tableKey = strtoupper(trim($_POST['table_key'] ?? ''));
      $divKey = trim($_POST['division_key'] ?? '');
      $lock = (int)($_POST['lock'] ?? 1) === 1 ? 1 : 0;
      if (!isset($tables[$tableKey]) || !isset($divisionMapByTable[$tableKey][$divKey])) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid parameters']); exit;
      }
      $pdo->prepare("
        INSERT INTO client_satisfaction_values (table_key, division_key, year, created_by, locked)
        VALUES (:t,:d,:y,:u,:l)
        ON DUPLICATE KEY UPDATE locked = VALUES(locked), updated_at = CURRENT_TIMESTAMP
      ")->execute([':t'=>$tableKey, ':d'=>$divKey, ':y'=>$year, ':u'=>$userId, ':l'=>$lock]);
      echo json_encode(['ok'=>true,'locked'=>$lock]); exit;
    }
    if ($role === 'admin' && $action === 'clear_row') {
      $tableKey = strtoupper(trim($_POST['table_key'] ?? ''));
      $divKey = trim($_POST['division_key'] ?? '');
      if (!isset($tables[$tableKey]) || !isset($divisionMapByTable[$tableKey][$divKey])) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid parameters']); exit;
      }
      $pdo->prepare("
        UPDATE client_satisfaction_values
        SET annual=NULL,january=NULL,february=NULL,march=NULL,april=NULL,may=NULL,june=NULL,july=NULL,august=NULL,september=NULL,october=NULL,november=NULL,december=NULL, updated_at=CURRENT_TIMESTAMP
        WHERE table_key=:t AND division_key=:d AND year=:y
      ")->execute([':t'=>$tableKey, ':d'=>$divKey, ':y'=>$year]);
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'msg'=>'Server error']); exit;
  }
}

// Load all values for the year and map by [table_key][division_key]
$allValues = [];
try {
  $stmt = $pdo->prepare("SELECT * FROM client_satisfaction_values WHERE year=:y");
  $stmt->execute([':y'=>$year]);
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tk = $r['table_key']; $dk = $r['division_key'];
    if (!isset($allValues[$tk])) $allValues[$tk] = [];
    $allValues[$tk][$dk] = $r;
  }
} catch (Throwable $e) {}

// Prepare data for charts
$chartTableLabels = [];
$chartTableData = [];
foreach ($tables as $k => $title) {
  $chartTableLabels[] = $title . " ($year)";
  $sum = 0; $cnt = 0;
  if (isset($allValues[$k])) {
    foreach ($allValues[$k] as $row) {
      if ($row['annual'] !== null && $row['annual'] !== '') {
        $sum += (float)$row['annual'];
        $cnt++;
      }
    }
  }
  $chartTableData[] = $cnt > 0 ? round($sum / $cnt, 3) : 0;
}
$monthsList = ['january','february','march','april','may','june','july','august','september','october','november','december'];
$monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartMonthLabels = $monthShort;
$chartMonthData = [];
foreach ($monthsList as $m) {
  $sum = 0; $cnt = 0;
  foreach ($tables as $k => $title) {
    if (isset($allValues[$k])) {
      foreach ($allValues[$k] as $row) {
        if ($row[$m] !== null && $row[$m] !== '') {
          $sum += (float)$row[$m];
          $cnt++;
        }
      }
    }
  }
  $chartMonthData[] = $cnt > 0 ? round($sum / $cnt, 3) : 0;
}

// Aggregated gauges (center numbers) - calculate from monthly values
$annualSum = 0; $annualCount = 0;
foreach ($tables as $k => $title) {
  if (!isset($allValues[$k])) continue;
  foreach ($allValues[$k] as $row) {
    // Calculate from monthly values instead of annual column
    foreach ($monthsList as $m) {
      if ($row[$m] !== null && $row[$m] !== '') {
        $annualSum += (float)$row[$m];
        $annualCount++;
      }
    }
  }
}
$annualAvg = $annualCount > 0 ? round($annualSum / $annualCount, 1) : 0.0;
$annualPossible = array_sum(array_map(function($tk) use ($divisionMapByTable){ return count($divisionMapByTable[$tk]); }, array_keys($tables)));

// Monthly average (same calculation, kept for clarity)
$monthlySum = 0; $monthlyCount = 0;
foreach ($tables as $k => $title) {
  if (!isset($allValues[$k])) continue;
  foreach ($allValues[$k] as $row) {
    foreach ($monthsList as $m) {
      if ($row[$m] !== null && $row[$m] !== '') {
        $monthlySum += (float)$row[$m];
        $monthlyCount++;
      }
    }
  }
}
$monthlyAvg = $monthlyCount > 0 ? round($monthlySum / $monthlyCount, 1) : 0.0;
$monthlyPossible = array_sum(array_map(function($tk) use ($divisionMapByTable){ return count($divisionMapByTable[$tk]); }, array_keys($tables))) * count($monthsList);

// Dashboard 2 trend data: Table 1 yearly overall (Actual) vs fixed Target
$trendYears = [2024, 2025, 2026, 2027];
$trendActualMap = array_fill_keys($trendYears, 0.0);
$trendTargetMap = array_fill_keys($trendYears, 85.0);
try {
  $inYears = implode(',', array_map('intval', $trendYears));
  $stmtTrend = $pdo->query("SELECT year, january, february, march, april, may, june, july, august, september, october, november, december FROM client_satisfaction_values WHERE table_key='1' AND year IN ($inYears)");
  $trendAgg = [];
  foreach ($trendYears as $y) {
    $trendAgg[$y] = ['sum' => 0.0, 'count' => 0];
  }
  while ($row = $stmtTrend->fetch(PDO::FETCH_ASSOC)) {
    $y = (int)$row['year'];
    if (!isset($trendAgg[$y])) continue;
    foreach ($monthsList as $m) {
      if ($row[$m] !== null && $row[$m] !== '') {
        $trendAgg[$y]['sum'] += (float)$row[$m];
        $trendAgg[$y]['count']++;
      }
    }
  }
  foreach ($trendYears as $y) {
    if ($trendAgg[$y]['count'] > 0) {
      $trendActualMap[$y] = round($trendAgg[$y]['sum'] / $trendAgg[$y]['count'], 2);
    }
  }
} catch (Throwable $e) {}

$chartTrendLabels = array_map('strval', $trendYears);
$chartTrendActualData = array_values(array_map(fn($y) => $trendActualMap[$y], $trendYears));
$chartTrendTargetData = array_values(array_map(fn($y) => $trendTargetMap[$y], $trendYears));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Governance Scorecard: Client Satisfaction Rating</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  =2'>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <style>
    html, body { height: 100%; }
    body { background-color: #f5f7fa; color: #2c3e50; min-height: 100vh; display: flex; flex-direction: column; }
    .page-container { flex: 1 0 auto; }
    .card { border: none; border-radius: 1rem; background-color: #ffffff; box-shadow:0 10px 24px rgba(11,74,162,.12); }
    .card-header { background: #0b4aa2; color: #fff; border-radius: 1rem 1rem 0 0; font-weight: 700; letter-spacing: .04em; padding: 14px 16px; }
    .header-wrap { display:flex; align-items:center; gap:1rem; margin: 16px 0; }
    .header-logo { width:80px; height:80px; object-fit:contain; border-radius:8px; border:2px solid #0b4aa2; background:#fff; }
    .header-title h4 { margin:0; font-weight:700; }
    .muted { color:#4a5568; }
    .table thead th { background:#0b4aa2; color:#fff; white-space:nowrap; }
    .section-title { font-weight:700; }
    .card-header .section-title { color:#fff; }
    .card-header select { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
    .card-header select option { background: #0b4aa2; color: #fff; }
    .glow-btn { box-shadow:0 0 0 rgba(11,74,162,.6); transition: box-shadow .2s; }
    .glow-btn:hover { box-shadow:0 0 0.75rem rgba(11,74,162,.35); }
    .cs-input { width: 6rem; min-width: 4.5rem; }
    .cs-actions { white-space:nowrap; }
    .badge-lock { font-size:.75rem; }
    .chart-card .card-body { padding: 10px 10px 8px; }
    .dashboard-charts .chart-card { height: 100%; width: 100%; }
    .dashboard-charts .chart-card .card-body {
      min-height: 260px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .chart-canvas-wrap {
      position: relative;
      width: 100%;
      height: 185px;
    }
    #chartA, #chartB {
      width: 100% !important;
      height: 100% !important;
      max-height: none;
    }
    .chart-no-data {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 8px;
      text-align: center;
      margin: 0;
    }
    .highlight-row td { background-color: #edf2f7 !important; }
    .highlight-pink td { background-color: #ffe6ef !important; }
  </style>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="container page-container" style="padding-top:110px;">
    <div class="header-wrap">
      <img src="/PGS/img/clients_logo.png" alt="Client Satisfaction" class="header-logo" onerror="this.style.display='none'">
      <div class="header-title">
        <h4>Governance Scorecard: Client Satisfaction Rating</h4>
        <small class="muted">Means of Verification: Annual Client Satisfaction Measurement Reports; Monthly Client Satisfaction Measurements Reports.</small>
      </div>
    </div>

    <div class="mb-4">
      <form method="get" class="d-flex align-items-center gap-2">
        <label class="fw-bold">Select Year:</label>
        <select name="year" class="form-select w-auto" onchange="this.form.submit()">
          <?php foreach ([2024, 2025, 2026, 2027] as $y): ?>
            <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <div class="row g-3 mb-2 dashboard-charts">
      <div class="col-12 col-lg-6">
        <div class="card chart-card">
          <div class="card-header text-center"><?= $year ?> Client Satisfaction Rating (Annual)</div>
          <div class="card-body">
            <div class="chart-canvas-wrap">
              <canvas id="chartA"></canvas>
            </div>
            <div id="noDataA" class="text-muted chart-no-data">No data yet</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card chart-card">
          <div class="card-header text-center">Client Satisfaction Rating Trend (2024-2027)</div>
          <div class="card-body">
            <div class="chart-canvas-wrap">
              <canvas id="chartB"></canvas>
            </div>
            <div id="noDataB" class="text-muted chart-no-data">No data yet</div>
          </div>
        </div>
      </div>
    </div>

    <?php foreach ($tables as $tk => $tTitle): ?>
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="section-title"><?= $tTitle ?></span>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light glow-btn" data-bs-toggle="collapse" data-bs-target="#wrap_<?= $tk ?>">Expand/Minimize</button>
            <button class="btn btn-sm btn-outline-light glow-btn btn-export-table" data-tablekey="<?= htmlspecialchars($tk) ?>" data-title="<?= htmlspecialchars($tTitle) ?>">Export .xlsx</button>
          </div>
        </div>
        <div id="wrap_<?= $tk ?>" class="collapse show">
          <div class="card-body">
            <div class="mb-2 fst-italic text-muted"><?= $tableSubtitles[$tk] ?? '' ?></div>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead>
                  <tr>
                    <th>Division / Section</th>
                    <th>January</th>
                    <th>February</th>
                    <th>March</th>
                    <th>April</th>
                    <th>May</th>
                    <th>June</th>
                    <th>July</th>
                    <th>August</th>
                    <th>September</th>
                    <th>October</th>
                    <th>November</th>
                    <th>December</th>
                    <th><?= $year ?></th>
                    <?php if ($role === 'admin'): ?>
                      <th>Actions</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (($divisionMapByTable[$tk] ?? []) as $dKey => $dLabel): 
                    $r = $allValues[$tk][$dKey] ?? null;
                    $locked = $r ? (int)$r['locked'] === 1 : 0;
                    $hlGray = in_array($dLabel, ['Office of the Chief of Hospital','Financial and Administrative Division','Treatment and Rehabilitation Division','Other'], true);
                    $hlPink = ($dLabel === 'DATRC-LA UNION RATING');
                    $rowClass = $hlGray ? 'highlight-row' : ($hlPink ? 'highlight-pink' : '');
                  ?>
                  <tr class="<?= $rowClass ?>">
                    <td>
                      <?= htmlspecialchars($dLabel) ?>
                      <?php if ($locked): ?>
                        <span class="badge bg-secondary badge-lock">Locked</span>
                      <?php endif; ?>
                    </td>
                    <?php
                      $months = ['january','february','march','april','may','june','july','august','september','october','november','december'];
                      $annualSum = 0.0; $annualHas = false;
                      foreach ($months as $m):
                        $val = $r[$m] ?? null;
                        $disabled = ($role !== 'admin' && $locked) ? 'disabled' : '';
                        $canEdit = in_array($role, ['admin','focal'], true) && (!$locked || $role === 'admin');
                        if ($val !== null && $val !== '') { $annualSum += (float)$val; $annualHas = true; }
                    ?>
                      <td>
                        <?php if ($canEdit): ?>
                          <?php if ($tk == '1'): ?>
                          <div class="input-group input-group-sm" style="width: 100px;">
                            <input type="number" step="0.001" min="0" max="100" class="form-control cs-input"
                              data-table="<?= htmlspecialchars($tk) ?>"
                              data-division="<?= htmlspecialchars($dKey) ?>"
                              data-month="<?= $m ?>"
                              value="<?= $val !== null ? htmlspecialchars($val) : '' ?>"
                              placeholder="0"
                              <?= $disabled ?>
                            >
                            <span class="input-group-text">%</span>
                          </div>
                          <?php else: ?>
                          <input type="number" step="1" min="0" class="form-control form-control-sm cs-input"
                            style="width: 100px;"
                            data-table="<?= htmlspecialchars($tk) ?>"
                            data-division="<?= htmlspecialchars($dKey) ?>"
                            data-month="<?= $m ?>"
                            value="<?= $val !== null ? htmlspecialchars($val) : '' ?>"
                            placeholder="0"
                            <?= $disabled ?>
                          >
                          <?php endif; ?>
                        <?php else: ?>
                          <?= $val !== null ? htmlspecialchars($val) . ($tk == '1' ? '%' : '') : '' ?>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                    <?php
                      $annualDisplay = $annualHas ? number_format($annualSum, 3) : '';
                    ?>
                    <td>
                      <?= $annualDisplay !== '' ? $annualDisplay . ($tk == '1' ? '%' : '') : '' ?>
                    </td>
                    <?php if ($role === 'admin'): ?>
                      <td class="cs-actions">
                        <button class="btn btn-sm <?= $locked ? 'btn-secondary' : 'btn-outline-secondary' ?> btn-lock"
                          data-table="<?= htmlspecialchars($tk) ?>" data-division="<?= htmlspecialchars($dKey) ?>" data-lock="<?= $locked ? 0 : 1 ?>">
                          <?= $locked ? 'Unlock' : 'Lock' ?>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-clear"
                          data-table="<?= htmlspecialchars($tk) ?>" data-division="<?= htmlspecialchars($dKey) ?>">
                          Clear
                        </button>
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
    <?php endforeach; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const YEAR = <?= json_encode($year) ?>;
    const ROLE = <?= json_encode($role) ?>;
    // Helpers for XLSX export
    function sanitizeSheetName(name) {
      return name.replace(/[\\/*?:\[\]]/g, '').substring(0,31) || 'Sheet';
    }
    function cloneTableForExport(tableEl, tableKey) {
      const clone = tableEl.cloneNode(true);
      // Replace inputs with numeric values; scale 0..1 to 0..100 only for Table 1
      clone.querySelectorAll('input.cs-input').forEach(inp => {
        const td = inp.closest('td');
        let v = inp.value.trim();
        if (v === '') { td.textContent = ''; return; }
        let num = parseFloat(v);
        if (!Number.isFinite(num)) { td.textContent = v; return; }
        // Only scale for Table 1 (percentage table)
        if (tableKey == '1' && num > 0 && num <= 1) num = num * 100;
        num = Math.round(num * 1000) / 1000;
        td.textContent = String(num);
      });
      // Remove percent suffix visuals
      clone.querySelectorAll('.input-group-text, .percent-suffix').forEach(el => el.remove());
      // Remove Actions column if present
      const thead = clone.querySelector('thead');
      let actionIndex = -1;
      if (thead) {
        const ths = Array.from(thead.querySelectorAll('th'));
        actionIndex = ths.findIndex(th => th.textContent.trim().toLowerCase() === 'actions');
      }
      if (actionIndex >= 0) {
        clone.querySelectorAll('tr').forEach(tr => {
          const cells = tr.querySelectorAll('th, td');
          if (cells[actionIndex]) cells[actionIndex].remove();
        });
      }
      return clone;
    }
    function exportSingleTableXLSX(tableKey, title) {
      try {
        const wrap = document.getElementById('wrap_' + tableKey);
        if (!wrap) { Swal.fire({icon:'error', title:'Export failed', text:'Table not found'}); return; }
        const table = wrap.querySelector('table');
        if (!table) { Swal.fire({icon:'error', title:'Export failed', text:'Table not found'}); return; }
        const clean = cloneTableForExport(table, tableKey);
        const ws = XLSX.utils.table_to_sheet(clean, { raw: true });
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, sanitizeSheetName(title));
        const fname = `Client_Satisfaction_${YEAR}_${tableKey}.xlsx`;
        XLSX.writeFile(wb, fname);
      } catch (e) {
        Swal.fire({icon:'error', title:'Export error', text:'Unable to export .xlsx'});
      }
    }
    // Save on change
    document.querySelectorAll('.cs-input').forEach(inp => {
      inp.addEventListener('change', async (e) => {
        const el = e.currentTarget;
        const payload = new FormData();
        payload.append('action','save_cell');
        payload.append('table_key', el.dataset.table);
        payload.append('division_key', el.dataset.division);
        payload.append('month', el.dataset.month);
        payload.append('value', el.value);
        try {
          const res = await fetch(location.href, { method:'POST', body: payload });
          const j = await res.json();
          if (!j.ok) {
            Swal.fire({ icon:'error', title:'Save failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Lock / Unlock
    document.querySelectorAll('.btn-lock').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const el = e.currentTarget;
        const lock = el.dataset.lock;
        const fd = new FormData();
        fd.append('action','set_lock');
        fd.append('table_key', el.dataset.table);
        fd.append('division_key', el.dataset.division);
        fd.append('lock', lock);
        try {
          const res = await fetch(location.href, { method:'POST', body: fd });
          const j = await res.json();
          if (j.ok) {
            location.reload();
          } else {
            Swal.fire({ icon:'error', title:'Action failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Clear
    document.querySelectorAll('.btn-clear').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const el = e.currentTarget;
        const confirm = await Swal.fire({
          icon:'warning',
          title:'Clear this row?',
          text:'All month values will be removed for <?= $year ?>.',
          showCancelButton:true,
          confirmButtonText:'Yes, clear'
        });
        if (!confirm.isConfirmed) return;
        const fd = new FormData();
        fd.append('action','clear_row');
        fd.append('table_key', el.dataset.table);
        fd.append('division_key', el.dataset.division);
        try {
          const res = await fetch(location.href, { method:'POST', body: fd });
          const j = await res.json();
          if (j.ok) {
            location.reload();
          } else {
            Swal.fire({ icon:'error', title:'Action failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Export per-table
    document.querySelectorAll('.btn-export-table').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const key = e.currentTarget.dataset.tablekey;
        const title = e.currentTarget.dataset.title || ('Table ' + key);
        exportSingleTableXLSX(key, title);
      });
    });
    // Charts using saved data
    const chartTableLabels = <?= json_encode($chartTableLabels) ?>;
    const chartTableData = <?= json_encode($chartTableData) ?>;
    const chartMonthLabels = <?= json_encode($chartMonthLabels) ?>;
    const chartMonthData = <?= json_encode($chartMonthData) ?>;
    const chartTrendLabels = <?= json_encode($chartTrendLabels) ?>;
    const chartTrendActualData = <?= json_encode($chartTrendActualData) ?>;
    const chartTrendTargetData = <?= json_encode($chartTrendTargetData) ?>;
    const annualAvg = <?= json_encode($annualAvg) ?>;
    const annualFilled = <?= json_encode($annualCount) ?>;
    const annualPossible = <?= json_encode($annualPossible) ?>;
    const monthlyAvg = <?= json_encode($monthlyAvg) ?>;
    const monthlyFilled = <?= json_encode($monthlyCount) ?>;
    const monthlyPossible = <?= json_encode($monthlyPossible) ?>;

    const hasTableData = chartTableData.some(v => Number(v) > 0);
    const hasTrendActualData = chartTrendActualData.some(v => Number(v) > 0);
    
    // Updated formal color palette: lighter, professional tones
    const FORMAL_COLORS = [
      '#4a90e2', // Soft Blue
      '#50e3c2', // Teal/Mint
      '#b8e986', // Light Green
      '#f5a623', // Muted Orange
      '#f8e71c', // Soft Yellow
      '#bd10e0', // Soft Purple
      '#9013fe', // Deep Purple
      '#417505', // Forest Green
      '#d0021b', // Muted Red
      '#9b9b9b'  // Grey
    ];
    // Helper to get color for n items
    const palette = (n) => Array.from({length: n}, (_, i) => FORMAL_COLORS[i % FORMAL_COLORS.length]);

    const ctxA = document.getElementById('chartA').getContext('2d');
    new Chart(ctxA, {
      type:'pie',
      data:{
        labels: ['Satisfied', 'Remaining'],
        datasets:[{
          data: [annualAvg, Math.max(0, 100 - annualAvg)],
          backgroundColor: [FORMAL_COLORS[0], '#e9ecef'], // Soft Blue vs Light Grey
          borderWidth: 0
        }]
      },
      options:{
        responsive: true,
        maintainAspectRatio: false,
        plugins:{
          legend:{ display:true, position:'right' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.label}: ${Number(ctx.parsed).toFixed(2)}%`
            }
          }
        }
      }
    });
    if (hasTableData) {
      const noDataA = document.getElementById('noDataA');
      if (noDataA) noDataA.style.display = 'none';
    }

    const ctxB = document.getElementById('chartB').getContext('2d');
    new Chart(ctxB, {
      type:'line',
      data:{
        labels: chartTrendLabels,
        datasets:[
          {
            label: 'Actual (%)',
            data: chartTrendActualData,
            borderColor: FORMAL_COLORS[1],
            backgroundColor: FORMAL_COLORS[1],
            tension: 0.25,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 5
          },
          {
            label: 'Target (%)',
            data: chartTrendTargetData,
            borderColor: FORMAL_COLORS[3],
            backgroundColor: FORMAL_COLORS[3],
            tension: 0,
            borderDash: [6, 6],
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 4
          }
        ]
      },
      options:{
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: {
              callback: (v) => `${Number(v).toFixed(0)}%`
            }
          }
        },
        plugins:{
          legend:{ display:true, position:'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(2)}%`
            }
          }
        }
      }
    });

    if (hasTrendActualData) {
      const noDataB = document.getElementById('noDataB');
      if (noDataB) noDataB.style.display = 'none';
    }
  </script>
</body>
</html>
