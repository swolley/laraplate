# SAO Phase 4 — Source Profiles, Webhook Ingest & Replay Plan

> REQUIRED SUB-SKILL: superpowers:executing-plans. Checkbox (`- [ ]`) steps.

**Goal:** A new source configurable without code; every delivery an auditable `IngestEvent`; a dry-run replay. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-4-ingest-source-profiles-design.md`.

## Global Constraints

- `declare(strict_types=1);`, `final`, explicit types, `#[Override]`; no new dependencies (dot-path bindings via `data_get`).
- SAO tables `sao_`-prefixed via `SAOTables` (+ `SaoEnumsTest`); models extend `Core\Overrides\Model` (soft deletes in migrations).
- Per task: minimal tests; Pint before commit. Commit per task; bump parent at the end.

## Task 1: ingest + source-profile models

- `IngestStatus` enum; `IngestEvent` (`sao_ingest_events`, unique connection+delivery), `SourceProfile` (`sao_source_profiles`); factories; `SAOTables` cases + `SaoEnumsTest`.
- [ ] Red → green; Pint + commit (`feat(sao): ingest event and source profile models`).

## Task 2: normalization + correlation + ingest + replay

- `PayloadMatcher`, `PayloadNormalizer`, `ProfileSelector`, `CorrelationRuleset`, `WebhookIngestService`, `IngestReplayService`.
- [ ] Red → green (matcher/normalizer/dedupe/correlate/ingest/replay); Pint + commit (`feat(sao): webhook ingest, correlation and dry-run replay`).

## Task 3: docs + parent bump

- Update SAO RAG docs/glossary + spec/plan indexes; full SAO suite green.
- [ ] Commit (SAO) + bump parent.

## Exit criteria

- A profile turns a webhook payload into canonical fields and an `IngestEvent` with an explicit outcome; a correlated error becomes a signal; a re-delivered id is recorded once; replay writes nothing.

## Known gaps

- Concrete Graylog/Sentry `logs` drivers (phase 7 / follow-up); Filament ingest-events UI; HTTP transport + signature verification.
