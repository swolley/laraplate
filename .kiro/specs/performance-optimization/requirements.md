# Requirements Document

## Introduction

This document defines the performance optimization requirements for the Laraplate application.
The analysis was conducted by inspecting the source code of the Core, AI, and CMS modules to
identify concrete bottlenecks, missing caching, N+1 query risks, and synchronous operations
that should be asynchronous.

The requirements are organized into three categories:
1. **No-downside improvements** — safe to apply with no trade-offs
2. **Trade-off improvements** — beneficial but with explicit costs
3. **Issues to monitor** — problems without an immediate solution

---

## Glossary

- **Cache_Manager**: The `Modules\Core\Cache\CacheManager` and `Repository` classes that extend Laravel's cache infrastructure
- **ACL_Resolver**: The `AclResolverService` responsible for resolving row-level access control per user/permission pair
- **HasValidations**: The trait applied to all Core `Model` subclasses that performs permission checks and validation on every Eloquent event
- **HasVersions**: The trait applied to all Core `Model` subclasses that manages versioning strategy resolution
- **HasTranslations**: The trait applied to translatable models (e.g. `Content`, `Category`) that manages locale-aware field access
- **DynamicEntityService**: The singleton service that resolves table names to Eloquent model classes with in-memory caching
- **SchemaInspector**: The singleton that memoizes database schema introspection results per request lifecycle
- **Content_Model**: The `Modules\CMS\Models\Content` Eloquent model with full-text search, translations, categories, tags, contributors, and locations
- **GenerateEmbeddingsJob**: The queued job in the AI module that generates vector embeddings for models
- **SearchOrchestratorAgent**: The AI service that generates LLM-based search plans with a 10-minute cache
- **NominatimService**: The geocoding service in CMS that makes synchronous HTTP calls to the Nominatim API
- **ContextualSuggestionService**: The AI service that generates per-user contextual suggestions with rate limiting and caching
- **Spatie_Permission**: The `spatie/laravel-permission` package used for role and permission management
- **CRUD_API**: The generic REST API layer built on `CrudService` + `AuthorizationService` + `QueryBuilder`
- **Filament**: The admin panel framework (v5) used for all back-office interfaces
- **Queue**: Laravel's queue system used for background processing (embeddings, translations, versioning)

---

## Requirements

---

### Requirement 1: Eliminate Redundant Permission DB Queries in HasValidations

**User Story:** As a developer, I want model-level permission checks to avoid hitting the database on every Eloquent event, so that CRUD operations do not generate unnecessary queries.

**Evidence:** `HasValidations::checkUserCanDo()` (line ~130) calls `$permission_class::whereName($permission)->count()` on every `retrieved`, `creating`, `updating`, `deleting`, and `forceDeleting` event. For a list page loading 50 records, this generates 50+ extra queries just to check if a permission row exists.

#### Acceptance Criteria

1. WHEN the `HasValidations` trait checks whether a permission exists, THE `HasValidations` SHALL resolve the result from an in-memory static cache keyed by permission name, populated on first access.
2. WHEN the permission existence cache is populated for a given permission name, THE `HasValidations` SHALL NOT issue a database query for the same permission name within the same request lifecycle.
3. WHEN the application boots a new request, THE `HasValidations` SHALL start with an empty in-memory permission existence cache.
4. IF a permission name is not found in the in-memory cache, THEN THE `HasValidations` SHALL query the database once and store the result.
5. THE `HasValidations` SHALL maintain 100% type coverage and pass PHPStan level max after the change.

---

### Requirement 2: Fix Cache::flush() in AclResolverService.clearCacheForPermission

**User Story:** As a system administrator, I want ACL cache invalidation to be targeted rather than global, so that a permission change does not wipe the entire application cache.

**Evidence:** `AclResolverService::clearCacheForPermission()` (line ~100) calls `Cache::flush()` with a TODO comment acknowledging the problem. This destroys all cached data (settings, cron jobs, dynamic entities, search plans, etc.) whenever any ACL is modified.

#### Acceptance Criteria

1. WHEN an ACL record is saved or deleted, THE `ACL_Resolver` SHALL invalidate only the cache entries related to the affected permission, not the entire cache store.
2. THE `ACL_Resolver` SHALL use a key-based invalidation strategy compatible with the `database`, `file`, and `array` cache drivers (no tag dependency).
3. WHEN `clearCacheForPermission` is called with a specific permission, THE `ACL_Resolver` SHALL iterate over all users that have that permission and delete only their ACL cache entries.
4. THE `ACL_Resolver` SHALL expose a `clearCacheForPermission(Permission $permission)` method signature that accepts the permission model.
5. IF the number of users with a given permission exceeds a configurable threshold (default: 500), THEN THE `ACL_Resolver` SHALL fall back to flushing only the ACL-prefixed keys rather than all cache.

---

### Requirement 3: Cache Version Strategy Resolution in HasVersions

**User Story:** As a developer, I want version strategy lookups to be cached, so that saving a model does not trigger a database query to the `settings` table on every write.

**Evidence:** `HasVersions::getVersionStrategy()` (line ~120) calls `Cache::rememberForever('version_strategies', fn () => Setting::where('group_name', 'versioning')->get())` — this is correct, but the result is a `Collection` that is then searched with `firstWhere()` on every call. The `rememberForever` key is shared across all model classes, meaning the full collection is deserialized on every `getVersionStrategy()` call even when the result is always the same for a given model class.

#### Acceptance Criteria

1. WHEN `getVersionStrategy()` is called for a model class that has already been resolved in the current request, THE `HasVersions` SHALL return the cached result from a static in-memory map keyed by model class name.
2. WHEN the static in-memory map does not contain an entry for a model class, THE `HasVersions` SHALL resolve the strategy from the persistent cache (or database) and store it in the static map.
3. THE `HasVersions` SHALL expose a static `resetVersionStrategyCache()` method that clears the in-memory map (used in tests).
4. THE `HasVersions` SHALL maintain the existing `Cache::rememberForever('version_strategies', ...)` as the persistent cache layer beneath the in-memory map.

---

### Requirement 4: Eager Load Translations in HasTranslations Global Scope

**User Story:** As a developer, I want translatable models to always eager-load their active translation, so that accessing translated fields does not trigger a separate query per model instance.

**Evidence:** `HasTranslations::initializeHasTranslations()` adds `'translation'` to `$this->with`, which is correct. However, `getTranslatableFieldValue()` (line ~280) falls back to calling `$this->getDefaultTranslation()` which issues a new query `$this->translations()->where('locale', $default_locale)->first()` when the eager-loaded translation is missing. In list contexts with fallback enabled, this generates one extra query per record.

#### Acceptance Criteria

1. WHEN `getTranslatableFieldValue()` is called and the eager-loaded `translation` relation is null, THE `HasTranslations` SHALL check the already-loaded `translations` collection (if present) before issuing a new database query.
2. WHEN the `translations` collection is loaded and contains a record for the default locale, THE `HasTranslations` SHALL use that record as the fallback without a new query.
3. WHEN neither the eager-loaded `translation` nor the `translations` collection is available, THE `HasTranslations` SHALL issue a single query to retrieve the default translation.
4. THE `HasTranslations` SHALL not change the public API of `getTranslation()`, `setTranslation()`, or `hasTranslation()`.

---

### Requirement 5: Add Missing Eager Loading in Content.toSearchableArray

**User Story:** As a developer, I want the `Content` model's search indexing to use eager-loaded relations, so that generating a searchable document does not trigger N+1 queries for contributors, categories, tags, and locations.

**Evidence:** `Content::toSearchableArray()` (line ~130) accesses `$this->contributors`, `$this->categories`, `$this->tags`, `$this->locations`, `$this->translations`, and `$this->preset` directly. When called from a batch indexing command on 1000 records, this generates 5000+ extra queries.

#### Acceptance Criteria

1. WHEN `Content::toSearchableArray()` is called, THE `Content_Model` SHALL access only relations that are already loaded (eager-loaded) or explicitly load them in a single batch if not present.
2. THE `Content_Model` SHALL define a `$with` array or a `toSearchableWith()` method listing the relations required for indexing: `['contributors', 'categories', 'tags', 'locations', 'translations', 'presettable.entity', 'presettable.preset']`.
3. WHEN the search indexing command runs on a collection of `Content` records, THE `Content_Model` SHALL not issue more than `N + R` queries where N is the number of records and R is the number of distinct relation types.
4. THE `Content_Model` SHALL pass all existing tests after the change.

---

### Requirement 6: Unified Cache Key Namespace and TTL Configuration

**User Story:** As a developer, I want all cache keys in the application to follow a consistent naming convention and use centrally configured TTLs, so that cache management, debugging, and invalidation are predictable.

**Evidence:** Cache keys are scattered across modules with inconsistent naming: `'acl:resolved:user:{id}:perm:{id}'` (AclResolverService), `'search_orchestrator:{md5}'` (SearchOrchestratorAgent), `'ai:suggestion:cache:{user}:{hash}'` (ContextualSuggestionService), `'version_strategies'` (HasVersions), `'anonymous_user'` (AuthorizationService), `'dynamic_entities.{conn}.{table}'` (DynamicEntityService). No central registry exists.

#### Acceptance Criteria

1. THE `Cache_Manager` SHALL provide a static `key(string $namespace, string ...$parts): string` method that generates cache keys in the format `{app_name}:{namespace}:{parts_joined_by_colon}`.
2. WHEN any service or trait generates a cache key, THE service SHALL use `Cache_Manager::key()` or the equivalent `Repository::getCacheTags()` pattern already established in the Core module.
3. THE Core module config (`config/core.php` or `config/cache.php`) SHALL define named TTL constants: `short` (60s), `medium` (300s), `long` (3600s), `day` (86400s), `forever` (null).
4. WHEN a service needs a cache TTL, THE service SHALL reference a named TTL from config rather than hardcoding a numeric value.
5. THE `Cache_Manager` SHALL maintain backward compatibility with existing cache keys during a transition period by supporting both old and new key formats.

---

### Requirement 7: Geocoding Cache Layer in NominatimService

**User Story:** As a developer, I want geocoding results to be cached, so that repeated lookups for the same address do not make redundant HTTP calls to the Nominatim API.

**Evidence:** `NominatimService::performSearch()` makes a synchronous HTTP call to `https://nominatim.openstreetmap.org/search` with no caching. The same address can be geocoded multiple times (e.g. when editing a Location record). Nominatim also enforces a 1 request/second rate limit.

#### Acceptance Criteria

1. WHEN `NominatimService::performSearch()` is called with a query that has been resolved before, THE `NominatimService` SHALL return the cached result without making an HTTP request.
2. THE `NominatimService` SHALL cache geocoding results using a key derived from the normalized query parameters (query, city, province, country, limit).
3. THE `NominatimService` SHALL use a configurable TTL for geocoding cache (default: 7 days), stored in the CMS module config.
4. WHEN the cache does not contain a result for the query, THE `NominatimService` SHALL make the HTTP request, cache the result, and return it.
5. IF the HTTP request fails, THEN THE `NominatimService` SHALL NOT cache the failure and SHALL return null.
6. THE `NominatimService` SHALL be compatible with the `database`, `file`, and `array` cache drivers.

---

### Requirement 8: Async Geocoding via Queued Job

**User Story:** As a developer, I want geocoding operations triggered by Location model saves to be dispatched as queued jobs, so that saving a Location record does not block the HTTP request with a synchronous external API call.

**Evidence:** The `NominatimService` is called synchronously during request handling. The CMS `Jobs/` directory is empty — no geocoding job exists. The AI module demonstrates the correct pattern with `GenerateEmbeddingsJob` and `TranslateModelJob`.

#### Acceptance Criteria

1. WHEN a `Location` model is saved with address fields that require geocoding, THE `Location` model observer or event listener SHALL dispatch a `GeocodeLocationJob` to the queue instead of calling the geocoding service synchronously.
2. THE `GeocodeLocationJob` SHALL implement `ShouldQueue` and be dispatched to a dedicated `geocoding` queue.
3. THE `GeocodeLocationJob` SHALL use `ThrottlesExceptions` middleware to respect the Nominatim 1 req/s rate limit.
4. WHEN the `GeocodeLocationJob` completes successfully, THE `Location` model SHALL be updated with the resolved coordinates.
5. IF the geocoding job fails after all retries, THEN THE `Location` model SHALL retain its existing coordinates (or null) and the failure SHALL be logged.
6. THE `GeocodeLocationJob` SHALL have `$deleteWhenMissingModels = true` to avoid processing deleted records.

---

### Requirement 9: Cache Spatie Permission Resolution Results

**User Story:** As a developer, I want permission checks for the authenticated user to be cached within the request lifecycle, so that repeated `hasPermissionTo()` calls for the same user and permission do not re-query the database.

**Evidence:** `AuthorizationService::checkPermission()` calls `$user->hasPermissionTo($permission_name, $guard_name)` which internally queries the `model_has_permissions` and `role_has_permissions` tables. In a single Filament page load, this can be called dozens of times for the same user. Spatie Permission has its own cache, but it caches the full permission set per user — the issue is that `Permission::findByName()` in `getAclFilters()` also queries the DB on every call.

#### Acceptance Criteria

1. WHEN `AuthorizationService::getAclFilters()` calls `Permission::findByName()`, THE `AuthorizationService` SHALL cache the resolved `Permission` model instance in a static in-memory map keyed by permission name for the duration of the request.
2. WHEN the same permission name is looked up again within the same request, THE `AuthorizationService` SHALL return the cached `Permission` instance without a database query.
3. THE `AuthorizationService` SHALL expose a `resetPermissionCache()` method for use in tests.
4. THE `AuthorizationService` SHALL not bypass or replace Spatie Permission's own cache mechanism.

---

### Requirement 10: Lazy Loading Guard in Development and Staging

**User Story:** As a developer, I want the application to detect and report N+1 query problems during development, so that new code does not introduce lazy loading regressions.

**Evidence:** `CoreServiceProvider::configureModels()` has a commented-out `Model::shouldBeStrict()` call with the comment "prevents also eager loading. App is not yet ready for this". This means N+1 queries are silently allowed in all environments.

#### Acceptance Criteria

1. WHEN the application runs in the `local` or `testing` environment, THE `CoreServiceProvider` SHALL enable `Model::preventLazyLoading()` to throw exceptions on lazy-loaded relations.
2. WHEN `Model::preventLazyLoading()` is enabled and a lazy-loaded relation is accessed, THE application SHALL throw a `LazyLoadingViolationException` with the model class and relation name.
3. THE `CoreServiceProvider` SHALL NOT enable `Model::preventLazyLoading()` in `production` or `staging` environments.
4. WHERE the `APP_PREVENT_LAZY_LOADING` environment variable is set to `true`, THE `CoreServiceProvider` SHALL enable `Model::preventLazyLoading()` regardless of environment.
5. THE `CoreServiceProvider` SHALL maintain the existing `Model::preventSilentlyDiscardingAttributes()` and `Model::preventAccessingMissingAttributes()` behavior.

---

### Requirement 11: Cache Settings Queries in HasVersions and Other Consumers

**User Story:** As a developer, I want the `settings` table to be queried at most once per request for a given group, so that models using `HasVersions` do not repeatedly deserialize the full settings collection.

**Evidence:** `HasVersions::getVersionStrategy()` uses `Cache::rememberForever('version_strategies', fn () => Setting::where('group_name', 'versioning')->get())`. The `Setting` model uses `HasCache` which invalidates on save/delete. However, the `rememberForever` key `'version_strategies'` is a flat string with no app-name prefix, risking key collisions in shared cache environments.

#### Acceptance Criteria

1. THE `HasVersions` trait SHALL prefix the `version_strategies` cache key with the application name using `Cache_Manager::key()` or equivalent.
2. WHEN a `Setting` with `group_name = 'versioning'` is saved or deleted, THE `Setting` model observer or `HasCache` trait SHALL invalidate the `version_strategies` cache key.
3. THE `HasVersions` SHALL use the static in-memory map from Requirement 3 as the L1 cache, with the persistent cache as L2.

---

### Requirement 12: Optimize Content Filament Table — Missing Relation Eager Loading

**User Story:** As an admin user, I want the CMS Contents list page to load quickly, so that browsing content records in Filament does not trigger N+1 queries.

**Evidence:** `ContentResource::table()` eager-loads `['presettable.entity', 'presettable.preset', 'media']` but does NOT eager-load `translations` (required by `HasTranslations` for every record's title display) or `categories` (used in table columns). The `Content` model's `$with` property includes `'translation'` via `initializeHasTranslations()`, but this is a `HasOne` with a complex `CASE WHEN` ordering clause that may not be optimized by all drivers.

#### Acceptance Criteria

1. WHEN the Filament Contents table loads, THE `ContentResource` SHALL eager-load all relations accessed by table columns, including `translations` (or `translation`), `categories`, and `contributors` if displayed.
2. THE `ContentResource` table query SHALL not issue more than `N + R` queries for N records and R relation types.
3. THE `ContentResource` SHALL use `modifyQueryUsing` to add the required eager loads without modifying the base model's `$with` property.

---

### Requirement 13: Introduce Request-Scoped Cache for Repeated Config Reads

**User Story:** As a developer, I want frequently-read config values to be cached in a static property, so that hot paths do not call `config()` hundreds of times per request.

**Evidence:** `Cache\Repository::getCacheTags()` caches `config('app.name')` in a static property — this is the correct pattern. However, `HasTranslations::getTranslatableFieldValue()` calls `config('app.locale')` and `LocaleContext::isFallbackEnabled()` on every attribute access. `AuthorizationService::buildPermissionName()` calls `$connection ?? 'default'` but the connection name could also be cached. `HasVersions::getVersionStrategy()` calls `config('versionable.version_model')` on every invocation.

#### Acceptance Criteria

1. WHEN `HasTranslations::getTranslatableFieldValue()` reads `config('app.locale')`, THE `HasTranslations` SHALL cache the default locale in a static property populated once per request.
2. WHEN `HasVersions::getVersionStrategy()` reads `config('versionable.version_model')`, THE `HasVersions` SHALL cache the version model class name in a static property.
3. THE static properties SHALL be reset between requests (they are naturally reset in PHP-FPM; for long-running processes, a `reset()` method SHALL be provided).
4. THE `HasTranslations` SHALL expose a static `resetLocaleCache()` method for use in tests.

---

### Requirement 14: Decouple Content Search Indexing from Synchronous Request Path

**User Story:** As a developer, I want search index updates for Content records to always be dispatched asynchronously, so that saving a Content record does not block the HTTP response waiting for Elasticsearch/Typesense indexing.

**Evidence:** `HandleModelIndexingListener::handle()` checks `$event->sync` and, if true, calls `app()->call([new GenerateEmbeddingsJob($event->model), 'handle'])` synchronously. The `sync` flag can be set by callers, meaning some code paths may inadvertently trigger synchronous embedding generation during a web request.

#### Acceptance Criteria

1. WHEN `HandleModelIndexingListener` receives a `ModelRequiresIndexing` event with `sync = true` during a web request (not a CLI command), THE `HandleModelIndexingListener` SHALL dispatch the job to the queue instead of executing it synchronously.
2. WHERE the application is running as a CLI command (artisan), THE `HandleModelIndexingListener` SHALL respect the `sync` flag and execute synchronously when requested.
3. THE `HandleModelIndexingListener` SHALL use `app()->runningInConsole()` to distinguish between web and CLI contexts.
4. THE `HandleModelIndexingListener` SHALL maintain the existing behavior for the `sync = false` case (always async).

---

### Requirement 15: Add Index on model_embeddings for Non-PostgreSQL Drivers

**User Story:** As a developer, I want the `model_embeddings` table to have appropriate indexes on all supported database drivers, so that embedding lookups by model type and ID are fast.

**Evidence:** The migration `2024_11_05_233754_create_model_embeddings_table.php` creates a `morphs('model', 'embedding_model_IDX')` index (covering `model_type` + `model_id`) and a PostgreSQL-only `ivfflat` vector index. On MySQL/SQLite, there is no vector index and the morphs index may not be sufficient for queries that filter by `model_type` alone.

#### Acceptance Criteria

1. THE `model_embeddings` migration SHALL create a composite index on `(model_type, model_id)` for all database drivers (already provided by `morphs()`).
2. WHERE the database driver is MySQL or MariaDB, THE migration SHALL create a `FULLTEXT` index on the `embedding` column only if the column type supports it; otherwise, it SHALL skip the vector index gracefully.
3. THE migration SHALL be idempotent — running it twice SHALL not produce errors.
4. THE `ModelEmbedding` model SHALL define a scope `forModel(Model $model)` that uses the composite index efficiently.

---

### Requirement 16: Introduce a Centralized Cache Warming Strategy

**User Story:** As a developer, I want critical runtime data (settings, cron jobs, version strategies, permission existence map) to be pre-warmed into cache on application boot or via an artisan command, so that the first requests after a deployment do not suffer cold-cache penalties.

**Evidence:** `CoreServiceProvider::registerCommandSchedules()` already warms the cron jobs cache on boot. `HasVersions::getVersionStrategy()` uses `rememberForever` which warms on first access. However, there is no coordinated warm-up for: ACL data, permission existence map, settings groups, or dynamic entity resolutions.

#### Acceptance Criteria

1. THE Core module SHALL provide an artisan command `core:cache:warm` that pre-populates the following cache entries: settings (all groups), cron jobs, version strategies, and permission existence map.
2. WHEN `core:cache:warm` is executed, THE command SHALL report the number of cache entries populated and the time taken.
3. THE `core:cache:warm` command SHALL be idempotent — running it multiple times SHALL produce the same cache state.
4. THE Core module config SHALL include a `cache.warm_on_boot` boolean (default: `false`) that, when `true`, triggers cache warming during `AppServiceProvider::boot()`.
5. WHERE `cache.warm_on_boot` is `true`, THE warming SHALL be deferred using `$this->app->booted()` to avoid running before all service providers are registered.

---

### Requirement 17: Optimize AclResolverService — Batch Permission Lookup

**User Story:** As a developer, I want ACL resolution to batch-load permissions and ACLs for a user in a single query, so that resolving ACLs for a user with multiple roles does not generate one query per role.

**Evidence:** `AclResolverService::resolveAcls()` iterates over `$user->roles` and for each role calls `resolveAclForRole()` → `findAclForRolePermission()` which issues a separate `ACL::query()->...->whereHas('permission.roles', ...)` query. For a user with 5 roles, this generates 5+ queries per permission check.

#### Acceptance Criteria

1. WHEN `AclResolverService::resolveAcls()` is called for a user with multiple roles, THE `ACL_Resolver` SHALL load all relevant ACLs for the permission in a single query using `whereIn` on role IDs.
2. THE `ACL_Resolver` SHALL eager-load the `permission.roles` relation when loading ACLs to avoid N+1 queries in the role matching logic.
3. THE `ACL_Resolver` SHALL maintain the existing inheritance logic (role → ancestor fallback) after the query optimization.
4. THE `ACL_Resolver` SHALL pass all existing ACL resolution tests after the change.

---

## Issues to Monitor (No Immediate Solution)

### Issue A: Cache Tag Incompatibility with File and Database Drivers

**Description:** The `Cache\Repository` methods `clearByEntity()`, `clearByUser()`, `clearByGroup()`, and `tryByRequest()` use `Cache::tags()` which is only supported by Redis and Memcached. The `CoreServiceProvider::registerCommandSchedules()` already handles this with a `supportsTags()` check, but `clearByEntity()` calls `$this->tags(...)` unconditionally when `method_exists($model, 'usesCache')` is true. On file/database drivers, this will throw a `BadMethodCallException`.

**Risk:** Medium — affects any deployment using file or database cache drivers (the documented default).

**Monitoring:** Log `BadMethodCallException` from cache operations. Track frequency in production.

---

### Issue B: Content.toSearchableArray Accesses $this->preset Without Eager Loading

**Description:** `Content::toSearchableArray()` accesses `$this->preset->name` directly. The `preset` relation is not in the `$with` array and is not included in the `ContentResource` eager loads. This is a lazy-load that will trigger a query per record during batch indexing.

**Risk:** Low in web context (single record), High in batch indexing context (N queries for N records).

**Monitoring:** Enable `Model::preventLazyLoading()` in development (Requirement 10) to surface this automatically.

---

### Issue C: HasTranslations Uses CASE WHEN in ORDER BY — SQLite Compatibility

**Description:** `HasTranslations::translation()` and `forLocale()` use `orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])`. This is valid SQL but may have different performance characteristics across MySQL, PostgreSQL, and SQLite. On large translation tables without a composite index on `(translatable_id, translatable_type, locale)`, this query can be slow.

**Risk:** Low currently (small datasets), Medium as content grows.

**Monitoring:** Add a composite index on translation tables: `(translatable_id, locale)` or `(content_id, locale)`.

---

### Issue D: GenerateEmbeddingsJob Stores Multiple Embedding Rows Per Model

**Description:** `GenerateEmbeddingsJob::handle()` calls `$this->model->embeddings()->create(...)` in a loop for each document chunk. There is no cleanup of old embeddings before creating new ones. Over time, a model can accumulate multiple embedding rows, causing the `model_embeddings` table to grow unboundedly and making vector search results ambiguous.

**Risk:** Medium — data quality and storage growth issue.

**Monitoring:** Track `model_embeddings` table row count per `model_type`. Alert when a single model has more than a configurable threshold of embeddings.

---

### Issue E: AclResolverService.clearCacheForUser Iterates All Permissions

**Description:** `AclResolverService::clearCacheForUser()` calls `Permission::all(['id'])` to get all permission IDs, then deletes one cache key per permission. For an application with 500+ permissions, this generates 500+ `Cache::forget()` calls. On the database cache driver, each `forget()` is a DELETE query.

**Risk:** Medium — performance spike on user role changes.

**Monitoring:** Track execution time of `clearCacheForUser()`. Consider batching or using a user-scoped cache tag pattern.
