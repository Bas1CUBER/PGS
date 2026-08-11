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
$name = trim($_POST['name'] ?? '');
$office = trim($_POST['office'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

// Ensure columns exist
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    if ($cols && !in_array('name', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) DEFAULT NULL");
    }
    if ($cols && !in_array('office', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN office VARCHAR(255) DEFAULT NULL");
    }
} catch (Throwable $e) {}

try {
    // Get user record before update
    $beforeStmt = $pdo->prepare("SELECT email, name, office FROM users WHERE id = :id");
    $beforeStmt->execute([':id' => $id]);
    $beforeUser = $beforeStmt->fetch(PDO::FETCH_ASSOC);
    $targetEmail = $beforeUser ? $beforeUser['email'] : 'ID#' . $id;

    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = :name, office = :office, password = :password WHERE id = :id");
        $stmt->execute([
            ':name' => ($name !== '' ? $name : null),
            ':office' => ($office !== '' ? $office : null),
            ':password' => $hash,
            ':id' => $id
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = :name, office = :office WHERE id = :id");
        $stmt->execute([
            ':name' => ($name !== '' ? $name : null),
            ':office' => ($office !== '' ? $office : null),
            ':id' => $id
        ]);
    }

    // Log to history
    $adminEmail = '';
    $adminStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $adminStmt->execute([':id' => (int)($_SESSION['user_id'] ?? 0)]);
    $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if ($adminRow) $adminEmail = $adminRow['email'];
    $detailParts = [];
    if ($name !== '') $detailParts[] = "Name: $name";
    if ($office !== '') $detailParts[] = "Office: $office";
    if ($newPassword !== '') $detailParts[] = "Password: reset";
    $beforeParts = [];
    if ($beforeUser) {
        $beforeParts[] = 'Name: ' . ($beforeUser['name'] ?? '—');
        $beforeParts[] = 'Office: ' . ($beforeUser['office'] ?? '—');
    }
    $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
        ->execute([':tu' => $targetEmail, ':at' => 'Updated', ':det' => implode(', ', $detailParts), ':detb' => implode(', ', $beforeParts), ':pb' => $adminEmail]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user']);
}
