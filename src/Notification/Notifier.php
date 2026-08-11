<?php

declare(strict_types=1);

namespace PGS\Notification;

class Notifier
{
    public static function create(int $userId, string $type, string $title, string $message, ?int $relatedId = null, ?string $relatedType = null): bool
    {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $userId, $type, $title, $message, $relatedId, $relatedType);
        return $stmt->execute();
    }

    public static function createForUsers(array $userIds, string $type, string $title, string $message, ?int $relatedId = null, ?string $relatedType = null): int
    {
        $count = 0;
        foreach ($userIds as $uid) {
            if (self::create((int)$uid, $type, $title, $message, $relatedId, $relatedType)) {
                $count++;
            }
        }
        return $count;
    }

    public static function notifyAdmins(string $type, string $title, string $message, ?int $relatedId = null, ?string $relatedType = null): int
    {
        global $conn;
        $result = $conn->query("SELECT id FROM users WHERE role = 'admin'");
        $adminIds = [];
        while ($row = $result->fetch_assoc()) {
            $adminIds[] = (int)$row['id'];
        }
        return self::createForUsers($adminIds, $type, $title, $message, $relatedId, $relatedType);
    }

    public static function notifyFocals(string $type, string $title, string $message, ?int $relatedId = null, ?string $relatedType = null): int
    {
        global $conn;
        $result = $conn->query("SELECT id FROM users WHERE role = 'focal'");
        $focalIds = [];
        while ($row = $result->fetch_assoc()) {
            $focalIds[] = (int)$row['id'];
        }
        return self::createForUsers($focalIds, $type, $title, $message, $relatedId, $relatedType);
    }

    public static function notifyUser(int $userId, string $type, string $title, string $message, ?int $relatedId = null, ?string $relatedType = null): bool
    {
        return self::create($userId, $type, $title, $message, $relatedId, $relatedType);
    }

    public static function getUserInfo(int $userId): ?array
    {
        global $conn;
        $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    public static function formatUserIdentifier(array $user): string
    {
        $email = $user['email'] ?? '';
        $parts = explode('@', $email);
        return strtoupper($parts[0] ?? 'User');
    }
}
