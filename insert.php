<?php
require_once __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form token. Please try again.']);
    exit;
}

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetch form data with defaults
    $form_type = $_POST['form_type'] ?? '—';
    $title = $_POST['title'] ?? '—';
    $focal_person = $_POST['focal_person'] ?? '—';
    $division = $_POST['division'] ?? '—';
    $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : NULL;
    $status = $_POST['status'] ?? '—';
    $actual_date = !empty($_POST['actual_date']) ? $_POST['actual_date'] : NULL;

    // Admin is not allowed to upload/replace MOV files. Employee uploads happen in employee_upload.php
    $mov_file = NULL;

    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO p_deliverables (form_type, title, focal_person, division, target_date, status, actual_date, mov_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $form_type, $title, $focal_person, $division, $target_date, $status, $actual_date, $mov_file);

    if ($stmt->execute()) {
        $inserted_id = $stmt->insert_id;

        // Fetch the inserted record
        $result = $conn->query("SELECT * FROM p_deliverables WHERE id = $inserted_id");
        $data = $result->fetch_assoc();

        // Return JSON
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to insert record: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
