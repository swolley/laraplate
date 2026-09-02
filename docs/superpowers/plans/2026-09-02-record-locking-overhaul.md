# Record Locking Overhaul Plan

Rebuild `HasLocks` into a usable three-mode locking system (editorial lease, deliberate hold,
freeze), fix the schema bug that has always made the user dimension unusable, and correct the
CRUD error mapping that answered `304 Not Modified` for failures that need a body.

Related spec: `docs/superpowers/specs/2026-07-10-nested-forms-and-draft-recovery-design.md` §8
(stack repo). That spec already fixes the frontend side of the contract; three of its details
diverge from the implementation and are reconciled in Task 10.

## Global Constraints

- The product is not in production. Behaviour changes need no staged rollout or compatibility shim.
- Tests run on SQLite `:memory:` while production is MySQL. SQLite ignores declared column types,
  so **no test can catch a column-type error**. Type correctness must be asserted against the
  schema builder, not by round-tripping a value.
- The lock is coordination metadata, not record data. Writing it must never touch `lock_version`
  or `updated_at`.
- Correctness must never depend on the cron. An expired lock is free the moment it expires,
  whether or not the sweep has run.
- The form lifecycle produces **only** leases, never holds or freezes, whatever the caller's
  permissions. Holds and freezes are always explicit, deliberate actions.
- 10 models use `HasLocks`: `CMS\Content`, `Core\{CronJob, Taxonomy, User, Role, Entity}`,
  `ERP\{SalesOrderLine, Quotation, Project, SalesOrder}`.

## The model

Two columns carry two orthogonal axes. `locked_at` records when the current lock was taken and is
never refreshed.

| `locked_user_id` | `locked_until` | Meaning | Icon |
|---|---|---|---|
| set | set | only that user may edit, until that moment, then free | padlock |
| set | NULL | only that user may edit, no expiry (**requires `lock`**) | padlock |
| NULL | set | nobody may edit until that moment | snowflake |
| NULL | NULL | nobody may edit, indefinitely | snowflake |

The icon states **who** (an owner, or nobody). The deadline is shown as text beside it, never as a
separate icon. "Freeze" therefore means *ownerless*, not *permanent*; permanence is the absence of
`locked_until` on either axis.

| Capability | Take a lease | Release own lock | Hold / freeze | Remove someone else's lock |
|---|---|---|---|---|
| `update` on the record | yes | yes | no | no |
| `lock` | yes | yes | yes | no |
| `unlock` | yes | yes | no | yes, within its ACL |

`lock` and `unlock` are deliberately separate: the authority to freeze a record and the authority
to release someone else's block are different responsibilities and often different people.

## Task 1: finish the CRUD error mapping

Partly done, uncommitted in `Modules/Core`.

Done: `MultipleRecordsFoundException` → 400 (it is a sibling of `RecordsNotFoundException`, not a
subclass, so it reached the `Throwable` arm and was reported as a server fault);
`InvalidArgumentException` → 400; `DomainException|CannotUnlockException` → 409; `LogicException`
removed from the 304 arm so a broken invariant falls through to `Throwable` (report + 500).
Tests: `CrudErrorMappingTest`, `CrudSingleRecordResolutionTest`.

Remaining:

- `AlreadyLockedException` fires for both "already locked" and "isn't locked", i.e. *already in the
  target state*. With the semantics below it disappears from the lock path entirely: both cases
  become 200 with an empty `data`, matching what the bulk path already does. Delete the throw and
  the exception, or keep the class only if another caller needs it.
- `StaleModelLockingException` → **409**. It is the textbook conflict and today it is a reported
  500 on ordinary concurrent editing.
- `MissingLockVersionException` → **400**. The client did not send the version, or asked for a
  partial select that omitted it.
- `CannotUnlockException` covers two different conditions: "locked by another user" and "unlocking
  is disabled for this class by config". Split into two exceptions: the first → **423**, the second
  → **403** (not "occupied", but "not unlockable here").
- Reconsider `LockedModelException` → 423 as the single status for every "someone else holds it"
  case, so a client can treat them uniformly and read the detail from the body.

`facets()` does not go through `handleServiceCall`: it has its own `try` catching only
`AuthorizationException`, so an invalid facet escapes to the global handler as a 500 and its
message (which is excellent and actionable) is lost. Route it through the same mapping.

## Task 2: fix the schema

`locked_user_id` is declared **`timestamp`** on every lockable table. It holds a user id. This is
why the user dimension has never been used: it could not be. It also explains the four
`DateTimePicker::make('locked_user_id')` in CMS Filament forms, which were generated from the
schema.

- `Modules/Core/app/Locking/Stubs/add_locked_column_to_table.stub`: `$table->timestamp($lock_by_column)`
  → `$table->unsignedBigInteger($lock_by_column)->nullable()` (`users.id` is `bigint unsigned`).
  Add `locked_until` as a nullable timestamp. Update the remove stub symmetrically.
- Migration over the 10 lockable tables: correct the column type, add `locked_until`.
- No foreign key in the generic stub: lockable models may live on a connection other than `users`,
  and the repo has an explicit model-connection-affinity rule. Add the FK only where model and
  `users` share a connection.
- `is_locked` is a **stored generated column** computed from `locked_at`. Its expression must
  account for `locked_until`, or it will report expired locks as locked. Decide between fixing the
  expression and dropping the column in favour of the query scope from Task 4.
- Fix the four Filament forms.
- Test the column **types** through the schema builder, not by writing and reading a value: SQLite
  will happily accept an int in a timestamp column and the assertion would pass on a broken schema.

## Task 3: lock writes must not go through `save()`

`HasLocks::lock()` ends with `$this->save()`. On a model that also uses `HasOptimisticLocking`
(e.g. `Content`) this reaches `performUpdate`, which increments `lock_version`.

Consequence: taking a lock invalidates the version the client is holding for the form it just
opened. And since the client's "did anything change?" check is precisely a comparison of
`lock_version` (Task 8), the signal would be destroyed by the act of measuring it.

Write the lock columns with a direct query-builder update touching only those columns, bypassing
model events, `lock_version` and `updated_at`.

Because `locked_at` is never refreshed and the lease has a fixed deadline rather than a rolling
TTL, a periodic `lock` call that finds the caller's own lock still valid **writes nothing at all**.
The common case is a pure read.

## Task 4: expiry evaluated lazily, cron only for housekeeping

- `isLocked()` becomes `locked_at IS NOT NULL AND (locked_until IS NULL OR locked_until > now())`,
  with a matching query scope so lists and filters agree with the model.
- A scheduled command clears the columns of expired locks. It is housekeeping: it may run every few
  minutes, may be bounded with a `LIMIT`, and a missed run changes nothing.
- Index `locked_until` on each lockable table. MySQL has no partial indexes, so the index carries an
  entry per row; the cost is modest for a nullable timestamp and is measurable later. The column
  stays on the model's own table rather than moving to a polymorphic side table because lock state
  is read on **every** list row and detail (the trait exposes those columns to the payload on
  purpose), a morph join on `(type, id)` cannot carry a foreign key, and a single `UPDATE` keeps
  acquisition atomic. Lazy expiry keeps the door open: if the index ever becomes a problem, only
  the sweep can move to a work table, with no change to the read path or the semantics.
- Enumeration of lockable models is already solved by the `models()` helper and the walk in
  `ModelLockingRefreshCommand`.

## Task 5: lock semantics

`CrudService::doLockOperation` currently calls `$found_record->{$operation}()` with no argument, so
every lock taken through the API is ownerless. A redactor clicking "take charge" silently creates
the ownerless block meant for administrators.

- Pass the acting user on the lease path. An **anonymous user may never take a lock**: without an
  owner the write would produce a freeze. Refuse explicitly rather than degrade. (The default
  install gives the anonymous user read-only permissions, so this is defence in depth, but the
  failure mode is bad enough to guard.)
- **A lease extends, never downgrades.** If the existing lock belongs to the caller and expires
  later, or does not expire, it is left untouched and the answer is 200. The lease TTL is a floor,
  not an assignment. Without this rule, opening the form on a record you deliberately held until
  Thursday silently shortens the hold to fifteen minutes.
- Already in the target state (locking a record you already hold, unlocking one that is not locked)
  answers **200 with an empty `data`**, matching the bulk path. 304 is the answer to a *conditional*
  request whose validators matched, carries no body by specification (verified: 0 bytes, no
  `Content-Type`), and would land in the frontend's error branch since axios treats only 2xx as
  success. The "nothing changed" signal is already free in the envelope: `affected_records` is empty.
- Held by someone else, or frozen → **423** with the holder and the deadline in the body.
- Releasing your own lock never requires `unlock`.
- `LockedModelSubscriber` must exempt the lock owner. It currently checks `wasLocked() && isDirty()`
  with no owner test, so with `prevent_modifications_on_locked_objects` enabled it blocks the very
  person who took the lock to work on the record. `wasLockedBy()` already exists.

## Task 6: permissions

- The lease checks `update` on the record. Not an extra permission: it is the same right the edit
  form already required. Without the check any authenticated client can lock records it cannot edit
  and make them uneditable for everyone.
- `lock` governs holds and freezes. `unlock` governs removing a lock that is not the caller's own.
- Uncomment `Unlock` in `Modules\Core\Casts\ActionEnum`. Permissions are generated by iterating
  `ActionEnum::cases()` and `PermissionRefreshSeeder` is already a seed-graph node calling
  `permission:refresh`, so new installs are covered; existing ones need one `permission:refresh` run
  (`resolvePermission` uses `firstOrFail`, so a missing row throws instead of denying).
- An owned lock **without** an expiry may only be created by a holder of `lock`. Every lease
  therefore expires, so no lease can hang forever even where nobody holds `unlock`.
- Default ACL for `unlock`: `locked_until < @now`, i.e. by default the permission cleans up expired
  locks and nothing more. Breaking a live lock requires a deliberately wider ACL. A default of
  "only locks you imposed" would be degenerate: releasing your own lock is already the owner's right
  and does not pass through `unlock`, so such a default would grant nothing.

## Task 7: ACL on write paths, and the user placeholder

Row-level ACL filters are applied on reads only: `list`, `detail`, `history`, `tree`, `facetTotals`
and the relation constraints. `insert`, `update`, `delete`, `approve`, `lock` call `ensurePermission`
(entity level) and then query by key with no filter.

- Apply `applyAclFiltersToQuery` to every operation that resolves existing rows by key: `update`,
  `delete`, `forceDelete`, `restore`, `approve`, `disapprove`, `activate`, `inactivate`, `lock`,
  `unlock`.
- Add a user placeholder to `AuthorizationService::resolveFilterValue`. The convention is already
  established as `@`-prefixed (`@now`, `@today` exist), so `@user.<attribute>` rather than `{user.id}`.
  A generic attribute resolver, not just `@user.id`: the same implementation yields
  `@user.department_id`, `@user.team_id`, `@user.tenant_id`, which are the ACL cuts that will
  actually be needed. Restrict it to non-hidden scalar attributes so no filter can pull
  `@user.password` into a query.
- With `@now` already present, "only expired locks" is expressible today, which is what makes the
  Task 6 default possible.

## Task 8: API response

- The lock response carries `lock_version` so the client can compare it with the version it holds
  and warn *before* the user does an hour of work that is about to conflict.
- Three outcomes must be distinguishable: still yours (silent), was expired and reacquired (warn:
  the record may have changed), held by another (423, read-only plus banner).
- No heartbeat verb. The client re-calls `lock` periodically; the response says what happened. The
  spec's `PATCH .../lock` heartbeat is dropped.

## Task 9: Filament

Same API, no special case. A Filament panel is simply a caller whose user holds `lock` and `unlock`.

- Acquire the lease in the Edit page `mount()`. There is no reliable "left the page" hook, so the
  TTL from Task 4 is what releases it.
- Freeze and unfreeze actions, gated by `lock` and `unlock` respectively.
- Fix the four `DateTimePicker::make('locked_user_id')` (Task 2).

## Task 10: documentation

Per `AGENTS.md`, feature work updates the RAG docs of the module it touches.

- New pair in Core, following the existing `*_USER.md` / `*_DEVELOPER.md` convention:
  `Modules/Core/docs/rag/RECORD_LOCKING_USER.md` (the four states, the two icons, who may do what,
  what happens on expiry) and `RECORD_LOCKING_DEVELOPER.md` (columns and types, lazy expiry, the
  no-`save()` rule, permissions and ACL defaults, the sweep).
- `MODULE.md` and `GLOSSARY.md` in Core for *lease*, *hold*, *freeze*, and for "freeze means
  ownerless, not permanent".
- CMS and ERP RAG docs where lockable models are exposed.
- Any new env var in the Core README, in the existing style.
- Reconcile the three divergences with the edit-lock spec: route shape (spec
  `POST /app/{module}/{entity}/{id}/lock` vs actual `PATCH /app/crud/lock/{module}/{entity}` with the
  id in the body; the UI client follows the actual one), version field name (`_version` vs
  `lock_version`), and the status for "held by another" (spec says 409, this plan says 423). The
  frontend has not implemented the lock flow yet, so nothing is in production either way.

## Exit criteria

- `locked_user_id` is `bigint unsigned` on all 10 tables, asserted through the schema builder.
- Taking, extending and releasing a lock leave `lock_version` and `updated_at` untouched.
- An expired lock is free without the sweep having run.
- A lease never shortens a stronger lock held by the same user.
- No CRUD failure answers 304.
- Every write path that resolves rows by key applies ACL filters.
- Core, CMS and ERP suites green; `vendor/bin/pint --dirty` clean.

## Known gaps

- Whether `is_locked` survives as a generated column or is replaced by the query scope is open
  (Task 2).
- Escalation when a live hold blocks someone: covered by granting `unlock` with a suitable ACL, but
  no notification or "request release" flow is planned. Bounded by leases always expiring.
- ACL filters cannot express conditions relative to the current lock holder beyond what
  `@user.<attribute>` reaches (e.g. "locks held by my team members"). Those stay code rules.
- `HasLocks::bootHasLocks` reads `request('lock_version')` inside a model event, which is fragile
  on queues and in console and trusts client input. Not addressed here.
