<?php
require_once __DIR__ . '/src/bootstrap.php';

// Restrict access to employees and focal
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['employee','focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

$search = $_GET['search'] ?? '';
$yearFilter = $_GET['year_filter'] ?? '';

$sql = "SELECT * FROM p_deliverables WHERE 1";

if (!empty($yearFilter)) {
    $sql .= " AND YEAR(target_date) = " . intval($yearFilter);
}

if (!empty($search)) {
    $searchSafe = $conn->real_escape_string($search);
    $sql .= " AND (title LIKE '%$searchSafe%' OR focal_person LIKE '%$searchSafe%' OR division LIKE '%$searchSafe%' OR form_type LIKE '%$searchSafe%')";
}

$sql .= " ORDER BY target_date ASC";
$result = $conn->query($sql);

$pageTitle = 'Form Upload';

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

.modal-content {
    border-radius: 1rem;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    background: #ffffff;
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

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Form Upload</h1>
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
            <a href="employee_form.php" class="btn btn-secondary btn-sm">Clear Filter</a>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <table class="w-full">
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
                <th class="px-6 py-3 text-left text-gray-500">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$result || $result->num_rows === 0): ?>
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-gray-500">No deliverables found for the selected year.</td>
                </tr>
            <?php else: ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="table-row border-b border-gray-100">
                        <td class="px-6 py-4"><?php echo h($row['form_type'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['title'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['focal_person'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['division'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['target_date'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo h($row['status'] ?? ''); ?></td>
                        <td class="px-6 py-4"><?php echo (!empty($row['actual_date']) && $row['actual_date'] !== '0000-00-00') ? h($row['actual_date']) : '&mdash;'; ?></td>
                        <td class="px-6 py-4">
                            <?php if (!empty($row['mov_file'])): ?>
                                <a href="uploads/<?php echo rawurlencode($row['mov_file']); ?>" target="_blank" class="text-blue-500 hover:underline">View File</a>
                            <?php else: ?>
                                No File
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($row['mov_file'])): ?>
                                <a href="uploads/<?php echo rawurlencode($row['mov_file']); ?>" target="_blank" class="btn btn-sm btn-secondary">View Document</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary uploadBtn" data-id="<?= (int)$row['id'] ?>">Upload</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="uploadForm" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload MOV File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="upload_id">
                <div class="mb-3">
                    <label for="mov_file" class="form-label">MOV (Attach File)</label>
                    <input type="file" name="mov_file" id="mov_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    <div class="form-text">Once uploaded, this file can no longer be replaced.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelectorAll('.uploadBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('upload_id').value = this.getAttribute('data-id');
        document.getElementById('mov_file').value = '';
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    });
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('employee_upload.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Uploaded!', 'File uploaded successfully.', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.message || 'Upload failed.', 'error');
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Something went wrong while uploading.', 'error');
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

