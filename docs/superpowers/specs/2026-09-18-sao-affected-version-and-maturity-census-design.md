# SAO — affected version & version-maturity census design

**Date:** 2026-09-18
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (§4, §9; D15/D17)
**Builds on:** phase 5b (`Release`/`ReleaseTag`/`TicketRelease`, deploy census — shipped).
**Status:** Design proposed.

---

## 1. Purpose

A ticket carries two versions: the one **detected when it is opened** (from the
reporting log, or entered by the person opening it) and the one **planned for its
resolution**. Phase 5b already models the resolution side (`TicketRelease`
`promised`/`shipped` → `Release` → `ReleaseTag` → VCS tag). This design adds the
**detection side**, and underneath it a **version census** that records every
maturity (alpha/beta/rc/stable) while keeping non-stable versions out of the way.

Both a human triaging and the AI need the detected version: the AI can diff the
affected VCS tag against the fix tag.

---

## 2. Locked decisions

| # | Decision |
|---|----------|
| V1 | **Maturity is a property of the tag, not the release.** One product version (`1.4.0`) is one `Release`; its pre-releases (`1.4.0-alpha.2`, `-beta.1`, `-rc.1`) and `1.4.0` are `ReleaseTag`s of differing `kind`. A release's effective maturity is **derived** (`max(kind)`), never stored; no tags = unknown maturity. |
| V2 | **`Release` is the version census row.** Dedup key `(project, version)` (already unique). Any source resolves to one row via `firstOrCreate`. |
| V3 | **You are affected by any maturity; you resolve only on stable.** A version with no stable tag can never be a resolution target, so no separate "hide from resolution" rule is needed — the asymmetry falls out of the tag model. |
| V4 | `ReleaseTagKind` extends to `Alpha`, `Beta`, `Candidate`, `Stable` with an explicit `precedence()` (`Alpha < Beta < Candidate < Stable`). Backed enums have no intrinsic order. |
| V5 | `ReleaseStatus` extends with `Observed`: `observed`, `announced`, `shipped`. `observed` = entered from the wild (log ingest) or typed by a human, not yet curated, **hidden by default** in version lists. Status is the *curation lifecycle*; tags are *maturity*; they stay orthogonal. |
| V6 | `TicketReleaseState` extends with `Affected`: `affected`, `promised`, `shipped`. `affected` accepts any maturity, including a tagless `observed` release. |
| V7 | The affected version is recorded at **two granularities**: the per-occurrence fact (`SignalOccurrence.affected_version` raw string always kept + `affected_release_id` when censused) and the ticket-level attribution (`TicketRelease(affected)`). Occurrence = fact per event; `TicketRelease(affected)` = curated ticket attribution; they layer, not duplicate. |
| V8 | **Provenance, two paths.** Automatic: the `SourceProfile` extracts + **normalizes** the reporting version, then `firstOrCreate`s the `Release` as `observed`. Human: the ticket form autocompletes the project's releases + "add new", which creates an `observed` release. Version search is scoped to the project in the form, not a global entity. |
| V9 | **Normalization at extraction.** `v1.4.0` / `1.4.0` / `1.4.0 (build 123)` must normalize before `firstOrCreate`, else the census fills with `observed` near-duplicates. Unnormalizable strings are **not** promoted to a `Release`; they stay raw on the occurrence (escape hatch, `affected_release_id = null`). |
| V10 | **Resolution invariant, enforced in the attribution service, not only the UI.** `shipped` requires an existing `stable` tag; `promised` may point to an `announced` release; `affected` accepts any maturity. `observed` releases are excluded from resolution automatically (no stable tag). |

## 3. Components

- Enums: `ReleaseTagKind` (+ `Alpha`/`Beta` + `precedence()`), `ReleaseStatus` (+ `Observed`), `TicketReleaseState` (+ `Affected`).
- `Release`: derived `effectiveMaturity(): ?ReleaseTagKind` (`max` over tags, null when tagless); default list scope excludes `observed`, toggle reveals.
- `SignalOccurrence`: `affected_version` (string, nullable) + `affected_release_id` (FK, nullable).
- `Ticket`: `affectedRelease` accessor (single, or first→last range — open decision).
- `SourceProfile`: version field binding + normalization; `firstOrCreate` observed release on ingest.
- Attribution service: the V10 invariant on `promised`/`shipped`.

## 4. Open decisions

- Automatic path always creates the `observed` release, or policy-driven per source (default: create, configurable).
- `Ticket.affectedRelease` as a single release or a first→last range in the UI (data supports both).
- Whether `ReleaseStatus` keeps `announced` explicit or derives shippability from a stable tag (kept explicit here: `announced` = being assembled, independent of tags).

## 5. Out of scope

- VCS tag ingestion/syncing of real tags (existing release-tag machinery).
- The AI diff itself (this only guarantees both tags are addressable).
- A global version-search entity.
