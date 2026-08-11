<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_page_access('performance_assessment');

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$token = $data['_token'] ?? '';
if (!verify_csrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}

if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit();
}

$id = (int)$data['id'];
$status = $data['status'];

// Validate status
$validStatuses = ['Pending', 'Approved', 'Returned'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

// Update status in database
$stmt = $conn->prepare("UPDATE operations_review_uploads SET status = ?, status_updated_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    // Notify the employee about status change
    // Get employee_id for this upload
    $empStmt = $conn->prepare("SELECT employee_id FROM operations_review_uploads WHERE id = ?");
    $empStmt->bind_param("i", $id);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    $empRow = $empResult->fetch_assoc();
    $employeeId = $empRow ? (int)$empRow['employee_id'] : 0;
    
    $userInfo = getUserInfo($_SESSION['user_id']);
    $userIdent = formatUserIdentifier($userInfo);
    $notifMsg = "Admin " . $userIdent . " updated your Operations Review document status to: " . $status;
    notifyUser($employeeId, strtolower($status), 'Operations Review Status Update', $notifMsg, $id, 'operations_review');
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
