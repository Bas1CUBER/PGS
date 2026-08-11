<?php
/**
 * Notification Helper Functions
 * Include this file to use notification functionality
 */

require_once __DIR__ . '/db.php';

/**
 * Create a notification for a user
 * 
 * @param int $userId The user ID to receive the notification
 * @param string $type Notification type (upload, approved, returned, edit, etc.)
 * @param string $title Short title
 * @param string $message Full message
 * @param int|null $relatedId Related record ID
 * @param string|null $relatedType Related record type (governance_culture, governance_sharing, etc.)
 * @return bool Success status
 */
function createNotification($userId, $type, $title, $message, $relatedId = null, $relatedType = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $userId, $type, $title, $message, $relatedId, $relatedType);
    return $stmt->execute();
}

/**
 * Create notifications for multiple users
 * 
 * @param array $userIds Array of user IDs
 * @param string $type Notification type
 * @param string $title Short title
 * @param string $message Full message
 * @param int|null $relatedId Related record ID
 * @param string|null $relatedType Related record type
 * @return int Number of notifications created
 */
function createNotificationsForUsers($userIds, $type, $title, $message, $relatedId = null, $relatedType = null) {
    $count = 0;
    foreach ($userIds as $uid) {
        if (createNotification($uid, $type, $title, $message, $relatedId, $relatedType)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Notify admins about an action
 * 
 * @param string $type Notification type
 * @param string $title Short title
 * @param string $message Full message
 * @param int|null $relatedId Related record ID
 * @param string|null $relatedType Related record type
 * @return int Number of admins notified
 */
function notifyAdmins($type, $title, $message, $relatedId = null, $relatedType = null) {
    global $conn;
    
    $result = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    $adminIds = [];
    while ($row = $result->fetch_assoc()) {
        $adminIds[] = (int)$row['id'];
    }
    return createNotificationsForUsers($adminIds, $type, $title, $message, $relatedId, $relatedType);
}

/**
 * Notify focals about an action
 * 
 * @param string $type Notification type
 * @param string $title Short title
 * @param string $message Full message
 * @param int|null $relatedId Related record ID
 * @param string|null $relatedType Related record type
 * @return int Number of focals notified
 */
function notifyFocals($type, $title, $message, $relatedId = null, $relatedType = null) {
    global $conn;
    
    $result = $conn->query("SELECT id FROM users WHERE role = 'focal'");
    $focalIds = [];
    while ($row = $result->fetch_assoc()) {
        $focalIds[] = (int)$row['id'];
    }
    return createNotificationsForUsers($focalIds, $type, $title, $message, $relatedId, $relatedType);
}

/**
 * Notify a specific user about an action
 * 
 * @param int $userId User ID to notify
 * @param string $type Notification type
 * @param string $title Short title
 * @param string $message Full message
 * @param int|null $relatedId Related record ID
 * @param string|null $relatedType Related record type
 * @return bool Success status
 */
function notifyUser($userId, $type, $title, $message, $relatedId = null, $relatedType = null) {
    return createNotification($userId, $type, $title, $message, $relatedId, $relatedType);
}

/**
 * Get user info by ID
 * 
 * @param int $userId User ID
 * @return array|null User info or null
 */
function getUserInfo($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Format user identifier (employee ID or email prefix)
 * 
 * @param array $user User array with email
 * @return string Formatted identifier
 */
function formatUserIdentifier($user) {
    // Extract employee ID from email (e.g., EMP0001 from EMP0001@example.com)
    $email = $user['email'] ?? '';
    $parts = explode('@', $email);
    return strtoupper($parts[0] ?? 'User');
}
