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
      if ($role !== 'admin') throw new Exception('Admin only');
      $indicator = trim($_POST['indicator'] ?? '');
      $share = $_POST['share'] !== '' ? (float)$_POST['share'] : null;
      $year = isset($_POST['year']) ? (int)$_POST['year'] : null;
      $yearValue = isset($_POST['year_value']) && $_POST['year_value'] !== '' ? (float)$_POST['year_value'] : null;
      $allowedYears = [2024,2025,2026,2027,2028];
      if ($year && in_array($year, $allowedYears, true) && $yearValue !== null) {
        $col = 'y'.$year;
        $sql = "INSERT INTO resilience_gvr (indicator, share, {$col}, created_by) VALUES (:i,:s,:yv,:cb)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':i'=>$indicator?:null, ':s'=>$share, ':yv'=>$yearValue, ':cb'=>$userId ?: null]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO resilience_gvr (indicator, share, created_by) VALUES (:i,:s,:cb)");
        $stmt->execute([':i'=>$indicator?:null, ':s'=>$share, ':cb'=>$userId ?: null]);
      }
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " added green viability indicator: " . ($indicator ?: 'N/A');
      notifyAdmins('upload', 'Green Viability Updated', $notifMsg, $newId, 'green_viability');
      notifyFocals('upload', 'Green Viability Updated', $notifMsg, $newId, 'green_viability');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_cell') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNum = ['y2024','y2025','y2026','y2027','y2028'];
      $allowedText = ['share'];
      if (in_array($field, $allowedNum, true)) {
        if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (float)$value;
        $pdo->prepare("UPDATE resilience_gvr SET {$field} = :v WHERE id = :id")->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated " . strtoupper($field) . " in Green Viability";
        notifyAdmins('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
        notifyFocals('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
        echo json_encode(['ok'=>true]); exit;
      } elseif (in_array($field, $allowedText, true)) {
        if ($role !== 'admin') throw new Exception('Admin only');
        $value = ($value === '' || $value === null) ? null : (float)$value;
        $pdo->prepare("UPDATE resilience_gvr SET {$field} = :v WHERE id = :id")->execute([':v'=>$value, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $notifMsg = "Admin " . $userIdent . " updated " . $field . " in Green Viability";
        notifyAdmins('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
        notifyFocals('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
        echo json_encode(['ok'=>true]); exit;
      } else {
        throw new Exception('Bad field');
      }
    }
    if ($action === 'set_lock') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE resilience_gvr SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_row') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM resilience_gvr WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted a green viability indicator";
      notifyAdmins('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
      notifyFocals('edit', 'Green Viability Updated', $notifMsg, $id, 'green_viability');
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

$pdo->exec("
  CREATE TABLE IF NOT EXISTS resilience_gvr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indicator VARCHAR(160) DEFAULT NULL,
    share DECIMAL(6,2) DEFAULT NULL,
    y2024 DECIMAL(6,2) DEFAULT NULL,
    y2025 DECIMAL(6,2) DEFAULT NULL,
    y2026 DECIMAL(6,2) DEFAULT NULL,
    y2027 DECIMAL(6,2) DEFAULT NULL,
    y2028 DECIMAL(6,2) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$rows = $pdo->query("SELECT * FROM resilience_gvr ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = [2024,2025,2026,2027,2028];
function overall_for_year(array $rows, int $year): ?float {
  $col = 'y'.$year;
  // Compute total (sum) across all non-Overall rows; ignore nulls
  $sum = 0.0; $has = false;
  foreach ($rows as $r) {
    if (strtolower(trim($r['indicator'] ?? '')) === 'overall') continue;
    if ($r[$col] !== null && $r[$col] !== '') { $sum += (float)$r[$col]; $has = true; }
  }
  if (!$has) return null;
  return round($sum, 2);
}
// Ensure an Overall row exists (locked and kept separate)
$overall = null;
foreach ($rows as $r) {
  if (strtolower(trim($r['indicator'] ?? '')) === 'overall') { $overall = $r; break; }
}
if (!$overall) {
  $pdo->prepare("INSERT INTO resilience_gvr (indicator, share, locked, created_by) VALUES ('Overall', 100, 1, :cb)")
      ->execute([':cb'=>$userId ?: null]);
  $rows = $pdo->query("SELECT * FROM resilience_gvr ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) { if (strtolower(trim($r['indicator'] ?? '')) === 'overall') { $overall = $r; break; } }
}
// Compute overall per year for dashboard and Overall row display
$ov = [];
foreach ($years as $yy) $ov[$yy] = overall_for_year($rows, $yy);
$yearsToShow = array_values(array_filter($years, fn($yy) => $ov[$yy] !== null));
// Build dataRows excluding Overall
$dataRows = array_values(array_filter($rows, fn($x)=> strtolower(trim($x['indicator'] ?? '')) !== 'overall'));
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Governance Scorecard: Green Viability Rating'; ?>
<?php $pageStyles = page_css('css/pages/resilience_roadmap_green_viability.css');
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<main class="container flex-grow-1" pt-110>
  <div class="header-wrap">
    <img src="/PGS/img/resilience_logo.png" alt="Resilience" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
    <div class="header-title">
      <h4>Governance Scorecard: Green Viability Rating</h4>
      <small class="muted">Means of Verification:</small>
    </div>
  </div>

  <?php if (!empty($yearsToShow)): ?>
  <div class="row g-3 mb-3">
    <?php foreach ($yearsToShow as $yr): $p=$ov[$yr]; $stars=0; if ($p!==null){ if ($p>=75) $stars=3; elseif ($p>=50) $stars=2; elseif ($p>0) $stars=1; } ?>
    <div class="col-12 col-md-4">
      <div class="card dash-card">
        <div class="card-header"><?= $yr ?></div>
        <div class="card-body">
          <div class="stars">
            <span class="star <?= $stars>=1?'active':'' ?>">★</span>
            <span class="star <?= $stars>=2?'active':'' ?>">★</span>
            <span class="star <?= $stars>=3?'active':'' ?>">★</span>
          </div>
          <div class="mt-2 fw-bold" style="font-size:1.6rem;"><?= number_format($p,2) ?>%</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="section-title">Table 1. Performance Indicators Score per Year</span>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Wrap">Expand/Minimize</button>
      </div>
    </div>
    <div class="card-body">
      <?php if ($role === 'admin'): ?>
      <form id="formAdd" class="row g-2 mb-3">
        <div class="col-12 col-md-6">
          <input type="text" class="form-control form-control-sm" name="indicator" placeholder="Performance Indicator">
        </div>
        <div class="col-6 col-md-2">
          <input type="number" step="0.01" class="form-control form-control-sm" name="share" placeholder="Percentage Share">
        </div>
        <div class="col-6 col-md-1">
          <input type="number" min="2024" max="2028" class="form-control form-control-sm" name="year" placeholder="Year">
        </div>
        <div class="col-6 col-md-2">
          <input type="number" step="0.01" class="form-control form-control-sm" name="year_value" placeholder="Percent for Year">
        </div>
        <div class="col-12 col-md-1 d-grid">
          <button type="submit" class="btn btn-success btn-sm">Add Row</button>
        </div>
      </form>
      <?php endif; ?>
      <div class="table-responsive collapse show" id="t1Wrap">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Performance Indicator</th>
              <th>Percentage Share</th>
              <th>2024</th>
              <th>2025</th>
              <th>2026</th>
              <th>2027</th>
              <th>2028</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody id="tbody">
            <?php
              // Render Overall row pinned at the top
              $overallVals = [];
              foreach ([2024,2025,2026,2027,2028] as $yy) { $overallVals[$yy] = overall_for_year($rows, $yy); }
            ?>
            <tr data-id="<?= (int)($overall['id'] ?? 0) ?>">
              <td><strong>Overall</strong></td>
              <td class="text-center">100%</td>
              <?php foreach ([2024,2025,2026,2027,2028] as $yy): ?>
                <td class="text-center"><strong><?= $overallVals[$yy] !== null ? number_format((float)$overallVals[$yy],2) : '' ?></strong></td>
              <?php endforeach; ?>
              <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
            </tr>
            <?php foreach ($dataRows as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><?= htmlspecialchars($r['indicator'] ?? '') ?></td>
                <td class="text-center">
                  <?php if ($role === 'admin'): ?>
                    <input type="number" step="0.01" class="form-control form-control-sm js-text" data-field="share" value="<?= $r['share']!==null? (float)$r['share'] : '' ?>">
                  <?php else: ?>
                    <?= $r['share']!==null? number_format((float)$r['share'],2) : '' ?><?= $r['share']!==null?'%':'' ?>
                  <?php endif; ?>
                </td>
                <?php foreach ([2024,2025,2026,2027,2028] as $yy): $col='y'.$yy; $val=$r[$col]; ?>
                  <td class="text-center">
                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                      <input type="number" step="0.01" class="form-control form-control-sm js-num" data-field="<?= $col ?>" value="<?= $val!==null? (float)$val : '' ?>" <?= $r['locked']? 'disabled' : '' ?>>
                    <?php else: ?>
                      <?= $val!==null? number_format((float)$val,2) : '' ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($role === 'admin'): ?>
                <td class="text-nowrap">
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
<script src="<?= asset('js/pages/resilience_roadmap_green_viability_1.js') ?>"></script>
</body>
</html>
