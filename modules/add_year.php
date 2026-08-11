<?php
require_once __DIR__ . '/../src/bootstrap.php';
$moduleKey = $_GET['module'] ?? '';
$modules = require __DIR__ . '/module_config.php';
if (!isset($modules[$moduleKey])) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid module']);
  exit;
}
$table = $modules[$moduleKey]['table'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: " . BASE_URL . "/login");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid or expired form token. Please try again.']);
  exit();
}

$year = isset($_POST['year']) ? $_POST['year'] : null;
$categories = isset($_POST['categories']) ? $_POST['categories'] : null;

if (!$year || empty($categories)) {
  echo json_encode(['status' => 'error', 'message' => 'Year and categories are required']);
  exit();
}

$conn->begin_transaction();
try {
  foreach ($categories as $categoryKey => $categoryData) {
    $categoryName = isset($categoryData['category']) ? $categoryData['category'] : null;
    $descriptions = isset($categoryData['descriptions']) ? $categoryData['descriptions'] : [];
    if (empty($categoryName) || empty($descriptions)) continue;
    foreach ($descriptions as $description) {
      $description = trim($description);
      if (empty($description)) continue;
      $stmt = $conn->prepare("INSERT INTO {$table} (year, category, description) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $year, $categoryName, $description);
      if (!$stmt->execute()) throw new Exception("Failed to add category: " . $stmt->error);
      $stmt->close();
    }
  }
  $conn->commit();
  echo json_encode(['status' => 'success', 'year' => $year]);
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
$conn->close();
?>
