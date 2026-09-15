# ERP factories and demo dataset — Design

**Status:** Draft (for review)
**Date:** 2026-09-15
**Module:** `Modules/ERP`
**Related:**
- `2026-05-25-erp-m36-m4-m6-m7-design.md` — named the missing factories as an explicit constraint
- `2026-07-31-seeder-orchestration-design.md` — `Dev*` seeders are out of its scope, so this one owns them

## Goal

Give ERP the two things every other module already has: model factories, and a `Dev` seeder that
produces a usable demo dataset. Today ERP has neither, and the cost is paid in every test.

## The evidence

| Module | Factories | What its `Dev` seeder produces |
|--------|-----------|--------------------------------|
| SAO | 29 | a full demo project with deterministic tickets, built from factories |
| CMS | 8 | demo contents |
| MES | (via Core) | demo production data |
| **ERP** | **0** | **taxonomies only**: opportunity stages and activity types |

`Modules/ERP/database/factories/` contains a `.gitkeep` and nothing else. The 131 ERP tests build
their fixtures by hand, and the same rows are rebuilt over and over:

| Model | `::query()->create` calls across the suite |
|-------|-------------------------------------------|
| `Company` | 172 |
| `Party` | 95 |
| `Invoice` | 79 |
| `Item` | 47 |
| `Warehouse` | 41 |
| `SalesOrder` | 28 |
| `FiscalPeriod` / `FiscalYear` | 27 / 26 |
| `PurchaseOrder` + `PurchaseOrderLine` | 24 + 22 |
| `DeliveryNote` | 22 |
| `Account` | 20 |

Spread over 85 files, six to ten lines each. The cost is not the typing: it is that adding one
required column to `Company` means touching 172 call sites, and that a newcomer cannot write an
ERP test without first reverse-engineering a valid invoice from an existing one.

`DevERPDatabaseSeeder` exists (199 lines) but seeds only `OpportunityStage` and `Activity`
taxonomies. There is no company, no customer, no article, no document: the module cannot be
demonstrated, and no evaluation or UI work can anchor on known ERP data the way SAO work anchors
on `SAO-1..SAO-8`.

## Decisions

1. **Factories live where Laravel expects them**, `Modules\ERP\Database\Factories`, resolved by an
   explicit `newFactory()` on the model — the pattern `Core\Models\Setting` already uses. No
   container-level `guessFactoryNamesUsing` hook: an explicit method on the model is greppable and
   fails loudly when the class is missing.

2. **Twelve models, not sixty-six.** The table above is the scope, in that order. Coverage for its
   own sake would produce fifty factories nobody calls. A thirteenth is added the day a test needs
   it, not before.

3. **Company scoping is explicit.** Models using `BelongsToCompany` take their company from a
   factory relation. A factory must never silently create a second `Company` when the caller
   already has one: that is how scope-leak bugs enter a suite and stay invisible.

4. **Document numbering stays with the observers.** `Invoice`, `DeliveryNote` and the orders
   receive their number from the numbering service on save. Factories must not write the number
   field: doing so would mask the very behaviour the tests exist to verify. A `->numbered(string)`
   state is provided for the few tests that need a fixed value.

5. **The demo dataset is deterministic and idempotent.** Fixed, recognisable identifiers, in the
   spirit of SAO's `SAO-1..SAO-8`, so tests, evaluations and UI work can anchor on them. Running
   the seeder twice changes nothing and creates nothing twice.

6. **Existing tests are not rewritten here.** Factories are additive. New tests use them; the 85
   existing files are converted opportunistically, file by file, each verified green. A single
   commit rewriting every fixture would put a working suite at risk for no functional gain.

7. **No new entry point.** The root `DevDatabaseSeeder` already globs `Dev*.php` in every module's
   seeder directory, so an extended `DevERPDatabaseSeeder` is picked up with no registration.

## The demo dataset

One coherent commercial cycle, enough to demonstrate the module and to anchor tests:

- one `Company` with its fiscal year and periods, its chart of accounts head and VAT codes
  (`ItalianTaxCodesSeeder` already covers the tax side);
- two `Party` rows, one customer and one supplier, with a `PaymentTerm`;
- one `Warehouse` and three `Item` rows;
- a customer flow: `SalesOrder` with lines → `DeliveryNote` → `Invoice`;
- a supplier flow: `PurchaseOrder` with lines → `GoodsReceipt` → purchase `Invoice`;
- the existing taxonomy seeding stays exactly as it is.

Deliberately excluded from the demo set: payments, bank statements, returns and e-invoice
submissions. They are each a workflow with their own states, and a demo that half-populates them
teaches the reader something false.

## Testing

- Every factory gets one test asserting it persists a valid model, and that a company-scoped
  factory reuses a provided company rather than creating another.
- One test asserts the numbering service, not the factory, assigned the document number.
- The dev seeder gets an idempotence test: run twice, row counts unchanged.
- Two or three existing test files are converted as proof that the factories serve real tests, not
  only their own.

## Out of scope

- Converting the remaining test files (opportunistic, tracked separately).
- Factories for the other fifty-four models.
- Any change to the seeder orchestration graph: `Dev*` seeders are outside it by that spec's own
  decision.
