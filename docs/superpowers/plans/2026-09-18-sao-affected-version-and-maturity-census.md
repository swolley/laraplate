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
- [x] Red → green; Pint + commit (`feat(sao): version maturity census — tag kinds and observed status`).

## Task 2: version provenance (ingest + human entry)

- `SourceProfile`: version field binding + normalization (`v1.4.0`/`1.4.0`/`1.4.0 (build 123)` → one form); `firstOrCreate` `observed` `Release` on ingest; unnormalizable strings not promoted.
- Ticket form: project-scoped release autocomplete + "add new" creating an `observed` release.
- Tests: two deliveries collapse to one `observed` release after normalization; human "add new" scoped to project + reuse existing; unnormalizable string not promoted.
- [x] Red → green; Pint + commit (`feat(sao): version provenance — normalized ingest and human entry`).

## Task 3: affected version on the ticket

- `SignalOccurrence`: add `affected_version` (string, nullable) + `affected_release_id` (FK, nullable) + migration + factory + casts.
- Extend `TicketReleaseState` with `Affected`; `Ticket::affectedRelease` accessor; auto path derives `TicketRelease(affected)` from occurrences, human path stores the chosen/new release.
- Tests: occurrence carries normalized version + resolved release id; auto ticket derives affected attribution; human ticket stores affected; first→last affected range across occurrences.
- [x] Red → green; Pint + commit (`feat(sao): affected version on occurrences and ticket attribution`).

## Task 4: resolution invariant (stable-only)

- In the attribution service: `shipped` requires an existing `stable` tag; `promised` allows an `announced` release; `affected` allows any maturity. Resolution pickers surface only stable-eligible releases (consequence).
- Tests: `shipped` to a stable-tagless release rejected; `affected` to a tagless `observed` release allowed.
- [x] Red → green; then full SAO suite green; Pint + commit (`feat(sao): resolution attribution requires a stable tag`).

## Task 5: docs + parent bump

- Update SAO RAG docs (`Modules/SAO/docs/rag/*`: ticket versions, version census) + glossary; SAO README if env/config vars change; spec/plan indexes.
- [x] Commit (SAO) + bump parent.

## Exit criteria

- A ticket exposes both its affected release (any maturity, possibly `observed`) and its promised/shipped release (stable-tagged); the census records every maturity, hides `observed` by default, and dedups per `(project, version)`; resolution to a non-stable release is rejected in the service.

## Known gaps

- Filament surfaces beyond the observed-hide toggle and the ticket-form autocomplete.
- Automatic-path creation policy per source (default create, configurable) — see spec open decisions.

## Delivery status (2026-09-19): shipped (service/model layer)

All five tasks landed in the SAO submodule (`Modules/SAO`), full SAO suite green
(675 passed, 1 skipped). Divergences from the plan as written:

- **Reordered** to schema-first: Task 3's `sao_signal_occurrences` columns and
  `TicketReleaseState::Affected` were built before Task 2's provenance (the
  columns the ingest populates), then the invariant. Commits: `a7bf64d`
  (Task 1), `64f59a4` (Tasks 2–4, one cohesive commit — the migration, enum,
  ingest and invariant interlock), `efd4904` (docs).
- **Migrations, per user instruction:** no standalone migration files for the
  enum widening; the create migrations reference the enums via `::values()`, so a
  fresh migrate emits the widened `CHECK` / new columns. The occurrence columns
  were added to the `sao_signal_occurrences` create migration. Existing databases
  were updated out of band (dev done). Production/existing Postgres DBs need the
  `sao_releases_status_check`, `sao_release_tags_kind_check`,
  `sao_ticket_releases_state_check` constraints widened and the two occurrence
  columns added manually.
- **Filament surfaces deferred** (module law keeps orchestration out of Filament;
  headless works): the observed-hide toggle, the ticket-form version autocomplete
  and the stable-only resolution picker are not built. The invariant is enforced
  in `TicketReleaseAttributor`, not the UI.
- **Classifier boundary kept:** `ReleaseTagClassifier` still maps every VCS
  pre-release tag to `candidate` (its 5b behaviour and test). `Alpha`/`Beta` are
  in the vocabulary for the census and manual/human entry; refining the classifier
  to emit them is a separate, behaviour-changing follow-up.

**Documented in:** `Modules/SAO/docs/rag/MODULE.md` (Roadmap, phase 5b follow-on)
and `Modules/SAO/docs/rag/GLOSSARY.md` (Release, ReleaseTag, Release maturity,
Version census, TicketRelease, SignalOccurrence, Affected version).
