<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Save to database
$stmt = $conn->prepare("
    INSERT INTO strategy_review_forms 
    (employee_id, form_data, status) 
    VALUES (?, ?, 'Draft')
    ON DUPLICATE KEY UPDATE
    form_data = VALUES(form_data),
    updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("is", 
    $_SESSION['user_id'], 
    json_encode($data)
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
