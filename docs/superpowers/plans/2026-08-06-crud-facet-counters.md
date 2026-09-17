# CRUD facet counters — Implementation Plan

> **For agentic workers:** this plan is **closed**. It is recorded retrospectively for spec/plan parity; the feature shipped before the plan existed. See the delivery status below and the module documentation it names.

**Goal:** Re-home the Grid "funnel" double counter (per facet value: a `total` and a cross-filtered `count`) as a thin utility on `CrudService`, secured by the existing Crud ACL and connection affinity, without reviving the parked Grid subsystem.

**Architecture:** Two tiers over `CrudService` primitives — tier 1 (enumerable facets) as pure `count()` composition, tier 2 (open/high-cardinality facets) as a single portable `GROUP BY` on one key column with its own pagination, search, sort and two-step key/label resolution. One standalone `GET|POST /crud/facets/{module}/{entity}` action over both.

**Tech Stack:** Laravel 12, `CrudService` / `QueryBuilder`, Pest.

---

### Task 1: Tier 1 — enumerable facet counts

- [x] `CrudService::facetCounts(ListRequestData): array` — one `count()` per requested column, `total` on base scope + ACL, `count` on the request filters
- [x] `excludeFacetField` self-exclusion transform (drop nodes targeting the facet's own field so each option stays live)
- [x] `CrudServiceFacetCountsTest` — hand-computed oracle + differential vs a Crud `count=true` list run

### Task 2: Tier 2 — open / high-cardinality facets

- [x] `CrudService::facetValues(ListRequestData, FacetQuery): FacetPage` — single `GROUP BY` on one key column with page/perPage, value `search`, `sort` (count/key), `distinctValues`
- [x] `FacetQuery`, `FacetPage`, `FacetSort` DTOs
- [x] Two-step key ≠ label resolution: grouped count on the key, then bounded `whereIn` read for display `fields`
- [x] `CrudServiceFacetValuesTest`

### Task 3: Single-hop relation facets and label sources

- [x] `relation.column` labels for a `BelongsTo` keyed by `groupBy`, resolved without a join (`facetRelatedColumnValues`); pivot relation faceting (`facetRelationValues`)
- [x] Label `search`/`sort` without joining the grouped query: `whereIn` on resolved keys + correlated `ORDER BY` subquery (`orderFacetPage` / `orderRelationFacet`, `FacetSort::LabelAsc`/`LabelDesc`)
- [x] `FacetLabelSource` + `ProvidesFacetLabelSources::facetLabelSources()`; `assertFacetResolvable` guard
- [x] `CrudServiceFacetRelationTest`, `CrudServiceFacetLabelSourceTest`

### Task 4: HTTP surface

- [x] Standalone `Route::match(['get','post'], '/facets/{module}/{entity}')` (`crud.facets`)
- [x] `CrudController::facets(FacetsRequest)` — singular `facet` payload selects tier 2, its absence tier 1; full bootstrap (entity resolution, filter parse, permission check) on purpose
- [x] `FacetsRequest`
- [x] `CrudFacetsTest`

### Task 5: Docs handoff

- [x] `Modules/Core/docs/CRUD_SYSTEM.md` "Faceted Counts" section + RAG note

---

## Delivery status (2026-09-18): shipped, recorded retrospectively

**Documented in:** `Modules/Core/docs/CRUD_SYSTEM.md` and `Modules/Core/docs/rag/MODULE.md`.

Both tiers shipped ahead of this plan, so the file is a retrospective record for spec/plan parity, not a work order. Verified 2026-09-18 against the code: `CrudService::facetCounts` / `facetValues`, the `FacetQuery` / `FacetPage` / `FacetSort` / `FacetLabelSource` DTOs, `CrudController::facets` + `FacetsRequest` on the `crud.facets` route, covered by `CrudServiceFacetCountsTest`, `CrudServiceFacetValuesTest`, `CrudServiceFacetRelationTest`, `CrudServiceFacetLabelSourceTest` and `CrudFacetsTest`.

Divergence from the spec's staging ("ship tier 1 first, tier 2 only if needed"): both tiers, single-hop relation facets and label search/sort all landed together. Still deferred, as the spec anticipated: multi-hop label paths and the optional list piggyback. The Grid subsystem was not revived.

---

**Spec:** `docs/superpowers/specs/2026-08-06-crud-facet-counters-design.md`
