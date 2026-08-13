<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

// Get form data from URL parameter or use empty defaults
$formData = [
    'department' => $_GET['department'] ?? '',
    'head_deputy' => $_GET['head_deputy'] ?? '',
    'documenter' => $_GET['documenter'] ?? '',
    'strategic_contributions' => $_GET['strategic_contributions'] ?? '',
    'deliverable' => $_GET['deliverable'] ?? '',
    'deadline' => $_GET['deadline'] ?? '',
    'status' => $_GET['status'] ?? '',
    'meeting_venue_schedule' => $_GET['meeting_venue_schedule'] ?? '',
    'scoreboard_location_oic' => $_GET['scoreboard_location_oic'] ?? '',
    'action_point' => $_GET['action_point'] ?? '',
    'responsible_person' => $_GET['responsible_person'] ?? '',
    'target_date' => $_GET['target_date'] ?? '',
    'action_status' => $_GET['action_status'] ?? '',
    'wins_celebrated' => $_GET['wins_celebrated'] ?? '',
    'best_performers_recognized' => $_GET['best_performers_recognized'] ?? '',
    'frequency' => $_GET['frequency'] ?? '',
    'prepared_by' => $_GET['prepared_by'] ?? '',
    'approved_by' => $_GET['approved_by'] ?? ''
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Review Template</title>
    <?= page_or_bundle_css('css/pages/print.css') ?>
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
                    <span class="value fill-value"><?= htmlspecialchars($formData['department']) ?></span>
                </div>
            </div>
        </div>
        <div class="column">
            <div class="section">
                <div class="section-title">Head/Deputy</div>
                <div class="field">
                    <span class="value fill-value"><?= htmlspecialchars($formData['head_deputy']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Documenter</div>
        <div class="field">
            <span class="value fill-value"><?= htmlspecialchars($formData['documenter']) ?></span>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Strategic Contributions</div>
        <div class="strategic-box fill-value">
            <?= nl2br(htmlspecialchars($formData['strategic_contributions'])) ?>
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
                <td class="fill-value"><?= htmlspecialchars($formData['deliverable']) ?></td>
                <td class="fill-value"><?= htmlspecialchars($formData['deadline']) ?></td>
                <td class="fill-value"><?= htmlspecialchars($formData['status']) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Operations Review</div>
        <div class="field">
            <span class="label">Meeting Venue & Schedule:</span>
            <span class="value fill-value"><?= htmlspecialchars($formData['meeting_venue_schedule']) ?></span>
        </div>
        <div class="field">
            <span class="label">Scoreboard Location & OIC:</span>
            <span class="value fill-value"><?= htmlspecialchars($formData['scoreboard_location_oic']) ?></span>
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
                <td class="fill-value"><?= htmlspecialchars($formData['action_point']) ?></td>
                <td class="fill-value"><?= htmlspecialchars($formData['responsible_person']) ?></td>
                <td class="fill-value"><?= htmlspecialchars($formData['target_date']) ?></td>
                <td class="fill-value"><?= htmlspecialchars($formData['action_status']) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Recognition</div>
        <div class="field">
            <span class="label">How Wins Are Celebrated:</span>
            <div class="mt-3px" class="fill-value"><?= nl2br(htmlspecialchars($formData['wins_celebrated'])) ?></div>
        </div>
        <div class="field">
            <span class="label">How Best Performers Are Recognized:</span>
            <div class="mt-3px" class="fill-value"><?= nl2br(htmlspecialchars($formData['best_performers_recognized'])) ?></div>
        </div>
        <div class="field">
            <span class="label">Frequency:</span>
            <span class="value fill-value"><?= htmlspecialchars($formData['frequency']) ?></span>
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
                <span class="value fill-value">(<?= htmlspecialchars($formData['approved_by']) ?>)</span>
            </div>
            <div class="signature-line"></div>
            <div class="signature-label">Unit Head</div>
        </div>
    </div>
</body>
</html>
