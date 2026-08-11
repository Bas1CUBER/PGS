<?php
require_once __DIR__ . '/src/bootstrap.php';
require_page_access('scorecard');

$role = $_SESSION['role'] ?? 'employee';
$isAdmin = ($role === 'admin');

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roadmap_titles (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            sort_order INT NOT NULL DEFAULT 0,
            title      VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roadmap_items (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            title_id         INT NOT NULL,
            sub_letter       VARCHAR(10) NOT NULL,
            sub_label        VARCHAR(500) NOT NULL,
            page_slug        VARCHAR(255) NOT NULL DEFAULT '',
            has_builder_page TINYINT(1) NOT NULL DEFAULT 0,
            sort_order       INT NOT NULL DEFAULT 0,
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (title_id) REFERENCES roadmap_titles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roadmap_page_blocks (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            item_id    INT NOT NULL,
            block_type ENUM('heading','paragraph','table','dashboard_stat') NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            content    JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES roadmap_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM roadmap_titles")->fetchColumn();
    if ($cnt === 0) {
        $seed = [
            ['title' => '1. Collaborative Health Care', 'items' => [
                ['A', 'A. Quality of Life Index (Employment Rate, Physical Health)', 'collab/roadmap_quality_of_life.php', 0],
                ['B', 'B. <5% relapse rate (WHO, UNODC)',                           'collab/roadmap_relapse_rate.php',    0],
            ]],
            ['title' => '2. Research', 'items' => [
                ['A', 'A. No. of research outputs completed, published or presented', 'research/roadmap_outputs.php', 0],
            ]],
            ['title' => '3. Culture of Organization', 'items' => [
                ['A', 'A. Employee engagement rating',  'culture/roadmap_employee_engagement.php', 0],
                ['B', 'B. Client Satisfaction Rating',  'culture/roadmap_client_satisfaction.php',  0],
            ]],
            ['title' => '4. Training', 'items' => [
                ['A', 'A. No. of Certified TOT on Key Intervention Frameworks',                                                     'training/roadmap_certified_tot.php',      0],
                ['B', 'B. Percentage of SFLU TRC Personnel with direct patient care are trained by certified trainers', 'training/roadmap_percentage_trained.php', 0],
            ]],
            ['title' => '5. Technology', 'items' => [
                ['A', 'A. Decreased turnaround time for patient records retrieval',  'technology/roadmap_patient_records_turnaround.php',  0],
                ['B', 'B. Decreased turnaround time for employee records retrieval', 'technology/roadmap_employee_records_turnaround.php', 0],
            ]],
            ['title' => '6. Resilience', 'items' => [
                ['A', 'A. Green viability rating',           'resilience/roadmap_green_viability.php',      0],
                ['B', 'B. Reduced preventable adverse events','resilience/roadmap_reduced_adverse_events.php', 0],
            ]],
            ['title' => '7. Revenue', 'items' => [
                ['A', 'A. Amount of non-traditional revenue', 'revenue/roadmap_non_traditional_revenue.php', 0],
                ['B', 'B. Hospital income',                   'revenue/roadmap_hospital_income.php',         0],
            ]],
        ];
        $st = $pdo->prepare("INSERT INTO roadmap_titles (sort_order, title) VALUES (:so, :t)");
        $si = $pdo->prepare("INSERT INTO roadmap_items (title_id, sub_letter, sub_label, page_slug, has_builder_page, sort_order) VALUES (:tid, :sl, :lab, :slug, :hbp, :so)");
        foreach ($seed as $idx => $group) {
            $st->execute([':so' => $idx + 1, ':t' => $group['title']]);
            $tid = (int)$pdo->lastInsertId();
            foreach ($group['items'] as $iidx => $item) {
                $si->execute([':tid' => $tid, ':sl' => $item[0], ':lab' => $item[1], ':slug' => $item[2], ':hbp' => $item[3], ':so' => $iidx]);
            }
        }
    }
} catch (Throwable $e) { /* silently continue */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'error'=>'Invalid or expired form token.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_item') {
            $titleId    = (int)($_POST['title_id'] ?? 0);
            $newTitle   = trim($_POST['new_title'] ?? '');
            $subLabel   = trim($_POST['sub_label'] ?? '');
            if (!$subLabel) { echo json_encode(['success' => false, 'error' => 'Sub-item label is required']); exit(); }

            if (!$titleId && $newTitle) {
                $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM roadmap_titles")->fetchColumn();
                $titleNum = $maxOrder + 1;
                if (!preg_match('/^\d+\./', $newTitle)) {
                    $newTitle = $titleNum . '. ' . $newTitle;
                }
                $pdo->prepare("INSERT INTO roadmap_titles (sort_order, title) VALUES (:so, :t)")
                    ->execute([':so' => $titleNum, ':t' => $newTitle]);
                $titleId = (int)$pdo->lastInsertId();
            }
            if (!$titleId) { echo json_encode(['success' => false, 'error' => 'Title is required']); exit(); }

            $existingCount = (int)$pdo->prepare("SELECT COUNT(*) FROM roadmap_items WHERE title_id=:tid")
                ->execute([':tid' => $titleId]) ? $pdo->query("SELECT COUNT(*) FROM roadmap_items WHERE title_id=$titleId")->fetchColumn() : 0;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmap_items WHERE title_id=:tid");
            $stmt->execute([':tid' => $titleId]);
            $existingCount = (int)$stmt->fetchColumn();
            $letter = chr(65 + $existingCount);

            if (!preg_match('/^[A-Z]\./', $subLabel)) {
                $subLabel = $letter . '. ' . $subLabel;
            }

            $ins = $pdo->prepare("INSERT INTO roadmap_items (title_id, sub_letter, sub_label, page_slug, has_builder_page, sort_order) VALUES (:tid,:sl,:lab,:slug,1,:so)");
            $ins->execute([':tid' => $titleId, ':sl' => $letter, ':lab' => $subLabel, ':slug' => '', ':so' => $existingCount]);
            $newId = (int)$pdo->lastInsertId();

            $adminInfo = getUserInfo((int)$_SESSION['user_id']);
            $adminId = formatUserIdentifier($adminInfo);
            $notifMsg = $adminId . " added a new roadmap item: \"" . $subLabel . "\"";
            notifyAdmins('upload', 'New Roadmap Item', $notifMsg, $newId, 'roadmap');
            notifyFocals('upload', 'New Roadmap Item', $notifMsg, $newId, 'roadmap');

            echo json_encode(['success' => true, 'item_id' => $newId]);
            exit();
        }

        if ($action === 'edit_item') {
            $itemId   = (int)($_POST['item_id'] ?? 0);
            $titleId  = (int)($_POST['title_id'] ?? 0);
            $subLabel = trim($_POST['sub_label'] ?? '');
            if (!$itemId || !$titleId || !$subLabel) { echo json_encode(['success' => false, 'error' => 'Missing fields']); exit(); }

            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM roadmap_items WHERE title_id=:tid AND id != :iid");
            $stmtC->execute([':tid' => $titleId, ':iid' => $itemId]);
            $cnt = (int)$stmtC->fetchColumn();

            $stmtCurr = $pdo->prepare("SELECT title_id, sub_letter FROM roadmap_items WHERE id=:id");
            $stmtCurr->execute([':id' => $itemId]);
            $curr = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            $letter = ($curr && (int)$curr['title_id'] === $titleId) ? $curr['sub_letter'] : chr(65 + $cnt);

            $pdo->prepare("UPDATE roadmap_items SET title_id=:tid, sub_letter=:sl, sub_label=:lab WHERE id=:id")
                ->execute([':tid' => $titleId, ':sl' => $letter, ':lab' => $subLabel, ':id' => $itemId]);

            $adminInfo = getUserInfo((int)$_SESSION['user_id']);
            $adminId = formatUserIdentifier($adminInfo);
            $notifMsg = $adminId . " edited roadmap item: \"" . $subLabel . "\"";
            notifyAdmins('edit', 'Roadmap Item Updated', $notifMsg, $itemId, 'roadmap');
            notifyFocals('edit', 'Roadmap Item Updated', $notifMsg, $itemId, 'roadmap');

            echo json_encode(['success' => true]);
            exit();
        }

        if ($action === 'delete_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            if (!$itemId) { echo json_encode(['success' => false, 'error' => 'Missing item_id']); exit(); }
            $check = $pdo->prepare("SELECT has_builder_page, sub_label FROM roadmap_items WHERE id=:id");
            $check->execute([':id' => $itemId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row || !(int)$row['has_builder_page']) {
                echo json_encode(['success' => false, 'error' => 'Cannot delete built-in items']); exit();
            }

            $deletedLabel = $row['sub_label'] ?? 'Unknown';
            $pdo->prepare("DELETE FROM roadmap_items WHERE id=:id")->execute([':id' => $itemId]);

            $adminInfo = getUserInfo((int)$_SESSION['user_id']);
            $adminId = formatUserIdentifier($adminInfo);
            $notifMsg = $adminId . " deleted roadmap item: \"" . $deletedLabel . "\"";
            notifyAdmins('edit', 'Roadmap Item Deleted', $notifMsg, $itemId, 'roadmap');
            notifyFocals('edit', 'Roadmap Item Deleted', $notifMsg, $itemId, 'roadmap');

            echo json_encode(['success' => true]);
            exit();
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$titles = [];
try {
    $titlesRaw = $pdo->query("SELECT * FROM roadmap_titles ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $itemsRaw  = $pdo->query("SELECT * FROM roadmap_items ORDER BY title_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $itemsByTitle = [];
    foreach ($itemsRaw as $item) {
        $itemsByTitle[$item['title_id']][] = $item;
    }
    foreach ($titlesRaw as $t) {
        $titles[] = [
            'id'    => $t['id'],
            'title' => $t['title'],
            'items' => $itemsByTitle[$t['id']] ?? [],
        ];
    }
} catch (Throwable $e) {}

$titlesJson = json_encode($titles);
$titlesForJs = json_encode(array_map(fn($t) => ['id' => $t['id'], 'title' => $t['title']], $titles));
$pageTitle = 'Scorecard: Roadmaps';
$pageStyles = '<style>
    body { background-color: #f5f7fa; color: #2c3e50; }
    .card { border: none; border-radius: 1rem; background-color: #ffffff; }
    .card-header { background: #0b4aa2; color: #fff; border-radius: 1rem 1rem 0 0; font-weight: 700; letter-spacing: .04em; text-align: center; padding: 14px 16px; }
    .section-title { color: #0b4aa2; font-weight: 600; }
    .toc-list .list-group-item { display: flex; justify-content: space-between; align-items: center; }
    .toc-badge { background-color: #0b4aa2; }
    .link-pill { background-color: #f0f2f5; color: #0b4aa2; border: none; }
    .link-pill:hover { background-color: #e6e9ee; color: #062f66; }
    .admin-bar { background: #fff3cd; border: 1px solid #ffc107; border-radius: .75rem; padding: .75rem 1rem; margin-bottom: 1.25rem; }
    .admin-bar label { font-weight: 600; font-size: .85rem; color: #856404; }
    .item-admin-btns { display: flex; gap: .3rem; margin-left: .5rem; }
    .item-admin-btns .btn { padding: 1px 7px; font-size: .78rem; }
    .new-badge { background: #198754; color: #fff; font-size: .65rem; border-radius: 4px; padding: 1px 5px; vertical-align: middle; margin-left: 4px; }
</style>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'PGS — TRC DOH') ?></title>
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<main>
    <div class="container" style="padding-top: 110px;">
        <div class="card shadow-sm">
            <div class="card-header">SCORECARD: ROADMAPS</div>
            <div class="card-body">

                <?php if ($isAdmin): ?>
                <div class="admin-bar d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label><i data-lucide="settings" class="me-1"></i>Admin Controls</label>
                    </div>
                    <button class="btn btn-sm btn-warning ms-auto" id="btnOpenAdd">
                        <i data-lucide="plus" class="me-1"></i>Add Sub-item
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnOpenEdit">
                        <i data-lucide="pencil" class="me-1"></i>Edit Sub-item
                    </button>
                </div>
                <?php endif; ?>

                <p class="mb-4">Select an item below to view its details. Each sub-item opens a dedicated page. Content will be added progressively.</p>

                <div class="row g-4" id="roadmapGrid">
                    <?php
                    $half = (int)ceil(count($titles) / 2);
                    ?>
                    <div class="col-12 col-lg-6" id="col-left">
                        <?php foreach (array_slice($titles, 0, $half) as $t): ?>
                        <?php $this_items = $t['items']; ?>
                        <h6 class="section-title mb-2"><?= h($t['title']) ?></h6>
                        <ul class="list-group toc-list mb-3">
                            <?php foreach ($this_items as $item): ?>
                            <li class="list-group-item">
                                <span>
                                    <?= h($item['sub_label']) ?>
                                    <?php if ($item['has_builder_page']): ?><span class="new-badge">NEW</span><?php endif; ?>
                                </span>
                                <div class="d-flex align-items-center gap-1">
                                    <?php
                                    $href = $item['has_builder_page']
                                        ? '/PGS/roadmap_page_builder.php?item_id=' . (int)$item['id']
                                        : '/PGS/' . htmlspecialchars($item['page_slug']);
                                    ?>
                                    <a class="btn btn-sm link-pill" href="<?= $href ?>">Open</a>
                                    <?php if ($isAdmin && $item['has_builder_page']): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-del-item"
                                        data-id="<?= (int)$item['id'] ?>"
                                        data-label="<?= h($item['sub_label']) ?>"
                                        title="Delete this sub-item">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-12 col-lg-6" id="col-right">
                        <?php foreach (array_slice($titles, $half) as $t): ?>
                        <?php $this_items = $t['items']; ?>
                        <h6 class="section-title mb-2"><?= h($t['title']) ?></h6>
                        <ul class="list-group toc-list mb-3">
                            <?php foreach ($this_items as $item): ?>
                            <li class="list-group-item">
                                <span>
                                    <?= h($item['sub_label']) ?>
                                    <?php if ($item['has_builder_page']): ?><span class="new-badge">NEW</span><?php endif; ?>
                                </span>
                                <div class="d-flex align-items-center gap-1">
                                    <?php
                                    $href = $item['has_builder_page']
                                        ? '/PGS/roadmap_page_builder.php?item_id=' . (int)$item['id']
                                        : '/PGS/' . htmlspecialchars($item['page_slug']);
                                    ?>
                                    <a class="btn btn-sm link-pill" href="<?= $href ?>">Open</a>
                                    <?php if ($isAdmin && $item['has_builder_page']): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-del-item"
                                        data-id="<?= (int)$item['id'] ?>"
                                        data-label="<?= h($item['sub_label']) ?>"
                                        title="Delete this sub-item">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php if ($isAdmin): ?>
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i data-lucide="plus-circle" class="me-2"></i>Add Sub-item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title (Section)</label>
                    <select class="form-select" id="addTitleSelect">
                        <option value="">— Create new title below —</option>
                        <?php foreach ($titles as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= h($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3" id="newTitleWrap">
                    <label class="form-label fw-semibold">New Title Name <small class="text-muted">(number auto-assigned if omitted)</small></label>
                    <input type="text" class="form-control" id="addNewTitle" placeholder="e.g. Finance or 8. Finance">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sub-item Label <small class="text-muted">(letter auto-assigned if omitted)</small></label>
                    <input type="text" class="form-control" id="addSubLabel" placeholder="e.g. Budget Surplus or C. Budget Surplus">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-dark fw-semibold" id="btnSaveAdd">
<i data-lucide="plus" class="me-1"></i>Add
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i data-lucide="pencil" class="me-2"></i>Edit Sub-item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Title (Section)</label>
                    <select class="form-select" id="editTitleSelect">
                        <?php foreach ($titles as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= h($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Sub-item</label>
                    <select class="form-select" id="editItemSelect">
                        <option value="">— select title first —</option>
                    </select>
                </div>
                <div class="mb-3" id="editLabelWrap" style="display:none">
                    <label class="form-label fw-semibold">Sub-item Label</label>
                    <input type="text" class="form-control" id="editSubLabel">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveEdit" disabled>
<i data-lucide="save" class="me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($isAdmin): ?>
<script>
const ALL_TITLES = <?= $titlesForJs ?>;
const ALL_DATA   = <?= $titlesJson ?>;

function itemsForTitle(tid) {
    const t = ALL_DATA.find(t => t.id == tid);
    return t ? t.items : [];
}

const addModal = new bootstrap.Modal(document.getElementById('addModal'));
document.getElementById('btnOpenAdd').addEventListener('click', () => {
    document.getElementById('addTitleSelect').value = '';
    document.getElementById('addNewTitle').value = '';
    document.getElementById('addSubLabel').value = '';
    document.getElementById('newTitleWrap').style.display = 'block';
    addModal.show();
});
document.getElementById('addTitleSelect').addEventListener('change', function() {
    document.getElementById('newTitleWrap').style.display = this.value ? 'none' : 'block';
});
document.getElementById('btnSaveAdd').addEventListener('click', async () => {
    const titleId  = document.getElementById('addTitleSelect').value;
    const newTitle = document.getElementById('addNewTitle').value.trim();
    const subLabel = document.getElementById('addSubLabel').value.trim();
    if (!subLabel) { Swal.fire({ icon: 'error', title: 'Missing field', text: 'Sub-item label is required.' }); return; }
    if (!titleId && !newTitle) { Swal.fire({ icon: 'error', title: 'Missing field', text: 'Select an existing title or enter a new one.' }); return; }

    const fd = new FormData();
    fd.append('_token','<?= csrf_token() ?>');
    fd.append('action', 'add_item');
    fd.append('title_id', titleId || '0');
    fd.append('new_title', newTitle);
    fd.append('sub_label', subLabel);

    const btn = document.getElementById('btnSaveAdd');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const r = await fetch(location.href, { method: 'POST', body: fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i data-lucide="plus" class="me-1"></i>Add';

    if (j && j.success) {
        addModal.hide();
        await Swal.fire({ icon: 'success', title: 'Sub-item Added', timer: 1400, showConfirmButton: false });
        location.reload();
    } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
    }
});

const editModal = new bootstrap.Modal(document.getElementById('editModal'));
document.getElementById('btnOpenEdit').addEventListener('click', () => {
    const sel = document.getElementById('editTitleSelect');
    if (ALL_TITLES.length) { sel.value = ALL_TITLES[0].id; sel.dispatchEvent(new Event('change')); }
    document.getElementById('editLabelWrap').style.display = 'none';
    document.getElementById('btnSaveEdit').disabled = true;
    editModal.show();
});

document.getElementById('editTitleSelect').addEventListener('change', function() {
    const tid = this.value;
    const items = itemsForTitle(tid);
    const sel2 = document.getElementById('editItemSelect');
    sel2.innerHTML = items.length
        ? items.map(i => `<option value="${i.id}" data-label="${i.sub_label.replace(/"/g,'&quot;')}">${i.sub_label}</option>`).join('')
        : '<option value="">No sub-items</option>';
    document.getElementById('editLabelWrap').style.display = 'none';
    document.getElementById('btnSaveEdit').disabled = true;
    sel2.dispatchEvent(new Event('change'));
});

document.getElementById('editItemSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) { document.getElementById('editLabelWrap').style.display = 'none'; document.getElementById('btnSaveEdit').disabled = true; return; }
    document.getElementById('editSubLabel').value = opt.dataset.label || opt.text;
    document.getElementById('editLabelWrap').style.display = 'block';
    document.getElementById('btnSaveEdit').disabled = false;
});

document.getElementById('btnSaveEdit').addEventListener('click', async () => {
    const titleId  = document.getElementById('editTitleSelect').value;
    const itemId   = document.getElementById('editItemSelect').value;
    const subLabel = document.getElementById('editSubLabel').value.trim();
    if (!itemId || !subLabel) { Swal.fire({ icon: 'error', title: 'Missing fields' }); return; }

    const fd = new FormData();
    fd.append('_token','<?= csrf_token() ?>');
    fd.append('action', 'edit_item');
    fd.append('item_id', itemId);
    fd.append('title_id', titleId);
    fd.append('sub_label', subLabel);

    const btn = document.getElementById('btnSaveEdit');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const r = await fetch(location.href, { method: 'POST', body: fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i data-lucide="save" class="me-1"></i>Save';

    if (j && j.success) {
        editModal.hide();
        await Swal.fire({ icon: 'success', title: 'Sub-item Updated', timer: 1400, showConfirmButton: false });
        location.reload();
    } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-del-item').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const label = btn.dataset.label;
        const c = await Swal.fire({
            icon: 'warning', title: 'Delete Sub-item?',
            html: `<span class="text-muted small">${label}</span><br>This will also delete all page content for this item.`,
            showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('action', 'delete_item'); fd.append('item_id', id);
        const r = await fetch(location.href, { method: 'POST', body: fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) {
            await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
        }
    });
});
</script>
<?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

