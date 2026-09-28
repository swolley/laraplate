# Core approvals subsystem — design

**Date:** 2026-09-27
**Module:** `Modules/Core` (`app/Approvals`), with the per-model rules of CMS, ERP and Core models that use approvals
**Related:** `2026-05-15-modification-moderation-design.md` (moderation events and AI voting, unchanged), `2026-09-12-mcp-server-design.md` (writes wait for this)
**Status:** Design agreed. No implementation started, beyond the attribution and the table names (Core commits `d6874436`, `c759afe4`). Amended 2026-09-28 with five decisions a review of the plan forced: the author's approve credit applies through the vote service, the operation reaches a model rule through its own hook, `Hide` does not filter when nobody is authenticated, comment deletions are excluded explicitly, and the diff hook is `getDirtyForApproval()` rather than a second `enrichModificationDiff()`.

---

## 1. Purpose

Approvals only cover saves today. `stephenlake/laravel-approval` listens to `saving` and nothing else, so:

- a soft delete runs `runSoftDelete()`, a direct query update: no `saving`, no approval;
- a force delete fires only `deleting`, which nobody intercepts: no approval;
- a restore goes through `save()`: it needs approval, while the delete it undoes did not.

A user who may not edit a record without approval can delete it. This design makes deletes, force deletes and restores follow the same approval rules as creates and updates, and moves the approval mechanism into Core.

## 2. Decisions

- **Code in Core, not a separate package.** `stephenlake/laravel-approval` (source repository `cloudcake/laravel-approval`) (1.1.4, last release 2020-12-31, ~780 lines) is small, unmaintained and already largely replaced by Core (own migrations, models, capture, vote service, write rule). Its remaining parts move into `Modules/Core/app/Approvals` and the dependency is removed. The code stays extractable later: Core is its own repository.
- **Attribution.** The package is MIT. Derived files carry `Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md.` in their header; `LICENSES/laravel-approval.md` holds the license (Copyright (c) 2019 Stephen Lake) and the upstream version (1.1.4, commit `e7527e1`); the Core README lists it under "Third-party code", like `laravel-locked` and `laravel-optimistic-locking`.
- **Operations covered by default: all.** Create, update, delete, force delete, restore. A model can exclude operations (e.g. a comment author deleting their own comment).
- **Who writes without approval:** console, superadmin (always, whatever the approvals required), a writer holding `approve` on the table when one approval is enough, plus each model's own rule (e.g. `Setting` presentation fields, `Content` drafts). A model rule that exempts some states covers deletes and restores too: a draft or expired `Content` is written, deleted and restored directly, a live or scheduled one goes through approval for every operation.
- **A pending deletion blocks the record by default.** Per model, the strategy is `Block` or `Hide`. No third "allow writes" state: automatic system writes (tokens, counters, sync status) are covered by a per-model list of attributes writable during a pending deletion, as `attributesWritableWhileLocked()` does for locks.
- **Hide means filtered at query time.** Nothing is written on the record (no `deleted_at`, no flag). A record with an active `delete`/`force_delete` request is excluded by a scope for users who hold neither `approve` nor `disapprove` on that table, so whoever has to decide always still sees it. **With nobody authenticated the scope does not apply at all**: console, queues, jobs, search indexing and exports read every record, as capture is skipped in console. Hide answers "who may not see a record they cannot decide on", which is a question about a person, and a pending deletion must not quietly change what background work reads. Hide only removes visibility: ACLs keep applying on top, as for any record. An author who cannot decide no longer sees the record, but still sees their request in Modifications and can withdraw it. Rejection or withdrawal makes it visible again without any restore.
- **Withdrawal.** Only the author can withdraw their own request, of any operation, at any time until the decision is complete, even with votes already cast. Voters can only approve or reject. Withdrawal deletes the modification and its votes. Once decided, a request cannot be withdrawn.
- **Decided modifications are kept** (deactivated) for every model. The per-model `deleteWhenApproved`/`deleteWhenDisapproved` options go.
- **Approving a deletion rejects the pending updates** of the same record, with reason "record deleted".
- **Interception through model events** (`saving`, `deleting`, `forceDeleting`, `restoring`), so every write through the model is covered: panel, API, jobs, AI tools, MCP. Mass query updates (`Model::query()->update()`) stay out, as today; this is a documented limit.
- **The caller always knows the outcome.** A captured operation exposes its modification on the model; a read-only check says in advance whether an operation would need approval.
- **No migrations for existing data.** The project has no other installation: `operation` goes into the Core create migration, `is_update` is removed, and the developer database is rebuilt with `migrate:fresh --seed`. Pending modifications are not preserved; they are development data, and the create migrations are the only description of the schema. Converting a live database by hand instead is what left `core_settings` without `is_internal` until 2026-09-28.

## 3. Data

`core_modifications`:

| Column | Change |
|---|---|
| `operation` | **New**, string backed by the `Operation` enum: `create`, `update`, `delete`, `force_delete`, `restore`. Indexed with `modifiable_type`, `modifiable_id`, `active`. |
| `is_update` | **Removed**, replaced by `operation`. |
| `modifications` | The diff for `create`/`update`; empty for `delete`, `force_delete`, `restore`. |
| `md5` | Identifies an identical pending request. For operations without a diff it hashes operation and record, so a repeated deletion request updates the pending one. |

`core_approvals`, `core_disapprovals`: unchanged. Withdrawal deletes the votes with the modification.

Rules: one active `delete`/`force_delete`/`restore` request per record; `restore` only on a soft-deleted record.

## 4. Components

All under `Modules\Core\Approvals` unless noted.

- **`Operation`** enum; **`PendingDeletionStrategy`** enum (`Block`, `Hide`).
- **`PendingDeletionLock`** exception: a write on a record blocked by a pending deletion.
- **`Modules\Core\Models\Concerns\HasApprovals`** becomes the only trait (it no longer uses the package's `RequiresApproval`). **Whatever asks "does this model have approvals?" must ask about this trait.** Two places name the package trait instead and answer through `class_uses_trait()`, which is recursive and therefore true today only because `HasApprovals` uses `RequiresApproval`: `PermissionsRefreshCommand` (whether the `approve` permission belongs in a model's vocabulary) and `CrudService` (whether a write can be captured). Dropping the package trait without moving them turns both silently false — a permission that stops being registered and an API that reports writes that did not happen, with no exception either way:
  - registers the listeners on `saving`, `deleting`, `forceDeleting`, `restoring`;
  - `pendingModification(): ?Modification`, the request captured by the last operation;
  - `wouldRequireApproval(Operation $operation): bool`, the read-only check;
  - scope `withoutPendingDeletion()`;
  - overridable per model: `approvalOperations(): list<Operation>` (default all), `pendingDeletionStrategy(): PendingDeletionStrategy` (default `Block`), `attributesWritableWhilePendingDeletion(): list<string>` (default none), `getDirtyForApproval(): array` (default `getDirty()`; a model whose write goes through a staging area folds it in here, as `Comment` does for pending translations and ratings), the model's write rules — `requiresApprovalWhen(array $modifications)` for a change set and `requiresApprovalForOperation(Operation $operation)` for an operation, both defaulting to the shared `approvalGate()` — approvers and disapprovers required.
  - **An operation never reaches a rule as a fake change set.** The two rules are separate because they answer different questions: a delete carries no fields, so a rule written about field names must not be consulted for one. Only a model with per-state rules overrides the operation hook — `Content` for drafts and expired contents; `Setting` and `Taxonomy` need no override, since their exemptions are about fields.
- **Models** `Modification`, `Approval`, `Disapproval` stay in `Modules\Core\Models` and no longer extend the package classes; the counters and scopes they inherit (`approversRemaining`, `disapproversRemaining`, `activeOnly`, …) move into them.
- **`ModificationVoteService`** (`Modules\Core\Services`) becomes the single entry point for decisions: vote, apply the operation when the decision is complete, withdraw. It takes over `applyModificationChanges()` from the package. Vote and application run in one transaction on the modifiable model's connection. **Nothing applies an approved operation outside this service**, the author's own `approve` credit included: when that credit completes the quorum the trait calls `applyAuthorCredit()`, not `applyModificationChanges()` directly, so the transaction, the event and the rejection of pending updates hold on that path too. A credit is not a vote and does not go through `cast()`, which refuses an author voting on their own request.
- **Vote authorization** stays `User::isAuthorizedToCastApprovalVote()`; the package trait `ApprovesChanges` goes. The author never votes on their own request.
- **Events:** `ModificationRequiresModeration` and `ModificationApproved` stay as specified in the May spec; `ModificationRejected` and `ModificationWithdrawn` are added. All of them live in `Modules\Core\Events`: one lifecycle, one namespace.
- **Model-specific rules** become implementations of the trait hooks. `CommentApprovalCapture` stays where it is — the only thing `Comment` needed from a diff hook is served by `getDirtyForApproval()`, so there is no `enrichModificationDiff()`. `Setting` and `Content` keep their `requiresApprovalWhen()`, `Content` adds `requiresApprovalForOperation()`, and the author approve credit (`applyAuthorApproveCredit()`) stays, triggered on capture and applying through the vote service.
- **Deleting a comment is outside approvals, declared.** `Comment::approvalOperations()` returns `[Create, Update]`: an author deletes their own comment, and only its text goes through moderation. Relying on `requiresApprovalWhen()` returning false when `body` is not dirty would reach the same result by accident, which is not the same thing.

## 5. Lifecycle

**Capture.** On each intercepted event the trait lets the operation through when it is not covered by the model, runs from console, is a forced write of an already decided request, or comes from a writer allowed to write directly. Otherwise it: enriches the diff through the model hook; creates or updates the modification (same `md5`, same request); stores it as the model's outcome; fires `ModificationRequiresModeration`; cancels the operation (the Eloquent method returns `false`).

A delete on a table whose soft delete is disabled (`soft_deletes.enabled.{table}` false) is captured as `force_delete`. Core `SoftDeletes` (with `is_deleted`) and Laravel's native one both run the real operation on approval.

**Pending deletion.** With `Block`, every `save()` of the record throws `PendingDeletionLock`, except for the attributes the model declares writable. With `Hide`, a global scope on the model excludes the record for users holding neither `approve` nor `disapprove` on its table; superadmins and deciders see it, and so does every context with no authenticated user.

**Vote.** Voting as today: a vote replaces the same user's opposite vote. When the decision is complete:
- approved: the operation runs as a forced write (diff applied, or `delete()`/`forceDelete()`/`restore()`), the modification is deactivated, pending updates of a deleted record are rejected, `ModificationApproved` fires;
- rejected: the modification is deactivated, `ModificationRejected` fires.

If applying the approved operation fails (e.g. a database constraint on a force delete), the transaction rolls back: the modification stays active and the error is reported.

**Withdrawal.** Author only, active request only. Deletes the modification and its votes, fires `ModificationWithdrawn`.

## 6. Surfaces

- **Panel.** A shared trait for edit pages and for delete/force delete/restore actions reads the outcome: it shows "Sent for approval" instead of "Saved"/"Deleted", labels actions from `wouldRequireApproval()` ("Request deletion"), turns `PendingDeletionLock` into a clear message, and offers "Withdraw" to the author in Modifications. It replaces the one-off notification in `EditSetting`.
- **CRUD API.** A captured write answers `202 Accepted` with the modification id and operation; a write on a blocked record answers `409 Conflict`; a withdrawal by someone else `403`, of a decided request `409`.
- **AI tools and MCP.** They receive the same outcome and report "sent for approval", never "done". This is the prerequisite the MCP spec waits for.

## 7. Testing

A test model in `Modules/Core/tests/Stubs` (no classes declared in test files). Covered:

- capture of every operation; direct writes for superadmin, `approve` holder, console; operations excluded by the model;
- `Block` and `Hide`, writable attributes during a pending deletion (the same attribute declared and not declared, never `updated_at`, which `Block` always lets through), and `Hide` not filtering when nobody is authenticated;
- the author's approve credit completing the quorum on a deletion: applied once, in a transaction, with the event fired;
- vote and decision for every operation, Core soft delete with `is_deleted`, disabled soft delete captured as `force_delete`;
- withdrawal: author only, with votes already cast, never after the decision; votes deleted with it;
- pending updates rejected when a deletion is approved; one deletion request per record; restore only on a trashed record;
- rollback when applying an approved operation fails;
- outcome and read-only check;
- existing behaviour of `Comment`, `Setting`, `Content` unchanged, with a comment delete running without a modification and a draft `Content` deleted directly while a live one is captured;
- panel notifications and actions, API `202`/`409`/`403`.

## 8. Delivery order

Each phase ends with its tests green and a commit.

1. Move the package code into Core with attribution; same behaviour as today, package still installed. The superadmin rule enters `HasApprovals` here.
2. `operation` column, `is_update` removed, developer database converted by hand.
3. Capture of delete, force delete and restore; `Block` and `Hide`.
4. Withdrawal, automatic rejection of pending updates, new events.
5. Surfaces: panel trait (absorbing `EditSetting`), API, AI tools.
6. Remove `stephenlake/laravel-approval` from `composer.json`, after explicit approval.
7. Module documentation and plan closure.

## 9. Out of scope

- Mass query updates and deletes (`Model::query()->update()/delete()`): no model events, no approval, as today.
- A third pending-deletion strategy allowing user edits.
- Keeping a trace of withdrawn requests.
