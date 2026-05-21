# Module Testing Strategy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganize Laraplate tests so module-owned tests remain inside modules while pure unit, integration, feature, and application-level suites can run independently.

**Architecture:** Keep module ownership unchanged and make bootstrap requirements explicit through suite names and paths. Add `Integration` suites to root and module PHPUnit configs, move mechanically identifiable non-unit tests out of `Unit`, then narrow Pest bindings so `Unit` no longer implies full Laravel bootstrapping.

**Tech Stack:** PHP 8.5, Laravel 12, Pest 4, PHPUnit config, nwidart/laravel-modules, Composer scripts.

---

## File Map

- Modify: `phpunit.xml`  
  Root aggregate suites: `Unit`, `Integration`, `Feature`.
- Modify: `tests/Pest.php`  
  Root Pest bindings for app tests and module bootstrap includes.
- Create: `tests/Integration/.gitkeep`  
  Root app-assembly integration test placeholder.
- Modify: `composer.json`  
  Root orchestration scripts for suite-level runs.
- Modify: `Modules/Core/phpunit.xml`  
  Add `Integration` suite and keep `UnitShell` explicitly classified.
- Modify: `Modules/Core/tests/Pest.php`  
  Bind `Unit` to minimal Core test case and `Integration`/`Feature` to Laravel test case after moves.
- Create: `Modules/Core/tests/Integration/.gitkeep`
- Modify: `Modules/CMS/phpunit.xml`
- Modify: `Modules/CMS/tests/Pest.php`
- Create: `Modules/CMS/tests/Integration/.gitkeep`
- Modify: `Modules/CMS/composer.json`
- Modify: `Modules/AI/phpunit.xml`
- Modify: `Modules/AI/tests/Pest.php`
- Create: `Modules/AI/tests/Integration/.gitkeep`
- Modify: `Modules/AI/composer.json`
- Modify: `Modules/ERP/phpunit.xml`
- Modify: `Modules/ERP/tests/Pest.php`
- Create: `Modules/ERP/tests/Integration/.gitkeep`
- Modify: `Modules/ERP/composer.json`
- Modify: `Modules/MES/phpunit.xml`
- Modify: `Modules/MES/tests/Pest.php`
- Create: `Modules/MES/tests/Integration/.gitkeep`
- Modify: `Modules/MES/composer.json`
- Move: mechanically misclassified tests from `Modules/*/tests/Unit` to `Modules/*/tests/Integration` or `Modules/*/tests/Feature`.

---

### Task 1: Establish Suite Directories

**Files:**
- Create: `tests/Integration/.gitkeep`
- Create: `Modules/Core/tests/Integration/.gitkeep`
- Create: `Modules/CMS/tests/Integration/.gitkeep`
- Create: `Modules/AI/tests/Integration/.gitkeep`
- Create: `Modules/ERP/tests/Integration/.gitkeep`
- Create: `Modules/MES/tests/Integration/.gitkeep`

- [ ] **Step 1: Create integration directories**

Run:

```bash
rtk mkdir -p tests/Integration Modules/Core/tests/Integration Modules/CMS/tests/Integration Modules/AI/tests/Integration Modules/ERP/tests/Integration Modules/MES/tests/Integration
rtk touch tests/Integration/.gitkeep Modules/Core/tests/Integration/.gitkeep Modules/CMS/tests/Integration/.gitkeep Modules/AI/tests/Integration/.gitkeep Modules/ERP/tests/Integration/.gitkeep Modules/MES/tests/Integration/.gitkeep
```

Expected: commands exit with status `0`.

- [ ] **Step 2: Verify directories exist**

Run:

```bash
rtk rg --files tests Modules | rtk rg '/Integration/\\.gitkeep$'
```

Expected output includes:

```text
tests/Integration/.gitkeep
Modules/Core/tests/Integration/.gitkeep
Modules/CMS/tests/Integration/.gitkeep
Modules/AI/tests/Integration/.gitkeep
Modules/ERP/tests/Integration/.gitkeep
Modules/MES/tests/Integration/.gitkeep
```

- [ ] **Step 3: Commit**

Run:

```bash
rtk git add tests/Integration/.gitkeep Modules/Core/tests/Integration/.gitkeep Modules/CMS/tests/Integration/.gitkeep Modules/AI/tests/Integration/.gitkeep Modules/ERP/tests/Integration/.gitkeep Modules/MES/tests/Integration/.gitkeep
rtk git commit -m "test: add integration suite directories"
```

Expected: commit succeeds.

---

### Task 2: Add Root Aggregate Suites

**Files:**
- Modify: `phpunit.xml`

- [ ] **Step 1: Replace root testsuites block**

In `phpunit.xml`, replace the current `<testsuites>` block with:

```xml
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
            <directory suffix="Test.php">Modules/Core/tests/Unit</directory>
            <directory suffix="Test.php">Modules/CMS/tests/Unit</directory>
            <directory suffix="Test.php">Modules/AI/tests/Unit</directory>
            <directory suffix="Test.php">Modules/ERP/tests/Unit</directory>
            <directory suffix="Test.php">Modules/MES/tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">tests/Integration</directory>
            <directory suffix="Test.php">Modules/Core/tests/Integration</directory>
            <directory suffix="Test.php">Modules/Core/tests/UnitShell</directory>
            <directory suffix="Test.php">Modules/CMS/tests/Integration</directory>
            <directory suffix="Test.php">Modules/CMS/tests/UnitShell</directory>
            <directory suffix="Test.php">Modules/AI/tests/Integration</directory>
            <directory suffix="Test.php">Modules/ERP/tests/Integration</directory>
            <directory suffix="Test.php">Modules/MES/tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">tests/Feature</directory>
            <directory suffix="Test.php">Modules/Core/tests/Feature</directory>
            <directory suffix="Test.php">Modules/CMS/tests/Feature</directory>
            <directory suffix="Test.php">Modules/AI/tests/Feature</directory>
            <directory suffix="Test.php">Modules/ERP/tests/Feature</directory>
            <directory suffix="Test.php">Modules/MES/tests/Feature</directory>
        </testsuite>
    </testsuites>
```

Rationale: `UnitShell` tests are not pure unit tests; classify them as integration until each file is reviewed.

- [ ] **Step 2: Validate PHPUnit config syntax**

Run:

```bash
rtk vendor/bin/phpunit --list-suites
```

Expected: output lists `Unit`, `Integration`, and `Feature`.

- [ ] **Step 3: Commit**

Run:

```bash
rtk git add phpunit.xml
rtk git commit -m "test: split root phpunit suites by bootstrap level"
```

Expected: commit succeeds.

---

### Task 3: Add Module Integration Suites

**Files:**
- Modify: `Modules/Core/phpunit.xml`
- Modify: `Modules/CMS/phpunit.xml`
- Modify: `Modules/AI/phpunit.xml`
- Modify: `Modules/ERP/phpunit.xml`

- [ ] **Step 1: Update Core testsuites**

In `Modules/Core/phpunit.xml`, use:

```xml
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
            <directory suffix="Test.php">./tests/UnitShell</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
```

- [ ] **Step 2: Update CMS testsuites**

In `Modules/CMS/phpunit.xml`, use:

```xml
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
            <directory suffix="Test.php">./tests/UnitShell</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
```

- [ ] **Step 3: Update AI testsuites**

In `Modules/AI/phpunit.xml`, use:

```xml
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
```

- [ ] **Step 4: Update ERP testsuites**

In `Modules/ERP/phpunit.xml`, use the same three-suite shape:

```xml
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
```

- [ ] **Step 5: Create MES phpunit config if missing**

If `Modules/MES/phpunit.xml` does not exist, create it with the same structure as ERP plus the standard testing env:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="SCOUT_DRIVER" value="collection"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

- [ ] **Step 6: Validate module configs**

Run:

```bash
rtk vendor/bin/phpunit --configuration Modules/Core/phpunit.xml --list-suites
rtk vendor/bin/phpunit --configuration Modules/CMS/phpunit.xml --list-suites
rtk vendor/bin/phpunit --configuration Modules/AI/phpunit.xml --list-suites
rtk vendor/bin/phpunit --configuration Modules/ERP/phpunit.xml --list-suites
rtk vendor/bin/phpunit --configuration Modules/MES/phpunit.xml --list-suites
```

Expected: each command lists `Unit`, `Integration`, and `Feature`.

- [ ] **Step 7: Commit**

Run:

```bash
rtk git add Modules/Core/phpunit.xml Modules/CMS/phpunit.xml Modules/AI/phpunit.xml Modules/ERP/phpunit.xml Modules/MES/phpunit.xml
rtk git commit -m "test: add module integration suites"
```

Expected: commit succeeds.

---

### Task 4: Update Composer Test Scripts

**Files:**
- Modify: `composer.json`
- Modify: `Modules/Core/composer.json`
- Modify: `Modules/CMS/composer.json`
- Modify: `Modules/AI/composer.json`
- Modify: `Modules/ERP/composer.json`
- Modify: `Modules/MES/composer.json`

- [ ] **Step 1: Add root suite scripts**

In root `composer.json`, add these scripts without removing existing quality scripts:

```json
"test:unit": "php artisan test --compact --testsuite=Unit",
"test:integration": "php artisan test --compact --testsuite=Integration",
"test:feature": "php artisan test --compact --testsuite=Feature",
"test:modules": [
  "@test:unit",
  "@test:integration",
  "@test:feature"
]
```

If `test:unit` already exists and currently means coverage, rename the current coverage command to `test:coverage` before assigning `test:unit` to the fast suite command.

- [ ] **Step 2: Add module suite scripts**

In each module `composer.json`, converge these scripts:

```json
"test:unit": "vendor/bin/pest --compact --testsuite=Unit",
"test:integration": "vendor/bin/pest --compact --testsuite=Integration",
"test:feature": "vendor/bin/pest --compact --testsuite=Feature",
"test:pest": "vendor/bin/pest --compact",
"test:pest:parallel": "vendor/bin/pest --parallel --compact"
```

Keep strict coverage scripts such as `test:type-coverage` and `test:unit:parallel` only if they already exist, but do not use `test:unit` for coverage after this change.

- [ ] **Step 3: Validate Composer script names**

Run:

```bash
rtk composer run --list | rtk rg 'test:(unit|integration|feature|modules)'
rtk composer --working-dir=Modules/Core run --list | rtk rg 'test:(unit|integration|feature|pest)'
rtk composer --working-dir=Modules/CMS run --list | rtk rg 'test:(unit|integration|feature|pest)'
rtk composer --working-dir=Modules/AI run --list | rtk rg 'test:(unit|integration|feature|pest)'
rtk composer --working-dir=Modules/ERP run --list | rtk rg 'test:(unit|integration|feature|pest)'
rtk composer --working-dir=Modules/MES run --list | rtk rg 'test:(unit|integration|feature|pest)'
```

Expected: all listed scripts are present.

- [ ] **Step 4: Commit**

Run:

```bash
rtk git add composer.json Modules/Core/composer.json Modules/CMS/composer.json Modules/AI/composer.json Modules/ERP/composer.json Modules/MES/composer.json
rtk git commit -m "test: standardize suite composer scripts"
```

Expected: commit succeeds.

---

### Task 5: Move Obvious AI Integration Tests

**Files:**
- Move selected files from `Modules/AI/tests/Unit` to `Modules/AI/tests/Integration`

- [ ] **Step 1: Move database-backed AI tests**

Run:

```bash
rtk mkdir -p Modules/AI/tests/Integration
rtk mv Modules/AI/tests/Unit/ActionRequestControllerTest.php Modules/AI/tests/Integration/ActionRequestControllerTest.php
rtk mv Modules/AI/tests/Unit/SuggestionControllerTest.php Modules/AI/tests/Integration/SuggestionControllerTest.php
rtk mv Modules/AI/tests/Unit/ChatControllerTest.php Modules/AI/tests/Integration/ChatControllerTest.php
rtk mv Modules/AI/tests/Unit/MemoryServiceFullTest.php Modules/AI/tests/Integration/MemoryServiceFullTest.php
rtk mv Modules/AI/tests/Unit/ChatServiceFullTest.php Modules/AI/tests/Integration/ChatServiceFullTest.php
rtk mv Modules/AI/tests/Unit/ExecuteActionRequestJobTest.php Modules/AI/tests/Integration/ExecuteActionRequestJobTest.php
rtk mv Modules/AI/tests/Unit/ContextualSuggestionModelTest.php Modules/AI/tests/Integration/ContextualSuggestionModelTest.php
rtk mv Modules/AI/tests/Unit/ContextualSuggestionServiceTest.php Modules/AI/tests/Integration/ContextualSuggestionServiceTest.php
rtk mv Modules/AI/tests/Unit/ToolRegistryTest.php Modules/AI/tests/Integration/ToolRegistryTest.php
rtk mv Modules/AI/tests/Unit/ConversationSummaryModelTest.php Modules/AI/tests/Integration/ConversationSummaryModelTest.php
rtk mv Modules/AI/tests/Unit/ConversationModelExtendedTest.php Modules/AI/tests/Integration/ConversationModelExtendedTest.php
rtk mv Modules/AI/tests/Unit/ActionRequestServiceTest.php Modules/AI/tests/Integration/ActionRequestServiceTest.php
rtk mv Modules/AI/tests/Unit/ActionRequestModelTest.php Modules/AI/tests/Integration/ActionRequestModelTest.php
rtk mv Modules/AI/tests/Unit/MessageModelExtendedTest.php Modules/AI/tests/Integration/MessageModelExtendedTest.php
```

Rationale: these files currently use `RefreshDatabase` or controller/model behavior and should not live in `Unit`.

- [ ] **Step 2: Move obvious controller tests to Feature if they issue HTTP requests**

Open the moved controller tests. If they call `$this->get()`, `$this->post()`, `$this->put()`, `$this->delete()`, or `actingAs()->...` HTTP assertions, move them from `Modules/AI/tests/Integration` to `Modules/AI/tests/Feature`.

Run for files that match:

```bash
rtk rg -n "\\$this->(get|post|put|patch|delete|actingAs)\\(" Modules/AI/tests/Integration/*ControllerTest.php
```

Expected: files with HTTP assertions are moved to `Modules/AI/tests/Feature`.

- [ ] **Step 3: Run AI suites**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration
rtk php artisan test --compact Modules/AI/tests/Feature
```

Expected: both suites pass or expose pre-existing failures to fix in the same task.

- [ ] **Step 4: Commit**

Run:

```bash
rtk git add Modules/AI/tests
rtk git commit -m "test: move AI framework tests out of unit suite"
```

Expected: commit succeeds.

---

### Task 6: Move Obvious CMS Integration Tests

**Files:**
- Move selected files from `Modules/CMS/tests/Unit` to `Modules/CMS/tests/Integration`

- [ ] **Step 1: Move CMS unit tests that use RefreshDatabase**

Run:

```bash
rtk rg -l "RefreshDatabase" Modules/CMS/tests/Unit | while read -r file; do target="${file/Modules\\/CMS\\/tests\\/Unit/Modules\\/CMS\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: files using `RefreshDatabase` are under `Modules/CMS/tests/Integration`.

- [ ] **Step 2: Move CMS unit tests that explicitly use module Laravel TestCase**

Run:

```bash
rtk rg -l "Modules\\\\CMS\\\\Tests\\\\TestCase|uses\\(TestCase::class" Modules/CMS/tests/Unit | while read -r file; do target="${file/Modules\\/CMS\\/tests\\/Unit/Modules\\/CMS\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: framework-dependent CMS tests are under `Modules/CMS/tests/Integration`.

- [ ] **Step 3: Move CMS controller tests to Feature**

Run:

```bash
rtk mkdir -p Modules/CMS/tests/Feature/Controllers
rtk rg --files Modules/CMS/tests/Integration | rtk rg '/Controllers/.*Test\\.php$' | while read -r file; do target="${file/Modules\\/CMS\\/tests\\/Integration/Modules\\/CMS\\/tests\\/Feature}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: controller tests live under `Modules/CMS/tests/Feature/Controllers`.

- [ ] **Step 4: Run CMS suites**

Run:

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration
rtk php artisan test --compact Modules/CMS/tests/Feature
```

Expected: both suites pass or expose pre-existing failures to fix in the same task.

- [ ] **Step 5: Commit**

Run:

```bash
rtk git add Modules/CMS/tests
rtk git commit -m "test: move CMS framework tests out of unit suite"
```

Expected: commit succeeds.

---

### Task 7: Move Obvious Core Integration Tests

**Files:**
- Move selected files from `Modules/Core/tests/Unit` to `Modules/Core/tests/Integration`

- [ ] **Step 1: Move Core unit tests that use RefreshDatabase**

Run:

```bash
rtk rg -l "RefreshDatabase" Modules/Core/tests/Unit | while read -r file; do target="${file/Modules\\/Core\\/tests\\/Unit/Modules\\/Core\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: files using `RefreshDatabase` are under `Modules/Core/tests/Integration`.

- [ ] **Step 2: Move Core provider tests to Integration**

Run:

```bash
rtk mkdir -p Modules/Core/tests/Integration/Providers
rtk rg --files Modules/Core/tests/Unit/Providers | rtk rg 'Test\\.php$' | while read -r file; do target="${file/Modules\\/Core\\/tests\\/Unit/Modules\\/Core\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: provider tests live under `Modules/Core/tests/Integration/Providers`.

- [ ] **Step 3: Move Core console tests to Feature**

Run:

```bash
rtk mkdir -p Modules/Core/tests/Feature/Console
rtk rg --files Modules/Core/tests/Unit/Console | rtk rg 'Test\\.php$' | while read -r file; do target="${file/Modules\\/Core\\/tests\\/Unit/Modules\\/Core\\/tests\\/Feature}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: console tests live under `Modules/Core/tests/Feature/Console`.

- [ ] **Step 4: Run Core suites**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration
rtk php artisan test --compact Modules/Core/tests/Feature
```

Expected: both suites pass or expose pre-existing failures to fix in the same task.

- [ ] **Step 5: Commit**

Run:

```bash
rtk git add Modules/Core/tests
rtk git commit -m "test: move Core framework tests out of unit suite"
```

Expected: commit succeeds.

---

### Task 8: Move Obvious ERP and MES Integration Tests

**Files:**
- Move selected files from `Modules/ERP/tests/Unit` to `Modules/ERP/tests/Integration`
- Move selected files from `Modules/MES/tests/Unit` to `Modules/MES/tests/Integration`

- [ ] **Step 1: Move ERP unit tests that use RefreshDatabase**

Run:

```bash
rtk rg -l "RefreshDatabase" Modules/ERP/tests/Unit | while read -r file; do target="${file/Modules\\/ERP\\/tests\\/Unit/Modules\\/ERP\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: ERP database-backed tests are under `Modules/ERP/tests/Integration`.

- [ ] **Step 2: Move MES unit tests that use full module dependencies**

Run:

```bash
rtk rg -l "Modules\\\\ERP|RefreshDatabase|Tests\\\\TestCase|uses\\(TestCase::class" Modules/MES/tests/Unit | while read -r file; do target="${file/Modules\\/MES\\/tests\\/Unit/Modules\\/MES\\/tests\\/Integration}"; mkdir -p "$(dirname "$target")"; mv "$file" "$target"; done
```

Expected: MES tests depending on ERP or Laravel are under `Modules/MES/tests/Integration`.

- [ ] **Step 3: Run ERP and MES suites**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Integration
rtk php artisan test --compact Modules/MES/tests/Integration
```

Expected: both suites pass or expose pre-existing failures to fix in the same task.

- [ ] **Step 4: Commit**

Run:

```bash
rtk git add Modules/ERP/tests Modules/MES/tests
rtk git commit -m "test: move ERP and MES integration tests out of unit suite"
```

Expected: commit succeeds.

---

### Task 9: Narrow Pest Bindings

**Files:**
- Modify: `tests/Pest.php`
- Modify: `Modules/Core/tests/Pest.php`
- Modify: `Modules/AI/tests/Pest.php`
- Modify: `Modules/ERP/tests/Pest.php`
- Modify: `Modules/MES/tests/Pest.php`
- Modify: `Modules/CMS/tests/Pest.php`

- [ ] **Step 1: Update AI Pest bindings**

Replace `Modules/AI/tests/Pest.php` with:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap-test-fakes.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Integration', 'Feature');
```

- [ ] **Step 2: Update ERP Pest bindings**

Replace `Modules/ERP/tests/Pest.php` with:

```php
<?php

declare(strict_types=1);

use Modules\ERP\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in(__DIR__ . '/Integration', __DIR__ . '/Feature');
```

- [ ] **Step 3: Update MES Pest bindings**

Replace `Modules/MES/tests/Pest.php` with:

```php
<?php

declare(strict_types=1);

use Modules\MES\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in(__DIR__ . '/Integration', __DIR__ . '/Feature');
```

- [ ] **Step 4: Update Core Pest bindings**

Replace the binding section at the top of `Modules/Core/tests/Pest.php` with:

```php
uses(Modules\Core\Tests\TestCase::class)->in(__DIR__ . '/Unit');

uses(Modules\Core\Tests\LaravelTestCase::class)->in(
    __DIR__ . '/Integration',
    __DIR__ . '/Feature',
    __DIR__ . '/UnitShell',
);
```

Keep the helper functions below unchanged.

- [ ] **Step 5: Keep CMS helpers and add integration binding**

At the top of `Modules/CMS/tests/Pest.php`, after `require_once __DIR__ . '/helpers.php';`, add:

```php
use Modules\CMS\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in(__DIR__ . '/Integration', __DIR__ . '/Feature', __DIR__ . '/UnitShell');
```

Do not remove the existing CMS helper functions.

- [ ] **Step 6: Update root Pest includes**

In `tests/Pest.php`, remove the broad AI root bindings:

```php
pest()->extend(Modules\AI\Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__ . '/../Modules/AI/tests/Feature');

pest()->extend(Modules\AI\Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__ . '/../Modules/AI/tests/Unit');
```

Then require module Pest files directly:

```php
require_once __DIR__ . '/../Modules/Core/tests/Pest.php';
require_once __DIR__ . '/../Modules/CMS/tests/Pest.php';
require_once __DIR__ . '/../Modules/AI/tests/Pest.php';
require_once __DIR__ . '/../Modules/ERP/tests/Pest.php';
require_once __DIR__ . '/../Modules/MES/tests/Pest.php';
```

Keep root app binding:

```php
pest()->extend(Tests\TestCase::class)
    ->in('Feature', 'Unit', 'Integration');
```

- [ ] **Step 7: Run suite smoke checks**

Run:

```bash
rtk php artisan test --compact --testsuite=Unit
rtk php artisan test --compact --testsuite=Integration
rtk php artisan test --compact --testsuite=Feature
```

Expected: all suites pass or expose classification mistakes to fix before committing.

- [ ] **Step 8: Commit**

Run:

```bash
rtk git add tests/Pest.php Modules/Core/tests/Pest.php Modules/CMS/tests/Pest.php Modules/AI/tests/Pest.php Modules/ERP/tests/Pest.php Modules/MES/tests/Pest.php
rtk git commit -m "test: narrow pest bindings by suite"
```

Expected: commit succeeds.

---

### Task 10: Final Verification

**Files:**
- All files changed by previous tasks

- [ ] **Step 1: Run aggregate tests**

Run:

```bash
rtk composer test:unit
rtk composer test:integration
rtk composer test:feature
```

Expected: all three commands pass.

- [ ] **Step 2: Run formatting**

Run:

```bash
rtk vendor/bin/pint --dirty
```

Expected: Pint exits with status `0`; commit any formatting changes.

- [ ] **Step 3: Review remaining Unit suite for Laravel bootstrap leaks**

Run:

```bash
rtk rg -n "RefreshDatabase|Tests\\\\TestCase|Modules\\\\.*\\\\Tests\\\\TestCase|\\$this->(get|post|put|patch|delete|artisan)\\(" Modules/*/tests/Unit tests/Unit
```

Expected: no matches, or each match is intentionally justified and moved before final commit.

- [ ] **Step 4: Commit final cleanup**

Run:

```bash
rtk git add phpunit.xml composer.json tests Modules
rtk git commit -m "test: finalize module suite reclassification"
```

Expected: commit succeeds if there are remaining changes; otherwise report that the worktree is clean.
