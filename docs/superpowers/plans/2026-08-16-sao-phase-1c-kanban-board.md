# SAO Phase 1c — Kanban Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A per-project board view — tickets in columns by status, moved through the workflow — with no new persisted concept and no new dependency.

**Architecture:** A `TicketBoardService` read model over `TicketQueryService::visible()` returns ordered `BoardColumn` value objects; a thin Filament custom page renders them and moves cards through `WorkflowService`. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-1c-kanban-board-design.md` (decisions K1–K6).

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest 4, Pint, PHPStan.

## Global Constraints

- `declare(strict_types=1);`; `final`; explicit types; constructor promotion; `#[Override]`.
- No new tables, no new dependencies. Reads only through `visible()`; moves only through `WorkflowService`.
- Tests in `Modules/SAO/tests/`; value objects under `app/Data`; never declare classes in a test file.
- Per step: minimal relevant tests; before each commit Pint on touched files.

---

## Task 1: Board read model

- Create: `app/Data/BoardColumn.php` — `readonly` value object `{ TicketStatus $status, Collection<Ticket> $tickets }`.
- Create: `app/Services/TicketBoardService.php` — `for(Project $project): Collection<int, BoardColumn>`; columns are every `TicketStatus` ordered by `order_column`; tickets from `TicketQueryService::visible()` filtered to the project, eager-loading `type`/`assignee`/`labels`.
- Create: `tests/Feature/Services/TicketBoardServiceTest.php`.

- [x] **Step 1: failing test** — columns follow `order_column`; an empty status yields an empty column; tickets group under the right status; an ACL-hidden ticket never appears; only the chosen project's tickets appear.
- [x] **Step 2–4: red → implement → green.**
- [x] **Step 5: Pint + commit** (`feat(sao): ticket board read model`).

---

## Task 2: Filament board page

- Create: `app/Filament/Pages/TicketBoard.php` — a custom page in the SAO group with a required project selector; resolves columns through `TicketBoardService`; a per-card Move action offering `WorkflowService::availableTransitions($ticket)` and executing `WorkflowService::transition()`.
- Create: `resources/views/filament/pages/ticket-board.blade.php` — columns layout (Tailwind, dark-mode aware), one card per ticket, the Move action per card.
- Create: `tests/Feature/Filament/TicketBoardPageTest.php` — structural: registered in the SAO group; resolves the board via `TicketBoardService`; the move path names `WorkflowService`.

- [x] **Step 1: failing test.**
- [x] **Step 2–4: red → implement → green; then run the full SAO suite green.**
- [x] **Step 5: Pint + commit** (`feat(sao): filament ticket board page`).

---

## Exit criteria

- A project's tickets render in status-ordered columns, ACL-scoped; a card moves only through workflow-allowed transitions via `WorkflowService`.
- Full SAO suite green; Pint clean.

## Known gaps carried forward

- HTML5 drag-and-drop (needs an approved Filament kanban package).
- Swimlanes, WIP limits, saved board layouts.
