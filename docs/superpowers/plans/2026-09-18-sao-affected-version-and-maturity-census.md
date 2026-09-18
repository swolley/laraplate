# SAO — Affected Version & Version-Maturity Census Plan

> REQUIRED SUB-SKILL: superpowers:executing-plans. Checkbox (`- [ ]`) steps.

**Goal:** record the version detected at ticket opening (log-automatic or human-entered) on top of a version census that keeps every maturity but hides non-stable ones, and enforce resolution-on-stable-only. Spec: `docs/superpowers/specs/2026-09-18-sao-affected-version-and-maturity-census-design.md`.

## Global Constraints

- `declare(strict_types=1);`, `final`, explicit types, `#[Override]`; no new dependencies.
- `sao_`-prefixed tables via `SAOTables` (+ `SaoEnumsTest`); models extend `Core\Overrides\Model` (soft deletes in migrations).
- When modifying an enum column, the migration keeps every prior attribute (Laravel column-modify rule).
- Per task: minimal tests; Pint before commit. Commit per task; bump parent at the end.

## Task 1: maturity census (vocabulary)

- Extend `ReleaseTagKind` with `Alpha`, `Beta` + `precedence()` (`Alpha < Beta < Candidate < Stable`).
- Extend `ReleaseStatus` with `Observed`; migrations for both enum columns (all prior attributes preserved).
- `Release::effectiveMaturity(): ?ReleaseTagKind` (max over tags, null when tagless); default query scope excluding `observed` + toggle. Update `SaoEnumsTest`.
- Tests: `precedence()`/`effectiveMaturity()` (tagless → null, mixed → max); `observed` excluded from default scope, included with toggle.
- [ ] Red → green; Pint + commit (`feat(sao): version maturity census — tag kinds and observed status`).

## Task 2: version provenance (ingest + human entry)

- `SourceProfile`: version field binding + normalization (`v1.4.0`/`1.4.0`/`1.4.0 (build 123)` → one form); `firstOrCreate` `observed` `Release` on ingest; unnormalizable strings not promoted.
- Ticket form: project-scoped release autocomplete + "add new" creating an `observed` release.
- Tests: two deliveries collapse to one `observed` release after normalization; human "add new" scoped to project + reuse existing; unnormalizable string not promoted.
- [ ] Red → green; Pint + commit (`feat(sao): version provenance — normalized ingest and human entry`).

## Task 3: affected version on the ticket

- `SignalOccurrence`: add `affected_version` (string, nullable) + `affected_release_id` (FK, nullable) + migration + factory + casts.
- Extend `TicketReleaseState` with `Affected`; `Ticket::affectedRelease` accessor; auto path derives `TicketRelease(affected)` from occurrences, human path stores the chosen/new release.
- Tests: occurrence carries normalized version + resolved release id; auto ticket derives affected attribution; human ticket stores affected; first→last affected range across occurrences.
- [ ] Red → green; Pint + commit (`feat(sao): affected version on occurrences and ticket attribution`).

## Task 4: resolution invariant (stable-only)

- In the attribution service: `shipped` requires an existing `stable` tag; `promised` allows an `announced` release; `affected` allows any maturity. Resolution pickers surface only stable-eligible releases (consequence).
- Tests: `shipped` to a stable-tagless release rejected; `affected` to a tagless `observed` release allowed.
- [ ] Red → green; then full SAO suite green; Pint + commit (`feat(sao): resolution attribution requires a stable tag`).

## Task 5: docs + parent bump

- Update SAO RAG docs (`Modules/SAO/docs/rag/*`: ticket versions, version census) + glossary; SAO README if env/config vars change; spec/plan indexes.
- [ ] Commit (SAO) + bump parent.

## Exit criteria

- A ticket exposes both its affected release (any maturity, possibly `observed`) and its promised/shipped release (stable-tagged); the census records every maturity, hides `observed` by default, and dedups per `(project, version)`; resolution to a non-stable release is rejected in the service.

## Known gaps

- Filament surfaces beyond the observed-hide toggle and the ticket-form autocomplete.
- Automatic-path creation policy per source (default create, configurable) — see spec open decisions.
