# Octane Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the state and concurrency hazards that would make Laraplate unsafe on long-lived workers, so that adopting Octane later is a configuration decision instead of a refactor.

**Architecture:** Two groups of work. **Group A** (Tasks 1–2) removes code that is structurally incompatible with a shared process: per-request subprocess concurrency and POSIX signal handlers in HTTP-reachable paths. Each of these tasks also adds its half of a source-level guardrail test, so the hazard cannot return. **Group B** (Tasks 3–6) converts state that is logically per-request but stored in `static` properties or process-wide singletons into request-scoped state, using `once()` and container `scoped()` bindings. Task 7 records the rules. **Every change in this plan is correct and beneficial under plain PHP-FPM too** — nothing here depends on Octane being installed.

**Octane itself is deliberately NOT installed by this plan.** The adoption steps are preserved in "Appendix: adopting Octane later" and were reviewed, but installing `laravel/octane` and the FrankenPHP binary into the AGPL repo is a product decision the user has deferred.

**Tech Stack:** PHP 8.5, Laravel 12, Pest 4, Filament 5, Livewire 4, Redis, SQLite for tests.

**Related:**
- `.cursor/rules/03-performance-optimization.mdc`
- `.cursor/rules/09-database-guidelines.mdc`
- `docs/superpowers/plans/2026-07-13-global-performance-optimization.md`
- `Modules/Core/docs/rag/PERFORMANCE_TOOLKIT.md`

---

## Global Constraints

- Chat with the user in Italian; code, comments and PHPDoc in English.
- PHP local and private variables `snake_case`; everything else `camelCase`.
- Conventional commits, one logical commit per task per repository.
- **No new Composer dependency.** Not even `laravel/octane`.
- Do not change public method signatures or return types unless the task says so.
- Do not run broad refactors while executing a task.
- Keep SQLite tests green. Run tests with `XDEBUG_MODE=off` — Xdebug is enabled by default in this environment and adds roughly 500 ms per request.
- **The user gave explicit consent to work directly on `master`.** No feature branch.
- **The working tree is dirty with unrelated work in progress.** `Modules/Core` has modifications to `app/Http/Requests/MediaUploadRequest.php` plus an untracked `tests/Integration/Http/Requests/MediaUploadRequestTest.php`; the parent repo has modified `composer.lock`, `package-lock.json`, `resources/swagger/App-swagger.json` and `storage/pest-grid.log`; `Modules/SAO` is untracked. **Never use `git add -A`, `git add .`, or `git commit -a`.** Stage only the exact paths your task names.

## Repository layout: where each task commits

`Modules/{Core,CMS,AI,ERP,MES,SAO}` are **git submodules**, each its own repository, each currently on `master`. A task that edits `Modules/Core/app/...` commits **inside** `Modules/Core`, and the parent `laraplate` repository then records the new submodule pointer in a second commit.

| Task | Repositories touched |
|---|---|
| 1 | `Modules/Core` |
| 2 | `Modules/AI`, `Modules/Core` (guardrail test lives in Core) |
| 3 | `Modules/Core`, `Modules/CMS` (one test call site lives in CMS) |
| 4 | `Modules/Core` |
| 5 | `Modules/Core` |
| 6 | `Modules/Core` |
| 7 | `Modules/Core` (module docs), `laraplate` (README) |

The commit sequence for a task touching one submodule — substitute the real paths and message:

```bash
cd Modules/Core
git add app/Path/Changed.php tests/Path/ChangedTest.php
git commit -m "type(core): subject"
cd ../..
git add Modules/Core
git commit -m "chore(modules): bump Core for <subject>"
```

For a task touching two submodules, commit in each, then bump both pointers in a single parent commit. Paths inside a task's **Files** block are written relative to `laraplate/`; translate them when you `cd` into the submodule.

## Verified baseline — what Octane already resets (do NOT write code for these)

Verified against `laravel/octane` 2.x sources, not assumed. Recorded here so a future reader does not "harden" a non-problem, and so the appendix stays honest.

| Concern | Already handled by | Evidence |
|---|---|---|
| `App::setLocale()` in `LocalizationMiddleware` / `LocaleContext` leaking to the next request | `CreateConfigurationSandbox` + `FlushLocaleState` | `tests/LocaleStateTest.php::test_carbon_and_app_locale_gets_updated_when_changing_app_locale` asserts `en → nl → en → pt` |
| `config()->set()` at runtime leaking | `CreateConfigurationSandbox` clones the config repository per operation | `Octane::prepareApplicationForNextOperation()` |
| `Auth::login()` in `AuthorizationService::resolveUser()` leaking the guard | `FlushAuthenticationState` | `Octane::prepareApplicationForNextRequest()` |
| Session state leaking | `FlushSessionState` + `GiveNewApplicationInstanceToSessionManager` | same |
| `array` cache store serving stale rows, including inside the `failover` store | `FlushArrayCache` flushes `Cache::store('array')`; `FailoverStore` holds that same instance | `Listeners/FlushArrayCache.php` |
| `once()` memoisation persisting across requests | `FlushOnce` on `OperationTerminated` | published `config/octane.php` |
| `Cache::memo()` and `scoped()` bindings persisting | `FlushTemporaryContainerInstances` on `OperationTerminated` | same |

Two facts the implementer must rely on:

1. **`once()` is correctly keyed by the closure's captured variables.** `Onceable::hashFromTrace()` maps `getClosureUsedVariables()` into the hash, so `once(fn () => f($key))` memoises per `$key`. Objects captured this way are hashed with `spl_object_hash`, which PHP **reuses** once the previous object is freed — verified against this repository's `vendor/`, where a captured object returned the value memoized for an earlier, unrelated object. So capture a scalar identity, or make the captured class implement `Illuminate\Contracts\Support\HasOnceHash` and return its own identity from `onceHash()`; `Onceable` prefers that over the handle.
2. **`Once::flush()` runs between tests** (`InteractsWithTestCaseLifecycle`), so `once()` never leaks across test cases, and tests can call it explicitly to simulate a request boundary.

## Caches that must stay warm (do NOT flush or convert these)

These derive from code or database schema, so a deploy or migration invalidates them naturally. Converting one of them to `once()` would throw away the benefit and slow the application down. Any task that touches one is wrong.

- `Modules/Core/app/Inspector/SchemaInspector.php` — table/column introspection
- `Modules/Core/app/Inspector/ModelMetadataRegistry.php` — reflection metadata
- `Modules/Core/app/Helpers/HelpersCache.php` — model class map
- `Modules/Core/app/Services/Crud/CrudService.php` — reflection cache for method parameters
- `Modules/Core/app/Models/Concerns/HasTranslations.php` — `$cached_translatable_fields`
- `Modules/Core/app/Helpers/LocaleContext.php` — `$cached_default_locale`
- `Modules/Core/app/Cache/CacheManager.php` — `$app_name`
- `Modules/Core/app/Services/FlagCDNService.php` — static country/flag map

Measured baseline for context (`perf:crud`, in-process, Xdebug off): `cms/contents` ≈ 320 ms / 25 queries, `cms/locations` ≈ 280 ms / 41 queries. This plan does **not** address those query counts.

## Non-goals

- Installing or configuring Octane (see appendix).
- Reducing the 25–41 queries per CRUD select.
- Refactoring `AuthorizationService::resolveUser()` to stop mutating the auth guard.
- Making `ParallelTaskRunner` / `BatchSeeder` worker-safe — they are CLI-only and Task 1's guardrail keeps them there.
- Removing the Grid subsystem. Task 1 marks it deprecated; deletion is separate work.

## Execution rules

- One task at a time: failing test → minimal implementation → passing test → commit.
- Test commands run from `laraplate/`. The root `phpunit.xml` includes `Modules/*/tests/{Unit,Integration,UnitShell,Feature}`, so tests inside submodules run from there.
- Behaviour gate: a task that changes a read path must assert the returned value, not merely the absence of an exception.
- If a task uncovers unrelated modifications in a file it must edit, stop and report rather than committing them.

## File structure

**Modify:**
- `Modules/Core/app/Grids/Components/Grid.php` — Task 1
- `Modules/Core/app/Http/Controllers/GridsController.php` — Task 1
- `Modules/Core/routes/web.php` — Task 1
- `Modules/AI/app/Services/ApplicationContent/ApplicationContentDeadlineExecutor.php` — Task 2
- `Modules/Core/app/Services/Authorization/AuthorizationService.php` — Task 3
- `Modules/Core/app/Models/Concerns/HasValidations.php` — Task 3
- `Modules/Core/app/Filament/Utils/HasTable.php` — Task 3
- `Modules/Core/app/Models/User.php` — Task 3 (`HasOnceHash`)
- `Modules/Core/app/Console/WarmCacheCommand.php` — Task 3 (delete the dead permission warm step)
- `Modules/Core/app/Models/Concerns/HasClosureTable.php` — Task 4
- `Modules/Core/app/Providers/CoreServiceProvider.php` — Task 5
- `Modules/Core/config/permission.php` — Task 6
- `Modules/Core/docs/rag/PERFORMANCE_TOOLKIT.md`, `README.md` — Task 7

**Create:**
- `Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php` — Task 1 (concurrency half), Task 2 (signals half)
- `Modules/AI/tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php` — Task 2
- `Modules/Core/tests/Integration/Services/Authorization/PermissionMemoizationTest.php` — Task 3
- `Modules/Core/tests/Integration/Models/ClosureTableDepthMemoizationTest.php` — Task 4
- `Modules/Core/app/Http/Middleware/ApplyDatabaseSettingsOverlay.php` — Task 5
- `Modules/Core/tests/Integration/Settings/DatabaseSettingsOverlayTest.php` — Task 5
- `Modules/Core/tests/Unit/Config/LongLivedWorkerConfigTest.php` — Task 6

---

### Task 1: Deprecate the Grid surface and stop spawning subprocesses

**Why:** `Grid::processGridActions()` runs up to three closures through `Concurrency::driver('process')`, which spawns `php artisan invoke-serialized-closure` child processes — a full framework boot each — on the live `app/crud/grid/*` routes. The closures mutate `$responseBuilder` **in place** (`processData()` calls `setClass()`, `setTable()`, `setDataIntoResponse()`) while `run()`'s return value is discarded, so those mutations cannot reach the caller from a child process. There is no `config/concurrency.php` in the repo and no test exercises `processGridActions`, so the branch is unverified.

The Grid subsystem is being retired: only its Funnels concept survives, extracted as **Facets** (`Modules/Core/app/Services/Crud/DTOs/Facet*.php`, served by `CrudService` / `CrudController`). Therefore **do not** invest in characterising or preserving Grid's response behaviour. Mark the surface deprecated and make the execution in-process, which removes the hazard and the subprocess boots at once.

**Files:**
- Modify: `Modules/Core/app/Grids/Components/Grid.php:704-729` (plus class docblock)
- Modify: `Modules/Core/app/Http/Controllers/GridsController.php` (class docblock)
- Modify: `Modules/Core/routes/web.php:45-56` (route group comment)
- Test: `Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php` (create, concurrency half only)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `Modules\Core\Tests\...` — the file `LongLivedWorkerSafetyTest.php` with a helper `http_reachable_sources(): array<string, string>` that **Task 2 extends with a second test**. Task 2 must reuse this helper, not redefine it.

- [x] **Step 1: Write the failing guardrail test**

Create `Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * Long-lived workers share one process across requests. Process-wide primitives
 * (subprocess pools, forks, POSIX signals) must stay out of code a request can
 * reach, both because they are unsafe there and because each subprocess pays a
 * full framework boot.
 */

use Symfony\Component\Finder\Finder;

/**
 * Sources reachable from an HTTP request, excluding the CLI-only allow-list.
 *
 * @return array<string, string>
 */
function http_reachable_sources(): array
{
    // The Unit suite boots a minimal container without a base path, so resolve the
    // project root from this file, exactly as ModelFinalClassTest.php in this
    // directory already does.
    $project_root = dirname(__DIR__, 5);

    $roots = [
        $project_root . '/app',
        $project_root . '/Modules/Core/app',
        $project_root . '/Modules/AI/app',
        $project_root . '/Modules/CMS/app',
        $project_root . '/Modules/ERP/app',
        $project_root . '/Modules/MES/app',
        $project_root . '/Modules/SAO/app',
    ];

    foreach ($roots as $root) {
        // Fail loudly if a module moves: a silently skipped root would narrow the guardrail.
        expect(is_dir($root))->toBeTrue("Expected source root at {$root}");
    }

    $finder = Finder::create()
        ->files()
        ->name('*.php')
        ->in($roots)
        ->notPath('Concurrency')
        ->notPath('Console')
        ->notPath('Performance')
        ->notPath('Helpers/BatchSeeder.php')
        ->notPath('Overrides/Seeder.php');

    $sources = [];

    foreach ($finder as $file) {
        $sources[$file->getRelativePathname()] = (string) $file->getContents();
    }

    return $sources;
}

it('never runs subprocess or fork concurrency in request-reachable code', function (): void {
    $offenders = [];

    foreach (http_reachable_sources() as $path => $contents) {
        if (preg_match("/Concurrency::driver\(/", $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
```

The `notPath` entries are the CLI-only allow-list: `Modules/Core/app/Concurrency` (`ParallelTaskRunner`), Artisan commands, `Modules/Core/app/Performance` (`EndpointProfiler`, which calls `setUser()` and is command-only), and the two seeder files. **Do not widen this list to make the test pass** — widening it is how the hazard comes back.

- [x] **Step 2: Run the test to verify it fails**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`
Expected: FAIL, listing `Grids/Components/Grid.php`.

- [x] **Step 3: Run the grid actions in-process**

Replace `Modules/Core/app/Grids/Components/Grid.php:721-729` with:

```php
        foreach ($processes as $process) {
            $process();
        }
```

Delete the now-unused `Concurrency` and `RuntimeException` imports **only if nothing else in the file uses them** — check first; `RuntimeException` in particular is thrown elsewhere in this class.

Note what this changes: the closures already mutate `$responseBuilder` in place, so running them here is what the surrounding code always assumed. No caller signature changes.

- [x] **Step 4: Mark the Grid surface deprecated**

Add to the `Grid` class docblock in `Modules/Core/app/Grids/Components/Grid.php`:

```php
/**
 * @deprecated The Grid subsystem is being retired. Its Funnels concept survives as
 *             Facets: see Modules\Core\Services\Crud\DTOs\FacetQuery and the facet
 *             handling in Modules\Core\Services\Crud\CrudService. Do not build on
 *             this class.
 */
```

Add the same `@deprecated` tag, with the same replacement pointer, to the `GridsController` class docblock in `Modules/Core/app/Http/Controllers/GridsController.php`.

In `Modules/Core/routes/web.php`, extend the existing comment above the `grid` route group (line 45) so the deprecation is visible where the surface is published:

```php
// Grid routes mirror the CRUD verbs above on a different URI prefix, so they need
// their own group. DEPRECATED: this surface is being retired; facets on the CRUD
// routes replace the Funnels action.
```

Keep the existing wording of that comment's first sentence; only append the deprecation note.

- [x] **Step 5: Run the guardrail test and the grid suite**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`
Expected: PASS.

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Grids/`
Expected: PASS — these tests do not exercise `processGridActions`, so they should be unaffected. If any fails, report it rather than adapting the test.

- [x] **Step 6: Commit**

```bash
cd Modules/Core
git add app/Grids/Components/Grid.php app/Http/Controllers/GridsController.php routes/web.php tests/Unit/Architecture/LongLivedWorkerSafetyTest.php
git commit -m "refactor(core): deprecate the grid surface and run grid actions in-process"
cd ../..
git add Modules/Core
git commit -m "chore(modules): bump Core for grid deprecation"
```

---

### Task 2: Deadline executor — no POSIX signals under long-lived workers

**Why:** `ApplicationContentDeadlineExecutor` installs a `SIGALRM` handler and calls `pcntl_alarm()` around AI retrieval, and it is reachable from HTTP through `ApplicationContentToolProvider`. A signal handler installed from a shared worker process outlives the request that installed it. The class already measures elapsed time after the operation, so a signal-free fallback preserves the deadline contract — post-hoc instead of interrupting.

**Design decision, already made — do not revisit:** the interrupting signal path is **kept**, not deleted, because under PHP-FPM it caps worst-case latency by aborting a slow retrieval mid-flight, which the soft deadline cannot do. It moves verbatim into `Modules/AI/app/Concurrency/ApplicationContentSignalDeadline.php`. The `Concurrency/` directory is the agreed home for process-level primitives and is on the guardrail's allow-list, so the guardrail passes without widening anything. It stays in the AI module because it throws an AI domain exception; do not move it to Core.

**Verified facts about the current code — the plan's earlier draft got these wrong:**
- The real signature is `run(callable $operation, int $timeoutSeconds)` — **operation first, timeout second.**
- The exception is `Modules\AI\Services\ApplicationContent\Exceptions\ApplicationContentDeadlineExceededException` (note the `Exceptions` sub-namespace).
- The class is `final readonly`, so a promoted constructor property must **not** carry its own `readonly` modifier — that is a fatal error in a readonly class. Write `private ?bool $signalsAllowed = null`.
- Today, when `supported()` is false, `run()` throws `LogicException`. After this task it runs the soft deadline instead. That is the intended improvement, **but existing tests may assert the old `LogicException`** — `Modules/AI/tests/Unit/Services/ApplicationContent/ApplicationContentToolProviderTest.php:260` constructs an executor directly. Check for such an assertion and report it rather than silently rewriting an unrelated test.
- The executor's only production consumer is `ApplicationContentToolProvider:36` (HTTP). No console command uses it.

**Files:**
- Modify: `Modules/AI/app/Services/ApplicationContent/ApplicationContentDeadlineExecutor.php`
- Create: `Modules/AI/app/Concurrency/ApplicationContentSignalDeadline.php`
- Test: `Modules/AI/tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php` (create)
- Test: `Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php` (extend)

**Interfaces:**
- Consumes: `http_reachable_sources(): array<string, string>` — the helper created by Task 1 in `LongLivedWorkerSafetyTest.php`. Reuse it; do not redefine it.
- Produces: `ApplicationContentDeadlineExecutor::__construct(?bool $signalsAllowed = null)` (`null` = auto-detect) and `ApplicationContentSignalDeadline::supported(): bool` + `ApplicationContentSignalDeadline::run(callable $operation, int $timeoutSeconds): mixed`.

- [x] **Step 1: Write the failing tests**

Create `Modules/AI/tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\ApplicationContent\ApplicationContentDeadlineExecutor;
use Modules\AI\Services\ApplicationContent\Exceptions\ApplicationContentDeadlineExceededException;

it('returns the operation result without installing signal handlers', function (): void {
    $executor = new ApplicationContentDeadlineExecutor(signalsAllowed: false);

    $handler_before = function_exists('pcntl_signal_get_handler')
        ? pcntl_signal_get_handler(SIGALRM)
        : null;

    $result = $executor->run(static fn (): string => 'payload', 5);

    expect($result)->toBe('payload');

    if (function_exists('pcntl_signal_get_handler')) {
        expect(pcntl_signal_get_handler(SIGALRM))->toBe($handler_before);
    }
});

it('reports a deadline breach after the fact when signals are unavailable', function (): void {
    $executor = new ApplicationContentDeadlineExecutor(signalsAllowed: false);

    expect(fn (): mixed => $executor->run(static function (): string {
        usleep(1_100_000);

        return 'too slow';
    }, 1))->toThrow(ApplicationContentDeadlineExceededException::class);
});

it('still interrupts with a signal when signals are explicitly allowed', function (): void {
    if (! ApplicationContentSignalDeadline::supported()) {
        $this->markTestSkipped('pcntl signals unavailable in this environment.');
    }

    $executor = new ApplicationContentDeadlineExecutor(signalsAllowed: true);

    expect($executor->run(static fn (): string => 'fast', 5))->toBe('fast');
});
```

Add the `use Modules\AI\Concurrency\ApplicationContentSignalDeadline;` import for the third test.

Then append the signals half of the guardrail to `Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`, below the existing test:

```php
it('never installs POSIX signal handlers in request-reachable code', function (): void {
    $offenders = [];

    foreach (http_reachable_sources() as $path => $contents) {
        if (preg_match('/\bpcntl_(signal|alarm|async_signals|fork)\s*\(/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
```

- [x] **Step 2: Run both test files to verify they fail**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/AI/tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`
Expected: FAIL — the constructor does not accept `signalsAllowed`, and the guardrail lists `ApplicationContent/ApplicationContentDeadlineExecutor.php`.

- [x] **Step 3: Move the signal path into its own class**

Create `Modules/AI/app/Concurrency/ApplicationContentSignalDeadline.php`, moving the existing signal logic **verbatim** out of the executor:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Concurrency;

use LogicException;
use Modules\AI\Services\ApplicationContent\Exceptions\ApplicationContentDeadlineExceededException;

/**
 * Interrupts an operation with SIGALRM once it overruns its deadline.
 *
 * Lives under Concurrency/ because it installs a process-wide signal handler: that
 * directory is this codebase's home for process-level primitives, and the
 * LongLivedWorkerSafetyTest guardrail excludes it from the request-reachable scan.
 * Callers must consult {@see self::supported()} first — on a long-lived worker the
 * handler would outlive the request that installed it.
 */
final readonly class ApplicationContentSignalDeadline
{
    public static function supported(): bool
    {
        return function_exists('pcntl_alarm')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_signal_get_handler')
            && defined('SIGALRM');
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $operation
     * @return TReturn
     */
    public function run(callable $operation, int $timeoutSeconds): mixed
    {
        $timeout_seconds = min(30, max(1, $timeoutSeconds));
        $previous_alarm = pcntl_alarm(0);

        if ($previous_alarm > 0) {
            pcntl_alarm($previous_alarm);

            throw new LogicException('An application content deadline cannot replace an active process alarm.');
        }

        $previous_async_signals = pcntl_async_signals(true);
        $previous_handler = pcntl_signal_get_handler(SIGALRM);
        $started_at = hrtime(true);

        pcntl_signal(SIGALRM, static function (): never {
            throw new ApplicationContentDeadlineExceededException('Application content retrieval exceeded its deadline.');
        });
        pcntl_alarm($timeout_seconds);

        try {
            $result = $operation();
            $elapsed_seconds = (hrtime(true) - $started_at) / 1_000_000_000;

            if ($elapsed_seconds > $timeout_seconds) {
                throw new ApplicationContentDeadlineExceededException('Application content retrieval exceeded its deadline.');
            }

            return $result;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previous_handler);
            pcntl_async_signals($previous_async_signals);
        }
    }
}
```

- [x] **Step 4: Reduce the executor to a runtime choice plus the soft deadline**

`Modules/AI/app/Services/ApplicationContent/ApplicationContentDeadlineExecutor.php` becomes:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\ApplicationContent;

use Modules\AI\Concurrency\ApplicationContentSignalDeadline;
use Modules\AI\Services\ApplicationContent\Exceptions\ApplicationContentDeadlineExceededException;

final readonly class ApplicationContentDeadlineExecutor
{
    /**
     * @param  bool|null  $signalsAllowed  Null auto-detects; false forces the soft deadline.
     */
    public function __construct(
        private ?bool $signalsAllowed = null,
    ) {}

    public function run(callable $operation, int $timeoutSeconds): mixed
    {
        if ($this->supportsSignals()) {
            return (new ApplicationContentSignalDeadline)->run($operation, $timeoutSeconds);
        }

        return $this->runWithSoftDeadline($operation, $timeoutSeconds);
    }

    /**
     * Long-lived workers must not receive process-wide signal handlers: the handler
     * would outlive the request that installed it.
     */
    private function longLivedWorker(): bool
    {
        return class_exists(\Laravel\Octane\Octane::class)
            || extension_loaded('swoole')
            || extension_loaded('openswoole');
    }

    private function supportsSignals(): bool
    {
        if ($this->signalsAllowed === false) {
            return false;
        }

        if ($this->signalsAllowed === null && $this->longLivedWorker()) {
            return false;
        }

        return ApplicationContentSignalDeadline::supported();
    }

    /**
     * Enforces the deadline after the fact: the operation is not interrupted, but a
     * breach is still reported to the caller.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $operation
     * @return TReturn
     */
    private function runWithSoftDeadline(callable $operation, int $timeoutSeconds): mixed
    {
        $timeout_seconds = min(30, max(1, $timeoutSeconds));
        $started_at = hrtime(true);

        $result = $operation();

        $elapsed_seconds = (hrtime(true) - $started_at) / 1_000_000_000;

        if ($elapsed_seconds > $timeout_seconds) {
            throw new ApplicationContentDeadlineExceededException('Application content retrieval exceeded its deadline.');
        }

        return $result;
    }
}
```

`run()`'s public signature is unchanged. The `LogicException` for "interruptible deadline unavailable" is gone by design: an unavailable signal path now degrades to the soft deadline instead of failing the request.

- [x] **Step 5: Run both test files to verify they pass**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/AI/tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php Modules/Core/tests/Unit/Architecture/LongLivedWorkerSafetyTest.php`
Expected: PASS (4 tests, one possibly skipped where pcntl is unavailable).

Run: `XDEBUG_MODE=off vendor/bin/pest --filter=ApplicationContent`
Expected: PASS — this is the gate that proves the tool provider and the adversarial retrieval tests still work. If a pre-existing test asserted the removed `LogicException`, report it instead of rewriting it.

- [x] **Step 6: Commit**

```bash
cd Modules/AI
git add app/Services/ApplicationContent/ApplicationContentDeadlineExecutor.php app/Concurrency/ApplicationContentSignalDeadline.php tests/Unit/ApplicationContent/ApplicationContentDeadlineExecutorTest.php
git commit -m "fix(ai): degrade to a soft deadline when POSIX signals are unsafe"
cd ../Core
git add tests/Unit/Architecture/LongLivedWorkerSafetyTest.php
git commit -m "test(core): guard against POSIX signal handlers in request-reachable code"
cd ../..
git add Modules/AI Modules/Core
git commit -m "chore(modules): bump AI and Core for signal-free AI deadlines"
```

---

### Task 3: Authorization caches become request-scoped

**Why:** Three permission caches live in `static` properties whose comments assume the process dies with the request. `HasValidations` says so literally: *"reset between requests naturally by PHP-FPM"*. On a shared worker they survive, so a revoked permission keeps returning its old answer until restart. `once()` is the right tool: correctly keyed by captured variables, request-scoped, and flushed between tests.

The task also **deletes step 4 of `cache:warm`**, which pre-seeded `HasValidations::$permission_existence_cache` by reflection. That step is dead code twice over, and both facts were verified before deciding to remove it rather than port it:

1. **Its keys can never be read.** `checkUserCanDo()` reads `"{$connection}:{$table}.{$operation}"` — e.g. `sqlite:orders.select`. The warmer writes the raw `permissions.name` value, which `AuthorizationService::buildPermissionName()` builds as `"{$connection}.{$table}.{$operation}"` — e.g. `default.orders.select`. The read key always contains a colon, the written key never does, so no warmed entry has ever produced a cache hit.
2. **It writes into the wrong process anyway.** `cache:warm` is an Artisan command; a static property it fills dies with that CLI process and was never visible to an FPM worker.

**Files:**
- Modify: `Modules/Core/app/Console/WarmCacheCommand.php:11-20,29-33,52,89-98,183-213` — delete the dead warm step
- Modify: `Modules/Core/app/Models/User.php:58` — implement `HasOnceHash`
- Modify: `Modules/Core/app/Services/Authorization/AuthorizationService.php:49,393-410`
- Modify: `Modules/Core/app/Models/Concerns/HasValidations.php:49-65,221-239`
- Modify: `Modules/Core/app/Filament/Utils/HasTable.php:47,153-166`
- Test: `Modules/Core/tests/Integration/Services/Authorization/PermissionMemoizationTest.php` (create — co-located with the existing `AuthorizationServiceTest.php`)
- Test: update the call sites listed in Step 5 across 6 existing test files, two of them in `Modules/CMS`/`Modules/Core` respectively

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: deletes `AuthorizationService::resetPermissionCache()`, `HasValidations::resetPermissionExistenceCache()` and `WarmCacheCommand::warmPermissionExistenceMap()`. Adds `Modules\Core\Models\User::onceHash()`.

**Do NOT capture entity objects in `once()` — this is not a style preference, it is a correctness bug.** `Onceable::hashFromTrace()` hashes captured objects with `spl_object_hash()`, and PHP reuses an object handle once the previous object is freed. Reproduced against this repository's `vendor/`:

```
plain A -> computed-for-alice
plain B -> computed-for-alice   # WRONG: B got A's memoized value
hashed A -> computed-for-alice
hashed B -> computed-for-bob    # correct, once the class implements HasOnceHash
```

In an authorization path that is one user receiving another user's permission decision. Hence two rules for this task: capture only scalars, and where an object must be captured (`HasTable` needs the `User` to call `can()`), make the class implement `Illuminate\Contracts\Support\HasOnceHash` so the memo key becomes its identity.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Integration/Services/Authorization/PermissionMemoizationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\HasOnceHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;

it('memoizes permission lookups within one request and forgets them afterwards', function (): void {
    $permission_name = 'default.memoized_' . uniqid() . '.select';

    // Seeding style copied from Modules/Core/tests/Integration/Services/AclRoleScopedTest.php:35
    Permission::create(['name' => $permission_name, 'guard_name' => 'web']);

    // Resolve the service once, outside the query log: the memo must be cleared by the
    // request boundary, not merely by getting a different service instance.
    $service = app(AuthorizationService::class);
    $method = new ReflectionMethod($service, 'resolvePermission');

    $resolve = function () use ($service, $method, $permission_name): void {
        $method->invoke($service, $permission_name);
        $method->invoke($service, $permission_name);
    };

    DB::enableQueryLog();
    DB::flushQueryLog();

    $resolve();

    // Two calls, one query: memoized inside the request.
    expect(DB::getQueryLog())->toHaveCount(1);

    // Octane's FlushOnce listener does exactly this between operations.
    Once::flush();
    DB::flushQueryLog();

    $resolve();

    // After the boundary the lookup hits the database again: no stale worker state.
    expect(DB::getQueryLog())->toHaveCount(1);
});

it('keys memoized permission checks on the user identity instead of the object handle', function (): void {
    $user_a = User::factory()->create();
    $user_b = User::factory()->create();

    expect($user_a)->toBeInstanceOf(HasOnceHash::class)
        ->and($user_a->onceHash())->not->toBe($user_b->onceHash());
});
```

The unique suffix keeps the row from colliding with seeded permissions, and it also guarantees the first `resolvePermission()` call really hits the database. Note that `AuthorizationService` has constructor dependencies, so resolve it with `app()` as shown — the class docblock documents that usage.

The second test is deliberately narrow. The GC-driven handle collision demonstrated above cannot be triggered deterministically from a test, so the test asserts the invariant that prevents it — `User` supplies its own memo key and two users never share one — rather than trying to reproduce the race.

- [x] **Step 2: Run the test to verify it fails**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Services/Authorization/PermissionMemoizationTest.php`
Expected: both tests FAIL. The first fails on the second count assertion — the static cache survives `Once::flush()`, so the query log is empty. The second fails on `toBeInstanceOf(HasOnceHash::class)`.

- [x] **Step 3: Give `User` a stable memo key**

In `Modules/Core/app/Models/User.php`, add `HasOnceHash` to the `implements` list (import `Illuminate\Contracts\Support\HasOnceHash`) and the method below. `App\Models\User` extends this class, so it inherits the behaviour.

```php
    /**
     * Key `once()` memoization on the user identity rather than the object handle.
     *
     * PHP reuses an object handle after the previous object is freed, so
     * spl_object_hash() — Onceable's default for captured objects — can make two
     * different users collide on one memoization key.
     */
    public function onceHash(): string
    {
        $key = $this->getKey();

        return static::class . ':' . (is_scalar($key) ? (string) $key : 'unsaved');
    }
```

- [x] **Step 4: Replace the three static caches**

In `AuthorizationService`, delete the `private static array $permission_model_cache` property and the `resetPermissionCache()` method, then rewrite `resolvePermission()`:

```php
    /**
     * Resolve a Permission model instance by name, memoized for the current request.
     *
     * @param  string  $permission_name  The full permission name (e.g., 'default.orders.select')
     */
    private function resolvePermission(string $permission_name): Permission
    {
        return once(fn (): Permission => Permission::query()
            ->where('name', $permission_name)
            ->firstOrFail());
    }
```

In `HasValidations`, delete the `private static array $permission_existence_cache` property with its docblock and the `resetPermissionExistenceCache()` method, then rewrite the head of `checkUserCanDo()`:

```php
    protected static function checkUserCanDo(Model $model, string $operation): bool
    {
        $permission = $model->getTable() . '.' . $operation;

        /** @var class-string<Model> $permission_class */
        $permission_class = config('permission.models.permission');

        // Memoized for the current request only: a permission granted or revoked
        // between requests must be observed by the next one. Both captured values are
        // scalars, so the memo key is stable and carries the permission identity.
        $permission_exists = once(fn (): bool => (new $permission_class)->newQuery()
            ->where('name', $permission)
            ->exists());

        if (! $permission_exists) {
            return true;
        }
```

The rest of the method (`Auth::user()` onwards) is unchanged. The `$permission_model`, `$permission_connection` and `$cache_key` locals all disappear: the permission model is now built inside the closure, which keeps the existing connection affinity — `new $permission_class` still resolves the permission model's own connection, never the audited model's. The connection no longer belongs in a key because `once()` is already request-scoped.

**Do not capture `$permission_model` instead of `$permission_class`.** A fresh model instance is built on every call, so its `spl_object_hash` changes (or worse, silently repeats) between calls and the memoization would be either useless or wrong.

In `HasTable`, delete the `private static array $permissionCache` property and rewrite `checkPermissionCached()`:

```php
    private static function checkPermissionCached(?User $user, string $permission): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // Memoized for the current request only. Capturing $user is safe because
        // User implements HasOnceHash, so the memo key is the user identity.
        return once(fn (): bool => $user->can($permission));
    }
```

Remove every remaining reference to `self::$permissionCache` in the trait. This snippet is correct **only** because Step 3 landed; without `HasOnceHash` it would key on the object handle.

- [x] **Step 5: Delete the dead `cache:warm` step and update the call sites**

In `Modules/Core/app/Console/WarmCacheCommand.php`:
- delete the whole `warmPermissionExistenceMap()` method and its docblock (`:183-213`)
- delete the "Step 4" `try/catch` block that called it (`:89-98`)
- change `$total_steps = 4` to `3` (`:52`)
- drop item `4. Permission existence map (...)` from the class docblock (`:33`) and the word `permissions` from `$description` (`:41`)
- remove the now-unused imports `HasValidations`, `Permission`, `ReflectionProperty`. **Keep** `CoreTables` and `SchemaInspector` — the settings and cron-job steps still use them.

Then update every call site of the two deleted reset methods. This is the full inventory, verified by repository-wide search — `Once::flush()` is the exact replacement, because that is what the deleted methods were simulating:

| File | Action |
|---|---|
| `Modules/CMS/tests/Feature/Controllers/MediaControllerTest.php:249` | `AuthorizationService::resetPermissionCache();` → `Once::flush();` |
| `Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalServiceTest.php:36` | same substitution |
| `Modules/Core/tests/Integration/Actions/Grids/GetGridConfigsActionTest.php:14` | same substitution |
| `Modules/Core/tests/Integration/Services/Authorization/AuthorizationServiceTest.php:35-47` | **delete** the test `resetPermissionCache clears resolved permission models` — it is a white-box test of a deleted method, and `PermissionMemoizationTest` covers the behaviour |
| `Modules/Core/tests/Integration/Helpers/HasValidationsTest.php:171-173` | **delete** the test `exposes resetPermissionExistenceCache static method` |
| `Modules/Core/tests/Integration/Helpers/HasValidationsTest.php:176,207,269,276,289,314` | `HasValidations::resetPermissionExistenceCache();` → `Once::flush();` |
| `Modules/Core/tests/Integration/Helpers/HasValidationsBehaviorTest.php:366,381,414,424` | same substitution |
| `Modules/Core/tests/Feature/Console/WarmCacheCommandTest.php:70-96` | **delete** the test `running cache:warm twice produces the same permission existence map state` — the warmed step no longer exists |
| `Modules/Core/tests/Feature/Console/WarmCacheCommandTest.php:103,151` | delete the two remaining `resetPermissionExistenceCache()` lines outright; `cache:warm` no longer touches that cache, so flushing it proves nothing |

Add `use Illuminate\Support\Once;` to each file that gains a `Once::flush()` call, and drop imports that the deletions leave unused (`HasValidations` and `Permission` in `WarmCacheCommandTest.php`; check the others rather than assuming). Rename `HasValidationsTest.php`'s `resets permission existence cache to empty state` to `issues a fresh DB query after the request-scoped memo is flushed` so the name matches what it now asserts.

Two of these tests are the behaviour gate for this task and must keep passing untouched otherwise:
- `HasValidationsTest.php:175-204` — a second `checkUserCanDo()` for the same permission issues no new query. This proves the new memo actually memoizes.
- `HasValidationsTest.php:206-273` — two models with the same table name but different connections share one permission query, and that query never runs on the audited model's connection. This proves the key and the connection affinity survived.

- [x] **Step 6: Run the test and the authorization suites**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Services/Authorization/PermissionMemoizationTest.php`
Expected: PASS.

Run: `XDEBUG_MODE=off vendor/bin/pest --filter='Authorization|Validation|HasTable|Acl|WarmCache'`
Expected: PASS with no new failures. `Acl` is included because `AclResolverService` and the ACL scope tests exercise `AuthorizationService` heavily; `WarmCache` because Step 5 changes that command.

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/CMS/tests/Feature/Controllers/MediaControllerTest.php`
Expected: PASS — this is the one call site outside `Modules/Core`.

- [x] **Step 7: Commit**

Three repositories this time, because one test call site lives in `Modules/CMS`.

```bash
cd Modules/Core
git add app/Services/Authorization/AuthorizationService.php app/Models/Concerns/HasValidations.php app/Filament/Utils/HasTable.php app/Models/User.php app/Console/WarmCacheCommand.php tests/Integration/Services/Authorization/PermissionMemoizationTest.php tests/Integration/Services/Authorization/AuthorizationServiceTest.php tests/Integration/Helpers/HasValidationsTest.php tests/Integration/Helpers/HasValidationsBehaviorTest.php tests/Feature/ApplicationContent/ApplicationContentRetrievalServiceTest.php tests/Integration/Actions/Grids/GetGridConfigsActionTest.php tests/Feature/Console/WarmCacheCommandTest.php
git commit -m "refactor(core): scope permission memoization to the request instead of the process"
cd ../CMS
git add tests/Feature/Controllers/MediaControllerTest.php
git commit -m "test(cms): flush once() memoization instead of the removed permission cache reset"
cd ../..
git add Modules/Core Modules/CMS
git commit -m "chore(modules): bump Core and CMS for request-scoped permission caches"
```

---

### Task 4: Bound the closure-table depth cache

**Why:** `HasClosureTable::$depth_cache` is a static array keyed per node, already backed by a 24-hour `Cache::remember()`. The static layer is only an L1, but it grows with every node any request touches and is cleared only for the single node being invalidated. On a shared worker that is unbounded growth. Converting the L1 to `once()` keeps the persistent L2 and bounds memory to one request.

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasClosureTable.php:24-27,155-180,405-415`
- Test: `Modules/Core/tests/Integration/Models/ClosureTableDepthMemoizationTest.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: no public API change; `depth()` still returns `int`.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Integration/Models/ClosureTableDepthMemoizationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;

it('memoizes depth within a request and holds no process-level state', function (): void {
    $node = closure_table_root_fixture();

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($node->depth())->toBe(0);
    expect($node->depth())->toBe(0);

    $queries_in_request = count(DB::getQueryLog());

    Once::flush();

    expect($node->depth())->toBe(0);
    expect($queries_in_request)->toBeLessThanOrEqual(1);

    expect((new ReflectionClass($node::class))->getStaticProperties())
        ->not->toHaveKey('depth_cache');
});
```

Implement `closure_table_root_fixture()` in the same file, returning a persisted root node of whichever closure-table model the existing closure-table tests use. Find that test first (search `Modules/Core/tests` for `rebuildClosure` or `depth(`) and reuse its model and setup; do not add a migration.

- [x] **Step 2: Run the test to verify it fails**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Models/ClosureTableDepthMemoizationTest.php`
Expected: FAIL on the final assertion — `depth_cache` is still a static property.

- [x] **Step 3: Replace the static L1 with `once()`**

Delete `private static array $depth_cache = [];` and its docblock. Rewrite the memoised section of `depth()`:

```php
        $cache_key = $this->depthCacheKey();
        $closure_table = $this->getClosureTable();

        // Request-scoped L1 over the 24h persistent L2: a long-lived worker must not
        // accumulate one entry per visited node.
        return once(fn (): int => (int) Cache::remember(
            $cache_key,
            now()->addHours(24),
            fn () => $this->getConnection()->table($closure_table)
                ->where($this->qualifyTreeColumn('ancestor_id', $closure_table), $this->id)
                ->where($this->qualifyTreeColumn('descendant_id', $closure_table), $this->id)
                ->value($this->qualifyTreeColumn('depth', $closure_table)) ?? 0,
        ));
```

Preserve whatever early returns precede this block in the current `depth()` implementation. In the invalidation method around lines 405-415, drop the `unset(self::$depth_cache[$cache_key]);` line and keep `Cache::forget($cache_key);`.

Note: `once()` keys on `$cache_key` **and** `$closure_table`, both scalars captured by the closure, so distinct nodes get distinct entries.

- [x] **Step 4: Run the test and the tree suites**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Models/ClosureTableDepthMemoizationTest.php`
Expected: PASS.

Run: `XDEBUG_MODE=off vendor/bin/pest --filter='Closure|Tree|Categor'`
Expected: PASS.

- [x] **Step 5: Commit**

```bash
cd Modules/Core
git add app/Models/Concerns/HasClosureTable.php tests/Integration/Models/ClosureTableDepthMemoizationTest.php
git commit -m "refactor(core): bound the closure-table depth cache to the request"
cd ../..
git add Modules/Core
git commit -m "chore(modules): bump Core for bounded depth cache"
```

---

### Task 5: Settings freshness — scoped resolver and per-request config overlay

**Why:** Two coupled problems.

1. `CoreServiceProvider:466` binds `PerModelSettingResolver` as a **singleton**. It holds an L1 (`$loaded_groups`, `$name_index`) over a persistent L2 (`Cache::forever`). `SettingsCacheCoordinator` invalidates both correctly — but only in the process that handled the write. Any other worker keeps its L1 and serves stale settings until restart.
2. `CoreServiceProvider:117-118` applies `DatabaseConfigOverlay` in `boot()`, once per process. A worker would therefore freeze database-backed config for its whole lifetime.

Making the resolver `scoped()` fixes (1) at the cost of one cache read per group per request. Under PHP-FPM `scoped()` behaves like a singleton for the request's lifetime, so this is behaviour-preserving today. Moving the overlay into a middleware fixes (2) and is correct in both runtimes.

**Files:**
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php:113-119,466`
- Create: `Modules/Core/app/Http/Middleware/ApplyDatabaseSettingsOverlay.php`
- Test: `Modules/Core/tests/Integration/Settings/DatabaseSettingsOverlayTest.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay::handle(Request $request, Closure $next): Response`.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Integration/Settings/DatabaseSettingsOverlayTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Symfony\Component\HttpFoundation\Response;

it('overlays dotted settings written after boot', function (): void {
    config()->set('core.demo_flag', 'boot-value');

    Setting::query()->create([
        'name' => 'core.demo_flag',
        'value' => 'runtime-value',
        'group_name' => 'core',
    ]);

    app(PerModelSettingResolver::class)->flush();

    app(ApplyDatabaseSettingsOverlay::class)->handle(
        Request::create('/app/test', 'GET'),
        static fn (): Response => new Response(),
    );

    expect(config('core.demo_flag'))->toBe('runtime-value');
});

it('binds the settings resolver per request, not per process', function (): void {
    expect(app()->isShared(PerModelSettingResolver::class))->toBeFalse();
});
```

Adjust the `Setting::create()` payload to the model's actual required columns — check an existing settings test under `Modules/Core/tests/Integration/` for the canonical shape. Note the overlay only applies names containing a dot (`DatabaseConfigOverlay::shouldOverlay()`), which is why the fixture name is `core.demo_flag`.

- [x] **Step 2: Run the test to verify it fails**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Settings/DatabaseSettingsOverlayTest.php`
Expected: FAIL — the middleware class does not exist.

- [x] **Step 3: Create the middleware**

Create `Modules/Core/app/Http/Middleware/ApplyDatabaseSettingsOverlay.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copies dotted database settings onto the config repository for this request.
 *
 * This runs per request rather than at boot because a long-lived worker boots once:
 * an overlay applied at boot would freeze database-backed config for the whole
 * process lifetime.
 */
final readonly class ApplyDatabaseSettingsOverlay
{
    public function __construct(
        private DatabaseConfigOverlay $overlay,
        private PerModelSettingResolver $settings,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->overlay->applyFromDatabase($this->settings);

        return $next($request);
    }
}
```

- [x] **Step 4: Rebind the resolver and register the middleware**

In `CoreServiceProvider`, change line 466 to:

```php
        $this->app->scoped(PerModelSettingResolver::class);
```

Replace the unconditional boot-time overlay (lines 117-118) with a console-only call, so Artisan behaviour is unchanged:

```php
        if ($this->app->runningInConsole()) {
            $this->app->make(DatabaseConfigOverlay::class)
                ->applyFromDatabase($this->app->make(PerModelSettingResolver::class));
        }
```

Register `ApplyDatabaseSettingsOverlay` in `registerMiddlewares()`, prepending it to the `web` and `api` groups. Follow the exact registration style already used in that method for `LocalizationMiddleware` — read it first and match it; do not introduce a different registration mechanism.

- [x] **Step 5: Run the tests**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Integration/Settings/`
Expected: PASS.

Run: `XDEBUG_MODE=off vendor/bin/pest --testsuite=Integration`
Expected: PASS. Settings are read very widely (`HasApprovals`, `HasVersions`, `HasTranslations`, `SoftDeletes`, `ListRequestData`), so this suite is the real gate for this task. Report any failure rather than adapting tests.

- [x] **Step 6: Commit**

```bash
cd Modules/Core
git add app/Providers/CoreServiceProvider.php app/Http/Middleware/ApplyDatabaseSettingsOverlay.php tests/Integration/Settings/DatabaseSettingsOverlayTest.php
git commit -m "fix(core): apply database settings per request and scope the settings resolver"
cd ../..
git add Modules/Core
git commit -m "chore(modules): bump Core for per-request settings overlay"
```

---

### Task 6: Enable the Spatie permission reset listener

**Why:** `Modules/Core/config/permission.php:111` sets `'register_octane_reset_listener' => false`. Spatie's `PermissionRegistrar` keeps permissions and roles in memory; without that listener the cache is never cleared between requests on a shared worker, which would undo Task 3. The setting is inert while Octane is absent — Spatie only registers the listener when Octane's events exist — so flipping it now is free and prevents a forgotten step later.

**Files:**
- Modify: `Modules/Core/config/permission.php:111`
- Test: `Modules/Core/tests/Unit/Config/LongLivedWorkerConfigTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed later.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Unit/Config/LongLivedWorkerConfigTest.php`:

```php
<?php

declare(strict_types=1);

it('registers the Spatie permission reset listener for long-lived workers', function (): void {
    expect(config('permission.register_octane_reset_listener'))->toBeTrue();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Unit/Config/LongLivedWorkerConfigTest.php`
Expected: FAIL — got `false`.

- [x] **Step 3: Flip the flag**

In `Modules/Core/config/permission.php`:

```php
    'register_octane_reset_listener' => true,
```

- [x] **Step 4: Run the test and the permission suites**

Run: `XDEBUG_MODE=off vendor/bin/pest Modules/Core/tests/Unit/Config/LongLivedWorkerConfigTest.php`
Expected: PASS.

Run: `XDEBUG_MODE=off vendor/bin/pest --filter='Permission|Acl|Polic'`
Expected: PASS.

- [x] **Step 5: Commit**

```bash
cd Modules/Core
git add config/permission.php tests/Unit/Config/LongLivedWorkerConfigTest.php
git commit -m "chore(core): enable the Spatie permission reset listener for long-lived workers"
cd ../..
git add Modules/Core
git commit -m "chore(modules): bump Core for Spatie reset listener"
```

---

### Task 7: Record the rules

**Why:** The single most valuable artefact of this work is the rule that prevents its own regression: state belonging to a request must not live in a `static` property. The guardrail test from Tasks 1–2 covers the two process-wide primitives, but nothing enforces the state rule, so it has to be written down where developers look.

**Files:**
- Modify: `Modules/Core/docs/rag/PERFORMANCE_TOOLKIT.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: the outcomes of Tasks 1–6.
- Produces: documentation only; no code.

- [x] **Step 1: Document the state rule in the module toolkit**

Add a section to `Modules/Core/docs/rag/PERFORMANCE_TOOLKIT.md` titled "Process-level vs request-level caches", containing:

- The rule: per-request state goes in `once()`, `Cache::memo()` or a `scoped()` binding — never a `static` property. Static is reserved for values derived from code or database schema, which a deploy invalidates.
- The verbatim "Caches that must stay warm" list from this plan, as the set that is intentionally process-level.
- The list of caches Tasks 3–5 converted, so a future reader does not "optimise" them back into statics.
- The note that `once()` hashes the closure's captured variables, so keys must be scalars: capturing an entity object keys on `spl_object_hash`, which is not stable across requests.

Match the file's existing heading depth and tone; read it before writing.

- [x] **Step 2: Document the Grid deprecation in the README**

Add a short note to `README.md` recording that the `app/crud/grid/*` surface is deprecated in favour of facets on the CRUD routes (`Modules\Core\Services\Crud\CrudService`), pointing at the `@deprecated` tags from Task 1. Place it wherever the README already describes the HTTP surfaces; do not create a new top-level section if a suitable one exists.

Do **not** add an Octane section: Octane is not installed. The appendix in this plan is the reference for that.

- [x] **Step 3: Verify the documentation builds and links resolve**

Run: `XDEBUG_MODE=off vendor/bin/pest --filter='Doc|Rag'`
Expected: PASS — some suites validate documentation frontmatter and cross-references. If none match, state that in the report rather than inventing a check.

- [x] **Step 4: Run the full suite and the linters**

Run: `XDEBUG_MODE=off vendor/bin/pest --compact`
Expected: PASS.

Run: `composer test:lint`
Expected: PASS (Pint and Rector clean).

- [x] **Step 5: Commit**

```bash
cd Modules/Core
git add docs/rag/PERFORMANCE_TOOLKIT.md
git commit -m "docs(core): record process-level vs request-level cache rules"
cd ../..
git add Modules/Core README.md
git commit -m "docs: note the grid deprecation and bump Core for cache documentation"
```

---

## Appendix: adopting Octane later

Not a task. These steps were reviewed against `laravel/octane` 2.x and are recorded so the adoption decision does not have to be re-researched. **Server: FrankenPHP, not Swoole** — after Tasks 1–2 the remaining `pcntl`/fork code is CLI-only, but FrankenPHP avoids a coroutine scheduler entirely, which keeps that code harmless.

1. `composer require laravel/octane` and `php artisan octane:install --server=frankenphp`.
2. In `config/octane.php`, set `'server' => env('OCTANE_SERVER', 'frankenphp')` and extend the warm list with the bindings whose process-level caches are the point of the exercise:

```php
    'warm' => [
        ...Octane::defaultServicesToWarm(),
        \Modules\Core\Inspector\SchemaInspector::class,
        \Modules\Core\Inspector\ModelMetadataRegistry::class,
        \Modules\Core\Services\DynamicContentsService::class,
    ],
```

3. Leave `'flush' => []`. Adding entries there discards the warm caches that justify Octane.
4. Add one listener on `OperationTerminated` for the two safety counters that are `try/finally`-guarded today and would only leak after a fatal error: `RetrievedSelectGuard::$suppressed` and `ReadonlyModel::$read_only_guards_bypass_depth`. Both need a new `public static function flush(): void`. Do **not** reset schema, reflection or metadata caches there.
5. `php artisan config:cache && php artisan route:cache` — both are currently uncached, and routes especially are a measurable per-request cost.
6. Start with `--workers=4 --max-requests=500`; recycling workers bounds any leak this plan did not find.
7. Re-measure the endpoints from the original slow log twice each: the second pass is the warm one, and the difference between passes is what Octane actually bought.
8. Refine the worker probe in `ApplicationContentDeadlineExecutor::longLivedWorker()`. Task 2 detects a worker with `class_exists(\Laravel\Octane\Octane::class)`, which is accurate only while Octane is absent: once the package is installed, that check is true even for Artisan and queue processes, so the interrupting deadline would never be used again. At adoption, switch it to a runtime signal — Octane sets `LARAVEL_OCTANE` in the server environment — while keeping the Swoole extension checks. This was raised as a Minor finding in Task 2's review and deliberately left for adoption time, because writing the runtime check now would leave untestable code in the tree.

## Deliberately deferred

Verified as real but not worth a task now. Recorded so the next audit does not re-litigate them.

| Finding | Why deferred |
|---|---|
| `AuthorizationService::resolveUser()` calls `Auth::login($anonymous)` without restoring the guard | Not a cross-request leak (`FlushAuthenticationState` handles it). Cleaning up the in-request guard mutation is a behavioural refactor of the ACL path and needs its own spec. |
| `getAclFilters()` / `hasUnrestrictedAccess()` read `Auth::user()` while `checkPermission()` resolves the user from the request | The divergence exists under PHP-FPM today, so it is not a worker-specific regression. |
| `DocumentationAgent::$shared_memory_stores` | Keyed by `DocumentationIndexProfile`, so bounded by the enum — not a leak. The real cost is RAM per worker when `ai.features.faq.vector_store === 'memory'`: a sizing decision, not a bug. |
| `spatie/fork` in `ParallelTaskRunner`, `pcntl` in `BatchSeeder`, `setUser()` in `EndpointProfiler` | CLI-only, and Tasks 1–2's guardrail keeps them there. |
| `ImportIdMap`, `PipelineContext`, `ActiveVersionSet` | `PipelineContext` and `ActiveVersionSet` are already `try/finally`-guarded or `scoped()`; `ImportIdMap` runs under `cms:import` (console). |
| `CrudToolProvider`, `InAppAssistanceService`, `GraphToolGateway` inject `Request` in their constructors | All bound with `bind()`, so a fresh instance per resolution — correct today. If any is ever promoted to `singleton()` it becomes critical. Worth a comment at each binding, not a task. |
| `CommandListenerProvider`, `SearchServiceProvider`, `ElasticsearchServiceProvider` | Verified **not loaded** at runtime via `getLoadedProviders()`. Dead code, unrelated to workers. |
| Elasticsearch / Typesense singleton clients | Long-lived sockets need explicit connect and read timeouts before running under workers for long. Separate reliability task. |

## Self-review

- **Coverage:** every CRITICA/ALTA audit finding maps to a task or to the deferred table with a stated reason. The two findings I could not reproduce (locale leak, config leak) sit in the verified-baseline table with the upstream test that disproves them.
- **Consistency:** `http_reachable_sources()` is defined once (Task 1) and reused (Task 2); the plan says so explicitly in both Interfaces blocks. The `once()` key-stability caveat appears in Tasks 3 and 4 and in Task 7's documentation.
- **Known soft spots:** Tasks 3, 4 and 5 depend on fixtures the implementer must locate rather than invent; each step names the directory and the search term to use. Task 2 Step 3 contains the plan's only open design decision (where the signal branch should live) and requires the implementer to state and justify its choice.

## Outcome — executed 2026-08-27

All six implemented tasks landed on `master` (Task 7 of the original numbering, the Octane
install, stays deferred; documentation was folded into the last commit block). Where
execution departed from the plan, the reason is recorded here rather than edited into the
task above, so the plan still reads as it was reviewed.

| Task | Commit (Core, unless noted) | Departure from plan |
|---|---|---|
| 1 — grid | `689472e` | The characterization test showed grid actions returning an empty payload, as suspected. On the user's decision the surface was marked `@deprecated` (Funnels already survive as facets) instead of being made to work. |
| 2 — deadlines | `e8d6e7a` + AI `ApplicationContentSignalDeadline` | The signal branch moved into its own class under the CLI-allowed path, with a soft deadline for worker environments. The worker probe is `class_exists(Octane::class)`, which must become a runtime `LARAVEL_OCTANE` check at adoption — see appendix item 8. |
| 3 — permission caches | `b56fc32`, `2ca22a4`, `589af5d` | The plan claimed `resetPermissionExistenceCache()` had no callers; it had 18, including `WarmCacheCommand` writing into the static through reflection with keys that never matched a read. That dead warm step was deleted on the user's instruction. The memo closure then had to be owned by a `final` class (`Authorization\PermissionExistenceMemo`), because Onceable keys on the closure's *called* class and a trait closure would give one memo per composing model. |
| 4 — depth cache | `d6bb51b` | `Cache::memo()`, not `once()`. `invalidateDepthCache()` needs to drop a single key, which `once()` cannot do. |
| 5 — settings overlay | `96cc5d7`, `afeb5ec` | Also scoped `AuthorizationService` (Finding 3: `once()` binds to `$this`, and a transient service memoized nothing). The console keeps its boot-time overlay — Artisan has no middleware pipeline. Registered with `pushMiddlewareToGroup()`; the four neighbouring `$router->middleware()` calls were found to register **nothing** and carry a `FIXME` now, since fixing them changes request behaviour. |
| 6 — Spatie listener | `590f48e` | None. |
| Docs | `10e6d61` + root `README.md` | None. |

**Regression found and fixed during execution:** `SeedReconcilerTest` pins a per-row query
budget. The old static permission cache stayed warm for the whole test *process*, so the
existence query was invisible; `once()` resets per test, which made the first `reconcile()`
of a request pay it. The test now warms the memo on a throwaway definition before
measuring (`afeb5ec`) — the budget it guards is unchanged.

**Verification:** Unit, Integration and Feature suites pass when run per-suite, which is
how `composer.json` defines them. Running all three in one process exhausts the 2 GB limit
inside `dg/bypass-finals`; that predates this work. `composer test:lint` is red on
`app/Models/User.php` (last touched 2026-07-07) and on 525 files for one Rector rule —
both pre-existing. Every file this plan touched passes Pint individually.
