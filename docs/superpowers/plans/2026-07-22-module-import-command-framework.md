# Module-owned import command framework implementation plan

> **For agentic workers:** execute one task and one owning repository at a time. Do not stage unrelated dirty files. Use module test stubs under `tests/Stubs` or `tests/Support`; do not declare test-only classes inside test files.

**Status:** Completed for Core/CMS; ERP work transferred to the dedicated ERP external-source importer plan

**Goal:** Extract common import command mechanics from CMS into Core without exposing a runnable Core command, then preserve `cms:import` on the shared infrastructure.

**Architecture:** Core owns `AbstractImportCommand`, the neutral executable importer contract, runner, and resolver/discovery abstractions. CMS owns its namespaced `ImportCommand`, marker importer interface, colored command description, module resolver, destination pipeline, and documentation. Common CLI options are declared once by Core through `getOptions()`; concrete commands declare `$name` and must not declare `$signature`.

**Spec:** `docs/superpowers/specs/2026-07-22-module-import-command-framework-design.md`

**Repositories:** root documentation, `Modules/Core`, `Modules/CMS`, and the sibling `laraplate-importers` repository for compatibility verification only.

## Locked implementation rules

- No runnable `core:import` command.
- Keep `cms:import` and its current options/behavior.
- Do not add `--entity`, `--resume`, or new dependencies.
- Use `$name` in concrete commands; common parameters come from parent `getOptions()`.
- Keep CMS DTOs, pipeline, upserters, and post-processing in CMS.
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

## Transferred ERP scope

The ERP command, ERP documentation, Symfony SQL adapter, SPLID adapter, Tricount adapter, and their final regression are no longer tasks in this completed Core/CMS plan. They are owned by [`2026-07-22-erp-external-source-importers.md`](2026-07-22-erp-external-source-importers.md).

## Completion definition

The shared framework is complete when Core import tests, CMS import tests, and external Acme compatibility checks pass; no runnable `core:import` command exists, and Core imports no CMS class. The optional Acme fixture smoke run remains operational evidence, not a blocker for the completed extraction.
