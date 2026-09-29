# AI Model Selection and Setting Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every AI feature gets a Filament setting holding its `provider:model`, whose choices a command on the setting row refreshes from the providers' own model lists.

**Architecture:** Core gains a generic, seeded-only `action_command` / `action_queued` pair on `core_settings`, run by `SettingActionRunner` from a settings-grid row action, plus command-managed choices and a settings overlay re-applied before every queued job. AI gains `AiModelFeature` / `AiModelChoice`, one `ModelLister` per provider, a `ModelCatalog`, the `ai:models:refresh` command, and wires every consumer to its feature's choice. Translation picks DeepL or an AI model in one setting, with no fallback.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Livewire 4, Pest, NeuronAI 3.17, nwidart modules. `Modules/Core` is the `laraplate-core` repository, `Modules/AI` is `laraplate-ai`, both submodules of `laraplate`.

**Spec:** `docs/superpowers/specs/2026-09-29-ai-model-selection-and-setting-actions-design.md`

## Delivery status (2026-09-29): shipped

**Documented in:** `Modules/Core/docs/rag/SETTING_ACTIONS_USER.md`, `Modules/Core/docs/rag/SETTING_ACTIONS_DEVELOPER.md`, `Modules/AI/docs/rag/AI_MODEL_SELECTION_USER.md`, `Modules/AI/docs/rag/AI_MODEL_SELECTION_DEVELOPER.md`, `Modules/AI/README.md`, `Modules/AI/docs/SEARCH_AND_TRANSLATION.md`, `Modules/AI/docs/WHISPER_INSTALLATION.md`.

Every task shipped, executed inline with one commit per task in `laraplate-core` and `laraplate-ai`, each pushed. Divergences from the steps:

- **Model refresh actions are seeded queued** (`action_queued = true`, spec said `false`): a synchronous refresh calls up to five providers in turn plus one Ollama request per installed model and can outlast a web request's time limit, which is a fatal error nothing catches. The grid reports "Command queued".
- **Ollama base URL:** `OLLAMA_API_URL` is the server base; chat now appends `/api` like embeddings and the lister. Before, chat received the bare base and called `/chat` (pre-existing bug found by the final review).
- **Malformed provider answers fail the provider** (an answer without a model list no longer counts as an empty listing), and `ModelCatalog` contains any provider error instead of only HTTP ones.
- **Translation completion on give-up:** `TranslateModelJob` signals `ModelPreProcessingCompleted('translation')` from `failed()` instead of on the last attempt inside `handle()`; the `--sync` translate commands report a failing model and continue.
- **Translation cache key** includes the chosen provider and model.
- **Overlay listener skips the `sync` queue connection:** such jobs run inside the request whose middleware already applied the overlay.
- `ModelSoftDeletesCommandsTest` builds its own `core_settings` table; it gained the two action columns.
- Environment: `php artisan event:clear` was run (a stale `bootstrap/cache/events.php` hid new listeners). `bootstrap/cache/routes-v7.php` is stale too and 404s `Modules/AI/tests/Feature/ChatTest` (unrelated to this plan); suites were run with `APP_ROUTES_CACHE` pointing to a missing file, and the cache was left in place.
- Deferred minors from the final review: the `choices` approval exemption also applies to CRUD API writes; Anthropic `has_more` without a cursor returns a partial list reported as listed; option values containing `''`/`""` are altered by Symfony's `StringInput`; the media transcription setting has no caller (`AnalyzeMediaJob` passes the vision profile to the transcriber, pre-existing); media analyses re-run once on the new `provider:model` version key.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Braces on every control structure, explicit parameter and return types, `#[Override]` on overridden members, `final` / `readonly` where the siblings use them. PHPDoc over inline comments. Code, comments and docs in English.
- No new dependencies, no new base folders.
- Core never depends on AI. AI depends on Core.
- A value managed by a setting has no config entry and no env variable; its default lives in code (`AiModelFeature::defaultChoice()`, or the default argument at the read site). Credentials, URLs and timeouts stay in env.
- Schema changes go into `Modules/Core/database/migrations/2024_03_30_170824_create_settings_table.php`; no alter migration. The developer database is rebuilt with `php artisan migrate:fresh --seed`.
- Run tests from the `laraplate` root: `php artisan test --compact <path>`. **Never with a cached config**: if `bootstrap/cache/config.php` exists, run `php artisan config:clear` first (a cached config points the suite at the real database and `RefreshDatabase` wipes it).
- Format from the `laraplate` root with an explicit file list: `vendor/bin/pint --format agent <files>`. Never run Pint inside a module and never with an empty list.
- Commit on `master` in the repository that owns the files: `git -C Modules/Core ...` for Core, `git -C Modules/AI ...` for AI, the root for `docs/`. No push. Every commit message ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Exact values from the spec: columns `action_command` (nullable string) and `action_queued` (boolean, default `false`); action template `ai:models:refresh --setting={name}`; schedule `dailyAt('03:00')`; lister timeouts connect 3 s, request 10 s; grid notification shows the last 1000 characters of output; a choice splits on the **first** `:`.
- Tick each step's checkbox when the step is done, in the same work block.

## Review Focus

- **Ollama ids contain a colon** (`ollama:llama3.2:3b`): parsing must split on the first `:` only, and the value must round-trip. Test in Task 8.
- **A provider answers with an empty list** (Ollama with no pulled model): no entries, reported as listed with 0 models, not as a failure. Test in Task 10.
- **A provider times out during the synchronous grid refresh** (`ConnectionException`): counted as a failure, previous entries kept, the command still finishes. Test in Task 10.
- **List-valued settings with choices** (`notifications.channels`, a checkbox list) must never be flagged as "no longer available". Test in Task 5.
- **Anthropic answers `has_more: true` with `last_id: null`**: pagination stops instead of looping. Test in Task 9.

---

### Task 1: Action columns on `core_settings` and the `Setting` model

**Files:**
- Modify: `Modules/Core/database/migrations/2024_03_30_170824_create_settings_table.php`
- Modify: `Modules/Core/app/Models/Setting.php`
- Create: `Modules/Core/tests/Feature/Models/SettingActionColumnsTest.php`

**Interfaces:**
- Produces: `Setting::$action_command` (`?string`), `Setting::$action_queued` (`bool`, cast), both outside `$fillable` and in `$hidden`; `choices` exempt from approval.

- [x] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassAssignmentException;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Support\HttpContext;

function settingActionColumnsFixture(array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'a',
        'choices' => ['a'],
        'encrypted' => false,
        ...$attributes,
    ]);
}

it('defaults action_queued to false', function (): void {
    expect((new Setting)->action_queued)->toBeFalse();
});

it('refuses to mass assign the action command', function (): void {
    (new Setting)->fill(['action_command' => 'probe:run']);
})->throws(MassAssignmentException::class);

it('stores the action columns but never serializes them', function (): void {
    $setting = settingActionColumnsFixture(['action_command' => 'probe:run {name}', 'action_queued' => true])->fresh();

    expect($setting->action_command)->toBe('probe:run {name}')
        ->and($setting->action_queued)->toBeTrue()
        ->and($setting->toArray())->not->toHaveKeys(['action_command', 'action_queued']);
});

it('writes a choices-only change directly for a writer who would otherwise be captured', function (): void {
    $setting = settingActionColumnsFixture();
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $fresh = $setting->fresh();
    $fresh->choices = ['a', 'b'];
    $fresh->save();

    expect($setting->fresh()->choices)->toBe(['a', 'b'])
        ->and($setting->modifications()->activeOnly()->exists())->toBeFalse();
});

it('still captures a value change from the same writer', function (): void {
    $setting = settingActionColumnsFixture();
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $fresh = $setting->fresh();
    $fresh->value = 'b';
    $fresh->save();

    expect($setting->fresh()->value)->toBe('a')
        ->and($setting->modifications()->activeOnly()->exists())->toBeTrue();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Models/SettingActionColumnsTest.php`
Expected: FAIL. `action_queued` is null, the factory cannot write the unknown `action_command` column, and the choices-only change is captured for approval.

- [x] **Step 3: Add the columns to the create migration**

In `2024_03_30_170824_create_settings_table.php`, right after the `seeded_value` column and before `MigrateUtils::timestamps(...)`:

```php
            $table->string('action_command')
                ->nullable(true)
                ->comment('Artisan command line run from the settings grid, with {attribute} placeholders; seeded only');
            $table->boolean('action_queued')
                ->nullable(false)
                ->default(false)
                ->comment('Queue the action command instead of running it inside the request');
```

- [x] **Step 4: Update the model**

In `Modules/Core/app/Models/Setting.php`:

Add after `$fillable`:

```php
    /**
     * The command a setting runs is code-owned: it is neither mass assignable nor exposed in
     * serialized output, since public settings are readable by guests.
     *
     * @var list<string>
     */
    #[Override]
    protected $hidden = [
        'action_command',
        'action_queued',
    ];
```

In `$attributes` add `'action_queued' => false,`. In `casts()` add `'action_queued' => 'boolean',`.

Replace `requiresApprovalWhen()` and its docblock with:

```php
    /**
     * Presentation-only fields (description, group) and the choices a refresh command writes
     * are applied directly: users cannot edit choices from the panel. Any other change follows
     * the shared approval rule: a writer holding the approve permission, when one approval is
     * enough, saves directly; everybody else goes through approval.
     */
    protected function requiresApprovalWhen(array $modifications): bool
    {
        $guarded = array_intersect_key(
            $modifications,
            array_flip(array_diff($this->getFillable(), ['description', 'group_name', 'choices'])),
        );

        if ($guarded === []) {
            return false;
        }

        return $this->requiresApprovalWhenTrait($modifications);
    }
```

- [x] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Models/SettingActionColumnsTest.php`
Expected: PASS (5 tests).

- [x] **Step 6: Run the neighbouring settings tests**

Run: `php artisan test --compact Modules/Core/tests/Feature/Filament/EditSettingFormTest.php Modules/Core/tests/Feature/Filament/ApprovalOutcomeTest.php`
Expected: PASS.

- [x] **Step 7: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/database/migrations/2024_03_30_170824_create_settings_table.php Modules/Core/app/Models/Setting.php Modules/Core/tests/Feature/Models/SettingActionColumnsTest.php
git -C Modules/Core add database/migrations/2024_03_30_170824_create_settings_table.php app/Models/Setting.php tests/Feature/Models/SettingActionColumnsTest.php
git -C Modules/Core commit -m "feat(settings): add seeded-only action columns and exempt choices from approval" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Seed definitions: realigned action columns, command-managed choices

**Files:**
- Modify: `Modules/Core/app/Overrides/Seeder.php` (`internalSettingsDefinition()`)
- Modify: `Modules/Core/tests/Feature/Seeding/InternalSettingsDefinitionTest.php`

**Interfaces:**
- Consumes: Task 1 columns.
- Produces: `Seeder::internalSettingsDefinition(string $module, array $rows): SeedDefinition` (structural now includes `action_command`, `action_queued`); `Seeder::commandManagedChoicesSettingsDefinition(string $module, array $rows): SeedDefinition` (same, minus `choices`). Both fill missing `action_command` / `action_queued` with `null` / `false`, so every upserted row has the same keys.

- [x] **Step 1: Write the failing tests**

Append to `InternalSettingsDefinitionTest.php` (add `use Modules\Core\Casts\SettingTypeEnum;` and `use Modules\Core\Seeding\SeedReconciler;` to the imports):

```php
function seedingDefinitionRow(array $overrides = []): array
{
    return [
        'name' => 'seed.managed',
        'value' => 'a',
        'encrypted' => false,
        'choices' => ['a'],
        'type' => SettingTypeEnum::String,
        'group_name' => 'test',
        'description' => 'Managed',
        ...$overrides,
    ];
}

it('realigns the action columns and defaults them on rows that declare none', function (): void {
    $definition = (new ReflectionMethod(Seeder::class, 'internalSettingsDefinition'))
        ->invoke(null, 'Core', [['name' => 'seed.plain']]);

    expect($definition->structural)->toContain('action_command', 'action_queued', 'choices')
        ->and($definition->rows[0]['action_command'])->toBeNull()
        ->and($definition->rows[0]['action_queued'])->toBeFalse();
});

it('leaves choices out of the realigned columns for command-managed settings', function (): void {
    $definition = (new ReflectionMethod(Seeder::class, 'commandManagedChoicesSettingsDefinition'))
        ->invoke(null, 'Core', [seedingDefinitionRow()]);

    expect($definition->structural)->toContain('action_command', 'action_queued')
        ->and($definition->structural)->not->toContain('choices');
});

it('realigns the action but keeps command-written choices on re-seed', function (): void {
    $managed = new ReflectionMethod(Seeder::class, 'commandManagedChoicesSettingsDefinition');
    $reconciler = app(SeedReconciler::class);

    $reconciler->reconcile($managed->invoke(null, 'Core', [seedingDefinitionRow(['action_command' => 'probe:one {name}'])]));

    Setting::query()->withoutGlobalScopes()->where('name', 'seed.managed')
        ->update(['choices' => json_encode(['a', 'b'])]);

    $reconciler->reconcile($managed->invoke(null, 'Core', [
        seedingDefinitionRow(['action_command' => 'probe:two {name}', 'action_queued' => true]),
    ]));

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'seed.managed')->sole();

    expect($setting->choices)->toBe(['a', 'b'])
        ->and($setting->action_command)->toBe('probe:two {name}')
        ->and($setting->action_queued)->toBeTrue();
});

it('still realigns the choices of ordinary settings', function (): void {
    $internal = new ReflectionMethod(Seeder::class, 'internalSettingsDefinition');
    $reconciler = app(SeedReconciler::class);

    $reconciler->reconcile($internal->invoke(null, 'Core', [seedingDefinitionRow(['name' => 'seed.ordinary'])]));

    Setting::query()->withoutGlobalScopes()->where('name', 'seed.ordinary')
        ->update(['choices' => json_encode(['a', 'b'])]);

    $reconciler->reconcile($internal->invoke(null, 'Core', [seedingDefinitionRow(['name' => 'seed.ordinary'])]));

    expect(Setting::query()->withoutGlobalScopes()->where('name', 'seed.ordinary')->sole()->choices)->toBe(['a']);
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/InternalSettingsDefinitionTest.php`
Expected: FAIL: `commandManagedChoicesSettingsDefinition` does not exist and `structural` lacks the action columns.

- [x] **Step 3: Implement the definitions**

In `Modules/Core/app/Overrides/Seeder.php`, replace `internalSettingsDefinition()` with the three methods below (keep its existing docblock on the first one, adding the sentence about actions):

```php
    /**
     * Reconcile definition for the settings a module ships.
     *
     * `is_internal` follows the declaring module's ownership, not the fact that a
     * seeder wrote the row: {@see is_laraplate_owned_module()} reads `module.json`
     * `laraplate_owned`, falling back to a `swolley/laraplate-*` composer name. A
     * third-party module shipping its own settings through this definition gets
     * `is_internal = false`. The flag is structural, so a re-seed realigns rows
     * written before it existed and follows a module that changes ownership.
     * `group_name` is written on insert only: operators regroup settings freely and
     * a re-seed keeps their choice. The action a setting runs is code-owned and
     * realigned like its type.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    protected static function internalSettingsDefinition(string $module, array $rows): SeedDefinition
    {
        return self::settingsDefinition(
            $module,
            $rows,
            ['type', 'description', 'choices', 'is_internal', 'action_command', 'action_queued'],
        );
    }

    /**
     * Same as {@see internalSettingsDefinition()} for settings whose choices a command
     * refreshes: `choices` is written when the row is created and never realigned, so a
     * re-seed cannot overwrite the list the command wrote.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    protected static function commandManagedChoicesSettingsDefinition(string $module, array $rows): SeedDefinition
    {
        return self::settingsDefinition(
            $module,
            $rows,
            ['type', 'description', 'is_internal', 'action_command', 'action_queued'],
        );
    }

    /**
     * Rows without an action get explicit defaults: an upsert needs every row to carry the
     * same columns, and a row whose action was removed must realign it to none.
     *
     * @param  list<array<string,mixed>>  $rows
     * @param  list<string>  $structural
     */
    private static function settingsDefinition(string $module, array $rows, array $structural): SeedDefinition
    {
        $is_internal = is_laraplate_owned_module($module);

        return SeedDefinition::for(Setting::class)
            ->identity(['name'])
            ->structural($structural)
            ->initial(['value'])
            ->ownedBy($module)
            ->rows(array_map(
                static fn (array $row): array => [
                    'action_command' => null,
                    'action_queued' => false,
                    ...$row,
                    'is_internal' => $is_internal,
                ],
                $rows,
            ));
    }
```

- [x] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/InternalSettingsDefinitionTest.php Modules/Core/tests/Feature/Seeding/CoreSettingsReconciliationTest.php`
Expected: PASS.

- [x] **Step 5: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Overrides/Seeder.php Modules/Core/tests/Feature/Seeding/InternalSettingsDefinitionTest.php
git -C Modules/Core add app/Overrides/Seeder.php tests/Feature/Seeding/InternalSettingsDefinitionTest.php
git -C Modules/Core commit -m "feat(seeding): realign setting actions and add command-managed choices definition" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `SettingActionRunner`

**Files:**
- Create: `Modules/Core/app/Services/SettingActionRunner.php`
- Create: `Modules/Core/app/Services/SettingActionResult.php`
- Create: `Modules/Core/app/Exceptions/InvalidSettingActionException.php`
- Create: `Modules/Core/tests/Stubs/Console/SettingActionProbeCommand.php`
- Create: `Modules/Core/tests/Feature/Services/SettingActionRunnerTest.php`

**Interfaces:**
- Consumes: Task 1 columns.
- Produces:
  - `SettingActionRunner::commandLine(Setting $setting): string` and `SettingActionRunner::run(Setting $setting): SettingActionResult`, both throwing `InvalidSettingActionException`.
  - `SettingActionResult` readonly: `string $commandLine`, `bool $queued`, `int $exitCode`, `string $output`, `succeeded(): bool`.
  - Test stub command `laraplate:setting-action-probe {name} {--flag=} {--fail}` recording calls in `SettingActionProbeCommand::$calls` (`list<array{name: string, flag: ?string}>`).

- [x] **Step 1: Write the probe stub command**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Console\Command;

/**
 * Records the arguments a setting action passed, so tests can prove each placeholder
 * arrived as exactly one argument.
 */
final class SettingActionProbeCommand extends Command
{
    /**
     * @var list<array{name: string, flag: ?string}>
     */
    public static array $calls = [];

    protected $signature = 'laraplate:setting-action-probe {name} {--flag=} {--fail}';

    protected $description = 'test';

    public function handle(): int
    {
        $flag = $this->option('flag');

        self::$calls[] = [
            'name' => (string) $this->argument('name'),
            'flag' => is_string($flag) ? $flag : null,
        ];

        $this->line('probe received ' . $this->argument('name'));

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
```

- [x] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Exceptions\InvalidSettingActionException;
use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingActionRunner;
use Modules\Core\Tests\Stubs\Console\SettingActionProbeCommand;

beforeEach(function (): void {
    SettingActionProbeCommand::$calls = [];
    app(ConsoleKernel::class)->registerCommand(new SettingActionProbeCommand);
});

function settingActionRunnerFixture(string $command, array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'probe.setting',
        'type' => 'string',
        'value' => 'plain',
        'choices' => null,
        'encrypted' => false,
        'action_command' => $command,
        'action_queued' => false,
        ...$attributes,
    ])->fresh();
}

it('quotes each substituted placeholder', function (): void {
    $line = app(SettingActionRunner::class)
        ->commandLine(settingActionRunnerFixture('laraplate:setting-action-probe {name}'));

    expect($line)->toBe('laraplate:setting-action-probe "probe.setting"');
});

it('passes a value with quotes, backslashes and a leading option as one argument', function (): void {
    $description = 'He said "hi" \ --flag=injected';
    $setting = settingActionRunnerFixture('laraplate:setting-action-probe {description}', ['description' => $description]);

    app(SettingActionRunner::class)->run($setting);

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => $description, 'flag' => null]]);
});

it('substitutes placeholders inside an option', function (): void {
    app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:setting-action-probe x --flag={name}'));

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => 'x', 'flag' => 'probe.setting']]);
});

it('encodes list and boolean values', function (): void {
    $runner = app(SettingActionRunner::class);

    $list = settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['type' => 'json', 'value' => ['a', 'b']]);
    $flag = settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['name' => 'probe.flag', 'type' => 'boolean', 'value' => true]);

    expect($runner->commandLine($list))->toBe('laraplate:setting-action-probe "[\"a\",\"b\"]"')
        ->and($runner->commandLine($flag))->toBe('laraplate:setting-action-probe "true"');
});

it('refuses an unknown placeholder without running anything', function (): void {
    expect(fn () => app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:setting-action-probe {nope}')))
        ->toThrow(InvalidSettingActionException::class);

    expect(SettingActionProbeCommand::$calls)->toBe([]);
});

it('refuses the value of an encrypted setting but runs its other placeholders', function (): void {
    $runner = app(SettingActionRunner::class);

    expect(fn () => $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {value}', ['encrypted' => true])))
        ->toThrow(InvalidSettingActionException::class);

    $result = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}', ['name' => 'probe.secret', 'encrypted' => true]));

    expect($result->succeeded())->toBeTrue();
});

it('refuses a command that is not registered', function (): void {
    app(SettingActionRunner::class)->run(settingActionRunnerFixture('laraplate:no-such-command {name}'));
})->throws(InvalidSettingActionException::class);

it('returns exit code and output of a synchronous run', function (): void {
    $runner = app(SettingActionRunner::class);

    $ok = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}'));
    $failed = $runner->run(settingActionRunnerFixture('laraplate:setting-action-probe {name} --fail', ['name' => 'probe.failing']));

    expect($ok->queued)->toBeFalse()
        ->and($ok->succeeded())->toBeTrue()
        ->and($ok->output)->toContain('probe received probe.setting')
        ->and($failed->succeeded())->toBeFalse()
        ->and($failed->exitCode)->toBe(1);
});

it('queues the command when action_queued is set', function (): void {
    Bus::fake();

    $result = app(SettingActionRunner::class)
        ->run(settingActionRunnerFixture('laraplate:setting-action-probe {name}', ['action_queued' => true]));

    expect($result->queued)->toBeTrue()
        ->and(SettingActionProbeCommand::$calls)->toBe([]);

    Bus::assertDispatched(QueuedCommand::class);
});
```

- [x] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Services/SettingActionRunnerTest.php`
Expected: FAIL with "Class Modules\Core\Services\SettingActionRunner not found".

- [x] **Step 4: Write the exception**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Modules\Core\Models\Setting;
use RuntimeException;

/**
 * A setting action that cannot run as declared. Raised before anything executes.
 */
final class InvalidSettingActionException extends RuntimeException
{
    public static function missing(Setting $setting): self
    {
        return new self(sprintf('Setting %s has no action command.', $setting->name));
    }

    public static function unknownPlaceholder(Setting $setting, string $attribute): self
    {
        return new self(sprintf('Setting %s: unknown placeholder {%s}.', $setting->name, $attribute));
    }

    public static function encryptedValue(Setting $setting): self
    {
        return new self(sprintf('Setting %s is encrypted: its value cannot be passed to a command.', $setting->name));
    }

    public static function unregisteredCommand(Setting $setting, string $command): self
    {
        return new self(sprintf('Setting %s runs %s, which is not a registered command.', $setting->name, $command));
    }
}
```

- [x] **Step 5: Write the result**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * Outcome of one setting action: the command line that ran, and its exit code and output
 * when it ran inside the request.
 */
final readonly class SettingActionResult
{
    private function __construct(
        public string $commandLine,
        public bool $queued,
        public int $exitCode,
        public string $output,
    ) {}

    public static function ran(string $commandLine, int $exitCode, string $output): self
    {
        return new self($commandLine, false, $exitCode, $output);
    }

    public static function queued(string $commandLine): self
    {
        return new self($commandLine, true, 0, '');
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
```

- [x] **Step 6: Write the runner**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use BackedEnum;
use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Exceptions\InvalidSettingActionException;
use Modules\Core\Models\Setting;
use Stringable;

/**
 * Runs the Artisan command a seeded setting carries in `action_command`.
 *
 * `{attribute}` placeholders are replaced with the setting's attributes, each wrapped in
 * double quotes with `"` and `\` escaped. Symfony's StringInput reads that as one token,
 * so a value holding spaces or a leading `--` can never add arguments or options.
 */
final readonly class SettingActionRunner
{
    public function __construct(private Kernel $artisan) {}

    /**
     * @throws InvalidSettingActionException
     */
    public function commandLine(Setting $setting): string
    {
        $template = $setting->action_command;

        if (! is_string($template) || mb_trim($template) === '') {
            throw InvalidSettingActionException::missing($setting);
        }

        $attributes = $setting->getAttributes();

        return (string) preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static function (array $match) use ($setting, $attributes): string {
                $attribute = $match[1];

                if (! array_key_exists($attribute, $attributes)) {
                    throw InvalidSettingActionException::unknownPlaceholder($setting, $attribute);
                }

                if ($attribute === 'value' && $setting->encrypted) {
                    throw InvalidSettingActionException::encryptedValue($setting);
                }

                return self::quote(self::stringify($setting->getAttribute($attribute)));
            },
            $template,
        );
    }

    /**
     * @throws InvalidSettingActionException
     */
    public function run(Setting $setting): SettingActionResult
    {
        $line = $this->commandLine($setting);
        $command = (string) strtok($line, " \t");

        if (! array_key_exists($command, $this->artisan->all())) {
            throw InvalidSettingActionException::unregisteredCommand($setting, $command);
        }

        if ($setting->action_queued) {
            $this->artisan->queue($line);

            return SettingActionResult::queued($line);
        }

        $exit_code = $this->artisan->call($line);

        return SettingActionResult::ran($line, $exit_code, $this->artisan->output());
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Stringable => (string) $value,
            default => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }
}
```

- [x] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Services/SettingActionRunnerTest.php`
Expected: PASS (9 tests).

- [x] **Step 8: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Services/SettingActionRunner.php Modules/Core/app/Services/SettingActionResult.php Modules/Core/app/Exceptions/InvalidSettingActionException.php Modules/Core/tests/Stubs/Console/SettingActionProbeCommand.php Modules/Core/tests/Feature/Services/SettingActionRunnerTest.php
git -C Modules/Core add app/Services/SettingActionRunner.php app/Services/SettingActionResult.php app/Exceptions/InvalidSettingActionException.php tests/Stubs/Console/SettingActionProbeCommand.php tests/Feature/Services/SettingActionRunnerTest.php
git -C Modules/Core commit -m "feat(settings): run a setting's action command with quoted placeholders" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Settings grid action and read-only action fields

**Files:**
- Modify: `Modules/Core/app/Filament/Resources/Settings/Tables/SettingsTable.php`
- Modify: `Modules/Core/app/Filament/Resources/Settings/Schemas/SettingForm.php`
- Create: `Modules/Core/tests/Feature/Filament/SettingActionTableTest.php`
- Modify: `Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`

**Interfaces:**
- Consumes: `SettingActionRunner::run()`, `SettingActionResult`, `InvalidSettingActionException` (Task 3); `SettingActionProbeCommand` stub (Task 3).
- Produces: table action named `runSettingAction`; form fields `action_command`, `action_queued` (disabled, not dehydrated, visible only when the setting has an action).

- [x] **Step 1: Write the failing grid test**

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Stubs\Console\SettingActionProbeCommand;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    SettingActionProbeCommand::$calls = [];
    app(ConsoleKernel::class)->registerCommand(new SettingActionProbeCommand);
});

function settingActionTableFixture(?string $command, array $attributes = []): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'probe.grid',
        'type' => 'string',
        'value' => 'plain',
        'choices' => null,
        'encrypted' => false,
        'action_command' => $command,
        'action_queued' => false,
        ...$attributes,
    ]);
}

it('hides the action on a setting without one', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture(null);

    Livewire::test(ListSettings::class)->assertTableActionHidden('runSettingAction', $setting);
});

it('hides the action from a user who cannot update settings', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}');

    Livewire::test(ListSettings::class)->assertTableActionHidden('runSettingAction', $setting);
});

it('runs the command from the row and reports its output', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}');

    Livewire::test(ListSettings::class)
        ->assertTableActionVisible('runSettingAction', $setting)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command completed');

    expect(SettingActionProbeCommand::$calls)->toBe([['name' => 'probe.grid', 'flag' => null]]);
});

it('reports a failing command', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name} --fail');

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command failed');
});

it('reports a refused action without running it', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {nope}');

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Action refused');

    expect(SettingActionProbeCommand::$calls)->toBe([]);
});

it('queues the command when the setting asks for it', function (): void {
    Bus::fake();
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);
    $setting = settingActionTableFixture('laraplate:setting-action-probe {name}', ['action_queued' => true]);

    Livewire::test(ListSettings::class)
        ->callTableAction('runSettingAction', $setting)
        ->assertNotified('Command queued');

    Bus::assertDispatched(QueuedCommand::class);
});
```

- [x] **Step 2: Add the failing form test**

Append to `EditSettingFormTest.php`:

```php
it('shows the action fields read-only when the setting has an action', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'a',
        'choices' => null,
        'encrypted' => false,
        'action_command' => 'laraplate:setting-action-probe {name}',
        'action_queued' => true,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertFormFieldDisabled('action_command')
        ->assertFormFieldDisabled('action_queued')
        ->assertFormSet([
            'action_command' => 'laraplate:setting-action-probe {name}',
            'action_queued' => true,
        ]);
});

it('hides the action fields when the setting has no action', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'a',
        'choices' => null,
        'encrypted' => false,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertFormFieldHidden('action_command')
        ->assertFormFieldHidden('action_queued');
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Feature/Filament/SettingActionTableTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`
Expected: FAIL: action `runSettingAction` and fields `action_command` / `action_queued` do not exist.

- [x] **Step 4: Add the row action to `SettingsTable`**

Add imports:

```php
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Exceptions\InvalidSettingActionException;
use Modules\Core\Services\SettingActionResult;
use Modules\Core\Services\SettingActionRunner;
use Modules\Core\Support\PermissionName;
use Throwable;
```

In `configure()`, pass two more named arguments to `self::configureTable(...)`, after `filters:`:

```php
            actions: static function (Collection $default_actions): void {
                $default_actions->push(self::runActionAction());
            },
            fixedActions: ['runSettingAction'],
```

Add the private methods:

```php
    /**
     * Runs the setting's seeded command. Visible only when the setting carries one and the user
     * may update settings; the command itself comes from the seeder, never from the user.
     */
    private static function runActionAction(): Action
    {
        return Action::make('runSettingAction')
            ->hiddenLabel()
            ->icon(Heroicon::OutlinedPlay)
            ->tooltip(static fn (Setting $record): string => (string) $record->action_command)
            ->visible(static fn (Setting $record): bool => $record->action_command !== null
                && (Auth::user()?->can(PermissionName::forModel($record, 'update')) ?? false))
            ->action(static function (Setting $record): void {
                self::runAction($record);
            });
    }

    private static function runAction(Setting $record): void
    {
        try {
            $result = app(SettingActionRunner::class)->run($record);
        } catch (InvalidSettingActionException $exception) {
            Notification::make()->danger()->title('Action refused')->body($exception->getMessage())->send();

            return;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('Action failed')->body($exception->getMessage())->send();

            return;
        }

        self::notifyResult($result);
    }

    private static function notifyResult(SettingActionResult $result): void
    {
        if ($result->queued) {
            Notification::make()->info()->title('Command queued')->body($result->commandLine)->send();

            return;
        }

        $notification = Notification::make()->body(self::outputTail($result->output));

        if ($result->succeeded()) {
            $notification->success()->title('Command completed');
        } else {
            $notification->danger()->title('Command failed');
        }

        $notification->send();
    }

    private static function outputTail(string $output): string
    {
        $output = mb_trim($output);

        return mb_strlen($output) > 1000 ? mb_substr($output, -1000) : $output;
    }
```

- [x] **Step 5: Add the read-only fields to `SettingForm`**

In `configure()`, append after the `description` `TextInput` inside `->components([...])`:

```php
                Grid::make(4)
                    ->schema([
                        TextInput::make('action_command')
                            ->label('Action')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(static function (TextInput $component, ?Setting $record): void {
                                $component->state($record?->action_command);
                            })
                            ->columnSpan(3),
                        Toggle::make('action_queued')
                            ->label('Queued')
                            ->inline(false)
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(static function (Toggle $component, ?Setting $record): void {
                                $component->state((bool) $record?->action_queued);
                            }),
                    ])
                    ->visible(static fn (?Setting $record): bool => $record?->action_command !== null)
                    ->columnSpanFull(),
```

Update the class docblock: "Settings are seeded: only the group, the value and the description can be edited. The value input follows the setting type, which is itself read-only. The action a setting runs is shown read-only; `$hidden` keeps it out of the fill data, so the fields read it from the record."

- [x] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/Core/tests/Feature/Filament/SettingActionTableTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`
Expected: PASS. The existing "lays out the edit form in rows" test still passes because the action row is hidden for a setting without an action. If `assertFormSet` or `assertFormFieldHidden` are not the Filament 5 names, look them up with Boost `search-docs` (`testing forms state`) and use the documented ones.

- [x] **Step 7: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Filament/Resources/Settings/Tables/SettingsTable.php Modules/Core/app/Filament/Resources/Settings/Schemas/SettingForm.php Modules/Core/tests/Feature/Filament/SettingActionTableTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core add app/Filament/Resources/Settings/Tables/SettingsTable.php app/Filament/Resources/Settings/Schemas/SettingForm.php tests/Feature/Filament/SettingActionTableTest.php tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core commit -m "feat(settings): run a setting's action from the settings grid" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Values no longer among the choices

**Files:**
- Modify: `Modules/Core/app/Models/Setting.php`
- Modify: `Modules/Core/app/Filament/Resources/Settings/Schemas/SettingForm.php` (`valueField()`)
- Modify: `Modules/Core/app/Filament/Resources/Settings/Tables/SettingsTable.php` (`value` column)
- Create: `Modules/Core/tests/Feature/Models/SettingChoicesAnomalyTest.php`
- Modify: `Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`

**Interfaces:**
- Produces: `Setting::isValueOutsideChoices(): bool`. True only for a scalar value missing from a non-empty choice list.

- [x] **Step 1: Write the failing model test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;

it('flags only a scalar value missing from a non-empty choice list', function (mixed $value, ?array $choices, bool $expected): void {
    $setting = new Setting;
    $setting->setRawAttributes([
        'value' => json_encode($value),
        'choices' => $choices === null ? null : json_encode($choices),
    ]);

    expect($setting->isValueOutsideChoices())->toBe($expected);
})->with([
    'offered' => ['ollama:llama3.2:3b', ['ollama:llama3.2:3b', 'openai:gpt-4o'], false],
    'no longer offered' => ['openai:gpt-3.5', ['openai:gpt-4o'], true],
    'no choices' => ['free text', null, false],
    'empty choices' => ['free text', [], false],
    'numeric against a numeric choice' => [5, [5, 10], false],
    'list value of a checkbox setting' => [['mail', 'sms'], ['mail', 'database'], false],
]);
```

- [x] **Step 2: Add the failing form test**

Append to `EditSettingFormTest.php`:

```php
it('keeps a value no longer offered selectable and flags it', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'openai:gpt-3.5',
        'choices' => ['openai:gpt-4o'],
        'encrypted' => false,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertFormFieldExists('value', static fn (Select $field): bool => $field->isSearchable()
            && array_key_exists('openai:gpt-3.5', $field->getOptions())
            && str_contains((string) $field->getOptions()['openai:gpt-3.5'], 'no longer available'))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBe('openai:gpt-3.5');
});

it('marks a value no longer offered in the settings grid', function (): void {
    editSettingActor();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'openai:gpt-3.5',
        'choices' => ['openai:gpt-4o'],
        'encrypted' => false,
    ]);

    Livewire::test(ListSettings::class)
        ->call('loadTable')
        ->assertSee('The saved value is not among the available choices');
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Feature/Models/SettingChoicesAnomalyTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`
Expected: FAIL: `isValueOutsideChoices()` is undefined; the select is not searchable and lacks the unavailable option.

- [x] **Step 4: Add the model method**

In `Setting.php`, add after `getRules()`:

```php
    /**
     * Whether the saved value is a scalar missing from a non-empty choice list, i.e. a choice a
     * refresh no longer offers. List values (checkbox settings) are never flagged.
     */
    public function isValueOutsideChoices(): bool
    {
        $choices = $this->choices;
        $value = $this->value;

        if (! is_array($choices) || $choices === [] || ! is_scalar($value)) {
            return false;
        }

        $offered = array_map(static fn (mixed $choice): ?string => is_scalar($choice) ? (string) $choice : null, $choices);

        return ! in_array((string) $value, $offered, true);
    }
```

- [x] **Step 5: Make the form select searchable and keep an unavailable value**

In `SettingForm::valueField()`, replace the `default` arm:

```php
            default => $choices !== []
                ? self::choiceSelect($record, $choices)
                : TextInput::make('value')->required()->maxLength(65535),
```

Add the private method:

```php
    /**
     * A value the choices no longer offer stays selectable, labelled as such, so the select is
     * never blank and saving does not drop it.
     *
     * @param  array<string, string>  $choices
     */
    private static function choiceSelect(?Setting $record, array $choices): Select
    {
        $unavailable = $record?->isValueOutsideChoices() ?? false;

        if ($unavailable) {
            $value = (string) $record->value;
            $choices[$value] = $value . ' (no longer available)';
        }

        return Select::make('value')
            ->required()
            ->searchable()
            ->options($choices)
            ->helperText($unavailable ? 'The saved value is no longer among the available choices.' : null);
    }
```

- [x] **Step 6: Mark the grid cell**

In `SettingsTable`, on `TextColumn::make('value')`, add after `->alignCenter()`:

```php
                        ->color(static fn (Setting $record): ?string => $record->isValueOutsideChoices() ? 'warning' : null)
                        ->icon(static fn (Setting $record): ?Heroicon => $record->isValueOutsideChoices() ? Heroicon::OutlinedExclamationTriangle : null)
                        ->tooltip(static fn (Setting $record): ?string => $record->isValueOutsideChoices()
                            ? 'The saved value is not among the available choices'
                            : null)
```

- [x] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/Core/tests/Feature/Models/SettingChoicesAnomalyTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php`
Expected: PASS.

- [x] **Step 8: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Setting.php Modules/Core/app/Filament/Resources/Settings/Schemas/SettingForm.php Modules/Core/app/Filament/Resources/Settings/Tables/SettingsTable.php Modules/Core/tests/Feature/Models/SettingChoicesAnomalyTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core add app/Models/Setting.php app/Filament/Resources/Settings/Schemas/SettingForm.php app/Filament/Resources/Settings/Tables/SettingsTable.php tests/Feature/Models/SettingChoicesAnomalyTest.php tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core commit -m "feat(settings): flag and keep values no longer among the choices" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Settings overlay before every queued job

**Files:**
- Create: `Modules/Core/app/Listeners/ApplySettingsOverlayBeforeJob.php`
- Modify: `Modules/Core/app/Providers/EventServiceProvider.php` (`$listen`)
- Create: `Modules/Core/tests/Feature/Listeners/ApplySettingsOverlayBeforeJobTest.php`

**Interfaces:**
- Consumes: `DatabaseConfigOverlay::applyFromDatabase(PerModelSettingResolver $settings): void`.
- Produces: listener on `Illuminate\Queue\Events\JobProcessing`.

The queue worker calls `forgetScopedInstances()` before each job (`Illuminate\Queue\QueueServiceProvider`, reset scope), and `PerModelSettingResolver` is scoped, so the resolver built for the listener is new and reads the persistent cache the saving process invalidated.

- [x] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingsCacheCoordinator;

it('applies a setting changed by another process before the next job runs', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'overlay.probe',
        'module' => 'Core',
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
        'encrypted' => false,
    ]);

    expect(config('core.overlay.probe'))->toBe('before');

    // Another process saves the setting: the row and the persistent cache change, this
    // process's config does not.
    Setting::query()->withoutGlobalScopes()->whereKey($setting->getKey())
        ->update(['value' => json_encode('after')]);
    app(SettingsCacheCoordinator::class)->flushAll();
    app()->forgetScopedInstances();

    expect(config('core.overlay.probe'))->toBe('before');

    event(new JobProcessing('sync', Mockery::mock(Job::class)));

    expect(config('core.overlay.probe'))->toBe('after');
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Listeners/ApplySettingsOverlayBeforeJobTest.php`
Expected: FAIL: the last expectation reads `before`.

- [x] **Step 3: Write the listener**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;

/**
 * Re-applies database settings to config before every queued job.
 *
 * A worker boots once, so the boot-time overlay in CoreServiceProvider alone would freeze
 * database-backed config for the worker's lifetime. The worker drops scoped instances before
 * each job, so the resolver injected here is new and reads the persistent cache that the
 * process saving a setting invalidates.
 */
final readonly class ApplySettingsOverlayBeforeJob
{
    public function __construct(
        private DatabaseConfigOverlay $overlay,
        private PerModelSettingResolver $settings,
    ) {}

    public function handle(JobProcessing $event): void
    {
        $this->overlay->applyFromDatabase($this->settings);
    }
}
```

- [x] **Step 4: Register it**

In `Modules/Core/app/Providers/EventServiceProvider.php`, add to `$listen` (and `use Illuminate\Queue\Events\JobProcessing;`):

```php
        JobProcessing::class => [
            \Modules\Core\Listeners\ApplySettingsOverlayBeforeJob::class,
        ],
```

- [x] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Listeners/ApplySettingsOverlayBeforeJobTest.php`
Expected: PASS.

- [x] **Step 6: Run the Core suite**

Run: `php artisan test --compact Modules/Core/tests`
Expected: PASS. Every sync-queue dispatch in the suite now re-applies the overlay; a failure here means a test relied on config set by hand being kept across a queued job, which must be set as a setting or through `config()` after dispatch instead.

- [x] **Step 7: Format and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Listeners/ApplySettingsOverlayBeforeJob.php Modules/Core/app/Providers/EventServiceProvider.php Modules/Core/tests/Feature/Listeners/ApplySettingsOverlayBeforeJobTest.php
git -C Modules/Core add app/Listeners/ApplySettingsOverlayBeforeJob.php app/Providers/EventServiceProvider.php tests/Feature/Listeners/ApplySettingsOverlayBeforeJobTest.php
git -C Modules/Core commit -m "fix(settings): re-apply the settings overlay before every queued job" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Ollama without a default URL

**Files:**
- Modify: `Modules/AI/config/config.php` (`providers.ollama.api_url`)
- Modify: `Modules/AI/app/Ai/Providers/ProviderFactory.php` (`createOllama()`)
- Modify: `Modules/AI/app/Ai/Embeddings/EmbeddingsProviderFactory.php` (`createOllama()`)
- Modify: `Modules/AI/tests/Integration/ProviderFactoryTest.php`
- Modify: `Modules/AI/tests/Integration/EmbeddingsProviderFactoryTest.php`
- Modify: `Modules/AI/README.md` (env block, `OLLAMA_API_URL` line)

**Interfaces:**
- Produces: an empty `ai.providers.ollama.api_url` means "Ollama not configured"; both factories throw `ConfigurationException('Ollama API URL is not configured')`.

- [x] **Step 1: Write the failing tests**

Append to `ProviderFactoryTest.php`:

```php
it('throws when the Ollama URL is missing', function (): void {
    config()->set('ai.providers.ollama.api_url', null);

    ProviderFactory::make('ollama');
})->throws(Modules\Core\Exceptions\ConfigurationException::class, 'Ollama API URL is not configured');
```

Append to `EmbeddingsProviderFactoryTest.php`:

```php
it('throws when the Ollama URL is missing for embeddings', function (): void {
    config()->set('ai.providers.ollama.api_url', null);

    Modules\AI\Ai\Embeddings\EmbeddingsProviderFactory::make('ollama');
})->throws(Modules\Core\Exceptions\ConfigurationException::class, 'Ollama API URL is not configured');
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/AI/tests/Integration/ProviderFactoryTest.php Modules/AI/tests/Integration/EmbeddingsProviderFactoryTest.php`
Expected: FAIL: the factories fall back to `localhost`.

- [x] **Step 3: Remove the defaults**

`config.php`: `'api_url' => env('OLLAMA_API_URL'),` (no default).

`ProviderFactory::createOllama()`:

```php
    private static function createOllama(?string $model): Ollama
    {
        $url = ai_config_string('ai.providers.ollama.api_url');
        throw_if($url === '', ConfigurationException::class, 'Ollama API URL is not configured');

        return new Ollama(
            url: $url,
            model: $model ?? ai_config_string('ai.providers.ollama.model', 'llama3.2:3b'),
        );
    }
```

`EmbeddingsProviderFactory::createOllama()` (add `use Modules\Core\Exceptions\ConfigurationException;`):

```php
    private static function createOllama(): OllamaEmbeddingsProvider
    {
        $url = ai_config_string('ai.providers.ollama.api_url');
        throw_if($url === '', ConfigurationException::class, 'Ollama API URL is not configured');

        return new OllamaEmbeddingsProvider(
            model: ai_config_string('ai.providers.ollama.model', 'nomic-embed-text'),
            url: $url . '/api',
        );
    }
```

`README.md` env block, replace the `OLLAMA_API_URL` line with:

```env
OLLAMA_API_URL=                      # Ollama API URL, e.g. http://localhost:11434. Required to use Ollama: unset means not configured
```

- [x] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Integration/ProviderFactoryTest.php Modules/AI/tests/Integration/EmbeddingsProviderFactoryTest.php`
Expected: PASS.

- [x] **Step 5: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/config/config.php Modules/AI/app/Ai/Providers/ProviderFactory.php Modules/AI/app/Ai/Embeddings/EmbeddingsProviderFactory.php Modules/AI/tests/Integration/ProviderFactoryTest.php Modules/AI/tests/Integration/EmbeddingsProviderFactoryTest.php
git -C Modules/AI add config/config.php app/Ai/Providers/ProviderFactory.php app/Ai/Embeddings/EmbeddingsProviderFactory.php tests/Integration/ProviderFactoryTest.php tests/Integration/EmbeddingsProviderFactoryTest.php README.md
git -C Modules/AI commit -m "fix(ai): an unset Ollama URL means Ollama is not configured" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: `AiModelFeature`, `ModelCapability`, `AiModelChoice`

**Files:**
- Create: `Modules/AI/app/Enums/ModelCapability.php`
- Create: `Modules/AI/app/Enums/AiModelFeature.php`
- Create: `Modules/AI/app/Ai/Providers/AiModelChoice.php`
- Modify: `Modules/AI/config/config.php` (remove `features.text_generation.default_provider` and `features.text_generation.model`)
- Modify: `Modules/AI/app/Listeners/HandleAiTextGenerationListener.php` (reads `features.text_generation.model`, whose meaning changes here)
- Create: `Modules/AI/tests/Unit/Ai/Providers/AiModelChoiceTest.php`
- Modify: `Modules/AI/tests/Integration/AiTextGenerationModelBindingTest.php`

**Interfaces:**
- Produces:
  - `enum ModelCapability: string { Chat = 'chat'; Tools = 'tools'; Vision = 'vision'; }`
  - `enum AiModelFeature: string` with cases `Chat`, `TextGeneration`, `Moderation`, `SearchOrchestration`, `Translation`, `Faq`, `ContextualSuggestions`, `ChatSummary`, `Guardrails`, `Vision`, `Transcription`; methods `settingName(): string`, `settingDescription(): string`, `defaultChoice(): string`, `supportedProviders(): list<string>`, `requiredCapabilities(): list<ModelCapability>`, `static fromSettingName(string $name): ?self`.
  - `final readonly class AiModelChoice(string $provider, ?string $model = null)` with `static parse(string $value): self`, `static forFeature(AiModelFeature $feature): self` (reads `ai.{settingName}`, falls back to `defaultChoice()`), `value(): string`.
  - No config entry and no env variable for any model setting: the default lives in `defaultChoice()`.

- [x] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Ai\Providers\AiModelChoice;
use Modules\AI\Enums\AiModelFeature;
use Modules\AI\Enums\ModelCapability;

it('splits a choice on the first colon only', function (): void {
    $choice = AiModelChoice::parse('ollama:llama3.2:3b');

    expect($choice->provider)->toBe('ollama')
        ->and($choice->model)->toBe('llama3.2:3b')
        ->and($choice->value())->toBe('ollama:llama3.2:3b');
});

it('reads a provider without a model', function (): void {
    $choice = AiModelChoice::parse('deepl');

    expect($choice->provider)->toBe('deepl')
        ->and($choice->model)->toBeNull()
        ->and($choice->value())->toBe('deepl');
});

it('refuses an empty choice', function (): void {
    AiModelChoice::parse('  ');
})->throws(InvalidArgumentException::class);

it('reads the overlaid choice of a feature', function (): void {
    config()->set('ai.features.chat.model', 'anthropic:claude-sonnet-5');

    $choice = AiModelChoice::forFeature(AiModelFeature::Chat);

    expect($choice->provider)->toBe('anthropic')
        ->and($choice->model)->toBe('claude-sonnet-5');
});

it('falls back to the default choice when nothing is overlaid', function (AiModelFeature $feature): void {
    config()->set('ai.' . $feature->settingName(), null);

    expect(AiModelChoice::forFeature($feature)->value())->toBe($feature->defaultChoice())
        ->and($feature->supportedProviders())->toContain(AiModelChoice::parse($feature->defaultChoice())->provider);
})->with(AiModelFeature::cases());

it('keeps today\'s defaults', function (): void {
    expect(AiModelFeature::Chat->defaultChoice())->toBe('ollama:llama3.2:3b')
        ->and(AiModelFeature::Guardrails->defaultChoice())->toBe('ollama:llama3.2:3b')
        ->and(AiModelFeature::Translation->defaultChoice())->toBe('deepl')
        ->and(AiModelFeature::Vision->defaultChoice())->toBe('anthropic:claude-sonnet-5')
        ->and(AiModelFeature::Transcription->defaultChoice())->toBe('whisper');
});

it('maps every setting name back to its feature', function (AiModelFeature $feature): void {
    expect(AiModelFeature::fromSettingName($feature->settingName()))->toBe($feature);
})->with(AiModelFeature::cases());

it('declares providers and capabilities per feature', function (): void {
    expect(AiModelFeature::Translation->supportedProviders())->toContain('deepl')
        ->and(AiModelFeature::Chat->supportedProviders())->not->toContain('deepl')
        ->and(AiModelFeature::Chat->requiredCapabilities())->toBe([ModelCapability::Chat, ModelCapability::Tools])
        ->and(AiModelFeature::Vision->requiredCapabilities())->toBe([ModelCapability::Vision])
        ->and(AiModelFeature::Transcription->supportedProviders())->toBe(['whisper'])
        ->and(AiModelFeature::fromSettingName('features.faq.enabled'))->toBeNull();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/AiModelChoiceTest.php`
Expected: FAIL with "Class Modules\AI\Ai\Providers\AiModelChoice not found".

- [x] **Step 3: Write `ModelCapability`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

/**
 * What a listed model can do, as far as feature selection cares.
 */
enum ModelCapability: string
{
    case Chat = 'chat';
    case Tools = 'tools';
    case Vision = 'vision';
}
```

- [x] **Step 4: Write `AiModelFeature`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

/**
 * Every AI feature whose provider and model are chosen in Settings. Embeddings are not here:
 * changing their model changes the vector dimensions and forces a reindex.
 */
enum AiModelFeature: string
{
    private const array CHAT_PROVIDERS = ['openai', 'ollama', 'mistral', 'anthropic'];

    case Chat = 'chat';
    case TextGeneration = 'text_generation';
    case Moderation = 'moderation';
    case SearchOrchestration = 'search_orchestration';
    case Translation = 'translation';
    case Faq = 'faq';
    case ContextualSuggestions = 'contextual_suggestions';
    case ChatSummary = 'chat_summary';
    case Guardrails = 'guardrails';
    case Vision = 'vision';
    case Transcription = 'transcription';

    public static function fromSettingName(string $name): ?self
    {
        foreach (self::cases() as $feature) {
            if ($feature->settingName() === $name) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * Setting name, without the module prefix; read from config as `ai.{name}`.
     */
    public function settingName(): string
    {
        return match ($this) {
            self::Chat => 'features.chat.model',
            self::TextGeneration => 'features.text_generation.model',
            self::Moderation => 'features.moderation.model',
            self::SearchOrchestration => 'features.search_orchestration.model',
            self::Translation => 'features.translation.model',
            self::Faq => 'features.faq.model',
            self::ContextualSuggestions => 'features.contextual_suggestions.model',
            self::ChatSummary => 'features.chat.summary.model',
            self::Guardrails => 'features.guardrails.model',
            self::Vision => 'features.media_analysis.vision.model',
            self::Transcription => 'features.media_analysis.transcription.model',
        };
    }

    public function settingDescription(): string
    {
        return match ($this) {
            self::Chat => 'AI model used by chat (provider:model)',
            self::TextGeneration => 'AI model used for one-shot text generation (provider:model)',
            self::Moderation => 'AI model used for moderation (provider:model)',
            self::SearchOrchestration => 'AI model used for search orchestration (provider:model)',
            self::Translation => 'Translation provider: deepl, or an AI model (provider:model)',
            self::Faq => 'AI model used for FAQ answers (provider:model)',
            self::ContextualSuggestions => 'AI model used for contextual suggestions (provider:model)',
            self::ChatSummary => 'AI model used for chat summaries and memory (provider:model)',
            self::Guardrails => 'AI model used for prompt-injection detection (provider:model)',
            self::Vision => 'AI model used for media vision analysis (provider:model)',
            self::Transcription => 'Transcription provider for media analysis',
        };
    }

    /**
     * The choice used until Settings holds one. It lives in code: a value managed by a setting
     * has no config entry and no env variable, since the seeder writes it once and a later env
     * change would silently do nothing.
     */
    public function defaultChoice(): string
    {
        return match ($this) {
            self::Translation => 'deepl',
            self::Vision => 'anthropic:claude-sonnet-5',
            self::Transcription => 'whisper',
            default => 'ollama:llama3.2:3b',
        };
    }

    /**
     * @return list<string>
     */
    public function supportedProviders(): array
    {
        return match ($this) {
            self::Translation => ['deepl', ...self::CHAT_PROVIDERS],
            self::Vision => ['anthropic', 'openai', 'ollama'],
            self::Transcription => ['whisper'],
            default => self::CHAT_PROVIDERS,
        };
    }

    /**
     * @return list<ModelCapability>
     */
    public function requiredCapabilities(): array
    {
        return match ($this) {
            self::Chat => [ModelCapability::Chat, ModelCapability::Tools],
            self::Vision => [ModelCapability::Vision],
            self::Transcription => [],
            default => [ModelCapability::Chat],
        };
    }
}
```

- [x] **Step 5: Write `AiModelChoice`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers;

use function ai_config_string;

use InvalidArgumentException;
use Modules\AI\Enums\AiModelFeature;

/**
 * One provider and model, stored in Settings as `provider:model`, or `provider` alone for
 * providers without a model catalogue (`deepl`, `whisper`). The value splits on the first
 * colon because Ollama ids contain one (`llama3.2:3b`) and provider names never do.
 */
final readonly class AiModelChoice
{
    public function __construct(
        public string $provider,
        public ?string $model = null,
    ) {}

    public static function parse(string $value): self
    {
        $value = mb_trim($value);

        throw_if($value === '', InvalidArgumentException::class, 'An AI model choice cannot be empty');

        $separator = mb_strpos($value, ':');

        if ($separator === false) {
            return new self($value);
        }

        $model = mb_substr($value, $separator + 1);

        return new self(mb_substr($value, 0, $separator), $model === '' ? null : $model);
    }

    /**
     * The overlay writes `ai.{setting name}` when the setting row exists; until then the
     * feature's default choice applies.
     */
    public static function forFeature(AiModelFeature $feature): self
    {
        return self::parse(ai_config_string('ai.' . $feature->settingName(), $feature->defaultChoice()));
    }

    public function value(): string
    {
        return $this->model === null ? $this->provider : $this->provider . ':' . $this->model;
    }
}
```

- [x] **Step 6: Remove the text-generation provider and model from config**

In `Modules/AI/config/config.php`, inside `features.text_generation`, delete the `'default_provider' => env('AI_TEXT_GENERATION_PROVIDER', env('AI_CHAT_PROVIDER', 'ollama')),` entry and the `'model' => env('AI_TEXT_GENERATION_MODEL'),` entry with its comment. The setting `features.text_generation.model` takes that name with a `provider:model` value, so a leftover config key holding a bare model id would be read as a provider.

- [x] **Step 7: Switch the text-generation listener to its choice**

In `HandleAiTextGenerationListener` (imports `Modules\AI\Ai\Providers\AiModelChoice`, `Modules\AI\Enums\AiModelFeature`), replace the body of `makeChatAgent()` after the factory check with:

```php
        $choice = AiModelChoice::forFeature(AiModelFeature::TextGeneration);

        /** @var ChatAgent */
        return ChatAgent::make( // @codeCoverageIgnore
            providerName: $choice->provider,
            systemPrompt: self::SYSTEM_PROMPT,
            model: $choice->model,
        );
```

and in `log()` use `'provider' => AiModelChoice::forFeature(AiModelFeature::TextGeneration)->provider,`.

In `Modules/AI/tests/Integration/AiTextGenerationModelBindingTest.php`: "builds the text-generation chat agent on the configured model" sets only `config()->set('ai.features.text_generation.model', 'ollama:phi3');` (drop the `default_provider` line) and still expects `phi3`; "leaves the chat agent model null when the feature configures none" sets only `config()->set('ai.features.text_generation.model', 'ollama');` and still expects `null`.

- [x] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/AiModelChoiceTest.php Modules/AI/tests/Integration/AiTextGenerationModelBindingTest.php Modules/AI/tests/Integration/HandleAiTextGenerationListenerTest.php`
Expected: PASS.

- [x] **Step 9: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/app/Enums/ModelCapability.php Modules/AI/app/Enums/AiModelFeature.php Modules/AI/app/Ai/Providers/AiModelChoice.php Modules/AI/config/config.php Modules/AI/app/Listeners/HandleAiTextGenerationListener.php Modules/AI/tests/Unit/Ai/Providers/AiModelChoiceTest.php Modules/AI/tests/Integration/AiTextGenerationModelBindingTest.php
git -C Modules/AI add app/Enums/ModelCapability.php app/Enums/AiModelFeature.php app/Ai/Providers/AiModelChoice.php config/config.php app/Listeners/HandleAiTextGenerationListener.php tests/Unit/Ai/Providers/AiModelChoiceTest.php tests/Integration/AiTextGenerationModelBindingTest.php
git -C Modules/AI commit -m "feat(ai): per-feature model choice with defaults in code" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Model listers

**Files:**
- Create: `Modules/AI/app/Ai/Providers/Models/ListedModel.php`
- Create: `Modules/AI/app/Ai/Providers/Models/ModelLister.php`
- Create: `Modules/AI/app/Ai/Providers/Models/OpenAiModelLister.php`
- Create: `Modules/AI/app/Ai/Providers/Models/AnthropicModelLister.php`
- Create: `Modules/AI/app/Ai/Providers/Models/MistralModelLister.php`
- Create: `Modules/AI/app/Ai/Providers/Models/OllamaModelLister.php`
- Create: `Modules/AI/tests/Unit/Ai/Providers/Models/ModelListersTest.php`

**Interfaces:**
- Consumes: `ModelCapability` (Task 8).
- Produces:
  - `final readonly class ListedModel(string $id, ?array $capabilities)`; `null` capabilities means unknown; `satisfies(list<ModelCapability> $required): bool` (unknown satisfies everything).
  - `interface ModelLister { public const int CONNECT_TIMEOUT = 3; public const int REQUEST_TIMEOUT = 10; /** @return list<ListedModel> */ public function list(): array; }`. `list()` throws `Illuminate\Http\Client\ConnectionException` or `RequestException` when the provider cannot be listed.
  - `new OpenAiModelLister(string $apiKey)`, `new AnthropicModelLister(string $apiKey)`, `new MistralModelLister(string $apiKey)`, `new OllamaModelLister(string $url)`.

Endpoints verified 2026-09-29: OpenAI `GET https://api.openai.com/v1/models` (Bearer, `data[].id`, no capabilities). Anthropic `GET https://api.anthropic.com/v1/models` (`x-api-key`, `anthropic-version: 2023-06-01`, `limit` up to 1000, `after_id`, response `data[].id`, `data[].capabilities.image_input.supported`, `has_more`, `last_id`). Mistral `GET https://api.mistral.ai/v1/models` (Bearer, `data[].capabilities.{completion_chat,function_calling,vision}`, `data[].archived`). Ollama `GET {url}/api/tags` (`models[].name`), `POST {url}/api/show` body `{"model": name}` (`capabilities` list: `completion`, `tools`, `vision`, `embedding`, `insert`, `thinking`).

- [x] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\AI\Ai\Providers\Models\AnthropicModelLister;
use Modules\AI\Ai\Providers\Models\ListedModel;
use Modules\AI\Ai\Providers\Models\MistralModelLister;
use Modules\AI\Ai\Providers\Models\OllamaModelLister;
use Modules\AI\Ai\Providers\Models\OpenAiModelLister;
use Modules\AI\Enums\ModelCapability;

/**
 * @param  list<ListedModel>  $models
 * @return array<string, ?list<string>>
 */
function listedModelsById(array $models): array
{
    $by_id = [];

    foreach ($models as $model) {
        $by_id[$model->id] = $model->capabilities === null
            ? null
            : array_map(static fn (ModelCapability $capability): string => $capability->value, $model->capabilities);
    }

    return $by_id;
}

it('lists OpenAI models without capabilities and drops the incompatible families', function (): void {
    Http::fake(['api.openai.com/v1/models' => Http::response(['object' => 'list', 'data' => [
        ['id' => 'gpt-4o'], ['id' => 'text-embedding-3-small'], ['id' => 'whisper-1'], ['id' => 'tts-1'],
        ['id' => 'dall-e-3'], ['id' => 'gpt-image-1'], ['id' => 'omni-moderation-latest'], ['id' => 'davinci-002'],
        ['id' => 'gpt-3.5-turbo-instruct'], ['id' => 'gpt-4o-realtime-preview'], ['id' => 'gpt-4o-transcribe'],
    ]])]);

    expect(listedModelsById((new OpenAiModelLister('sk-test'))->list()))->toBe(['gpt-4o' => null]);

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('fails loudly when OpenAI answers with an error', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([], 500)]);

    (new OpenAiModelLister('sk-test'))->list();
})->throws(RequestException::class);

it('pages through Anthropic models and reads image input as vision', function (): void {
    Http::fake(['api.anthropic.com/v1/models*' => Http::sequence()
        ->push(['data' => [['id' => 'claude-opus-5', 'capabilities' => ['image_input' => ['supported' => true]]]], 'has_more' => true, 'last_id' => 'claude-opus-5'])
        ->push(['data' => [
            ['id' => 'claude-text-only', 'capabilities' => ['image_input' => ['supported' => false]]],
            ['id' => 'claude-legacy', 'capabilities' => null],
        ], 'has_more' => false, 'last_id' => 'claude-legacy'])]);

    expect(listedModelsById((new AnthropicModelLister('ak-test'))->list()))->toBe([
        'claude-opus-5' => ['chat', 'tools', 'vision'],
        'claude-text-only' => ['chat', 'tools'],
        'claude-legacy' => ['chat', 'tools', 'vision'],
    ]);

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('x-api-key', 'ak-test')
        && $request->hasHeader('anthropic-version', '2023-06-01'));
    Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'after_id=claude-opus-5'));
});

it('stops paging when Anthropic claims more results without a cursor', function (): void {
    Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-opus-5', 'capabilities' => null]], 'has_more' => true, 'last_id' => null])]);

    expect((new AnthropicModelLister('ak-test'))->list())->toHaveCount(1);

    Http::assertSentCount(1);
});

it('maps Mistral capabilities and skips archived models', function (): void {
    Http::fake(['api.mistral.ai/v1/models' => Http::response(['object' => 'list', 'data' => [
        ['id' => 'mistral-large-latest', 'capabilities' => ['completion_chat' => true, 'function_calling' => true, 'vision' => false]],
        ['id' => 'pixtral-large-latest', 'capabilities' => ['completion_chat' => true, 'function_calling' => false, 'vision' => true]],
        ['id' => 'mistral-embed', 'capabilities' => ['completion_chat' => false, 'function_calling' => false, 'vision' => false]],
        ['id' => 'old-model', 'archived' => true, 'capabilities' => ['completion_chat' => true]],
        ['id' => 'no-capabilities'],
    ]])]);

    expect(listedModelsById((new MistralModelLister('mk-test'))->list()))->toBe([
        'mistral-large-latest' => ['chat', 'tools'],
        'pixtral-large-latest' => ['chat', 'vision'],
        'mistral-embed' => [],
        'no-capabilities' => null,
    ]);
});

it('reads Ollama capabilities per model and falls back to unknown', function (): void {
    Http::fake([
        'ollama.test/api/tags' => Http::response(['models' => [
            ['name' => 'llama3.2:3b'], ['name' => 'old-model:7b'], ['name' => 'nomic-embed-text:latest'], ['name' => 'bge-m3:latest'],
        ]]),
        'ollama.test/api/show' => static fn (Request $request) => match ($request->data()['model']) {
            'llama3.2:3b' => Http::response(['capabilities' => ['completion', 'tools']]),
            'bge-m3:latest' => Http::response(['capabilities' => ['embedding']]),
            default => Http::response([], 404),
        },
    ]);

    expect(listedModelsById((new OllamaModelLister('http://ollama.test/'))->list()))->toBe([
        'llama3.2:3b' => ['chat', 'tools'],
        'old-model:7b' => null,
        'bge-m3:latest' => [],
    ]);
});

it('fails when Ollama cannot be reached', function (): void {
    Http::fake(['ollama.test/*' => Http::failedConnection('Connection refused')]);

    (new OllamaModelLister('http://ollama.test'))->list();
})->throws(ConnectionException::class);

it('treats unknown capabilities as satisfying any requirement', function (): void {
    expect((new ListedModel('m', null))->satisfies([ModelCapability::Vision]))->toBeTrue()
        ->and((new ListedModel('m', [ModelCapability::Chat]))->satisfies([ModelCapability::Chat, ModelCapability::Tools]))->toBeFalse()
        ->and((new ListedModel('m', [ModelCapability::Chat, ModelCapability::Tools]))->satisfies([ModelCapability::Chat]))->toBeTrue();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/Models/ModelListersTest.php`
Expected: FAIL with "Class ... not found".

- [x] **Step 3: Write `ListedModel` and `ModelLister`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Modules\AI\Enums\ModelCapability;

/**
 * A model a provider lists. `null` capabilities means the provider does not say: such a
 * model satisfies every requirement, since including an unknown model is the last resort,
 * never excluding it.
 */
final readonly class ListedModel
{
    /**
     * @param  list<ModelCapability>|null  $capabilities
     */
    public function __construct(
        public string $id,
        public ?array $capabilities,
    ) {}

    /**
     * @param  list<ModelCapability>  $required
     */
    public function satisfies(array $required): bool
    {
        if ($this->capabilities === null) {
            return true;
        }

        return array_all($required, fn (ModelCapability $capability): bool => in_array($capability, $this->capabilities, true));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Reads the models a provider offers. Short timeouts: the grid runs a refresh inside the
 * request, which must not hang on an unreachable host.
 */
interface ModelLister
{
    public const int CONNECT_TIMEOUT = 3;

    public const int REQUEST_TIMEOUT = 10;

    /**
     * @return list<ListedModel>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function list(): array;
}
```

- [x] **Step 4: Write the OpenAI lister**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Support\Facades\Http;
use Override;

/**
 * OpenAI lists every model family without capabilities: the families no AI feature can use
 * are dropped by id, everything else is kept with unknown capabilities.
 */
final readonly class OpenAiModelLister implements ModelLister
{
    private const array INCOMPATIBLE = [
        'embedding', 'whisper', 'tts', 'audio', 'realtime', 'transcribe',
        'dall-e', 'image', 'moderation', 'babbage', 'davinci', 'instruct',
    ];

    public function __construct(private string $apiKey) {}

    #[Override]
    public function list(): array
    {
        $data = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->withToken($this->apiKey)
            ->get('https://api.openai.com/v1/models')
            ->throw()
            ->json('data');

        $models = [];

        foreach (is_array($data) ? $data : [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;

            if (is_string($id) && $id !== '' && ! self::isIncompatible($id)) {
                $models[] = new ListedModel($id, null);
            }
        }

        return $models;
    }

    private static function isIncompatible(string $id): bool
    {
        return array_any(self::INCOMPATIBLE, static fn (string $marker): bool => str_contains($id, $marker));
    }
}
```

- [x] **Step 5: Write the Anthropic lister**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Support\Facades\Http;
use Modules\AI\Enums\ModelCapability;
use Override;

/**
 * Every Claude model chats and calls tools; vision comes from `capabilities.image_input`, and
 * is assumed when the API returns no capabilities for a model.
 */
final readonly class AnthropicModelLister implements ModelLister
{
    public function __construct(private string $apiKey) {}

    #[Override]
    public function list(): array
    {
        $models = [];
        $after_id = null;

        do {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])
                ->get('https://api.anthropic.com/v1/models', array_filter(['limit' => 1000, 'after_id' => $after_id]))
                ->throw();

            $data = $response->json('data');

            foreach (is_array($data) ? $data : [] as $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : null;

                if (is_string($id) && $id !== '') {
                    $models[] = new ListedModel($id, self::capabilities($item));
                }
            }

            $last_id = $response->json('last_id');
            $after_id = $response->json('has_more') === true && is_string($last_id) && $last_id !== '' ? $last_id : null;
        } while ($after_id !== null);

        return $models;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<ModelCapability>
     */
    private static function capabilities(array $item): array
    {
        $capabilities = [ModelCapability::Chat, ModelCapability::Tools];
        $image_input = data_get($item, 'capabilities.image_input.supported');

        if (($item['capabilities'] ?? null) === null || $image_input === true) {
            $capabilities[] = ModelCapability::Vision;
        }

        return $capabilities;
    }
}
```

- [x] **Step 6: Write the Mistral lister**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Support\Facades\Http;
use Modules\AI\Enums\ModelCapability;
use Override;

final readonly class MistralModelLister implements ModelLister
{
    public function __construct(private string $apiKey) {}

    #[Override]
    public function list(): array
    {
        $data = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->withToken($this->apiKey)
            ->get('https://api.mistral.ai/v1/models')
            ->throw()
            ->json('data');

        $models = [];

        foreach (is_array($data) ? $data : [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;

            if (! is_string($id) || $id === '' || ($item['archived'] ?? false) === true) {
                continue;
            }

            $models[] = new ListedModel($id, self::capabilities($item['capabilities'] ?? null));
        }

        return $models;
    }

    /**
     * @return list<ModelCapability>|null
     */
    private static function capabilities(mixed $declared): ?array
    {
        if (! is_array($declared)) {
            return null;
        }

        $map = [
            'completion_chat' => ModelCapability::Chat,
            'function_calling' => ModelCapability::Tools,
            'vision' => ModelCapability::Vision,
        ];
        $capabilities = [];

        foreach ($map as $field => $capability) {
            if (($declared[$field] ?? false) === true) {
                $capabilities[] = $capability;
            }
        }

        return $capabilities;
    }
}
```

- [x] **Step 7: Write the Ollama lister**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\AI\Enums\ModelCapability;
use Override;

/**
 * Lists the locally installed models, then asks `/api/show` for each model's capabilities.
 * Only a failed `/api/tags` fails the provider: a failed `show`, or an older Ollama that
 * returns no capabilities, leaves that model's capabilities unknown.
 */
final readonly class OllamaModelLister implements ModelLister
{
    public function __construct(private string $url) {}

    #[Override]
    public function list(): array
    {
        $base = mb_rtrim($this->url, '/');
        $data = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->get($base . '/api/tags')
            ->throw()
            ->json('models');

        $models = [];

        foreach (is_array($data) ? $data : [] as $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $capabilities = $this->capabilities($base, $name);

            if ($capabilities === null && str_contains($name, 'embed')) {
                continue;
            }

            $models[] = new ListedModel($name, $capabilities);
        }

        return $models;
    }

    /**
     * @return list<ModelCapability>|null
     */
    private function capabilities(string $base, string $name): ?array
    {
        try {
            $declared = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->post($base . '/api/show', ['model' => $name])
                ->throw()
                ->json('capabilities');
        } catch (ConnectionException|RequestException) {
            return null;
        }

        if (! is_array($declared)) {
            return null;
        }

        $map = ['completion' => ModelCapability::Chat, 'tools' => ModelCapability::Tools, 'vision' => ModelCapability::Vision];
        $capabilities = [];

        foreach ($map as $declared_name => $capability) {
            if (in_array($declared_name, $declared, true)) {
                $capabilities[] = $capability;
            }
        }

        return $capabilities;
    }
}
```

- [x] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/Models/ModelListersTest.php`
Expected: PASS.

- [x] **Step 9: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/app/Ai/Providers/Models/ListedModel.php Modules/AI/app/Ai/Providers/Models/ModelLister.php Modules/AI/app/Ai/Providers/Models/OpenAiModelLister.php Modules/AI/app/Ai/Providers/Models/AnthropicModelLister.php Modules/AI/app/Ai/Providers/Models/MistralModelLister.php Modules/AI/app/Ai/Providers/Models/OllamaModelLister.php Modules/AI/tests/Unit/Ai/Providers/Models/ModelListersTest.php
git -C Modules/AI add app/Ai/Providers/Models tests/Unit/Ai/Providers/Models/ModelListersTest.php
git -C Modules/AI commit -m "feat(ai): list the models of OpenAI, Anthropic, Mistral and Ollama" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: `ProviderConfiguration` and `ModelCatalog`

**Files:**
- Create: `Modules/AI/app/Ai/Providers/Models/ProviderConfiguration.php`
- Create: `Modules/AI/app/Enums/ProviderListingStatus.php`
- Create: `Modules/AI/app/Ai/Providers/Models/ProviderOutcome.php`
- Create: `Modules/AI/app/Ai/Providers/Models/CatalogResult.php`
- Create: `Modules/AI/app/Ai/Providers/Models/ModelCatalog.php`
- Create: `Modules/AI/tests/Unit/Ai/Providers/Models/ModelCatalogTest.php`

**Interfaces:**
- Consumes: `AiModelFeature`, `ModelCapability`, `AiModelChoice` (Task 8); the listers (Task 9).
- Produces:
  - `ProviderConfiguration::isConfigured(string $provider): bool` (key: openai, anthropic, mistral read `ai.providers.{p}.api_key`, deepl reads `core.deepl_api_key` as `DeepLTranslationService` does; URL: ollama `ai.providers.ollama.api_url`, whisper `ai.providers.whisper.url`); `ProviderConfiguration::lister(string $provider): ?ModelLister` (null for deepl and whisper).
  - `enum ProviderListingStatus: string { Listed = 'listed'; NotConfigured = 'not_configured'; Failed = 'failed'; }`
  - `ProviderOutcome(string $provider, ProviderListingStatus $status, int $modelCount = 0, ?string $error = null)`.
  - `CatalogResult(array<string, list<string>> $choices, array<string, ProviderOutcome> $outcomes)` with `hasFailures(): bool`; `choices` keyed by setting name.
  - `ModelCatalog::build(list<AiModelFeature> $features, array<string, list<string>> $currentChoices): CatalogResult`.

- [x] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\AI\Ai\Providers\Models\ModelCatalog;
use Modules\AI\Enums\AiModelFeature;
use Modules\AI\Enums\ProviderListingStatus;

beforeEach(function (): void {
    foreach (['openai', 'anthropic', 'mistral'] as $provider) {
        config()->set("ai.providers.{$provider}.api_key", '');
    }

    config()->set('core.deepl_api_key', '');
    config()->set('ai.providers.ollama.api_url', '');
    config()->set('ai.providers.whisper.url', '');
});

it('drops the entries of a provider that is not configured', function (): void {
    $result = app(ModelCatalog::class)->build([AiModelFeature::Chat], ['features.chat.model' => ['openai:gpt-4o']]);

    expect($result->choices['features.chat.model'])->toBe([])
        ->and($result->outcomes['openai']->status)->toBe(ProviderListingStatus::NotConfigured)
        ->and($result->hasFailures())->toBeFalse();
});

it('keeps the previous entries of a configured provider that fails', function (): void {
    config()->set('ai.providers.anthropic.api_key', 'ak-test');
    Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);

    $result = app(ModelCatalog::class)->build(
        [AiModelFeature::Chat],
        ['features.chat.model' => ['anthropic:claude-opus-5', 'openai:gpt-4o']],
    );

    expect($result->choices['features.chat.model'])->toBe(['anthropic:claude-opus-5'])
        ->and($result->outcomes['anthropic']->status)->toBe(ProviderListingStatus::Failed)
        ->and($result->hasFailures())->toBeTrue();
});

it('treats a timeout like any other failure', function (): void {
    config()->set('ai.providers.ollama.api_url', 'http://ollama.test');
    Http::fake(['ollama.test/*' => Http::failedConnection('Operation timed out')]);

    $result = app(ModelCatalog::class)->build([AiModelFeature::Faq], ['features.faq.model' => ['ollama:llama3.2:3b']]);

    expect($result->choices['features.faq.model'])->toBe(['ollama:llama3.2:3b'])
        ->and($result->outcomes['ollama']->status)->toBe(ProviderListingStatus::Failed)
        ->and($result->outcomes['ollama']->error)->toContain('timed out');
});

it('reports an empty listing as listed, not failed', function (): void {
    config()->set('ai.providers.ollama.api_url', 'http://ollama.test');
    Http::fake(['ollama.test/api/tags' => Http::response(['models' => []])]);

    $result = app(ModelCatalog::class)->build([AiModelFeature::Chat], ['features.chat.model' => ['ollama:gone:1b']]);

    expect($result->choices['features.chat.model'])->toBe([])
        ->and($result->outcomes['ollama']->status)->toBe(ProviderListingStatus::Listed)
        ->and($result->outcomes['ollama']->modelCount)->toBe(0)
        ->and($result->hasFailures())->toBeFalse();
});

it('filters by the capabilities each feature requires and calls each provider once', function (): void {
    config()->set('ai.providers.mistral.api_key', 'mk-test');
    config()->set('ai.providers.openai.api_key', 'sk-test');
    Http::fake([
        'api.mistral.ai/v1/models' => Http::response(['data' => [
            ['id' => 'mistral-large-latest', 'capabilities' => ['completion_chat' => true, 'function_calling' => true, 'vision' => false]],
            ['id' => 'mistral-small-no-tools', 'capabilities' => ['completion_chat' => true, 'function_calling' => false, 'vision' => false]],
        ]]),
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4o'], ['id' => 'text-embedding-3-small']]]),
    ]);

    $result = app(ModelCatalog::class)->build(
        [AiModelFeature::Chat, AiModelFeature::TextGeneration, AiModelFeature::Vision],
        [],
    );

    expect($result->choices['features.chat.model'])->toBe(['mistral:mistral-large-latest', 'openai:gpt-4o'])
        ->and($result->choices['features.text_generation.model'])->toBe(['mistral:mistral-large-latest', 'mistral:mistral-small-no-tools', 'openai:gpt-4o'])
        ->and($result->choices['features.media_analysis.vision.model'])->toBe(['openai:gpt-4o']);

    Http::assertSentCount(2);
});

it('offers providers without a catalogue by name when configured', function (): void {
    Http::fake();
    config()->set('core.deepl_api_key', 'dk-test');
    config()->set('ai.providers.whisper.url', 'http://whisper.test');

    $result = app(ModelCatalog::class)->build([AiModelFeature::Translation, AiModelFeature::Transcription], []);

    expect($result->choices['features.translation.model'])->toBe(['deepl'])
        ->and($result->choices['features.media_analysis.transcription.model'])->toBe(['whisper']);

    Http::assertNothingSent();
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/Models/ModelCatalogTest.php`
Expected: FAIL with "Class ... ModelCatalog not found".

- [x] **Step 3: Write `ProviderConfiguration`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use function ai_config_string;

/**
 * Whether a provider is configured, and how to list its models. A provider is configured
 * when its API key (openai, anthropic, mistral, deepl) or its URL (ollama, whisper) is set.
 */
final readonly class ProviderConfiguration
{
    public function isConfigured(string $provider): bool
    {
        return match ($provider) {
            'openai', 'anthropic', 'mistral' => ai_config_string("ai.providers.{$provider}.api_key") !== '',
            'deepl' => ai_config_string('core.deepl_api_key') !== '',
            'ollama' => ai_config_string('ai.providers.ollama.api_url') !== '',
            'whisper' => ai_config_string('ai.providers.whisper.url') !== '',
            default => false,
        };
    }

    /**
     * Null for providers without a model catalogue: DeepL has no model to choose, and the
     * Whisper host picks its model through its own `WHISPER_MODEL`.
     */
    public function lister(string $provider): ?ModelLister
    {
        return match ($provider) {
            'openai' => new OpenAiModelLister(ai_config_string('ai.providers.openai.api_key')),
            'anthropic' => new AnthropicModelLister(ai_config_string('ai.providers.anthropic.api_key')),
            'mistral' => new MistralModelLister(ai_config_string('ai.providers.mistral.api_key')),
            'ollama' => new OllamaModelLister(ai_config_string('ai.providers.ollama.api_url')),
            default => null,
        };
    }
}
```

- [x] **Step 4: Write the outcome types**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum ProviderListingStatus: string
{
    case Listed = 'listed';
    case NotConfigured = 'not_configured';
    case Failed = 'failed';
}
```

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Modules\AI\Enums\ProviderListingStatus;

final readonly class ProviderOutcome
{
    public function __construct(
        public string $provider,
        public ProviderListingStatus $status,
        public int $modelCount = 0,
        public ?string $error = null,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Modules\AI\Enums\ProviderListingStatus;

final readonly class CatalogResult
{
    /**
     * @param  array<string, list<string>>  $choices  keyed by setting name
     * @param  array<string, ProviderOutcome>  $outcomes  keyed by provider
     */
    public function __construct(
        public array $choices,
        public array $outcomes,
    ) {}

    public function hasFailures(): bool
    {
        return array_any($this->outcomes, static fn (ProviderOutcome $outcome): bool => $outcome->status === ProviderListingStatus::Failed);
    }
}
```

- [x] **Step 5: Write `ModelCatalog`**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\Providers\Models;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Modules\AI\Ai\Providers\AiModelChoice;
use Modules\AI\Enums\AiModelFeature;
use Modules\AI\Enums\ProviderListingStatus;

/**
 * Builds the model choices of a set of features. Each needed provider is called once per
 * build. A provider that is not configured loses its entries; one that fails keeps the
 * entries it had, so an outage never empties a list.
 */
final readonly class ModelCatalog
{
    public function __construct(private ProviderConfiguration $providers) {}

    /**
     * @param  list<AiModelFeature>  $features
     * @param  array<string, list<string>>  $currentChoices  keyed by setting name
     */
    public function build(array $features, array $currentChoices): CatalogResult
    {
        $outcomes = [];
        $listed = [];

        foreach ($features as $feature) {
            foreach ($feature->supportedProviders() as $provider) {
                if (! array_key_exists($provider, $outcomes)) {
                    [$outcomes[$provider], $listed[$provider]] = $this->listProvider($provider);
                }
            }
        }

        $choices = [];

        foreach ($features as $feature) {
            $choices[$feature->settingName()] = $this->choicesFor(
                $feature,
                $outcomes,
                $listed,
                $currentChoices[$feature->settingName()] ?? [],
            );
        }

        return new CatalogResult($choices, $outcomes);
    }

    /**
     * @return array{0: ProviderOutcome, 1: list<ListedModel>|null}
     */
    private function listProvider(string $provider): array
    {
        if (! $this->providers->isConfigured($provider)) {
            return [new ProviderOutcome($provider, ProviderListingStatus::NotConfigured), null];
        }

        $lister = $this->providers->lister($provider);

        if ($lister === null) {
            return [new ProviderOutcome($provider, ProviderListingStatus::Listed, 1), null];
        }

        try {
            $models = $lister->list();
        } catch (ConnectionException|RequestException $exception) {
            return [new ProviderOutcome($provider, ProviderListingStatus::Failed, error: $exception->getMessage()), null];
        }

        return [new ProviderOutcome($provider, ProviderListingStatus::Listed, count($models)), $models];
    }

    /**
     * @param  array<string, ProviderOutcome>  $outcomes
     * @param  array<string, list<ListedModel>|null>  $listed
     * @param  list<string>  $current
     * @return list<string>
     */
    private function choicesFor(AiModelFeature $feature, array $outcomes, array $listed, array $current): array
    {
        $entries = [];

        foreach ($feature->supportedProviders() as $provider) {
            $outcome = $outcomes[$provider];

            if ($outcome->status === ProviderListingStatus::NotConfigured) {
                continue;
            }

            if ($outcome->status === ProviderListingStatus::Failed) {
                foreach ($current as $entry) {
                    if (AiModelChoice::parse($entry)->provider === $provider) {
                        $entries[] = $entry;
                    }
                }

                continue;
            }

            $models = $listed[$provider];

            if ($models === null) {
                $entries[] = $provider;

                continue;
            }

            foreach ($models as $model) {
                if ($model->satisfies($feature->requiredCapabilities())) {
                    $entries[] = new AiModelChoice($provider, $model->id)->value();
                }
            }
        }

        $entries = array_values(array_unique($entries));
        sort($entries, SORT_STRING);

        return $entries;
    }
}
```

- [x] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Providers/Models/ModelCatalogTest.php`
Expected: PASS.

- [x] **Step 7: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/app/Ai/Providers/Models/ProviderConfiguration.php Modules/AI/app/Enums/ProviderListingStatus.php Modules/AI/app/Ai/Providers/Models/ProviderOutcome.php Modules/AI/app/Ai/Providers/Models/CatalogResult.php Modules/AI/app/Ai/Providers/Models/ModelCatalog.php Modules/AI/tests/Unit/Ai/Providers/Models/ModelCatalogTest.php
git -C Modules/AI add app/Ai/Providers/Models app/Enums/ProviderListingStatus.php tests/Unit/Ai/Providers/Models/ModelCatalogTest.php
git -C Modules/AI commit -m "feat(ai): build per-feature model choices from the configured providers" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Model settings, `ai:models:refresh` and its schedule

**Files:**
- Modify: `Modules/AI/database/seeders/AIDatabaseSeeder.php`
- Create: `Modules/AI/app/Console/RefreshAiModelsCommand.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php` (`registerCommandSchedules()`)
- Modify: `Modules/AI/tests/Feature/Seeders/AIDatabaseSeederTest.php`
- Create: `Modules/AI/tests/Feature/Console/RefreshAiModelsCommandTest.php`

**Interfaces:**
- Consumes: `Seeder::commandManagedChoicesSettingsDefinition()` (Task 2); `Setting::isValueOutsideChoices()` (Task 5); `AiModelFeature`, `AiModelChoice` (Task 8); `ModelCatalog` (Task 10).
- Produces: `AIDatabaseSeeder::modelSettingDefinitions(): list<array<string,mixed>>`; command `ai:models:refresh {--setting=}`, exit `0` or `1`; schedule daily at 03:00.

- [x] **Step 1: Write the failing seeder test**

Append to `AIDatabaseSeederTest.php` (add `use Modules\AI\Enums\AiModelFeature;`):

```php
it('seeds one model setting per feature with its refresh action', function (): void {
    $this->seed(AIDatabaseSeeder::class);

    foreach (AiModelFeature::cases() as $feature) {
        $setting = Setting::query()->withoutGlobalScopes()->where('name', $feature->settingName())->sole();
        $initial = $feature->defaultChoice();

        expect($setting->module)->toBe('AI')
            ->and($setting->value)->toBe($initial)
            ->and($setting->choices)->toBe([$initial])
            ->and($setting->action_command)->toBe('ai:models:refresh --setting={name}')
            ->and($setting->action_queued)->toBeFalse();
    }
});

it('keeps refreshed model choices across a re-seed', function (): void {
    $this->seed(AIDatabaseSeeder::class);

    Setting::query()->withoutGlobalScopes()->where('name', 'features.chat.model')
        ->update(['choices' => json_encode(['ollama:a', 'ollama:b'])]);

    $this->seed(AIDatabaseSeeder::class);

    expect(Setting::query()->withoutGlobalScopes()->where('name', 'features.chat.model')->sole()->choices)
        ->toBe(['ollama:a', 'ollama:b']);
});
```

- [x] **Step 2: Write the failing command test**

```php
<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\Core\Models\Setting;

beforeEach(function (): void {
    foreach (['openai', 'anthropic', 'mistral'] as $provider) {
        config()->set("ai.providers.{$provider}.api_key", '');
    }

    config()->set('core.deepl_api_key', '');
    config()->set('ai.providers.whisper.url', '');
    config()->set('ai.providers.ollama.api_url', 'http://ollama.test');

    $this->seed(AIDatabaseSeeder::class);
});

function refreshCommandSetting(string $name): Setting
{
    return Setting::query()->withoutGlobalScopes()->where('name', $name)->sole();
}

function fakeOllamaModels(array $names): void
{
    Http::fake([
        'ollama.test/api/tags' => Http::response(['models' => array_map(static fn (string $name): array => ['name' => $name], $names)]),
        'ollama.test/api/show' => Http::response(['capabilities' => ['completion', 'tools']]),
    ]);
}

it('refreshes every model setting from the providers', function (): void {
    fakeOllamaModels(['llama3.2:3b', 'qwen3:8b']);

    $this->artisan('ai:models:refresh')->assertExitCode(0);

    expect(refreshCommandSetting('features.chat.model')->choices)->toBe(['ollama:llama3.2:3b', 'ollama:qwen3:8b'])
        ->and(refreshCommandSetting('features.faq.model')->choices)->toBe(['ollama:llama3.2:3b', 'ollama:qwen3:8b']);
});

it('refreshes only the named setting', function (): void {
    fakeOllamaModels(['qwen3:8b']);
    $faq_before = refreshCommandSetting('features.faq.model')->choices;

    $this->artisan('ai:models:refresh', ['--setting' => 'features.chat.model'])->assertExitCode(0);

    expect(refreshCommandSetting('features.chat.model')->choices)->toBe(['ollama:qwen3:8b'])
        ->and(refreshCommandSetting('features.faq.model')->choices)->toBe($faq_before);
});

it('fails on a setting that is not a model setting and writes nothing', function (): void {
    Http::fake();
    $chat_before = refreshCommandSetting('features.chat.model')->choices;

    $this->artisan('ai:models:refresh', ['--setting' => 'features.faq.enabled'])->assertExitCode(1);

    expect(refreshCommandSetting('features.chat.model')->choices)->toBe($chat_before);
    Http::assertNothingSent();
});

it('fails when a provider fails but keeps its entries', function (): void {
    Setting::query()->withoutGlobalScopes()->where('name', 'features.chat.model')
        ->update(['choices' => json_encode(['ollama:llama3.2:3b'])]);
    Http::fake(['ollama.test/*' => Http::response([], 500)]);

    $this->artisan('ai:models:refresh', ['--setting' => 'features.chat.model'])->assertExitCode(1);

    expect(refreshCommandSetting('features.chat.model')->choices)->toBe(['ollama:llama3.2:3b']);
});

it('warns when the current value is no longer offered', function (): void {
    Setting::query()->withoutGlobalScopes()->where('name', 'features.chat.model')
        ->update(['value' => json_encode('ollama:gone:1b')]);
    fakeOllamaModels(['qwen3:8b']);

    $this->artisan('ai:models:refresh', ['--setting' => 'features.chat.model'])
        ->expectsOutputToContain('is no longer offered')
        ->assertExitCode(0);
});

it('is scheduled every night at 03:00', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn ($event): bool => str_contains((string) $event->command, 'ai:models:refresh'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *');
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/AI/tests/Feature/Seeders/AIDatabaseSeederTest.php Modules/AI/tests/Feature/Console/RefreshAiModelsCommandTest.php`
Expected: FAIL: no model settings are seeded, the command does not exist.

- [x] **Step 4: Seed the model settings**

In `AIDatabaseSeeder.php` add the import `use Modules\AI\Enums\AiModelFeature;`. Add:

```php
    /**
     * One setting per AI feature holding its `provider:model`. The initial value is the
     * feature's default choice, from code: it does not read config, so seeding never depends on
     * what the overlay holds. The refresh command owns the choices from its first run on.
     *
     * @return list<array<string, mixed>>
     */
    public static function modelSettingDefinitions(): array
    {
        return array_map(
            static function (AiModelFeature $feature): array {
                $initial = $feature->defaultChoice();

                return [
                    ...self::setting($feature->settingName(), $initial, SettingTypeEnum::String, 'ai', $feature->settingDescription(), [$initial]),
                    'action_command' => 'ai:models:refresh --setting={name}',
                    'action_queued' => false,
                ];
            },
            AiModelFeature::cases(),
        );
    }
```

Replace `run()` with:

```php
    public function run(): void
    {
        $reconciler = app(SeedReconciler::class);

        $runtime = $reconciler->reconcile(
            self::internalSettingsDefinition('AI', [
                ...self::runtimeSettingDefinitions(),
                ...app(ModerationEntitySettings::class)->definitions(),
            ]),
        );
        $models = $reconciler->reconcile(
            self::commandManagedChoicesSettingsDefinition('AI', self::modelSettingDefinitions()),
        );

        $this->command?->line(sprintf(
            '    - created %d, realigned %d, unchanged %d',
            count($runtime->created) + count($models->created),
            count($runtime->realigned) + count($models->realigned),
            $runtime->unchanged + $models->unchanged,
        ));
    }
```

- [x] **Step 5: Write the command**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Modules\AI\Ai\Providers\Models\CatalogResult;
use Modules\AI\Ai\Providers\Models\ModelCatalog;
use Modules\AI\Enums\AiModelFeature;
use Modules\AI\Enums\ProviderListingStatus;
use Modules\Core\Models\Setting;
use Override;

/**
 * Refreshes the choices of the AI model settings from the providers' model lists. Run from
 * the settings grid (one setting) and nightly (all of them). Exits 1 when a provider failed:
 * its previous entries are kept, the other providers' entries are still written.
 */
final class RefreshAiModelsCommand extends Command
{
    #[Override]
    protected $signature = 'ai:models:refresh
                            {--setting= : Refresh only this model setting, e.g. features.chat.model}';

    #[Override]
    protected $description = 'Refresh the model choices of the AI model settings from the providers <fg=magenta>(✨ Modules\AI)</fg=magenta>';

    public function handle(ModelCatalog $catalog): int
    {
        $features = $this->features();

        if ($features === null) {
            $this->error('Not an AI model setting: ' . $this->option('setting'));

            return self::FAILURE;
        }

        $names = array_map(static fn (AiModelFeature $feature): string => $feature->settingName(), $features);
        $settings = Setting::query()->whereIn('name', $names)->get()->keyBy('name');
        $current = $settings
            ->map(static fn (Setting $setting): array => array_values(array_filter((array) $setting->choices, 'is_string')))
            ->all();

        $result = $catalog->build($features, $current);

        $this->reportProviders($result);

        foreach ($features as $feature) {
            $this->writeChoices($feature, $settings->get($feature->settingName()), $result);
        }

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<AiModelFeature>|null
     */
    private function features(): ?array
    {
        $name = $this->option('setting');

        if (! is_string($name) || $name === '') {
            return AiModelFeature::cases();
        }

        $feature = AiModelFeature::fromSettingName($name);

        return $feature === null ? null : [$feature];
    }

    private function reportProviders(CatalogResult $result): void
    {
        foreach ($result->outcomes as $outcome) {
            match ($outcome->status) {
                ProviderListingStatus::Listed => $this->line("{$outcome->provider}: {$outcome->modelCount} models"),
                ProviderListingStatus::NotConfigured => $this->line("{$outcome->provider}: not configured"),
                ProviderListingStatus::Failed => $this->error("{$outcome->provider}: failed, previous entries kept ({$outcome->error})"),
            };
        }
    }

    private function writeChoices(AiModelFeature $feature, ?Setting $setting, CatalogResult $result): void
    {
        $name = $feature->settingName();

        if (! $setting instanceof Setting) {
            $this->warn("{$name}: setting not seeded, skipped");

            return;
        }

        $choices = $result->choices[$name];
        $setting->choices = $choices;
        $setting->save();

        $this->line("{$name}: " . count($choices) . ' choices');

        if ($choices === []) {
            $this->warn("{$name}: no configured provider offers a model");
        } elseif ($setting->isValueOutsideChoices()) {
            $this->warn("{$name}: current value {$setting->value} is no longer offered");
        }
    }
}
```

- [x] **Step 6: Schedule it**

In `AIServiceProvider.php` add imports `use Illuminate\Console\Scheduling\Schedule;` and `use Modules\AI\Console\RefreshAiModelsCommand;`, and:

```php
    #[Override]
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(RefreshAiModelsCommand::class)
                ->dailyAt('03:00')
                ->onOneServer();
        });
    }
```

Check first that `Modules\Core\Overrides\ModuleServiceProvider::boot()` calls `registerCommandSchedules()` (`rtk proxy grep -n "registerCommandSchedules" Modules/Core/app/Overrides/ModuleServiceProvider.php`); if it does not, call it from `AIServiceProvider::boot()`.

- [x] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Feature/Seeders/AIDatabaseSeederTest.php Modules/AI/tests/Feature/Console/RefreshAiModelsCommandTest.php`
Expected: PASS.

- [x] **Step 8: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/database/seeders/AIDatabaseSeeder.php Modules/AI/app/Console/RefreshAiModelsCommand.php Modules/AI/app/Providers/AIServiceProvider.php Modules/AI/tests/Feature/Seeders/AIDatabaseSeederTest.php Modules/AI/tests/Feature/Console/RefreshAiModelsCommandTest.php
git -C Modules/AI add database/seeders/AIDatabaseSeeder.php app/Console/RefreshAiModelsCommand.php app/Providers/AIServiceProvider.php tests/Feature/Seeders/AIDatabaseSeederTest.php tests/Feature/Console/RefreshAiModelsCommandTest.php
git -C Modules/AI commit -m "feat(ai): seed model settings and refresh their choices from the providers" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Chat-family consumers read their feature's choice

**Files:**
- Modify: `Modules/AI/app/Ai/Agents/ChatAgent.php` (add `forFeature()`)
- Modify: `Modules/AI/app/Ai/Providers/ProviderFactory.php` (`make()` default)
- Modify: `Modules/AI/app/Services/ChatService.php` (`buildProtectedAgent()`)
- Modify: `Modules/AI/app/Listeners/HandleAiTextGenerationListener.php` (`log()`, `makeChatAgent()`)
- Modify: `Modules/AI/app/Services/ModerationService.php` (`createAgent()`)
- Modify: `Modules/AI/app/Services/LlmSearchService.php` (`createAgent()`)
- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php` (`provider()`)
- Modify: `Modules/AI/app/Services/ContextualSuggestionService.php` (`makeChatAgent()`)
- Modify: `Modules/AI/app/Services/MemoryService.php` (`makeChatAgent()`)
- Modify: `Modules/AI/app/Services/GuardrailsService.php` (extract `makeChatAgent()`)
- Modify: `Modules/AI/config/config.php` (remove `chat.default_provider`, `moderation.provider`, `search_orchestration.default_provider`, `providers.anthropic.model`)
- Modify tests: `ProviderFactoryTest.php`, `ChatAgentTest.php`, `ChatServiceFullTest.php`, `DocumentationAgentTest.php` (all under `Modules/AI/tests/Integration/`)
- Create: `Modules/AI/tests/Integration/AiModelFeatureWiringTest.php`

**Interfaces:**
- Consumes: `AiModelFeature`, `AiModelChoice` (Task 8).
- Produces: `ChatAgent::forFeature(AiModelFeature $feature, ?string $systemPrompt = null): static`; `ProviderFactory::make(null, ...)` takes both provider and model from the chat choice.

- [x] **Step 1: Write the failing wiring test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Ai\Agents\ChatAgent;
use Modules\AI\Ai\Agents\DocumentationAgent;
use Modules\AI\Ai\Providers\ProviderFactory;
use Modules\AI\Listeners\HandleAiTextGenerationListener;
use Modules\AI\Services\ContextualSuggestionService;
use Modules\AI\Services\GuardrailsService;
use Modules\AI\Services\LlmSearchService;
use Modules\AI\Services\MemoryService;
use Modules\AI\Services\ModerationService;
use Modules\AI\Tests\Stubs\RecordingHttpClient;
use Modules\Core\Data\ModerationInput;
use Modules\Core\Data\ModerationRequest;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;

beforeEach(function (): void {
    config()->set('ai.providers.ollama.api_url', 'http://ollama.test');
    config()->set('ai.providers.anthropic.api_key', 'ak-test');
});

/**
 * @return array{0: ?string, 1: ?string}
 */
function wiredAgent(ChatAgent $agent): array
{
    return [
        (new ReflectionProperty($agent, 'providerName'))->getValue($agent),
        (new ReflectionProperty($agent, 'model'))->getValue($agent),
    ];
}

function invokeWiring(object $target, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
}

it('builds every chat-family agent on its own feature choice', function (): void {
    config()->set('ai.features.chat.model', 'ollama:chat-model');
    config()->set('ai.features.text_generation.model', 'ollama:text-model');
    config()->set('ai.features.moderation.model', 'ollama:moderation-model');
    config()->set('ai.features.search_orchestration.model', 'ollama:search-model');
    config()->set('ai.features.contextual_suggestions.model', 'ollama:suggestion-model');
    config()->set('ai.features.chat.summary.model', 'ollama:summary-model');
    config()->set('ai.features.guardrails.model', 'ollama:guard-model');

    $request = new ModerationRequest(
        input: new ModerationInput(subjectText: 'x', locale: 'en', contextSections: [], profile: 'test'),
        systemPrompt: 'Moderate.',
        userPrompt: 'x',
    );

    expect(wiredAgent(ChatAgent::forFeature(Modules\AI\Enums\AiModelFeature::Chat)))->toBe(['ollama', 'chat-model'])
        ->and(wiredAgent(invokeWiring(new HandleAiTextGenerationListener(), 'makeChatAgent')))->toBe(['ollama', 'text-model'])
        ->and(wiredAgent(invokeWiring(new ModerationService(new GuardrailsService()), 'createAgent', $request)))->toBe(['ollama', 'moderation-model'])
        ->and(wiredAgent(invokeWiring(new LlmSearchService(), 'createAgent', 'prompt')))->toBe(['ollama', 'search-model'])
        ->and(wiredAgent(invokeWiring(new ContextualSuggestionService(), 'makeChatAgent')))->toBe(['ollama', 'suggestion-model'])
        ->and(wiredAgent(invokeWiring(new MemoryService(), 'makeChatAgent', 'prompt')))->toBe(['ollama', 'summary-model'])
        ->and(wiredAgent(invokeWiring(new GuardrailsService(), 'makeChatAgent')))->toBe(['ollama', 'guard-model']);
});

it('keeps an explicit provider override on search orchestration', function (): void {
    config()->set('ai.features.search_orchestration.model', 'ollama:search-model');

    expect(wiredAgent(invokeWiring(new LlmSearchService('anthropic'), 'createAgent', 'prompt')))->toBe(['anthropic', null]);
});

it('answers FAQ questions on the FAQ choice', function (): void {
    config()->set('ai.features.faq.model', 'ollama:faq-model');

    $provider = invokeWiring(DocumentationAgent::make(), 'provider');
    $client = new RecordingHttpClient(['message' => ['content' => 'ok']]);
    $provider->setHttpClient($client);
    $provider->chat(new UserMessage('hi'));

    expect($client->lastRequest->body['model'])->toBe('faq-model');
});

it('falls back to the chat choice when no provider is named', function (): void {
    config()->set('ai.features.chat.model', 'anthropic:claude-sonnet-5');

    expect(ProviderFactory::make())->toBeInstanceOf(Anthropic::class);
});
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Integration/AiModelFeatureWiringTest.php`
Expected: FAIL: `ChatAgent::forFeature()` does not exist.

- [x] **Step 3: Add `ChatAgent::forFeature()` and the factory default**

`ChatAgent.php` (imports `Modules\AI\Ai\Providers\AiModelChoice`, `Modules\AI\Enums\AiModelFeature`):

```php
    /**
     * An agent on the provider and model chosen in Settings for this feature.
     */
    public static function forFeature(AiModelFeature $feature, ?string $systemPrompt = null): static
    {
        $choice = AiModelChoice::forFeature($feature);

        return static::make($choice->provider, $systemPrompt, $choice->model);
    }
```

`ProviderFactory::make()`, replace the first line of the body:

```php
        if ($provider === null) {
            $choice = AiModelChoice::forFeature(AiModelFeature::Chat);
            $provider = $choice->provider;
            $model ??= $choice->model;
        }
```

and update its docblock: "Without a provider, provider and model come from the chat choice in Settings."

- [x] **Step 4: Switch each consumer**

- `ChatService::buildProtectedAgent()`: `return $provider === null ? ChatAgent::forFeature(AiModelFeature::Chat, $system_prompt) : ChatAgent::make($provider, $system_prompt);`
- `HandleAiTextGenerationListener::makeChatAgent()`: after the factory check, replace the Task 8 body with `return ChatAgent::forFeature(AiModelFeature::TextGeneration, self::SYSTEM_PROMPT); // @codeCoverageIgnore`.
- `ModerationService::createAgent()`: after the factory check, `return ChatAgent::forFeature(AiModelFeature::Moderation, $request->systemPrompt);`
- `LlmSearchService::createAgent()`: `return $this->provider !== null ? ChatAgent::make($this->provider, $system_prompt) : ChatAgent::forFeature(AiModelFeature::SearchOrchestration, $system_prompt);`
- `DocumentationAgent::provider()`:

```php
    protected function provider(): AIProviderInterface
    {
        if ($this->providerName !== null) {
            return ProviderFactory::make($this->providerName);
        }

        $choice = AiModelChoice::forFeature(AiModelFeature::Faq);

        return ProviderFactory::make($choice->provider, $choice->model);
    }
```

- `ContextualSuggestionService::makeChatAgent()`: `return ChatAgent::forFeature(AiModelFeature::ContextualSuggestions, self::SUGGESTION_SYSTEM_PROMPT); // @codeCoverageIgnore`
- `MemoryService::makeChatAgent()`: `return ChatAgent::forFeature(AiModelFeature::ChatSummary, $systemPrompt); // @codeCoverageIgnore`
- `GuardrailsService::checkViaLlmFallback()`: replace `$factory = $this->chatAgentFactory ?? fn (): ChatAgent => ChatAgent::make(systemPrompt: self::INJECTION_DETECTION_PROMPT);` and `$agent = $factory();` with `$agent = $this->makeChatAgent();`, and add:

```php
    private function makeChatAgent(): ChatAgent
    {
        if ($this->chatAgentFactory instanceof Closure) {
            return ($this->chatAgentFactory)();
        }

        return ChatAgent::forFeature(AiModelFeature::Guardrails, self::INJECTION_DETECTION_PROMPT);
    }
```

Add the `AiModelFeature` / `AiModelChoice` / `Closure` imports where used; drop `ai_config_string` imports that become unused.

- [x] **Step 5: Remove the replaced config keys**

In `config.php` remove `chat.default_provider` (`AI_CHAT_PROVIDER`), `moderation.provider` (`AI_MODERATION_PROVIDER`, `AI_COMMENT_MOD_PROVIDER`), `search_orchestration.default_provider` (`AI_SEARCH_ORCHESTRATION_PROVIDER`) and `providers.anthropic.model` (`ANTHROPIC_MODEL`). Keep `search_orchestration.enabled`, `moderation.queue`, `text_generation.enabled` and the other tuning keys, and keep `providers.{openai,ollama,mistral}.model`: the embeddings factory reads them.

In `ProviderFactory::createAnthropic()`, the model fallback becomes a constant: `model: $model ?? self::ANTHROPIC_DEFAULT_MODEL,` with `private const string ANTHROPIC_DEFAULT_MODEL = 'claude-sonnet-4-20250514';` on the class. In `ProviderFactoryTest.php`, "creates an Anthropic provider when configured" drops its `config()->set('ai.providers.anthropic.model', ...)` line.

- [x] **Step 6: Update the existing tests to the new keys**

- `ProviderFactoryTest.php`, "uses default provider from config when none specified": replace `config()->set('ai.features.chat.default_provider', 'ollama');` with `config()->set('ai.features.chat.model', 'ollama:llama3.2:3b');`.
- `ChatAgentTest.php`, `ChatServiceFullTest.php`: every `config()->set('ai.features.chat.default_provider', 'ollama');` becomes `config()->set('ai.features.chat.model', 'ollama:llama3.2:3b');`.
- `DocumentationAgentTest.php`: `config()->set('ai.features.chat.default_provider', 'ollama');` becomes `config()->set('ai.features.faq.model', 'ollama:llama3.2:3b');`.

- [x] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Integration/AiModelFeatureWiringTest.php Modules/AI/tests/Integration/ProviderFactoryTest.php Modules/AI/tests/Integration/ChatAgentTest.php Modules/AI/tests/Integration/ChatServiceFullTest.php Modules/AI/tests/Integration/DocumentationAgentTest.php Modules/AI/tests/Integration/AiTextGenerationModelBindingTest.php Modules/AI/tests/Integration/HandleAiTextGenerationListenerTest.php Modules/AI/tests/Integration/GuardrailsServiceTest.php Modules/AI/tests/Integration/GuardrailsServiceFullTest.php Modules/AI/tests/Integration/MemoryServiceFullTest.php Modules/AI/tests/Integration/ContextualSuggestionServiceTest.php Modules/AI/tests/Integration/LlmSearchServiceTest.php Modules/AI/tests/Integration/Services/ModerationServiceTest.php`
Expected: PASS.

- [x] **Step 8: Check no reader of the removed keys is left**

Run: `rtk proxy grep -rnE "features\.(chat\.default_provider|text_generation\.default_provider|moderation\.provider|search_orchestration\.default_provider)" Modules/*/app Modules/*/tests Modules/*/config`
Expected: no output.

- [x] **Step 9: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/app/Ai/Agents/ChatAgent.php Modules/AI/app/Ai/Providers/ProviderFactory.php Modules/AI/app/Services/ChatService.php Modules/AI/app/Listeners/HandleAiTextGenerationListener.php Modules/AI/app/Services/ModerationService.php Modules/AI/app/Services/LlmSearchService.php Modules/AI/app/Ai/Agents/DocumentationAgent.php Modules/AI/app/Services/ContextualSuggestionService.php Modules/AI/app/Services/MemoryService.php Modules/AI/app/Services/GuardrailsService.php Modules/AI/config/config.php Modules/AI/tests/Integration/AiModelFeatureWiringTest.php Modules/AI/tests/Integration/ProviderFactoryTest.php Modules/AI/tests/Integration/ChatAgentTest.php Modules/AI/tests/Integration/ChatServiceFullTest.php Modules/AI/tests/Integration/DocumentationAgentTest.php
git -C Modules/AI add app/Ai/Agents/ChatAgent.php app/Ai/Providers/ProviderFactory.php app/Services/ChatService.php app/Listeners/HandleAiTextGenerationListener.php app/Services/ModerationService.php app/Services/LlmSearchService.php app/Ai/Agents/DocumentationAgent.php app/Services/ContextualSuggestionService.php app/Services/MemoryService.php app/Services/GuardrailsService.php config/config.php tests/Integration/AiModelFeatureWiringTest.php tests/Integration/ProviderFactoryTest.php tests/Integration/ChatAgentTest.php tests/Integration/ChatServiceFullTest.php tests/Integration/DocumentationAgentTest.php
git -C Modules/AI commit -m "feat(ai): every chat-family feature uses its own model setting" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 13: Media analysis on its settings: models, `whisper`, master switch

Amends M18 and M21 of `docs/superpowers/specs/2026-09-18-media-ai-analysis-search-design.md`, whose plan (`2026-09-19-media-ai-analysis-search.md`) is still open: its Task 1 built the static registry and the unseeded switch this task replaces.

**Files:**
- Modify: `Modules/AI/app/Ai/MediaAnalysis/MediaAnalysisModelRegistry.php`
- Modify: `Modules/AI/app/Ai/MediaAnalysis/MediaAnalysisGate.php`
- Modify: `Modules/AI/database/seeders/AIDatabaseSeeder.php` (`runtimeSettingDefinitions()`)
- Modify: `Modules/AI/config/config.php` (remove `media_analysis.enabled` and `media_analysis.capabilities`)
- Modify: `Modules/AI/tests/Unit/Ai/MediaAnalysis/MediaAnalysisModelRegistryTest.php`
- Modify: `Modules/AI/tests/Feature/MediaAnalysis/MediaAnalysisGateTest.php`
- Modify: `Modules/AI/tests/Feature/MediaAnalysis/WhisperTranscriberTest.php`
- Modify: `Modules/AI/tests/Feature/MediaAnalysis/AnalyzeMediaJobTest.php` (lines 69 and 84)
- Modify: `Modules/AI/docs/WHISPER_INSTALLATION.md` (lines 22 and 103)

**Interfaces:**
- Consumes: `AiModelFeature::Vision`, `AiModelFeature::Transcription`, `AiModelChoice` (Task 8).
- Produces:
  - `MediaAnalysisModelRegistry::active(string $capability): MediaAnalysisModelProfile` with `key` = the full choice (`anthropic:claude-sonnet-5`, `whisper`), `provider`, `serviceModel` = model or `''`. `get()` is removed.
  - `MediaAnalysisGate::enabled()` reads `ai_config_bool('ai.features.media_analysis.enabled', false)`; the gate has no constructor dependency and no `SETTING_NAME` / `SETTING_GROUP` constants.
  - Seeded runtime setting `features.media_analysis.enabled` (boolean, group `ai`, `false`).

`AnalyzeMediaJob` stores `$profile->key` as `analysis_model_version`: existing analyses carry `claude-sonnet-5` and will be redone once on the new key. Accepted: developer data, rebuilt with `migrate:fresh --seed`.

- [x] **Step 1: Rewrite the registry test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Ai\MediaAnalysis\MediaAnalysisModelProfile;
use Modules\AI\Ai\MediaAnalysis\MediaAnalysisModelRegistry;

it('builds the vision profile from the vision choice', function (): void {
    config()->set('ai.features.media_analysis.vision.model', 'anthropic:claude-sonnet-5');

    $profile = (new MediaAnalysisModelRegistry())->active('vision');

    expect($profile)->toBeInstanceOf(MediaAnalysisModelProfile::class)
        ->and($profile->capability)->toBe('vision')
        ->and($profile->key)->toBe('anthropic:claude-sonnet-5')
        ->and($profile->provider)->toBe('anthropic')
        ->and($profile->serviceModel)->toBe('claude-sonnet-5');
});

it('builds the transcription profile on whisper', function (): void {
    $profile = (new MediaAnalysisModelRegistry())->active('transcription');

    expect($profile->key)->toBe('whisper')
        ->and($profile->provider)->toBe('whisper')
        ->and($profile->serviceModel)->toBe('');
});

it('follows a changed vision setting', function (): void {
    config()->set('ai.features.media_analysis.vision.model', 'ollama:llava:13b');

    expect((new MediaAnalysisModelRegistry())->active('vision')->serviceModel)->toBe('llava:13b');
});

it('throws on an unknown capability', function (): void {
    (new MediaAnalysisModelRegistry())->active('nope');
})->throws(InvalidArgumentException::class);
```

In `WhisperTranscriberTest.php` line 11: `return new MediaAnalysisModelProfile('transcription', 'whisper', 'whisper', '');`

In `AnalyzeMediaJobTest.php` lines 69 and 84: `'claude-sonnet-5'` becomes `'anthropic:claude-sonnet-5'` (the default vision choice).

- [x] **Step 2: Rewrite the gate test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Ai\MediaAnalysis\MediaAnalysisGate;
use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\Core\Models\Setting;

it('is off when nothing turns it on', function (): void {
    config()->set('ai.features.media_analysis.enabled', null);

    expect(app(MediaAnalysisGate::class)->enabled())->toBeFalse();
});

it('follows the seeded setting, which starts off', function (): void {
    $this->seed(AIDatabaseSeeder::class);

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'features.media_analysis.enabled')->sole();

    expect($setting->value)->toBeFalse()
        ->and($setting->group_name)->toBe('ai')
        ->and(app(MediaAnalysisGate::class)->enabled())->toBeFalse();

    $setting->value = true;
    $setting->save();

    expect(app(MediaAnalysisGate::class)->enabled())->toBeTrue();
});
```

`HandleMediaAnalysisListenerTest.php` keeps working: it sets `ai.features.media_analysis.enabled` through `config()->set()`, which is what the overlay writes.

- [x] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/MediaAnalysis/MediaAnalysisModelRegistryTest.php Modules/AI/tests/Feature/MediaAnalysis`
Expected: FAIL: the registry reads `capabilities.*.active`, and no `features.media_analysis.enabled` row is seeded.

- [x] **Step 4: Rewrite the registry**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\MediaAnalysis;

use InvalidArgumentException;
use Modules\AI\Ai\Providers\AiModelChoice;
use Modules\AI\Enums\AiModelFeature;

/**
 * Resolves the media-analysis profile of a capability from its model setting
 * (`features.media_analysis.{vision,transcription}.model`). The profile key is the full
 * `provider:model` choice, stored with each analysis as its model version.
 */
final class MediaAnalysisModelRegistry
{
    public function active(string $capability): MediaAnalysisModelProfile
    {
        $feature = match ($capability) {
            'vision' => AiModelFeature::Vision,
            'transcription' => AiModelFeature::Transcription,
            default => throw new InvalidArgumentException("Unknown media-analysis capability: {$capability}"),
        };

        $choice = AiModelChoice::forFeature($feature);

        return new MediaAnalysisModelProfile(
            capability: $capability,
            key: $choice->value(),
            provider: $choice->provider,
            serviceModel: $choice->model ?? '',
        );
    }
}
```

- [x] **Step 5: Rewrite the gate**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Ai\MediaAnalysis;

use function ai_config_bool;

use Illuminate\Database\Eloquent\Model;
use Modules\AI\Services\FeatureModuleGate;

/**
 * The runtime master switch for the whole media LLM analysis subsystem (M18), plus the
 * per-module allowlist (M8/M10). The switch is the seeded setting
 * `features.media_analysis.enabled` (group `ai`, off), read from config like every other AI
 * switch; it has no env variable. When off, no LLM media work runs and media still index on
 * Core's deterministic layer via the fallback listener (M12).
 */
final readonly class MediaAnalysisGate
{
    public function enabled(): bool
    {
        return ai_config_bool('ai.features.media_analysis.enabled', false);
    }

    /**
     * Whether media analysis may run for this model: master switch on and the
     * optional per-module allowlist admits the model.
     */
    public function allows(Model $model): bool
    {
        return $this->enabled() && FeatureModuleGate::allows('media_analysis', $model);
    }
}
```

- [x] **Step 6: Seed the switch and clean the config**

In `AIDatabaseSeeder::runtimeSettingDefinitions()`, after the `features.translation.enabled` row:

```php
            self::setting('features.media_analysis.enabled', false, SettingTypeEnum::Boolean, 'ai', 'Enable media LLM analysis (caption, OCR, transcription)'),
```

In `config.php`, under `media_analysis`, delete `'enabled' => env('AI_MEDIA_ANALYSIS_ENABLED', false),` and the whole `capabilities` block; keep `modules`. Replace the block comment with: "Media LLM analysis (M1-M21 in the media spec). The master switch is the setting `features.media_analysis.enabled` and the models are the settings `features.media_analysis.{vision,transcription}.model`; only the per-module allowlist is config."

In `WHISPER_INSTALLATION.md`: the table row at line 22 becomes ``| `features.media_analysis.transcription.model` | Which transcription provider runs | `whisper` |``; delete the `AI_MEDIA_TRANSCRIPTION_MODEL=whisper-local` line (103) and any `AI_MEDIA_WHISPER_LOCAL_MODEL` or `AI_MEDIA_ANALYSIS_ENABLED` mention, noting that the Whisper model is chosen on the Whisper host with `WHISPER_MODEL` and that media analysis is switched on from Settings (`features.media_analysis.enabled`).

- [x] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/MediaAnalysis/MediaAnalysisModelRegistryTest.php Modules/AI/tests/Feature/MediaAnalysis Modules/AI/tests/Feature/Seeders/AIDatabaseSeederTest.php Modules/Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php`
Expected: PASS.

- [x] **Step 8: Check no old name is left**

Run: `rtk proxy grep -rnE "whisper_local|whisper-local|capabilities\.(vision|transcription)|AI_MEDIA_(ANALYSIS_ENABLED|TRANSCRIPTION_MODEL|WHISPER_LOCAL_MODEL|VISION_OLLAMA_MODEL|VISION_MODEL)|MediaAnalysisGate::SETTING" Modules/AI --include=*.php --include=*.md`
Expected: no output.

- [x] **Step 9: Format and commit**

```bash
vendor/bin/pint --format agent Modules/AI/app/Ai/MediaAnalysis/MediaAnalysisModelRegistry.php Modules/AI/app/Ai/MediaAnalysis/MediaAnalysisGate.php Modules/AI/database/seeders/AIDatabaseSeeder.php Modules/AI/config/config.php Modules/AI/tests/Unit/Ai/MediaAnalysis/MediaAnalysisModelRegistryTest.php Modules/AI/tests/Feature/MediaAnalysis/MediaAnalysisGateTest.php Modules/AI/tests/Feature/MediaAnalysis/WhisperTranscriberTest.php Modules/AI/tests/Feature/MediaAnalysis/AnalyzeMediaJobTest.php
git -C Modules/AI add app/Ai/MediaAnalysis/MediaAnalysisModelRegistry.php app/Ai/MediaAnalysis/MediaAnalysisGate.php database/seeders/AIDatabaseSeeder.php config/config.php tests/Unit/Ai/MediaAnalysis/MediaAnalysisModelRegistryTest.php tests/Feature/MediaAnalysis/MediaAnalysisGateTest.php tests/Feature/MediaAnalysis/WhisperTranscriberTest.php tests/Feature/MediaAnalysis/AnalyzeMediaJobTest.php docs/WHISPER_INSTALLATION.md
git -C Modules/AI commit -m "feat(ai): media analysis reads its settings; seeded master switch; whisper_local becomes whisper" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 14: Translation without fallback, failures retried

**Files:**
- Modify: `Modules/AI/app/Services/Translation/TranslationService.php`
- Modify: `Modules/AI/app/Services/Translation/AiTranslationService.php`
- Modify: `Modules/AI/app/Jobs/TranslateModelJob.php` (`handle()`)
- Modify: `Modules/AI/config/config.php` (remove `translation.default_provider`)
- Modify: `Modules/AI/tests/Integration/TranslationServiceCoreTest.php`
- Modify: `Modules/AI/tests/Integration/AiTranslationServiceTest.php`
- Modify: `Modules/AI/tests/Integration/TranslateModelJobTest.php`
- Modify: `Modules/Core/database/seeders/CoreDatabaseSeeder.php` (remove two settings)
- Modify: `Modules/Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php`

**Interfaces:**
- Consumes: `AiModelFeature::Translation`, `AiModelChoice` (Task 8).
- Produces: `new AiTranslationService(?Closure $chatAgentFactory = null, ?string $provider = null, ?string $model = null)`; the factory closure still receives `?string $provider`. `TranslationService` has no fallback and never returns the source text on failure.

- [x] **Step 1: Rewrite the service tests**

`TranslationServiceCoreTest.php`: replace every `config()->set('core.translations.provider', 'deepl');` with `config()->set('ai.features.translation.model', 'deepl');`. Delete the tests "constructor initializes with ai provider", "constructor throws on unsupported provider", "translate returns original when primary fails and fallback is disabled" and "translate returns original text when both primary and fallback fail". Add:

```php
it('builds the AI translator on the translation choice', function (): void {
    config()->set('ai.features.translation.model', 'ollama:phi3');

    $service = new TranslationService;
    $inner = (new ReflectionProperty($service, 'service'))->getValue($service);

    expect($inner)->toBeInstanceOf(Modules\AI\Services\Translation\AiTranslationService::class)
        ->and((new ReflectionProperty($inner, 'provider'))->getValue($inner))->toBe('ollama')
        ->and((new ReflectionProperty($inner, 'model'))->getValue($inner))->toBe('phi3');
});

it('propagates a provider failure and caches nothing', function (): void {
    config()->set('ai.features.translation.model', 'deepl');
    config()->set('core.deepl_api_key', 'test-key');
    config()->set('core.translations.cache.enabled', true);
    Cache::flush();

    Http::fake(['https://api-free.deepl.com/v2/translate' => Http::sequence()
        ->push(null, 500)
        ->push(['translations' => [['text' => 'Tradotto']]], 200)]);

    expect(fn (): string => (new TranslationService)->translate('original text', 'en', 'it'))->toThrow(Exception::class);

    expect((new TranslationService)->translate('original text', 'en', 'it'))->toBe('Tradotto');
});
```

`AiTranslationServiceTest.php`: delete the `beforeEach` that sets `ai.features.translation.default_provider`, every other `config()->set('ai.features.translation.default_provider', ...)` line, the test "resolves an unknown provider name to the configured default" and the test "resolveProvider maps known providers correctly" (the method goes). Add:

```php
it('hands its provider to the agent factory', function (): void {
    $received_provider = 'untouched';
    $handler = Mockery::mock(NeuronAI\Agent\AgentHandler::class);
    $handler->shouldReceive('getMessage')->andReturn(new NeuronAI\Chat\Messages\AssistantMessage('Ciao'));
    $agent = Mockery::mock(ChatAgent::class);
    $agent->shouldReceive('chat')->andReturn($handler);

    $service = new AiTranslationService(
        chatAgentFactory: function (?string $provider) use (&$received_provider, $agent): ChatAgent {
            $received_provider = $provider;

            return $agent;
        },
        provider: 'mistral',
        model: 'mistral-large-latest',
    );

    expect($service->translate('hello', 'en', 'it'))->toBe('Ciao')
        ->and($received_provider)->toBe('mistral');
});
```

`TranslateModelJobTest.php`, add:

```php
it('translates the other locales and rethrows the first failure', function (): void {
    $defaultTranslation = Mockery::mock(Model::class)->makePartial();
    $defaultTranslation->title = 'Hello';

    $model = new TranslateModelJobStub;
    $model->defaultTranslation = $defaultTranslation;
    $model->hasTranslationResult = false;
    TranslateModelJobStub::$translatableFields = ['title'];

    $translationService = Mockery::mock(TranslationService::class);
    $translationService->shouldReceive('translate')->with('Hello', 'en', 'fr')->andThrow(new RuntimeException('DeepL down'));
    $translationService->shouldReceive('translate')->with('Hello', 'en', 'it')->andReturn('Ciao');

    $job = new TranslateModelJob($model, ['fr', 'it'], true);

    expect(fn () => $job->handle($translationService))->toThrow(RuntimeException::class, 'DeepL down');

    expect($model->setTranslationCalls)->toHaveCount(1)
        ->and($model->setTranslationCalls[0]['locale'])->toBe('it');
});
```

`ApplicationSettingDefinitionsTest.php`: in "defines core runtime settings with current defaults and choices", replace the two `translations.provider` expectations with `->and($definitions->has('translations.provider'))->toBeFalse()->and($definitions->has('translations.fallback_to_ai'))->toBeFalse()`.

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/AI/tests/Integration/TranslationServiceCoreTest.php Modules/AI/tests/Integration/AiTranslationServiceTest.php Modules/AI/tests/Integration/TranslateModelJobTest.php Modules/Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php`
Expected: FAIL.

- [x] **Step 3: Rewrite `TranslationService`**

Replace the properties, constructor and `performTranslation()`; keep `translate()`, `translateBatch()` and `getCacheKey()`:

```php
    private readonly TranslationServiceInterface $service;

    private readonly bool $cache_enabled;

    /**
     * In-memory cache for translations during the request.
     *
     * @var array<string, string>
     */
    private array $memory_cache = [];

    /**
     * DeepL or an AI model, as chosen in Settings. No fallback: a failure propagates, so
     * nothing is cached and the queued translation is retried.
     */
    public function __construct()
    {
        $choice = AiModelChoice::forFeature(AiModelFeature::Translation);
        $this->cache_enabled = ai_config_bool('core.translations.cache.enabled', true);

        $this->service = $choice->provider === 'deepl'
            ? new DeepLTranslationService()
            : new AiTranslationService(provider: $choice->provider, model: $choice->model);
    }
```

```php
    private function performTranslation(string $text, string $from_locale, string $to_locale): string
    {
        return $this->service->translate($text, $from_locale, $to_locale);
    }
```

Drop the `fallback_service` property, the `Exception` / `Log` / `ai_config_string` imports if unused; add `Modules\AI\Ai\Providers\AiModelChoice` and `Modules\AI\Enums\AiModelFeature`. `Cache::remember()` stores nothing when its callback throws.

- [x] **Step 4: Rewrite `AiTranslationService`**

Constructor:

```php
    public function __construct(
        private ?Closure $chatAgentFactory = null,
        private ?string $provider = null,
        private ?string $model = null,
    ) {}
```

In `translate()`, delete `$provider = ai_config_nullable_string(...)`, build the agent with `$agent = $this->makeChatAgent();` and log `'provider' => $this->provider`. Delete `resolveProvider()`. Replace `makeChatAgent()`:

```php
    private function makeChatAgent(): ChatAgent
    {
        if ($this->chatAgentFactory instanceof Closure) {
            return ($this->chatAgentFactory)($this->provider);
        }

        if ($this->provider === null) {
            return ChatAgent::forFeature(AiModelFeature::Translation, self::SYSTEM_PROMPT);
        }

        /** @var ChatAgent */
        return ChatAgent::make(
            providerName: $this->provider,
            systemPrompt: self::SYSTEM_PROMPT,
            model: $this->model,
        );
    }
```

- [x] **Step 5: Rethrow from `TranslateModelJob`**

In `handle()`, replace the locale loop and the completion event with:

```php
        $failure = null;

        foreach ($locales_to_translate as $locale) {
            if (! $this->force && $model->hasTranslation($locale)) {
                continue;
            }

            try {
                $this->translateModel($model, $default_translation, $locale, $translation_service);
            } catch (Exception $e) {
                Log::error('Translation failed for model', [
                    'model_class' => $model::class,
                    'model_id' => $model->getKey(),
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);

                $failure ??= $e;
            }
        }

        // Indexing waits for this event: send it when every locale is done, or when the job
        // gives up, so a failing provider cannot hold indexing back for good.
        if (class_uses_trait($model, Searchable::class) && ($failure === null || $this->attempts() >= $this->tries)) {
            event(new ModelPreProcessingCompleted($model, 'translation'));
        }

        if ($failure !== null) {
            throw $failure;
        }
```

Keep the `event(...)` argument exactly as the current code passes it; only the condition around it changes.

- [x] **Step 6: Remove the Core settings and the AI config key**

`CoreDatabaseSeeder::runtimeSettingDefinitions()`: delete the `translations.fallback_to_ai` and `translations.provider` rows. `Modules/AI/config/config.php`: delete `translation.default_provider`.

- [x] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/AI/tests/Integration/TranslationServiceCoreTest.php Modules/AI/tests/Integration/AiTranslationServiceTest.php Modules/AI/tests/Integration/TranslateModelJobTest.php Modules/AI/tests/Integration/Jobs/TranslateModelJobCommentSourceTest.php Modules/AI/tests/Integration/DeepLTranslationServiceTest.php Modules/Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php`
Expected: PASS.

- [x] **Step 8: Check no old key is left**

Run: `rtk proxy grep -rnE "translations\.(provider|fallback_to_ai)|translation\.default_provider|fallback_service" Modules --include=*.php`
Expected: no output.

- [x] **Step 9: Format and commit (two repositories)**

```bash
vendor/bin/pint --format agent Modules/AI/app/Services/Translation/TranslationService.php Modules/AI/app/Services/Translation/AiTranslationService.php Modules/AI/app/Jobs/TranslateModelJob.php Modules/AI/config/config.php Modules/AI/tests/Integration/TranslationServiceCoreTest.php Modules/AI/tests/Integration/AiTranslationServiceTest.php Modules/AI/tests/Integration/TranslateModelJobTest.php Modules/Core/database/seeders/CoreDatabaseSeeder.php Modules/Core/tests/Unit/Settings/ApplicationSettingDefinitionsTest.php
git -C Modules/AI add app/Services/Translation app/Jobs/TranslateModelJob.php config/config.php tests/Integration/TranslationServiceCoreTest.php tests/Integration/AiTranslationServiceTest.php tests/Integration/TranslateModelJobTest.php
git -C Modules/AI commit -m "feat(ai): translation picks DeepL or an AI model, with no fallback; failures retry" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git -C Modules/Core add database/seeders/CoreDatabaseSeeder.php tests/Unit/Settings/ApplicationSettingDefinitionsTest.php
git -C Modules/Core commit -m "chore(settings): drop translation provider settings now owned by AI" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 15: Documentation, full run, closing

**Files:**
- Create: `Modules/Core/docs/rag/SETTING_ACTIONS_USER.md`
- Create: `Modules/Core/docs/rag/SETTING_ACTIONS_DEVELOPER.md`
- Create: `Modules/AI/docs/rag/AI_MODEL_SELECTION_USER.md`
- Create: `Modules/AI/docs/rag/AI_MODEL_SELECTION_DEVELOPER.md`
- Modify: `Modules/AI/README.md` (Configuration paragraph and env block)
- Modify: `Modules/AI/docs/SEARCH_AND_TRANSLATION.md` (sections "2. Automatic translation → Configuration" and "4. Fallback behaviour")
- Modify: this plan (delivery status), the spec (status), `docs/superpowers/specs/INDEX.md`, `docs/superpowers/plans/INDEX.md`

- [x] **Step 1: Write the Core user page**

`SETTING_ACTIONS_USER.md`, with the same front matter as `RECORD_LOCKING_USER.md` (`module: core`, `audience: user`, `cross_cutting_user: true`). Content, in English:
  - some settings show a play icon in the Settings grid. It runs the command the setting was shipped with, for example refreshing the list of AI models;
  - the tooltip shows the command;
  - the button appears only for users who may update settings;
  - it runs at once, without confirmation, and a notification shows the outcome and the end of the output ("Command completed", "Command failed", "Action refused", "Command queued");
  - nobody can change the command from the panel;
  - a value shown with a warning icon is no longer among the available choices. It stays saved, and the edit form still offers it, labelled "(no longer available)".

- [x] **Step 2: Write the Core developer page**

`SETTING_ACTIONS_DEVELOPER.md`:
  - the two columns and why they are outside `$fillable` and inside `$hidden`;
  - declaring an action in a seeder row (`action_command`, `action_queued`);
  - both columns are realigned on every seed;
  - placeholder syntax `{attribute}`, quoting (one token each, Symfony `StringInput`), the refusals (unknown placeholder, `{value}` on an encrypted setting, unregistered command);
  - sync versus queued (`Artisan::call` inside the request, with the user's approval rules applying to what the command writes; `Artisan::queue` from console, where approvals do not apply);
  - `commandManagedChoicesSettingsDefinition()` and why `choices` is exempt from approval;
  - `Setting::isValueOutsideChoices()`;
  - the `JobProcessing` overlay listener (why queue workers now see setting changes without a restart).

- [x] **Step 3: Write the AI pages**

`AI_MODEL_SELECTION_USER.md` (front matter `module: ai`, `audience: user`):
  - the list of AI features with their setting names;
  - the `provider:model` format, and `deepl` / `whisper` alone;
  - the refresh button and the nightly refresh;
  - a provider without an API key or URL does not appear, and one that is down keeps its previous models;
  - a model no longer offered is flagged, not replaced;
  - translation picks DeepL or an AI model and has no fallback: a failed translation is retried by the queue instead of saving the original text.

`AI_MODEL_SELECTION_DEVELOPER.md`:
  - `AiModelFeature`, `AiModelChoice::forFeature()`, `ChatAgent::forFeature()`, and `ProviderFactory::make()` without a provider;
  - the listers and the endpoints they call (the verified table of the plan's Task 9);
  - capability mapping per provider;
  - `ModelCatalog` rules;
  - `ai:models:refresh` (options, exit codes, output, schedule at 03:00);
  - defaults in `AiModelFeature::defaultChoice()`: no config entry and no env variable for a value managed by a setting, and why (the seeder writes it once);
  - how to add a feature (enum case with its default choice, consumer calls `forFeature()`).

- [x] **Step 4: Update the README and translation doc**

`Modules/AI/README.md`:
  - **Configuration paragraph:** add to the list of runtime settings each feature's model `features.*.model` (refreshed by `ai:models:refresh`) and `features.media_analysis.enabled`.
  - **Env block:**
    - delete `AI_CHAT_PROVIDER`, `AI_TEXT_GENERATION_PROVIDER`, `AI_TEXT_GENERATION_MODEL`, `AI_MODERATION_PROVIDER`, `AI_SEARCH_ORCHESTRATION_PROVIDER`, `AI_TRANSLATION_PROVIDER`, `ANTHROPIC_MODEL` and any `AI_MEDIA_*` model or switch variable;
    - describe `OPENAI_MODEL`, `OLLAMA_MODEL`, `MISTRAL_MODEL` as the model used by embeddings with that provider (AI features take their model from Settings);
    - add a short "Removed" note listing the deleted variables and pointing to Settings.

`SEARCH_AND_TRANSLATION.md`:
  - **"Configuration" under section 2:** the provider is the `features.translation.model` setting (`deepl` or `provider:model`); `DEEPL_API_KEY` configures DeepL.
  - **"4. Fallback behaviour" table:** add a row "Translation provider fails: nothing is saved or cached for that locale; the job retries (3 tries, backoff 30/60/120 s); indexing proceeds when the job succeeds or gives up".

- [x] **Step 5: Run the full suites of both modules and the root checks**

Run: `php artisan test --compact Modules/Core/tests Modules/AI/tests tests/Unit/ClosedPlansPointToDocumentationTest.php`
Expected: PASS. Then ask the user to run the complete suite with `php artisan test --compact`.

- [x] **Step 6: Rebuild the developer database and smoke the command**

Run: `php artisan migrate:fresh --seed` then `php artisan ai:models:refresh`
Expected: the migration and seed succeed; the command prints one line per provider (configured or not) and one per setting. A non-zero exit here only means a configured provider was unreachable on this machine; read the output.

- [x] **Step 7: Commit the module docs**

```bash
git -C Modules/Core add docs/rag/SETTING_ACTIONS_USER.md docs/rag/SETTING_ACTIONS_DEVELOPER.md
git -C Modules/Core commit -m "docs(settings): setting actions and command-managed choices" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
git -C Modules/AI add docs/rag/AI_MODEL_SELECTION_USER.md docs/rag/AI_MODEL_SELECTION_DEVELOPER.md README.md docs/SEARCH_AND_TRANSLATION.md
git -C Modules/AI commit -m "docs(ai): per-feature model selection and translation without fallback" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [x] **Step 8: Close the plan and the spec**

- **Plan:** add below the header of this plan a `## Delivery status (YYYY-MM-DD): shipped` section. It gets a `**Documented in:**` line naming the four `docs/rag` pages, `Modules/AI/README.md` and `Modules/AI/docs/SEARCH_AND_TRANSLATION.md`, and it lists every divergence from these steps.
- **Spec:** set its status to "Implemented YYYY-MM-DD by `docs/superpowers/plans/2026-09-29-ai-model-selection-and-setting-actions.md`".
- **Media analysis spec and plan:** in both amendment notes dated 2026-09-29 (M18/M21 in the spec, Task 1 in the plan), replace "planned in" with "delivered on YYYY-MM-DD by" Task 13 of this plan.
- **Specs index:** update the entry in `docs/superpowers/specs/INDEX.md`.
- **Plans index:** mark the plan's entry in `docs/superpowers/plans/INDEX.md` as shipped, in the style of its neighbours.

- [x] **Step 9: Commit the root repository**

```bash
git add Modules/Core Modules/AI docs/superpowers/specs/2026-09-29-ai-model-selection-and-setting-actions-design.md docs/superpowers/specs/INDEX.md docs/superpowers/plans/2026-09-29-ai-model-selection-and-setting-actions.md docs/superpowers/plans/INDEX.md
git commit -m "feat: AI model selection per feature and setting actions" -m "Bumps Core and AI; adds the spec and the plan." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
