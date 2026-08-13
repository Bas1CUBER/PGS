<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// Ensure user_page_access table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_page_access (
            user_id INT PRIMARY KEY,
            roadmaps TINYINT(1) NOT NULL DEFAULT 1,
            scorecard TINYINT(1) NOT NULL DEFAULT 1,
            performance_assessment TINYINT(1) NOT NULL DEFAULT 1,
            cascading TINYINT(1) NOT NULL DEFAULT 1,
            governance TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_page_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Throwable $e) {
}
// Ensure is_active column exists
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'");
    $check->execute();
    $hasColumn = (int)$check->fetchColumn() > 0;

    if (!$hasColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (Throwable $e) {
    // If this fails, the page will still load but toggling may fail.
}

// Ensure users.role supports focal role
try {
    $roleCol = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if ($roleCol && stripos($roleCol, "'focal'") === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee','focal') NOT NULL");
    }
} catch (Throwable $e) {
}

// Ensure optional profile columns exist
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    if ($cols && !in_array('name', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) DEFAULT NULL");
    }
    if ($cols && !in_array('office', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN office VARCHAR(255) DEFAULT NULL");
    }
} catch (Throwable $e) {
}

$stmt = $pdo->query("SELECT id, email, role, created_at, COALESCE(is_active, 1) AS is_active, name, office FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure history table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_management_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        target_user VARCHAR(255) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        details TEXT DEFAULT NULL,
        details_before TEXT DEFAULT NULL,
        performed_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
// Ensure details_before column exists for older installs
try {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_management_history' AND COLUMN_NAME = 'details_before'");
    $colCheck->execute();
    if ((int)$colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE user_management_history ADD COLUMN details_before TEXT DEFAULT NULL AFTER details");
    }
} catch (Throwable $e) {}

$history = $pdo->query("SELECT * FROM user_management_history ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
?>


<?php
$pageTitle = 'User Management';
$pageStyles = 'body {
            background-color: #f5f7fa;
            padding-top: 0;
        }

        main {
            padding-top: 110px;
            padding-bottom: 40px;
        }

        .container {
            max-width: 1200px;
        }

        .um-wrapper {
            max-width: 980px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-soft {
            background: #edf2f7;
            border: none;
        }

        .history-toggle-btn {
            background: linear-gradient(135deg, #0b4aa2 0%, #1a5fc9 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            box-shadow: 0 4px 14px rgba(11, 74, 162, 0.25);
            transition: all 0.25s ease;
        }
        .history-toggle-btn:hover {
            background: linear-gradient(135deg, #083a7f 0%, #0b4aa2 100%);
            color: #fff;
            box-shadow: 0 6px 20px rgba(11, 74, 162, 0.35);
            transform: translateY(-1px);
        }
        .history-toggle-btn:active { transform: translateY(0); }
        .history-toggle-btn .toggle-icon {
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .history-toggle-btn.active .toggle-icon {
            transform: rotate(180deg);
        }

        .history-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        .history-card .card-header {
            background: linear-gradient(135deg, #0b4aa2 0%, #1a5fc9 100%);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 14px 20px;
            border: none;
        }
        .history-table thead th {
            background: #f0f4f8;
            color: #334155;
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 16px;
            white-space: nowrap;
        }
        .history-table tbody td {
            padding: 11px 16px;
            font-size: 0.88rem;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .history-table tbody tr:hover {
            background: #f8fafc;
        }
        .history-table tbody tr:last-child td {
            border-bottom: none;
        }
        .badge-action {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }
        .badge-added { background: #d1fae5; color: #065f46; }
        .badge-updated { background: #dbeafe; color: #1e40af; }
        .badge-deleted { background: #fee2e2; color: #991b1b; }
        .badge-activated { background: #ccfbf1; color: #115e59; }
        .badge-deactivated { background: #fef3c7; color: #92400e; }
        .badge-role-changed { background: #ede9fe; color: #5b21b6; }
        .badge-access-updated { background: #e0e7ff; color: #3730a3; }
        .history-empty {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .history-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; }
        .history-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.45s ease, opacity 0.35s ease;
            opacity: 0;
        }
        .history-wrapper.open {
            max-height: 2000px;
            opacity: 1;
        }
        .history-detail-link {
            color: #0b4aa2;
            cursor: pointer;
            font-weight: 500;
            border-bottom: 1px dashed #93b4e0;
            transition: color 0.15s, border-color 0.15s;
        }
        .history-detail-link:hover {
            color: #083a7f;
            border-bottom-color: #083a7f;
        }
        .detail-modal-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .detail-modal-value {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.9rem;
            color: #1e293b;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .detail-modal-value.before-state {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #9a3412;
        }
        .detail-modal-value.after-state {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<main>
        <div class="container py-4">
            <div class="um-wrapper">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">User Management</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i data-lucide="user-plus" class="me-2"></i>Add User
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="input-group" style="max-width:360px;">
                                <span class="input-group-text"><i data-lucide="search"></i></span>
                                <input type="text" id="umFilter" class="form-control" placeholder="Search by User ID, Name, Office, Role">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Office</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?= (int)$u['id'] ?></td>
                                            <td><?= h($u['email']) ?></td>
                                            <td><?= h($u['name'] ?? '') ?></td>
                                            <td><?= h($u['office'] ?? '') ?></td>
                                            <td><?= h($u['role']) ?></td>
                                            <td>
                                                <?php if ((int)$u['is_active'] === 1): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-inactive">Deactivated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= h($u['created_at']) ?></td>
                                            <td class="text-end">
                                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                                    <div class="btn-group" role="group" aria-label="Actions">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary editBtn"
                                                                data-bs-toggle="tooltip" title="Edit profile / password"
                                                                data-id="<?= (int)$u['id'] ?>"
                                                                data-email="<?= h($u['email']) ?>"
                                                                data-name="<?= h($u['name'] ?? '', ENT_QUOTES) ?>"
                                                                data-office="<?= h($u['office'] ?? '', ENT_QUOTES) ?>">
                                                            <i data-lucide="user-pen"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-primary accessBtn" data-id="<?= (int)$u['id'] ?>" data-email="<?= h($u['email']) ?>" data-bs-toggle="tooltip" title="Page access">
                                                            <i data-lucide="lock"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm <?= ((int)$u['is_active'] === 1) ? 'btn-outline-danger' : 'btn-outline-success' ?> toggleBtn" data-id="<?= (int)$u['id'] ?>" data-active="<?= (int)$u['is_active'] ?>" data-bs-toggle="tooltip" title="<?= ((int)$u['is_active'] === 1) ? 'Deactivate' : 'Activate' ?>">
                                                            <i data-lucide="<?= ((int)$u['is_active'] === 1) ? 'user-x' : 'user-check' ?>"></i>
                                                        </button>
                                                <?php if (in_array($u['role'], ['employee','focal'], true)): ?>
                                                    <div class="btn-group dropstart">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Change Role
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($u['role'] === 'employee'): ?>
                                                                <li><button class="dropdown-item roleChangeBtn" data-id="<?= (int)$u['id'] ?>" data-role="focal">Set as focal</button></li>
                                                            <?php elseif ($u['role'] === 'focal'): ?>
                                                                <li><button class="dropdown-item roleChangeBtn" data-id="<?= (int)$u['id'] ?>" data-role="employee">Set as employee</button></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= (int)$u['id'] ?>" data-email="<?= h($u['email']) ?>" data-bs-toggle="tooltip" title="Delete user">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Current user</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- History Section -->
    <div class="um-wrapper mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" style="color:#334155;"><i data-lucide="clock-rotate-left" class="me-2" style="color:#0b4aa2;"></i>Audit History</h5>
            <button type="button" class="history-toggle-btn" id="historyToggleBtn">
<i data-lucide="chevron-down" class="toggle-icon me-2"></i>Show History
            </button>
        </div>
        <div class="history-wrapper" id="historyWrapper">
            <div class="history-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i data-lucide="list-checks" class="me-2"></i>User Management History</span>
                    <span class="badge bg-white bg-opacity-25" style="font-size:.75rem;"><?= count($history) ?> records</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($history)): ?>
                        <div class="history-empty">
                            <i data-lucide="inbox"></i>
                            <div>No history records yet</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table history-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $i => $h):
                                        $actionLower = strtolower($h['action_type']);
                                        $badgeClass = 'badge-updated';
                                        if ($actionLower === 'added') $badgeClass = 'badge-added';
                                        elseif ($actionLower === 'deleted') $badgeClass = 'badge-deleted';
                                        elseif ($actionLower === 'activated') $badgeClass = 'badge-activated';
                                        elseif ($actionLower === 'deactivated') $badgeClass = 'badge-deactivated';
                                        elseif ($actionLower === 'role changed') $badgeClass = 'badge-role-changed';
                                        elseif ($actionLower === 'access updated') $badgeClass = 'badge-access-updated';
                                    ?>
                                        <tr>
                                            <td class="text-muted" style="width:50px;"><?= count($history) - $i ?></td>
                                            <td style="font-weight:600; color:#1e293b;"><?= h($h['target_user']) ?></td>
                                            <td style="white-space:nowrap;"><?= date('M d, Y g:i A', strtotime($h['created_at'])) ?></td>
                                            <td><span class="badge badge-action <?= $badgeClass ?>"><?= h($h['action_type']) ?></span></td>
                                            <td>
                                                <span class="history-detail-link" role="button" tabindex="0"
                                                      data-details="<?= h($h['details'] ?? '', ENT_QUOTES) ?>"
                                                      data-before="<?= h($h['details_before'] ?? '', ENT_QUOTES) ?>"
                                                      data-action="<?= h($h['action_type'], ENT_QUOTES) ?>"
                                                      data-user="<?= h($h['target_user'], ENT_QUOTES) ?>"
                                                      data-date="<?= h(date('M d, Y g:i A', strtotime($h['created_at'])), ENT_QUOTES) ?>"
                                                      data-by="<?= h($h['performed_by'] ?? '', ENT_QUOTES) ?>">
                                                    <?= h($h['details']) ?>
                                                </span>
                                                <span class="text-muted" style="font-size:.78rem;">â€” by <?= h($h['performed_by']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- History Detail Modal -->
    <div class="modal fade" id="historyDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 20px 50px rgba(0,0,0,.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,#0b4aa2,#1a5fc9); color:#fff; border-radius:16px 16px 0 0; border:none;">
                    <h6 class="modal-title" id="hdModalTitle"><i data-lucide="clock-rotate-left" class="me-2"></i>Change Details</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:20px 24px;">
                    <div class="mb-3">
                        <div class="detail-modal-label"><i data-lucide="user" class="me-1"></i>User</div>
                        <div class="detail-modal-value" id="hdModalUser"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6"><div class="detail-modal-label"><i data-lucide="tag" class="me-1"></i>Action</div><div id="hdModalAction"></div></div>
                        <div class="col-6"><div class="detail-modal-label"><i data-lucide="calendar" class="me-1"></i>Date</div><div id="hdModalDate" style="font-size:.88rem; color:#475569;"></div></div>
                    </div>
                    <hr style="border-color:#e2e8f0; margin:16px 0;">
                    <div id="hdBeforeSection" style="display:none;" class="mb-3">
                        <div class="detail-modal-label"><i data-lucide="undo-2" class="me-1"></i>Before</div>
                        <div class="detail-modal-value before-state" id="hdModalBefore"></div>
                    </div>
                    <div class="mb-2">
                        <div class="detail-modal-label"><i data-lucide="check" class="me-1"></i>After (Current)</div>
                        <div class="detail-modal-value after-state" id="hdModalAfter"></div>
                    </div>
                    <div class="text-end mt-3" style="font-size:.78rem; color:#94a3b8;">Performed by: <strong id="hdModalBy"></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="addUserForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User ID</label>
                        <input type="text" name="email" class="form-control" placeholder="Enter User ID" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Office</label>
                        <input type="text" name="office" class="form-control" placeholder="Office/Department">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="employee">employee</option>
                            <option value="focal">focal</option>
                            <option value="admin">admin</option>
                </select>
                    </div>
                </div>
<div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Page Access Modal -->
    <div class="modal fade" id="accessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="accessForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Page Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="accessUserId">
                    <div class="mb-2 small text-muted" id="accessUserEmail"></div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="accRoadmaps" name="roadmaps">
                        <label class="form-check-label" for="accRoadmaps">Roadmaps</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="accScorecard" name="scorecard">
                        <label class="form-check-label" for="accScorecard">Scorecard</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="accPerformance" name="performance_assessment">
                        <label class="form-check-label" for="accPerformance">Performance Assessment</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="accCascading" name="cascading">
                        <label class="form-check-label" for="accCascading">Cascading</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="accGovernance" name="governance">
                        <label class="form-check-label" for="accGovernance">Governance</label>
                    </div>
                </div>
<div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editUserForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-2 small text-muted" id="edit-email"></div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" placeholder="Full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Office</label>
                        <input type="text" name="office" id="edit-office" class="form-control" placeholder="Office/Department">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (optional)</label>
                        <input type="password" name="new_password" id="edit-password" class="form-control" minlength="6" placeholder="Leave blank to keep current">
                    </div>
                </div>
<div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<script>
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('user_add.php', {
                method: 'POST',
                body: formData
            })
            .then(async r => {
                const text = await r.text();
                let data = null;
                try { data = JSON.parse(text); } catch (err) {
                    throw new Error(text || ('HTTP ' + r.status));
                }
                return data;
            })
            .then(data => {
                if (data && data.success) {
                    Swal.fire('Created', 'User added successfully', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', (data && data.error) || 'Failed to add user', 'error');
                }
            })
            .catch((err) => Swal.fire('Error', (err && err.message) ? err.message : 'Request failed', 'error'));
        });

        document.querySelectorAll('.toggleBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const active = parseInt(this.getAttribute('data-active'), 10);
                const nextActive = active === 1 ? 0 : 1;

                Swal.fire({
                    title: nextActive === 0 ? 'Deactivate user?' : 'Activate user?',
                    text: nextActive === 0 ? 'Deactivated users cannot log in.' : 'User will be able to log in again.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('is_active', String(nextActive));

                    fetch('user_toggle.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', data.error || 'Failed to update user', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Request failed', 'error'));
                });
            });
        });

        // Page Access handlers
        document.querySelectorAll('.accessBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');
                document.getElementById('accessUserId').value = id;
                document.getElementById('accessUserEmail').textContent = 'User ID: ' + email;
                fetch('user_access_get.php?id=' + encodeURIComponent(id))
                  .then(r => r.json())
                  .then(data => {
                      document.getElementById('accRoadmaps').checked = !!data.roadmaps;
                      document.getElementById('accScorecard').checked = !!data.scorecard;
                      document.getElementById('accPerformance').checked = !!data.performance_assessment;
                      document.getElementById('accCascading').checked = !!data.cascading;
                      document.getElementById('accGovernance').checked = !!data.governance;
                      const modalEl = document.getElementById('accessModal');
                      new bootstrap.Modal(modalEl).show();
                  })
                  .catch(() => Swal.fire('Error', 'Failed to load access settings', 'error'));
            });
        });

        document.getElementById('accessForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            // Unchecked checkboxes are not sent; set explicitly
            ['roadmaps','scorecard','performance_assessment','cascading','governance'].forEach(k => {
                fd.set(k, this.querySelector('[name="'+k+'"]').checked ? '1' : '0');
            });
            fetch('user_access_update.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(data => {
                  if (data.success) {
                      Swal.fire('Saved', 'Access settings updated', 'success');
                      bootstrap.Modal.getInstance(document.getElementById('accessModal')).hide();
                  } else {
                      Swal.fire('Error', data.error || 'Failed to update access', 'error');
                  }
              })
              .catch(() => Swal.fire('Error', 'Request failed', 'error'));
        });

        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');

                Swal.fire({
                    title: 'Delete user?',
                    text: 'This will permanently delete User ID ' + email,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Delete'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('id', id);

                    fetch('user_delete.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted', 'User deleted successfully', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error || 'Failed to delete user', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Request failed', 'error'));
                });
            });
        });

        // Role change handlers
        document.querySelectorAll('.roleChangeBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const role = btn.getAttribute('data-role');
                const fd = new FormData();
                fd.append('id', id);
                fd.append('role', role);
                fetch('user_role_update.php', { method:'POST', body:fd })
                  .then(r => r.json())
                  .then(data => {
                    if (data && data.success) {
                        Swal.fire('Updated', 'User role changed to ' + role, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', (data && data.error) || 'Failed to change role', 'error');
                    }
                  })
                  .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });
        });
        // Enhance UX: filter table rows
        (function(){
            const input = document.getElementById('umFilter');
            if (!input) return;
            input.addEventListener('input', function(){
                const q = this.value.toLowerCase();
                document.querySelectorAll('table tbody tr').forEach(tr => {
                    const text = tr.textContent.toLowerCase();
                    tr.style.display = text.includes(q) ? '' : 'none';
                });
            });
            // Enable tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
              new bootstrap.Tooltip(tooltipTriggerEl);
            });
        })();

        // Edit handlers
        document.querySelectorAll('.editBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');
                const name = this.getAttribute('data-name') || '';
                const office = this.getAttribute('data-office') || '';
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-email').textContent = 'User ID: ' + email;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-office').value = office;
                document.getElementById('edit-password').value = '';
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            });
        });

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('user_update.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(data => {
                if (data && data.success) {
                    Swal.fire('Saved', 'User updated successfully', 'success').then(()=>location.reload());
                } else {
                    Swal.fire('Error', (data && data.error) || 'Failed to update user', 'error');
                }
              })
              .catch(()=> Swal.fire('Error','Request failed','error'));
        });

        // History toggle
        (function(){
            const btn = document.getElementById('historyToggleBtn');
            const wrapper = document.getElementById('historyWrapper');
            if (!btn || !wrapper) return;
            btn.addEventListener('click', function(){
                const isOpen = wrapper.classList.toggle('open');
                btn.classList.toggle('active', isOpen);
                btn.innerHTML = isOpen
                    ? '<i data-lucide="chevron-up" class="toggle-icon me-2"></i>Hide History'
                    : '<i data-lucide="chevron-down" class="toggle-icon me-2"></i>Show History';
            });
        })();

        // History detail click â€” open before/after modal
        (function(){
            document.querySelectorAll('.history-detail-link').forEach(el => {
                el.addEventListener('click', function(){
                    const details = this.getAttribute('data-details') || '';
                    const before = this.getAttribute('data-before') || '';
                    const action = this.getAttribute('data-action') || '';
                    const user = this.getAttribute('data-user') || '';
                    const date = this.getAttribute('data-date') || '';
                    const by = this.getAttribute('data-by') || '';

                    document.getElementById('hdModalUser').textContent = user;
                    document.getElementById('hdModalAction').innerHTML = '<span class="badge badge-action" style="font-size:.8rem;">' + action + '</span>';
                    document.getElementById('hdModalDate').textContent = date;
                    document.getElementById('hdModalAfter').textContent = details || 'â€”';
                    document.getElementById('hdModalBy').textContent = by || 'â€”';

                    const beforeSection = document.getElementById('hdBeforeSection');
                    if (before && before.trim() !== '') {
                        beforeSection.style.display = 'block';
                        document.getElementById('hdModalBefore').textContent = before;
                    } else {
                        beforeSection.style.display = 'none';
                    }

                    new bootstrap.Modal(document.getElementById('historyDetailModal')).show();
                });
            });
        })();
    </script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

