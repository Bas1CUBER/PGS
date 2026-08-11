<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

// Check if user is authorized
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get deliverable ID and data to edit
$id = $_POST['id'] ?? '';
$title = $_POST['title'] ?? '';
$focal = $_POST['focal_person'] ?? '';
$division = $_POST['division'] ?? '';
$target = $_POST['target_completion_date'] ?? '';
$status = $_POST['current_status'] ?? '';
$actual = $_POST['actual_completion_date'] ?? ''; // Use an empty string if not provided

// Check if ID and required fields are present
if (!$id || !$title || !$focal || !$division || !$target || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Handle file upload for MOV (only if a new file is uploaded)
$movs_path = null;
if (isset($_FILES['movs']) && $_FILES['movs']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = [
        'application/pdf',
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'text/csv',
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'image/jpeg', // .jpg, .jpeg
        'image/png',  // .png
        'image/gif'   // .gif
    ];

    $file_tmp = $_FILES['movs']['tmp_name'];
    $file_name = basename($_FILES['movs']['name']);
    $file_type = mime_content_type($file_tmp);

    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type']);
        exit;
    }

    $upload_dir = 'uploads/movs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file_name);
    $target_file = $upload_dir . $unique_name;

    if (move_uploaded_file($file_tmp, $target_file)) {
        $movs_path = $target_file;
    } else {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
        exit;
    }
}

// Update the deliverable record in the database
// If MOV file is uploaded, we update the MOVs column as well
$update_query = "UPDATE deliverables SET 
                    title = ?, 
                    focal_person = ?, 
                    division = ?, 
                    target_completion_date = ?, 
                    current_status = ?, 
                    actual_completion_date = ?, 
                    movs = ? 
                WHERE id = ?";

$stmt = $conn->prepare($update_query);

// If no new MOV file, use the current MOV path
if ($movs_path === null) {
    $movs_path = $_POST['existing_movs'] ?? ''; // Use the existing MOV path if no new file
}

$stmt->bind_param("sssssssi", $title, $focal, $division, $target, $status, $actual, $movs_path, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>
