<?php

declare(strict_types=1);

use App\Support\CsvFormulaGuard;

/**
 * CSV cells that spreadsheets interpret as formulas (=, +, -, @) or that
 * start with control characters must be neutralized before export.
 */
it('prefixes formula-leading cells with an apostrophe', function (string $input): void {
    expect(CsvFormulaGuard::cell($input))->toBe("'".$input);
})->with([
    ['=cmd|"/c calc"!A0'],
    ['+SUM(A1:A2)'],
    ['-2'],
    ['@import_url'],
]);

it('neutralizes control-character leading cells', function (): void {
    expect(CsvFormulaGuard::cell("\tTabbed"))->toBe("'\tTabbed")
        ->and(CsvFormulaGuard::cell("\rCarriage"))->toBe("'\rCarriage");
});

it('leaves safe text untouched', function (): void {
    expect(CsvFormulaGuard::cell('Plain text'))->toBe('Plain text')
        ->and(CsvFormulaGuard::cell('Total = 100'))->toBe('Total = 100')
        ->and(CsvFormulaGuard::cell('42'))->toBe('42')
        ->and(CsvFormulaGuard::cell('user@example.com'))->toBe('user@example.com');
});

it('passes through nulls, numbers and empty strings unchanged', function (): void {
    expect(CsvFormulaGuard::cell(null))->toBeNull()
        ->and(CsvFormulaGuard::cell(123))->toBe(123)
        ->and(CsvFormulaGuard::cell(''))->toBe('');
});

it('maps every cell of a row', function (): void {
    $row = CsvFormulaGuard::row(['safe', '=danger', 5, null]);

    expect($row[0])->toBe('safe')
        ->and($row[1])->toBe("'=danger")
        ->and($row[2])->toBe(5)
        ->and($row[3])->toBeNull();
});
