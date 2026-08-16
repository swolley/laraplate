# SAO phase 2 — signals, shared fingerprinting & loop protection design

**Date:** 2026-08-16
**Module:** `Modules/Core` (fingerprint foundation) + `Modules/SAO` (signals, ingest, loop protection)
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (§6.1, §7, §4 Signal; decisions D7, D11, D13)
**Status:** Design proposed.

---

## 1. Purpose

Phase 2 turns received errors into **signals**: grouped, counted, project-scoped
error records keyed by a fingerprint that is stable across releases and
comparable across projects. It moves the fingerprint normalization rules into
**Core** so the in-process Monolog resolver and SAO's payload path yield one key
for one error (D7), adds SAO's signal model and ingest, and makes the
self-feeding loop **structurally impossible** (§6.1). No external API is
involved; everything is verified offline.

---

## 2. Locked decisions

| # | Decision | Reason / source |
|---|----------|-----------------|
| S1 | Normalization rules live in `Modules\Core\Logging\Fingerprint\` as an **ordered chain of single-responsibility rule classes**; a `FingerprintNormalizer` runs the chain; a `Fingerprinter` hashes the parts. | D7, §7 "Shared design". One key whether fingerprinted in-process or from a payload. |
| S2 | Hash parts are `kind + module + class + normalized file + function + normalized message` — **the line number is not hashed** (kept as metadata). | §7 correction 1: line churn is the top cause of false duplicates. |
| S3 | Numeric normalization is **value-position only** (`= 500`, `code 42`, key/value pairs), never a blanket `\d+ → {n}`. | §7 correction 2: blanket merges a 404 and a 500. |
| S4 | Two frame resolvers over the same chain: **in-process** (Core's `GelfErrorFingerprintResolver`, refactored onto the chain) and **from-payload** (SAO, recovers class/file/function/message from flattened text). | §7 "Two frame resolvers". |
| S5 | A source-provided key is a **native key, namespaced per source** (`core:…`, `sentry:…`); SAO computes its own only in its absence. | §7 "Native keys". |
| S6 | `Signal` is **project-scoped** (`group_key`, `algo_version`, project, counters, first/last seen, state); `group_key` is **comparable across projects** but tickets are never auto-merged across projects (one ticket per project). `algo_version` is stored **from the first migration**. | §4 Signal, D13, §7 correction 3. |
| S7 | `SignalOccurrence` records each occurrence (project, environment, optional context) with retention; `SignalAlias` maps a superseded `group_key` → `Signal`. The **recompute job / alias writes are not built now** (pre-production); only the key format and columns must keep them possible. | §4, §7 correction 3. |
| S8 | **Loop protection layer 1**: a runtime **pipeline-origin marker** is set while any ingest/normalization/sync job runs; the internal log source **discards every record carrying it, regardless of module**. **Layer 2**: a per-group rate limiter caps occurrences of one signal per window. | §6.1 (D11). Makes the loop structurally impossible, not merely improbable. |
| S9 | The internal (host-app) log source is **optional**; SAO works with only external sources. | D11. |

## 3. Components

**Core (`Modules\Core\Logging\Fingerprint\`)**
- `Rule` interface (`apply(string): string`); rule classes: `StripStackTraces`, `CollapseVolatilePayloads` (SQL statements, HTTP bodies), `CollapseSqlState`, `SubstituteUuidIpHex`, `SubstituteNumbersInValuePosition`.
- `FingerprintNormalizer` — runs the ordered chain over a message.
- `Fingerprinter` — `hash(kind, module, class, file, function, message): string` (16-char sha256 of the S2 parts; normalizes file/message through the normalizer).
- `GelfErrorFingerprintResolver` refactored to build parts and call `Fingerprinter` (line dropped from the hash, kept in the GELF payload as metadata).

**SAO**
- `PayloadFrameResolver` — from a flattened `array` payload recovers `{kind, module, class, file, function, message}`, then `Fingerprinter` → key.
- `SignalState` enum (`open`, `resolved`, `muted`, `archived`).
- `Signal`, `SignalOccurrence`, `SignalAlias` models + migrations (`sao_signals`, `sao_signal_occurrences`, `sao_signal_aliases`), `algo_version` on `sao_signals` from creation.
- `GroupKeyResolver` — native (namespaced) if present, else computed via the payload resolver.
- `SignalIngestService` — given a project + normalized payload, resolves the key, opens or recurs a `Signal`, records a `SignalOccurrence`, applies the per-group rate limit.
- `PipelineContext` — sets/reads the origin marker; the internal log source discards marked records.

## 4. Testing

- Core: each rule in isolation; `FingerprintNormalizer` chain; `Fingerprinter` stability (line change → same key; 404 vs 500 → different keys); refactored resolver keeps grouping intent (its existing test updated for the new hash).
- SAO: `PayloadFrameResolver` recovers frames and matches the in-process key for the same logical error; signal open-vs-recur; occurrence retention counting; native-key namespacing; rate-limit cap; pipeline-marker discard.

## 5. Non-goals (later phases / slices)

- Ticket auto-open from a signal and closure policies (phase 6).
- The recompute job and live `SignalAlias` rewrites (built when the algorithm first changes on a live install).
- Deploy census / environment liveness feeds (phase 4/5 wiring).
- Real webhook transport and source profiles (phase 4).
