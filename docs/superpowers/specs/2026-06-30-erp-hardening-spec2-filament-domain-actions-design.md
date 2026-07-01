# ERP Hardening — Filament Domain Actions & State-Aware Policies (Spec 2, Phase 2A)

**Status:** Approved design, ready for implementation planning  
**Date:** 2026-06-30  
**Module:** `Modules/ERP`  
**Depends on:** Spec 1 (implemented + patch `971851d` / Core `15b11c8`)

---

## Context

Spec 1 hardened correctness, decimal money math, and generic-CRUD write integrity. Spec 2
Phase 2A completes the **M4 follow-up** deferred in
`docs/superpowers/plans/2026-05-25-erp-m4-policies-filament-reporting.md` (Tasks 1–2, 4) and the
“Out of Scope → Spec 2” Filament/policy items from Spec 1.

Backend services for most domain actions already exist and are covered by integration/feature tests.
The gap is **Filament page actions**, **seeded domain permissions**, and **state-aware policy
guards** so UI authorization matches business state — not just DB permission rows.

**Phase 2A scope (this spec):**

| Area | In scope |
|------|----------|
| Filament domain actions | Fiscal period close/reopen, fiscal year close, journal reverse, DDT post/unpost, sales order amend |
| Seeded abilities | `close`, `reopen`, `reverse`, `amend`, `force_post` |
| State-aware policies | Guards on the abilities above + existing `post`/`unpost` |
| Form hardening | Remove direct `is_closed` toggles that bypass services |

**Explicitly deferred to Spec 2 Phase 2B / later specs:**

- Party price-rule UI, bank difference journals, automatic NC/ND creation
- Quotation `unlock`, document sequence `reset`
- Spec 3 domain HTTP action endpoints and exposure governance

---

## Goals

1. Expose existing domain services through Filament header actions with the same patterns as
   `InvoicePostingActions`.
2. Seed permissions only for abilities that have a wired UI action (M4 rule).
3. Make `ERPModelPolicy` the single authorization source: **state guard first**, then permission
   check (superadmin still bypasses permission, never state).
4. Replace CRUD form workarounds (`is_closed` toggle) with read-only display + service-driven
   actions.

## Non-goals

- New accounting business rules (services stay as-is unless a wiring bug is found).
- Browser click-through / Livewire Filament tests (follow M4 smoke + policy/service tests).
- Automatic credit/debit notes, bank difference journals, pricing UI.
- Core or Spec 3 API routes.

---

## Approach

### 1. Action class pattern (reuse invoice precedent)

Create focused action factories under
`Modules/ERP/app/Filament/Resources/{Entity}/Actions/` mirroring
`InvoicePostingActions.php`:

| Class | Page(s) | Service path |
|-------|---------|--------------|
| `FiscalPeriodActions` | `EditFiscalPeriod` | `FiscalPeriodCloser::closePeriod()` / `reopenPeriod()` |
| `FiscalYearActions` | `EditFiscalYear` | `FiscalPeriodCloser::closeYear()` |
| `JournalEntryActions` | `ViewJournalEntry` (primary), optionally `EditJournalEntry` | `JournalPostingService::reverse()` |
| `DeliveryNotePostingActions` | `EditDeliveryNote` | Observer path via `posted_at` update (same as invoice) |
| `SalesOrderAmendmentActions` | `EditSalesOrder` | `SalesOrderAmendmentService::amend()` |

Each action:

- `->authorize(fn ($record) => auth()->user()?->can('<ability>', $record) ?? false)`
- `->visible(fn ($record) => …)` may duplicate state hints for UX, but **policy is authoritative**
- `->requiresConfirmation()` for mutating operations
- Success/error `Notification` + `$this->refreshFormData()` / redirect where needed

**Delivery note post/unpost:** call a small static helper (like `InvoicePostingActions::postInvoice`)
that sets `posted_at` to `now()` or `null`. Do **not** call non-existent
`DeliveryNoteInventoryService::post()` / `::unpost()` — inventory runs in
`DeliveryNoteObserver::saving()` when `posted_at` changes.

**Journal reverse:** modal with required `reversal_reason` text field; resolve company from record;
call `JournalPostingService::reverse($entry, $company, $reason, auth()->id())`; redirect to new
reversal entry view on success.

**Sales order amend:** on success redirect to the new draft amendment’s edit page.

### 2. Permissions (seeder)

Extend `ERPDatabaseSeeder::domainPermissions()`:

| Model | New abilities |
|-------|----------------|
| `FiscalPeriod` | `.close`, `.reopen` |
| `FiscalYear` | `.close` |
| `JournalEntry` | `.reverse` |
| `SalesOrder` | `.amend` |
| `Invoice` | `.force_post` |

Keep using `newInstanceWithoutConstructor()` + `getConnectionName() ?? 'default'` + `getTable()`
(Spec 1 permission-prefix note).

Update `ErpDomainPermissionsSeederTest` for new permission names.

### 3. State-aware `ERPModelPolicy`

Add methods: `reverse()`, `amend()`, `forcePost()`.

Refactor shared flow:

```php
private function allowsDomainAction(User $user, Model $record, string $operation, bool $state_ok): bool
{
    if (! $state_ok) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    return $this->allows($user, $record, $operation);
}
```

**State rules (Phase 2A):**

| Ability | Model | State OK when |
|---------|-------|----------------|
| `post` | `Invoice` | `journal_entry_id === null` |
| `unpost` | `Invoice` | `journal_entry_id !== null` |
| `forcePost` | `Invoice` | same as `post` **and** `direction === purchase` |
| `post` | `DeliveryNote` | `posted_at === null` |
| `unpost` | `DeliveryNote` | `posted_at !== null` |
| `close` | `FiscalPeriod` | `is_closed === false` |
| `reopen` | `FiscalPeriod` | `is_closed === true` |
| `close` | `FiscalYear` | `is_closed === false` |
| `reverse` | `JournalEntry` | `posted_at !== null` and no row with `reverses_journal_entry_id = id` |
| `amend` | `SalesOrder` | `status` in `Confirmed`, `PartiallyEvased` (mirror service) |

Apply state guards to existing `post()` / `unpost()` / `close()` / `reopen()` — **breaking change
for superadmin** who could previously post already-posted records via policy alone (UI already hid
actions).

**Invoice force checkbox:** show only when `auth()->user()?->can('forcePost', $record)`. If user has
`post` but not `forcePost`, modal posts without checkbox (strict match enforced). Update both
`InvoicePostingActions` and any duplicate in `EditInvoice.php`.

Register `FiscalYear` in `ERPServiceProvider::policyModels()` for `close` authorization.

### 4. Form hardening

- `FiscalPeriodForm`: replace editable `Toggle::make('is_closed')` with read-only display
  (`Placeholder` / disabled toggle / `TextEntry` in infolist) — closure state changes only via
  actions calling `FiscalPeriodCloser`.
- `FiscalYearForm`: same for `is_closed` if present.

### 5. Error handling

- Service exceptions (`FiscalPeriodAlreadyClosedException`, `ValidationException`,
  `CannotReverseUnpostedJournalException`, etc.) → Filament `Notification::danger()` with message;
  do not swallow.
- No silent success on no-op reopen (service no-ops when already open — action should be hidden via
  policy/state).

---

## Testing Strategy

TDD per ability cluster:

1. **Policy tests** — extend `ErpModelPolicyTest.php` (or add focused files per model) for:
   - state denies even superadmin when record in wrong state
   - permission granted + correct state → allow (non-superadmin)
   - fail-closed when permission row missing

2. **Seeder test** — `ErpDomainPermissionsSeederTest` asserts new permission names exist.

3. **Action wiring tests** — lightweight feature tests invoking static action helpers or page logic
   (without browser):
   - e.g. `DeliveryNotePostingActions::postNote()` sets `posted_at` and triggers inventory side
     effect (assert `inventory_posted_at` set) using existing service test patterns.

4. **Regression** — run existing integration tests:
   - `tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php`
   - journal reverse coverage in accounting golden-master / posting tests
   - `SalesOrderAmendmentService` tests

5. **Filament smoke** — extend `ERPFilamentResourcesTest` / route smoke if new routes unchanged
   (pages already registered).

Run `vendor/bin/pint --dirty` before completion.

---

## File Map (expected touch set)

```
Modules/ERP/database/seeders/ERPDatabaseSeeder.php
Modules/ERP/app/Policies/ERPModelPolicy.php
Modules/ERP/app/Providers/ERPServiceProvider.php

Modules/ERP/app/Filament/Resources/FiscalPeriods/Actions/FiscalPeriodActions.php
Modules/ERP/app/Filament/Resources/FiscalPeriods/Pages/EditFiscalPeriod.php
Modules/ERP/app/Filament/Resources/FiscalPeriods/Schemas/FiscalPeriodForm.php

Modules/ERP/app/Filament/Resources/FiscalYears/Actions/FiscalYearActions.php
Modules/ERP/app/Filament/Resources/FiscalYears/Pages/EditFiscalYear.php
Modules/ERP/app/Filament/Resources/FiscalYears/Schemas/FiscalYearForm.php  (if is_closed toggle exists)

Modules/ERP/app/Filament/Resources/JournalEntries/Actions/JournalEntryActions.php
Modules/ERP/app/Filament/Resources/JournalEntries/Pages/ViewJournalEntry.php

Modules/ERP/app/Filament/Resources/DeliveryNotes/Actions/DeliveryNotePostingActions.php
Modules/ERP/app/Filament/Resources/DeliveryNotes/Pages/EditDeliveryNote.php

Modules/ERP/app/Filament/Resources/SalesOrders/Actions/SalesOrderAmendmentActions.php
Modules/ERP/app/Filament/Resources/SalesOrders/Pages/EditSalesOrder.php

Modules/ERP/app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php
Modules/ERP/app/Filament/Resources/Invoices/Pages/EditInvoice.php  (force_post gate)

Modules/ERP/tests/Feature/ErpModelPolicyTest.php
Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php
Modules/ERP/tests/Feature/Filament/…  (new action wiring tests as needed)
```

---

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Duplicate post actions on `EditInvoice` vs `InvoicePostingActions` | Consolidate force_post gate in both or extract shared form builder |
| DDT post via `posted_at` without lines | Keep existing model validation; action visible only when draft |
| Fiscal period CRUD bypass | Disable `is_closed` on form |
| Superadmin state bypass expectation | Document: superadmin bypasses permissions only, not state |
| Journal reverse from Edit page on draft | Prefer `ViewJournalEntry`; hide reverse when unposted |

---

## Implementation Order (recommended)

1. Permissions seeder + seeder test  
2. Policy state guards + policy tests (all abilities)  
3. `force_post` gate on invoice actions  
4. Fiscal period/year actions + form hardening  
5. Delivery note post/unpost actions  
6. Journal reverse action  
7. Sales order amend action  
8. Filament smoke + full ERP test subset  

---

## Out of Scope (Phase 2B+)

See Context section. Track in Spec 2 umbrella index when Phase 2B spec is written.
