# Model Connection Affinity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every Laraplate database operation use the connection that owns its data, so moving a model away from the default connection does not silently redirect related queries or transactions.

**Architecture:** Eloquent model instances remain the source of truth for model-owned connections and table names. Direct query builders, transactions, locks, seed/import helpers, and test assertions derive a connection from the owning model or receive an explicit `ConnectionInterface`; migrations instead reuse the connection selected by the migrator or Schema Builder. A focused architecture test prevents new unscoped runtime facade queries while allowing connection-neutral expressions and explicit infrastructure operations.

**Tech Stack:** Laravel database manager and Eloquent, PHP 8.4, Pest 4, SQLite in-memory test connections, Cursor project rules.

**Spec:** `docs/superpowers/specs/2026-07-19-model-connection-affinity-design.md`

---

### Task 1: Project Rule and Runtime Guard

**Files:**
- Modify: `.cursor/rules/09-database-guidelines.mdc`
- Modify: `.cursor/rules/README.md`
- Modify: `CLAUDE.md`
- Create: `Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php`
- Create: `Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php`

- [ ] **Step 1: Write the architecture test with a self-checking detector**

Create a Pest test that tokenizes PHP files under `Modules/*/app` and `app`, records executable static calls to `DB::table`, `DB::transaction`, `DB::beginTransaction`, `DB::commit`, and `DB::rollBack`, and ignores comments and strings. Prove the detector itself with these fixtures before scanning the repository:

```php
it('detects unscoped runtime database operations', function (): void {
    expect(findUnscopedDatabaseCalls('<?php DB::table("users")->get();'))
        ->toBe(['DB::table']);

    expect(findUnscopedDatabaseCalls('<?php $model->getConnection()->table($model->getTable())->get();'))
        ->toBe([]);

    expect(findUnscopedDatabaseCalls('<?php DB::raw("count(*)"); // DB::table("ignored")'))
        ->toBe([]);
});

it('does not add unscoped runtime database operations', function (): void {
    $violations = collect(runtimePhpFiles())
        ->flatMap(fn (string $file): array => array_map(
            static fn (string $call): string => "{$file}: {$call}",
            findUnscopedDatabaseCalls(file_get_contents($file) ?: ''),
        ));
    $baseline = collect(require base_path(
        'Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php',
    ));

    expect($violations->diff($baseline)->values()->all())->toBe([]);
});
```

- [ ] **Step 2: Run the detector fixture and capture the runtime baseline**

Run: `rtk php artisan test --compact Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php`

Expected: the detector fixture proves violations are recognized. Store the current repository violations in `Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php`, make the repository assertion fail on any addition to that baseline, and require subsequent tasks to shrink it as calls are corrected.

- [ ] **Step 3: Add the connection-affinity rule**

Broaden the rule glob to runtime PHP and add these requirements in English:

```markdown
## Model connection affinity

- Eloquent first: start queries from the relevant model instance so runtime `setConnection()` values are preserved.
- A direct query for model-owned data must derive both connection and table from the owning model: `$model->getConnection()->table($model->getTable())`.
- Transactions and locks must use the aggregate root model connection.
- All models in one transaction must share a connection; Laravel transactions are not atomic across connections.
- Dynamic or infrastructure tables must receive an explicit connection from their trusted owning context.
- Migration SQL must reuse the connection selected by the migrator or Schema Builder.
- `DB::raw()` is connection-neutral and inherits the builder connection; do not wrap it mechanically.
```

Mirror the short invariant in `CLAUDE.md` after “Avoid `DB::`; prefer `Model::query()`,” and update the rule index description to mention multiple configured connections.

- [ ] **Step 4: Commit the passing guard, baseline, and documentation**

```bash
git add .cursor/rules/09-database-guidelines.mdc .cursor/rules/README.md CLAUDE.md Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php
git commit -m "test: guard model connection affinity"
```

### Task 2: Core Model Traits and Shared Query Services

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasClosureTable.php`
- Modify: `Modules/Core/app/Observers/FieldObserver.php`
- Modify: `Modules/Core/app/Services/AclResolverService.php`
- Modify: `Modules/Core/app/Services/PresetVersioningService.php`
- Modify: `Modules/Core/app/Services/Crud/CrudService.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php`
- Modify: `Modules/Core/tests/Integration/Services/AclResolverServiceTest.php`
- Modify: `Modules/Core/tests/Feature/Services/PresetVersioningServiceTest.php`
- Modify: `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php`

- [ ] **Step 1: Add failing non-default connection tests**

In each affected suite, configure a second SQLite connection, create the minimal tables on `Schema::connection('affinity')`, and set the aggregate model connection before invoking the behavior:

```php
beforeEach(function (): void {
    config()->set('database.connections.affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge('affinity');
});

afterEach(function (): void {
    DB::disconnect('affinity');
    DB::purge('affinity');
});
```

Assert that closure rows, ACL pivots, preset versions, and CRUD mutations are written on `affinity` and absent from the default connection.

- [ ] **Step 2: Run the four focused suites and verify connection failures**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Services/AclResolverServiceTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Services/PresetVersioningServiceTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php
```

Expected: new affinity cases fail because current direct builders or transaction boundaries still use the default connection.

- [ ] **Step 3: Route direct builders through the owning model**

Use the model instance as the common source for connection and table:

```php
$model->getConnection()->table($model->getTable());
```

For closure and pivot tables without their own model, use the connection of the model that owns the trait or relation:

```php
$this->getConnection()->table($this->getClosureTable());
```

For ACL tables, resolve the permission/user model once and reuse its connection for every related pivot query. Do not introduce `DB::connection(config('database.default'))`.

- [ ] **Step 4: Route transaction boundaries through the aggregate root**

Replace facade transactions with the resolved model connection:

```php
$model->getConnection()->transaction(function () use ($model): void {
    // Existing mutation body unchanged.
});
```

When a collection is the input, obtain the connection from its first model and reject mixed connection names before entering the transaction.

- [ ] **Step 5: Re-run focused suites and Core static analysis**

Run the four test commands from Step 2, followed by `rtk composer analyse`.

Expected: all focused tests pass and PHPStan reports no new errors.

- [ ] **Step 6: Commit Core shared behavior**

```bash
git add Modules/Core/app/Models/Concerns/HasClosureTable.php Modules/Core/app/Observers/FieldObserver.php Modules/Core/app/Services/AclResolverService.php Modules/Core/app/Services/PresetVersioningService.php Modules/Core/app/Services/Crud/CrudService.php Modules/Core/tests
git commit -m "fix: preserve model connections in core queries"
```

### Task 3: Core Commands, Widgets, Seed Infrastructure, and Utilities

**Files:**
- Modify: `Modules/Core/app/Console/CompactVersions.php`
- Modify: `Modules/Core/app/Console/CreateEntityCommand.php`
- Modify: `Modules/Core/app/Console/CreateUserCommand.php`
- Modify: `Modules/Core/app/Console/HandleLicensesCommand.php`
- Modify: `Modules/Core/app/Console/InitializeUsers.php`
- Modify: `Modules/Core/app/Console/PermissionsRefreshCommand.php`
- Modify: `Modules/Core/app/Console/Concerns/HasBenchmark.php`
- Modify: `Modules/Core/app/Database/Seeders/Concerns/HasSeedersUtils.php`
- Modify: `Modules/Core/app/Filament/Widgets/CoreStatsWidget.php`
- Modify: `Modules/Core/app/Grids/Components/Grid.php`
- Modify: `Modules/Core/app/Grids/Traits/HasGridUtils.php`
- Modify: `Modules/Core/app/Helpers/BatchSeeder.php`
- Modify: `Modules/Core/app/Helpers/ModuleDatabaseActivator.php`
- Modify: `Modules/Core/app/Overrides/Seeder.php`
- Modify: `Modules/Core/app/SoftDeletes/Console/ModelSoftDeletesRemoveCommand.php`
- Modify: `Modules/Core/tests/Feature/Console/CreateUserCommandPromptFlowTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasSeedersUtilsTest.php`
- Modify: `Modules/Core/tests/Stubs/Benchmark/BenchmarkHarness.php`

- [ ] **Step 1: Add failing affinity cases for commands, seeders, and benchmarks**

Extend the existing tests so the selected model is assigned to `affinity`, then assert command mutations, seeded rows, and query-log measurements occur on that connection. Use `DB::connection('affinity')->getQueryLog()` only when the test is intentionally inspecting the named connection.

- [ ] **Step 2: Verify the new cases fail on the default-connection implementations**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Console/CreateUserCommandPromptFlowTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasSeedersUtilsTest.php
```

Expected: affinity assertions fail before implementation.

- [ ] **Step 3: Make every utility accept or derive a connection**

Use a model instance where available. For model-less infrastructure helpers, add an `Illuminate\Database\ConnectionInterface` or trusted connection-name parameter and pass it from the caller. Query logs and PDO calls must use that same connection object:

```php
$connection->enableQueryLog();
$connection->table($table)->count();
$connection->getPdo()->lastInsertId();
```

Remove dead commented examples of unscoped facade transactions from Grid code so the architecture guard describes executable policy without stale counterexamples.

- [ ] **Step 4: Re-run affected tests**

Run the commands from Step 2 and these existing focused suites:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Console/CompactVersionsCommandTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Console/PermissionsRefreshCommandTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Console/ModelSoftDeletesCommandsTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Grids/GridTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Grids/HasGridUtilsTest.php
```

Expected: all discovered focused suites pass.

- [ ] **Step 5: Commit Core infrastructure changes**

```bash
git add Modules/Core/app Modules/Core/tests
git commit -m "fix: scope core infrastructure database operations"
```

### Task 4: CMS Imports, Matchers, and Seeders

**Files:**
- Modify: `Modules/CMS/app/Import/Pipeline/ImportPipeline.php`
- Modify: `Modules/CMS/app/Import/Support/BulkImportRunner.php`
- Modify: `Modules/CMS/app/Import/Support/ContributorMatcher.php`
- Modify: `Modules/CMS/app/Import/Support/ExternalReferenceLocator.php`
- Modify: `Modules/CMS/app/Import/Support/LocationMatcher.php`
- Modify: `Modules/CMS/database/seeders/CMSDatabaseSeeder.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportCommandTest.php`
- Modify: `Modules/CMS/tests/Feature/Import/Stubs/FakeBulkImporter.php`

- [ ] **Step 1: Add failing import tests using a non-default connection**

Give the fake importer and CMS models an `affinity` connection. Assert imported rows, origin records, contributor matches, location matches, and rollback behavior all operate there. Add an explicit failure case for an import graph that reports mixed connection names; it must fail before mutations begin rather than claiming cross-database atomicity.

- [ ] **Step 2: Run the import suite and verify expected failures**

Run: `rtk php artisan test --compact Modules/CMS/tests/Feature/Import/ImportCommandTest.php`

Expected: the affinity and mixed-connection cases fail under default `DB` transactions/builders.

- [ ] **Step 3: Pass the target connection through the import pipeline**

Resolve one connection from the import target model, pass its `ConnectionInterface` through runner and matcher constructors/methods, and use `$connection->table(...)`. Validate every model participating in a transactional import has the same resolved connection name before calling `$connection->transaction(...)`.

- [ ] **Step 4: Make the CMS seeder model-bound**

Resolve the seeded model connection once and wrap seeding transactions with that connection. Keep `DB::raw()` expressions unchanged when they are attached to an already scoped builder.

- [ ] **Step 5: Re-run CMS import and model suites**

Run:

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Import/ImportCommandTest.php
rtk php artisan test --compact Modules/CMS/tests/Feature/Models/ContentTest.php
rtk php artisan test --compact Modules/CMS/tests/Feature/Models/TagTest.php
```

Expected: all pass.

- [ ] **Step 6: Commit CMS connection affinity**

```bash
git add Modules/CMS/app/Import Modules/CMS/database/seeders Modules/CMS/tests/Feature/Import
git commit -m "fix: preserve model connections during cms imports"
```

### Task 5: ERP Aggregate Transactions and Installers

**Files:**
- Modify: `Modules/ERP/app/Services/Accounting/ChartOfAccountsInstaller.php`
- Modify: `Modules/ERP/app/Services/Accounting/CreditNoteService.php`
- Modify: `Modules/ERP/app/Services/Accounting/DocumentNumberAllocator.php`
- Modify: `Modules/ERP/app/Services/Accounting/DocumentSequenceResetService.php`
- Modify: `Modules/ERP/app/Services/Accounting/FiscalCalendarInstaller.php`
- Modify: `Modules/ERP/app/Services/Accounting/FiscalPeriodCloser.php`
- Modify: `Modules/ERP/app/Services/Accounting/InvoiceCompactionService.php`
- Modify: `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`
- Modify: `Modules/ERP/app/Services/Accounting/JournalPostingService.php`
- Modify: `Modules/ERP/app/Services/Accounting/VatRegisterService.php`
- Modify: `Modules/ERP/app/Services/Accounting/VatSettlementService.php`
- Modify: `Modules/ERP/app/Services/Banking/BankReconciliationService.php`
- Modify: `Modules/ERP/app/Services/Banking/BankStatementImportService.php`
- Modify: `Modules/ERP/app/Services/Currency/FxRevaluationService.php`
- Modify: `Modules/ERP/app/Services/EInvoice/EInvoiceSubmissionService.php`
- Modify: `Modules/ERP/app/Services/Inventory/DeliveryNoteInventoryService.php`
- Modify: `Modules/ERP/app/Services/Inventory/GoodsReceiptInventoryService.php`
- Modify: `Modules/ERP/app/Services/Inventory/StockMovementService.php`
- Modify: `Modules/ERP/app/Services/Payments/PaymentAllocationService.php`
- Modify: `Modules/ERP/app/Services/Payments/PaymentRunBuilderService.php`
- Modify: `Modules/ERP/app/Services/Payments/PaymentScheduleGeneratorService.php`
- Modify: `Modules/ERP/app/Services/Returns/CustomerReturnReceiptService.php`
- Modify: `Modules/ERP/app/Services/Returns/ReturnOrderService.php`
- Modify: `Modules/ERP/app/Services/Returns/SupplierReturnService.php`
- Modify: `Modules/ERP/app/Services/Returns/SupplierReturnShipmentService.php`
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderAmendmentService.php`
- Modify: `Modules/ERP/app/Services/Taxation/TaxCodeSupersessionService.php`
- Test: closest corresponding files under `Modules/ERP/tests/Feature/Services`

- [ ] **Step 1: Add representative failing affinity tests by aggregate family**

Add one non-default connection case for accounting, banking, inventory, payments, returns, and sales orders. Each case must pass an aggregate root on `affinity`, perform one mutation, and assert both the row location and transaction connection. Add a mixed-connection rejection case wherever a service accepts two independently resolved aggregates.

- [ ] **Step 2: Run the representative suites and confirm failures**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/FiscalCalendarInstallerTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/BankReconciliationServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/PaymentRunBuilderServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/FxRevaluationServiceTest.php
```

Expected: new affinity assertions fail before refactoring.

- [ ] **Step 3: Convert each transaction to its aggregate connection**

For every listed service, identify the aggregate root already passed to the public method and replace the facade boundary with:

```php
$aggregate->getConnection()->transaction(
    fn () => $this->performMutation($aggregate),
);
```

For installer services that receive a model class or company identifier, instantiate/resolve the owning model first and reuse its connection. Do not silently choose the first connection when multiple participating models disagree; throw `LogicException` before opening the transaction.

- [ ] **Step 4: Re-run all ERP service tests**

Run: `rtk php artisan test --compact Modules/ERP/tests/Feature/Services`

Expected: all ERP service tests pass.

- [ ] **Step 5: Commit ERP runtime changes**

```bash
git add Modules/ERP/app/Services Modules/ERP/tests/Feature/Services
git commit -m "fix: bind erp transactions to aggregate connections"
```

### Task 6: Migration and Schema Connection Context

**Files:**
- Modify: `Modules/Core/app/Helpers/MigrateUtils.php`
- Modify: `Modules/ERP/app/Helpers/ERPMigrateUtils.php`
- Modify: `Modules/Core/database/migrations/2024_03_15_224941_create_permission_tables.php`
- Modify: `Modules/Core/database/migrations/2024_03_16_221329_add_teams_fields.php`
- Modify: `Modules/Core/database/migrations/2024_11_05_233754_create_model_embeddings_table.php`
- Modify: `Modules/Core/database/migrations/2024_11_28_224400_create_presettables_table.php`
- Modify: `Modules/Core/database/migrations/2024_11_28_225853_create_taxonomies_table.php`
- Modify: `Modules/Core/database/migrations/2024_11_30_220000_create_places_table.php`
- Modify: `Modules/ERP/database/migrations/2026_04_30_121200_extend_document_sequences_enum_for_sales_order.php`
- Modify: `Modules/ERP/database/migrations/2026_05_04_100000_extend_document_sequences_enum_for_purchase_order.php`
- Modify: `Modules/ERP/database/migrations/2026_05_07_200000_create_lock_guard_triggers.php`
- Modify: `Modules/ERP/database/migrations/2026_05_07_400001_extend_document_sequences_enum_for_credit_debit_notes.php`
- Modify: `Modules/ERP/database/migrations/2026_07_11_140257_add_unit_price_to_return_lines_tables.php`
- Modify: `Modules/Core/tests/Feature/Inspector/SchemaInspectorTest.php`

- [ ] **Step 1: Add a failing migration-context test**

Configure `affinity` SQLite, run a representative anonymous migration or helper against `Schema::connection('affinity')`, and assert all raw statements and driver detection use `affinity` while the default schema remains unchanged.

- [ ] **Step 2: Run the focused migration test and verify default leakage**

Run: `rtk php artisan test --compact Modules/Core/tests/Feature/Inspector/SchemaInspectorTest.php`

Expected: the new case fails because helper `DB::statement()`/driver calls resolve the default connection.

- [ ] **Step 3: Pass the active schema connection into helpers**

Change migration helpers to accept the active `ConnectionInterface` or derive it from the supplied Blueprint/Schema builder. Use:

```php
$connection->getDriverName();
$connection->statement($sql);
$connection->unprepared($sql);
$connection->afterCommit($callback);
```

Update every listed migration call site. Preserve existing driver branches and SQLite-safe fallbacks.

- [ ] **Step 4: Verify migrations from a clean SQLite database**

Run:

```bash
rtk php artisan migrate:fresh --database=sqlite --no-interaction
rtk php artisan test --compact Modules/Core/tests/Feature/Inspector/SchemaInspectorTest.php
```

Expected: migrations complete and the focused test passes.

- [ ] **Step 5: Commit migration context changes**

```bash
git add Modules/Core/app/Helpers/MigrateUtils.php Modules/ERP/app/Helpers/ERPMigrateUtils.php Modules/Core/database/migrations Modules/ERP/database/migrations Modules/Core/tests/Feature/Inspector/SchemaInspectorTest.php
git commit -m "fix: preserve active connection in migration sql"
```

### Task 7: Seeders, Tests, Benchmarks, and Diagnostic Queries

**Files:**
- Modify: `Modules/Core/database/seeders/CoreDatabaseSeeder.php`
- Modify: `Modules/CMS/database/seeders/CMSDatabaseSeeder.php`
- Modify: `Modules/ERP/database/seeders/ERPDatabaseSeeder.php`
- Modify: `Modules/ERP/database/seeders/DevERPDatabaseSeeder.php`
- Modify: `Modules/ERP/tests/Support/OpportunityStageTaxonomy.php`
- Modify: `Modules/ERP/tests/Support/ActivityTaxonomy.php`
- Modify: `Modules/ERP/tests/Feature/ModelQuantityValidationTest.php`
- Modify: `Modules/ERP/tests/Feature/DocumentNumberConcurrencyTest.php`
- Modify: `Modules/ERP/tests/Feature/Services/InvoiceLinePricingServiceTest.php`
- Modify: `Modules/ERP/tests/Feature/Models/ErpPivotModelsTest.php`
- Modify: `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`
- Modify: `Modules/CMS/tests/Feature/Models/FieldImmutabilityTest.php`
- Modify: `Modules/CMS/tests/Feature/Models/ContentTest.php`
- Modify: `Modules/CMS/tests/Feature/Models/TagTest.php`
- Modify: `Modules/CMS/tests/Benchmark/CmsGraphRuntimeBenchmarkTest.php`
- Modify: `Modules/CMS/tests/Feature/Import/Stubs/FakeBulkImporter.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportCommandTest.php`
- Modify: `Modules/Core/tests/Stubs/Benchmark/BenchmarkHarness.php`
- Modify: `Modules/Core/tests/Integration/Concurrency/ParallelTaskRunnerTest.php`
- Modify: `Modules/Core/tests/Feature/Observers/FieldableObserverTest.php`
- Modify: `Modules/Core/tests/Feature/Console/CreateUserCommandPromptFlowTest.php`
- Modify: `Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php`
- Modify: `Modules/Core/tests/Feature/Models/UserTest.php`
- Modify: `Modules/Core/tests/Integration/Auth/Providers/AuthenticationProvidersTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasSeedersUtilsTest.php`
- Modify: `Modules/Core/tests/Feature/Inspector/SchemaInspectorTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php`
- Modify: `Modules/Core/tests/Integration/Services/AclResolverServiceTest.php`
- Modify: `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasTranslationsTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasVersionsTest.php`
- Modify: `Modules/Core/tests/Feature/Helpers/HasDynamicContentsTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasValidationsTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasValidationsBehaviorTest.php`
- Modify: `Modules/Core/tests/Feature/Console/InspectorWarmCommandTest.php`
- Modify: `Modules/Core/tests/Integration/Models/VersionModelTest.php`
- Modify: `Modules/Core/tests/Integration/Models/PlaceVersionableAttributesTest.php`

- [ ] **Step 1: Convert model-owned test setup and assertions**

For each reported test operation, instantiate or reuse the owning model and query through its connection:

```php
$model->getConnection()->table($model->getTable());
```

For pivot and translation tables, use the connection of the parent model whose relation owns the table. For intentional default-connection tests, use `DB::connection(config('database.default'))` and make the intent explicit in the test name.

- [ ] **Step 2: Scope seeder transactions and direct writes**

Resolve the seeded root model connection once per seeder and use its transaction/builder. If a seeder intentionally spans connections, split it into one transaction per connection and do not promise atomic rollback between them.

- [ ] **Step 3: Scope benchmark and diagnostic state**

Replace facade-global query-log calls with the measured model connection. Ensure `enableQueryLog`, `getQueryLog`, `flushQueryLog`, and `disableQueryLog` all execute on the same connection object.

- [ ] **Step 4: Run all directly affected test files**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests
rtk php artisan test --compact Modules/CMS/tests
rtk php artisan test --compact Modules/ERP/tests
```

Expected: all suites pass.

- [ ] **Step 5: Commit test and seeder consistency**

```bash
git add Modules/Core/database/seeders Modules/CMS/database/seeders Modules/ERP/database/seeders Modules/Core/tests Modules/CMS/tests Modules/ERP/tests
git commit -m "test: use owning model database connections"
```

### Task 8: Final Inventory, Guard Green, and Full Verification

**Files:**
- Modify: `Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php`
- Modify: `Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php`
- Create: `docs/database-connection-affinity-audit.md`

- [ ] **Step 1: Re-scan every facade operation**

Run:

```bash
rtk rg -n "DB::" app Modules database tests --glob '*.php'
```

Classify each remainder in the audit document as one of: connection-neutral expression, explicit connection lifecycle/diagnostic operation, migration-context operation, or intentional default-connection test. The document must contain no unexplained occurrence.

- [ ] **Step 2: Run the architecture guard and make it green**

Run: `rtk php artisan test --compact Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php`

Expected: detector fixtures and repository assertion pass with an empty baseline. If a legitimate runtime operation remains, require an explicit connection at the call site instead of adding a broad file allowlist.

- [ ] **Step 3: Format and analyze changed PHP**

Run:

```bash
rtk vendor/bin/pint --dirty
rtk composer analyse
```

Expected: Pint completes and PHPStan reports no errors.

- [ ] **Step 4: Run the complete backend verification**

Run:

```bash
rtk php artisan migrate:fresh --database=sqlite --no-interaction
rtk php artisan test --compact
```

Expected: clean SQLite migration and all Pest tests pass.

- [ ] **Step 5: Verify scope and preserve the existing user change**

Run:

```bash
rtk git status --short
rtk git diff --check
rtk git diff --submodule=short
```

Expected: no whitespace errors; the pre-existing modified `Modules/AI` submodule remains untouched and is not staged.

- [ ] **Step 6: Commit the final guard and audit**

```bash
git add Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php docs/database-connection-affinity-audit.md
git commit -m "docs: account for database facade usage"
```
