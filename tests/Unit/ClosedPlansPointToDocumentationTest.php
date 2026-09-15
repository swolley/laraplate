<?php

declare(strict_types=1);

/**
 * A plan and a module document answer different questions and age differently.
 *
 * A plan is dated: it stays true forever about one moment. Module documentation describes the
 * present and is wrong the instant the code moves. So a shipped feature whose only description
 * lives in a plan is undocumented, and the reader who finds it there will build what was decided
 * rather than what exists. That has already happened in this repository: a plan named artifacts
 * the implementation deliberately replaced, and a shipped bibliography went unmentioned in the
 * module docs for months.
 *
 * The rule this enforces: closing a plan means saying where the behaviour is described now.
 */

use Illuminate\Support\Str;

/**
 * @return list<string>
 */
function closedPlanFiles(): array
{
    $files = glob(base_path('docs/superpowers/plans/*.md')) ?: [];

    return array_values(array_filter(
        $files,
        static fn (string $file): bool => Str::contains((string) file_get_contents($file), '## Delivery status'),
    ));
}

it('finds plans marked as delivered', function (): void {
    // Guards the guard: a glob that silently matches nothing would make every case below vacuous.
    expect(closedPlanFiles())->not->toBeEmpty();
});

it('makes every delivered plan name the documentation that describes it now', function (): void {
    $missing = [];

    foreach (closedPlanFiles() as $file) {
        if (preg_match('#Modules/[A-Za-z]+/docs/[A-Za-z0-9_/.-]+\.md#', (string) file_get_contents($file)) !== 1) {
            $missing[] = basename($file);
        }
    }

    expect($missing)->toBe([], implode(', ', $missing) . ' report delivery but point at no module documentation');
});

it('keeps those pointers resolving to files that exist', function (): void {
    $broken = [];

    foreach (closedPlanFiles() as $file) {
        preg_match_all('#Modules/[A-Za-z]+/docs/[A-Za-z0-9_/.-]+\.md#', (string) file_get_contents($file), $matches);

        foreach (array_unique($matches[0]) as $path) {
            if (! is_file(base_path($path))) {
                $broken[] = basename($file) . ' -> ' . $path;
            }
        }
    }

    expect($broken)->toBe([], implode('; ', $broken));
});
