# SAO phase 1c — kanban board design

**Date:** 2026-08-16
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 1)
**Builds on:** `docs/superpowers/specs/2026-08-01-sao-phase-1a-ticketing-core-design.md` (1a) and `docs/superpowers/specs/2026-08-15-sao-phase-1b-ticket-enrichment-design.md` (1b)
**Status:** Design proposed.

---

## 1. Purpose

Slice 1c gives a project a **board view**: its tickets arranged in columns by status, and moved between columns through the same workflow rules the rest of SAO enforces. It adds no new persisted concept — the board is a **read model plus a move action** over what 1a/1b already store.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| K1 | The board is a **read model**, not a table. A `TicketBoardService` returns, for one project, the ordered columns (statuses) each carrying its visible tickets. No board/column/card tables. | The status set and its order already live in `TicketStatus` (sortable). A board table would duplicate that. |
| K2 | Columns are **every `TicketStatus` ordered by `order_column`**; a column may be empty. Tickets are grouped by `ticket_status_id`. | Statuses are global and ordered; the board reflects that order so it reads the same everywhere. |
| K3 | Reads go through **`TicketQueryService::visible()`**, scoped to the chosen project. A ticket the ACL hides never appears on the board. | Same single read path as every other SAO surface; the board must not become an ACL bypass. |
| K4 | Moving a card goes through **`WorkflowService::transition()`** with the ticket's **allowed transitions only**. The board offers no move a workflow scheme forbids; enforcement stays in the service, not the UI. | The board is one more caller of the one transition path; a rule only the board honoured would not be a rule. |
| K5 | **No drag-and-drop library.** A move is an explicit action on a card offering `availableTransitions(ticket)`. True HTML5 drag-drop would require an approved Filament kanban package; that is deferred, not assumed. | AGENTS.md forbids new dependencies without approval; the action-based move delivers the board's value (see the work grouped, move it through the workflow) with zero new packages and is fully testable. |
| K6 | The board is **per project** (a required project selector). | Statuses/workflow are meaningful within a project; a global board would mix unrelated processes. |

## 3. Components

- `TicketBoardService::for(Project $project): Collection<int, BoardColumn>` — ordered columns; each `BoardColumn` is a value object `{ TicketStatus $status, Collection<Ticket> $tickets }`. Tickets come from `visible()` filtered to the project, eager-loading `type`/`assignee`/`labels` to avoid N+1.
- `BoardColumn` — a readonly value object under `app/Data`.
- A Filament custom page `TicketBoard` (SAO navigation group) with a project selector, rendering the columns and, per card, a **Move** action whose options are `WorkflowService::availableTransitions($ticket)`, executed through `WorkflowService::transition()`.

## 4. Testing

- `TicketBoardServiceTest`: columns are every status in `order_column` order; a status with no ticket yields an empty column; tickets are grouped under the right status; a ticket hidden by the ACL never appears; only the chosen project's tickets appear.
- `TicketBoardPageTest`: structural — the page is registered in the SAO group, resolves the board through the service, and its move action names `WorkflowService`.

## 5. Non-goals

- HTML5 drag-and-drop (needs an approved package).
- Swimlanes, WIP limits, per-user board layouts.
- Any external sync (phase 3+).
