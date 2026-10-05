<?php

declare(strict_types=1);

use Illuminate\Database\Schema\ForeignKeyDefinition;

/**
 * A misspelled foreign key action fails silently.
 *
 * `ForeignKeyDefinition` extends `Fluent`, so an unknown call such as `restricadeOnDelete()` is stored
 * as an attribute instead of raising an error, and the constraint is created with no ON DELETE rule.
 * The migration runs, the tests pass, and the intended behaviour never reaches the database. That
 * happened to the MES work centers table, which carried the typo from its first commit.
 *
 * The rule this enforces: every `...OnDelete()` / `...OnUpdate()` call in a migration names a method
 * that `ForeignKeyDefinition` really declares.
 */

/**
 * @return list<string>
 */
function migrationFiles(): array
{
    return array_merge(
        glob(base_path('database/migrations/*.php')) ?: [],
        glob(base_path('Modules/*/database/migrations/*.php')) ?: [],
    );
}

it('finds migrations to inspect', function (): void {
    // Guards the guard: a glob that silently matches nothing would make the case below vacuous.
    expect(migrationFiles())->not->toBeEmpty();
});

it('uses only foreign key actions that exist', function (): void {
    $unknown = [];

    foreach (migrationFiles() as $file) {
        preg_match_all('/->(\w+On(?:Delete|Update))\(/', (string) file_get_contents($file), $matches);

        foreach (array_unique($matches[1]) as $method) {
            if (! method_exists(ForeignKeyDefinition::class, $method)) {
                $unknown[] = str_replace(base_path() . '/', '', $file) . ' -> ' . $method . '()';
            }
        }
    }

    expect($unknown)->toBe([], implode('; ', $unknown));
});
