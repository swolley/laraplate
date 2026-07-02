# ERP M6.3 - E-Invoice Stub And Submission Structure Plan

> **Navigation:** M6.3 stub workflow is **implemented**. Full FatturaPA → Spec 2 Phase 2C in
> [`specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Wire the existing e-invoice contract into the ERP module with a deterministic stub
provider, submission persistence, and minimal Filament actions. Full FatturaPA XML remains
optional backlog.

**Architecture:** `EInvoiceProvider` already exists and is transport-agnostic. M6.3 binds a stub
implementation, stores `EInvoiceSubmission` rows, and gives operators submit/refresh actions for
posted sale invoices.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, no new Composer dependencies.

---

## Current Truth

- Contract: `Modules\ERP\Contracts\EInvoiceProvider`.
- Data namespace: `Modules\ERP\Data\EInvoice`.
- Existing DTOs:
  - `EInvoicePayload(array $document, ?string $mimeType = null)`
  - `EInvoiceSubmissionResult(string $externalId, bool $success, ?string $message = null, array $raw = [])`
  - `EInvoiceRemoteStatus` enum with `Unknown`, `Pending`, `Processing`, `Delivered`, `Accepted`, `Rejected`
- Persistence model: `Modules\ERP\Models\EInvoiceSubmission`.
- Persistence status enum: `Modules\ERP\Casts\EInvoiceSubmissionStatus` with `Draft`, `Queued`,
  `Submitted`, `Accepted`, `Rejected`, `Error`.
- `EInvoiceProvider::remoteStatus()` accepts `string $externalId`, not an `EInvoiceSubmission`.
- Current invoices have a nullable `party_id`, but complete FatturaPA mapping still needs
  anagraphic, transmitter, recipient, PEC/SDI, fiscal-regime, address, and provider-specific fields
  that are outside this stub milestone.

## Task 1: Config And Binding

**Files:**
- Modify: `Modules/ERP/config/config.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Create: `Modules/ERP/app/Services/EInvoice/StubEInvoiceProvider.php`

**Config:**

```php
'einvoice' => [
    'driver' => env('ERP_EINVOICE_DRIVER', 'stub'),
],
```

**Binding:**
- Bind `EInvoiceProvider::class` in `ERPServiceProvider`.
- Default to `StubEInvoiceProvider`.
- Unsupported drivers should fail safe with the stub or throw a clear `InvalidArgumentException`
  during service resolution; choose one behavior and test it.

## Task 2: Stub Provider

**Files:**
- Create: `Modules/ERP/app/Services/EInvoice/StubEInvoiceProvider.php`
- Create: `Modules/ERP/tests/Feature/Services/EInvoiceProviderTest.php`

**Provider behavior:**
- `code(): string` returns `stub`.
- `prepare(Invoice $invoice): EInvoicePayload`
  - returns a neutral document array with invoice id, company id, direction, invoice type,
    reference, currency, posted date, and line totals;
  - does not require FatturaPA XML;
  - does not require `Invoice::party`.
- `submit(EInvoicePayload $payload): EInvoiceSubmissionResult`
  - returns success true with deterministic external id format such as `STUB-{invoice_id}` when
    invoice id is available in payload;
  - includes raw provider data in `raw`.
- `remoteStatus(string $externalId): EInvoiceRemoteStatus`
  - returns `Accepted` for known `STUB-*` ids;
  - returns `Unknown` for unsupported ids.

**Tests:**
- Resolve `EInvoiceProvider::class` and assert it is the stub by default.
- Assert `prepare()` returns an `EInvoicePayload`.
- Assert `submit()` returns success and a stable external id.
- Assert `remoteStatus('STUB-1')` maps to `Accepted`.

Use manual model setup; do not assume ERP factories exist.

## Task 3: Submission Application Service

**Files:**
- Create: `Modules/ERP/app/Services/EInvoice/EInvoiceSubmissionService.php`
- Create or extend: `Modules/ERP/tests/Feature/Services/EInvoiceProviderTest.php`

**Service behavior:**

```php
public function submit(Invoice $invoice): EInvoiceSubmission;
public function refresh(EInvoiceSubmission $submission): EInvoiceSubmission;
```

**Rules:**
- Submit only posted sale invoices.
- Prevent a duplicate active submission when an invoice already has `Submitted` or `Accepted`.
- Create `EInvoiceSubmission` with:
  - `company_id`
  - `invoice_id`
  - `provider_code`
  - `external_id`
  - `status`
  - `submitted_at`
  - `response_payload`
- Map `EInvoiceSubmissionResult` to persistence status:
  - success true -> `Submitted`
  - success false -> `Error`
- Refresh by calling `EInvoiceProvider::remoteStatus($submission->external_id)`.
- Map remote status:
  - `Accepted` or `Delivered` -> `Accepted`
  - `Rejected` -> `Rejected`
  - `Pending` or `Processing` -> `Submitted`
  - `Unknown` -> keep current status and append raw context if useful.

## Task 4: Filament Actions

**Files:**
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Pages/EditInvoice.php`
- Optionally extract actions under `Modules/ERP/app/Filament/Resources/Invoices/Actions/`.

**Actions:**
- `send_einvoice`
  - visible only when invoice is posted, sale direction, and no active submission exists;
  - calls `EInvoiceSubmissionService::submit()`;
  - shows provider code and external id.
- `refresh_einvoice_status`
  - visible when the invoice has a submitted row with external id;
  - calls `EInvoiceSubmissionService::refresh()` on latest submission.

**UI scope:**
- Add a compact read-only section or table of submissions only if the existing invoice page pattern
  supports it cleanly.
- Do not add FatturaPA XML preview in this milestone.

## Task 5: Optional Schema Additions

Avoid adding `sdi_code`, `pec_email`, `fiscal_regime`, or `natura_code` in M6.3 stub mode unless
another approved task needs them immediately. These columns belong to the optional FatturaPA work.

## Test Plan

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/EInvoiceProviderTest.php
vendor/bin/pint --dirty
```

Test scenarios:
- Default provider binding resolves to stub.
- Stub prepare/submit/remote status works.
- Submission service rejects unposted or purchase invoices.
- Submission service persists `EInvoiceSubmission`.
- Refresh maps remote status to persistence status.

## Verification Status

- Implemented as v1 stub/submission workflow: `EInvoiceProvider` resolves to
  `StubEInvoiceProvider`, `EInvoiceSubmissionService` persists submissions and refreshes status,
  and invoice edit pages expose submit/refresh actions for posted sale invoices.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/EInvoiceProviderTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php`
  - included in the combined ERP focused command documented in the final verification note
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
- Additional smoke verification on 2026-05-30:
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentRouteSmokeTest.php`
  - Confirms ERP Filament routes are registered and the invoice edit page renders server-side for
    a posted sale invoice. Full browser click-through remains optional if a browser runner is added.

## Optional / Backlog: Full FatturaPA

Full FatturaPA remains a separate optional milestone:

- Add missing anagraphic/company/customer fields after completing the fiscal data model and provider
  package decision.
- Build FatturaPA XML and validate against the official XSD fixture.
- Add `natura_code`, fiscal regime, PEC/SDI, transmitter data, and required address fields.
- Add Aruba or other intermediary driver with HTTP fakes.
- Cover advanced SDI states, rejection details, and legal retention requirements.
