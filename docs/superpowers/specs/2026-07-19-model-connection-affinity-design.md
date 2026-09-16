# Model Connection Affinity

## Goal

Keep Laraplate queries correct when a model is moved from the default database
connection to another configured connection. A query tied to model-owned data
must obtain its connection and table identity from that model instead of relying
on Laravel's default connection.

This requirement is distinct from database-driver compatibility. Driver
compatibility covers MySQL, MariaDB, PostgreSQL, Oracle, and SQLite dialects;
connection affinity covers multiple configured connections used by models in
the same application runtime.

## Invariant

For model-owned data, the model is the single source of truth for both the
connection and the table name.

Preferred Eloquent queries must start from the relevant model instance so that
runtime `setConnection()` values are preserved:

```php
$model->newQuery()->whereKey($id)->first();
```

When the query builder is necessary, derive its connection and table from the
same model:

```php
$model->getConnection()
    ->table($model->getTable())
    ->where('active', true)
    ->get();
```

Do not replace an unknown connection with the literal name `default`.
Laravel model connections may be `null`, in which case `getConnection()`
correctly resolves the configured default connection.

## Transactions and Locks

A transaction or lock that protects an aggregate must execute through the
aggregate root model connection:

```php
$order->getConnection()->transaction(function () use ($order): void {
    // Mutations belonging to the order aggregate.
});
```

All models participating in one transaction must resolve to the same connection
name. A Laravel transaction on one connection is not atomic across another
connection. Workflows that intentionally span connections must define separate
transaction boundaries and an explicit failure strategy; connection switching
must never be hidden inside a default-connection transaction.

## Dynamic and Infrastructure Tables

Queries for pivot, cache, metadata, import, or other tables without a dedicated
model must receive an explicit trusted connection from their owning model or
calling context. The connection and table identifier must describe the same
storage boundary.

If no ownership or connection can be determined safely, the code must not guess.
The call site must be redesigned to supply the connection, or the intentional
use of the configured default connection must be made explicit and covered by a
test.

## Migrations and Schema Helpers

Migrations operate through the connection selected by Laravel's migrator or by
an explicit migration connection. Schema helpers and driver-specific statements
must reuse that migration/schema connection rather than resolving a model
connection.

This keeps migrations portable while avoiding accidental execution of schema
SQL on the application default connection when the migrator selected another
connection.

## Seeders, Imports, Commands, and Tests

- Seeders and imports must use the connection of the models whose data they
  create or update.
- Commands must derive the connection from the selected model or require it as
  input when operating on infrastructure tables.
- Tests that inspect model-owned rows must query through the model connection.
- Tests may intentionally use the configured default connection only when the
  default connection itself is the subject of the test.
- Benchmark and diagnostic code must monitor the same connection used by the
  operation being measured.

## Expressions and Connection Management

`DB::raw()` creates an expression and does not select a connection. It inherits
the connection of the Eloquent or query builder that consumes it, so it must not
be mechanically converted.

Connection lifecycle operations such as reconnecting, purging, or selecting the
application default are reviewed according to their infrastructure purpose.
They are not model queries, but any subsequent data operation must still obey
connection affinity.

## Enforcement

The project database rule will state:

> Database queries that do not use Eloquent models but access model-owned data
> must always use the owning model's connection and canonical table name. This
> keeps the query correct when the model is moved to another connection.

The rule will additionally require model-bound transactions and prohibit
assuming cross-connection atomicity. Its file matching must include runtime PHP,
not only migrations and database configuration.

An architecture test will reject newly introduced unscoped runtime operations
such as `DB::table()` and `DB::transaction()`. Contextual operations through a
model connection or an explicitly supplied infrastructure connection remain
valid. Migration context, expressions, mocks, and intentional diagnostics are
handled separately rather than suppressed by a broad allowlist.

## Refactoring Strategy

The complete audit covers runtime code, migrations and schema helpers, seeders,
tests, benchmarks, and diagnostics. Changes proceed in tested groups:

1. Add characterization and architecture tests for the relevant connection
   behavior.
2. Correct shared Core abstractions and traits first.
3. Correct CMS and ERP services, imports, commands, and seeders.
4. Correct migration/schema helpers using the active schema connection.
5. Correct test assertions, fixtures, benchmarks, and diagnostics.
6. Re-scan every `DB::` occurrence and classify any deliberate remainder.

The refactor must preserve existing behavior when every model uses the default
connection. It must also prove representative behavior after assigning a model
to a non-default test connection.

## Verification

Verification consists of focused Pest tests for each changed subsystem, an
architecture test for future violations, SQLite migration coverage, and the
smallest relevant module suites. A final static inventory must account for every
remaining `DB::` occurrence and show why it is connection-neutral or explicitly
scoped.
