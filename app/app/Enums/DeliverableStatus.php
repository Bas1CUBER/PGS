<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliverableStatus: string
{
    case NotYetStarted = 'Not Yet Started';
    case Ongoing = 'Ongoing';
    case Accomplished = 'Accomplished';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
