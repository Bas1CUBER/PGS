<?php
require_once __DIR__ . '/src/bootstrap.php';
require_page_access('performance_assessment');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

$stmt = $pdo->prepare('SELECT pdf_file FROM operations_review WHERE id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record || empty($record['pdf_file'])) {
    http_response_code(404);
    exit('File not found');
}

$filePath = __DIR__ . '/uploads/operations_review/' . $record['pdf_file'];
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Serve the HTML file (browser can print/save as PDF)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . $record['pdf_file'] . '"');
readfile($filePath);
