# Large dataset query patterns and Filament performance

**Status:** Draft
**Date:** 2026-07-09
**Scope:** Core query/memory patterns, CMS cache warm-up, Filament admin panel
**Related:** `docs/filament-performance-recommendations.md` (extended Filament notes and applied-resource history)

## Problem

Several paths load full result sets with `get()` or run N+1 queries where iteration, batching, eager loading, or caching suffices. Filament list pages amplify this: each row can trigger extra queries for relations, media, tab badges, and widgets.

## Goals

1. Standardize when to use `get()`, `lazy()`, `chunk()`, `cursor()`, and `cursorPaginate()`.
2. Track Core query/memory backlog with priority and implementation status.
3. Encode Filament table/widget performance rules for new and existing resources.
4. Require tests on large datasets for every batching change.

## Non-Goals

- Refactor experimental Grid module (`Grid.php`, `Funnel.php`, `Option.php`).
- Micro-optimize datasets known to stay small (< ~100 rows).
- Vendor-specific SQL unless portability is documented.
- Replace Filament/Livewire overhead; only reduce avoidable query/render cost.

## Eloquent iteration rules

| Need | Use | Notes |
|------|-----|-------|
| Small known set, full Collection ops | `get()` | Typically < ~500 rows |
| Iterate all, may need `with()` | `lazy(n)` | Default chunk 1000; supports eager loading |
| Per-batch side effects | `chunk(n)` | Explicit batch handler |
| Huge simple scan, no relations | `cursor()` | One row in memory; **no** `with()` |
| API infinite scroll / feed | `cursorPaginate()` | Requires stable unique `orderBy` |
| One row only | `first()` / `limit(1)` | Prefer over `get()` when one match is enough |
| UI page numbers | `paginate()` / `simplePaginate()` | When cursor pagination is not viable |

**Hard rules**

- `cursor()` never with `with()` — N+1 risk on relations.
- `lazy()` faster and supports relations; `cursor()` uses less memory.
- `cursorPaginate()` needs `orderBy` on a unique column.
- Prefer `chunk()` or `lazy()` over loading full trees in memory (e.g. closure rebuild).

## Core query backlog

| P | File | Issue | Direction | Status |
|---|------|-------|-----------|--------|
| P0 | `Modules/Core/app/Helpers/HasClosureTable.php` (`rebuild`) | Loads all models + `children` | `chunk()` or `lazy()`; drop eager load if unused | Open (trait embryonic / absent in some checkouts) |
| P1 | `Modules/Core/app/Search/Engines/DatabaseEngine.php` (SQLite) | Full `get()` + similarity in PHP | `lazy()`/`chunk()` + early stop at limit | Open |
| P1 | `Modules/Core/app/Console/HandleLicensesCommand.php` (`listLicenses`) | `License::with('user')->get()` | `lazy(100)` with `with('user')` | Partial (`groupBy` uses `lazy`; `listLicenses` still `get`) |
| P1 | `Modules/Core/app/Services/Crud/CrudService.php` (bulk update/delete/lock) | Wide match loads all rows | `lazy(100)` on match | **Done** |
| P2 | `Modules/Core/app/Models/User.php` (`getPermissions`) | `Permission::query()->get()` | Cache or accept if < 100; `lazy()` if growth expected | Open |
| P2 | `Modules/Cms/app/Services/DynamicContentsService.php` | `get()` inside `rememberForever` warm-up | `lazy()` on first load if > 5k; review TTL | Open |
| P2 | `Modules/Core/app/Listeners/AfterLoginListener.php` | Loads all available licenses | `first()` when one license is enough | **Done** |

**Exclusions**

- `HasCrudOperations`: dynamic ordering; `skip()`/`take()` acceptable.
- Grid/Funnel/Option: experimental; out of scope until stabilized.

## Filament performance rules

### Expectations

- Production is faster than constrained dev (OPcache, Redis, `APP_DEBUG=false`) but Filament remains Livewire-heavy.
- Targeted query and render fixes matter in every environment.

### New table checklist

1. **Eager loading:** For every column using a relation (`entity.name`, `->relationship()`, media), apply `modifyQueryUsing(fn ($q) => $q->with([...]))` on the Resource with all required relations.
2. **Default sort:** Always set `defaultSort()` (e.g. `name`, `created_at desc`).
3. **Pagination:** Do not use `paginated(false)` on growing tables; keep ~25 rows (see `HasTable`).
4. **Filter options:** Cache `distinct()->pluck()` options (TTL e.g. `core.filament.tabs_counts_ttl_seconds` or 300s) or use `->relationship()->searchable()->preload()`.
5. **Expensive columns:** Relations used in `formatStateUsing` / `getStateUsing` must be in `with()`; include `ancestors` for hierarchy counts.
6. **Hidden by default:** Use `toggleable(isToggledHiddenByDefault: true)` for heavy columns (path, JSON, long text).

### Filament backlog

| P | Area | Issue | Direction | Status |
|---|------|-------|-----------|--------|
| P0 | `ListModifications::getTabs()` | N+1 `count()` per tab | Single `groupBy` query | **Done** |
| P0 | Contents list | N+1 on entity, preset, media | `with(['entity', 'preset', 'media'])` on Resource | Verify in module checkout |
| P1 | `SearchEngineHealthTableWidget` | N models × count + engine stats | Cache `getViewData()` (300s) | **Done** |
| P1 | `CoreStatsWidget` | Count queries every dashboard load | `$isLazy = true` and/or short cache | Open |
| P2 | Tab badge cache | Optional TTL on modification tab counts | Uncomment/adopt `Cache::remember` in `ListModifications` | Open |
| P2 | Dashboard widget count | Too many widgets on home | Reduce or restrict by role | Open |

### Resources already optimized (reference)

User (`roles`), Role (`permissions`), ACL (`permission`), Modification (`modifier`), Permission/Settings filter options (cache), Content (`entity`, `preset`, `media`), Category (`entity`, `preset`, `ancestors`), Preset (`entity`, `template`).

Entities, Tags, Fields, Locations, Templates, CronJobs, Licenses: no relation columns in table — eager load not required.

## Verification

1. **TTFB:** Network tab on list routes (Users, Contents, Categories, Modifications, Permissions).
2. **Query count:** `DB::enableQueryLog()`, Telescope, or Debugbar — target ~1 + 2–3 queries per 25-row page with eager load (not 1 + 25×N).
3. **Memory:** Batch jobs on > 10k rows without `memory limit exceeded`.
4. **Production:** Repeat TTFB in staging with OPcache and `APP_DEBUG=false`.

## Success criteria

- No memory-limit failures on Core batch paths > 10k rows.
- Filament list pages: no N+1 on relation/media columns.
- Each backlog change has an automated test (feature or integration) with large dataset or chunked assertion.
