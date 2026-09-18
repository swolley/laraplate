<?php

declare(strict_types=1);

/**
 * Counts the frozen PHPStan debt: the errors the baseline is holding.
 *
 * `composer run check` includes phpstan-baseline.neon, so it answers "did I make
 * things worse", and [OK] means no new error. It cannot answer "how much is left",
 * because everything in the baseline is subtracted before the total is printed.
 *
 * This runs the same analysis with that one include removed. Nothing else changes:
 * the same level, the same paths, the same ignoreErrors rules, so the number is
 * comparable with the one the baseline header records.
 *
 * Usage:
 *   composer run check:debt              totals, and a breakdown by identifier
 *   composer run check:debt -- --quiet   the number alone, for a script
 */
final class PhpstanDebt
{
    private const string CONFIG = 'phpstan.neon';

    /**
     * Written next to phpstan.neon because relative includes (vendor/…, paths)
     * resolve against the config file's own directory, so a temp dir will not do.
     */
    private const string TEMP_CONFIG = '.phpstan-debt.neon';

    private const string BASELINE_LINE = '#^\s*-\s*phpstan-baseline\.neon\s*$#m';

    public function __construct(private readonly string $root) {}

    public function run(bool $quiet): int
    {
        $config = $this->root . '/' . self::CONFIG;

        if (! is_file($config)) {
            fwrite(STDERR, "No {$config}.\n");

            return 1;
        }

        $temp = $this->root . '/' . self::TEMP_CONFIG;
        $source = (string) file_get_contents($config);
        $stripped = preg_replace(self::BASELINE_LINE, '', $source);

        if ($stripped === null || $stripped === $source) {
            fwrite(STDERR, "phpstan.neon does not include phpstan-baseline.neon; `composer run check` already reports the real total.\n");

            return 1;
        }

        file_put_contents($temp, $stripped);

        // The temp config must not survive a failed run: a stale copy without the
        // baseline would make the next `composer run check` look catastrophic.
        register_shutdown_function(static function () use ($temp): void {
            if (is_file($temp)) {
                unlink($temp);
            }
        });

        if (! $quiet) {
            fwrite(STDERR, "Analysing without the baseline (a cold run takes a few minutes)...\n");
        }

        $report = $this->analyse($temp);

        if ($report === null) {
            return 1;
        }

        return $this->report($report, $quiet);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyse(string $config): ?array
    {
        $command = sprintf(
            '%s/vendor/bin/phpstan analyse -c %s --memory-limit=4G --no-progress --error-format=json 2>/dev/null',
            escapeshellarg($this->root),
            escapeshellarg($config),
        );

        // escapeshellarg() on the whole path would quote the binary and the redirect
        // with it, so the root is quoted where it is interpolated instead.
        $command = str_replace("'" . $this->root . "'/vendor", escapeshellarg($this->root . '/vendor'), $command);

        $output = shell_exec($command);

        if (! is_string($output) || $output === '') {
            fwrite(STDERR, "PHPStan produced no output.\n");

            return null;
        }

        // A warning printed before the JSON would break json_decode; start at the
        // first brace, which is where the report begins.
        $start = mb_strpos($output, '{"totals"');
        $decoded = json_decode($start === false ? $output : mb_substr($output, $start), true);

        if (! is_array($decoded) || ! isset($decoded['totals'])) {
            fwrite(STDERR, "Could not read the PHPStan report.\n");

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function report(array $report, bool $quiet): int
    {
        $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
        $total = is_numeric($totals['file_errors'] ?? null) ? (int) $totals['file_errors'] : 0;

        if ($quiet) {
            echo $total, PHP_EOL;

            return 0;
        }

        echo PHP_EOL, 'Frozen debt: ', number_format($total, 0, '.', '.'), ' errors', PHP_EOL;
        echo 'Held by phpstan-baseline.neon. `composer run check` subtracts these and reports only what is new.', PHP_EOL, PHP_EOL;

        foreach ($this->byIdentifier($report) as $identifier => [$count, $places]) {
            printf("  %-34s %7s in %5s %s\n", $identifier, number_format($count, 0, '.', '.'), number_format($places, 0, '.', '.'), $places === 1 ? 'place' : 'places');
        }

        echo PHP_EOL, 'A high count over few places is one fact reported once per class using a trait:', PHP_EOL;
        echo 'those are the cheap ones to fix.', PHP_EOL;

        return 0;
    }

    /**
     * Report count and distinct physical locations per identifier, most reports
     * first, which is the ordering that says where the leverage is.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array{int, int}>
     */
    private function byIdentifier(array $report): array
    {
        $counts = [];
        $places = [];
        $files = is_array($report['files'] ?? null) ? $report['files'] : [];

        foreach ($files as $file => $entry) {
            if (! is_array($entry) || ! is_array($entry['messages'] ?? null)) {
                continue;
            }

            // PHPStan appends "(in context of class X)" when a trait is analysed
            // once per user; the physical file is what comes before it.
            $path = is_string($file) ? (string) preg_replace('# \(in context of .*$#', '', $file) : '';

            foreach ($entry['messages'] as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $identifier = is_string($message['identifier'] ?? null) ? $message['identifier'] : '(none)';
                $line = is_numeric($message['line'] ?? null) ? (int) $message['line'] : 0;

                $counts[$identifier] = ($counts[$identifier] ?? 0) + 1;
                $places[$identifier][$path . ':' . $line] = true;
            }
        }

        arsort($counts);

        $rows = [];

        foreach ($counts as $identifier => $count) {
            $rows[$identifier] = [$count, count($places[$identifier] ?? [])];
        }

        return $rows;
    }
}

$quiet = in_array('--quiet', array_slice($argv, 1), true);

exit(new PhpstanDebt(dirname(__DIR__))->run($quiet));
