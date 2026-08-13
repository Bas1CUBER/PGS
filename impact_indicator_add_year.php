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

$year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
$values = $_POST['values'] ?? [];

if ($year < 1900 || $year > 3000) {
    echo json_encode(['success' => false, 'error' => 'Invalid year']);
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

    $stmt = $pdo->prepare('SELECT id FROM impact_scorecard_years WHERE year = :year LIMIT 1');
    $stmt->execute([':year' => $year]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Year already exists']);
        exit;
    }

    $insYear = $pdo->prepare('INSERT INTO impact_scorecard_years (year, sort_order) VALUES (:year, :sort_order)');
    $insYear->execute([':year' => $year, ':sort_order' => $year]);
    $yearId = (int)$pdo->lastInsertId();

    if ($yearId <= 0) {
        throw new RuntimeException('Failed to create year');
    }

    $measureRows = $pdo->query('SELECT id, measure FROM impact_scorecard_measures ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

    $insVal = $pdo->prepare('INSERT INTO impact_scorecard_values (measure_id, year_id, value) VALUES (:measure_id, :year_id, :value)');

    foreach ($measureRows as $mr) {
        $mid = (int)($mr['id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }

        $measureText = (string)($mr['measure'] ?? '');
        $rawVal = '';
        if (is_array($values) && array_key_exists((string)$mid, $values)) {
            $rawVal = (string)$values[(string)$mid];
        }

        $normalized = $normalizePercentIfNeeded($measureText, $rawVal);
        $insVal->execute([
            ':measure_id' => $mid,
            ':year_id' => $yearId,
            ':value' => $normalized,
        ]);
    }

    $pdo->commit();
    
    // Notify about new year added
    $adminInfo = getUserInfo((int)$_SESSION['user_id']);
    $adminId = formatUserIdentifier($adminInfo);
    $notifMsg = $adminId . " added year " . $year . " to Impact Scorecard";
    notifyAdmins('upload', 'New Year Added', $notifMsg, $yearId, 'impact_indicator');
    notifyFocals('upload', 'New Year Added', $notifMsg, $yearId, 'impact_indicator');
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Failed to add year']);
}
