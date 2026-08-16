# SAO Phase 5b — Code-to-Work, Releases & Deploy Census Plan

> REQUIRED SUB-SKILL: superpowers:executing-plans. Checkbox (`- [ ]`) steps.

**Goal:** ChangeRef, Release/ReleaseTag/TicketRelease and Environment + deploy census. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-5b-code-to-work-releases-design.md`.

## Global Constraints

- `declare(strict_types=1);`, `final`, explicit types, `#[Override]`; no new dependencies.
- `sao_`-prefixed tables via `SAOTables` (+ `SaoEnumsTest`); models extend `Core\Overrides\Model` (soft deletes in migrations).
- Per task: minimal tests; Pint before commit. Commit per task; bump parent at the end.

## Task 1: code-to-work references

- `ChangeRefType` enum; `ChangeRef` (`sao_change_refs`) + factory; `Ticket::changeRefs()`. Test.
- [x] Red → green; Pint + commit (`feat(sao): code-to-work change references`).

## Task 2: releases

- `ReleaseStatus`, `ReleaseTagKind`, `TicketReleaseState` enums; `Release`, `ReleaseTag`, `TicketRelease` models + migrations + factories; `SAOTables` cases + `SaoEnumsTest`. Test.
- [x] Red → green; Pint + commit (`feat(sao): releases, release tags and ticket-release attribution`).

## Task 3: environments + deploy census

- `Environment` model + migration + factory; `DeployCensusService` (passive observe, active probe, staleness, census read model). Test.
- [x] Red → green; then full SAO suite green; Pint + commit (`feat(sao): environments and deploy census`).

## Task 4: docs + parent bump

- Update SAO RAG docs/glossary + spec/plan indexes.
- [ ] Commit (SAO) + bump parent.

## Exit criteria

- Commit/PR/tag → ticket; a release carries tickets as promised/shipped independent of workflow status; the census answers "what runs where" with honest staleness.

## Known gaps

- Fix propagation + evidence-based closure (phase 6); Filament release/environment surfaces; live probe transport.
