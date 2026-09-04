# Record Locking Overhaul Plan

Rebuild `HasLocks` into a usable three-mode locking system (editorial lease, deliberate hold,
freeze), fix the schema bug that has always made the user dimension unusable, and correct the
CRUD error mapping that answered `304 Not Modified` for failures that need a body.

Related spec: `docs/superpowers/specs/2026-07-10-nested-forms-and-draft-recovery-design.md` §8
(stack repo). That spec already fixes the frontend side of the contract; three of its details
diverge from the implementation and are reconciled in Task 10.

## Global Constraints

- The product is not in production. Behaviour changes need no staged rollout or compatibility shim,
  and schema changes are made in the migrations that create the columns rather than in corrective
  migrations: the database is rebuilt with `migrate:fresh`.
- Tests run on SQLite `:memory:` while production is MySQL. SQLite ignores declared column types,
  so **no test can catch a column-type error**. Type correctness must be asserted against the
  schema builder, not by round-tripping a value.
- The lock is coordination metadata, not record data. Writing it must never touch `lock_version`
  or `updated_at`.
- Correctness must never depend on the cron. An expired lock is free the moment it expires,
  whether or not the sweep has run.
- The form lifecycle produces **only** leases, never holds or freezes, whatever the caller's
  permissions. Holds and freezes are always explicit, deliberate actions.
- **The lease owner is never blocked by their own lease.** While the lock is theirs, the user may
  save the record, release it, and change its `locked_until`. This holds at *every* layer that
  guards locked rows: the Eloquent subscriber, the CRUD service, and the database triggers. A guard
  that cannot know the acting user must therefore key on the lock being **ownerless**, never on the
  mere presence of a lock.
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

- Lock columns are generated in **two** places and both carry the bug:
  `Modules/Core/app/Locking/Stubs/add_locked_column_to_table.stub` and
  `Modules/Core/app/Helpers/MigrateUtils.php` (`locked()` / `dropLocked()`, the path the model
  migrations actually take, which also holds the Oracle variant). In both,
  `$table->timestamp($lock_by_column)` → `$table->unsignedBigInteger($lock_by_column)->nullable()`
  (`users.id` is `bigint unsigned`). Add `locked_until` as a nullable timestamp in both. Update the
  remove stub and `dropLocked()` symmetrically.
- **No corrective migration.** The product is still being built, so the fix goes into the original
  generators and the database is rebuilt with `migrate:fresh`. The same applies to every later
  schema change in this plan.
- No foreign key in the generic stub: lockable models may live on a connection other than `users`,
  and the repo has an explicit model-connection-affinity rule. Add the FK only where model and
  `users` share a connection.
- `is_locked` is a **stored generated column** (`locked_at IS NOT NULL`, indexed, Oracle emulates it
  with triggers). It cannot be fixed: MySQL, PostgreSQL and SQLite all require a generated column's
  expression to be deterministic, and expiry needs `NOW()`. Keeping it as a plain column maintained
  by the application would make correctness depend on the sweep, which the constraints above forbid.
  **Drop the stored column** (and the Oracle triggers that emulate it) and compute `is_locked` as an
  accessor, with the Task 4 scope for lists and filters. The attribute name stays in the payload, so
  the frontend keeps working: `ContentsListView.vue:221` and `ContentsFormView.vue:245` already read
  `!!row.locked_at || row.is_locked === true`, and they become correct on expiry for free. In-repo
  cost is small: the `IconColumn` in `Core/app/Filament/Utils/HasTable.php:243` and the four CMS
  `Toggle::make('is_locked')`, which Task 2 has to touch anyway. SQL sort and filter on the column
  are lost; neither is used today (the `TernaryFilter` at `HasTable.php:584` is commented out).
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

## Task 3b: the ERP lock guard triggers must exempt the lease owner

`Modules/ERP/database/migrations/2026_05_07_200000_create_lock_guard_triggers.php` installs, on
MySQL and PostgreSQL, `BEFORE UPDATE` and `BEFORE DELETE` guards over `quotations`, `sales_orders`,
`projects` and `sales_order_lines`. The record guard reads:

```sql
IF OLD.locked_at IS NOT NULL AND NEW.locked_at IS NOT NULL THEN SIGNAL ...
```

It never looks at who holds the lock, so on four of the ten lockable models the database refuses
every update while the row is locked. That forbids the two things a lease exists for: editing the
record you hold, and extending your own `locked_until`. No test catches it, because the migration is
a no-op on SQLite (`default => null`) and the suites run on SQLite.

The triggers are not wrong, they are under-specified: they encode "a confirmed document is
immutable", which in this model is a **freeze**, i.e. a lock with no owner. Make them owner-aware by
adding `OLD.locked_user_id IS NULL` to every guard condition, update and delete alike, in both the
MySQL and the PostgreSQL variants and in the `sales_order_line` commercial-field guard.

This costs the ERP guarantee nothing. The chain triggers that lock documents automatically
(`erp_lock_sales_order_chain`, `erp_lock_sales_order_line_chain` and their MySQL twins) set
`locked_at` and leave `locked_user_id` NULL, so every lock ERP creates on its own is ownerless and
stays fully protected at the database level. Only a lock a human deliberately took as their own
becomes editable, by its owner.

Also add `AND (OLD.locked_until IS NULL OR OLD.locked_until > CURRENT_TIMESTAMP)`: without it a
freeze with a deadline keeps blocking writes after it expires, since a trigger cannot benefit from
lazy expiry. This is the one place where the sweep would otherwise become load-bearing.

The guards are corrected in the original migration, not in a new one, per the constraint above. On
SQLite the migration stays a no-op, so the behaviour itself cannot be exercised by this suite: the
regression guard is `Modules/ERP/tests/Integration/Locking/LockGuardTriggersTest.php`, which asserts
over the DDL the migration emits that no guard is keyed on the bare presence of a lock and that the
chain triggers keep locking ownerlessly.

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

## Task 4b: the lock guard was never wired up

`LockedModelSubscriber` is referenced by nothing outside its own tests, whose assertions are over
its *source text* rather than its behaviour. It was never subscribed to the event dispatcher, so
`prevent_modifications_on_locked_objects` enforced nothing at all: a record locked by one user could
be saved, deleted or replicated by anybody. Subscribe it in `EventServiceProvider::boot()` and
replace the source-text assertions with behavioural ones.

The setting is **on** by default. A lock that enforces nothing is decoration: with it off, the only
real protection in the system is the handful of database triggers on the ERP documents, and nothing
but good manners stops a second user from saving over the first.

The guard has no acting user outside a request, so on a queue or in the console nobody holds the
lock and every leased record is closed to writing. That is the intended default rather than an
oversight: a lease exists to protect work in progress, and a background task overwriting it is the
damage the mechanism is for. System work that genuinely must go through says so out loud, with
`Locked::withoutGuard(fn () => …)`; nested calls restore the previous state rather than switching
the guard back on halfway.

The bypass has three call sites, all in ERP fulfilment: `SalesOrderEvasionService`,
`CustomerReturnReceiptService` and `ReturnOrderService`, each writing a delivered, invoiced or
returned quantity onto a line of a confirmed, and therefore frozen, document. The database had
already drawn that line, and more finely than an Eloquent guard can: the trigger on
`erp_sales_order_lines` blocks only the **commercial** fields of a locked line and leaves the
fulfilment quantities free. A freeze on an ERP document protects its commercial terms, not its
progress; the bypass declares in the application what the schema already said, rather than opening
something new.

The CMS importer is the counter-example and must not bypass. `ContentUpserter` saves `Content` from
`cms:import` with no authenticated user, so it meets the guard too, but `ImportRunner` already
catches per row, so a held content becomes "skipped, reported" and the run continues, which is the
protection working. Jobs and listeners elsewhere write only their own bookkeeping models
(`Modification`, `ImportSession`, `ActionRequest`), none of which are lockable.

## Task 5: lock semantics

`CrudService::doLockOperation` currently calls `$found_record->{$operation}()` with no argument, so
every lock taken through the API is ownerless. A redactor clicking "take charge" silently creates
the ownerless block meant for administrators.

- Pass the acting user on the lease path. An **anonymous user may never take a lock**: without an
  owner the write would produce a freeze. Refuse explicitly rather than degrade. (The default
  install gives the anonymous user read-only permissions, so this is defence in depth, but the
  failure mode is bad enough to guard.)
- **An implicit refresh on a valid lock of your own writes nothing.** The lease deadline is fixed
  when the lock is taken, not rolling, so the periodic re-lock is a pure read and the answer is 200
  with an empty `data`. This is what protects a record you deliberately held until Thursday from
  being shortened to fifteen minutes by reopening the form. (An earlier draft framed this as
  "extends, never downgrades", with the TTL as a floor. That is not implementable as written: the
  TTL is computed from *now*, so every poll would find a later deadline and rewrite the row, which
  is precisely the rolling behaviour the next paragraph rules out. A lapsed lock is not reached by
  this path at all, since the record is free and the call is a fresh acquisition.)
- The two rules above govern the **implicit** refresh the form lifecycle performs. The owner may
  still set `locked_until` **explicitly**, to any value including an earlier one, because it is
  their own lock: an explicit deadline in the request is an assignment, not a floor. Only removing
  the deadline altogether stays gated behind `lock`, since an owned lock with no expiry is a hold.
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
- **No default ACL ships for `unlock`.** Both candidates turn out to grant nothing. "Only locks you
  imposed" is degenerate because releasing your own lock is the owner's right and never passes
  through `unlock`. `locked_until < @now` is degenerate for the same reason once expiry is lazy: a
  lapsed lock is already free to everybody, so unlock has nothing to release and the write path
  returns before the ACL is even consulted. What remains is the honest reading: granting `unlock`
  means granting the right to unblock people, and a deployment that wants less writes its own ACL.
  The mechanism is real and tested (`Modules/Core/tests/Feature/Authorization/UnlockAclTest.php`);
  only the default is absent.

## Task 7: ACL on write paths, and the user placeholder

Row-level ACL filters are applied on reads only: `list`, `detail`, `history`, `tree`, `facetTotals`
and the relation constraints. `insert`, `update`, `delete`, `approve`, `lock` call `ensurePermission`
(entity level) and then query by key with no filter.

- Apply `applyAclFiltersToQuery` to every operation that resolves existing rows by key: `update`,
  `delete`, `forceDelete`, `restore`, `approve`, `disapprove`, `activate`, `inactivate`, `lock`,
  `unlock`.
- **ACL resolution has to merge direct grants with role grants.** `AclResolverService::resolveAcls`
  started from the user's roles and returned nothing when there were none, so a permission granted
  straight to a user came with no row filters at all: the grant that looks narrower was in fact the
  one that bypassed every restriction. Unscoped ACLs (`role_id IS NULL`) now speak for direct grants
  too; role-scoped ones still belong to their role, keeping the existing precedence in which a
  role's own ACL replaces the unscoped one rather than being OR-ed with it.
- **Role inheritance stopped one step short of the user.** `Role::hasPermission()` and
  `Role::getAllPermissions()` walk the ancestor chain, but the gate called Spatie's
  `User::hasPermissionTo()`, which asks whether the user holds one of the roles *attached to the
  permission*. A permission granted to a parent role was therefore invisible: the child role
  reported holding it and the gate denied it. `User::hasPermission()` adds the missing step and
  `AuthorizationService::checkPermission` uses it. Proven in
  `Modules/Core/tests/Feature/Authorization/InheritedPermissionTest.php`, which records the old
  answer alongside the new one.
- Add a user placeholder to `AuthorizationService::resolveFilterValue`. The convention is already
  established as `@`-prefixed (`@now`, `@today` exist), so `@user.<attribute>` rather than `{user.id}`.
  A generic attribute resolver, not just `@user.id`: the same implementation yields
  `@user.department_id`, `@user.team_id`, `@user.tenant_id`, which are the ACL cuts that will
  actually be needed. Restrict it to non-hidden scalar attributes so no filter can pull
  `@user.password` into a query.
- With `@now` already present, "only expired locks" is expressible today, which is what makes the
  Task 6 default possible.

## Task 8: API response

Closed with no change to the contract.

The three outcomes are already distinguishable: still yours and still valid is 200 with an empty
`data`; lapsed and reacquired is 200 with the record, and therefore with its current `lock_version`;
held by another is 423. The case that motivated carrying `lock_version` on every answer was "I hold
the lease and somebody changed the record underneath me", and Task 4b closes it: while the lease is
valid the guard refuses everybody else's write. The record can only change under a lock that has
lapsed, and that path already returns the record.

What remains uncovered is writing that bypasses Eloquent entirely — raw queries and the sweep — and
neither carrying the record nor adding a `meta` field would help there.

No heartbeat verb. The client re-calls `lock` periodically; the response says what happened, and the
common case writes nothing. The spec's `PATCH .../lock` heartbeat is dropped.

## Task 9: Filament

Same semantics as everywhere else. A panel is simply another caller.

- **The lease** is taken by `Modules/Core/app/Filament/Utils/HasRecordLease`, hooked on `afterFill`
  (the extension point `EditRecord::fillFormWithDataAndCallHooks()` calls once the record is
  resolved). Opening the edit page is a statement of intent, since viewing has its own page. There
  is no reliable "left the page" hook, so nothing releases the lease: the deadline does, and
  reopening takes a fresh one.
- **A held record is not decided for the user.** The page asks, with a modal that cannot be
  dismissed by clicking away: cancel and go back to the list, or open read-only. Read-only disables
  the whole schema rather than only the save button, because letting somebody type into a form whose
  save the guard will refuse is the one outcome worth ruling out. Applied to the eight edit pages of
  lockable models: `Content`, `Entity`, `CronJob`, `User`, `Role`, `Quotation`, `Project`,
  `SalesOrder`. `SalesOrderLine` has no page of its own and `Taxonomy` is abstract.
- **Freeze and unfreeze** are row and bulk actions in the existing `HasTable`, gated by `lock` and
  `unlock`. Tables never offer a lease: a lease belongs to the edit lifecycle. `configureActions`
  had to start receiving the `hasLocks` flag, which `configureTable` already computed and passed
  only to columns and filters.
- Copy for the modal in `Modules/Core/lang/{de,en,es,it,sl}/app.php` under `locking.held`.
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
- Reconcile the three divergences with the edit-lock spec, §8 of
  `docs/superpowers/specs/2026-07-10-nested-forms-and-draft-recovery-design.md` in the stack repo.
  Done: the section was written ahead of the backend and guessed the route shape
  (`POST /app/{module}/{entity}/{id}/lock` versus the actual `PATCH /app/crud/lock/{module}/{entity}`
  with the id in the body), the version field name (`_version` versus `lock_version`), and the status
  for "held by another" (409 versus 423, which matters because 409 is kept for the optimistic
  conflict on save). It now records what the backend does, including that there is no heartbeat and
  that the TTL is fixed rather than rolling.

## Exit criteria

- `locked_user_id` is `bigint unsigned` on all 10 tables, asserted through the schema builder.
- Taking, extending and releasing a lock leave `lock_version` and `updated_at` untouched.
- An expired lock is free without the sweep having run.
- A lease never shortens a stronger lock held by the same user.
- The lease owner can save the record, release it and change its `locked_until`, on every lockable
  model including the four ERP tables carrying database triggers.
- No CRUD failure answers 304.
- Every write path that resolves rows by key applies ACL filters.
- Core, CMS and ERP suites green; `vendor/bin/pint --dirty` clean.

## Known gaps

- Escalation when a live hold blocks someone: covered by granting `unlock` with a suitable ACL, but
  no notification or "request release" flow is planned. Bounded by leases always expiring.
- ACL filters cannot express conditions relative to the current lock holder beyond what
  `@user.<attribute>` reaches (e.g. "locks held by my team members"). Those stay code rules.
- `HasLocks::bootHasLocks` reads `request('lock_version')` inside a model event, which is fragile
  on queues and in console and trusts client input. Not addressed here.
