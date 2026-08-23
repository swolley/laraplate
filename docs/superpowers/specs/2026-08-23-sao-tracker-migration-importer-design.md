# SAO — External Tracker/Error-Service Migration Importer ("switch to Laraplate, import your history")

**Status:** implemented (live-API path) · **decisions locked 2026-08-23** · **Module:** SAO
**Related:** `2026-08-15-sao-phase-3b-issues-sync-design.md`,
`2026-07-22-module-import-command-framework-design.md`,
`2026-08-23-core-bulk-import-mapping-design.md`.

## Decisions (locked 2026-08-23) & what shipped

- **Open-only via `status_map` → category.** `TrackerImportService` maps each
  issue's remote status through the binding's `status_map` to a canonical
  `StatusCategory` and, under `ImportScope::Open`, skips terminal (closed/rejected)
  issues; an unmapped status is kept as open so nothing active is lost.
- **Migration-friendly upsert.** `IssueSyncService::import()` reuses the reconcile
  upsert but drops the unmapped-status gate — a migration brings the whole history
  in, every ticket opening at the workflow's initial status. Idempotent by
  `TicketLink`, so re-running is safe.
- **Persistent resume shipped (2026-08-23).** `TrackerImportService` now records an
  `ImportRun` per (binding, scope) in `sao_import_runs`: the driver's next-page
  cursor and running counts, saved after every page. An import killed by a crash,
  redeploy or requeue resumes the still-`running` run from its stored cursor
  instead of restarting; it flips to `completed` only when the page walk is
  exhausted, and re-importing a finished migration opens a fresh run.
- **Comment/attachment history shipped (2026-08-23).** Optional
  `IssueHistoryCapability` (an `issues` extension, driver-optional like
  `BlameCapability`): when the driver implements it, each imported ticket also gets
  its comments (idempotent by remote comment id, stored as `system` `TicketComment`s)
  and attachments (idempotent by remote attachment id, stored in the `attachments`
  media collection). The driver downloads attachment bytes itself — only it holds
  the connection credentials — so the importer never makes an unauthenticated
  request.
- **Import + cutover shipped together.** `BindingCutoverService` flips the binding
  to authoritative — `disabled` (fully switched to Laraplate) by default, or
  `outbound` for a transition — keeping `TicketLink`s as provenance.
- **Surfaces:** `sao:tracker:import {connection} {--project=} {--scope=all|open}
  {--cutover} {--cutover-direction=} {--queue}` runs inline or dispatches
  `ImportTrackerHistoryJob` per binding.
- **Retention added (2026-08-23).** Instead of importing log history, SAO keeps
  logs live-only and prunes aged data: `RetentionService` + `sao:prune` hard-delete
  old signal occurrences, ingest events and deployments (latest-per-env kept), and
  the heavy data of long-deactivated projects (project/environments/releases kept
  as anagraphic). Windows via `sao.retention.*`, scheduling gated by
  `SAO_RETENTION_SCHEDULE`.
- **Follow-ups closed (2026-08-23):** comment/attachment history and persistent
  resume both shipped (above). Note on pagination correctness: the persistent
  cursor makes an interrupted import resumable, but true stability under concurrent
  writes still requires the driver to page ascending by an immutable key (keyset)
  rather than by offset; idempotency neutralises duplicates a shifting offset could
  reintroduce, so the practical guarantee holds, and per-driver keyset ordering
  stays a driver-level refinement. Error-service history is out (logs stay
  live-only). The file-dump path is shared with the bulk-import spec.

> One of four specs scaffolded together. Analysed and implemented on its own later.

## The idea

Offer a migration path: *"stop using your external tracker and start using
Laraplate — and if you want, import the history, all of it or only the items still
open."* The connectors already let SAO mirror an external tracker; this turns the
mirror into a **one-time (or staged) bulk import** with a clean cutover.

## What already exists (strong reuse)

- `IssuesCapability::list(BindingContext, ?cursor): Page` — cursor paging over a
  tracker's issues; nine external drivers (Jira, GitHub, GitLab, Bitbucket, Gitea,
  Azure DevOps, YouTrack, Linear, Redmine) + `internal`.
- `IssueSyncPoller::poll(ProjectBinding)` — **already walks all pages** until
  `nextCursor === null`, with a `MAX_PAGES = 1000` truncation guard. Exactly the
  "read all history" loop.
- `IssueSyncService::reconcile(ProjectBinding, array $issue): SyncOutcome` — the
  idempotent upsert primitive: matches `TicketLink` by `(connection_id,
  remote_id)`, else creates a `Ticket` via `TicketCreationService`; SHA-256
  `idempotency_key` in `SyncOperation`; outcomes `Created | Updated |
  SkippedIdempotent | UnmappedStatus | NotFound | SkippedDirection`.
- `NormalizedIssue` — provider-agnostic issue DTO. Remote status → canonical
  category via the binding's maps.
- Core provenance store `RecordOrigin` / `RecordOriginRegistry`
  (`core_record_origins`) for `(referable, source_key, external_id)` dedupe.

**The gaps:** the poller is **synchronous** (no async/resumable bulk mode); no
"open-only / date-range" history filters; no comment/attachment history (the
`issues` capability has `comment()` for push but no pull-comments); error-services
(Sentry/Graylog) deliver signals via push, with no historical **issue** backfill;
and no "cutover" concept turning a mirror binding into an owned one.

## Proposed direction

1. **Async, resumable bulk import** on top of the existing binding/driver seam: a
   queued job that reuses the poller loop, chunked, resuming from a stored cursor
   (`SyncOperation`), with progress surfaced and completion pushed to the in-app
   notification feed (see the notifications spec).
2. **Filters:** `{all | open-only}`, optional date range / status filter, mapped to
   the driver's `list` query where supported (open decision per driver).
3. **Target:** SAO tickets from `NormalizedIssue` via `TicketCreationService`,
   remote status → canonical category via binding maps; provenance via `TicketLink`
   (and optionally `RecordOrigin` for uniformity with other importers).
4. **Cutover / binding mode:** after import, either keep the binding as an ongoing
   mirror or **sever it** so SAO becomes authoritative — a `shadow → owned`
   transition on `ProjectBinding` (open: how sync direction and closure defaults
   change on cutover; `ClosureAction` already defaults to `Propose` on shadow).
5. **History depth:** comments and attachments as a later increment — needs an
   `issues` capability extension (`listComments`/pull), not present today.
6. **Error-service backfill:** for Sentry/GlitchTip etc., a historical issue/signal
   pull would need a pull-capable `logs`/errors backfill (today `logs` is
   push-only `unpack`). Deferred; may instead be covered by file-based import of an
   exported dump via the generic bulk-import feature.

## Relationship to the generic bulk-import spec

Two complementary paths: **live API import** (this spec, via drivers) and
**file-dump import** (the Core bulk-import spec — e.g. a Jira CSV/JSON export
mapped to tickets). A tracker with no reachable API, or an air-gapped migration,
uses the file path; a reachable tracker uses the driver path. They should share the
`NormalizedIssue` → `TicketCreationService` upsert and `RecordOrigin`/`TicketLink`
dedupe so a mixed migration never double-creates.

## Open decisions

- Per-driver support for server-side `open-only` / date filters vs client-side
  filtering after fetch.
- Dedupe across an already-running mirror (skip already-linked) and across the
  file path (shared identity).
- Cutover semantics: sync direction, closure defaults, whether to keep `TicketLink`
  after severing.
- Comment/attachment history → capability extension scope.
- Rate-limit / backoff budget for large histories (reuse driver health/throttle).

## Out of scope

No new tracker semantics; migration rides the existing driver + Core import seams.

## Sequencing

1. Async, resumable bulk-pull job + `all|open-only` filter over existing drivers,
   with progress + completion notification.
2. Cutover / binding-mode (`shadow → owned`).
3. Comment/attachment history (capability extension).
4. Error-service / file-dump backfill (shared with the generic bulk-import spec).
