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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT measure FROM impact_scorecard_measures WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Impact not found']);
        exit;
    }
    $measureText = (string)$row['measure'];

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM impact_scorecard_values WHERE measure_id = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM impact_scorecard_measures WHERE id = :id')->execute([':id' => $id]);
    $pdo->commit();

    $adminInfo = getUserInfo((int)$_SESSION['user_id']);
    $adminId = formatUserIdentifier($adminInfo);
    $notifMsg = $adminId . ' removed impact: "' . $measureText . '"';
    notifyAdmins('delete', 'Impact Removed', $notifMsg, $id, 'impact_indicator');
    notifyFocals('delete', 'Impact Removed', $notifMsg, $id, 'impact_indicator');

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Failed to delete impact']);
}

