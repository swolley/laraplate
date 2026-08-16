# SAO phase 6 — fix propagation & evidence-based closure design

**Status:** Draft
**Module:** SAO
**Builds on:** phase 5b (`ChangeRef`, `Release`/`ReleaseTag`/`TicketRelease`, `Environment` + deploy census) and phase 2 (`Signal`/`SignalOccurrence`).

## 1. Why

Phases 5/5b gave SAO the *facts*: which code artefact touches a ticket, which
shipped release carries it, what version runs where. Phase 6 turns those facts
into two answers, both **deterministic** (D8): "is this fix already merged /
released / deployed, and where is it still missing?" (fix propagation) and "may
this ticket be closed, and on what evidence?" (evidence-based closure). No model
judgement decides a state change — composable predicates over verifiable facts
do, and every automatic change is audited and reversible.

## 2. Requirements traceability

| Req | Statement | Design ref |
|-----|-----------|------------|
| R1 | A signal can be linked to a ticket; a merged PR is a persisted fact. | §3, D8 |
| R2 | Fix propagation reports PR-merged / fix-released (shipped only) / deployed-there, so "already fixed on dev, deploy missing" is answerable. | §4, §9.1 (design) |
| R3 | The six closure conditions are independent testable predicates over a `ClosureContext`, combined with AND. A candidate never satisfies `fix_released`. | §5, §9 (design) |
| R4 | A `ClosurePolicy` is project-scoped: conditions + action (`close`/`propose`/`notify_only`); default on `shadow` external bindings is `propose`. | §6, D8 |
| R5 | Every automatic closure records which conditions held with what evidence (`ClosureAudit`) and is reversible: recurrence in the reporting environment reopens and marks **premature closure** with closed-because / returned-after. | §7 |
| R6 | Time-to-truth: how long from first sighting until "fix merged, deploy missing" was known, and until a premature-closure reopen. | §8 |

## 3. Persisted facts (additive)

- `Signal.ticket_id` (nullable FK): a signal promoted to / correlated with a ticket. Adds `Signal::ticket()` and `Ticket::signals()`.
- `ChangeRef.merged_at` (nullable timestamp): a merged pull request is a fact worth persisting; fix propagation and `pull_request_merged` read it. Only meaningful for `type = pull_request`.

Both are additive migrations; phase 5b tables are untouched.

## 4. Fix propagation (read model)

`FixStatusResolver::forTicket(Ticket, ?reportingEnvironment): FixStatus`.

`FixStatus` (readonly DTO): `pull_request_merged` (bool), `fix_released` (bool —
a **shipped** release attributed to the ticket exists), `released_version`
(?string), `deployed_environments` (list of environment names running that
version), `missing_environments` (list running something else or stale),
`deployed_there` (?bool — null when no reporting environment given). This is the
"already fixed on dev, deploy missing" answer, computed from `ChangeRef`,
`TicketRelease`/`Release`, and `Environment` — no driver call.

## 5. Closure conditions

`ClosureCondition` interface: `key(): string`, `evaluate(ClosureContext): ClosureConditionResult`.
`ClosureConditionResult` (readonly): `held` (bool), `evidence` (array shape).
`ClosureContext` (readonly): the assembled facts — `pull_request_merged`,
`reporting_environment` (?string), `last_recurrence_at` (?Carbon, scoped to the
reporting environment), `fix_released`, `fix_deployed_there`, `resolved_at`
(?Carbon), `is_internal`, `now` (injected for determinism).

Six conditions, each configured at construction with the duration it needs:

- `pull_request_merged` — a merged PR change ref exists.
- `no_recurrence_for(duration)` — no occurrence in the reporting environment within the window ending at `now`.
- `fix_released` — a **shipped** release attributed to the ticket exists (never a candidate).
- `fix_deployed_there` — that shipped release is the version running on the reporting environment.
- `resolved_for(duration)` — ticket resolved with no counter-evidence for the window.
- `internal_tickets_only` — the ticket has no `TicketLink`.

## 6. ClosurePolicy

`ClosurePolicy` (`sao_closure_policies`): project_id, name, `conditions` (json —
list of `{key, config}`), `action` (`ClosureAction`: `Close`/`Propose`/`NotifyOnly`),
`is_active`. Conditions are combined with **AND**. `ClosureConditionRegistry`
builds the condition objects from the json.

`ClosureEvaluator::evaluate(ClosurePolicy, ClosureContext): ClosureDecision`.
`ClosureDecision` (readonly): `action`, `satisfied` (all held), per-condition
outcomes (`ClosureConditionResult[]` keyed by condition key), and the evidence
union. The default action for a policy attached to a `shadow` external binding
is `propose` (prudence: the foreign tracker owns the ticket).

## 7. ClosureAudit + premature closure

`ClosureAudit` (`sao_closure_audits`): ticket_id, closure_policy_id (nullable),
action, `conditions_held` (json — the predicate set + evidence, "closed
because"), reporting_environment, `closed_at`, and reopen fields
`reopened_at`, `returned_after_seconds`, `returned_occurrence_id`, `is_premature`
(bool). `ClosureAuditService`:

- `record(Ticket, ClosureDecision, reportingEnvironment): ClosureAudit` — persists the "closed because".
- `reopenForRecurrence(ClosureAudit, SignalOccurrence): ClosureAudit` — stamps returned-after (duration since `closed_at`, environment, occurrence id), sets `is_premature = true`. Reversible by construction.

## 8. Time-to-truth

`TimeToTruthService`: from a `Signal`'s `first_seen_at`, compute
`time_to_fix_merged` (to the earliest merged change ref on the linked ticket),
`time_to_deploy_gap_known` (to when a shipped-but-not-deployed-everywhere state
first held), and `time_to_premature_reopen` (to a premature `ClosureAudit`
reopen). Returned as a `TimeToTruth` readonly DTO of nullable interval seconds.

## 9. Testing

- Fix propagation: merged PR + shipped release deployed on staging only → `deployed_there` false for production, `missing_environments` = [production].
- Each closure condition: held / not-held with evidence, over a synthetic `ClosureContext`.
- `fix_released` is false when only a candidate tag carries the fix.
- Policy AND semantics; `shadow` default `propose`.
- `ClosureAudit` records the held set; a recurrence reopens and marks premature with returned-after.
- Time-to-truth intervals computed from stamped timestamps.

## 10. Non-goals

- Ownership suggestion (persist/UI) — data exists (`ChangeRef`); the suggestion surface is a later slice.
- AI-phrased suggestions (phase 8).
- Live PR-merge polling / webhook transport — `merged_at` is set by ingest/fix-propagation writes exercised with injected facts.
- Filament surfaces for policies/audits — a later UI slice.
- Automatic assignee changes (forbidden, D14).
