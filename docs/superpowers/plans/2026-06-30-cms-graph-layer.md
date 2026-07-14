# Core Graph Framework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Core Graph Framework in ordered phases: CRUD-aligned expand, search + expand, stats, provider regulation, and materialized performance support.

**Architecture:** Graph lives in `Modules/Core` and reuses the CRUD model. `ExpandGraphRequest` extends `DetailRequest`, `SearchGraphRequest` extends `SearchRequest`, entity resolution uses `DynamicEntity`, authorization uses `AuthorizationService`, and responses use `ResponseBuilder`. Providers are optional Core contracts registered by modules; CMS is only the first provider/consumer.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Eloquent relations, Core CRUD stack, nwidart modules.

---

## Source Spec

- `docs/superpowers/specs/2026-06-30-cms-graph-layer-design.md`

## Execution Scope

This plan is the living implementation plan for the Core Graph framework. It must be updated whenever Graph architecture or implementation changes.

The roadmap phases remain mandatory:

1. Core Graph Expand
2. Core Graph Search + Expand
3. Core Graph Stats and Analytics
4. Provider Rules and Regulation Layer
5. Materialized Edges and Performance Layer

## Current Status

- [x] Phase 1: Core Graph Expand implemented in `Modules/Core`.
- [x] Phase 1: CMS provider implemented as first consumer.
- [x] Phase 1 hardening: ACL omission, dedupe, cycles, truncation metadata, MorphTo traversal, and cross-module traversal covered.
- [x] Phase 2: Core Graph Search + Expand implemented.
- [x] Phase 2 hardening: search error propagation, expanded search aggregation, cross-result node dedupe, and graph/search metadata covered.
- [x] Phase 3 MVP: Core Graph Stats and Analytics over authorized expand output.
- [x] Phase 4 MVP: Provider Rules and Regulation Layer.
- [ ] Phase 5: Materialized Edges and Performance Layer, deferred until measured performance need and invalidation strategy exist.
- [x] Point 0 documentation checkpoint: stable Core/CMS README and module RAG docs describe the implemented generic Graph behavior and the Phase 5 gate.

## Recent Graph Commits

- `bb92ee0 feat(core): add graph search endpoint`
- `9d2608a test(core): cover graph search expansion aggregation`
- `f8db53a feat(core): add graph stats endpoint`
- `fea0e7d feat(core): add graph provider rules`

## Current Code Facts

- `Modules/Core/app/Http/Requests/DetailRequest.php` already extends `SelectRequest`.
- `DetailRequest::parsed()` returns `DetailRequestData`.
- `SelectRequestData` already stores `relations`, but Graph needs path semantics under `/graph/expand`.
- `CrudRequest::prepareForValidation()` resolves the model via `DynamicEntity::resolve()`.
- `AuthorizationService` exposes `ensurePermission()`, `checkPermission()`, `buildPermissionName()`, and `applyAclFiltersToQuery()`.
- `CrudController::buildResponse()` and `CrudController::handleServiceCall()` are private. Phase 1 does not need to open them because `GraphController` can use `ResponseBuilder` directly.
- API routes currently live under `/api/v1`; web CRUD routes live under `/app/crud`.

## File Map

| File | Responsibility |
| --- | --- |
| `Modules/Core/config/graph.php` | Core graph defaults: max depth, max nodes, relation limit, node detail |
| `Modules/Core/app/Graph/Contracts/GraphProviderInterface.php` | Optional module/entity provider contract |
| `Modules/Core/app/Graph/Contracts/GraphProviderRegistryInterface.php` | Provider registry contract |
| `Modules/Core/app/Graph/GraphProviderRegistry.php` | Entity provider wins over module provider |
| `Modules/Core/app/Casts/ExpandGraphRequestData.php` | Parsed graph expand request, extending `DetailRequestData` |
| `Modules/Core/app/Casts/SearchGraphRequestData.php` | Parsed graph search request, extending `SearchRequestData` |
| `Modules/Core/app/Http/Requests/ExpandGraphRequest.php` | CRUD-aligned graph request extending `DetailRequest` |
| `Modules/Core/app/Http/Requests/SearchGraphRequest.php` | CRUD-aligned graph request extending `SearchRequest` |
| `Modules/Core/app/Graph/DTOs/GraphNode.php` | Public graph node DTO |
| `Modules/Core/app/Graph/DTOs/GraphEdge.php` | Public graph edge DTO |
| `Modules/Core/app/Graph/DTOs/GraphMeta.php` | Graph metadata DTO |
| `Modules/Core/app/Graph/DTOs/GraphData.php` | Response data DTO |
| `Modules/Core/app/Graph/GraphEntityResolver.php` | Convert models/classes into graph module/entity IDs |
| `Modules/Core/app/Graph/GraphNodeSerializer.php` | Serialize `minimal`, `summary`, and `full` nodes |
| `Modules/Core/app/Graph/GraphRelationInspector.php` | Validate and instantiate traversable Eloquent relations |
| `Modules/Core/app/Graph/GraphTraversal.php` | Walk relation paths, apply limits, dedupe, cycles, ACL omission |
| `Modules/Core/app/Graph/GraphService.php` | Load center node and orchestrate graph expansion |
| `Modules/Core/app/Http/Controllers/GraphController.php` | HTTP endpoint using `ResponseBuilder` |
| `Modules/Core/routes/graph.php` | Shared graph route body |
| `Modules/Core/routes/api.php` | API `/crud/graph/...` mount |
| `Modules/Core/routes/web.php` | Web `/app/crud/graph/...` mount |
| `Modules/Core/app/Providers/CoreServiceProvider.php` | Bind registry interface and implementation |
| `Modules/CMS/app/Graph/CmsGraphProvider.php` | First module provider with default relations and summary fields |
| `Modules/CMS/app/Providers/CMSServiceProvider.php` | Register CMS graph provider |
| `Modules/Core/tests/Feature/Graph/*.php` | Core Graph feature tests |
| `Modules/CMS/tests/Feature/Graph/*.php` | CMS provider integration tests |

## Phase 3 Stats Plan

Phase 3 MVP computes stats from the same graph data that `expand` would return for the same request and user. This keeps authorization, ACL filtering, provider defaults, requested relations, depth, truncation, and cycle semantics identical to expand.

### Phase 3 File Map

| File | Responsibility |
| --- | --- |
| `Modules/Core/app/Graph/DTOs/GraphStats.php` | Count totals and distributions for nodes and edges |
| `Modules/Core/app/Graph/GraphStatsCalculator.php` | Build `GraphStats` from expanded graph arrays |
| `Modules/Core/app/Graph/GraphService.php` | Add `stats(ExpandGraphRequestData $requestData)` by reusing expand |
| `Modules/Core/app/Http/Controllers/GraphController.php` | Add `stats(ExpandGraphRequest $request)` endpoint |
| `Modules/Core/routes/graph.php` | Add `/graph/stats/{module}/{entity}/{id}` route |
| `Modules/Core/tests/Feature/Graph/GraphStatsTest.php` | Stats calculator and service tests |
| `Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php` | Stats route registration tests |

### Phase 3 Task 1: Stats DTO And Calculator

- [x] Write a failing test that builds stats from a graph array with multiple modules, entities, relations, and edge types.
- [x] Add `GraphStats` with `totalNodes`, `totalEdges`, `nodesByModule`, `nodesByEntity`, `edgesByRelation`, and `edgesByType`.
- [x] Add `GraphStatsCalculator::fromGraph(array $graph): GraphStats`.
- [x] Run `rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphStatsTest.php`.
- [x] Commit stats DTO/calculator/test files as part of `f8db53a`.

### Phase 3 Task 2: Stats Service And Route

- [x] Write a failing service test proving `GraphService::stats()` reuses expand output and returns `center`, `stats`, and `graphMeta`.
- [x] Write failing API/web route registration tests for `/crud/graph/stats/{module}/{entity}/{id}`.
- [x] Inject `GraphStatsCalculator` into `GraphService`.
- [x] Add `GraphController::stats(ExpandGraphRequest $request)`.
- [x] Add stats route to `Modules/Core/routes/graph.php`.
- [x] Run `rtk php artisan test --compact Modules/Core/tests/Feature/Graph Modules/CMS/tests/Feature/Graph`.
- [x] Run `rtk vendor/bin/pint --dirty`.
- [x] Commit Phase 3 stats files as `f8db53a`.

## Phase 4 Provider Rules Plan

Phase 4 MVP keeps Graph generic and adds optional provider-side restrictions. Providers are not required for Graph availability; a provider can opt into rules by implementing `GraphProviderRulesInterface`.

### Phase 4 File Map

| File | Responsibility |
| --- | --- |
| `Modules/Core/app/Graph/Contracts/GraphProviderRulesInterface.php` | Optional graph policy contract |
| `Modules/Core/app/Graph/GraphProviderRuleEnforcer.php` | Applies provider allow-list, max depth, and relation-limit rules |
| `Modules/Core/app/Graph/GraphService.php` | Applies request-level provider rules before traversal/search expansion |
| `Modules/Core/app/Graph/GraphTraversal.php` | Applies per-source relation-limit provider rules while walking relations |
| `Modules/Core/tests/Feature/Graph/GraphProviderRulesTest.php` | Provider rule behavior tests |
| `Modules/CMS/app/Graph/CmsGraphProvider.php` | Can opt into rules later, but Phase 4 MVP does not require CMS rules |

### Phase 4 Task 1: Optional Rules Contract And Enforcer

- [x] Write failing tests proving provider rules reject disallowed relation paths and excessive depth.
- [x] Add `GraphProviderRulesInterface` with `allowedRelationPaths()`, `maxDepth()`, and `maxRelationLimit()`.
- [x] Add `GraphProviderRuleEnforcer::assertRequestAllowed()`.
- [x] Run `rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphProviderRulesTest.php`.
- [x] Commit rules contract/enforcer/tests as part of `fea0e7d`.

### Phase 4 Task 2: Relation Limit Enforcement During Traversal

- [x] Write failing test proving a provider can reject a requested `relation_limit` for a specific source entity relation.
- [x] Inject `GraphProviderRuleEnforcer` into `GraphTraversal`.
- [x] Apply `maxRelationLimit()` before loading relation targets.
- [x] Run `rtk php artisan test --compact Modules/Core/tests/Feature/Graph`.
- [x] Run `rtk vendor/bin/pint --dirty`.
- [x] Commit Phase 4 MVP files as `fea0e7d`.


## Point 0 Documentation Checkpoint

Point 0 is the stabilization handoff after the runtime MVP phases. It does not add graph behavior. It makes the implemented contract discoverable in stable module documentation before any further implementation work.

- [x] Document Core Graph as a generic Core capability in `Modules/Core/docs/GRAPH_SYSTEM.md`.
- [x] Link Graph from `Modules/Core/README.md`.
- [x] Update Core RAG documentation with developer and API/operator semantics for graph expand/search/stats.
- [x] Update CMS README and RAG documentation to describe CMS as a provider/consumer, not the owner of graph traversal.
- [x] Keep Phase 5 open and explicitly blocked by real benchmark evidence plus invalidation/freshness design.
- [x] Add a CMS opt-in runtime benchmark harness for realistic content graph datasets without adding it to the normal test suite.

Next implementation work must start from measured runtime traversal behavior on real module data. Do not introduce graph edge storage, migrations, or cache persistence until the benchmark and invalidation strategy are accepted.

## Phase 5 Materialized Edges Plan

Phase 5 is intentionally deferred. Runtime traversal remains the correctness baseline for `expand`, `search`, and `stats`.

Do not introduce a materialized edge migration or model until a concrete performance problem is measured and the invalidation/freshness strategy is specified. The next Phase 5 plan must first answer:

- which module/entity/relation paths are expensive enough to materialize;
- which model events or module workflows create, update, or invalidate edges;
- how provider rules and ACL filtering remain equivalent to runtime traversal;
- how stale materialized data is detected and bypassed;
- which benchmark proves the materialized path is needed.

### Phase 5 Future Task 0: Runtime Benchmark Harness

- [x] Add `Modules/CMS/tests/Benchmark/CmsGraphRuntimeBenchmarkTest.php` as an opt-in benchmark outside the normal module test suites.
- [x] Gate execution behind `CMS_GRAPH_BENCHMARK_ENABLED=true`.
- [x] Make dataset size and iterations configurable through documented `CMS_GRAPH_BENCHMARK_*` environment variables.
- [x] Report duration, SQL query count, peak memory, node count, edge count, truncation, and ACL filtering per scenario.

The harness creates evidence; it does not by itself justify materialized storage. Future Task 1 still must be completed with benchmark results from real module workflows before any Phase 5 implementation.

### Phase 5 Future Task 1: Performance Evidence And Invalidation Design

- [ ] Capture benchmark evidence for at least one graph workflow where runtime traversal is too expensive.
- [ ] Define the exact relation paths to materialize.
- [ ] Define invalidation rules per module/entity/relation.
- [ ] Update this plan with concrete file paths only after the invalidation strategy is accepted.

### Phase 5 Future Task 2: Edge Store Implementation

- [ ] Add storage only after Future Task 1 is complete.
- [ ] Keep runtime traversal as fallback.
- [ ] Prove response equivalence with tests comparing runtime traversal and materialized reads.
- [ ] Prove stale edges are never served when invalidation cannot guarantee freshness.

## Shared Test Commands

Run focused tests after each task:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph
```

Run CMS provider tests after CMS tasks:

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Graph
```

Run formatter after code edits:

```bash
rtk vendor/bin/pint --dirty
```

## Task 1: Provider Contracts And Registry

**Files:**
- Create: `Modules/Core/app/Graph/Contracts/GraphProviderInterface.php`
- Create: `Modules/Core/app/Graph/Contracts/GraphProviderRegistryInterface.php`
- Create: `Modules/Core/app/Graph/GraphProviderRegistry.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphProviderRegistryTest.php`

- [x] **Step 1: Write the failing registry test**

Create `Modules/Core/tests/Feature/Graph/GraphProviderRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class);

final class RegistryTestProvider implements GraphProviderInterface
{
    public function __construct(private readonly string $name) {}

    public function defaultRelations(string $module, string $entity): array
    {
        return [$this->name];
    }

    public function summaryFields(string $module, string $entity): array
    {
        return ['name'];
    }

    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return $this->name . ':' . $relation;
    }

    public function excludedRelations(string $module, string $entity): array
    {
        return [];
    }
}

it('resolves entity providers before module providers', function (): void {
    $registry = new GraphProviderRegistry();

    $registry->register(new RegistryTestProvider('module'), 'cms');
    $registry->register(new RegistryTestProvider('entity'), 'cms', 'contents');

    expect($registry->providerFor('cms', 'contents')?->defaultRelations('cms', 'contents'))->toBe(['entity']);
    expect($registry->providerFor('cms', 'tags')?->defaultRelations('cms', 'tags'))->toBe(['module']);
    expect($registry->providerFor('erp', 'customers'))->toBeNull();
});

it('binds the registry contract in the container', function (): void {
    expect(app(GraphProviderRegistryInterface::class))->toBeInstanceOf(GraphProviderRegistry::class);
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphProviderRegistryTest.php
```

Expected: FAIL because the graph contracts and registry do not exist.

- [x] **Step 3: Create provider contracts**

Create `Modules/Core/app/Graph/Contracts/GraphProviderInterface.php`:

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

Create `Modules/Core/app/Graph/Contracts/GraphProviderRegistryInterface.php`:

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

- [x] **Step 4: Implement the registry**

Create `Modules/Core/app/Graph/GraphProviderRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Support\Str;
use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Override;

final class GraphProviderRegistry implements GraphProviderRegistryInterface
{
    /**
     * @var array<string, GraphProviderInterface>
     */
    private array $providers = [];

    #[Override]
    public function register(GraphProviderInterface $provider, string $module, ?string $entity = null): void
    {
        $this->providers[$this->key($module, $entity)] = $provider;
    }

    #[Override]
    public function providerFor(string $module, string $entity): ?GraphProviderInterface
    {
        return $this->providers[$this->key($module, $entity)]
            ?? $this->providers[$this->key($module, null)]
            ?? null;
    }

    private function key(string $module, ?string $entity): string
    {
        $module = Str::lower($module);
        $entity = $entity === null ? '*' : Str::lower($entity);

        return $module . ':' . $entity;
    }
}
```

- [x] **Step 5: Bind the registry in Core**

Modify `Modules/Core/app/Providers/CoreServiceProvider.php`:

```php
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\GraphProviderRegistry;
```

Inside `register()` after existing singleton registrations:

```php
$this->app->singleton(GraphProviderRegistryInterface::class, GraphProviderRegistry::class);
```

- [x] **Step 6: Run the test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphProviderRegistryTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
rtk git add Modules/Core/app/Graph/Contracts/GraphProviderInterface.php Modules/Core/app/Graph/Contracts/GraphProviderRegistryInterface.php Modules/Core/app/Graph/GraphProviderRegistry.php Modules/Core/app/Providers/CoreServiceProvider.php Modules/Core/tests/Feature/Graph/GraphProviderRegistryTest.php
rtk git commit -m "feat(core): add graph provider registry"
```

## Task 2: Graph Config And Expand Request

**Files:**
- Create: `Modules/Core/config/graph.php`
- Create: `Modules/Core/app/Casts/ExpandGraphRequestData.php`
- Create: `Modules/Core/app/Http/Requests/ExpandGraphRequest.php`
- Test: `Modules/Core/tests/Feature/Graph/ExpandGraphRequestTest.php`

- [x] **Step 1: Write the failing request test**

Create `Modules/Core/tests/Feature/Graph/ExpandGraphRequestTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class);

beforeEach(function (): void {
    Route::get('/test/graph/{module}/{entity}/{id}', static function (ExpandGraphRequest $request): array {
        $parsed = $request->parsed();

        return [
            'entity' => $parsed->mainEntity,
            'module' => $parsed->module,
            'recordKey' => $parsed->recordKey,
            'relations' => $parsed->graphRelations,
            'depth' => $parsed->depth,
            'limit' => $parsed->limit,
            'relationLimit' => $parsed->relationLimit,
            'nodeDetail' => $parsed->nodeDetail,
        ];
    })->middleware('web');
});

it('parses graph expand parameters from a crud detail style request', function (): void {
    $response = $this->getJson('/test/graph/Core/users/123?relations[]=roles.permissions&depth=2&limit=30&relation_limit=5&node_detail=minimal');

    $response->assertOk()
        ->assertJsonPath('entity', 'users')
        ->assertJsonPath('module', 'Core')
        ->assertJsonPath('recordKey', '123')
        ->assertJsonPath('relations.0', 'roles.permissions')
        ->assertJsonPath('depth', 2)
        ->assertJsonPath('limit', 30)
        ->assertJsonPath('relationLimit', 5)
        ->assertJsonPath('nodeDetail', 'minimal');
});

it('rejects relation paths deeper than depth', function (): void {
    $this->getJson('/test/graph/Core/users/123?relations[]=roles.permissions&depth=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['relations']);
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/ExpandGraphRequestTest.php
```

Expected: FAIL because the request classes do not exist.

- [x] **Step 3: Add graph config**

Create `Modules/Core/config/graph.php`:

```php
<?php

declare(strict_types=1);

return [
    'max_depth' => 3,
    'default_limit' => 100,
    'max_limit' => 200,
    'default_relation_limit' => 25,
    'max_relation_limit' => 100,
    'default_node_detail' => 'summary',
];
```

- [x] **Step 4: Implement request data**

Create `Modules/Core/app/Casts/ExpandGraphRequestData.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\ExpandGraphRequest;

final class ExpandGraphRequestData extends DetailRequestData
{
    /**
     * @var list<string>
     */
    public readonly array $graphRelations;

    public readonly int|string $recordKey;

    public readonly int $depth;

    public readonly int $limit;

    public readonly int $relationLimit;

    public readonly string $nodeDetail;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(ExpandGraphRequest $request, string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey, $module);

        $key = is_array($primaryKey) ? head($primaryKey) : $primaryKey;

        $this->recordKey = $validated[$key] ?? $request->route('id');
        $this->graphRelations = array_values($validated['relations'] ?? []);
        $this->depth = (int) ($validated['depth'] ?? $this->deriveDepth($this->graphRelations));
        $this->limit = (int) ($validated['limit'] ?? config('graph.default_limit', 100));
        $this->relationLimit = (int) ($validated['relation_limit'] ?? config('graph.default_relation_limit', 25));
        $this->nodeDetail = (string) ($validated['node_detail'] ?? config('graph.default_node_detail', 'summary'));
    }

    /**
     * @param  list<string>  $relations
     */
    private function deriveDepth(array $relations): int
    {
        if ($relations === []) {
            return 1;
        }

        return max(array_map(static fn (string $relation): int => substr_count($relation, '.') + 1, $relations));
    }
}
```

- [x] **Step 5: Implement expand request**

Create `Modules/Core/app/Http/Requests/ExpandGraphRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Validation\Validator;
use Modules\Core\Casts\ExpandGraphRequestData;
use Override;

final class ExpandGraphRequest extends DetailRequest
{
    #[Override]
    public function rules(): array
    {
        $max_depth = (int) config('graph.max_depth', 3);
        $max_limit = (int) config('graph.max_limit', 200);
        $max_relation_limit = (int) config('graph.max_relation_limit', 100);

        return parent::rules() + [
            'relations' => ['sometimes', 'array'],
            'relations.*' => ['string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/'],
            'depth' => ['sometimes', 'integer', 'min:1', 'max:' . $max_depth],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:' . $max_limit],
            'relation_limit' => ['sometimes', 'integer', 'min:1', 'max:' . $max_relation_limit],
            'node_detail' => ['sometimes', 'string', 'in:minimal,summary,full'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $depth = (int) $this->input('depth', 0);

                if ($depth === 0) {
                    return;
                }

                foreach ($this->input('relations', []) as $relation) {
                    if (is_string($relation) && substr_count($relation, '.') + 1 > $depth) {
                        $validator->errors()->add('relations', 'Relation paths cannot be deeper than depth.');
                    }
                }
            },
        ];
    }

    #[Override]
    public function parsed(): ExpandGraphRequestData
    {
        return new ExpandGraphRequestData($this, $this->resolveMainEntity(), $this->validated(), $this->primaryKey, $this->input('module'));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $key = is_array($this->primaryKey) ? head($this->primaryKey) : $this->primaryKey;
        $id = $this->route('id');

        $relations = $this->normalizeRelations($this->input('relations', []));

        $this->merge([
            $key => $id ?? $this->input($key),
            'filters' => [
                ['property' => $key, 'value' => $id ?? $this->input($key)],
            ],
            'relations' => $relations,
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizeRelations(mixed $relations): array
    {
        if (is_string($relations)) {
            $relations = is_json($relations) ? json_decode($relations, true) : preg_split('/,\s?/', $relations);
        }

        if (! is_array($relations)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $relation): ?string {
            if (is_string($relation)) {
                return $relation;
            }

            if (is_array($relation) && isset($relation['name']) && is_string($relation['name'])) {
                return $relation['name'];
            }

            return null;
        }, $relations)));
    }
}
```

- [x] **Step 6: Run the request test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/ExpandGraphRequestTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
rtk git add Modules/Core/config/graph.php Modules/Core/app/Casts/ExpandGraphRequestData.php Modules/Core/app/Http/Requests/ExpandGraphRequest.php Modules/Core/tests/Feature/Graph/ExpandGraphRequestTest.php
rtk git commit -m "feat(core): add graph expand request"
```

## Task 3: Graph DTOs, Identity, And Serialization

**Files:**
- Create: `Modules/Core/app/Graph/DTOs/GraphNode.php`
- Create: `Modules/Core/app/Graph/DTOs/GraphEdge.php`
- Create: `Modules/Core/app/Graph/DTOs/GraphMeta.php`
- Create: `Modules/Core/app/Graph/DTOs/GraphData.php`
- Create: `Modules/Core/app/Graph/GraphEntityResolver.php`
- Create: `Modules/Core/app/Graph/GraphNodeSerializer.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphNodeSerializerTest.php`

- [x] **Step 1: Write the failing serializer test**

Create `Modules/Core/tests/Feature/Graph/GraphNodeSerializerTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Models\User;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class);

it('builds stable graph node ids from module entity and model key', function (): void {
    $user = new User();
    $user->forceFill(['id' => 7, 'name' => 'Graph User', 'email' => 'graph@example.test']);
    $user->exists = true;

    $serializer = new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry());

    $node = $serializer->serialize($user, 'summary');

    expect($node->id)->toBe('core:users:7');
    expect($node->module)->toBe('core');
    expect($node->entity)->toBe('users');
    expect($node->key)->toBe(7);
    expect($node->label)->toBe('Graph User');
    expect($node->attributes)->toHaveKey('name', 'Graph User');
    expect($node->attributes)->not->toHaveKey('password');
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphNodeSerializerTest.php
```

Expected: FAIL because graph DTOs and serializer do not exist.

- [x] **Step 3: Create graph DTOs**

Create `Modules/Core/app/Graph/DTOs/GraphNode.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphNode
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $module,
        public string $entity,
        public int|string $key,
        public ?string $label,
        public array $attributes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'entity' => $this->entity,
            'key' => $this->key,
            'label' => $this->label,
            'attributes' => $this->attributes,
        ];
    }
}
```

Create `Modules/Core/app/Graph/DTOs/GraphEdge.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphEdge
{
    public function __construct(
        public string $id,
        public string $source,
        public string $target,
        public string $relation,
        public ?string $type = null,
        public bool $directed = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'relation' => $this->relation,
            'type' => $this->type,
            'directed' => $this->directed,
        ];
    }
}
```

Create `Modules/Core/app/Graph/DTOs/GraphMeta.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphMeta
{
    /**
     * @param  list<string>  $requestedRelations
     * @param  list<string>  $truncatedBy
     */
    public function __construct(
        public int $depth,
        public array $requestedRelations,
        public bool $defaultRelationsApplied = false,
        public bool $truncated = false,
        public array $truncatedBy = [],
        public bool $filteredByAcl = false,
        public bool $hasCycles = false,
        public int $deduplicatedNodeCount = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'depth' => $this->depth,
            'requestedRelations' => $this->requestedRelations,
            'defaultRelationsApplied' => $this->defaultRelationsApplied,
            'truncated' => $this->truncated,
            'truncatedBy' => $this->truncatedBy,
            'filteredByAcl' => $this->filteredByAcl,
            'hasCycles' => $this->hasCycles,
            'deduplicatedNodeCount' => $this->deduplicatedNodeCount,
        ];
    }
}
```

Create `Modules/Core/app/Graph/DTOs/GraphData.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphData
{
    /**
     * @param  list<GraphNode>  $nodes
     * @param  list<GraphEdge>  $edges
     */
    public function __construct(
        public string $center,
        public array $nodes,
        public array $edges,
        public GraphMeta $graphMeta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'center' => $this->center,
            'nodes' => array_map(static fn (GraphNode $node): array => $node->toArray(), $this->nodes),
            'edges' => array_map(static fn (GraphEdge $edge): array => $edge->toArray(), $this->edges),
            'graphMeta' => $this->graphMeta->toArray(),
        ];
    }
}
```

- [x] **Step 4: Implement entity resolver**

Create `Modules/Core/app/Graph/GraphEntityResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class GraphEntityResolver
{
    public function moduleFor(Model|string $model): string
    {
        return Str::lower(class_module($model));
    }

    public function entityFor(Model $model): string
    {
        $table = $model->getTable();
        $module = $this->moduleFor($model);
        $prefix = $module . '_';

        if (Str::startsWith($table, $prefix)) {
            return Str::after($table, $prefix);
        }

        return $table;
    }

    public function nodeId(Model $model): string
    {
        return sprintf(
            '%s:%s:%s',
            $this->moduleFor($model),
            $this->entityFor($model),
            (string) $model->getKey(),
        );
    }
}
```

- [x] **Step 5: Implement node serializer**

Create `Modules/Core/app/Graph/GraphNodeSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphNode;

final class GraphNodeSerializer
{
    /**
     * @var list<string>
     */
    private const FALLBACK_SUMMARY_FIELDS = [
        'title',
        'name',
        'label',
        'slug',
        'path',
        'status',
        'type',
        'code',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly GraphEntityResolver $entities,
        private readonly GraphProviderRegistryInterface $providers,
    ) {}

    public function serialize(Model $model, string $detail): GraphNode
    {
        $module = $this->entities->moduleFor($model);
        $entity = $this->entities->entityFor($model);
        $attributes = $this->attributesFor($model, $module, $entity, $detail);

        return new GraphNode(
            id: $this->entities->nodeId($model),
            module: $module,
            entity: $entity,
            key: $model->getKey(),
            label: $this->labelFor($model),
            attributes: $attributes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(Model $model, string $module, string $entity, string $detail): array
    {
        if ($detail === 'minimal') {
            return $this->onlyExisting($model, ['slug', 'path']);
        }

        if ($detail === 'full') {
            return $this->safeAttributes($model->toArray());
        }

        $provider = $this->providers->providerFor($module, $entity);
        $fields = $provider?->summaryFields($module, $entity) ?: self::FALLBACK_SUMMARY_FIELDS;

        return $this->onlyExisting($model, $fields);
    }

    private function labelFor(Model $model): ?string
    {
        foreach (['title', 'name', 'label', 'slug', 'code'] as $field) {
            $value = $model->getAttribute($field);

            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return (string) $model->getKey();
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function onlyExisting(Model $model, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $model->getAttribute($field);

            if ($value !== null) {
                $values[$field] = $value;
            }
        }

        return $this->safeAttributes($values);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function safeAttributes(array $attributes): array
    {
        unset($attributes['password'], $attributes['remember_token']);

        return $attributes;
    }
}
```

- [x] **Step 6: Run the serializer test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphNodeSerializerTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
rtk git add Modules/Core/app/Graph/DTOs Modules/Core/app/Graph/GraphEntityResolver.php Modules/Core/app/Graph/GraphNodeSerializer.php Modules/Core/tests/Feature/Graph/GraphNodeSerializerTest.php
rtk git commit -m "feat(core): add graph node serialization"
```

## Task 4: Relation Inspector

**Files:**
- Create: `Modules/Core/app/Graph/DTOs/GraphRelation.php`
- Create: `Modules/Core/app/Graph/GraphRelationInspector.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphRelationInspectorTest.php`

- [x] **Step 1: Write the failing inspector test**

Create `Modules/Core/tests/Feature/Graph/GraphRelationInspectorTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphRelationInspector;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class);

final class GraphInspectorParent extends Model
{
    protected $table = 'graph_inspector_parents';

    public function children(): HasMany
    {
        return $this->hasMany(GraphInspectorChild::class, 'parent_id');
    }
}

final class GraphInspectorChild extends Model
{
    protected $table = 'graph_inspector_children';
}

it('inspects normal eloquent relations', function (): void {
    $relation = (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'children');

    expect($relation->name)->toBe('children');
    expect($relation->relatedClass)->toBe(GraphInspectorChild::class);
    expect($relation->isMultiple)->toBeTrue();
});

it('rejects missing relations with validation errors', function (): void {
    expect(fn () => (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'missing'))
        ->toThrow(ValidationException::class);
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphRelationInspectorTest.php
```

Expected: FAIL because the inspector does not exist.

- [x] **Step 3: Create relation DTO**

Create `Modules/Core/app/Graph/DTOs/GraphRelation.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

use Illuminate\Database\Eloquent\Relations\Relation;

readonly class GraphRelation
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $relatedClass
     */
    public function __construct(
        public string $name,
        public Relation $relation,
        public string $relatedClass,
        public bool $isMultiple,
    ) {}
}
```

- [x] **Step 4: Implement relation inspector**

Create `Modules/Core/app/Graph/GraphRelationInspector.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\DTOs\GraphRelation;
use ReflectionMethod;

final class GraphRelationInspector
{
    public function inspect(Model $model, string $relationName): GraphRelation
    {
        if (! method_exists($model, $relationName)) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Relation '%s' does not exist on '%s'.", $relationName, $model::class),
            ]);
        }

        $method = new ReflectionMethod($model, $relationName);

        if ($method->getNumberOfRequiredParameters() > 0) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Relation '%s' cannot be traversed because it requires parameters.", $relationName),
            ]);
        }

        $relation = $model->{$relationName}();

        if (! $relation instanceof Relation) {
            throw ValidationException::withMessages([
                'relations' => sprintf("Method '%s' is not an Eloquent relation.", $relationName),
            ]);
        }

        if ($relation instanceof MorphTo) {
            throw ValidationException::withMessages([
                'relations' => sprintf("MorphTo relation '%s' is not supported in Phase 1.", $relationName),
            ]);
        }

        $related = $relation->getRelated();

        return new GraphRelation(
            name: $relationName,
            relation: $relation,
            relatedClass: $related::class,
            isMultiple: $relation instanceof HasMany
                || $relation instanceof BelongsToMany
                || $relation instanceof MorphMany
                || $relation instanceof MorphToMany,
        );
    }
}
```

- [x] **Step 5: Run the inspector test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphRelationInspectorTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
rtk git add Modules/Core/app/Graph/DTOs/GraphRelation.php Modules/Core/app/Graph/GraphRelationInspector.php Modules/Core/tests/Feature/Graph/GraphRelationInspectorTest.php
rtk git commit -m "feat(core): add graph relation inspector"
```

## Task 5: Graph Traversal

**Files:**
- Create: `Modules/Core/app/Graph/GraphTraversal.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphTraversalTest.php`

- [x] **Step 1: Write the failing traversal test**

Create `Modules/Core/tests/Feature/Graph/GraphTraversalTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphRelationInspector;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class, RefreshDatabase::class);

final class GraphTraversalParent extends Model
{
    protected $table = 'graph_traversal_parents';
    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(GraphTraversalChild::class, 'parent_id');
    }
}

final class GraphTraversalChild extends Model
{
    protected $table = 'graph_traversal_children';
    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(GraphTraversalParent::class, 'parent_id');
    }
}

beforeEach(function (): void {
    Schema::create('graph_traversal_parents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('graph_traversal_children', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('parent_id');
        $table->string('name');
        $table->timestamps();
    });
});

it('walks requested relations with deterministic nodes and edges', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child B']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children'], 1, 10, 25, 'summary', request());

    expect($data->center)->toBe('app:graph_traversal_parents:' . $parent->getKey());
    expect($data->nodes)->toHaveCount(3);
    expect($data->edges)->toHaveCount(2);
    expect($data->graphMeta->truncated)->toBeFalse();
});

it('marks relation limit truncation', function (): void {
    $parent = GraphTraversalParent::query()->create(['name' => 'Parent']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child A']);
    GraphTraversalChild::query()->create(['parent_id' => $parent->getKey(), 'name' => 'Child B']);

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('checkPermission')->andReturnTrue();
    $auth->shouldReceive('buildPermissionName')->andReturn('default.graph_traversal_children.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->zeroOrMoreTimes();

    $traversal = new GraphTraversal(
        new GraphRelationInspector(),
        new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry()),
        new GraphEntityResolver(),
        new GraphProviderRegistry(),
        $auth,
    );

    $data = $traversal->expand($parent, ['children'], 1, 10, 1, 'summary', request());

    expect($data->nodes)->toHaveCount(2);
    expect($data->graphMeta->truncated)->toBeTrue();
    expect($data->graphMeta->truncatedBy)->toContain('relation_limit');
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphTraversalTest.php
```

Expected: FAIL because traversal does not exist.

- [x] **Step 3: Implement traversal**

Create `Modules/Core/app/Graph/GraphTraversal.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphData;
use Modules\Core\Graph\DTOs\GraphEdge;
use Modules\Core\Graph\DTOs\GraphMeta;
use Modules\Core\Graph\DTOs\GraphNode;
use Modules\Core\Services\Authorization\AuthorizationService;

final class GraphTraversal
{
    /**
     * @var array<string, GraphNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, GraphEdge>
     */
    private array $edges = [];

    private bool $truncated = false;

    /**
     * @var list<string>
     */
    private array $truncatedBy = [];

    private bool $filteredByAcl = false;

    private bool $hasCycles = false;

    private int $deduplicatedNodeCount = 0;

    public function __construct(
        private readonly GraphRelationInspector $relations,
        private readonly GraphNodeSerializer $serializer,
        private readonly GraphEntityResolver $entities,
        private readonly GraphProviderRegistryInterface $providers,
        private readonly AuthorizationService $auth,
    ) {}

    /**
     * @param  list<string>  $relationPaths
     */
    public function expand(Model $center, array $relationPaths, int $depth, int $limit, int $relationLimit, string $nodeDetail, Request $request): GraphData
    {
        $this->reset();

        $centerNode = $this->addNode($center, $nodeDetail);

        foreach ($relationPaths as $path) {
            $segments = explode('.', $path);

            if (count($segments) > $depth) {
                throw ValidationException::withMessages(['relations' => 'Relation path exceeds depth.']);
            }

            $this->walk($center, $centerNode->id, $segments, $path, $limit, $relationLimit, $nodeDetail, $request, [$centerNode->id]);
        }

        return new GraphData(
            center: $centerNode->id,
            nodes: array_values($this->nodes),
            edges: array_values($this->edges),
            graphMeta: new GraphMeta(
                depth: $depth,
                requestedRelations: $relationPaths,
                truncated: $this->truncated,
                truncatedBy: array_values(array_unique($this->truncatedBy)),
                filteredByAcl: $this->filteredByAcl,
                hasCycles: $this->hasCycles,
                deduplicatedNodeCount: $this->deduplicatedNodeCount,
            ),
        );
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $branch
     */
    private function walk(Model $source, string $sourceNodeId, array $segments, string $fullPath, int $limit, int $relationLimit, string $nodeDetail, Request $request, array $branch): void
    {
        if ($segments === [] || count($this->nodes) >= $limit) {
            return;
        }

        $relationName = array_shift($segments);
        $this->assertNotExcluded($source, $relationName);

        $relation = $this->relations->inspect($source, $relationName);
        $query = $relation->relation->getQuery();
        $related = $relation->relation->getRelated();

        if (! $this->canSeeRelated($request, $related)) {
            $this->filteredByAcl = true;

            return;
        }

        $permissionName = $this->auth->buildPermissionName($related->getTable(), 'select', $related->getConnectionName());
        $this->auth->applyAclFiltersToQuery($query, $permissionName);

        if ($relation->isMultiple) {
            $targets = $query->limit($relationLimit + 1)->get();
        } else {
            $result = $relation->relation instanceof BelongsTo || $relation->relation instanceof HasOne
                ? $relation->relation->getResults()
                : $query->first();
            $targets = new EloquentCollection($result instanceof Model ? [$result] : []);
        }

        if ($targets->count() > $relationLimit) {
            $this->markTruncated('relation_limit');
            $targets = $targets->take($relationLimit);
        }

        foreach ($targets as $target) {
            if (! $target instanceof Model) {
                continue;
            }

            if (count($this->nodes) >= $limit) {
                $this->markTruncated('limit');

                return;
            }

            $targetNode = $this->addNode($target, $nodeDetail);
            $this->addEdge($sourceNodeId, $targetNode->id, $relationName, $fullPath, $source);

            if (in_array($targetNode->id, $branch, true)) {
                $this->hasCycles = true;

                continue;
            }

            $this->walk($target, $targetNode->id, $segments, $fullPath, $limit, $relationLimit, $nodeDetail, $request, [...$branch, $targetNode->id]);
        }
    }

    private function addNode(Model $model, string $nodeDetail): GraphNode
    {
        $node = $this->serializer->serialize($model, $nodeDetail);

        if (isset($this->nodes[$node->id])) {
            $this->deduplicatedNodeCount++;

            return $this->nodes[$node->id];
        }

        $this->nodes[$node->id] = $node;

        return $node;
    }

    private function addEdge(string $source, string $target, string $relation, string $fullPath, Model $sourceModel): void
    {
        $module = $this->entities->moduleFor($sourceModel);
        $entity = $this->entities->entityFor($sourceModel);
        $type = $this->providers->providerFor($module, $entity)?->edgeType($module, $entity, $relation);
        $edgeId = hash('xxh128', $source . '|' . $fullPath . '|' . $target . '|' . (string) $type);

        $this->edges[$edgeId] = new GraphEdge($edgeId, $source, $target, $relation, $type);
    }

    private function assertNotExcluded(Model $source, string $relation): void
    {
        $module = $this->entities->moduleFor($source);
        $entity = $this->entities->entityFor($source);
        $excluded = $this->providers->providerFor($module, $entity)?->excludedRelations($module, $entity) ?? [];

        if (in_array($relation, $excluded, true)) {
            throw ValidationException::withMessages(['relations' => sprintf("Relation '%s' is excluded by provider.", $relation)]);
        }
    }

    private function canSeeRelated(Request $request, Model $related): bool
    {
        return $this->auth->checkPermission($request, $related->getTable(), 'select', $related->getConnectionName());
    }

    private function markTruncated(string $reason): void
    {
        $this->truncated = true;
        $this->truncatedBy[] = $reason;
    }

    private function reset(): void
    {
        $this->nodes = [];
        $this->edges = [];
        $this->truncated = false;
        $this->truncatedBy = [];
        $this->filteredByAcl = false;
        $this->hasCycles = false;
        $this->deduplicatedNodeCount = 0;
    }
}
```

- [x] **Step 4: Run traversal tests and verify they pass**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphTraversalTest.php
```

Expected: PASS.

- [x] **Step 5: Commit**

```bash
rtk git add Modules/Core/app/Graph/GraphTraversal.php Modules/Core/tests/Feature/Graph/GraphTraversalTest.php
rtk git commit -m "feat(core): add graph traversal"
```

## Task 6: Graph Service And Controller

**Files:**
- Create: `Modules/Core/app/Graph/GraphService.php`
- Create: `Modules/Core/app/Http/Controllers/GraphController.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphServiceTest.php`

- [x] **Step 1: Write the failing service test**

Create `Modules/Core/tests/Feature/Graph/GraphServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Graph\GraphService;
use Modules\Core\Graph\GraphTraversal;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('graph_service_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('loads the center record through detail semantics and returns a crud result', function (): void {
    DB::table('graph_service_records')->insert(['id' => 1, 'name' => 'Center', 'created_at' => now(), 'updated_at' => now()]);

    $request = ExpandGraphRequest::create('/graph/Core/graph_service_records/1', 'GET', [
        'module' => 'Core',
        'entity' => 'graph_service_records',
        'id' => 1,
    ]);
    $request->setRouteResolver(static fn () => new class {
        public function parameter(string $key): mixed
        {
            return ['module' => 'Core', 'entity' => 'graph_service_records', 'id' => 1][$key] ?? null;
        }
    });

    $data = new ExpandGraphRequestData($request, 'graph_service_records', [
        'id' => 1,
        'relations' => [],
    ], 'id', 'Core');

    $auth = Mockery::mock(AuthorizationService::class);
    $auth->shouldReceive('ensurePermission')->once()->andReturn('default.graph_service_records.select');
    $auth->shouldReceive('applyAclFiltersToQuery')->once();

    $traversal = Mockery::mock(GraphTraversal::class);
    $traversal->shouldReceive('expand')->once()->andReturnUsing(function (): \Modules\Core\Graph\DTOs\GraphData {
        return new \Modules\Core\Graph\DTOs\GraphData(
            center: 'core:graph_service_records:1',
            nodes: [],
            edges: [],
            graphMeta: new \Modules\Core\Graph\DTOs\GraphMeta(depth: 1, requestedRelations: []),
        );
    });

    $result = (new GraphService($auth, $traversal, app(\Modules\Core\Graph\Contracts\GraphProviderRegistryInterface::class)))->expand($data);

    expect($result)->toBeInstanceOf(CrudResult::class);
});
```

- [x] **Step 2: Run the test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphServiceTest.php
```

Expected: FAIL because service and controller do not exist.

- [x] **Step 3: Implement graph service**

Create `Modules/Core/app/Graph/GraphService.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;

final class GraphService
{
    public function __construct(
        private readonly AuthorizationService $auth,
        private readonly GraphTraversal $traversal,
        private readonly GraphProviderRegistryInterface $providers,
    ) {}

    public function expand(ExpandGraphRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        $permissionName = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $center = $this->findCenter($requestData, $permissionName);
        $relations = $this->relationsFor($requestData);

        $data = $this->traversal->expand(
            $center,
            $relations,
            $requestData->depth,
            $requestData->limit,
            $requestData->relationLimit,
            $requestData->nodeDetail,
            $requestData->request,
        );

        return new CrudResult(
            data: $data->toArray(),
            meta: new CrudMeta(
                class: $model::class,
                table: $model->getTable(),
                cachedAt: Date::now(),
            ),
        );
    }

    private function findCenter(ExpandGraphRequestData $requestData, string $permissionName): Model
    {
        $model = $requestData->model;
        $key = is_array($requestData->primaryKey) ? head($requestData->primaryKey) : $requestData->primaryKey;

        throw_if($requestData->recordKey === null || $requestData->recordKey === '', ModelNotFoundException::class, 'Primary key is required for graph expand.');

        $query = $model->newQuery()->where($key, $requestData->recordKey);
        $this->auth->applyAclFiltersToQuery($query, $permissionName);

        return $query->sole();
    }

    /**
     * @return list<string>
     */
    private function relationsFor(ExpandGraphRequestData $requestData): array
    {
        if ($requestData->graphRelations !== []) {
            return $requestData->graphRelations;
        }

        $module = strtolower((string) $requestData->module);
        $provider = $this->providers->providerFor($module, $requestData->mainEntity);

        return $provider?->defaultRelations($module, $requestData->mainEntity) ?? [];
    }
}
```

- [x] **Step 4: Implement graph controller**

Create `Modules/Core/app/Http/Controllers/GraphController.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphService;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GraphController extends Controller
{
    public function __construct(private readonly GraphService $graphs) {}

    public function expand(ExpandGraphRequest $request): Response
    {
        try {
            return $this->buildResponse($this->graphs->expand($request->parsed()), $request);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ModelNotFoundException $exception) {
            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_NOT_FOUND), $request);
        } catch (AuthorizationException $exception) {
            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_UNAUTHORIZED), $request);
        } catch (Throwable $exception) {
            report($exception);

            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_INTERNAL_SERVER_ERROR), $request);
        }
    }

    private function buildResponse(CrudResult $result, Request $request): Response
    {
        $builder = new ResponseBuilder($request);
        $builder->setData($result->data);

        if ($result->error !== null) {
            $builder->setError($result->error);
        }

        if ($result->statusCode !== null) {
            $builder->setStatus($result->statusCode);
        }

        return $builder->getResponse();
    }
}
```

- [x] **Step 5: Run the service test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphServiceTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
rtk git add Modules/Core/app/Graph/GraphService.php Modules/Core/app/Http/Controllers/GraphController.php Modules/Core/tests/Feature/Graph/GraphServiceTest.php
rtk git commit -m "feat(core): add graph expand service"
```

## Task 7: Routes And End-To-End Core Endpoint

**Files:**
- Create: `Modules/Core/routes/graph.php`
- Modify: `Modules/Core/routes/api.php`
- Modify: `Modules/Core/routes/web.php`
- Test: `Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php`

- [x] **Step 1: Write the failing route test**

Create `Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\ApplicationTestCase;

uses(ApplicationTestCase::class, RefreshDatabase::class);

it('registers the api graph expand route under crud', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/crud/graph/expand/Core/users/' . $user->getKey())
        ->assertStatus(401);
});

it('registers the web graph expand route under app crud', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app/crud/graph/expand/Core/users/' . $user->getKey())
        ->assertStatus(401);
});
```

The expected `401` means the route reached `GraphController` and failed through CRUD authorization because the test user has no permissions.

- [x] **Step 2: Run the route test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php
```

Expected: FAIL because routes are not registered.

- [x] **Step 3: Create shared graph route file**

Create `Modules/Core/routes/graph.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\GraphController;

Route::controller(GraphController::class)->prefix('graph')->name('graph.')->group(function (): void {
    Route::get('/expand/{module}/{entity}/{id}', 'expand')->name('expand');
});
```

- [x] **Step 4: Mount API graph routes under `/crud`**

Modify `Modules/Core/routes/api.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::name('crud.')->prefix('/crud')->group(function (): void {
    require __DIR__ . '/graph.php';
});

require __DIR__ . '/crud.php';
```

- [x] **Step 5: Mount web graph routes under `/app/crud`**

Modify `Modules/Core/routes/web.php` inside the existing `Route::name('crud.')->prefix('/crud')->group(...)` block:

```php
require __DIR__ . '/crud.php';
require __DIR__ . '/graph.php';
```

- [x] **Step 6: Run the route test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
rtk git add Modules/Core/routes/graph.php Modules/Core/routes/api.php Modules/Core/routes/web.php Modules/Core/tests/Feature/Graph/GraphExpandRouteTest.php
rtk git commit -m "feat(core): expose graph expand routes"
```

## Task 8: CMS Provider Integration

**Files:**
- Create: `Modules/CMS/app/Graph/CmsGraphProvider.php`
- Modify: `Modules/CMS/app/Providers/CMSServiceProvider.php`
- Test: `Modules/CMS/tests/Feature/Graph/CmsGraphProviderTest.php`

- [x] **Step 1: Write the failing CMS provider test**

Create `Modules/CMS/tests/Feature/Graph/CmsGraphProviderTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\CMS\Graph\CmsGraphProvider;
use Modules\CMS\Tests\TestCase;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;

uses(TestCase::class);

it('registers cms graph defaults through the provider registry', function (): void {
    $provider = app(GraphProviderRegistryInterface::class)->providerFor('cms', 'contents');

    expect($provider)->toBeInstanceOf(CmsGraphProvider::class);
    expect($provider?->defaultRelations('cms', 'contents'))->toContain('tags');
    expect($provider?->summaryFields('cms', 'contents'))->toContain('title');
    expect($provider?->edgeType('cms', 'contents', 'tags'))->toBe('tagged_as');
});
```

- [x] **Step 2: Run the CMS provider test and verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Graph/CmsGraphProviderTest.php
```

Expected: FAIL because the CMS provider does not exist.

- [x] **Step 3: Implement CMS graph provider**

Create `Modules/CMS/app/Graph/CmsGraphProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\CMS\Graph;

use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Override;

final class CmsGraphProvider implements GraphProviderInterface
{
    #[Override]
    public function defaultRelations(string $module, string $entity): array
    {
        return match ($entity) {
            'contents' => ['tags', 'categories', 'contributors', 'locations'],
            'tags', 'categories', 'contributors', 'locations' => [],
            default => [],
        };
    }

    #[Override]
    public function summaryFields(string $module, string $entity): array
    {
        return match ($entity) {
            'contents' => ['title', 'slug', 'path', 'status', 'type', 'created_at', 'updated_at'],
            'tags', 'categories', 'contributors', 'locations' => ['name', 'slug', 'path', 'type'],
            default => [],
        };
    }

    #[Override]
    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return match ($relation) {
            'tags' => 'tagged_as',
            'categories' => 'categorized_as',
            'contributors' => 'contributed_by',
            'locations' => 'located_at',
            'children' => 'parent_of',
            'parent' => 'child_of',
            default => $relation,
        };
    }

    #[Override]
    public function excludedRelations(string $module, string $entity): array
    {
        return ['translations', 'history', 'modifications', 'locks', 'media'];
    }
}
```

- [x] **Step 4: Register the provider in CMS boot**

Modify `Modules/CMS/app/Providers/CMSServiceProvider.php`:

```php
use Modules\CMS\Graph\CmsGraphProvider;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
```

Inside `boot()` after `parent::boot()`:

```php
$this->app
    ->make(GraphProviderRegistryInterface::class)
    ->register($this->app->make(CmsGraphProvider::class), 'cms');
```

- [x] **Step 5: Run the CMS provider test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Graph/CmsGraphProviderTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
rtk git add Modules/CMS/app/Graph/CmsGraphProvider.php Modules/CMS/app/Providers/CMSServiceProvider.php Modules/CMS/tests/Feature/Graph/CmsGraphProviderTest.php
rtk git commit -m "feat(cms): register graph provider"
```

## Task 9: CMS Expand Smoke Test

**Files:**
- Test: `Modules/CMS/tests/Feature/Graph/CmsGraphExpandTest.php`

- [x] **Step 1: Write the CMS expand smoke test**

Create `Modules/CMS/tests/Feature/Graph/CmsGraphExpandTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Models\Content;
use Modules\CMS\Models\Tag;
use Modules\CMS\Tests\TestCase;
use Modules\Core\Models\User;

uses(TestCase::class, RefreshDatabase::class);

it('expands cms content tags when the relation is requested', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    $content = Content::factory()->create(['title' => 'Graph Content']);
    $tag = Tag::factory()->create();
    $content->tags()->attach($tag);

    $this->actingAs($user)
        ->getJson('/api/v1/crud/graph/expand/CMS/contents/' . $content->getKey() . '?relations[]=tags&node_detail=summary')
        ->assertOk()
        ->assertJsonPath('data.center', 'cms:contents:' . $content->getKey())
        ->assertJsonPath('data.graphMeta.requestedRelations.0', 'tags')
        ->assertJsonFragment([
            'id' => 'cms:tags:' . $tag->getKey(),
            'module' => 'cms',
            'entity' => 'tags',
        ]);
});

it('uses provider defaults when relations are absent', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    $content = Content::factory()->create(['title' => 'Default Graph Content']);
    $tag = Tag::factory()->create();
    $content->tags()->attach($tag);

    $this->actingAs($user)
        ->getJson('/api/v1/crud/graph/expand/CMS/contents/' . $content->getKey())
        ->assertOk()
        ->assertJsonPath('data.graphMeta.requestedRelations.0', 'tags')
        ->assertJsonFragment([
            'id' => 'cms:tags:' . $tag->getKey(),
        ]);
});
```

- [x] **Step 2: Run the smoke test and verify it passes**

Run:

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Graph/CmsGraphExpandTest.php
```

Expected: PASS.

- [x] **Step 3: Run the full graph-focused suite**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph Modules/CMS/tests/Feature/Graph
```

Expected: PASS.

- [x] **Step 4: Format dirty files**

Run:

```bash
rtk vendor/bin/pint --dirty
```

Expected: PASS with files formatted.

- [x] **Step 5: Commit**

```bash
rtk git add Modules/Core Modules/CMS
rtk git commit -m "test(cms): cover graph expand smoke path"
```

## Final Verification

- [x] **Step 1: Run graph tests**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph Modules/CMS/tests/Feature/Graph
```

Expected: PASS.

- [x] **Step 2: Run affected CRUD request tests**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Http/Requests/AuthAndSearchRequestsTest.php Modules/Core/tests/Feature/Api/CrudApiTest.php
```

Expected: PASS.

- [x] **Step 3: Format dirty files**

```bash
rtk vendor/bin/pint --dirty
```

Expected: PASS.

- [x] **Step 4: Check git diff**

```bash
rtk git diff --check
```

Expected: no output.

## Acceptance Checklist

- [x] `GET /api/v1/crud/graph/expand/{module}/{entity}/{id}` is registered.
- [x] `GET /app/crud/graph/expand/{module}/{entity}/{id}` is registered.
- [x] `ExpandGraphRequest` extends `DetailRequest`.
- [x] `relations[]` is graph traversal input under `/graph/expand`.
- [x] Missing `relations[]` uses provider defaults, or center-node-only output when no provider exists.
- [x] Depth validation rejects relation paths deeper than `depth`.
- [x] Invalid or excluded relations return `422`.
- [x] Center node authorization errors like CRUD detail.
- [x] Unauthorized neighbor nodes are omitted with `graphMeta.filteredByAcl=true`.
- [x] `limit` and `relation_limit` mark truncation in `graphMeta`.
- [x] Node IDs use `{module}:{entity}:{id}`.
- [x] Edge IDs are deterministic.
- [x] Deduplication and cycle guards are active.
- [x] CMS provider registers through `CMSServiceProvider`.
- [x] CMS does not own traversal logic.

## Follow-Up Plan Gate

After this plan is implemented and verified, create the Phase 2 plan for Core Graph Search + Expand. The Phase 2 plan must start from the Phase 1 contracts in `Modules/Core/app/Graph` and must reuse CRUD list/search request semantics instead of introducing a separate graph query language.

---

# Phase 2 Search Infrastructure Plan

**Goal:** make the Core CRUD search route support engine-aware advanced search for Elasticsearch, Typesense, and the database Scout driver through the existing Core/Scout engine layer, while keeping Core independent from the AI module.

**Scope:** Core search infrastructure used by CRUD search and later Graph Search + Expand.

**Decisions:**

- `mode=auto` chooses `orchestrated` when the model resolves to a supported Core search engine; otherwise it uses `basic`.
- `mode=basic` always uses Scout.
- `mode=orchestrated` requires a supported Core search engine and fails explicitly when unavailable.
- Elasticsearch, Typesense, and database must have equivalent public behavior for pagination, filters, schema-driven fields, and normalized results.
- Core contracts remain AI-extension points; Core must not depend on the AI module.
- Do not create a parallel advanced adapter layer; reuse the existing `EngineManager`, model `searchableUsing()`, `ISearchEngine`, `ElasticsearchEngine`, `TypesenseEngine`, and `DatabaseEngine`.

## Phase 2 File Map

| File | Responsibility |
| --- | --- |
| `Modules/Core/app/Search/DTOs/AdvancedSearchResult.php` | Normalized advanced search result and metadata |
| `Modules/Core/app/Search/Services/EnsembleSearchService.php` | Composes Scout builders, runs keyword/vector/hybrid strategies, fuses normalized results |
| `Modules/Core/app/Search/Services/AdvancedSearchService.php` | Orchestrates parser, planner, optional embedder, and ensemble |
| `Modules/Core/app/Search/Services/ScoutSearchConstraintApplier.php` | Applies the portable Scout filter and sort subset shared by basic and orchestrated search |
| `Modules/Core/app/Search/Traits/CommonEngineFunctions.php` | Shared engine helpers, including schema-driven vector field resolution |
| `Modules/Core/app/Search/Engines/ElasticsearchEngine.php` | Elasticsearch query construction, vector search, filters, and response normalization through existing engine |
| `Modules/Core/app/Search/Engines/TypesenseEngine.php` | Typesense search params, vector search, filters, and response normalization through existing engine |
| `Modules/Core/app/Search/Engines/DatabaseEngine.php` | Database Scout keyword/vector/hybrid search, model-query candidate prefiltering, driver-specific vector scoring, and normalized pagination |
| `Modules/Core/app/Services/Crud/CrudService.php` | Chooses basic/orchestrated from `SearchMode` |
| `Modules/Core/app/Providers/SearchServiceProvider.php` | Registers Core search contracts and fallback AI-extension services |

## Task 1: Normalized Advanced Result DTO

- [x] Create normalized result DTO with:
  - result IDs
  - normalized scores
  - total
  - page
  - per page
  - total pages
  - executed strategies
  - raw engine metadata

## Task 2: Searchable Schema Field Resolution In Existing Engines

- [x] Resolve vector field from `getVectorField()` when available.
- [x] Resolve vector field from `getSearchMapping()` when available.
- [x] Fall back to vector field `embedding`.
- [x] Ensure Elasticsearch and Typesense vector searches use the resolved field.
- [x] Ensure vector markers are not sent as normal filters.
- [x] Cover mapping-driven vector field resolution in tests.

## Task 3: Portable Filter Translation

- [x] Support the same filter subset for Elasticsearch and Typesense:
  - `=`
  - `in`
  - `!=`
  - nested `AND`
- [x] Reject:
  - `like`
  - `not like`
  - relation-path filters
- [x] Add tests proving unsupported filters fail before engine execution.
- [x] Apply accepted filters to Scout builders so the active engine owns pagination consistency.
- [x] Centralize portable Scout filters and sorts in one shared applier used by both `CrudService` and `EnsembleSearchService`.

## Task 3A: Advanced Portable Filters

- [x] Add the next portable filter subset for Elasticsearch, Typesense, and database:
  - `not in`
  - `>`
  - `>=`
  - `<`
  - `<=`
  - `between`
  - controlled `OR` groups over schema-declared filterable scalar fields
- [x] Enforce schema-declared filterability when searchable schema metadata exists, with backward-compatible scalar filters only when no schema metadata is available.
- [x] Preserve filterability metadata in driver translations: Typesense `facet`, Elasticsearch `meta.filterable`, and database column `filterable`.
- [x] Reject advanced filters when the active engine cannot apply them before pagination.
- [x] Keep generic Eloquent relation-path filters rejected.
- [x] Keep arbitrary relation-like filters deferred until schema aliases or denormalized searchable fields exist.
- [x] Cover equivalent translation for Elasticsearch query DSL, Typesense `filter_by`, and database Scout/Eloquent constraints.

## Task 3B: Cross-Driver Score Contract

- [x] Extend normalized hits with:
  - `score`
  - `raw_score`
  - `score_details`
- [x] Include `driver`, `strategy`, `rank`, `raw_score`, and `normalized_score` in `score_details`.
- [x] Preserve optional driver-specific score diagnostics such as `distance`, `similarity`, or text relevance metadata.
- [x] Make default scores explicit when a driver cannot provide a meaningful raw score.
- [x] Normalize scores per strategy before ensemble fusion and document that raw score values are not comparable across drivers.
- [x] Add tests for Elasticsearch, Typesense, and database normalized hit shape.

## Task 3C: Indexed Relation-Field Filters

- [x] Add schema support for relation-field filter metadata:
  - parent field option `relation` for the Eloquent relation used by database search;
  - nested `properties` entries that can declare `type` and `filterable`;
  - backward-compatible support for existing `properties` entries expressed as `FieldType` values.
- [x] Update CMS searchable schemas for indexed relation fields:
  - `contributors.id`, `contributors.slug`, `contributors.path`;
  - `categories.id`, `categories.slug`, `categories.path`;
  - `tags.id`, `tags.slug`, `tags.path`;
  - `locations.id`, `locations.slug`, `locations.city`, `locations.province`, `locations.country`, `locations.postcode`, `locations.zone`.
- [x] Accept dot-path filters only when declared by searchable schema metadata.
- [x] Reject arbitrary relation paths that are not declared in the searchable schema.
- [x] Translate positive relation filters to:
  - Elasticsearch nested queries when the field mapping is `nested`;
  - Typesense nested-field dot notation;
  - database `whereHas`.
- [x] Translate relation `!=` and `not in` as anti-exists:
  - Elasticsearch outer `must_not` nested query;
  - Typesense negative nested-field filter when supported by `filter_by`;
  - database `whereDoesntHave`.
- [x] Keep relation-path sorts rejected.
- [x] Cover equivalent behavior for Elasticsearch, Typesense, and database search tests.

## Task 4: Existing Elasticsearch Engine

- [x] Keep Elasticsearch request-body construction in `ElasticsearchEngine`.
- [x] Build vector query with the resolved vector field.
- [x] Exclude vector markers from filter clauses.
- [x] Execute keyword/vector/hybrid searches through Scout builders.
- [x] Propagate engine failures instead of converting them to empty results.

## Task 5: Existing Typesense Engine

- [x] Keep Typesense search parameter construction in `TypesenseEngine`.
- [x] Build vector query with the resolved vector field.
- [x] Exclude vector markers from `filter_by`.
- [x] Execute keyword/vector/hybrid searches through Scout builders.
- [x] Propagate engine failures instead of converting them to empty results.

## Task 6: Existing Database Engine

- [x] Register the Core database Scout engine as an orchestrated-capable search engine.
- [x] Support keyword, vector, and hybrid strategies through the same Scout builder path used by Elasticsearch and Typesense.
- [x] Return normalized Eloquent models with `_score` for vector results and default score `1.0` for keyword-only database results.
- [x] Apply keyword constraints, portable wheres, where-ins, where-not-ins, Scout callbacks, query callbacks, and soft-delete constraints to derive candidate model IDs before vector scoring.
- [x] Keep vector pagination exact by filtering constrained matches before slicing and hydrating only the requested page of models.
- [x] Isolate PostgreSQL `pgvector` and MySQL/MariaDB `JSON_TABLE` query construction behind testable query builders.
- [x] Keep SQLite vector scoring lazy over `model_embeddings` and avoid full collection map pipelines.

## Task 7: Ensemble Service Refactor

- [x] Make `EnsembleSearchService` execute per-strategy Scout builders.
- [x] Keep reciprocal-rank fusion and reranking driver-agnostic.
- [x] Keep driver-specific query construction in existing engines only.
- [x] Use a generic vector marker so field resolution remains engine-owned.
- [x] Normalize model hits from loaded attributes so keyword-only database hits do not require a synthetic `_score` attribute.

## Task 8: Search Mode Selection

- [x] `basic` uses Scout.
- [x] `orchestrated` requires a supported Core search engine and fails explicitly if unavailable.
- [x] `auto` uses `orchestrated` when the model resolves to Elasticsearch, Typesense, or database, otherwise `basic`.
- [x] Runtime engine errors in selected advanced search must not silently fall back to `basic`.

## Task 9: CRUD Search Metadata

- [x] Populate `CrudMeta::totalRecords` from advanced search total.
- [x] Populate current page, total pages, and pagination from advanced search metadata.
- [x] Hydrate Eloquent models by returned IDs without changing result membership.
- [x] Preserve engine result order after hydration.

## Task 10: Verification

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Http/Requests/AuthAndSearchRequestsTest.php Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php Modules/Core/tests/Integration/Search Modules/Core/tests/Integration/Providers/SearchServiceProviderTest.php
```

Run:

```bash
rtk vendor/bin/pint --dirty
```

Expected: all tests pass and formatting succeeds.
