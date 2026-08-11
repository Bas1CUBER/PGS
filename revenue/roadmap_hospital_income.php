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
    if ($action === 'add_main') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $classification = trim($_POST['classification'] ?? '');
      $pdo->prepare("INSERT INTO revenue_hospital_main (classification, created_by) VALUES (:c,:cb)")
          ->execute([':c'=>$classification?:null, ':cb'=>$userId?:null]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added hospital income classification: " . ($classification ?: 'N/A');
      notifyAdmins('upload', 'Hospital Income Updated', $notifMsg, $newId, 'hospital_income');
      notifyFocals('upload', 'Hospital Income Updated', $notifMsg, $newId, 'hospital_income');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_main') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNum = ['y2024','y2025','y2026','y2027','y2028'];
      if (in_array($field, $allowedNum, true)) {
        if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (float)$value;
        $pdo->prepare("UPDATE revenue_hospital_main SET {$field} = :v WHERE id = :id")->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated " . strtoupper($field) . " in Hospital Income";
        notifyAdmins('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
        notifyFocals('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
        echo json_encode(['ok'=>true]); exit;
      } else { throw new Exception('Bad field'); }
    }
    if ($action === 'lock_main') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE revenue_hospital_main SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_main') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM revenue_hospital_main WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted a hospital income classification";
      notifyAdmins('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
      notifyFocals('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'add_detail') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $label = trim($_POST['label'] ?? '');
      $pdo->prepare("INSERT INTO revenue_hospital_details (label, created_by) VALUES (:l,:cb)")
          ->execute([':l'=>$label?:null, ':cb'=>$userId?:null]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added hospital income detail: " . ($label ?: 'N/A');
      notifyAdmins('upload', 'Hospital Income Updated', $notifMsg, $newId, 'hospital_income');
      notifyFocals('upload', 'Hospital Income Updated', $notifMsg, $newId, 'hospital_income');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_detail') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNum = ['y2024','y2025','y2026','y2027'];
      if (in_array($field, $allowedNum, true)) {
        if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (float)$value;
        $pdo->prepare("UPDATE revenue_hospital_details SET {$field} = :v WHERE id = :id")->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated " . strtoupper($field) . " in Hospital Income Details";
        notifyAdmins('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
        notifyFocals('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
        echo json_encode(['ok'=>true]); exit;
      } else { throw new Exception('Bad field'); }
    }
    if ($action === 'lock_detail') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE revenue_hospital_details SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_detail') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM revenue_hospital_details WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted a hospital income detail";
      notifyAdmins('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
      notifyFocals('edit', 'Hospital Income Updated', $notifMsg, $id, 'hospital_income');
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

// Tables
$pdo->exec("
  CREATE TABLE IF NOT EXISTS revenue_hospital_main (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classification VARCHAR(180) DEFAULT NULL,
    y2024 DECIMAL(15,2) DEFAULT NULL,
    y2025 DECIMAL(15,2) DEFAULT NULL,
    y2026 DECIMAL(15,2) DEFAULT NULL,
    y2027 DECIMAL(15,2) DEFAULT NULL,
    y2028 DECIMAL(15,2) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS revenue_hospital_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(180) DEFAULT NULL,
    y2024 DECIMAL(15,2) DEFAULT NULL,
    y2025 DECIMAL(15,2) DEFAULT NULL,
    y2026 DECIMAL(15,2) DEFAULT NULL,
    y2027 DECIMAL(15,2) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Fetch
$main = $pdo->query("SELECT * FROM revenue_hospital_main ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$details = $pdo->query("SELECT * FROM revenue_hospital_details ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$yearsMain = ['y2024','y2025','y2026','y2027','y2028'];
$totalsMain = [];
foreach ($yearsMain as $y) {
  $sum = 0.0; $has=false;
  foreach ($main as $r) { if ($r[$y] !== null) { $sum += (float)$r[$y]; $has=true; } }
  $totalsMain[$y] = $has? $sum : null;
}
$diffMain = []; $incMain = [];
for ($i=0;$i<count($yearsMain);$i++) {
  $y = $yearsMain[$i];
  if ($i===0 || $totalsMain[$yearsMain[$i-1]]===null || (float)$totalsMain[$yearsMain[$i-1]]==0.0 || $totalsMain[$y]===null) {
    $diffMain[$y] = null; $incMain[$y] = null;
  } else {
    $diffMain[$y] = (float)$totalsMain[$y] - (float)$totalsMain[$yearsMain[$i-1]];
    $incMain[$y] = $diffMain[$y] / (float)$totalsMain[$yearsMain[$i-1]] * 100.0;
  }
}

$totalsNonTrad = [];
foreach ($yearsMain as $y) {
  $sum = 0.0; $has=false;
  foreach ($pdo->query("SELECT {$y} FROM revenue_non_traditional") as $row) {
    $val = $row[$y];
    if ($val !== null) { $sum += (float)$val; $has=true; }
  }
  $totalsNonTrad[$y] = $has? $sum : null;
}
$overallTotals = [];
foreach ($yearsMain as $y) {
  $a = $totalsMain[$y];
  $b = $totalsNonTrad[$y];
  if ($a === null && $b === null) {
    $overallTotals[$y] = null;
  } else {
    $overallTotals[$y] = (float)($a ?? 0) + (float)($b ?? 0);
  }
}

$yearsDet = ['y2024','y2025','y2026','y2027'];
$totalsDet = [];
foreach ($yearsDet as $y) {
  $sum = 0.0; $has=false;
  foreach ($details as $r) { if ($r[$y] !== null) { $sum += (float)$r[$y]; $has=true; } }
  $totalsDet[$y] = $has? $sum : null;
}

// Chart
$labels = array_values(array_map(fn($r)=> $r['classification'] ?? '', $main));
$labels = array_merge($labels, ['Difference', 'Increase']);

$d2024 = array_values(array_map(fn($r)=> (float)($r['y2024'] ?? 0), $main));
$d2024[] = (float)($diffMain['y2024'] ?? 0);
$d2024[] = (float)($incMain['y2024'] ?? 0);

$d2025 = array_values(array_map(fn($r)=> (float)($r['y2025'] ?? 0), $main));
$d2025[] = (float)($diffMain['y2025'] ?? 0);
$d2025[] = (float)($incMain['y2025'] ?? 0);

$d2026 = array_values(array_map(fn($r)=> (float)($r['y2026'] ?? 0), $main));
$d2026[] = (float)($diffMain['y2026'] ?? 0);
$d2026[] = (float)($incMain['y2026'] ?? 0);

$d2027 = array_values(array_map(fn($r)=> (float)($r['y2027'] ?? 0), $main));
$d2027[] = (float)($diffMain['y2027'] ?? 0);
$d2027[] = (float)($incMain['y2027'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Governance Scorecard: Hospital Income</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel='stylesheet' href='<?= BASE_URL ?>/assets/css/app.css'>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body { background-color: #f5f7fa; color: #2c3e50; }
    .card { border: none; border-radius: 1rem; background-color: #ffffff; box-shadow:0 10px 24px rgba(11,74,162,.12); }
    .card-header { background: #0b4aa2; color: #fff; border-radius: 1rem 1rem 0 0; font-weight: 700; letter-spacing: .04em; text-align: center; padding: 14px 16px; }
    .header-wrap { display:flex; align-items:center; gap:1rem; margin: 16px 0; }
    .header-logo { width:80px; height:80px; object-fit:contain; border-radius:8px; border:2px solid #0b4aa2; background:#fff; }
    .header-title h4 { margin:0; font-weight:700; }
    .muted { color:#4a5568; }
    .table thead th { background:#0b4aa2; color:#fff; white-space:nowrap; }
    .section-title { font-weight:700; }
    .input-sm { max-width:9rem; }
    .chart-card .card-body { padding: 8px 10px 6px; }
    tfoot td { font-weight: 700; background:#f1f5f9; }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<main class="container flex-grow-1" style="padding-top:110px;">
  <div class="header-wrap">
    <img src="/PGS/img/revenue_logo.png" alt="Revenue" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
    <div class="header-title">
      <h4>Governance Scorecard: Hospital Income</h4>
      <small class="muted">Means of Verification:</small>
    </div>
  </div>

  <div class="card mb-4 chart-card">
    <div class="card-header">Hospital Income</div>
    <div class="card-body">
      <canvas id="chart" height="120"></canvas>
      <div id="noData" class="text-center text-muted mt-2" style="<?= count($labels)?'display:none;':'' ?>">No data yet</div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="section-title">Table 1. Hospital Income for CY2024 and CY2025</span>
      <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Wrap">Expand/Minimize</button>
    </div>
    <div class="card-body">
      <?php if (in_array($role, ['admin','focal'], true)): ?>
      <form id="formAddMain" class="row g-2 mb-3">
        <div class="col-12 col-md-9">
          <input type="text" class="form-control form-control-sm" name="classification" placeholder="Classification">
        </div>
        <div class="col-12 col-md-3 d-grid">
          <button type="submit" class="btn btn-success btn-sm">Add Row</button>
        </div>
      </form>
      <?php endif; ?>
      <div class="table-responsive collapse show" id="t1Wrap">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Classification</th>
              <th>2024</th>
              <th>2025</th>
              <th>2026</th>
              <th>2027</th>
              <th>2028</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($main as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><?= htmlspecialchars($r['classification'] ?? '') ?></td>
                <?php foreach ($yearsMain as $col): $val=$r[$col]; ?>
                  <td class="text-center">
                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                      <input type="number" step="0.01" min="0" class="form-control form-control-sm input-sm js-main-num" data-field="<?= $col ?>" value="<?= $val!==null?number_format((float)$val,2,'.','') : '' ?>" <?= $r['locked']?'disabled':'' ?>>
                    <?php else: ?>
                      <?= $val!==null?number_format((float)$val,2):'' ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($role === 'admin'): ?>
                <td class="text-nowrap">
                  <button class="btn btn-sm <?= $r['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-main-lock" data-locked="<?= $r['locked']?0:1 ?>"><?= $r['locked']? 'Unlock' : 'Lock' ?></button>
                  <button class="btn btn-sm btn-outline-danger js-main-del">Delete</button>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>Traditional Total</td>
              <?php foreach ($yearsMain as $y): ?>
                <td class="text-end"><?= $totalsMain[$y]!==null?number_format((float)$totalsMain[$y],2):'' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
            <tr>
              <td>Non-Traditional Total</td>
              <?php foreach ($yearsMain as $y): ?>
                <td class="text-end"><?= $totalsNonTrad[$y]!==null?number_format((float)$totalsNonTrad[$y],2):'' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
            <tr>
              <td>Overall</td>
              <?php foreach ($yearsMain as $y): ?>
                <td class="text-end"><?= $overallTotals[$y]!==null?number_format((float)$overallTotals[$y],2):'' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
            <tr>
              <td>Difference</td>
              <?php foreach ($yearsMain as $y): ?>
                <td class="text-end"><?= $diffMain[$y]!==null?number_format((float)$diffMain[$y],2):'' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
            <tr>
              <td>Increasion Rate</td>
              <?php foreach ($yearsMain as $y): ?>
                <td class="text-end"><?= $incMain[$y]!==null?number_format((float)$incMain[$y],2).'%' : '' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="section-title">Details</span>
      <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t2Wrap">Expand/Minimize</button>
    </div>
    <div class="card-body">
      <?php if (in_array($role, ['admin','focal'], true)): ?>
      <form id="formAddDet" class="row g-2 mb-3">
        <div class="col-12 col-md-9">
          <input type="text" class="form-control form-control-sm" name="label" placeholder="Regular Income">
        </div>
        <div class="col-12 col-md-3 d-grid">
          <button type="submit" class="btn btn-primary btn-sm">Add Row</button>
        </div>
      </form>
      <?php endif; ?>
      <div class="table-responsive collapse show" id="t2Wrap">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Regular Income</th>
              <th>2024</th>
              <th>2025</th>
              <th>2026</th>
              <th>2027</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($details as $d): ?>
              <tr data-id="<?= (int)$d['id'] ?>">
                <td><?= htmlspecialchars($d['label'] ?? '') ?></td>
                <?php foreach ($yearsDet as $col): $val=$d[$col]; ?>
                  <td class="text-center">
                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                      <input type="number" step="0.01" min="0" class="form-control form-control-sm input-sm js-det-num" data-field="<?= $col ?>" value="<?= $val!==null?number_format((float)$val,2,'.','') : '' ?>" <?= $d['locked']?'disabled':'' ?>>
                    <?php else: ?>
                      <?= $val!==null?number_format((float)$val,2):'' ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($role === 'admin'): ?>
                <td class="text-nowrap">
                  <button class="btn btn-sm <?= $d['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-det-lock" data-locked="<?= $d['locked']?0:1 ?>"><?= $d['locked']? 'Unlock' : 'Lock' ?></button>
                  <button class="btn btn-sm btn-outline-danger js-det-del">Delete</button>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <?php foreach ($yearsDet as $y): ?>
                <td class="text-end"><?= $totalsDet[$y]!==null?number_format((float)$totalsDet[$y],2):'' ?></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</main>
<?php include PGS_TEMPLATES . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const labels = <?= json_encode($labels) ?>;
  const data2024 = <?= json_encode($d2024) ?>;
  const data2025 = <?= json_encode($d2025) ?>;
  const data2026 = <?= json_encode($d2026) ?>;
  const data2027 = <?= json_encode($d2027) ?>;
  function formatCompactValue(value) {
    const num = Number(value) || 0;
    const absNum = Math.abs(num);
    if (absNum >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (absNum >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    return num.toLocaleString();
  }
  if (labels.length > 0) {
    new Chart(document.getElementById('chart').getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label:'2024', data: data2024, backgroundColor: '#3b82f6' },
          { label:'2025', data: data2025, backgroundColor: '#ef4444' },
          { label:'2026', data: data2026, backgroundColor: '#22c55e' },
          { label:'2027', data: data2027, backgroundColor: '#f59e0b' },
        ]
      },
      plugins: [ChartDataLabels],
      options: {
        responsive: true,
        plugins: {
          tooltip: {
            callbacks: {
              label: (context) => `${context.dataset.label}: ${formatCompactValue(context.parsed.y)}`
            }
          },
          datalabels: {
            anchor: 'end',
            align: 'top',
            color: '#2c3e50',
            font: { weight: '600', size: 10 },
            formatter: (value) => formatCompactValue(value),
            display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0
          }
        },
        scales: {
          y: { display:false, beginAtZero:true, min:0, max:13000000, ticks:{ stepSize:2500000 }, grid:{ display:false }, border:{ display:false } }
        }
      }
    });
  }

  // Main table handlers
  document.querySelectorAll('.js-main-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_main');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-main-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','lock_main');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-main-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_main');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });
  const formAddMain = document.getElementById('formAddMain');
  if (formAddMain) {
    formAddMain.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddMain);
      fd.append('action','add_main');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }

  // Details table handlers
  document.querySelectorAll('.js-det-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_detail');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-det-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','lock_detail');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-det-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_detail');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });
  const formAddDet = document.getElementById('formAddDet');
  if (formAddDet) {
    formAddDet.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddDet);
      fd.append('action','add_detail');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }
</script>
</body>
</html>
