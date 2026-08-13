<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case Upload = 'upload';
    case Approved = 'approved';
    case Returned = 'returned';
    case Edit = 'edit';
    case Default = 'default';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
