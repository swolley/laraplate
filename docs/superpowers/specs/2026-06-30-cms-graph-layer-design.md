# CMS Graph Layer — Relational Search & Exploration

**Status:** Approved (brainstorming 2026-06-30)
**Date:** 2026-06-30
**Module:** `Modules/CMS`
**Scope:** Graph API for frontend discovery/navigation, search-with-context, and CMS
connectivity analytics. No Filament UI. No dedicated graph database.

---

## Context

The CMS module already stores rich relationships in relational pivot tables:

| Edge type | Pivot / source |
|-----------|----------------|
| `tagged_with` | `cms_taggables` |
| `in_category` | `cms_categorizables` |
| `related_to` | `cms_relatables` (bidirectional) |
| `authored_by` | `cms_contributables` |
| `located_at` | `cms_locatables` |
| `parent_of` | `Category` hierarchy (recursive) |

`Content` is already **Searchable** (Elasticsearch via Core Search) and indexes nested
tags/categories/locations/contributors, but there is no graph-shaped API and no connectivity
analytics.

Consumers (priority):

1. **Frontend / API** — incremental graph expansion, search + relational context (Consumer B).
2. **Analytics / ops** — orphan tags, isolated contents, hubs (Consumer C).

Filament admin is **out of scope** (infra/dev use only).

---

## Goal

Expose CMS relationships as an explicit **nodes + edges** graph through dedicated routes that
follow existing CRUD URL conventions, with three distinct operations:

| Operation | Entry point | Storage |
|-----------|-------------|---------|
| **Expand** | Known `{entity}/{id}` | DB pivots only |
| **Search** | Text/query (`q`) | Elasticsearch → DB expand |
| **Stats** | Global aggregates | DB pivots (or materialized edges in Phase 4) |

All three share `GraphService` internals; they differ by **route and intent**, not by a single
`mode` switch parameter.

---

## Non-Goals

- Filament widgets or admin graph UI.
- Neo4j or any dedicated graph database.
- Graph write operations (links still managed via normal Content/Tag CRUD).
- Mandatory vector search (keyword ES search is enough for MVP search mode).
- Expand depth greater than **2** in MVP.
- Cross-module graph (ERP, etc.) — CMS routes only for now.

---

## Naming & ID Conventions

Use **plural `{entity}` names everywhere**, aligned with Core CRUD / `DynamicEntity`:

| CRUD entity | Node / edge endpoint ID |
|-------------|---------------------------|
| `contents` | `contents:101` |
| `tags` | `tags:12` |
| `categories` | `categories:5` |
| `contributors` | `contributors:3` |
| `locations` | `locations:7` |

**No singular/plural mapping layer.** URL segment, JSON `node.id`, and `source`/`target` on edges
use the same plural entity slug.

Allowed `{entity}` values for graph routes:

`contents`, `tags`, `categories`, `contributors`, `locations`

---

## API Routes (CMS module)

Registered in `Modules/CMS/routes/api.php` and `Modules/CMS/routes/web.php`.

### API (`api` + `crud_api` middleware, prefix `api/v1`)

| Method | Path | Phase | Description |
|--------|------|-------|-------------|
| GET | `/graph/expand/{module}/{entity}/{id}` | 1 | Expand subgraph from a known node |
| POST | `/graph/search/{module}` | 2 | ES search + optional graph expansion |
| GET | `/graph/stats/{module}` | 3 | Connectivity metrics (JSON) |

### Web (session, prefix `app/crud`)

| Method | Path | Phase | Description |
|--------|------|-------|-------------|
| GET | `/crud/graph/expand/{module}/{entity}/{id}` | 1 | Same as API expand |
| POST | `/crud/graph/search/{module}` | 2 | Same as API search |
| GET | `/crud/graph/stats/{module}` | 3 | Same as API stats |

Route constraints:

- `{module}` → `cms` (MVP; keep segment for CRUD symmetry).
- `{entity}` → `whereIn('entity', ['contents','tags','categories','contributors','locations'])`.
- `{id}` → `whereNumber('id')`.

### Artisan (Consumer C, ops/cron)

```bash
php artisan cms:graph-analytics [--output=json|table] [--metrics=...]
```

Same metrics as `/graph/stats/cms`; command is the primary ops entry.

---

## Request / Response Contracts

### Expand — `GET .../graph/expand/cms/tags/12`

**Query parameters:**

| Param | Type | Default | Max (MVP) | Description |
|-------|------|---------|-----------|-------------|
| `depth` | int | `1` | `2` | Traversal depth from center node |
| `types[]` | string[] | all node types | — | Filter returned node types |
| `edge_types[]` | string[] | all edge types | — | Filter edge types |
| `limit` | int | `50` | `200` | Max nodes in response |

**Response** (standard CRUD envelope via `ResponseBuilder`):

```json
{
  "data": {
    "nodes": [
      {
        "id": "tags:12",
        "entity": "tags",
        "label": "laravel",
        "slug": "laravel",
        "path": null,
        "meta": {}
      }
    ],
    "edges": [
      {
        "id": "edge:contents:101:tags:12:tagged_with",
        "source": "contents:101",
        "target": "tags:12",
        "type": "tagged_with",
        "weight": 1
      }
    ],
    "graphMeta": {
      "center": "tags:12",
      "depth": 1,
      "truncated": false,
      "edge_count": 31
    }
  },
  "meta": {
    "status": 200,
    "currentRecords": 23,
    "cachedAt": "2026-06-30T12:00:00Z",
    "duration": "45ms"
  }
}
```

Graph-specific fields live in `data.graphMeta`. Standard CRUD meta fields (`currentRecords`,
`cachedAt`, `duration`, …) come from `CrudMeta` + `ResponseBuilder`, same as `/select` and
`/detail`.

**Behavior:**

- No Elasticsearch involvement.
- Supports **async incremental loading**: frontend calls expand on each user navigation click;
  responses are merged client-side.
- `meta.truncated = true` when `limit` is hit; client may re-request with narrower filters.

### Search — `POST .../graph/search/cms`

**Body (JSON):**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `q` | string | required | Search query (full-text; vector optional later) |
| `expand_depth` | int | `1` | `0` = ES hits only; `1+` = expand neighbors via pivots |
| `entity` | string | null | Restrict ES to one entity type (e.g. `contents`) |
| `filters` | object | `{}` | ES filters (categories, tags, validity, etc.) |
| `limit` | int | `10` | Max ES hits before expansion |

**Response:**

```json
{
  "data": {
    "results": [
      {
        "id": "contents:101",
        "score": 0.92,
        "entity": "contents",
        "source": { "title": "Guida Laravel", "slug": "guida-laravel" }
      }
    ],
    "graph": {
      "nodes": [],
      "edges": []
    },
    "graphMeta": {
      "search_total": 47,
      "expand_depth": 1
    }
  },
  "meta": {
    "status": 200,
    "currentRecords": 10,
    "cachedAt": "..."
  }
}
```

**Behavior:**

1. Elasticsearch / Scout finds relevant `contents` (and optionally other searchable entities).
2. `GraphService::expand()` runs for top-N result IDs (deduplicated subgraph merge).
3. ACL filters apply to both search hits and expanded nodes.

### Stats — `GET .../graph/stats/cms`

**Query:** `metrics[]=isolated_contents&metrics[]=orphan_tags&metrics[]=hub_tags`

**Metrics (MVP):**

| Metric key | Definition |
|------------|------------|
| `isolated_contents` | Contents with ≤1 graph edge (excluding `parent_of`) |
| `orphan_tags` | Tags linked to exactly one content |
| `hub_tags` | Top tags by content count (default top 20) |
| `hub_categories` | Top categories by content count |
| `avg_degree_by_entity` | Average edge count per entity type |
| `summary` | Total nodes/edges counts by entity |

---

## Reuse from Core CRUD (mandatory)

Follow the same patterns as `ContentsController` → `CrudController` and existing CMS relation
lookup (`GetContentsByRelationAction` → `list()`).

| Core building block | Graph usage |
|---------------------|-------------|
| `CrudController` | `GraphController extends CrudController` — inherit `handleServiceCall()`, `buildResponse()`, error mapping, optional `Cache::tryByRequest()` |
| `CrudResult` + `CrudMeta` | `GraphService` returns `CrudResult`; map `node_count` → `CrudMeta.currentRecords`, `cachedAt` as today |
| `ResponseBuilder` | No custom JSON builder; graph payload in `CrudResult.data` |
| `CrudRequest` hierarchy | `ExpandGraphRequest extends DetailRequest`, `SearchGraphRequest extends SearchRequest`, `StatsGraphRequest extends CrudRequest` |
| `*RequestData` (`DetailRequestData`, `SearchRequestData`) | `ExpandGraphRequestData extends DetailRequestData`, `SearchGraphRequestData extends SearchRequestData` — add graph params (`depth`, `expand_depth`, …) |
| `DynamicEntity::resolve()` | Resolve center node model from `{module}` + `{entity}` + `{id}` via existing `CrudRequest::parsed()` |
| `AuthorizationService` | `ensurePermission($request, $table, 'select', $connection)` per entity type; `applyAclFiltersToQuery()` when loading nodes (same as `CrudService::detail`) |
| `QueryBuilder` | Optional: load node display fields with same column/relation rules as `detail` when `columns[]` passed |
| `SearchRequest` / `SearchRequestData` | Search mode inherits `qs`, filters, pagination conventions; add `expand_depth` |
| Scout / `Content::search()` | ES entry points for search mode (same searchable pipeline as CRUD search stub) |
| Eloquent relations on CMS models | `PivotGraphEdgeRepository` traverses `Content::tags()`, `categories()`, `related()`, etc. — **not** ad-hoc SQL on pivot table names |
| `Cache::tryByRequest()` / `Cache::clearByEntity()` | Read caching for expand; invalidation on Content/Tag/Category writes (existing observers) |

**Reference implementation already in CMS:**

```php
// ContentsController: Action prepares payload → delegates to CrudController::list()
final class ContentsController extends CrudController { ... }

// GraphController: GraphService returns CrudResult → delegates to inherited response pipeline
final class GraphController extends CrudController { ... }
```

**Do not duplicate:**

- Permission/ACL logic (use `AuthorizationService` only).
- HTTP error handling (use inherited `handleServiceCall()`).
- Entity resolution (use `DynamicEntity` / `CrudRequest` parsing).
- Response envelope (use `ResponseBuilder` via `CrudResult`).

**Not in Core module code:** graph domain logic stays in `Modules/CMS` because only CMS needs it
today. Core primitives above are reused, not extended with CMS-specific graph routes.

---

## Architecture

```
Modules/CMS/
  app/
    Graph/
      GraphService.php              expand(), searchWithGraph(), stats() → CrudResult
      GraphNodeResolver.php         builds nodes from resolved Eloquent models (toArray/translation)
      GraphEdgeRepositoryInterface.php
      PivotGraphEdgeRepository.php  Phase 1–3: Eloquent relations + pivot models
      MaterializedGraphEdgeRepository.php  Phase 4 (optional)
      DTOs/                           internal only; serialized into CrudResult.data arrays
        GraphSubgraph.php
        GraphNode.php
        GraphEdge.php
    Http/
      Controllers/GraphController.php   extends CrudController
      Requests/
        ExpandGraphRequest.php          extends DetailRequest
        SearchGraphRequest.php          extends SearchRequest
        StatsGraphRequest.php           extends CrudRequest
    Casts/
        ExpandGraphRequestData.php      extends DetailRequestData
        SearchGraphRequestData.php      extends SearchRequestData
    Console/
      GraphAnalyticsCommand.php
  routes/
    api.php
    web.php
  tests/Feature/Graph/
```

**Dependencies (all existing):**

- `CrudController`, `CrudResult`, `CrudMeta`, `ResponseBuilder`
- `AuthorizationService`, `QueryBuilder` (optional node projection)
- `DynamicEntity`, `SearchRequest` / Scout on `Content`
- CMS Eloquent models, relations, `CMSTables`, factories

---

## Phased Delivery

### Phase 1 — Expand (DB only)

- `GraphEdgeRepository` reads pivots directly.
- `GET /graph/expand/{module}/{entity}/{id}` (API + web).
- Feature tests: expand from tags, contents, categories; ACL; truncation.

### Phase 2 — Search + expand (ES → DB)

- `POST /graph/search/{module}`.
- Reuses Phase 1 `expand()` after ES entry points.
- Feature tests with Scout/ES fake or mocked engine.

### Phase 3 — Analytics

- `GraphService::stats()`.
- `GET /graph/stats/{module}` + `cms:graph-analytics` command.
- Feature + command tests.

### Phase 4 — Materialized edges (performance, not new API)

Trigger: expand or stats exceed agreed latency budget (e.g. >2s on production dataset).

- Migration `cms_graph_edges` (`source_entity`, `source_id`, `target_entity`, `target_id`,
  `edge_type`, `weight`, timestamps).
- Observers on Content, Tag, Category (and pivots) keep table in sync.
- `php artisan cms:graph-rebuild-edges` for backfill.
- Swap `GraphEdgeRepository` binding to `MaterializedGraphEdgeRepository`.
- **Same routes and JSON contracts** — transparent to consumers.

---

## Authorization & Caching

- Every node returned must pass `select` permission on its underlying table/connection (same
  pattern as `CrudService::list` / `detail`).
- Nodes the user cannot read are **omitted** (not returned as stubs).
- Optional cache key: `cms:graph:expand:{entity}:{id}:{depth}:{filters_hash}` TTL 5–15 minutes.
- Invalidate on Content/Tag/Category save/delete via existing observers (or dedicated graph cache
  tags).

---

## Testing Strategy

- Pest feature tests under `Modules/CMS/tests/Feature/Graph/`.
- Use factories (`ContentFactory`, `TagFactory`, `CategoryFactory`, …) for graph setup.
- Run: `php artisan test --compact Modules/CMS/tests/Feature/Graph/`
- Format: `vendor/bin/pint --dirty` after each task.

---

## Success Criteria

- [ ] Expand returns valid `{ nodes, edges }` for all five entity types at depth 1–2.
- [ ] Frontend can load graph incrementally (multiple expand calls, stable IDs).
- [ ] Search returns ES-ranked results plus merged subgraph when `expand_depth >= 1`.
- [ ] Stats/command report isolated contents and orphan tags on seeded data.
- [ ] ACL hides unauthorized nodes in expand and search responses.
- [ ] Phase 4 can be added without breaking API contracts.
