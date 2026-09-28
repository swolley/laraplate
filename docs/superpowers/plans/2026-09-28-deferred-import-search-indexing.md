# Deferred Import Search Indexing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `{module}:import` stops indexing record by record: `searchable()` calls are recorded as keys and indexed through the existing bulk path every `--index-batch` distinct models and at the end.

**Architecture:** A Core singleton `DeferredSearchIndexing` captures `Searchable::queueMakeSearchable()` while `run()` is active, deduplicates `(class, scout key)`, and flushes by reloading through `makeAllSearchableQuery()` and calling the new `ISearchableModel::makeSearchableInBulk()` (bulk pre-process + adaptive engine writes). Threshold flushes go through `Connection::afterCommit()` so they never run inside an open transaction. `AbstractImportCommand` wraps the import in `run()`; `--no-search` and dry-run use discard mode. AI's single-locale re-embed listener joins the deferral.

**Tech Stack:** PHP 8.5, Laravel 12, Scout + Elastic Scout Driver Plus, Pest, nwidart modules (`Modules/Core`, `Modules/AI`, `Modules/CMS`).

**Spec:** `docs/superpowers/specs/2026-09-28-deferred-import-search-indexing-design.md`

## Global Constraints

- Every PHP file: `declare(strict_types=1);`, explicit parameter and return types, braces on every control structure.
- Tests stay in the owning module's `tests/`; no classes declared inside test files, stubs go in `Modules/{Module}/tests/Stubs/`.
- Run Pint from the laraplate root on an explicit file list: `vendor/bin/pint --format agent <files>` (never inside a module, never with an empty list).
- Core, AI and CMS are submodules: commit inside each submodule, then bump the parent. Commit only when the user asks.
- No new dependencies, no new base folders.
- Removals (`unsearchable()`) are not deferred.

---

### Task 1: Core `DeferredSearchIndexing` and the bulk entry point

**Files:**
- Create: `Modules/Core/app/Search/DeferredSearchIndexing.php`
- Modify: `Modules/Core/app/Contracts/ISearchableModel.php` (add `makeSearchableInBulk`)
- Modify: `Modules/Core/app/Search/Traits/Searchable.php` (`queueMakeSearchable` hook, `makeSearchableInBulk`)
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php` (singleton)
- Create: `Modules/Core/tests/Stubs/Search/DeferredSearchableStubModel.php`
- Test: `Modules/Core/tests/Integration/Search/DeferredSearchIndexingTest.php`

**Interfaces:**
- Produces: `DeferredSearchIndexing::run(callable $callback, int $batchSize, bool $discard = false): mixed`, `defer(iterable $models): bool`, `isDeferring(): bool`, `flush(): void`; `ISearchableModel::makeSearchableInBulk(Collection $models): void`; test stub `DeferredSearchableStubModel` with `TABLE`, `static ?RecordingSearchEngineStub $engine`, `createTable()`, `dropTable()`.

- [x] **Step 1: Write the stub model**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Traits\Searchable;

/**
 * Persisted searchable model whose engine is a shared recording double, so a
 * model reloaded by key (as a deferred flush does) still writes to the double.
 */
final class DeferredSearchableStubModel extends Model implements ISearchableModel
{
    use Searchable;

    public const string TABLE = 'core_deferred_search_stub_rows';

    public static ?RecordingSearchEngineStub $engine = null;

    public $timestamps = false;

    protected $table = self::TABLE;

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    public static function dropTable(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    public function searchableAs(): string
    {
        return 'core_deferred_stub';
    }

    public function searchableUsing(): RecordingSearchEngineStub
    {
        return self::$engine ??= new RecordingSearchEngineStub;
    }
}
```

- [x] **Step 2: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModelsRequireIndexing;
use Modules\Core\Search\DeferredSearchIndexing;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\RecordingSearchEngineStub;

beforeEach(function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
});

afterEach(function (): void {
    DeferredSearchableStubModel::dropTable();
    DeferredSearchableStubModel::$engine = null;
});

function saveDeferredStub(string $name): DeferredSearchableStubModel
{
    return DeferredSearchableStubModel::query()->create(['name' => $name]);
}

it('keeps the real-time path outside a run', function (): void {
    saveDeferredStub('live');

    Event::assertDispatched(ModelRequiresIndexing::class);
    expect(app(DeferredSearchIndexing::class)->defer([]))->toBeFalse();
});

it('indexes every record saved during a run once, in one bulk flush at the end', function (): void {
    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('a')->update(['name' => 'a2']);
        saveDeferredStub('b');

        expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
    }, batchSize: 10);

    Event::assertNotDispatched(ModelRequiresIndexing::class);
    Event::assertDispatchedTimes(ModelsRequireIndexing::class, 1);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2]);
});

it('flushes every batch size distinct records', function (): void {
    app(DeferredSearchIndexing::class)->run(function (): void {
        foreach (range(1, 5) as $index) {
            saveDeferredStub("row-{$index}");
        }
    }, batchSize: 2);

    Event::assertDispatchedTimes(ModelsRequireIndexing::class, 3);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 2, 1]);
});

it('waits for the open transaction to commit before a threshold flush', function (): void {
    $indexing = app(DeferredSearchIndexing::class);

    $indexing->run(function () use ($indexing): void {
        DB::transaction(function () use ($indexing): void {
            DB::table(DeferredSearchableStubModel::TABLE)->insert([['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);
            $indexing->defer(DeferredSearchableStubModel::query()->get());

            expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
        });

        expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 1]);
    }, batchSize: 2);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 1]);
});

it('skips recorded records whose rows are gone at flush time', function (): void {
    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('kept');
        saveDeferredStub('gone');
        DB::table(DeferredSearchableStubModel::TABLE)->where('name', 'gone')->delete();
    }, batchSize: 10);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
});

it('indexes and embeds nothing in discard mode', function (): void {
    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('a');
    }, batchSize: 1, discard: true);

    Event::assertNotDispatched(ModelRequiresIndexing::class);
    Event::assertNotDispatched(ModelsRequireIndexing::class);
    expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
});

it('flushes what was recorded before the callback failed, then rethrows', function (): void {
    $indexing = app(DeferredSearchIndexing::class);

    expect(fn (): mixed => $indexing->run(function (): void {
        saveDeferredStub('committed');

        throw new RuntimeException('source unavailable');
    }, batchSize: 10))->toThrow(RuntimeException::class, 'source unavailable');

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1])
        ->and($indexing->isDeferring())->toBeFalse();
});

it('lets a nested run join the outer one', function (): void {
    $indexing = app(DeferredSearchIndexing::class);

    $indexing->run(function () use ($indexing): void {
        $indexing->run(fn (): DeferredSearchableStubModel => saveDeferredStub('inner'), batchSize: 10);

        expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
    }, batchSize: 10);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Integration/Search/DeferredSearchIndexingTest.php`
Expected: FAIL, class `Modules\Core\Search\DeferredSearchIndexing` not found.

- [x] **Step 4: Implement `DeferredSearchIndexing`**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Throwable;

/**
 * Defers search indexing for the duration of a bulk operation such as an
 * import. Every `searchable()` call made while {@see run()} is active is
 * recorded as a (model class, scout key) pair instead of being indexed one
 * model at a time. The recorded models are indexed through the bulk path, one
 * batched pre-process pass (embeddings) and adaptive engine writes, every
 * `batchSize` distinct models and once at the end. Only keys are held in
 * memory: the models are reloaded when flushed, and rows deleted in the
 * meantime are skipped.
 *
 * A threshold flush never runs inside an open database transaction: it waits
 * for the commit, and a rollback leaves the keys pending for the next flush.
 */
final class DeferredSearchIndexing
{
    private bool $active = false;

    private bool $discard = false;

    private bool $flushing = false;

    private int $batchSize = 1;

    /**
     * Distinct pending scout keys, grouped by model class.
     *
     * @var array<class-string<Model&ISearchableModel>, array<int|string, true>>
     */
    private array $pending = [];

    private int $pendingCount = 0;

    /**
     * Run the callback with indexing deferred, then flush what it left pending.
     * In discard mode the captured calls are dropped: nothing is indexed and
     * nothing is embedded. A nested call joins the outer run.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(callable $callback, int $batchSize, bool $discard = false): mixed
    {
        if ($this->active) {
            return $callback();
        }

        $this->active = true;
        $this->discard = $discard;
        $this->batchSize = max(1, $batchSize);

        try {
            $result = $callback();
            $this->flush();

            return $result;
        } catch (Throwable $exception) {
            // Graphs committed before the failure are real rows: index them,
            // without letting a flush error hide the one that stopped the run.
            $this->flushQuietly();

            throw $exception;
        } finally {
            $this->reset();
        }
    }

    public function isDeferring(): bool
    {
        return $this->active && ! $this->flushing;
    }

    /**
     * Take over a `searchable()` call. Returns false when nothing is being
     * deferred, so the caller indexes the models as usual.
     *
     * @param  iterable<Model&ISearchableModel>  $models
     */
    public function defer(iterable $models): bool
    {
        if (! $this->isDeferring()) {
            return false;
        }

        if ($this->discard) {
            return true;
        }

        $last = null;

        foreach ($models as $model) {
            $key = $model->getScoutKey();

            if (! isset($this->pending[$model::class][$key])) {
                $this->pending[$model::class][$key] = true;
                $this->pendingCount++;
            }

            $last = $model;
        }

        if ($last !== null && $this->pendingCount >= $this->batchSize) {
            // Runs at once outside a transaction and after the commit inside one.
            $last->getConnection()->afterCommit(fn () => $this->flushWhenFull());
        }

        return true;
    }

    /**
     * Index every recorded model now, in chunks of the batch size per class.
     */
    public function flush(): void
    {
        if ($this->flushing || $this->pending === []) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];
        $this->pendingCount = 0;
        $this->flushing = true;

        try {
            foreach ($pending as $class => $keys) {
                foreach (array_chunk(array_keys($keys), $this->batchSize) as $chunk) {
                    $this->indexChunk($class, $chunk);
                }
            }
        } finally {
            $this->flushing = false;
        }
    }

    private function flushWhenFull(): void
    {
        if ($this->pendingCount >= $this->batchSize) {
            $this->flush();
        }
    }

    private function flushQuietly(): void
    {
        try {
            $this->flush();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Reload one chunk through Scout's import query (the same scopes
     * `scout:import` drops) and index it through the bulk path.
     *
     * @param  class-string<Model&ISearchableModel>  $class
     * @param  list<int|string>  $keys
     */
    private function indexChunk(string $class, array $keys): void
    {
        $instance = new $class;

        $models = $class::makeAllSearchableQuery()
            ->whereIn($instance->qualifyColumn($instance->getScoutKeyName()), $keys)
            ->get()
            ->filter(static fn (Model $model): bool => $model->shouldBeSearchable())
            ->values();

        if ($models->isEmpty()) {
            return;
        }

        $instance->makeSearchableInBulk($models);
    }

    private function reset(): void
    {
        $this->active = false;
        $this->discard = false;
        $this->batchSize = 1;
        $this->pending = [];
        $this->pendingCount = 0;
    }
}
```

- [x] **Step 5: Add `makeSearchableInBulk` to `ISearchableModel`**

```php
    /**
     * Index the given models of this class through the bulk path now.
     *
     * @param  Collection<int, static>  $models
     */
    public function makeSearchableInBulk(Collection $models): void;
```

with `use Illuminate\Support\Collection;` added to the interface imports.

- [x] **Step 6: Hook the trait**

In `Searchable::queueMakeSearchable()`, first statement:

```php
        // A bulk run (an import) records the models and indexes them in bulk later.
        if (app(DeferredSearchIndexing::class)->defer(is_iterable($models) ? $models : [$models])) {
            return;
        }
```

New public method, next to `makeSearchableUsing()`:

```php
    /**
     * Index the given models of this class through the bulk path now: one
     * batched pre-process pass (embeddings) and adaptive engine writes,
     * whatever `scout.queue` says. {@see DeferredSearchIndexing} flushes here.
     *
     * @param  Collection<int, static>  $models
     */
    public function makeSearchableInBulk(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->degradeWhenSearchEngineUnreachable(
            'ensure indexes',
            fn (): mixed => $this->ensureIndexesForModels($models),
        );

        $this->bulkQueueMakeSearchable($models->values());
    }
```

Add `use Modules\Core\Search\DeferredSearchIndexing;`.

- [x] **Step 7: Register the singleton** in `CoreServiceProvider::register()`, after `EntityImporterRegistry`:

```php
        // Singleton so the import command and the Searchable hook share one run.
        $this->app->singleton(DeferredSearchIndexing::class);
```

- [x] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/Core/tests/Integration/Search/DeferredSearchIndexingTest.php Modules/Core/tests/Integration/Search/BulkQueueMakeSearchableTest.php`
Expected: PASS.

- [x] **Step 9: Pint**

Run: `vendor/bin/pint --format agent Modules/Core/app/Search/DeferredSearchIndexing.php Modules/Core/app/Contracts/ISearchableModel.php Modules/Core/app/Search/Traits/Searchable.php Modules/Core/app/Providers/CoreServiceProvider.php Modules/Core/tests/Stubs/Search/DeferredSearchableStubModel.php Modules/Core/tests/Integration/Search/DeferredSearchIndexingTest.php`

### Task 2: `AbstractImportCommand` runs the import deferred

**Files:**
- Modify: `Modules/Core/app/Console/AbstractImportCommand.php`
- Create: `Modules/Core/tests/Stubs/Import/FakeSearchableBulkImporter.php`
- Test: `Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php`

**Interfaces:**
- Consumes: `DeferredSearchIndexing::run()`, `DeferredSearchableStubModel` (Task 1).
- Produces: option `--index-batch=`; `--no-search` / `--dry-run` run in discard mode.

- [x] **Step 1: Write the fake importer**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Importer that saves searchable models through Eloquent, so the Scout
 * observer fires exactly as it does for a real import.
 */
final readonly class FakeSearchableBulkImporter implements BulkImporterInterface
{
    public function __construct(
        public string $records = '0',
    ) {}

    public function import(?OutputInterface $output = null): int
    {
        $records = max(0, (int) $this->records);

        for ($index = 0; $index < $records; $index++) {
            DeferredSearchableStubModel::query()->create(['name' => "record-{$index}"]);
        }

        return $records;
    }
}
```

- [x] **Step 2: Write the failing tests** (add `'index-batch'` to the option list of the definition test, then append)

```php
it('indexes imported records in bulk flushes of --index-batch', function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    try {
        $command = new TestImportCommand(
            app(BulkImportRunner::class),
            new FakeBulkImporterResolver(app()),
            new FakeImportPluginDiscovery,
        );
        [$status] = runCoreImportCommand($command, [
            '--importer' => FakeSearchableBulkImporter::class,
            '--arg' => ['records=5'],
            '--index-batch' => 2,
        ]);

        expect($status)->toBe(TestImportCommand::SUCCESS)
            ->and(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 2, 1]);
        Event::assertNotDispatched(ModelRequiresIndexing::class);
    } finally {
        DeferredSearchableStubModel::dropTable();
        DeferredSearchableStubModel::$engine = null;
    }
});

it('indexes and embeds nothing with --no-search', function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    try {
        $command = new TestImportCommand(
            app(BulkImportRunner::class),
            new FakeBulkImporterResolver(app()),
            new FakeImportPluginDiscovery,
        );
        [$status] = runCoreImportCommand($command, [
            '--importer' => FakeSearchableBulkImporter::class,
            '--arg' => ['records=3'],
            '--no-search' => true,
        ]);

        expect($status)->toBe(TestImportCommand::SUCCESS)
            ->and(DeferredSearchableStubModel::query()->count())->toBe(3)
            ->and(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
        Event::assertNotDispatched(ModelRequiresIndexing::class);
        Event::assertNotDispatched(ModelsRequireIndexing::class);
    } finally {
        DeferredSearchableStubModel::dropTable();
        DeferredSearchableStubModel::$engine = null;
    }
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php`
Expected: FAIL, option `--index-batch` does not exist; `--no-search` test sees `ModelRequiresIndexing` dispatched.

- [x] **Step 4: Implement**

`handle()` receives the service by method injection (the constructor, shared with every module command, stays unchanged):

```php
    final public function handle(DeferredSearchIndexing $deferredIndexing): int
```

Replace the `--no-search` block and the runner call:

```php
        $skip_search = (bool) $this->option('no-search') || $dry_run;

        if ($skip_search) {
            config(['scout.driver' => 'null']);
            $this->warn('Search indexing disabled for this import.');
        }

        $connection = $importer instanceof ConnectionAwareBulkImporterInterface
            ? $importer->importConnection()
            : null;
        $imported = $deferredIndexing->run(
            fn (): int => $this->runner->run(
                $dry_run,
                fn (): int => $importer->import($this->output),
                $connection,
            ),
            $this->resolveIndexBatch(),
            discard: $skip_search,
        );
```

Option, after `no-search`:

```php
            ['index-batch', null, InputOption::VALUE_OPTIONAL, 'Records indexed per bulk search flush (default: scout.chunk.searchable)'],
```

Helper:

```php
    private function resolveIndexBatch(): int
    {
        $batch = $this->option('index-batch');

        return $batch === null || $batch === ''
            ? max(1, (int) config('scout.chunk.searchable', 500))
            : max(1, (int) $batch);
    }
```

- [x] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php Modules/CMS/tests/Feature/Import/ImportCommandDefinitionTest.php Modules/CMS/tests/Feature/Import/ImportCommandTest.php Modules/ERP/tests/Feature/Import/ImportCommandTest.php`
Expected: PASS.

- [x] **Step 6: Pint** on the three touched files.

### Task 3: AI single-locale re-embed joins the deferral

**Files:**
- Modify: `Modules/AI/app/Listeners/HandleTranslationReembeddingListener.php`
- Test: `Modules/AI/tests/Integration/HandleTranslationReembeddingListenerTest.php`

**Interfaces:**
- Consumes: `DeferredSearchIndexing::defer()`, `run()` (Task 1).

- [x] **Step 1: Write the failing test** (existing tests switch from `new HandleTranslationReembeddingListener()` to `app(HandleTranslationReembeddingListener::class)`)

```php
it('dispatches no job during a deferred run, the flush embeds every locale', function (): void {
    $model = new SearchableModelStub;
    $model->id = 1;
    $indexing = app(DeferredSearchIndexing::class);

    $indexing->run(function () use ($model): void {
        app(HandleTranslationReembeddingListener::class)->handle(new TranslationRequiresReembedding($model, 'it'));
    }, batchSize: 10, discard: true);

    Queue::assertNothingPushed();
});
```

- [x] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Integration/HandleTranslationReembeddingListenerTest.php`
Expected: FAIL, `GenerateEmbeddingsJob` pushed.

- [x] **Step 3: Implement**

```php
    public function __construct(private readonly DeferredSearchIndexing $deferredIndexing) {}

    public function handle(TranslationRequiresReembedding $event): void
    {
        if (! $this->shouldHandle($event->model)) {
            return;
        }

        // During a bulk run the next flush embeds the model, every stale locale at once.
        if ($event->model instanceof ISearchableModel && $this->deferredIndexing->defer([$event->model])) {
            return;
        }

        GenerateEmbeddingsJob::dispatch($event->model, $event->locale);
    }
```

- [x] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Integration/HandleTranslationReembeddingListenerTest.php Modules/AI/tests/Integration/EventServiceProviderTest.php`
Expected: PASS.

- [x] **Step 5: Pint** on the two touched files.

### Task 4: CMS post-import reindex honours the null driver

**Files:**
- Modify: `Modules/CMS/app/Import/Support/ImportPostProcessor.php`
- Test: `Modules/CMS/tests/Feature/Import/ImportPostProcessorTest.php`

- [x] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Import\Support\ImportPostProcessor;
use Modules\CMS\Models\Content;

/**
 * @return list<string>
 */
function contentQueriesDuringPostProcess(string $driver): array
{
    config(['scout.driver' => $driver]);
    $table = (new Content)->getTable();
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries, $table): void {
        if (str_contains($query->sql, $table)) {
            $queries[] = $query->sql;
        }
    });

    (new ImportPostProcessor)->run(clearCaches: false, reindex: true);

    return $queries;
}

it('skips the post-import reindex on the null search driver', function (): void {
    expect(contentQueriesDuringPostProcess('null'))->toBe([]);
});

it('runs the post-import reindex on a real search driver', function (): void {
    expect(contentQueriesDuringPostProcess('collection'))->not->toBe([]);
});
```

- [x] **Step 2: Run to verify the first fails**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Import/ImportPostProcessorTest.php`
Expected: FAIL on the null-driver test.

- [x] **Step 3: Implement**

```php
        if ($reindex && ! in_array(config('scout.driver'), [null, 'null'], true)) {
            Content::makeAllSearchable();
        }
```

- [x] **Step 4: Run to verify both pass**, then **Pint** on the two files.

### Task 5: Documentation, indexes, delivery status

**Files:**
- Modify: `Modules/Core/docs/IMPORT_FRAMEWORK.md` (options list, new "Search indexing during an import" section)
- Modify: `Modules/Core/docs/rag/MODULE.md` (import framework paragraph: `--index-batch`, deferred indexing)
- Modify: `Modules/Core/docs/rag/SEARCH_MATCHING_DEVELOPER.md` (the `--no-search` sentence; one paragraph on `DeferredSearchIndexing`)
- Modify: `Modules/CMS/docs/IMPORTS.md` (options table, `CMS_IMPORT_REINDEX` note)
- Modify: `Modules/CMS/docs/rag/IMPORTS.md` (options sentence)
- Modify: `Modules/ERP/README.md` (`erp:import` options line)
- Modify: `docs/superpowers/specs/INDEX.md`, `docs/superpowers/plans/INDEX.md`
- Modify: this plan (`## Delivery status`, `**Documented in:**`) and the spec status

- [x] **Step 1:** Update the module docs with the behaviour shipped: deferral during `{module}:import`, `--index-batch` default, flush timing (threshold after commit, end, on failure), `--no-search` / dry-run discard (no indexing, no embeddings, removals untouched), recovery after a killed process (`scout:import`, `ai:embeddings:repair`), `CMS_IMPORT_REINDEX` redundancy.
- [x] **Step 2:** Add both index entries.
- [x] **Step 3:** Run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php` after adding the delivery status.

### Task 6: Ctrl+C / SIGTERM handling

Added after Task 5 at the user's request (spec, "Interrupts").

**Files:**
- Create: `Modules/Core/app/Search/Exceptions/DeferredRunInterruptedException.php`
- Create: `Modules/Core/app/Import/Support/ImportInterruptHandler.php`
- Modify: `Modules/Core/app/Search/DeferredSearchIndexing.php` (`interrupt()`, `hasPendingWork()`, `pendingCount()`, `isFlushing()`, save-time recording inside transactions)
- Modify: `Modules/Core/app/Console/AbstractImportCommand.php` (`trap()` on SIGINT/SIGTERM, operator prompt, status 128 + signal)
- Modify: `Modules/Core/tests/Stubs/Search/RecordingSearchEngineStub.php` (`onUpdate` hook), `Modules/Core/tests/Stubs/Import/FakeSearchableBulkImporter.php` (`interruptAfter`)
- Test: `Modules/Core/tests/Integration/Search/DeferredSearchIndexingTest.php`, `Modules/Core/tests/Feature/Import/ImportInterruptHandlerTest.php`, `Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php`

- [x] **Step 1:** Tests for `interrupt()` outside and during a flush, including a flush running inside a commit's after-commit callbacks (fails without save-time recording: the last model of the transaction is lost).
- [x] **Step 2:** `interrupt()` + `DeferredRunInterruptedException`; save-time recording limited to models saved inside a transaction (outside one, Scout's observer already runs at save time and a second record would re-index the model after each flush).
- [x] **Step 3:** Handler tests (nothing pending, finish, quit, resume, SIGTERM / no terminal, second signal) and `ImportInterruptHandler`.
- [x] **Step 4:** Command test (interrupted import returns 130, indexes what it imported) and the command wiring.
- [x] **Step 5:** Pint, PHPStan on the touched app files, module docs.

## Delivery status (2026-09-28): delivered

All six tasks shipped. Task 6 (interrupt handling) was added after the first five, same day. Divergences from the code written above:

- `DeferredSearchIndexingTest` and the two new `AbstractImportCommandTest` cases create `Event::fake()` inside each test, not in `beforeEach()`, per the project's testing rules.
- `ImportPostProcessorTest` declares `uses(TestCase::class, RefreshDatabase::class)`, like its sibling CMS import tests: the CMS `Pest.php` binds no test case to `Feature/`.
- `ImportPostProcessor` carries a one-line comment explaining why the guard also matches the `'null'` string.
- `ISearchableModel` also declares the Scout methods the flush calls on a generic model (`makeAllSearchableQuery()`, typed `Builder<Model&self>`, `shouldBeSearchable()`, `getScoutKey()`, `getScoutKeyName()`), so PHPStan checks them instead of taking them on faith. `DeferredSearchIndexing::$batchSize` is typed `int<1, max>`, and the command reads the default with `config()->integer('scout.chunk.searchable', 500)`.

Not built, as the spec states: deferral for the interactive file import (`ImportRunner`), deferred removals, importer-side memory. The residual `Content::toSearchableArray` N+1 (spec 2026-09-19) is still open and is now the main cost of a flush.

**Documented in:** `Modules/Core/docs/IMPORT_FRAMEWORK.md` ("Search indexing during an import"), `Modules/Core/docs/rag/MODULE.md`, `Modules/Core/docs/rag/SEARCH_MATCHING_DEVELOPER.md`, `Modules/CMS/docs/IMPORTS.md`, `Modules/CMS/docs/rag/IMPORTS.md`, `Modules/ERP/README.md`.
