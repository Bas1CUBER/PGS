<?php

global $conn;

$moduleKey = $_GET['module'] ?? '';
$modules = require PGS_SRC . '/Modules/module_config.php';
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

if (isset($_GET['year'])) {
    $year = (int)$_GET['year'];
    $sql = "SELECT category, description FROM {$table} WHERE year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[$row['category']][] = $row['description'];
    }
    $conn->close();
    echo json_encode(['status' => 'success', 'year' => $year, 'categories' => $categories]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Year not specified']);
}
