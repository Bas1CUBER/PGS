<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');
require_page_access('scorecard');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$impact = trim($_POST['impact'] ?? '');
$measure = trim($_POST['measure'] ?? '');
$bl = trim($_POST['bl'] ?? '');
$values = $_POST['values'] ?? [];

if ($impact === '' || $measure === '') {
    echo json_encode(['success' => false, 'error' => 'Impact and Measure are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Compute next sort order
    $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM impact_scorecard_measures')->fetchColumn();
    $nextSort = $maxSort + 10;

    $ins = $pdo->prepare('INSERT INTO impact_scorecard_measures (impact, measure, bl, sort_order) VALUES (:impact, :measure, :bl, :sort_order)');
    $ins->execute([
        ':impact' => $impact,
        ':measure' => $measure,
        ':bl' => $bl === '' ? null : $bl,
        ':sort_order' => $nextSort,
    ]);

    $measureId = (int)$pdo->lastInsertId();
    if ($measureId <= 0) {
        throw new RuntimeException('Failed to create impact row');
    }

    $yearIds = $pdo->query('SELECT id FROM impact_scorecard_years ORDER BY sort_order ASC, year ASC')->fetchAll(PDO::FETCH_COLUMN);

    $normalizePercentIfNeeded = function (string $measureText, string $raw): ?string {
        $val = trim($raw);
        if ($val === '') {
            return null;
        }

        $isPercentMeasure = stripos($measureText, 'relapse rate') !== false
            || stripos($measureText, 'percent change') !== false;

        if (!$isPercentMeasure) {
            return $val;
        }

        if (strpos($val, '%') !== false) {
            return $val;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $val) === 1) {
            return $val . '%';
        }

        return $val;
    };

    $insVal = $pdo->prepare('INSERT INTO impact_scorecard_values (measure_id, year_id, value) VALUES (:measure_id, :year_id, :value)');

    foreach ($yearIds as $yid) {
        $yid = (int)$yid;
        if ($yid <= 0) {
            continue;
        }

        $rawVal = '';
        if (is_array($values) && array_key_exists((string)$yid, $values)) {
            $rawVal = (string)$values[(string)$yid];
        }

        $normalized = $normalizePercentIfNeeded($measure, $rawVal);

        $insVal->execute([
            ':measure_id' => $measureId,
            ':year_id' => $yid,
            ':value' => $normalized,
        ]);
    }

    $pdo->commit();
    
    // Notify about new impact indicator
    $adminInfo = getUserInfo((int)$_SESSION['user_id']);
    $adminId = formatUserIdentifier($adminInfo);
    $notifMsg = $adminId . " added a new impact indicator: \"" . $measure . "\"";
    notifyAdmins('upload', 'New Impact Indicator', $notifMsg, $measureId, 'impact_indicator');
    notifyFocals('upload', 'New Impact Indicator', $notifMsg, $measureId, 'impact_indicator');
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Failed to add impact row']);
}
