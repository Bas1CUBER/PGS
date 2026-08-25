<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Neutralizes spreadsheet formula injection in CSV exports. Cells that a
 * spreadsheet application would interpret as formulas (=, +, -, @) or that
 * begin with control characters are prefixed with an apostrophe so they are
 * treated as literal text.
 */
final class CsvFormulaGuard
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
