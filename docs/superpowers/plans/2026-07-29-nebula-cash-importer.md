# Nebula legacy Symfony cash importer implementation plan

> **For Codex:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Status:** Blocked until the ERP external cash-import foundation is complete

**Goal:** Import Nebula's historical income, expenses, partner contributions,
partner withdrawals, and expense allocations into ERP from the supplied SQL
dump, with explicit mappings, exact decimal reconciliation, dry-run support, and
idempotent reruns.

**Architecture:** All Nebula schema knowledge, SQL parsing, fixtures, mapping
files, DTOs, and orchestration live in the proprietary
`laraplate-importers` repository. The adapter invokes only generic ERP import
contracts and services at runtime. The raw dump remains outside version control.

**Tech stack:** PHP 8.5, Pest 4, streaming MariaDB SQL parser, Laravel container
integration at runtime, ERP destination services from
`2026-07-29-erp-external-cash-import-foundation.md`.

**Authoritative audit:**
`../laraplate-importers/docs/source-audits/2024-10-20-nebula-sql.md`

## Accepted source semantics

| Source record | Destination |
|---|---|
| `movement.discr = in` | ERP `Income` movement |
| `movement.discr = out` | ERP `Expense` movement |
| `payment` | ERP `Contribution` movement |
| `transfer` | ERP `Withdrawal` movement |
| outgoing `movement_user` plus payments | ERP expense allocation |

Payments 823 and 876, totaling EUR 30, are accepted as valid partner
contributions even if the historical intent was different. They must not count
as customer income and must not block reconciliation.

## Non-negotiable safety

- Never commit the original `2024-10-20_nebula.sql` artifact.
- Never log credentials, password hashes, emails, or raw personal names.
- Read only the `nebula` database section; the dump also contains `mysql` and
  `laravel_tests`.
- Fixture data must be manually minimized and consistently pseudonymized.
- Parse numeric literals as strings. Never round-trip money through PHP float.
- Require `currency`, `timezone`, company, user, account, category, and pool
  mappings. Do not infer destination IDs from names.
- The initial persistent scope is financial only. Clients, contacts, works,
  sessions, quotations, price lists, and equipment remain later phases.

---

## Task 1: Extract a safe, minimal financial fixture

**Owner:** `laraplate-importers`

**Files:**

- Create: `tests/Fixtures/Nebula/nebula-cash-sample.sql`
- Create: `tests/Fixtures/Nebula/README.md`
- Modify: `.gitignore` if needed to reject full dump patterns

### Step 1: Define fixture coverage before copying rows

The fixture must contain only the needed table definitions and pseudonymized
rows for:

- two source users mapped to `Partner A` and `Partner B`;
- at least one client and work;
- one ordinary income movement with transfers;
- one expense with equal owed shares and unequal/multi-date payments;
- payments 823 and 876 and their two parent income movements;
- the participant-less movement;
- all six parent/participant discriminator mismatch shapes;
- one row using a MariaDB `DOUBLE` artifact;
- one doubled single quote and one escaped Unicode description;
- composite-key pivots needed to prove joins.

Preserve source IDs, dates, amounts, foreign keys, and discriminator values.
Replace names, emails, descriptions, URLs, hashes, and unrelated metadata.

### Step 2: Add provenance without sensitive paths

The fixture README records:

- source artifact label and SHA-256;
- source database name `nebula`;
- extraction date;
- included source IDs and why;
- pseudonymization rules;
- confirmation that credentials and unrelated databases are absent.

Do not record the user's home-directory path.

### Step 3: Prove the fixture is safe

```bash
rg -n -i 'password|remember_token|@|mysql database|laravel_tests' \
  tests/Fixtures/Nebula/nebula-cash-sample.sql
rg -n '823|876|movement|movement_user|payment|transfer' \
  tests/Fixtures/Nebula/nebula-cash-sample.sql
git diff --check
```

The first command must return no sensitive values. Table names in schema
comments may be allow-listed only after manual inspection.

### Step 4: Commit fixture separately

```bash
git add tests/Fixtures/Nebula .gitignore
git commit -m "test(importers): add anonymized Nebula cash fixture"
```

---

## Task 2: Introduce a source-neutral SQL dump reader

**Owner:** `laraplate-importers`

**Files:**

- Create: `src/Shared/Source/Sql/SqlNumericMode.php`
- Create: `src/Shared/Source/Sql/SqlInsertParser.php`
- Create: `src/Shared/Source/Sql/SqlCreateTableParser.php`
- Create: `src/Shared/Source/Sql/SqlDumpReader.php`
- Modify: `src/Acme/Source/Sql/SqlInsertParser.php`
- Modify: `src/Acme/Source/Sql/SqlCreateTableParser.php`
- Modify: `src/Acme/Source/Sql/SqlDumpReader.php`
- Modify: `composer.json`
- Create: `tests/Unit/Shared/SqlInsertParserTest.php`
- Create: `tests/Unit/Shared/SqlDumpReaderTest.php`
- Modify: `tests/Unit/SqlInsertParserTest.php`

### Step 1: Write parser regression tests first

Cover:

- integers remain integers;
- decimal and exponent literals remain their exact source strings in
  `PreserveDecimal` mode;
- `NULL` remains null;
- backslash escapes and doubled single quotes decode correctly;
- commas and parentheses inside quoted strings do not split fields;
- rows are streamed across 64 KiB chunk boundaries;
- only inserts under `USE \`nebula\`` are returned;
- same-named tables under `mysql` and `laravel_tests` are ignored;
- missing database/table definitions fail with a useful exception;
- current Acme fixture behavior remains green.

The doubled-quote test must expose and prevent the existing undefined
`$valuesSql` reference in `Acme\Source\Sql\SqlInsertParser::parseRow()`.

```php
expect(SqlInsertParser::parseRow("1,0.30000000000000004,'Sant''Anna'"))
    ->toBe([1, '0.30000000000000004', "Sant'Anna"]);
```

Run and confirm red:

```bash
composer test -- --filter='SqlInsertParser|SqlDumpReader'
```

### Step 2: Add shared autoloading

Add `"LaraplateImporters\\": "src/"` to Composer PSR-4 autoload while preserving
the existing `Acme\\` mapping. Run:

```bash
composer dump-autoload
```

Do not add a spreadsheet or SQL dependency.

### Step 3: Implement the shared parser

`SqlDumpReader` accepts:

```php
new SqlDumpReader(
    path: $path,
    database: 'nebula',
    numericMode: SqlNumericMode::PreserveDecimal,
);
```

Track `CREATE DATABASE`, `USE`, `CREATE TABLE`, and `INSERT INTO` statements
while streaming. The reader must never restore or execute SQL.

Move generic logic into the shared namespace. Keep the Acme public classes as
small compatibility facades using legacy numeric behavior where its existing
tests require it.

### Step 4: Verify parser and Acme regression suite

```bash
composer test
vendor/bin/pint --dirty
git diff --check
```

### Step 5: Commit

```bash
git add composer.json src/Shared src/Acme/Source/Sql tests/Unit
git commit -m "refactor(importers): share safe SQL dump reader"
```

---

## Task 3: Model and index the Nebula financial source

**Owner:** `laraplate-importers`

**Files:**

- Create: `src/LegacySymfony/Nebula/Data/NebulaMovement.php`
- Create: `src/LegacySymfony/Nebula/Data/NebulaMovementParticipant.php`
- Create: `src/LegacySymfony/Nebula/Data/NebulaPayment.php`
- Create: `src/LegacySymfony/Nebula/Data/NebulaTransfer.php`
- Create: `src/LegacySymfony/Nebula/Data/NebulaWorkClient.php`
- Create: `src/LegacySymfony/Nebula/Source/NebulaFinancialIndex.php`
- Create: `src/LegacySymfony/Nebula/Source/NebulaFinancialSource.php`
- Create: `tests/Unit/LegacySymfony/NebulaFinancialSourceTest.php`

### Step 1: Write fixture-to-DTO tests

Assert:

- exact row counts for each fixture table;
- movement-to-participant, payer, receiver, work, and client joins;
- effective dates are preserved as source strings until timezone validation;
- all amounts remain decimal strings;
- incoming movements expose their client identity;
- payments 823 and 876 expose user 4 and amounts `5.0000`/`25.0000`;
- orphan references throw a diagnostic containing table and source ID;
- duplicate primary/composite keys throw rather than overwrite.

### Step 2: Implement immutable source DTOs

DTOs contain only source facts and stable IDs. They must not reference ERP
models, accounts, Core users, or destination IDs.

### Step 3: Build one read-only index

Read each required table once, keyed by its stable source identity. Join in PHP
without changing raw values. Expose ordered iterators by effective date, then
record class, then numeric source ID, so reruns are deterministic.

### Step 4: Verify and commit

```bash
composer test -- --filter=NebulaFinancialSource
vendor/bin/pint --dirty
git add src/LegacySymfony/Nebula/Data src/LegacySymfony/Nebula/Source \
  tests/Unit/LegacySymfony/NebulaFinancialSourceTest.php
git commit -m "feat(importers): read Nebula financial source"
```

---

## Task 4: Add and validate the explicit Nebula mapping manifest

**Owner:** `laraplate-importers`

**Files:**

- Create: `src/LegacySymfony/Nebula/Config/NebulaMapping.php`
- Create: `src/LegacySymfony/Nebula/Config/NebulaMappingLoader.php`
- Create: `tests/Fixtures/Nebula/nebula-mapping.example.json`
- Create: `tests/Unit/LegacySymfony/NebulaMappingLoaderTest.php`

### Step 1: Write manifest validation tests

The manifest shape is:

```json
{
  "source_instance": "nebula",
  "database": "nebula",
  "currency": "EUR",
  "timezone": "Europe/Rome",
  "company_id": 1,
  "partner_pool_id": 1,
  "post_movements": false,
  "users": {
    "3": {"core_user_id": 10, "liability_account_id": 2103},
    "4": {"core_user_id": 11, "liability_account_id": 2104}
  },
  "movement_types": {
    "7": {"counterparty_account_id": 5607},
    "17": {"counterparty_account_id": 4201}
  }
}
```

Test missing/extra user mappings, unknown used movement types, invalid IANA
timezone, non-ISO currency, duplicate destination users, missing liability
accounts, and wrong scalar types. The example IDs are fixture-only placeholders.

### Step 2: Implement strict loading

Use `json_decode(..., flags: JSON_THROW_ON_ERROR)`. Reject unknown top-level and
nested keys so typos cannot silently select defaults. Do not validate destination
database existence here; that belongs to preflight.

### Step 3: Verify and commit

```bash
composer test -- --filter=NebulaMappingLoader
vendor/bin/pint --dirty
git add src/LegacySymfony/Nebula/Config tests/Fixtures/Nebula/nebula-mapping.example.json \
  tests/Unit/LegacySymfony/NebulaMappingLoaderTest.php
git commit -m "feat(importers): validate Nebula mapping manifest"
```

---

## Task 5: Map source rows to generic ERP inputs

**Owner:** `laraplate-importers`

**Files:**

- Create: `src/LegacySymfony/Nebula/Mapping/NebulaFingerprint.php`
- Create: `src/LegacySymfony/Nebula/Mapping/NebulaCashMovementMapper.php`
- Create: `src/LegacySymfony/Nebula/Mapping/NebulaExpenseAllocationMapper.php`
- Create: `src/LegacySymfony/Nebula/Mapping/NebulaEqualShareAllocator.php`
- Create: `src/LegacySymfony/Nebula/Reporting/NebulaReject.php`
- Create: `tests/Unit/LegacySymfony/NebulaCashMovementMapperTest.php`
- Create: `tests/Unit/LegacySymfony/NebulaExpenseAllocationMapperTest.php`

### Step 1: Write golden mapping tests

Assert:

- incoming parent -> `Income` with client/work evidence in description;
- outgoing parent -> `Expense`;
- payment -> `Contribution` using payer's liability account;
- transfer -> `Withdrawal` using receiver's liability account;
- source keys are `legacy_symfony:<instance>`;
- external IDs are `movement:<id>`, `payment:<id>`,
  `transfer:<id>`, and `movement-allocation:<id>`;
- fingerprints use canonical JSON with sorted keys and raw source values;
- dates use the manifest timezone and become immutable dates without a UTC day
  shift;
- source `DOUBLE` artifacts normalize with scale 4 and `HALF_UP`;
- payments 823/876 map only to contributions;
- an unmapped user/type produces a structured reject, not a guessed mapping.

### Step 2: Implement deterministic equal shares

For `calculation_mode = equal`, divide the normalized movement amount across
participants at scale 4. Assign any remainder deterministically by ascending
source user ID. Aggregate payment totals per user for `paid`; preserve each
individual dated payment as its own Contribution input.

Reject the participant-less movement and unsupported calculation modes with
source evidence. For the six discriminator mismatches, parent
`movement.discr` controls movement direction; retain the child mismatch in the
report and focused fixture result.

### Step 3: Reconcile every mapped expense

Before returning an allocation input, prove:

```text
sum(owed) = normalized movement total
sum(paid) = normalized movement total
```

An unbalanced movement becomes a reject. It must not be partially written.

### Step 4: Verify and commit

```bash
composer test -- --filter='NebulaCashMovementMapper|NebulaExpenseAllocationMapper'
vendor/bin/pint --dirty
git add src/LegacySymfony/Nebula/Mapping src/LegacySymfony/Nebula/Reporting \
  tests/Unit/LegacySymfony
git commit -m "feat(importers): map Nebula cash history"
```

---

## Task 6: Implement runtime preflight and importer orchestration

**Owner:** `laraplate-importers`

**Files:**

- Create: `src/LegacySymfony/Nebula/Import/NebulaPreflight.php`
- Create: `src/LegacySymfony/Nebula/Import/NebulaImportReport.php`
- Create: `src/LegacySymfony/Nebula/Importers/LegacySymfonySqlImporter.php`
- Create: `tests/Stubs/ERP/ErpRuntimeStubs.php`
- Create: `tests/Unit/LegacySymfony/NebulaPreflightTest.php`
- Create: `tests/Unit/LegacySymfony/LegacySymfonySqlImporterTest.php`

### Step 1: Write importer contract tests with stubs

Prove:

- the class implements `Modules\ERP\Import\Contracts\BulkImporterInterface`;
- constructor parameters are `path`, `mapping`, `dryRun`, and `limit`;
- preflight happens before the first destination write;
- ordering is parent movement, its allocations, then dated payment/transfer
  movements using deterministic tie breakers;
- `limit` counts source aggregates consistently and is documented;
- rejected aggregates do not stop valid independent aggregates;
- the return value is the number of created/updated destination records, not
  skipped records;
- dry-run delegates transaction rollback to the host and still produces totals;
- exceptions never include raw PII or the mapping contents.

### Step 2: Implement destination preflight

Resolve destination models/services from Laravel only at runtime. Verify:

- company and partner pool exist on the active ERP connection;
- pool belongs to company and uses manifest currency;
- every Core user exists and is a pool member;
- every contribution/withdrawal account is active, same-company `Liability`;
- every movement-type account is active, same-company `Revenue` or `Expense`
  according to the source direction;
- active `bank_cash` role exists;
- `post_movements` is false by default.

Return all mapping errors in one report.

### Step 3: Implement thin orchestration

The importer:

1. loads manifest and safe SQL source;
2. runs preflight;
3. maps source aggregates;
4. invokes `ExternalCashMovementImportService`;
5. invokes `ExternalExpenseAllocationService` for outgoing parents;
6. collects created, updated, skipped, rejected, and control-total counts;
7. returns created plus updated.

It never calls `Movement::create()`, writes journal tables, or writes
`core_record_origins` directly.

### Step 4: Verify and commit

```bash
composer test -- --filter='NebulaPreflight|LegacySymfonySqlImporter'
vendor/bin/pint --dirty
git add src/LegacySymfony/Nebula/Import src/LegacySymfony/Nebula/Importers \
  tests/Stubs/ERP tests/Unit/LegacySymfony
git commit -m "feat(importers): add Nebula ERP cash importer"
```

---

## Task 7: Add Laravel integration and rerun tests

**Owners:** `laraplate-importers`, then disposable Laraplate database

**Files:**

- Create: `tests/Integration/LegacySymfony/LegacySymfonySqlImporterIntegrationTest.php`
- Modify: `phpunit.xml` or `tests/Pest.php` only if the existing integration
  bootstrap requires it

### Step 1: Write end-to-end fixture assertions

Against a disposable ERP database, assert:

- exact destination movement count by all four types;
- exact cash balance and liability-account balances;
- expense owed/paid allocations by user;
- every imported record has one origin and fingerprint;
- unchanged persistent rerun creates zero records;
- changing an unposted fixture row updates the same movement;
- changing a posted fixture row rejects and leaves journal/origin untouched;
- an induced failure rolls back both destination row and origin;
- dry-run leaves all destination tables unchanged.

### Step 2: Run via the real command

```bash
php artisan erp:import \
  --bootstrap='/absolute/path/to/laraplate-importers/vendor/autoload.php' \
  --importer='LegacySymfony\Nebula\Importers\LegacySymfonySqlImporter' \
  --arg='path=/absolute/path/to/laraplate-importers/tests/Fixtures/Nebula/nebula-cash-sample.sql' \
  --arg='mapping=/absolute/path/to/nebula-mapping.test.json' \
  --dry-run
```

The integration test must construct absolute paths dynamically; never hard-code
a home directory.

### Step 3: Verify and commit

```bash
composer test
vendor/bin/pint --dirty --test
git diff --check
git add tests/Integration tests/Pest.php phpunit.xml
git commit -m "test(importers): verify Nebula cash import reruns"
```

Stage only files that actually changed.

---

## Task 8: Build and approve full-dump control totals

**Owner:** operator plus `laraplate-importers`

**Files:**

- Create: `src/LegacySymfony/Nebula/Reporting/NebulaControlTotals.php`
- Create: `tests/Unit/LegacySymfony/NebulaControlTotalsTest.php`
- Modify: `docs/source-audits/2024-10-20-nebula-sql.md`
- Create: `docs/runbooks/nebula-cash-import.md`

### Step 1: Test exact decimal accumulation

Control totals must use decimal strings and group by:

- movement direction and movement type;
- calendar year;
- payer and receiver source user;
- contribution and withdrawal;
- owed and paid allocation user;
- created, updated, skipped, rejected;
- exceptional IDs and anomaly class.

### Step 2: Run full-dump dry-run

Create a local mapping file outside version control, set task-specific path
variables, and run:

```bash
export NEBULA_DUMP_PATH='/absolute/private/path/2024-10-20_nebula.sql'
export NEBULA_MAPPING_PATH='/absolute/private/path/nebula-mapping.json'
php artisan erp:import \
  --bootstrap='/srv/http/laraplate-stack/laraplate-importers/vendor/autoload.php' \
  --importer='LegacySymfony\Nebula\Importers\LegacySymfonySqlImporter' \
  --arg="path=${NEBULA_DUMP_PATH}" \
  --arg="mapping=${NEBULA_MAPPING_PATH}" \
  --dry-run
```

Record only aggregate totals and source IDs needed for rejects. Do not paste raw
rows or personal data into the audit.

Expected structural controls from the static audit:

- 376 movements: 289 expense and 87 income;
- 451 contributions: 290 from source user 3 and 161 from source user 4;
- 120 withdrawals: 86 to source user 3 and 34 to source user 4;
- payments 823 and 876 contribute EUR 30 in total;
- source users 5 and 6 have no payment/transfer cash rows.

The ten previously identified scale-4 parent/child mismatches (movement IDs
720–729) remain rejects until their exact normalized totals are reviewed. Do not
force them through to make counts match.

### Step 3: Obtain explicit persistence approval

Stop after the dry-run report. Persistent import requires operator approval of:

- company, user, account, pool, timezone, and EUR mappings;
- reject list;
- exact decimal control totals;
- whether `post_movements` remains false for the first run.

### Step 4: Commit report code and sanitized docs

```bash
composer test -- --filter=NebulaControlTotals
vendor/bin/pint --dirty
git diff --check
git add src/LegacySymfony/Nebula/Reporting/NebulaControlTotals.php \
  tests/Unit/LegacySymfony/NebulaControlTotalsTest.php \
  docs/source-audits/2024-10-20-nebula-sql.md docs/runbooks/nebula-cash-import.md
git commit -m "docs(importers): add Nebula cash import runbook"
```

## Completion gate

The Nebula cash importer is ready for a persistent run only when the anonymized
fixture and package suite pass, ERP integration proves dry-run and idempotency,
all mappings preflight successfully, full-dump decimal totals are reviewed, and
the operator approves the reject list. SPLID and Tricount reconciliation begins
after this authoritative Nebula baseline exists; it is not part of this plan.
