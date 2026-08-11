<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired form token.']);
    exit();
}

if (!isset($_FILES['file']) || !isset($_POST['title'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit();
}

$file = $_FILES['file'];
$title = $_POST['title'];
$uploadDir = __DIR__ . '/uploads/strategy_refresh/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = 'strategy_refresh_' . time() . '_' . mt_rand(1000, 9999) . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Validate file
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
        INSERT INTO strategy_refresh_uploads 
        (title, employee_id, filename, original_name, file_size, mime_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sisiss", 
        $title, 
        $_SESSION['user_id'], 
        $uniqueFilename, 
        $file['name'], 
        $file['size'], 
        $file['type']
    );
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        // Notify all users about the upload
        $userInfo = getUserInfo($_SESSION['user_id']);
        $userIdent = formatUserIdentifier($userInfo);
        $notifMsg = "Admin " . $userIdent . " uploaded Strategy Refresh document: " . $title;
        notifyAdmins('upload', 'Strategy Refresh Upload', $notifMsg, $newId, 'strategy_refresh');
        notifyFocals('upload', 'Strategy Refresh Upload', $notifMsg, $newId, 'strategy_refresh');
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
