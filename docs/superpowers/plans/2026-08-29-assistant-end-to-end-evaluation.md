# Assistant End-to-End Evaluation (R1b) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the composed in-app assistant a per-module, deterministic evaluation "report card": a dataset of cases run through the real `InAppAssistanceService::respond()` with a scripted router, scoring composition-plumbing metrics behind a CI regression gate.

**Architecture:** Mirror the R0 documentation-evaluation harness (`Modules/AI/app/Services/Documentation/Evaluation/*`). Value objects `AssistantEvaluationCase`/`AssistantEvaluationDataset`; a scoring `AssistantEvaluationService::evaluate(dataset, runner)` that takes a `fn(AssistantEvaluationCase): Message` runner and computes Level-1 metrics from the returned `Message`; a deterministic scripted-runner fixture that builds `respond()` with fake providers and a `completion` closure that invokes the case's `expected_surface` tool (reusing the exact scaffolding in `Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php`); a first-module dataset plus a Pest baseline gate.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, NeuronAI `Tool`/`Message`. No live LLM or Elasticsearch anywhere in this plan.

**Spec:** `docs/superpowers/specs/2026-08-29-assistant-end-to-end-evaluation-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Braces on all control structures; explicit param + return types; `final`/`readonly` per sibling style.
- No new dependencies, no new base folders. Chat Italian, code/docs English.
- Never declare classes inside test files; test-support goes under `Modules/AI/tests/Stubs/` (namespace `Modules\AI\Tests\Stubs\`, PSR-4 registered). Run `composer dump-autoload -o` after adding a Stubs class.
- Pest tests; **no live LLM, no live Elasticsearch**. The deterministic Level-1 harness reuses the existing test scaffolding: `Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php` (helpers `inAppContentService`, `executeInAppContentTool`, `inAppContentDescriptor`, `inAppContentResult`) and the stub `Modules\AI\Tests\Stubs\ApplicationContent\InAppAssistanceContentProvider`, plus the R0 documentation fixtures.
- The unit is the **module**; datasets live at `Modules/{Module}/docs/rag/evaluations/assistant-{slug}.json`.
- Reports contain only aggregate floats + slugged slice keys — never raw query text, citations, or record content. `data_classification` restricted to `synthetic`.
- **Scope refinement discovered while planning (noted for the reviewer):** the spec lists `ai:evaluate-assistant (Level 1)`. A production command cannot run a *deterministic* Level 1, because the real `respond()` completion IS the live LLM. Therefore Level 1's deliverable is the **deterministic gate test** (like R0's `DocumentationBaselineGateTest`), and the `ai:evaluate-assistant` command + `--live` mode move to the **Level-2 (deferred)** contract. This plan builds the deterministic harness + gate; it does NOT build the command. Every other spec requirement is unchanged.
- Format with `vendor/bin/pint` on the specific changed files (not `--dirty` from repo root — it misses submodule-internal files). Commit inside the `Modules/AI` submodule (branch `master`); the first-module dataset commits inside that module's submodule. Do not commit at the laraplate root; touch nothing else.

**Verified code facts (touch points):**
- `InAppAssistanceService::respond(Conversation $conversation, User $authenticated_user, string $user_input, ?array $request_context = null): Message`. Constructor (order): `AssistantAccessContextFactory, AssistantPolicyCompiler, AssistanceGuardrailPipeline, DocumentationService, ContextualToolProviderInterface, ToolRegistry, ChatService, Request, AssistantScopeResolver, ?Closure $documentation_retrieval = null, ?Closure $completion = null, ?ApplicationContentCitationMapper $application_content_citations = null`.
- The injected completion: `Closure(string $input, string $systemPrompt, AssistantPromptContext $context, list<Tool> $tools): string`.
- `respond()` outcomes on the returned `Message`: normal answer → `metadata['citations']` set; refusal → `metadata['refused'] === true`; clarification → content equals `AssistanceGuardrailPipeline::defaults()->clarificationRequired($locale)`; insufficient evidence (abstention) → content equals `...->insufficientEvidence($locale)`.
- A NeuronAI `Tool` is invoked programmatically with `$tool->setInputs([...])->execute()` (see `executeInAppContentTool`); the application-content tool name is `application_content_search`.
- `ApplicationContentCitationMapper` state: `attempted()`, `hasEvidence()`, `clarificationRequired()`, `citations()`, `results()`, `reset()`.
- R0 mirror templates: `Modules/AI/app/Services/Documentation/Evaluation/DocumentationEvaluationCase.php`, `DocumentationEvaluationDataset.php`, `DocumentationEvaluationService.php` — READ these before Tasks 1/2/4; copy their structure, validation idioms, exact-key assertions, ratio/percentile/slice helpers.

---

### Task 1: `AssistantEvaluationCase` value object

**Files:**
- Create: `Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationCase.php`
- Test: `Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationCaseTest.php`

**Interfaces:**
- Produces: `final readonly class AssistantEvaluationCase` with public props `string $id, string $query, string $locale, ?string $moduleKey, string $expectedSurface, list<string> $expectedCitations, bool $expectClarification, bool $expectRefusal, list<string> $slices`. `expectedSurface` ∈ `{documentation, application_content, graph, clarify, refuse}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationCase;

function makeAssistantCase(array $o = []): AssistantEvaluationCase
{
    return new AssistantEvaluationCase(
        id: $o['id'] ?? 'c1',
        query: $o['query'] ?? 'how do I publish content?',
        locale: $o['locale'] ?? 'en',
        moduleKey: $o['moduleKey'] ?? 'cms',
        expectedSurface: $o['expectedSurface'] ?? 'application_content',
        expectedCitations: $o['expectedCitations'] ?? ['Publishing guide'],
        expectClarification: $o['expectClarification'] ?? false,
        expectRefusal: $o['expectRefusal'] ?? false,
        slices: $o['slices'] ?? ['publishing', 'single_hop'],
    );
}

it('builds a valid application_content case', function (): void {
    $c = makeAssistantCase();
    expect($c->expectedSurface)->toBe('application_content')->and($c->moduleKey)->toBe('cms');
});

it('allows a null moduleKey (generic scope)', function (): void {
    expect(makeAssistantCase(['moduleKey' => null, 'expectedSurface' => 'documentation'])->moduleKey)->toBeNull();
});

it('rejects an unknown surface', function (): void {
    expect(fn () => makeAssistantCase(['expectedSurface' => 'sql']))->toThrow(InvalidArgumentException::class);
});

it('requires clarify surface to set expectClarification and carry no citations', function (): void {
    expect(fn () => makeAssistantCase(['expectedSurface' => 'clarify', 'expectClarification' => false]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => makeAssistantCase(['expectedSurface' => 'clarify', 'expectClarification' => true, 'expectedCitations' => ['x']]))
        ->toThrow(InvalidArgumentException::class);
    expect(makeAssistantCase(['expectedSurface' => 'clarify', 'expectClarification' => true, 'expectedCitations' => []])->expectClarification)->toBeTrue();
});

it('requires refuse surface to set expectRefusal and carry no citations', function (): void {
    expect(fn () => makeAssistantCase(['expectedSurface' => 'refuse', 'expectRefusal' => false]))
        ->toThrow(InvalidArgumentException::class);
    expect(makeAssistantCase(['expectedSurface' => 'refuse', 'expectRefusal' => true, 'expectedCitations' => []])->expectRefusal)->toBeTrue();
});

it('rejects a malformed id, locale, module key, or slice slug', function (): void {
    expect(fn () => makeAssistantCase(['id' => 'Bad Id']))->toThrow(InvalidArgumentException::class);
    expect(fn () => makeAssistantCase(['locale' => 'english']))->toThrow(InvalidArgumentException::class);
    expect(fn () => makeAssistantCase(['moduleKey' => 'Bad Mod']))->toThrow(InvalidArgumentException::class);
    expect(fn () => makeAssistantCase(['slices' => ['Not A Slug']]))->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationCaseTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\Assistance\Evaluation;

use InvalidArgumentException;

final readonly class AssistantEvaluationCase
{
    private const array SURFACES = ['documentation', 'application_content', 'graph', 'clarify', 'refuse'];

    /**
     * @param  list<string>  $expectedCitations
     * @param  list<string>  $slices
     */
    public function __construct(
        public string $id,
        public string $query,
        public string $locale,
        public ?string $moduleKey,
        public string $expectedSurface,
        public array $expectedCitations,
        public bool $expectClarification,
        public bool $expectRefusal,
        public array $slices,
    ) {
        $no_citations = $this->expectedCitations === [];

        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $this->id) !== 1
            || mb_trim($this->query) === ''
            || mb_strlen($this->query) > 2000
            || preg_match('/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/', $this->locale) !== 1
            || ($this->moduleKey !== null && preg_match('/^[a-z][a-z0-9_]*$/', $this->moduleKey) !== 1)
            || ! in_array($this->expectedSurface, self::SURFACES, true)
            || ! $this->validList($this->expectedCitations, 500)
            || ! $this->validSlugList($this->slices, 63)
            || ($this->expectedSurface === 'clarify' && (! $this->expectClarification || ! $no_citations))
            || ($this->expectedSurface === 'refuse' && (! $this->expectRefusal || ! $no_citations))
            || ($this->expectClarification && $this->expectRefusal)
            || (($this->expectClarification && $this->expectedSurface !== 'clarify'))
            || (($this->expectRefusal && $this->expectedSurface !== 'refuse'))) {
            throw new InvalidArgumentException('Assistant evaluation case is invalid.');
        }
    }

    /**
     * @param  array<mixed>  $values
     */
    private function validList(array $values, int $maximumLength): bool
    {
        if (! array_is_list($values) || count(array_unique($values)) !== count($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || mb_trim($value) === '' || $maximumLength < mb_strlen($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function validSlugList(array $values, int $maximumLength): bool
    {
        if (! $this->validList($values, $maximumLength)) {
            return false;
        }

        foreach ($values as $value) {
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $value) !== 1) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationCaseTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationCase.php Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationCaseTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/Evaluation/AssistantEvaluationCase.php tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationCaseTest.php
git commit -m "feat(ai): assistant evaluation case value object"
```

---

### Task 2: `AssistantEvaluationDataset` loader

**Files:**
- Create: `Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationDataset.php`
- Test: `Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationDatasetTest.php`

**Interfaces:**
- Consumes: `AssistantEvaluationCase` (Task 1).
- Produces: `final readonly class AssistantEvaluationDataset` with public props `string $version, string $corpusRevision, string $module, string $dataClassification, list<AssistantEvaluationCase> $cases`; static `fromFile(string $path): self` and `fromArray(array $data): self`. Mirror `DocumentationEvaluationDataset` exactly (exact-key assertions, bounded sizes, typed extractors, `data_classification` must equal `synthetic`, module matches `^[a-z][a-z0-9_]*$`, non-empty unique case ids, ≤1000 cases).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationDataset;

function assistantDatasetArray(array $o = []): array
{
    return array_replace([
        'version' => '1',
        'corpus_revision' => 'cms-1',
        'module' => 'cms',
        'data_classification' => 'synthetic',
        'cases' => [[
            'id' => 'c1', 'query' => 'how do I publish content?', 'locale' => 'en',
            'module_key' => 'cms', 'expected_surface' => 'application_content',
            'expected_citations' => ['Publishing guide'],
            'expect_clarification' => false, 'expect_refusal' => false,
            'slices' => ['publishing'],
        ]],
    ], $o);
}

it('builds a dataset from a valid array', function (): void {
    $d = AssistantEvaluationDataset::fromArray(assistantDatasetArray());
    expect($d->module)->toBe('cms')->and($d->cases)->toHaveCount(1)->and($d->cases[0]->id)->toBe('c1');
});

it('rejects an unknown top-level key', function (): void {
    expect(fn () => AssistantEvaluationDataset::fromArray(assistantDatasetArray(['x' => 1])))->toThrow(InvalidArgumentException::class);
});

it('rejects a non-synthetic classification', function (): void {
    expect(fn () => AssistantEvaluationDataset::fromArray(assistantDatasetArray(['data_classification' => 'live'])))->toThrow(InvalidArgumentException::class);
});

it('rejects an empty case list', function (): void {
    expect(fn () => AssistantEvaluationDataset::fromArray(assistantDatasetArray(['cases' => []])))->toThrow(InvalidArgumentException::class);
});

it('accepts a null module_key case', function (): void {
    $arr = assistantDatasetArray();
    $arr['cases'][0]['module_key'] = null;
    $arr['cases'][0]['expected_surface'] = 'documentation';
    expect(AssistantEvaluationDataset::fromArray($arr)->cases[0]->moduleKey)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationDatasetTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Write minimal implementation**

Read `Modules/AI/app/Services/Documentation/Evaluation/DocumentationEvaluationDataset.php` and mirror it. The dataset-level exact keys are `cases, corpus_revision, data_classification, module, version`. The case-level exact keys are `expect_clarification, expect_refusal, expected_citations, expected_surface, id, locale, module_key, query, slices`. `module_key` is string-or-null (mirror the `tenant_id` string-or-null handling in the documentation dataset). Build each case with:

```php
new AssistantEvaluationCase(
    id: self::string($data, 'id'),
    query: self::string($data, 'query'),
    locale: self::string($data, 'locale'),
    moduleKey: $module_key,               // string|null, validated as in the documentation dataset's tenant_id
    expectedSurface: self::string($data, 'expected_surface'),
    expectedCitations: self::stringList($data, 'expected_citations'),
    expectClarification: self::boolean($data, 'expect_clarification'),
    expectRefusal: self::boolean($data, 'expect_refusal'),
    slices: self::stringList($data, 'slices'),
);
```

Copy the constructor invariants (`validRevision` on version/corpus_revision; module pattern; unique non-empty ids; ≤1000 cases; `data_classification === 'synthetic'`), `fromFile`/`fromArray`, `assertExactKeys`, and the `string`/`integer`/`boolean`/`stringList` helpers verbatim from the documentation dataset, adjusting the key sets and the case construction only.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationDatasetTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationDataset.php Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationDatasetTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/Evaluation/AssistantEvaluationDataset.php tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationDatasetTest.php
git commit -m "feat(ai): assistant evaluation dataset loader"
```

---

### Task 3: `AssistantEvaluationOutcome` reader + `AssistantEvaluationService` scoring

**Files:**
- Create: `Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationService.php`
- Test: `Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationServiceTest.php`

**Interfaces:**
- Consumes: `AssistantEvaluationDataset`, `AssistantEvaluationCase` (Tasks 1–2); NeuronAI `Message`.
- Produces: `final readonly class AssistantEvaluationService` with `evaluate(AssistantEvaluationDataset $dataset, string $mode, callable $runner): array`, where `$runner` is `fn(AssistantEvaluationCase $case): Message`. Report keys: `schema_version, module, mode, dataset_version, corpus_revision, data_classification, case_count, metrics, latency_ms, slices`. Metric keys: `citation_assembly, clarification_trigger_accuracy, abstention_accuracy, output_valid, unavailable_rate`. (`surface_offered` is asserted by the runner/fixture in Task 4, not scored here — see note.) The service reads each `Message`: `metadata['refused'] === true` → refused; `metadata['citations']` (a list of `['label' => ...]`) → returned citation labels.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationDataset;
use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationService;
use NeuronAI\Chat\Messages\AssistantMessage;

function assistantMessage(string $content, array $metadata): AssistantMessage
{
    $m = new AssistantMessage($content);
    $m->setMeta($metadata); // adjust to the Message API confirmed in Task 3 Step 3
    return $m;
}

it('scores citation assembly and refusal correctly', function (): void {
    $dataset = AssistantEvaluationDataset::fromArray(assistantDatasetArray([
        'cases' => [
            ['id' => 'hit', 'query' => 'q', 'locale' => 'en', 'module_key' => 'cms',
             'expected_surface' => 'application_content', 'expected_citations' => ['Publishing guide'],
             'expect_clarification' => false, 'expect_refusal' => false, 'slices' => ['publishing']],
            ['id' => 'refuse', 'query' => 'weather?', 'locale' => 'en', 'module_key' => 'cms',
             'expected_surface' => 'refuse', 'expected_citations' => [],
             'expect_clarification' => false, 'expect_refusal' => true, 'slices' => ['off_topic']],
        ],
    ]));

    $runner = static function ($case) {
        if ($case->id === 'refuse') {
            return assistantMessage('...', ['refused' => true]);
        }
        return assistantMessage('answer', ['citations' => [['label' => 'Publishing guide']]]);
    };

    $report = (new AssistantEvaluationService)->evaluate($dataset, 'level1', $runner);

    expect($report['metrics']['citation_assembly'])->toBe(1.0)
        ->and($report['metrics']['abstention_accuracy'])->toBe(1.0)
        ->and($report['module'])->toBe('cms')
        ->and($report['mode'])->toBe('level1')
        ->and(json_encode($report))->not->toContain('weather?');
});

it('counts a runner exception as unavailable', function (): void {
    $dataset = AssistantEvaluationDataset::fromArray(assistantDatasetArray());
    $report = (new AssistantEvaluationService)->evaluate($dataset, 'level1', static function (): never {
        throw new RuntimeException('down');
    });
    expect($report['metrics']['unavailable_rate'])->toBe(1.0);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationServiceTest.php`
Expected: FAIL (service not found).

- [ ] **Step 3: Write minimal implementation**

First confirm the NeuronAI `Message` metadata API (`AssistantMessage`): read how `respond()`/`Conversation::addMessage` stores and how a `Message` exposes metadata (grep `Modules/AI/app/Models/Message.php` and the NeuronAI message class). Use the confirmed getter (e.g. `$message->getMetadata()` or the `Message` model's `metadata` attribute) consistently in both the test helper and the service. Then implement, mirroring `DocumentationEvaluationService` (same `ratio`/`percentile`/`rounded`/`slices` helpers, `Throwable` → unavailable, `hrtime` clock):

Metric definitions:
- `citation_assembly`: over cases with non-empty `expectedCitations`, the returned citation labels set equals (or contains) the expected set. Score = matched / relevant.
- `clarification_trigger_accuracy`: over `expect_clarification` cases, the message content equals `AssistanceGuardrailPipeline::defaults()->clarificationRequired($case->locale)`.
- `abstention_accuracy`: over `expect_refusal` cases, `metadata['refused'] === true` OR content equals `...->insufficientEvidence($case->locale)`.
- `output_valid`: fraction of non-unavailable cases whose message is non-empty and not the generic hard-refusal when a supported answer was expected.
- `unavailable_rate`: runner threw / total.

Slices by `locale` and by slice tag, mirroring R0.

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationService.php Modules/AI/tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationServiceTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/Evaluation/AssistantEvaluationService.php tests/Unit/Services/Assistance/Evaluation/AssistantEvaluationServiceTest.php
git commit -m "feat(ai): assistant evaluation scoring service"
```

---

### Task 4: Scripted-runner fixture + surface offering assertion

**Files:**
- Create: `Modules/AI/tests/Stubs/Assistance/ScriptedAssistantRunner.php`
- Test: `Modules/AI/tests/Feature/Assistance/ScriptedAssistantRunnerTest.php`

**Interfaces:**
- Consumes: `AssistantEvaluationCase` (Task 1); the existing scaffolding in `Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php` (`inAppContentService`, `executeInAppContentTool`, `inAppContentDescriptor`, `inAppContentResult`) and `Modules\AI\Tests\Stubs\ApplicationContent\InAppAssistanceContentProvider`.
- Produces: `Modules\AI\Tests\Stubs\Assistance\ScriptedAssistantRunner` — a class holding the authenticated `User`, `Conversation`, and `Request`, exposing `run(AssistantEvaluationCase $case): Message`. Internally it builds `InAppAssistanceService` (via the same collaborators as `inAppContentService`) with: the R0 documentation fixture as `documentation_retrieval`; a fake application-content provider whose result matches the case's `expectedCitations`; and a scripted `completion` closure that, for `expected_surface = application_content`, finds the `application_content_search` tool in `$tools`, calls `setInputs([...])->execute()`, and returns a scripted answer; for `documentation` returns a scripted answer using the already-retrieved docs context; for `clarify`/`refuse` invokes nothing (so `respond()`'s clarification/abstention paths fire, driven by the fake provider's ambiguity/empty result). It sets the request's `assistant_application_context` to `['module' => $case->moduleKey]` when non-null so R1a resolves the module scope.

Because the helper `inAppContentService` currently lives inside the test file, **first extract the reusable parts** (`inAppContentService`, `executeInAppContentTool`, `inAppContentDescriptor`, `inAppContentResult`) into the Stub or a shared test-support file so both the existing test and this runner use one copy — do NOT duplicate the logic. Confirm the existing `InAppApplicationContentAssistanceTest` still passes after the extraction.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationCase;
use Modules\AI\Tests\Stubs\Assistance\ScriptedAssistantRunner;

uses(RefreshDatabase::class);

it('drives respond() to an application_content answer with the expected citation', function (): void {
    $runner = ScriptedAssistantRunner::bootstrap(); // creates superadmin user, conversation, request, login
    $message = $runner->run(new AssistantEvaluationCase(
        id: 'hit', query: 'how do I publish?', locale: 'en', moduleKey: 'cms',
        expectedSurface: 'application_content', expectedCitations: ['Publishing guide'],
        expectClarification: false, expectRefusal: false, slices: ['publishing'],
    ));

    $labels = array_map(static fn (array $c): string => $c['label'], $message->/* confirmed metadata getter */getMeta('citations') ?? []);
    expect($labels)->toContain('Publishing guide');
});

it('drives respond() to a refusal for a refuse case (empty evidence)', function (): void {
    $runner = ScriptedAssistantRunner::bootstrap();
    $message = $runner->run(new AssistantEvaluationCase(
        id: 'refuse', query: 'weather?', locale: 'en', moduleKey: 'cms',
        expectedSurface: 'refuse', expectedCitations: [],
        expectClarification: false, expectRefusal: true, slices: ['off_topic'],
    ));
    // refusal OR insufficient-evidence output; assert via the confirmed markers used in Task 3.
    expect($message)->not->toBeNull();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer dump-autoload -o && php artisan test --compact Modules/AI/tests/Feature/Assistance/ScriptedAssistantRunnerTest.php`
Expected: FAIL (runner not found).

- [ ] **Step 3: Write minimal implementation**

Build `ScriptedAssistantRunner` reusing the exact construction from `inAppContentService` (copy its body into the Stub as the canonical implementation and have the existing test call the Stub). Per-case: register an `InAppAssistanceContentProvider` fake whose `retrieve()` returns `inAppContentResult()` sized to the case (empty for `refuse`; ambiguous-source for `clarify`); set `request->attributes->set('assistant_application_context', ['module' => $case->moduleKey])` when non-null; pass a `completion` that switches on `$case->expectedSurface` and uses the `executeInAppContentTool`-style invocation for `application_content`. Return `respond($conversation, $user, $case->query)`.

`bootstrap()` performs the `beforeEach` setup (superadmin role + user + login + conversation + request), returning a configured runner.

- [ ] **Step 4: Run to verify it passes, and the extracted helper didn't break the sibling test**

Run:
```bash
php artisan test --compact Modules/AI/tests/Feature/Assistance/ScriptedAssistantRunnerTest.php Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php
```
Expected: PASS (runner green; the sibling test still green after the helper extraction).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/tests/Stubs/Assistance/ScriptedAssistantRunner.php Modules/AI/tests/Feature/Assistance/ScriptedAssistantRunnerTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add tests/Stubs/Assistance/ScriptedAssistantRunner.php tests/Feature/Assistance/ScriptedAssistantRunnerTest.php tests/Feature/InAppApplicationContentAssistanceTest.php
git commit -m "test(ai): scripted assistant runner fixture over real respond()"
```

---

### Task 5: First-module (CMS) assistant dataset + baseline gate

**Files:**
- Create: `Modules/CMS/docs/rag/evaluations/assistant-cms.json`
- Test: `Modules/AI/tests/Feature/Assistance/AssistantBaselineGateTest.php`

**Interfaces:**
- Consumes: `AssistantEvaluationDataset`, `AssistantEvaluationService` (Tasks 2–3), `ScriptedAssistantRunner` (Task 4).
- Produces: the CMS assistant dataset (a handful of cases: an `application_content` hit with a citation, a `documentation` case, a `clarify` case, a `refuse` case) and the gate test asserting committed Level-1 thresholds and `unavailable_rate === 0.0`.

- [ ] **Step 1: Write the CMS dataset** (`Modules/CMS/docs/rag/evaluations/assistant-cms.json`) with `module: "cms"`, `data_classification: "synthetic"`, and 4 cases whose `expected_citations` match what `ScriptedAssistantRunner`'s fake application-content provider returns (`Publishing guide`). Keep it small and aligned with the runner's fixtures.

- [ ] **Step 2: Write the gate test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationDataset;
use Modules\AI\Services\Assistance\Evaluation\AssistantEvaluationService;
use Modules\AI\Tests\Stubs\Assistance\ScriptedAssistantRunner;

uses(RefreshDatabase::class);

it('keeps the CMS assistant baseline at or above committed thresholds', function (): void {
    $path = base_path('Modules/CMS/docs/rag/evaluations/assistant-cms.json');
    $dataset = AssistantEvaluationDataset::fromFile($path);
    $runner = ScriptedAssistantRunner::bootstrap();

    $report = (new AssistantEvaluationService)->evaluate($dataset, 'level1', fn ($case) => $runner->run($case));

    expect($report['metrics']['citation_assembly'])->toBeGreaterThanOrEqual(1.0)
        ->and($report['metrics']['clarification_trigger_accuracy'])->toBeGreaterThanOrEqual(1.0)
        ->and($report['metrics']['abstention_accuracy'])->toBeGreaterThanOrEqual(1.0)
        ->and($report['metrics']['output_valid'])->toBeGreaterThanOrEqual(1.0)
        ->and($report['metrics']['unavailable_rate'])->toBe(0.0);
})->skip(fn (): bool => ! is_file(base_path('Modules/CMS/docs/rag/evaluations/assistant-cms.json')), 'CMS assistant dataset missing');
```

- [ ] **Step 3: Run the gate**

Run: `php artisan test --compact Modules/AI/tests/Feature/Assistance/AssistantBaselineGateTest.php`
Expected: PASS (thresholds met by the deterministic runner). If a metric is below 1.0, reconcile the dataset's expected values with what `ScriptedAssistantRunner` produces — do NOT weaken thresholds to force green.

- [ ] **Step 4: Commit (two submodules)**

```bash
cd /srv/http/laraplate-stack/laraplate/Modules/CMS
git add docs/rag/evaluations/assistant-cms.json
git commit -m "feat(cms): CMS assistant evaluation dataset"
cd /srv/http/laraplate-stack/laraplate/Modules/AI
vendor/bin/pint tests/Feature/Assistance/AssistantBaselineGateTest.php 2>/dev/null || (cd /srv/http/laraplate-stack/laraplate && vendor/bin/pint Modules/AI/tests/Feature/Assistance/AssistantBaselineGateTest.php)
git add tests/Feature/Assistance/AssistantBaselineGateTest.php
git commit -m "test(ai): CMS assistant baseline regression gate"
```

---

### Task 6: RAG documentation

**Files:**
- Modify: `Modules/AI/docs/rag/MODULE.md` (add an "Assistant evaluation" subsection)
- Create: `Modules/AI/docs/rag/ASSISTANT_EVALUATION.md`

- [ ] **Step 1: Write the docs** — `ASSISTANT_EVALUATION.md` (RAG section model): the assistant evaluation measures the composed `respond()` per module; Level-1 is deterministic (scripted router over the real `respond()`, no LLM/Elasticsearch) and gates CI via `AssistantBaselineGateTest`; it measures composition plumbing (surface offered, citation assembly, clarification, abstention, output validation), NOT the LLM's routing accuracy; Level-2 (live routing/answer quality via `ai:evaluate-assistant --live`) is specified but deferred. Datasets live under each module's `docs/rag/evaluations/assistant-*.json`. Add a one-line pointer from `MODULE.md`. Reference the design spec.

- [ ] **Step 2: Commit**

```bash
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add docs/rag/ASSISTANT_EVALUATION.md docs/rag/MODULE.md
git commit -m "docs(ai): assistant evaluation guide"
```

---

## Final verification

- [ ] Run the whole R1b surface together:
```bash
php artisan test --compact \
  Modules/AI/tests/Unit/Services/Assistance/Evaluation/ \
  Modules/AI/tests/Feature/Assistance/ScriptedAssistantRunnerTest.php \
  Modules/AI/tests/Feature/Assistance/AssistantBaselineGateTest.php \
  Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php
```
- [ ] `vendor/bin/pint` clean on all changed files.

## Self-review notes (author)

- **Spec coverage:** dataset schema + value objects (Tasks 1–2); deterministic scoring service (Task 3); scripted-router over the real `respond()` (Task 4); first-module dataset + gate (Task 5); RAG docs (Task 6). The Level-2 live mode + `ai:evaluate-assistant` command are intentionally deferred (see the Global-Constraints scope-refinement note) — the reviewer should confirm this deviation from the spec's "command in Level 1" wording is acceptable, since a production command cannot run a deterministic Level 1.
- **Known soft spots (flag for the reviewer / implementer):** (1) the exact NeuronAI `Message` metadata getter (`getMeta`/`getMetadata`/model attribute) must be confirmed in Task 3 Step 3 and used consistently in Tasks 3–5 — the plan marks each spot. (2) Task 4 requires extracting `inAppContentService`/`executeInAppContentTool` from the existing test into the Stub without duplication and re-running the sibling test green. (3) clarification/abstention detection compares message content to `AssistanceGuardrailPipeline::defaults()->clarificationRequired($locale)` / `insufficientEvidence($locale)`; if those methods' names differ, confirm against `AssistanceGuardrailPipeline` and use the real ones.
- **Type consistency:** `AssistantEvaluationCase` props and the `expected_surface` enum-of-strings are used identically across Tasks 1–5; report metric keys (`citation_assembly, clarification_trigger_accuracy, abstention_accuracy, output_valid, unavailable_rate`) match between Task 3 and Task 5.
- **Determinism:** no test uses a live LLM or Elasticsearch; the scripted completion replaces the agent, fake providers replace live data, and the R0 documentation fixtures back the docs surface.
