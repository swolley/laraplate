# SAO module — architectural design

**Date:** 2026-07-31
**Module:** `Modules/SAO` (Simply Another Orchestrator)
**Status:** Design agreed. No implementation started.
**License:** AGPL-3.0-or-later, `laraplate_owned: true` — same terms as Core/CMS/ERP/AI/MES.

---

## 1. Purpose

SAO is a **correlation engine between code, errors and work**.

It ingests already-selected events from third-party systems, correlates them to a project and a
deployed version, and turns them into tracked work. It is not a log aggregator, not an APM, not a CI
runner: it does not ingest volume, it ingests decisions made elsewhere.

The value no single tool on the market provides is the **third leg**. Sentry groups errors but does
not know the fix is already merged on `dev` and only a deploy is missing. Jira tracks work but does
not know the error reappeared in production last night. SAO holds together *which code runs where*,
*which error manifests*, and *which work is open*.

### Origin

SAO is written from scratch inside Laraplate. It has no code, data, configuration or runtime
relationship with any pre-existing system, and inherits no implementation. The engineering choices
below rest on standard industry practice for webhook-driven integration services — idempotent
ingest, persisted deduplication keys, error-message normalization, configurable mapping profiles —
and on the constraints of the Laraplate platform itself.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | Single-tenant, following Laraplate | Laraplate is single-tenant today; SAO inherits whatever it becomes. No tenancy design in SAO. |
| D2 | Greenfield | No inherited implementation, no data migration, no feature-parity obligation towards anything. |
| D3 | SAO is a **complete ticketing system with zero connections configured** | Every external integration is optional and independently switchable. Internal ticketing is the core, not a late plugin. |
| D4 | `Ticket` is always local; external trackers are a **sync layer** | Makes standalone use and integrated use the same architecture. |
| D5 | Sync direction is declared **per binding**: `local`, `mirror`, `shadow` | Covers both standalone use and teams that genuinely work inside Redmine/Jira. |
| D6 | Ingest is **push-first**; each driver declares supported modes (`push`, `pull`, `in_process`) | Polling gets implemented only where a real driver needs it, without redesign. |
| D7 | Fingerprint normalization rules live in **Core**, shared by the Monolog resolver and SAO | One event yields one key whether fingerprinted in-process or received by SAO. |
| D8 | Automatic ticket state changes are **deterministic evidence policies**, not AI judgements | Ships in phase 6, two phases before AI. |
| D9 | AI arrives in phase 8 as a **hard module requirement** (`requires: ["Core", "AI"]`) | Same convention as MES → ERP. Consequence accepted: from phase 8 on, installing SAO installs AI. |
| D10 | First concrete vertical: Laraplate's own logs → internal ticket, then Redmine | Exercises the whole flow without needing any external system to test against. |
| D11 | SAO ingests its own errors, **except** those emitted while its own pipeline runs | Real self-diagnosis with the feedback loop made structurally impossible, not merely improbable. See §6.1. |

---

## 3. Boundaries

- **No orchestration logic in a UI layer.** The engine lives in services and queued jobs. Filament
  and `laraplate-ui` are consumers. Removing every panel must leave SAO fully functional headless.
  This is what makes "ticketing system", "background orchestrator" and "configurator only" the same
  architecture under different configuration, rather than three products.
- **No provider knowledge in the core.** Core services speak capabilities only. A vendor name
  (`github`, `redmine`, `graylog`) appearing outside `Modules/SAO/app/Drivers` is a design defect.
- **Core is a hard dependency** (`requires: ["Core"]`). AI becomes one at phase 8 (D9).
- **No AI reference before phase 8.** Not even contracts or null objects. Verifiable condition: no
  occurrence of `Modules\AI` in SAO source before phase 8.

### Non-goals

- Log storage, search or aggregation at volume.
- Metrics/APM collection.
- Running CI or deployments.
- A generic configurable rule engine. Policies stay in typed configuration until at least three real
  cases diverge.
- SAO instances federating into other SAO instances.

---

## 4. Domain model

Tables use the `sao_` prefix, declared through a `SAOTables` enum using
`Modules\Core\Enums\Concerns\HasModuleTablesUtils` — the pattern already used by `AITables`. Names
like `connections`, `projects`, `tickets` are too generic to sit unprefixed beside Core/CMS/ERP.

| Entity | Role |
|--------|------|
| `Connection` | A reachable external system: driver key, base URL, encrypted credentials, health state, **declared capabilities**. One GitHub connection exposes `vcs` + `issues` + `releases`; Redmine only `issues`; Graylog only `logs`. Prevents configuring the same token twice. |
| `Project` | The correlation anchor — a tracked software project. Holds no URLs or credentials: it holds **bindings** to connections (this repo on that connection, that board on that tracker, that log stream). Multiple bindings of the same family are allowed. |
| `ProjectBinding` | `Project` × `Connection` × capability × remote identifier, plus binding-scoped configuration: sync direction (D5), status map, priority map. |
| `Environment` | A deployed instance of a project (`production`, `staging`, customer X) with its current running version. Serves deploy census **and** prevents errors from different environments merging. |
| `IngestEvent` | A raw received event: connection, `delivery_id` unique per connection, payload, status, outcome. Gives idempotency on retries, replay during development, and diagnosis when mapping fails. |
| `SourceProfile` | Normalization profile stored in DB: matchers + JSONPath field bindings to canonical fields. Lets a new source be supported **by configuration, without code**. |
| `Signal` | An error group: `group_key`, `algo_version`, project, counters, first/last seen, state, affected versions. |
| `SignalOccurrence` | Individual occurrence with configurable retention. Needed for "recurring for three days" and for closure evidence; not kept forever. |
| `SignalAlias` | Superseded `group_key` → `Signal`. The mechanism that lets the fingerprint algorithm evolve without splitting history. |
| `Ticket` | Canonical unit of work: title, body, canonical status, priority, assignee, comments. Exists with or without any external counterpart. |
| `TicketLink` | `Ticket` ↔ external tracker (connection, remote id, URL, last sync state). Absent link = internal ticket. |
| `ChangeRef` | Link between a code artefact (commit, pull request, tag) and a ticket, with the source that produced it. Backs code-to-work deduplication. |
| `Release` | A **product version** of a project, with a status: *announced* (a release candidate exists, so the version will ship) or *shipped* (a stable tag exists). Distinct from the **tags that realize it**, since one version is usually preceded by several candidates. Plus the map of what is deployed where. Fed actively from VCS APIs, not only inferred from alerts. See §9.1. |
| `ClosurePolicy` | Per-project set of composable closure conditions and the action taken when they hold. |

### Environment liveness

`Environment` carries a **liveness signal**: the last time the environment was observed sending
anything. Absence of errors is only evidence when the source was demonstrably alive. See §9.

---

## 5. Driver framework

A **driver** is registered code; a **connection** is a configured instance of it. The registry is
populated by SAO's service provider but is **open**: another module or a third-party package can
register a driver without modifying SAO. If adding a provider requires editing SAO, the abstraction
has failed.

The base contract declares: key, **exposed capabilities**, **supported ingest modes**, a
configuration schema (so connection forms are generated rather than hand-written per driver), and a
health check.

Per-capability contracts, which domain services depend on exclusively:

| Capability | Responsibilities | First implementations |
|------------|------------------|----------------------|
| `issues` | create/update tickets, comment, look up by key, translate statuses and priorities | internal, Redmine, GitHub, GitLab, Jira |
| `vcs` | read commits and ranges, compare branches, read a file at a ref, open pull requests | GitHub, GitLab, Bitbucket, Gitea |
| `logs` | verify signature, unpack a delivery into events, **declare whether a native grouping key is carried** | Laraplate internal, Graylog, Sentry |
| `releases` | list tags, find the first tag containing a commit | same drivers as `vcs` |

### Two details that are not minor

**Status maps live on the binding, not on the driver.** Redmine statuses are per-installation and
Jira workflows are per-project: no driver can know a priori that "Risolto" means canonical
*resolved*. The map is configurable data, with defaults proposed by the driver and correctable in
the UI. Ignoring this is the fastest way to make the module unusable outside one specific Redmine.

**Every outbound write is idempotent by construction.** Queued jobs carry a persisted idempotency
key: a retry must never be able to produce a second comment or a second ticket. Trackers rarely
offer idempotent write APIs, so the guarantee has to live on our side of the wire.

**Every list endpoint paginates.** Tags, branches, commits and issues all arrive a page at a time,
with a provider-specific and often silently capped page size. A driver that reads the first page and
stops looks correct on a small project and loses data on a real one. The capability conformance
suite includes a fixture with more items than one page holds.

### Where configuration lives

Secrets and infrastructure — credentials, endpoints, queue and cache wiring — stay in the
environment and are never written to the database. Product behaviour — thresholds, durations, policy
toggles, which suffixes mark a release candidate — has its default in config and is editable in the
UI, with the stored value overriding the environment.

The line matters because the two have opposite requirements: a secret must be rotatable without a UI
and must never be readable from one, while a threshold that requires a deployment to change will
simply never be tuned. SAO stores the editable half through Core's existing settings, not a
mechanism of its own.

### Planned driver waves

| Family | First wave | Second wave |
|--------|-----------|-------------|
| VCS / versioning | GitHub, GitLab, Bitbucket, Gitea | Forgejo (reuses the Gitea driver), Azure DevOps Repos |
| Logs / errors | Laraplate internal (Core GELF), Graylog, Sentry/GlitchTip | Grafana Loki + Alerting, Elasticsearch/OpenSearch, Prometheus Alertmanager |
| Issues | internal, Redmine, GitHub Issues, GitLab Issues, Jira | Linear, Plane, OpenProject, YouTrack, Azure DevOps Boards |

Error sources come in two races, and the interface must express the difference: **error trackers**
(Sentry, GlitchTip, Rollbar, Bugsnag) already group and arrive with a stable native key, which SAO
must *respect* rather than recompute; **log/alert systems** (Graylog, Loki, Elastic, Alertmanager)
deliver raw events for which SAO computes its own key.

---

## 6. Ingest and normalization

```
delivery → signature verification (driver)
        → IngestEvent persisted (idempotent on delivery_id)
        → SourceProfile selection (matchers)
        → canonical field extraction (JSONPath bindings)
        → group key: native if present, computed otherwise
        → correlation to Project + Environment + deployed version
        → Signal: new or recurrence
        → action: open ticket / comment / stay silent per policy
```

Every step records its outcome on the `IngestEvent`, with an explicit reason when it stops
(`profile_not_found`, `project_not_correlated`, `below_threshold`, `duplicate`). When an alert
produces no ticket, the answer to *why* must be available in seconds without reading logs.

**Replay is a product function, not a development tool.** Retained sample payloads allow replaying
an event against a modified profile in **dry-run**, showing what would have happened. This is what
makes a new source configurable without code, and how normalization is tested without access to the
external system.

### 6.1 Self-ingestion and loop protection

SAO runs inside a Laraplate application, that application emits logs, and SAO ingests logs. If an
error raised *by the ingest pipeline* re-enters the ingest pipeline, the result is not a slow loop
but amplification: each pass can produce more than one successor.

Filtering by module is not sufficient, and this is the non-obvious trap: an error inside SAO can be
thrown by Core code called from SAO, so `file_module()` reports `Core` and a module filter never
fires.

Three layers, in order of precision:

1. **Runtime origin marker.** While an ingest, normalization or synchronization job runs, a scoped
   context marks every log it emits as pipeline-originated. The internal log source discards those
   records *regardless of which module produced them*. This closes the loop at the root rather than
   by heuristic.
2. **Per-group rate limiter.** Past N occurrences of the same signal within a window, SAO stops
   acting: one ticket, then silence. This defends against any storm, not only self-feeding ones — a
   looping error inside the *observed* application does equal damage.
3. **No outbound amplification.** A failed external write does not log at error level on an ingested
   channel; it is recorded as the `IngestEvent` outcome, which is where it belongs.

With layer 1 in place, a loop would require the pipeline itself to fail, which is exactly the
excluded case. Everything else SAO produces — Filament, console commands, domain services — is
ingested normally, so the module diagnoses itself like any other.

**Project correlation is the most fragile point of the whole design.** The natural implementation —
a cascade of fallbacks buried in code, each added when the previous one missed a case — becomes
unexplainable within months: nobody can say why a given event landed on a given project. SAO uses an
**ordered, inspectable correlation ruleset**, visible in the UI, recording *which rule won* on each
event.

---

## 7. Fingerprinting

### Starting point

Core already ships `GelfErrorFingerprintResolver`: an **in-process** Monolog resolver that sees the
real `Throwable`, walks to the root cause via `getPrevious()`, attributes the owning module through
`file_module()`, falls back to the caller frame for non-exception log records, and normalizes
`{uuid}`, `{ip}`, `{hex}` and `{n}` before hashing to a 16-character digest of
`kind + module + class + file + line + message`.

That resolver is authoritative precisely because it runs inside the process. SAO faces the opposite
situation: it receives **strings** — a payload where the exception class, file, function and message
have already been flattened into text by whatever produced them, sometimes with a stack trace
embedded in the message, sometimes with the file and line missing entirely. Two capabilities Core
has never needed become mandatory:

- **recovery** — reconstructing class, file and function from flattened text;
- **defensive normalization** — stripping embedded stack traces, and collapsing the volatile parts
  of messages that would otherwise make every occurrence unique (database driver errors that inline
  the full failing statement, HTTP client errors that inline the whole response body).

SAO needs both halves. Hence the shared design below.

### Shared design

Normalization rules move out of Core's resolver into a dependency-free component at
`Modules\Core\Logging\Fingerprint\`: an **ordered chain of rules** (strip stack traces, collapse
volatile payloads, collapse SQLSTATE, substitute uuid/ip/hex, substitute numbers in value position).
Each rule is a class testable in isolation, and the chain is extensible — supporting a new error
format means adding a rule, not editing a long method.

Two *frame resolvers* sit above it:

- **in-process** (Core, today): from the `Throwable`, with root cause and module attribution;
- **from payload** (SAO, new): recovers file, class and function from flattened text.

Same rules, same hash.

### Three corrections to Core's current algorithm

1. **The line number leaves the hash.** Core includes `line`. Any refactor that shifts lines changes
   the fingerprint: same bug, new ticket after a release. It is the leading cause of real-world
   duplicates and precisely what breaks cross-version correlation. Hash parts become
   `kind + module + class + normalized file + function + normalized message`; the line is retained
   as metadata. `module` is populated only where derivable (Laraplate paths via `file_module()`) and
   empty otherwise — it is stable per source, which is what matters for grouping.
2. **Numeric normalization becomes targeted.** `\d+ → {n}` applied to everything merges distinct
   errors — a 404 and a 500 become one group. Numbers are normalized in value position only.
3. **The algorithm is versioned.** `algo_version` is stored on the `Signal` **from the first
   migration** — adding the column later means backfilling unknown values over existing rows. The
   recompute job and `SignalAlias` writes are *not* built up front: Laraplate is not yet in
   production, so until SAO runs somewhere real the algorithm can simply be changed and improved.
   The machinery gets written when the first change lands on a live installation, and the key format
   must keep that possible from day one.

### Native keys

Keys carried by the source are **namespaced per source** (`core:…`, `sentry:…`) so keys from
different systems cannot collide. SAO computes its own key **only in their absence**.

---

## 8. Ticket synchronization

A project with **no** `issues` binding is `local`: tickets exist only in SAO. This is the default and
requires no configuration. When an `issues` binding exists, it declares its direction:

| Direction | Meaning |
|-----------|---------|
| `mirror` | SAO owns the ticket; the external system receives writes. |
| `shadow` | The external system owns the ticket; SAO reads it and keeps the correlation. |

The `Ticket` model records field provenance so a `shadow` binding cannot have remote content
overwritten by local edits, and a `mirror` binding cannot silently lose local intent to a remote
change.

---

## 9. Evidence-based closure policy

Automatic state changes are permitted, gated by configuration, and **decided by deterministic
predicates over verifiable facts** — not by a model's judgement. The facts are ones SAO already
collects: `ChangeRef` for the pull request, `SignalOccurrence` for recurrence, `Release` +
`Environment` for the deployment.

A `ClosurePolicy` is a set of composable conditions combined with AND, each an independently
testable class:

- `pull_request_merged` — the linked PR is approved and merged into the target branch
- `no_recurrence_for(duration)` — scoped to the **reporting environment**
- `fix_released` — a **shipped** release containing the fix commit exists. Not an announced one: a
  fix present only in a release candidate is on staging, not in anyone's production (§9.1)
- `fix_deployed_there` — that release is the version currently running on the reporting environment
- `resolved_for(duration)` — ticket marked resolved with no counter-evidence since
- `internal_tickets_only` — restricts the policy to tickets with no `TicketLink`

The action when the conditions hold is configurable: **close**, **propose closure**, or **notify
only**. Default is prudent: on external bindings in `shadow` direction — where the foreign tracker
owns the ticket — the default action is *propose*, never *close*.

Every automatic change records **which conditions held and with what evidence**, and is reversible:
if the signal reappears, the ticket reopens and the event is marked a premature closure. That record
is also the data that says whether configured durations are tuned correctly.

### 9.1 Release candidates are not releases

A project's tags come in two shapes: **stable** (`v1.2.3`) and **release candidate**
(`v1.2.3-rc.7`). Treating them alike breaks the closure policy in both directions, so `Release`
separates the *product version* from the *tags that realize it*.

A candidate is worth recording rather than ignoring, for a reason that is operational rather than
semantic. The first candidate announces that the version **will** exist, which lets SAO attribute a
fix version to a ticket immediately. Waiting for the stable tag instead means scanning the entire
commit range accumulated since the previous release in one go — slow, and increasingly likely to
straddle unrelated work. Processing each candidate keeps every scan to the range since the last tag
processed, whichever kind it was.

But a candidate must never satisfy `fix_released`. A fix present only in a candidate is on staging;
nobody's production is running it. Announced means *coming*; shipped means *out*. Conflating them
would close tickets for bugs that are still live for every user.

Which suffixes count as candidates is configuration, not a hardcoded pattern: `-rc.N` is common,
`-beta`, `-alpha` and others exist, and a project that uses none of them must not be forced to.

### Silence is not evidence

"No recurrence for 15 days" is solid evidence only if the source was alive during those 15 days. If
the server was powered off, the log shipper broke, or the stream was reconfigured, silence is
missing data disguised as recovery. The condition is therefore formulated as *source alive within
the window AND no occurrences*, backed by the `Environment` liveness signal (§4). Small to
implement, devastating to omit.

---

## 10. AI (phase 8)

Through phase 7, SAO contains no trace of AI: no placeholder contracts, no gates, no null
implementations. At phase 8, `module.json` becomes `"requires": ["Core", "AI"]` — the MES → ERP
convention — and AI features use the AI module's existing infrastructure, including `ActionRequest`,
`GuardrailsService` and `ModerationService`, rather than reinventing guardrails.

Phase 8 gets its own brainstorming, with the experience of phases 0–7 behind it. Nothing about AI
behaviour is specified here beyond the invariant below, because the useful questions — what is worth
asking a model, what evidence it needs, where it earns its cost — can only be answered once the
deterministic layers exist and have run.

AI proposes and drafts; the closure decision belongs to the policy of §9. Generated content is
always labelled as such.

---

## 11. User interfaces

**Filament** (superadmin, first): connections with driver-schema-generated forms and a test button;
projects and bindings; source profiles with dry-run replay; ingest event monitor showing the
explicit reason for every discard; signal list and detail; tickets; environment/version matrix.

**`laraplate-ui`** (Vue, proprietary, later): the experience for people doing the work — ticket
board, signal detail with history, project timeline.

Both go through the same engine services. No shortcuts.

---

## 12. Testing strategy

- **Per-capability conformance suite.** Any driver implementing `issues` passes the same battery,
  run against an in-memory double and, where possible, against recorded real responses. A driver is
  not done when it works; it is done when it passes conformance. This is the defence against a fake
  abstraction where the second driver barely fits and the third needs a core exception.
- **Anonymized real payload corpus** per driver, driving normalization tests.
- **Fake drivers** for domain tests.
- **The "zero connections configured" scenario**, exercising the complete work flow. This is the
  guard that keeps integration concerns out of the domain.
- Fingerprint rules tested individually and as a chain, with a shared fixture corpus proving Core
  and SAO produce identical keys.
- Metrics: events by outcome, latency, dedup rate, connection health.

---

## 13. Phased decomposition

Each phase gets its own spec and implementation plan.

| Phase | Content | Exit criterion |
|-------|---------|----------------|
| **0** | Scaffolding parity with ERP/AI/CMS | Module tests green in the application suite; Pint, PHPStan and `composer validate` clean; SAO registered as a submodule |
| **1a** | Projects, ticket keys, tickets, types, workflow schemes, comments, history, Filament | Usable as a standalone tracker, zero connections. Unblocks phase 2. See `2026-08-01-sao-phase-1a-ticketing-core-design.md` |
| **1b** | Labels, watchers, attachments, due dates, ticket relations, search | Ticket enrichment; independent of the error flow |
| **1c** | Kanban board | Custom Filament page; deliberately last, so it is designed against real data |
| **2** | Shared fingerprint in Core + `Signal` + internal log source + loop protection (§6.1) | Laraplate error → signal → internal ticket, nothing external; a forced failure inside the pipeline produces no signal |
| **3** | Driver framework, `Connection`, capabilities, registry, conformance + **Redmine** | Ticket synchronized in a configurable direction |
| **4** | `SourceProfile`, generic webhook ingest, replay + **Graylog** | A new source configurable without writing code |
| **5** | `vcs`/`releases` capabilities + first Git driver + `ChangeRef` + version census | Commit → ticket, and "what runs where" |
| **6** | Fix propagation + **evidence-based closure policy** (deterministic, no AI) | "Already fixed on `dev`, deploy missing"; auto-closure with audited evidence |
| **7** | Second driver wave (GitLab, Bitbucket, Gitea, GitHub Issues, Jira, Sentry) | Each passes capability conformance |
| **8** | AI, as a hard module requirement | Own brainstorming first |
| **9** | `laraplate-ui` surfaces | — |

Phases 0–2 produce something **usable**: a working tracker that collects Laraplate's own errors by
itself. From phase 3 onward it opens to the world.

### Phase 0 concrete gap

`Modules/SAO` is currently the bare nwidart stub. Missing relative to ERP/AI/CMS: `LICENSE`,
`README.md`, `CHANGELOG.md`, `cliff.toml`, `phpstan.neon`, `pint.json`, `rector.php`, `peck.json`,
`phpunit.xml`, `scripts/`, `docs/`, `.cursor/`. `composer.json` lacks license, version, the
`swolley/laraplate-sao` name, PHP/Laravel constraints, dev dependencies and the full test script
battery. `module.json` lacks `"laraplate_owned": true`, `"requires": ["Core"]`, a description and
keywords.

---

## 14. Known risks

| Risk | Mitigation |
|------|-----------|
| The plugin abstraction turns out to be fake | Per-capability conformance suite from phase 3; two drivers per capability before declaring the interface stable |
| Project correlation misroutes events | Explicit ordered ruleset, winning rule recorded per event, dry-run replay |
| Fingerprint change causes a ticket storm | `algo_version` column from the first migration; recompute + `SignalAlias` written before the first algorithm change on a live installation |
| SAO ingests its own errors and amplifies them | Runtime origin marker excluding pipeline-emitted logs, per-group rate limiter, no error-level logging of outbound failures on ingested channels (§6.1) |
| Auto-closure closes live bugs | Environment liveness requirement; `propose` default on `shadow` bindings; reopen-and-flag on recurrence |
| Integration concerns leak into the domain | The zero-connections scenario is a permanent test, not a one-off check |
| AI hard requirement burdens ticketing-only installs | Accepted (D9); consistent with MES → ERP |
