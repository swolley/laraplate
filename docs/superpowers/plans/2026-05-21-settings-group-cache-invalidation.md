# Settings Group Cache Invalidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Change settings invalidation so normal `Setting` save/delete flushes only the affected `group_name` cache while preserving the existing name-based read API.

**Architecture:** `PerModelSettingResolver` will store settings by group plus a lightweight `name => group_name` index. `SettingsCacheCoordinator` will expose group-level invalidation and `SettingObserver` will call `flushSetting()` instead of `flushAll()` for normal Eloquent changes.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Laravel cache, Eloquent model observers.

---

## File Map

- Modify: `Modules/Core/app/Services/PerModelSettingResolver.php`
  - Owns settings read-through cache and in-request memoization.
  - Add grouped cache keys, name index cache, group flush methods, and legacy compatibility.
- Modify: `Modules/Core/app/Services/SettingsCacheCoordinator.php`
  - Owns invalidation orchestration.
  - Add `flushSetting()`, `flushGroup()`, `flushGroups()`, derived cache invalidation, and versioning-specific reset.
- Modify: `Modules/Core/app/Observers/SettingObserver.php`
  - Replace normal `flushAll()` calls with `flushSetting($setting)`.
- Modify: `Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php`
  - Cover grouped cache read and name-index compatibility.
- Modify: `Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php`
  - Cover group-only invalidation, observer behavior, group rename, delete, and derived caches.
- Modify: `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`
  - Update expectations from global settings key invalidation to grouped settings key invalidation.
- Modify if needed: `Modules/Core/tests/Unit/Console/WarmCacheCommandTest.php`
  - Only if existing expectations around `CacheManager::key('settings')` fail after compatibility changes.
- No schema changes.
- No new dependencies.

---

### Task 1: Resolver Group Cache API

**Files:**
- Modify: `Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php`
- Modify: `Modules/Core/app/Services/PerModelSettingResolver.php`

- [ ] **Step 1: Add failing resolver tests for grouped persistent keys**

Append these tests to `Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php`:

```php
use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\CacheManager;

it('stores each settings group under a group-specific cache key', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translation_group_cache_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse();

    $group = $resolver->group('translations');

    expect($group->has('translation_group_cache_test'))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeTrue();
});

it('resolves name based reads through the settings name index', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'indexed_setting_test',
        'value' => 'from-index',
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeFalse()
        ->and($resolver->string('indexed_setting_test', 'fallback'))->toBe('from-index')
        ->and(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('base')))->toBeTrue();
});

it('flushes one group without clearing another loaded group', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translations_group_flush_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'erp_group_flush_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'erp',
        'description' => 'test',
    ]);

    $resolver->flush();
    $resolver->group('translations');
    $resolver->group('erp');

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue();

    $resolver->flushGroup('translations');

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue();
});
```

- [ ] **Step 2: Run resolver tests to verify failure**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php
```

Expected: failure because `groupCacheKey()`, `nameIndexCacheKey()`, and `flushGroup()` do not exist yet.

- [ ] **Step 3: Replace resolver implementation with grouped cache support**

Edit `Modules/Core/app/Services/PerModelSettingResolver.php`.

Keep the class name and typed helpers, but replace the single `$loaded_settings` collection with this structure:

```php
/**
 * @var array<string, Collection<string, Setting>>
 */
private array $loaded_groups = [];

/**
 * @var array<string, string>|null
 */
private ?array $name_index = null;
```

Add these public/static methods:

```php
public function flushGroup(string $group_name): void
{
    Cache::forget(self::groupCacheKey($group_name));
    unset($this->loaded_groups[$group_name]);
}

/**
 * @param  array<int, string>  $group_names
 */
public function flushGroups(array $group_names): void
{
    foreach (array_unique(array_filter($group_names)) as $group_name) {
        $this->flushGroup((string) $group_name);
    }
}

public function flushNameIndex(): void
{
    Cache::forget(self::nameIndexCacheKey());
    $this->name_index = null;
}

public static function groupCacheKey(string $group_name): string
{
    return CacheManager::key('settings', 'group', $group_name);
}

public static function nameIndexCacheKey(): string
{
    return CacheManager::key('settings', 'name_index');
}

public static function legacyTableCacheKey(): string
{
    return CacheManager::key('settings');
}
```

Update `collection()`, `group()`, `value()`, `flush()`, and private helpers to this behavior:

```php
public function collection(): Collection
{
    $settings = new Collection();

    foreach (array_unique(array_values($this->nameIndex())) as $group_name) {
        $settings = $settings->merge($this->group($group_name));
    }

    return $settings;
}

public function group(string $group_name): Collection
{
    return $this->loaded_groups[$group_name] ??= Cache::rememberForever(
        self::groupCacheKey($group_name),
        static fn (): Collection => Setting::query()
            ->where('group_name', $group_name)
            ->get()
            ->keyBy('name'),
    );
}

public function value(string $name, mixed $default = null): mixed
{
    $group_name = $this->nameIndex()[$name] ?? null;

    if ($group_name === null) {
        return $default;
    }

    $setting = $this->group($group_name)->get($name);

    if ($setting === null) {
        return $default;
    }

    return $setting->value ?? $default;
}

public function flush(): void
{
    Cache::forget(self::cacheKey());
    Cache::forget(self::legacyTableCacheKey());
    $this->flushNameIndex();

    foreach (array_keys($this->loaded_groups) as $group_name) {
        Cache::forget(self::groupCacheKey($group_name));
    }

    $this->loaded_groups = [];
}

/**
 * @return array<string, string>
 */
private function nameIndex(): array
{
    if ($this->name_index !== null) {
        return $this->name_index;
    }

    $this->name_index = Cache::rememberForever(
        self::nameIndexCacheKey(),
        static fn (): array => Setting::query()
            ->pluck('group_name', 'name')
            ->toArray(),
    );

    return $this->name_index;
}
```

Keep the existing `cacheKey()` method returning `CacheManager::key('settings', 'by_name')` for legacy compatibility.

- [ ] **Step 4: Run resolver tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php
```

Expected: PASS.

---

### Task 2: Coordinator Group Invalidation

**Files:**
- Modify: `Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php`
- Modify: `Modules/Core/app/Services/SettingsCacheCoordinator.php`

- [ ] **Step 1: Add failing coordinator tests**

Append these tests to `Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php`:

```php
use Modules\Core\Cache\CacheManager;
use Modules\Core\Helpers\HasVersions;

it('flushes only the affected settings group', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    $translation_setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'coordinator_translation_group_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'coordinator_erp_group_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'erp',
        'description' => 'test',
    ]);

    $resolver->flush();
    $resolver->group('translations');
    $resolver->group('erp');

    app(SettingsCacheCoordinator::class)->flushSetting($translation_setting);

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeFalse();
});

it('flushes old and new groups when a setting moves groups', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'coordinator_group_move_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver->flush();
    $resolver->group('base');

    $setting->setForcedApprovalUpdate(true);
    $setting->group_name = 'erp';
    $setting->save();

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('base')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeFalse();
});

it('forgets derived settings caches when flushing a setting', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'coordinator_derived_cache_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    Cache::put('filament_settings_distinct_group_name', ['base' => 'base'], 300);
    Cache::forever(PerModelSettingResolver::legacyTableCacheKey(), collect([$setting]));
    Cache::forever(PerModelSettingResolver::cacheKey(), collect([$setting]));

    app(SettingsCacheCoordinator::class)->flushSetting($setting);

    expect(Cache::has('filament_settings_distinct_group_name'))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::legacyTableCacheKey()))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::cacheKey()))->toBeFalse();
});

it('resets versioning caches only when the versioning group is affected', function (): void {
    $versioning_setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'version_strategy_coordinator_test',
        'value' => false,
        'type' => SettingTypeEnum::Json,
        'group_name' => 'versioning',
        'description' => 'test',
    ]);

    Cache::forever(CacheManager::key('version_strategies'), collect([$versioning_setting]));

    app(SettingsCacheCoordinator::class)->flushSetting($versioning_setting);

    expect(Cache::has(CacheManager::key('version_strategies')))->toBeFalse();
});
```

- [ ] **Step 2: Run coordinator tests to verify failure**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
```

Expected: failure because `flushSetting()` and `legacyTableCacheKey()` are not fully wired yet.

- [ ] **Step 3: Implement coordinator group methods**

Edit `Modules/Core/app/Services/SettingsCacheCoordinator.php`.

Add these constants/properties:

```php
private const string FILAMENT_GROUP_OPTIONS_CACHE_KEY = 'filament_settings_distinct_group_name';

/**
 * @var array<string, array<int, callable(): void>>
 */
private array $group_invalidators = [];
```

Add a group invalidator registration method:

```php
public function registerGroupInvalidator(string $group_name, callable $invalidator): void
{
    $this->group_invalidators[$group_name][] = $invalidator;
}
```

Add these invalidation methods:

```php
public function flushSetting(Setting $setting): void
{
    $groups = [$setting->group_name];

    $original_group = $setting->getOriginal('group_name');

    if (is_string($original_group) && $original_group !== '' && $original_group !== $setting->group_name) {
        $groups[] = $original_group;
    }

    $this->flushGroups($groups);
    $this->flushNameIndex();
    $this->flushDerivedSettingsCaches();
}

/**
 * @param  array<int, string|null>  $group_names
 */
public function flushGroups(array $group_names): void
{
    foreach (array_unique(array_filter($group_names)) as $group_name) {
        $this->flushGroup((string) $group_name);
    }
}

public function flushGroup(string $group_name): void
{
    if (app()->bound(PerModelSettingResolver::class)) {
        app(PerModelSettingResolver::class)->flushGroup($group_name);
    } else {
        Cache::forget(PerModelSettingResolver::groupCacheKey($group_name));
    }

    if ($group_name === 'versioning') {
        $this->flushVersioningCaches();
    }

    foreach ($this->group_invalidators[$group_name] ?? [] as $invalidator) {
        $invalidator();
    }
}

private function flushNameIndex(): void
{
    if (app()->bound(PerModelSettingResolver::class)) {
        app(PerModelSettingResolver::class)->flushNameIndex();
    } else {
        Cache::forget(PerModelSettingResolver::nameIndexCacheKey());
    }
}

private function flushVersioningCaches(): void
{
    Cache::forget(CacheManager::key('version_strategies'));
    HasVersions::resetVersionStrategyCache();
}

private function flushDerivedSettingsCaches(): void
{
    Cache::forget(self::FILAMENT_GROUP_OPTIONS_CACHE_KEY);
    Cache::forget(PerModelSettingResolver::cacheKey());
    Cache::forget(PerModelSettingResolver::legacyTableCacheKey());
}
```

Update `flushAll()` so it calls resolver `flush()`, `flushVersioningCaches()`, `flushDerivedSettingsCaches()`, `(new Setting())->invalidateCache()`, and then all global invalidators. Keep existing global `registerInvalidator()` behavior.

- [ ] **Step 4: Run coordinator tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
```

Expected: PASS.

---

### Task 3: Observer Uses Surgical Invalidation

**Files:**
- Modify: `Modules/Core/app/Observers/SettingObserver.php`
- Modify: `Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php`

- [ ] **Step 1: Add observer test that proves unrelated group survives**

Append to `Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php`:

```php
it('setting observer invalidates only the saved setting group', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    $translation_setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'observer_translation_group_test',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'observer_erp_group_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'erp',
        'description' => 'test',
    ]);

    $resolver->flush();
    $resolver->group('translations');
    $resolver->group('erp');

    $translation_setting->setForcedApprovalUpdate(true);
    $translation_setting->value = true;
    $translation_setting->save();

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue();
});
```

- [ ] **Step 2: Run coordinator tests to verify failure**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
```

Expected: failure because observer still calls `flushAll()`, clearing the `erp` group.

- [ ] **Step 3: Update SettingObserver**

Change `Modules/Core/app/Observers/SettingObserver.php` to:

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

Keep the existing `saving()` normalization for empty string values.

- [ ] **Step 4: Run coordinator tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
```

Expected: PASS.

---

### Task 4: Update Existing Versioning Expectations

**Files:**
- Modify: `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`

- [ ] **Step 1: Update global-cache invalidation tests to group-cache expectations**

In `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`, replace expectations that saving/deleting any setting clears `PerModelSettingResolver::cacheKey()` with expectations around:

```php
$cache_key = PerModelSettingResolver::groupCacheKey('versioning');
```

For the generic "any Setting" test, use:

```php
$cache_key = PerModelSettingResolver::groupCacheKey('base');
```

Keep the test intent: saving/deleting a setting invalidates the affected group. Do not assert that unrelated groups are flushed there; that is covered by coordinator tests.

- [ ] **Step 2: Run HasVersions tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Helpers/HasVersionsTest.php
```

Expected: PASS.

---

### Task 5: Run Focused Regression Suite

**Files:**
- No code edits unless a focused test exposes a real compatibility failure.

- [ ] **Step 1: Run resolver tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php
```

Expected: PASS.

- [ ] **Step 2: Run coordinator tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
```

Expected: PASS.

- [ ] **Step 3: Run versioning tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Helpers/HasVersionsTest.php
```

Expected: PASS.

- [ ] **Step 4: Run Filament settings table tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Feature/Filament/TablesTest.php
```

Expected: PASS.

- [ ] **Step 5: Run warm cache tests**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Console/WarmCacheCommandTest.php
```

Expected: PASS or a clear failure around the legacy `{app}:settings` warm key. If it fails, keep compatibility by forgetting the legacy key in coordinator/resolver rather than changing warm behavior broadly.

---

### Task 6: Formatting And Final Verification

**Files:**
- Format changed PHP files only through Pint dirty mode.

- [ ] **Step 1: Run Pint**

Run:

```bash
vendor/bin/pint --dirty
```

Expected: completes successfully and formats only dirty PHP files.

- [ ] **Step 2: Re-run focused tests after formatting**

Run:

```bash
php artisan test --compact Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php
php artisan test --compact Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php
php artisan test --compact Modules/Core/tests/Unit/Helpers/HasVersionsTest.php
php artisan test --compact Modules/Core/tests/Feature/Filament/TablesTest.php
php artisan test --compact Modules/Core/tests/Unit/Console/WarmCacheCommandTest.php
```

Expected: all pass.

- [ ] **Step 3: Review git diff**

Run:

```bash
git diff -- Modules/Core/app/Services/PerModelSettingResolver.php Modules/Core/app/Services/SettingsCacheCoordinator.php Modules/Core/app/Observers/SettingObserver.php Modules/Core/tests/Unit/Services/PerModelSettingResolverTest.php Modules/Core/tests/Unit/Services/SettingsCacheCoordinatorTest.php Modules/Core/tests/Unit/Helpers/HasVersionsTest.php docs/superpowers/specs/2026-05-21-settings-group-cache-invalidation-design.md docs/superpowers/plans/2026-05-21-settings-group-cache-invalidation.md
```

Expected: diff only covers the approved settings group cache invalidation work and docs.

---

## Self-Review

- Spec coverage:
  - Group-level persistent cache: Task 1.
  - Name-based backward compatibility: Task 1.
  - Coordinator `flushSetting`, `flushGroup`, `flushGroups`, `flushAll`: Task 2.
  - Observer no longer calls `flushAll()` for normal changes: Task 3.
  - Versioning derived cache reset: Task 2 and Task 4.
  - Filament group options cache invalidation: Task 2 and Task 5.
  - Direct/bulk update caveat: documented in spec, no implementation in this plan.
- Placeholder scan: no unresolved placeholders.
- Type consistency:
  - `groupCacheKey()`, `nameIndexCacheKey()`, `legacyTableCacheKey()`, `flushGroup()`, `flushGroups()`, and `flushNameIndex()` are introduced in Task 1 before Task 2 uses them.
  - `flushSetting()` is introduced in Task 2 before Task 3 observer calls it.
