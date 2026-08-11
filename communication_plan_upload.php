<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'employee') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
require_page_access('cascading');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}
$file = $_FILES['file'];
$allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
$maxSize = 10 * 1024 * 1024;
if (!in_array($file['type'], $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit();
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit();
}
$dir = __DIR__ . '/uploads/communication_plan/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
$unique = 'communication_plan_' . time() . '_' . mt_rand(1000, 9999) . '.' . ($ext ?: 'bin');
$path = $dir . $unique;
if (!move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
    exit();
}
$stmt = $conn->prepare("INSERT INTO communication_plan_uploads (employee_id, filename, original_name, file_size, mime_type) VALUES (?,?,?,?,?)");
$stmt->bind_param('issis', $_SESSION['user_id'], $unique, $safeBase, $file['size'], $file['type']);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit();
}
@unlink($path);
echo json_encode(['success' => false, 'error' => 'Database error']);
