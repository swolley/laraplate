# MES machine data acquisition, step 5: probe measurements filling quality checks. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Measurement samples from probes land on the quality check of the operation they belong to, the check resolves by itself when every characteristic has its required samples, out-of-limit values are announced at once, and measurements that cannot be placed wait to be assigned by hand.

**Architecture:** A synchronous listener on `ProbeMeasured` (`ProbeMeasurementRecorder`) matches each sample to the quality check of its attributed operation through the signal's plan characteristic, records it through the new `QualityCheckService::record()`, resolves the check when it is complete (`resolve()`), and dispatches `OutOfToleranceMeasured` for an out-of-limit value. A sample with no check goes to `mes_machine_unattributed_measurements`; `QualityCheckPlanner` attaches the waiting measurements of an operation when the check of that operation is created, and a Filament action assigns the others by hand.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest 4. No new dependency.

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (sections 6.3, 9.3, 11.5, 13 "Probes", 15 step 5). Builds on `2026-10-06-mes-machine-data-foundation.md` and the later step plans.

## Global Constraints

- Everything in the Global Constraints of the earlier plans still holds (strict types, braces, explicit types, `#[Override]`, `final`; English code and docs; idempotent writes; five-driver portability; tests in `Modules/MES/tests`, support in `tests/Support`; pint and phpstan before each commit; commits in the `Modules/MES` submodule, pushed after each task; commit only your own files with explicit paths; no new tables without `company_id` and the `mes_` prefix, registered in `MESTables`; changes to existing tables folded into their create migrations).
- Spec 6.3: `mes_quality_plan_characteristics` gets `required_samples` (default 1); `mes_quality_check_measurements` gets `quality_plan_characteristic_id` (nullable), `serial` (nullable), `measured_at`, `source` (`manual` / `machine`) and `machine_signal_id` (nullable).
- Spec 9.3: a `Measurement` signal points at `quality_plan_characteristic_id`. The measurement goes to the quality check of the attributed operation whose plan contains that characteristic; the limits (nominal, lower, upper) are copied from the plan characteristic. `QualityCheckService` is split into `record()` and `resolve()`; `execute()` stays `record()` then `resolve()`, so existing callers do not change. The check resolves when every characteristic of its plan has at least `required_samples` measurements; a failure follows the existing path (status `failed`, non-conformance opened). An out-of-limit measurement dispatches `OutOfToleranceMeasured` immediately, with a notification, even before resolution; one arriving after resolution opens a non-conformance linked to that check. Measurements with no check or no operation go to `mes_machine_unattributed_measurements` (`signal_id`, `ts`, `value`, `serial`, `context`, `assigned_at`) and are assigned by hand in Filament.

## Rulings made in this plan (the spec is silent)

- **R1 Checks are created at completion.** `QualityCheckPlanner` creates the pending check of an operation when the operation completes, so probes measuring during production find none. The recorder never creates a check (decided with the user). The measurement waits in the unattributed table with its operation, and the planner attaches the waiting measurements of an operation to the check it creates (`UnattributedMeasurementAttacher`), then resolves it if complete.
- **R2 Which check.** The check of the attributed operation whose `quality_plan_id` equals the plan of the signal's characteristic, in any status (pending or already resolved: the history of the operation stays on one check). No check, no operation or no characteristic on the signal: unattributed.
- **R3 No unique index on measurements.** Spec 6.3 asks for a unique `(machine_signal_id, measured_at)`. A nullable composite unique breaks manual rows on SQL Server and Oracle (the same reason as step 3, R5), so idempotency comes from the unattributed table's unique `(signal_id, ts)` plus an existence check on `(machine_signal_id, measured_at)` done under a lock on the signal row; the measurements table gets a plain index on those columns.
- **R4 Completeness.** A check is complete when, for every characteristic of its plan, the count of its measurements with that `quality_plan_characteristic_id` is at least `required_samples`. Manual measurements (no characteristic id) do not count. A plan without characteristics is never complete by machine.
- **R5 Resolution status.** On resolution the status is `failed` when any measurement of the check is outside its limits, else `passed`; `checked_at` is the moment of the resolving measurement. A failure opens the non-conformance of the existing path, once.
- **R6 After resolution.** A measurement for a check that is no longer pending is stored on that check; if it is out of limits it opens a non-conformance linked to the check (one per measurement); the status of the check does not change.
- **R7 Out of tolerance event.** `OutOfToleranceMeasured` is dispatched when a measurement is first stored (on a check or in the unattributed table) and is out of the limits of its characteristic; never again for a replay or a reprocess. A sample whose signal has no characteristic has no limits and never fires it. The notification goes to the roles of `mes.notifications.out_of_tolerance` (default admin and superadmin).
- **R8 Value and limits.** A limit is inclusive: `lower <= value <= upper`. A non-numeric value is not a measurement: it is skipped (the signal rules already require a number for this role).
- **R9 Manual assignment.** `UnattributedMeasurementAssigner::assign(UnattributedMeasurement $row, QualityCheck $check): QualityCheckMeasurement` records the row on a check that belongs to the row's work center's company and whose plan contains the signal's characteristic, sets `assigned_at`, and applies R4 to R6. A row already assigned is refused with a `DomainException`.

## Review Focus

- **The same message twice, or reprocessed**: one measurement, one event, one non-conformance. Task 3.
- **Fewer samples than `required_samples`, or one characteristic missing**: the check stays pending; the last missing sample resolves it. Task 3.
- **Out-of-limit exactly on the limit, one hundredth over, and after resolution**: boundary is inclusive; after resolution a non-conformance is opened and the status stays. Tasks 2 and 3.
- **`execute()` for existing callers**: same statuses, same non-conformance, same rows. Task 2.
- **A probe measuring during production (no check yet), then the operation completes**: the waiting measurements attach to the new check and resolve it. Task 4.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_08_000001_create_mes_machine_unattributed_measurements_table.php`, the plan characteristics and check measurements create migrations (modify), `app/Models/UnattributedMeasurement.php` + factory, `app/Enums/MESTables.php` (modify), the two quality models (modify) | Storage. |
| `app/Services/QualityCheckService.php` (modify) | `record()`, `resolve()`, `isComplete()`; `execute()` unchanged in behaviour. |
| `app/Listeners/ProbeMeasurementRecorder.php`, `app/Events/OutOfToleranceMeasured.php`, `app/Listeners/NotifyOutOfTolerance.php`, `app/Notifications/OutOfToleranceNotification.php`, `config/config.php` (modify), `app/Providers/MESServiceProvider.php` (modify) | Matching, recording, event. |
| `app/Services/{UnattributedMeasurementAttacher,UnattributedMeasurementAssigner}.php`, `app/Services/QualityCheckPlanner.php` (modify) | Late attachment and manual assignment. |
| `app/Filament/Resources/UnattributedMeasurements/...`, `app/Filament/Resources/ProductionOrders/RelationManagers/QualityChecksRelationManager.php` (modify) | Backoffice. |

---

### Task 1: Schema and models

**Files:**
- Create: the unattributed measurements migration, `app/Models/UnattributedMeasurement.php`, `database/factories/UnattributedMeasurementFactory.php`
- Modify: `app/Enums/MESTables.php` (`UnattributedMeasurements`), the create migrations of `mes_quality_plan_characteristics` and `mes_quality_check_measurements`, `QualityPlanCharacteristic` and `QualityCheckMeasurement` (fillable, casts, relations)
- Test: `tests/Feature/Machine/ProbeMeasurementSchemaTest.php`

**Interfaces:**
- Produces: `UnattributedMeasurement` (plain Eloquent, `BelongsToCompany`, `$dateFormat = 'Y-m-d H:i:s.v'`): `company_id`, `signal_id`, `device_id`, `work_center_id`, `production_order_operation_id` (nullable), `ts`, `value` (decimal 15,4), `serial` (nullable), `context` (json, nullable), `assigned_at` (nullable); unique `(signal_id, ts)`. Characteristic `required_samples` (unsigned int, default 1). Measurement columns as in the Global Constraints, `measured_at` nullable datetime(3), `source` default `manual`, a plain index on `(machine_signal_id, measured_at)` (R3).

- [ ] **Step 1: Write the failing test.** The unattributed row stores milliseconds and refuses a second row of the same signal at the same moment; a characteristic defaults to one required sample; a measurement defaults to source `manual` and accepts the machine columns; existing measurement rows still insert without them.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/ProbeMeasurementSchemaTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the migrations (folded where the table exists), models, factory, enum case.
- [ ] **Step 4: Run** the file and `php artisan test --compact Modules/MES/tests/Feature/QualityCheckFlowTest.php`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): probe measurement columns and the unattributed measurements table`.

### Task 2: Split of `QualityCheckService`

**Files:**
- Modify: `app/Services/QualityCheckService.php`
- Test: extend `tests/Feature/QualityCheckFlowTest.php`

**Interfaces:**
- Produces (keep `execute()` as it is for callers):
  - `record(QualityCheck $check, array $measurements): list<QualityCheckMeasurement>`: stores the rows (each with `is_within_limits` computed by the inclusive rule of R8) and, for a check that is not pending, opens a non-conformance per out-of-limit row (R6). Measurement arrays accept the old keys plus optional `quality_plan_characteristic_id`, `serial`, `measured_at`, `source`, `machine_signal_id`.
  - `resolve(QualityCheck $check): QualityCheck`: status by R5, `checked_at` now, non-conformance when failed (once per check).
  - `isComplete(QualityCheck $check): bool`: R4.
  - `execute()` = `record()` then `resolve()` in one transaction, with the same results as today.

- [ ] **Step 1: Write the failing tests.** `execute()` keeps its two existing outcomes (pass, fail with one non-conformance); `record()` alone leaves the check pending; `resolve()` after a record of an out-of-limit row fails the check and opens exactly one non-conformance, and a second `resolve()` opens no second one; a value exactly on a limit is within; `record()` on a resolved check with an out-of-limit row opens a non-conformance and leaves the status; `isComplete()` is false with fewer than `required_samples` of one characteristic, true when all are met, and false for a plan without characteristics.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/QualityCheckFlowTest.php`. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** the three methods and rewrite `execute()` on top of them.
- [ ] **Step 4: Run** the file and `php artisan test --compact Modules/MES/tests/Feature/Filament`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): split quality check recording from resolution`.

### Task 3: The recorder and the out of tolerance event

**Files:**
- Create: `app/Listeners/ProbeMeasurementRecorder.php`, `app/Events/OutOfToleranceMeasured.php`, `app/Listeners/NotifyOutOfTolerance.php`, `app/Notifications/OutOfToleranceNotification.php`
- Modify: `config/config.php` (`notifications.out_of_tolerance`), `app/Providers/MESServiceProvider.php`
- Test: `tests/Feature/Machine/ProbeMeasurementRecorderTest.php`

**Interfaces:**
- Produces: `ProbeMeasurementRecorder::handle(ProbeMeasured $event): void` (R1 to R8); `OutOfToleranceMeasured(int $company_id, ?int $quality_check_id, int $signal_id, string $characteristic, float $value, ?float $lower, ?float $upper)`.
- Consumes: `QualityCheckService::record()/resolve()/isComplete()`, `ResolvedSample` (`production_order_operation_id`, `sample->context['serial']`).

- [ ] **Step 1: Write the failing tests.** A sample on a signal with a characteristic goes to the pending check of its operation with limits copied from the characteristic, `source` machine, `machine_signal_id`, `measured_at`, serial; with `required_samples` 3 the check stays pending after two samples and resolves (passed) on the third; two characteristics resolve only when both are met; an out-of-limit sample dispatches the event at once and the check fails on resolution with one non-conformance; a sample exactly on the limit is within; after resolution an out-of-limit sample opens a non-conformance and keeps the status; no operation, no check, or a signal without characteristic leaves the sample in the unattributed table (with the event when out of limits and a characteristic exists); the same event handled twice stores one measurement, one event, one non-conformance; the whole pipeline (a message through the processing job, reprocessed once) gives the same rows; the notification goes to the configured roles.
- [ ] **Step 2: Run** the file. Expected: FAIL.
- [ ] **Step 3: Implement** the recorder: one transaction per sample, `lockForUpdate` on the signal row, existence check then insert (R3), event only for newly stored rows (R7), the resolution through `isComplete()` and `resolve()`. Register the listeners; add the config key with the same shape as the others.
- [ ] **Step 4: Run** the file and `php artisan test --compact Modules/MES/tests/Feature/Machine`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): probe measurements fill quality checks and announce out of tolerance values`.

### Task 4: Late attachment and manual assignment

**Files:**
- Create: `app/Services/UnattributedMeasurementAttacher.php`, `app/Services/UnattributedMeasurementAssigner.php`
- Modify: `app/Services/QualityCheckPlanner.php` (calls the attacher after creating a check)
- Test: `tests/Feature/Machine/UnattributedMeasurementTest.php`, extend `tests/Feature/QualityCheckPlannerTest.php` if it exists (otherwise the new file covers it)

**Interfaces:**
- Produces: `UnattributedMeasurementAttacher::attachFor(QualityCheck $check): int` (attaches the unassigned rows of the check's operation whose signal characteristic belongs to the check's plan, sets `assigned_at`, applies R4 to R6, returns how many); `UnattributedMeasurementAssigner::assign(UnattributedMeasurement $row, QualityCheck $check): QualityCheckMeasurement` (R9).

- [ ] **Step 1: Write the failing tests.** Measurements taken before the operation completes (waiting with their operation) attach to the check the planner creates at completion and resolve it when complete; rows of other operations or other plans stay; replaying the completion adds nothing; manual assignment records the row, sets `assigned_at`, resolves a complete check, refuses a check of another company or a plan without the characteristic, and refuses an already assigned row.
- [ ] **Step 2: Run** the files. Expected: FAIL.
- [ ] **Step 3: Implement** the two services and the planner hook.
- [ ] **Step 4: Run** the files and `php artisan test --compact Modules/MES/tests/Feature/QualityCheckFlowTest.php`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): attach waiting probe measurements to the check of their operation`.

### Task 5: Filament, documentation, plan closing

**Files:**
- Create: `app/Filament/Resources/UnattributedMeasurements/{UnattributedMeasurementResource.php,Pages/ListUnattributedMeasurements.php,Tables/UnattributedMeasurementsTable.php}`
- Modify: `app/Filament/Resources/ProductionOrders/RelationManagers/QualityChecksRelationManager.php` (measurement count column), `README.md`, `docs/MACHINE_CONNECTIVITY.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Test: extend `tests/Feature/Filament/MesFilamentPagesTest.php`, `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Produces: a read-only resource "Unattributed measurements" in the group "Machine connectivity" (slug `mes/unattributed-measurements`) listing the rows with signal, operation, value, serial, time, assigned; an "Assign" row action (check select limited to checks whose plan holds the signal's characteristic, same company) calling `UnattributedMeasurementAssigner`, visible to users allowed to insert machine signals as in the unmapped signals table.

- [ ] **Step 1: Write the failing tests.** Filament: the list shows an unassigned row; the assign action records it on the chosen check and marks it assigned; the action is hidden from a user without permission. Documentation test: `docs/MACHINE_CONNECTIVITY.md` contains `mes_machine_unattributed_measurements`, `required_samples`, `OutOfToleranceMeasured`, `quality_plan_characteristic_id` and `inclusive`; the README roadmap no longer lists probe measurements.
- [ ] **Step 2: Run** the two files. Expected: FAIL.
- [ ] **Step 3: Implement** the resource and write the documents (the "not built yet" statements: probes are built; R1 to R9 in plain words; glossary: Unattributed measurement, Required samples; Italian guide section).
- [ ] **Step 4: Run** the two files, the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: green (phpstan may still report files outside this plan).
- [ ] **Step 5: Close the plan.** Add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick the boxes, record divergences; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`; update `docs/superpowers/plans/INDEX.md`. Commit and push in `Modules/MES` and in the laraplate repo.

---

## Self-review

- **Spec coverage:** 9.3 first bullet (Tasks 3, R2), second (Task 2), third and fourth (Tasks 2 and 3, R4 to R6), fifth (Task 3, R7), sixth (Tasks 1, 3 and 4), 6.3 columns (Task 1), 11.5 unattributed measurements (Task 5), 13 "Probes" (Tasks 2 and 3: resolution with `required_samples`, immediate event, non-conformance after resolution, `execute()` unchanged).
- **Spec gaps decided here:** R1 to R9; the heaviest are R1 (checks exist only after completion, so measurements wait and attach), R3 (no unique index on measurements) and R6 (what happens after resolution).
- **Type consistency:** `QualityCheckService::record()/resolve()/isComplete()`, `ProbeMeasurementRecorder::handle()`, `UnattributedMeasurementAttacher::attachFor()`, `UnattributedMeasurementAssigner::assign()` and `OutOfToleranceMeasured` are named once and used with those names in every task that consumes them.
