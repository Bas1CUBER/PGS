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

$yearId = isset($_POST['year_id']) ? (int)$_POST['year_id'] : 0;
if ($yearId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid year']);
    exit;
}

try {
    // Get year label for notifications
    $stmt = $pdo->prepare('SELECT year FROM impact_scorecard_years WHERE id = :id');
    $stmt->execute([':id' => $yearId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Year not found']);
        exit;
    }
    $yearLabel = (string)$row['year'];

    $pdo->beginTransaction();
    $delVals = $pdo->prepare('DELETE FROM impact_scorecard_values WHERE year_id = :id');
    $delVals->execute([':id' => $yearId]);

    $delYear = $pdo->prepare('DELETE FROM impact_scorecard_years WHERE id = :id');
    $delYear->execute([':id' => $yearId]);

    $pdo->commit();

    // Notify about year deletion
    $adminInfo = getUserInfo((int)$_SESSION['user_id']);
    $adminId = formatUserIdentifier($adminInfo);
    $notifMsg = $adminId . " removed year " . $yearLabel . " from Impact Scorecard";
    notifyAdmins('delete', 'Impact Year Removed', $notifMsg, $yearId, 'impact_indicator');
    notifyFocals('delete', 'Impact Year Removed', $notifMsg, $yearId, 'impact_indicator');

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Failed to delete year']);
}
