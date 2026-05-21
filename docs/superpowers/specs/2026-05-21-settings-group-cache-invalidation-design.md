# Settings group cache invalidation

**Status:** Draft
**Date:** 2026-05-21
**Scope:** Core settings cache invalidation only
**Chosen approach:** Group-level invalidation with backward-compatible reads

## Problem

Settings are moving from Laravel config files into the `settings` database table so values can be managed from the UI. Runtime reads already avoid repeated database/cache hits through a singleton resolver and in-request memoization.

The current invalidation is too broad:

- `SettingObserver::saved()` calls `SettingsCacheCoordinator::flushAll()`.
- `SettingObserver::deleted()` calls `SettingsCacheCoordinator::flushAll()`.
- `PerModelSettingResolver` stores all rows under one cache key: `{app}:settings:by_name`.

This works, but a change to one setting invalidates every setting group. The table already has `group_name`, so the cache should use that domain boundary.

## Goals

1. Save/delete of one `Setting` invalidates only the affected group cache.
2. If `group_name` changes, invalidate both old and new groups.
3. Keep the existing `PerModelSettingResolver::value($name)` API working.
4. Preserve `flushAll()` as an explicit fallback for bulk/direct updates, tests, warm/reset commands, and emergency reset.
5. Reset in-request singleton state for only the affected group when possible.
6. Keep derived settings caches coherent, especially Filament group options and version strategy L1 cache.

## Non-Goals

- Do not require all current callers to pass `group_name` immediately.
- Do not introduce a full settings write service in this first change.
- Do not solve direct query-builder updates automatically; Eloquent events do not fire for them.
- Do not change settings table schema.

## Current Findings

Core classes:

- `Modules\Core\Models\Setting`
- `Modules\Core\Observers\SettingObserver`
- `Modules\Core\Services\SettingsCacheCoordinator`
- `Modules\Core\Services\PerModelSettingResolver`
- `Modules\Core\Helpers\HasVersions`

Current persistent keys:

- `{app}:settings:by_name` from `PerModelSettingResolver::cacheKey()`
- `{app}:version_strategies` legacy cache warmed by `WarmCacheCommand`
- `{app}:settings` legacy/cache-model table key warmed by `WarmCacheCommand`
- `filament_settings_distinct_group_name` for settings table filters

Current setting groups found in seeders/code/tests:

- `base`
- `versioning`
- `soft_deletes`
- `locking`
- `translations`
- `moderation`
- `approvals`
- `erp`
- `modules`
- `backend`

## Proposed Cache Keys

Use `CacheManager::key()` for new settings cache keys.

- `CacheManager::key('settings', 'name_index')`
  - maps setting name to group name.
  - example value: `['pagination' => 'base', 'auto_translate_contents' => 'translations']`
- `CacheManager::key('settings', 'group', $group_name)`
  - stores one group only, keyed by setting name.
  - example: `{app}:settings:group:translations`

Keep legacy keys invalidated for compatibility:

- `CacheManager::key('settings', 'by_name')`
- `CacheManager::key('settings')`
- `CacheManager::key('version_strategies')`

## Resolver Design

`PerModelSettingResolver` keeps backward compatibility while changing its internal cache shape.

In-memory state:

- `$loaded_groups`: `array<string, Collection<string, Setting>>`
- `$name_index`: `array<string, string>|null`

Public API:

- `collection()` returns all settings, built by loading all known groups.
- `group(string $group_name)` returns one group, loaded from `{app}:settings:group:{group}`.
- `value(string $name, mixed $default = null)` remains compatible:
  1. load `name_index`;
  2. resolve `$group_name = $name_index[$name] ?? null`;
  3. if no group exists, return default;
  4. return value from `group($group_name)->get($name)`.
- typed helpers (`boolean`, `int`, `float`, `string`) keep using `value()`.

Invalidation API:

- `flushGroup(string $group_name)` forgets one group key and clears only that L1 group.
- `flushGroups(array $group_names)` loops unique non-empty groups.
- `flushNameIndex()` forgets name index and clears L1 index.
- `flush()` / `flushAll()` compatibility clears all known settings resolver state and legacy keys.

## Coordinator Design

`SettingsCacheCoordinator` becomes the only settings invalidation coordinator.

New methods:

- `flushSetting(Setting $setting): void`
- `flushGroup(string $group_name): void`
- `flushGroups(array $group_names): void`
- `flushAll(): void`

`flushSetting()` behavior:

1. Start with current `$setting->group_name`.
2. If the model exists and `group_name` changed, add `$setting->getOriginal('group_name')`.
3. Flush those groups.
4. Flush `name_index` when the setting is saved/deleted because `name`, `group_name`, create, delete, and restore can change name-to-group resolution.
5. Flush derived settings caches.
6. If the affected groups include `versioning`, reset `HasVersions` L1 and forget `{app}:version_strategies`.

`flushGroup()` behavior:

1. Ask `PerModelSettingResolver` to forget that group from persistent and L1 cache.
2. Forget derived caches that depend on group listing when needed.
3. Run group-specific registered invalidators.

`flushAll()` remains broad:

1. Clear all resolver L1 and persistent settings cache keys.
2. Forget legacy settings keys.
3. Reset `HasVersions::resetVersionStrategyCache()`.
4. Invalidate `Setting` model cache through `HasCache`.
5. Run all registered invalidators.

## Observer Design

`SettingObserver` must stop calling `flushAll()` for normal model changes.

Target behavior:

```php
public function saved(Setting $setting): void
{
    app(SettingsCacheCoordinator::class)->flushSetting($setting);
}

public function deleted(Setting $setting): void
{
    app(SettingsCacheCoordinator::class)->flushSetting($setting);
}
```

This is the core behavior change: normal UI/admin edits invalidate the precise group, not every setting group.

## Direct And Bulk Updates

Laravel does not fire model events for query-builder direct updates such as:

```php
Setting::query()->where('name', 'x')->update(['value' => true]);
```

For this first change, direct/bulk update callers must explicitly call the coordinator:

```php
app(SettingsCacheCoordinator::class)->flushGroup('translations');
```

or:

```php
app(SettingsCacheCoordinator::class)->flushAll();
```

Future work can add a dedicated settings write action/service to centralize DB writes and invalidation.

## Derived Caches

The coordinator must also invalidate settings-derived caches:

- `filament_settings_distinct_group_name`
  - forget on any setting create/delete/group rename.
  - safe to forget on every `flushSetting()`.
- `CacheManager::key('version_strategies')`
  - forget when `versioning` group is affected or during `flushAll()`.
- `HasVersions::resetVersionStrategyCache()`
  - reset when `versioning` group is affected or during `flushAll()`.
- `CacheManager::key('settings')`
  - forget as legacy compatibility.
- `CacheManager::key('settings', 'by_name')`
  - forget as legacy compatibility.

## Testing

Add or update focused tests:

1. `PerModelSettingResolver` caches and resolves one group with `{app}:settings:group:{group}`.
2. `value($name)` resolves through `settings:name_index`.
3. `flushGroup('translations')` forgets only `translations`, not `erp`.
4. Saving a `Setting` in `translations` invalidates only `translations` group cache.
5. Changing `group_name` from `base` to `erp` invalidates both groups.
6. Deleting a `Setting` invalidates its group and name index.
7. `flushAll()` keeps legacy behavior and clears all known settings keys.
8. Versioning group change resets `HasVersions` L1 and legacy `version_strategies`.
9. Filament distinct group cache is forgotten after `flushSetting()`.

Run minimum relevant tests:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
php artisan test --compact Modules/Core/tests/Unit/Helpers/HasVersionsTest.php
php artisan test --compact Modules/Core/tests/Feature/Filament/TablesTest.php
```

Run formatting after implementation:

```bash
vendor/bin/pint --dirty
```

## Migration Strategy

Step 1 keeps caller compatibility:

- Existing callers using `value($name)` continue to work.
- New grouped cache keys are internal to the resolver.
- `flushAll()` remains available.

Step 2 can migrate obvious callers later:

- `HasTranslations` reads from `translations`.
- `HasVersions` reads from `versioning`.
- `SoftDeletes` reads from `soft_deletes`.
- `HasApprovals` reads from `moderation`.
- `ErpCompanySettings` reads from `erp`.

This second step is optional and should happen after the group-cache refactor is stable.
