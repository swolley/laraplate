# Domain Action HTTP Routes on the `/app` Surface — Design

**Status:** Implemented 2026-08-01 (Core mechanism + ERP registrations and HTTP matrix)

**Date:** 2026-07-31

**Modules:** Core (mechanism) + ERP (first consumer)

**Backlog:** closes `3-01`, `3-04`, `3-06` from
[`2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).
Deliberately excludes `3-02` and `4-13`.

---

## Problem

ERP domain operations — posting an invoice, closing a fiscal period, reversing a journal
entry — exist only as Filament actions. They have no HTTP route on any surface, and
`Modules/ERP/routes/web.php` is empty. Any custom frontend can therefore perform generic
CRUD on ERP entities but cannot advance a single document through its lifecycle.

## The two surfaces

The distinction already exists in Core and is enforced purely by which file declares a route:

| Surface | Prefix | Middleware | Loads | Consumers |
|---------|--------|-----------|-------|-----------|
| Internal | `/app` | `web` (session) | `routes/web.php` | Laraplate UI, first-party frontends |
| External | `/api/v1` | `api` + `crud_api` | `routes/crud.php` + `routes/api.php` | Opt-in headless clients |

`routes/crud.php` is required by both, so the eight generic CRUD verbs are shared. Everything
declared in `routes/web.php` — `lock`, `unlock`, `approve`, `disapprove`, `activate`,
`inactivate`, `cache-clear`, plus the grid and graph groups — is internal-only and is never
reachable on `/api/v1`, regardless of `core.expose_crud_api`.

**Domain actions belong to the internal surface.** They are declared in `routes/web.php` and
inherit that property without any new gating mechanism.

## Route matching order

Laravel matches routes in registration order with no notion of specificity. Core boots first
(`module.json` priority 0) so other modules can build on its bindings, which would have made
its generic `{module}/{entity}` routes shadow every module-specific route of the same shape.

`Modules/Core/app/Providers/RouteServiceProvider.php` therefore defers Core's web and API route
registration to `$this->app->booted()`, so Core registers **after** every module. Verified with
the `route:check` command:

| Request | Resolves to |
|---------|-------------|
| `GET app/crud/detail/ai/conversations/5` | `ai.crud.conversations.detail` |
| `GET app/crud/select/erp/invoices` | `core.crud.list` |

This ordering is a precondition of this design: it is what makes module-declared overrides win
over the generic implementation.

---

## Decisions

### D1 — One verb space, not two

Domain actions use the same positional composition as every other CRUD operation:

```
POST /app/crud/{action}/{module}/{entity}
```

The alternative considered was a reserved segment (`/app/crud/action/{action}/{module}/{entity}`)
separating Core's cross-cutting "meta" verbs from module domain verbs. It was rejected: it breaks
the composition the CRUD system was designed around, and the ambiguity it defends against is
avoidable by D3.

The catch-all **must be registered last within Core's `crud` group**, after every literal verb.
Literal routes declared earlier in the same file win; the grid and graph groups are unaffected
because they carry an extra path segment.

Record id travels in the body, as it already does for `update`, `delete`, `lock` and the rest.
`ModifyRequest` resolves it from either route or input, so no new convention is introduced.
Action-specific payload travels alongside it (`force_post`, `last_number`, …).

**Method is `POST`**, deliberately differing from the `PATCH` used by the generic verbs. A domain
action invokes an operation rather than patching a representation, and several are not idempotent
— `create_credit_note` and `reverse` produce a new record on each call. `PATCH` would imply a
safety these actions do not have.

### D2 — A per-entity registry is the single source of truth

Each module registers its domain actions as `{module}/{entity}/{action}` → handler, mapping to
the services that already implement them. The registry — not the route table — decides what
exists. One generic route serves all modules and all 29 known ERP actions, and any action added
later needs no routing change.

### D3 — Modules declare the generic verbs they override

Seven Core verbs (`approve`, `disapprove`, `lock`, `unlock`, `activate`, `inactivate`,
`cache-clear`) act on cross-cutting structures attached to a record: a pending `Modification`,
the lock columns, the soft-delete state, the cache. Domain verbs act on the record itself.

Where a module needs a generic verb to mean something different for one of its entities, the
model declares it:

```php
interface OverridesGenericCrudActions
{
    /** @return list<string> */
    public static function overriddenCrudActions(): array;
}
```

`ReturnOrder` and `SupplierReturn` return `['approve']`. Core's `approve` votes on a pending
`Modification`; theirs locks the row, requires status `Draft`, validates the counterparty is a
customer, and transitions to `Approved`. Different object, different effect, different
precondition.

One declaration serves three purposes: the dispatcher resolves the override, the boot-time guard
below detects the contradiction, and the intent is recorded in the class itself. A comment would
be a fourth place stating the same thing and the only one nothing verifies.

**Boot-time guard.** When a module registers its domain actions, registration fails if a model
both declares an overridden verb and uses the trait that gives that verb its generic meaning —
today, `approve`/`disapprove` against `HasApprovals`. Failing at registration rather than in the
model's `boot()` means the contradiction surfaces at application start, in every environment,
rather than whenever that particular record is first instantiated.

### D4 — Domain actions authorize through the policy, not the permission string

Generic CRUD authorizes via `AuthorizationService::ensurePermission()`, which checks a permission
name and nothing else. That is insufficient here: the guard for a domain action is intrinsic to
the action. Posting an already-posted invoice is not a permission problem.

Domain actions therefore authorize through `Gate` — `ERPModelPolicy` already implements exactly
this in `allowsDomainAction()`, combining a state predicate with the permission check. Without it
an invalid transition would reach the service and surface as an exception instead of a clean
refusal.

Permission names remain `{connection}.{table}.{operation}` and are built by one shared helper
(`3-04`). They are currently constructed by two independent `sprintf` calls —
`ERPModelPolicy::hasPermission()` and `AuthorizationService::buildPermissionName()` — which is
the duplication `3-04` removes.

### D5 — Responses and errors reuse the CRUD contract, with a binary escape hatch

Handlers return through `CrudResult` and `ResponseBuilder`, so a domain action response is shaped
like every other CRUD response. `CrudController::handleServiceCall()` already maps the exception
families; domain services additionally raise `ValidationException` and `DomainException`, which
need explicit mappings rather than falling through to 500.

**File actions are exposed on `/app` and need a second response kind.** The rule: if a handler
returns a `Symfony\Component\HttpFoundation\Response`, the controller returns it unchanged;
anything else is wrapped in a `CrudResult`. That covers both directions without a parallel
mechanism:

| Action | Shape |
|--------|-------|
| `export_sepa`, `export_cbi_bonifici`, `export_ics` | handler returns a streamed download, passed through |
| `import_file` | handler consumes a `multipart/form-data` upload and returns a normal `CrudResult` summary |

Two consequences to respect in implementation. Authorization and state guards run **before** any
byte is streamed, so a refusal is still a normal JSON error response. And once streaming has
begun the JSON error envelope is no longer available — handlers must fail before producing
output, not midway.

Exposure of these four on `/api/v1` is a separate decision, taken with `3-02`.

### D6 — `lock`/`unlock` stay uniform

Treated exactly as for every other class, with no ERP special case. `Quotation::unlock` is
behaviourally identical to Core's — both call `HasLocks::unlock()` — so ERP declares no override
and the generic route serves it.

Known divergence, deliberately deferred: the Filament action authorizes through
`ERPModelPolicy::unlock()` and requires `default.erp_quotations.unlock`, while the HTTP route
requires `default.erp_quotations.lock`, because Core governs an operation pair with a single
permission (`approve` likewise governs `disapprove`). Same operation, two entry points, two
permissions. Resolve when `3-04` lands.

### D7 — `/api/v1` exposure is out of scope

`3-02` (Sanctum tokens, version negotiation, throttling) and `4-13` (mobile) remain deferred.
Domain actions declared in `routes/web.php` are internal-only by construction, so nothing in this
design leaks to the external surface.

---

## Interaction with the approvals workflow

`HasApprovals` holds edits as pending `Modification` rows until approved. No ERP model uses it
today; consumers are `CMS\Comment`, `CMS\Content`, `Core\Field`, `Core\Taxonomy`, `Core\Setting`,
`Core\Preset`.

Adopting it in ERP is desirable and planned as a **separate spec**. It is not an additive change:
edits to those entities stop applying immediately, which affects existing tests, Filament
affordances, seeded permissions, per-model quorum, and `deleteWhenDisapproved`. Recommended
starting point is `PartyBankAccount` alone — supplier IBAN redirection is the highest-value
fraud vector in the system — validating the workflow end to end before widening.

**Candidates**

| Verdict | Models | Reason |
|---------|--------|--------|
| Strong | `PartyBankAccount`, `BankAccount`, `Party`, `Account`, `TaxCode`, `ExchangeRate`, `Item`, `PriceList`, `PriceListItem`, `PartyPriceRule`, `PaymentTerm`, `Company`, `DocumentSequence`, `AnalyticDimension`, `AnalyticDimensionValue` | rare, high-impact edits that propagate into documents and accounting |
| Pointless | `JournalEntry`, `JournalEntryLine`, `VatRegisterEntry`, `StockMovement`, `StockCostLayer`, `StockLevel`, `ReportSnapshot` | `RestrictsCrudWrites` forbids edits outright; approving impossible changes is dead weight |
| Pointless | all `*Line`, allocations, `PaymentScheduleLine`, `BankStatementLine`, `EInvoiceSubmission` | children follow their parent |
| Excluded | `ReturnOrder`, `SupplierReturn` | they override `approve`; D3's guard rejects the combination |
| Conditional | `Invoice`, `SalesOrder`, `PurchaseOrder`, `DeliveryNote`, `GoodsReceipt`, `Quotation`, `PaymentRun`, `PaymentRequest`, `VatSettlement`, `Movement` | own state machine, locks and posting guards; meaningful only for `Draft` edits, redundant once posted |

---

## ERP actions covered

29 distinct actions, none requiring a route declaration except the two overrides.

`post`, `unpost`, `close`, `reopen`, `reverse`, `reverse_processed`, `amend`, `reset`, `reserve`,
`supersede`, `switch_context`, `unlock`, `submitEInvoice`, `refreshEInvoice`, `approve`\*,
`cancel`, `complete`, `create_credit_note`, `create_debit_note`, `create_revision`,
`allocate_expense`, `settle_up`, `compute_settlement`, `send`, `import_file`, `export_sepa`,
`export_cbi_bonifici`, `export_ics`.

\* overridden on `ReturnOrder` and `SupplierReturn` per D3.

**`force_post` is not an action.** It was listed as one while auditing the policy, which has a
`forcePost` method. Reading `InvoicePostingActions` shows the UI has no separate force action:
`post` carries a `force_three_way_match` checkbox, shown only for a purchase invoice when the user
holds `forcePost`. The HTTP surface mirrors that — a payload flag on `post` with its own
permission check — because a second route would be a way to post that bypasses the normal path.
The seeded `…erp_invoices.force_post` permission stays and keeps governing the flag.

The four file operations (`import_file`, `export_sepa`, `export_cbi_bonifici`, `export_ics`) are
exposed on `/app` — the UIs need them — and use the binary response kind described in D5. They do
not affect the route count.

---

## Testing

- Registry resolution and the D3 boot-time guard, including the failing combination.
- Route matching via `route:check`: generic verb, module override, unregistered action → 404.
- Per-action HTTP matrix: authorized → 200, missing permission → 403, invalid state → 403,
  unknown action → 404.
- `force_post` payload on a purchase invoice.
- Accounting golden master stays green.

## Open items

- `unlock` permission divergence between Filament and HTTP (D6), to resolve with `3-04`.
- Whether the four file actions are also exposed on `/api/v1`, to decide with `3-02`.
