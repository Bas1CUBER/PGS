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
        <script src="' . asset('js/pages/OPCR_1.js') . '"></script>
    </body>
    </html>
    ';
    exit();
}

// Fetch existing data
try {
    $stmt = $pdo->prepare("
        SELECT id, strategic_goal, success_indicator, division_accountable, annual_target,
               quarter1_target, quarter2_target, quarter3_target, quarter4_target, remarks
        FROM performance_targets
    ");
    $stmt->execute();
    $existing_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map by unique composite key for updates
    $existing_data_map = [];
    foreach ($existing_data as $data) {
        $key = md5(($data['strategic_goal'] ?? '') . $data['success_indicator'] . $data['division_accountable'] . ($data['annual_target'] ?? ''));
        $existing_data_map[$key] = $data;
    }
} catch (PDOException $e) {
    $error_message = "Error fetching existing data: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $insert_stmt = $pdo->prepare("
            INSERT INTO performance_targets (
                strategic_goal, success_indicator, division_accountable, annual_target,
                quarter1_target, quarter2_target, quarter3_target, quarter4_target, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $update_stmt = $pdo->prepare("
            UPDATE performance_targets
            SET strategic_goal = ?, quarter1_target = ?, quarter2_target = ?, 
                quarter3_target = ?, quarter4_target = ?, remarks = ?
            WHERE success_indicator = ? AND division_accountable = ? AND annual_target = ?
        ");

        if (!empty($_POST['rows']) && is_array($_POST['rows'])) {
            foreach ($_POST['rows'] as $index => $row) {
                $strategic_goal = filter_var($_POST['strategic_goal'][$index] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $success_indicator = filter_var($_POST['success_indicator'][$index] ?? '', FILTER_SANITIZE_STRING);
                $division_accountable = filter_var($_POST['division_accountable'][$index] ?? '', FILTER_SANITIZE_STRING);
                $annual_target = filter_var($_POST['annual_target'][$index] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $quarter1 = filter_var($row['quarter1_target'] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $quarter2 = filter_var($row['quarter2_target'] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $quarter3 = filter_var($row['quarter3_target'] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $quarter4 = filter_var($row['quarter4_target'] ?? '', FILTER_SANITIZE_STRING) ?: null;
                $remarks = filter_var($row['remarks'] ?? '', FILTER_SANITIZE_STRING) ?: null;

                // Validate required fields
                if (empty($success_indicator) || empty($division_accountable)) {
                    throw new Exception("Success Indicator and Division Accountable are required for row " . ($index + 1));
                }

                // Validate numeric fields where provided
                foreach (['quarter1_target', 'quarter2_target', 'quarter3_target', 'quarter4_target'] as $field) {
                    if (!empty($row[$field]) && !preg_match('/^(?:(?:\d+(?:\.\d+)?)%(?:\s*\([^)]+\))?|TBD|N\/A|\d+(?:\.\d+)?(?:\s*\([^)]+\))?)$/', $row[$field])) {
                        throw new Exception("Invalid value in $field for row " . ($index + 1));
                    }
                }

                $key = md5(($strategic_goal ?? '') . $success_indicator . $division_accountable . ($annual_target ?? ''));

                if (isset($existing_data_map[$key])) {
                    $update_stmt->execute([
                        $strategic_goal,
                        $quarter1,
                        $quarter2,
                        $quarter3,
                        $quarter4,
                        $remarks,
                        $success_indicator,
                        $division_accountable,
                        $annual_target
                    ]);
                } else {
                    $insert_stmt->execute([
                        $strategic_goal,
                        $success_indicator,
                        $division_accountable,
                        $annual_target,
                        $quarter1,
                        $quarter2,
                        $quarter3,
                        $quarter4,
                        $remarks
                    ]);
                }
            }
        }

        $pdo->commit();
        $success_message = "Data successfully saved!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error saving data: " . $e->getMessage();
    }
}

// export
// Handle export requests
if (isset($_GET['export'])) {
    $format = $_GET['export'];

    // Fetch data for export
    try {
        $stmt = $pdo->prepare("
            SELECT strategic_goal, success_indicator, division_accountable, annual_target,
                   quarter1_target, quarter2_target, quarter3_target, quarter4_target, remarks
            FROM performance_targets
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set headers for download
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="performance_targets.csv"');
            $output = fopen('php://output', 'w');

            // Add column headers
            fputcsv($output, [
                'Strategic Goal',
                'Success Indicator',
                'Division Accountable',
                'Annual Target',
                'Quarter 1 Target',
                'Quarter 2 Target',
                'Quarter 3 Target',
                'Quarter 4 Target',
                'Remarks'
            ]);

            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        } elseif ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="performance_targets.xls"');

            // Create tab-separated content for Excel
            echo implode("\t", [
                'Strategic Goal',
                'Success Indicator',
                'Division Accountable',
                'Annual Target',
                'Quarter 1 Target',
                'Quarter 2 Target',
                'Quarter 3 Target',
                'Quarter 4 Target',
                'Remarks'
            ]) . "\n";

            foreach ($data as $row) {
                // Clean data to prevent issues with special characters
                $row = array_map(function ($value) {
                    return str_replace(["\t", "\n", "\r"], ' ', $value ?? '');
                }, $row);
                echo implode("\t", $row) . "\n";
            }
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "Error exporting data: " . $e->getMessage();
    }
}

$pageTitle = 'Admin Dashboard - TRC Modern';

$pageStyles = '<link rel="stylesheet" href="' . page_or_bundle_css('css/pages/OPCR.css') . '">';

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

<section>
    <div class="card p-4 shadow-sm">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo h($success_message); ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo h($error_message); ?></div>
        <?php endif; ?>
        <div class="d-flex justify-content-center mb-3">
            <a href="?export=excel" class="btn btn-green btn-sm">Export to Excel</a>
        </div>

        <form method="POST" action="" id="performanceForm">
            <?= csrf_field() ?>
            <div class="overflow-x-auto">
                <div class="container">
                    <div class="table-responsive">
                        <table class="modern-table w-100">
                            <thead class="text-center">
                                <tr>
                                    <th rowspan="2" class="left-align">Strategic Goals and Objectives</th>
                                    <th rowspan="2" class="left-align">Success Indicators and Annual Target in Percentage</th>
                                    <th rowspan="2">Division/Unit Accountable</th>
                                    <th rowspan="2">ANNUAL TARGET<br><span class="subheader">(Raw Data)</span></th>
                                    <th colspan="4">QUARTERLY TARGETS</th>
                                    <th rowspan="2" class="left-align">Remarks or Justification of Unmet Targets</th>
                                </tr>
                                <tr>
                                    <th class="quarter-column">QUARTER 1<br><span class="subheader">(Raw Data)</span></th>
                                    <th class="quarter-column">QUARTER 2<br><span class="subheader">(Raw Data)</span></th>
                                    <th class="quarter-column">QUARTER 3<br><span class="subheader">(Raw Data)</span></th>
                                    <th class="quarter-column">QUARTER 4<br><span class="subheader">(Raw Data)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="section-header">
                                    <td colspan="9" class="left-align bold">Strategic Functions</td>
                                </tr>
                                <?php
                                $rows = [
                                    [
                                        'strategic_goal' => 'To ensure quality and safety of drug abuse treatment and rehabilitation centers and its services through compliance with international and local standards',
                                        'success_indicator' => '100% of Targeted International Organization for Standardization (ISO 9001:2015) Certification processes implemented',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:2 D:2',
                                        'quarter1_target' => '50% (1/2)',
                                        'quarter2_target' => 'TBD',
                                        'quarter3_target' => 'TBD',
                                        'quarter4_target' => '100% (2/2)',
                                        'remarks' => "1. Internal Quality Audit\n2. Surveillance Audit"
                                    ],
                                    [
                                        'strategic_goal' => 'To ensure quality and safety of drug abuse treatment and rehabilitation centers and its services through compliance with international and local standards',
                                        'success_indicator' => '100% compliance to DOH Accreditation Renewal standards as (Outpatient TRC/ Residential TRC/ TRC with Outpatient Service), through compliance of HFSRB guidelines',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:1 D:1',
                                        'quarter1_target' => 'TBD',
                                        'quarter2_target' => 'TBD',
                                        'quarter3_target' => 'TBD',
                                        'quarter4_target' => '100%',
                                        'remarks' => 'Residential TRC with Outpatient Service'
                                    ],
                                    [
                                        'is_header' => true,
                                        'header_text' => 'Average Rating (Strategic Functions)'
                                    ],
                                    [
                                        'is_header' => true,
                                        'header_text' => 'Core Functions'
                                    ],
                                    [
                                        'strategic_goal' => 'To ensure access to drug abuse treatment and rehabilitation services through sustained operations of government DATRCs',
                                        'success_indicator' => '85.8% Drug Abuse Treatment Completion Rate (Inpatient)',
                                        'division_accountable' => 'Treatment and Rehabilitation Division',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '85.80%',
                                        'quarter2_target' => '85.80%',
                                        'quarter3_target' => '85.80%',
                                        'quarter4_target' => '85.80%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '75 % Drug Abuse Treatment Completion Rate (Outpatient)',
                                        'division_accountable' => 'Treatment and Rehabilitation Division',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '75%',
                                        'quarter2_target' => '75%',
                                        'quarter3_target' => '75%',
                                        'quarter4_target' => '75%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% of inpatient drug abuse cases managed. (% and Number)',
                                        'division_accountable' => 'Treatment and Rehabilitation Division',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% of outpatient drug abuse cases managed. (% and Number)',
                                        'division_accountable' => 'Treatment and Rehabilitation Division',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% of aftercare drug abuse cases managed. (% and Number)',
                                        'division_accountable' => 'Treatment and Rehabilitation Division',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '85% of clients rated the DATRC "4" or higher through the Client Satisfaction Measurement Form',
                                        'division_accountable' => 'All Divisions and/or Anti-Red Tape Act (ARTA) Committee',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '85%',
                                        'quarter2_target' => '85%',
                                        'quarter3_target' => '85%',
                                        'quarter4_target' => '85%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => 'To assist various LGUs, health partners and other stakeholders in implementing an effective and relevant drug abuse treatment and rehabilitation services through technical support',
                                        'success_indicator' => '100% of Technical Assistance requests responded to within the prescribed timeline',
                                        'division_accountable' => 'All Divisions and/or Office of the Chief of Hospital (OCOH)',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => 'Proactive and strategic engagement with NGAs, LGUs, International, local health partners and private sector for dangerous drug abuse prevention',
                                        'success_indicator' => '100% of targeted drug abuse prevention advocacy activities conducted within the rating period',
                                        'division_accountable' => 'Public Health Unit (PHU)',
                                        'annual_target' => 'N:8 D:8',
                                        'quarter1_target' => '25% (2/8)',
                                        'quarter2_target' => '50% (4/8)',
                                        'quarter3_target' => '75% (6/8)',
                                        'quarter4_target' => '100% (8/8)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '',
                                        'division_accountable' => '',
                                        'annual_target' => '',
                                        'quarter1_target' => '',
                                        'quarter2_target' => '',
                                        'quarter3_target' => '',
                                        'quarter4_target' => '',
                                        'remarks' => '',
                                        'is_header' => true,
                                        'header_text' => 'Average Rating (Core Functions)'
                                    ],
                                    [
                                        'is_header' => true,
                                        'header_text' => 'Support Functions'
                                    ],
                                    [
                                        'strategic_goal' => 'To ensure efficient utilization of DOH funds',
                                        'success_indicator' => '95% Current Obligation Utilization Rate',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:26,367,487.50 D:27,775,525.00',
                                        'quarter1_target' => '10% (2,775,525.00)',
                                        'quarter2_target' => '35% (9,714,337.50)',
                                        'quarter3_target' => '70% (19,428,675.00)',
                                        'quarter4_target' => '95% (26,367,487.50)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '85% Current Disbursement Utilization Rate',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:22,413,364.37 D:26,367,487.50',
                                        'quarter1_target' => '85% (2,359,196.25)',
                                        'quarter2_target' => '85% (8,257,186.87)',
                                        'quarter3_target' => '85% (16,514,373.75)',
                                        'quarter4_target' => '85% (22,413,364.37)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% CONAP Obligation Utilization Rate',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:3,303,369.69 D:3,303,369.69',
                                        'quarter1_target' => '25% (825,842.42)',
                                        'quarter2_target' => '50% (1,651,684.85)',
                                        'quarter3_target' => '75% (2,477,527.27)',
                                        'quarter4_target' => '100% (3,303,369.69)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% CONAP Disbursement Utilization Rate',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:3,303,369.69 D:3,303,369.69',
                                        'quarter1_target' => '25% (825,842.42)',
                                        'quarter2_target' => '50% (1,651,684.85)',
                                        'quarter3_target' => '75% (2,477,527.27)',
                                        'quarter4_target' => '100% (3,303,369.69)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => 'To increase capacity of DOH personnel in order to improve workplace performance',
                                        'success_indicator' => '100% of all internal staff provided with learning and development interventions (LDIs) and/or updates based on the Learning and Development (L&D) Plan',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:96 D:96',
                                        'quarter1_target' => '25% (24/96)',
                                        'quarter2_target' => '50% (48/96)',
                                        'quarter3_target' => '75% (72/96)',
                                        'quarter4_target' => '100% (96/96)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => 'To ensure compliance with cross-cutting requirements based on standard procedures and timeliness in accordance to ARTA and other relevant laws',
                                        'success_indicator' => 'Percentage of other cross cutting requirements complied within the prescribed timeline: 100% of Non-conformities (or similarly) responded within the prescribed timeline',
                                        'division_accountable' => 'Quality Management System (QMS) Committee',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% of complaints closed',
                                        'division_accountable' => 'ARTA Committee',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '80% Percentage of COA Audit Recommendations fully implemented',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => 'N/A',
                                        'quarter2_target' => 'N/A',
                                        'quarter3_target' => '80%',
                                        'quarter4_target' => '80%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% Percentage of FOI requests that were responded to within the prescribed timeline',
                                        'division_accountable' => 'All Divisions',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '100% Percentage of all documents/requests processed within the prescribed timeline of office services in compliance with the Citizen\'s Charter',
                                        'division_accountable' => 'ARTA Committee and/or OCOH',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => '100%',
                                        'quarter2_target' => '100%',
                                        'quarter3_target' => '100%',
                                        'quarter4_target' => '100%',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => 'To ensure the delivery of quality service through the provision of adequate human resource based on the approved standard staffing pattern.',
                                        'success_indicator' => '80% Percentage of vacant positions filled within prescribed timelines with no invalidated appointment',
                                        'division_accountable' => 'Human Resource Merit Promotion and Selection Board (HRMPSB) and Human Resource Management Section (HRMS)',
                                        'annual_target' => 'N:TBD D:TBD',
                                        'quarter1_target' => 'N/A',
                                        'quarter2_target' => '80% (TBD)',
                                        'quarter3_target' => 'N/A',
                                        'quarter4_target' => '80% (TBD)',
                                        'remarks' => ''
                                    ],
                                    [
                                        'strategic_goal' => '',
                                        'success_indicator' => '',
                                        'division_accountable' => '',
                                        'annual_target' => '',
                                        'quarter1_target' => '',
                                        'quarter2_target' => '',
                                        'quarter3_target' => '',
                                        'quarter4_target' => '',
                                        'remarks' => '',
                                        'is_header' => true,
                                        'header_text' => 'Average Rating (Support Functions)'
                                    ],
                                ];

                                foreach ($rows as $index => $row):
                                    if (isset($row['is_header']) && $row['is_header']): ?>
                                        <tr class="section-header <?php echo strpos($row['header_text'], 'Average Rating') !== false ? 'average-rating' : ''; ?>">
                                            <td colspan="9" class="bold"><?php echo h($row['header_text']); ?></td>
                                        </tr>
                                    <?php else:
                                        $key = md5(($row['strategic_goal'] ?? '') . $row['success_indicator'] . $row['division_accountable'] . ($row['annual_target'] ?? ''));
                                        $existing_row = $existing_data_map[$key] ?? null;
                                    ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td class="left-align" rowspan="2">
                                                    <input type="hidden" name="strategic_goal[<?php echo $index; ?>]" value="<?php echo h($row['strategic_goal']); ?>">
                                                    <?php echo h($row['strategic_goal']); ?>
                                                </td>
                                            <?php elseif ($index === 1): ?>
                                                <!-- Skip strategic_goal cell for second row due to rowspan -->
                                            <?php else: ?>
                                                <td class="left-align">
                                                    <input type="hidden" name="strategic_goal[<?php echo $index; ?>]" value="<?php echo h($row['strategic_goal']); ?>">
                                                    <?php echo h($row['strategic_goal']); ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="left-align">
                                                <input type="hidden" name="success_indicator[<?php echo $index; ?>]" value="<?php echo h($row['success_indicator']); ?>">
                                                <?php echo h($row['success_indicator']); ?>
                                            </td>
                                            <td>
                                                <input type="hidden" name="division_accountable[<?php echo $index; ?>]" value="<?php echo h($row['division_accountable']); ?>">
                                                <?php echo h($row['division_accountable']); ?>
                                            </td>
                                            <td>
                                                <input type="hidden" name="annual_target[<?php echo $index; ?>]" value="<?php echo h($row['annual_target']); ?>">
                                                <div class="annual-target">
                                                    <?php
                                                    $parts = explode(' ', $row['annual_target']);
                                                    foreach ($parts as $part) {
                                                        if (strpos($part, ':') !== false) {
                                                            list($key, $value) = explode(':', $part, 2);
                                                            echo "<div><span>$key:</span><span>$value</span></div>";
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="quarter-column">
                                                <input type="text"
                                                    name="rows[<?php echo $index; ?>][quarter1_target]"
                                                    value="<?php echo h($existing_row['quarter1_target'] ?? $row['quarter1_target']); ?>"
                                                    class="form-control form-control-sm">
                                            </td>
                                            <td class="quarter-column">
                                                <input type="text"
                                                    name="rows[<?php echo $index; ?>][quarter2_target]"
                                                    value="<?php echo h($existing_row['quarter2_target'] ?? $row['quarter2_target']); ?>"
                                                    class="form-control form-control-sm">
                                            </td>
                                            <td class="quarter-column">
                                                <input type="text"
                                                    name="rows[<?php echo $index; ?>][quarter3_target]"
                                                    value="<?php echo h($existing_row['quarter3_target'] ?? $row['quarter3_target']); ?>"
                                                    class="form-control form-control-sm">
                                            </td>
                                            <td class="quarter-column">
                                                <input type="text"
                                                    name="rows[<?php echo $index; ?>][quarter4_target]"
                                                    value="<?php echo h($existing_row['quarter4_target'] ?? $row['quarter4_target']); ?>"
                                                    class="form-control form-control-sm">
                                            </td>
                                            <td class="left-align small-text">
                                                <textarea name="rows[<?php echo $index; ?>][remarks]"
                                                    class="form-control form-control-sm"><?php echo h($existing_row['remarks'] ?? $row['remarks']); ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="submit-btn-container text-center">
                <button type="submit" class="btn btn-green mt-3">Submit</button>
            </div>
        </form>
    </div>
</section>

<script src="<?= asset('js/pages/OPCR_2.js') ?>"></script>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

