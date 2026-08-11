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

$id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

$fields = ['roadmaps','scorecard','performance_assessment','cascading','governance'];
$vals = [];
foreach ($fields as $f) {
    $vals[$f] = isset($_POST[$f]) ? (int)($_POST[$f] ? 1 : 0) : 0;
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
    $stmt = $pdo->prepare("SELECT user_id FROM user_page_access WHERE user_id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetchColumn()) {
        $update = $pdo->prepare("
            UPDATE user_page_access
            SET roadmaps = :roadmaps,
                scorecard = :scorecard,
                performance_assessment = :performance_assessment,
                cascading = :cascading,
                governance = :governance
            WHERE user_id = :id
        ");
        $update->execute([
            ':roadmaps' => $vals['roadmaps'],
            ':scorecard' => $vals['scorecard'],
            ':performance_assessment' => $vals['performance_assessment'],
            ':cascading' => $vals['cascading'],
            ':governance' => $vals['governance'],
            ':id' => $id
        ]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO user_page_access (user_id, roadmaps, scorecard, performance_assessment, cascading, governance)
            VALUES (:id, :roadmaps, :scorecard, :performance_assessment, :cascading, :governance)
        ");
        $insert->execute([
            ':id' => $id,
            ':roadmaps' => $vals['roadmaps'],
            ':scorecard' => $vals['scorecard'],
            ':performance_assessment' => $vals['performance_assessment'],
            ':cascading' => $vals['cascading'],
            ':governance' => $vals['governance']
        ]);
    }
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
    $accessParts = [];
    foreach ($fields as $f) {
        $accessParts[] = ucfirst(str_replace('_', ' ', $f)) . ': ' . ($vals[$f] ? 'On' : 'Off');
    }
    // Get previous access settings
    $beforeAccess = [];
    $prevStmt = $pdo->prepare("SELECT * FROM user_page_access WHERE user_id = :id");
    $prevStmt->execute([':id' => $id]);
    $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);
    if ($prevRow) {
        foreach ($fields as $f) {
            $beforeAccess[] = ucfirst(str_replace('_', ' ', $f)) . ': ' . ((int)$prevRow[$f] ? 'On' : 'Off');
        }
    } else {
        $beforeAccess[] = 'No previous access record';
    }
    $pdo->prepare("INSERT INTO user_management_history (target_user, action_type, details, details_before, performed_by) VALUES (:tu, :at, :det, :detb, :pb)")
        ->execute([':tu' => $targetEmail, ':at' => 'Access Updated', ':det' => implode(', ', $accessParts), ':detb' => implode(', ', $beforeAccess), ':pb' => $adminEmail]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to update access']);
}
