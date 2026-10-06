# MES machine data acquisition, step 1: foundation. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a machine source push `laraplate-machine/1` messages into the MES over HTTP, store them durably, process them asynchronously (normalise, resolve, attribute, dispatch typed events) and manage the configuration, unmapped signals, inbox and incidents from Filament. Consumers (states, counts, probes, process values) come in later steps.

**Architecture:** A durable inbox (`mes_machine_messages`) fed by a Sanctum-authenticated HTTP endpoint (the MQTT bridge, step 2, will call the same `MachineMessageInbox::accept()`). A queued `ProcessMachineMessageJob`, serialised per source, runs a `MachineMessageNormalizer` (resolved by a registry), a cached `SignalResolver`, an `OperationAttributor`, and dispatches four typed events that nothing listens to yet. Every write is idempotent so a retry or a reprocess leaves the database as one processing would.

**Tech Stack:** PHP 8.5, Laravel 12, Sanctum 4, Filament 5, Pest 4, nwidart/laravel-modules, Laravel Horizon (queue `mes-machine`). No new Composer dependency.

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (stack root; sections 6 to 8, 11 and 15 step 1 are the ones this plan implements). Module context: `Modules/MES/.cursor/rules/module-context.mdc`.

## Delivery status (2026-10-07): shipped, reviewed once on the whole branch

**Documented in:** `Modules/MES/docs/MACHINE_CONNECTIVITY.md`, `Modules/MES/docs/rag/MODULE.md` and `Modules/MES/README.md`.

All 16 tasks are done (MES suite 478 tests, phpstan clean on the files of this plan). Divergences from the plan, deliberate:

- Tests that need the application (validator, normalisers) live in `tests/Feature/Machine`, not `tests/Unit/Machine`: the test case is bound to Feature and Integration only.
- The configuration models (source, profile, device, signal) extend Core's model and so carry soft delete; the pipeline models (message, unmapped signal, incident) are plain Eloquent models. A deleted signal comes back when its key is mapped again or its profile applied again; the signals relation manager has no trashed filter.
- `MachineMessageProcessor` holds the pipeline; the job keeps retries, status and the failure incident. `OperationAttributor::prepare()` loads a message's candidate operations in one query.
- The per-source rate limit keys on the sha1 of the bearer token (the throttle runs before authentication), and failed authentications are limited per address (30 a minute).
- The job has unlimited releases (`tries` 0, `maxExceptions` 3, `retryUntil` two hours). Order across messages of a source holds only while one worker serves `mes-machine`.
- The watchdog also queues again messages left pending for more than five minutes. `clock_skew` keeps one open incident per source while the skew lasts; notifications are limited to one per source and type every five minutes.
- A profile cannot declare `measurement` signals (R-ruling, Task 13). The profile import takes a file upload and a company.
- Deferred minors: company consistency of source, work center and characteristic; `seq_gap` false positives and no recovery after a sequence reset; MySQL `INSERT IGNORE` hiding non-duplicate errors; the plain token sits in a flashed notification; `reprocessRange` is synchronous; the watchdog does not resolve `device_silent` of a deactivated device; no pruning of incidents.

## Scope of step 1

In: tables `mes_machine_sources`, `mes_machine_profiles`, `mes_machine_devices`, `mes_machine_signals`, `mes_unmapped_signals`, `mes_machine_messages`, `mes_machine_incidents`; protocol schema and fixtures; normalisers `canonical` and `mapped_json`; HTTP ingress; inbox; job; resolver; attributor; unmapped signals; incidents; watchdog; retention of raw messages; Filament for sources, devices, signals, profiles, unmapped signals, inbox and incidents; documentation.

Out, and where it goes: `sparkplug_b` normaliser, the MQTT bridge and its heartbeat (step 2, so the watchdog's `bridge_down` is step 2); state intervals, the synthetic `Offline` interval, `Downtime` changes and the `mes_work_centers` / `mes_downtimes` columns of 6.3 (step 3); counts and the `mes_production_order_operations` columns (step 4); probe measurements, `mes_quality_*` columns and `mes_machine_unattributed_measurements` (step 5); process values and their tables, `raw_retention_days`, `minute_aggregate_retention_days`, `process_store` (step 6). Unattributed-data Filament screens arrive with the step that creates the data.

## Global Constraints

- Every PHP file: `declare(strict_types=1);`, braces on every control structure, explicit parameter and return types, `#[Override]` when overriding, `final` on classes where siblings are final. Code, comments and docs in English.
- All new tables carry `company_id`, use the `mes_` prefix, are registered in `MESTables`; changes to existing MES tables are folded into their create migrations (no alter migrations); migrations stay portable across the five supported drivers (use `MigrateUtils` for timestamps).
- Models extend `Modules\Core\Overrides\Model`, use `Modules\ERP\Concerns\BelongsToCompany` (follow `Modules\MES\Models\WorkCenter`), validate through `getRules()`, and ship a factory in `Modules/MES/database/factories/`.
- Protocol string `laraplate-machine/1`. HTTP: `POST api/v1/mes/machine-data`, route name `mes.api.machine-data.ingest`, bearer Sanctum token of the source with ability `mes:machine-ingest`; the URL carries no source id. Responses: `202` accepted; `200` with `duplicate: true`; `401` invalid token; `403` missing ability or inactive source; `413` too many samples or body too large; `422` invalid envelope; `429` / `5xx` temporary.
- Configuration keys and defaults added by this step: `mes.machine.queue` = `mes-machine`; `mes.machine.max_samples` = 5000; `mes.machine.max_body_kb` = 1024; `mes.machine.clock_skew_seconds` = 30; `mes.machine.inbox_retention_days` = 7.
- No tokens and no full payloads in logs. Authentication failures are logged and rate limited.
- Idempotency: every write uses a natural unique key with insert-or-ignore semantics; aggregates are never incremented. Insert-or-ignore must work on all five drivers; Oracle gets a driver-guarded fallback that treats a unique-key violation as "already stored".
- Tests live in `Modules/MES/tests`; support classes in `Modules/MES/tests/Support` (PSR-4 `Modules\MES\Tests\Support\`, already registered); no class, trait, interface or enum declared inside a test file. No timing-based tests.
- Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress` from the laraplate root before each commit; commits go in the `Modules/MES` submodule. Do not bump the submodule in the monorepo.
- No new Composer dependency without the user's approval.

## Rulings made in this plan (the spec is silent)

- **R1 Envelope validation without a JSON Schema library.** None is installed and adding one needs approval. `MachineEnvelopeValidator` implements the rules with Laravel's validator; the schema file is the published contract; the shared fixtures (valid and invalid envelopes) are what keep the backend and the agent repository, which validates them with a real schema validator, from drifting. If the user approves a validator later, a test can assert schema and validator agree on the fixtures.
- **R2 HTTP is canonical-only.** The endpoint validates the canonical envelope synchronously, so a source whose `normalizer` is not `canonical` or whose `transport` is not `http` gets `403`. Other normalisers are fed by the MQTT bridge (step 2).
- **R3 Message identity of non-canonical payloads.** `mapped_json` has no `message_id` in its payload by default: it is `sha1` of the raw payload (a resent identical payload is a duplicate), or the value at `normalizer_options.message_id_path` when configured.
- **R4 References.** Context `order_ref` is a production order `number`; `operation_ref` is a production order operation id. Both given: the operation must belong to the order, else no attribution. Only `order_ref`: the single in-progress operation of that order on the device's work center, else none. References resolve inside the source's company only.
- **R5 Unmapped counters stay idempotent.** `seen_count` increments only on first processing of a message, never on a reprocess; `last_value` and `last_seen_at` move only forward in `ts`.
- **R6 A raw state value missing from the signal's map** is not an error and not dispatched: it is recorded in `mes_unmapped_signals` under `signal_key` = `{key}#{raw value}`.
- **R7 Birth and death.** `birth` lists the device's signals (`signals: [{signal, data_type, unit?}]`, `data_type` in `number`, `boolean`, `string`); unknown ones become unmapped signals with no value. `death` dispatches `MachineStateObserved` with `MachineState::Offline` on the device's `State` signal (nothing when it has none); the interval logic is step 3.
- **R8 Prunable messages.** Only `processed` messages older than `inbox_retention_days` are pruned; `failed` ones stay.
- **R9 Rate limit.** One extra key, `mes.machine.rate_limit_per_minute` (default 600), limits per source; its `429` carries `Retry-After`.
- **R10 One token per source.** Issuing a token revokes the source's previous ones; the plain token is shown once.

## Review Focus

- **The same message delivered twice at the same time** (agent retry racing the first request): exactly one row and one dispatched job, the second gets `200 duplicate`. Test in Task 6, `concurrent duplicate accept stores one message and dispatches one job`.
- **Two operations in progress on one work center, and late data**: attribution must return none rather than pick one, and a sample older than the arrival by hours must land on the operation active at its `ts`. Tests in Task 9.
- **A raw state value the map does not know** (a new PLC firmware code): the message still processes, the value is visible as unmapped, nothing is dispatched for it. Test in Task 10.
- **A sender clock far ahead of the server, or samples dated in the future**: one `clock_skew` incident per message, processing continues by sample `ts`, a future `ts` attributes to none. Tests in Tasks 6 and 9.
- **A mapping fix followed by "reprocess"**: the sample that was unmapped now resolves, and the reprocess leaves `seen_count` of every other unmapped signal unchanged. Test in Task 11.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `app/Enums/{SignalRole,MachineState,MachineTransport,MachineMessageStatus,MachineIncidentType,SampleQuality}.php` | Value sets. |
| `app/Models/{MachineSource,MachineProfile,MachineDevice,MachineSignal,UnmappedSignal,MachineMessage,MachineIncident}.php` | Eloquent models. |
| `app/Machine/Data/*.php` | DTOs: `NormalizedSample`, `DeviceNotice`, `NormalizedMessage`, `MessageMeta`, `ResolvedSample`, `ResolvedSignal`, `Attribution`, `InboxResult`. |
| `app/Machine/Normalizers/*.php` | `MachineMessageNormalizer` contract, `NormalizerRegistry`, `CanonicalNormalizer`, `MappedJsonNormalizer`, `UnreadableMachinePayload`. |
| `app/Machine/Protocol/MachineEnvelopeValidator.php` | Envelope rules. |
| `app/Machine/{MachineMessageInbox,SignalResolver,UnmappedSignalRecorder,OperationAttributor,MachineIncidentRecorder,MachineMessageReprocessor,MachineProfileService,UnmappedSignalMapper,MachineSourceTokenService,MachineWatchdog}.php` | Pipeline services. |
| `app/Machine/Support/IdempotentWriter.php` | Insert-or-ignore across the five drivers. |
| `app/Jobs/ProcessMachineMessageJob.php` | The queued pipeline. |
| `app/Events/{MachineStateObserved,PartsCounted,ProbeMeasured,ProcessValuesSampled,MachineIncidentRecorded}.php` | Typed events. |
| `app/Http/Controllers/MachineDataIngestController.php`, `app/Http/Middleware/AuthenticateMachineSource.php`, `routes/api.php` | HTTP ingress. |
| `app/Console/{MachineWatchdogCommand}.php`, `app/Listeners/NotifyMachineIncident.php`, `app/Notifications/MachineIncidentNotification.php` | Health and incident notification. |
| `app/Filament/Resources/{MachineSources,MachineDevices,MachineProfiles,UnmappedSignals,MachineMessages,MachineIncidents}/...` | Backoffice, navigation group "Machine connectivity". |
| `resources/protocol/laraplate-machine-1.schema.json` | Published JSON Schema. |
| `tests/Fixtures/machine-protocol/{valid,invalid,normalizers}/` | Shared fixtures. |

---

### Task 1: Enums, table registry, configuration keys

**Files:**
- Create: the six enums above, in `app/Enums/`
- Modify: `app/Enums/MESTables.php` (cases `MachineSources = 'mes_machine_sources'`, `MachineProfiles = 'mes_machine_profiles'`, `MachineDevices = 'mes_machine_devices'`, `MachineSignals = 'mes_machine_signals'`, `UnmappedSignals = 'mes_unmapped_signals'`, `MachineMessages = 'mes_machine_messages'`, `MachineIncidents = 'mes_machine_incidents'`), `config/config.php`
- Test: `tests/Unit/Enums/MachineEnumsTest.php`, `tests/Feature/MachineConfigTest.php`

**Interfaces:**
- Produces: string-backed enums, same shape as `DowntimeCause` (`validationRule(): string`, `values(): list<string>`):
  - `SignalRole`: `State='state'`, `Alarm='alarm'`, `GoodCount='good_count'`, `ScrapCount='scrap_count'`, `TotalCount='total_count'`, `Measurement='measurement'`, `ProcessValue='process_value'`, `OrderReference='order_reference'`, `OperationReference='operation_reference'`; plus `isCount(): bool` (the three counts) and `isReference(): bool`.
  - `MachineState`: `Running`, `Idle`, `Setup`, `Stopped`, `Fault`, `Maintenance`, `Offline` (values lowercase of the names).
  - `MachineTransport`: `Http='http'`, `Mqtt='mqtt'`. `MachineMessageStatus`: `Pending`, `Processed`, `Failed`. `SampleQuality`: `Good`, `Uncertain`, `Bad`.
  - `MachineIncidentType`: `SeqGap='seq_gap'`, `ClockSkew='clock_skew'`, `MessageFailed='message_failed'`, `AuthFailure='auth_failure'`, `BridgeDown='bridge_down'`, `DeviceSilent='device_silent'`.
- Produces: `config('mes.machine.*')` with the five keys of Global Constraints plus `rate_limit_per_minute` (600), read in code with `config()->string()` / `config()->integer()`.

- [x] **Step 1: Write the failing tests.** `MachineEnumsTest`: each enum's `values()` equals the list above; `SignalRole::GoodCount->isCount()` is true and `SignalRole::State->isCount()` false; `MachineState::from('offline')` is `Offline`. `MachineConfigTest`: `config()->string('mes.machine.queue')` is `mes-machine`; `max_samples` 5000; `max_body_kb` 1024; `clock_skew_seconds` 30; `inbox_retention_days` 7; `rate_limit_per_minute` 600; `MESTables::MachineMessages->value` is `mes_machine_messages`.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Unit/Enums/MachineEnumsTest.php Modules/MES/tests/Feature/MachineConfigTest.php`. Expected: FAIL (classes and keys missing).
- [x] **Step 3: Implement** the enums, the `MESTables` cases and a `machine` block in `config/config.php` (each key backed by an env variable `MES_MACHINE_*` with the default above; comment each key as the sibling blocks do).
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit** in `Modules/MES`: `feat(mes): machine connectivity enums, table registry and configuration keys`.

---

### Task 2: Source, profile, device and signal tables and models

**Files:**
- Create: migrations `database/migrations/2026_10_06_000001_create_mes_machine_sources_table.php`, `..._000002_create_mes_machine_profiles_table.php`, `..._000003_create_mes_machine_devices_table.php`, `..._000004_create_mes_machine_signals_table.php`; models `MachineSource`, `MachineProfile`, `MachineDevice`, `MachineSignal`; factories for each in `database/factories/`
- Test: `tests/Feature/Machine/MachineConfigurationModelsTest.php`

**Interfaces:**
- Consumes: Task 1 enums.
- Produces:
  - `MachineSource` (`HasApiTokens`, `BelongsToCompany`): columns `company_id`, `code` (string 64, unique per company), `name`, `normalizer` (string 64, default `canonical`), `transport` (`MachineTransport` cast, default `http`), `mqtt_topic` (nullable string 255), `normalizer_options` (json array cast, nullable), `protocol_version` (string 16, default `1`), `heartbeat_timeout_seconds` (default 120), `last_seen_at`, `last_seq` (nullable unsigned big integer), `is_active` (default true). Relations `devices(): HasMany`. Factory states `inactive()`, `mqtt()`.
  - `MachineProfile` (`BelongsToCompany`): `company_id`, `vendor`, `model`, `version` (string 32), `definition` (json array cast); unique `(company_id, vendor, model, version)`.
  - `MachineDevice` (`BelongsToCompany`): `company_id`, `source_id`, `external_id` (string 128), `work_center_id` (FK `MESTables::WorkCenters`, restrict on delete), `machine_profile_id` (nullable FK, null on delete), `profile_version` (nullable string 32), `last_seen_at`, `is_active`; unique `(source_id, external_id)`. Relations `source()`, `workCenter()`, `profile()`, `signals(): HasMany`.
  - `MachineSignal` (`BelongsToCompany`): `company_id`, `device_id`, `key` (string 128), `role` (`SignalRole` cast), `data_type` (string 16, `number` / `boolean` / `string`), `unit` (nullable string 16), `config` (json array cast, nullable), `quality_plan_characteristic_id` (nullable FK `MESTables::QualityPlanCharacteristics`); unique `(device_id, key)`. `config` rules per role (in `getRules()`): `State` requires `config.map` as an object whose values are `MachineState` values; `Alarm` requires `config.map` whose values are `DowntimeCause` values; counts require `config.mode` in `cumulative`, `delta` and, for `cumulative`, an optional integer `config.rollover_max` greater than zero; `ProcessValue` accepts optional numeric `config.min` and `config.max` with `min` not greater than `max`; the other roles accept `config` null or empty. A `Measurement` signal requires `quality_plan_characteristic_id`; other roles refuse it.

- [x] **Step 1: Write the failing tests** in `MachineConfigurationModelsTest` (use `MesTestHelpers::makeCompany()` and `WorkCenter::factory()`): a source persists with defaults (`normalizer` `canonical`, `heartbeat_timeout_seconds` 120, `is_active` true); two sources with the same `code` in one company are refused (`ValidationException`), different companies are fine; `(source_id, external_id)` unique on devices; `(device_id, key)` unique on signals; signal `config` dataset with `->throws(ValidationException::class)`: state map with an unknown state, alarm map with an unknown cause, count without `mode`, cumulative `rollover_max` 0, process range with `min` > `max`, measurement without a characteristic, a state signal with a characteristic; and valid ones for each role; a source can issue a Sanctum token (`$source->createToken('t', ['mes:machine-ingest'])->plainTextToken` is non-empty and `$source->tokens()->count()` is 1).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineConfigurationModelsTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** migrations (named FKs and indexes `"{$table_name}_..._FK|IDX|UNIQUE"` as `create_mes_shifts_table` does; `MigrateUtils::timestamps()` for timestamps), models and factories with the rules above. Models get `getRules()` per `WorkCenter`; unique rules scope to the company.
- [x] **Step 4: Run** the same command. Expected: PASS. Then `php artisan test --compact Modules/MES/tests/Feature/MasterDataValidationTest.php` to confirm nothing else moved.
- [x] **Step 5: Commit**: `feat(mes): machine source, profile, device and signal models`.

---

### Task 3: Message, unmapped-signal and incident tables and models

**Files:**
- Create: migrations `..._000005_create_mes_machine_messages_table.php`, `..._000006_create_mes_unmapped_signals_table.php`, `..._000007_create_mes_machine_incidents_table.php`; models `MachineMessage`, `UnmappedSignal`, `MachineIncident`; factories
- Test: `tests/Feature/Machine/MachinePipelineModelsTest.php`

**Interfaces:**
- Consumes: Task 2 models.
- Produces:
  - `MachineMessage` (`BelongsToCompany`, `MassPrunable`): `company_id`, `source_id` (FK, cascade), `message_id` (string 128), `source_seq` (nullable unsigned big integer), `transport` (`MachineTransport` cast), `payload` (long text), `received_at` (datetime), `status` (`MachineMessageStatus` cast, default `pending`, indexed), `error` (nullable text), `attempts` (unsigned integer, default 0), `processed_at` (nullable); unique `(source_id, message_id)`; index `(source_id, received_at)`. `prunable(): Builder` = `status = processed` and `received_at` older than `mes.machine.inbox_retention_days` days (R8). Factory states `processed()`, `failed()`.
  - `UnmappedSignal` (`BelongsToCompany`): `company_id`, `source_id`, `device_external_id` (string 128), `signal_key` (string 160), `last_value` (nullable text), `last_seen_at`, `seen_count` (unsigned integer, default 0); unique `(source_id, device_external_id, signal_key)`.
  - `MachineIncident` (`BelongsToCompany`): `company_id`, `source_id`, `device_id` (nullable FK, null on delete), `type` (`MachineIncidentType` cast), `detail` (json array cast), `occurred_at`, `resolved_at` (nullable); index `(source_id, type, resolved_at)`. Scope `unresolved()`.

- [x] **Step 1: Write the failing tests** in `MachinePipelineModelsTest`: duplicate `(source_id, message_id)` raises `Illuminate\Database\UniqueConstraintViolationException` on a raw insert; `MachineMessage::query()->prunable()` returns a processed message older than 7 days, and does not return a failed one of the same age nor a processed one from yesterday (use `config(['mes.machine.inbox_retention_days' => 7])`); `MachineIncident::unresolved()` excludes rows with `resolved_at`; unmapped unique key.
- [x] **Step 2: Run** the file. Expected: FAIL.
- [x] **Step 3: Implement** migrations, models, factories. Timestamps through `MigrateUtils::timestamps()`.
- [x] **Step 4: Run** the file. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine message, unmapped signal and incident models`.

---

### Task 4: Protocol schema, fixtures, envelope validator, DTOs

**Files:**
- Create: `resources/protocol/laraplate-machine-1.schema.json`; `app/Machine/Protocol/MachineEnvelopeValidator.php`; `app/Machine/Data/{NormalizedSample,DeviceNotice,NormalizedMessage,MessageMeta}.php`; fixtures under `tests/Fixtures/machine-protocol/valid/` and `.../invalid/`
- Test: `tests/Unit/Machine/MachineEnvelopeValidatorTest.php`

**Interfaces:**
- Consumes: `SampleQuality`.
- Produces:
  - `MachineEnvelopeValidator::validate(array $envelope): array<string, list<string>>` (laravel error bag as array; empty = valid) and `sampleCount(array $envelope): int`.
  - `NormalizedSample` (readonly): `string $device`, `string $signal`, `CarbonImmutable $ts` (UTC, millisecond precision), `int|float|bool|string $value`, `SampleQuality $quality`, `array<string, string> $context`.
  - `DeviceNotice` (readonly): `string $device`, `string $type` (`birth` or `death`), `CarbonImmutable $ts`, `list<array{signal: string, data_type: string, unit: ?string}> $signals`.
  - `NormalizedMessage` (readonly): `list<NormalizedSample> $samples`, `list<DeviceNotice> $notices`.
  - `MessageMeta` (readonly): `string $message_id`, `?int $source_seq`, `?CarbonImmutable $sent_at`.
- The envelope rules (the fixtures name each): `protocol` equals `laraplate-machine/1`; `message_id` string 1 to 128 chars; `source_seq` integer at least 0; `sent_at` ISO 8601 date-time; `devices` array of at least 1; per device: `device` string 1 to 128, `type` in `data`, `birth`, `death`; `data` needs `samples` (array of at least 1), `birth` needs `signals`, `death` carries neither; per sample: `signal` string 1 to 160, `ts` ISO 8601 date-time, `value` number, boolean or string (never null, array or object), `quality` optional in `good`, `uncertain`, `bad`, `context` optional object whose keys are only `order_ref`, `operation_ref`, `serial`, `lot`, all strings.

- [x] **Step 1: Write the failing test and fixtures.** Valid fixtures: `minimal-data.json`, `birth-and-death.json`, `with-context.json` (the example of spec 7.1 verbatim). Invalid fixtures, one defect each: `wrong-protocol`, `missing-message-id`, `negative-seq`, `bad-sent-at`, `empty-devices`, `unknown-type`, `data-without-samples`, `unknown-quality`, `bad-ts`, `array-value`, `null-value`, `unknown-context-key`, `numeric-context-value`. The test loads every file with `glob` and asserts `validate()` is empty for `valid/*` and non-empty for each `invalid/*` (dataset keyed by file name). Add a test that the schema file decodes to JSON with `$id` and `properties.protocol.const` equal to `laraplate-machine/1`, and one that `sampleCount()` of the 7.1 example is 3.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Unit/Machine/MachineEnvelopeValidatorTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the validator (R1) with `Illuminate\Support\Facades\Validator`, the four DTOs, and the schema file (draft 2020-12, the same rules; `$id` `https://laraplate.dev/schemas/laraplate-machine-1.schema.json`).
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): laraplate-machine/1 schema, fixtures and envelope validator`.

---

### Task 5: Normalisers

**Files:**
- Create: `app/Machine/Normalizers/{MachineMessageNormalizer,NormalizerRegistry,CanonicalNormalizer,MappedJsonNormalizer,UnreadableMachinePayload}.php`; fixtures `tests/Fixtures/machine-protocol/normalizers/{canonical,mapped_json}/*.input.json` with `*.expected.json`
- Modify: `app/Providers/MESServiceProvider.php` (singleton `NormalizerRegistry` with both built-ins registered)
- Test: `tests/Unit/Machine/NormalizersTest.php`

**Interfaces:**
- Consumes: Task 4 DTOs, `MachineSource`.
- Produces:
  - `interface MachineMessageNormalizer { public function key(): string; public function meta(MachineSource $source, string $payload): MessageMeta; public function normalize(MachineSource $source, string $payload): NormalizedMessage; }`; both throw `UnreadableMachinePayload` (extends `RuntimeException`) on a payload they cannot read.
  - `NormalizerRegistry::register(MachineMessageNormalizer $normalizer): void`, `for(MachineSource $source): MachineMessageNormalizer` (throws `InvalidArgumentException` naming the key when unknown), `keys(): list<string>`.
  - `CanonicalNormalizer` (`canonical`): decodes the envelope; `meta()` reads `message_id`, `source_seq`, `sent_at`; `normalize()` maps `data` samples (quality default `good`, context copied) and `birth`/`death` to `DeviceNotice` (a death notice has `signals` empty, `ts` = `sent_at`).
  - `MappedJsonNormalizer` (`mapped_json`), configured by `normalizer_options` (R3): `samples_path` (optional dot path to a list; absent means the root object is one sample), and relative to each sample `device`, `signal`, `timestamp`, `value`, optional `quality`, optional `quality_map` (raw => `good`/`uncertain`/`bad`), `timestamp_format` (`iso8601` default, `unix`, `unix_ms`), `message_id_path` (optional). Values read with `data_get`. A sample missing device, signal, timestamp or value is skipped, not fatal; a payload that is not JSON, or whose `samples_path` is not a list, is unreadable. `meta()`: `message_id` per R3, `source_seq` null, `sent_at` null.

- [x] **Step 1: Write the failing test.** For each fixture pair the test runs the normaliser on `*.input.json` and compares the resulting list (`toArray` of DTOs: device, signal, ts as ISO string with milliseconds, value, quality, context) with `*.expected.json`. Cases: canonical data with context; canonical birth plus death; mapped_json single object with `unix_ms` timestamps; mapped_json with `samples_path` `data.points` and a `quality_map`; a mapped_json sample missing its value is skipped. Plus: unreadable JSON throws `UnreadableMachinePayload` for both; `meta()` of mapped_json without `message_id_path` equals `sha1` of the payload and equal payloads give equal ids; `NormalizerRegistry::for()` on an unknown key throws `InvalidArgumentException`; a custom normaliser registered with `register()` is returned by `for()` (use a stub in `tests/Support/StubNormalizer.php`).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Unit/Machine/NormalizersTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the contract, registry, both normalisers and the provider registration.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine message normaliser contract, registry, canonical and mapped_json`.

---

### Task 6: Idempotent writer, incident recorder, inbox

**Files:**
- Create: `app/Machine/Support/IdempotentWriter.php`, `app/Machine/MachineIncidentRecorder.php`, `app/Machine/MachineMessageInbox.php`, `app/Machine/Data/InboxResult.php`, `app/Events/MachineIncidentRecorded.php`
- Test: `tests/Feature/Machine/MachineMessageInboxTest.php`, `tests/Unit/Machine/IdempotentWriterTest.php`

**Interfaces:**
- Consumes: Tasks 2, 3 and 5.
- Produces:
  - `IdempotentWriter::insert(ConnectionInterface $connection, string $table, array $row): bool`: true when the row was stored, false when a unique key already held it. Uses `insertOrIgnore` on `mysql`, `mariadb`, `pgsql`, `sqlite`, `sqlsrv`; on `oracle` (or any other driver name) runs `insert` and returns false on `UniqueConstraintViolationException`.
  - `MachineIncidentRecorder::record(MachineSource $source, MachineIncidentType $type, array $detail = [], ?MachineDevice $device = null, ?CarbonInterface $at = null): MachineIncident` (always creates one, dispatches `MachineIncidentRecorded(int $company_id, int $incident_id)`); `recordOnce(...)` same parameters, returns the existing unresolved incident of the same `(source, device, type)` instead of creating a second one; `resolve(MachineSource $source, MachineIncidentType $type, ?MachineDevice $device = null): int` closes unresolved ones, returns how many.
  - `InboxResult` (readonly): `bool $duplicate`, `MachineMessage $message`.
  - `MachineMessageInbox::accept(MachineSource $source, string $payload, MachineTransport $transport): InboxResult`. Steps: `meta` from the source's normaliser; store the row through `IdempotentWriter` (`status` `pending`, `received_at` now, `payload` raw); on a duplicate return the existing row with `duplicate: true` and dispatch nothing; on a new row, update `source.last_seen_at`, detect a gap (`source_seq` greater than `last_seq + 1` records `seq_gap` with `detail` `{expected, received}`), advance `last_seq` only forward through a conditional update, record `clock_skew` (`detail` `{sent_at, received_at, skew_seconds}`) when `sent_at` differs from now by more than `mes.machine.clock_skew_seconds`, then dispatch `ProcessMachineMessageJob` (Task 10) on the `mes.machine.queue` queue and `mes.queue.connection`.
- Note: `ProcessMachineMessageJob` does not exist until Task 10; create it here as a stub class with the final constructor `(public int $machine_message_id, public int $source_id, public bool $reprocess = false)` and an empty `handle()`, so this task is self-contained; Task 10 fills it.

- [x] **Step 1: Write the failing tests.** `IdempotentWriterTest`: inserting the same unique row twice returns true then false and leaves one row (sqlite, a real table `mes_machine_messages`); with a partial mock of the connection whose `getDriverName()` returns `oracle` and whose `insert` throws `UniqueConstraintViolationException`, the writer returns false and does not call `insertOrIgnore`. `MachineMessageInboxTest` (`Queue::fake()`): a new message stores one pending row and pushes one job on the `mes-machine` queue; the same payload again returns `duplicate: true`, still one row, still one job; `concurrent duplicate accept stores one message and dispatches one job` (accept, then simulate the racing loser by pre-inserting the row through `IdempotentWriter` between meta and insert: use a stub normaliser from `tests/Support` that inserts the same `message_id` during `meta()`), asserting one row and no job from the loser; `source_seq` 5 then 8 records one `seq_gap` with expected 6 and received 8, and `last_seq` 8; a lower `source_seq` after 8 records nothing and keeps `last_seq` 8; `sent_at` 5 minutes ahead records one `clock_skew` while the message is still accepted; `sent_at` within the tolerance records none; `MachineIncidentRecorder::recordOnce` twice yields one row and `resolve` sets `resolved_at`.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Unit/Machine/IdempotentWriterTest.php Modules/MES/tests/Feature/Machine/MachineMessageInboxTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the classes above.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine message inbox with idempotent insert, gap and clock skew incidents`.

---

### Task 7: HTTP ingress

**Files:**
- Create: `app/Machine/MachineSourceTokenService.php`, `app/Http/Middleware/AuthenticateMachineSource.php`, `app/Http/Controllers/MachineDataIngestController.php`
- Modify: `routes/api.php`, `app/Providers/MESServiceProvider.php` (rate limiter `mes-machine-ingest`)
- Test: `tests/Feature/Machine/MachineDataIngestTest.php`

**Interfaces:**
- Consumes: Task 4 validator, Task 6 inbox and recorder.
- Produces:
  - `MachineSourceTokenService::issue(MachineSource $source): string` (revokes the source's tokens, creates one with ability `mes:machine-ingest`, returns the plain text, R10); `revoke(MachineSource $source): int`.
  - `AuthenticateMachineSource` middleware: reads the bearer token, finds it with `Laravel\Sanctum\PersonalAccessToken::findToken()`; missing or unknown token or a tokenable that is not a `MachineSource`: `401`, logged with `Log::warning` (no token in the context) at most once a minute per IP; a source whose token lacks the ability or that is inactive: `403`, one `auth_failure` incident per source per 5 minutes (`Cache::add` guard); on success sets `$request->attributes->set('machine_source', $source)` and touches the token's `last_used_at`.
  - `MachineDataIngestController::__invoke(Request $request, MachineEnvelopeValidator $validator, MachineMessageInbox $inbox): JsonResponse`. Order: source must have `transport` `http` and `normalizer` `canonical` else `403` (R2); body larger than `mes.machine.max_body_kb` KB: `413`; invalid JSON or invalid envelope: `422` with `{message, errors}`; more than `mes.machine.max_samples` samples: `413`; then `accept()`; `202` `{status: "accepted", message_id}` or `200` `{status: "duplicate", duplicate: true, message_id}`.
  - Route `Route::post('mes/machine-data', MachineDataIngestController::class)->middleware([AuthenticateMachineSource::class, 'throttle:mes-machine-ingest'])->name('machine-data.ingest');` giving `api/v1/mes/machine-data`, route name `mes.api.machine-data.ingest`. Limiter: `Limit::perMinute(config()->integer('mes.machine.rate_limit_per_minute'))->by(source id)`.

- [x] **Step 1: Write the failing tests** (`Queue::fake()`; send with `$this->postJson(route('mes.api.machine-data.ingest'), $envelope, ['Authorization' => 'Bearer '.$token])`): the valid fixture returns `202` and stores one pending message; the same body returns `200` with `duplicate` true; no token and an unknown token return `401`; a token created with another ability returns `403`; an inactive source returns `403`; a source with `transport` `mqtt` or `normalizer` `mapped_json` returns `403`; an invalid envelope returns `422` with an `errors` key; a body over `max_body_kb` (set to 1 in the test) returns `413`; a 3-sample envelope with `max_samples` 2 returns `413`; the 601st request in a minute returns `429` with a `Retry-After` header (set `rate_limit_per_minute` to 2); `Log::spy()` after a `403` and a `401` shows no log context containing the plain token nor the payload; `route('mes.api.machine-data.ingest', absolute: false)` equals `/api/v1/mes/machine-data`; `issue()` twice leaves one token and the first plain token no longer authenticates.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineDataIngestTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the three classes, the route and the limiter.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine data HTTP ingress with per-source tokens and limits`.

---

### Task 8: Signal resolver and unmapped signals

**Files:**
- Create: `app/Machine/SignalResolver.php`, `app/Machine/UnmappedSignalRecorder.php`, `app/Machine/Data/ResolvedSignal.php`, `app/Observers/MachineConfigurationObserver.php`
- Modify: `app/Providers/MESServiceProvider.php` (observe `MachineDevice` and `MachineSignal`)
- Test: `tests/Feature/Machine/SignalResolverTest.php`

**Interfaces:**
- Consumes: Tasks 2 and 3.
- Produces:
  - `ResolvedSignal` (readonly): `MachineDevice $device`, `MachineSignal $signal`, `int $work_center_id`, `int $company_id`.
  - `SignalResolver::resolve(MachineSource $source, string $device_external_id, string $signal_key): ?ResolvedSignal` (null when the device or the signal is unknown or the device inactive); `forget(int $source_id): void`. The per-source map (devices with their signals) is loaded in one pass and cached under `mes:machine:map:{source_id}`; saving or deleting a `MachineDevice` or `MachineSignal` forgets it.
  - `UnmappedSignalRecorder::record(MachineSource $source, string $device_external_id, string $signal_key, ?string $value, CarbonImmutable $ts, bool $count): void`: upserts the `(source, device, key)` row through one statement; `last_value` and `last_seen_at` only move forward in `ts`; `seen_count` increases by one only when `$count` is true (R5). `recordMany(MachineSource $source, list<array{device: string, key: string, value: ?string, ts: CarbonImmutable}> $entries, bool $count): void` does the same in one batched statement (the job uses it).

- [x] **Step 1: Write the failing tests:** an existing device and signal resolve to the right `ResolvedSignal`; an unknown device, an unknown signal and an inactive device resolve to null; the second `resolve()` of any key of the source issues no query (assert with `DB::getQueryLog()` empty); saving a signal invalidates the map (a signal added after the first resolve resolves afterwards); `record()` creates a row with `seen_count` 1, again with the same `ts` and `$count` true makes 2; with `$count` false it stays; an older `ts` does not overwrite `last_value`; `recordMany()` of 100 entries runs in at most 2 queries.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/SignalResolverTest.php`. Expected: FAIL.
- [x] **Step 3: Implement.** The conditional "only forward" update uses `upsert` with a `CASE`-free approach portable to the five drivers: read the existing rows for the batch in one query, compute the merged values in PHP, then `upsert` them (unique keys `source_id`, `device_external_id`, `signal_key`).
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine signal resolver and unmapped signal recorder`.

---

### Task 9: Operation attributor

**Files:**
- Create: `app/Machine/OperationAttributor.php`, `app/Machine/Data/Attribution.php`
- Test: `tests/Feature/Machine/OperationAttributorTest.php`

**Interfaces:**
- Consumes: `ResolvedSignal`, `NormalizedSample`, production order operations.
- Produces:
  - `Attribution` (readonly): `?int $production_order_operation_id`, `string $reason` (`explicit`, `single_active`, `none`).
  - `OperationAttributor::attribute(ResolvedSignal $target, NormalizedSample $sample, ?string $order_reference = null, ?string $operation_reference = null): Attribution`. `$order_reference` / `$operation_reference` are the values of the device's `OrderReference` / `OperationReference` signals as seen in the same message; the sample's own `context.order_ref` / `context.operation_ref` win over them. Rules (R4, spec 8.2): explicit reference first; otherwise the single operation on `$target->work_center_id` with `actual_start_at <= ts` and (`actual_end_at` null or `> ts`); zero or more than one such operation: none. Only operations of orders of `$target->company_id`. Uses the sample `ts`, never the clock.

- [x] **Step 1: Write the failing tests:** one in-progress operation on the work center attributes with `single_active`; `ts` before its `actual_start_at` attributes none; a completed operation attributes only for a `ts` inside its start and end (late data: an operation that ended an hour ago, a sample from 2 hours ago); two operations active at the same `ts` give none (not the first); `context.operation_ref` naming an operation of the company wins over the single active one (`explicit`); `operation_ref` of another company's operation gives none; `order_ref` equal to an order number picks that order's single in-progress operation on the work center, and another order's number picks none; `order_ref` plus a mismatching `operation_ref` gives none; the `OrderReference` signal value is used when the sample has no context; a `ts` one day in the future gives none.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/OperationAttributorTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** with one query per call at most (the job caches nothing here).
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine sample operation attribution`.

---

### Task 10: The job and the typed events

**Files:**
- Create: `app/Events/{MachineStateObserved,PartsCounted,ProbeMeasured,ProcessValuesSampled}.php`, `app/Machine/Data/ResolvedSample.php`
- Modify: `app/Jobs/ProcessMachineMessageJob.php` (fill the Task 6 stub)
- Test: `tests/Feature/Machine/ProcessMachineMessageJobTest.php`

**Interfaces:**
- Consumes: Tasks 5, 8, 9, 6 (`MachineIncidentRecorder`).
- Produces:
  - `ResolvedSample` (readonly): `MachineDevice $device`, `MachineSignal $signal`, `NormalizedSample $sample`, `?int $production_order_operation_id`, `?MachineState $state` (State role samples, mapped), `?string $alarm_code` (Alarm role samples).
  - Four events, each readonly with `int $company_id`, `int $device_id`, `int $work_center_id`, `list<ResolvedSample> $samples` ordered by `ts`, `Dispatchable`, `SerializesModels`: `MachineStateObserved` (State and Alarm roles), `PartsCounted` (GoodCount, ScrapCount, TotalCount), `ProbeMeasured` (Measurement), `ProcessValuesSampled` (ProcessValue).
  - `ProcessMachineMessageJob` (`ShouldQueue`): `public int $tries = 3`; `middleware()` returns `[new WithoutOverlapping((string) $this->source_id)->releaseAfter(30)->expireAfter(300)]`; queue and connection from `mes.machine.queue` / `mes.queue.connection`. `handle(NormalizerRegistry $registry, SignalResolver $resolver, OperationAttributor $attributor, UnmappedSignalRecorder $unmapped, MachineIncidentRecorder $incidents): void`: loads the message, increments `attempts`, normalises (an `UnreadableMachinePayload` marks the message `failed` with the reason and returns without retrying), resolves every sample, records unknown devices and signals through `recordMany()` with `count` = `! $this->reprocess` (R5), drops `bad`-quality samples, applies R6 to State samples (map through `config.map`; value absent from the map is recorded as unmapped under `{key}#{value}`), attributes with the device's reference-signal values of the same message, handles birth (R7: unknown signals to unmapped) and death (R7: `MachineStateObserved` with `MachineState::Offline` on the device's State signal), updates `last_seen_at` of source and devices forward only, dispatches the four events (grouped by device and role, skipping empty groups; OrderReference and OperationReference samples are not dispatched), then sets `status` `processed`, `processed_at`, clears `error`. `failed(Throwable $e): void` sets `status` `failed` with the message text and records `message_failed` (`detail` `{machine_message_id, error}`) through `recordOnce`.

- [x] **Step 1: Write the failing tests** (`Event::fake` of the four events; build messages from the valid fixtures and a configured device with State, GoodCount and Measurement signals): a message with a state, a count and a measurement dispatches one event of each class with the right samples and a mapped `state`; a `bad`-quality sample is not dispatched; an unknown device and an unknown signal each create an unmapped row (`seen_count` 1) and no event; a raw state value outside the map is processed, recorded as unmapped `state#RAW`, and nothing is dispatched for it; an `OrderReference` signal sample attributes the other samples of the message and is not dispatched itself; a death notice dispatches `MachineStateObserved` with `Offline`; a birth notice with an unknown signal creates an unmapped row with no value; an unreadable payload ends `failed` with a reason and no exception; a thrown exception from a listener marks the message `failed` through `failed()` and records one `message_failed` incident; `last_seen_at` of the source and device is set; processing the same message twice (second run `reprocess: true`) leaves `mes_unmapped_signals` and `mes_machine_incidents` identical (snapshot `toArray()` comparison) and the status `processed`; `a 5000-sample message uses the same number of queries as a 50-sample one` (log queries for both runs with sample values spread over 3 devices and equal signal sets; assert equal counts).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/ProcessMachineMessageJobTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the job and events; the dispatch groups by role once and the resolver map is read once per job.
- [x] **Step 4: Run** the same command. Expected: PASS. Also run `php artisan test --compact Modules/MES/tests/Feature/Machine` to confirm the earlier tasks still pass with the filled job.
- [x] **Step 5: Commit**: `feat(mes): machine message processing job and typed machine events`.

---

### Task 11: Reprocessing, token actions and permissions

**Files:**
- Create: `app/Machine/MachineMessageReprocessor.php`
- Modify: `app/Authorization/MESPermissions.php`, `app/Policies/MesModelPolicy.php`, `app/Services/DomainActions/MesDomainActionRegistrar.php`, `app/Providers/MESServiceProvider.php` (add the new models to `policyModels()`)
- Test: `tests/Feature/Machine/MachineReprocessingTest.php`; extend `tests/Feature/MesModelPolicyTest.php`, `tests/Feature/DomainActionRegistrationTest.php`

**Interfaces:**
- Consumes: Task 10 job, Task 7 `MachineSourceTokenService`.
- Produces:
  - `MachineMessageReprocessor::reprocess(MachineMessage $message): MachineMessage` (sets `status` `pending`, `error` null, dispatches `ProcessMachineMessageJob` with `reprocess: true` on the machine queue); `reprocessRange(MachineSource $source, CarbonInterface $from, CarbonInterface $to): int` (every message of the source with `received_at` in the range, oldest first; returns the count; dispatches one job per message so per-source serialisation keeps their order).
  - Domain operations added to `MESPermissions::operations()`: `MachineMessage::class => ['reprocess']`, `MachineSource::class => ['issue_token', 'revoke_token', 'reprocess_range']`, `MachineDevice::class => ['apply_profile']`, `MachineProfile::class => ['export']`. Registrar handlers: `MachineMessage` `reprocess`; `MachineSource` `issue_token` (returns the source; the plain token is returned in the response payload key `token` by returning an array `['token' => ...]`, like the `Bom` `explode` handler returns an array), `revoke_token`, `reprocess_range` (payload `from`, `to`); `MachineDevice` `apply_profile` (payload `profile_id`, calls `MachineProfileService::apply`, Task 13: register the handler in Task 13); `MachineProfile` `export` (Task 13).
  - `MesModelPolicy` methods `reprocess` (any message), `issueToken` (source active), `revokeToken`, `reprocessRange`, `applyProfile`, `export`, each through `allowsDomainAction` with the matching permission name; the permission names are the snake_case operations above.

- [x] **Step 1: Write the failing tests.** `MachineReprocessingTest` (`Queue::fake()`): `reprocess()` of a failed message sets it pending with `error` null and pushes one job with `reprocess` true; `reprocessRange()` returns the count of messages of that source inside the range only and pushes jobs oldest first; `a mapping fix followed by reprocess`: process a message that carries an unmapped signal `s1` and an unrelated unmapped signal `s2` (`seen_count` 1 each), add `s1` as a configured signal, reprocess the message synchronously (`Queue::fake` off, `mes.queue.connection` `sync`): `s1` now produces its event, `s2` keeps `seen_count` 1. Policy tests (dataset rows in `MesModelPolicyTest`): `reprocess` allowed for a superadmin on a message; `issueToken` allowed on an active source and refused on an inactive one; a user without the permission is refused; the seeded-permissions test already covers the new names, assert `MESPermissions::operations()` contains them. Registration test: the new `(model, action)` pairs resolve in `DomainActionRegistry` (except `apply_profile` and `export`, added in Task 13).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineReprocessingTest.php Modules/MES/tests/Feature/MesModelPolicyTest.php Modules/MES/tests/Feature/DomainActionRegistrationTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the reprocessor, the permissions, policy methods, handlers and policy-model registration.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine message reprocessing, token actions and permissions`.

---

### Task 12: Watchdog, incident notification, retention schedule

**Files:**
- Create: `app/Machine/MachineWatchdog.php`, `app/Console/MachineWatchdogCommand.php`, `app/Listeners/NotifyMachineIncident.php`, `app/Notifications/MachineIncidentNotification.php`
- Modify: `app/Providers/MESServiceProvider.php` (listener, schedules), `config/config.php` (`notifications.machine_incident` block, same shape as `capacity_overload`)
- Test: `tests/Feature/Machine/MachineWatchdogTest.php`

**Interfaces:**
- Consumes: Task 6 recorder and `MachineIncidentRecorded`, Task 10 `last_seen_at`.
- Produces:
  - `MachineWatchdog::sweep(?CarbonImmutable $now = null): int`: for every active device of an active source whose `last_seen_at` is older than its source's `heartbeat_timeout_seconds` (or null and the device older than that timeout), `recordOnce(DeviceSilent, detail {device, silent_seconds})`; for a device seen again, `resolve(DeviceSilent)` closes the incident. Returns the number of incidents opened. Command `mes:machine-watchdog` calls it and prints the count. `bridge_down` and the synthetic `Offline` interval are not here (steps 2 and 3).
  - `NotifyMachineIncident` (queued like `NotifyCapacityOverload`) sends `MachineIncidentNotification` to the roles under `mes.notifications.machine_incident.recipients.roles` over `...channels` (defaults `['database']`, roles `['admin', 'superadmin']`); `toArray` carries `type`, `company_id`, `source_id`, `device_id`, `detail`, `occurred_at`.
  - Schedules (in `registerCommandSchedules()` next to the KPI schedule): `mes:machine-watchdog` every minute, `withoutOverlapping()->onOneServer()`; `model:prune --model=MachineMessage::class` daily, `onOneServer()`.

- [x] **Step 1: Write the failing tests** (`Carbon::setTestNow`): a device silent beyond the timeout opens one `device_silent` incident and a second sweep opens none; the device sending again then a sweep resolves it; an inactive device or source is ignored; a device never seen and created more than the timeout ago is silent, one created a minute ago is not; the sweep honours each source's own timeout; `Notification::fake()`: recording an incident notifies the configured role and records nothing for a user without it; the schedule lists `mes:machine-watchdog` every minute and `model:prune` for `MachineMessage` (`Schedule` events via `app(Schedule::class)->events()`).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineWatchdogTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the sweep with one query for devices and one for open incidents.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine watchdog, incident notification and inbox pruning schedule`.

---

### Task 13: Profiles: apply, import, export

**Files:**
- Create: `app/Machine/MachineProfileService.php`
- Modify: `app/Services/DomainActions/MesDomainActionRegistrar.php` (`MachineDevice` `apply_profile`, `MachineProfile` `export`)
- Test: `tests/Feature/Machine/MachineProfileServiceTest.php`

**Interfaces:**
- Consumes: Task 2 models, Task 11 operations and policy.
- Produces:
  - Profile `definition` format (json): `{"signals": [{"key", "role", "data_type", "unit"?, "config"?}], "state_map"?: {raw: state}, "alarm_map"?: {code: cause}, "default_causes"?: {state: cause}}`. `state_map` and `alarm_map` are copied into the `config.map` of the profile's State and Alarm signals that have no map of their own.
  - `MachineProfileService::apply(MachineDevice $device, MachineProfile $profile): MachineDevice`: copies the profile's signals onto the device inside a transaction (insert missing keys, leave existing keys and their local edits untouched), sets `machine_profile_id` and `profile_version`, and clears the signal map cache. Returns the refreshed device.
  - `export(MachineProfile $profile): string` (pretty JSON `{vendor, model, version, definition}`) and `import(int $company_id, string $json): MachineProfile` (validates the structure and every signal through `MachineSignal` rules; an existing `(vendor, model, version)` is refused with `ValidationException`; atomic).
  - Registrar: `MachineDevice` `apply_profile` (payload `profile_id`) and `MachineProfile` `export` (returns `['json' => ...]`); the Task 11 registration test now also asserts both.

- [x] **Step 1: Write the failing tests:** applying a profile with three signals creates three device signals and records the version; applying again changes nothing; a locally edited signal (changed `unit`) survives a second apply; the `state_map` lands in the State signal config; a profile with an invalid signal role or a malformed map fails `import()` with `ValidationException` and creates no profile; `import(export($p))` into another company reproduces an equal definition and refuses the same `(vendor, model, version)` twice; applying a profile to a device invalidates the resolver map (resolve a new key after apply).
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineProfileServiceTest.php Modules/MES/tests/Feature/DomainActionRegistrationTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the service and the two handlers.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine profile apply, import and export`.

---

### Task 14: Filament: sources, devices with signals, profiles

**Files:**
- Create: `app/Filament/Resources/MachineSources/`, `.../MachineDevices/` (with `RelationManagers/SignalsRelationManager.php`), `.../MachineProfiles/`, each with `Pages/`, `Schemas/`, `Tables/` like `Shifts/`
- Test: `tests/Feature/Filament/MesMachineFilamentTest.php`

**Interfaces:**
- Consumes: Tasks 2, 7, 11, 13; the order page actions in `EditProductionOrder` are the pattern for header actions (policy called directly through `MesModelPolicy`, refused `DomainException` or `ValidationException` shown as a notification).
- Produces: resources with slugs `mes/machine-sources`, `mes/machine-devices`, `mes/machine-profiles`, navigation group `Machine connectivity` (declared in `MESPlugin::afterRegister()` beside the MES group, icon `Heroicon::OutlinedSignal`).
  - Sources: form `code`, `name`, `normalizer` (select of `NormalizerRegistry::keys()`), `transport`, `mqtt_topic` (visible for `mqtt`), `normalizer_options` (key-value), `heartbeat_timeout_seconds`, `is_active`; table `code`, `name`, `transport`, `last_seen_at`, `is_active`. Edit-page header actions: `issue_token` (confirmation; on success a persistent notification with the plain token and the sentence that it is shown once) and `revoke_token`, visible by `MesModelPolicy` (`issueToken`, `revokeToken`); `reprocess_range` (form `from`, `to`) calling the reprocessor.
  - Devices: form `source_id`, `external_id`, `work_center_id`, `machine_profile_id`, `is_active`; `SignalsRelationManager` edits `key`, `role`, `data_type`, `unit`, `config` (JSON text area validated through the model rules), `quality_plan_characteristic_id`; header action `apply_profile` (select a profile) calling `MachineProfileService`.
  - Profiles: form `vendor`, `model`, `version`, `definition` (JSON text area); list header action `import` (file upload, `MachineProfileService::import`) and edit header action `export` (download).

- [x] **Step 1: Write the failing tests** in `MesMachineFilamentTest` as a superadmin (copy the `beforeEach` of `MesFilamentPagesTest`): each of the three list pages renders; creating a source through `CreateMachineSource` persists it; `issue_token` on a source's edit page shows a notification and leaves exactly one token, and the plain text is not stored anywhere in the database (assert `personal_access_tokens.token` differs from the notified text); `revoke_token` removes it; a user without permission does not see either action; `apply_profile` on a device creates the signals; `import` of a valid exported file creates a profile, an invalid file notifies and creates none; the signals relation manager lists a device's signals and refuses an invalid `config`.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Filament/MesMachineFilamentTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** resources, pages, relation manager and actions.
- [x] **Step 4: Run** the same command, then `php artisan test --compact Modules/MES/tests/Feature/Filament`. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): machine connectivity backoffice for sources, devices and profiles`.

---

### Task 15: Filament: unmapped signals, inbox, incidents

**Files:**
- Create: `app/Machine/UnmappedSignalMapper.php`; `app/Filament/Resources/UnmappedSignals/`, `.../MachineMessages/`, `.../MachineIncidents/`
- Test: `tests/Feature/Machine/UnmappedSignalMapperTest.php`, extend `tests/Feature/Filament/MesMachineFilamentTest.php`

**Interfaces:**
- Consumes: Tasks 3, 8, 11, 14.
- Produces:
  - `UnmappedSignalMapper::map(UnmappedSignal $unmapped, array $attributes): MachineSignal`, `$attributes` being `work_center_id` (needed when the device does not exist yet, creating it under the unmapped row's source), `role`, `data_type`, `unit?`, `config?`, `quality_plan_characteristic_id?`. Creates the device when missing and the signal, validates through the model rules, deletes the unmapped row, and clears the resolver map; all in one transaction. A `state#RAW` key (R6) is refused with `ValidationException`: such a row is fixed by editing the state signal's map, not by mapping it, so the action is hidden for it.
  - Resources: `mes/unmapped-signals` (read-only list: source, device, key, last value, last seen, seen count; row action `map` with a form of the attributes above, hidden for `#` keys); `mes/machine-messages` (read-only list, filters by status and source, `payload` shown in a view page truncated to 5000 characters; row action `reprocess` visible by `MesModelPolicy`); `mes/machine-incidents` (read-only list, filters by type and resolved, no actions). All in the group `Machine connectivity`.

- [x] **Step 1: Write the failing tests.** Mapper: mapping an unmapped signal for an existing device creates the signal and removes the unmapped row; for an unknown device creates the device with the given work center first; without `work_center_id` for an unknown device fails validation and creates nothing; a `state#RAW` row is refused; after mapping, `SignalResolver::resolve()` finds the signal. Filament: the three list pages render; the `map` action maps a row and the row disappears; `reprocess` on a failed message from the inbox notifies and sets it pending (`Queue::fake()`); the incidents filter by type returns only that type.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/UnmappedSignalMapperTest.php Modules/MES/tests/Feature/Filament/MesMachineFilamentTest.php`. Expected: FAIL.
- [x] **Step 3: Implement** the mapper, the three resources and the two actions.
- [x] **Step 4: Run** the same command. Expected: PASS.
- [x] **Step 5: Commit**: `feat(mes): backoffice for unmapped signals, message inbox and incidents`.

---

### Task 16: Documentation and plan reconciliation

**Files:**
- Modify: `README.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Create: `docs/MACHINE_CONNECTIVITY.md` (protocol, HTTP contract, response table, token handling, normalisers and `mapped_json` options, PackML state mapping table, Horizon supervisor example for queue `mes-machine`, TLS recommendation)
- Test: `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: documentation of every new configuration key and env variable (`MES_MACHINE_*`) in `README.md` in the existing style, the machine tables and services in `docs/rag/MODULE.md`, glossary entries (Machine source, Machine device, Machine signal, Signal role, Machine profile, Machine message, Machine incident, Unmapped signal, Normaliser, Attribution) in both glossaries, an operator section in the Italian guide ("Collegare le macchine": sources, token shown once, devices and signals, unmapped signals, inbox and reprocess, incidents), and the delivery status of this plan when every task is done.

- [x] **Step 1: Write the failing test.** `MachineDocumentationTest` reads `README.md` and asserts each of the six `mes.machine.*` keys and each `MES_MACHINE_*` env name defined in `config/config.php` appears in it; reads `docs/MACHINE_CONNECTIVITY.md` and asserts it contains the string `laraplate-machine/1`, every response code of the Global Constraints (`202`, `200`, `401`, `403`, `413`, `422`, `429`) and the path `api/v1/mes/machine-data`; and decodes `resources/protocol/laraplate-machine-1.schema.json`.
- [x] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineDocumentationTest.php`. Expected: FAIL.
- [x] **Step 3: Write the documents.** State in `README.md` and `MACHINE_CONNECTIVITY.md` which steps (2 to 6) are not built.
- [x] **Step 4: Run** the same command, then the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: all green.
- [x] **Step 5: Close the plan.** In this file add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick every box, record divergences; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`. Commit in `Modules/MES`: `docs(mes): machine connectivity foundation documentation`; commit the plan in the laraplate repo.

---

## Self-review

- **Spec coverage:** 6.1 tables (Tasks 2, 3), 6.2 enums and per-role config (1, 2), 7.1 envelope and 7.2 schema (4), 7.3 HTTP (7), 7.5 normalisers (5), 8.1 ingress and 8.2 job (6, 10), 8.3 idempotency and reprocess (6, 8, 10, 11), 8.4 retention for raw messages (3, 12), 11.1 health without the bridge or Offline interval (12), 11.2 incidents (6, 12), 11.4 security and permissions (7, 11), 11.5 Filament except unattributed data (14, 15), 11.6 configuration keys of this step (1), 13 testing areas protocol, HTTP, pipeline, idempotency, volume (4, 5, 7, 10). Deferred with a stated owner: sections 6.3, 7.4, 9, 10, `sparkplug_b`, MQTT, `bridge_down`, unattributed data.
- **Spec gaps decided here:** R1 to R10 above; the three most consequential for the user are R1 (no JSON Schema validator), R2 (HTTP canonical-only) and R4 (reference semantics).
- **Type consistency:** `NormalizedSample`, `MessageMeta`, `ResolvedSignal`, `ResolvedSample`, `Attribution`, `InboxResult` and the job constructor `(machine_message_id, source_id, reprocess)` are defined once and used with those names; `MachineIncidentRecorder::recordOnce` and `resolve` signatures are the ones Tasks 10 and 12 call.
