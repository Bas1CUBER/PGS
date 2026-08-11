<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['employee', 'focal'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}
require_page_access('performance_assessment');

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/uploads/strategy_review/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = 'strategy_review_' . time() . '_' . mt_rand(1000, 9999) . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

$allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/jpg',
    'image/png'
];
$maxSize = 10 * 1024 * 1024;

if (!in_array($file['type'], $allowedTypes, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit();
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit();
}

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO strategy_review_uploads 
    (employee_id, filename, original_name, file_size, mime_type) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'issis',
    $_SESSION['user_id'],
    $uniqueFilename,
    $file['name'],
    $file['size'],
    $file['type']
);

if ($stmt->execute()) {
    $newId = $conn->insert_id;
    // Notify admins about the upload
    $userInfo = getUserInfo($_SESSION['user_id']);
    $userIdent = formatUserIdentifier($userInfo);
    $roleLabel = ucfirst($_SESSION['role'] ?? 'user');
    $notifMsg = $roleLabel . " " . $userIdent . " uploaded Strategy Review document: " . $file['name'];
    notifyAdmins('upload', 'Strategy Review Upload', $notifMsg, $newId, 'strategy_review');
    echo json_encode(['success' => true]);
    exit();
}

@unlink($uploadPath);
echo json_encode(['success' => false, 'error' => 'Database error']);
?>
