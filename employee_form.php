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
<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/employee_form.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-800">Form Upload</h1>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <form method="GET" action="" class="d-flex align-items-center gap-2 flex-wrap" style="gap: 0.5rem;">
  <?= csrf_field() ?>
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
            <a href="employee_form" class="btn btn-secondary btn-sm">Clear Filter</a>
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
  <?= csrf_field() ?>
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
<script src="<?= asset('js/pages/employee_form_1.js') ?>"></script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

