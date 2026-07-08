# Core Graph Framework - CRUD-Aligned Graph Expansion

## Status

- Date: 2026-07-08
- Status: draft
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

## Phase 3: Stats And Analytics

Stats should be built after search and expand semantics are stable.

Direction:

- Count nodes and edges by entity, relation, module, and provider metadata.
- Respect the same authorization and ACL rules.
- Avoid leaking counts for entities the user cannot view.
- Define clear behavior for truncated or sampled stats.

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

## Phase 5: Materialized Edges And Performance

Materialized edges become relevant when runtime traversal is too expensive.

Direction:

- Keep runtime traversal as the correctness baseline.
- Add materialized edge storage only where measurable performance requires it.
- Preserve the same public response contract.
- Ensure invalidation rules are explicit per module/entity/relation.

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
