<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
require_page_access('performance_assessment');

// Check if strategy_review_forms table exists
$tableExists = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'strategy_review_forms'");
    $tableExists = $res && $res->num_rows > 0;
} catch (Throwable $e) {
    $tableExists = false;
}

// Create table if it doesn't exist
if (!$tableExists) {
    $createTable = "
    CREATE TABLE IF NOT EXISTS strategy_review_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        form_data TEXT NOT NULL,
        pdf_filename VARCHAR(255),
        status ENUM('Draft', 'Submitted', 'Approved', 'Returned') DEFAULT 'Draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($createTable);
}

$forms = [];
if ($tableExists) {
    $q = $conn->query("
        SELECT f.id, f.form_data, f.pdf_filename, f.status, f.created_at, f.updated_at, u.email AS employee_email
        FROM strategy_review_forms f
        JOIN users u ON f.employee_id = u.id
        ORDER BY f.created_at DESC
    ");
    while ($q && ($r = $q->fetch_assoc())) {
        $forms[] = $r;
    }
}

$pageTitle = 'Strategy Review Form';

$pageStyles = '<style>
html, body {
    background-color: #f5f7fa;
    color: #2c3e50;
    margin: 0;
    padding: 0;
}
.card {
    border: none;
    border-radius: 1rem;
    background-color: #ffffff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.card-body {
    padding: 2rem;
}
.section-title {
    background: #196a6b;
    color: #fff;
    text-align: center;
    font-weight: 700;
    letter-spacing: 0.04em;
    padding: 14px 16px;
    border-radius: 1rem 1rem 0 0;
}
.form-container {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 30px;
    margin: 20px 0;
}
.form-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #196a6b;
}
.form-header h2 {
    color: #196a6b;
    font-weight: 600;
    margin-bottom: 10px;
}
.form-section {
    margin-bottom: 25px;
}
.form-section h4 {
    color: #196a6b;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    align-items: flex-end;
}
.form-group {
    flex: 1;
}
.form-group label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}
.form-control, .form-select {
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.form-control:focus, .form-select:focus {
    border-color: #196a6b;
    box-shadow: 0 0 0 0.2rem rgba(25, 106, 107, 0.25);
}
.btn-primary-custom {
    background-color: #196a6b;
    border-color: #196a6b;
    color: #fff;
    padding: 10px 25px;
    font-weight: 500;
    border-radius: 6px;
}
.btn-primary-custom:hover {
    background-color: #145556;
    border-color: #145556;
    color: #fff;
}
.btn-secondary-custom {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #fff;
    padding: 10px 25px;
    font-weight: 500;
    border-radius: 6px;
}
.table th {
    background-color: #f0f2f5;
    color: #34495e;
    font-weight: 600;
    border-color: #e9ecef;
}
.badge {
    font-size: 0.8em;
}
.pdf-preview {
    border: 1px solid #ddd;
    border-radius: 8px;
    max-height: 600px;
    overflow: auto;
    background: #fff;
}
#formContent {
    line-height: 1.6;
}
.form-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    font-size: 12px;
    color: #6c757d;
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

<div class="card shadow-sm mb-5">
    <div class="section-title">STRATEGY REVIEW FORM</div>
    <div class="card-body">
        <?php if (($_SESSION['role'] ?? null) === 'employee'): ?>
            <!-- Employee Form View -->
            <div class="form-container" id="formContent">
                <div class="form-header">
                    <h2>TRC-LU STRATEGY REVIEW</h2>
                    <p>Strategy Review Template and Process Flow</p>
                </div>

                <form id="strategyReviewForm">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="review_date">DATE</label>
                                <input type="date" class="form-control" id="review_date" name="review_date" required>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-4" style="table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:50%;">OBJECTIVE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:50%;">DIRECTLY CONTRIBUTING UNITS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <textarea class="form-control" id="objective" name="objective" rows="4" required></textarea>
                                        </td>
                                        <td>
                                            <textarea class="form-control" id="directly_contributing_units" name="directly_contributing_units" rows="4" required></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700;">MEASURE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700;">TARGET</th>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control" id="measure" name="measure" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" id="target" name="target" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700;">ACTUAL TO DATE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700;">STATUS</th>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control" id="actual_to_date_measure" name="actual_to_date_measure" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" id="status_measure" name="status_measure" required>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="table table-bordered align-middle mb-4" style="table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:25%;">KEY RESULTS AREA</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:25%;">DELIVERABLE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:25%;">ACTUAL TO DATE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:25%;">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" class="form-control" name="kra1_key_results_area" required></td>
                                        <td><input type="text" class="form-control" name="kra1_deliverable" required></td>
                                        <td><input type="text" class="form-control" name="kra1_actual_to_date" required></td>
                                        <td><input type="text" class="form-control" name="kra1_status" required></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" class="form-control" name="kra2_key_results_area"></td>
                                        <td><input type="text" class="form-control" name="kra2_deliverable"></td>
                                        <td><input type="text" class="form-control" name="kra2_actual_to_date"></td>
                                        <td><input type="text" class="form-control" name="kra2_status"></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" class="form-control" name="kra3_key_results_area"></td>
                                        <td><input type="text" class="form-control" name="kra3_deliverable"></td>
                                        <td><input type="text" class="form-control" name="kra3_actual_to_date"></td>
                                        <td><input type="text" class="form-control" name="kra3_status"></td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="table table-bordered align-middle mb-4" style="table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:33.33%;">CONTINUE</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:33.33%;">STOP</th>
                                        <th class="text-center" style="background:#00A3D9; color:#000; font-weight:700; width:33.33%;">START</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><textarea class="form-control" name="continue" rows="3" required></textarea></td>
                                        <td><textarea class="form-control" name="stop" rows="3" required></textarea></td>
                                        <td><textarea class="form-control" name="start" rows="3" required></textarea></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="prepared_by">PREPARED BY:</label>
                                <input type="text" class="form-control" id="prepared_by" name="prepared_by" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="approved_by">APPROVED BY: (UNIT HEAD)</label>
                                <input type="text" class="form-control" id="approved_by" name="approved_by" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer">
                        <p>TRC-LU Strategy Review Form - Page 1</p>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <button type="button" class="btn btn-primary-custom me-2" onclick="generatePDF()">
                        <i data-lucide="file-text" class="me-2"></i>Generate PDF
                    </button>
                    <button type="button" class="btn btn-secondary-custom" onclick="saveDraft()">
                        <i data-lucide="save" class="me-2"></i>Save Draft
                    </button>
                </div>
            </div>

            <!-- Submitted Forms -->
            <?php if (!empty($forms)): ?>
                <div class="mt-5">
                    <h4 class="mb-4">Your Submitted Forms</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Date Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $employeeForms = array_filter($forms, function($f) {
                                    return $f['employee_id'] == $_SESSION['user_id'];
                                });
                                foreach ($employeeForms as $f): ?>
                                    <tr>
                                        <td><?= date('Y-m-d H:i:s', strtotime($f['created_at'])) ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                switch($f['status']) {
                                                    case 'Approved':
                                                        echo 'bg-success';
                                                        break;
                                                    case 'Returned':
                                                        echo 'bg-danger';
                                                        break;
                                                    case 'Submitted':
                                                        echo 'bg-info';
                                                        break;
                                                    default:
                                                        echo 'bg-warning text-dark';
                                                }
                                                ?>">
                                                <?= h($f['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($f['pdf_filename']): ?>
                                                <a href="strategy_review_forms/<?= h($f['pdf_filename']) ?>" 
                                                   class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                    <i data-lucide="eye" class="me-1"></i> View (HTML)
                                                </a>
                                                <a href="strategy_review_forms/<?= h($f['pdf_filename']) ?>" 
                                                   class="btn btn-sm btn-outline-success" download>
                                                    <i data-lucide="download" class="me-1"></i> Download
                                                </a>
                                                <small class="text-muted ms-2">(Print to save as PDF)</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Admin View -->
            <h4 class="mb-4">Submitted Strategy Review Forms</h4>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date Created</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($forms)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    No forms submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($forms as $f): ?>
                                <tr>
                                    <td><?= h($f['employee_email']) ?></td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($f['created_at'])) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch($f['status']) {
                                                case 'Approved':
                                                    echo 'bg-success';
                                                    break;
                                                case 'Returned':
                                                    echo 'bg-danger';
                                                    break;
                                                case 'Submitted':
                                                    echo 'bg-info';
                                                    break;
                                                default:
                                                    echo 'bg-warning text-dark';
                                            }
                                            ?>">
                                            <?= h($f['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($f['pdf_filename']): ?>
                                            <a href="strategy_review_forms/<?= h($f['pdf_filename']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                                <i data-lucide="eye" class="me-1"></i> View
                                            </a>
                                            <a href="strategy_review_forms/<?= h($f['pdf_filename']) ?>" 
                                               class="btn btn-sm btn-outline-success" download>
                                                <i data-lucide="download" class="me-1"></i> Download
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<?php if (($_SESSION['role'] ?? null) === 'employee'): ?>
<script>
function generatePDF() {
    const form = document.getElementById('strategyReviewForm');
    
    // Validate form
    if (!form.checkValidity()) {
        Swal.fire('Error', 'Please fill in all required fields', 'error');
        return;
    }

    // Collect form data
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Show loading
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we generate your PDF',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send to server
    fetch('strategy_review_generate_pdf.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        Swal.close();
        if (result.success) {
            Swal.fire({
                title: 'Success!',
                text: 'PDF generated successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', result.error || 'Failed to generate PDF', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to generate PDF', 'error');
    });
}

function saveDraft() {
    const form = document.getElementById('strategyReviewForm');
    
    // Collect form data
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Show loading
    Swal.fire({
        title: 'Saving Draft...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send to server
    fetch('strategy_review_save_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        Swal.close();
        if (result.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Draft saved successfully',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', result.error || 'Failed to save draft', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to save draft', 'error');
    });
}

// Set today's date as default
document.getElementById('review_date').valueAsDate = new Date();
</script>
<?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

