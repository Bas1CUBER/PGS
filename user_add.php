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

$email = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');
$role = trim($_POST['role'] ?? 'employee');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'User ID and password are required']);
    exit;
}

// Allow non-email User IDs (removed FILTER_VALIDATE_EMAIL check)
/*
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email']);
    exit;
}
*/

if ($role !== 'admin' && $role !== 'employee' && $role !== 'focal') {
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

// Optional profile fields
$name = trim($_POST['name'] ?? '');
$office = trim($_POST['office'] ?? '');

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

// Ensure name and office columns exist
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    if ($cols && !in_array('name', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) DEFAULT NULL");
    }
    if ($cols && !in_array('office', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN office VARCHAR(255) DEFAULT NULL");
    }
} catch (Throwable $e) {}

// Ensure users.role supports focal role
try {
    $roleCol = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if ($roleCol && stripos($roleCol, "'focal'") === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee','focal') NOT NULL");
    }
} catch (Throwable $e) {
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare('INSERT INTO users (email, password, role, is_active, name, office) VALUES (:email, :password, :role, 1, :name, :office)');
    $stmt->execute([
        ':email' => $email,
        ':password' => $hash,
        ':role' => $role,
        ':name' => ($name !== '' ? $name : null),
        ':office' => ($office !== '' ? $office : null)
    ]);

    // Log to history
    $adminEmail = '';
    $adminStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $adminStmt->execute([':id' => (int)($_SESSION['user_id'] ?? 0)]);
    $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if ($adminRow) $adminEmail = $adminRow['email'];
    $detailParts = [];
    if ($name !== '') $detailParts[] = "Name: $name";
    if ($office !== '') $detailParts[] = "Office: $office";
    $detailParts[] = "Role: $role";
    $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
        ->execute([':tu' => $email, ':at' => 'Added', ':det' => implode(', ', $detailParts), ':detb' => 'New user — no previous record', ':pb' => $adminEmail]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Duplicate') !== false) {
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add user: ' . $msg]);
    }
}
