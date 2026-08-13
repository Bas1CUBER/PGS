<?php
require_once __DIR__ . '/src/bootstrap.php';
$pageTitle = 'Add Deliverable';
$pageStyles = '';

// Load distinct years for the filter
$years = [];
$yearResult = $conn->query("SELECT DISTINCT YEAR(target_date) AS year FROM p_deliverables WHERE target_date IS NOT NULL ORDER BY year ASC");
if ($yearResult) {
    while ($y = $yearResult->fetch_assoc()) {
        $years[] = $y['year'];
    }
}

// Load deliverables
$results = $conn->query("SELECT * FROM p_deliverables ORDER BY target_date ASC");

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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=3">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <label for="filterYearSelect" class="form-label mb-0">Filter by Year:</label>
                <select id="filterYearSelect" class="form-select d-inline-block w-auto ms-2">
                    <option value="">All</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                + Add New Deliverable
            </button>
        </div>

        <!-- Card Container for styling -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body table-responsive">
                <table class="table table-bordered align-middle text-nowrap" id="dataTable">
                    <thead class="table-light text-center">
                        <tr>
                            <th>Project/Deliverable Title</th>
                            <th>Focal Person</th>
                            <th>Division</th>
                            <th>Target Date</th>
                            <th>Status</th>
                            <th>Actual Date</th>
                            <th>MOVs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php while ($row = $results->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['focal_person']) ?></td>
                                <td><?= htmlspecialchars($row['division']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['target_date'])) ?></td>
                                <td>
                                    <?php
                                    $status = $row['status'];
                                    $badgeClass = match ($status) {
                                        'Accomplished' => 'success',
                                        'Ongoing' => 'warning text-dark',
                                        'Not Yet Started' => 'secondary',
                                        default => 'light text-dark'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $badgeClass ?>"><?= $status ?></span>
                                </td>
                                <td>
                                    <?php
                                    $actualDate = $row['actual_date'];
                                    if ($actualDate && $actualDate !== '0000-00-00') {
                                        echo date('M d, Y', strtotime($actualDate));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['mov_file']): ?>
                                        <a href="<?= htmlspecialchars($row['mov_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            View MOV
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="<?= $row['id'] ?>" title="Edit"><i class="bi bi-pencil"></i>Edit</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $row['id'] ?>" title="Delete"><i class="bi bi-trash"></i>Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form id="addDataForm" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel">Add New Deliverable</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="deliverableId">
                        <label class="form-label">Form Type:</label>
                        <select class="form-select mb-2" name="form_type" id="form_type" required>
                            <option value="">Select form</option>
                            <option>Collaborative Healthcare Management</option>
                            <option>Research</option>
                            <option>Training</option>
                            <option>Culture of Organization</option>
                            <option>Resilience</option>
                            <option>Technology</option>
                            <option>Revenue</option>
                        </select>
                        <label class="form-label">Title:</label>
                        <input type="text" class="form-control mb-2" name="title" id="title" placeholder="Title" required>
                        <label class="form-label">Focal Person:</label>
                        <input type="text" class="form-control mb-2" name="focal_person" id="focal_person" placeholder="Focal Person" required>
                        <label class="form-label">Division:</label>
                        <input type="text" class="form-control mb-2" name="division" id="division" placeholder="Division" required>
                        <label class="form-label">Target Completion Date:</label>
                        <input type="date" class="form-control mb-2" name="target_date" id="target_date" required>
                        <label class="form-label">Current Status:</label>
                        <select class="form-select mb-2" name="status" id="status" required>
                            <option>Accomplished</option>
                            <option>Ongoing</option>
                            <option>Not Yet Started</option>
                        </select>
                        <label class="form-label">Actual Completion Date:</label>
                        <input type="date" class="form-control mb-2" name="actual_date" id="actual_date">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
<?php
$pageScripts = <<<'SCRIPTS'
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
            const filterSelect = document.getElementById('filterYearSelect');
            const tableBody = document.getElementById('tableBody');

            filterSelect.addEventListener('change', () => {
                const year = filterSelect.value;
                Array.from(tableBody.rows).forEach(row => {
                    const dateText = row.cells[3].textContent;
                    const rowYear = new Date(dateText).getFullYear().toString();
                    row.style.display = (year === "" || rowYear === year) ? "" : "none";
                });
            });

            document.getElementById('addDataForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('insert.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Deliverable added successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'An unknown error occurred.',
                            });
                        }
                    });
            });

            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    fetch('delete_deliverables.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + id
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Deliverable deleted.',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Delete failed.' });
                            }
                        });
                });
            });
        </script>
SCRIPTS;
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php
