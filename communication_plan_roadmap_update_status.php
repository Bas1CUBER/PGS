<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','focal'], true)) {
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit();
}

require_page_access('cascading');

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['_token'] ?? '';
if (!verify_csrf($token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}
$id = isset($input['id']) ? (int)$input['id'] : 0;
$status = $input['status'] ?? '';

if ($id <= 0 || !in_array($status, ['Not Accomplished/Started','Ongoing','Completed'], true)) {
  echo json_encode(['success' => false, 'error' => 'Invalid data']);
  exit();
}

try {
  $colRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communication_plan_roadmap' AND COLUMN_NAME = 'status'");
  $colCount = 1;
  if ($colRes && ($colRow = $colRes->fetch_assoc())) {
    $colCount = (int)($colRow['c'] ?? 1);
  }
  if ($colCount === 0) {
    $conn->query("ALTER TABLE communication_plan_roadmap ADD COLUMN status ENUM('Not Accomplished/Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Accomplished/Started'");
  }
} catch (Throwable $e) {
}

$stmt = $conn->prepare("UPDATE communication_plan_roadmap SET status=? WHERE id=?");
$stmt->bind_param('si', $status, $id);
if ($stmt->execute()) {
  echo json_encode(['success' => true]);
  exit();
}

echo json_encode(['success' => false, 'error' => 'Update failed']);
?>
