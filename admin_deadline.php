<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  header("Location: " . BASE_URL . "/login");
  exit();
}
// Create table if not exists
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS deadline_controls (
      role ENUM('employee','focal') PRIMARY KEY,
      enabled TINYINT(1) NOT NULL DEFAULT 0,
      end_time DATETIME DEFAULT NULL,
      message VARCHAR(255) DEFAULT 'Please comply with the submission requirements before the deadline.',
      updated_by INT DEFAULT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) {}

$roles = ['employee','focal'];
$data = [];
foreach ($roles as $r) {
  $row = $pdo->prepare("SELECT role, enabled, end_time, message FROM deadline_controls WHERE role = :r");
  $row->execute([':r'=>$r]);
  $data[$r] = $row->fetch(PDO::FETCH_ASSOC) ?: ['role'=>$r,'enabled'=>0,'end_time'=>null,'message'=>'Please comply with the submission requirements before the deadline.'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'msg'=>'Invalid or expired form token.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'set') {
      $role = $_POST['role'] ?? '';
      if (!in_array($role, $roles, true)) throw new Exception('Bad role');
      $days = max(0, (int)($_POST['days'] ?? 0));
      $hours = max(0, (int)($_POST['hours'] ?? 0));
      $minutes = max(0, (int)($_POST['minutes'] ?? 0));
      $msg = trim($_POST['message'] ?? '');
      $intervalSeconds = ($days*86400) + ($hours*3600) + ($minutes*60);
      $enabled = ($intervalSeconds > 0) ? 1 : 0;
      $end = $enabled ? (new DateTime('+'.$intervalSeconds.' seconds'))->format('Y-m-d H:i:s') : null;
      $pdo->prepare("INSERT INTO deadline_controls (role, enabled, end_time, message, updated_by) VALUES (:r,:e,:t,:m,:u)
                     ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), end_time=VALUES(end_time), message=VALUES(message), updated_by=VALUES(updated_by)")
          ->execute([':r'=>$role, ':e'=>$enabled, ':t'=>$end, ':m'=>$msg ?: null, ':u'=>(int)$_SESSION['user_id']]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'reset') {
      $role = $_POST['role'] ?? '';
      if ($role === 'all') {
        foreach ($roles as $rr) {
          $pdo->prepare("INSERT INTO deadline_controls (role, enabled, end_time) VALUES (:r, 0, NULL)
                         ON DUPLICATE KEY UPDATE enabled=0, end_time=NULL")->execute([':r'=>$rr]);
        }
      } else {
        if (!in_array($role, $roles, true)) throw new Exception('Bad role');
        $pdo->prepare("INSERT INTO deadline_controls (role, enabled, end_time) VALUES (:r, 0, NULL)
                       ON DUPLICATE KEY UPDATE enabled=0, end_time=NULL")->execute([':r'=>$role]);
      }
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false, 'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
    exit;
  }
}
$pageTitle = 'Deadline Controls';
$pageStyles = <<<'STYLES'
body { background:#f5f7fa; color:#1f2937; }
.card { border:none; border-radius:1rem; box-shadow:0 6px 18px rgba(0,0,0,.06); }
.card-header { background:#0b4aa2; color:#fff; border-radius:1rem 1rem 0 0; font-weight:700; }
.fixed-info { font-size:.95rem; color:#374151; }
.countdown { font-weight:700; }
.btn-theme { background:#0b4aa2; border-color:#0b4aa2; color:#fff; }
.btn-theme:hover { background:#083a7f; border-color:#083a7f; color:#fff; }
.badge-theme { background:#0b4aa2; color:#fff; }
STYLES;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

  <main class="container flex-grow-1" pt-110>
    <div class="card mb-4">
      <div class="card-header">Deadline Controls</div>
      <div class="card-body">
        <p class="fixed-info mb-3">
          Configure submission windows for Employee and Focal accounts. During an active deadline, a countdown banner appears below the navbar on their pages. When the countdown reaches zero, all interactive actions are disabled and pages become read-only for affected roles.
        </p>
        <div class="row g-4">
          <?php foreach ($roles as $r): $row=$data[$r]; $enabled=(int)$row['enabled']===1; $end=$row['end_time']? new DateTime($row['end_time']) : null; $remaining=$end? max(0, $end->getTimestamp() - time()) : 0; ?>
          <div class="col-12 col-md-6">
            <div class="card h-100">
              <div class="card-header"><?= ucfirst($r) ?> Window</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">Message</label>
                  <input type="text" class="form-control form-control-sm" id="msg_<?= $r ?>" value="<?= h($row['message'] ?? '') ?>" placeholder="Banner message">
                </div>
                <div class="row g-2">
                  <div class="col-4">
                    <label class="form-label">Days</label>
                    <input type="number" min="0" class="form-control form-control-sm" id="days_<?= $r ?>" value="0">
                  </div>
                  <div class="col-4">
                    <label class="form-label">Hours</label>
                    <input type="number" min="0" class="form-control form-control-sm" id="hours_<?= $r ?>" value="0">
                  </div>
                  <div class="col-4">
                    <label class="form-label">Minutes</label>
                    <input type="number" min="0" class="form-control form-control-sm" id="mins_<?= $r ?>" value="0">
                  </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                  <button class="btn btn-theme btn-sm" onclick="setDeadline('<?= $r ?>')">Set Deadline</button>
                  <button class="btn btn-outline-danger btn-sm" onclick="resetDeadline('<?= $r ?>')">Reset</button>
                </div>
                <hr>
                <div class="small">
                  <div>Status: <?= $enabled ? '<span class="badge badge-theme">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></div>
                  <div>End Time: <?= $end ? h($end->format('Y-m-d H:i:s')) : '—' ?></div>
                  <div>Remaining: <span class="countdown" id="rem_<?= $r ?>"><?= $enabled && $end ? $remaining : 0 ?></span> seconds</div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4">
          <button class="btn btn-danger" onclick="resetDeadline('all')">Reset All</button>
        </div>
      </div>
    </div>
  </main>
<?php
$pageScripts = '<script src="' . asset('js/pages/admin_deadline_1.js') . '"></script>';
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

