<?php

function createNotification($userId, $type, $title, $message, $relatedId = null, $relatedType = null)
{
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $userId, $type, $title, $message, $relatedId, $relatedType);
    return $stmt->execute();
}

function createNotificationsForUsers($userIds, $type, $title, $message, $relatedId = null, $relatedType = null)
{
    $count = 0;
    foreach ($userIds as $uid) {
        if (createNotification($uid, $type, $title, $message, $relatedId, $relatedType)) {
            $count++;
        }
    }
    return $count;
}

function notifyAdmins($type, $title, $message, $relatedId = null, $relatedType = null)
{
    global $conn;
    $result = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    $adminIds = [];
    while ($row = $result->fetch_assoc()) {
        $adminIds[] = (int)$row['id'];
    }
    return createNotificationsForUsers($adminIds, $type, $title, $message, $relatedId, $relatedType);
}

function notifyFocals($type, $title, $message, $relatedId = null, $relatedType = null)
{
    global $conn;
    $result = $conn->query("SELECT id FROM users WHERE role = 'focal'");
    $focalIds = [];
    while ($row = $result->fetch_assoc()) {
        $focalIds[] = (int)$row['id'];
    }
    return createNotificationsForUsers($focalIds, $type, $title, $message, $relatedId, $relatedType);
}

function notifyUser($userId, $type, $title, $message, $relatedId = null, $relatedType = null)
{
    return createNotification($userId, $type, $title, $message, $relatedId, $relatedType);
}

function getUserInfo($userId)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function formatUserIdentifier($user)
{
    $email = $user['email'] ?? '';
    $parts = explode('@', $email);
    return strtoupper($parts[0] ?? 'User');
}
