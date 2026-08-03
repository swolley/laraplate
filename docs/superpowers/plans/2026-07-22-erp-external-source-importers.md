# ERP external-source importers implementation plan

> **For agentic workers:** execute one task and one owning repository at a time. Do not infer source fields. Every parser starts from a versioned, anonymized fixture and an approved mapping. Commit ERP, Core, importer-package, and root-documentation changes separately.

**Status:** Core/ERP import foundation complete; source fixtures and mappings required before parser implementation

**Goal:** Add the module-owned `erp:import` entry point and implement three ERP importers in the sibling `laraplate-importers` repository:

1. legacy Symfony SQL database;
2. SPLID mobile-app Excel export;
3. Tricount mobile-app supported export.

**Repositories:** `Modules/ERP`, optionally `Modules/Core` for genuinely neutral identity helpers, sibling `laraplate-importers`, and root Superpowers documentation.

**Framework:** Core import infrastructure from [`2026-07-22-module-import-command-framework.md`](2026-07-22-module-import-command-framework.md).

**Approved first vertical slice:**

- [`2026-07-29-erp-external-cash-import-foundation.md`](2026-07-29-erp-external-cash-import-foundation.md)
  implements the generic Core/ERP host, provenance, cash types, and destination
  services.
- [`2026-07-29-nebula-cash-importer.md`](2026-07-29-nebula-cash-importer.md)
  implements the proprietary Nebula financial adapter after the foundation.

Nebula is the authoritative initial baseline. SPLID and Tricount remain later
source-reconciliation phases.

## Locked boundaries

- Every importer targets ERP and implements the ERP marker contract.
- `erp:import` selects ERP as destination; `--importer` selects Symfony, SPLID, or Tricount.
- Source-specific readers, DTOs, fixtures, and mappings live in `laraplate-importers`.
- ERP owns destination DTOs/services and all accounting, journal, inventory, numbering, locking, pool, and audit invariants.
- Importers never write posted journal, inventory, settlement, or document state with raw SQL.
- Persisted money and quantities use decimal strings; never PHP float arithmetic.
- Rerunning the same source is deterministic and idempotent through stable source identities and `core_record_origins`.
- Dry-run covers only the importer-declared destination connection. Source access must be read-only and all external side effects must be suppressed.
- Participant names from SPLID/Tricount are not Laraplate identities. They must map explicitly to existing Core users; no implicit user creation.
- Initial scope is inbound batch import, not bidirectional synchronization.
- No reverse-engineered private mobile API. SPLID uses its official Excel export. Tricount uses an officially obtained CSV/ODF export or an approved normalized file derived from it.
- Add a parser dependency only after inspecting real fixtures and obtaining approval. Prefer the already compatible `openspout/openspout` stack for XLSX/ODS/CSV when appropriate.

---

## Task 1: Add the ERP import host

**Module:** ERP

**Files:**

- Create: `Modules/ERP/app/Console/ImportCommand.php`
- Create: `Modules/ERP/app/Import/Contracts/BulkImporterInterface.php`
- Create: `Modules/ERP/app/Import/Support/ErpBulkImporterResolver.php`
- Create: `Modules/ERP/app/Import/Support/ErpImportPluginDiscovery.php`
- Create: ERP import test stubs under `Modules/ERP/tests/Stubs/Import/`
- Create: `Modules/ERP/tests/Feature/Import/ImportCommandTest.php`

- [x] Extend Core `AbstractImportCommand`; declare `$name = 'erp:import'`, not `$signature`.
- [x] Add the established green ERP command suffix.
- [x] Accept only the ERP marker interface and reject CMS/Core-only importers before execution.
- [x] Inherit the common `importer`, `bootstrap`, repeatable `arg`, `dry-run`, `limit`, and `no-search` options unchanged.
- [x] Discover compatible classes from the sibling `laraplate-importers` checkout without depending on that package from ERP.
- [x] Cover bootstrap, resolution, arguments, limit, dry-run connection affinity, output, failures, and command registration.
- [x] Update ERP README, developer RAG, RAG glossary, and normal glossary for the public runtime boundary.

```bash
php artisan test --compact Modules/ERP/tests/Feature/Import/ImportCommandTest.php
vendor/bin/pint --dirty
```

**Commit:** `feat(erp): add module import entry point`

---

## Task 2: Define ERP destination contracts and identity rules

**Module:** ERP; Core only if a neutral `RecordOrigin` service is demonstrably reusable

- [x] Inventory existing ERP services for parties, contacts, projects, tasks, time entries, quotations, price lists, movements, partner pools, settlements, journals, inventory, and fiscal documents.
- [x] Define small typed ERP import inputs by destination workflow; do not create one unbounded `ErpImportDto`.
- [x] Add composable ERP application services that ingest a draft cash `Movement`, optionally post it, and apply participant shares through `PartnerPoolSettlementService` without conflating dated cash events with allocations.
- [x] Define source identity keys qualified by source instance/group where needed; adapters supply the concrete `legacy_symfony`, `splid`, or `tricount` key.
- [x] Wrap `core_record_origins` lookup/register behavior so duplicate source records resolve to the same local aggregate.
- [x] Define changed-source policy before code: unchanged rerun skips; changed unposted records may update; changed posted/accounted records reject and require an explicit correction workflow.
- [x] Require explicit maps for company, Core users, chart-of-account roles/accounts, currency, tax codes, and any source category/taxonomy.
- [x] Return structured conflict evidence without changing Core's current `import(): int` compatibility contract.

**Stop condition:** do not implement external parsers until these destination contracts and correction rules have focused tests.

**Commit:** `feat(erp): add external import destination services`

---

## Task 3: Add source-neutral shared-expense DTOs in the importer package

**Repository:** `laraplate-importers`

- [ ] Add immutable DTOs for group, participant, expense, payer contribution, owed share, transfer/settlement, currency, date, description, and stable source identity.
- [ ] Preserve both paid and owed amounts; do not reduce imports to final balances.
- [ ] Normalize decimal scale, ISO currency, locale-dependent dates, and Unicode names without losing original values used in diagnostics.
- [ ] Validate each expense independently: paid total = amount and owed total = amount after the approved rounding policy.
- [ ] Add a participant-map loader from source participant identity to existing Core user ID.
- [ ] Add a common ERP shared-expense importer orchestration reused by SPLID and Tricount; parsers remain source-specific.
- [ ] Keep settlement transactions separate from expense allocations so ERP balances can be reconciled from both histories.

**Commit:** `feat(importers): add ERP shared expense mapping model`

---

## Task 4: Audit the legacy Symfony SQL source

**Source gate:** real schema plus anonymized records

- [ ] Identify database engine/version, charset, timezone, SQL modes, and supported read-only access.
- [ ] Capture schema, constraints, row counts, soft-deletion rules, polymorphism, and representative anonymized rows.
- [ ] Confirm stable keys and modification timestamps for every imported table.
- [ ] Replace the historical conceptual mapping with a field-level mapping against current ERP names; notably use `Party`, not the removed `Customer` abstraction.
- [ ] Cover at minimum the approved subsets of `Client`, `Contact`, `Work`, `Appointment`, `WorkSession`, `Quotation`, `PriceList`, `Movement`, `MovementUser`, and `Transfer`.
- [ ] Mark unsupported legacy entities, such as vertical equipment data, as explicit rejects/backlog rather than silently dropping them.
- [ ] Establish control totals for rows, quotations, worked duration, movement amounts by currency/direction, participant balances, journals, and any inventory scope.
- [ ] Approve ordering, chunk size, restart, duplicate, historical-state, and failure policies.

**Stop condition:** no SQL reader or mapper implementation before mapping and control totals are approved.

---

## Task 5: Implement `LegacySymfonySqlImporter`

**Repository:** `laraplate-importers`

- [ ] Read through a named, read-only source connection; credentials remain in Laraplate configuration, never repeated CLI arguments or logs.
- [ ] Import master data before dependent operational data.
- [ ] Use ERP destination services for every protected mutation.
- [ ] Record stable external identities and make restart/rerun deterministic.
- [ ] Support bounded chunks, progress, row rejection reports, and source checkpoints inside the importer.
- [ ] Never auto-post incomplete or ambiguous fiscal/accounting records.
- [ ] Reconcile every approved source and destination control total.
- [ ] Test with small fixtures, a full anonymized database copy, dry-run, interrupted restart, and persistent rerun.

```bash
php artisan erp:import \
  --bootstrap='/absolute/path/to/laraplate-importers/vendor/autoload.php' \
  --importer='LegacySymfony\Importers\LegacySymfonySqlImporter' \
  --arg='connection=legacy_symfony' \
  --arg='companyId=1' \
  --dry-run
```

**Commit:** `feat(importers): add legacy Symfony ERP importer`

---

## Task 6: Audit the SPLID Excel export

**Known channel:** SPLID officially supports Excel summaries; the exact workbook schema must come from real exports.

- [ ] Obtain anonymized exports covering one/many payers, unequal shares, transfers, multiple currencies, Unicode names, notes, and rounding edges.
- [ ] Record workbook version, sheet names, header localization, cell types, formulas, date representation, and stable row identifiers if present.
- [ ] Decide whether source identity can use a native ID or requires a deterministic hash of group/expense fields.
- [ ] Define group-to-company/pool mapping and participant-to-Core-user mapping.
- [ ] Define handling for converted currencies: source amount/currency, displayed group currency, and any available rate.
- [ ] Approve golden totals per participant and per currency.
- [ ] Approve `openspout/openspout` as an explicit importer-package dependency only if the fixture confirms XLSX/ODS parsing is required.

---

## Task 7: Implement `SplidExcelImporter`

**Repository:** `laraplate-importers`

- [ ] Parse the approved workbook shape without depending on translated display labels where a stable structural alternative exists.
- [ ] Convert each group to one ERP `PartnerPool` and each expense to one ERP expense `Movement` plus exact paid/owed allocations.
- [ ] Import actual reimbursements as `PoolTransaction`; do not import SPLID's suggested settle-up output as completed payments.
- [ ] Reject unmapped participants, unsupported currencies, ambiguous formulas, and unbalanced rows with row/sheet diagnostics.
- [ ] Prove idempotent rerun using stable IDs or approved content hashes.
- [ ] Reconcile expense totals, paid/owed totals, settlements, and final participant balances.

```bash
php artisan erp:import \
  --bootstrap='/absolute/path/to/laraplate-importers/vendor/autoload.php' \
  --importer='Splid\Importers\SplidExcelImporter' \
  --arg='path=/absolute/path/to/splid-export.xlsx' \
  --arg='companyId=1' \
  --arg='participantMap=/absolute/path/to/splid-users.json' \
  --dry-run
```

**Commit:** `feat(importers): add SPLID ERP importer`

---

## Task 8: Audit the Tricount export

**Known channel:** current official documentation says CSV/ODF export is not generally exposed in the app and may require Tricount support. Private reverse-engineered APIs are out of scope.

- [ ] Obtain an official CSV/ODF export or an approved normalized export file plus provenance instructions.
- [ ] Capture format/version, delimiter, encoding, locale, headers, participant identities, transaction types, currencies, shares, reimbursements, and stable IDs.
- [ ] Obtain fixtures for uneven splits, income/refunds, transfers, deleted/corrected expenses, multiple currencies, and rounding edges.
- [ ] Define which transaction kinds become ERP expense movements, income/corrections, or pool settlements.
- [ ] Define participant, company, pool, account, currency, and category mappings.
- [ ] Establish golden totals and participant balances.

**Stop condition:** do not build against an undocumented mobile endpoint or guessed CSV layout.

---

## Task 9: Implement `TricountExportImporter`

**Repository:** `laraplate-importers`

- [ ] Parse only the approved official/normalized format and reject unknown revisions explicitly.
- [ ] Reuse the source-neutral shared-expense DTOs and ERP destination orchestration from Task 3.
- [ ] Preserve payer contributions and beneficiary shares for every transaction.
- [ ] Import confirmed reimbursements only; never materialize suggestions as payments.
- [ ] Handle refunds/income through an approved accounting rule rather than negating expense floats.
- [ ] Prove idempotent rerun, changed-record rejection after posting, and detailed malformed-row reporting.
- [ ] Reconcile group totals, allocations, settlements, currencies, and final participant balances.

```bash
php artisan erp:import \
  --bootstrap='/absolute/path/to/laraplate-importers/vendor/autoload.php' \
  --importer='Tricount\Importers\TricountExportImporter' \
  --arg='path=/absolute/path/to/tricount-export.csv' \
  --arg='companyId=1' \
  --arg='participantMap=/absolute/path/to/tricount-users.json' \
  --dry-run
```

**Commit:** `feat(importers): add Tricount ERP importer`

---

## Task 10: Documentation and final verification

- [ ] Update ERP README, operator guide, developer RAG, user RAG, and glossaries after each implemented source.
- [ ] Update `laraplate-importers` README, RAG/docs, spec, plan, fixture provenance, and source-specific troubleshooting.
- [ ] Run focused ERP import tests and importer-package tests after every task.
- [ ] Run `vendor/bin/pint --dirty` in each changed code repository.
- [ ] Confirm `php artisan list` exposes `erp:import` and `cms:import`, but no `core:import`.
- [ ] Run each importer in dry-run against approved anonymized fixtures.
- [ ] Run approved persistent imports into a disposable database, then rerun unchanged inputs and verify zero duplicate aggregates.
- [ ] Reconcile every source-specific control total before declaring an importer complete.
- [ ] Update ERP backlog `4-09` only after all three importers satisfy their independent completion gates.

## Completion definition

The plan is complete only when the ERP host command and all three importer classes exist, each importer has approved source fixtures and mappings, dry-run and persistent rerun tests pass, participant mappings are explicit, protected ERP mutations use domain services, and source-to-destination control totals reconcile. One completed importer does not imply completion of the other two.
