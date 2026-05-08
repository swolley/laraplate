# Design Document — Performance Optimization

## Overview

This document describes the technical design for the performance optimization feature of Laraplate.
The optimizations are organized into three categories mirroring the requirements document:

1. **No-downside improvements** (Req 1, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13) — safe to apply with no trade-offs.
2. **Trade-off improvements** (Req 2, 8, 14, 15, 16, 17) — beneficial but with explicit costs or behavioral changes.
3. **Issues to monitor** (Issue A–E) — documented problems without an immediate solution.

The design targets PHP 8.5, Laravel 12, Filament 5, PestPHP 4, PHPStan level max, and 100% type coverage.
All cache operations must be compatible with the `database`, `file`, and `array` drivers (no Redis-specific features).

---

## Architecture

### Caching Layers

The application uses a two-level caching strategy throughout:

```
L1 — Static in-memory map (per-request, zero-cost after first access)
  └── L2 — Persistent cache (database / file / array driver, configurable TTL)
        └── L3 — Database (single query on cold cache)
```

This pattern is already established in `DynamicEntityService` (in-memory `$resolved_cache` + `Cache::remember`) and `Repository::getCacheTags()` (static `$app_name`). All new optimizations follow the same pattern.

### Key Naming Convention

A new static helper `CacheManager::key(string $namespace, string ...$parts): string` centralizes key generation:

```
{app_name}:{namespace}:{part1}:{part2}:...
```

Examples:
- `laraplate:acl:user:42:perm:7`
- `laraplate:version_strategies:Modules\CMS\Models\Content`
- `laraplate:geocoding:a3f8b2c1d4e5f6a7`

### TTL Registry

Named TTL constants are added to `config/cache.php` (or `config/core.php`):

| Name      | Seconds | Use case                          |
|-----------|---------|-----------------------------------|
| `short`   | 60      | Volatile data (rate limits)       |
| `medium`  | 300     | Default API responses             |
| `long`    | 3600    | ACL resolution, search plans      |
| `day`     | 86400   | Geocoding results                 |
| `forever` | null    | Version strategies, settings      |

---

## Components and Interfaces

### Modified Files — Core Module

| File | Change |
|------|--------|
| `Modules/Core/app/Helpers/HasValidations.php` | Add static `$permission_existence_cache` map + `resetPermissionExistenceCache()` |
| `Modules/Core/app/Helpers/HasVersions.php` | Add static `$version_strategy_cache` map + `resetVersionStrategyCache()` + prefixed cache key |
| `Modules/Core/app/Helpers/HasTranslations.php` | Add static `$default_locale_cache` + `resetLocaleCache()` + check loaded `translations` collection before querying |
| `Modules/Core/app/Cache/CacheManager.php` | Add static `key(string $namespace, string ...$parts): string` method |
| `Modules/Core/app/Services/AclResolverService.php` | Fix `clearCacheForPermission(Permission $permission)` to use targeted key deletion; add batch ACL loading in `resolveAcls()` |
| `Modules/Core/app/Services/Authorization/AuthorizationService.php` | Add static `$permission_model_cache` map + `resetPermissionCache()` |
| `Modules/Core/app/Providers/CoreServiceProvider.php` | Enable `Model::preventLazyLoading()` in local/testing; add `APP_PREVENT_LAZY_LOADING` env support; add `cache.warm_on_boot` boot hook |
| `Modules/Core/app/Console/WarmCacheCommand.php` | New artisan command `core:cache:warm` |
| `Modules/Core/config/config.php` | Add `cache.warm_on_boot` boolean |
| `config/cache.php` | Add named TTL constants (`short`, `medium`, `long`, `day`, `forever`) |

### Modified Files — CMS Module

| File | Change |
|------|--------|
| `Modules/CMS/app/Models/Content.php` | Add `toSearchableWith()` method listing all required relations |
| `Modules/CMS/app/Filament/Resources/Contents/ContentResource.php` | Add `translations`, `categories`, `contributors` to `modifyQueryUsing` eager loads |
| `Modules/CMS/app/Jobs/GeocodeLocationJob.php` | New queued job implementing `ShouldQueue` |
| `Modules/CMS/app/Observers/LocationObserver.php` | New or updated observer dispatching `GeocodeLocationJob` on save |
| `Modules/CMS/config/config.php` | Add `geocoding.cache_ttl` (default: 604800 = 7 days) |

### Modified Files — AI Module

| File | Change |
|------|--------|
| `Modules/AI/app/Listeners/HandleModelIndexingListener.php` | Check `app()->runningInConsole()` before executing sync |

### New Migration — Core Module

| File | Change |
|------|--------|
| `Modules/Core/database/migrations/YYYY_MM_DD_add_formodel_scope_to_model_embeddings.php` | Add `forModel` scope to `ModelEmbedding` (no schema change needed, scope is code-only) |

---

## Data Models

### Static Cache Maps (in-memory, per-request)

#### `HasValidations::$permission_existence_cache`

```php
/** @var array<string, bool> */
private static array $permission_existence_cache = [];
```

- Key: permission name string (e.g. `'contents.select'`)
- Value: `bool` — whether the permission row exists in the DB
- Reset: `HasValidations::resetPermissionExistenceCache(): void`

#### `HasVersions::$version_strategy_cache`

```php
/** @var array<class-string, VersionStrategy|false> */
private static array $version_strategy_cache = [];
```

- Key: model class name (e.g. `Modules\CMS\Models\Content`)
- Value: `VersionStrategy` enum case or `false` (disabled)
- Reset: `HasVersions::resetVersionStrategyCache(): void`

#### `HasTranslations::$default_locale_cache`

```php
private static ?string $default_locale_cache = null;
```

- Value: default locale string (e.g. `'it'`)
- Reset: `HasTranslations::resetLocaleCache(): void`

#### `AuthorizationService::$permission_model_cache`

```php
/** @var array<string, Permission> */
private static array $permission_model_cache = [];
```

- Key: full permission name (e.g. `'default.contents.select'`)
- Value: `Permission` model instance
- Reset: `AuthorizationService::resetPermissionCache(): void`

### Persistent Cache Keys (L2)

All keys use the new `CacheManager::key()` helper:

| Old key | New key |
|---------|---------|
| `'version_strategies'` | `CacheManager::key('version_strategies')` → `'{app}:version_strategies'` |
| `'acl:resolved:user:{id}:perm:{id}'` | `CacheManager::key('acl', 'user', $user_id, 'perm', $perm_id)` |
| `'search_orchestrator:{md5}'` | `CacheManager::key('search_orchestrator', $md5)` |
| `'ai:suggestion:cache:{user}:{hash}'` | `CacheManager::key('ai', 'suggestion', $user_id, $hash)` |
| `'anonymous_user'` | `CacheManager::key('anonymous_user')` |
| `'dynamic_entities.{conn}.{table}'` | `CacheManager::key('dynamic_entities', $conn, $table)` |

### `GeocodeLocationJob` — Job Model

```php
final class GeocodeLocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;
    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(private readonly Location $location) {
        $this->onQueue('geocoding');
    }

    public function middleware(): array {
        return [new ThrottlesExceptions(1, 1)]; // 1 req/s Nominatim limit
    }
}
```

### `WarmCacheCommand` — Artisan Command

```
php artisan core:cache:warm
```

Warms the following entries:
1. All `Setting` groups (all records)
2. Cron jobs (already warmed by `registerCommandSchedules`)
3. Version strategies (all `Setting` records with `group_name = 'versioning'`)
4. Permission existence map (all `Permission` names → `true`)

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Permission existence cache eliminates redundant DB queries

*For any* permission name, calling `HasValidations::checkUserCanDo()` a second time within the same request lifecycle SHALL NOT issue a database query to the permissions table.

**Validates: Requirements 1.1, 1.2, 1.4**

---

### Property 2: ACL cache invalidation is targeted

*For any* set of N permissions with cached ACL entries, calling `clearCacheForPermission()` on one permission SHALL leave the cache entries for the other N-1 permissions intact.

**Validates: Requirements 2.1, 2.3**

---

### Property 3: ACL cache invalidation threshold fallback

*For any* permission where the number of users exceeds the configured threshold, `clearCacheForPermission()` SHALL NOT attempt to delete individual user-scoped ACL keys and SHALL instead flush only ACL-prefixed keys.

**Validates: Requirements 2.5**

---

### Property 4: Version strategy L1 cache eliminates repeated deserialization

*For any* model class, calling `HasVersions::getVersionStrategy()` a second time within the same request lifecycle SHALL return the cached result from the static in-memory map without issuing a database or persistent-cache query.

**Validates: Requirements 3.1, 3.2, 13.2**

---

### Property 5: HasTranslations avoids extra queries when translations collection is loaded

*For any* model instance where the `translations` collection is already loaded in memory, calling `getTranslatableFieldValue()` with fallback enabled SHALL NOT issue a new database query to retrieve the default translation.

**Validates: Requirements 4.1, 4.2**

---

### Property 6: Content toSearchableArray does not trigger lazy loading

*For any* `Content` record with all required relations eager-loaded (`contributors`, `categories`, `tags`, `locations`, `translations`, `presettable.entity`, `presettable.preset`), calling `toSearchableArray()` with `Model::preventLazyLoading()` enabled SHALL NOT throw a `LazyLoadingViolationException`.

**Validates: Requirements 5.1, 5.3**

---

### Property 7: Cache key format is consistent

*For any* namespace string and any sequence of parts, `CacheManager::key($namespace, ...$parts)` SHALL return a string matching the pattern `{app_name}:{namespace}:{parts_joined_by_colon}`.

**Validates: Requirements 6.1**

---

### Property 8: Geocoding cache prevents redundant HTTP calls

*For any* set of query parameters (query, city, province, country, limit), calling `NominatimService::search()` a second time with the same parameters SHALL return the cached result without making an HTTP request.

**Validates: Requirements 7.1, 7.2, 7.4**

---

### Property 9: Failed geocoding requests are not cached

*For any* geocoding request that results in an HTTP failure, the cache SHALL remain empty for that query key, and the method SHALL return `null`.

**Validates: Requirements 7.5**

---

### Property 10: Location save dispatches geocoding job asynchronously

*For any* `Location` model saved with non-empty address fields, a `GeocodeLocationJob` SHALL be dispatched to the `geocoding` queue and the geocoding service SHALL NOT be called synchronously during the save.

**Validates: Requirements 8.1**

---

### Property 11: Geocoding job updates Location coordinates on success

*For any* `Location` model and any valid geocoding result, executing `GeocodeLocationJob::handle()` SHALL update the `Location`'s `geolocation` field with the resolved coordinates.

**Validates: Requirements 8.4**

---

### Property 12: Geocoding job preserves coordinates on failure

*For any* `Location` model with existing coordinates, when `GeocodeLocationJob` fails after all retries, the `Location`'s coordinates SHALL remain unchanged.

**Validates: Requirements 8.5**

---

### Property 13: Permission model cache eliminates repeated findByName queries

*For any* permission name, calling `AuthorizationService::getAclFilters()` a second time within the same request lifecycle SHALL NOT issue a database query for `Permission::findByName()`.

**Validates: Requirements 9.1, 9.2**

---

### Property 14: Lazy loading detection throws in local/testing environments

*For any* Eloquent model with an unloaded relation, accessing that relation when `Model::preventLazyLoading()` is enabled SHALL throw a `LazyLoadingViolationException`.

**Validates: Requirements 10.1, 10.2**

---

### Property 15: Version strategies cache key includes app name prefix

*For any* application name, the persistent cache key used by `HasVersions::getVersionStrategy()` SHALL contain the application name as a prefix, preventing key collisions in shared cache environments.

**Validates: Requirements 11.1**

---

### Property 16: Versioning settings cache is invalidated on Setting save/delete

*For any* `Setting` record with `group_name = 'versioning'`, saving or deleting that record SHALL cause the `version_strategies` persistent cache entry to be absent on the next read.

**Validates: Requirements 11.2**

---

### Property 17: ContentResource table loads without lazy loading violations

*For any* page load of the Filament Contents table with `Model::preventLazyLoading()` enabled, no `LazyLoadingViolationException` SHALL be thrown for any column rendered in the table.

**Validates: Requirements 12.1, 12.2**

---

### Property 18: Default locale is read from config at most once per request

*For any* number of `getTranslatableFieldValue()` calls within the same request, `config('app.locale')` SHALL be invoked at most once.

**Validates: Requirements 13.1**

---

### Property 19: Web-context sync indexing is always dispatched asynchronously

*For any* `ModelRequiresIndexing` event with `sync = true` fired during a web request (not CLI), `HandleModelIndexingListener` SHALL dispatch `GenerateEmbeddingsJob` to the queue and SHALL NOT execute it inline.

**Validates: Requirements 14.1**

---

### Property 20: CLI-context sync indexing executes synchronously

*For any* `ModelRequiresIndexing` event with `sync = true` fired during a CLI command, `HandleModelIndexingListener` SHALL execute `GenerateEmbeddingsJob` synchronously.

**Validates: Requirements 14.2**

---

### Property 21: ModelEmbedding forModel scope uses composite index

*For any* Eloquent model instance, `ModelEmbedding::forModel($model)` SHALL generate a query that filters on both `model_type` and `model_id`, enabling use of the composite morphs index.

**Validates: Requirements 15.4**

---

### Property 22: Cache warming command is idempotent

*For any* initial cache state, running `core:cache:warm` twice SHALL produce the same final cache state as running it once.

**Validates: Requirements 16.3**

---

### Property 23: ACL resolution uses a single batch query for multi-role users

*For any* user with N roles (N ≥ 2), calling `AclResolverService::resolveAcls()` SHALL issue at most one database query to load ACLs (using `whereIn` on role IDs), regardless of N.

**Validates: Requirements 17.1, 17.2**

---

### Property 24: ACL resolution results are preserved after batch optimization

*For any* user and permission combination, the set of effective ACLs returned by `AclResolverService::resolveAcls()` after the batch optimization SHALL be identical to the set returned by the original per-role query implementation.

**Validates: Requirements 17.3**

---

## Error Handling

### Cache Driver Compatibility (Issue A)

`Repository::clearByEntity()`, `clearByUser()`, `clearByGroup()`, and `tryByRequest()` call `Cache::tags()` unconditionally when the model uses cache. On `file` and `database` drivers this throws `BadMethodCallException`.

**Design decision**: These methods already have a `supportsTags()` guard in `registerCommandSchedules()`. The same guard must be applied in `clearByEntity()` and related methods. When tags are not supported, fall back to key-based invalidation using `Cache::forget($this->getCacheKey())`.

```php
public function clearByEntity(Model|string|array $entity): void
{
    $models = Arr::wrap($entity);
    foreach ($models as $model) {
        if (is_string($model)) { $model = new $model(); }
        if (method_exists($model, 'usesCache') && $model->usesCache()) {
            if (Cache::supportsTags()) {
                $this->tags(self::getCacheTags($this->getTableName($model)))->flush();
            } else {
                Cache::forget($model->getCacheKey());
            }
        }
    }
}
```

This fix is included in the no-downside improvements scope.

### Geocoding Failures

`GeocodeLocationJob` uses `ThrottlesExceptions` middleware (1 exception per 1 second window) to respect Nominatim's rate limit. On final failure:
- The `failed()` method logs the error with model ID and exception message.
- The `Location` model retains its existing `geolocation` (or `null`).
- No retry is attempted beyond `$tries = 3` with backoff `[30, 60, 120]` seconds.

### Lazy Loading Violations

`Model::preventLazyLoading()` is enabled only in `local` and `testing` environments (or when `APP_PREVENT_LAZY_LOADING=true`). In production, lazy loading is silently allowed to avoid breaking existing behavior. The `APP_PREVENT_LAZY_LOADING` env variable allows staging environments to opt in.

### Cache Warming Failures

`WarmCacheCommand` wraps each warming step in a try/catch. If one step fails (e.g. the `settings` table does not exist yet), it logs the error and continues with the remaining steps. The command exits with `Command::FAILURE` only if all steps fail.

### ACL Threshold Fallback

When `clearCacheForPermission()` detects that the user count exceeds the threshold (default: 500), it falls back to iterating all cache keys with the `acl:` prefix. On the `database` driver this is a `WHERE key LIKE 'acl:%'` DELETE query. On the `file` driver it scans the cache directory. On the `array` driver it filters the in-memory store. This fallback is configurable via `config('core.acl.clear_threshold', 500)`.

---

## Testing Strategy

### Dual Testing Approach

- **Unit tests** (PestPHP): verify specific examples, edge cases, and error conditions for each component.
- **Property-based tests** (PestPHP + `pestphp/pest-plugin-arch` or a PBT library): verify universal properties across many generated inputs.

### Property-Based Testing Library

Use **`giorgiopogliani/pest-faker`** or **`spatie/pest-plugin-test-time`** for simple generators, or implement lightweight generators using `fake()` within `it()` blocks with `repeat()`. For true PBT, use **`eris/eris`** (PHP QuickCheck-style) or **`giorgiopogliani/pest-faker`**.

Given the project's existing PestPHP 4 setup, the recommended approach is:
- Use `Dataset` with `fake()` for parameterized tests (100+ iterations via `repeat()`).
- Each property test runs a minimum of **100 iterations**.
- Tag format: `// Feature: performance-optimization, Property N: {property_text}`

### Unit Test Coverage

Each requirement maps to at least one unit test:

| Requirement | Test file | Test type |
|-------------|-----------|-----------|
| Req 1 | `Tests/Unit/Helpers/HasValidationsTest.php` | Property (P1) |
| Req 2 | `Tests/Unit/Services/AclResolverServiceTest.php` | Property (P2, P3) |
| Req 3 | `Tests/Unit/Helpers/HasVersionsTest.php` | Property (P4) |
| Req 4 | `Tests/Unit/Helpers/HasTranslationsTest.php` | Property (P5) |
| Req 5 | `Tests/Unit/Models/ContentTest.php` | Property (P6) |
| Req 6 | `Tests/Unit/Cache/CacheManagerTest.php` | Property (P7) |
| Req 7 | `Tests/Unit/Services/NominatimServiceTest.php` | Property (P8, P9) |
| Req 8 | `Tests/Unit/Jobs/GeocodeLocationJobTest.php` | Property (P10, P11, P12) |
| Req 9 | `Tests/Unit/Services/AuthorizationServiceTest.php` | Property (P13) |
| Req 10 | `Tests/Feature/Providers/CoreServiceProviderTest.php` | Property (P14) |
| Req 11 | `Tests/Unit/Helpers/HasVersionsTest.php` | Property (P15, P16) |
| Req 12 | `Tests/Feature/Filament/ContentResourceTest.php` | Property (P17) |
| Req 13 | `Tests/Unit/Helpers/HasTranslationsTest.php` | Property (P18) |
| Req 14 | `Tests/Unit/Listeners/HandleModelIndexingListenerTest.php` | Property (P19, P20) |
| Req 15 | `Tests/Unit/Models/ModelEmbeddingTest.php` | Property (P21) |
| Req 16 | `Tests/Unit/Console/WarmCacheCommandTest.php` | Property (P22) |
| Req 17 | `Tests/Unit/Services/AclResolverServiceTest.php` | Property (P23, P24) |

### Property Test Configuration

```php
// Example: Property 1 — Permission existence cache
it('does not query DB for the same permission name twice', function () {
    // Feature: performance-optimization, Property 1: permission existence cache eliminates redundant DB queries
    HasValidations::resetPermissionExistenceCache();
    $permission_name = fake()->unique()->word() . '.' . fake()->word();

    DB::enableQueryLog();
    HasValidations::checkUserCanDo($model, $permission_name); // cold
    $count_after_first = count(DB::getQueryLog());
    HasValidations::checkUserCanDo($model, $permission_name); // warm
    $count_after_second = count(DB::getQueryLog());

    expect($count_after_second)->toBe($count_after_first); // no new query
})->repeat(100);
```

### Integration Tests

For Issue A (cache tag incompatibility), add integration tests that run with the `database` and `file` drivers to verify no `BadMethodCallException` is thrown from `clearByEntity()`.

### Smoke Tests

- PHPStan level max: `composer test:types`
- 100% type coverage: `composer test:type-coverage`
- `core:cache:warm` command exits with `Command::SUCCESS`
- `Model::preventLazyLoading()` is NOT enabled in production environment

---

## Issues to Monitor (No Immediate Solution)

### Issue A: Cache Tag Incompatibility with File and Database Drivers

**Affected methods**: `Repository::clearByEntity()`, `clearByUser()`, `clearByGroup()`, `tryByRequest()`

**Mitigation in this design**: Add `Cache::supportsTags()` guard to all affected methods (see Error Handling section). This converts Issue A from a runtime crash risk to a graceful degradation.

**Remaining risk**: Tag-based invalidation is less precise than key-based invalidation. On file/database drivers, `clearByEntity()` will only clear the model's primary cache key, not all tagged entries.

### Issue B: Content.toSearchableArray Accesses $this->preset Without Eager Loading

**Mitigation**: Requirement 5 adds `presettable.entity` and `presettable.preset` to `toSearchableWith()`. The `$this->preset` access in `toSearchableArray()` (line: `$document['preset'] = $this->preset->name`) will be caught by `Model::preventLazyLoading()` in development (Requirement 10) if `preset` is not in the eager-load list.

**Action**: Verify that `presettable.preset` resolves the `preset` accessor correctly, or add `preset` explicitly to the eager-load list.

### Issue C: HasTranslations Uses CASE WHEN in ORDER BY — SQLite Compatibility

**Monitoring**: Add a composite index on `(translatable_id, locale)` (or the concrete FK column) in the translation table migrations. This is a migration-level change outside the scope of this optimization sprint.

**Recommended index** (to be added in a future migration):
```sql
CREATE INDEX idx_translations_translatable_locale
    ON {table}_translations (translatable_id, locale);
```

### Issue D: GenerateEmbeddingsJob Stores Multiple Embedding Rows Per Model

**Monitoring**: Track `model_embeddings` table row count per `model_type`. The `forModel()` scope added in Requirement 15 enables efficient cleanup queries.

**Recommended future fix**: Add a `deleteOldEmbeddings()` step at the start of `GenerateEmbeddingsJob::handle()`:
```php
$this->model->embeddings()->delete(); // before creating new ones
```

### Issue E: AclResolverService.clearCacheForUser Iterates All Permissions

**Mitigation**: Requirement 17 reduces the number of ACL queries during resolution. The `clearCacheForUser()` method still iterates all permissions, but this is a less frequent operation (triggered only on role changes).

**Recommended future fix**: Store a user-scoped index of permission IDs in cache, updated when ACL entries are created/modified. This reduces `clearCacheForUser()` to a single cache read + N targeted deletes where N is the user's actual permission count.
