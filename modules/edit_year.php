<?php

require_once __DIR__ . '/../src/bootstrap.php';
$moduleKey = $_GET['module'] ?? ($moduleKey ?? '');
$modules = require __DIR__ . '/module_config.php';
if (!isset($modules[$moduleKey])) {
    echo 'Invalid module';
    exit;
}
$table = $modules[$moduleKey]['table'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo 'User not authorized.';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    echo 'Invalid or expired form token. Please try again.';
    exit();
}

$year = isset($_POST['year']) ? intval($_POST['year']) : null;
$categories = isset($_POST['categories']) ? $_POST['categories'] : null;

if (!$year || empty($categories)) {
    echo 'Error: Missing year or categories.';
    exit();
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE year = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $year);
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete existing data for year $year: " . $stmt->error);
    }
    $stmt->close();

    foreach ($categories as $originalCategoryName => $categoryData) {
        $categoryName = isset($categoryData['category']) ? $categoryData['category'] : $originalCategoryName;
        $descriptions = isset($categoryData['descriptions']) ? $categoryData['descriptions'] : [];
        if (empty($categoryName) || empty($descriptions)) {
            continue;
        }
        foreach ($descriptions as $description) {
            $description = trim($description);
            if (empty($description)) {
                continue;
            }
            $stmt = $conn->prepare("INSERT INTO {$table} (year, category, description) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('iss', $year, $categoryName, $description);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed for category '$categoryName': " . $stmt->error);
            }
            $stmt->close();
        }
    }
    $conn->commit();
    echo 'Year updated successfully';
} catch (Exception $e) {
    $conn->rollback();
    echo 'Error: ' . $e->getMessage();
}
$conn->close();
