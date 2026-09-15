# Module testing strategy

**Status:** Revised
**Date:** 2026-05-21
**Revised:** 2026-09-15
**Scope:** Laraplate application and module test organization, test toolchain ownership
**Chosen approach:** Keep test files inside each module, split suites by bootstrap level, and run
every suite from the application root with a single toolchain

## Revision 2026-09-15: one runner, one toolchain

The original version of this spec had two decisions in it. The first was a taxonomy: classify
tests by the bootstrap they need, not by the folder they sit in. That decision held, and it is
implemented. The second was a runner policy: every module keeps its own `phpunit.xml`, its own
Pest, and its own `composer test:*` scripts so it can be tested alone. That decision is reversed
here.

It is reversed because it never described the repository. A module-local test run cannot work,
and the reasons are structural rather than incidental:

- **The module test cases extend the application.** `Modules/{CMS,AI,ERP,MES,SAO}/tests/TestCase.php`
  each declare `extends \Tests\TestCase`, and `Modules/Core/tests/LaravelTestCase.php` does the
  same. `\Tests\TestCase` lives in the application's `tests/` directory, outside every module. A
  module cannot autoload it.
- **No module declares Core as a Composer dependency.** `module.json` records `requires: ["Core"]`,
  but the `composer.json` of CMS, AI, ERP, MES and SAO requires only `laravel/framework`. Nothing
  resolves `swolley/laraplate-core`, and there is no `path` repository that would. Meanwhile the
  module code uses Core everywhere: 184 files under `Modules/ERP/app`, 93 under `Modules/SAO/app`,
  73 under `Modules/CMS/app`. Installed alone, a module would not compile.
- **No module has a `vendor/` directory,** and every module test script invokes a relative
  `vendor/bin/pest`. Those scripts have never been runnable.
- **`orchestra/testbench` was the way out and was never taken.** It sits in the `require-dev` of
  AI, ERP, MES and SAO, but there is no `testbench.yaml` anywhere and no test case extends it. The
  only trace of the attempt is a comment in a Core test migration.

The same investigation found the opposite of what the file layout suggests about dependencies.
The module `require-dev` blocks are not dormant: they are the application's real test toolchain.
The root `composer.json` configures `wikimedia/composer-merge-plugin` with
`include: ["Modules/*/composer.json"]`, `merge-dev: true` and `merge-scripts: true`. The root's own
`require-dev` holds five packages and Pest is not among them. Pest, PHPUnit, Larastan, Pint,
Rector, PHPInsights, Peck and BypassFinals all reach `vendor/` because a module asked for them.

That indirection costs more than it gives:

- **Constraints silently lose.** `Modules/Core/composer.json` requires `peckphp/peck: ^0.2.0`; the
  installed version is `v0.1.3`, because `ignore-duplicates: true` drops the duplicate rather than
  reconciling it. Four packages carry divergent constraints across modules
  (`larastan/larastan`, `laravel/pint`, `driftingly/rector-laravel`, `peckphp/peck`) and which one
  wins is an artifact of merge order.
- **Module scripts leak into the root.** `composer run --list` at the root offers `test:pest`,
  `test:pest:parallel`, `test:unit:parallel` and `test:standalone`, none of which the root defines.
  `test:standalone` is an alias for `test:unit` and names a capability that does not exist.
- **The toolchain configuration is duplicated rather than specialized.** `peck.json` and
  `pint.json` are byte-identical across the root and all six modules; a module `rector.php` is a
  copy of the root's, down to paths like `bootstrap/app.php` that do not exist inside a module. The
  root already globs `Modules/*/app` for Rector and analyses `Modules/` with PHPStan. The one
  configuration that differs, `phpstan.neon`, differs by contradicting the root (`level: 5` against
  `level: 9`) while neither side is actually running.

So the revised decision is: **a module contains functionality and the tests for that functionality,
and nothing else.** The runner, the test dependencies and the quality toolchain belong to the
application.

The forward-looking argument for the old policy was the move to Composer packages. It is not lost.
A module that is genuinely installable alone needs Core declared as a real dependency, a path
repository to resolve it, and a Testbench-based test case that does not extend the application.
None of that exists, and none of it is created by keeping an unusable `phpunit.xml` in place. When
that work is actually scheduled, it starts from a clean module, not from scaffolding that has been
pretending for a year.

## Problem

Laraplate is a Laravel application shell where most behavior lives in modules under `Modules/`.
The modules are currently Git submodules, with the intent to become Composer packages later.
Most modules depend on `Core`, and some depend on other modules that themselves depend on
`Core`.

The initial goal was to make every module testable as if it existed alone. That led to too
many stubs and mocks for `Core`, which weakens the tests by breaking the same dependency
contracts that production code relies on. As the revision above records, it also produced a
module-local runner that could never run.

The test suite also has a semantic drift problem: many tests under `tests/Unit` boot the full
Laravel application, use `RefreshDatabase`, touch Eloquent models, or exercise framework
behavior. Those tests are valuable, but they are not pure unit tests.

## Goals

1. Keep module test ownership inside each module: the tests for a module's behavior live with it.
2. Allow fast pure unit test runs.
3. Allow module integration tests with Laravel bootstrapped and declared module dependencies loaded.
4. Keep application-level tests focused on the assembled app, not on owning module behavior.
5. Run every suite from the application root, with one runner and one configuration.
6. Keep every test and quality dependency in the application's `require-dev`, declared once.
7. Avoid heavy mocking of declared package/module dependencies such as `Core`.
8. Make runner intent obvious from suite names and paths.

## Non-Goals

- Do not move module behavior tests into the root application test folder.
- Do not make every existing test a perfect pure unit test in the first pass.
- Do not add dependencies. Promoting a module's existing dependency to the root is not an addition:
  the installed set must not change.
- Do not build the standalone-package test setup (path repositories, `Core` as a real requirement,
  Testbench). That is a separate decision with its own spec, taken when packaging is scheduled.
- Do not remove anything a module still needs in order to boot or autoload. Where
  the safe order is unclear, the file stays and the question is recorded rather than guessed.
- Do not change a single formatting or analysis rule while centralizing the configuration. The
  merged configuration is the union of what is already declared, and the reformatting pass is
  mechanical.

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

Core is the module that actually exercises this level: `Modules/Core/tests/TestCase.php` extends
`PHPUnit\Framework\TestCase` and boots `minimal-test-environment.php` instead of the application.
That test case is the point of the `Unit` suite, and it is unaffected by where the runner lives.

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

Each module converges on this layout:

```text
Modules/X/tests/Unit
Modules/X/tests/Integration
Modules/X/tests/Feature
Modules/X/tests/TestCase.php
Modules/X/tests/Pest.php
```

There is no `Modules/X/phpunit.xml`. A module that needs extra test fixtures keeps them beside its
tests (`tests/Stubs`, `tests/Support`, `tests/Fixtures`), and registers their namespaces in its own
`autoload-dev` so the merged root autoloader can resolve them.

The root application converges on this layout:

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

There is one runner: the root `phpunit.xml`, driven by `php artisan test` or `vendor/bin/pest`.
It exposes three aggregate suites that glob the modules:

```text
Unit
  tests/Unit
  Modules/*/tests/Unit

Integration
  tests/Integration
  Modules/*/tests/Integration
  Modules/*/tests/UnitShell

Feature
  tests/Feature
  Modules/*/tests/Feature
```

The glob matters: a new module joins the suites by existing, with no configuration to update.

Commands:

```bash
php artisan test --compact --testsuite=Unit
php artisan test --compact --testsuite=Integration
php artisan test --compact --testsuite=Feature
php artisan test --compact Modules/CMS/tests/Integration
vendor/bin/pest --filter=someTest
```

Running a single module is a path argument, not a separate runner. `php artisan test --compact
Modules/ERP/tests` is the supported way, and unlike the removed module scripts it actually works.

Root Composer scripts:

```text
test:unit
test:integration
test:feature
test:modules
test:coverage
test:type-coverage
test:lint
test:types
test:refactor
test:licenses
test
```

Modules define no `test:*` script. Because `merge-scripts` is enabled, a script defined in a
module is offered at the root as if the root owned it, which is how `test:standalone` came to be
listed by a project that has no standalone test mode.

## Toolchain Ownership

Test and quality tooling is declared once, in the root `composer.json`, under `require-dev`.

Modules declare no `require-dev` at all. What a module depends on to *run* stays in its `require`;
what the project needs to *test and check* the module is the application's concern. A module's
`autoload-dev` stays, because it is what makes the module's test helpers loadable from the merged
root autoloader.

The same applies to tool configuration. `pint.json`, `rector.php`, `peck.json` and `phpstan.neon`
exist once, at the root, and their paths cover `Modules/`. What the modules carry today is not
six configurations of six modules:

- **`peck.json` is byte-identical in all six modules and at the root.** Six copies of twelve lines.
- **`pint.json` is identical too**, rule for rule, all 72 of them. The only difference is that the
  root adds `Modules` to `notPath`. So the modules do not configure a different style, they exist
  because the root refuses to format them.
- **`rector.php` in a module is a copy of the root's**, paths included: it lists `__DIR__ . '/app'`,
  `__DIR__ . '/bootstrap/app.php'`, `__DIR__ . '/config'`, `__DIR__ . '/routes'` and globs
  `__DIR__ . '/Modules/*'`. Inside `Modules/CMS` those resolve to `Modules/CMS/bootstrap/app.php`
  and `Modules/CMS/Modules/*`, which do not exist. Meanwhile the root `rector.php` already globs
  `Modules/*/app` on its own. One difference is real and must survive the merge: CMS, ERP, MES and
  SAO skip `RemoveNullArgOnNullDefaultParamRector`, with the reason written next to it (it turns
  `where('col', null)` into `where('col')`, changing query semantics). The root does not skip it.
  The module copies are newer than the root's, so the merge takes the strictest union.
- **`phpstan.neon` is the one genuine exception,** and it points at a problem rather than a
  configuration to keep. The module files analyse at `level: 5` with per-file `ignoreErrors`, while
  the root analyses `Modules/` at `level: 9`. Two settings that contradict each other, and neither
  is in force: no module can run PHPStan without a `vendor/`, and the root invocation aborts before
  analysing anything because `excludePaths` names `.phpstorm.meta.php`, a file that does not exist.
  Repairing the root is the prerequisite; only then is it known what the module `ignoreErrors`
  would still be covering.

The formatter exclusion goes with them. `Modules` leaves the root `notPath`, and the module code is
formatted once, by the same 72 rules it already declares for itself. That reformats 618 of 3426
module files, all of it whitespace, import order, brace position and PHPDoc spacing, plus the
`mb_str_functions` substitutions the modules' own configuration has always asked for. A single
mechanical commit is a smaller cost than six configuration files that exist so that formatting can
be skipped.

Release tooling (`scripts/version.sh`, `cliff.toml`, git hooks) is not part of this spec: see
`2026-08-30-release-tooling-design.md`.

This does not weaken the module boundary. A module still declares its runtime dependencies, still
owns its tests, and is still reviewed on its own. It simply stops carrying a second, divergent copy
of the application's test environment.

## Pest Binding Rules

`tests/Pest.php` and `Modules/*/tests/Pest.php` avoid broad bindings that make every `Unit` test a
Laravel integration test.

Target behavior:

- `Unit` receives no automatic `RefreshDatabase`.
- `Integration` uses the module Laravel `TestCase` and can use `RefreshDatabase`.
- `Feature` uses the module Laravel `TestCase` and can use `RefreshDatabase`.
- Module-specific fakes may still be loaded from the module test bootstrap.

The root `tests/Pest.php` discovers module bootstraps by glob and requires each one, so a module's
bindings and helpers load without the root naming the module. This is the Pest-side counterpart of
the suite globbing in `phpunit.xml`: optional modules never become root-level dependencies.

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

`UnitShell` is a transitional directory, not a fourth level. It holds tests that were written as
unit tests but need the shell, and the root and module suites classify it as integration. Each file
is reviewed and lands in `Unit` or `Integration` on its own merits; the directory disappears when
it empties.

## Dependency Policy

Declared module dependencies are part of the test environment for integration and feature tests.

Examples:

- `CMS` integration tests may use real `Core`.
- `AI` integration tests may use real `Core`.
- `ERP` integration tests may use real `Core`.
- `MES` integration tests may use real `ERP` and therefore real `Core`.

Mocking should focus on external or unstable systems:

- HTTP APIs.
- Search engines.
- AI providers.
- Queues when testing synchronous service logic.
- Filesystems and generated artifacts.
- Time-sensitive services where deterministic fakes are needed.

Broad stubs of `Core` should be avoided because they hide production integration failures.

## Success Criteria

- A developer can run pure unit tests separately from Laravel integration tests.
- Module tests remain inside their owning module.
- Integration and feature tests use real declared module dependencies.
- The root application runs aggregate unit, integration, and feature suites, and is the only place
  that runs them.
- No module declares a `require-dev` entry, a `phpunit.xml`, a `test:*` script, or a copy of the
  root's tool configuration.
- The root `pint.json` formats module code, and `vendor/bin/pint --test` is clean across `Modules/`.
- `vendor/bin/phpstan` actually analyses instead of aborting on its own `excludePaths`.
- The installed package set is unchanged by the toolchain move: `composer.lock` gains no package
  and loses only what nothing used.
- `composer run --list` at the root shows only scripts the root defines.
- The application test folder contains only app assembly and cross-module smoke coverage.
- Test placement communicates bootstrap requirements without reading each file.
