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

$id = (int)($_POST['id'] ?? 0);
$targetRole = trim($_POST['role'] ?? '');

if ($id <= 0 || ($targetRole !== 'employee' && $targetRole !== 'focal')) {
  echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
  }
  $currentRole = $row['role'];

  if ($currentRole === 'admin') {
    echo json_encode(['success' => false, 'error' => 'Cannot change role of admin']);
    exit;
  }
  if ($currentRole !== 'employee' && $currentRole !== 'focal') {
    echo json_encode(['success' => false, 'error' => 'Role not changeable']);
    exit;
  }

  $upd = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
  $upd->execute([':role' => $targetRole, ':id' => $id]);

  // Log to history
  $targetEmail = '';
  $emailStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
  $emailStmt->execute([':id' => $id]);
  $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
  if ($emailRow) $targetEmail = $emailRow['email'];
  $adminEmail = '';
  $adminStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
  $adminStmt->execute([':id' => (int)($_SESSION['user_id'] ?? 0)]);
  $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
  if ($adminRow) $adminEmail = $adminRow['email'];
  $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
      ->execute([':tu' => $targetEmail, ':at' => 'Role Changed', ':det' => "Role changed from $currentRole to $targetRole", ':detb' => 'Role: ' . $currentRole, ':pb' => $adminEmail]);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
