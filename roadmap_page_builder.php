<?php
require_once __DIR__ . '/src/bootstrap.php';
require_page_access('roadmaps');

$role    = $_SESSION['role'] ?? 'employee';
$isAdmin = ($role === 'admin');
$canEdit = in_array($role, ['admin', 'employee', 'focal'], true);
$itemId  = (int)($_GET['item_id'] ?? 0);

if (!$itemId) { header('Location: ' . BASE_URL . '/roadmap'); exit(); }

$item = null;
try {
    $st = $pdo->prepare("SELECT r.*, t.title AS title_label FROM roadmap_items r JOIN roadmap_titles t ON t.id=r.title_id WHERE r.id=:id");
    $st->execute([':id' => $itemId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

if (!$item) { header('Location: ' . BASE_URL . '/roadmap'); exit(); }

try {
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
} catch (Throwable $e) {}

function normaliseRow($row) {
    if (is_array($row) && isset($row['cells'])) return $row;
    return ['cells' => (array)$row, 'locked' => false];
}

function notifyRoadmapChange($pdo, $itemId, $action, $details) {
    $userInfo = getUserInfo((int)$_SESSION['user_id']);
    $userId = formatUserIdentifier($userInfo);
    $role = $_SESSION['role'] ?? 'employee';
    $roleLabel = ucfirst($role);

    $stmt = $pdo->prepare("SELECT r.sub_label, t.title AS section FROM roadmap_items r JOIN roadmap_titles t ON t.id=r.title_id WHERE r.id=?");
    $stmt->execute([$itemId]);
    $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $pageName = $itemInfo ? $itemInfo['sub_label'] : 'Roadmap Page';
    $section = $itemInfo ? $itemInfo['section'] : '';

    $title = 'Roadmap Page Updated';
    $msg = $roleLabel . " " . $userId . " " . $action . " on \"" . $pageName . "\"" . ($details ? ": " . $details : "");
    if ($section) $msg .= " (" . $section . ")";

    notifyAdmins('edit', $title, $msg, $itemId, 'roadmap_page');
    notifyFocals('edit', $title, $msg, $itemId, 'roadmap_page');

    $result = $pdo->query("SELECT id FROM users WHERE role = 'employee'");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['id'] !== (int)$_SESSION['user_id']) {
            createNotification((int)$row['id'], 'edit', $title, $msg, $itemId, 'roadmap_page');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'error'=>'Invalid or expired form token.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    try {
        if ($isAdmin) {
            if ($action === 'add_block') {
                $type    = $_POST['block_type'] ?? '';
                $allowed = ['heading', 'paragraph', 'table', 'dashboard_stat'];
                if (!in_array($type, $allowed, true)) { echo json_encode(['success'=>false,'error'=>'Invalid block type']); exit(); }
                $content = [];
                switch ($type) {
                    case 'heading':
                        $content = ['text' => trim($_POST['text'] ?? 'New Heading'), 'level' => (int)($_POST['level'] ?? 4)];
                        break;
                    case 'paragraph':
                        $content = ['text' => trim($_POST['text'] ?? '')];
                        break;
                    case 'table':
                        $cols = array_values(array_filter(array_map('trim', explode(',', $_POST['columns'] ?? ''))));
                        $content = ['columns' => $cols, 'rows' => []];
                        break;
                    case 'dashboard_stat':
                        $content = ['label'=>trim($_POST['label']??'Stat'),'value'=>trim($_POST['value']??'0'),'color'=>trim($_POST['color']??'#0b4aa2'),'icon'=>trim($_POST['icon']??'bar-chart-3')];
                        break;
                }
                $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM roadmap_page_blocks WHERE item_id=$itemId")->fetchColumn();
                $ins = $pdo->prepare("INSERT INTO roadmap_page_blocks (item_id, block_type, sort_order, content) VALUES (:iid,:bt,:so,:c)");
                $ins->execute([':iid'=>$itemId,':bt'=>$type,':so'=>$maxOrder+1,':c'=>json_encode($content)]);
                notifyRoadmapChange($pdo, $itemId, 'added a ' . $type . ' block', null);
                echo json_encode(['success'=>true,'block_id'=>(int)$pdo->lastInsertId()]); exit();
            }
            if ($action === 'update_block') {
                $blockId = (int)($_POST['block_id'] ?? 0);
                $content = json_decode($_POST['content'] ?? '{}', true);
                if (!$blockId || !$content) { echo json_encode(['success'=>false]); exit(); }
                $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id AND item_id=:iid")
                    ->execute([':c'=>json_encode($content),':id'=>$blockId,':iid'=>$itemId]);
                notifyRoadmapChange($pdo, $itemId, 'updated a block', null);
                echo json_encode(['success'=>true]); exit();
            }
            if ($action === 'delete_block') {
                $blockId = (int)($_POST['block_id'] ?? 0);
                $pdo->prepare("DELETE FROM roadmap_page_blocks WHERE id=:id AND item_id=:iid")
                    ->execute([':id'=>$blockId,':iid'=>$itemId]);
                notifyRoadmapChange($pdo, $itemId, 'deleted a block', null);
                echo json_encode(['success'=>true]); exit();
            }
            if ($action === 'reorder_block') {
                $blockId  = (int)($_POST['block_id'] ?? 0);
                $dir      = $_POST['direction'] ?? '';
                $block    = $pdo->query("SELECT id, sort_order FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
                if (!$block) { echo json_encode(['success'=>false]); exit(); }
                $curOrder = (int)$block['sort_order'];
                $swap = ($dir === 'up')
                    ? $pdo->query("SELECT id, sort_order FROM roadmap_page_blocks WHERE item_id=$itemId AND sort_order < $curOrder ORDER BY sort_order DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC)
                    : $pdo->query("SELECT id, sort_order FROM roadmap_page_blocks WHERE item_id=$itemId AND sort_order > $curOrder ORDER BY sort_order ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($swap) {
                    $pdo->prepare("UPDATE roadmap_page_blocks SET sort_order=:so WHERE id=:id")->execute([':so'=>$swap['sort_order'],':id'=>$blockId]);
                    $pdo->prepare("UPDATE roadmap_page_blocks SET sort_order=:so WHERE id=:id")->execute([':so'=>$curOrder,':id'=>$swap['id']]);
                }
                notifyRoadmapChange($pdo, $itemId, 'reordered blocks', null);
                echo json_encode(['success'=>true]); exit();
            }
            if ($action === 'delete_table_row') {
                $blockId  = (int)($_POST['block_id'] ?? 0);
                $rowIndex = (int)($_POST['row_index'] ?? -1);
                $bl = $pdo->query("SELECT content FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
                if (!$bl) { echo json_encode(['success'=>false]); exit(); }
                $c = json_decode($bl['content'], true);
                array_splice($c['rows'], $rowIndex, 1);
                $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id")->execute([':c'=>json_encode($c),':id'=>$blockId]);
                notifyRoadmapChange($pdo, $itemId, 'deleted a table row', 'row ' . ($rowIndex + 1));
                echo json_encode(['success'=>true]); exit();
            }
            if ($action === 'lock_table_row') {
                $blockId  = (int)($_POST['block_id'] ?? 0);
                $rowIndex = (int)($_POST['row_index'] ?? -1);
                $lockVal  = (bool)($_POST['lock'] ?? true);
                $bl = $pdo->query("SELECT content FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
                if (!$bl) { echo json_encode(['success'=>false]); exit(); }
                $c = json_decode($bl['content'], true);
                if (!isset($c['rows'][$rowIndex])) { echo json_encode(['success'=>false,'error'=>'Row not found']); exit(); }
                $c['rows'][$rowIndex] = normaliseRow($c['rows'][$rowIndex]);
                $c['rows'][$rowIndex]['locked'] = $lockVal;
                $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id")->execute([':c'=>json_encode($c),':id'=>$blockId]);
                notifyRoadmapChange($pdo, $itemId, $lockVal ? 'locked' : 'unlocked' . ' a table row', 'row ' . ($rowIndex + 1));
                echo json_encode(['success'=>true,'locked'=>$lockVal]); exit();
            }
        }

        if ($action === 'add_table_row') {
            $blockId = (int)($_POST['block_id'] ?? 0);
            $cells   = json_decode($_POST['cells'] ?? '[]', true);
            $bl = $pdo->query("SELECT content FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
            if (!$bl) { echo json_encode(['success'=>false]); exit(); }
            $c = json_decode($bl['content'], true);
            $c['rows'][] = ['cells' => $cells, 'locked' => false];
            $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id")->execute([':c'=>json_encode($c),':id'=>$blockId]);
            notifyRoadmapChange($pdo, $itemId, 'added a table row', null);
            echo json_encode(['success'=>true]); exit();
        }

        if ($action === 'edit_table_row') {
            $blockId  = (int)($_POST['block_id'] ?? 0);
            $rowIndex = (int)($_POST['row_index'] ?? -1);
            $cells    = json_decode($_POST['cells'] ?? '[]', true);
            $bl = $pdo->query("SELECT content FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
            if (!$bl) { echo json_encode(['success'=>false]); exit(); }
            $c = json_decode($bl['content'], true);
            if (!isset($c['rows'][$rowIndex])) { echo json_encode(['success'=>false,'error'=>'Row not found']); exit(); }
            $row = normaliseRow($c['rows'][$rowIndex]);
            if (!$isAdmin && $row['locked']) { echo json_encode(['success'=>false,'error'=>'Row is locked']); exit(); }
            $row['cells'] = $cells;
            $c['rows'][$rowIndex] = $row;
            $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id")->execute([':c'=>json_encode($c),':id'=>$blockId]);
            notifyRoadmapChange($pdo, $itemId, 'edited a table row', 'row ' . ($rowIndex + 1));
            echo json_encode(['success'=>true]); exit();
        }

        if ($action === 'add_table_column') {
            $blockId   = (int)($_POST['block_id'] ?? 0);
            $colName   = trim($_POST['col_name'] ?? 'New Column');
            $bl = $pdo->query("SELECT content FROM roadmap_page_blocks WHERE id=$blockId AND item_id=$itemId")->fetch(PDO::FETCH_ASSOC);
            if (!$bl) { echo json_encode(['success'=>false]); exit(); }
            $c = json_decode($bl['content'], true);
            $c['columns'][] = $colName;
            foreach ($c['rows'] as &$row) {
                $row = normaliseRow($row);
                $row['cells'][] = '';
            }
            unset($row);
            $pdo->prepare("UPDATE roadmap_page_blocks SET content=:c WHERE id=:id")->execute([':c'=>json_encode($c),':id'=>$blockId]);
            notifyRoadmapChange($pdo, $itemId, 'added a table column', $colName);
            echo json_encode(['success'=>true]); exit();
        }

        echo json_encode(['success'=>false,'error'=>'Unknown action or insufficient permission']);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit();
}

$blocks = [];
try {
    $bl = $pdo->query("SELECT * FROM roadmap_page_blocks WHERE item_id=$itemId ORDER BY sort_order ASC");
    foreach ($bl->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $b['content'] = json_decode($b['content'], true);
        $blocks[] = $b;
    }
} catch (Throwable $e) {}

$pageTitle    = h($item['sub_label']);
$sectionTitle = h($item['title_label']);
$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/roadmap_page_builder.css') . '">';
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<main class="page-container container">

    <div class="page-header">
        <div class="d-flex align-items-start gap-3">
            <img src="/PGS/img/roadmap1.png" alt="" style="width:56px;height:56px;object-fit:contain;border-radius:8px;background:#fff2;padding:4px;" onerror="this.style.display='none'">
            <div>
                <h4><?= $pageTitle ?></h4>
                <small><i data-lucide="git-fork" class="me-1"></i><?= $sectionTitle ?></small>
            </div>
            <a href="/PGS/roadmap" class="btn btn-sm btn-light ms-auto"><i data-lucide="arrow-left" class="me-1"></i>Back</a>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="add-block-panel">
        <h6><i data-lucide="plus-circle" class="me-1"></i>Add a Block</h6>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button class="add-block-btn" onclick="openAddBlock('heading')"><i data-lucide="heading"></i>Heading</button>
            <button class="add-block-btn" onclick="openAddBlock('paragraph')"><i data-lucide="pilcrow"></i>Paragraph</button>
            <button class="add-block-btn" onclick="openAddBlock('table')"><i data-lucide="table"></i>Table</button>
            <button class="add-block-btn" onclick="openAddBlock('dashboard_stat')"><i data-lucide="bar-chart-3"></i>Stat Card</button>
        </div>
    </div>
    <?php endif; ?>

    <div id="blocksContainer">
    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <i data-lucide="layers"></i>
            <?php if ($isAdmin): ?>
            <p>No content yet. Use the panel above to add your first block.</p>
            <?php else: ?>
            <p>No content has been added to this page yet.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($blocks as $bidx => $block): ?>
        <?php $isFirst = ($bidx === 0); $isLast = ($bidx === count($blocks)-1); ?>
        <div class="block-card"
             data-block-id="<?= (int)$block['id'] ?>"
             data-block-type="<?= h($block['block_type']) ?>"
             data-content="<?= h(json_encode($block['content'])) ?>">

            <?php if ($isAdmin): ?>
            <div class="block-toolbar">
                <?php if (!$isFirst): ?><button class="btn btn-outline-secondary btn-move" data-dir="up" title="Move up"><i data-lucide="chevron-up"></i></button><?php endif; ?>
                <?php if (!$isLast): ?><button class="btn btn-outline-secondary btn-move" data-dir="down" title="Move down"><i data-lucide="chevron-down"></i></button><?php endif; ?>
                <?php if ($block['block_type'] !== 'table'): ?>
                <button class="btn btn-outline-primary btn-edit-block" title="Edit block"><i data-lucide="pencil"></i></button>
                <?php endif; ?>
                <button class="btn btn-outline-danger btn-del-block" title="Delete block"><i data-lucide="trash-2"></i></button>
            </div>
            <?php endif; ?>

            <div class="block-body">
            <?php
            $c = $block['content'];
            switch ($block['block_type']):
                case 'heading':
            ?>
            <h<?= (int)($c['level'] ?? 4) ?> class="fw-bold mb-0" style="color:#0b4aa2"><?= h($c['text'] ?? '') ?></h<?= (int)($c['level'] ?? 4) ?>>

            <?php break; case 'paragraph': ?>
            <p class="mb-0" style="white-space:pre-wrap"><?= h($c['text'] ?? '') ?></p>

            <?php break; case 'table':
                $cols = $c['columns'] ?? [];
                $rows = $c['rows']   ?? [];
            ?>
            <div class="table-responsive">
                <table class="table table-bordered block-table align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($cols as $col): ?>
                            <th><?= h($col) ?></th>
                            <?php endforeach; ?>
                            <th class="text-center" style="width:<?= $isAdmin ? '130px' : '90px' ?>">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $ri => $rawRow):
                        $row    = normaliseRow($rawRow);
                        $cells  = $row['cells'];
                        $locked = (bool)($row['locked'] ?? false);
                    ?>
                        <tr class="<?= $locked ? 'row-locked-bg' : '' ?>">
                            <?php foreach ($cols as $ci => $col): ?>
                            <td><?= h($cells[$ci] ?? '') ?><?= $locked && $ci === 0 ? '<i data-lucide="lock" class="lock-badge" title="Row locked"></i>' : '' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center text-nowrap">
                                <?php $canEditRow = $isAdmin || !$locked; ?>
                                <?php if ($canEditRow): ?>
                                <button class="btn btn-sm btn-outline-primary btn-edit-row me-1"
                                    data-block-id="<?= (int)$block['id'] ?>"
                                    data-row="<?= $ri ?>"
                                    data-cells="<?= h(json_encode($cells)) ?>"
                                    data-cols="<?= h(json_encode($cols)) ?>"
                                    title="Edit row">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted small"><i data-lucide="lock"></i> Locked</span>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                <button class="btn btn-sm btn-outline-danger btn-del-row me-1"
                                    data-block-id="<?= (int)$block['id'] ?>"
                                    data-row="<?= $ri ?>"
                                    title="Delete row">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <button class="btn btn-sm <?= $locked ? 'btn-danger' : 'btn-outline-secondary' ?> btn-lock-row"
                                    data-block-id="<?= (int)$block['id'] ?>"
                                    data-row="<?= $ri ?>"
                                    data-locked="<?= $locked ? '1' : '0' ?>"
                                    title="<?= $locked ? 'Unlock row' : 'Lock row' ?>">
                                    <i data-lucide="<?= $locked ? 'unlock' : 'lock' ?>"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-action-bar">
                <button class="btn btn-sm btn-outline-success btn-add-row"
                    data-block-id="<?= (int)$block['id'] ?>"
                    data-cols="<?= h(json_encode($cols)) ?>">
                    <i data-lucide="plus" class="me-1"></i>Add Row
                </button>
                <button class="btn btn-sm btn-outline-primary btn-add-col"
                    data-block-id="<?= (int)$block['id'] ?>">
                    <i data-lucide="columns" class="me-1"></i>Add Column
                </button>
            </div>

            <?php break; case 'dashboard_stat': ?>
            <div class="stat-card" style="background:<?= h($c['color'] ?? '#0b4aa2') ?>">
                <div class="stat-icon"><i data-lucide="bar-chart-3"></i></div>
                <div>
                    <div class="stat-val"><?= h($c['value'] ?? '0') ?></div>
                    <div class="stat-label"><?= h($c['label'] ?? '') ?></div>
                </div>
            </div>
            <?php break; endswitch; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="rowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="rowModalTitle"><i data-lucide="table" class="me-2"></i>Row</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rowModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSaveRow"><i data-lucide="check" class="me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addColModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i data-lucide="columns" class="me-2"></i>Add Column</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Column Name</label>
                <input type="text" class="form-control" id="newColName" placeholder="e.g. Remarks">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveCol"><i data-lucide="plus" class="me-1"></i>Add Column</button>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="addBlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addBlockTitle"><i data-lucide="plus-circle" class="me-2"></i>Add Block</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="addBlockBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveBlock"><i data-lucide="plus" class="me-1"></i>Add Block</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editBlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i data-lucide="pencil" class="me-2"></i>Edit Block</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editBlockBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnUpdateBlock"><i data-lucide="save" class="me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const ITEM_ID  = <?= $itemId ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const POST_URL = location.href.split('?')[0] + '?item_id=' + ITEM_ID;

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const rowModal   = new bootstrap.Modal(document.getElementById('rowModal'));
const addColModal= new bootstrap.Modal(document.getElementById('addColModal'));

let rowState = { blockId:0, rowIndex:-1, cols:[], mode:'add' };
let addColBlockId = 0;

document.querySelectorAll('.btn-add-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const cols = JSON.parse(btn.dataset.cols || '[]');
        rowState = { blockId: parseInt(btn.dataset.blockId), rowIndex: -1, cols, mode: 'add' };
        document.getElementById('rowModalTitle').innerHTML = '<i data-lucide="plus" class="me-2"></i>Add Row';
        document.getElementById('rowModalBody').innerHTML = buildRowForm(cols, []);
        document.getElementById('btnSaveRow').className = 'btn btn-success';
        document.getElementById('btnSaveRow').innerHTML  = '<i data-lucide="check" class="me-1"></i>Save';
        rowModal.show();
    });
});

document.querySelectorAll('.btn-edit-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const cols  = JSON.parse(btn.dataset.cols  || '[]');
        const cells = JSON.parse(btn.dataset.cells || '[]');
        rowState = { blockId: parseInt(btn.dataset.blockId), rowIndex: parseInt(btn.dataset.row), cols, mode: 'edit' };
        document.getElementById('rowModalTitle').innerHTML = '<i data-lucide="pencil" class="me-2"></i>Edit Row';
        document.getElementById('rowModalBody').innerHTML = buildRowForm(cols, cells);
        document.getElementById('btnSaveRow').className = 'btn btn-primary';
        document.getElementById('btnSaveRow').innerHTML  = '<i data-lucide="save" class="me-1"></i>Save Changes';
        rowModal.show();
    });
});

function buildRowForm(cols, values) {
    if (!cols.length) return '<p class="text-muted">No columns defined on this table.</p>';
    return cols.map((col, i) => `
        <div class="mb-3">
            <label class="form-label fw-semibold">${escHtml(col)}</label>
            <input type="text" class="form-control" id="rowcell_${i}" value="${escHtml(values[i] ?? '')}" placeholder="${escHtml(col)}">
        </div>
    `).join('');
}

document.getElementById('btnSaveRow').addEventListener('click', async () => {
    const cells = rowState.cols.map((_, i) => document.getElementById(`rowcell_${i}`)?.value || '');
    const fd = new FormData();
    fd.append('_token','<?= csrf_token() ?>');
    fd.append('action', rowState.mode === 'add' ? 'add_table_row' : 'edit_table_row');
    fd.append('block_id', rowState.blockId);
    fd.append('cells', JSON.stringify(cells));
    if (rowState.mode === 'edit') fd.append('row_index', rowState.rowIndex);

    const btn = document.getElementById('btnSaveRow');
    btn.disabled = true;
    const r = await fetch(POST_URL, { method:'POST', body:fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false;

    if (j && j.success) {
        rowModal.hide();
        await Swal.fire({ icon:'success', title: rowState.mode === 'add' ? 'Row Added' : 'Row Updated', timer:1200, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-add-col').forEach(btn => {
    btn.addEventListener('click', () => {
        addColBlockId = parseInt(btn.dataset.blockId);
        document.getElementById('newColName').value = '';
        addColModal.show();
        setTimeout(() => document.getElementById('newColName').focus(), 300);
    });
});

document.getElementById('btnSaveCol').addEventListener('click', async () => {
    const colName = document.getElementById('newColName').value.trim();
    if (!colName) { Swal.fire({ icon:'error', title:'Column name is required' }); return; }
    const fd = new FormData();
    fd.append('_token','<?= csrf_token() ?>');
    fd.append('action', 'add_table_column');
    fd.append('block_id', addColBlockId);
    fd.append('col_name', colName);

    const btn = document.getElementById('btnSaveCol');
    btn.disabled = true;
    const r = await fetch(POST_URL, { method:'POST', body:fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false;

    if (j && j.success) {
        addColModal.hide();
        await Swal.fire({ icon:'success', title:'Column Added', timer:1200, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-del-row').forEach(btn => {
    btn.addEventListener('click', async () => {
        const c = await Swal.fire({ icon:'warning', title:'Delete row?', text:'This cannot be undone.', showCancelButton:true, confirmButtonColor:'#dc3545', confirmButtonText:'Delete' });
        if (!c.isConfirmed) return;
        const fd = new FormData();
        fd.append('_token','<?= csrf_token() ?>');
        fd.append('action','delete_table_row');
        fd.append('block_id', btn.dataset.blockId);
        fd.append('row_index', btn.dataset.row);
        const r = await fetch(POST_URL, { method:'POST', body:fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Row Deleted', timer:1000, showConfirmButton:false }); location.reload(); }
        else Swal.fire({ icon:'error', title:'Failed', text: j?.error||'Error' });
    });
});

document.querySelectorAll('.btn-lock-row').forEach(btn => {
    btn.addEventListener('click', async () => {
        const isLocked = btn.dataset.locked === '1';
        const newLock  = !isLocked;
        const fd = new FormData();
        fd.append('_token','<?= csrf_token() ?>');
        fd.append('action', 'lock_table_row');
        fd.append('block_id', btn.dataset.blockId);
        fd.append('row_index', btn.dataset.row);
        fd.append('lock', newLock ? '1' : '0');
        const r = await fetch(POST_URL, { method:'POST', body:fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { location.reload(); }
        else Swal.fire({ icon:'error', title:'Failed' });
    });
});

<?php if ($isAdmin): ?>
const addBlockModal  = new bootstrap.Modal(document.getElementById('addBlockModal'));
const editBlockModal = new bootstrap.Modal(document.getElementById('editBlockModal'));
let currentAddType = '';
let editBlockId = 0, editBlockType = '';

function formForType(type, prefill) {
    prefill = prefill || {};
    const icons = ['bar-chart-3','line-chart','pie-chart','users','check-circle','star','target','flag','trophy','file-text'];
    const iconOpts = icons.map(ic => `<option value="${ic}" ${prefill.icon===ic?'selected':''}>${ic.replace(/-/g,' ')}</option>`).join('');
    switch (type) {
        case 'heading': return `
            <div class="mb-3"><label class="form-label fw-semibold">Heading Text</label><input type="text" class="form-control" id="bf_text" value="${escHtml(prefill.text||'')}" placeholder="Enter heading text"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Size</label>
            <select class="form-select" id="bf_level">
                <option value="3" ${prefill.level==3?'selected':''}>Large (H3)</option>
                <option value="4" ${!prefill.level||prefill.level==4?'selected':''}>Medium (H4)</option>
                <option value="5" ${prefill.level==5?'selected':''}>Small (H5)</option>
                <option value="6" ${prefill.level==6?'selected':''}>Extra Small (H6)</option>
            </select></div>`;
        case 'paragraph': return `<div class="mb-3"><label class="form-label fw-semibold">Text Content</label><textarea class="form-control" id="bf_text" rows="6" placeholder="Enter paragraph text...">${escHtml(prefill.text||'')}</textarea></div>`;
        case 'table': return `<div class="mb-3"><label class="form-label fw-semibold">Column Names</label><input type="text" class="form-control" id="bf_columns" value="${escHtml((prefill.columns||[]).join(', '))}" placeholder="e.g. Name, Date, Status, Remarks"><div class="form-text">Separate with commas. Add rows and columns after creating.</div></div>`;
        case 'dashboard_stat': return `
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Label</label><input type="text" class="form-control" id="bf_label" value="${escHtml(prefill.label||'')}" placeholder="e.g. Total Patients"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Value</label><input type="text" class="form-control" id="bf_value" value="${escHtml(prefill.value||'0')}" placeholder="e.g. 120 or 85%"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Color</label><input type="color" class="form-control form-control-color" id="bf_color" value="${prefill.color||'#0b4aa2'}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Icon</label><select class="form-select" id="bf_icon">${iconOpts}</select></div>
                <div class="col-12"><div class="stat-card mt-2" id="statPreview" style="background:${prefill.color||'#0b4aa2'}">
                    <div class="stat-icon"><i data-lucide="${prefill.icon||'bar-chart-3'}"></i></div>
                    <div><div class="stat-val" id="prevVal">${escHtml(prefill.value||'0')}</div><div class="stat-label" id="prevLabel">${escHtml(prefill.label||'Stat')}</div></div>
                </div></div>
            </div>`;
        default: return '<p>Unknown type</p>';
    }
}

function collectForm(type) {
    switch (type) {
        case 'heading':   return { text: document.getElementById('bf_text')?.value||'', level: parseInt(document.getElementById('bf_level')?.value||'4') };
        case 'paragraph': return { text: document.getElementById('bf_text')?.value||'' };
        case 'table':     return { columns: (document.getElementById('bf_columns')?.value||'').split(',').map(s=>s.trim()).filter(Boolean), rows:[] };
        case 'dashboard_stat': return { label:document.getElementById('bf_label')?.value||'', value:document.getElementById('bf_value')?.value||'', color:document.getElementById('bf_color')?.value||'#0b4aa2', icon:document.getElementById('bf_icon')?.value||'bar-chart-3' };
    }
}

function setupStatPreview() {
    ['bf_color','bf_icon','bf_value','bf_label'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            const prev = document.getElementById('statPreview');
            if (prev) prev.style.background = document.getElementById('bf_color')?.value||'#0b4aa2';
            const pi = prev?.querySelector('.stat-icon i');
            if (pi) { pi.setAttribute('data-lucide', document.getElementById('bf_icon')?.value||'bar-chart-3'); lucide.createIcons(); }
            const pv = document.getElementById('prevVal'); if(pv) pv.textContent = document.getElementById('bf_value')?.value||'0';
            const pl = document.getElementById('prevLabel'); if(pl) pl.textContent = document.getElementById('bf_label')?.value||'Stat';
        });
    });
}

function openAddBlock(type) {
    currentAddType = type;
    const names = {heading:'Heading',paragraph:'Paragraph',table:'Table',dashboard_stat:'Stat Card'};
    document.getElementById('addBlockTitle').innerHTML = `<i data-lucide="plus-circle" class="me-2"></i>Add ${names[type]||type}`;
    document.getElementById('addBlockBody').innerHTML = formForType(type, {});
    if (type === 'dashboard_stat') setupStatPreview();
    addBlockModal.show();
}

document.getElementById('btnSaveBlock').addEventListener('click', async () => {
    const content = collectForm(currentAddType);
    if (currentAddType === 'table' && !content.columns.length) { Swal.fire({icon:'error',title:'Need columns',text:'Enter at least one column name.'}); return; }
    const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('action','add_block'); fd.append('block_type', currentAddType);
    Object.entries(content).forEach(([k,v]) => { if (!Array.isArray(v)) fd.append(k, v); });
    if (currentAddType === 'table') fd.append('columns', content.columns.join(','));
    const btn = document.getElementById('btnSaveBlock'); btn.disabled = true;
    const r = await fetch(POST_URL, {method:'POST',body:fd});
    let j=null; try{j=await r.json();}catch(e){}
    btn.disabled = false;
    if (j&&j.success) { addBlockModal.hide(); await Swal.fire({icon:'success',title:'Block Added',timer:1200,showConfirmButton:false}); location.reload(); }
    else Swal.fire({icon:'error',title:'Failed',text:j?.error||'Unknown error'});
});

document.querySelectorAll('.btn-edit-block').forEach(btn => {
    btn.addEventListener('click', () => {
        const card = btn.closest('[data-block-id]');
        editBlockId   = parseInt(card.dataset.blockId);
        editBlockType = card.dataset.blockType;
        const raw = card.dataset.content ? JSON.parse(card.dataset.content) : {};
        document.getElementById('editBlockBody').innerHTML = formForType(editBlockType, raw);
        if (editBlockType === 'dashboard_stat') setupStatPreview();
        editBlockModal.show();
    });
});

document.getElementById('btnUpdateBlock').addEventListener('click', async () => {
    const content = collectForm(editBlockType);
    const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('action','update_block'); fd.append('block_id',editBlockId); fd.append('content', JSON.stringify(content));
    const btn = document.getElementById('btnUpdateBlock'); btn.disabled = true;
    const r = await fetch(POST_URL, {method:'POST',body:fd});
    let j=null; try{j=await r.json();}catch(e){}
    btn.disabled = false;
    if (j&&j.success) { editBlockModal.hide(); await Swal.fire({icon:'success',title:'Block Updated',timer:1200,showConfirmButton:false}); location.reload(); }
    else Swal.fire({icon:'error',title:'Failed',text:j?.error||'Unknown error'});
});

document.querySelectorAll('.btn-del-block').forEach(btn => {
    btn.addEventListener('click', async () => {
        const card = btn.closest('[data-block-id]');
        const id = parseInt(card.dataset.blockId);
        const c = await Swal.fire({icon:'warning',title:'Delete block?',text:'This cannot be undone.',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Delete'});
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('action','delete_block'); fd.append('block_id',id);
        const r = await fetch(POST_URL,{method:'POST',body:fd});
        let j=null; try{j=await r.json();}catch(e){}
        if (j&&j.success) { await Swal.fire({icon:'success',title:'Deleted',timer:1000,showConfirmButton:false}); location.reload(); }
        else Swal.fire({icon:'error',title:'Failed'});
    });
});

document.querySelectorAll('.btn-move').forEach(btn => {
    btn.addEventListener('click', async () => {
        const card = btn.closest('[data-block-id]');
        const fd = new FormData(); fd.append('_token','<?= csrf_token() ?>'); fd.append('action','reorder_block'); fd.append('block_id',parseInt(card.dataset.blockId)); fd.append('direction',btn.dataset.dir);
        await fetch(POST_URL,{method:'POST',body:fd});
        location.reload();
    });
});
<?php endif; ?>
</script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

