# SAO Phase 1b — Ticket Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enrich the SAO ticket with labels, watchers, attachments, due dates, typed ticket-to-ticket relations, and advanced search with saved filters — every feature offline, ACL-respecting, and independent of the error flow.

**Architecture:** Each feature is a standalone slice with its own model(s)/migration/test. Reads go through `TicketQueryService::visible()`; attachments reuse `spatie/laravel-medialibrary`; search is a service over the visible query; saved filters persist a criteria set. Nothing here touches connections, drivers, or signals.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules` 12, `spatie/laravel-medialibrary` 11, Filament 5, Pest 4, PHPStan/Larastan 3, Pint.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-08-15-sao-phase-1b-ticket-enrichment-design.md` (decisions H1–H8).
- `declare(strict_types=1);`; `final` unless a sibling proves otherwise; explicit types; constructor promotion; `#[Override]`.
- Models extend `Modules\Core\Overrides\Model`; tables `sao_`-prefixed via `SAOTables` (add cases + update `SaoEnumsTest` each time); enum keys TitleCase, lowercase backing values.
- Tests in `Modules/SAO/tests/`; fakes under `tests/Support/`; never declare classes in a test file. Feature tests that touch the DB use `RefreshDatabase`; those that create tickets/connections seed permissions and authenticate as in the 1a/3b tests.
- Per step: minimal relevant tests (`php artisan test --compact Modules/SAO/...`); before each commit Pint (SAO config) on touched files. Regenerate autoload with `COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload` if needed.

Tasks are ordered simplest-first; each is independently shippable.

---

## Task 1: Due dates

- Create: migration `..._add_due_at_to_sao_tickets_table.php` (nullable `due_at` timestamp, indexed).
- Edit: `Ticket` — add `due_at` to fillable, cast `datetime`, add `scopeOverdue` and `scopeDueWithin(Builder, CarbonInterval|int $days)`; update create/update rules to allow `due_at` (nullable date).
- Create: `tests/Feature/Models/TicketDueDateTest.php`.

- [x] **Step 1: failing test** — `overdue()` selects a past-due, unresolved ticket and excludes a future one; `dueWithin(7)` selects one due in 3 days.
- [x] **Step 2: run to fail → Step 3: implement → Step 4: run to pass.**
- [x] **Step 5: Pint + commit** (`feat(sao): ticket due dates with overdue/due-within scopes`).

---

## Task 2: Labels

- Create: migration `..._create_sao_labels_table.php` (project_id FK, name, colour; unique (project_id, name)) and `..._create_sao_ticket_label_table.php` pivot.
- Create: `Label` model + `LabelFactory`; `SAOTables` cases `Labels`, `TicketLabel`.
- Edit: `Ticket` — `labels(): BelongsToMany`.
- Create: `tests/Feature/Models/LabelTest.php`.

- [x] **Step 1: failing test** — a label is project-scoped-unique; attaching/detaching labels to a ticket works; a ticket lists only its own labels.
- [x] **Step 2–4: red → implement → green.**
- [x] **Step 5: Pint + commit** (`feat(sao): project-scoped ticket labels`).

---

## Task 3: Watchers

- Create: migration `..._create_sao_ticket_watchers_table.php` (ticket_id, user_id; unique pair).
- Edit: `Ticket` — `watchers(): BelongsToMany` (to the app user model); helpers `watch(User)`/`unwatch(User)` idempotent.
- Create: `tests/Feature/Models/TicketWatcherTest.php`.

- [x] **Step 1: failing test** — a user watches a ticket at most once (idempotent watch); unwatch removes; watchers list is correct. Notifications are explicitly not asserted (out of scope).
- [x] **Step 2–4: red → implement → green.**
- [x] **Step 5: Pint + commit** (`feat(sao): ticket watchers`).

---

## Task 4: Attachments (spatie medialibrary)

- **Prerequisite:** `docs/superpowers/plans/2026-08-15-core-media-foundation.md` must be done — media (`vend_media` + `Core\Models\Media` + config) is Core-owned, so SAO uses it without depending on CMS.
- Edit: `Ticket` — implement `HasMedia`, use `InteractsWithMedia`, register an `attachments` media collection (resolving the global Core `media_model`).
- Create: `tests/Feature/Models/TicketAttachmentTest.php` (uses `Storage::fake`).

- [x] **Step 1: failing test** — adding a fake file to the `attachments` collection stores it and the ticket lists exactly one attachment with the right name.
- [x] **Step 2–4: red → implement → green.**
- [x] **Step 5: Pint + commit** (`feat(sao): ticket attachments via media library`).

---

## Task 5: Ticket relations

- Create: `TicketRelationType` enum (`blocks`/`duplicates`/`relates` + `isDirectional()`).
- Create: migration `..._create_sao_ticket_relations_table.php` (source_ticket_id, target_ticket_id, type; unique triple; self-relation guarded in the model). `SAOTables` case `TicketRelations`.
- Create: `TicketRelation` model + factory; `Ticket` — `relations(): HasMany` (outgoing) and a `relatedVia(TicketRelationType)` helper that also resolves inverse (`blocked by`) by query.
- Create: `tests/Feature/Models/TicketRelationTest.php`.

- [x] **Step 1: failing test** — create blocks/duplicates/relates; the inverse of `blocks` resolves as *blocked by*; symmetric types resolve both ways; a self-relation and a duplicate triple are rejected.
- [x] **Step 2–4: red → implement → green.**
- [x] **Step 5: Pint + commit** (`feat(sao): typed ticket-to-ticket relations`).

---

## Task 6: Advanced search and saved filters

- Create: `TicketSearchCriteria` value object (text, status_id, type_id, priority, assignee_id, label_id, due_before, due_after, is_overdue; `fromArray`/`toArray`).
- Create: `TicketSearchService::search(TicketSearchCriteria): Builder` built strictly on `TicketQueryService::visible()`.
- Create: migration `..._create_sao_saved_filters_table.php` (user_id, nullable project_id, name, criteria json) + `SavedFilter` model + factory; `SAOTables` case `SavedFilters`.
- Create: `tests/Feature/Services/TicketSearchServiceTest.php` and `tests/Feature/Models/SavedFilterTest.php`.

- [x] **Step 1: failing tests** — each criterion filters correctly; results stay ACL-scoped (a hidden ticket never appears); a saved filter round-trips its criteria and reapplies to produce the same result set.
- [x] **Step 2–4: red → implement → green; then run the full SAO suite green.**
- [x] **Step 5: Pint + commit** (`feat(sao): ticket search and saved filters`).

---

## Task 7 (optional UI): Filament surfaces

- Extend the Ticket Filament resource with labels, watchers, attachments (media plugin), due date, and a relations manager; add a saved-filter picker on the ticket list.
- Create: `tests/Feature/Filament/TicketEnrichmentUiTest.php`.
- [ ] Red → implement → green; Pint + commit (`feat(sao): filament surfaces for ticket enrichment`). **(deferred: optional UI; models + services ship and are fully tested)**

---

## Slice exit criteria

- A ticket can carry labels, watchers, attachments, a due date, and typed relations; each respects the ACL and has tests.
- `TicketSearchService` filters the visible set by any combination of criteria and never surfaces a hidden ticket; a `SavedFilter` round-trips and reapplies.
- Full SAO suite green; Pint and PHPStan clean.

## Known gaps carried forward

- Watcher **notification delivery** (needs a notification pathway) — recorded here as out of scope.
- The kanban board (1c).
- External sync of labels/relations/attachments (phase 3+).
