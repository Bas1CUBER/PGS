<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['employee', 'focal'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

require_page_access('performance_assessment');

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/uploads/operations_review/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = 'operations_review_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Validate file
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
$maxSize = 10 * 1024 * 1024; // 10MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit();
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit();
}

// Move file
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Save to database
    $stmt = $conn->prepare("
        INSERT INTO operations_review_uploads 
        (employee_id, filename, original_name, file_size, mime_type) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", 
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
        $notifMsg = $roleLabel . " " . $userIdent . " uploaded Operations Review document: " . $file['name'];
        notifyAdmins('upload', 'Operations Review Upload', $notifMsg, $newId, 'operations_review');
        echo json_encode(['success' => true]);
    } else {
        // Remove uploaded file if database insert fails
        unlink($uploadPath);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
}
?>
