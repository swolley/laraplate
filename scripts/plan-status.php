<?php

declare(strict_types=1);

/**
 * plan-status — development backlog of a repository, or of a whole workspace.
 *
 * Scans three kinds of work item and prints them as a terminal table, JSON or a
 * self-contained HTML page:
 *
 *   plans  implementation plans in `docs/superpowers/plans/`, `.cursor/plans/`
 *          and `Modules/&#42;/docs/plans/`, broken down per task
 *   specs  design docs in `docs/superpowers/specs/`, flagged when no plan mentions them
 *   todos  TODO / FIXME / @todo markers in source files
 *
 * Repositories are discovered, not declared: every directory under the root that
 * holds plans or specs is one, the root included. `--root` therefore decides how
 * much the tool can see. Pointed at this repository it reports this repository;
 * pointed at the parent folder of a multi-repository workspace it reports all of
 * them. No sibling repository is named anywhere in this file, which is what lets
 * the same script serve an open source backend and a closed workspace around it.
 *
 * Truth about a plan's state comes from its YAML front-matter `status:` when
 * present; checkbox counts are reported alongside and any disagreement between
 * the two is surfaced as an inconsistency. The tool never edits a plan.
 *
 * Usage: composer run plan-status [-- options]
 *        php scripts/plan-status.php [--root=<path>] [options]
 */
final class Workspace
{
    public const PLAN_DIRS = ['docs/superpowers/plans', '.cursor/plans', 'Modules/*/docs/plans'];

    public const SPEC_DIRS = ['docs/superpowers/specs', '.cursor/specs'];

    public const TODO_EXTENSIONS = ['php', 'ts', 'tsx', 'js', 'mjs', 'vue', 'sh'];

    public const TODO_EXCLUDED = [
        'vendor', 'node_modules', '.git', 'storage', 'dist', 'build', 'coverage',
        '.pnpm-store', 'public/build', 'bootstrap/cache', '.nuxt', '.output',
    ];

    /**
     * @var array<string, string>|null
     */
    private ?array $repos = null;

    public function __construct(public readonly string $root) {}

    /**
     * The repositories to scan, discovered rather than declared: any directory
     * holding plans or specs is one, the root included. A declared list would have
     * to name the sibling repositories, and would keep reporting one after its
     * plans are gone; this cannot drift because it only ever describes what is
     * there.
     *
     * @return array<string, string> repository name => path relative to the root, '' being the root itself
     */
    public function repos(): array
    {
        if ($this->repos !== null) {
            return $this->repos;
        }

        $root_holds_work = false;
        $children = [];

        foreach ([...self::PLAN_DIRS, ...self::SPEC_DIRS] as $dir) {
            if ((glob($this->path($this->root, $dir), GLOB_ONLYDIR) ?: []) !== []) {
                $root_holds_work = true;
            }

            foreach (glob($this->path($this->root, '*', $dir), GLOB_ONLYDIR) ?: [] as $hit) {
                $children[explode('/', $this->relative($hit))[0]] = true;
            }
        }

        $names = array_keys($children);
        sort($names);

        // The root first: it is the workspace itself, and reads as the heading of
        // the repositories nested under it.
        $repos = $root_holds_work ? [basename($this->root) => ''] : [];

        foreach ($names as $name) {
            $repos[$name] = $name;
        }

        return $this->repos = $repos;
    }

    public function path(string ...$parts): string
    {
        $clean = array_filter($parts, static fn (string $p): bool => $p !== '');
        $joined = implode('/', $clean);

        return preg_replace('#/+#', '/', $joined) ?? $joined;
    }

    public function relative(string $path): string
    {
        return str_starts_with($path, $this->root . '/') ? mb_substr($path, mb_strlen($this->root) + 1) : $path;
    }
}

final class PlanParser
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return list<array<string, mixed>>
     */
    public static function sort(array $plans, string $by): array
    {
        $comparator = match ($by) {
            'repo' => static fn (array $a, array $b): int => [$a['repo'], $a['file']] <=> [$b['repo'], $b['file']],
            'progress' => static fn (array $a, array $b): int => [
                $a['total_steps'] > 0 ? $a['checked_steps'] / $a['total_steps'] : 1.0,
                $a['date'],
            ] <=> [
                $b['total_steps'] > 0 ? $b['checked_steps'] / $b['total_steps'] : 1.0,
                $b['date'],
            ],
            default => static fn (array $a, array $b): int => [$a['date'], $a['repo'], $a['file']] <=> [$b['date'], $b['repo'], $b['file']],
        };

        usort($plans, $comparator);

        return $plans;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $repo_filter, ?string $name_filter, bool $with_evidence): array
    {
        $plans = [];

        foreach ($this->workspace->repos() as $repo => $relative) {
            if ($repo_filter !== null && $repo_filter !== $repo) {
                continue;
            }

            foreach (Workspace::PLAN_DIRS as $plan_dir) {
                $pattern = $this->workspace->path($this->workspace->root, $relative, $plan_dir);
                $dirs = str_contains($pattern, '*') ? (glob($pattern, GLOB_ONLYDIR) ?: []) : [$pattern];

                foreach ($dirs as $dir) {
                    if (! is_dir($dir)) {
                        continue;
                    }

                    foreach ($this->markdown($dir) as $file) {
                        $name = basename($file);

                        if (in_array($name, ['INDEX.md', 'README.md'], true)) {
                            continue;
                        }

                        if ($name_filter !== null && ! str_contains($name, $name_filter)) {
                            continue;
                        }

                        $plan = $this->parse($file, $repo, $relative, $plan_dir, $with_evidence);

                        $plans[] = $plan;
                    }
                }
            }
        }

        return $plans;
    }

    /**
     * @return list<string>
     */
    private function markdown(string $dir): array
    {
        $files = glob($dir . '/*.md');

        return $files === false ? [] : array_values($files);
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $file, string $repo, string $repo_relative, string $plan_dir, bool $with_evidence): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

        $front_matter = $this->frontMatter($lines);
        $title = basename($file, '.md');
        $title_seen = false;
        $status_banner = null;
        $tasks = [];
        $current = $this->newTask('(plan level)', 0);
        $in_fence = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $in_fence = ! $in_fence;

                continue;
            }

            if ($in_fence) {
                continue;
            }

            if (! $title_seen && preg_match('/^#\s+(.+)$/', $line, $m) === 1) {
                $title = mb_trim($m[1]);
                $title_seen = true;

                continue;
            }

            if ($status_banner === null && preg_match('/^\*\*Status:\*\*\s*(.+)$/', $line, $m) === 1) {
                $status_banner = mb_trim($m[1]);

                continue;
            }

            if (preg_match('/^#{2,4}\s+((?:Task|Phase|Step|Milestone|Wave)\b.*)$/i', $line, $m) === 1) {
                if ($current['total'] > 0) {
                    $tasks[] = $current;
                }

                $current = $this->newTask(mb_trim($m[1]), $index + 1);

                continue;
            }

            if (preg_match('/^\s*[-*] \[([ xX])\]\s*(.*)$/', $line, $m) === 1) {
                $current['total']++;

                if (mb_strtolower($m[1]) === 'x') {
                    $current['checked']++;
                }

                continue;
            }

            if (preg_match('/^\s*[-*] (Create|Modify|Test|Migration|File):\s*(.+)$/i', $line, $m) === 1) {
                foreach ($this->paths($m[2]) as $path) {
                    $current['files'][] = $path;
                }
            }
        }

        if ($current['total'] > 0) {
            $tasks[] = $current;
        }

        $total = 0;
        $checked = 0;

        foreach ($tasks as $i => $task) {
            $total += $task['total'];
            $checked += $task['checked'];

            $tasks[$i]['state'] = $this->state($task['checked'], $task['total']);
            $tasks[$i]['files'] = array_values(array_unique($task['files']));
            [$found, $missing] = $with_evidence
                ? $this->evidence($tasks[$i]['files'], $repo_relative)
                : [0, []];
            $tasks[$i]['files_found'] = $found;
            $tasks[$i]['files_total'] = count($tasks[$i]['files']);
            $tasks[$i]['files_missing'] = $missing;
            $tasks[$i]['stale'] = $tasks[$i]['state'] !== 'done'
                && $tasks[$i]['files_total'] > 0
                && $found === $tasks[$i]['files_total'];
        }

        $checkbox_state = $this->state($checked, $total);
        $declared = $front_matter['status'] ?? null;

        [$date, $date_source] = $this->dateOf($file);

        return [
            'kind' => 'plan',
            'repo' => $repo,
            'source' => $plan_dir,
            'file' => basename($file),
            'date' => $date,
            'date_source' => $date_source,
            'path' => $this->workspace->relative($file),
            'title' => $title,
            'front_matter_status' => $declared,
            'status_banner' => $status_banner,
            'tasks' => $tasks,
            'total_steps' => $total,
            'checked_steps' => $checked,
            'checkbox_state' => $checkbox_state,
            'state' => $this->effectiveState($declared, $checkbox_state),
            'inconsistency' => $this->inconsistency($declared, $status_banner, $checkbox_state, $checked, $total),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function frontMatter(array $lines): array
    {
        if (($lines[0] ?? '') !== '---') {
            return [];
        }

        $data = [];

        foreach (array_slice($lines, 1) as $line) {
            if ($line === '---') {
                break;
            }

            if (preg_match('/^([a-z_]+):\s*(.+)$/i', $line, $m) === 1) {
                $data[mb_strtolower($m[1])] = mb_trim($m[2], " \t\"'");
            }
        }

        return $data;
    }

    private function effectiveState(?string $declared, string $checkbox_state): string
    {
        return match (mb_strtolower((string) $declared)) {
            'completed', 'done', 'shipped' => 'done',
            'superseded', 'abandoned', 'cancelled' => 'superseded',
            'in-progress', 'in_progress', 'partial' => 'partial',
            'open', 'draft', 'planned' => $checkbox_state === 'done' ? 'partial' : 'open',
            default => $checkbox_state,
        };
    }

    private function inconsistency(?string $declared, ?string $banner, string $checkbox_state, int $checked, int $total): ?string
    {
        // A plan with no checkboxes cannot disagree with its checkboxes. Retrospective
        // records are written that way on purpose, and flagging them would bury the
        // real disagreements.
        if ($total === 0) {
            return null;
        }

        $effective = $this->effectiveState($declared, $checkbox_state);

        if ($declared === null && $banner !== null && preg_match('/^complet/i', $banner) === 1 && $checkbox_state !== 'done') {
            return sprintf('banner says completed but %d/%d steps are flagged', $checked, $total);
        }

        if ($declared !== null && $effective === 'done' && $checkbox_state !== 'done') {
            return sprintf('front-matter says %s but %d/%d steps are flagged', $declared, $checked, $total);
        }

        if ($declared !== null && $effective !== 'done' && $checkbox_state === 'done') {
            return sprintf('front-matter says %s but every step is flagged', $declared);
        }

        return null;
    }

    /**
     * The plan's own date, which is the one that matters when deciding what to
     * pick up next. Most plans carry it in the file name; anything else falls
     * back to the file's modification time, and says so.
     *
     * @return array{0: string, 1: string}
     */
    private function dateOf(string $file): array
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', basename($file), $m) === 1) {
            return [$m[1], 'filename'];
        }

        $mtime = @filemtime($file);

        return [$mtime === false ? '0000-00-00' : date('Y-m-d', $mtime), 'mtime'];
    }

    /**
     * @return array<string, mixed>
     */
    private function newTask(string $name, int $line): array
    {
        return ['name' => $name, 'line' => $line, 'checked' => 0, 'total' => 0, 'files' => []];
    }

    private function state(int $checked, int $total): string
    {
        if ($total === 0) {
            return 'empty';
        }

        return $checked === $total ? 'done' : ($checked === 0 ? 'open' : 'partial');
    }

    /**
     * @return list<string>
     */
    private function paths(string $text): array
    {
        if (preg_match_all('/`([^`]+)`/', $text, $matches) === 0) {
            return [];
        }

        $paths = [];

        foreach ($matches[1] as $candidate) {
            $candidate = mb_rtrim(mb_trim(explode(' ', mb_trim($candidate))[0]), ',;');

            if (! str_contains($candidate, '/') || str_contains($candidate, '::') || str_contains($candidate, '(')) {
                continue;
            }

            $paths[] = $candidate;
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     * @return array{0: int, 1: list<string>}
     */
    private function evidence(array $paths, string $repo_relative): array
    {
        $found = 0;
        $missing = [];

        foreach ($paths as $path) {
            $candidates = [
                $this->workspace->path($this->workspace->root, $path),
                $this->workspace->path($this->workspace->root, $repo_relative, $path),
            ];

            $exists = false;

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $exists = true;

                    break;
                }

                if (str_contains($candidate, '*')) {
                    $matches = glob($candidate);

                    if ($matches !== false && $matches !== []) {
                        $exists = true;

                        break;
                    }
                }
            }

            $exists ? $found++ : $missing[] = $path;
        }

        return [$found, $missing];
    }
}

final class SpecScanner
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return list<array<string, mixed>>
     */
    public function all(?string $repo_filter, array $plans): array
    {
        $plan_blob = '';
        $plan_names = [];

        foreach ($plans as $plan) {
            $contents = @file_get_contents($this->workspace->path($this->workspace->root, $plan['path']));
            $plan_blob .= $contents === false ? '' : $contents;
            $plan_names[(string) $plan['file']] = (string) $plan['path'];
        }

        $specs = [];

        foreach ($this->workspace->repos() as $repo => $relative) {
            if ($repo_filter !== null && $repo_filter !== $repo) {
                continue;
            }

            foreach (Workspace::SPEC_DIRS as $spec_dir) {
                $dir = $this->workspace->path($this->workspace->root, $relative, $spec_dir);

                if (! is_dir($dir)) {
                    continue;
                }

                $files = glob($dir . '/*.md');

                foreach ($files === false ? [] : $files as $file) {
                    $name = basename($file);

                    if (in_array($name, ['INDEX.md', 'README.md'], true)) {
                        continue;
                    }

                    // Three distinct situations, and conflating them hides real work:
                    // a plan cites the spec; a plan exists for the same subject but
                    // never names it; no plan exists at all.
                    $cited = str_contains($plan_blob, $name);
                    $sibling = $cited ? null : $this->siblingPlan($name, $plan_names);
                    $state = match (true) {
                        $cited => 'planned',
                        $sibling !== null => 'plan_uncited',
                        default => 'unplanned',
                    };

                    $specs[] = [
                        'kind' => 'spec',
                        'repo' => $repo,
                        'file' => $name,
                        'path' => $this->workspace->relative($file),
                        'title' => $this->title($file),
                        'has_plan' => $state !== 'unplanned',
                        'sibling_plan' => $sibling,
                        'state' => $state,
                    ];
                }
            }
        }

        usort($specs, static fn (array $a, array $b): int => [$a['repo'], $a['file']] <=> [$b['repo'], $b['file']]);

        return $specs;
    }

    /**
     * A plan for the same subject, found by stripping the spec's trailing marker.
     * `2026-07-29-hasform-entity-preset-design.md` -> `2026-07-29-hasform-entity-preset.md`.
     *
     * @param  array<string, string>  $plan_names
     */
    private function siblingPlan(string $spec_file, array $plan_names): ?string
    {
        $stem = preg_replace('/-(design|review|gate|spec)\.md$/', '.md', $spec_file);

        if ($stem === null || $stem === $spec_file) {
            return null;
        }

        return $plan_names[$stem] ?? null;
    }

    private function title(string $file): string
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return basename($file, '.md');
        }

        $title = basename($file, '.md');

        for ($i = 0; $i < 20; $i++) {
            $line = fgets($handle);

            if ($line === false) {
                break;
            }

            if (preg_match('/^#\s+(.+)$/', mb_trim($line), $m) === 1) {
                $title = mb_trim($m[1]);

                break;
            }
        }

        fclose($handle);

        return $title;
    }
}

final class TodoScanner
{
    private const PATTERN = '/\b(TODO|FIXME|@todo|XXX)\b:?\s*(.*)$/i';

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $repo_filter, int $limit): array
    {
        $todos = [];
        $repos = $this->workspace->repos();

        foreach ($repos as $repo => $relative) {
            if ($repo_filter !== null && $repo_filter !== $repo) {
                continue;
            }

            $base = $this->workspace->path($this->workspace->root, $relative);

            if (! is_dir($base)) {
                continue;
            }

            // The root contains the repositories nested under it. Without this, its
            // scan would walk into each of them and report every marker twice: once
            // against the root, once against the repository that owns it.
            $nested = [];

            foreach ($repos as $other => $other_relative) {
                if ($other !== $repo && $other_relative !== '') {
                    $nested[] = $this->workspace->path($this->workspace->root, $other_relative);
                }
            }

            foreach ($this->sourceFiles($base, $nested) as $file) {
                $handle = fopen($file, 'r');

                if ($handle === false) {
                    continue;
                }

                $line_number = 0;

                while (($line = fgets($handle)) !== false) {
                    $line_number++;

                    if (preg_match(self::PATTERN, $line, $m) !== 1) {
                        continue;
                    }

                    $todos[] = [
                        'kind' => 'todo',
                        'repo' => $repo,
                        'marker' => mb_strtoupper($m[1]),
                        'path' => $this->workspace->relative($file),
                        'line' => $line_number,
                        'text' => mb_trim(mb_substr(mb_trim($m[2]), 0, 120)),
                        'state' => 'open',
                    ];

                    if ($limit <= count($todos)) {
                        fclose($handle);

                        return $todos;
                    }
                }

                fclose($handle);
            }
        }

        return $todos;
    }

    /**
     * Pruning happens here, in the filter a RecursiveCallbackFilterIterator applies
     * before descending: an excluded directory is never entered, so the cost of
     * `vendor/` and `node_modules/` is one string comparison each, not a walk.
     *
     * @param  list<string>  $nested  absolute directories to skip, the repositories inside this one
     * @return iterable<string>
     */
    private function sourceFiles(string $base, array $nested): iterable
    {
        $directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $info) use ($base, $nested): bool {
            if (! $info->isDir()) {
                return in_array(mb_strtolower($info->getExtension()), Workspace::TODO_EXTENSIONS, true);
            }

            $path = $info->getPathname();

            if (in_array($path, $nested, true)) {
                return false;
            }

            if (str_starts_with($info->getFilename(), '.')) {
                return false;
            }

            // Entries carrying a slash are matched against the path from the
            // repository root, otherwise `bootstrap/cache` would never match:
            // the comparison only ever saw the last segment.
            $relative = str_starts_with($path, $base . '/') ? mb_substr($path, mb_strlen($base) + 1) : $path;

            foreach (Workspace::TODO_EXCLUDED as $excluded) {
                $matches = str_contains($excluded, '/')
                    ? $relative === $excluded
                    : $excluded === $info->getFilename();

                if ($matches) {
                    return false;
                }
            }

            return true;
        });

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}

final class Renderer
{
    private const LABEL = [
        'done' => 'DONE',
        'partial' => 'PARTIAL',
        'open' => 'OPEN',
        'superseded' => 'SUPERSED',
        'empty' => 'NO BOXES',
        'planned' => 'PLANNED',
        'unplanned' => 'NO PLAN',
        'plan_uncited' => 'UNCITED',
    ];

    private const COLOR = [
        'done' => "\033[32m",
        'partial' => "\033[33m",
        'open' => "\033[31m",
        'superseded' => "\033[90m",
        'empty' => "\033[90m",
        'planned' => "\033[90m",
        'unplanned' => "\033[35m",
        'plan_uncited' => "\033[33m",
    ];

    public function __construct(
        private readonly bool $colors,
        private readonly string $style = 'icon',
    ) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function table(array $report, bool $summary): string
    {
        $out = [];

        if ($report['plans'] !== []) {
            $out[] = $this->rule('PLANS');

            foreach ($report['plans'] as $plan) {
                $declared = is_string($plan['front_matter_status'])
                    ? sprintf('  status: %s', $plan['front_matter_status'])
                    : '';

                $out[] = sprintf(
                    '%s%s  [%s]  %d/%d steps  %s%s',
                    $plan['date_source'] === 'mtime' ? sprintf('~%s  ', $plan['date']) : '',
                    $plan['file'],
                    $plan['repo'],
                    $plan['checked_steps'],
                    $plan['total_steps'],
                    $this->state((string) $plan['state']),
                    $declared,
                );

                if (is_string($plan['inconsistency'])) {
                    $out[] = '    ! ' . $plan['inconsistency'];
                }

                if ($summary) {
                    continue;
                }

                foreach ($plan['tasks'] as $task) {
                    $evidence = $task['files_total'] > 0
                        ? sprintf('  files %d/%d', $task['files_found'], $task['files_total'])
                        : '';

                    $out[] = sprintf(
                        '    %s %s %2d/%-2d%s%s',
                        $this->checkbox((string) $task['state']),
                        $this->pad($this->shorten((string) $task['name'], 52), 52),
                        $task['checked'],
                        $task['total'],
                        $evidence,
                        $task['stale'] === true ? '  << every declared file exists' : '',
                    );
                }

                $out[] = '';
            }
        }

        $uncited = array_values(array_filter(
            $report['specs'],
            static fn (array $spec): bool => ($spec['state'] ?? '') === 'plan_uncited',
        ));

        if ($uncited !== []) {
            $out[] = $this->rule('SPECS WHOSE PLAN EXISTS BUT NEVER NAMES THEM');

            foreach ($uncited as $spec) {
                $out[] = sprintf('    %-8s [%s] %s', $this->state('plan_uncited'), $spec['repo'], $this->shorten((string) $spec['title'], 66));
                $out[] = sprintf('             spec: %s', $spec['path']);
                $out[] = sprintf('             plan: %s', (string) $spec['sibling_plan']);
            }

            $out[] = '';
        }

        $unplanned = array_values(array_filter(
            $report['specs'],
            static fn (array $spec): bool => ($spec['state'] ?? '') === 'unplanned',
        ));

        if ($unplanned !== []) {
            $out[] = $this->rule('SPECS WITH NO PLAN AT ALL');

            foreach ($unplanned as $spec) {
                $out[] = sprintf('    %-8s [%s] %s', $this->state('unplanned'), $spec['repo'], $this->shorten((string) $spec['title'], 70));
                $out[] = sprintf('             %s', $spec['path']);
            }

            $out[] = '';
        }

        if ($report['todos'] !== []) {
            $out[] = $this->rule('CODE MARKERS');

            foreach ($report['todos'] as $todo) {
                $out[] = sprintf(
                    '    %-6s %s:%d  %s',
                    $todo['marker'],
                    $todo['path'],
                    $todo['line'],
                    $this->shorten((string) $todo['text'], 80),
                );
            }

            $out[] = '';
        }

        $totals = $report['totals'];
        $out[] = sprintf(
            'Plans %d (%d done, %d partial, %d open, %d superseded) | tasks %d done, %d partial, %d open | specs: %d with no plan, %d with an uncited plan | code markers %d',
            $totals['plans'],
            $totals['plans_done'],
            $totals['plans_partial'],
            $totals['plans_open'],
            $totals['plans_superseded'],
            $totals['tasks_done'],
            $totals['tasks_partial'],
            $totals['tasks_open'],
            $totals['specs_unplanned'],
            $totals['specs_plan_uncited'],
            $totals['todos'],
        );

        if ($totals['stale_tasks'] > 0) {
            $out[] = sprintf(
                '%d unflagged task(s) have every declared file on disk: verify them, then flag or explain.',
                $totals['stale_tasks'],
            );
        }

        if ($totals['inconsistent_plans'] > 0) {
            $out[] = sprintf(
                '%d plan(s) disagree with their own declared status: reconcile the front-matter.',
                $totals['inconsistent_plans'],
            );
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function json(array $report): string
    {
        $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ($encoded === false ? '{}' : $encoded) . "\n";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function html(array $report): string
    {
        $rows = [];

        foreach ($report['plans'] as $plan) {
            $state = (string) $plan['state'];
            $note = is_string($plan['inconsistency'])
                ? '<span class="warn">' . $this->escape($plan['inconsistency']) . '</span>'
                : $this->escape((string) ($plan['front_matter_status'] ?? ''));

            $rows[] = sprintf(
                '<tr class="group plan %s" data-kind="plan"><td>%s</td><td><strong>%s</strong></td>'
                . '<td class="num">%d/%d</td><td><span class="pill %s">%s</span></td><td>%s</td></tr>',
                $state,
                $this->escape((string) $plan['repo']),
                $this->escape((string) $plan['title']),
                $plan['checked_steps'],
                $plan['total_steps'],
                $state,
                self::LABEL[$state] ?? $state,
                $note,
            );

            foreach ($plan['tasks'] as $task) {
                $task_state = (string) $task['state'];
                $evidence = $task['files_total'] > 0
                    ? sprintf('%d/%d files', $task['files_found'], $task['files_total'])
                    : '';
                $hint = $task['stale'] === true ? ' <span class="warn">all files exist</span>' : '';

                $rows[] = sprintf(
                    '<tr class="row %s" data-kind="plan"><td class="dim">%s</td><td>%s</td>'
                    . '<td class="num">%d/%d</td><td><span class="box" title="%s">%s</span></td><td>%s%s</td></tr>',
                    $task_state,
                    $this->escape((string) $plan['file']),
                    $this->escape((string) $task['name']),
                    $task['checked'],
                    $task['total'],
                    $this->escape(self::LABEL[$task_state] ?? $task_state),
                    $this->icon($task_state),
                    $this->escape($evidence),
                    $hint,
                );
            }
        }

        foreach ($report['specs'] as $spec) {
            if (($spec['state'] ?? '') === 'planned') {
                continue;
            }

            $rows[] = sprintf(
                '<tr class="row %s" data-kind="spec"><td class="dim">%s</td><td>%s</td>'
                . '<td class="num"></td><td><span class="pill %s">%s</span></td><td class="dim">%s</td></tr>',
                (string) $spec['state'],
                $this->escape((string) $spec['repo']),
                $this->escape((string) $spec['title']),
                (string) $spec['state'],
                self::LABEL[(string) $spec['state']] ?? '',
                $this->escape((string) ($spec['sibling_plan'] ?? $spec['path'])),
            );
        }

        foreach ($report['todos'] as $todo) {
            $rows[] = sprintf(
                '<tr class="row open" data-kind="todo"><td class="dim">%s</td><td>%s</td>'
                . '<td class="num"></td><td><span class="pill open">%s</span></td><td class="dim">%s:%d</td></tr>',
                $this->escape((string) $todo['repo']),
                $this->escape((string) $todo['text']),
                $this->escape((string) $todo['marker']),
                $this->escape((string) $todo['path']),
                $todo['line'],
            );
        }

        $totals = $report['totals'];
        $summary = sprintf(
            '%d plans · %d tasks open · %d specs without a plan · %d code markers · generated %s',
            $totals['plans'],
            $totals['tasks_open'],
            $totals['specs_unplanned'],
            $totals['todos'],
            (string) $report['generated_at'],
        );

        $body = implode("\n", $rows);

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laraplate backlog</title>
<style>
:root { color-scheme: light dark; --bg:#fff; --fg:#14161a; --muted:#6b7280; --line:#e5e7eb; --chip:#f3f4f6;
        --done:#0a7f3f; --partial:#9a6700; --open:#b42318; --other:#6b21a8; }
@media (prefers-color-scheme: dark) { :root { --bg:#14161a; --fg:#e8e8e8; --muted:#9aa0a6; --line:#2a2d33; --chip:#22252b;
        --done:#43c06f; --partial:#e3b341; --open:#ff7b72; --other:#d8b4fe; } }
body { margin:0; padding:24px; background:var(--bg); color:var(--fg);
       font:14px/1.5 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
h1 { font-size:18px; margin:0 0 4px; } p.sub { color:var(--muted); margin:0 0 16px; font-size:13px; }
.controls { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
button { font:inherit; font-size:13px; padding:5px 12px; border:1px solid var(--line); border-radius:999px;
         background:var(--chip); color:var(--fg); cursor:pointer; }
button[aria-pressed="true"] { border-color:currentColor; font-weight:600; }
.wrap { overflow-x:auto; } table { border-collapse:collapse; width:100%; min-width:760px; }
td { padding:6px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
tr.group td { background:var(--chip); border-top:2px solid var(--line); }
td.dim { color:var(--muted); font-size:12px; white-space:nowrap; }
td.num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
.pill { font-size:11px; letter-spacing:.04em; padding:2px 8px; border-radius:999px; border:1px solid currentColor; white-space:nowrap; }
.done .pill{color:var(--done)} .partial .pill{color:var(--partial)} .open .pill{color:var(--open)}
.box { display:inline-flex; align-items:center; }
.ico { width:16px; height:16px; display:block; }
.done .ico{color:var(--done)} .partial .ico{color:var(--partial)} .open .ico{color:var(--open)} .empty .ico{color:var(--muted)}
.unplanned .pill,.superseded .pill{color:var(--other)}
.warn { color:var(--partial); font-size:12px; }
[hidden]{display:none!important}
</style></head><body>
<h1>Laraplate backlog</h1>
<p class="sub">{$summary}</p>
<div class="controls">
  <button data-f="all" aria-pressed="true">All</button>
  <button data-f="open" aria-pressed="false">Open</button>
  <button data-f="partial" aria-pressed="false">Partial</button>
  <button data-f="done" aria-pressed="false">Done</button>
  <button data-k="plan" aria-pressed="false">Plans only</button>
  <button data-k="spec" aria-pressed="false">Specs only</button>
  <button data-k="todo" aria-pressed="false">Markers only</button>
</div>
<div class="wrap"><table>{$body}</table></div>
<script>
var state = { f: 'all', k: 'all' };
function apply() {
  document.querySelectorAll('tr.row').forEach(function (row) {
    var okF = state.f === 'all' || row.classList.contains(state.f);
    var okK = state.k === 'all' || row.dataset.kind === state.k;
    row.hidden = !(okF && okK);
  });
  document.querySelectorAll('tr.group').forEach(function (row) {
    var next = row.nextElementSibling, visible = false;
    while (next && next.classList.contains('row')) {
      if (!next.hidden) { visible = true; break; }
      next = next.nextElementSibling;
    }
    row.hidden = !(visible || (state.f === 'all' && state.k === 'all'));
  });
}
document.querySelectorAll('button[data-f]').forEach(function (b) {
  b.addEventListener('click', function () {
    state.f = b.dataset.f;
    document.querySelectorAll('button[data-f]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    apply();
  });
});
document.querySelectorAll('button[data-k]').forEach(function (b) {
  b.addEventListener('click', function () {
    state.k = state.k === b.dataset.k ? 'all' : b.dataset.k;
    document.querySelectorAll('button[data-k]').forEach(function (o) {
      o.setAttribute('aria-pressed', String(o === b && state.k !== 'all'));
    });
    apply();
  });
});
</script>
</body></html>
HTML;
    }

    /**
     * Inline SVG rather than a glyph: the shape is identical on every machine,
     * it inherits the state colour through `currentColor`, and it never widens
     * a column because a font substituted it.
     */
    private function icon(string $state): string
    {
        $path = match ($state) {
            'done' => '<polyline points="3.5,8.5 6.8,11.8 12.5,4.8"/>',
            'partial' => '<path d="M3.5 9.2c1.2-2.6 2.5-2.6 3.7 0s2.5 2.6 3.7 0"/><circle cx="8" cy="8" r="6.2" opacity=".35"/>',
            'empty' => '<path d="M4 8h8" opacity=".6"/>',
            default => '<circle cx="8" cy="8" r="5.6"/>',
        };

        return '<svg class="ico" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }

    private function rule(string $title): string
    {
        return "\n" . $title . "\n" . str_repeat('-', mb_strlen($title));
    }

    private function state(string $state): string
    {
        $label = self::LABEL[$state] ?? $state;

        return $this->colors ? (self::COLOR[$state] ?? '') . $label . "\033[0m" : $label;
    }

    /**
     * Pad to a visible width. `sprintf`'s `%-Ns` counts bytes, so a single
     * em-dash or accented letter in a task name shifted the whole column.
     */
    private function pad(string $text, int $width): string
    {
        $missing = $width - mb_strlen($text);

        return $missing > 0 ? $text . str_repeat(' ', $missing) : $text;
    }

    private function boxMark(string $state): string
    {
        return match ($state) {
            'done' => 'x',
            'partial' => '~',
            'empty' => '-',
            default => ' ',
        };
    }

    /**
     * Single-width glyphs on purpose: anything wider (or an emoji) breaks the
     * column on terminals that render it double-width.
     */
    private function iconMark(string $state): string
    {
        return match ($state) {
            'done' => '✓',
            'partial' => '~',
            'empty' => '–',
            default => '○',
        };
    }

    /**
     * Task rows read as checkboxes, the way they are written in the plan itself.
     * Always three visible characters, so callers pad around it rather than
     * through it: ANSI colour codes would break `%-Ns` alignment.
     */
    private function checkbox(string $state): string
    {
        if ($this->style === 'icon') {
            $icon = $this->iconMark($state);

            return $this->colors
                ? (self::COLOR[$state] ?? '') . $icon . "\033[0m" . '  '
                : $icon . '  ';
        }

        $mark = $this->boxMark($state);

        if (! $this->colors) {
            return '[' . $mark . ']';
        }

        // Only the mark carries colour: coloured brackets read as noise and
        // make three adjacent boxes look like one block.
        return '[' . (self::COLOR[$state] ?? '') . $mark . "\033[0m" . ']';
    }

    private function shorten(string $text, int $max): string
    {
        $text = mb_trim(preg_replace('/\*\*|`/', '', $text) ?? $text);

        return $max >= mb_strlen($text) ? $text : mb_substr($text, 0, $max - 1) . '…';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * @return array<string, string|bool>
 */
function parse_options(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;

            continue;
        }

        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m) !== 1) {
            fwrite(STDERR, "Unknown argument: {$arg}\n");

            exit(1);
        }

        $options[$m[1]] = $m[2] ?? true;
    }

    return $options;
}

/**
 * Resolve a --repo filter against the repositories actually found. An exact name
 * wins; failing that a single substring match is enough, so the short names people
 * already type keep working once a repository is known by its directory name.
 *
 * @param  array<string, string>  $repos
 */
function resolve_repo(string $filter, array $repos): string
{
    if (array_key_exists($filter, $repos)) {
        return $filter;
    }

    $matches = array_values(array_filter(
        array_keys($repos),
        static fn (string $name): bool => str_contains($name, $filter),
    ));

    if (count($matches) === 1) {
        return $matches[0];
    }

    $known = implode(', ', array_keys($repos));

    fwrite(STDERR, $matches === []
        ? "Unknown --repo={$filter}. Found: {$known}.\n"
        : sprintf("Ambiguous --repo=%s: matches %s.\n", $filter, implode(', ', $matches)));

    exit(1);
}

function usage(): string
{
    return <<<'TXT'
plan-status — development backlog of a repository, or of a whole workspace.

Usage: composer run plan-status [-- options]
       php scripts/plan-status.php [--root=<path>] [options]

  --root=<path>              where to scan from (default: this repository). Point it
                             at the parent folder to cover every repository in it
  --format=table|json|html   output format (default: table)
  --kind=plans,specs,todos   which sources to scan (default: plans,specs)
  --open                     only work that is not done
  --stale                    only unflagged tasks whose declared files all exist
  --plan=<substring>         filter plans by file name
  --repo=<name>              limit to one repository; a unique substring is enough
  --sort=date|repo|progress  order plans: oldest first (default), grouped by repo,
                             or least-advanced first
  --style=icon|box           task marks as icons (default) or ASCII checkboxes
  --summary                  one line per plan, no task detail
  --todo-limit=<n>           cap code markers (default: 200)
  --out=<path>               write to a file instead of stdout
  --no-evidence              skip filesystem checks (faster)
  --repos                    list the repositories found under the root and exit
  -h, --help                 this help

Repositories are discovered: every directory under the root holding plans or specs
is one. Sources are docs/superpowers/{plans,specs}, .cursor/{plans,specs} and
Modules/*/docs/plans. A plan's YAML front-matter `status:` (completed|in-progress|
open|superseded) overrides its checkbox counts; disagreements between the two are
reported. The tool never edits a plan.

TXT;
}

$options = parse_options($argv);

if (($options['help'] ?? false) === true) {
    fwrite(STDOUT, usage());

    exit(0);
}

$format = is_string($options['format'] ?? null) ? $options['format'] : 'table';

if (! in_array($format, ['table', 'json', 'html'], true)) {
    fwrite(STDERR, "Invalid --format. Use table, json or html.\n");

    exit(1);
}

$kinds = is_string($options['kind'] ?? null)
    ? array_map('trim', explode(',', $options['kind']))
    : ['plans', 'specs'];

$root = is_string($options['root'] ?? null) ? $options['root'] : dirname(__DIR__);
$resolved_root = realpath($root);

if ($resolved_root === false || ! is_dir($resolved_root)) {
    fwrite(STDERR, "Invalid --root={$root}: not a directory.\n");

    exit(1);
}

$workspace = new Workspace($resolved_root);
$repos = $workspace->repos();

if ($repos === []) {
    fwrite(STDERR, "No plans or specs found under {$resolved_root}.\n");

    exit(1);
}

if (($options['repos'] ?? false) === true) {
    foreach ($repos as $name => $relative) {
        fwrite(STDOUT, sprintf("%-24s %s\n", $name, $relative === '' ? '.' : $relative));
    }

    exit(0);
}

$repo = is_string($options['repo'] ?? null) ? resolve_repo($options['repo'], $repos) : null;
$with_evidence = ($options['no-evidence'] ?? false) !== true;
$only_open = ($options['open'] ?? false) === true;
$only_stale = ($options['stale'] ?? false) === true;

$plan_parser = new PlanParser($workspace);

$sort = is_string($options['sort'] ?? null) ? $options['sort'] : 'date';

if (! in_array($sort, ['date', 'repo', 'progress'], true)) {
    fwrite(STDERR, "Invalid --sort. Use date, repo or progress.\n");

    exit(1);
}

$plans = in_array('plans', $kinds, true)
    ? PlanParser::sort(
        $plan_parser->all($repo, is_string($options['plan'] ?? null) ? $options['plan'] : null, $with_evidence),
        $sort,
    )
    : [];

// The "does any plan reference this spec?" question must always be answered against
// every plan in the stack, never against the filtered subset: a --plan or --repo
// filter would otherwise report perfectly planned specs as orphans.
$specs = in_array('specs', $kinds, true)
    ? (new SpecScanner($workspace))->all($repo, $plan_parser->all(null, null, false))
    : [];

$todo_limit = is_string($options['todo-limit'] ?? null) ? max(1, (int) $options['todo-limit']) : 200;
$todos = in_array('todos', $kinds, true)
    ? (new TodoScanner($workspace))->all($repo, $todo_limit)
    : [];

$stale_tasks = 0;
$inconsistent_plans = 0;
$tasks_done = 0;
$tasks_partial = 0;
$tasks_open = 0;

foreach ($plans as $plan) {
    if (is_string($plan['inconsistency'])) {
        $inconsistent_plans++;
    }

    foreach ($plan['tasks'] as $task) {
        match ($task['state']) {
            'done' => $tasks_done++,
            'partial' => $tasks_partial++,
            default => $tasks_open++,
        };

        if ($task['stale'] === true) {
            $stale_tasks++;
        }
    }
}

$totals = [
    'plans' => count($plans),
    'plans_done' => count(array_filter($plans, static fn (array $p): bool => $p['state'] === 'done')),
    'plans_partial' => count(array_filter($plans, static fn (array $p): bool => $p['state'] === 'partial')),
    'plans_open' => count(array_filter($plans, static fn (array $p): bool => $p['state'] === 'open')),
    'plans_superseded' => count(array_filter($plans, static fn (array $p): bool => $p['state'] === 'superseded')),
    'tasks_done' => $tasks_done,
    'tasks_partial' => $tasks_partial,
    'tasks_open' => $tasks_open,
    'stale_tasks' => $stale_tasks,
    'inconsistent_plans' => $inconsistent_plans,
    'specs_unplanned' => count(array_filter($specs, static fn (array $s): bool => $s['state'] === 'unplanned')),
    'specs_plan_uncited' => count(array_filter($specs, static fn (array $s): bool => $s['state'] === 'plan_uncited')),
    'todos' => count($todos),
];

if ($only_open || $only_stale) {
    $filtered = [];

    foreach ($plans as $plan) {
        if ($plan['state'] === 'done' || $plan['state'] === 'superseded') {
            continue;
        }

        $tasks = array_values(array_filter($plan['tasks'], static function (array $task) use ($only_stale): bool {
            if ($task['state'] === 'done') {
                return false;
            }

            return $only_stale ? $task['stale'] === true : true;
        }));

        if ($tasks === []) {
            continue;
        }

        $plan['tasks'] = $tasks;
        $filtered[] = $plan;
    }

    $plans = $filtered;

    if ($only_stale) {
        $specs = [];
        $todos = [];
    }
}

$report = [
    'generated_at' => date('c'),
    'totals' => $totals,
    'plans' => $plans,
    'specs' => $specs,
    'todos' => $todos,
];

$out_path = is_string($options['out'] ?? null) ? $options['out'] : null;
$colors = $out_path === null && $format === 'table' && stream_isatty(STDOUT);
$style = is_string($options['style'] ?? null) ? $options['style'] : 'icon';

if (! in_array($style, ['icon', 'box'], true)) {
    fwrite(STDERR, "Invalid --style. Use icon or box.\n");

    exit(1);
}

$renderer = new Renderer($colors, $style);

$output = match ($format) {
    'json' => $renderer->json($report),
    'html' => $renderer->html($report),
    default => $renderer->table($report, ($options['summary'] ?? false) === true),
};

if ($out_path !== null) {
    file_put_contents($out_path, $output);
    fwrite(STDOUT, "Written: {$out_path}\n");

    exit(0);
}

fwrite(STDOUT, $output);

exit($plans === [] && $specs === [] && $todos === [] ? 2 : 0);
