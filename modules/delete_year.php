<?php
require_once __DIR__ . '/../src/bootstrap.php';
$moduleKey = $_GET['module'] ?? '';
$modules = require __DIR__ . '/module_config.php';
if (!isset($modules[$moduleKey])) {
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'message' => 'Invalid module']);
  exit;
}
$table = $modules[$moduleKey]['table'];

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
  exit;
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid or expired form token. Please try again.']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['year'])) {
  $year = intval($_POST['year']);
  $stmt = $conn->prepare("DELETE FROM {$table} WHERE year = ?");
  $stmt->bind_param("i", $year);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
  }
  $stmt->close();
  $conn->close();
} else {
  echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
