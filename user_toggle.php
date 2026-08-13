<?php
require_once __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form token. Please try again.']);
    exit;
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$isActive = ($isActive === 1) ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['success' => false, 'error' => 'You cannot change the status of the currently logged-in user']);
    exit;
}

// Ensure is_active column exists
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'");
    $check->execute();
    $hasColumn = (int)$check->fetchColumn() > 0;
    if (!$hasColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (Throwable $e) {
}

try {
    // Get user status before toggle
    $beforeStmt = $pdo->prepare("SELECT email, COALESCE(is_active,1) AS is_active FROM users WHERE id = :id");
    $beforeStmt->execute([':id' => $id]);
    $beforeUser = $beforeStmt->fetch(PDO::FETCH_ASSOC);
    $targetEmail = $beforeUser ? $beforeUser['email'] : 'ID#' . $id;
    $beforeStatus = $beforeUser ? ((int)$beforeUser['is_active'] === 1 ? 'Active' : 'Deactivated') : 'Unknown';

    $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
    $stmt->execute([':is_active' => $isActive, ':id' => $id]);

    if ($stmt->rowCount() > 0) {
        // Log to history
        $adminEmail = '';
        $adminStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
        $adminStmt->execute([':id' => (int)($_SESSION['user_id'] ?? 0)]);
        $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($adminRow) $adminEmail = $adminRow['email'];
        $actionLabel = $isActive === 1 ? 'Activated' : 'Deactivated';
        $detailText = $isActive === 1 ? 'User account activated' : 'User account deactivated';
        $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
            ->execute([':tu' => $targetEmail, ':at' => $actionLabel, ':det' => $detailText, ':detb' => 'Status: ' . $beforeStatus, ':pb' => $adminEmail]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found or no change']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user']);
}
