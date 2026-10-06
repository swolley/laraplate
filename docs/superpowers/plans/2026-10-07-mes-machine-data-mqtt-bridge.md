# MES machine data acquisition, step 2: MQTT bridge and Sparkplug B. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let machine sources deliver messages through the customer's MQTT broker: a long-running `mes:machine-bridge` command subscribes to the sources' topics and feeds the step 1 inbox, and a `sparkplug_b` normaliser reads Sparkplug B payloads.

**Architecture:** The MQTT client sits behind a `MachineMessageSubscriber` interface so the bridge is tested with a fake. A `MqttMessageRouter` maps a topic to its source and hands the payload to `MachineMessageInbox::accept()` (canonical sources: the JSON body; Sparkplug sources: a wrapper holding the topic and the binary payload). Sparkplug payloads are decoded by a small protobuf wire-format reader written for this module, so there is no protobuf dependency. A heartbeat the bridge writes to cache lets the watchdog record `bridge_down`.

**Tech Stack:** PHP 8.5, Laravel 12, Pest 4, `pcntl` and `sockets` (present), and one new Composer dependency, `php-mqtt/client` (see Decisions that need approval).

**Spec:** `/srv/http/laraplate-stack/docs/superpowers/specs/2026-10-05-mes-machine-data-acquisition-design.md` (sections 7.4, 7.5, 11.1 to 11.3, 13 and 15 step 2). Foundation it builds on: `docs/superpowers/plans/2026-10-06-mes-machine-data-foundation.md` (shipped); read its `Modules/MES/docs/MACHINE_CONNECTIVITY.md`.

## Decisions (approved 2026-10-07: D1 and D2 as recommended)

- **D1. Composer dependency `php-mqtt/client`.** Latest tag `v2.3.2`, MIT, requires PHP `^8.0` and `myclabs/php-enum`; MQTT 3.1.1 and 5, TLS, QoS 0 to 2, persistent sessions. Needed for the bridge (spec 15: "an MQTT client for PHP (step 2)"). Alternative: write an MQTT client ourselves, which is not recommended. Licence check for the spec's rule: MIT is compatible with the AGPL backend.
- **D2. No protobuf dependency.** The spec lists "a protobuf library for Sparkplug B". `google/protobuf` (BSD-3, `v5.36.2`) would need generated classes from the Eclipse Tahu `.proto`, which is licensed EPL-2.0 (a licence check against the AGPL backend the spec requires), and `ext-protobuf` is not installed (the pure PHP runtime is slow). This plan instead decodes the few fields it needs with a wire-format reader of about 100 lines, from the published Sparkplug B specification. If the user prefers the library, only Task 4 changes. Say which before Task 4.

## Global Constraints

- Everything in the Global Constraints of the foundation plan still holds: `declare(strict_types=1);`, braces, explicit types, `#[Override]`, `final`; code, comments and docs in English; no tokens or full payloads in logs; idempotent writes; five-driver portability; tests in `Modules/MES/tests`, support classes in `tests/Support`; no class declared in a test file; run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress` before each commit; commit in the `Modules/MES` submodule, no bump in the monorepo.
- MQTT: QoS 1 with a persistent session (clean session off), so messages published while the bridge is down wait on the broker; duplicates are discarded through `message_id`. One broker per installation. The bridge shuts down cleanly on `SIGTERM`, reconnects with backoff, and the client sits behind `MachineMessageSubscriber`.
- Topics: canonical sources `{prefix}/laraplate-machine/1/{source_code}` with the same envelope as HTTP; `sparkplug_b` sources `spBv1.0/{group}/#`. The topic identifies the source; publishing rights rely on broker ACLs, one credential per agent.
- Normaliser keys: `canonical` (exists), `mapped_json` (exists), `sparkplug_b` (new). `sparkplug_b`: `message_id` derived from topic, sequence number and timestamp; NBIRTH and DBIRTH map to `birth`, NDEATH and DDEATH to `death`.
- Configuration keys `mes.machine.mqtt.*`: host, port, username, password, tls, client id, topic prefix; read from env and documented in the MES README (new env names `MES_MACHINE_MQTT_*`).
- The watchdog records `bridge_down` when the bridge heartbeat stops (spec 11.1); the step 1 incident type already exists.
- An integration test against a real broker runs only when its env variable is set (spec 13).

## Rulings made in this plan (the spec is silent)

- **R1 Stored payload of a Sparkplug message.** The inbox stores one text column, and a Sparkplug message needs its topic: the router stores JSON `{"topic": "...", "payload_base64": "..."}` for `sparkplug_b` sources; canonical sources store the JSON body as received.
- **R2 Sparkplug device identity.** Node-level metrics (NBIRTH, NDATA) belong to a device whose `external_id` is the edge node id; device-level metrics (DBIRTH, DDATA) to `{edge_node_id}/{device_id}`. The group is the source's.
- **R3 Aliases.** DBIRTH and NBIRTH declare `name` with an `alias`; later data may carry only the alias. The map is persisted in `mes_sparkplug_aliases` (source, device, alias, name; unique per source, device and alias), written when a birth is normalised and read when data is. Data whose alias is unknown is not an error: it becomes an unmapped signal with key `alias#{n}`.
- **R4 Sparkplug `seq` is not `source_seq`.** It wraps 0 to 255, so `MessageMeta::source_seq` is null for Sparkplug (no false `seq_gap`); `sent_at` is the payload timestamp. `message_id` is `sparkplug:{topic}:{seq}:{payload timestamp}`.
- **R5 Control metrics are not signals.** Metrics named `bdSeq`, or starting with `Node Control/` or `Device Control/`, are skipped. A `NDEATH` yields a death notice for the node and for every device of that node already configured (`{node}/...`). STATE, NCMD and DCMD topics are ignored.
- **R6 Sparkplug carries no per-metric quality.** Every sample is `good`; metrics flagged `is_null` are skipped; `is_historical` ones are kept, since the sample time is used anyway. Datatypes without a scalar value (data set, template, bytes, file) are skipped. Signed integer types are two's-complement converted from the unsigned wire values.
- **R7 An invalid canonical envelope over MQTT** cannot be answered, so it is dropped with a rate-limited `message_failed` incident (detail: topic, schema errors); it is not stored. A topic of no source, of an inactive source, or matched by several sources is dropped and counted (a log line per minute), never stored.
- **R8 Subscriptions follow the sources.** The bridge subscribes to the topics of every active `mqtt` source at start and reloads the list every 60 seconds, so adding a source needs no restart.
- **R9 `bridge_down` is one incident per active mqtt source** (incidents belong to a source), opened when the heartbeat is older than 60 seconds or absent, closed when it returns. Nothing is recorded while no mqtt source is active.

## Review Focus

- **The same QoS 1 message redelivered after a reconnect**: one stored message, no second job. Test in Task 3, `a redelivered message is stored once`.
- **Sparkplug data arriving before its birth** (alias unknown, or birth processed later): no crash, an `alias#n` unmapped signal, and a reprocess after the birth resolves it. Test in Task 5.
- **A topic that belongs to no source, an inactive source, or two sources**: dropped, never stored, never a crash. Test in Task 2.
- **SIGTERM while a message is being handled**: the bridge finishes the message it holds, stores it, then exits; nothing half-stored. Test in Task 6.
- **A truncated or malformed Sparkplug payload**: unreadable, the message ends `failed` and can be reprocessed; no exception escapes the bridge. Test in Task 4.

---

## File structure

All paths under `Modules/MES/`.

| Path | Responsibility |
|---|---|
| `app/Machine/Mqtt/{MachineMessageSubscriber,MqttMessage,PhpMqttSubscriber,MqttConnectionSettings}.php` | The client boundary and its php-mqtt adapter. |
| `app/Machine/Mqtt/{MqttMessageRouter,MqttPayloadEnvelope,MqttIngest}.php` | Topic to source, the stored wrapper, the ingest path. |
| `app/Machine/Mqtt/MachineBridge.php`, `app/Console/MachineBridgeCommand.php` | The long-running loop and its command. |
| `app/Machine/Sparkplug/{ProtobufReader,SparkplugPayloadDecoder,SparkplugTopic,SparkplugAliasStore,SparkplugBNormalizer}.php` | Sparkplug B reading. |
| `database/migrations/2026_10_07_000001_create_mes_sparkplug_aliases_table.php`, `app/Models/SparkplugAlias.php` | Alias persistence. |
| `config/config.php` | `mes.machine.mqtt` block. |
| `tests/Fixtures/machine-protocol/normalizers/sparkplug_b/` | Binary payload fixtures with expected samples. |
| `tests/Support/FakeMachineMessageSubscriber.php` | The fake client. |

---

### Task 1: Dependency, configuration, subscriber contract

**Files:**
- Modify: `composer.json` of the laraplate root (`require` gets `"php-mqtt/client": "^2.3"`), `Modules/MES/composer.json` (same constraint), `Modules/MES/config/config.php`, `Modules/MES/app/Enums/MESTables.php` (`SparkplugAliases = 'mes_sparkplug_aliases'`)
- Create: `app/Machine/Mqtt/{MachineMessageSubscriber,MqttMessage,MqttConnectionSettings}.php`, `tests/Support/FakeMachineMessageSubscriber.php`
- Test: `tests/Feature/Machine/MqttConfigurationTest.php`

**Interfaces:**
- Produces:
  - `MqttMessage` (readonly): `string $topic`, `string $payload` (raw bytes), `int $qos`.
  - `MachineMessageSubscriber`: `connect(MqttConnectionSettings $settings): void`; `subscribe(list<string> $topics): void` (QoS 1; replaces the previous subscription set); `loop(callable $on_message, callable $should_continue): void` (blocks, calls `$on_message(MqttMessage)` for each message, returns when `$should_continue()` is false); `disconnect(): void`. Throws `MqttConnectionLost` (extends `RuntimeException`) when the connection drops.
  - `MqttConnectionSettings` (readonly), built by `MqttConnectionSettings::fromConfig()`: `host`, `port`, `?username`, `?password`, `bool tls`, `client_id`, `topic_prefix`.
  - Config `mes.machine.mqtt`: `host` (env `MES_MACHINE_MQTT_HOST`, default `127.0.0.1`), `port` (`MES_MACHINE_MQTT_PORT`, 1883), `username`, `password` (null), `tls` (`MES_MACHINE_MQTT_TLS`, false), `client_id` (`MES_MACHINE_MQTT_CLIENT_ID`, `laraplate-mes-bridge`), `topic_prefix` (`MES_MACHINE_MQTT_TOPIC_PREFIX`, `laraplate`).
  - `FakeMachineMessageSubscriber` (in `tests/Support`): queues messages with `push(MqttMessage)`, a `failNextLoopWith(MqttConnectionLost)`, records `connected`, `subscriptions` history and `disconnects`.

- [x] **Step 0: D1 and D2 approved by the user on 2026-10-07** (add `php-mqtt/client`, no protobuf library).
- [ ] **Step 1: Write the failing test.** `MqttConfigurationTest`: the six `mes.machine.mqtt.*` defaults above; `MqttConnectionSettings::fromConfig()` reflects `config([...])` overrides (tls true, a username); `MESTables::SparkplugAliases->value` is `mes_sparkplug_aliases`; `interface_exists(MachineMessageSubscriber::class)`; and `class_exists(PhpMqtt\Client\MqttClient::class)`.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MqttConfigurationTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.** Run `composer require php-mqtt/client:^2.3` from the laraplate root (check the resulting `composer.lock` diff touches only it and `myclabs/php-enum`), mirror the constraint in the module `composer.json`, add the config block (commented like its siblings), the enum case, the contract, the two DTOs and the fake.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): MQTT subscriber contract and configuration`.

---

### Task 2: Topic router and the MQTT ingest path

**Files:**
- Create: `app/Machine/Mqtt/{MqttMessageRouter,MqttPayloadEnvelope,MqttIngest}.php`
- Modify: `app/Models/MachineSource.php` (`defaultMqttTopic(): string`, effective topic accessor), `app/Machine/MachineIncidentRecorder.php` only if a new method is needed (it is not)
- Test: `tests/Feature/Machine/MqttIngestTest.php`

**Interfaces:**
- Consumes: Task 1 types, step 1 `MachineMessageInbox`, `MachineEnvelopeValidator`, `MachineIncidentRecorder`.
- Produces:
  - `MachineSource::effectiveMqttTopic(): string` = `mqtt_topic` when set, else (canonical normaliser only) `{mes.machine.mqtt.topic_prefix}/laraplate-machine/1/{code}`; for a `sparkplug_b` source `mqtt_topic` is required (`spBv1.0/{group}/#`): add a rule to the model (`mqtt_topic` required when `transport` is `mqtt` and normaliser is `sparkplug_b`).
  - `MqttMessageRouter::subscriptions(): list<string>` (effective topics of active `mqtt` sources, distinct); `sourceFor(string $topic): ?MachineSource`, matching a topic against each active mqtt source's topic with MQTT wildcard rules (`+`, `#`); returns null when none or when two sources match.
  - `MqttPayloadEnvelope::wrap(string $topic, string $payload): string` and `unwrap(string $stored): array{topic: string, payload: string}` (R1).
  - `MqttIngest::handle(MqttMessage $message): ?InboxResult`: routes; null for a dropped message (R7); for a canonical source, decodes and validates the envelope (invalid: `recordOnce` a `message_failed` incident with `{topic, errors}` and return null); for a `sparkplug_b` source wraps the payload (R1); then `MachineMessageInbox::accept($source, $stored, MachineTransport::Mqtt)`. A source whose normaliser is neither is dropped.

- [ ] **Step 1: Write the failing test** (`Queue::fake()`, step 1 helpers): a canonical envelope published on `laraplate/laraplate-machine/1/gw-1` is stored (transport `mqtt`) and queues one job; `a redelivered message is stored once` (the same message twice: one row, one job, the second result `duplicate`); a topic of no source, of an inactive source, and one matched by two sources are dropped (null, nothing stored, no job); `subscriptions()` lists each active mqtt source's topic once and skips http and inactive sources; wildcard matching (`spBv1.0/plant/#` matches `spBv1.0/plant/DDATA/node/dev`, not `spBv1.0/other/NDATA/node`; `+` matches one level); an invalid canonical envelope is dropped with one open `message_failed` incident whose detail names the topic and the errors, a second invalid one does not add an incident; a `sparkplug_b` source stores `{"topic","payload_base64"}` and `unwrap(wrap($t, $p))` returns the original bytes (including non-UTF-8); a `sparkplug_b` mqtt source without `mqtt_topic` fails the model rules.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MqttIngestTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the router (cache nothing: the bridge reloads every 60 s), the envelope helper, the ingest class and the source accessor and rule. Matching is a small function over topic levels.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): MQTT topic router and ingest path`.

---

### Task 3: The bridge loop

**Files:**
- Create: `app/Machine/Mqtt/MachineBridge.php`, `app/Console/MachineBridgeCommand.php`
- Modify: `app/Providers/MESServiceProvider.php` (bind `MachineMessageSubscriber` to `PhpMqttSubscriber`, created in Task 7)
- Test: `tests/Feature/Machine/MachineBridgeTest.php`

**Interfaces:**
- Consumes: Tasks 1 and 2.
- Produces:
  - `MachineBridge::__construct(MachineMessageSubscriber $subscriber, MqttMessageRouter $router, MqttIngest $ingest, ?callable $sleep = null)`; time is read through `now()`, so tests control it with `Carbon::setTestNow`.
  - `MachineBridge::run(MqttConnectionSettings $settings, ?callable $should_continue = null): void`: connects, subscribes to `router->subscriptions()`, loops delivering each message to `MqttIngest::handle`, writes the heartbeat `Cache::put('mes:machine:bridge-heartbeat', now()->getTimestamp(), 120)` at least every 10 seconds, re-reads the subscription list every 60 seconds and re-subscribes when it changed, and on `MqttConnectionLost` waits with backoff (1, 2, 4, ... up to 60 seconds) and reconnects, resubscribing. `stop(): void` makes `$should_continue` false; a message being handled is finished first (R: clean shutdown).
  - `MachineBridgeCommand` (`mes:machine-bridge`): builds `MqttConnectionSettings::fromConfig()`, installs `pcntl_signal` handlers for `SIGTERM` and `SIGINT` that call `stop()` (`pcntl_async_signals(true)`), runs the bridge, prints one line on start and one on stop.
  - A handler failure for one message (an exception from `MqttIngest::handle`) is logged without the payload and does not stop the bridge.

- [ ] **Step 1: Write the failing test** with `FakeMachineMessageSubscriber` and `$should_continue` closures that stop after N calls: delivered messages reach `MqttIngest` (two canonical messages, two stored); the heartbeat key is written and moves forward (`Carbon::setTestNow`); subscriptions are set at start from the active mqtt sources and re-subscribed after a source is added (advance time 60 s); `MqttConnectionLost` from the loop triggers a reconnect and a resubscribe, with the backoff sequence recorded by an injected sleeper (`MachineBridge` takes a `callable $sleep` defaulting to `usleep` in seconds; the test captures `[1, 2]` for two consecutive losses and `[1]` after a success resets it); an exception thrown while handling one message is logged and the next message is still handled; `SIGTERM while a message is being handled`: the fake delivers a message whose handler calls `stop()`, the message is stored, and `run()` returns without handling later queued messages; the command is registered (`php artisan list` contains `mes:machine-bridge`) and `--help` exits 0.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineBridgeTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the bridge and the command. Signal handlers are only installed by the command, so tests drive `stop()` directly.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): machine bridge loop and mes:machine-bridge command`.

---

### Task 4: Sparkplug B payload decoding

**Files:**
- Create: `app/Machine/Sparkplug/{ProtobufReader,SparkplugPayloadDecoder,SparkplugTopic}.php`; fixtures `tests/Fixtures/machine-protocol/normalizers/sparkplug_b/*.payload.bin` with `*.expected.json`
- Test: `tests/Feature/Machine/SparkplugDecoderTest.php`

**Interfaces:**
- Produces:
  - `ProtobufReader` (over a byte string): `eof(): bool`, `readVarint(): int` (unsigned, up to 64 bits as PHP int, two's complement for the top bit), `readTag(): array{field: int, wire: int}`, `readLengthDelimited(): string`, `readFixed32(): string`, `readFixed64(): string`, `skip(int $wire): void`. Throws `UnreadableMachinePayload` on a truncated varint, a length past the end, an unknown wire type, or more than 64 levels of nesting.
  - `SparkplugPayloadDecoder::decode(string $bytes): SparkplugPayload`, with `SparkplugPayload` (readonly): `?int $timestamp_ms`, `?int $seq`, `list<SparkplugMetric> $metrics`; `SparkplugMetric` (readonly): `?string $name`, `?int $alias`, `?int $timestamp_ms`, `int $datatype`, `bool $is_null`, `bool $is_historical`, `int|float|bool|string|null $value` already converted by datatype (R6; unsupported datatypes give `value` null with `supported` false: `bool $supported`).
  - `SparkplugTopic::parse(string $topic): ?SparkplugTopic` with `string $group`, `string $type` (`NBIRTH`, `NDEATH`, `DBIRTH`, `DDEATH`, `NDATA`, `DDATA`, `NCMD`, `DCMD`, `STATE`), `string $edge_node`, `?string $device`; null when the topic is not in the `spBv1.0` namespace. `deviceExternalId(): string` per R2.
- Field numbers come from the Sparkplug B specification (`Payload`: timestamp, metrics, seq; `Metric`: name, alias, timestamp, datatype, is_historical, is_null and the value variants; the datatype enumeration). The implementer reads them from the published specification and the Eclipse Tahu `sparkplug_b.proto` **as a reference, not copied code**, and the fixtures below pin them.

- [ ] **Step 1: Write the failing test and the fixtures.** Fixtures are binary payloads produced by a reference encoder (the Eclipse Tahu Python or Java client, run once on a developer machine, never committed as a dependency): `birth.payload.bin` (named metrics with aliases of types Int32, Double, Boolean, String; a `bdSeq`; a `Node Control/Rebirth`), `data-alias-only.payload.bin` (metrics with an alias and no name), `signed-ints.payload.bin` (Int8 -5, Int16 -300, Int32 -70000, Int64 -5000000000), `unsupported-and-null.payload.bin` (a data set, a null metric, a historical metric). Each has an `.expected.json` listing the decoded fields. Tests: each fixture decodes to its expected structure; a truncated payload (each fixture cut at every prefix length) throws `UnreadableMachinePayload` or decodes a prefix without any other exception type (loop over prefixes; the only allowed outcomes); a payload of random bytes (seeded) never throws anything but `UnreadableMachinePayload`; `ProtobufReader` unit cases (varint boundaries 127, 128, 16384, a 10-byte varint, a truncated one); `SparkplugTopic::parse` for `spBv1.0/plant/DDATA/node1/dev2`, `spBv1.0/plant/NBIRTH/node1`, `spBv1.0/plant/STATE/host` and a non-Sparkplug topic (null); device ids per R2 (`node1` and `node1/dev2`).
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/SparkplugDecoderTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the reader, the decoder and the topic parser. The decoder ignores fields it does not know (skips them by wire type); a metric value is read from whichever value field is present and converted by `datatype` (R6).
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): Sparkplug B payload decoder`.

---

### Task 5: The `sparkplug_b` normaliser and the alias store

**Files:**
- Create: `database/migrations/2026_10_07_000001_create_mes_sparkplug_aliases_table.php`, `app/Models/SparkplugAlias.php`, `database/factories/SparkplugAliasFactory.php`, `app/Machine/Sparkplug/{SparkplugAliasStore,SparkplugBNormalizer}.php`; fixtures `.../normalizers/sparkplug_b/*.input.json` (`{"topic": ..., "payload_base64": ...}` wrapping Task 4's binaries) with `*.expected.json`
- Modify: `app/Providers/MESServiceProvider.php` (register the normaliser), the `MachineSource` form's normaliser options come from the registry and need no change
- Test: `tests/Feature/Machine/SparkplugNormalizerTest.php`

**Interfaces:**
- Consumes: Task 4 decoder and topic, step 1 `MachineMessageNormalizer`, `NormalizedMessage`, `DeviceNotice`, `MessageMeta`, `MqttPayloadEnvelope` (Task 2).
- Produces:
  - `SparkplugAlias` (`BelongsToCompany`, plain Eloquent model like the pipeline tables): `company_id`, `source_id`, `device_external_id` (string 160), `alias` (unsigned big integer), `name` (string 255); unique `(source_id, device_external_id, alias)`.
  - `SparkplugAliasStore::remember(MachineSource $source, string $device, array $aliases): void` (`array<int, string>` alias to name; replaces the device's map; one upsert), `name(MachineSource $source, string $device, int $alias): ?string` (loaded once per source and device, memoised in the instance).
  - `SparkplugBNormalizer` (`key()` `sparkplug_b`): `meta()` returns `MessageMeta('sparkplug:{topic}:{seq}:{timestamp}', null, sent_at = payload timestamp)` (R4); `normalize()` unwraps (R1), parses the topic, decodes, and by topic type: `NBIRTH`/`DBIRTH` remember aliases, skip control metrics (R5), return a `birth` `DeviceNotice` (signals: name, `data_type` `number`/`boolean`/`string` by datatype, unit null) and also the birth's metric values as samples; `NDATA`/`DDATA` return samples, resolving an alias without a name through the store and, when unknown, a sample whose signal is `alias#{n}` (R3); `NDEATH` returns death notices for the node and for every `MachineDevice` of the source whose external id starts with `{node}/`; `DDEATH` a death notice for the device; other topic types return an empty message. A malformed wrapper or payload throws `UnreadableMachinePayload`.

- [ ] **Step 1: Write the failing test.** Fixture pairs (wrapper input to expected samples and notices) for: NBIRTH of a node with a device-less metric; DBIRTH with aliases; DDATA by alias after the birth (names resolved); DDATA by name only; NDEATH (with two configured devices of the node, notices for three ids); DDEATH. Then: `data before its birth` (an alias DDATA with no stored aliases) yields one sample `alias#12` and no exception; after the birth is normalised, normalising the same data again yields the named signal; `meta()` of the same topic, seq and timestamp is stable and a different timestamp gives a different id, and `source_seq` is null for every message; a seq of 255 followed by 0 produces no gap (the foundation inbox records none because `source_seq` is null: assert through `MachineMessageInbox::accept` with `Queue::fake()`); control metrics (`bdSeq`, `Node Control/Rebirth`) are not samples; a null metric and an unsupported datatype are skipped; STATE and NCMD yield an empty message; a truncated payload in the wrapper throws `UnreadableMachinePayload` and the stored message ends `failed` when run through `ProcessMachineMessageJob` (fixture of Task 4 cut short); the registry keys contain `sparkplug_b`.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/SparkplugNormalizerTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the migration (named constraints like step 1, `MigrateUtils::timestamps` without soft delete), model, factory, store and normaliser, and register the normaliser in the provider's `NormalizerRegistry` singleton. Birth alias writes happen in `normalize()` through the store, in one statement.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): sparkplug_b normaliser with a persisted alias map`.

---

### Task 6: `bridge_down` in the watchdog

**Files:**
- Modify: `app/Machine/MachineWatchdog.php`
- Test: extend `tests/Feature/Machine/MachineWatchdogTest.php`

**Interfaces:**
- Consumes: Task 3 heartbeat key `mes:machine:bridge-heartbeat` (a Unix timestamp), step 1 `MachineIncidentRecorder`.
- Produces: `MachineWatchdog::sweep()` additionally, for each active source with transport `mqtt`: when the heartbeat is absent or older than 60 seconds, `recordOnce(BridgeDown, ['heartbeat_age_seconds' => n or null])`; when it is fresh, `resolve(BridgeDown)`. Nothing for http sources, and nothing at all while no mqtt source is active (R9). The return value still counts only newly opened `device_silent` incidents plus newly opened `bridge_down` incidents.

- [ ] **Step 1: Write the failing test** (`Carbon::setTestNow`, `Cache::put` of the heartbeat): a stale heartbeat opens one `bridge_down` per active mqtt source and a second sweep opens none; an absent heartbeat does the same with `heartbeat_age_seconds` null; a fresh heartbeat resolves them; http sources and inactive mqtt sources get none; with no mqtt source, nothing is recorded and no cache read is needed.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineWatchdogTest.php`. Expected: FAIL on the new cases.
- [ ] **Step 3: Implement** inside `sweep()`; keep the device logic untouched.
- [ ] **Step 4: Run** the same file. Expected: PASS.
- [ ] **Step 5: Commit**: `feat(mes): watchdog records bridge_down from the bridge heartbeat`.

---

### Task 7: The real MQTT adapter and the broker integration test

**Files:**
- Create: `app/Machine/Mqtt/PhpMqttSubscriber.php`, `app/Machine/Mqtt/MqttConnectionLost.php`
- Modify: `app/Providers/MESServiceProvider.php` (binding, if not done in Task 3)
- Test: `tests/Integration/Machine/MqttBrokerIntegrationTest.php`, `tests/Feature/Machine/PhpMqttSubscriberTest.php`

**Interfaces:**
- Consumes: Task 1 contract; `PhpMqtt\Client\MqttClient` and `ConnectionSettings` (read the installed package's source for the exact signatures; do not assume them from memory).
- Produces: `PhpMqttSubscriber` implements `MachineMessageSubscriber` over `MqttClient`: `connect()` uses the settings (host, port, TLS through the client's TLS options, credentials, the stable client id, `clean_session` false, MQTT 3.1.1), `subscribe()` unsubscribes what is no longer wanted and subscribes the rest at QoS 1, `loop()` registers one handler per topic filter and runs the client loop until `$should_continue()` is false (use the client's loop interrupt so the check runs at least once a second), mapping client exceptions to `MqttConnectionLost`, `disconnect()` is safe to call twice.
- No credentials or payloads in any log line or exception message.

- [ ] **Step 1: Write the tests.** `PhpMqttSubscriberTest` (no broker): the adapter builds connection settings from `MqttConnectionSettings` (assert the values the package's `ConnectionSettings` object carries: client id, TLS flag, credentials, clean session false) through a small seam: a protected factory method the test overrides in a subclass kept in `tests/Support`; `disconnect()` twice does not throw; a client exception becomes `MqttConnectionLost` whose message contains neither the password nor a payload. `MqttBrokerIntegrationTest` is skipped unless `MES_MQTT_TEST_BROKER` (a `host:port`) is set: it publishes a canonical envelope at QoS 1 to a source topic, runs the bridge with the real subscriber until the message is stored (bounded by a 10 second deadline, not a sleep), and asserts one stored message; a second run of the bridge with the same client id receives a message published while no bridge was connected (the persistent session).
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/PhpMqttSubscriberTest.php`. Expected: FAIL. The integration test reports skipped.
- [ ] **Step 3: Implement** the adapter and the binding.
- [ ] **Step 4: Run** the same command. Expected: PASS; run the integration test too when a broker is at hand (`MES_MQTT_TEST_BROKER=127.0.0.1:1883 php artisan test --compact Modules/MES/tests/Integration/Machine`) and record the result in the commit message, or say that it was not run.
- [ ] **Step 5: Commit**: `feat(mes): php-mqtt adapter for the machine bridge`.

---

### Task 8: Documentation and plan closing

**Files:**
- Modify: `README.md`, `docs/MACHINE_CONNECTIVITY.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md`, `docs/rag/GLOSSARY.md`, `docs/MES_GUIDA_SEMPLICE.md`
- Test: extend `tests/Feature/Machine/MachineDocumentationTest.php`

**Interfaces:**
- Produces documentation of: the `MES_MACHINE_MQTT_*` variables and `mes.machine.mqtt.*` keys (README); the topic layout and the response of the bridge to bad topics (MACHINE_CONNECTIVITY); a ready Mosquitto setup (listener, password file, an ACL file with one user per agent that may publish only to `{prefix}/laraplate-machine/1/{its source code}` and the bridge user that may read `{prefix}/laraplate-machine/1/#` and `spBv1.0/#`) and the same ACL idea for EMQX; a `systemd` unit and a `supervisor` program for `php artisan mes:machine-bridge` (restart always, `stopsignal` `TERM`, `stopwaitsecs` 30); the Sparkplug mapping (R2 to R6 in plain words, including that aliases need the birth first and how to trigger a rebirth); the `bridge_down` incident; and the guide for operators (Italian): MQTT sources, topic, and what "no data" looks like when the bridge is down.
- The glossaries gain: MQTT bridge, Sparkplug B, Edge node, Alias.

- [ ] **Step 1: Write the failing test.** The documentation test asserts: the README contains every `MES_MACHINE_MQTT_*` env name defined in `config/config.php` and the six `mes.machine.mqtt.*` keys; `docs/MACHINE_CONNECTIVITY.md` contains `mes:machine-bridge`, `spBv1.0`, `laraplate-machine/1/`, `sparkplug_b`, `bridge_down`, `mosquitto` (case-insensitive) and `SIGTERM`.
- [ ] **Step 2: Run** `php artisan test --compact Modules/MES/tests/Feature/Machine/MachineDocumentationTest.php`. Expected: FAIL on the new cases.
- [ ] **Step 3: Write the documents**, and update the "not built yet" statements of step 1 (the bridge and `sparkplug_b` are now built; the README roadmap loses its first line).
- [ ] **Step 4: Run** the file, then the whole module suite `php artisan test --compact Modules/MES` and `vendor/bin/phpstan analyse Modules/MES/app --no-progress`. Expected: all green (the broker test skipped without its variable).
- [ ] **Step 5: Close the plan.** Add `## Delivery status (<date>)` with `**Documented in:** \`Modules/MES/docs/MACHINE_CONNECTIVITY.md\`, \`Modules/MES/docs/rag/MODULE.md\` and \`Modules/MES/README.md\`.`, tick the boxes, record divergences and whether the broker test ran; run `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`; update `docs/superpowers/plans/INDEX.md`. Commit in `Modules/MES`: `docs(mes): MQTT bridge and Sparkplug B documentation`; commit the plan in the laraplate repo.

---

## Self-review

- **Spec coverage:** 7.4 MQTT (topics, QoS 1, persistent session, one broker, env config: Tasks 1 to 3, 7, 8), 7.5 `sparkplug_b` (Tasks 4, 5), 11.1 `bridge_down` (Task 6), 11.3 bridge process (Task 3), 13 MQTT bridge testing, fake subscriber and the env-gated broker test (Tasks 3, 7), 15 step 2 dependencies (D1, D2).
- **Open for the user before Task 1:** D1 (approve `php-mqtt/client`) and D2 (hand-written decoder versus `google/protobuf`).
- **Spec gaps decided here:** R1 to R9; the heaviest are R1 (the stored wrapper), R2 (device identity) and R3 (alias persistence).
- **Type consistency:** `MqttMessage`, `MachineMessageSubscriber`, `MqttMessageRouter::subscriptions()/sourceFor()`, `MqttIngest::handle()`, `SparkplugPayload`/`SparkplugMetric`, `SparkplugAliasStore::remember()/name()` and the heartbeat key `mes:machine:bridge-heartbeat` are named once and used with those names in every task that consumes them.
