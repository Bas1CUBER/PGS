<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_page_access (
            user_id INT PRIMARY KEY,
            roadmaps TINYINT(1) NOT NULL DEFAULT 1,
            scorecard TINYINT(1) NOT NULL DEFAULT 1,
            performance_assessment TINYINT(1) NOT NULL DEFAULT 1,
            cascading TINYINT(1) NOT NULL DEFAULT 1,
            governance TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_page_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $stmt = $pdo->prepare("SELECT * FROM user_page_access WHERE user_id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->prepare("INSERT INTO user_page_access (user_id) VALUES (:id)")->execute([':id' => $id]);
        $row = [
            'user_id' => $id,
            'roadmaps' => 1,
            'scorecard' => 1,
            'performance_assessment' => 1,
            'cascading' => 1,
            'governance' => 1
        ];
    }
    echo json_encode($row);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch access']);
}
