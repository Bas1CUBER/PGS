<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
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
$status = $input['status'] ?? 'Pending';
if ($id <= 0 || !in_array($status, ['Pending','Approved','Returned'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}
$stmt = $conn->prepare("UPDATE communication_plan_uploads SET status=?, status_updated_at=NOW() WHERE id=?");
$stmt->bind_param('si', $status, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit();
}
echo json_encode(['success' => false, 'error' => 'Update failed']);
