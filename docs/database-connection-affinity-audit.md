# Database connection affinity audit

Date: 2026-07-29

## Scope: native modules share one schema

Decision date: 2026-09-04.

The native modules (`Core`, `CMS`, `AI`, `ERP`, `MES`, `SAO`) are one relational
model split across folders, not separable databases. They share a single schema
on a single connection. This bounds the invariant below: the invariant is a code
discipline, not a promise that a module can be relocated to another database.

Rationale:

- 58 foreign keys already cross module boundaries: CMS to Core (9), AI to Core
  (4), ERP to Core (15), SAO to Core (3), MES to ERP (27, several of them
  `cascadeOnDelete`). MES rows carry no meaning without `erp_items` and
  `erp_companies`.
- The default driver is PostgreSQL, where a foreign key cannot cross databases
  at all. Only MySQL and MariaDB support cross-database foreign keys, and only
  within one server instance.
- `whereHas`, `has`, `withCount`, `whereRelation` and every `join` compile into
  a single statement on the parent model's connection. Eloquent applies no
  cross-connection guard, so a relocated module fails at runtime, not at boot.
- A transaction never spans connections.
- The capability was never configured: `core.model_connections` and
  `erp.model_connections` are empty, `config/database.php` declares no named
  module connection, and no model declares `protected $connection`.
- Connection-affinity tests provision secondary-connection tables inside the
  test itself. They prove the code paths, not that the schema exists on that
  connection.

What stays allowed:

- Cross-module foreign keys and cross-module relation queries.
- Multiple connections at driver level: separate read and write hosts with
  `sticky` on one logical database.
- A future external integration reading a foreign database through a read-side
  adapter. That is a different case: foreign data, not a native aggregate moved
  elsewhere.

What is frozen:

- Do not add entries to `core.model_connections` or `erp.model_connections`.
- Do not extend `ErpConnectionContext`, `ConnectionScopedTransaction` or
  `ConnectionScopedModels` to new code. Existing usages stay as they are;
  removing them carries more risk than value.

The invariant below remains in force as a code discipline. Deriving queries and
transactions from the owning model still prevents real single-connection bugs,
notably a read reaching a replica inside a write transaction.

## Invariant

Database operations that are logically owned by an Eloquent model must use that
model's connection. Moving a model to another configured database must not leave
queries, schema inspection, transactions, or diagnostics on the application's
default connection.

The preferred forms are:

- `$model->newQuery()` for model-owned queries;
- `$model->getConnection()` for transactions, driver inspection, and low-level
  operations;
- `$model->getConnection()->getSchemaBuilder()` for model-owned schema
  inspection or mutation;
- `DB::connection($name)` or `Schema::connection($name)` when there is no model
  owner and the connection is intentionally selected.

`DB::raw()` is connection-neutral: it creates an expression and is acceptable
when the expression is attached to a model-owned query builder.

## Audit scope and method

The audit covered PHP sources under `app`, `Modules`, `database`, and `tests`.
The primary searches were:

```shell
rg -n 'DB::' app Modules database tests --glob '*.php'
rg -n 'Schema::' app Modules database tests --glob '*.php'
```

Each match was classified as one of:

1. a connection-neutral expression;
2. an explicit connection lifecycle or diagnostic operation;
3. migration-context schema work;
4. an intentional default-connection test.

Filament form `Schema` types, comments, detector fixtures, and source-code
assertion strings are lexical matches rather than database operations.

## Runtime inventory

No unexplained implicit runtime `DB` or database `Schema` facade call remains.

### Connection-neutral expressions

These calls only construct SQL expressions; the surrounding query determines
the connection:

- Core:
  `HandleLicensesCommand` (2), `Entity` (1), and `DynamicContentsService` (1).
- CMS:
  `Preset` (1) and `Content` (1).
- ERP:
  `Preset` (1), `TrialBalanceService` (1), and
  `IncomeStatementService` (1).

Total: 9 `DB::raw()` calls.

### Explicit lifecycle and diagnostic operations

Core contains the intentional non-query facade operations:

- `CoreServiceProvider`: production destructive-command protection;
- `BatchSeeder`: six named-connection reconnect, purge, default-switch, and
  connection lifecycle calls;
- `ParallelTaskRunner`: one explicitly selected configured default connection;
- `HasBenchmark`: one caller-selected connection with an explicit configured
  default fallback;
- `ModelMakeCommand`: one explicit configured default connection for a
  model-less generator;
- `ResourceSizer`: one named connection;
- `Place`: one connection selected from the model's connection name.

Comments showing `DB::reconnect()` examples are not executable calls.

### Explicit schema operations

The remaining runtime database `Schema` facade calls all select a connection:

- `Inspect`: one configured connection;
- `InspectorWarmCommand`: one configured connection;
- `MoveEmbeddingTable`: three configured source/destination connection calls.

Commands that inspect or alter an existing model now obtain the schema builder
from that model's connection. Settings-table work obtains both the schema
builder and query builder from a `Setting` model. The model-less
`ModelMakeCommand` explicitly selects `config('database.default')`.

### Configured runtime fallbacks

Entrypoints that do not receive a connected owner may resolve a trusted model
prototype from module configuration. `StockMovementService` uses an explicit
`$source` model when one is supplied; otherwise it resolves `Company` through
`erp.model_connections` and runs all inventory participants on that connection.
Deployments that move ERP inventory models must configure the `Company` class
(or its table) in that map rather than relying on the application default.

Core exposes the equivalent `core.model_connections` map for dashboard models.
`CoreStatsWidget` queries `User` and `License` independently, then counts the
intersection of distinct user `license_id` values with scoped `License` rows.
This avoids a cross-database join and excludes orphaned license identifiers.
Its cache key hashes each model class, table, resolved connection name, and
database identity configuration, so reusing a connection name for another
database cannot return statistics cached for the previous database.

## Migration and seeder context

Migration schema operations intentionally run through the migration
connection/context:

- application root: 18 schema operations across 4 migrations;
- Core: 38 schema operations across 18 migrations, with 10 explicit connection
  acquisitions across 24 migration files;
- CMS: 34 schema operations in migrations;
- ERP: 168 schema operations in migrations.

Seeders use model-owned queries or transactions. Core's `BatchSeeder` is the
exceptional connection-lifecycle coordinator documented above; CMS and ERP
seeders remain model-bound.

Core also runs approval-vote deduplication and unique-index migrations through
the migrator's active connection. The forward fix keeps the newest historical
vote for each modification and actor before enforcing one vote per actor. The
constraint migration repeats the idempotent cleanup immediately before adding
the indexes so a retry remains safe if an old application node writes between
the separately recorded migrations.

## Test inventory

Test-only default connection usage is intentional where a test owns an isolated
SQLite/default database contract. It does not represent application runtime
ownership. Lifecycle tests also deliberately purge, reconnect, listen to, or
inspect a named test connection.

- Core: the architecture detector scans executable application code, while its
  fixture baseline is empty. Test matches consist of detector samples and
  source assertions, named-connection lifecycle/diagnostic tests, model-bound
  builders, and isolated default-SQLite setup/contract tests.
- CMS: 11 executable `DB` facade calls: 7 named lifecycle/purge operations and
  4 intentional explicit-default test operations. Model-bound builders and
  schema builders account for the remaining query/schema checks.
- ERP: listener diagnostics now attach to an explicit connection. Default
  schema contracts now obtain a schema builder from
  `DB::connection(config('database.default'))`; model-specific contracts use a
  named/model connection. Source assertion strings in seeder-affinity tests are
  not executable database calls.

No broad runtime allowlist is used. A runtime exception must instead be made
connection-explicit or model-owned.

## Automated guard

`Modules/Core/tests/Unit/Architecture/DatabaseConnectionAffinityTest.php`
parses runtime PHP and rejects:

- implicit `DB::connection()`;
- implicit facade query and statement methods, including `table`, `select`,
  `selectOne`, `scalar`, `insert`, `update`, `delete`, `statement`,
  `unprepared`, `affectingStatement`, and transaction methods;
- implicit database `Schema` facade operations such as table creation,
  alteration, inspection, and foreign-key toggling.

`DB::raw()` and `DB::connection($explicitName)` are accepted. Filament's
unrelated `Schema` class is excluded by resolved class identity. Detector
self-tests cover both rejected and accepted forms. The committed baseline is an
empty array, so new runtime exceptions fail the architecture test immediately.

## ERP public API compatibility notes

Connection affinity required deliberate API breaks in ERP. Consumers must pass
trusted aggregate models instead of scalar identifiers wherever the service has
to select a database connection. The model supplies the connection and, when
the service uses it as the aggregate, its identifier; callers must not create a
new default-connection model merely to satisfy the signature.

### Currency conversion

ERP commit `54bc1e7` changed the `CurrencyConverter` contract from:

```php
convert(string $from, string $to, float|string|int $amount, ?DateTimeInterface $at = null)
getRate(string $from, string $to, ?DateTimeInterface $at = null)
```

to:

```php
convert(Model $owner, string $from, string $to, float|string|int $amount, ?DateTimeInterface $at = null)
getRate(Model $owner, string $from, string $to, ?DateTimeInterface $at = null)
```

The trusted aggregate supplies the connection that owns exchange-rate data;
current callers pass the relevant company/aggregate model.

External implementations and decorators must add the leading `Model $owner`
parameter to both methods and use its connection for exchange-rate queries.
External callers must pass their trusted owning aggregate. There is no
compatibility adapter because silently falling back to the default connection
would reintroduce the inconsistency this rule prevents. This change therefore
requires an explicit upgrade note for consumers of the ERP contract.

### Payments

ERP commit `19851f4` changed two public service methods:

```php
// Before
PaymentRequestService::applyCallback(string $provider, array $payload)
PaymentRunBuilderService::build(
    int $company_id,
    int $bank_account_id,
    array $payment_schedule_line_ids,
    CarbonInterface|string $execution_date,
)

// After
PaymentRequestService::applyCallback(
    string $provider,
    array $payload,
    PaymentRequest $source,
)
PaymentRunBuilderService::build(
    int $company_id,
    int $bank_account_id,
    array $payment_schedule_line_ids,
    CarbonInterface|string $execution_date,
    BankAccount $source,
)
```

For provider callbacks, resolve the trusted `PaymentRequest` prototype on the
configured ERP connection and pass it as `$source`; the service then locates the
persisted request on that connection. For payment runs, pass the owning
`BankAccount` (or a trusted `BankAccount` prototype) as `$source`. The source
selects the connection; the persisted bank account identified by
`$bank_account_id` must still belong to `$company_id`.

### Accounting audits and VAT settlements

ERP commit `32d85de` replaced scalar owners with connected models:

```php
// Before
DocumentSequenceAuditService::audit(int $company_id, int $year)
VatSettlementBatchService::compute(
    int $company_id,
    int $year,
    ?string $period = null,
    bool $dry_run = false,
)
VatSettlementService::preview(int $company_id, int $fiscal_period_id)
VatSettlementService::compute(int $company_id, int $fiscal_period_id)

// After
DocumentSequenceAuditService::audit(Company $company, int $year)
VatSettlementBatchService::compute(
    Company $company,
    int $year,
    ?string $period = null,
    bool $dry_run = false,
)
VatSettlementService::preview(Company $company, FiscalPeriod $fiscal_period)
VatSettlementService::compute(Company $company, FiscalPeriod $fiscal_period)
```

Callers must load `Company` and `FiscalPeriod` from the same connection. VAT
settlement methods validate that the period belongs to the supplied company and
reject mixed-connection participants before querying.

### Pricing

The same ERP commit, `32d85de`, changed the public pricing APIs:

```php
// Before
InvoiceLinePricingService::defaultsFromSalesOrderLine(
    int $company_id,
    int $sales_order_line_id,
    ?int $party_id = null,
    string $currency = 'EUR',
)
PriceResolverService::resolve(
    int $company_id,
    int $item_id,
    ?int $party_id = null,
    string $currency = 'EUR',
    ?CarbonInterface $date = null,
)

// After
InvoiceLinePricingService::defaultsFromSalesOrderLine(
    Company $company,
    int $sales_order_line_id,
    ?int $party_id = null,
    string $currency = 'EUR',
)
PriceResolverService::resolve(
    Company $company,
    Item $item,
    ?int $party_id = null,
    string $currency = 'EUR',
    ?CarbonInterface $date = null,
)
```

Pass the connected company that owns the price lists. `PriceResolverService`
also requires the connected item and validates that both models share a
connection and that the item belongs to the company.

### Compatible additions

The affinity work also added an optional `Company` argument to
`ErpHealthCheckService::run()` and an optional `ConnectionScopedModels` argument
to `CreditNoteService::validateCreditNoteTotal()`. Existing calls remain valid;
passing the owner explicitly is recommended when checking a non-default ERP
connection. Framework-managed constructors gained connection-context
dependencies and should continue to be resolved through Laravel's container.
