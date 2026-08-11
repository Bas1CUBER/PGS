<?php
require_once __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Ensure table exists (in case user hits this endpoint before visiting the form page)
$conn->query("
    CREATE TABLE IF NOT EXISTS strategy_review_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        form_data TEXT NOT NULL,
        pdf_filename VARCHAR(255),
        status ENUM('Draft', 'Submitted', 'Approved', 'Returned') DEFAULT 'Draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Create forms directory if it doesn't exist
$formsDir = __DIR__ . '/strategy_review_forms';
if (!is_dir($formsDir)) {
    mkdir($formsDir, 0755, true);
}

// Generate unique filename
$filename = 'strategy_review_' . $_SESSION['user_id'] . '_' . time() . '.html';
$filepath = $formsDir . '/' . $filename;

// Generate HTML content
$html = generatePDFHTML($data);

// Save HTML file
if (@file_put_contents($filepath, $html) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to save generated file']);
    exit();
}

// Save to database
$stmt = $conn->prepare("
    INSERT INTO strategy_review_forms 
    (employee_id, form_data, pdf_filename, status) 
    VALUES (?, ?, ?, 'Submitted')
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database prepare failed']);
    exit();
}

$formJson = json_encode($data);
if ($formJson === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to encode form data']);
    exit();
}

// 3 params only => "iss"
$stmt->bind_param(
    "iss",
    $_SESSION['user_id'],
    $formJson,
    $filename
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

function generatePDFHTML($data) {
    $headerBlue = '#00A3D9';

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>TRC-LU Strategy Review</title>
        <style>
            @page { size: A4; margin: 30px 30px; }
            body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 0; }
            .page { padding: 0; }
            .title { text-align: center; font-weight: 700; margin-top: 10px; }
            .subtitle { text-align: center; font-weight: 700; margin-top: 2px; }
            .dateLine { text-align: center; font-weight: 700; margin-top: 2px; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            th, td { border: 1px solid #000; padding: 6px; vertical-align: top; }
            th { background: ' . $headerBlue . '; color: #000; text-align: center; font-weight: 700; }
            .spacer { height: 14px; }
            .label { font-weight: 700; margin-top: 18px; }
            .sigline { display: inline-block; width: 60%; border-bottom: 1px solid #000; height: 14px; vertical-align: bottom; }
            .sigtext { margin-top: 34px; }
            .small { font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="page">
            <div class="title">TRC-LU STRATEGY REVIEW FORM</div>
            <div class="dateLine">(' . htmlspecialchars($data['review_date'] ?? '') . ')</div>

            <div class="spacer"></div>

            <table>
                <thead>
                    <tr>
                        <th style="width:50%;">OBJECTIVE</th>
                        <th style="width:50%;">DIRECTLY CONTRIBUTING UNITS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height:70px;">' . nl2br(htmlspecialchars($data['objective'] ?? '')) . '</td>
                        <td style="height:70px;">' . nl2br(htmlspecialchars($data['directly_contributing_units'] ?? '')) . '</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top:-1px;">
                <thead>
                    <tr>
                        <th style="width:25%;">MEASURE</th>
                        <th style="width:25%;">TARGET</th>
                        <th style="width:25%;">ACTUAL TO DATE</th>
                        <th style="width:25%;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height:40px;">' . htmlspecialchars($data['measure'] ?? '') . '</td>
                        <td style="height:40px;">' . htmlspecialchars($data['target'] ?? '') . '</td>
                        <td style="height:40px;">' . htmlspecialchars($data['actual_to_date_measure'] ?? '') . '</td>
                        <td style="height:40px;">' . htmlspecialchars($data['status_measure'] ?? '') . '</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top:18px;">
                <thead>
                    <tr>
                        <th style="width:25%;">KEY RESULTS AREA</th>
                        <th style="width:25%;">DELIVERABLE</th>
                        <th style="width:25%;">ACTUAL TO DATE</th>
                        <th style="width:25%;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height:34px;">' . htmlspecialchars($data['kra1_key_results_area'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra1_deliverable'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra1_actual_to_date'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra1_status'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td style="height:34px;">' . htmlspecialchars($data['kra2_key_results_area'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra2_deliverable'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra2_actual_to_date'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra2_status'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td style="height:34px;">' . htmlspecialchars($data['kra3_key_results_area'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra3_deliverable'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra3_actual_to_date'] ?? '') . '</td>
                        <td style="height:34px;">' . htmlspecialchars($data['kra3_status'] ?? '') . '</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top:18px;">
                <thead>
                    <tr>
                        <th style="width:33.33%;">CONTINUE</th>
                        <th style="width:33.33%;">STOP</th>
                        <th style="width:33.33%;">START</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height:40px;">' . nl2br(htmlspecialchars($data['continue'] ?? '')) . '</td>
                        <td style="height:40px;">' . nl2br(htmlspecialchars($data['stop'] ?? '')) . '</td>
                        <td style="height:40px;">' . nl2br(htmlspecialchars($data['start'] ?? '')) . '</td>
                    </tr>
                </tbody>
            </table>

            <div class="sigtext">
                <div class="label">Prepared by:</div>
                <div>' . htmlspecialchars($data['prepared_by'] ?? '') . '</div>

                <div class="label" style="margin-top:26px;">Approved by: (Unit Head)</div>
                <div>' . htmlspecialchars($data['approved_by'] ?? '') . '</div>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
?>
