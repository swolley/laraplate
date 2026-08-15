# SAO phase 1b — ticket enrichment design

**Date:** 2026-08-15
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 1)
**Builds on:** `docs/superpowers/specs/2026-08-01-sao-phase-1a-ticketing-core-design.md` (slice 1a)
**Status:** Design proposed. No implementation started.

---

## 1. Purpose

Slice 1a delivered a usable standalone tracker. Slice 1b enriches the ticket without touching the error flow or any external system: **labels, watchers, attachments, due dates, ticket-to-ticket relations, and advanced search with saved filters** (the exact list 1a's non-goals deferred here). Every feature is independent, offline, and ACL-respecting; each can ship on its own, so the plan is a sequence of small self-contained tasks rather than one monolith.

1b is deliberately independent of phase 2 (error signals) and phase 3 (external sync): none of it requires a connection, a driver, or a signal.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| H1 | **Labels are project-scoped** (`sao_labels`: project, name, colour; unique per project) and attached to tickets through a `sao_ticket_label` pivot. | A label vocabulary belongs to a project; the same name in two projects is two labels. |
| H2 | **Watchers are an explicit ticket↔user list** (`sao_ticket_watchers`). Delivering notifications is **out of scope** — 1b records who watches; notifying them arrives with a notification pathway later. | Keeps 1b free of the notification/error machinery it must not depend on. |
| H3 | **Attachments reuse `spatie/laravel-medialibrary`** via the **Core media foundation** (`docs/superpowers/specs/2026-08-15-core-media-foundation-design.md`): `Ticket implements HasMedia` with an `attachments` collection. Media ownership is promoted from CMS to Core first, so SAO uses it without depending on CMS. No bespoke attachment table. | Reuse over reinvention; but the media table/model/config live in CMS today, so Core must own them before a Core-only module can use them. |
| H4 | **Due date is a nullable `due_at` column** on tickets, with `overdue()`/`dueWithin()` query scopes. | The simplest correct model; no separate entity needed. |
| H5 | **Ticket relations are typed** (`blocks`, `duplicates`, `relates`) in `sao_ticket_relations` (source, target, type). `blocks` is **directional** (inverse: *blocked by*); `duplicates`/`relates` are **symmetric**. One row is stored; the inverse is derived by query. A self-relation and a duplicate (source,target,type) are rejected. | Captures the three relations 1a deferred without a row per direction. |
| H6 | **Advanced search is a `TicketSearchService`** over `TicketQueryService::visible()` (ACL-scoped) accepting structured criteria (free text, status, type, priority, assignee, label, due range); **saved filters** (`sao_saved_filters`) persist a criteria set per user (optionally project-scoped). | Reuses the one sanctioned read path; ACL is never bypassed. Saved filters are data, not code. |
| H7 | Every read goes through `TicketQueryService::visible()`; authorization stays Laraplate's (`PermissionName` + ACL). Enrichment never introduces a visibility mechanism of SAO's own. | Consistency with 1a E11. |
| H8 | Each feature is a standalone slice with its own tests and can be delivered, reordered, or dropped independently. | 1a's slicing rationale (E1) applied within 1b. |

---

## 3. Non-goals

- The kanban board (1c).
- Notification delivery to watchers (needs a notification pathway; 1b only records watchers).
- Syncing labels/relations/attachments to external trackers (phase 3+).
- Saved filters shared across users or turned into dashboards.

---

## 4. Domain model (this slice)

| Entity | Role |
|--------|------|
| `Label` | Project-scoped tag: `project_id`, `name`, `colour`. Unique on (project, name). |
| `sao_ticket_label` | Ticket ↔ Label pivot. |
| `sao_ticket_watchers` | Ticket ↔ User pivot (who follows a ticket). |
| `Ticket.due_at` | New nullable column; `overdue()` / `dueWithin($interval)` scopes. |
| `Ticket` media | `HasMedia` with an `attachments` collection (spatie medialibrary). |
| `TicketRelation` | `source_ticket_id`, `target_ticket_id`, `type` (`TicketRelationType`). Unique on the triple; self-relation rejected. |
| `SavedFilter` | `user_id`, optional `project_id`, `name`, `criteria` (json). |

New tables are `sao_`-prefixed via `SAOTables`. Models extend `Modules\Core\Overrides\Model`. `TicketRelationType` is a backed enum (`blocks`/`duplicates`/`relates`) with a `isDirectional()` helper.

---

## 5. Search and saved filters

`TicketSearchService::search(TicketSearchCriteria $criteria): Builder|Collection` builds on `TicketQueryService::visible()` and applies only the criteria present: `text` (title/description/key like), `status_id`, `type_id`, `priority`, `assignee_id`, `label_id`, `due_before`/`due_after`, `is_overdue`. It returns a query the caller paginates. `SavedFilter` stores a `TicketSearchCriteria` as json for reuse; applying a saved filter is `search(SavedFilter::criteria())`.

---

## 6. Authorization

Unchanged: `PermissionName` + Core ACL, all reads through `visible()`. Managing labels, watchers, relations, attachments and saved filters is gated by ticket-level permissions; a user who cannot see a ticket cannot enrich it.

---

## 7. Testing

- Labels: attach/detach; project-scoped uniqueness; a ticket lists only its labels.
- Watchers: add/remove; a user watches a ticket at most once.
- Attachments: add a media file to the `attachments` collection and read it back (fake storage).
- Due dates: `overdue()`/`dueWithin()` scopes select the right tickets.
- Relations: create blocks/duplicates/relates; inverse resolves by query; self-relation and duplicate rejected.
- Search: each criterion filters correctly and results stay ACL-scoped (an ACL-hidden ticket never appears); a saved filter round-trips and reapplies.
- Zero-enrichment tickets keep working (1a behaviour unaffected).

---

## 8. Risks

| Risk | Mitigation |
|------|-----------|
| Attachments depend on CMS (where media lives today). | The Core media foundation change promotes media ownership to Core before Task 4, so SAO depends only on Core. |
| Search bypasses ACL with a raw query. | `TicketSearchService` is built strictly on `TicketQueryService::visible()`; a test asserts hidden tickets never surface. |
| Relations grow a row-per-direction. | One stored row with a typed direction; the inverse is a query, enforced by a uniqueness rule and a self-relation guard. |
| 1b creeps toward notifications. | H2 records watchers only; delivery is explicitly out of scope. |
