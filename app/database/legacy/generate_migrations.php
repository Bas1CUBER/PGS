<?php

declare(strict_types=1);

/**
 * Phase 2 schema import generator.
 *
 * Reads the live-schema snapshot (docs/migrations/planning_schema.sql,
 * produced by `mysqldump --no-data`) and emits one Laravel migration per
 * legacy table using the original DDL verbatim. Tables are emitted in
 * topological order (FK parents before children) so foreign keys validate.
 * This guarantees 1:1 parity with the production schema. Redesign of wide
 * year-column tables happens module-by-module during Phases 6-7
 * (see docs/DataModel.md §5).
 *
 * Usage:  php database/legacy/generate_migrations.php
 * After regenerating: run `composer lint:fix` to normalize generated files.
 */
$snapshot = dirname(__DIR__, 3).'/docs/migrations/planning_schema.sql';
$outDir = __DIR__.'/../migrations';

if (! is_file($snapshot)) {
    fwrite(STDERR, "Missing snapshot: $snapshot\n");
    exit(1);
}

$sql = file_get_contents($snapshot);

preg_match_all('/CREATE TABLE `[^`]+` \(.*?\) ENGINE=[^;]+;/s', $sql, $matches);

if (count($matches[0]) === 0) {
    fwrite(STDERR, "No CREATE TABLE statements found.\n");
    exit(1);
}

$frameworkOwned = ['users', 'cache', 'cache_locks', 'jobs', 'job_batches', 'sessions', 'password_reset_tokens'];

/** @var array<string, string> $tables */
$tables = [];

foreach ($matches[0] as $ddl) {
    preg_match('/CREATE TABLE `([^`]+)`/', $ddl, $m);
    $table = $m[1];

    if (in_array($table, $frameworkOwned, true)) {
        fwrite(STDOUT, "skip (framework-owned): $table\n");

        continue;
    }

    $tables[$table] = $ddl;
}

// Topological sort: parent tables (referenced via FK) come first.
$deps = [];
foreach ($tables as $table => $ddl) {
    preg_match_all('/REFERENCES `([^`]+)`/', $ddl, $m);
    $deps[$table] = array_values(array_unique($m[1]));
}

$sorted = [];
$visited = [];

function visit(string $table, array &$tables, array $deps, array &$visited, array &$sorted): void
{
    if (isset($visited[$table])) {
        return;
    }
    $visited[$table] = true;

    foreach ($deps[$table] ?? [] as $parent) {
        if (isset($tables[$parent]) && ! isset($visited[$parent])) {
            visit($parent, $tables, $deps, $visited, $sorted);
        }
    }

    $sorted[] = $table;
}

foreach (array_keys($tables) as $table) {
    visit($table, $tables, $deps, $visited, $sorted);
}

// Detect dependency cycles (should not exist in the legacy schema).
$seen = array_flip($sorted);
$ordered = [];
foreach ($sorted as $i => $table) {
    $ordered[$table] = $i;
}
foreach ($deps as $table => $parents) {
    foreach ($parents as $parent) {
        if (isset($tables[$parent]) && ! isset($ordered[$parent])) {
            fwrite(STDERR, "CYCLE/UNRESOLVED: $table -> $parent\n");
        }
    }
}

$generated = 0;
foreach ($sorted as $table) {
    $ddl = $tables[$table];
    $seq = sprintf('%04d', $generated + 1);
    $file = "{$outDir}/2026_08_13_000100_{$seq}_create_legacy_{$table}_table.php";

    $php = <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Support\Facades\DB;

        return new class extends Migration
        {
            public function up(): void
            {
                DB::statement(<<<'SQL'
        {$ddl}
        SQL);
            }

            public function down(): void
            {
                DB::statement('DROP TABLE IF EXISTS `{$table}`');
            }
        };

        PHP;

    file_put_contents($file, $php);
    $generated++;
    fwrite(STDOUT, "generated: {$table}\n");
}

fwrite(STDOUT, "\n{$generated} migrations written to {$outDir}\n");
