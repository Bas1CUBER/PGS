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
    <style>
        @page { margin: 0.5in; }
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px;
            font-size: 11px;
            line-height: 1.2;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 { 
            font-size: 16px; 
            font-weight: bold; 
            margin: 0;
            text-transform: uppercase;
        }
        .header .subtitle { 
            font-size: 12px; 
            margin: 5px 0;
            font-weight: bold;
        }
        .two-column {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .column {
            flex: 1;
        }
        .section { 
            margin-bottom: 20px; 
        }
        .section-title { 
            font-weight: bold; 
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        .field { 
            margin-bottom: 5px; 
            font-size: 11px;
        }
        .label { 
            font-weight: bold; 
            display: inline-block; 
            width: 120px;
        }
        .value { 
            display: inline-block; 
            border-bottom: 1px solid #000;
            min-width: 150px;
            padding: 2px;
        }
        .table-field { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px;
        }
        .table-field th, .table-field td { 
            border: 1px solid #000; 
            padding: 4px; 
            font-size: 10px;
            text-align: left;
        }
        .table-field th { 
            background-color: #f0f0f0; 
            font-weight: bold;
        }
        .signature-area { 
            margin-top: 30px; 
            display: flex;
            gap: 40px;
        }
        .signature-box {
            flex: 1;
        }
        .signature-line { 
            border-bottom: 1px solid #000; 
            width: 100%; 
            margin-bottom: 3px;
            height: 20px;
        }
        .signature-label {
            font-size: 10px;
            text-align: center;
        }
        .strategic-box {
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 10px;
            min-height: 60px;
        }
        .fill-value {
            background-color: #f9f9f9;
        }
    </style>
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
                <th style="width: 25%;">Deadline</th>
                <th style="width: 35%;">Status</th>
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
                <th style="width: 35%;">Action Point</th>
                <th style="width: 25%;">Responsible Person</th>
                <th style="width: 20%;">Target Date</th>
                <th style="width: 20%;">Status</th>
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
            <div style="margin-top: 3px;" class="fill-value">' . nl2br(htmlspecialchars($data['wins_celebrated'] ?? '')) . '</div>
        </div>
        <div class="field">
            <span class="label">How Best Performers Are Recognized:</span>
            <div style="margin-top: 3px;" class="fill-value">' . nl2br(htmlspecialchars($data['best_performers_recognized'] ?? '')) . '</div>
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
