# MES machine data acquisition, step 6: process values. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Process value samples from machines are stored behind a `ProcessValueStore` contract (relational driver by default), rolled up into minute and hour aggregates, pruned by retention, and summarised permanently per operation so a lot can be traced to the process parameters it was made with.

**Architecture:** A synchronous listener on `ProcessValuesSampled` (`ProcessValueRecorder`) writes the samples through the store in batches and marks the touched minute buckets dirty. The scheduled `mes:machine-rollup` recomputes dirty minute buckets from the raw samples and the hour buckets from the minute aggregates. A daily command prunes by retention. `ProcessSummarizer` computes `mes_operation_process_summaries` when an operation completes and again when late or reprocessed samples touch a completed operation. The store contract is the only thing the rest of the code knows; the relational driver is selected by `mes.machine.process_store`.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest 4. No new dependency.

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (sections 6.2 process value config, 9.4, 11.6, 13 "Volume" and "Process values", 15 step 6). Builds on `2026-10-06-mes-machine-data-foundation.md` and the later step plans.

## Global Constraints

- Everything in the Global Constraints of the earlier plans still holds (strict types, braces, explicit types, `#[Override]`, `final`; English code and docs; idempotent writes; five-driver portability; tests in `Modules/MES/tests`, support in `tests/Support`; pint and phpstan before each commit; commits in the `Modules/MES` submodule, pushed after each task; commit only your own files with explicit paths; new tables carry `company_id`, use the `mes_` prefix and are registered in `MESTables`).
- Spec 9.4 contract: `write(list<ProcessSample>)`, `aggregates(signal, from, to, resolution)`, `rollup(from, to)`, `prune(before)`. Relational driver: `mes_process_samples` (`signal_id`, `ts`, `value`, `quality`; unique `(signal_id, ts)`); `mes_process_aggregates` (`signal_id`, `resolution` `1m` / `1h`, `bucket_start`, `min`, `max`, `avg`, `last`, `count`; unique `(signal_id, resolution, bucket_start)`); `mes:machine-rollup` runs every minute on buckets marked dirty and recomputes them, so late data and reprocessing are absorbed.
- Independent of the driver: `mes_operation_process_summaries` (`production_order_operation_id`, `signal_id`, `min`, `max`, `avg`, `count`, `out_of_range_count`, `first_ts`, `last_ts`), computed when the operation completes and on reprocessing; permanent. Sampling is decided by the agent: the backend does not downsample on arrival.
- Configuration (spec 11.6): `mes.machine.raw_retention_days` (30), `mes.machine.minute_aggregate_retention_days` (90), `mes.machine.process_store` (`database`), each with an env variable `MES_MACHINE_RAW_RETENTION_DAYS`, `MES_MACHINE_MINUTE_AGGREGATE_RETENTION_DAYS`, `MES_MACHINE_PROCESS_STORE`, documented in the README like the other machine keys (the existing documentation test counts the `MES_MACHINE_` variables: update its number).
- Volume (spec 13): a 5000-sample message runs within a bounded, asserted number of queries (batched inserts, no N+1). No timing-based tests.

## Rulings made in this plan (the spec is silent)

- **R1 Which samples.** The event also carries order and operation reference samples; the recorder keeps only signals with the role `process_value`. A non-numeric value is skipped.
- **R2 Quality.** A `bad` sample is stored (its quality is kept) but excluded from aggregates and summaries; `good` and `uncertain` count.
- **R3 Dirty buckets.** The marks live in `mes_process_dirty_buckets` (`company_id`, `signal_id`, `bucket_start` minute, `marked_at`; unique `(signal_id, bucket_start)`). The rollup reads a batch of the oldest marks (500 per run), recomputes each minute bucket from the raw samples, and deletes a mark only if its `marked_at` is unchanged, so a sample arriving during the run keeps its mark.
- **R4 Hours come from minutes.** The `1h` aggregate of an hour is rebuilt from the `1m` aggregates of that hour (min of mins, max of maxes, count summed, avg as the count-weighted mean of the minute averages, last of the latest minute), never from raw samples: raw samples are pruned long before hour aggregates.
- **R5 Late data older than the raw retention.** A sample older than `raw_retention_days` is not stored (the next prune would delete it and its bucket could not be rebuilt): the recorder counts it as dropped and logs once per message. A minute bucket whose raw samples have all been pruned is never recomputed, so an aggregate is never replaced by an empty one.
- **R6 Prune.** `prune(DateTimeInterface $before): int` deletes raw samples older than `$before`; `pruneAggregates(DateTimeInterface $before): int` deletes `1m` aggregates older than `$before`; `1h` aggregates and summaries are kept. `mes:machine-prune-process-values` runs daily with the two retentions.
- **R7 Summaries.** `ProcessSummarizer::summarize(int $operation_id): int` (returns the rows written) builds one row per signal that has good or uncertain samples attributed to the operation; `out_of_range_count` counts samples outside the signal's `config.min` / `config.max` (inclusive bounds; 0 when none is set). It runs on `OperationCompleted` and again when the recorder stores samples attributed to a completed operation. A summary row is only replaced when its signal still has samples, so pruned raw data never empties it. The summaries read through the store (`operationStatistics()`), so any driver supplies them.
- **R8 Attribution is stored.** `mes_process_samples.production_order_operation_id` (nullable) keeps the attribution the pipeline made; `operationStatistics(int $operation_id, array $ranges)` groups by signal.
- **R9 Batching.** The recorder writes the samples of a message in one multi-row insert per chunk of 500 and marks the buckets of the chunk in one multi-row insert (`IdempotentWriter::insertMany()`, new: insert-or-ignore on mysql, mariadb, pgsql and sqlite; row by row with unique-violation tolerance elsewhere).
- **R10 Traceability.** `LotTracingService::processSummaries(int $lot_id): Collection<int, OperationProcessSummary>` returns the summaries of the operations of the lot's production order.
- **R11 Store selection.** `ProcessValueStore` is bound in the service provider to the driver named by `mes.machine.process_store`; an unknown name throws a `RuntimeException` that lists the known ones.

## Review Focus

- **The same message twice, a late sample into a closed minute, and reprocessing**: identical samples; the aggregates equal a recomputation from raw; no mark lost. Tasks 2 and 3.
- **Bad quality samples, a minute with only bad samples, an hour spanning minutes with different counts**: aggregates skip bad samples; the hour average is count-weighted. Task 2.
- **A sample older than the raw retention, and pruning after a rollup**: nothing stored; pruned raw never empties an aggregate or a summary. Tasks 2 and 4.
- **A 5000-sample message**: a bounded number of queries. Task 3.
- **An operation completed before its late samples arrive, an out-of-range count on the bounds, a signal with no range**: the summary follows. Task 4.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_08_000002_create_mes_process_samples_table.php`, `..._000003_create_mes_process_aggregates_table.php`, `..._000004_create_mes_process_dirty_buckets_table.php`, `..._000005_create_mes_operation_process_summaries_table.php`, `app/Models/{ProcessSample,ProcessAggregate,OperationProcessSummary}.php` + factories, `app/Enums/MESTables.php` (modify) | Storage. |
| `app/Machine/Process/{ProcessValueStore,RelationalProcessValueStore,ProcessSample,ProcessAggregateRow,ProcessStatistics}.php` | Contract, relational driver and the value objects. |
| `app/Machine/Support/IdempotentWriter.php` (modify) | `insertMany()`. |
| `app/Listeners/ProcessValueRecorder.php`, `app/Console/{MachineRollupCommand,PruneProcessValuesCommand}.php`, `config/config.php`, `app/Providers/MESServiceProvider.php` (modify) | Recording, rollup, prune, binding, schedule. |
| `app/Services/ProcessSummarizer.php`, `app/Listeners/SummarizeOperationProcessValues.php`, `app/Services/LotTracingService.php` (modify) | Summaries and traceability. |
| `app/Filament/Resources/OperationProcessSummaries/...` | Backoffice. |

---

### Task 1: Schema, models and configuration

**Files:**
- Create: the four migrations, `ProcessSample`, `ProcessAggregate`, `OperationProcessSummary` models and factories, `app/Machine/Process/{ProcessSample,ProcessAggregateRow,ProcessStatistics}.php` (readonly value objects)
- Modify: `app/Enums/MESTables.php` (`ProcessSamples`, `ProcessAggregates`, `ProcessDirtyBuckets`, `OperationProcessSummaries`), `config/config.php` (the three keys), `README.md` (the three variables), `tests/Feature/Machine/MachineDocumentationTest.php` (the variable count)
- Test: `tests/Feature/Machine/ProcessValueSchemaTest.php`

**Interfaces:**
- Produces: `ProcessSample(int $company_id, int $signal_id, int $device_id, int $work_center_id, ?int $production_order_operation_id, CarbonImmutable $ts, float $value, SampleQuality $quality)`; `ProcessAggregateRow(int $signal_id, string $resolution, CarbonImmutable $bucket_start, float $min, float $max, float $avg, float $last, int $count)`; `ProcessStatistics(int $signal_id, float $min, float $max, float $avg, int $count, int $out_of_range_count, CarbonImmutable $first_ts, CarbonImmutable $last_ts)`. Tables as in the Global Constraints, `ts` and `bucket_start` with milliseconds precision 3 (the buckets use whole minutes and hours), `value` decimal(18,6), the operation id nullable with a nullable foreign key, `quality` enum of `SampleQuality`; the dirty buckets table as in R3; the summaries unique `(production_order_operation_id, signal_id)`.

- [x] **Step 1: Write the failing test.** Raw samples store milliseconds and refuse a second row of one signal at the same moment; aggregates refuse a duplicate `(signal, resolution, bucket_start)`; a dirty mark refuses a duplicate bucket of one signal; a summary refuses a second row for one operation and signal; the three configuration keys have the stated defaults; the README names the three variables.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/ProcessValueSchemaTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the migrations, models, factories, value objects, enum cases, config and README.
- [x] **Step 4: Run** the file and `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineDocumentationTest.php`. Expected: PASS.
- [x] **Step 5: Commit and push**: `feat(mes): process value tables and configuration`.

### Task 2: The store contract and the relational driver

**Files:**
- Create: `app/Machine/Process/ProcessValueStore.php` (interface), `app/Machine/Process/RelationalProcessValueStore.php`
- Modify: `app/Machine/Support/IdempotentWriter.php` (`insertMany()`), `app/Providers/MESServiceProvider.php` (R11 binding)
- Test: `tests/Feature/Machine/ProcessValueStoreTest.php`, extend `tests/Feature/Machine/IdempotentWriterTest.php` if it exists

**Interfaces:**
- Produces:
  - `ProcessValueStore::write(array $samples): int` (`list<ProcessSample>`, returns how many were stored; marks nothing itself, the recorder does).
  - `aggregates(int $signal_id, DateTimeInterface $from, DateTimeInterface $to, string $resolution): list<ProcessAggregateRow>` (buckets with `from <= bucket_start < to`, ordered).
  - `rollup(int $limit = 500): int` (processes up to `$limit` dirty marks, oldest first, R3 to R5; returns how many minute buckets were rebuilt; also rebuilds the hour buckets they touch).
  - `prune(DateTimeInterface $before): int` and `pruneAggregates(DateTimeInterface $before): int` (R6).
  - `operationStatistics(int $operation_id, array $ranges): list<ProcessStatistics>` (`$ranges` is `array<int, array{min: ?float, max: ?float}>` by signal id; bad samples excluded).
  - `IdempotentWriter::insertMany(Connection $connection, string $table, array $rows): int` (rows stored; insert-or-ignore on the four drivers, row by row elsewhere).

- [x] **Step 1: Write the failing tests.** `write()` stores a batch once and ignores a repeat; `rollup()` builds the minute aggregate (min, max, avg, last, count) of a dirty bucket and removes its mark; a late sample into a closed minute re-marked and rolled up changes the aggregate; a mark set while the rollup runs is kept (simulate by re-marking between read and delete through a second mark with a newer `marked_at`); bad samples are left out and a minute with only bad samples yields no aggregate row (and removes a stale one); the hour aggregate is the count-weighted combination of two minutes with different counts; a dirty minute whose raw samples were pruned keeps its old aggregate (R5); `aggregates()` returns the requested resolution and range; `prune()` and `pruneAggregates()` delete only older rows and keep hour aggregates; `operationStatistics()` returns min, max, avg, count, out-of-range count (bounds inclusive, no range gives 0) and first and last time per signal; an unknown `mes.machine.process_store` throws with the known names.
- [x] **Step 2: Run** the files. Expected: FAIL.
- [x] **Step 3: Implement** the interface, the driver (set-based SQL through the query builder; the hour rebuild reads the `1m` rows of the hour) and `insertMany()`.
- [x] **Step 4: Run** the files. Expected: PASS.
- [x] **Step 5: Commit and push**: `feat(mes): process value store with a relational driver and rollup`.

### Task 3: Recorder, rollup command and pruning

**Files:**
- Create: `app/Listeners/ProcessValueRecorder.php`, `app/Console/MachineRollupCommand.php`, `app/Console/PruneProcessValuesCommand.php`
- Modify: `app/Providers/MESServiceProvider.php` (listener, schedules: rollup every minute with `withoutOverlapping()->onOneServer()`, prune daily)
- Test: `tests/Feature/Machine/ProcessValueRecorderTest.php`

**Interfaces:**
- Produces: `ProcessValueRecorder::handle(ProcessValuesSampled $event): void` (R1, R2, R5, R9); commands `mes:machine-rollup` (`ProcessValueStore::rollup()`) and `mes:machine-prune-process-values` (`prune()` with `raw_retention_days`, `pruneAggregates()` with `minute_aggregate_retention_days`). The recorder returns the ids of the completed operations whose samples it stored through a protected hook `afterWrite()` left empty here (Task 4 fills it).

- [x] **Step 1: Write the failing tests.** A message of process value samples is stored with the attributed operation and quality and its minute buckets are marked; reference-role samples and non-numeric values are ignored; the same message twice (and through the processing job, reprocessed) stores the same rows and marks; a sample older than the raw retention is not stored; a bad sample is stored but does not move an aggregate after the rollup; a 5000-sample message performs a bounded number of queries (assert `DB::getQueryLog()` count stays below a fixed constant that does not grow with the samples, comparing a 500-sample and a 5000-sample message); `mes:machine-rollup` rebuilds the marked buckets and is scheduled every minute with `withoutOverlapping()->onOneServer()`; `mes:machine-prune-process-values` prunes by the two retentions and is scheduled daily.
- [x] **Step 2: Run** the file. Expected: FAIL.
- [x] **Step 3: Implement** the recorder (one transaction per message, chunks of 500), the commands and the registrations.
- [x] **Step 4: Run** the file and `php artisan test --compact Modules/MES/tests/Feature/Machine`. Expected: PASS.
- [x] **Step 5: Commit and push**: `feat(mes): record process values, roll them up and prune them`.

### Task 4: Operation summaries and lot traceability

**Files:**
- Create: `app/Services/ProcessSummarizer.php`, `app/Listeners/SummarizeOperationProcessValues.php`
- Modify: `app/Listeners/ProcessValueRecorder.php` (fills `afterWrite()`), `app/Providers/MESServiceProvider.php` (listener on `OperationCompleted`), `app/Services/LotTracingService.php`
- Test: `tests/Feature/Machine/ProcessSummarizerTest.php`

**Interfaces:**
- Produces: `ProcessSummarizer::summarize(int $operation_id): int` (R7); `SummarizeOperationProcessValues::handle(OperationCompleted $event): void`; `LotTracingService::processSummaries(int $lot_id): Collection` (R10).
- Consumes: `ProcessValueStore::operationStatistics()`, the signals' `config.min` / `config.max`.

- [x] **Step 1: Write the failing tests.** Completing an operation (through `ProductionOrderOperationService::complete()`) writes one summary per signal with min, max, avg, count, out-of-range count, first and last time; bounds count as within; a signal with no range has 0; bad samples are excluded; samples of other operations are left out; late samples stored after completion update the summary of the completed operation (and the same message reprocessed leaves it unchanged); pruned raw samples never empty an existing summary; an operation without samples has no summaries; `LotTracingService::processSummaries()` returns the summaries of the operations of the lot's order and nothing for another order.
- [x] **Step 2: Run** the file. Expected: FAIL.
- [x] **Step 3: Implement** the summarizer, the listener, the recorder hook (summarize only operations that are already completed) and the tracing method.
- [x] **Step 4: Run** the file, `php artisan test --compact Modules/MES/tests/Feature/ProductionOrderOperationServiceTest.php` and the Machine folder. Expected: PASS.
- [x] **Step 5: Commit and push**: `feat(mes): permanent process summaries per operation and lot traceability`.

### Task 5: Filament, documentation, plan closing

**Files:**
- Create: `app/Filament/Resources/OperationProcessSummaries/{OperationProcessSummaryResource.php,Pages/ListOperationProcessSummaries.php,Tables/OperationProcessSummariesTable.php}`
- Modify: `README.md`, `docs/MACHINE_CONNECTIVITY.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Test: extend `tests/Feature/Filament/MesMachineFilamentTest.php`, `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Produces: a read-only resource "Process summaries" in the group "Machine connectivity" (slug `mes/operation-process-summaries`): order number, operation, signal, min, max, avg, count, out-of-range count, first and last time; filters by signal; no create, edit or delete.

- [x] **Step 1: Write the failing tests.** Filament: the list shows a summary with its columns and filters by signal. Documentation test: `docs/MACHINE_CONNECTIVITY.md` contains `mes_process_samples`, `mes_process_aggregates`, `mes:machine-rollup`, `ProcessValueStore`, `mes_operation_process_summaries`, `raw_retention_days`, `minute_aggregate_retention_days` and `process_store`; the README no longer lists process values in its roadmap and the roadmap section says every step is built.
- [x] **Step 2: Run** the two files. Expected: FAIL.
- [x] **Step 3: Implement** the resource and write the documents (the "not built yet" statements: everything is built; R1 to R11 in plain words; glossary: Process sample, Process aggregate, Process summary; Italian guide section).
- [x] **Step 4: Run** the two files, the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: green (phpstan may still report files outside this plan).
- [x] **Step 5: Close the plan.** Add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick the boxes, record divergences; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`; update `docs/superpowers/plans/INDEX.md`. Commit and push in `Modules/MES` and in the laraplate repo.

---

## Self-review

- **Spec coverage:** 9.4 contract and relational tables (Tasks 1 and 2), `mes:machine-rollup` on dirty buckets (Tasks 2 and 3), summaries on completion and reprocessing (Task 4), no downsampling on arrival (Task 3), traceability lot to order to operation to parameters (Task 4, R10), 11.6 keys (Task 1), 13 "Volume" (Task 3) and "Process values" (rollup idempotent with late data: Task 2; summary on completion: Task 4; pruning by retention: Task 3), `process_store` selection (Task 2, R11).
- **Spec gaps decided here:** R1 to R11; the heaviest are R4 (hours from minutes), R5 (nothing stored beyond the raw retention, no empty aggregates), R7 (summaries never emptied) and R9 (batched writes).
- **Type consistency:** `ProcessValueStore` methods, the three value objects, `IdempotentWriter::insertMany()`, `ProcessValueRecorder::handle()`, `ProcessSummarizer::summarize()` and `LotTracingService::processSummaries()` are named once and used with those names in every task that consumes them.

## Delivery status (2026-10-08): delivered

All five tasks shipped in `Modules/MES`; the whole module suite passes, phpstan reports only files outside this plan. With this step every part of the machine data acquisition design is built.

**Documented in:** `Modules/MES/docs/MACHINE_CONNECTIVITY.md`, `Modules/MES/docs/rag/MODULE.md` and `Modules/MES/README.md`.

Divergences and review outcome:

- `ProcessValueStore::write()` marks the dirty minutes itself (a store detail, so a driver without a rollup need not), not the recorder. The contract gained `hasPendingRollup()`.
- The value objects keep the plan's names in `Machine\Process` (`ProcessSample`, ...), next to the Eloquent models of the same name in `Models`.
- Summaries are written by a queued `SummarizeOperationProcessValuesJob`, unique per operation until it starts, dispatched on `OperationCompleted` and when new samples are stored for a completed operation; completing an operation never waits for it and a replay that stores nothing queues nothing. A summary row is replaced only by one that counts at least as many samples (review: pruning could degrade the permanent record).
- `mes:machine-rollup` runs batches of 500 until nothing waits or 50 seconds have passed (a fixed single batch left a backlog that could grow into permanent holes).
- Prune deletes a chunk of 2000 rows at a time. Values that are not finite or beyond 12 integer digits are dropped and logged, so one bad value cannot fail the message. The store and the summarizer use the model's connection, not the default one.
- Deferred, not fixed: a mark set again in the same millisecond as the one a rollup read can be deleted with it (a version counter instead of the timestamp would remove it); the unique `(signal_id, ts)` loses the second of two samples in the repeated hour of a daylight saving change (the machine time convention already requires a timezone without daylight saving); on SQL Server samples go in one row at a time because insert-or-ignore does not exist there (the volume test runs on SQLite only); on PostgreSQL the string comparison at the exact raw-retention boundary may skip one minute.
- The Filament component and route caches of this machine (from `php artisan optimize`) were stale and hid the new resources; they were cleared with `filament:clear-cached-components` and `route:clear`.
