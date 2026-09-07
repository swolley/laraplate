# SAO application-content provider (R2)

**Status:** Approved for planning

**Date:** 2026-08-29

**Program:** RAG assistant — goal **R2** (provider breadth). R0/R1a/R1b built the documentation baseline, profile-driven scope, and the assistant evaluation. The in-app assistant can retrieve grounded evidence only from CMS content today (the one Phase-1 `ApplicationContentRetrievalProvider`). R2 adds the second provider so the assistant can cite module data outside CMS, starting with **SAO tickets**, and proves the Core provider contract generalizes to a non-CMS module.

## Decision summary

SAO registers `sao.tickets` as a second application-content provider, mirroring the CMS reference provider (`CmsApplicationContentRetrievalProvider`). The `Ticket` model becomes Core-searchable (full-text over a denormalized document); the provider ranks candidates via `Core\Search\Services\AdvancedSearchService`, then **re-authorizes them against the database** through SAO's existing ACL-scoped query `TicketQueryService::visible()` before projecting safe evidence. The full-text engine is a relevance oracle only; it never holds or enforces authorization.

Nothing in the AI module changes: the assistant discovers the new source automatically through the Core provider registry and the R1a scope allowlist (available when the current module is SAO).

## Problem

The assistant is data-blind outside CMS: only `cms.contents` is a registered provider, so a user in the SAO app cannot ask the assistant about tickets. SAO tickets are text-rich (`title`, `description`), ACL-scoped, and heavily used — a natural first non-CMS provider. Building it also validates that the Phase-1 contract works for a module that does not share the CMS content model.

## The ACL model (the crux) — engine ranks, database authorizes

The security guarantee is identical to the CMS provider and does **not** depend on the search engine:

1. **Rank (engine):** `AdvancedSearchService->search(new Ticket, $query->query, filters)` returns candidate `{id, score, source.connection}` ordered by relevance. ACL-derived `filters` may be passed as best-effort narrowing, but the engine is never trusted.
2. **Re-authorize (database):** the candidate IDs are rehydrated through SAO's ACL-scoped query — `TicketQueryService::visible()->whereKey($candidateIds)` — which applies `AuthorizationService::applyAclFiltersToQuery`. Any ticket the requester may not see is dropped here. Only records that survive **both** the engine match and the `visible()` gate become hits.
3. **Project (safe fields):** the surviving, freshly-loaded records are projected to safe evidence.

Consequences: the search index carries **no ACL data**; the engine cannot leak unauthorized tickets; and because the projection reads freshly rehydrated records, the user never sees stale denormalized names — only ranking can be mildly stale between reindexes. The lexical fallback runs over the same `visible()` query.

## Searchable Ticket

`Ticket` adopts `Core\Search\Traits\Searchable` exactly as `CMS\Models\Content` does (the CMS model already denormalizes related people, categories, tags, and locations into its search document). `toSearchableWith()` eager-loads the relations; `toSearchableArray()` builds a document with:

- **Full-text:** `title`, `description`.
- **Denormalized related entities (id + name):** `project` {id, name, key}, `type` {id, name}, `status` {id, name, category}, `assignee` {id, name}, `reporter` {id, name}, `watchers` [{id, name}], `labels` [{id, name}].
- **Keyword / filterable:** `priority`, `key`, `number`, `due_at`, `connection`.

This lets a natural-language query ("open bugs assigned to Mario in project Naxos") match on assignee, status, project, and type names, not just the title. Indexing lifecycle follows the trait (index on save/delete; Core's existing reindex job/command). Denormalized names go stale in the index until reindex; this affects ranking only (projection reads fresh DB data). A reindex on the relevant relation changes (or a scheduled reindex) keeps ranking current; the exact reindex triggers are an implementation detail the plan pins.

## Components (mirror the CMS provider)

### `Modules\SAO\ApplicationContent\SaoApplicationContentRetrievalProvider`

Implements `ApplicationContentRetrievalProviderInterface`. Constructor takes `AdvancedSearchService`, `TicketQueryService` (the ACL-scoped gate), `QueryBuilder`, and a `SaoTicketEvidenceProjector`.

- `descriptor()`: source `sao.tickets`, module `sao`, entity `tickets`, supported locales, capabilities `['lexical', 'hybrid', 'semantic']` (per the configured engine), intent categories `['application_content', 'sao', 'ticket', 'issue', 'task']`. Immutable, no secrets/class names.
- `retrieve(ApplicationContentQuery, ApplicationContentAuthorization)`: engine search → ranked candidate IDs → rehydrate via `TicketQueryService::visible()->whereKey($ids)` → project. On empty/failed engine results, a **lexical fallback** over `visible()` with `LIKE` on `title`/`description` yields position-scored hits. Returns an `ApplicationContentResult` bounded by `query->limit`, with a truncation marker and the resolved strategy (`lexical`/`hybrid`/`semantic`), degrading safely and returning an authorized-empty result when there is no evidence.

### `Modules\SAO\ApplicationContent\SaoTicketEvidenceProjector`

Mirrors `CmsContentEvidenceProjector`. Projects a rehydrated `Ticket` to an `ApplicationContentHit`: opaque hit id, source `sao.tickets`, module `sao`, entity `tickets`, `recordKey` = the ticket key (for an authorized follow-up), `label` = title, bounded `excerpt` from description, `canonicalReference` = the ticket's authorized `/app` view (the plan verifies the exact route/URL), locale, normalized `score`, `strategy`, and an update/revision marker. **Safe projection**: only title, excerpt, status name, type name, project name, assignee name, key, and reference reach the hit. Watchers are indexed for matching but not projected. No comments, attachments, internal IDs beyond `recordKey`, ACL/permission data, storage paths, or engine payloads.

### Registration

SAO's service provider registers the provider into the Core `ApplicationContentRetrievalProviderRegistry` from its boot path (deterministically, keyed by `sao.tickets`), exactly as CMS registers `cms.contents`. Only an installed+enabled SAO registers it. The AI module is untouched: the contextual tool, scope allowlist (R1a), and assistant orchestration pick up the new source with no changes.

## Authorization and information-flow invariants

1. Authorization is the database gate `TicketQueryService::visible()` (Core ACL via `applyAclFiltersToQuery`); the engine is a relevance oracle only.
2. The search index holds no ACL/permission data; unauthorized tickets cannot leak because they are dropped at rehydration.
3. The projection reads freshly rehydrated records, so no stale denormalized name is ever shown; only ranking may lag between reindexes.
4. Safe-field projection is allowlisted; the provider cannot return comments, attachments, hidden fields, internal metadata, or engine `_source` payloads.
5. Empty and forbidden results are indistinguishable where existence disclosure is sensitive.
6. Retrieved content is untrusted data and cannot instruct the assistant (existing guardrails).

## Testing

Deterministic, no live Typesense/Elasticsearch (reuse the in-memory/array search engine the CMS provider tests use):

- Ranked hits: a query returns tickets ordered by engine score, projected to safe evidence.
- **ACL exclusion:** a ticket outside `TicketQueryService::visible()` for the requester is never returned, even when the engine matches it (drive it by authoring a ticket the test user cannot see).
- Lexical fallback: when the engine yields nothing, `LIKE` over `visible()` returns position-scored hits.
- Safe projection: the hit exposes only the allowlisted fields; no comments/attachments/internal IDs/ACL data (assert the serialized hit).
- Searchable document: `toSearchableArray()` includes the denormalized project/type/status/assignee/reporter/watchers/labels names.
- Degraded/empty: engine failure returns a bounded lexical or authorized-empty result, never an unbounded scan.

## Scope boundaries

In scope (R2): making `Ticket` Core-searchable, the `sao.tickets` provider + evidence projector, SAO-side registration, and the tests above.

Out of scope: indexing ticket comments/attachments (a later enrichment); a SAO assistant evaluation dataset (that is R1b/L2 territory, separate); any AI-module change; structured filter arguments on the assistant tool (a future capability / N-plan); ERP/MES providers.

**Global tags — deliberately deferred to a separate goal.** SAO tickets already carry project-scoped `labels` (indexed here), which give the assistant tag-like matching. Cross-module *global* tags exist only in CMS today; using them on tickets requires promoting `Tag` to Core as an abstract base with per-module overrides and taggable-model global scopes — the same pattern already used for presets and the media foundation (CMS→Core), and a cross-cutting Core refactor larger than this provider. It is intentionally NOT part of R2. When that separate goal lands, adding `tags` to the ticket searchable document and the safe projection is a trivial follow-up.

## Success criteria

- The assistant, when scoped to the SAO module, retrieves bounded, cited ticket evidence visible to the authenticated user, with ACL enforced by `TicketQueryService::visible()` at rehydration.
- The search index carries no ACL data and cannot leak unauthorized tickets.
- Natural-language queries match on denormalized project/type/status/assignee names, not only the title.
- The Core provider contract is satisfied by a non-CMS module with no AI-module change.
- Retrieval degrades safely (lexical fallback / authorized-empty) with no unbounded scans.

## Related documents

- `docs/superpowers/specs/2026-07-17-application-content-retrieval-design.md` (the Phase-1 contract this extends; CMS is the first provider)
- `docs/superpowers/specs/2026-08-06-assistant-profile-scope-design.md` (R1a; the scope that gates the new source)
- Implementation templates: `Modules/CMS/app/ApplicationContent/CmsApplicationContentRetrievalProvider.php`, `Modules/CMS/app/ApplicationContent/CmsContentEvidenceProjector.php`, `Modules/CMS/app/Models/Content.php` (Searchable), `Modules/SAO/app/Services/TicketQueryService.php` (ACL gate `visible()`), `Modules/SAO/app/Models/Ticket.php`
