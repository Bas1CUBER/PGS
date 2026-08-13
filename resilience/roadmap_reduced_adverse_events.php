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
      $category = trim($_POST['category'] ?? '');
      $type = trim($_POST['type'] ?? '');
      $stmt = $pdo->prepare("INSERT INTO resilience_adverse_events (category, type, created_by) VALUES (:c,:t,:cb)");
      $stmt->execute([':c'=>$category?:null, ':t'=>$type?:null, ':cb'=>$userId ?: null]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added adverse event: " . ($category ?: 'N/A') . " - " . ($type ?: 'N/A');
      notifyAdmins('upload', 'Adverse Events Updated', $notifMsg, $newId, 'adverse_events');
      notifyFocals('upload', 'Adverse Events Updated', $notifMsg, $newId, 'adverse_events');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_cell') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNum = ['y2024','y2025','y2026','y2027'];
      $allowedText = ['category','type'];
      if (in_array($field, $allowedNum, true)) {
        if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (int)$value;
        $pdo->prepare("UPDATE resilience_adverse_events SET {$field} = :v WHERE id = :id")->execute([':v'=>$val, ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $roleLabel = ucfirst($role);
        $notifMsg = $roleLabel . " " . $userIdent . " updated " . strtoupper($field) . " in Adverse Events";
        notifyAdmins('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
        notifyFocals('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
        echo json_encode(['ok'=>true]); exit;
      } elseif (in_array($field, $allowedText, true)) {
        if ($role !== 'admin') throw new Exception('Admin only');
        $pdo->prepare("UPDATE resilience_adverse_events SET {$field} = :v WHERE id = :id")->execute([':v'=>trim((string)$value), ':id'=>$id]);
        // Notify all users
        $userInfo = getUserInfo($userId);
        $userIdent = formatUserIdentifier($userInfo);
        $notifMsg = "Admin " . $userIdent . " updated " . $field . " in Adverse Events";
        notifyAdmins('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
        notifyFocals('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
        echo json_encode(['ok'=>true]); exit;
      } else { throw new Exception('Bad field'); }
    }
    if ($action === 'set_lock') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE resilience_adverse_events SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_row') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM resilience_adverse_events WHERE id = :id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted an adverse event entry";
      notifyAdmins('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
      notifyFocals('edit', 'Adverse Events Updated', $notifMsg, $id, 'adverse_events');
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'add_note') {
      if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
      $label = trim($_POST['label'] ?? '');
      $pdo->prepare("INSERT INTO resilience_adverse_notes (label, created_by) VALUES (:l,:cb)")
          ->execute([':l'=>$label?:null, ':cb'=>$userId ?: null]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'save_note') {
      $id = (int)($_POST['id'] ?? 0);
      $field = $_POST['field'] ?? '';
      $value = $_POST['value'] ?? null;
      $allowedNumNotes = ['y2024','y2025','y2026','y2027'];
      if ($field === 'label') {
        if ($role !== 'admin') throw new Exception('Admin only');
        $pdo->prepare("UPDATE resilience_adverse_notes SET {$field} = :v WHERE id = :id")->execute([':v'=>$value, ':id'=>$id]);
      } elseif (in_array($field, $allowedNumNotes, true)) {
        if (!in_array($role, ['admin','focal'], true)) throw new Exception('Not allowed');
        $val = ($value === '' || $value === null) ? null : (int)$value;
        $pdo->prepare("UPDATE resilience_adverse_notes SET {$field} = :v WHERE id = :id")->execute([':v'=>$val, ':id'=>$id]);
      } else { throw new Exception('Bad field'); }
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'set_lock_note') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $locked = (int)($_POST['locked'] ?? 0) ? 1 : 0;
      $pdo->prepare("UPDATE resilience_adverse_notes SET locked = :l WHERE id = :id")->execute([':l'=>$locked, ':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'delete_note') {
      if ($role !== 'admin') throw new Exception('Admin only');
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM resilience_adverse_notes WHERE id = :id")->execute([':id'=>$id]);
      echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    exit;
  }
}

// Setup tables
$pdo->exec("
  CREATE TABLE IF NOT EXISTS resilience_adverse_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(120) DEFAULT NULL,
    type VARCHAR(160) DEFAULT NULL,
    y2024 INT DEFAULT NULL,
    y2025 INT DEFAULT NULL,
    y2026 INT DEFAULT NULL,
    y2027 INT DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS resilience_adverse_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(160) DEFAULT NULL,
    y2024 INT DEFAULT NULL,
    y2025 INT DEFAULT NULL,
    y2026 INT DEFAULT NULL,
    y2027 INT DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
// Migrate older schema (adds year columns if they don't exist)
try { $pdo->exec("ALTER TABLE resilience_adverse_notes ADD COLUMN y2024 INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE resilience_adverse_notes ADD COLUMN y2025 INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE resilience_adverse_notes ADD COLUMN y2026 INT DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE resilience_adverse_notes ADD COLUMN y2027 INT DEFAULT NULL"); } catch (Throwable $e) {}

// Seed two default notes if empty
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM resilience_adverse_notes")->fetchColumn();
if ($cnt === 0) {
  $pdo->prepare("INSERT INTO resilience_adverse_notes (label, created_by) VALUES ('Bed Capacity', :cb)")
      ->execute([':cb'=>$userId?:null]);
  $pdo->prepare("INSERT INTO resilience_adverse_notes (label, created_by) VALUES ('Rate', :cb)")
      ->execute([':cb'=>$userId?:null]);
}

// Fetch data
$rows = $pdo->query("SELECT * FROM resilience_adverse_events ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$notes = $pdo->query("SELECT * FROM resilience_adverse_notes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build chart data: labels from type, datasets by year
$labels = array_values(array_map(fn($r)=> $r['type'] ?? '', $rows));
$d2024 = array_values(array_map(fn($r)=> (int)($r['y2024'] ?? 0), $rows));
$d2025 = array_values(array_map(fn($r)=> (int)($r['y2025'] ?? 0), $rows));
$d2026 = array_values(array_map(fn($r)=> (int)($r['y2026'] ?? 0), $rows));
$d2027 = array_values(array_map(fn($r)=> (int)($r['y2027'] ?? 0), $rows));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Governance Scorecard: Reduced Preventable Adverse Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  =2'>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
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
    .input-sm { max-width:6rem; }
    .chart-card .card-body { padding: 8px 10px 6px; }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<main class="container flex-grow-1" style="padding-top:110px;">
  <div class="header-wrap">
    <img src="/PGS/img/resilience_logo.png" alt="Resilience" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
    <div class="header-title">
      <h4>Governance Scorecard: Reduced Preventable Adverse Events</h4>
      <small class="muted">Means of Verification:</small>
    </div>
  </div>

  <div class="card mb-4 chart-card">
    <div class="card-header">Occurrence of Adverse Events</div>
    <div class="card-body">
      <canvas id="chart" height="120"></canvas>
      <div id="noData" class="text-center text-muted mt-2" style="<?= count($labels)?'display:none;':'' ?>">No data yet</div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="section-title">Table 1. Adverse Events</span>
      <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#t1Wrap">Expand/Minimize</button>
    </div>
    <div class="card-body">
      <?php if (in_array($role, ['admin','focal'], true)): ?>
      <form id="formAdd" class="row g-2 mb-3">
        <div class="col-12 col-md-3">
          <input type="text" class="form-control form-control-sm" name="category" placeholder=" ">
        </div>
        <div class="col-12 col-md-6">
          <input type="text" class="form-control form-control-sm" name="type" placeholder="Type">
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
              <th style="width:140px;"></th>
              <th>Type</th>
              <th>2024</th>
              <th>2025</th>
              <th>2026</th>
              <th>2027</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td>
                  <?php if ($role === 'admin'): ?>
                    <input class="form-control form-control-sm js-text" data-field="category" value="<?= htmlspecialchars($r['category'] ?? '') ?>" <?= $r['locked']?'disabled':'' ?>>
                  <?php else: ?>
                    <?= htmlspecialchars($r['category'] ?? '') ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($role === 'admin'): ?>
                    <input class="form-control form-control-sm js-text" data-field="type" value="<?= htmlspecialchars($r['type'] ?? '') ?>" <?= $r['locked']?'disabled':'' ?>>
                  <?php else: ?>
                    <?= htmlspecialchars($r['type'] ?? '') ?>
                  <?php endif; ?>
                </td>
                <?php foreach (['y2024','y2025','y2026','y2027'] as $col): $val=$r[$col]; ?>
                  <td class="text-center">
                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                      <input type="number" min="0" step="1" class="form-control form-control-sm input-sm js-num" data-field="<?= $col ?>" value="<?= $val!==null?(int)$val:'' ?>" <?= $r['locked']?'disabled':'' ?>>
                    <?php else: ?>
                      <?= $val!==null?(int)$val:'' ?>
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

  <div class="card mb-4">
    <div class="card-body">
      <?php if (in_array($role, ['admin','focal'], true)): ?>
      <form id="formAddNote" class="row g-2 mb-3">
        <div class="col-12 col-md-6">
          <input type="text" class="form-control form-control-sm" name="label" placeholder="Label (e.g., Bed Capacity)">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button type="submit" class="btn btn-primary btn-sm">Add</button>
        </div>
      </form>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr class="text-center">
              <th>Label</th>
              <th>2024</th>
              <th>2025</th>
              <th>2026</th>
              <th>2027</th>
              <?php if ($role === 'admin'): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notes as $n): ?>
              <tr data-id="<?= (int)$n['id'] ?>">
                <td>
                  <?php if ($role === 'admin'): ?>
                    <input class="form-control form-control-sm js-note-text" data-field="label" value="<?= htmlspecialchars($n['label'] ?? '') ?>" <?= $n['locked']?'disabled':'' ?>>
                  <?php else: ?>
                    <?= htmlspecialchars($n['label'] ?? '') ?>
                  <?php endif; ?>
                </td>
                <?php foreach (['y2024','y2025','y2026','y2027'] as $col): $val = $n[$col] ?? null; ?>
                  <td class="text-center">
                    <?php if (in_array($role, ['admin','focal'], true)): ?>
                      <input type="number" min="0" step="1" class="form-control form-control-sm input-sm js-note-num" data-field="<?= $col ?>" value="<?= $val!==null?(int)$val:'' ?>" <?= $n['locked']?'disabled':'' ?>>
                    <?php else: ?>
                      <?= $val!==null?(int)$val:'' ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($role === 'admin'): ?>
                <td class="text-nowrap">
                  <button class="btn btn-sm <?= $n['locked']? 'btn-secondary' : 'btn-outline-secondary' ?> js-lock-note" data-locked="<?= $n['locked']?0:1 ?>"><?= $n['locked']? 'Unlock' : 'Lock' ?></button>
                  <button class="btn btn-sm btn-outline-danger js-del-note">Delete</button>
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
<script>
  // Chart
  const labels = <?= json_encode($labels) ?>;
  const data2024 = <?= json_encode($d2024) ?>;
  const data2025 = <?= json_encode($d2025) ?>;
  const data2026 = <?= json_encode($d2026) ?>;
  const data2027 = <?= json_encode($d2027) ?>;
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
      options: {
        responsive: true,
        scales: { y: { beginAtZero:true, ticks:{ stepSize:1 } } }
      }
    });
  }

  // Main table
  document.querySelectorAll('.js-text').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_cell');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_cell');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','set_lock');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_row');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });

  // Add row
  const formAdd = document.getElementById('formAdd');
  if (formAdd) {
    formAdd.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdd);
      fd.append('action','add_row');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }

  // Notes table
  const formAddNote = document.getElementById('formAddNote');
  if (formAddNote) {
    formAddNote.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddNote);
      fd.append('action','add_note');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }
  document.querySelectorAll('.js-note-text').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_note');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-note-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_note');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-lock-note').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','set_lock_note');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-del-note').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_note');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });
</script>
</body>
</html>
