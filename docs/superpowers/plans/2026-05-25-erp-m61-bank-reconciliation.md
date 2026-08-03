# ERP M6.1 - Bank Reconciliation Implementation Plan

> **Point 0 status:** M6.1 plus difference journals, CAMT.053, and the minimal MT940 slice are implemented. This plan is historical; current ERP status is `Modules/ERP/docs/STATUS.md`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Import CSV bank statements, match statement lines to existing `Payment` records, and
provide a minimal reconciliation UI.

**Architecture:** Add ERP-prefixed bank tables and a parser/service layer. The canonical match is
`erp_bank_statement_lines.matched_payment_id -> erp_payments.id`. No new Composer dependencies.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, Eloquent.

---

## File Map

- Modify: `Modules/ERP/app/Enums/ERPTables.php`
- Create: ERP bank migrations under `Modules/ERP/database/migrations/`
- Create: `Modules/ERP/app/Casts/BankStatementLineStatus.php`
- Create: `Modules/ERP/app/Models/BankAccount.php`
- Create: `Modules/ERP/app/Models/BankStatement.php`
- Create: `Modules/ERP/app/Models/BankStatementLine.php`
- Create: `Modules/ERP/app/Data/Bank/BankStatementLineData.php`
- Create: `Modules/ERP/app/Contracts/BankStatementParser.php`
- Create: `Modules/ERP/app/Services/Bank/CsvBankStatementParser.php`
- Create: `Modules/ERP/app/Services/Bank/BankStatementImportService.php`
- Create: `Modules/ERP/app/Services/Bank/BankReconciliationService.php`
- Create: Filament resources/pages for bank accounts, statements, and reconciliation.
- Create: focused tests under `Modules/ERP/tests/Feature/Services/`.

## Task 1: Schema And Models

**Decisions:**
- Add `ERPTables::BankAccounts = 'erp_bank_accounts'`.
- Add `ERPTables::BankStatements = 'erp_bank_statements'`.
- Add `ERPTables::BankStatementLines = 'erp_bank_statement_lines'`.
- Use `Modules\ERP\Concerns\BelongsToCompany` on `BankAccount`.
- Existing `Payment::bank_account_id` should become a real FK to `erp_bank_accounts` if the
  current in-place migration can be updated safely.

**Schema:**
- `erp_bank_accounts`: `id`, `company_id`, `name`, `iban`, `bic`, `currency`, `is_active`,
  timestamps.
- `erp_bank_statements`: `id`, `bank_account_id`, `statement_date`, `opening_balance`,
  `closing_balance`, `imported_at`, timestamps.
- `erp_bank_statement_lines`: `id`, `bank_statement_id`, `transaction_date`, `value_date`,
  `description`, `amount`, `reference`, `status`, `matched_payment_id`, timestamps.

**Implementation notes:**
- Migrations must use `ERPTables::*->value`, `ERPMigrateUtils::companyForeign()`, and explicit FK
  names matching sibling migrations.
- Schema tests must assert against `ERPTables::BankAccounts->value`, not literal
  `bank_accounts`.
- Models must extend `Modules\Core\Overrides\Model`.

## Task 2: CSV Import

**Interfaces:**
- `BankStatementParser::parse(string $content): array`
- Return values are `BankStatementLineData` objects under `Modules\ERP\Data\Bank`.

**Parser behavior:**
- Default CSV header: `date,description,amount,reference`.
- Use `str_getcsv()` and `CarbonImmutable::parse()`.
- Skip empty rows.
- Throw `ValidationException` for rows missing date/description/amount.

**Import service behavior:**
- `BankStatementImportService::import(BankAccount $account, string $content, CarbonInterface $statementDate, string $openingBalance, string $closingBalance): BankStatement`
- Create one statement and child lines in a transaction.
- Store imported lines as `BankStatementLineStatus::Unmatched`.
- Tests must create `Company` and `BankAccount` manually.

## Task 3: Reconciliation Service

**Service methods:**

```php
public function match(BankStatementLine $line, Payment $payment): void;
public function unmatch(BankStatementLine $line): void;
public function exclude(BankStatementLine $line): void;
public function suggest(BankStatementLine $line): Collection;
```

**Rules:**
- `match()` sets `status = Matched` and `matched_payment_id`.
- `unmatch()` clears `matched_payment_id` and restores `Unmatched`.
- `exclude()` sets `Excluded` and clears any match.
- `suggest()` searches payments in the same company by:
  - absolute amount within EUR 1.00;
  - payment date within +/- 5 days;
  - optional reference similarity against `Payment::reference`.
- Do not invent an inverse matched-bank-line field on payments.

**Difference journals:**
- Defer automatic difference journal posting unless tests and account-role policy are specified.
- If implemented in this milestone, use current `JournalPostingService` signed amount format and
  existing account-role lookup patterns.

## Task 4: Filament UI

**Resources:**
- `BankAccountResource`: CRUD for active bank accounts.
- `BankStatementResource`: list imported statements and provide CSV import action.
- `BankReconciliationPage`: show unmatched lines and suggested payments.

**UI constraints:**
- Resolve company with `current_company_id()` or an explicit filter.
- File upload action must read stored file content through Laravel storage.
- Avoid complex drag/drop matching in v1; a modal select with suggestions is enough.

## Test Plan

Run focused tests:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php
php artisan test --compact Modules/ERP/tests/Feature/Services/BankReconciliationServiceTest.php
vendor/bin/pint --dirty
```

Test scenarios:
- Schema uses `erp_*` tables and `ERPTables`.
- CSV import creates statement and unmatched lines.
- Match/unmatch/exclude state transitions work.
- Suggestions are scoped to company and date/amount window.

## Assumptions

- CSV is the only import driver in M6.1.
- Payment matching is manual-first with suggestions, not fully automatic posting.
- CAMT.053 / MT940 are backlog.

## Implementation Status / Verification

- Implemented: `erp_bank_accounts`, `erp_bank_statements`, `erp_bank_statement_lines`, bank
  account FK on payments, Eloquent models, CSV importer, manual match/unmatch/ignore
  reconciliation service, ranked payment suggestions, bank account/statement Filament resources,
  CSV upload action, and a minimal `BankReconciliationPage`.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php Modules/ERP/tests/Feature/Services/BankReconciliationServiceTest.php`
  - included in the combined ERP focused command documented in the final verification note
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
- Additional verification on 2026-05-30:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/BankReconciliationServiceTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php --filter "bank reconciliation"`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentRouteSmokeTest.php`
