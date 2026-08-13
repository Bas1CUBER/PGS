<?php
require_once __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}
$role = $_SESSION['role'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Get unread count
if ($action === 'get_unread_count') {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['ok' => true, 'count' => (int)$result['cnt']]);
    exit();
}

// Get notifications (30 most recent)
if ($action === 'get_notifications') {
    $stmt = $conn->prepare("SELECT n.*, 
        CASE 
            WHEN n.related_type = 'governance_culture' THEN 'Governance Culture'
            WHEN n.related_type = 'governance_sharing' THEN 'Governance Sharing'
            ELSE n.related_type
        END as related_type_display
        FROM notifications n 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 30");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'related_id' => $row['related_id'],
            'related_type' => $row['related_type'],
            'related_type_display' => $row['related_type_display'],
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at'],
            'time_ago' => timeAgo($row['created_at'])
        ];
    }
    $unread = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) {
            $unread++;
        }
    }
    echo json_encode(['ok' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
    exit();
}

// Mark all as read
if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    echo json_encode(['ok' => true, 'updated' => $conn->affected_rows]);
    exit();
}

// Mark single notification as read
if ($action === 'mark_read') {
    $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($notifId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notifId, $userId);
        $stmt->execute();
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid notification ID']);
    }
    exit();
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);

// Helper function for time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
