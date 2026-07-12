# Core Graph Framework - CRUD-Aligned Graph Operations

## Status

- Date: 2026-07-12
- Status: in progress
- Scope: Core framework, with CMS as the first consumer/provider
- Supersedes: the previous CMS-only graph layer direction

## Context

Laraplate already exposes a generic CRUD paradigm through Core. CRUD can resolve module entities dynamically, apply authorization, validate requests, build queries, serialize records, and allow relations only when requested.

Graph should follow the same architectural direction instead of becoming a CMS-specific feature. The goal is to make Graph a Core capability available to every CRUD entity, then let modules provide optional graph metadata and restrictions when the generic behavior is not enough.

CMS remains the first real implementation target, but the design must not depend on CMS-specific models, pivot tables, or content assumptions.

## Goals

- Provide a generic Core Graph framework available wherever CRUD is available.
- Reuse CRUD request semantics, authorization, query, model resolution, and response patterns as much as possible.
- Start with `expand`, modeled as an extension of CRUD `detail`.
- Traverse only explicitly requested relations, except for provider-defined defaults.
- Support cross-module graph traversal when relations are requested or provider defaults require it.
- Make module providers optional refinements, not prerequisites for graph availability.
- Define provider contracts in Phase 1 so later regulation and optimization work has a stable extension point.
- Keep all later roadmap phases mandatory, even if they are implemented after the MVP.

## Non-Goals For Phase 1

- Graph search is not part of Phase 1.
- Graph statistics and analytics are not part of Phase 1.
- Materialized graph edges are not part of Phase 1.
- Provider-driven business rules beyond default relations, summary fields, edge labels, and excluded relations are not part of Phase 1.
- Automatic full relationship discovery is not allowed in Phase 1.

## Roadmap

The order is intentional. Later phases are not optional; they are deferred only to keep the first implementation small and coherent.

1. Core Graph Expand
2. Core Graph Search + Expand
3. Core Graph Stats and Analytics
4. Provider Rules and Regulation Layer
5. Materialized Edges and Performance Layer

## Implementation Status

- Phase 1 is implemented in Core: generic expand routes, request/data classes, provider registry, CMS provider, relation traversal, ACL omission, cross-module traversal, MorphTo support, dedupe, cycles, and truncation metadata.
- Phase 2 is implemented for the Graph layer: graph search routes, `SearchGraphRequest extends SearchRequest`, `SearchGraphRequestData extends SearchRequestData`, `qs` kept as the search query parameter, CRUD search reused through `CrudService::search()`, and optional graph expansion through the same traversal pipeline.
- Phase 3 MVP is implemented: stats are computed over an authorized graph expansion and do not introduce global graph scans or materialized edges.
- Phase 4 MVP is implemented: optional provider rules can further restrict generic Graph behavior without making providers required.
- Phase 5 MVP is implemented: Core has an optional materialized edge store and repository, while public graph routes still use runtime traversal as the correctness baseline.

## CRUD Alignment Principles

Graph must preserve the CRUD mental model:

- `expand` behaves like a richer `detail`.
- Request classes should extend CRUD request classes when possible.
- `ExpandGraphRequest` should extend `DetailRequest`.
- Existing CRUD parameter names should be reused when they express the same concept.
- `relations[]` keeps the same parameter name used by CRUD, but under `/graph/expand` it means graph traversal paths.
- Authorization should use the same `AuthorizationService` policy as CRUD.
- Entity resolution should use `DynamicEntity`.
- Query constraints should use existing Core query infrastructure when applicable.
- Serialization should reuse CRUD response/data patterns where possible.

Graph can add parameters that CRUD does not need, but should not create a parallel request language for concepts CRUD already has.

## Phase 1 API

### Routes

API route:

```http
GET /api/v1/crud/graph/expand/{module}/{entity}/{id}
```

Web route:

```http
GET /app/crud/graph/expand/{module}/{entity}/{id}
```

The route namespace intentionally stays under `crud` because Graph is an extension of the CRUD paradigm, not a separate module-specific API surface.

### Request Parameters

`ExpandGraphRequest` extends `DetailRequest` and adds Graph-specific validation.

Supported parameters:

- `relations[]`: relation paths to traverse.
- `depth`: maximum traversal depth.
- `limit`: maximum total nodes in the response, including the center node.
- `relation_limit`: maximum target nodes per relation step.
- `node_detail`: node serialization level.

`node_detail` values:

- `minimal`
- `summary`
- `full`

Default value:

```text
node_detail=summary
```

### Relation Path Semantics

Relations are explicit graph traversal paths:

```http
relations[]=tags
relations[]=tags.contents
relations[]=categories.children
```

Rules:

- If `relations[]` is present, Graph traverses only those paths.
- If `relations[]` is absent, Graph asks the provider for default relations.
- If no provider defaults exist, Graph returns only the center node and no edges.
- Relations are never auto-loaded by model discovery alone.
- Cross-module relations are allowed when explicitly requested or provider-defined as defaults.

### Depth Semantics

Depth is validated against relation paths:

- If `depth` is omitted, Core derives it from the deepest requested/default relation path.
- If `depth` is provided, every relation path depth must be less than or equal to `depth`.
- If any relation path exceeds `depth`, the request fails with `422`.
- Core must enforce a configurable maximum depth.

Examples:

```http
relations[]=tags.contents&depth=2
```

Valid.

```http
relations[]=tags.contents&depth=1
```

Invalid, returns `422`.

### Limits And Truncation

`limit` limits the total node count for the graph response, including the center node.

`relation_limit` limits the number of target nodes loaded for each relation step.

If limits truncate the graph, the response remains successful and includes graph metadata:

```json
{
  "graphMeta": {
    "truncated": true,
    "truncatedBy": ["limit", "relation_limit"]
  }
}
```

Graph must not silently pretend a truncated graph is complete.

## Node Detail Levels

### Minimal

Minimal nodes contain only identity and routing fields:

```json
{
  "id": "cms:contents:10",
  "module": "cms",
  "entity": "contents",
  "key": 10,
  "label": "Example content",
  "slug": "example-content",
  "path": "/example-content"
}
```

### Summary

Summary nodes include minimal fields plus provider/model summary fields.

Core fallback summary fields should include common readable columns when present:

- `title`
- `name`
- `label`
- `slug`
- `path`
- `status`
- `type`
- `code`
- `created_at`
- `updated_at`

Only existing fields that are readable for the current entity should be included.

### Full

Full nodes use a detail-like serialization and are expected to be heavier. This level is explicit only and should not be the default.

## Clicking Neighbor Nodes

The expand response contains enough data to render discovered neighbor nodes according to `node_detail`.

When a user clicks a neighbor and needs that node's own neighbors, the client calls `expand` again using that neighbor as the center node.

Graph should not recursively preload unspecified future interactions.

## Response Shape

The response should stay compatible with Laraplate API conventions while exposing graph-specific data.

Example:

```json
{
  "data": {
    "center": "cms:contents:10",
    "nodes": [
      {
        "id": "cms:contents:10",
        "module": "cms",
        "entity": "contents",
        "key": 10,
        "label": "Example content"
      },
      {
        "id": "cms:tags:3",
        "module": "cms",
        "entity": "tags",
        "key": 3,
        "label": "News"
      }
    ],
    "edges": [
      {
        "id": "cms:contents:10|tags|cms:tags:3",
        "source": "cms:contents:10",
        "target": "cms:tags:3",
        "relation": "tags",
        "type": "tagged_as",
        "directed": true
      }
    ],
    "graphMeta": {
      "depth": 1,
      "requestedRelations": ["tags"],
      "defaultRelationsApplied": false,
      "truncated": false,
      "filteredByAcl": false,
      "hasCycles": false,
      "deduplicatedNodeCount": 0
    }
  }
}
```

Exact wrapping can reuse existing CRUD result/meta structures, but graph-specific metadata must remain explicit.

## Node And Edge Identity

Public node IDs use this format:

```text
{module}:{entity}:{id}
```

Examples:

```text
cms:contents:10
core:users:1
erp:customers:42
```

Edge IDs must be deterministic. They should be derived from:

- source node ID
- target node ID
- relation path or relation name
- edge type

Long edge IDs are acceptable. If needed, Core may hash the deterministic source string.

## Deduplication And Cycles

Phase 1 must include node deduplication and cycle guards.

Rules:

- The same node must not be duplicated in `nodes`.
- A relation may still add another edge to an already discovered node.
- A node already seen in the current branch must not be expanded again in a way that creates an infinite cycle.
- `graphMeta.hasCycles` should be set when a cycle is detected.
- `graphMeta.deduplicatedNodeCount` should report deduplication when practical.

## Authorization And ACL

Graph uses CRUD authorization semantics.

Center node:

- Uses `AuthorizationService::ensurePermission()`.
- If the user cannot view the center node, the request fails like CRUD detail.

Neighbor nodes:

- Must pass permission checks and applicable ACL filtering.
- Unauthorized neighbor nodes are omitted.
- Edges connected only to omitted nodes are also omitted.
- No placeholder nodes are returned for unauthorized records.
- `graphMeta.filteredByAcl` is set when ACL filtering removes graph data.

Provider implementations must not own user-specific security in Phase 1. User-specific visibility remains a Core authorization concern.

## Relation Validation

Invalid graph relation requests return `422`.

A relation path is invalid when:

- the relation method does not exist on the current model;
- the relation exists but Core cannot safely introspect or traverse it;
- the provider explicitly excludes it;
- the path depth exceeds the validated depth;
- the relation target cannot be resolved as a CRUD entity;
- Core cannot apply the required authorization/ACL semantics to the target entity.

## Traversable Relation Types

Core Graph should support any Eloquent relation type that it can control safely.

A relation is graph-traversable only when Core can:

- instantiate the relation through the model relation method;
- identify the target model or target models;
- apply `relation_limit` for multi-record relations;
- apply target authorization and ACL filtering;
- serialize the target as a graph node;
- deduplicate nodes;
- guard against cycles.

If any requirement cannot be satisfied, the relation must return `422` for Phase 1.

### Morph Relations

Morph relations are supported in Phase 1 only if Graph can preserve the same visibility semantics used by CRUD.

If a morph relation cannot be resolved to authorized CRUD entities with predictable ACL behavior, it is outside the Phase 1 implementation and must return `422`.

Full morph support remains a required roadmap item, not an optional enhancement.

## Provider Contract

Providers are optional. Graph remains available without a provider.

Providers refine generic behavior by supplying defaults and static graph metadata.

Planned namespace:

```text
Modules\Core\Graph\Contracts
```

Core provider interface:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

interface GraphProviderInterface
{
    /**
     * @return list<string>
     */
    public function defaultRelations(string $module, string $entity): array;

    /**
     * @return list<string>
     */
    public function summaryFields(string $module, string $entity): array;

    public function edgeType(string $module, string $entity, string $relation): ?string;

    /**
     * @return list<string>
     */
    public function excludedRelations(string $module, string $entity): array;
}
```

Provider registry:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

interface GraphProviderRegistryInterface
{
    public function register(GraphProviderInterface $provider, string $module, ?string $entity = null): void;

    public function providerFor(string $module, string $entity): ?GraphProviderInterface;
}
```

Resolution order:

1. Entity provider for `{module}/{entity}`.
2. Module provider for `{module}`.
3. Core defaults.

Registration happens in module service providers:

```php
$this->app
    ->make(GraphProviderRegistryInterface::class)
    ->register($this->app->make(CmsGraphProvider::class), 'cms');
```

Entity-specific registration:

```php
$this->app
    ->make(GraphProviderRegistryInterface::class)
    ->register($this->app->make(CmsContentGraphProvider::class), 'cms', 'contents');
```

## Cross-Module Traversal

Cross-module traversal is allowed when:

- the relation is explicitly requested; or
- the selected provider returns it as a default relation.

The target node must use its real module/entity identity and its own CRUD authorization rules.

Example:

```json
{
  "id": "erp:customers:42",
  "module": "erp",
  "entity": "customers",
  "key": 42
}
```

## Phase 1 Architecture

Expected Core components:

- `ExpandGraphRequest`
- `GraphController`
- `GraphService`
- `GraphTraversal`
- `GraphNodeSerializer`
- `GraphRelationInspector`
- `GraphProviderInterface`
- `GraphProviderRegistryInterface`
- `GraphProviderRegistry`
- Graph DTOs for nodes, edges, and metadata

The exact class layout may follow existing Core CRUD conventions during implementation, but responsibilities should remain separated:

- controller: request/response orchestration;
- request: validation and normalized parameters;
- service: authorization and high-level operation flow;
- traversal: relation walking, limits, deduplication, cycles;
- serializer: node detail levels;
- provider registry: default metadata and static exclusions.

## CMS As First Consumer

CMS should implement a provider only where generic Core defaults are not enough.

Initial CMS provider responsibilities may include:

- default content graph relations;
- CMS-specific summary fields;
- CMS-specific edge type labels;
- static exclusions for relations that should not be graph-traversable.

CMS must not own the generic graph traversal engine.

## Phase 2: Search + Expand

Graph search should come after expand is stable.

Direction:

- Reuse CRUD list/search semantics as much as possible.
- Search should return graph-compatible nodes.
- Search results can be expanded through the same Phase 1 expand pipeline.
- Request classes should extend CRUD list/search request classes where coherent.
- Any additional graph parameters must be additive, not a replacement for CRUD query semantics.

### Graph Search Routes

API route:

```http
GET /api/v1/crud/graph/search/{module}/{entity}
```

Web route:

```http
GET /app/crud/graph/search/{module}/{entity}
```

`SearchGraphRequest` extends `SearchRequest`. It keeps all CRUD search semantics, including `qs`, `mode`, pagination, filters, and sorting. Graph-specific parameters are additive:

- `relations[]`: optional graph relation paths to expand from each search result.
- `depth`: maximum graph traversal depth for requested relation paths.
- `relation_limit`: maximum target nodes per relation step.
- `node_detail`: node serialization level.

The `limit` parameter keeps its CRUD search meaning: maximum search results. It is not remapped to a graph node limit on search routes.

If `relations[]` is absent, Graph Search returns graph-compatible nodes for search results and no edges. If `relations[]` is present, each search result becomes a seed for the same traversal pipeline used by `expand`; nodes and edges are deduplicated across seed expansions.

Graph Search responses use `center: null` because the graph has multiple search-result seeds rather than a single detail center. The response includes both `graphMeta` and `searchMeta`.

Example response shape:

```json
{
  "data": {
    "center": null,
    "nodes": [],
    "edges": [],
    "graphMeta": {
      "depth": 1,
      "requestedRelations": ["roles"],
      "defaultRelationsApplied": false,
      "truncated": false,
      "truncatedBy": [],
      "filteredByAcl": false,
      "hasCycles": false,
      "deduplicatedNodeCount": 0
    },
    "searchMeta": {
      "resultCount": 2
    }
  }
}
```

### Core Search Route And Advanced Search

The generic CRUD search route is shared infrastructure, not CMS-specific and not AI-specific.

`SearchRequest` must expose a `mode` enum:

- `auto`
- `basic`
- `orchestrated`

Mode semantics:

- `basic` uses Laravel Scout through the configured search driver.
- `orchestrated` uses the advanced retrieval pipeline when the model resolves to a supported Core search engine.
- `auto` chooses `orchestrated` when the model resolves to a supported Core search engine; otherwise it falls back to `basic`.

The Core module must not depend on the AI module. AI may override Core contracts such as query intent parsing, planning, embedding, and reranking through service-provider bindings.

### Existing Search Engine Layer

Advanced search must be engine-aware and support both Elasticsearch and Typesense as first-class drivers through the existing Core search engine layer.

Core must not create a parallel advanced adapter layer. Instead:

- Laravel Scout resolves the model's configured engine through the existing `EngineManager` and `searchableUsing()` flow;
- existing Core engines implementing `ISearchEngine` own driver-specific query construction;
- Elasticsearch-specific request bodies stay in `ElasticsearchEngine`;
- Typesense-specific search parameters stay in `TypesenseEngine`;
- the ensemble/fusion layer composes Scout builders and normalizes results without knowing driver-specific response shapes.

Supported initial drivers:

- `elasticsearch`
- `typesense`

Unsupported engines, including database search, fall back to `basic` only when `mode=auto`; they must fail explicitly when `mode=orchestrated`.

### Advanced Search Pagination

Pagination behavior must be aligned for Elasticsearch and Typesense.

The first advanced implementation should support page-based pagination for both drivers using a common request model:

- `page`
- `per_page`
- `offset`
- `limit`

The advanced search result must include:

- normalized result IDs;
- normalized per-result scores;
- `total`;
- `page`;
- `per_page`;
- `total_pages`;
- raw engine metadata for diagnostics.

Core must not paginate, filter, or sort advanced-search results after the engine returns them except for Eloquent hydration by returned IDs. Any filtering that changes the result set must be pushed into the search engine before pagination.

### Advanced Search Filters

The first advanced implementation supports the same portable filter subset for both Elasticsearch and Typesense:

- `=`
- `in`
- `!=`
- nested `AND` groups

Unsupported filters must be rejected before executing search:

- `OR`
- `like`
- `not like`
- range operators
- relation-path filters
- engine-specific filter syntax in public request input

The rejection is intentional because applying unsupported filters after engine pagination breaks pagination semantics.

### Searchable Schema Fields

Full-text search fields must be read from the model searchable schema when available.

The existing search engines should derive vector fields from `getVectorField()`, `getSearchMapping()`, or equivalent searchable schema metadata. Text/searchable fields should remain owned by the Scout driver and model mapping, with a conservative fallback only when schema metadata is unavailable.

Fallback fields:

- `title`
- `name`
- `body`
- `content`
- `description`

Vector fields should also come from schema metadata. The default vector field is `embedding` only when no schema field can be inferred.

### Advanced Search Error Policy

`orchestrated` must fail explicitly when the advanced pipeline is unavailable or the search engine errors.

`auto` may fall back to `basic` only when the model resolves to an unsupported engine. It should not silently hide runtime search engine failures once advanced search has been selected.

## Phase 3: Stats And Analytics

Stats should be built after search and expand semantics are stable.

Direction:

- Count nodes and edges by entity, relation, module, and provider metadata.
- Respect the same authorization and ACL rules.
- Avoid leaking counts for entities the user cannot view.
- Define clear behavior for truncated or sampled stats.

### Phase 3 MVP: Expand Stats

The first stats implementation is intentionally scoped to a single authorized expand operation.

API route:

```http
GET /api/v1/crud/graph/stats/{module}/{entity}/{id}
```

Web route:

```http
GET /app/crud/graph/stats/{module}/{entity}/{id}
```

The request reuses `ExpandGraphRequest` semantics. Stats are computed from the same graph data that `expand` would return for the same user and parameters. This preserves:

- center-node authorization;
- related-node ACL omission;
- provider default relations;
- explicit `relations[]`;
- depth, node, and relation limits;
- truncation and cycle metadata.

Stats response shape:

```json
{
  "data": {
    "center": "cms:contents:10",
    "stats": {
      "totalNodes": 3,
      "totalEdges": 2,
      "nodesByModule": {
        "cms": 3
      },
      "nodesByEntity": {
        "cms:contents": 1,
        "cms:tags": 2
      },
      "edgesByRelation": {
        "tags": 2
      },
      "edgesByType": {
        "tagged_as": 2
      }
    },
    "graphMeta": {
      "truncated": false,
      "filteredByAcl": false
    }
  }
}
```

Phase 3 MVP does not provide global graph statistics for all records of an entity. Global, sampled, cached, or materialized statistics belong to the provider rules and materialized edge phases.

## Phase 4: Provider Rules And Regulation

The provider layer should later become the place for module-specific graph policy.

Examples:

- max depth per entity;
- max relation limit per relation;
- allowed or denied relation paths;
- display labels and grouping;
- relation direction hints;
- edge weighting;
- business-specific graph constraints.

This phase is mandatory, but it should build on the small Phase 1 provider contract instead of blocking the MVP.

### Phase 4 MVP: Optional Provider Rules

Core keeps Graph generic. Every CRUD-resolvable entity remains graph-capable unless normal CRUD resolution or authorization fails.

Provider rules are optional refinements. A module can implement `GraphProviderRulesInterface` in addition to `GraphProviderInterface` to restrict the generic behavior. If a provider does not implement rules, Core uses the existing generic limits from `config/graph.php`.

Initial rules:

- `allowedRelationPaths(string $module, string $entity): array`
  - Empty list means no provider allow-list is enforced.
  - Non-empty list means requested relation paths for that entity must match exactly one allowed path.
- `maxDepth(string $module, string $entity): ?int`
  - `null` means use request/config depth only.
  - A value lower than requested depth rejects the request before traversal.
- `maxRelationLimit(string $module, string $entity, string $relation): ?int`
  - `null` means use request/config relation limit.
  - A value lower than requested `relation_limit` rejects traversal of that relation.

Rule failures return validation errors (`422`) because the request is syntactically valid but violates module/entity graph policy.

Provider exclusions from Phase 1 remain supported through `excludedRelations()`. Exclusions deny a relation segment on the source entity even if the user explicitly requested it. The rules interface adds stricter policy without replacing the existing exclusion behavior.

Implemented behavior:

- `GraphProviderRulesInterface` is optional and independent from base graph provider metadata.
- `GraphProviderRuleEnforcer` applies request-level allow-list/depth/first-relation-limit checks before expand or search traversal.
- `GraphTraversal` applies per-source `maxRelationLimit()` while walking relations, so nested traversal uses the provider for the current source entity.
- Providers that do not implement `GraphProviderRulesInterface` keep the generic Core behavior unchanged.

## Phase 5: Materialized Edges And Performance

Materialized edges become relevant when runtime traversal is too expensive.

Direction:

- Keep runtime traversal as the correctness baseline.
- Add materialized edge storage only where measurable performance requires it.
- Preserve the same public response contract.
- Ensure invalidation rules are explicit per module/entity/relation.

### Phase 5 MVP: Optional Edge Store

The first performance layer adds an optional materialized edge store without changing `expand`, `search`, or `stats` behavior.

The runtime traversal remains the correctness baseline. Materialized edges are written and read through a dedicated Core service, but public Graph routes continue using traversal until a later step can prove freshness, invalidation, authorization, and provider-rule behavior are equivalent.

Storage table:

```text
core_graph_edges
```

Stored fields:

- source module, entity, key, and node id;
- target module, entity, key, and node id;
- relation path and current relation segment;
- edge type;
- directed flag;
- metadata JSON;
- `stale_at` timestamp for invalidation without destructive deletes;
- timestamps.

Initial service behavior:

- upsert one or more edges by stable edge key;
- query non-stale outgoing edges by source node id;
- mark edges stale by source node id;
- preserve runtime traversal as fallback when no valid materialized edges exist.

This phase intentionally does not add automatic synchronization hooks on model save/delete. Module-specific invalidation hooks belong to later provider/performance work once the first store is stable.

Implemented behavior:

- `core_graph_edges` stores stable source/target graph identity, relation path, edge type, metadata, stale marker, and timestamps.
- `Modules\Core\Models\GraphEdge` represents materialized edges.
- `MaterializedGraphEdgeRepository` can upsert edges, query non-stale outgoing edges by source node id, and mark source edges stale.
- Public graph endpoints are unchanged and still use runtime traversal.

## Testing Strategy

Phase 1 tests should cover:

- route availability for API and web paths;
- `ExpandGraphRequest` validation;
- relation path parsing;
- depth validation;
- provider default relation behavior;
- no-provider center-node-only behavior;
- invalid relation `422`;
- provider-excluded relation `422`;
- authorization failure on center node;
- ACL omission of neighbor nodes;
- cross-module traversal;
- `limit` truncation;
- `relation_limit` truncation;
- node detail levels;
- deduplication;
- cycle guard behavior;
- deterministic node and edge IDs.

If any existing tests assert that CRUD request classes are `final`, they must be updated when a request needs to become extensible for Graph.

## Success Criteria

Phase 1 is successful when:

- any CRUD entity can be expanded as a graph center node;
- requested relation paths return nodes and edges with stable IDs;
- no requested relations means provider defaults or center-node-only output;
- CRUD authorization behavior is preserved;
- unauthorized neighbor nodes are omitted without placeholders;
- graph metadata reports truncation, ACL filtering, and cycle/deduplication state;
- CMS can provide graph metadata without owning Core traversal logic;
- the design does not prevent later search, stats, provider regulation, or materialized edge phases.
