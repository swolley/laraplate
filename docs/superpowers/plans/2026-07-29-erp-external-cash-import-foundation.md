# ERP external cash-import foundation implementation plan

> **For Codex:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Status:** Complete (2026-08-03)

**Goal:** Give ERP a generic, idempotent import entry point and a source-neutral
cash-movement ingestion service that can represent income, expenses, partner
contributions, and partner withdrawals without putting Nebula-specific code in
the AGPL backend.

**Architecture:** Core owns the generic external-record identity registry. ERP
owns its importer marker, command, typed destination inputs, mutation policy, and
accounting invariants. Proprietary adapters call these public services at
runtime; ERP never depends on `laraplate-importers`.

**Tech stack:** Laravel 12, PHP 8.5, Eloquent, Pest 4, existing Core bulk-import
framework and ERP journal services.

**Design inputs:**

- `docs/superpowers/specs/2026-07-29-erp-import-source-reconciliation-design.md`
- `../laraplate-importers/docs/source-audits/2024-10-20-nebula-sql.md`
- `docs/superpowers/plans/2026-07-22-erp-external-source-importers.md`

## Locked behavior

- `income`: debit cash, credit an active same-company `Revenue` account.
- `expense`: debit an active same-company `Expense` account, credit cash.
- `contribution`: debit cash, credit an active same-company `Liability` account.
- `withdrawal`: debit an active same-company `Liability` account, credit cash.
- `contribution` and `withdrawal` are generic accounting terms, not
  Nebula-specific hooks.
- A source identity is `(referable_type, source_key, external_id)`.
- A SHA-256 fingerprint is computed by the adapter from normalized source
  fields. Unchanged reruns skip.
- A changed unposted movement may be updated. A changed posted movement rejects
  with structured evidence; import code never rewrites a posted journal.
- Source timestamps are evidence only and never replace destination audit
  timestamps.
- All money passed into ERP is a decimal string at scale 4. No import boundary
  accepts floats.

---

## Task 1: Add source-neutral origin fingerprint metadata

**Owner:** `Modules/Core`

**Files:**

- Create: `Modules/Core/database/migrations/2026_07_29_120000_add_import_metadata_to_record_origins_table.php`
- Modify: `Modules/Core/app/Models/RecordOrigin.php`
- Modify: `Modules/Core/database/factories/RecordOriginFactory.php`
- Create: `Modules/Core/app/Import/ValueObjects/ExternalRecordIdentity.php`
- Create: `Modules/Core/app/Import/Enums/ExternalRecordState.php`
- Create: `Modules/Core/app/Import/Support/RecordOriginRegistry.php`
- Create: `Modules/Core/tests/Feature/Import/RecordOriginRegistryTest.php`

### Step 1: Write failing registry tests

Cover:

1. an unknown identity returns `Missing`;
2. the same identity and fingerprint returns `Unchanged`;
3. the same identity with a different fingerprint returns `Changed`;
4. registration stores the referable morph, fingerprint, source timestamp,
   label, and URL on the referable model's connection;
5. a source key or external ID from another source does not collide;
6. when present, a fingerprint must be 64-character lowercase hexadecimal;
   null remains allowed only for existing compatibility callers.

Use a real Core test model and `RefreshDatabase`; do not mock Eloquent.

```php
$identity = new ExternalRecordIdentity(
    sourceKey: 'legacy_symfony:nebula',
    externalId: 'movement:42',
    fingerprint: hash('sha256', 'normalized payload'),
    sourceUpdatedAt: CarbonImmutable::parse('2024-09-21T16:40:53+00:00'),
);

expect($registry->inspect($movement, $identity))->toBe(ExternalRecordState::Missing);
```

Run and confirm red:

```bash
php artisan test --compact Modules/Core/tests/Feature/Import/RecordOriginRegistryTest.php
```

### Step 2: Add the migration and model metadata

Add nullable `char(64) fingerprint` and nullable
`timestamp source_updated_at` columns. Index `source_key` plus
`source_updated_at`; keep the existing identity unique constraint unchanged.

Add both attributes to `RecordOrigin::$fillable` and cast
`source_updated_at` to `immutable_datetime`.

### Step 3: Implement the minimal registry

`RecordOriginRegistry` must:

- query through `$referable->getConnection()`;
- accept string external IDs, including composite IDs;
- expose `inspect(Model $referable, ExternalRecordIdentity $identity)`;
- expose `referableId(Model $referable, sourceKey, externalId): ?int`;
- expose `register(Model $referable, ExternalRecordIdentity $identity, ...)`;
- use `updateOrInsert` keyed by the existing identity tuple;
- never decide whether a destination record is mutable.

`ExternalRecordState` has exactly `Missing`, `Unchanged`, and `Changed`.

### Step 4: Run focused tests and format

```bash
php artisan test --compact Modules/Core/tests/Feature/Import/RecordOriginRegistryTest.php
vendor/bin/pint --dirty
```

### Step 5: Commit Core only

```bash
git add Modules/Core/app/Import Modules/Core/app/Models/RecordOrigin.php \
  Modules/Core/database/factories/RecordOriginFactory.php \
  Modules/Core/database/migrations/2026_07_29_120000_add_import_metadata_to_record_origins_table.php \
  Modules/Core/tests/Feature/Import/RecordOriginRegistryTest.php
git commit -m "feat(core): track imported record fingerprints"
```

---

## Task 2: Make CMS delegate persistent identities to Core

**Owner:** `Modules/CMS`

**Files:**

- Modify: `Modules/CMS/app/Import/Support/ExternalReferenceLocator.php`
- Modify: `Modules/CMS/app/Providers/ImportServiceProvider.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportReferenceResolverTest.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportConnectionContextTest.php`

### Step 1: Add a failing compatibility test

Prove that existing CMS integer identities still resolve and register exactly as
before when `RecordOriginRegistry` is injected. Preserve the CMS-only import-slug
fallback.

```bash
php artisan test --compact \
  Modules/CMS/tests/Feature/Import/ImportReferenceResolverTest.php \
  Modules/CMS/tests/Feature/Import/ImportConnectionContextTest.php
```

### Step 2: Delegate only the persistent-registry branch

Inject `RecordOriginRegistry` into `ExternalReferenceLocator`. Replace its raw
`core_record_origins` lookup and write with registry calls. Build an identity
with no fingerprint for the legacy CMS compatibility path; do not move
translation-slug knowledge into Core.

### Step 3: Verify focused CMS imports

```bash
php artisan test --compact Modules/CMS/tests/Feature/Import
vendor/bin/pint --dirty
```

### Step 4: Commit CMS only

```bash
git add Modules/CMS/app/Import/Support/ExternalReferenceLocator.php \
  Modules/CMS/app/Providers/ImportServiceProvider.php \
  Modules/CMS/tests/Feature/Import
git commit -m "refactor(cms): use core import origin registry"
```

---

## Task 3: Add contribution and withdrawal movement types

**Owner:** `Modules/ERP`

**Files:**

- Modify: `Modules/ERP/app/Casts/MovementType.php`
- Create: `Modules/ERP/database/migrations/2026_07_29_121000_extend_movement_type_for_funding.php`
- Modify: `Modules/ERP/app/Services/Cash/MovementPostingService.php`
- Modify: `Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php`

### Step 1: Write failing accounting tests

Extend the fixture with a same-company active `Liability` account. Assert exact
decimal journal signs:

| Type | Cash line | Counterparty line |
|---|---:|---:|
| Contribution 40 | `40.0000` | `-40.0000` |
| Withdrawal 15 | `-15.0000` | `15.0000` |

Also assert:

- contribution/withdrawal reject Revenue, Expense, Asset, and Equity accounts;
- income/expense behavior remains unchanged;
- inactive, cross-company liability accounts reject;
- posting each valid type twice remains idempotent;
- cash balance after `+100 -30 +40 -15` is `95.0000`.

Run and confirm red:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php
```

### Step 2: Extend the enum and database enum

Add:

```php
case Contribution = 'contribution';
case Withdrawal = 'withdrawal';
```

The migration must follow the existing MySQL enum-extension pattern. SQLite
needs no alteration. `down()` is a documented no-op because shrinking a live
enum could invalidate rows.

### Step 3: Implement an explicit posting matrix

Avoid a binary income/expense conditional. Use exhaustive `match` expressions:

```php
$expected_kind = match ($movement->type) {
    MovementType::Income => AccountKind::Revenue,
    MovementType::Expense => AccountKind::Expense,
    MovementType::Contribution, MovementType::Withdrawal => AccountKind::Liability,
};
```

The cash-in branch is `Income` plus `Contribution`; the cash-out branch is
`Expense` plus `Withdrawal`.

### Step 4: Verify migration and posting

```bash
php artisan migrate
php artisan test --compact \
  Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php \
  Modules/ERP/tests/Feature/MigrationConnectionAffinityTest.php
vendor/bin/pint --dirty
```

### Step 5: Commit ERP accounting only

```bash
git add Modules/ERP/app/Casts/MovementType.php \
  Modules/ERP/app/Services/Cash/MovementPostingService.php \
  Modules/ERP/database/migrations/2026_07_29_121000_extend_movement_type_for_funding.php \
  Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php
git commit -m "feat(erp): post partner cash movements"
```

---

## Task 4: Add the ERP import host

**Owner:** `Modules/ERP`

**Files:**

- Create: `Modules/ERP/app/Console/ImportCommand.php`
- Create: `Modules/ERP/app/Import/Contracts/BulkImporterInterface.php`
- Create: `Modules/ERP/app/Import/Support/ErpBulkImporterResolver.php`
- Create: `Modules/ERP/app/Import/Support/SiblingImportersDiscovery.php`
- Create: `Modules/ERP/tests/Stubs/Import/SuccessfulErpImporter.php`
- Create: `Modules/ERP/tests/Stubs/Import/WrongModuleImporter.php`
- Create: `Modules/ERP/tests/Feature/Import/ImportCommandTest.php`

### Step 1: Port the host contract tests before production code

Mirror the proven CMS command behavior but assert the ERP marker:

- `erp:import` is registered by module command discovery;
- options are inherited from `AbstractImportCommand`;
- constructor parameters from repeated `--arg` values resolve;
- `dryRun` and `limit` reach the importer;
- a Core-only or CMS-only importer rejects before `import()`;
- bootstrap errors, missing classes, and importer exceptions fail visibly;
- dry-run rolls back the importer-selected connection;
- sibling discovery returns only ERP marker implementations.

```bash
php artisan test --compact Modules/ERP/tests/Feature/Import/ImportCommandTest.php
```

### Step 2: Implement the thin host

`BulkImporterInterface` extends Core's interface without adding methods.
`ErpBulkImporterResolver` wraps `ContainerBulkImporterResolver` using the ERP
marker. `SiblingImportersDiscovery` wraps
`FilesystemImportPluginDiscovery`, rooted at `../laraplate-importers`.

`ImportCommand` extends `AbstractImportCommand` and declares:

```php
protected $name = 'erp:import';
protected $description =
    'Run a bulk ERP import through an external importer plugin <fg=green>(💼 Modules\\ERP)</fg=green>';
```

Do not add manual command registration: `ModuleServiceProvider` already discovers
concrete commands in `app/Console`.

### Step 3: Verify command isolation

```bash
php artisan test --compact \
  Modules/ERP/tests/Feature/Import/ImportCommandTest.php \
  Modules/CMS/tests/Feature/Import/ImportCommandTest.php
php artisan list | rg 'cms:import|erp:import'
vendor/bin/pint --dirty
```

### Step 4: Commit ERP host only

```bash
git add Modules/ERP/app/Console/ImportCommand.php Modules/ERP/app/Import \
  Modules/ERP/tests/Stubs/Import Modules/ERP/tests/Feature/Import/ImportCommandTest.php
git commit -m "feat(erp): add module import entry point"
```

---

## Task 5: Add typed external cash-movement ingestion

**Owner:** `Modules/ERP`

**Files:**

- Create: `Modules/ERP/app/Import/Data/ExternalCashMovementInput.php`
- Create: `Modules/ERP/app/Import/Enums/ImportMutation.php`
- Create: `Modules/ERP/app/Import/ValueObjects/CashMovementImportResult.php`
- Create: `Modules/ERP/app/Import/Exceptions/PostedImportConflict.php`
- Create: `Modules/ERP/app/Import/Services/ExternalCashMovementImportService.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Create: `Modules/ERP/tests/Feature/Import/ExternalCashMovementImportServiceTest.php`

### Step 1: Write the destination-contract tests

Construct `ExternalCashMovementInput` with named arguments:

```php
new ExternalCashMovementInput(
    companyId: (int) $company->id,
    type: MovementType::Contribution,
    occurredOn: CarbonImmutable::parse('2022-12-03'),
    amount: '5.0000',
    currency: 'EUR',
    counterpartyAccountId: (int) $partner_account->id,
    description: 'Legacy cash adjustment',
    sourceKey: 'legacy_symfony:nebula',
    externalId: 'payment:823',
    fingerprint: hash('sha256', 'fixture payment 823'),
    sourceUpdatedAt: CarbonImmutable::parse('2022-12-03T00:00:00+01:00'),
    post: false,
);
```

Cover:

- first ingest creates one movement and origin;
- unchanged ingest returns `Skipped` and makes no writes;
- changed unposted ingest updates the same movement and fingerprint;
- changed posted ingest throws `PostedImportConflict` with source identity and
  local movement ID;
- an invalid decimal, currency, account, company, or type fails atomically;
- `post: true` delegates to `MovementPostingService`;
- `post: false` never creates a journal;
- a failure leaves neither movement nor origin;
- the service respects the destination model connection.

Run and confirm red:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Import/ExternalCashMovementImportServiceTest.php
```

### Step 2: Implement immutable input and result types

Reject floats in the constructor. Normalize currency to uppercase and amount
through ERP `Decimal::format()`. `ImportMutation` has `Created`, `Updated`, and
`Skipped`. The result carries the mutation, movement ID, and optional journal ID.

### Step 3: Implement the transactional service

Inside `ConnectionScopedTransaction`:

1. inspect the origin identity;
2. return `Skipped` when unchanged;
3. create or lock the existing movement;
4. reject changed records when `posted_journal_entry_id` is set;
5. validate and save through the model/domain services;
6. post only when requested;
7. register the new fingerprint after all destination writes succeed.

Never accept a source table name, Nebula discriminator, participant name, or
mapping file in this service.

### Step 4: Verify focused import behavior

```bash
php artisan test --compact \
  Modules/ERP/tests/Feature/Import/ExternalCashMovementImportServiceTest.php \
  Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php \
  Modules/ERP/tests/Feature/Services/DirectRuntimeConnectionAffinityTest.php
vendor/bin/pint --dirty
```

### Step 5: Commit ERP destination service

```bash
git add Modules/ERP/app/Import Modules/ERP/app/Providers/ERPServiceProvider.php \
  Modules/ERP/tests/Feature/Import/ExternalCashMovementImportServiceTest.php
git commit -m "feat(erp): ingest external cash movements"
```

---

## Task 6: Add typed imported expense allocations

**Owner:** `Modules/ERP`

**Files:**

- Create: `Modules/ERP/app/Import/Data/ExternalExpenseAllocationInput.php`
- Create: `Modules/ERP/app/Import/Services/ExternalExpenseAllocationService.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Create: `Modules/ERP/tests/Feature/Import/ExternalExpenseAllocationServiceTest.php`

### Step 1: Write failing allocation tests

Cover:

- only an unposted `Expense` movement can be allocated;
- every mapped user must be a pool member;
- owed and paid totals each equal the movement amount;
- input values must be decimal strings, never floats;
- replacing changed unposted allocations is atomic;
- unchanged allocation fingerprints skip;
- posted movement changes reject;
- contribution movements remain separate and are not generated by this service.

The input must carry a stable identity for the source parent allocation, such as
`movement-allocation:42`, plus:

```php
shares: [
    $user_a_id => ['owed' => '50.0000', 'paid' => '70.0000'],
    $user_b_id => ['owed' => '50.0000', 'paid' => '30.0000'],
]
```

### Step 2: Implement as a narrow wrapper

Delegate balance validation and replacement to
`PartnerPoolSettlementService::allocate()`. Use `RecordOriginRegistry` for the
allocation identity and fingerprint. Do not add dates to
`MovementAllocation`; dated partner cash events are separate ERP movements.

### Step 3: Verify

```bash
php artisan test --compact \
  Modules/ERP/tests/Feature/Import/ExternalExpenseAllocationServiceTest.php \
  Modules/ERP/tests/Feature/Services/PartnerPoolSettlementServiceTest.php
vendor/bin/pint --dirty
```

### Step 4: Commit

```bash
git add Modules/ERP/app/Import/Data/ExternalExpenseAllocationInput.php \
  Modules/ERP/app/Import/Services/ExternalExpenseAllocationService.php \
  Modules/ERP/app/Providers/ERPServiceProvider.php \
  Modules/ERP/tests/Feature/Import/ExternalExpenseAllocationServiceTest.php
git commit -m "feat(erp): ingest external expense allocations"
```

---

## Task 7: Document and verify the complete foundation

**Files:**

- Modify: `Modules/Core/docs/IMPORT_FRAMEWORK.md`
- Modify: `Modules/ERP/README.md`
- Modify: `Modules/ERP/docs/rag/MODULE.md`
- Modify: the existing ERP operator/developer glossary files identified by
  `rg -l "PartnerPool|MovementPostingService" Modules/ERP/docs`
- Modify: `docs/superpowers/plans/2026-07-22-erp-external-source-importers.md`

### Step 1: Document the public runtime boundary

Document:

- `erp:import` invocation and ERP marker contract;
- the four movement types and account-kind matrix;
- identity/fingerprint semantics;
- changed-unposted versus changed-posted policy;
- why contributions/withdrawals are distinct from pool allocations;
- decimal-string and explicit-mapping requirements.

Mark Tasks 1–2 of the umbrella plan complete only after their tests pass.

### Step 2: Run final verification

```bash
php artisan test --compact \
  Modules/Core/tests/Feature/Import/RecordOriginRegistryTest.php \
  Modules/CMS/tests/Feature/Import \
  Modules/ERP/tests/Feature/Import \
  Modules/ERP/tests/Feature/Services/MovementPostingServiceTest.php \
  Modules/ERP/tests/Feature/Services/PartnerPoolSettlementServiceTest.php
vendor/bin/pint --dirty --test
php artisan list | rg 'cms:import|erp:import'
git diff --check
```

Review the worktree before staging. Do not include unrelated generated assets,
root `composer.lock`, or pre-existing ERP changes.

### Step 3: Commit documentation only

```bash
git add Modules/Core/docs/IMPORT_FRAMEWORK.md Modules/ERP/README.md \
  Modules/ERP/docs docs/superpowers/plans/2026-07-22-erp-external-source-importers.md
git commit -m "docs(erp): document external cash import foundation"
```

## Completion gate

This foundation is complete when all four movement types post correctly, the
ERP host rejects non-ERP importers, external identities are idempotent, changed
posted movements are protected, allocation writes remain separate from dated
cash events, and the focused Core/CMS/ERP suites pass. It does not require or
authorize any source-specific parser in Laraplate.
