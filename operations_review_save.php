<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'employee') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$required = ['department', 'head_deputy', 'documenter'];
foreach ($required as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        echo json_encode(['success' => false, 'error' => "Field $field is required"]);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // Generate PDF filename
    $pdfFilename = 'operations_review_' . date('Y-m-d_H-i-s') . '_' . $_SESSION['user_id'] . '.pdf';
    
    // Insert record
    $stmt = $pdo->prepare("
        INSERT INTO operations_review (employee_id, form_data, pdf_file, created_at)
        VALUES (:eid, :data, :pdf, NOW())
    ");
    $stmt->execute([
        ':eid' => $_SESSION['user_id'],
        ':data' => json_encode($input),
        ':pdf' => $pdfFilename
    ]);

    $recordId = $pdo->lastInsertId();
    $pdo->commit();

    // Generate and save PDF file
    generateAndSavePdf($recordId, $input, $pdfFilename);

    echo json_encode(['success' => true, 'id' => $recordId]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Operations review save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to save form']);
}

function generateAndSavePdf($recordId, $data, $filename) {
    // Build URL with form data
    $queryString = http_build_query($data);
    $templateUrl = "operations_review_template.php?" . $queryString;
    
    // Use cURL to get the rendered HTML
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $templateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        // Fallback to basic HTML generation
        $html = generateBasicHtml($data);
    }
    
    // Save HTML file
    $filePath = __DIR__ . '/uploads/operations_review/' . $filename;
    file_put_contents($filePath, $html);
}

function generateBasicHtml($data) {
    return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Review</title>
    <link rel="stylesheet" href="' . asset('css/pages/print.css') . '">
</head>
<body>
    <div class="header">
        <h1>Performance Assessment: Operations Review</h1>
        <div class="subtitle">TRC-LU Operations Review Template</div>
    </div>
    
    <div class="two-column">
        <div class="column">
            <div class="section">
                <div class="section-title">Department/Division</div>
                <div class="field">
                    <span class="value fill-value">' . htmlspecialchars($data['department'] ?? '') . '</span>
                </div>
            </div>
        </div>
        <div class="column">
            <div class="section">
                <div class="section-title">Head/Deputy</div>
                <div class="field">
                    <span class="value fill-value">' . htmlspecialchars($data['head_deputy'] ?? '') . '</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Documenter</div>
        <div class="field">
            <span class="value fill-value">' . htmlspecialchars($data['documenter'] ?? '') . '</span>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Strategic Contributions</div>
        <div class="strategic-box fill-value">
            ' . nl2br(htmlspecialchars($data['strategic_contributions'] ?? '')) . '
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Deliverable/s Deadline Status</div>
        <table class="table-field">
            <tr>
                <th style="width: 40%;">Deliverable</th>
                <th class="w-25">Deadline</th>
                <th class="w-35">Status</th>
            </tr>
            <tr>
                <td class="fill-value">' . htmlspecialchars($data['deliverable'] ?? '') . '</td>
                <td class="fill-value">' . htmlspecialchars($data['deadline'] ?? '') . '</td>
                <td class="fill-value">' . htmlspecialchars($data['status'] ?? '') . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Operations Review</div>
        <div class="field">
            <span class="label">Meeting Venue & Schedule:</span>
            <span class="value fill-value">' . htmlspecialchars($data['meeting_venue_schedule'] ?? '') . '</span>
        </div>
        <div class="field">
            <span class="label">Scoreboard Location & OIC:</span>
            <span class="value fill-value">' . htmlspecialchars($data['scoreboard_location_oic'] ?? '') . '</span>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Action Points</div>
        <table class="table-field">
            <tr>
                <th class="w-35">Action Point</th>
                <th class="w-25">Responsible Person</th>
                <th class="w-20">Target Date</th>
                <th class="w-20">Status</th>
            </tr>
            <tr>
                <td class="fill-value">' . htmlspecialchars($data['action_point'] ?? '') . '</td>
                <td class="fill-value">' . htmlspecialchars($data['responsible_person'] ?? '') . '</td>
                <td class="fill-value">' . htmlspecialchars($data['target_date'] ?? '') . '</td>
                <td class="fill-value">' . htmlspecialchars($data['action_status'] ?? '') . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Recognition</div>
        <div class="field">
            <span class="label">How Wins Are Celebrated:</span>
            <div class="mt-3px" class="fill-value">' . nl2br(htmlspecialchars($data['wins_celebrated'] ?? '')) . '</div>
        </div>
        <div class="field">
            <span class="label">How Best Performers Are Recognized:</span>
            <div class="mt-3px" class="fill-value">' . nl2br(htmlspecialchars($data['best_performers_recognized'] ?? '')) . '</div>
        </div>
        <div class="field">
            <span class="label">Frequency:</span>
            <span class="value fill-value">' . htmlspecialchars($data['frequency'] ?? '') . '</span>
        </div>
    </div>
    
    <div class="signature-area">
        <div class="signature-box">
            <div class="field">
                <span class="label">Prepared by:</span>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Name & Signature</div>
        </div>
        <div class="signature-box">
            <div class="field">
                <span class="label">Approved by:</span>
                <span class="value fill-value">(' . htmlspecialchars($data['approved_by'] ?? '') . ')</span>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Unit Head</div>
        </div>
    </div>
</body>
</html>';
}
