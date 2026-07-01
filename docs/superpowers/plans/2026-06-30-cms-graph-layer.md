# CMS Graph Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose CMS relationships as a graph API (expand, search+expand, stats) reusing Core CRUD infrastructure (`CrudController`, `CrudRequest`, `CrudResult`, `AuthorizationService`, `DynamicEntity`, Scout).

**Architecture:** `GraphController extends CrudController` (same pattern as `ContentsController`). `GraphService` returns `CrudResult` consumed by inherited `handleServiceCall()` / `ResponseBuilder`. Expand traverses existing Eloquent relations on CMS models. Search extends `SearchRequest` and uses `Content::search()` then reuses `expand()`. Stats uses aggregate pivot/relation queries. Phase 4 swaps edge repository only.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Core CRUD stack, Scout/ES on `Content`, nwidart modules.

**Source spec:** `docs/superpowers/specs/2026-06-30-cms-graph-layer-design.md`

**Conventions:**
- Run: `php artisan test --compact Modules/CMS/tests/Feature/Graph/<File>.php`
- Format: `vendor/bin/pint --dirty`
- Node IDs: plural entity + id (`contents:101`)
- Reference: `Modules/CMS/app/Http/Controllers/ContentsController.php` (Action/Service → CrudController)

---

## Reuse checklist (apply on every task)

- [ ] Controller extends `CrudController`, calls `$this->handleServiceCall(fn () => ..., $request, $model)`
- [ ] Form requests extend `CrudRequest` hierarchy (`DetailRequest`, `SearchRequest`)
- [ ] RequestData extends existing Core `*RequestData` classes
- [ ] Service returns `CrudResult` + `CrudMeta`, never raw `JsonResponse`
- [ ] Entity resolved via `CrudRequest::parsed()->model` / `DynamicEntity::resolve()`
- [ ] ACL via `AuthorizationService::ensurePermission` + `applyAclFiltersToQuery`
- [ ] Edge traversal via Eloquent relations (`Content::tags()`, …), not raw pivot SQL
- [ ] Response envelope matches `/select` and `/detail` (`data` + `meta` from `ResponseBuilder`)

---

## File map

| File | Responsibility |
|------|----------------|
| `Modules/CMS/app/Graph/GraphService.php` | expand/search/stats → `CrudResult` |
| `Modules/CMS/app/Graph/PivotGraphEdgeRepository.php` | Edges via Eloquent relations |
| `Modules/CMS/app/Graph/GraphNodeResolver.php` | Node labels from resolved models |
| `Modules/CMS/app/Graph/DTOs/*.php` | Internal subgraph structures |
| `Modules/CMS/app/Http/Controllers/GraphController.php` | `extends CrudController` |
| `Modules/CMS/app/Http/Requests/ExpandGraphRequest.php` | `extends DetailRequest` |
| `Modules/CMS/app/Http/Requests/SearchGraphRequest.php` | `extends SearchRequest` |
| `Modules/CMS/app/Http/Requests/StatsGraphRequest.php` | `extends CrudRequest` |
| `Modules/CMS/app/Casts/ExpandGraphRequestData.php` | `extends DetailRequestData` |
| `Modules/CMS/app/Casts/SearchGraphRequestData.php` | `extends SearchRequestData` |
| `Modules/CMS/app/Console/GraphAnalyticsCommand.php` | Ops analytics |
| `Modules/CMS/routes/api.php` | API graph routes |
| `Modules/CMS/routes/web.php` | Web graph routes |

---

## Task 1: Request layer (CRUD-aligned)

**Files:**
- Create: `Modules/CMS/app/Casts/ExpandGraphRequestData.php`
- Create: `Modules/CMS/app/Http/Requests/ExpandGraphRequest.php`
- Test: `Modules/CMS/tests/Feature/Graph/ExpandGraphRequestTest.php`

- [ ] **Step 1: Write failing test** — POST-less GET to expand resolves `parsed()->model` as `Tag` when route is `cms/tags/{id}`.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Implement `ExpandGraphRequest extends DetailRequest`**

```php
final class ExpandGraphRequest extends DetailRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'depth' => ['sometimes', 'integer', 'min:1', 'max:2'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', 'in:contents,tags,categories,contributors,locations'],
            'edge_types' => ['sometimes', 'array'],
            'edge_types.*' => ['string', 'in:tagged_with,in_category,related_to,authored_by,located_at,parent_of'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);
    }

    public function parsed(): ExpandGraphRequestData
    {
        return new ExpandGraphRequestData(
            $this,
            $this->resolveMainEntity(),
            $this->validated(),
            $this->getPrimaryKey(),
            $this->input('module'),
        );
    }
}
```

`ExpandGraphRequestData extends DetailRequestData` adds readonly `depth`, `types`, `edgeTypes`, `limit` with defaults.

Route `{id}` merged into request like `DetailRequest::prepareForValidation()`.

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: `vendor/bin/pint --dirty`**

---

## Task 2: PivotGraphEdgeRepository (Eloquent relations)

**Files:**
- Create: `Modules/CMS/app/Graph/GraphEdgeRepositoryInterface.php`
- Create: `Modules/CMS/app/Graph/PivotGraphEdgeRepository.php`
- Test: `Modules/CMS/tests/Feature/Graph/PivotGraphEdgeRepositoryTest.php`

- [ ] **Step 1: Write failing test** — content with tag via factories; repository returns `tagged_with` edge using `Content::tags()` relation, not raw `cms_taggables` query.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Implement repository**

Map entity → model → relation methods already on CMS models:

| Entity | Neighbor relations to traverse |
|--------|-------------------------------|
| `contents` | `tags`, `categories`, `contributors`, `locations`, `related` |
| `tags` | `contents` |
| `categories` | contents via categorizables + `parent`/`children` for `parent_of` |
| `contributors` | `contents` (inverse contributables) |
| `locations` | `contents` (inverse locatables) |

Use `CMSTables` pivot models only where inverse relations are missing.

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: `vendor/bin/pint --dirty`**

---

## Task 3: GraphService expand + ACL (returns CrudResult)

**Files:**
- Create: `Modules/CMS/app/Graph/GraphService.php`
- Create: `Modules/CMS/app/Graph/GraphNodeResolver.php`
- Create: `Modules/CMS/app/Graph/DTOs/GraphNode.php`, `GraphEdge.php`, `GraphSubgraph.php`
- Test: `Modules/CMS/tests/Feature/Graph/ExpandGraphTest.php`
- Test: `Modules/CMS/tests/Feature/Graph/GraphAclTest.php`

- [ ] **Step 1: Write failing service test**

Inject real `AuthorizationService`. Expand from tag → expect `CrudResult` with `data.nodes`,
`data.edges`, `data.graphMeta.center`.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Implement `GraphService::expand(ExpandGraphRequestData $data): CrudResult`**

```php
// Pattern mirrors CrudService::detail()
$permission_name = $this->auth->ensurePermission(
    $data->request,
    $data->model->getTable(),
    'select',
    $data->model->getConnectionName(),
);
$query = $data->model->newQuery()->whereKey($data->id);
$this->auth->applyAclFiltersToQuery($query, $permission_name);
$center = $query->firstOrFail();
// BFS expand via repository, ACL-filter each discovered node query
return new CrudResult(
    data: $subgraph->toArray(), // nodes, edges, graphMeta
    meta: new CrudMeta(
        currentRecords: count($subgraph->nodes),
        cachedAt: Date::now(),
        table: $data->model->getTable(),
        class: $data->model::class,
    ),
);
```

`GraphNodeResolver` builds label/slug/path from model `toArray()` / translation (same fields as
`Content::toSearchableArray()` subset).

- [ ] **Step 4: ACL test** — user without `select` on contents omits content nodes from subgraph.

- [ ] **Step 5: Run tests — expect PASS**

- [ ] **Step 6: `vendor/bin/pint --dirty`**

---

## Task 4: GraphController + expand routes (Phase 1)

**Files:**
- Create: `Modules/CMS/app/Http/Controllers/GraphController.php`
- Modify: `Modules/CMS/routes/api.php`, `Modules/CMS/routes/web.php`
- Test: `Modules/CMS/tests/Feature/Graph/ExpandGraphEndpointTest.php`

- [ ] **Step 1: Write failing HTTP test**

Assert standard CRUD response shape: top-level `data` + `meta.status`, not custom envelope.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Implement controller**

```php
final class GraphController extends CrudController
{
    public function __construct(
        private readonly GraphService $graphService,
        CrudService $crudService,
    ) {
        parent::__construct($crudService);
    }

    public function expand(ExpandGraphRequest $request): Response
    {
        $request_data = $request->parsed();

        return $this->handleServiceCall(
            fn (): CrudResult => $this->graphService->expand($request_data),
            $request,
            $request_data->model,
        );
    }
}
```

Register routes (api + web `crud/graph` prefix) with same constraints as spec.

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: `vendor/bin/pint --dirty`**

**Phase 1 done:** expand via CRUD response pipeline.

---

## Task 5: Search + expand (Phase 2, extends SearchRequest)

**Files:**
- Create: `Modules/CMS/app/Casts/SearchGraphRequestData.php`
- Create: `Modules/CMS/app/Http/Requests/SearchGraphRequest.php`
- Modify: `GraphService.php`, `GraphController.php`, routes
- Test: `Modules/CMS/tests/Feature/Graph/SearchGraphTest.php`

- [ ] **Step 1: Write failing test** — `SearchGraphRequest` accepts `qs` (inherited) + `expand_depth`; `Content::search('Laravel')` hit expanded with tag neighbor.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Implement `SearchGraphRequest extends SearchRequest`**

Add rules: `expand_depth` (0–2), optional `entity` restrict. Map `q` → `qs` in `prepareForValidation()` if frontend sends `q`.

- [ ] **Step 4: Implement `GraphService::searchWithGraph(SearchGraphRequestData): CrudResult`**

1. Reuse `AuthorizationService` + `Content` model from parsed request (default entity `contents`).
2. `Content::search($data->qs)->take($limit)` (Scout — same index as CRUD search stub).
3. If `expand_depth > 0`, call `expand()` for each hit ID (reuse Task 3).
4. Return `CrudResult` with `data.results`, `data.graph`, `data.graphMeta`.

- [ ] **Step 5: Add `GraphController::search()` + POST routes**

- [ ] **Step 6: Run tests — expect PASS**

- [ ] **Step 7: `vendor/bin/pint --dirty`**

---

## Task 6: Stats + command (Phase 3)

**Files:**
- Create: `Modules/CMS/app/Http/Requests/StatsGraphRequest.php`
- Create: `Modules/CMS/app/Console/GraphAnalyticsCommand.php`
- Modify: `GraphService.php`, `GraphController.php`, `CMSServiceProvider.php`, routes
- Test: `Modules/CMS/tests/Feature/Graph/GraphStatsTest.php`
- Test: `Modules/CMS/tests/Feature/Graph/GraphAnalyticsCommandTest.php`

- [ ] **Step 1: Write failing stats test** — orphan tag + isolated content detected.

- [ ] **Step 2: Implement `GraphService::stats()`** — aggregate via Eloquent/pivot counts; return `CrudResult`.

- [ ] **Step 3: `GraphController::stats()`** via `handleServiceCall()`.

- [ ] **Step 4: `cms:graph-analytics` command** calls same `GraphService::stats()` (no HTTP duplication).

- [ ] **Step 5: Run tests — expect PASS**

- [ ] **Step 6: `vendor/bin/pint --dirty`**

---

## Task 7 (deferred): Materialized edges — Phase 4

Swap `PivotGraphEdgeRepository` → `MaterializedGraphEdgeRepository` via service provider binding.
Same `GraphService`, controller, routes, tests — no API changes.

---

## Spec coverage

| Spec | Task |
|------|------|
| CRUD reuse | All tasks |
| Expand DB | 1–4 |
| Search ES→expand | 5 |
| Stats + command | 6 |
| CMS-only routes | 4–6 |
| Phase 4 materialized | 7 |

---

## Execution handoff

Plan saved to `docs/superpowers/plans/2026-06-30-cms-graph-layer.md`.

**1. Subagent-Driven** — one task per subagent.
**2. Inline Execution** — task-by-task in session.

Which approach?
