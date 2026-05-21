# Module testing strategy

**Status:** Draft
**Date:** 2026-05-21
**Scope:** Laraplate application and module test organization
**Chosen approach:** Keep tests inside each module, split suites by bootstrap level

## Problem

Laraplate is a Laravel application shell where most behavior lives in modules under `Modules/`.
The modules are currently Git submodules, with the intent to become Composer packages later.
Most modules depend on `Core`, and some depend on other modules that themselves depend on
`Core`.

The initial goal was to make every module testable as if it existed alone. That led to too
many stubs and mocks for `Core`, which weakens the tests by breaking the same dependency
contracts that production code relies on.

The test suite also has a semantic drift problem: many tests under `tests/Unit` boot the full
Laravel application, use `RefreshDatabase`, touch Eloquent models, or exercise framework
behavior. Those tests are valuable, but they are not pure unit tests.

## Goals

1. Keep module test ownership inside each module.
2. Allow fast pure unit test runs.
3. Allow module integration tests with Laravel bootstrapped and declared module dependencies loaded.
4. Keep application-level tests focused on the assembled app, not on owning module behavior.
5. Prepare the test structure for the future Composer-package model.
6. Avoid heavy mocking of declared package/module dependencies such as `Core`.
7. Make runner intent obvious from suite names and paths.

## Non-Goals

- Do not move module behavior tests into the root application test folder.
- Do not remove module test runners.
- Do not make every existing test a perfect pure unit test in the first pass.
- Do not introduce new dependencies.
- Do not solve external package CI publishing in this design.

## Test Taxonomy

### Unit

Unit tests are tests that do not require the full Laravel application bootstrap.

Rules:

- No full Laravel app bootstrap.
- No database.
- No `RefreshDatabase`.
- No HTTP, console, Livewire, or Filament behavior.
- No service provider or route registration assertions.
- Mock or fake only true external boundaries, such as HTTP clients, AI providers, filesystems,
  queues, or services behind explicit interfaces.

Examples:

- DTOs.
- Enums.
- Value objects.
- Pure helpers.
- Small services whose dependencies are explicit interfaces.
- Data transformation and parsing logic.

### Integration

Integration tests are module-owned tests that boot Laravel and use the module's declared
dependencies as real dependencies.

Rules:

- Laravel bootstrap is allowed.
- `RefreshDatabase` is allowed.
- Eloquent models, factories, config, facades, providers, observers, jobs, and listeners are allowed.
- Declared module dependencies such as `Core` or `ERP` should be loaded for real, not replaced
  by broad stubs.
- External systems should still be mocked or faked.

Examples:

- Model behavior with database persistence.
- Actions and services that rely on Laravel container/config/database.
- Observers and listeners tested through realistic framework behavior.
- Jobs that need container, database, or module services.
- Provider/config behavior that belongs to the module.

### Feature

Feature tests are module-owned or app-owned tests that verify visible application behavior.

Rules:

- Laravel bootstrap is required.
- HTTP routes, controllers, console commands, Livewire, Filament, and user workflows belong here.
- These tests may use the database and real declared module dependencies.

Examples:

- Controller tests.
- API tests.
- Console command behavior.
- Filament resource/page/table tests.
- Livewire component behavior.
- End-to-end module workflows.

### Application

Application tests live under root `tests/` and verify the assembled Laraplate app.

Rules:

- Use these for shell-level and cross-module confidence.
- Do not move normal module behavior tests here.
- Keep this suite small and focused.

Examples:

- App bootstraps with all enabled modules.
- Package discovery works without a Vite manifest.
- Module provider load order does not conflict.
- Global routes and redirects work.
- Cross-module smoke tests.

## Directory Layout

Each module should converge on this layout:

```text
Modules/X/tests/Unit
Modules/X/tests/Integration
Modules/X/tests/Feature
Modules/X/tests/TestCase.php
Modules/X/tests/Pest.php
Modules/X/phpunit.xml
```

The root application should converge on this layout:

```text
tests/Unit
tests/Integration
tests/Feature
tests/TestCase.php
tests/Pest.php
phpunit.xml
```

`tests/Integration` at root is for application assembly tests only. It is not the home for
module integration tests.

## Runner Design

Each module `phpunit.xml` should expose three suites:

```xml
<testsuite name="Unit">
    <directory suffix="Test.php">./tests/Unit</directory>
</testsuite>
<testsuite name="Integration">
    <directory suffix="Test.php">./tests/Integration</directory>
</testsuite>
<testsuite name="Feature">
    <directory suffix="Test.php">./tests/Feature</directory>
</testsuite>
```

The root `phpunit.xml` should expose aggregate suites:

```text
Unit
  tests/Unit
  Modules/*/tests/Unit

Integration
  tests/Integration
  Modules/*/tests/Integration

Feature
  tests/Feature
  Modules/*/tests/Feature
```

Useful commands:

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --testsuite=Feature
php artisan test --compact Modules/CMS/tests/Integration
```

Module-local commands should remain available:

```bash
vendor/bin/pest --testsuite=Unit
vendor/bin/pest --testsuite=Integration
vendor/bin/pest --testsuite=Feature
```

Composer scripts in each module should converge on:

```text
test:unit
test:integration
test:feature
test:pest
test
```

The root project can orchestrate all modules, but it should not replace module-local ownership.

## Pest Binding Rules

`tests/Pest.php` and `Modules/*/tests/Pest.php` should avoid broad bindings that make every
`Unit` test a Laravel integration test.

Target behavior:

- `Unit` receives no automatic `RefreshDatabase`.
- `Integration` uses the module Laravel `TestCase` and can use `RefreshDatabase`.
- `Feature` uses the module Laravel `TestCase` and can use `RefreshDatabase`.
- Module-specific fakes may still be loaded from the module test bootstrap.

This replaces broad patterns such as binding all `Modules/AI/tests/Unit` tests to
`RefreshDatabase`.

## Test Reclassification Rules

The first reclassification pass should be mechanical and defensible.

Move from `Unit` to `Integration` when a test:

- uses `RefreshDatabase`;
- uses `Tests\TestCase` or a module `TestCase` that extends the full app test case;
- touches persisted Eloquent behavior;
- depends on Laravel container/config/facades in a non-trivial way;
- asserts provider, observer, job, listener, or migration behavior;
- relies on a declared module dependency such as `Core`.

Move from `Unit` or `Integration` to `Feature` when a test:

- performs HTTP requests;
- tests a controller;
- tests a console command through Artisan;
- tests Filament or Livewire UI behavior;
- verifies a visible workflow rather than an internal service contract.

Keep in `Unit` when a test:

- can run without the full Laravel app;
- has no database dependency;
- has explicit mock/fake boundaries;
- tests a small unit with stable inputs and outputs.

## Dependency Policy

Declared module dependencies are part of the test environment for integration and feature tests.

Examples:

- `CMS` integration tests may use real `Core`.
- `AI` integration tests may use real `Core`.
- `ERP` integration tests may use real `Core`.
- `MES` integration tests may use real `ERP` and therefore real `Core`.

Mocking should focus on external or unstable systems:

- HTTP APIs.
- AI providers.
- Search engines.
- Queues when testing synchronous service logic.
- Filesystems and generated artifacts.
- Time-sensitive services where deterministic fakes are needed.

Broad stubs of `Core` should be avoided because they hide production integration failures.

## Migration Plan

1. Add `Integration` suites to root and module `phpunit.xml` files.
2. Create missing `tests/Integration` directories where tests need them.
3. Narrow Pest bindings so `Unit` is not automatically bootstrapped as Laravel integration.
4. Move obvious misclassified tests using the reclassification rules.
5. Update module Composer scripts to expose `test:unit`, `test:integration`, and `test:feature`.
6. Update root Composer scripts to orchestrate aggregate suites.
7. Run the smallest relevant suite after each module migration.
8. Run `vendor/bin/pint --dirty` after code/config changes.

## Success Criteria

- A developer can run pure unit tests separately from Laravel integration tests.
- Module tests remain inside their owning module.
- Integration and feature tests use real declared module dependencies.
- The root application can run aggregate unit, integration, and feature suites.
- The application test folder contains only app assembly and cross-module smoke coverage.
- Test placement communicates bootstrap requirements without reading each file.

