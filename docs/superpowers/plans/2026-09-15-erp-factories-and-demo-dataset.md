---
status: completed
created_on: 2026-09-15
completed_on: 2026-09-15
---
# ERP factories and demo dataset — Implementation Plan

> Test-first, task by task: write the failing test, run it, implement, run it green,
> `vendor/bin/pint --dirty`, commit inside `Modules/ERP`.

**Goal:** twelve model factories and a deterministic demo dataset in `DevERPDatabaseSeeder`, so ERP
tests stop rebuilding fixtures by hand and the module can be demonstrated.

**Architecture:** factories in `Modules\ERP\Database\Factories`, resolved by `newFactory()` on each
model (the `Core\Models\Setting` pattern). Company-scoped factories take their company from a
relation. Document numbers stay with the numbering observers. The demo dataset extends the existing
`DevERPDatabaseSeeder`, which the root `DevDatabaseSeeder` already discovers by glob.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-15-erp-factories-and-demo-dataset-design.md`

## Global Constraints

- `declare(strict_types=1);`, explicit types, `final` where the module's siblings are final.
- Tables come from `ERPTables::*->value`; never a literal table name.
- A factory never writes a document number and never creates a second `Company` when one was given.
- `database/factories/` already exists in ERP: no new folder, no new dependency.
- Run the narrowest tests: `php artisan test --compact Modules/ERP/tests/...`.

---

### Task 1: Factory resolution and `CompanyFactory`

**Files:**
- Create: `Modules/ERP/database/factories/CompanyFactory.php`
- Modify: `Modules/ERP/app/Models/Company.php` (`newFactory()`)
- Test: `Modules/ERP/tests/Integration/Factories/CompanyFactoryTest.php` (Integration, not Unit:
  Pest binds the Laravel `TestCase` to `Integration`, `Feature` and `Stress` only, so a `Unit` test
  has no database)

- [x] **Step 1: Failing test.** `Company::factory()->create()` persists a company whose required
      fields are populated and whose settings resolve (`ErpCompanySettings` reads it without
      throwing).
- [x] **Step 2: Run, confirm fail** (`newFactory()` absent, factory class missing).
- [x] **Step 3: Implement** the factory and the `newFactory()` override. Read
      `Modules/Core/database/factories/SettingFactory.php` first for the house shape.
- [x] **Step 4: Run, confirm pass.**
- [x] **Step 5: Pint + commit.**

---

### Task 2: The anagraphic core — `Party`, `Item`, `Warehouse`

**Files:**
- Create: `PartyFactory`, `ItemFactory`, `WarehouseFactory`
- Modify: the three models (`newFactory()`)
- Test: `Modules/ERP/tests/Integration/Factories/AnagraphicFactoriesTest.php`

**Interfaces:** `PartyFactory` exposes `->customer()` and `->supplier()` states.

- [x] **Step 1: Failing test**, including the scoping assertion that matters:
      `Party::factory()->for($company)->create()` reuses that company and does **not** create a
      second one (`Company::query()->count()` stays 1).
- [x] **Step 2: Run, confirm fail.**
- [x] **Step 3: Implement.**
- [x] **Step 4: Run, confirm pass.**
- [x] **Step 5: Pint + commit.**

---

### Task 3: The fiscal frame — `FiscalYear`, `FiscalPeriod`, `Account`

**Files:**
- Create: `FiscalYearFactory`, `FiscalPeriodFactory`, `AccountFactory`
- Modify: the three models
- Test: `Modules/ERP/tests/Integration/Factories/FiscalFactoriesTest.php`

**Interfaces:** `FiscalYearFactory->withPeriods(int $months = 12)` creates the year and its periods
in one call, since no test ever wants a year without them.

- [x] **Step 1: Failing test:** a year with twelve contiguous periods, no gap and no overlap; an
      `open()` / `closed()` state on the period.
- [x] **Step 2: Run, confirm fail.**
- [x] **Step 3: Implement.**
- [x] **Step 4: Run, confirm pass.**
- [x] **Step 5: Pint + commit.**

---

### Task 4: Documents — orders, delivery note, invoice

**Files:**
- Create: `SalesOrderFactory`, `SalesOrderLineFactory`, `PurchaseOrderFactory`,
  `PurchaseOrderLineFactory`, `DeliveryNoteFactory`, `InvoiceFactory`, `InvoiceLineFactory`
- Modify: the seven models
- Test: `Modules/ERP/tests/Integration/Factories/DocumentFactoriesTest.php`

**Interfaces:** `->withLines(int $count = 2)` on each header; `->numbered(string $number)` for the
tests that need a fixed value.

- [x] **Step 1: Failing test**, and this is the load-bearing assertion of the whole plan: a
      factory-created `Invoice` has **no** `reference`. Corrected while implementing: the number is
      allocated by `DocumentNumberAllocator` at **posting** time (and at the creation page for
      orders), not on save, so an unposted document legitimately has none. Posting itself stays
      covered by the service tests, which own the accounting chain a factory must not fabricate.
- [x] **Step 2: Run, confirm fail.**
- [x] **Step 3: Implement.** Let the observers run; the factory sets everything except the number.
- [x] **Step 4: Run, confirm pass**, including a case where two invoices in the same company get
      consecutive numbers.
- [x] **Step 5: Pint + commit.**

---

### Task 5: The demo dataset

**Files:**
- Modify: `Modules/ERP/database/seeders/DevERPDatabaseSeeder.php`
- Test: `Modules/ERP/tests/Feature/Seeders/DevERPDatabaseSeederTest.php` (seeders take a `DatabaseManager`, so the test resolves them through `$this->seed()` rather than instantiating them)

**Interfaces:** the existing taxonomy seeding is untouched; a `seedDemoDataset()` private method is
added and called from `run()` inside the same transaction.

- [x] **Step 1: Failing test:** after seeding, one company, one customer, one supplier, one
      warehouse, three items, and both document chains exist; **and** running the seeder a second
      time leaves every row count unchanged.
- [x] **Step 2: Run, confirm fail.**
- [x] **Step 3: Implement** with fixed, recognisable identifiers so tests and UI work can anchor on
      them, mirroring what `DevSAODatabaseSeeder` does for `SAO-1..SAO-8`.
- [x] **Step 4: Run, confirm pass**, then run the seeder against a dev database and open the ERP
      Filament panel to confirm the dataset is actually demonstrable.
- [x] **Step 5: Pint + commit.**

---

### Task 6: Prove the factories on real tests

**Files:**
- Modify: two or three existing test files with the heaviest manual setup (start with
  `Modules/ERP/tests/Feature/Filament/InvoicePostingActionsTest.php`)

- [x] **Step 1: Convert one file** from `::query()->create([...])` to factories, one test at a time.
- [x] **Step 2: Run that file, confirm it stays green** with the same assertions. If an assertion
      has to change, the factory is wrong: fix the factory, not the assertion.
- [x] **Step 3: Repeat for one or two more files**, then stop. The remaining conversion is
      opportunistic and out of this plan's scope.
- [x] **Step 4: Pint + commit.**

---

### Task 7: Documentation and closure

**Files:**
- Modify: `Modules/ERP/README.md` (factories and the demo dataset in the development section)
- Modify: `Modules/ERP/docs/rag/MODULE.md` (what the dev seeder produces)

- [x] **Step 1: Document** how to get an ERP dev dataset and which factories exist.
- [x] **Step 2: Run the ERP suite** (`php artisan test --compact Modules/ERP`) and report the
      result honestly in the delivery note.
- [x] **Step 3: Close this plan** with a `## Delivery status` section and a `**Documented in:**`
      line, and set the front-matter to `status: completed`.

## Self-Review notes

- Task 4 Step 1 is the assertion that keeps this honest: a factory that writes its own document
  number would make every numbering test pass for the wrong reason.
- Task 2 Step 1 guards the other silent failure mode, a factory quietly creating a second company
  and scoping the test away from its own data.
- Nothing here rewrites the existing suite, so a mistake in this plan cannot break tests that pass
  today.

## Delivery status (2026-09-15): shipped

**Documented in:** `Modules/ERP/README.md` and `Modules/ERP/docs/rag/MODULE.md`.

Twelve factories, the demo dataset, and two converted test files. The full ERP suite runs
**643 passed, 2 skipped, 2575 assertions** — 641 before this work, so the twelve factories added
their own coverage and broke nothing that existed.

Three things the implementation corrected in the plan, recorded here because the plan was wrong
about them and the next reader would repeat the mistake:

- **The document number is not assigned on save.** `DocumentNumberAllocator` allocates it at
  posting time, and at the creation page for orders, so an unposted document legitimately has no
  `reference`. The factories leave it null and expose `->numbered()` for the tests that need a
  fixed value. Posting itself stays with the service tests, which own the accounting chain.
- **Factory tests live in `tests/Integration`, not `tests/Unit`.** Pest binds the Laravel
  `TestCase` to Integration, Feature and Stress only, so a Unit test has no database.
- **Two invariants the first draft broke**: a party must belong to its document's company, and a
  line's item to its header's company. Both are resolved from the already-resolved attributes;
  handing the same `Company` factory to two fields silently created two companies.

Deliberately not done: converting the remaining 83 test files. That is opportunistic work, and a
single commit rewriting every fixture would have risked a working suite for no functional gain.
