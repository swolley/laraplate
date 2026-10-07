# MES machine data acquisition, step 4: piece counts and OEE performance and quality. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Count samples from machines become stored deltas attributed to operations, operations show machine and declared quantities, reaching the planned quantity notifies once, and OEE performance and quality of a connected work center follow ISO 22400 from the counts.

**Architecture:** A synchronous listener on `PartsCounted` (`PartsCountRecorder`) turns each count sample into a row of `mes_machine_counts` (delta against the previous sample of the signal, rollover and reset aware), idempotently, and recomputes the row that follows a late insert. `OperationCountTally` refreshes `machine_good_quantity` and `machine_scrap_quantity` of the attributed operations from their rows and fires `OperationTargetReached` once. Completion prefills `declared_*`; corrections are audited in an append-only table. `OeeCalculatorService::performance()` and `quality()` switch to the count formulas for work centers that have count rows in the window.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest 4. No new dependency.

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (sections 6.2 count config, 6.3, 9.2, 10, 13 "Counts", 15 step 4). Builds on `2026-10-06-mes-machine-data-foundation.md`, `2026-10-07-mes-machine-data-mqtt-bridge.md` and `2026-10-07-mes-machine-data-states-downtime.md`.

## Global Constraints

- Everything in the Global Constraints of the foundation and states plans still holds (strict types, braces, explicit types, `#[Override]`, `final`; English code and docs; idempotent writes; five-driver portability; tests in `Modules/MES/tests`, support in `tests/Support`; pint and phpstan before each commit; commits in the `Modules/MES` submodule, pushed after each task; commit only your own files with explicit paths).
- New tables carry `company_id`, use the `mes_` prefix, are registered in `MESTables`; changes to existing MES tables are folded into their create migrations (no alter migrations).
- `mes_machine_counts` (spec 9.2): `signal_id`, `device_id`, `work_center_id`, `production_order_operation_id` (nullable), `ts`, `good`, `scrap`, `total`; unique `(signal_id, ts)`.
- Cumulative counters: delta against the previous sample by `ts`, recognising rollover (`rollover_max`) and reset (a lower value with no rollover counts from zero). Delta counters are used as received.
- `mes_production_order_operations` gets `machine_good_quantity`, `machine_scrap_quantity` (derived, recomputable), `declared_good_quantity`, `declared_scrap_quantity` (prefilled at completion, editable, audited).
- The operation's machine quantities are sums of its attributed deltas. When the operation is completed `declared_*` are prefilled from them; the operator may correct them and the correction is audited. The order's produced quantity is prefilled from its last operation. Counts never complete an operation.
- When good pieces reach the order's planned quantity, `OperationTargetReached` is dispatched once per operation, with a notification. Unattributed counts stay on the work center, count towards its OEE, and can be assigned to an operation by time range in Filament.
- OEE (spec 10), for work centers with count rows in the window: Performance = Σ (ideal cycle time x total count) / run time, ideal cycle time is the operation's `cycle_time_minutes`; Quality = good count / total count. Work centers without counts keep today's formulas and results. Run time = planned busy time minus unplanned downtime, as of step 3.

## Rulings made in this plan (the spec is silent)

- **R1 One value per row.** A count row carries the delta of one signal: the column of its role (`good`, `scrap` or `total`) holds it, the other two are 0. `raw_value` (decimal 18,4) keeps the received value so the next delta can be computed; the first sample of a signal has no predecessor and its delta is its value when the mode is `delta`, and 0 when the mode is `cumulative` (a baseline).
- **R2 Late samples.** A sample older than rows already stored is inserted by its `ts`; its delta is computed against the row before it, and the row after it is recomputed against it (same transaction). The same message twice leaves the same rows (the unique key).
- **R3 Reset and rollover.** `value < previous` with `rollover_max` set and `previous > rollover_max / 2` is a rollover: delta = `rollover_max - previous + value`. Any other drop is a reset: delta = `value`. A negative delta is never stored.
- **R4 Total.** `total` of a work center in a window is the sum of `total` rows when the device has a total signal, otherwise `good + scrap`. Quality = good / total, 1.0 when total is 0.
- **R5 Ideal cycle time of unattributed counts.** Counts without an operation use the work center's ideal cycle `60 / capacity_per_hour` minutes (nothing when `capacity_per_hour` is 0); they do count in quality.
- **R6 Target reached.** `mes_production_order_operations.target_reached_at` (nullable) is set when `machine_good_quantity >= quantity_planned` of the order; the event fires only on the transition from null. Recomputing downwards never clears it.
- **R7 Audit.** Corrections of `declared_*` are rows of `mes_operation_quantity_audits` (append-only: operation, user, field, old value, new value, created at), written by `OperationQuantityDeclarer::declare()`, the only writer of the declared columns outside completion.
- **R8 Completion prefill.** `ProductionOrderOperationService::complete()` copies `machine_*` into `declared_*` when the operation has machine quantities and `declared_*` are null; the order's produced quantity default in the Filament Complete action is the declared good quantity of the order's last operation.
- **R9 Assignment by time range.** `MachineCountAssigner::assign(int $operation_id, DateTimeInterface $from, DateTimeInterface $to): int` attributes the unattributed counts of the operation's work center inside the range (`from <= ts < to`) and refreshes the tally; it returns how many rows it assigned.
- **R10 KPI cache.** Performance and quality change meaning for counted work centers, so the KPI cache key becomes `mes:kpi:v3:`.

## Review Focus

- **Counter reset or rollover mid-stream**: no negative or huge delta; tested in Task 2 with a rollover and a reset.
- **Same message twice, and a late sample between two stored rows**: identical rows after reprocessing; the following delta recomputed. Task 2.
- **Target reached exactly once**, also when a late sample raises the total after completion of the check, and never clearing on recount. Task 3.
- **A correction of declared quantities leaves an audit row** with old and new values; a no-op correction leaves none. Task 4.
- **A work center with counts but run time 0, or counts with no good pieces**: performance and quality stay in [0, 1], never a division error. Task 5.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_07_000003_create_mes_machine_counts_table.php`, `..._000004_create_mes_operation_quantity_audits_table.php`, `app/Models/{MachineCount,OperationQuantityAudit}.php` + factory of `MachineCount`, `app/Enums/MESTables.php` (modify) | Storage. |
| `database/migrations/2026_05_08_..._create_mes_production_order_operations_table.php` (modify), `app/Models/ProductionOrderOperation.php` (modify) | The five new operation columns. |
| `app/Machine/Counts/CounterDelta.php` | `CounterDelta::between()` pure maths. |
| `app/Listeners/PartsCountRecorder.php` | Listener on `PartsCounted`. |
| `app/Services/{OperationCountTally,OperationQuantityDeclarer,MachineCountAssigner}.php` | Tally, audited declaration, assignment. |
| `app/Events/OperationTargetReached.php`, `app/Listeners/NotifyOperationTargetReached.php`, `app/Notifications/OperationTargetReachedNotification.php`, `config/config.php` (modify) | Target notification, `mes.notifications.operation_target`. |
| `app/Services/{OeeCalculatorService,ProductionOrderOperationService,WorkCenterKpiStore}.php` (modify) | Count formulas, completion prefill, cache key. |
| `app/Filament/Resources/ProductionOrders/...` (modify) | Columns, declare and assign actions, completion default. |

---

### Task 1: Schema and models

**Files:**
- Create: the two migrations, `app/Models/MachineCount.php`, `app/Models/OperationQuantityAudit.php`, `database/factories/MachineCountFactory.php`
- Modify: `app/Enums/MESTables.php` (`MachineCounts`, `OperationQuantityAudits`), the production order operations create migration, `ProductionOrderOperation` (fillable, casts `decimal:4`, `target_reached_at` datetime, `@property`)
- Test: `tests/Feature/Machine/MachineCountSchemaTest.php`

**Interfaces:**
- Produces: `MachineCount` (plain Eloquent, `$dateFormat = 'Y-m-d H:i:s.v'`, `BelongsToCompany`, relations `signal()`, `device()`, `operation()`); table `mes_machine_counts` as in Global Constraints plus `raw_value` (R1) with indexes `(work_center_id, ts)` and `(production_order_operation_id)`; `OperationQuantityAudit` with `operation_id`, `user_id` (nullable), `field`, `old_value`, `new_value` (decimal 12,4 nullable), `created_at` only (no updates); operation columns `machine_good_quantity`, `machine_scrap_quantity`, `declared_good_quantity`, `declared_scrap_quantity` (decimal 12,4, the machine ones default 0, the declared ones nullable) and `target_reached_at` (nullable datetime).

- [ ] **Step 1: Write the failing test.** A count row stores milliseconds and refuses a second row with the same `(signal_id, ts)`; a row without operation is valid; an operation has the new columns with the stated defaults; an audit row stores old and new values.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineCountSchemaTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the migrations (folding the operation columns into its create migration), models, factory, enum cases.
- [ ] **Step 4: Run** the file, and `php artisan test --compact Modules/MES/tests/Feature/ProductionOrderOperationServiceTest.php`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): machine counts table and the quantity columns of operations`.

### Task 2: Counter deltas and the recorder

**Files:**
- Create: `app/Machine/Counts/CounterDelta.php`, `app/Listeners/PartsCountRecorder.php`
- Modify: `app/Providers/MESServiceProvider.php` (`Event::listen(PartsCounted::class, PartsCountRecorder::class)`)
- Test: `tests/Feature/Machine/CounterDeltaTest.php` (unit-style, no database), `tests/Feature/Machine/PartsCountRecorderTest.php`

**Interfaces:**
- Produces: `CounterDelta::between(?float $previous, float $value, string $mode, ?float $rollover_max): float` per R1 and R3 (`$mode` is `cumulative` or `delta`; a null `$previous` is the first sample); `PartsCountRecorder::handle(PartsCounted $event): void`.
- Consumes: `ResolvedSample` (signal role from `$sample->signal->role`, `config['mode']`, `config['rollover_max']`, `production_order_operation_id`), `IdempotentWriter`.

- [ ] **Step 1: Write the failing tests.** `CounterDelta`: cumulative 100 to 130 gives 30; first cumulative sample gives 0; delta mode first sample gives its value; rollover (`previous` 995, `value` 5, `rollover_max` 1000) gives 10; a reset (`previous` 500, `value` 3, no `rollover_max`) gives 3; a drop below half of `rollover_max` is a reset. Recorder: good, scrap and total signals write rows in their own column; the same message twice leaves the same rows; a late sample between two stored rows is inserted by `ts` and the next row's delta is recomputed; attribution is stored; the full pipeline (processor job on a message with count samples) produces the rows.
- [ ] **Step 2: Run** both files. Expected: FAIL.
- [ ] **Step 3: Implement** `CounterDelta` and the recorder. The recorder reads the previous row of the signal by `ts` (`raw_value`), writes through `IdempotentWriter` in a transaction on the device connection, then recomputes the row after the written one. It dispatches nothing yet; it returns the ids of the operations whose rows changed to the tally of Task 3 (a protected hook `afterWrite(array $operation_ids)` left empty here).
- [ ] **Step 4: Run** the two files and `php artisan test --compact Modules/MES/tests/Feature/Machine`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): machine count deltas with rollover and reset`.

### Task 3: Operation tally and target reached

**Files:**
- Create: `app/Services/OperationCountTally.php`, `app/Events/OperationTargetReached.php`, `app/Listeners/NotifyOperationTargetReached.php`, `app/Notifications/OperationTargetReachedNotification.php`
- Modify: `app/Listeners/PartsCountRecorder.php` (calls the tally), `config/config.php` (`notifications.operation_target`, same shape as the others), `MESServiceProvider` (listener), `README.md` and `docs` later in Task 6
- Test: `tests/Feature/Machine/OperationCountTallyTest.php`

**Interfaces:**
- Produces: `OperationCountTally::refresh(int $operation_id): void` (sums `good` and `scrap` of its rows into the two machine columns; sets `target_reached_at` and dispatches `OperationTargetReached` on the first time `machine_good_quantity >= quantity_planned`, R6); `OperationTargetReached(int $company_id, int $production_order_id, int $production_order_operation_id, int $work_center_id, float $good, float $planned)`.

- [ ] **Step 1: Write the failing tests.** Rows of two signals attributed to one operation sum into its machine columns; recomputing after a late row changes the sums; reaching the planned quantity dispatches the event once and sets `target_reached_at`; a recount below the target does not clear it and a further row does not dispatch again; the operation is never completed; the notification goes to the configured roles (same assertions as the capacity overload listener test).
- [ ] **Step 2: Run** the file. Expected: FAIL.
- [ ] **Step 3: Implement** the tally, event, queued listener, notification and config key; the recorder calls `refresh()` for every operation whose rows changed, including the operation a recomputed row belongs to.
- [ ] **Step 4: Run** the file and the Machine folder. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): operation machine quantities and the target reached event`.

### Task 4: Declared quantities, audit, assignment

**Files:**
- Create: `app/Services/OperationQuantityDeclarer.php`, `app/Services/MachineCountAssigner.php`
- Modify: `app/Services/ProductionOrderOperationService.php` (prefill in `complete()`), `app/Models/ProductionOrderOperation.php` (relation `quantityAudits()`)
- Test: `tests/Feature/Machine/OperationQuantityDeclarerTest.php`, extend `tests/Feature/ProductionOrderOperationServiceTest.php`

**Interfaces:**
- Produces: `OperationQuantityDeclarer::declare(ProductionOrderOperation $operation, ?float $good, ?float $scrap, ?int $user_id = null): ProductionOrderOperation` (R7; writes an audit row per changed field; no row when nothing changes; refuses negative values with a `DomainException`); `MachineCountAssigner::assign(...)` (R9); completion prefill (R8).

- [ ] **Step 1: Write the failing tests.** Completing an operation with machine quantities prefills `declared_*` and leaves them alone when already set, and for an operation with no counts leaves them null; a correction writes one audit row per changed field with old and new values and the user; the same values again write none; a negative value is refused; assignment attributes only unattributed rows of the work center inside `[from, to)`, refreshes the tally, returns the count, and leaves rows of other work centers and already attributed rows alone.
- [ ] **Step 2: Run** the files. Expected: FAIL.
- [ ] **Step 3: Implement** the services and the prefill.
- [ ] **Step 4: Run** the files plus `php artisan test --compact Modules/MES/tests/Feature/ProductionOrderServiceTest.php`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): declared quantities with an audit trail and count assignment by time range`.

### Task 5: ISO 22400 performance and quality

**Files:**
- Modify: `app/Services/OeeCalculatorService.php`, `app/Services/WorkCenterKpiStore.php` (`mes:kpi:v3:`)
- Test: extend `tests/Feature/OeeCalculatorServiceTest.php`, `tests/Feature/KpiMaterializationTest.php`

**Interfaces:**
- Produces: `OeeCalculatorService::performance()` and `quality()` use the count formulas (R4, R5) when the work center has count rows inside the window, else today's formulas; run time for performance = availability's busy time minus unplanned downtime (working time), computed once per call; every result clamped to [0, 1].

- [ ] **Step 1: Write the failing tests.** Known values for a connected work center over 480 working minutes with 60 minutes of breakdown (run time 420): an operation with `cycle_time_minutes` 0.5 and 600 total pieces gives performance 300/420; good 570, scrap 30 give quality 0.95; unattributed counts use `60 / capacity_per_hour` and count in quality; counts with run time 0 give performance 1.0 and no division error; no good pieces gives quality 0 when total is above 0; a work center without count rows keeps today's numbers (existing tests untouched); the KPI materialiser stores the new values and a value cached under `mes:kpi:v2:` is not read.
- [ ] **Step 2: Run** the two files. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** the branches; counts are read once per call, in the window by `ts` (`from <= ts < to`, bound through `MachineTime::db()`).
- [ ] **Step 4: Run** the two files, `CapacityServiceTest.php` and the Filament folder. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): ISO 22400 performance and quality from machine counts`.

### Task 6: Filament, documentation, plan closing

**Files:**
- Modify: `app/Filament/Resources/ProductionOrders/RelationManagers/OperationsRelationManager.php`, `app/Filament/Resources/ProductionOrders/Pages/EditProductionOrder.php`, `README.md`, `docs/MACHINE_CONNECTIVITY.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Test: extend `tests/Feature/Filament/MesFilamentPagesTest.php`, `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Produces: the operations table shows machine good and scrap, declared good and scrap, and `target_reached_at`; a "Declare quantities" table action calls `OperationQuantityDeclarer` with the signed-in user; an "Assign machine counts" table action (from, to) calls `MachineCountAssigner`; the Complete action of the order defaults `quantity_produced` to the declared good quantity of the order's last operation (R8).

- [ ] **Step 1: Write the failing tests.** Filament: the declare action saves values and writes the audit row; the assign action attributes the counts of its range; the Complete action's default is the last operation's declared good quantity; the operations table lists the new columns. Documentation test: `docs/MACHINE_CONNECTIVITY.md` contains `mes_machine_counts`, `rollover`, `OperationTargetReached`, `declared`, `mes:kpi:v3` is not required but `ISO 22400` performance is; the README no longer lists counts in its roadmap.
- [ ] **Step 2: Run** the two files. Expected: FAIL.
- [ ] **Step 3: Implement** the Filament changes and write the documents (the "not built yet" statements: counts are built; R1 to R10 in plain words; glossary terms: Machine count, Declared quantity; Italian guide section).
- [ ] **Step 4: Run** the two files, the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: green (phpstan may still report files outside this plan).
- [ ] **Step 5: Close the plan.** Add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick the boxes, record divergences; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`; update `docs/superpowers/plans/INDEX.md`. Commit and push in `Modules/MES` and in the laraplate repo.

---

## Self-review

- **Spec coverage:** 9.2 (Tasks 1 to 4: rows, deltas, rollover and reset, quantities, declared prefill and audit, target reached, assignment), 10 (Task 5), 13 "Counts" (Tasks 2 to 4) and "OEE" (Task 5), 6.3 columns (Task 1). `mes_machine_counts` unique key as in the spec.
- **Spec gaps decided here:** R1 to R10; the heaviest are R1 (raw value, baseline), R5 (ideal cycle of unattributed counts) and R7 (audit table).
- **Type consistency:** `CounterDelta::between()`, `PartsCountRecorder::handle()`, `OperationCountTally::refresh()`, `OperationQuantityDeclarer::declare()`, `MachineCountAssigner::assign()` and `OperationTargetReached` are named once and used with those names in every task that consumes them.
