<?php

declare(strict_types=1);

namespace App\Enums;

enum RoadmapBlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case Table = 'table';
    case DashboardStat = 'dashboard_stat';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
