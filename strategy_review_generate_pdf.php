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
        <link rel="stylesheet" href="' . asset('css/pages/strategy_review_generate_pdf.css') . '">
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
                        <th class="w-25">MEASURE</th>
                        <th class="w-25">TARGET</th>
                        <th class="w-25">ACTUAL TO DATE</th>
                        <th class="w-25">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="h-40">' . htmlspecialchars($data['measure'] ?? '') . '</td>
                        <td class="h-40">' . htmlspecialchars($data['target'] ?? '') . '</td>
                        <td class="h-40">' . htmlspecialchars($data['actual_to_date_measure'] ?? '') . '</td>
                        <td class="h-40">' . htmlspecialchars($data['status_measure'] ?? '') . '</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top:18px;">
                <thead>
                    <tr>
                        <th class="w-25">KEY RESULTS AREA</th>
                        <th class="w-25">DELIVERABLE</th>
                        <th class="w-25">ACTUAL TO DATE</th>
                        <th class="w-25">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="h-34">' . htmlspecialchars($data['kra1_key_results_area'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra1_deliverable'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra1_actual_to_date'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra1_status'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="h-34">' . htmlspecialchars($data['kra2_key_results_area'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra2_deliverable'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra2_actual_to_date'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra2_status'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="h-34">' . htmlspecialchars($data['kra3_key_results_area'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra3_deliverable'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra3_actual_to_date'] ?? '') . '</td>
                        <td class="h-34">' . htmlspecialchars($data['kra3_status'] ?? '') . '</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top:18px;">
                <thead>
                    <tr>
                        <th class="w-33">CONTINUE</th>
                        <th class="w-33">STOP</th>
                        <th class="w-33">START</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="h-40">' . nl2br(htmlspecialchars($data['continue'] ?? '')) . '</td>
                        <td class="h-40">' . nl2br(htmlspecialchars($data['stop'] ?? '')) . '</td>
                        <td class="h-40">' . nl2br(htmlspecialchars($data['start'] ?? '')) . '</td>
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
