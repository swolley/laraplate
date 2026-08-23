# Core — Generic Bulk Import (upload → map columns → preview → queue → notify)

**Status:** proposed · **draft — deep analysis pending** · **Module:** Core (consumed by any module)
**Related:** `2026-07-22-module-import-command-framework-design.md`,
`2026-07-29-erp-import-source-reconciliation-design.md`,
`2026-08-23-core-in-app-notifications-design.md`,
`2026-08-23-sao-tracker-migration-importer-design.md`.

> One of four specs scaffolded together. Analysed and implemented on its own later.

## Decisions (locked 2026-08-23) & what shipped

- **Surface: backend + Filament + Vue SPA.** Built **(B)** as the durable Core
  feature. Backend framework in `Modules/Core/app/Import`: `EntityImporterInterface`
  + open `EntityImporterRegistry`, streaming `SourceReader`s (CSV via league/csv,
  XLSX/ODS via openspout, JSON in-process), `ImportPreviewService` (columns +
  sample rows + header auto-match), `ImportSession`/`ImportRowError` models,
  `ImportRunner` + `ProcessImportSessionJob`. SPA API at `/app/crud/imports`
  (`ImportSessionController`). Vue UI in `laraplate-stack/laraplate-ui`
  (`createImportsClient`, `useImportSession`, `ImportWizard.vue`, sao `/imports`
  route). Filament monitoring resource (`ImportSessionResource`) in Core.
- **SQL deferred** — a raw dump is a security concern; CSV/XLSX/ODS/JSON ship.
- **Transaction: per-chunk commit + downloadable per-row failure report.** Each
  chunk commits (durable progress on large files); each row runs in its own
  savepoint so a failing row rolls back only itself and is recorded in
  `core_import_row_errors` (CSV download in both the API and Filament).
- **Pilots: both.** `core.user` (Core reference, dedupe by email) and `sao.ticket`
  (SAO — closes the tracker-migration spec's "file-dump" follow-up).
- **Per-entity registration gate.** Only a registered `EntityImporterInterface` is
  importable — the framework never imports an arbitrary table. Modules register
  their own importers from their provider's `boot`.
- **Completion notification** depends on the not-yet-built in-app notifications
  spec; for now `ImportRunner` fires `ImportSessionCompleted` / `ImportSessionFailed`
  as the seam that tray will listen on.

## The idea

A generic, interactive bulk import for selected entities: the user uploads a file
(**JSON / CSV / Excel / SQL**), the system detects the columns, the user maps each
target field to a source column through **dropdowns with a spreadsheet-like
preview** (à la LibreOffice Calc's CSV import), confirms, and the import runs as an
**async queued job**; on completion the user gets an **in-app notification** in the
notification tray.

## What already exists (reusable pieces)

- **Core CLI import framework** (`Modules/Core/app/Import`, `Console`):
  `AbstractImportCommand`, `BulkImporterInterface::import(?Output): int`,
  `BulkImportRunner::run(dryRun, callable, ?connection)` (dry-run = transaction +
  rollback), resolver + sibling-repo plugin discovery. **Idempotency store**:
  `RecordOrigin` (`core_record_origins`, morph `referable`) + `RecordOriginRegistry`
  (`inspect`/`register`, `ExternalRecordIdentity` with SHA-256 fingerprint;
  `ExternalRecordState = Missing | Unchanged | Changed`). This is the natural dedupe
  backbone.
- **CMS reference importer** (`Modules/CMS/app/Import`): `SourceIteratorInterface`,
  `RecordMapperInterface::mapGraph(): ?ImportGraphDto`, `ImportPipeline` with
  per-entity `Upserters/*` and `ImportIdMap` — a mapper/pipeline/upsert model to
  mirror.
- **ERP column-mapping precedent**: `BankStatementCsvImporter` parses CSV with
  `SplFileObject(READ_CSV)`, takes a `$columns` **source-header → domain-field**
  map, validates rows, emits DTOs. Closest existing "map columns → parse → import".
- **Parsers available (installed, transitive)**: `league/csv` 9.28 (CSV),
  `openspout/openspout` 4.32 (XLSX/ODS/CSV streaming). No `maatwebsite/excel` /
  `spatie/simple-excel`.
- **Filament v5 native Import stack** (`filament/actions` 5.7.6): `ImportAction`,
  `Importer`, `ImportColumn` (mapping + guessing), queued `ImportCsv` chunk job,
  `Import`/`FailedImportRow` models, `ImportCompleted` event + native completion
  notification. **Installed but unused anywhere.**

**The gaps:** no interactive spreadsheet-style **preview**; no persisted
**dropdown mapping**; no **JSON/SQL** in-app parsing; no exposure in the **Vue
SPA** (Filament import is admin-only); no generic per-entity registration; and no
job **batching/progress** infra (`Bus::batch`/`Batchable` unused today).

## Proposed direction

Weigh two builds, likely a **hybrid**:

- **(A) Adopt Filament's native Importer** for the admin panel — fast path for CSV:
  `ImportColumn` mapping, queued chunking, per-row `FailedImportRow`, completion
  notification. Fill gaps: XLSX/ODS via openspout, and it offers mapping dropdowns
  but not a full grid preview; admin-only.
- **(B) Core-owned import feature** reusable by **both SPA and Filament**:
  - `ImportSession` (uploaded file ref + detected columns + chosen target entity +
    saved mapping + status) and a reusable `ImportProfile` (mapping remembered per
    `(entity, source-shape)`).
  - **Preview endpoint** returning the first N rows as a typed grid for the
    LibreOffice-Calc-like mapping UI.
  - A **queued, batched `ImportJob`** streaming rows via openspout/league-csv →
    map → validate → upsert through a **per-entity `Importer` contract** with
    `RecordOriginRegistry` dedupe; per-row failures collected into a downloadable
    report (mirroring `FailedImportRow`); completion → in-app notification.
  - **Source parsers**: CSV (`league/csv`), XLSX/ODS (`openspout`), JSON (stream).
    **SQL** is the risky one — parse-only (extract `INSERT` tuples) or sandboxed
    import, or **defer**; a raw SQL dump is a security concern (open decision).

Recommendation to validate in analysis: build **(B)** as the durable Core feature
(SPA + Filament), reuse **(A)**'s ImportCsv/openspout machinery under the hood
where it fits, and gate the entity list behind an explicit per-entity `Importer`
registration (no importing arbitrary tables).

## Open decisions

- Pilot importable entities + the per-entity `Importer` contract shape.
- **SQL** support: parse-only, sandbox, or defer (lean: defer / parse-only).
- Preview tech in Vue (virtualized grid), row count, type inference.
- Mapping persistence & reuse (`ImportProfile`), including header auto-match.
- Validation surfacing: per-row error report (downloadable) vs inline.
- Transaction strategy: all-or-nothing vs per-chunk commit with a failure report.
- Max file size / streaming limits; where uploads are stored (temporary disk).
- Whether the SAO tracker file-dump path (other spec) is just this feature with a
  `ticket` importer.

## Out of scope / dependencies

No new heavy dependency without approval — `openspout` and `league/csv` are already
present, as is Filament's import stack. Completion notifications depend on the
in-app notifications spec.

## Sequencing

1. Core `ImportSession` + CSV/XLSX parse + mapping + preview + queued batched job +
   `RecordOrigin` dedupe, for one pilot entity (Filament first).
2. JSON source; downloadable per-row failure report.
3. Vue SPA mapping/preview UI + completion notification.
4. `ImportProfile` reuse / header auto-match.
5. SQL source (gated) — or leave deferred.
