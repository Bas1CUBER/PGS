<?php
require_once __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form token. Please try again.']);
    exit;
}
header('Content-Type: application/json');

// Only allow employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'employee') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid deliverable ID']);
    exit;
}

if (!isset($_FILES['mov_file']) || $_FILES['mov_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid file to upload']);
    exit;
}

// Ensure uploaded_by column exists
try {
    $colCheck = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'p_deliverables' AND COLUMN_NAME = 'uploaded_by'");
    $colCheck->execute();
    $colRes = $colCheck->get_result();
    $colRow = $colRes ? $colRes->fetch_assoc() : null;
    $colCheck->close();

    $hasUploadedBy = $colRow && (int)$colRow['c'] > 0;
    if (!$hasUploadedBy) {
        $conn->query("ALTER TABLE p_deliverables ADD COLUMN uploaded_by INT(11) NULL");
    }
} catch (Throwable $e) {
    // best-effort
}

// Check if already uploaded
$stmt = $conn->prepare('SELECT mov_file FROM p_deliverables WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Deliverable not found']);
    exit;
}

if (!empty($row['mov_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'File already uploaded and cannot be replaced']);
    exit;
}

$allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$originalName = $_FILES['mov_file']['name'] ?? '';
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($ext === '' || !in_array($ext, $allowedExt, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
    exit;
}

$targetDir = __DIR__ . '/uploads/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
$filename = time() . '_' . $safeBaseName;
$targetPath = $targetDir . $filename;

if (!move_uploaded_file($_FILES['mov_file']['tmp_name'], $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
    exit;
}

$uploaderId = (int)($_SESSION['user_id'] ?? 0);
$update = $conn->prepare('UPDATE p_deliverables SET mov_file = ?, uploaded_by = ? WHERE id = ? AND (mov_file IS NULL OR mov_file = \'\' OR mov_file = \'\')');
$update->bind_param('sii', $filename, $uploaderId, $id);

if ($update->execute() && $update->affected_rows > 0) {
    echo json_encode(['status' => 'success']);
} else {
    // If it failed because mov_file got set between check and update, keep immutability
    echo json_encode(['status' => 'error', 'message' => 'Upload rejected. File may have already been uploaded.']);
}

$update->close();
$conn->close();
