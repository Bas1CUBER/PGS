<?php
global $conn;
require_once __DIR__ . '/../Auth/access_guard.php';
if (!isset($moduleKey)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
$modules = require PGS_SRC . '/Modules/module_config.php';
if (!isset($modules[$moduleKey])) {
    die('Invalid module');
}
$mod = $modules[$moduleKey];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, $mod['roles'], true)) {
    header('Location: ' . BASE_URL . '/login');
    exit();
}
require_page_access('roadmaps');
$role = session_get('role') ?? '';
$userId = (int)(session_get('user_id') ?? 0);
$table = $mod['table'];
$progressTable = $mod['progress_table'];

// Progress tracking table
try {
    $conn->query("CREATE TABLE IF NOT EXISTS {$progressTable} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    month TINYINT NOT NULL,
    status ENUM('Not Accomplished/Started','Ongoing','Accomplished') NOT NULL,
    remarks TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_progress (category, year, month)
  )");
    $conn->query("ALTER TABLE {$progressTable} MODIFY COLUMN status ENUM('Not Accomplished/Started','Ongoing','Accomplished') NOT NULL");
    $conn->query("ALTER TABLE {$progressTable} ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL");
    $conn->query("CREATE TABLE IF NOT EXISTS progress_pending_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    change_type ENUM('add_row','save_progress') NOT NULL,
    category VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    month TINYINT NULL,
    status ENUM('Not Accomplished/Started','Ongoing','Accomplished') NULL,
    remarks TEXT NULL,
    description TEXT NULL,
    submitted_by INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decision ENUM('Pending','Approved','Rejected') DEFAULT 'Pending'
  )");
} catch (Throwable $e) {
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired form token. Please try again.']);
        exit;
    }
    header('Content-Type: application/json');
    $action = $_POST['action'];
    try {
        if ($action === 'delete_row') {
            if ($role !== 'admin') {
                echo json_encode(['status' => 'error','message' => 'Unauthorized']);
                exit;
            }
            $category = trim($_POST['category'] ?? '');
            if (!$category) {
                echo json_encode(['status' => 'error','message' => 'Invalid category']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE category = ?");
            $stmt->bind_param('s', $category);
            $ok = $stmt->execute();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error','message' => $conn->error]);
            exit;
        }
        if ($action === 'save_progress') {
            if (!in_array($role, ['employee','focal'], true)) {
                echo json_encode(['ok' => false,'msg' => 'Read-only']);
                exit;
            }
            $category = trim($_POST['category'] ?? '');
            $year = (int)($_POST['year'] ?? 0);
            $month = (int)($_POST['month'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
            $description = isset($_POST['description']) ? trim($_POST['description']) : null;
            $allowed = ['Not Accomplished/Started','Ongoing','Accomplished'];
            if (!$category || !$year || $month < 1 || $month > 12 || !in_array($status, $allowed, true)) {
                echo json_encode(['ok' => false,'msg' => 'Invalid input']);
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO progress_pending_changes (module, change_type, category, year, month, status, remarks, description, submitted_by) VALUES (?, 'save_progress', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssiisssi', $moduleKey, $category, $year, $month, $status, $remarks, $description, $userId);
            echo json_encode(['ok' => $stmt->execute()]);
            exit;
        }
        if ($action === 'get_progress') {
            $year = (int)($_POST['year'] ?? 0);
            $data = [];
            if ($year) {
                $stmt = $conn->prepare("SELECT category, month, status, remarks, description FROM {$progressTable} WHERE year = ?");
                $stmt->bind_param('i', $year);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $cat = $row['category'];
                    $m = (int)$row['month'];
                    if (!isset($data[$cat])) {
                        $data[$cat] = [];
                    }
                    $data[$cat][$m] = ['status' => $row['status'], 'remarks' => $row['remarks'], 'description' => $row['description']];
                }
            }
            echo json_encode(['ok' => true,'progress' => $data]);
            exit;
        }
        if ($action === 'add_row_item') {
            if (!in_array($role, ['employee','focal'], true)) {
                echo json_encode(['ok' => false,'msg' => 'Read-only']);
                exit;
            }
            $category = trim($_POST['category'] ?? '');
            $year = (int)($_POST['year'] ?? 0);
            $desc = trim($_POST['description'] ?? '');
            if (!$category || !$year || !$desc) {
                echo json_encode(['ok' => false,'msg' => 'Missing fields']);
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO progress_pending_changes (module, change_type, category, year, description, submitted_by) VALUES (?, 'add_row', ?, ?, ?, ?)");
            $stmt->bind_param('ssisi', $moduleKey, $category, $year, $desc, $userId);
            echo json_encode(['ok' => $stmt->execute()]);
            exit;
        }
        if ($action === 'delete_progress_cell') {
            if ($role !== 'admin') {
                echo json_encode(['ok' => false,'msg' => 'Unauthorized']);
                exit;
            }
            $category = trim($_POST['category'] ?? '');
            $year = (int)($_POST['year'] ?? 0);
            $month = (int)($_POST['month'] ?? 0);
            if (!$category || !$year || $month < 1 || $month > 12) {
                echo json_encode(['ok' => false,'msg' => 'Invalid input']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM {$progressTable} WHERE category = ? AND year = ? AND month = ?");
            $stmt->bind_param('sii', $category, $year, $month);
            echo json_encode(['ok' => $stmt->execute()]);
            exit;
        }
        if ($action === 'approve_pending') {
            if ($role !== 'admin') {
                echo json_encode(['ok' => false,'msg' => 'Unauthorized']);
                exit;
            }
            $pid = (int)($_POST['id'] ?? 0);
            if ($pid <= 0) {
                echo json_encode(['ok' => false,'msg' => 'Invalid']);
                exit;
            }
            $stmt = $conn->prepare("SELECT * FROM progress_pending_changes WHERE id = ? AND module = ? AND decision = 'Pending'");
            $stmt->bind_param('is', $pid, $moduleKey);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            if (!$row) {
                echo json_encode(['ok' => false,'msg' => 'Not found']);
                exit;
            }
            if ($row['change_type'] === 'add_row') {
                $ins = $conn->prepare("INSERT INTO {$table} (category, year, description) VALUES (?, ?, ?)");
                $ins->bind_param('sis', $row['category'], $row['year'], $row['description']);
                $ok = $ins->execute();
            } else {
                $updBy = (int)$row['submitted_by'];
                $ins = $conn->prepare("INSERT INTO {$progressTable} (category,year,month,status,remarks,description,updated_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), remarks=VALUES(remarks), description=IFNULL(VALUES(description), description), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP");
                $ins->bind_param('siisssi', $row['category'], $row['year'], $row['month'], $row['status'], $row['remarks'], $row['description'], $updBy);
                $ok = $ins->execute();
            }
            if ($ok) {
                $del = $conn->prepare('DELETE FROM progress_pending_changes WHERE id = ?');
                $del->bind_param('i', $pid);
                $del->execute();
            }
            echo json_encode(['ok' => $ok]);
            exit;
        }
        if ($action === 'reject_pending') {
            if ($role !== 'admin') {
                echo json_encode(['ok' => false,'msg' => 'Unauthorized']);
                exit;
            }
            $pid = (int)($_POST['id'] ?? 0);
            if ($pid <= 0) {
                echo json_encode(['ok' => false,'msg' => 'Invalid']);
                exit;
            }
            $stmt = $conn->prepare('DELETE FROM progress_pending_changes WHERE id = ?');
            $stmt->bind_param('i', $pid);
            echo json_encode(['ok' => $stmt->execute()]);
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false,'msg' => $e->getMessage()]);
        exit;
    }
}

// Data fetching
$yearsResult = $conn->query("SELECT DISTINCT year FROM {$table} ORDER BY year ASC");
$years = [];
while ($row = $yearsResult->fetch_assoc()) {
    $years[] = $row['year'];
}

$categoriesResult = $conn->query("SELECT DISTINCT category FROM {$table}");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

$data = [];
foreach ($categories as $cat) {
    $data[$cat] = array_fill_keys($years, []);
    $stmt = $conn->prepare("SELECT year, description FROM {$table} WHERE category = ?");
    $stmt->bind_param('s', $cat);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $data[$cat][$row['year']][] = $row['description'];
    }
}

$baseUrl = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = $mod['title'] ?? 'PGS'; ?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
<?php include PGS_TEMPLATES . '/navbar.php'; ?>
<div class="page-wrapper container my-5">
  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <div class="d-flex align-items-center mb-3">
        <img src="<?= $baseUrl ?>/assets/img/<?= h($mod['image']) ?>" alt="<?= h($mod['title']) ?>" style="max-width: 100px; height: auto;" class="me-3">
        <h1 class="card-title h3 text-secondary fw-bold mb-0"><?= $mod['title'] ?></h1>
      </div>
      <p class="text-muted" style="text-align: justify;"><?= h($mod['description']) ?></p>
    </div>
  </div>

  <!-- Roadmap Links -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3"><i data-lucide="map" class="me-2"></i>Roadmaps</h6>
      <div class="d-flex gap-2 flex-wrap">
        <?php foreach ($mod['roadmaps'] as $rp): ?>
          <a href="<?= h($rp) ?>" class="btn btn-outline-primary btn-sm"><?= h(pathinfo($rp, PATHINFO_FILENAME)) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Pending Approvals (admin only) -->
  <?php if ($role === 'admin'): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3"><i data-lucide="inbox" class="me-2"></i>Pending Approvals</h6>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead><tr><th>Uploader</th><th>Category</th><th>Year</th><th>Type</th><th>Details</th><th>Time</th><th>Actions</th></tr></thead>
          <tbody>
            <?php
            $pend = [];
      try {
          $q = $conn->query("SELECT p.*, u.email AS submitter_email FROM progress_pending_changes p JOIN users u ON p.submitted_by = u.id WHERE p.module = '{$moduleKey}' AND p.decision = 'Pending' ORDER BY p.submitted_at DESC");
          while ($q && ($r = $q->fetch_assoc())) {
              $pend[] = $r;
          }
      } catch (Throwable $e) {
      }
      if (empty($pend)): ?>
            <tr><td colspan="7" class="text-center text-muted">No pending changes</td></tr>
            <?php else: foreach ($pend as $p): ?>
            <tr>
              <td><?= h($p['submitter_email'] ?? (string)$p['submitted_by']) ?></td>
              <td><?= h($p['category']) ?></td>
              <td><?= (int)$p['year'] ?></td>
              <td><?= $p['change_type'] === 'add_row' ? 'Add Row' : 'Progress Update' ?></td>
              <td><?php if ($p['change_type'] === 'add_row'): ?><?= h($p['description']) ?><?php else: ?><div><strong>Month:</strong> <?= (int)$p['month'] ?></div><div><strong>Status:</strong> <?= h($p['status']) ?></div><div><strong>Remarks:</strong> <?= h($p['remarks']) ?></div><div><strong>Description:</strong> <?= h($p['description']) ?></div><?php endif; ?></td>
              <td><?= h(date('Y-m-d H:i:s', strtotime($p['submitted_at']))) ?></td>
              <td>
                <button class="btn btn-sm btn-success me-2 btn-approve" data-id="<?= (int)$p['id'] ?>">Approve</button>
                <button class="btn btn-sm btn-danger btn-reject" data-id="<?= (int)$p['id'] ?>">Reject</button>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Dashboard -->
  <div class="dashboard-container">
    <div class="dashboard-card">
      <h6><i data-lucide="chart-pie" class="me-2"></i>Status Overview</h6>
      <div class="chart-container"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="dashboard-card">
      <h6><i data-lucide="list-checks" class="me-2"></i>Status Summary</h6>
      <div class="status-summary">
        <div class="status-item" id="statusAccomplished"><div class="status-dot accomplished"></div><div class="status-info"><div class="status-label">Accomplished</div></div><div class="status-count" id="countAccomplished">0</div></div>
        <div class="status-item" id="statusOngoing"><div class="status-dot ongoing"></div><div class="status-info"><div class="status-label">Ongoing</div></div><div class="status-count" id="countOngoing">0</div></div>
        <div class="status-item" id="statusNotAccomplished"><div class="status-dot not-accomplished"></div><div class="status-info"><div class="status-label">Not Accomplished/Started</div></div><div class="status-count" id="countNotAccomplished">0</div></div>
      </div>
    </div>
    <div class="dashboard-card year-selector-card">
      <h6><i data-lucide="calendar" class="me-2"></i>Select Year</h6>
      <select id="dashboardYear" class="form-select"><?php foreach ($years as $y): ?><option value="<?= h($y) ?>"><?= h($y) ?></option><?php endforeach; ?></select>
      <div class="mt-3"><small class="text-muted">Total Entries: <strong id="totalEntries">0</strong></small></div>
    </div>
  </div>
  <div id="dashboardTooltip" class="dashboard-tooltip"></div>

  <!-- Progress Tracker -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-semibold mb-0">Progress Tracker</h5>
        <div class="d-flex align-items-center gap-2">
          <label class="form-label mb-0">Year</label>
          <select id="progressYear" class="form-select form-select-sm" style="width:140px"><?php foreach ($years as $y): ?><option value="<?= h($y) ?>"><?= h($y) ?></option><?php endforeach; ?></select>
          <?php if (in_array($role, ['employee','focal'], true)): ?>
          <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#addRowModal" style="background-color: #196a6b; color: #ffffff; border: none;"><i data-lucide="plus-circle" class="me-1"></i> Add Row</button>
          <?php endif; ?>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#progressCollapse" aria-expanded="true">Hide</button>
        </div>
      </div>
      <div class="table-responsive collapse show" id="progressCollapse">
        <table class="table table-bordered" id="progressTable">
          <thead class="table-light text-center">
            <tr><th colspan="14" id="ptYear">&mdash;</th></tr>
            <tr><th>Category</th><th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th><th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th></tr>
          </thead>
          <tbody id="progressBody"></tbody>
        </table>
      </div>
      <small class="text-muted">Employees and Focal can update statuses with remarks; Admin is read-only.</small>
    </div>
  </div>

  <!-- Key Initiatives Table -->
  <div class="card shadow-sm mb-5">
    <div class="card-body table-responsive">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0 me-3">Key Initiatives by Year</h5>
        <div class="d-flex gap-2">
          <?php if ($role === 'admin'): ?>
          <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#addYearModal" style="background-color: #196a6b; color: #ffffff; border: none;"><i data-lucide="plus-circle" class="me-1"></i> Add Year</button>
          <select id="yearDropdown" class="form-select form-select-sm" style="height:40px;font-size:0.875rem;width:120px;background-color:#f1f5f9;border:1px solid #94a3b8;color:#1e293b;" data-edit-url="<?= $baseUrl ?>/modules/get_year_data.php?module=<?= h($moduleKey) ?>">
            <option value="">Select a Year</option>
            <?php foreach ($years as $year): ?><option value="<?= h($year) ?>"><?= h($year) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm" id="editYearBtn" disabled style="background-color:#fcd34d;color:#1e293b;border:none;"><i data-lucide="pencil"></i> Edit</button>
          <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#deleteYearModal" style="background-color:#f87171;color:#fff;border:none;"><i data-lucide="trash-2" class="me-1"></i> Delete Year</button>
          <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#deleteRowModal" style="background-color:#f87171;color:#fff;border:none;"><i data-lucide="minus-circle" class="me-1"></i> Delete Row</button>
          <?php endif; ?>
        </div>
      </div>
      <table class="table table-bordered align-middle">
        <thead class="table-light text-center">
          <tr><th>Key Result Areas</th><?php foreach ($years as $year): ?><th><?= h($year) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
          <tr>
            <td class="fw-semibold"><?= h($category) ?></td>
            <?php foreach ($years as $year): ?>
            <td><?php if (isset($data[$category][$year]) && !empty($data[$category][$year])): ?><ul class="mb-0"><?php foreach ($data[$category][$year] as $item): ?><li><?= h($item) ?></li><?php endforeach; ?></ul><?php endif; ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addYearModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="<?= $baseUrl ?>/modules/add_year?module=<?= h($moduleKey) ?>" id="addYearForm">
      <?= csrf_field() ?>
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Year and Key Area Descriptions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-4"><label class="form-label fw-semibold">Enter Year</label><input type="number" class="form-control" id="year" name="year" required min="2023" placeholder="e.g., 2024"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Add Key Areas</label>
            <button type="button" class="btn btn-outline-primary w-100 mb-3" id="addCategoryBtn"><i data-lucide="plus" class="me-1"></i> Add Key Area</button>
            <div id="categoriesContainer"></div>
          </div>
          <div class="alert alert-info"><strong>Tip:</strong> You can add multiple descriptions under each Key Area.</div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary" id="saveButton" disabled><i data-lucide="save" class="me-1"></i> Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editYearModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="<?= $baseUrl ?>/modules/edit_year?module=<?= h($moduleKey) ?>" id="editYearForm">
      <?= csrf_field() ?>
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Edit Year and Key Area Descriptions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="year_id" id="editYearId">
          <div class="mb-4"><label class="form-label fw-semibold">Year</label><input type="number" class="form-control" id="editYear" name="year" required min="2023"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Edit Key Areas</label>
            <button type="button" class="btn btn-outline-primary w-100 mb-3" id="editAddCategoryBtn" disabled><i data-lucide="plus" class="me-1"></i> Add Key Area</button>
            <div id="editCategoriesContainer"></div>
            <input type="hidden" name="categories" id="categoriesInput">
          </div>
          <div class="alert alert-info"><strong>Note:</strong> Modify descriptions or add new key areas below.</div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-success"><i data-lucide="save" class="me-1"></i> Update</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
      </div>
    </form>
  </div>
</div>

<!-- Delete Year Modal -->
<div class="modal fade" id="deleteYearModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= $baseUrl ?>/modules/delete_year?module=<?= h($moduleKey) ?>" id="deleteYearForm">
      <?= csrf_field() ?>
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Delete Year</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label for="yearToDelete" class="form-label">Select Year to Delete</label>
            <select class="form-select" id="yearToDelete" name="year" required><option value="">-- Select Year --</option><?php foreach ($years as $year): ?><option value="<?= h($year) ?>"><?= h($year) ?></option><?php endforeach; ?></select>
          </div>
          <div class="alert alert-warning"><strong>Warning:</strong> This will permanently delete all data for the selected year.</div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-danger"><i data-lucide="trash-2" class="me-1"></i> Delete</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
      </div>
    </form>
  </div>
</div>

<!-- Delete Row Modal -->
<div class="modal fade" id="deleteRowModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="deleteRowForm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Delete Row (Key Area)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label for="rowToDelete" class="form-label">Select Key Area to Delete</label>
            <select class="form-select" id="rowToDelete" name="category" required><option value="">-- Select Key Area --</option><?php foreach ($categories as $cat): ?><option value="<?= h($cat) ?>"><?= h($cat) ?></option><?php endforeach; ?></select>
          </div>
          <div class="alert alert-warning"><strong>Warning:</strong> This will delete the entire row and all its associated data.</div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-danger"><i data-lucide="trash-2" class="me-1"></i> Delete</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
      </div>
    </form>
  </div>
</div>

<!-- Add Row Modal -->
<div class="modal fade" id="addRowModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="addRowForm">
      <div class="modal-header"><h5 class="modal-title">Add Row</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Category</label><select class="form-select" name="category" required><option value="">Select Category</option><?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Year</label><select class="form-select" name="year" required><option value="">Select Year</option><?php foreach ($years as $y): ?><option value="<?= (int)$y ?>"><?= (int)$y ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Month</label><select class="form-select" name="month" required><option value="">Select Month</option><?php for ($mi = 1;$mi <= 12;$mi++): ?><option value="<?= $mi ?>"><?= date('M', mktime(0, 0, 0, $mi, 1)) ?></option><?php endfor; ?></select></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" required><option value="">Select Status</option><option value="Not Accomplished/Started">Not Accomplished/Started</option><option value="Ongoing">Ongoing</option><option value="Accomplished">Accomplished</option></select></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save</button></div>
    </form>
  </div>
</div>

<?php include PGS_TEMPLATES . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $pgsPage = ['role' => $role, 'categories' => $categories, 'years' => $years]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/src_Modules_module_page_1.js') ?>"></script>
<script src="<?= $baseUrl ?>/assets/js/module.js"></script>
</body>
</html>
