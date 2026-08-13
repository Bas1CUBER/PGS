<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

// If employee, show SweetAlert then redirect
if (in_array($_SESSION['role'] ?? null, ['employee','focal'], true)) {
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "Access Denied",
                text: "YOU DON\'T HAVE ACCESS TO THIS PAGE",
                confirmButtonColor: "#d33"
            }).then(() => {
                window.location.href = "employee_dashboard.php"; // or any fallback page
            });
        </script>
    </body>
    </html>
    ';
    exit();
}

$conn = new mysqli("localhost", "root", "", "planning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure uploaded_by column exists for tracking uploads
try {
    $colRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'p_deliverables' AND COLUMN_NAME = 'uploaded_by'");
    $colRow = $colRes ? $colRes->fetch_assoc() : null;
    if ($colRow && (int)$colRow['c'] === 0) {
        $conn->query("ALTER TABLE p_deliverables ADD COLUMN uploaded_by INT(11) NULL");
    }
} catch (Throwable $e) {
}

$search = $_GET['search'] ?? '';
$yearFilter = $_GET['year_filter'] ?? '';

$sql = "SELECT p.*, u.email AS uploaded_by_email FROM p_deliverables p LEFT JOIN users u ON u.id = p.uploaded_by WHERE 1";

if (!empty($yearFilter)) {
    $sql .= " AND YEAR(target_date) = " . intval($yearFilter);
}

if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    $sql .= " AND (title LIKE '%$searchSafe%' OR focal_person LIKE '%$searchSafe%' OR division LIKE '%$searchSafe%' OR form_type LIKE '%$searchSafe%')";
}

$sql .= " ORDER BY target_date ASC";
$result = $conn->query($sql);

$pageTitle = 'Deliverables Dashboard';

$pageStyles = '<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
html,
body {
    background-color: #f5f7fa;
    color: #2c3e50;
    height: 100%;
    margin: 0;
    padding-top: 30px;
}

main {
    flex: 1;
}

.navbar {
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table-header {
    background: #edf2f7;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #718096;
}

.table-row {
    transition: background 0.2s ease;
}

.table-row:hover {
    background: #f7fafc;
}

.modal-content {
    border-radius: 1rem;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    background: #ffffff;
}

.form-select,
.form-control {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    background: #ffffff;
    transition: all 0.3s ease;
}

.form-select:focus,
.form-control:focus {
    border-color: #5a67d8;
    box-shadow: 0 0 0 4px rgba(90, 103, 216, 0.1);
    outline: none;
}

.btn-primary {
    background: #5a67d8;
    border: none;
    border-radius: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    color: #ffffff;
    transition: background 0.3s ease, transform 0.2s ease;
}

.btn-primary:hover {
    background: #4c51bf;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #edf2f7;
    color: #4a5568;
    border: none;
    border-radius: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: background 0.3s ease, transform 0.2s ease;
}

.btn-secondary:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
}

.container {
    max-width: 1400px;
}

.card {
    background: #ffffff;
    border-radius: 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.modal-header {
    border-bottom: 1px solid #e2e8f0;
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #e2e8f0;
    padding: 1.5rem;
}

.modal-body {
    padding: 2rem;
}

.text-accent {
    color: #5a67d8;
}

.table-cell {
    padding: 1.25rem 1.5rem;
    font-size: 0.875rem;
    color: #2d3748;
}

.col-uploaded-by {
    min-width: 220px;
    max-width: 260px;
    white-space: nowrap;
}

.col-action-sticky {
    position: sticky;
    right: 0;
    background: #ffffff;
    z-index: 3;
}

thead .col-action-sticky {
    background: #edf2f7;
    z-index: 4;
}
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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=3">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Deliverables</h1>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <form method="GET" action="" class="d-flex align-items-center gap-2 flex-wrap" style="gap: 0.5rem;">
            <input
                type="text"
                name="search"
                placeholder="Search..."
                value="<?= h($_GET['search'] ?? '') ?>"
                class="form-control form-control-sm"
                style="width: 250px;" />
            <button type="submit" class="btn btn-primary btn-sm" style="background-color: #6ec1b4; border: none; color: #fff;">
                Search
            </button>
            <select name="year_filter" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                <option value="">Select Year</option>
                <?php
                $yearQuery = "SELECT DISTINCT YEAR(target_date) AS year FROM p_deliverables WHERE target_date IS NOT NULL ORDER BY year ASC";
                $yearResult = $conn->query($yearQuery);
                while ($yearRow = $yearResult->fetch_assoc()) {
                    $selected = ($yearFilter == $yearRow['year']) ? 'selected' : '';
                    echo "<option value='" . $yearRow['year'] . "' $selected>" . $yearRow['year'] . "</option>";
                }
                ?>
            </select>
        </form>

        <div class="d-flex gap-2">
            <a href="form.php" class="btn btn-secondary btn-sm">
                Clear Filter
            </a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFormModal">
                <i data-lucide="plus" class="me-1"></i>Add
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm overflow-hidden" style="overflow-x:auto;">
    <table class="w-full" style="min-width: 1100px;">
        <thead class="table-header">
            <tr>
                <th class="px-6 py-3 text-left text-gray-500">Form Type</th>
                <th class="px-6 py-3 text-left text-gray-500">Title</th>
                <th class="px-6 py-3 text-left text-gray-500">Focal Person</th>
                <th class="px-6 py-3 text-left text-gray-500">Division</th>
                <th class="px-6 py-3 text-left text-gray-500">Target Date</th>
                <th class="px-6 py-3 text-left text-gray-500">Status</th>
                <th class="px-6 py-3 text-left text-gray-500">Actual Date</th>
                <th class="px-6 py-3 text-left text-gray-500">File</th>
                <th class="px-6 py-3 text-left text-gray-500 col-uploaded-by">Uploaded by</th>
                <th class="px-6 py-3 text-left text-gray-500 col-action-sticky">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">No deliverables found for the selected year.</td>
                </tr>
            <?php else: ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="table-row border-b border-gray-100">
                        <td class="px-6 py-4"><?php echo h($row['form_type']); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['title']); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['focal_person']); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['division']); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['target_date']); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['status']); ?></td>
                        <td class="px-6 py-4"><?php echo $row['actual_date'] && $row['actual_date'] != '0000-00-00' ? $row['actual_date'] : '&mdash;'; ?></td>
                        <td class="px-6 py-4">
                            <?php echo $row['mov_file'] ? '<a href="uploads/' . $row['mov_file'] . '" target="_blank" class="text-blue-500 hover:underline">View File</a>' : 'No File'; ?>
                        </td>
                        <td class="px-6 py-4 col-uploaded-by">
                            <?php echo !empty($row['mov_file']) ? h($row['uploaded_by_email'] ?? '') : ''; ?>
                        </td>
                        <td class="px-6 py-4 col-action-sticky">
                            <div class="d-flex gap-2">
                                <button class="btn-secondary editBtn" data-id="<?= h($row['id']) ?>" data-info='<?= json_encode($row) ?>'>
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int)$row['id'] ?>">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="addFormModal" tabindex="-1" aria-labelledby="addFormModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="deliverableForm" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header border-b border-gray-100">
                <h5 class="text-lg font-semibold text-gray-800" id="addFormModalLabel">Add Deliverable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Form Type</label>
                    <select name="form_type" class="form-select w-full" required>
                        <option value="">Select form</option>
                        <option value="Collaborative Healthcare Management">Collaborative Healthcare Management</option>
                        <option value="Research">Research</option>
                        <option value="Training">Training</option>
                        <option value="Culture of Organization">Culture of Organization</option>
                        <option value="Resilience">Resilience</option>
                        <option value="Technology">Technology</option>
                        <option value="Revenue">Revenue</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" class="form-control w-full" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Focal Person</label>
                    <input type="text" name="focal_person" class="form-control w-full" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Division</label>
                    <input type="text" name="division" class="form-control w-full" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Completion Date</label>
                    <input type="date" name="target_date" class="form-control w-full" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Status</label>
                    <select name="status" class="form-select w-full" required>
                        <option value="Accomplished">Accomplished</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Not Yet Started">Not Yet Started</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Actual Completion Date</label>
                    <input type="date" name="actual_date" class="form-control w-full">
                </div>
            </div>
            <div class="modal-footer border-t border-gray-100 p-6">
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Deliverable Modal -->
<div class="modal fade" id="editFormModal" tabindex="-1" aria-labelledby="editFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="editDeliverableForm" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFormModalLabel">Edit Deliverable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label for="edit_form_type" class="form-label">Form Type</label>
                    <select class="form-select" id="edit_form_type" name="form_type" required>
                        <option value="">Select form</option>
                        <option value="Collaborative Healthcare Management">Collaborative Healthcare Management</option>
                        <option value="Research">Research</option>
                        <option value="Training">Training</option>
                        <option value="Culture of Organization">Culture of Organization</option>
                        <option value="Resilience">Resilience</option>
                        <option value="Technology">Technology</option>
                        <option value="Revenue">Revenue</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="edit_title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="edit_title" name="title" required>
                </div>

                <div class="mb-3">
                    <label for="edit_focal_person" class="form-label">Focal Person</label>
                    <input type="text" class="form-control" id="edit_focal_person" name="focal_person" required>
                </div>

                <div class="mb-3">
                    <label for="edit_division" class="form-label">Division</label>
                    <input type="text" class="form-control" id="edit_division" name="division" required>
                </div>

                <div class="mb-3">
                    <label for="edit_target_date" class="form-label">Target Date</label>
                    <input type="date" class="form-control" id="edit_target_date" name="target_date" required>
                </div>

                <div class="mb-3">
                    <label for="edit_status" class="form-label">Status</label>
                    <select class="form-select" id="edit_status" name="status" required>
                        <option value="Accomplished">Accomplished</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Not Yet Started">Not Yet Started</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="edit_actual_date" class="form-label">Actual Date</label>
                    <input type="date" class="form-control" id="edit_actual_date" name="actual_date">
                </div>

                <div class="mb-3">
                    <div id="current_mov_file" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.getElementById('deliverableForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = document.getElementById('deliverableForm');
    const formData = new FormData(form);
    fetch('insert.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Deliverable added successfully!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });

                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('addFormModal'));
                if (modal) {
                    modal.hide();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to insert record.'
                });
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Something went wrong with the server.'
            });
        });
});
</script>

<script>
// Delete deliverable
document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        Swal.fire({
            title: 'Delete this record?',
            text: 'This will permanently delete the row from the database.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('delete_deliverables.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'Record deleted successfully.', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error || 'Delete failed.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Something went wrong while deleting.', 'error');
            });
        });
    });
});
</script>

<script>
// Open edit modal and populate fields
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        const data = JSON.parse(this.getAttribute('data-info'));

        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_title').value = data.title;
        document.getElementById('edit_focal_person').value = data.focal_person;
        document.getElementById('edit_division').value = data.division;
        document.getElementById('edit_form_type').value = data.form_type;
        document.getElementById('edit_target_date').value = data.target_date;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_actual_date').value = data.actual_date || '';

        const currentMOVDiv = document.getElementById('current_mov_file');
        if (data.mov_file) {
            currentMOVDiv.innerHTML = `
                <p class="mb-0">Current File: 
                    <a href="uploads/${data.mov_file}" target="_blank">${data.mov_file}</a>
                </p>
            `;
        } else {
            currentMOVDiv.innerHTML = '<p class="text-muted mb-0">No file uploaded.</p>';
        }

        const editModal = new bootstrap.Modal(document.getElementById('editFormModal'));
        editModal.show();
    });
});

// Handle form submission
document.getElementById('editDeliverableForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);

    fetch('update.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Updated!', 'Deliverable updated successfully.', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Update failed.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Something went wrong while updating.', 'error');
        });
});
</script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

