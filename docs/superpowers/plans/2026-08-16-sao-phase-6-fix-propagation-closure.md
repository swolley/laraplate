# SAO Phase 6 — Fix Propagation & Evidence-Based Closure Plan

> REQUIRED SUB-SKILL: superpowers:executing-plans. Checkbox (`- [ ]`) steps.

**Goal:** deterministic fix propagation and evidence-based closure with audit and time-to-truth. Spec: `docs/superpowers/specs/2026-08-16-sao-phase-6-fix-propagation-closure-design.md`.

## Global Constraints

- `declare(strict_types=1);`, `final`, explicit types, `#[Override]`; no new dependencies.
- `sao_`-prefixed tables via `SAOTables` (+ `SaoEnumsTest`); models extend `Core\Overrides\Model` (soft deletes in migrations). Additive migrations only; phase 5b tables untouched.
- Deterministic: conditions are pure over `ClosureContext`; inject `now`. Per task: minimal tests; Pint before commit. Commit per task; bump parent at the end.

## Task 1: persisted facts + fix propagation

- `Signal.ticket_id` (nullable FK) + `ChangeRef.merged_at` (nullable) migrations; `Signal::ticket()`, `Ticket::signals()`, `ChangeRef` merged accessor/scope. `FixStatus` DTO + `FixStatusResolver::forTicket()`. Test.
- [x] Red → green; Pint + commit (`feat(sao): fix propagation read model`).

## Task 2: closure conditions

- `ClosureConditionResult`, `ClosureContext` VOs; `ClosureCondition` interface; the six condition classes; `ClosureConditionRegistry`. Test each.
- [x] Red → green; Pint + commit (`feat(sao): evidence-based closure conditions`).

## Task 3: closure policy + context resolver

- `ClosureAction` enum; `ClosurePolicy` (`sao_closure_policies`) + migration + factory; `SAOTables` case + `SaoEnumsTest`; `ClosureContextResolver` (Ticket + reporting env → `ClosureContext`). Test.
- [x] Red → green; Pint + commit (`feat(sao): closure policies and context resolver`).

## Task 4: evaluator + audit + premature closure

- `ClosureDecision` VO; `ClosureEvaluator`; `ClosureAudit` (`sao_closure_audits`) + migration + factory; `SAOTables` case + `SaoEnumsTest`; `ClosureAuditService` (record + reopenForRecurrence marks premature with returned-after). Test.
- [x] Red → green; Pint + commit (`feat(sao): closure audit and premature-closure memory`).

## Task 5: time-to-truth + docs + parent bump

- `TimeToTruth` DTO + `TimeToTruthService`; full SAO suite green. Update SAO RAG docs/glossary + spec/plan indexes.
- [x] Red → green; Pint + commit (`feat(sao): time-to-truth metric`) + docs commit + bump parent.

## Exit criteria

- "Fix merged, deploy missing" is answerable from data; a policy closes/proposes only when its conditions hold with recorded evidence; a recurrence reopens and flags premature closure; time-to-truth reads from stamped timestamps.

## Known gaps

- Ownership suggestion persistence/UI; AI-phrased suggestions (phase 8); Filament policy/audit surfaces; live PR-merge transport.
