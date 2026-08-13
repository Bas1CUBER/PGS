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

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/strategy_review_form.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
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
                            <table class="table table-bordered align-middle mb-4" table-fixed>
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
                                        <th class="text-center" header-cyan>MEASURE</th>
                                        <th class="text-center" header-cyan>TARGET</th>
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
                                        <th class="text-center" header-cyan>ACTUAL TO DATE</th>
                                        <th class="text-center" header-cyan>STATUS</th>
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

                            <table class="table table-bordered align-middle mb-4" table-fixed>
                                <thead>
                                    <tr>
                                        <th class="text-center" header-cyan-w25>KEY RESULTS AREA</th>
                                        <th class="text-center" header-cyan-w25>DELIVERABLE</th>
                                        <th class="text-center" header-cyan-w25>ACTUAL TO DATE</th>
                                        <th class="text-center" header-cyan-w25>STATUS</th>
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

                            <table class="table table-bordered align-middle mb-4" table-fixed>
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

