<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');

// AUTH CHECK
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
    exit;
}

if (!verify_csrf()) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired form token."
    ]);
    exit;
}

// INPUT
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $description === '') {
    echo json_encode([
        "success" => false,
        "message" => "Title and description are required"
    ]);
    exit;
}

// FILE UPLOADS
$imagePath = null;
$videoPath = null;

// IMAGE UPLOAD
if (!empty($_FILES['image']['name'])) {
    $imageExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowedImages = ['jpg','jpeg','png','gif','webp'];

    if (in_array($imageExt, $allowedImages)) {
        $imageName = uniqid('img_') . '.' . $imageExt;
        $imageDir = 'uploads/images/';
        if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);
        if (move_uploaded_file($_FILES['image']['tmp_name'], $imageDir . $imageName)) {
            $imagePath = $imageDir . $imageName;
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid image type"
        ]);
        exit;
    }
}

// VIDEO FILE UPLOAD (MP4)
if (!empty($_FILES['video_file']['name'])) {
    $videoExt = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
    if ($videoExt !== 'mp4') {
        echo json_encode([
            "success" => false,
            "message" => "Only MP4 videos are allowed"
        ]);
        exit;
    }

    $videoName = uniqid('vid_') . '.' . $videoExt;
    $videoDir = 'uploads/videos/';
    if (!is_dir($videoDir)) mkdir($videoDir, 0777, true);
    if (move_uploaded_file($_FILES['video_file']['tmp_name'], $videoDir . $videoName)) {
        $videoPath = $videoDir . $videoName;
    }
}

// STORE ONLY UPLOADED VIDEO FILE
$videoToStore = $videoPath;

// INSERT INTO DB
$stmt = $conn->prepare(
    "INSERT INTO notices (title, description, image, video, created_at)
     VALUES (?, ?, ?, ?, NOW())"
);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("ssss", $title, $description, $imagePath, $videoToStore);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Insert failed: " . $conn->error
    ]);
}

$stmt->close();
$conn->close();
