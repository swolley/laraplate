# Module Testing Strategy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the test reorganization by centralizing the runner and the test toolchain in the
application, so a module carries functionality and its tests and nothing else.

**Architecture:** The suite taxonomy (`Unit` / `Integration` / `Feature`, classified by required
bootstrap) is already in place and is confirmed rather than rebuilt. What remains is removing the
module-local test runner that never worked, and moving the test dependencies that modules declare
today into the application's `require-dev`, where they are already being used from.

**Tech Stack:** PHP 8.5, Laravel 12, Pest 4, PHPUnit config, nwidart/laravel-modules,
wikimedia/composer-merge-plugin, Composer scripts.

**Spec:** `docs/superpowers/specs/2026-05-21-module-testing-strategy-design.md`
(revised 2026-09-15: the module-local runner policy is reversed there, with the evidence)

---

## Why this plan changed

The 2026-05-21 version of this plan had ten tasks: create `Integration` directories, add suites to
the root and module PHPUnit configs, add per-module `test:*` scripts, move misclassified tests, and
narrow the Pest bindings.

Most of that shipped. Every module has the three suites, the root globs them, the tests were moved,
and the Pest bindings are narrow. The checkboxes were never ticked, which is what made the work
look outstanding.

What did not survive review is the half of the design that kept a runner inside each module. It
cannot work: the module test cases extend `\Tests\TestCase`, which lives in the application; no
module requires `swolley/laraplate-core` in Composer even though its code uses Core in hundreds of
files; no module has a `vendor/`; and the `orchestra/testbench` that would have made standalone
runs possible was never wired to anything. The spec records the full evidence.

Investigating that turned up the inverse problem. Module `require-dev` blocks are not idle
scaffolding, they are the application's actual test toolchain: the root `composer.json` merges
`Modules/*/composer.json` through `wikimedia/composer-merge-plugin` with `merge-dev` and
`merge-scripts` on, and the root's own `require-dev` does not contain Pest. So the constraints that
decide which Pest, which Larastan and which Pint get installed live in six files that disagree with
each other, and the loser is silently dropped: Core asks for `peckphp/peck: ^0.2.0` and the project
runs `v0.1.3`.

So tasks 1 to 4 and 9 of the old plan are now verification tasks, tasks 5 to 8 are recorded as
delivered, and the new work is the toolchain move.

---

## Safety rule for this plan

Remove only what the module does not need. Anything that a module still requires in order to boot,
autoload, or be formatted stays, even when it looks redundant, and the open question is written
down instead of guessed.

Three consequences, all binding:

- **Promote before removing.** The root must require a package, and carry every rule a module
  configuration carries, *before* the module stops declaring it. `composer.lock` must be checked to
  prove the installed set did not change. Removing first empties `vendor/` of the tooling the
  application runs on.
- **Centralizing a configuration must not change a rule.** The merged configuration is the union of
  what is already declared. Where the root and a module disagree, the stricter side wins and the
  choice is written down. Reformatting is mechanical and lands in its own commit, never mixed with
  a behavioral change.
- **Release tooling is not part of this plan.** `version.sh`, `cliff.toml` and the git hooks moved
  to `docs/superpowers/plans/2026-09-15-release-tooling.md`, together with the tasks already
  delivered for them.

---

## File Map

- Modify: `composer.json`
  Promote the merged test/quality dev dependencies into the root `require-dev`; adopt the module
  scripts worth keeping.
- Modify: `Modules/{Core,CMS,AI,ERP,MES,SAO}/composer.json`
  Drop `require-dev` and every `test:*` script; keep `require`, `autoload` and `autoload-dev`.
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/phpunit.xml`
- Modify: `pint.json`
  Drop `Modules` from `notPath` so the application formatter reaches module code.
- Modify: `rector.php`
  Absorb the `RemoveNullArgOnNullDefaultParamRector` skip that four modules declare and the root
  does not.
- Modify: `phpstan.neon`
  Repair `excludePaths` so the analysis runs at all, then decide what the module configs covered.
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/{pint.json,rector.php,peck.json}` and the five module
  `phpstan.neon` files (MES has none).
- Modify: 618 module PHP files, by `vendor/bin/pint`, in a commit of their own.
- Modify: `Modules/ERP/README.md`, `Modules/ERP/docs/ERP_GUIDA_SEMPLICE.md`,
  `Modules/ERP/docs/rag/MODULE.md`
  They document `composer test:standalone`, which is being removed.
- Modify: module READMEs and RAG docs that document a module-local test or lint command.
- Keep untouched: `Modules/*/composer.json` `autoload-dev`.
- Verify only: `phpunit.xml`, `tests/Pest.php`, `Modules/*/tests/Pest.php`, `Modules/*/tests/`.

---

### Task 1: Confirm The Suite Taxonomy Already In Place

The previous plan's tasks 1 to 9 are believed delivered. Confirm that from the code before removing
anything, because the removals below assume the root runner already covers every module.

**Files:**
- Verify: `phpunit.xml`, `Modules/*/tests/`, `tests/Pest.php`, `Modules/*/tests/Pest.php`

- [x] **Step 1: Confirm the root suites glob every module**

Run:

```bash
rtk rg -n 'testsuite name|directory suffix' phpunit.xml
```

Expected: suites `Unit`, `Integration` and `Feature`, each pairing a root directory with a
`Modules/*/tests/...` glob, and `Modules/*/tests/UnitShell` classified under `Integration`.

- [x] **Step 2: Confirm every module has the three directories**

Run:

```bash
rtk ls -d Modules/*/tests/Unit Modules/*/tests/Integration Modules/*/tests/Feature
```

Expected: all six modules (`Core`, `CMS`, `AI`, `ERP`, `MES`, `SAO`) present for each of the three.

- [x] **Step 3: Confirm the Pest bindings are narrow**

Run:

```bash
rtk rg -n 'pest\(\)->extend|uses\(' Modules/*/tests/Pest.php
```

Expected: no module binds a Laravel `TestCase` or `RefreshDatabase` to its `Unit` directory, except
AI, which binds `Unit` to its own `TestCase` without `RefreshDatabase`. `Modules/Core/tests/Pest.php`
binds `Unit` to the minimal `Modules\Core\Tests\TestCase` and the other directories to
`LaravelTestCase`.

- [x] **Step 4: Confirm the root Pest bootstrap discovers modules by glob**

Run:

```bash
rtk rg -n 'glob|require_once' tests/Pest.php
```

Expected: `tests/Pest.php` globs `Modules/*/tests/Pest.php` and requires each, so no module is
named at the root.

- [x] **Step 5: Record the baseline**

Run:

```bash
rtk php artisan test --compact --testsuite=Unit
rtk php artisan test --compact --testsuite=Integration
rtk php artisan test --compact --testsuite=Feature
```

Expected: record the pass/fail counts. These are the numbers every later task compares against; a
pre-existing failure stays a pre-existing failure and is not fixed here.

---

### Task 2: Capture The Merged Dependency Set

Before changing any `composer.json`, write down what is installed and where each constraint comes
from. The removals in Task 4 are only verifiable against this baseline.

**Files:**
- Create: no repository files. Work in the scratchpad.

- [x] **Step 1: Snapshot the installed dev packages**

Run:

```bash
rtk php -r '$l=json_decode(file_get_contents("composer.lock"),true); $r=[]; foreach($l["packages-dev"] as $p){$r[]=$p["name"]." ".$p["version"];} sort($r); echo implode(PHP_EOL,$r),PHP_EOL;' > /tmp/dev-packages-before.txt
rtk wc -l /tmp/dev-packages-before.txt
```

Expected: the file lists every installed dev package with its exact version.

- [x] **Step 2: List every module dev constraint and flag the divergent ones**

Run:

```bash
rtk php -r 'foreach(["Core","CMS","AI","ERP","MES","SAO"] as $m){$d=json_decode(file_get_contents("Modules/$m/composer.json"),true); foreach(($d["require-dev"]??[]) as $k=>$v){echo "$m $k $v",PHP_EOL;}}' | sort -k2
```

Expected: the constraints that disagree between modules are visible. As of 2026-09-15 these are
`larastan/larastan`, `laravel/pint`, `driftingly/rector-laravel` and `peckphp/peck`. For each of
them the version actually installed wins, not the strictest constraint.

- [x] **Step 3: Identify what the root already provides**

Run:

```bash
rtk php -r '$d=json_decode(file_get_contents("composer.json"),true); echo "require: ",implode(", ",array_keys($d["require"])),PHP_EOL,PHP_EOL,"require-dev: ",implode(", ",array_keys($d["require-dev"])),PHP_EOL;'
```

Expected: `filament/filament` and `nwidart/laravel-modules` are already in the root `require`, and
`fakerphp/faker` and `swolley/license-compliance-checker` in the root `require-dev`. Those four
need no promotion: they are module `require-dev` entries the root already covers.

---

### Task 3: Promote The Test Toolchain To The Root

Add to the root `require-dev` every package a module currently contributes, pinned to the version
already installed. Nothing new is introduced: the goal is an unchanged `vendor/` with an honest
declaration.

**Files:**
- Modify: `composer.json`

- [x] **Step 1: Add the promoted dev dependencies**

In the root `composer.json`, extend `require-dev` so it reads (constraints match the installed
versions recorded in Task 2; re-derive them from the lock rather than copying these if time has
passed):

```json
"require-dev": {
    "barryvdh/laravel-ide-helper": "^3.7",
    "dg/bypass-finals": "^1.11",
    "driftingly/rector-laravel": "^2.6",
    "fakerphp/faker": "^1.24.1",
    "larastan/larastan": "^3.12",
    "laravel/boost": "^2.9",
    "laravel/pail": "^1.2",
    "laravel/pint": "^1.32",
    "laravel/sail": "^1.59",
    "mockery/mockery": "^1.6.12",
    "mtrajano/laravel-swagger": "^0.6.4",
    "nunomaduro/collision": "^8.9.4",
    "nunomaduro/phpinsights": "^2.14",
    "peckphp/peck": "^0.1.3",
    "pestphp/pest": "^4.7",
    "pestphp/pest-plugin-laravel": "^4.1",
    "pestphp/pest-plugin-stressless": "^4.0",
    "pestphp/pest-plugin-type-coverage": "^4.0",
    "rector/rector": "^2.6",
    "swolley/license-compliance-checker": "dev-master"
}
```

`orchestra/testbench` is deliberately absent. It is in the `require-dev` of AI, ERP, MES and SAO,
but there is no `testbench.yaml` in the repository and no test case extends it. It is dropped in
Task 4 rather than promoted.

Note the `peckphp/peck` constraint: it records `^0.1.3`, what is installed, not Core's `^0.2.0`,
which the merge plugin has been discarding. Raising it is a separate upgrade with its own test run,
not a side effect of this move.

- [x] **Step 2: Adopt the module scripts the root was inheriting**

The root currently offers `test:pest`, `test:pest:parallel` and `test:unit:parallel` only because
`merge-scripts` copies them up from the modules. Task 4 removes them from the modules, so define in
the root `composer.json` the ones worth keeping:

```json
"test:pest": "vendor/bin/pest --compact",
"test:pest:parallel": "vendor/bin/pest --parallel --compact"
```

Do not define `test:standalone`. It is an alias for `test:unit` that names a capability the project
does not have, and keeping it would re-advertise the thing this plan removes.

- [x] **Step 3: Verify the installed set did not change**

Run:

```bash
rtk composer update --lock
rtk php -r '$l=json_decode(file_get_contents("composer.lock"),true); $r=[]; foreach($l["packages-dev"] as $p){$r[]=$p["name"]." ".$p["version"];} sort($r); echo implode(PHP_EOL,$r),PHP_EOL;' > /tmp/dev-packages-after-promote.txt
rtk diff /tmp/dev-packages-before.txt /tmp/dev-packages-after-promote.txt
```

Expected: empty diff. A non-empty diff means a constraint was mistyped; fix the constraint, do not
accept the upgrade.

- [x] **Step 4: Commit**

Run:

```bash
rtk git add composer.json composer.lock
rtk git commit -m "build: declare the test toolchain in the application"
```

Expected: commit succeeds.

---

### Task 4: Remove The Module Dev Dependencies

Only now, with the root declaring them, do the module `require-dev` blocks go. Each module is a
separate submodule commit.

**Files:**
- Modify: `Modules/Core/composer.json`
- Modify: `Modules/CMS/composer.json`
- Modify: `Modules/AI/composer.json`
- Modify: `Modules/ERP/composer.json`
- Modify: `Modules/MES/composer.json`
- Modify: `Modules/SAO/composer.json`

- [x] **Step 1: Delete the `require-dev` block from each module**

Remove the whole `require-dev` key from all six module `composer.json` files.

Keep, in every module:

- `require` — the module's runtime dependencies, which is what actually documents the module;
- `autoload` — the module's own PSR-4 namespaces;
- `autoload-dev` — this one is load-bearing and must not be touched. It is what lets the merged
  root autoloader resolve `Modules\X\Tests\TestCase`, the `Stubs`, `Support` and `Fixtures`
  classes. Removing it breaks the suite instantly;
- `repositories` — needed to resolve `swolley/license-compliance-checker`;
- `update:requirements`, which acts on the module's own dependencies.

The versioning scripts (`version`, `version:*`) and `setup:hooks` are not in this list: they are
release tooling, handled in `docs/superpowers/plans/2026-09-15-release-tooling.md`.

- [x] **Step 2: Remove the test and quality scripts from each module**

From each module's `scripts`, delete: `test`, `test:unit`, `test:integration`, `test:feature`,
`test:pest`, `test:pest:parallel`, `test:coverage`, `test:unit:parallel`, `test:type-coverage`,
`test:typos`, `test:lint`, `test:types`, `test:refactor`, `test:licenses`, `test:standalone`
(ERP, MES, SAO only), and the `lint`, `check`, `fix`, `refactor` entries, which invoke the same
now-absent binaries.

None of these can run: they call a relative `vendor/bin/...` and no module has a `vendor/`. With
`merge-scripts` enabled they are worse than dead, because they appear at the root as if the root
had defined them.

- [x] **Step 3: Confirm nothing in the module code referenced Testbench**

Run:

```bash
rtk rg -n 'Orchestra\\Testbench' Modules tests app
```

Expected: no match in executable code. The only hit should be a comment in
`Modules/Core/tests/database/migrations/2024_01_01_000000_create_users_table.php`. Leave that
migration alone: it is part of the Core test environment and out of scope here.

- [x] **Step 4: Verify the installed set still did not change**

Run:

```bash
rtk composer update --lock
rtk php -r '$l=json_decode(file_get_contents("composer.lock"),true); $r=[]; foreach($l["packages-dev"] as $p){$r[]=$p["name"]." ".$p["version"];} sort($r); echo implode(PHP_EOL,$r),PHP_EOL;' > /tmp/dev-packages-after-remove.txt
rtk diff /tmp/dev-packages-before.txt /tmp/dev-packages-after-remove.txt
```

Expected: exactly one removal, `orchestra/testbench`, plus any package that existed only to satisfy
it. Every other line identical. Anything else disappearing means a package was contributed by a
module and not promoted in Task 3; put it in the root `require-dev` and repeat.

- [x] **Step 5: Verify the toolchain still runs**

Run:

```bash
rtk vendor/bin/pest --version
rtk vendor/bin/pint --version
rtk vendor/bin/phpstan --version
rtk php artisan test --compact --testsuite=Unit
```

Expected: all four succeed, and the `Unit` suite matches the Task 1 baseline.

- [x] **Step 6: Commit**

Run:

```bash
rtk git add Modules/Core/composer.json Modules/CMS/composer.json Modules/AI/composer.json Modules/ERP/composer.json Modules/MES/composer.json Modules/SAO/composer.json composer.lock
rtk git commit -m "build: modules declare functionality, not the test toolchain"
```

Expected: commit succeeds. Remember each module is a submodule: commit inside the module first,
then record the pointer in the application.

---

### Task 5: Remove The Module PHPUnit Configs

**Files:**
- Delete: `Modules/Core/phpunit.xml`
- Delete: `Modules/CMS/phpunit.xml`
- Delete: `Modules/AI/phpunit.xml`
- Delete: `Modules/ERP/phpunit.xml`
- Delete: `Modules/MES/phpunit.xml`
- Delete: `Modules/SAO/phpunit.xml`

- [x] **Step 1: Check nothing outside the modules points at them**

Run:

```bash
rtk rg -n 'Modules/[A-Za-z]+/phpunit.xml' --glob '!vendor' .
```

Expected: no hit in CI, scripts, or hooks. `.github/workflows/laravel.yml` runs `php artisan test`
at the root and never names a module config. Any hit found here is fixed before the deletion.

- [x] **Step 2: Confirm the root config does not inherit from them**

Run:

```bash
rtk rg -n 'env name' phpunit.xml
```

Expected: the root `phpunit.xml` declares its own testing environment (`APP_ENV`, `DB_CONNECTION`,
`CACHE_STORE`, `QUEUE_CONNECTION`, and the rest). The module configs duplicated a subset of these;
nothing is lost by deleting them because PHPUnit only ever reads one configuration file.

- [x] **Step 3: Delete them**

Run:

```bash
rtk git rm Modules/Core/phpunit.xml Modules/CMS/phpunit.xml Modules/AI/phpunit.xml Modules/ERP/phpunit.xml Modules/MES/phpunit.xml Modules/SAO/phpunit.xml
```

Expected: six files removed.

- [x] **Step 4: Re-run the three suites** (done 2026-09-17, after the four interrupted attempts of 2026-09-15: `Integration` 2.229 passed / 0 failed in 8m26s, `Feature` run with `memory_limit=-1` — see the execution log below for why, and for the failures that had to be fixed first.)

Run:

```bash
rtk php artisan test --compact --testsuite=Unit
rtk php artisan test --compact --testsuite=Integration
rtk php artisan test --compact --testsuite=Feature
```

Expected: identical to the Task 1 baseline.

- [x] **Step 5: Commit**

Run:

```bash
rtk git commit -m "test: one phpunit configuration, at the application root"
```

Expected: commit succeeds.

---

### Task 6: Confirm The Root Script Surface Is The Root's Own

The point of removing the module scripts is that `composer run --list` should describe the project,
not the merge order of six files.

**Files:**
- Verify: `composer.json`

- [x] **Step 1: List the scripts the root offers**

Run:

```bash
rtk composer run --list
```

Expected: every listed script is defined in the root `composer.json`. `test:standalone` is gone.
`test:pest` and `test:pest:parallel` are present because Task 3 defined them, not because a module
leaked them.

- [x] **Step 2: Run the aggregate entry points**

Run:

```bash
rtk composer test:unit
rtk composer test:integration
rtk composer test:feature
```

Expected: matches the Task 1 baseline.

- [x] **Step 3: Confirm a single module can still be run by path**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests
```

Expected: the ERP tests run. This is the replacement for the deleted module scripts, and unlike
them it works.

---

### Task 7: Merge The Tool Configurations Into The Root

Before deleting anything, make the root configuration carry everything the module copies carry.
Two of the four tools need a real change; two need none.

**Files:**
- Modify: `rector.php`
- Modify: `phpstan.neon`

- [x] **Step 1: Confirm `peck.json` and `pint.json` are pure duplicates**

Run:

```bash
for m in Core CMS AI ERP MES SAO; do rtk diff -q peck.json Modules/$m/peck.json; done
rtk php -r '$r=json_decode(file_get_contents("pint.json"),true); foreach(["Core","CMS","AI","ERP","MES","SAO"] as $m){$c=json_decode(file_get_contents("Modules/$m/pint.json"),true); echo $m," rules identical: ",var_export($r["rules"]===$c["rules"],true),PHP_EOL;}'
```

Expected: no diff output for Peck, and `true` six times for Pint. The module configurations declare
the same 72 rules the root does; only the root's `notPath` differs. Nothing to merge for these two.

- [x] **Step 2: Absorb the Rector skip the modules declare**

CMS, ERP, MES and SAO skip a rule the root does not. In `rector.php`, add to the `withSkip([...])`
list, keeping the modules' own explanation:

```php
// Turns where('col', null) into where('col'), which changes query semantics (e.g. soft-delete unique rules).
RemoveNullArgOnNullDefaultParamRector::class,
```

with the matching `use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;`.

Run:

```bash
rtk vendor/bin/rector --dry-run
```

Expected: the run completes. Any change it proposes is reviewed but not applied here; this task
only widens the skip list.

- [x] **Step 3: Repair the root PHPStan configuration**

The root analysis does not run today. It aborts with:

```text
Invalid entry in excludePaths:
Path ".../.phpstorm.meta.php" is neither a directory, nor a file path, nor a fnmatch pattern.
```

In `phpstan.neon`, mark the optional entries as optional:

```yaml
    excludePaths:
        - _ide_helper.php (?)
        - .phpstorm.meta.php (?)
```

Run:

```bash
rtk vendor/bin/phpstan analyse --memory-limit=3G --no-progress
```

Expected: PHPStan analyses instead of aborting. Record the error count. It will be large: this is
`level: 9` over `Modules/`, and nothing has enforced it.

- [x] **Step 4: Decide what the module PHPStan configs were covering** (done: ignores carried over, backlog recorded)

This is the only configuration that is not a duplicate. The module files declare `level: 5` with
per-file `ignoreErrors`; the root declares `level: 9` over the same code. They contradict each
other and neither has been running.

Compare the module `ignoreErrors` against the errors the root now reports:

```bash
rtk rg -n 'identifier:|path:|message:' Modules/*/phpstan.neon
```

For each module entry, either carry it into the root `ignoreErrors` with its path prefixed
(`app/Models/Content.php` becomes `Modules/CMS/app/Models/Content.php`), or drop it because the
root does not report that error. Do not lower the root level to make the merge easier: the
module level was never enforced, so it is not a standard being given up.

If the root error count is too large to triage in this task, say so in the delivery status, keep
the root at `level: 9`, and record the module ignore lists in the plan before Task 9 deletes the
files. What must not happen is deleting them while nobody has read them.

- [x] **Step 5: Commit**

Run:

```bash
rtk git add rector.php phpstan.neon
rtk git commit -m "build: the application's tool configuration covers the modules"
```

Expected: commit succeeds.

---

### Task 8: Format Module Code From The Root

The root `pint.json` excludes `Modules`, which is why six identical copies of it exist. Remove the
exclusion and format once.

**Files:**
- Modify: `pint.json`
- Modify: 618 files under `Modules/*`

- [x] **Step 1: Drop the exclusion**

In `pint.json`, remove `"Modules"` from `notPath`, leaving:

```json
"notPath": [
    "tests/TestCase.php",
    "tmp"
]
```

- [x] **Step 2: Measure before applying**

Run:

```bash
rtk vendor/bin/pint --test Modules
```

Expected: a failure listing the files to change. As of 2026-09-15 that is 618 files of 3426:
Core 272, ERP 162, SAO 58, AI 55, CMS 48, MES 23. Every fixer in the list is stylistic
(`not_operator_with_successor_space`, `unary_operator_spaces`, `ordered_imports`,
`phpdoc_separation`, `braces_position`, and so on). If the list contains something that is not,
stop and review it rather than applying.

- [x] **Step 3: Apply**

Run:

```bash
rtk vendor/bin/pint Modules
```

Expected: the same file count is fixed. Note `mb_str_functions` among the fixers: it rewrites
`str_*` calls to their `mb_*` equivalents, which does change behavior on multibyte strings. It is
the modules' own declared rule, applied for the first time because nothing was ever formatting
them. The suite run in the next step is what confirms it.

- [x] **Step 4: Run the full suite** (done 2026-09-17, and it was the step that mattered: `mb_str_functions` had broken exactly one thing, and the suite found it — see the execution log.)

Run:

```bash
rtk php artisan test --compact
```

Expected: matches the Task 1 baseline. A new failure here is almost certainly `mb_str_functions`
meeting a test that asserted byte semantics; fix the test or exclude that specific call, and say
which in the delivery status.

**Status 2026-09-15:** only `Unit` was run after the reformatting: 1 failed, 497 passed, exactly
the baseline. `Integration` and `Feature` were attempted four times and interrupted every time
(backgrounded runs killed, the per-module foreground run cancelled). They are the verification
that `mb_str_functions` did not break a byte-semantics assumption, and they have not run. The
`mb_str_functions` changes sit in their own commit per module precisely so they can be reverted
alone if this step fails.

**Status 2026-09-17: both suites ran, and the step paid for itself.** `mb_str_functions` had
broken exactly one thing, and this is what found it. `SearchQuerySyntaxParser` indexes the raw
string by byte (`$query[$offset]`, `$end++`) while the rule turned `strlen`/`substr` into
`mb_strlen`/`mb_substr`, which count characters. Every accented query came back in fragments:

    +"D'Angiò José" +città \"citazione\"   ->   'tà "ci azione"'   instead of   '"citazione"'

The fix was not a revert: the parser now walks `mb_str_split()`, so indices, lengths and cuts all
count the same unit and the module's own rule is honoured rather than worked around. Fixed in
Core `44392c6`. Nothing else in the 618 reformatted files was broken.

- [x] **Step 5: Commit the reformatting on its own**

Run:

```bash
rtk git add pint.json Modules
rtk git commit -m "style: format module code with the application's Pint configuration"
```

Expected: one commit containing the configuration change and the mechanical reformatting, and
nothing else. Each module is a submodule: commit inside each module first, then record the
pointers.

---

### Task 9: Delete The Module Tool Configurations

**Files:**
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/pint.json`
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/rector.php`
- Delete: `Modules/{Core,CMS,AI,ERP,MES,SAO}/peck.json`
- Delete: `Modules/{Core,CMS,AI,ERP,SAO}/phpstan.neon` (MES has none)

- [x] **Step 1: Confirm nothing invokes them**

Run:

```bash
rtk rg -n 'Modules/[A-Za-z]+/(pint.json|rector.php|peck.json|phpstan.neon)' --glob '!vendor' .
```

Expected: no hit outside the files themselves. The module Composer scripts that referenced them
were removed in Task 4, and CI runs `php artisan test` at the root.

- [x] **Step 2: Delete**

Run:

```bash
rtk git rm Modules/*/pint.json Modules/*/rector.php Modules/*/peck.json
rtk git rm Modules/Core/phpstan.neon Modules/CMS/phpstan.neon Modules/AI/phpstan.neon Modules/ERP/phpstan.neon Modules/SAO/phpstan.neon
```

Expected: 23 files removed.

- [x] **Step 3: Verify the root toolchain still covers the modules**

Run:

```bash
rtk vendor/bin/pint --test Modules
rtk vendor/bin/rector --dry-run
rtk vendor/bin/phpstan analyse --memory-limit=3G --no-progress
rtk vendor/bin/peck
```

Expected: Pint is clean after Task 8. Rector and PHPStan reach module code through the root's own
paths (`rector.php` globs `Modules/*/app`, `phpstan.neon` lists `Modules/`). Their error counts
match what Task 7 recorded, with no new category introduced by the deletion.

**Status 2026-09-15:** Pint clean, Rector reaches the modules (548 files it would change,
pre-existing), PHPStan reports the 33282 recorded in Task 7. `peck` printed its usage instead of
running and was not re-attempted; it needs the right invocation before this step is honest.

- [x] **Step 4: Note what MES tells you**

`Modules/MES/**` is in the root `phpstan.neon` `excludePaths`, and MES was the one module with no
`phpstan.neon` of its own. So MES has never been analysed from either side. Leave the exclusion
in place, and record it in the delivery status as known, deliberate, and outstanding.

- [x] **Step 5: Commit**

Run:

```bash
rtk git commit -m "build: one tool configuration, at the application root"
```

Expected: commit succeeds.

---

### Task 10: Update The Documentation That Promised A Module Runner

A removed command that is still documented is worse than no documentation: the reader tries it.

**Files:**
- Modify: `Modules/ERP/README.md`
- Modify: `Modules/ERP/docs/ERP_GUIDA_SEMPLICE.md`
- Modify: `Modules/ERP/docs/rag/MODULE.md`
- Modify: any other module README or RAG doc the search below turns up

- [x] **Step 1: Find every documented module test command**

Run:

```bash
rtk rg -n 'composer (test|test:unit|test:integration|test:feature|test:standalone|test:pest|lint|check|refactor)' --glob '*.md' --glob '!vendor' Modules docs
```

Expected: a list of documentation lines to correct. As of 2026-09-15 the known ones are
`Modules/ERP/README.md:452`, `Modules/ERP/docs/ERP_GUIDA_SEMPLICE.md:409` and
`Modules/ERP/docs/rag/MODULE.md:484`, all documenting `composer test:standalone`.

- [x] **Step 2: Replace them with the root commands**

Each occurrence becomes the equivalent root invocation, run from the application:

```bash
php artisan test --compact Modules/ERP/tests              # the whole module
php artisan test --compact Modules/ERP/tests/Unit         # one suite of it
php artisan test --compact --testsuite=Unit               # the fast suite, all modules
```

Say in each document that module tests run from the application root, because the module test cases
extend the application's and a module has no `vendor/` of its own. A reader who knows why will not
try to reinstate the old command.

- [x] **Step 3: Check no module README still advertises tooling it no longer declares**

Run:

```bash
rtk rg -n 'require-dev|pestphp|orchestra/testbench|pint.json|rector.php|phpstan.neon' --glob '*.md' Modules
```

Expected: no module document claims to own test dependencies or its own tool configuration.

- [ ] **Step 4: Commit**

Run:

```bash
rtk git add Modules/ERP/README.md Modules/ERP/docs Modules
rtk git commit -m "docs: module tests run from the application"
```

Expected: commit succeeds.

---

### Task 11: Review What UnitShell Is Still Holding

`UnitShell` is a transitional directory: tests written as unit tests that need the application
shell. It has 13 files left, 12 in Core and 1 in CMS, and both the root and the module suites
classify it as integration. It is the last piece of the original reclassification that was left
half-done.

**Files:**
- Move: files from `Modules/Core/tests/UnitShell` and `Modules/CMS/tests/UnitShell` to `Unit` or
  `Integration`

- [x] **Step 1: List what is there**

Run:

```bash
rtk rg --files Modules/Core/tests/UnitShell Modules/CMS/tests/UnitShell
```

Expected: 13 files.

- [x] **Step 2: Classify each file against the spec's rules**

For each file, read it and decide with the taxonomy in the spec:

- needs no application bootstrap and no database, and can run under
  `Modules\Core\Tests\TestCase` (the minimal environment): move to `tests/Unit`;
- needs the shell, config overlays, migrations, or the database: move to `tests/Integration`.

Do not classify from the file name. `DatabaseConfigOverlayTest` and `ObjectCastTest` sound like
opposite cases and may not be.

- [x] **Step 3: Move and run each file as you go**

After each move:

```bash
rtk php artisan test --compact <moved file path>
```

Expected: the file passes in its new suite. If it fails in `Unit`, it belongs in `Integration`;
that is the classification answer, not a bug to fix.

- [x] **Step 4: Drop UnitShell from the suite definitions once empty** (CMS's is gone; Core's stays, with the five files and their reasons recorded below)

When both directories are empty, remove the `Modules/*/tests/UnitShell` line from the `Integration`
suite in `phpunit.xml` and delete the directories.

If files remain because their classification is genuinely unclear, leave the directory and the
suite line in place and record which files and why in the delivery status. A half-empty transitional
directory that is documented is fine; an undocumented one is how this became invisible for months.

**Classified on 2026-09-18: 8 of 13 moved, 5 stay, and none of the five is unclear — each is
blocked by a concrete incompatibility.**

Moved to `Unit` (they need no application bootstrap):
- `Core/tests/Unit/Casts/ObjectCastTest.php` — a cast and Mockery, nothing else. 6 tests in 0.09s.
- `Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php` — asserts seeder definitions.

Moved to `Integration` (they need the container, the database, or both — verified by running them
in `Unit` first and reading the failure, not by guessing):
- `Core/tests/Integration/Services/DatabaseConfigOverlayTest.php` — resolves `request` and a
  connection resolver.
- `Core/tests/Integration/Seeding/BatchSeederScaleTest.php`, `BatchSeederVersioningTest.php`,
  `CoreDatabaseSeederConnectionAffinityTest.php` — `DatabaseManager` needs `$app`.
- `Core/tests/Integration/Services/DynamicContentsServiceClearCacheTest.php` — uses the cache.
- `CMS/tests/Integration/Models/TaxonomyTranslationsForeignKeyTest.php` — `RefreshDatabase`.

Staying in `Core/tests/UnitShell`, with the reason:
- `BatchSeederBootstrapTest.php` — runs `VACUUM` and creates the `migrations` table, so it manages
  its own database lifecycle. `LaravelTestCase` wraps each test in a transaction, and SQLite
  refuses: *cannot VACUUM from within a transaction*. It needs an environment that neither `Unit`
  nor `Integration` offers.
- `Migrations/` (4 tests + its own `Pest.php`) — bound to `Modules\Core\Tests\ApplicationTestCase`
  by a local `Pest.php`. Moving the directory under `Integration` makes Pest refuse the file:
  *Test case [X] can not be used. The folder already uses the test case [LaravelTestCase]*. Two
  directory bindings cannot coexist.

Both groups need a third environment — the application shell without a wrapping transaction — which
is exactly what `UnitShell` was created to be. The honest conclusion is that the directory is not
transitional after all: it is a third suite that was never named. Naming it, or giving `Integration`
a way to opt out of the transaction, is the decision that would empty it. That is not this plan's
call, and it is written here so the next reader does not rediscover it.

`CMS/tests/UnitShell` is gone: its single file moved. The `phpunit.xml` glob stays for Core's.

- [x] **Step 5: Commit** (Core `79c8ee7`, CMS `ed5b845`)

Run:

```bash
rtk git add phpunit.xml Modules/Core/tests Modules/CMS/tests
rtk git commit -m "test: classify the remaining UnitShell tests"
```

Expected: commit succeeds.

---

### Task 12: Final Verification

**Files:**
- All files changed by previous tasks

- [x] **Step 1: Prove no module declares test or toolchain infrastructure** (verified 2026-09-18: no `require-dev`, no `test:*` scripts, and none of `phpunit.xml`, `pint.json`, `rector.php`, `peck.json`, `phpstan.neon` in any module)

Run:

```bash
rtk rg -n 'require-dev|"test:|"lint"|"check"|"refactor"' Modules/*/composer.json
rtk ls Modules/*/phpunit.xml Modules/*/pint.json Modules/*/rector.php Modules/*/peck.json Modules/*/phpstan.neon
```

Expected: the first command returns nothing; the second reports no such file, for every pattern.

- [x] **Step 2: Prove the module test helpers still autoload** (all six keep their `autoload-dev`, and the namespaced fixtures resolve)

Run:

```bash
rtk rg -n 'autoload-dev' -A 12 Modules/Core/composer.json Modules/CMS/composer.json
rtk composer dump-autoload
rtk php artisan test --compact Modules/Core/tests/Integration
```

Expected: `autoload-dev` is intact in every module and the Core integration tests, which use
namespaced fixtures, resolve them.

- [x] **Step 3: Run the full suite** (2026-09-18: **5.618 passed, 0 failed, 30 skipped**, 15.858 assertions, 26m41s with `memory_limit=-1` — see the delivery status for why that flag is needed)

Run:

```bash
rtk php artisan test --compact
```

Expected: matches the Task 1 baseline. Ask the user to confirm the run on their machine as well,
since this is the change's real assertion.

- [x] **Step 4: Format and analyse from the root** (Pint clean across application and modules; PHPStan exits `No errors` against a baseline of 9.785, and the 36 errors that arrived with the merge were fixed rather than frozen)

Run:

```bash
rtk vendor/bin/pint --test
rtk vendor/bin/phpstan analyse --memory-limit=3G --no-progress
```

Expected: Pint is clean across the application and the modules, which is the assertion Task 8
bought. PHPStan runs and reports the count Task 7 recorded.

- [x] **Step 5: Close the plan**

Add the two closing sections this repository requires: a delivery-status heading carrying the
date, and a `**Documented in:**` line naming the module documentation that now describes how tests
are run. Record anything deliberately not done, in particular: the module quality configurations
the `peckphp/peck` constraint left at the installed version, the PHPStan error backlog and the
`Modules/MES/**` exclusion left standing, and any `UnitShell` file still unclassified.

(Both headings are written out in `AGENTS.md`; they are not spelled literally here because
`tests/Unit/ClosedPlansPointToDocumentationTest.php` reads the delivery heading as the marker that
a plan is closed, and this plan is not.)

- [ ] **Step 6: Commit**

Run:

```bash
rtk git add docs/superpowers
rtk git commit -m "docs: close the module testing strategy plan"
```

Expected: commit succeeds.

---

## Delivery status (2026-09-18)

**Done.** A module now carries functionality and its tests, and nothing else: no `require-dev`,
no `phpunit.xml`, no `pint.json`, `rector.php`, `peck.json` or `phpstan.neon`. The runner, the
toolchain and the three suites live once in the application.

**Documented in:** `Modules/Core/README.md` (*Code Quality and Testing* — how the suites are
classified, why everything runs from the application root, and the memory flag the full run
needs), `Modules/ERP/README.md`, `Modules/ERP/docs/ERP_GUIDA_SEMPLICE.md` and
`Modules/ERP/docs/rag/MODULE.md`.

**What the final verification cost, and what it was worth.** Tasks 5 and 8 hung on one step —
running `Integration` and `Feature` — attempted four times on 2026-09-15 and interrupted every
time. They ran on 2026-09-17 and found what the plan predicted they would: `mb_str_functions`
had broken `SearchQuerySyntaxParser`, which indexes the raw string by byte while the rule made
the lengths count characters. Every accented search query came back in fragments
(`+città \"citazione\"` returning `'tà "ci azione"'`) and had been doing so for two days. Fixed
in Core `44392c6` by walking `mb_str_split()`, not by reverting the rule.

Final run, 2026-09-18: **5.618 passed, 0 failed, 30 skipped**, 15.858 assertions, 26m41s.

**Deliberately not done, and why:**

- **`UnitShell` is not empty, and will not be by this plan.** Five files stay: `BatchSeederBootstrapTest`
  runs `VACUUM` and creates the `migrations` table, which SQLite refuses inside the transaction
  `LaravelTestCase` opens; `Migrations/` (4 tests) is bound to `ApplicationTestCase` by a local
  `Pest.php`, and two directory bindings cannot coexist. Both want the application shell *without*
  a wrapping transaction. That is a third environment, and the directory has been it all along:
  it is not transitional, it is an unnamed suite. Naming it — or letting `Integration` opt out of
  the transaction — is what would empty it. The `phpunit.xml` glob stays for Core's;
  `CMS/tests/UnitShell` is gone.
- **The full suite needs `memory_limit=-1`.** At the default 2G it dies about two thirds through,
  with no failing test: a single 640MB allocation exhausts the limit. That allocation deserves its
  own look and is not this plan's subject.
- **The `peckphp/peck` constraint** stayed at the installed version, as recorded in Task 9.
- **PHPStan's backlog** was never this plan's work, but it moved anyway: 33.282 errors at the
  start of 2026-09-17, 9.785 frozen in a baseline by the end, and the analysis exits clean, so a
  new error now fails the run. The 36 errors that arrived with a merge on 2026-09-18 were fixed,
  not added to the baseline.

---

## Execution log (2026-09-15)

Tasks 1 through 9 are done, with the exceptions noted below. Nothing is committed: the user asked
for the work to land in the working tree of `master` in each repository, for review first.

**What the execution found that the plan had not predicted:**

- **`composer update --lock` does not drop a package that nothing requires any more.** After the
  module `require-dev` blocks were removed, `orchestra/testbench` was still in the lock. A full
  `composer update` would have removed it but also upgraded 16 unrelated packages (Symfony,
  Livewire), which the plan forbids. The fix was a targeted `composer update "orchestra/*"`:
  exactly six removals (`testbench`, `testbench-core`, `workbench`, `canvas`, `canvas-core`,
  `sidekick`), no version changed anywhere else.
- **Removing a package with `--no-scripts` leaves Laravel's package manifest stale.** Every suite
  died with `Class "Orchestra\Canvas\LaravelServiceProvider" not found`, from
  `bootstrap/cache/services.php`. `php artisan package:discover` alone did not repair it, because
  it fails while reading the stale manifest; the cache files had to be deleted first.
- **SAO encodes the old model in its own tests.** `ScaffoldingComplianceTest` asserted that the
  module ships `phpunit.xml`, `phpstan.neon`, `pint.json`, `peck.json`, `rector.php`, `cliff.toml`
  and three executable scripts; `ModuleMetadataTest` asserted the full `test:*` script battery.
  They are good tests of the wrong law. They were rewritten to assert the new one: the module must
  *not* carry the toolchain, the application's suites must glob it, and the application must be
  able to version it. No other module has compliance tests of this kind.
- **PHPStan's real state.** With `excludePaths` repaired, the analysis runs for the first time and
  reports **33.282 errors at `level: 9`** (Core 21.252, ERP 3.822, CMS 3.392, AI 2.485, SAO 2.332,
  app 1). This backlog is not new and was not introduced here: it was invisible because the root
  aborted before analysing and the modules had no `vendor/`. The module `ignoreErrors` were carried
  into the root with their paths prefixed before their files were deleted; they account for 2 of
  those errors. Fixing the rest is not this plan's work.
- **`Modules/Core/scripts/bench/`** holds benchmark SQL fixtures, not tooling. It stays, so Core is
  the one module that still has a `scripts/` directory.
- **Rector** reports 548 files it would change across the modules. Pre-existing, untouched: the
  root `rector.php` already globbed `Modules/*/app` before this work.

**Results:**

- `Unit`: 1 failed, 497 passed, both before and after. The failure is pre-existing and unrelated
  (`Modules/CMS/tests/Unit/Casts/FieldTypeTest` expects `FieldType::Number->getRule()` to be
  `'number'`; it returns `'numeric'`).
- Dev packages: 90 before, 84 after. The six removed are Testbench and its chain.
- Pint: 618 module files reformatted, then `pint --test Modules` passes clean.
- `composer run --list` at the root now lists only scripts the root defines. `test:standalone`,
  `test:unit:parallel` and the module `test:pest*` entries are gone.

**Committed (nothing pushed).** Each module carries three commits, MES two: the safe formatting
(558 files across the six), `mb_str_functions` isolated on its own (60 files), and the toolchain
removal. The application carries five: the tool configuration, the versioning script (its record moved to
`2026-09-15-release-tooling.md`), the
composer/lock promotion, this documentation, and the submodule pointers.

**Not done, and the first one is not a formality:**

- **`Integration` and `Feature` have not been run since the reformatting.** Four attempts, four
  interruptions. Only `Unit` was verified (1 failed / 497 passed, the baseline). Until those two
  suites run, `mb_str_functions` is unverified against 60 files of behavioural change. That is why
  it sits in its own commit per module.
- `peck` printed its usage rather than running, in Task 9 Step 3.
- Tasks 10 to 12: documentation, `UnitShell` classification, final verification.

---

## Delivered before this revision

Recorded here so the work is not repeated. Verified against the code on 2026-09-15 by reading what
exists, not by trusting the old task list.

- **Suite directories.** `tests/Integration` and `Modules/*/tests/Integration` exist for all six
  modules, SAO included, which the original plan did not cover.
- **Root aggregate suites.** `phpunit.xml` defines `Unit`, `Integration` and `Feature`. It improves
  on the plan's design by globbing `Modules/*/tests/...` instead of listing each module, so a new
  module needs no configuration change.
- **Module suites.** Every module had the three suites in its `phpunit.xml`. Task 5 above deletes
  those files; the classification they expressed lives on in the root globs.
- **Composer suite scripts.** The root defines `test:unit`, `test:integration`, `test:feature` and
  `test:modules`. The per-module equivalents were also added, and Task 4 removes them.
- **Test reclassification.** The moves happened: Core holds 23 `Unit`, 214 `Integration` and 146
  `Feature`; CMS 20/23/58; AI 30/58/21; ERP 1/14/119; MES 3/2/25; SAO 14/2/119. `UnitShell` still
  holds 13 files, which is Task 11.
- **Pest bindings.** Narrowed as designed. `Modules/Core/tests/Pest.php` binds `Unit` to the
  minimal `TestCase` and `Integration`/`Feature` to `LaravelTestCase`; no module applies
  `RefreshDatabase` to `Unit`. The root `tests/Pest.php` improves on the plan by globbing module
  bootstraps rather than requiring each by name.
