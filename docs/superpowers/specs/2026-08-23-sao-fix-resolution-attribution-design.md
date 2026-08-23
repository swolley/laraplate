# SAO — Fix Resolution & Attribution (which commits/versions actually fix a ticket)

**Status:** accepted · **decisions locked 2026-08-23; implementation in phases** · **Module:** SAO
**Related:** `2026-07-31-sao-module-design.md`, `2026-08-15-sao-phase-3b-issues-sync-design.md`,
`Modules/SAO/docs/plans/release-health-and-deploy-ingest.md`.

> One of four specs scaffolded together (fix-attribution, tracker migration importer,
> generic bulk import, in-app notifications) so none is forgotten. Each is analysed
> and implemented on its own later.

## The question

SAO already tracks which **versions** each app runs (deploy census) and on which
version a bug is or will be fixed. The open question: **how do we actually know
which commits/versions resolve a ticket** — not by a human toggling "Resolved",
but from evidence? And, strategically: **where must we depend on external
services, and where can SAO be better than them**, so the connectors become a
reason to make Laraplate the source of truth rather than the external tracker?

## What already exists (and the gap)

The evidence model is designed and its **read side is built**; the **write side is
mostly missing**.

- `ChangeRef` (`sao_change_refs`) — the commit/PR/tag ↔ ticket link.
  `ChangeRefType` = `Commit | PullRequest | Tag`; fields `identifier` (SHA / PR
  number / tag), `merged_at`, `base_ref`/`head_ref`, `url`, `source`. Scope
  `mergedPullRequests()`. Attached to a ticket via `Ticket::changeRefs()`.
- `Release` / `ReleaseTag` (`Stable` makes a release shippable, `Candidate` = RC) /
  `TicketRelease` (`Promised | Shipped`, independent of the ticket's workflow
  status) — the "which release carries this fix" ledger.
- `Environment.current_version` — fed live by `DeploymentIngestService` →
  `DeployCensusService::observe()` on a **succeeded** deploy (the only status that
  advances the census).
- Read models over that evidence, **no driver call**: `FixStatusResolver`
  (`pull_request_merged`, `fix_released`, `released_version`,
  `deployed_environments`, `missing_environments`, `deployed_there`),
  `TimeToTruthService`, and evidence-based closure (`ClosurePolicy` +
  `ClosureConditionRegistry`: `pull_request_merged`, `fix_released`,
  `fix_deployed_there`, `no_recurrence_for`, `resolved_for`, `internal_tickets_only`
  → `ClosureEvaluator` AND-semantics → `ClosureApplicationService` transitions only
  through `WorkflowService`, audited by `ClosureAudit` and auto-reversed by
  `reopenForRecurrence`). `StatusCategory::Resolved` is **deliberately
  non-terminal** — "fixed but unconfirmed".

**The gap.** There is no production writer for `ChangeRef`, `Release`,
`ReleaseTag` or `TicketRelease` — they exist only from factories/tests. Only the
`Deployment`/`Environment` feed is live. So today the resolution read models are
correct but starved: nothing tells SAO that commit `abc` (merged in PR #42,
shipped in tag `v1.4.0`) fixes `SAO-123`.

## Proposed direction — the attribution write path

Two writers, both reusing the existing driver capabilities:

1. **`ChangeRef` ingest — commit/PR → ticket.** Two feeds, host-agnostic:
   - *Message-parse* merged PRs and commits for ticket references
     (`SAO-123`, `fixes #123`, `Closes SAO-45`) via a configurable grammar, over
     `VcsCapability::commits(range)` (pull) and/or a `pull_request` webhook (push).
     Create `ChangeRef(commit|pull_request, identifier, merged_at)`.
   - This is a place SAO is **better**: it correlates uniformly across many repos
     and many trackers, something a single external tool cannot.
2. **`Release`/`TicketRelease` ingest — commit → version.** Use
   `ReleasesCapability::firstTagContaining($commitSha)` to map a fixing commit to
   the first tag/release that contains it → upsert `Release` + `ReleaseTag`
   (stable/candidate) + `TicketRelease` (`shipped` if a stable tag carries it,
   else `promised`). This answers "which version fixes the ticket" from data, and
   feeds `FixStatusResolver` / closure automatically.

With both writers live, "is `SAO-123` actually fixed, and everywhere?" is computed
end-to-end: merged PR → shipped release → deployed to every environment → no
recurrence → auto-close (audited, reversible).

## Dependence on external services vs SAO's edge

- **Depend on external for** raw git facts SAO does not host: commits, PR merge
  state, tags, compare/blame (`vcs`/`releases`/`blame` capabilities), and issue
  content when a foreign tracker stays authoritative (shadow bindings).
- **SAO is better at the correlation ledger**: joining code ↔ error-signals ↔ work
  ↔ releases ↔ deploys across many projects and many external tools; deriving
  resolution truth from **evidence** rather than a human flag (Resolved is
  non-terminal; closure needs merged + released + deployed + silence, and
  auto-reopens on recurrence); time-to-truth metrics; cross-project fingerprint
  dedup; reliable-silence closure; release-health regression (shipped separately).
  No external issue tracker sees your deploys or signals; no error-service sees
  your tickets or releases. **SAO is the join** — that is the pitch for the
  connectors: keep your current tools, let SAO own "is it truly fixed".

## Open decisions (resolve during deep analysis)

- Ticket-key grammar: per-project vs global; multiple keys per commit; false-match
  guards. Trust model: auto-attach vs propose-then-confirm (mirror ownership
  suggestions).
- Squash/rebase changing SHAs; monorepo vs multi-repo → project mapping.
- `TicketRelease` writer driven by `firstTagContaining` (pull) vs release webhooks
  (push); reconciliation when both fire.
- Backfill of historical refs (ties into the tracker-migration importer spec).
- Whether "fixing" refs should also flow from external tracker "resolved-in-version"
  fields when present.

## Decisions (locked 2026-08-23)

1. **Fixes vs Mentions is first-class.** `ChangeRef` gains a `relation` column
   (`ChangeRefRelation = Fixes | Mentions`, additive migration, default `Fixes`).
   A closing verb (`fixes|closes|resolves|…`) before a ticket key → `Fixes`; a
   bare key reference → `Mentions`. The resolution read-models (`FixStatusResolver`,
   `TimeToTruthService`, closure) count only `Fixes`; mentions stay as timeline
   context and never inflate the evidence. An upsert may upgrade `Mentions → Fixes`
   but never downgrades.
2. **Both transports.** A **pull** scan over `VcsCapability::commits(range)`
   (backfillable, no new webhook — synergy with the migration importer) *and* a
   **push** PR-merge webhook (low latency, precise `merged_at`) both feed the same
   `CodeReferenceWriter`.
3. **Read-model + propose, with a settings toggle for auto-close.** The pipeline
   always computes the `FixStatus` and, where a `ClosurePolicy` is active,
   proposes closure (never auto on a `shadow` binding — the existing `Propose`
   default). Automatic closure (all conditions satisfied → close through
   `WorkflowService`, audited, auto-reopen on recurrence — machinery already
   built) is gated by a module setting, off by default.

## Out of scope

SAO never hosts code and never gates a rollout. This spec is attribution and
evidence only.

## Sequencing (phased; each shippable and green)

1. **Attribution core** — `ChangeRefRelation` + migration, `TicketReferenceExtractor`
   (configurable grammar), `CodeReferenceWriter` (idempotent upsert, mention→fix
   upgrade), and `FixStatusResolver`/`TimeToTruthService` counting only `Fixes`.
   Pure, no transport.
2. **Release attribution writer** — map a fixing commit to its release via
   `ReleasesCapability::firstTagContaining` → upsert `Release`/`ReleaseTag`/
   `TicketRelease`.
3. **Pull transport** — `sao:vcs:scan` walking `commits(range)` per `vcs` binding
   into the writer (cursor/last-scanned), backfillable.
4. **Push transport** — a PR-merge webhook (new capability + ingest + the shared
   `webhooks/{connection}` route branch, mirroring deploy/logs).
5. **Closure activation** — wire `ClosureApplicationService` behind a module
   setting: propose by default, auto-close when the toggle is on.
