# SAO Phase 2 — Signals, Shared Fingerprinting & Loop Protection Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Group received errors into project-scoped signals keyed by a release-stable, cross-project fingerprint shared with Core's in-process resolver, and make the self-feeding loop structurally impossible. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-2-signals-fingerprinting-design.md`.

**Tech Stack:** PHP 8.5, Laravel 12, Pest 4, Pint, PHPStan. No new dependencies.

## Global Constraints

- `declare(strict_types=1);`; `final`; explicit types; `#[Override]`; PHPDoc over inline comments.
- Fingerprint rules are dependency-free (no framework calls) so they unit-test in isolation.
- SAO tables `sao_`-prefixed via `SAOTables` (+ `SaoEnumsTest`); models extend `Core\Overrides\Model`; migrations set `hasSoftDelete: true` where the model uses soft deletes.
- Per step: minimal relevant tests; Pint on touched files before each commit. Commit per task; bump parent at the end.

---

## Task 1 (Core): fingerprint rule chain

- Create `Modules/Core/app/Logging/Fingerprint/`: `Rule` interface; rules `StripStackTraces`, `CollapseVolatilePayloads`, `CollapseSqlState`, `SubstituteUuidIpHex`, `SubstituteNumbersInValuePosition`; `FingerprintNormalizer` (ordered chain); `Fingerprinter` (S2 parts, line excluded).
- Tests: each rule in isolation + normalizer chain + `Fingerprinter` (line change → same key; 404 vs 500 → different keys).
- [ ] Red → implement → green; Pint + commit (`feat(core): shared fingerprint normalization chain`).

## Task 2 (Core): refactor the in-process resolver onto the chain

- Edit `GelfErrorFingerprintResolver` to build parts and call `Fingerprinter`; drop line from the hash (keep as metadata); keep module/class/file recovery and caller-frame fallback.
- Update its existing test for the new hash and the value-position numeric rule.
- [ ] Red → implement → green; Pint + commit (`refactor(core): resolve fingerprints through the shared chain`).

## Task 3 (SAO): payload frame resolver

- Create `PayloadFrameResolver` — recover `{kind,module,class,file,function,message}` from a flattened payload; hash via Core `Fingerprinter`.
- Test: a payload whose fields match an in-process error yields the same key; a missing-file payload still yields a stable key.
- [ ] Red → implement → green; Pint + commit (`feat(sao): payload frame resolver for received errors`).

## Task 4 (SAO): signal models + migrations

- `SignalState` enum; `Signal` (`sao_signals`: project_id, group_key, algo_version, state, counters, first/last seen), `SignalOccurrence` (`sao_signal_occurrences`), `SignalAlias` (`sao_signal_aliases`); factories; `SAOTables` cases + `SaoEnumsTest`.
- Tests: signal persists with algo_version; occurrences relate; alias maps a superseded key.
- [ ] Red → implement → green; Pint + commit (`feat(sao): signal, occurrence and alias models`).

## Task 5 (SAO): group-key resolution + ingest service

- `GroupKeyResolver` (native namespaced key if present, else computed) and `SignalIngestService::ingest(Project, payload)` — open or recur a signal, record an occurrence.
- Tests: native key wins and is namespaced; two matching payloads recur one signal and count two occurrences; the same bug in two projects makes two signals with the same group_key.
- [ ] Red → implement → green; Pint + commit (`feat(sao): group-key resolution and signal ingest`).

## Task 6 (SAO): loop protection

- `PipelineContext` (set/read the origin marker); an internal log-source reader that discards marked records regardless of module; a per-group rate limiter in `SignalIngestService`.
- Tests: a marked record is discarded with a recorded reason; an unmarked one ingests; past N occurrences in the window the signal stops counting.
- [ ] Red → implement → green; then full SAO + Core suites green; Pint + commit (`feat(sao): loop protection via pipeline marker and rate limit`).

## Task 7: docs + parent bump

- Update Core + SAO RAG docs/glossaries (fingerprint chain, signals, loop protection) and the spec/plan indexes.
- [ ] Commit (Core), commit (SAO), bump parent.

## Exit criteria

- One error yields one key in-process and from a payload; line churn does not change it; a 404 and a 500 stay distinct.
- Signals group per project with a cross-project-comparable key and `algo_version` from the first migration; occurrences count; the loop is structurally closed.
- Full Core + SAO suites green; Pint clean.

## Known gaps carried forward

- Ticket auto-open/closure policies (phase 6); recompute job + live alias rewrites; deploy census; real webhook transport (phase 4).
