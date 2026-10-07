# MES machine data acquisition, step 3: states to downtime and OEE availability. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Machine state samples build a full state history per device and open and close downtimes by themselves, and OEE availability of a connected work center follows ISO 22400 from that history.

**Architecture:** A synchronous listener on `MachineStateObserved` (`MachineStateRecorder`) keeps `mes_machine_state_intervals` through one service (`MachineStateIntervals`) that opens, closes and splits intervals; a second service (`MachineDowntimeDeriver`) turns the intervals of a work center's downtime states into `Downtime` rows with `source = machine`, written through new machine-only methods of `DowntimeService`. A scheduled command opens downtimes for stops still running past the micro-stop threshold; the watchdog adds the synthetic `Offline` interval. `OeeCalculatorService` switches to the ISO formula for work centers that have an active device with a state signal.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest 4. No new dependency.

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (sections 6.3, 9.1, 10, 11.1, 13 "States and downtime" and "OEE", 15 step 3). Builds on the shipped plans `2026-10-06-mes-machine-data-foundation.md` and `2026-10-07-mes-machine-data-mqtt-bridge.md`.

## Global Constraints

- Everything in the Global Constraints of the foundation plan still holds (strict types, braces, explicit types, `#[Override]`, `final`; English code and docs; idempotent writes; five-driver portability; tests in `Modules/MES/tests`, support in `tests/Support`; pint and phpstan before each commit; commits in the `Modules/MES` submodule, pushed after each task; commit only your own files with explicit paths).
- All new tables carry `company_id`, use the `mes_` prefix and are registered in `MESTables`; changes to existing MES tables are folded into their create migrations (no alter migrations).
- Changes to existing tables (spec 6.3): `mes_work_centers` gets `micro_stop_threshold_seconds` and `downtime_states` (json list of `MachineState`, default `fault`, `stopped`, `setup`, `maintenance`); `mes_downtimes` gets `source` (`manual` / `machine`), `machine_device_id` (nullable) and `alarm_code` (nullable). `DowntimeCause` gets the case `Unclassified`; `cause` stays required; in OEE an unclassified downtime counts as unplanned.
- `mes_machine_state_intervals`: `device_id`, `work_center_id`, `state`, `alarm_code`, `started_at`, `ended_at`; unique `(device_id, started_at)`.
- State recording (spec 9.1): a state sample equal to the current state is a no-op; a different state closes the open interval and opens a new one; a state in the work center's `downtime_states` produces a `Downtime` only once its interval exceeds `micro_stop_threshold_seconds`, created when the interval closes or by the scheduled `mes:machine-open-stops` (every minute) for intervals still open past the threshold; shorter stops remain intervals only. Cause, in order: the alarm map of the interval's alarm code, the state's default cause (`Setup` to `Setup`, `Maintenance` to `PlannedMaintenance`), `Unclassified`. `Offline` is missing data, not a downtime; OEE windows that contain `Offline` are flagged "incomplete data". Machine downtimes are opened through the dedicated downtime action with `source = machine`; the operator edits cause and notes, the times are locked; manual downtimes are refused on work centers that have an active device. A sample older than the open interval splits the interval that contains it, and the downtimes derived from that interval are recomputed.
- OEE (spec 10), for work centers with an active device: Availability = run time / planned busy time, planned busy time = calendar time minus planned maintenance, run time = planned busy time minus unplanned downtime; every downtime counts only the part inside the window. Work centers without devices keep today's formula and results. Performance and Quality of connected work centers keep today's formulas until step 4 (counts).
- `mes:machine-open-stops` runs every minute, `withoutOverlapping()->onOneServer()`, next to the existing schedules.

## Rulings made in this plan (the spec is silent)

- **R1 Connected.** A work center is "connected" when it has an active device, of an active source, with an active `state` signal. Manual downtimes are refused for it; OEE uses ISO availability for it.
- **R2 One state device per work center.** A second active `state` signal on another device of the same work center is refused by the signal rules: two devices in different states would overlap their downtimes and double count them.
- **R3 Threshold.** `micro_stop_threshold_seconds` defaults to 60 (unsigned integer, 0 allowed and then every stop counts). A stop is a downtime when its interval lasts **more than** the threshold.
- **R4 Alarm codes.** An alarm sample (`Alarm` role) sets `alarm_code` on the interval that contains its sample time, the first alarm of an interval winning; the cause comes from the alarm map of the device's alarm signals when the downtime is derived. Agents send an alarm after the state change it explains.
- **R5 Downtime times.** `mes_downtimes.started_at` and `ended_at` get millisecond precision so a machine downtime matches its interval exactly. The unique index of spec 6.3 is `(work_center_id, started_at)` instead of `(machine_device_id, started_at)`: a nullable column in a composite unique index breaks manual rows on SQL Server and Oracle, and R2 makes the two equivalent for machine rows.
- **R6 Recompute keeps the operator's work.** When an interval changes (a late sample splits it, its end moves), its derived downtime is updated in place: times change, `cause` and `notes` stay. A downtime whose interval no longer exceeds the threshold is deleted.
- **R7 Locked times.** A machine downtime's `started_at`, `ended_at` and `duration_minutes` can only be written by the machine path (`Downtime::writingAsMachine()`); any other write of them is a `ValidationException`. A machine downtime cannot be closed by hand (`DowntimeService::close()` refuses it, the policy hides the action).
- **R8 Synthetic Offline.** When the watchdog opens a `device_silent` incident it records an `Offline` interval starting at the device's `last_seen_at` (the device stopped talking then); when the device is heard again, an open `Offline` interval closes at that `last_seen_at`. A `death` notice already arrives as an `Offline` state sample.
- **R9 KPI cache.** `WorkCenterKpis` gains `incomplete_data`; the cache key gets a version segment (`mes:kpi:v2:`) so entries cached before this step are never read.

## Review Focus

- **A late state sample inside an already closed interval**: it splits that interval, the derived downtime changes in place, and the operator's cause and notes survive. Test in Task 4, `a late sample splits its interval and keeps the operator's cause`.
- **The same message processed twice, or a duplicate state sample**: the intervals and downtimes are identical to one processing. Test in Task 4.
- **A stop exactly at the threshold and one second over**: the first is only an interval, the second a downtime; a late split that shortens an interval below the threshold deletes the downtime it had. Tests in Tasks 4 and 5.
- **A manual downtime on a connected work center, through the service and through the generic insert, and a hand edit of a machine downtime's times**: refused; other work centers unaffected. Test in Task 2.
- **An OEE window with an `Offline` stretch**: flagged incomplete, `Offline` not counted as a downtime, planned maintenance reducing planned busy time and not availability. Test in Task 6, with a known-value dataset.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `app/Enums/DowntimeSource.php`, `app/Enums/DowntimeCause.php` (modify) | `manual` / `machine`; the `Unclassified` case. |
| `app/Models/MachineStateInterval.php`, its factory, migration `2026_10_07_000002_create_mes_machine_state_intervals_table.php` | The state history. |
| `app/Machine/MachineConnectivity.php` | R1: is a work center connected. |
| `app/Machine/States/{MachineStateIntervals,MachineDowntimeDeriver,DowntimeCauseResolver}.php` | Interval logic, downtime derivation, cause. |
| `app/Listeners/MachineStateRecorder.php` | The listener on `MachineStateObserved`. |
| `app/Console/MachineOpenStopsCommand.php` | `mes:machine-open-stops`. |
| `app/Services/{DowntimeService,OeeCalculatorService,WorkCenterKpiMaterializer,WorkCenterKpiStore}.php` (modify), `app/Data/WorkCenterKpis.php` (modify) | Machine downtime methods, ISO availability, incomplete flag. |
| `app/Machine/MachineWatchdog.php` (modify) | Synthetic `Offline`. |
| `app/Filament/Resources/{WorkCenters,Downtimes}/...` (modify) | New fields. |

---

### Task 1: Schema, enums and models

**Files:**
- Create: `app/Enums/DowntimeSource.php`, `app/Models/MachineStateInterval.php`, `database/factories/MachineStateIntervalFactory.php`, `database/migrations/2026_10_07_000002_create_mes_machine_state_intervals_table.php`
- Modify: `app/Enums/DowntimeCause.php` (`Unclassified = 'unclassified'`), `app/Enums/MESTables.php` (`MachineStateIntervals = 'mes_machine_state_intervals'`), the create migrations of `mes_work_centers` and `mes_downtimes`, `app/Models/WorkCenter.php`, `app/Models/Downtime.php`, `database/factories/DowntimeFactory.php` and `WorkCenterFactory.php` if needed
- Test: `tests/Feature/Machine/MachineStateSchemaTest.php`

**Interfaces:**
- Produces: `DowntimeSource` (`Manual='manual'`, `Machine='machine'`, `values()`, `validationRule()`); `DowntimeCause::Unclassified`; `WorkCenter::$micro_stop_threshold_seconds` (int, default 60), `WorkCenter::$downtime_states` (`list<string>` cast, default the four states, rules: each value in `MachineState::values()`), `WorkCenter::isDowntimeState(MachineState): bool`; `Downtime::$source` (`DowntimeSource` cast, default `manual`), `Downtime::$machine_device_id`, `Downtime::$alarm_code`, relation `device()`; `MachineStateInterval` (plain Eloquent, `BelongsToCompany`, `HasFactory`): `company_id`, `device_id`, `work_center_id`, `state` (`MachineState` cast), `alarm_code` (nullable string 64), `started_at` and `ended_at` (datetime with 3 digits, nullable `ended_at`), unique `(device_id, started_at)`, scope `open()`, relation `device()`.

- [ ] **Step 1: Write the failing test.** `MachineStateSchemaTest`: a new work center reads `micro_stop_threshold_seconds` 60 and the four default states, and refuses a state outside `MachineState` and a negative threshold; `isDowntimeState` is true for `fault` and false for `running`; a downtime defaults to `source` `manual` and accepts `cause` `unclassified`; a machine downtime stores `machine_device_id` and `alarm_code`; a downtime stores and reads back millisecond times (`started_at` `2026-10-05 08:00:00.123`); a state interval persists with ms times and refuses a duplicate `(device_id, started_at)` with `UniqueConstraintViolationException`; the unique index `(work_center_id, started_at)` on downtimes refuses two downtimes of one work center with the same start.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineStateSchemaTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the enum, the folded migration columns (`dateTime('started_at', 3)` and `dateTime('ended_at', 3)` on downtimes, R5), the new table, the models and factories, `MESTables` case.
- [ ] **Step 4: Run** the new test plus `php artisan test --compact Modules/MES/tests/Feature/CapacityServiceTest.php Modules/MES/tests/Feature/OeeCalculatorServiceTest.php Modules/MES/tests/Feature/DowntimeOpeningTest.php` (the unique index may expose tests that create two downtimes of one work center at the same instant: give those distinct start times). Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): machine state interval table and the downtime columns of step 3`.

---

### Task 2: Connected work centers and the downtime guards

**Files:**
- Create: `app/Machine/MachineConnectivity.php`
- Modify: `app/Services/DowntimeService.php`, `app/Models/Downtime.php`, `app/Models/MachineSignal.php`, `app/Policies/MesModelPolicy.php`
- Test: `tests/Feature/Machine/DowntimeGuardsTest.php`

**Interfaces:**
- Produces: `MachineConnectivity::isConnected(int $work_center_id): bool` (R1); `Downtime::writingAsMachine(Closure $callback): mixed` (static; runs the callback with machine writes allowed, restoring the flag even on exceptions); `DowntimeService::open()` throws `DomainException` for a connected work center; `Downtime` creating a `manual` row for a connected work center throws `ValidationException` (field `work_center_id`); updating `started_at`, `ended_at` or `duration_minutes` of a `machine` row outside `writingAsMachine` throws `ValidationException`; `DowntimeService::close()` throws `DomainException` for a machine downtime; policy `close` is false for a machine downtime; `MachineSignal` rules refuse a second active `state` signal on another device of the same work center (R2).

- [ ] **Step 1: Write the failing test.** `isConnected` is true only with an active device of an active source and an active `state` signal, and false after any of the three is deactivated or deleted, and for a device without a state signal; `DowntimeService::open()` on a connected work center throws `DomainException` and on an unconnected one still works; `Downtime::factory()->create(['work_center_id' => connected])` (manual) throws `ValidationException`, while a `machine` one is allowed; editing `cause` and `notes` of a machine downtime works, editing `ended_at` or `started_at` throws, and the same edit inside `Downtime::writingAsMachine(fn () => ...)` works and the flag is off again after an exception inside it; `close()` of a machine downtime throws; the policy refuses `close` on it and still allows it on a manual open one; a second state signal on a different device of the same work center is refused, on another work center allowed, and a second non-state signal is allowed.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/DowntimeGuardsTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the helper (one query joining device, source and signal), the model hooks (`creating`, `updating`) and the guards, the signal rule.
- [ ] **Step 4: Run** the new test, then `php artisan test --compact Modules/MES/tests/Feature/MesModelPolicyTest.php Modules/MES/tests/Feature/DowntimeOpeningTest.php Modules/MES/tests/Feature/Machine/MachineConfigurationModelsTest.php`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): connected work centers refuse manual downtimes and lock machine downtime times`.

---

### Task 3: Cause resolution and the machine downtime methods

**Files:**
- Create: `app/Machine/States/DowntimeCauseResolver.php`
- Modify: `app/Services/DowntimeService.php`
- Test: `tests/Feature/Machine/MachineDowntimeServiceTest.php`

**Interfaces:**
- Consumes: Task 2 `writingAsMachine`.
- Produces:
  - `DowntimeCauseResolver::resolve(MachineDevice $device, MachineState $state, ?string $alarm_code): DowntimeCause`: the cause from the `config.map` of the device's active `alarm` signals when `$alarm_code` is mapped there (the value is a `DowntimeCause` value), else `Setup` for `MachineState::Setup`, `PlannedMaintenance` for `Maintenance`, else `Unclassified`.
  - `DowntimeService::openFromMachine(MachineDevice $device, DowntimeCause $cause, CarbonInterface $started_at, ?string $alarm_code = null): Downtime` creates a `machine` downtime (`machine_device_id`, `alarm_code`, company and work center from the device) through `writingAsMachine`, dispatches `DowntimeOpened`; a downtime with the same work center and start already stored is returned unchanged.
  - `DowntimeService::closeFromMachine(Downtime $downtime, CarbonInterface $ended_at): Downtime` sets `ended_at` and `duration_minutes` (minutes with milliseconds as a decimal) through `writingAsMachine`, dispatches `DowntimeClosed` only on the first close; closing again with the same time is a no-op.

- [ ] **Step 1: Write the failing test:** the resolver cases (mapped alarm wins over the state default; unmapped alarm falls to the state default; `Setup` and `Maintenance` defaults; `Fault`, `Stopped` and `Idle` without alarm give `Unclassified`; an alarm map entry with an unknown cause value is ignored); `openFromMachine` stores a `machine` downtime with the device's work center and the alarm code and dispatches the event once (calling it twice gives one row); `closeFromMachine` stores `ended_at` and a duration (a 90.5 second stop gives 1.5083 minutes, 4 decimals), dispatches once, and a second call changes nothing; neither method touches the manual `open()` rule of one open downtime per work center.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineDowntimeServiceTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the resolver and the two methods.
- [ ] **Step 4: Run** the same file. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): machine downtime opening, closing and cause resolution`.

---

### Task 4: State intervals, derivation and the listener

**Files:**
- Create: `app/Machine/States/{MachineStateIntervals,MachineDowntimeDeriver}.php`, `app/Listeners/MachineStateRecorder.php`
- Modify: `app/Providers/MESServiceProvider.php` (`Event::listen(MachineStateObserved::class, MachineStateRecorder::class)`)
- Test: `tests/Feature/Machine/MachineStateRecorderTest.php`

**Interfaces:**
- Consumes: Tasks 1 to 3; the step 1 event `MachineStateObserved` (`samples` are `ResolvedSample`: `device`, `signal`, `sample->ts`, `state`, `alarm_code`).
- Produces:
  - `MachineStateIntervals::observe(MachineDevice $device, MachineState $state, CarbonInterface $at): list<MachineStateInterval>` returns the intervals it created or changed. Algorithm: find the interval of the device that contains `$at` (`started_at <= at` and `ended_at` null or after `at`). None: create `[at, next interval's start or open)`. Same state: nothing. An interval that starts exactly at `at` with another state: nothing (the first writer wins). Otherwise split: shorten the container to end at `at` and create `[at, container's old end)` with the new state (open when the container was open); if the interval that follows has the same state, merge the two. Every write is idempotent for the same input.
  - `MachineStateIntervals::tagAlarm(MachineDevice $device, string $alarm_code, CarbonInterface $at): ?MachineStateInterval` sets `alarm_code` on the containing interval when it has none (R4).
  - `MachineDowntimeDeriver::sync(MachineStateInterval $interval): void` per the rule of the Global Constraints and R6: an interval qualifies when its state is one of the work center's `downtime_states` and it lasts more than `micro_stop_threshold_seconds` (an open interval is measured to `now()`); a qualifying interval without a downtime opens one (cause from `DowntimeCauseResolver`), a closed qualifying interval closes it at its end, an existing downtime is updated in place (times only, R6), a downtime of an interval that no longer qualifies is deleted. `Offline` is never in `downtime_states` processing: it never derives a downtime.
  - `MachineStateRecorder::handle(MachineStateObserved $event): void`: for the samples in time order, `observe()` for those with a `state` (the `Offline` death sample included), `tagAlarm()` for those with an `alarm_code`, then `sync()` for every interval returned or tagged.

- [ ] **Step 1: Write the failing test.** Dispatch `MachineStateObserved` events built from `ResolvedSample`s (helper in `tests/Support`) or call the services directly, with a work center whose threshold is 60 s and the default downtime states: a first sample opens an interval; the same state again changes nothing; a different state closes the open interval at the sample time and opens the next; **boundary**: a `fault` that lasts exactly 60 s derives no downtime, one that lasts 61 s derives one with the end time and `machine` source; a stop still open past the threshold derives nothing in the listener (the command does it, Task 5); alarm: an alarm sample tags the containing interval, the first alarm wins, and a mapped alarm code becomes the downtime cause while an unmapped one falls to the state default, and `Setup` gives cause `setup`, `Maintenance` gives `planned_maintenance`, `Fault` without alarm gives `unclassified`; `Offline` intervals exist but derive no downtime; `a late sample splits its interval and keeps the operator's cause`: a closed `stopped` interval of 5 minutes with its downtime, the operator changed `cause` and `notes`, then a late `running` sample inside it splits it, the first part's downtime is updated in place (end moved, cause and notes unchanged), and a second part that is a different state derives nothing; a late sample that shortens a stop below the threshold deletes its downtime; a late sample before the first interval creates an interval ending where the first starts; a late sample whose state equals its container changes nothing; a late sample that creates an interval equal to the following one merges them; `processing the same events twice leaves the intervals and the downtimes identical` (compare `toArray()` snapshots of both tables) and the same for the full pipeline through `ProcessMachineMessageJob` of one stored message run twice (the second with `reprocess: true`); an interval of a work center with a threshold of 0 derives a downtime for any stop; a device whose work center has `downtime_states` limited to `fault` ignores `setup`.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineStateRecorderTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the three classes and the registration. Times are stored in the application timezone (convert before writing, as the unmapped signal recorder does).
- [ ] **Step 4: Run** the same file, then `php artisan test --compact Modules/MES/tests/Feature/Machine`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): machine state intervals and automatic downtimes`.

---

### Task 5: Open stops, and the synthetic Offline

**Files:**
- Create: `app/Console/MachineOpenStopsCommand.php`
- Modify: `app/Providers/MESServiceProvider.php` (schedule), `app/Machine/MachineWatchdog.php`
- Test: `tests/Feature/Machine/MachineOpenStopsTest.php`, extend `tests/Feature/Machine/MachineWatchdogTest.php`

**Interfaces:**
- Consumes: Task 4 services.
- Produces: `mes:machine-open-stops` (every minute, `withoutOverlapping()->onOneServer()`): for every open interval of an active device, `MachineDowntimeDeriver::sync()`; prints how many downtimes it opened. The watchdog, when it opens a `device_silent` incident, calls `MachineStateIntervals::observe($device, MachineState::Offline, $device->last_seen_at ?? $device->created_at)`; when it resolves a `device_silent` incident because the device was heard again, it closes an open `Offline` interval of that device at `last_seen_at` (R8).

- [ ] **Step 1: Write the failing test** (`Carbon::setTestNow`): an open `fault` interval older than the threshold gets an open machine downtime from the command, one younger than the threshold gets none, a second run adds none; the downtime closes (`closeFromMachine`) when a later `running` sample ends the interval (through the recorder); a stop that opened by the command and then ended by a late `running` sample inside the threshold is deleted; an inactive device is skipped; the schedule lists the command every minute; the watchdog records an `Offline` interval starting at `last_seen_at` when a device goes silent (and none for a device without a state signal), a second sweep adds none, and when the device is heard again the open `Offline` interval closes at `last_seen_at`; an `Offline` interval derives no downtime.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineOpenStopsTest.php Modules/MES/tests/Feature/Machine/MachineWatchdogTest.php`. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** the command, the schedule and the watchdog changes.
- [ ] **Step 4: Run** the same files. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): open stops past the threshold and synthetic Offline intervals`.

---

### Task 6: ISO 22400 availability and the incomplete-data flag

**Files:**
- Modify: `app/Services/OeeCalculatorService.php`, `app/Services/DowntimeService.php` (`plannedMaintenanceMinutesWithin`), `app/Services/WorkCenterKpiMaterializer.php`, `app/Services/WorkCenterKpiStore.php`, `app/Data/WorkCenterKpis.php`, `app/Filament/Resources/WorkCenters/Tables/WorkCentersTable.php`
- Test: extend `tests/Feature/OeeCalculatorServiceTest.php`, `tests/Feature/KpiMaterializationTest.php`

**Interfaces:**
- Consumes: Task 2 `MachineConnectivity`.
- Produces:
  - `DowntimeService::plannedMaintenanceMinutesWithin(int $work_center_id, DateTimeInterface $from, DateTimeInterface $to): float` (planned-maintenance downtimes clipped to the window).
  - `OeeCalculatorService::availability()` for a connected work center: `busy = calendar - plannedMaintenance` (the `$planned_minutes` argument is the calendar time), `run = busy - unplanned` (clipped), result `run / busy` clamped to [0, 1], 1.0 when `busy <= 0`; an unconnected work center keeps today's formula and numbers.
  - `OeeCalculatorService::hasIncompleteData(int $work_center_id, DateTimeInterface $from, DateTimeInterface $to): bool`: true when a `MachineStateInterval` with state `offline` of the work center overlaps the window.
  - `WorkCenterKpis::$incomplete_data` (bool, last constructor parameter); the materialiser fills it; `WorkCenterKpiStore` cache keys become `mes:kpi:v2:{id}:{date}` (R9); the work center list shows `OEE (today)` with the suffix ` (incomplete)` when the flag is set.

- [ ] **Step 1: Write the failing test.** Known values for a connected work center over a window of 480 calendar minutes (default calendar, one day): no downtime gives 1.0; 60 minutes of breakdown gives `(480-60)/480` = 0.875; 120 minutes of planned maintenance and 60 of breakdown gives busy 360, run 300, 300/360 = 0.8333 (and the unconnected formula on the same data gives (480-60)/480 = 0.875, proving the switch); a breakdown overlapping the window start counts only its part inside; busy time of 0 (planned maintenance covering the whole calendar) gives 1.0; `Offline` intervals are not downtime (an offline stretch changes nothing in availability) but `hasIncompleteData` is true for a window overlapping one and false for one that only touches its end; existing OEE tests stay green untouched for work centers without devices; the materialiser stores `incomplete_data` and the store returns it, and a value cached under the old key is not read.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/OeeCalculatorServiceTest.php Modules/MES/tests/Feature/KpiMaterializationTest.php`. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** the changes. Connectivity is read once per call.
- [ ] **Step 4: Run** the same files, then `php artisan test --compact Modules/MES/tests/Feature/CapacityServiceTest.php Modules/MES/tests/Feature/Filament`. Expected: PASS.
- [ ] **Step 5: Commit and push**: `feat(mes): ISO 22400 availability for connected work centers and the incomplete-data flag`.

---

### Task 7: Filament, documentation, plan closing

**Files:**
- Modify: `app/Filament/Resources/WorkCenters/Schemas/WorkCenterForm.php`, `app/Filament/Resources/Downtimes/Schemas/DowntimeForm.php`, `.../Tables/DowntimesTable.php`, `README.md`, `docs/MACHINE_CONNECTIVITY.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Test: extend `tests/Feature/Filament/MesFilamentPagesTest.php`, `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Produces: the work center form edits `micro_stop_threshold_seconds` (integer, minimum 0) and `downtime_states` (a checkbox list of `MachineState` values); the downtime table shows `source`, the device (`machine_device_id` label through `device.external_id`) and `alarm_code`; the downtime form disables `started_at`, `ended_at` and the work center for a `machine` downtime (cause and notes stay editable), and a manual downtime for a connected work center shows the validation error; documentation of the interval history, the derivation rules, the threshold, the alarm codes (R4), the locked times, the refusal of manual downtimes, `mes:machine-open-stops`, the synthetic Offline, the ISO availability, the incomplete-data flag and R1 to R9 in plain words; glossary terms (State interval, Micro-stop, Connected work center, Machine downtime); an operator section in the Italian guide.

- [ ] **Step 1: Write the failing tests.** Filament: the work center edit page saves a threshold and a changed list of downtime states; a machine downtime's edit page keeps cause and notes editable and rejects a changed `ended_at` (the form field is disabled, and a forced write is refused by the model); the downtime list shows machine rows with their source; creating a manual downtime for a connected work center from the create page shows an error and stores nothing. Documentation test: `docs/MACHINE_CONNECTIVITY.md` contains `mes:machine-open-stops`, `micro_stop_threshold_seconds`, `downtime_states`, `unclassified`, `Offline`, `ISO 22400` and `incomplete`; the README no longer lists the states step in its roadmap.
- [ ] **Step 2: Run** the two files. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** the form and table changes and write the documents (update the "not built yet" statements: states and downtimes are built).
- [ ] **Step 4: Run** the two files, then the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: green (phpstan may still report files outside this plan).
- [ ] **Step 5: Close the plan.** Add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick the boxes, record divergences; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`; update `docs/superpowers/plans/INDEX.md`. Commit and push in `Modules/MES` and in the laraplate repo.

---

## Self-review

- **Spec coverage:** 6.3 (Tasks 1), 9.1 (2 to 5), 10 (6), 11.1 synthetic Offline (5), 13 "States and downtime" (micro-stop threshold, alarm map to cause, `Unclassified`, Offline not a downtime, out-of-order split, manual downtime refused on connected work centers: Tasks 2 to 5) and "OEE" (known-value dataset, existing tests green, availability clipped: Task 6).
- **Spec gaps decided here:** R1 to R9; the heaviest are R2 (one state device per work center), R5 (the unique index on `(work_center_id, started_at)`) and R6 (recompute keeps the operator's cause and notes).
- **Type consistency:** `MachineStateIntervals::observe()/tagAlarm()`, `MachineDowntimeDeriver::sync()`, `DowntimeService::openFromMachine()/closeFromMachine()`, `DowntimeCauseResolver::resolve()`, `MachineConnectivity::isConnected()`, `Downtime::writingAsMachine()` and `OeeCalculatorService::hasIncompleteData()` are named once and used with those names in every task that consumes them.
