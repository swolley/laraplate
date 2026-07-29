# Database connection affinity audit

Date: 2026-07-29

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
  `HandleLicensesCommand` (2), `CoreStatsWidget` (3), `Entity` (1), and
  `DynamicContentsService` (1).
- CMS:
  `Preset` (1) and `Content` (2).
- ERP:
  `Preset` (1), `TrialBalanceService` (1), and
  `IncomeStatementService` (1).

Total: 13 `DB::raw()` calls.

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

## `CurrencyConverter` compatibility note

Connection affinity required a deliberate external API break in ERP's
`CurrencyConverter` contract. The signatures changed from:

```php
convert(string $from, string $to, float|string|int $amount, ?DateTimeInterface $at = null)
getRate(string $from, string $to, ?DateTimeInterface $at = null)
```

to:

```php
convert(Model $owner, string $from, string $to, float|string|int $amount, ?DateTimeInterface $at = null)
getRate(Model $owner, string $from, string $to, ?DateTimeInterface $at = null)
```

This was introduced in ERP commit `54bc1e7`. The trusted aggregate supplies the
connection that owns exchange-rate data; current callers pass the relevant
company/aggregate model.

External implementations and decorators must add the leading `Model $owner`
parameter to both methods and use its connection for exchange-rate queries.
External callers must pass their trusted owning aggregate. There is no
compatibility adapter because silently falling back to the default connection
would reintroduce the inconsistency this rule prevents. This change therefore
requires an explicit upgrade note for consumers of the ERP contract.

