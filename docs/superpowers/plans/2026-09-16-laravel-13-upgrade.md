# Laravel 12 to 13 Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the application and all six native modules from Laravel 12 to Laravel 13 in place, keeping git history, submodules, tooling and behaviour unchanged.

**Architecture:** In-place upgrade, not a migration into `../laraplate13`. That folder is only the **reference lock**: its `composer.lock` is a resolved Laravel 13 dependency set built from every module's requirements, and each task checks the real lock against it. `wikimedia/composer-merge-plugin` ANDs every `Modules/*/composer.json` into the root solve, so all seven manifests are edited first and resolved once. Framework upgrade and test-runner upgrade (Pest 5 / PHPUnit 13) are separate tasks so a red suite has one cause.

**Tech Stack:** PHP 8.5, Laravel 13, Filament 5, Livewire 4, Pest, nwidart/laravel-modules 13, Composer merge-plugin.

**Spec:** Official upgrade guide `https://laravel.com/docs/13.x/upgrade` (source `laravel/docs` branch `13.x`, `upgrade.md`). Reference lock: `/srv/http/laraplate-stack/laraplate13/composer.lock` (`laravel/framework v13.32.0`).

## Global Constraints

- Upgrade in place. Do not copy code into `../laraplate13`; do not commit anything from it.
- Never import from the reference manifest: `livewire/flux`, `livewire/blaze`, `laravel/chisel`, `laravel/pao`. They belong to the starter kit, not to Laraplate (`livewire/flux` is commercial, this repo is AGPL). No new dependency without user approval. Exception approved by the user on 2026-09-16: `pestphp/pest-plugin-phpstan` (Task 7), so PHPStan understands Pest test files instead of reporting undeclared `$this` properties.
- The reference manifest lacks three packages Laraplate still uses: `laravel/sanctum` (root), `laravel/socialite` (Core: `Actions/Users/HandleSocialLoginAction.php`, `Auth/Providers/SocialiteProvider.php`), `babenkoivan/elastic-scout-driver-plus` (Core: `Search/Engines/ElasticsearchEngine.php`, `Search/Traits/Searchable.php`, `Providers/SearchServiceProvider.php`). They stay. Verified on 2026-09-16 with `composer require --dry-run` against a copy of the reference lock: `babenkoivan/elastic-scout-driver-plus:^7.0` adds `v7.0.0` with no other change; `laravel/socialite:^5.31` resolves **only with `-W`**, because `league/oauth1-client` still requires `guzzlehttp/guzzle ^6|^7` while the reference lock picked Guzzle 8. With `-W` Composer moves `guzzlehttp/guzzle` 8.2.0 to 7.15.5, `guzzlehttp/promises` 3 to 2.5.3, `guzzlehttp/psr7` 3 to 2.13.1 (Laravel 13 accepts `^7.8.2 || ^8.0`, so this is supported). Every solve in this plan therefore uses `-W`, and Guzzle 7 is the expected result.
- `symfony/http-client` is removed from the root manifest. No PHP file in `app/`, `config/`, `routes/` or any module references `Symfony\Component\HttpClient` or `Symfony\Contracts\HttpClient`; it was added with the initial scaffold (`f5b44bf`) and nothing else requires it (`composer why` lists only the root). PSR-18 discovery used by `elastic/transport` resolves to Guzzle, which Laravel already ships. The user asked about it on 2026-09-16; the removal is part of this plan.
- `mtrajano/laravel-swagger` resolved to `v0.4.0` in the reference lock (a downgrade from `v0.6.4`). It is runtime code in Core (`SwaggerGenerateCommand`, `Overrides/ModuleDocGenerator.php`, `Overrides/ModuleDocRoute.php`, `Providers/RouteServiceProvider.php`). Task 4 has a decision gate for it; never accept the downgrade silently.
- Local PHP lacks `ext-oci8` and may lack `ext-imagick` (required by the reference manifest, not by Laraplate): pass `--ignore-platform-req=<ext>` only for missing extensions, never `--ignore-platform-reqs`.
- Keep `config/cache.php` without `serializable_classes` (framework reads `null`, current behaviour) and `config/session.php` without `serialization` (framework default `php`, sessions stay valid). Hardening both is a separate decision, not part of this plan.
- The CSRF middleware exclusion in the `web` group is intentional during development (`TODO` in `bootstrap/app.php`). It must survive the upgrade: `array_diff` in `Illuminate\Foundation\Configuration\Middleware` compares class strings, and Laravel 13's default `web` group lists `PreventRequestForgery`, so `remove: [ValidateCsrfToken::class]` silently stops removing anything.
- Every submodule work happens on branch `upgrade/laravel-13` inside that submodule; the root repo also uses `upgrade/laravel-13`. Touch only files named in the task. Commit trailer: `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Commands run from `/srv/http/laraplate-stack/laraplate`. Tests: `php artisan test --compact <path>`. Formatting: `vendor/bin/pint --dirty --format agent`.
- `ext-oci8` is missing locally: every `composer update` / `composer require` passes `--ignore-platform-req=ext-oci8` (the reference manifest does the same through `config.platform.ext-oci8`).

---

### Task 1: Pre-flight and baseline

**Files:**
- None modified.

**Interfaces:**
- Produces: `storage/logs/upgrade-l13/baseline-failures.txt` (list of tests already failing on Laravel 12), branches `upgrade/laravel-13` in root and in the six submodules.

- [ ] **Step 1: Check the trees are clean**

Run:
```bash
git status --short
git submodule foreach --quiet 'echo "$name $(git status --short | wc -l)"'
```
Expected: root shows no `M`/`??` outside this plan file and its index line; every submodule prints `0`.
If any submodule is dirty (on 2026-09-16 all six were: AI 5, CMS 13, Core 18, ERP 52, MES 21, SAO 33 files), STOP and ask the user to commit or stash that work. Do not stash it yourself.

- [ ] **Step 2: Create the branches**

```bash
git switch -c upgrade/laravel-13
git submodule foreach 'git switch -c upgrade/laravel-13'
```
Expected: seven `Switched to a new branch 'upgrade/laravel-13'` lines.

- [ ] **Step 3: Record the Laravel 12 baseline**

```bash
mkdir -p storage/logs/upgrade-l13
php artisan test --compact 2>&1 | tee "storage/logs/upgrade-l13/baseline-l12.txt" | tail -5
grep -E '^\s+(FAIL|⨯)' "storage/logs/upgrade-l13/baseline-l12.txt" > "storage/logs/upgrade-l13/baseline-failures.txt" || true
```
`storage/logs/` is gitignored, so these logs never reach a commit. Expected: the summary line with pass/fail counts. Tests failing here are not caused by the upgrade; later tasks compare against this file.

- [ ] **Step 4: Confirm Boost 2 is installed**

Run: `composer show laravel/boost | grep versions`
Expected: `versions : * v2.9.0` (or newer 2.x). This makes the Boost `/upgrade-laravel-v13` slash command available as a cross-check for Tasks 5 and 7.

---

### Task 2: Bump the five light modules

**Files:**
- Modify: `Modules/AI/composer.json`, `Modules/CMS/composer.json`, `Modules/ERP/composer.json`, `Modules/MES/composer.json`, `Modules/SAO/composer.json` (the `require` block only)

**Interfaces:**
- Produces: every light module requires `laravel/framework: ^13.0`. Task 4 depends on it: one module left on `^12.0` pins the whole solve to Laravel 12.

- [ ] **Step 1: Edit the constraints**

In each of the five files change:
```json
"laravel/framework": "^12.0",
```
to:
```json
"laravel/framework": "^13.0",
```
Additionally, in `Modules/AI/composer.json` change `"neuron-core/neuron-ai": "^3.2"` to `"neuron-core/neuron-ai": "^3.16"` (the version both locks already use; keeps the module manifest honest). Leave `Modules/CMS` `php-ffmpeg/php-ffmpeg: ^1.4`, `ext-gd`, `ext-exif` untouched.

- [ ] **Step 2: Validate the manifests**

```bash
for m in AI CMS ERP MES SAO; do composer validate --no-check-publish --no-check-lock "Modules/$m/composer.json" || echo "INVALID $m"; done
```
Expected: five `is valid` lines, no `INVALID`.

- [ ] **Step 3: Commit inside each submodule**

```bash
for m in AI CMS ERP MES SAO; do
  git -C "Modules/$m" add composer.json
  git -C "Modules/$m" commit -m "chore(deps): require Laravel 13

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
done
```

---

### Task 3: Bump Core

**Files:**
- Modify: `Modules/Core/composer.json` (the `require` block only)

**Interfaces:**
- Consumes: nothing.
- Produces: Core constraints that admit the reference lock versions.

- [ ] **Step 1: Edit the constraints**

Apply exactly these changes in `Modules/Core/composer.json` `require` (every target was checked against the reference lock and Packagist metadata):

| package | from | to | why |
|---|---|---|---|
| `laravel/framework` | `^12.0` | `^13.0` | target |
| `babenkoivan/elastic-scout-driver` | `^4.0` | `^6.0` | v4 has no Scout 11 / L13 line |
| `babenkoivan/elastic-scout-driver-plus` | `^5.1` | `^7.0` | v7 requires driver `^6.0` |
| `elasticsearch/elasticsearch` | `^8.19` | `^9.5` | pulled by elastic-client 4 |
| `hedii/laravel-gelf-logger` | `^10.1` | `^13.0` | 13.0.0 is the first L13 release, L13 only |
| `laravel/scout` | `^10.25` | `^11.7` | reference lock |
| `overtrue/laravel-versionable` | `^5.5` | `^6.0` | v6 requires `laravel/framework ^13.0` |
| `spatie/eloquent-sortable` | `^4.5.2` | `^5.0` | 5.0.1 is the first L13 release |
| `spatie/laravel-permission` | `^6.25` | `^8.3` | reference lock (L13 support starts at 7.2.1) |
| `staudenmeir/laravel-adjacency-list` | `^1.25.2` | `^1.26.1` | 1.26 requires `illuminate/database ^13.0` |
| `typesense/typesense-php` | `^5.2` | `^6.0` | reference lock |
| `wotz/laravel-swagger-ui` | `^1.2` | `^2.1` | 2.1.0 is the first L13 release |
| `yajra/laravel-oci8` | `^12.11` | `^13.14` | v13 is L13 only |

Leave unchanged (already L13 compatible at the current floor): `doctrine/dbal`, `filament/spatie-laravel-media-library-plugin` (L13 from 5.4.0), `lab404/laravel-impersonate`, `laravel/fortify` (from 1.34.1), `laravel/horizon` (from 5.44.0), `laravel/socialite` (from 5.24.3), `livewire/livewire` (from 4.2.0), `matanyadaev/laravel-eloquent-spatial` (from 4.7.0), `spatie/fork`, `spatie/laravel-medialibrary` (from 11.21.0), `stephenlake/laravel-approval`, all `ext-*`. Leave `mtrajano/laravel-swagger: ^0.6.4` as is: Task 4 decides.

- [ ] **Step 2: Validate**

Run: `composer validate --no-check-publish --no-check-lock Modules/Core/composer.json`
Expected: `is valid`.

- [ ] **Step 3: Commit inside Core**

```bash
git -C Modules/Core add composer.json
git -C Modules/Core commit -m "chore(deps): require Laravel 13 and its compatible package majors

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Bump root and resolve the lock

**Files:**
- Modify: `composer.json` (`require`), `composer.lock`

**Interfaces:**
- Consumes: Tasks 2 and 3 manifests.
- Produces: `composer.lock` on `laravel/framework 13.x`, with `pestphp/pest 4.x` still in place (Task 6 moves it).

- [ ] **Step 1: Edit the root constraints**

In `composer.json` `require`:

| package | from | to |
|---|---|---|
| `laravel/framework` | `^12.59` | `^13.32` |
| `laravel/tinker` | `^2.11.1` | `^3.0` |
| `nwidart/laravel-modules` | `^12.0.5` | `^13.0` |
| `symfony/http-client` | `^7.4.9` | removed (see Global Constraints) |

Leave `coolsam/modules`, `filament/filament`, `joshbrw/laravel-module-installer`, `laravel/sanctum` and all of `require-dev` unchanged in this task.

- [ ] **Step 2: Dry-run the solve**

```bash
composer update -W --dry-run --no-scripts --ignore-platform-req=ext-oci8 2>&1 | tee "storage/logs/upgrade-l13/l13-dry.txt" | head -30
```
Expected: `Lock file operations: ...` and no `Your requirements could not be resolved`. The solve takes several minutes; run it in the background if needed.
If it fails, read the first `Problem` block, fix the named constraint in the manifest that owns it (use the table in Task 3 / this task), commit that fix in the owning repo, rerun. Do not add `--ignore-platform-reqs` or loosen `minimum-stability`.

- [ ] **Step 3: Resolve for real**

```bash
composer update -W --ignore-platform-req=ext-oci8
```
Expected: ends with `package:discover` output and `filament:upgrade` without errors.

- [ ] **Step 4: Compare with the reference lock**

```bash
php -r '
$ref = []; $l = json_decode(file_get_contents("../laraplate13/composer.lock"), true);
foreach (array_merge($l["packages"], $l["packages-dev"]) as $p) { $ref[$p["name"]] = $p["version"]; }
$cur = []; $l = json_decode(file_get_contents("composer.lock"), true);
foreach (array_merge($l["packages"], $l["packages-dev"]) as $p) { $cur[$p["name"]] = $p["version"]; }
foreach ($cur as $n => $v) { if (isset($ref[$n]) && ltrim($ref[$n], "v") !== ltrim($v, "v")) { printf("%-50s ours=%-12s ref=%s\n", $n, $v, $ref[$n]); } }
'
```
Expected: `laravel/framework` on `v13.x`; `laravel/socialite v5.31.x`, `babenkoivan/elastic-scout-driver-plus v7.0.x` and `laravel/sanctum v4.3.x` present (the reference lock does not have them, so the script prints nothing for them: check them with `composer show laravel/socialite babenkoivan/elastic-scout-driver-plus laravel/sanctum | grep versions`); `symfony/http-client` absent unless another package now requires it (`composer why symfony/http-client`). Differences are acceptable only for: Guzzle 7 instead of 8 (`guzzlehttp/guzzle`, `guzzlehttp/promises`, `guzzlehttp/psr7`, required by Socialite's `league/oauth1-client`), Pest/PHPUnit/`sebastian/*`/`peck` and their transitive deps (Task 6), patch-level drift from newer releases, and `mtrajano/laravel-swagger` (next step). Any other major-version difference: stop and report it.

- [ ] **Step 5: Decision gate for `mtrajano/laravel-swagger`**

Run: `composer show mtrajano/laravel-swagger | grep versions`
- If it is `v0.6.4`: nothing to do.
- If the solve failed on it, or composer picked an older version: run `composer why-not mtrajano/laravel-swagger 0.6.4` and report the blocking package to the user (expected cause: `phpdocumentor/reflection-docblock ^6`, which `v0.6.4` excludes with `^4.3|^5.2`). STOP and ask the user whether to (a) accept the downgrade after Step 6 proves the swagger command still works, (b) replace the package, or (c) drop swagger generation. Do not choose alone.

- [ ] **Step 6: Boot and smoke check**

```bash
php artisan --version
php artisan about --only=environment
php artisan route:list --except-vendor --path=api | head -20
php artisan module:list
```
Expected: `Laravel Framework 13.x`; routes listed; all six modules `Enabled`. Then run the swagger command tests:
```bash
php artisan test --compact --filter=Swagger
```
Expected: PASS (or same result as in `baseline-failures.txt`).

- [ ] **Step 7: Commit (root)**

```bash
git add composer.json composer.lock
git commit -m "chore(deps): upgrade to Laravel 13

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: CSRF middleware rename (`PreventRequestForgery`)

**Files:**
- Modify: `bootstrap/app.php:13-14,59,81`
- Modify: `app/Providers/Filament/AdminPanelProvider.php:23,136`
- Modify: `config/sanctum.php:83`
- Test: `tests/Feature/Filament/AdminPanelMiddlewareTest.php`
- Test: `tests/Feature/Http/MiddlewareGroupsTest.php` (create)

**Interfaces:**
- Consumes: Laravel 13 installed (Task 4).
- Produces: all CSRF references point to `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`; the `web` group keeps CSRF disabled as before.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Http/MiddlewareGroupsTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Router;

/**
 * @return list<string>
 */
function middlewareGroup(string $group): array
{
    return app(Router::class)->getMiddlewareGroups()[$group] ?? [];
}

it('keeps request forgery protection out of the web group during development', function (): void {
    // Laravel 13 lists PreventRequestForgery in the default web group, and group removal
    // compares class strings: removing a deprecated alias silently removes nothing.
    expect(middlewareGroup('web'))
        ->not->toContain(PreventRequestForgery::class)
        ->not->toContain(ValidateCsrfToken::class)
        ->not->toContain(VerifyCsrfToken::class);
});

it('protects the auth group against request forgery', function (): void {
    expect(middlewareGroup('auth'))
        ->toContain(PreventRequestForgery::class)
        ->not->toContain(VerifyCsrfToken::class);
});
```

Append to `tests/Feature/Filament/AdminPanelMiddlewareTest.php`:
```php
it('protects the admin panel with the non-deprecated forgery middleware', function (): void {
    expect(Filament::getPanel('admin')->getMiddleware())
        ->toContain(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
        ->not->toContain(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});
```

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact tests/Feature/Http/MiddlewareGroupsTest.php tests/Feature/Filament/AdminPanelMiddlewareTest.php`
Expected: 3 FAIL: the web group contains `PreventRequestForgery` (the silent regression), the auth group and the panel still list `VerifyCsrfToken`.

- [ ] **Step 3: Fix `bootstrap/app.php`**

Replace the two imports:
```php
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
```
with:
```php
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
```
In the `web(remove: [...])` list replace `ValidateCsrfToken::class,` with `PreventRequestForgery::class,`.
In the `appendToGroup('auth', [...])` list replace `VerifyCsrfToken::class,` with `PreventRequestForgery::class,`.

- [ ] **Step 4: Fix the panel and Sanctum config**

`app/Providers/Filament/AdminPanelProvider.php`: replace
`use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;` with `use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;`
and `VerifyCsrfToken::class,` in `->middleware([...])` with `PreventRequestForgery::class,`.

`config/sanctum.php` line 83: replace
```php
'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
```
with
```php
'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
```

- [ ] **Step 5: Check nothing else references the aliases**

Run: `grep -rn "VerifyCsrfToken\|ValidateCsrfToken" app bootstrap config routes Modules/*/app Modules/*/config Modules/*/routes`
Expected: no output. If a module matches, fix it the same way and commit inside that submodule.

- [ ] **Step 6: Run the tests and see them pass**

Run: `php artisan test --compact tests/Feature/Http/MiddlewareGroupsTest.php tests/Feature/Filament/AdminPanelMiddlewareTest.php`
Expected: all PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add bootstrap/app.php app/Providers/Filament/AdminPanelProvider.php config/sanctum.php tests/Feature/Http/MiddlewareGroupsTest.php tests/Feature/Filament/AdminPanelMiddlewareTest.php
git commit -m "fix(http): use PreventRequestForgery and keep the web group CSRF exclusion working on Laravel 13

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: Full suite on Laravel 13 (Pest 4)

**Files:**
- Modify: only files a failing test points to; each fix is its own commit in the owning repo.

**Interfaces:**
- Consumes: Tasks 4 and 5.
- Produces: suite result equal to `baseline-failures.txt` (no new failures).

- [ ] **Step 1: Run the suite**

```bash
php artisan test --compact 2>&1 | tee "storage/logs/upgrade-l13/l13-pest4.txt" | tail -5
grep -E '^\s+(FAIL|⨯)' "storage/logs/upgrade-l13/l13-pest4.txt" > "storage/logs/upgrade-l13/l13-failures.txt" || true
diff "storage/logs/upgrade-l13/baseline-failures.txt" "storage/logs/upgrade-l13/l13-failures.txt"
```
Expected: `diff` prints nothing. Every `>` line is a regression to fix in Step 2.

- [ ] **Step 2: Fix regressions one at a time**

For each new failure, use superpowers:systematic-debugging. Known Laravel 13 changes to check first, in this order:
1. Upstream package majors from Task 3 (permission 6 to 8, versionable 5 to 6, scout 10 to 11, elastic driver 4 to 6, typesense 5 to 6, gelf 10 to 13, swagger-ui 1 to 2): read the package `UPGRADE.md`/release notes under `vendor/<package>/` before changing code.
2. `upsert` now throws `InvalidArgumentException` on empty `uniqueBy` (callers in `Modules/Core/app/Seeding/SeedReconciler.php:98,111` already pass `[$column]`).
3. `Str` factories are reset between tests; UUID/ULID fakes must be set in each test.
4. `Js::from` no longer escapes unicode.
5. Eager-loaded relations are restored when model collections are unserialized (queued jobs).
6. Manager `extend` closures are bound to the manager (`$this` inside them changed).
7. Instantiating a model while it boots throws `LogicException`.
8. Polymorphic pivot table names are now pluralized for custom pivot classes: set `$table` explicitly on the pivot model.
9. Cache `Store` contract gained `touch()`: `Modules/Core/app/Cache/CacheManager.php` and `Repository.php` extend the framework classes, so they inherit it; a custom store implementing the interface directly would need it.

Each fix: failing test already exists (the regression), change code, rerun that test, `vendor/bin/pint --dirty --format agent`, commit in the owning repo with `fix(<scope>): <what> for Laravel 13`.

- [ ] **Step 3: Rerun the suite**

Repeat Step 1. Expected: `diff` prints nothing.

---

### Task 7: Pest 5 / PHPUnit 13 and toolchain

**Files:**
- Modify: `composer.json` (`require-dev`), `composer.lock`, `phpunit.xml` (only if Pest asks to migrate it), `phpstan.neon:5`

**Interfaces:**
- Consumes: green Laravel 13 suite (Task 6).
- Produces: dev toolchain matching the reference lock (`pestphp/pest v5.2.x`, `phpunit/phpunit 13.3.x`, `peckphp/peck v0.3.x`, `pestphp/pest-plugin-phpstan v5.2.x`) with the Pest PHPStan extension active.

- [ ] **Step 1: Bump the dev constraints**

In `composer.json` `require-dev`:

| package | from | to |
|---|---|---|
| `pestphp/pest` | `^4.7` | `^5.2` |
| `pestphp/pest-plugin-laravel` | `^4.1` | `^5.0` |
| `pestphp/pest-plugin-stressless` | `^4.0` | `^5.0` |
| `pestphp/pest-plugin-type-coverage` | `^4.0` | `^5.0` |
| `peckphp/peck` | `^0.1.3` | `^0.3.0` |
| `pestphp/pest-plugin-phpstan` | (new) | `^5.2` |

`pestphp/pest-plugin-phpstan` requires `pestphp/pest ^5.0.0` and `phpstan/phpstan ^2.2.5`, so it cannot land before this task.

- [ ] **Step 2: Resolve**

```bash
composer update -W --ignore-platform-req=ext-oci8 'pestphp/*' peckphp/peck 'phpunit/*' 'sebastian/*' 'phpstan/*'
```
Expected: pest `v5.x`, phpunit `13.x`, `pestphp/pest-plugin-phpstan v5.x`. If the solve fails on a package that is not in the reference lock (for example a reintroduced `nunomaduro/phpinsights`, which caps `sebastian/diff` at `^7`), STOP and report: do not remove packages without approval.

- [ ] **Step 3: Migrate the PHPUnit configuration if asked**

Run: `vendor/bin/pest --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`
If the output warns that the XML configuration validates against a deprecated schema, run `vendor/bin/pest --migrate-configuration` and review the `phpunit.xml` diff (only schema/attribute changes allowed; the `Unit`, `Integration`, `Feature` suites must stay).

- [ ] **Step 4: Enable the Pest PHPStan extension**

The repo has no `phpstan/extension-installer`, so the plugin's `extra.phpstan.includes` is not picked up automatically. In `phpstan.neon` line 5 replace:
```neon
    # - vendor/pestphp/pest-plugin-phpstan/extension.neon
```
with:
```neon
    - vendor/pestphp/pest-plugin-phpstan/extension.neon
```
Run: `composer test:types 2>&1 | tail -20`
Expected: no `Access to an undefined property` / `Undefined variable: $this` errors coming from Pest test files. If `phpstan.neon` has `ignoreErrors` entries that only existed to silence those, list them and remove them in this step (`composer test:types` must stay green after removal); leave every other entry alone.

- [ ] **Step 5: Full suite and quality gates**

```bash
php artisan test --compact 2>&1 | tail -5
composer test:lint
composer test:types
composer test:typos
composer test:licenses
```
Expected: suite result equal to Task 6 (compare as in Task 6 Step 1). Fix PHPUnit 13 deprecations/removals the same way as Task 6 Step 2. Rector or PHPStan findings introduced by new package stubs are fixed in the owning repo, one commit per concern.

- [ ] **Step 6: Commit (root)**

```bash
git add composer.json composer.lock phpunit.xml phpstan.neon
git commit -m "chore(deps): upgrade test toolchain to Pest 5 and PHPUnit 13, enable the Pest PHPStan extension

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 8: Guidelines and documentation

**Files:**
- Modify: `CLAUDE.md` and `AGENTS.md` Boost sections (regenerated), `AGENTS.md:66`
- Modify: `Modules/AI/README.md:153`, `Modules/Core/README.md:561`, `Modules/ERP/README.md:102`
- Modify: `docs/superpowers/plans/INDEX.md`, this plan

**Interfaces:**
- Consumes: finished upgrade.

- [ ] **Step 1: Regenerate the Boost guidelines**

Run: `php artisan boost:update --no-interaction`
Expected: the `=== laravel/v12 rules ===` blocks in `CLAUDE.md` and `AGENTS.md` become Laravel 13 rules. Review the diff: only the `<laravel-boost-guidelines>` block may change. If Boost drops the "Laravel 10 structure is intentional" guidance, the project rule in `AGENTS.md` (`Laravel 10-style structure is intentional. Do not migrate.`) still stands; do not edit it.

- [ ] **Step 2: Update the stack line and module READMEs**

`AGENTS.md:66`: `- Stack: PHP 8.5+, Laravel 12, Filament 5, Livewire 4, Sanctum 4, Tailwind 4.` becomes `- Stack: PHP 8.5+, Laravel 13, Filament 5, Livewire 4, Sanctum 4, Tailwind 4.`
In `Modules/AI/README.md`, `Modules/Core/README.md`, `Modules/ERP/README.md`: `-   Laravel 12.0+` becomes `-   Laravel 13.0+`. Commit each README inside its submodule (`docs: require Laravel 13`).
No env var was added, removed or renamed, so no README env section changes. No RAG doc change: framework behaviour for operators is unchanged.

- [ ] **Step 3: Close the plan**

Add at the end of this file:
```markdown
## Delivery status (YYYY-MM-DD): shipped

**Documented in:** `AGENTS.md` (stack line), `Modules/Core/README.md`, `Modules/AI/README.md`, `Modules/ERP/README.md` (requirements).
```
plus any divergence recorded during Tasks 4-7 (for example the swagger decision). Mark the `INDEX.md` entry `(**shipped YYYY-MM-DD**)`.

Run: `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`
Expected: PASS.

- [ ] **Step 4: Commit (root)**

```bash
vendor/bin/pint --dirty --format agent
git add CLAUDE.md AGENTS.md docs/superpowers/plans/INDEX.md docs/superpowers/plans/2026-09-16-laravel-13-upgrade.md
git commit -m "docs: record the Laravel 13 upgrade

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 9: Bump submodule pointers

**Files:**
- Modify: root gitlinks `Modules/AI`, `Modules/CMS`, `Modules/Core`, `Modules/ERP`, `Modules/MES`, `Modules/SAO`

- [ ] **Step 1: Check each submodule is clean and on the branch**

Run: `git submodule foreach --quiet 'echo "$name $(git rev-parse --abbrev-ref HEAD) $(git status --short | wc -l)"'`
Expected: six lines `... upgrade/laravel-13 0`.

- [ ] **Step 2: Final suite**

Run: `php artisan test --compact 2>&1 | tail -5`
Expected: same result as Task 7 Step 4.

- [ ] **Step 3: Commit the pointers**

```bash
git add Modules/AI Modules/CMS Modules/Core Modules/ERP Modules/MES Modules/SAO
git commit -m "chore(modules): bump all modules to their Laravel 13 upgrade

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

- [ ] **Step 4: Hand over**

Report to the user: branches to merge (root + six submodules, submodules first), the swagger decision, and that pushing/merging is theirs to do. Do not push or merge.
