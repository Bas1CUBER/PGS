<?php
require_once __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form token. Please try again.']);
    exit;
}
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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$impact = trim($_POST['impact'] ?? '');
$measure = trim($_POST['measure'] ?? '');
$bl = trim($_POST['bl'] ?? '');
$values = $_POST['values'] ?? [];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

if ($impact === '' || $measure === '') {
    echo json_encode(['success' => false, 'error' => 'Impact and Measure are required']);
    exit;
}

try {
    $pdo->beginTransaction();

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

    $stmt = $pdo->prepare('UPDATE impact_scorecard_measures SET impact = :impact, measure = :measure, bl = :bl WHERE id = :id');
    $stmt->execute([
        ':impact' => $impact,
        ':measure' => $measure,
        ':bl' => $bl,
        ':id' => $id,
    ]);

    if (is_array($values)) {
        $upsert = $pdo->prepare(
            'INSERT INTO impact_scorecard_values (measure_id, year_id, value) VALUES (:measure_id, :year_id, :value)\n'
            . 'ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );

        foreach ($values as $yearIdRaw => $valRaw) {
            $yearId = (int)$yearIdRaw;
            if ($yearId <= 0) {
                continue;
            }
            $val = $normalizePercentIfNeeded($measure, (string)$valRaw);
            $upsert->execute([
                ':measure_id' => $id,
                ':year_id' => $yearId,
                ':value' => $val,
            ]);
        }
    }

    $pdo->commit();
    
    // Notify about impact indicator update
    $adminInfo = getUserInfo((int)$_SESSION['user_id']);
    $adminId = formatUserIdentifier($adminInfo);
    $notifMsg = $adminId . " updated impact indicator: \"" . $measure . "\"";
    notifyAdmins('edit', 'Impact Indicator Updated', $notifMsg, $id, 'impact_indicator');
    notifyFocals('edit', 'Impact Indicator Updated', $notifMsg, $id, 'impact_indicator');
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Update failed']);
}
