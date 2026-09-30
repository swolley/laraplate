<?php

declare(strict_types=1);

/**
 * `- [-]` marks a step deliberately not done. It must close the step without counting it as
 * delivered work, so a plan that drops a phase can be closed and stops reading as open.
 */
it('resolves cancelled steps without reporting them as done or open', function (): void {
    $root = sys_get_temp_dir() . '/plan-status-' . uniqid();
    mkdir($root . '/docs/superpowers/plans', 0777, true);
    file_put_contents($root . '/docs/superpowers/plans/2026-01-01-sample.md', <<<'MD'
        # Sample plan

        ### Task 1: Built

        - [x] Step one

        ### Task 2: Dropped

        - [-] Step two, not built: measured need was absent
        - [-] Step three
        MD);

    try {
        exec(sprintf('php %s --root=%s --format=json --no-evidence', escapeshellarg(base_path('scripts/plan-status.php')), escapeshellarg($root)), $output);
        $report = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        exec(sprintf('php %s --root=%s --open --no-evidence', escapeshellarg(base_path('scripts/plan-status.php')), escapeshellarg($root)), $open);
    } finally {
        unlink($root . '/docs/superpowers/plans/2026-01-01-sample.md');
        rmdir($root . '/docs/superpowers/plans');
        rmdir($root . '/docs/superpowers');
        rmdir($root . '/docs');
        rmdir($root);
    }

    $plan = $report['plans'][0];

    expect($plan['state'])->toBe('done')
        ->and($plan['checked_steps'])->toBe(3)
        ->and(array_column($plan['tasks'], 'state'))->toBe(['done', 'cancelled'])
        ->and($report['totals']['tasks_cancelled'])->toBe(1)
        ->and(implode("\n", $open))->not->toContain('2026-01-01-sample.md');
});
