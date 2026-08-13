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
    CREATE TABLE IF NOT EXISTS research_outputs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      research_no VARCHAR(50) NOT NULL,
      title VARCHAR(255) NOT NULL,
      topic VARCHAR(255) NOT NULL,
      target_year INT NOT NULL,
      phase_status VARCHAR(32) NOT NULL, -- Planning | Data Gathering | Analyzing | Writing
      outcome_status VARCHAR(32) DEFAULT NULL, -- Submitted | Presented | Published
      row_locked TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_research_outputs_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}

$action = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
  header("Content-Type: application/json");
  try {
    if ($action === 'add_output' && in_array($role, ['admin','focal'], true)) {
      $stmt = $pdo->prepare("
        INSERT INTO research_outputs (research_no,title,topic,target_year,phase_status,created_by)
        VALUES (:no,:title,:topic,:year,:phase,:uid)
      ");
      $stmt->execute([
        ':no'=>trim($_POST['research_no'] ?? ''),
        ':title'=>trim($_POST['title'] ?? ''),
        ':topic'=>trim($_POST['topic'] ?? ''),
        ':year'=>(int)($_POST['target_year'] ?? 0),
        ':phase'=>trim($_POST['phase_status'] ?? ''),
        ':uid'=>$userId
      ]);
      $newId = (int)$pdo->lastInsertId();
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $roleLabel = ucfirst($role);
      $notifMsg = $roleLabel . " " . $userIdent . " added research output: " . trim($_POST['title'] ?? '');
      notifyAdmins('upload', 'Research Outputs Updated', $notifMsg, $newId, 'research_outputs');
      notifyFocals('upload', 'Research Outputs Updated', $notifMsg, $newId, 'research_outputs');
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'edit_output' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $pdo->prepare("
        UPDATE research_outputs SET
          research_no=:no, title=:title, topic=:topic, target_year=:year,
          phase_status=:phase, outcome_status=:outcome
        WHERE id=:id
      ");
      $stmt->execute([
        ':no'=>trim($_POST['research_no'] ?? ''),
        ':title'=>trim($_POST['title'] ?? ''),
        ':topic'=>trim($_POST['topic'] ?? ''),
        ':year'=>(int)($_POST['target_year'] ?? 0),
        ':phase'=>trim($_POST['phase_status'] ?? ''),
        ':outcome'=>trim($_POST['outcome_status'] ?? '') ?: null,
        ':id'=>$id
      ]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " edited research output: " . trim($_POST['title'] ?? '');
      notifyAdmins('edit', 'Research Outputs Updated', $notifMsg, $id, 'research_outputs');
      notifyFocals('edit', 'Research Outputs Updated', $notifMsg, $id, 'research_outputs');
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'toggle_lock' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $val = isset($_POST['row_locked']) ? (int)($_POST['row_locked'] ? 1 : 0) : 0;
      $pdo->prepare("UPDATE research_outputs SET row_locked=:v WHERE id=:id")->execute([':v'=>$val, ':id'=>$id]);
      echo json_encode(['success'=>true]); exit();
    }
    if ($action === 'delete_output' && $role === 'admin') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM research_outputs WHERE id=:id")->execute([':id'=>$id]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted a research output";
      notifyAdmins('edit', 'Research Outputs Updated', $notifMsg, $id, 'research_outputs');
      notifyFocals('edit', 'Research Outputs Updated', $notifMsg, $id, 'research_outputs');
      echo json_encode(['success'=>true]); exit();
    }
    echo json_encode(['success'=>false,'error'=>'Invalid action']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'Server error']);
  }
  exit();
}

$canInput = in_array($role, ['admin','focal'], true);
$rows = [];
try {
  $rows = $pdo->query("SELECT * FROM research_outputs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$phaseMap = ['Planning','Data Gathering','Analyzing','Writing'];
$ongoingCount = 0; $submittedCount = 0; $presentedCount = 0; $publishedCount = 0;
$yearSet = [];
foreach ($rows as $r) {
  if (in_array($r['phase_status'], $phaseMap, true)) $ongoingCount++;
  $out = $r['outcome_status'] ?? null;
  if ($out === 'Submitted') $submittedCount++;
  if ($out === 'Presented') $presentedCount++;
  if ($out === 'Published') $publishedCount++;
  $yearSet[$r['target_year']] = true;
}
$years = array_keys($yearSet); sort($years);
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Research: No. of research outputs completed, published or presented'; ?>
<?php $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/research_roadmap_outputs.css') . '">';
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <main class="page-container container">
    <div class="header-wrap">
      <img src="/PGS/img/research_logo.png" alt="Research" class="header-logo" onerror="this.src='/PGS/assets/img/logo.png'">
      <div class="header-title">
        <h4>Research: No. of research outputs completed, published or presented</h4>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center"></div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3"><div class="metric"><div class="label">ON-GOING</div><div class="value"><?= (int)$ongoingCount ?></div></div></div>
          <div class="col-md-3"><div class="metric"><div class="label">COMPLETED</div><div class="value"><?= (int)$submittedCount ?></div></div></div>
          <div class="col-md-3"><div class="metric"><div class="label">PRESENTED</div><div class="value"><?= (int)$presentedCount ?></div></div></div>
          <div class="col-md-3"><div class="metric"><div class="label">PUBLISHED</div><div class="value"><?= (int)$publishedCount ?></div></div></div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Table 1. Summary (auto-generated)</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr class="text-center group-header">
                <th colspan="4">On-going</th>
                <th>Year</th>
                <th>Published</th>
                <th>Presented</th>
              </tr>
              <tr class="text-center">
                <th>Planning</th><th>Data Gathering</th><th>Analyzing</th><th>Writing</th>
                <th>Distinct Target Years</th><th>Total</th><th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr class="text-center">
                <td><?= count(array_filter($rows, fn($x)=>$x['phase_status']==='Planning')) ?></td>
                <td><?= count(array_filter($rows, fn($x)=>$x['phase_status']==='Data Gathering')) ?></td>
                <td><?= count(array_filter($rows, fn($x)=>$x['phase_status']==='Analyzing')) ?></td>
                <td><?= count(array_filter($rows, fn($x)=>$x['phase_status']==='Writing')) ?></td>
                <td><?= h(implode(', ', $years) ?: '-') ?></td>
                <td><?= (int)$publishedCount ?></td>
                <td><?= (int)$presentedCount ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="section-title">Table 2. Research Outputs</span>
        <div>
          <button class="btn btn-sm btn-outline-primary glow-btn" data-bs-toggle="collapse" data-bs-target="#t2Wrap">Expand/Minimize</button>
          <?php if ($canInput): ?><button id="addBtn" class="btn btn-sm btn-primary glow-btn">Add Row</button><?php endif; ?>
        </div>
      </div>
      <div id="t2Wrap" class="collapse">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead>
                <tr class="text-center group-header">
                  <th>Research No.</th><th>Title</th><th>Topic</th><th>Target (Year)</th>
                  <th>Status</th><?php if ($role==='admin'): ?><th>Outcome</th><th>Action</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= h($r['research_no'] ?? '') ?></td>
                    <td><?= h($r['title'] ?? '') ?></td>
                    <td><?= h($r['topic'] ?? '') ?></td>
                    <td><?= h($r['target_year'] ?? '') ?></td>
                    <td><?= h($r['phase_status'] ?? '') ?></td>
                    <?php if ($role==='admin'): ?>
                      <td><?= h($r['outcome_status'] ?? '') ?></td>
                      <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary me-1 edit-row" data-id="<?= (int)$r['id'] ?>">Edit</button>
                        <button class="btn btn-sm <?= ((int)$r['row_locked']===1)?'btn-danger':'btn-success' ?> lock-row" data-id="<?= (int)$r['id'] ?>" data-locked="<?= (int)$r['row_locked'] ?>"><?= ((int)$r['row_locked']===1)?'Unlock':'Lock' ?></button>
                        <button class="btn btn-sm btn-outline-danger ms-1 del-row" data-id="<?= (int)$r['id'] ?>">Delete</button>
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
  </main>

  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form id="editForm" class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Research Output</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f_id">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Research No.</label><input type="text" class="form-control" name="research_no" id="f_no"></div>
            <div class="col-md-8"><label class="form-label">Title</label><input type="text" class="form-control" name="title" id="f_title"></div>
            <div class="col-md-8"><label class="form-label">Topic</label><input type="text" class="form-control" name="topic" id="f_topic"></div>
            <div class="col-md-4"><label class="form-label">Target (Year)</label><input type="number" min="2000" max="2100" class="form-control" name="target_year" id="f_year"></div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="phase_status" id="f_phase">
                <option>Planning</option>
                <option>Data Gathering</option>
                <option>Analyzing</option>
                <option>Writing</option>
              </select>
            </div>
            <?php if ($role==='admin'): ?>
            <div class="col-md-4">
              <label class="form-label">Outcome</label>
              <select class="form-select" name="outcome_status" id="f_outcome">
                <option value="">None</option>
                <option>Submitted</option>
                <option>Presented</option>
                <option>Published</option>
              </select>
            </div>
            <?php endif; ?>
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
  <script>
    const canInput = <?= $canInput ? 'true' : 'false' ?>;
    document.getElementById('addBtn')?.addEventListener('click', () => {
      if (!canInput) return;
      document.getElementById('f_id').value = '';
      ['f_no','f_title','f_topic','f_year'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('f_phase').value = 'Planning';
      <?php if ($role==='admin'): ?>document.getElementById('f_outcome').value = '';<?php endif; ?>
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
    document.querySelectorAll('.edit-row').forEach(btn => btn.addEventListener('click', () => {
      const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
      document.getElementById('f_id').value = btn.getAttribute('data-id');
      document.getElementById('f_no').value = cells[0].textContent.trim();
      document.getElementById('f_title').value = cells[1].textContent.trim();
      document.getElementById('f_topic').value = cells[2].textContent.trim();
      document.getElementById('f_year').value = cells[3].textContent.trim();
      document.getElementById('f_phase').value = cells[4].textContent.trim();
      <?php if ($role==='admin'): ?>document.getElementById('f_outcome').value = cells[5].textContent.trim();<?php endif; ?>
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }));
    document.querySelectorAll('.lock-row').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const locked = btn.getAttribute('data-locked') === '1';
      const fd = new FormData(); fd.append('action','toggle_lock'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.del-row').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const c = await Swal.fire({ icon:'warning', title:'Delete Row?', showCancelButton:true, confirmButtonText:'Delete' }); if(!c.isConfirmed) return;
      const fd = new FormData(); fd.append('action','delete_output'); fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.getElementById('editForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('f_id').value.trim();
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_output' : 'add_output');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { const m = bootstrap.Modal.getInstance(document.getElementById('editModal')); m?.hide(); await Swal.fire({ icon:'success', title:'Changes Saved', timer:1200, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    });
  </script>
</body>
</html>
