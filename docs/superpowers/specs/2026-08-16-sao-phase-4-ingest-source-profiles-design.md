# SAO phase 4 — source profiles, webhook ingest & replay design

**Date:** 2026-08-16
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (§5 driver waves, §6 ingest, §13 phase 4)
**Builds on:** phase 2 (signals, fingerprint, loop protection) and phase 3 (connections).
**Status:** Design proposed.

---

## 1. Purpose

Phase 4 lets a **new error/log source be supported by configuration, not code**:
a `SourceProfile` (matchers + field bindings) turns an arbitrary webhook payload
into canonical fields, a generic webhook ingest path records every delivery as
an `IngestEvent` with an explicit outcome (so **silence is auditable without app
logs**), correlates the event to a project through an ordered ruleset that
records which rule won, and — when it is a real error — hands the normalized
fields to phase 2's `SignalIngestService`. A **dry-run replay** re-runs a stored
payload against a modified profile without acting. All offline and mock-verified.

---

## 2. Locked decisions

| # | Decision | Reason / source |
|---|----------|-----------------|
| G1 | Field bindings are **dot-path expressions** evaluated with Laravel's `data_get` (dot + `*` wildcard), not a new JSONPath dependency. | §6 "JSONPath field bindings" intent, honoured without a new package (AGENTS.md). |
| G2 | Every delivery is recorded as an **`IngestEvent`** (connection, delivery id, raw payload, matched profile, status, outcome, winning correlation rule, resulting signal). | §6, glossary IngestEvent; "discard reasons visible without app logs" (§13 phase 4). |
| G3 | **Idempotency by `delivery_id`** per connection: a re-delivered id is recorded once and not re-ingested. | Glossary Delivery id. |
| G4 | A `SourceProfile` carries **matchers** (which payloads it applies to) and **field bindings** (payload path → canonical field). Selection picks the first active profile whose matchers all pass. | Glossary SourceProfile/Matcher/Field binding. |
| G5 | Correlation is an **ordered, inspectable ruleset**; the winning rule is recorded on the event. The built-in rule maps a canonical `project_key` to a `Project.key_prefix`; an unmatched event is recorded `uncorrelated`, never silently dropped. | Glossary Correlation ruleset; §14 "winning rule recorded". |
| G6 | **Native vs computed key**: a profile that binds `native_key`/`source` yields a namespaced native key (error trackers); otherwise SAO computes the key from the frame (log/alert systems). Reuses phase 2's `GroupKeyResolver`. | §5 "two races"; phase 2 S5. |
| G7 | **Replay is a pure dry-run**: it returns the canonical fields, correlation and would-be outcome for a stored payload under a given profile, and performs no writes. | Glossary Dry-run replay; §14 mitigation. |
| G8 | Ingest runs inside the phase 2 `PipelineContext`, so any error it logs is marked and never re-ingested. | §6.1 layer 1. |

## 3. Components

- `IngestStatus` enum: `received`, `discarded`, `uncorrelated`, `ingested`, `failed`.
- `IngestEvent` model (`sao_ingest_events`): connection_id (nullable), delivery_id, payload json, source_profile_id (nullable), status, outcome (reason string), project_id (nullable), winning_rule (nullable), signal_id (nullable). Unique (connection_id, delivery_id).
- `SourceProfile` model (`sao_source_profiles`): name, is_active, matchers json, field_bindings json.
- `PayloadMatcher` — evaluate a profile's matchers (`equals`/`exists`/`contains`) against a payload via `data_get`.
- `PayloadNormalizer` — apply field bindings to produce the canonical field array.
- `ProfileSelector` — first active profile whose matchers all pass.
- `CorrelationRuleset` — ordered rules resolving a project; returns `{project, rule}` or `{null, null}`; records the winning rule.
- `WebhookIngestService::ingest(?Connection, deliveryId, payload)` — dedupe, select profile, normalize, correlate, record the `IngestEvent`, and on a correlated error call `SignalIngestService`; returns the event.
- `IngestReplayService::dryRun(IngestEvent, SourceProfile)` — recompute fields/correlation/outcome with no writes.

## 4. Testing

- Matcher passes/fails per operator; normalizer maps bound paths (incl. nested and wildcard) to canonical fields.
- Ingest records an event with `ingested` and creates a signal for a correlated error; an unmatched project records `uncorrelated` and no signal; a re-delivered `delivery_id` is recorded once.
- A native-key profile yields a namespaced key; a log-style profile computes one.
- Replay returns the would-be outcome and writes nothing (event/signal counts unchanged).

## 5. Non-goals

- Concrete Graylog/Sentry drivers with real fixtures (their `logs` capability wiring is phase 7 / follow-up).
- The Filament ingest-events surface (a follow-up UI slice).
- Real HTTP transport hardening (signature verification lives in the `logs` capability; the generic path accepts already-verified deliveries).
