<?php
require_once __DIR__ . '/src/bootstrap.php';
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
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['success' => false, 'error' => 'You cannot delete the currently logged-in user']);
    exit;
}

try {
    // Get full user record before deleting
    $userStmt = $pdo->prepare("SELECT email, name, office, role, COALESCE(is_active,1) AS is_active FROM users WHERE id = :id");
    $userStmt->execute([':id' => $id]);
    $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    $targetEmail = $targetUser ? $targetUser['email'] : 'ID#' . $id;

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        // Log to history
        $adminEmail = '';
        $adminStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
        $adminStmt->execute([':id' => (int)($_SESSION['user_id'] ?? 0)]);
        $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($adminRow) $adminEmail = $adminRow['email'];
        $beforeParts = [];
        if ($targetUser) {
            $beforeParts[] = 'Email: ' . ($targetUser['email'] ?? '');
            $beforeParts[] = 'Name: ' . ($targetUser['name'] ?? '—');
            $beforeParts[] = 'Office: ' . ($targetUser['office'] ?? '—');
            $beforeParts[] = 'Role: ' . ($targetUser['role'] ?? '');
            $beforeParts[] = 'Status: ' . ((int)$targetUser['is_active'] === 1 ? 'Active' : 'Deactivated');
        }
        $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
            ->execute([':tu' => $targetEmail, ':at' => 'Deleted', ':det' => 'User permanently removed', ':detb' => implode(', ', $beforeParts), ':pb' => $adminEmail]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
}
