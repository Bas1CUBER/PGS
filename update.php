<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'];
$title = $_POST['title'];
$focal_person = $_POST['focal_person'];
$division = $_POST['division'];
$form_type = $_POST['form_type'];
$target_date = $_POST['target_date'];
$status = $_POST['status'];
$actual_date = $_POST['actual_date'] ?: null;

$sql = "UPDATE p_deliverables SET 
    title=?, focal_person=?, division=?, form_type=?, target_date=?, status=?, actual_date=?
    WHERE id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssi", $title, $focal_person, $division, $form_type, $target_date, $status, $actual_date, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
