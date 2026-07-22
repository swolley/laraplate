# Module-owned import command framework implementation plan

> **For agentic workers:** execute one task and one owning repository at a time. Do not stage unrelated dirty files. Use module test stubs under `tests/Stubs` or `tests/Support`; do not declare test-only classes inside test files.

**Status:** Core framework and CMS migration completed; ERP entry point and source integration pending

**Goal:** Extract common import command mechanics from CMS into Core without exposing a runnable Core command, preserve `cms:import`, add the equivalent `erp:import` entry point, and prepare ERP `4-09` for a source-specific Symfony adapter.

**Architecture:** Core owns `AbstractImportCommand`, the neutral executable importer contract, runner, and resolver/discovery abstractions. CMS and ERP each own a namespaced `ImportCommand`, marker importer interface, colored command description, module resolver, destination pipeline, and documentation. Common CLI options are declared once by Core through `getOptions()`; concrete commands declare `$name` and must not declare `$signature`.

**Spec:** `docs/superpowers/specs/2026-07-22-module-import-command-framework-design.md`

**Repositories:** root documentation, `Modules/Core`, `Modules/CMS`, `Modules/ERP`, and optionally the sibling `laraplate-importers` repository.

## Locked implementation rules

- No runnable `core:import` command.
- Keep `cms:import` and its current options/behavior.
- Add `erp:import` with the same common options.
- Do not add `--entity`, `--resume`, or new dependencies.
- Use `$name` in concrete commands; common parameters come from parent `getOptions()`.
- Keep CMS DTOs, pipeline, upserters, and post-processing in CMS.
- ERP importers write through ERP services, never raw accounting/inventory mutations.
- Preserve the old CMS importer contract namespace as a compatibility marker.
- Treat importer-selected-or-default single-connection rollback as the exact dry-run guarantee.
- Update user/developer/RAG docs in every affected module.
- Commit each task in its owning repository before advancing the root submodule pointer.

---

## Task 1: Freeze the current CMS command contract — completed 2026-07-22

**Module:** CMS

**Files:**

- Modify: `Modules/CMS/tests/Feature/Import/ImportCommandTest.php`
- Create or modify: `Modules/CMS/tests/Feature/Import/ImportCommandDefinitionTest.php`

- [x] Asserted the command name is exactly `cms:import`.
- [x] Asserted the inherited definition contains `importer`, `bootstrap`, repeatable `arg`, `dry-run`, `limit`, and `no-search`.
- [x] Asserted there are no positional import arguments.
- [x] Asserted the command listing description contains the CMS colored suffix.
- [x] Preserved execution coverage for FQCN resolution, bootstrap loading, args, limit, rollback, Scout suppression, sibling discovery, invalid importer, and exit codes.
- [x] Ran:

```bash
php artisan test --compact Modules/CMS/tests/Feature/Import/ImportCommandTest.php Modules/CMS/tests/Feature/Import/ImportCommandDefinitionTest.php
```

**Verification:** CMS command and definition coverage passes with `15 passed`, `49 assertions`.

**Commit:** `test(cms): freeze bulk import command contract`

---

## Task 2: Add neutral Core import infrastructure — completed 2026-07-22

**Module:** Core

**Files:**

- Create: `Modules/Core/app/Import/Contracts/BulkImporterInterface.php`
- Create: `Modules/Core/app/Import/Contracts/BulkImporterResolverInterface.php`
- Create: `Modules/Core/app/Import/Contracts/ImportPluginDiscoveryInterface.php`
- Create: `Modules/Core/app/Import/Support/BulkImportRunner.php`
- Create: `Modules/Core/app/Console/AbstractImportCommand.php`
- Create: `Modules/Core/tests/Stubs/Import/FakeBulkImporter.php`
- Create: `Modules/Core/tests/Stubs/Import/FakeBulkImporterResolver.php`
- Create: `Modules/Core/tests/Stubs/Import/FakeImportPluginDiscovery.php`
- Create: `Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php`
- Modify: `Modules/Core/composer.json` only if the existing test PSR-4 mapping does not cover the new stubs.

- [x] Defined the neutral `import(): int` contract without module references.
- [x] Moved transactional behavior to Core, including optional importer connection affinity, default-connection fallback, `limitReached()` compatibility, and restoration of the previous nesting level.
- [x] Implemented the abstract command as non-registerable infrastructure.
- [x] Defined all current options through inherited `getOptions()` using Symfony `InputOption` modes.
- [x] Preserved common parsing semantics: malformed `--arg` entries are ignored, later duplicate keys win, blank keys are ignored, and limit is normalized to a non-negative integer or `null`.
- [x] Kept bootstrap validation, dry-run warning, Scout suppression, imported-count output, and common failure mapping in the parent.
- [x] Made `handle()` final so module commands cannot silently fork execution semantics.
- [x] Injected resolver and discovery contracts through the parent constructor.
- [x] Added reusable container resolver and filesystem discovery implementations parameterized by the module marker contract.
- [x] Tested that a minimal concrete command needs only `$name`, `$description`, and injected collaborators.
- [x] Updated automatic module command discovery to ignore every non-instantiable command class, including the abstract parent.
- [x] Tested that Core registers no `core:import` command.
- [x] Covered dry-run rollback on both the default and importer-declared connection; only the selected connection is covered.
- [x] Ran:

```bash
php artisan test --compact Modules/Core/tests/Feature/Import/AbstractImportCommandTest.php
vendor/bin/pint --dirty
```

**Verification:** focused Core import and `ModuleServiceProvider` coverage passes with `22 passed`, `51 assertions`; the command-definition test passes with `6 passed`, `26 assertions`. Focused PHPStan was attempted but is blocked before analysis by the unrelated required `excludePaths` entry for missing root `.phpstorm.meta.php`.

**Commit:** `feat(core): add abstract module import command`

---

## Task 3: Migrate CMS onto the Core parent without breaking plugins — completed 2026-07-22

**Modules:** CMS; Core only if a defect is found in the new neutral API

**Files:**

- Modify: `Modules/CMS/app/Console/ImportCommand.php`
- Modify: `Modules/CMS/app/Import/Contracts/BulkImporterInterface.php`
- Move or replace: `Modules/CMS/app/Import/Support/BulkImportRunner.php`
- Modify or replace: `Modules/CMS/app/Import/Support/SiblingImportersDiscovery.php`
- Create: `Modules/CMS/app/Import/Support/CmsBulkImporterResolver.php`
- Create if needed: `Modules/CMS/app/Import/Support/CmsImportPluginDiscovery.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportCommandTest.php`
- Modify: `Modules/CMS/tests/Feature/Import/SiblingImportersDiscoveryTest.php`
- Modify: `Modules/CMS/tests/Feature/Import/Stubs/FakeBulkImporter.php`

- [x] Made the existing CMS contract extend the Core contract so current external classes remain valid.
- [x] Made CMS `ImportCommand` extend Core `AbstractImportCommand`.
- [x] Removed `$signature`; set `$name = 'cms:import'`.
- [x] Added the established CMS colored suffix to the description.
- [x] Injected CMS-specific resolver/discovery implementations in the constructor and passed them to the parent.
- [x] Required the CMS marker interface before execution.
- [x] Preserved interactive sibling project discovery and external autoloader behavior.
- [x] Retained the CMS runner only as a compatibility adapter for external importers using `limitReached()`; execution delegates to Core.
- [x] Kept `AbstractBulkImporter`, mapper contracts, DTOs, pipeline, upserters, and post-processing in CMS.
- [x] Ran focused import coverage successfully.

```bash
php artisan test --compact Modules/CMS/tests/Feature/Import
vendor/bin/pint --dirty
```

**Commit:** `refactor(cms): use Core import command framework`

---

## Task 4: Verify external Acme importer compatibility — completed 2026-07-22

**Repository:** sibling `laraplate-importers`

**Files:** source importer classes, tests, Composer/static-analysis configuration, and README only where required by the actual compatibility change.

- [x] Loaded the external Composer bootstrap alongside Laraplate's autoloader.
- [x] Verified both `AcmeApiImporter` and `AcmeSqlImporter` satisfy the retained CMS marker contract.
- [x] Preserved existing imports through the compatibility marker; no external source change was required.
- [x] Added no reverse Laraplate dependency to the CMS module.
- [x] Ran the external package test suite: `124 passed`, `298 assertions`.
- [ ] Run one Laraplate dry-run smoke import against an anonymized fixture, not a production source.

```bash
composer test
vendor/bin/pint
```

**Commit if changes are required:** `refactor(importers): align with Core import framework`

**Boundary:** no external repository commit is required when the CMS compatibility marker makes the existing package work unchanged. Record that verification in the plan instead.

---

## Task 5: Add the ERP module import entry point

**Module:** ERP

**Files:**

- Create: `Modules/ERP/app/Console/ImportCommand.php`
- Create: `Modules/ERP/app/Import/Contracts/BulkImporterInterface.php`
- Create: `Modules/ERP/app/Import/Support/ErpBulkImporterResolver.php`
- Create if needed: `Modules/ERP/app/Import/Support/ErpImportPluginDiscovery.php`
- Create: `Modules/ERP/tests/Stubs/Import/FakeErpBulkImporter.php`
- Create: `Modules/ERP/tests/Stubs/Import/FakeCmsBulkImporter.php`
- Create: `Modules/ERP/tests/Feature/Import/ImportCommandTest.php`
- Modify: ERP service-provider command registration only if automatic module command discovery does not already load the command.

- [ ] Add `$name = 'erp:import'`; do not declare `$signature`.
- [ ] Add the established colored ERP suffix to the command description.
- [ ] Inject ERP-specific resolver/discovery collaborators into the Core parent.
- [ ] Accept only the ERP marker interface.
- [ ] Prove inherited options have the same names and modes as CMS.
- [ ] Cover FQCN resolution, bootstrap, constructor args, limit, dry-run, Scout suppression, missing importer, invalid class, and imported count.
- [ ] Prove `erp:import` rejects a CMS importer before `import()` is called.
- [ ] Do not implement source mappings or direct model writes in this task.
- [ ] Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Import/ImportCommandTest.php
vendor/bin/pint --dirty
```

**Commit:** `feat(erp): add module import entry point`

---

## Task 6: Document Core, CMS, and ERP behavior — Core/CMS completed; ERP pending

**Modules:** Core, CMS, ERP

**Files:**

- Modify each affected module README.
- Modify each affected module developer RAG document.
- Modify CMS and ERP user/operator guides.
- Modify relevant normal and RAG glossaries.

- [x] Core docs explain the abstract framework, absence of `core:import`, common option contract, and extension rules.
- [x] CMS docs preserve Acme examples and explain the compatibility marker.
- [ ] ERP docs explain that `erp:import` is infrastructure and does not by itself provide a Symfony mapping.
- [x] Core and CMS docs state the exact dry-run boundary.
- [x] CMS command examples use absolute bootstrap paths and quote importer FQCNs safely.
- [x] Core and CMS document the colored command suffix convention.

**Commits:** one documentation commit per owning module.

---

## Task 7: Audit and specify the Symfony ERP source

**Backlog:** ERP `4-09`, concrete adapter gate

- [ ] Identify database engine, version, charset, and timezone.
- [ ] Obtain schema/schema dump and anonymized representative rows.
- [ ] Inventory source tables, foreign keys, soft deletion, polymorphism, and historical state.
- [ ] Define stable source keys and an idempotent source-to-ERP ID map.
- [ ] Decide one-shot migration versus repeatable synchronization.
- [ ] Establish row counts and monetary, inventory, quotation, time, and settlement control totals.
- [ ] Write a field-level mapping document against current ERP models/services.
- [ ] Define ordering, chunk size, restart, failure, and duplicate policies.
- [ ] Determine whether default-connection transactional dry-run is sufficient; otherwise design importer-owned multi-connection side-effect suppression.
- [ ] Obtain explicit approval of the mapping before writing the adapter.

**Stop condition:** if source evidence is unavailable, leave the concrete adapter pending. Do not invent source fields from the historical conceptual mapping.

---

## Task 8: Implement the external Symfony ERP importer after the gate

**Repository:** external importer package, not Core and not ERP domain internals

**Prerequisite:** Task 7 approved.

- [ ] Implement the ERP marker interface.
- [ ] Read the source through a named read-only connection or dump reader.
- [ ] Map source data into ERP-owned typed inputs.
- [ ] Import master data before dependent operational/fiscal data.
- [ ] Use ERP services for journal, inventory, numbering, posting, lock, return, and settlement behavior.
- [ ] Add idempotent external identity tracking and deterministic rerun behavior.
- [ ] Add chunking, progress, rejected-row reporting, and restart checkpoints inside the importer; do not extend the common CLI until semantics are shared by CMS.
- [ ] Reconcile every approved control total.
- [ ] Test with anonymized fixtures and a disposable full-size database copy.
- [ ] Run `erp:import --dry-run` first, then an approved disposable persistent run.

**Commit:** repository-specific, isolated from Laraplate module commits.

---

## Task 9: Final regression and backlog closure

- [ ] Run focused Core import tests.
- [ ] Run all CMS import tests.
- [ ] Run ERP import command tests.
- [ ] Run external importer tests when Task 8 exists.
- [ ] Run `vendor/bin/pint --dirty` after every code-owning module change.
- [ ] Confirm `php artisan list` shows `cms:import` and `erp:import` with distinct colored module suffixes and no `core:import`.
- [ ] Confirm no Core class imports `Modules\CMS` or `Modules\ERP`.
- [ ] Confirm `cms:import` examples remain executable.
- [ ] Update the ERP master backlog:
  - framework/ERP entry point complete after Tasks 1–6;
  - concrete `4-09` complete only after Tasks 7–8 and reconciliation evidence.
- [ ] Commit root Superpowers updates separately from submodule pointers.

## Completion definition

The shared framework is complete when Core, CMS, and ERP Tasks 1–6 pass even if no Symfony database is available. ERP `4-09` as a legacy migration is complete only when the approved source adapter has run against representative data and reconciliation totals pass. These are deliberately separate completion claims.
