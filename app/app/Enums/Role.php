<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Focal = 'focal';
    case Employee = 'employee';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Focal => 'Focal',
            self::Employee => 'Employee',
        };
    }
}
