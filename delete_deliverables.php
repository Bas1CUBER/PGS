<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = 0;
if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

// Fetch filename first so we can remove it from disk (if present)
$stmt = $conn->prepare('SELECT mov_file FROM p_deliverables WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Record not found']);
    exit;
}

$movFile = $row['mov_file'] ?? '';

// Delete DB row
$del = $conn->prepare('DELETE FROM p_deliverables WHERE id = ?');
$del->bind_param('i', $id);
if (!$del->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete']);
    exit;
}
$del->close();

// Delete file from uploads folder if it exists
if (!empty($movFile)) {
    $uploadsDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsDir) {
        $safeName = basename($movFile);
        $candidate = $uploadsDir . DIRECTORY_SEPARATOR . $safeName;
        $realCandidate = realpath($candidate);
        $withinUploads = false;
        if ($realCandidate) {
            $withinUploads = (substr($realCandidate, 0, strlen($uploadsDir)) === $uploadsDir);
        }
        if ($realCandidate && $withinUploads && is_file($realCandidate)) {
            @unlink($realCandidate);
        }
    }
}

echo json_encode(['success' => true]);
?>
