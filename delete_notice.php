<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

if (!verify_csrf()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired form token.'
    ]);
    exit;
}

$noticeIdRaw = $_POST['notice_id'] ?? '';
$noticeId = filter_var($noticeIdRaw, FILTER_VALIDATE_INT);

if (!$noticeId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid notice id'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM notices WHERE notice_id = :id');
    $stmt->execute([':id' => $noticeId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notice not found'
        ]);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Delete failed'
    ]);
}
