# Profile-Driven Assistant Scope (R1a) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the in-app assistant's reach an explicit, server-owned dimension: scope the documentation surface to the current module (plus explicitly cross-cutting user guides), gate application-data tools by a data-access level ("no module → docs only"), keep the CLI profile generic, and seam a future superadmin profile — without changing the security boundary.

**Architecture:** Introduce an `AssistantScope` value object (`moduleKey` / `dataAccess` / `docScope`) produced by a server-owned `AssistantScopeResolver` from profile + verified application context. Thread it (optionally, backward-compatible) into documentation retrieval so `DocumentationRetrievalContext` adds a relevance-only module clause on top of the existing audience/permission/tenant/locale filters, and into `InAppAssistanceService::respond()` to withhold application-data tools when data access is `None`.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, NeuronAI `Document`, Elasticsearch (documentation vector store). Deterministic tests reuse the R0 documentation fixtures.

**Spec:** `docs/superpowers/specs/2026-08-06-assistant-profile-scope-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Braces on all control structures; explicit param + return types; `final`/`readonly` per sibling style; `#[Override]` on overrides.
- No new dependencies, no new base folders. Chat Italian, code/docs English.
- Never declare classes/enums inside test files; test support goes under `Modules/AI/tests/Stubs/` (namespace `Modules\AI\Tests\Stubs\`, PSR-4 registered). Run `composer dump-autoload -o` after adding a Stubs class.
- Tests are Pest; no live Elasticsearch or LLM in any test here — reuse the R0 deterministic fixtures (`Modules\AI\Tests\Stubs\Documentation\FakeDocumentationSearch`).
- Scope is server-owned and resolved by server code; conversation metadata / model output can only narrow, never expand it.
- The documentation module clause is **relevance-only**: it is added to the search query alongside (never instead of) the existing audience/permission/tenant/locale filters. `cross_cutting_user` never relaxes those filters and is not exposed in user-facing output.
- `dataAccess = None` must guarantee no application-data tool is constructed for that turn. A missing/unrecognized module context resolves to `dataAccess = None` (fail-closed to documentation-only).
- Backward compatibility: existing callers and the R0 evaluation harness call documentation retrieval without a scope; a null scope must behave exactly as today (no module clause). The R0 suite must stay green.
- Format with `vendor/bin/pint` on the specific changed files (not `pint --dirty` from the repo root — it misses submodule-internal files). Commit inside the `Modules/AI` submodule (branch `master`); do not commit at the laraplate root; touch nothing else (concurrent unrelated changes exist in the working tree).

**Verified code facts (touch points):**
- `AssistantProfile` enum: cases `InAppAssistance = 'in_app_assistance'`, `DeveloperHelp = 'developer_help'` (`Modules/AI/app/Enums/AssistantProfile.php`).
- `AssistantAccessContext` (`profile, userId, tenantScope, tenantId, locale, effectivePermissions, conversationId`) — do NOT add required fields (R0's `DocumentationEvaluationCase::accessContext()` and others construct it directly).
- `DocumentationRetrievalContext`: private constructor (`tenantScope, tenantId, locale, effectivePermissions, topK`), static `fromAccessContext(AssistantAccessContext $access): self`, `elasticsearchFilter(string $classificationVersion): array` returning a `bool.filter` array. Metadata terms used: `metadata.audience`, `metadata.locale`, `metadata.policy_classification`, `metadata.tenant_scope`, `metadata.required_permissions`.
- `InAppDocumentationRetrieval::retrieve(string $question, AssistantAccessContext $access): list<Document>` with injectable `$search` closure `(list<float> $embedding, DocumentationRetrievalContext $context): array<Document>`; `searchUserIndex()` builds the context via `DocumentationRetrievalContext::fromAccessContext($access)`.
- `DocumentationService::retrieveForInApp(string $question, AssistantAccessContext $access): array` delegates to `InAppDocumentationRetrieval`.
- `InAppAssistanceService::respond()` resolves access via `AssistantAccessContextFactory::forInApp()`, retrieves docs (`documentation_retrieval` closure or `documentation->retrieveForInApp`), builds tools via `contextualTools()` → `CompositeContextualToolProvider::toolsForRequest($access, $input, $this->serverApplicationContext())`, and reads the module context from request attribute `assistant_application_context` in `serverApplicationContext()` returning `?ApplicationContentRequestContext(module, entity, recordKey)`.
- `FakeDocumentationSearch` (R0): `__invoke(array $embedding, DocumentationRetrievalContext $context): array` with `isVisible($document, $context)`; `::document($label,$locale,$content,$breadcrumb,$requiredPermissions=[],$tenantScope='global',$tenantId=null,$classificationVersion='in-app-docs-v1'): Document`.
- Documentation metadata originates from YAML frontmatter via `FileDocumentReader::extractFrontMatter()` (arbitrary frontmatter keys flow into `$document->metadata`).

---

### Task 1: `DataAccess` + `DocScope` enums and `AssistantScope` value object

**Files:**
- Create: `Modules/AI/app/Services/Assistance/Scope/DataAccess.php`
- Create: `Modules/AI/app/Services/Assistance/Scope/DocScope.php`
- Create: `Modules/AI/app/Services/Assistance/Scope/AssistantScope.php`
- Test: `Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeTest.php`

**Interfaces:**
- Produces: `enum DataAccess: string { case None='none'; case Module='module'; case Application='application'; }`; `enum DocScope: string { case Module='module'; case Application='application'; }`; `final readonly class AssistantScope` with public `?string $moduleKey`, `DataAccess $dataAccess`, `DocScope $docScope`, and a static `AssistantScope::generic(): self` (moduleKey null, dataAccess None, docScope Application) plus validation.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\Assistance\Scope\AssistantScope;
use Modules\AI\Services\Assistance\Scope\DataAccess;
use Modules\AI\Services\Assistance\Scope\DocScope;

it('builds a module scope', function (): void {
    $scope = new AssistantScope('erp', DataAccess::Module, DocScope::Module);
    expect($scope->moduleKey)->toBe('erp')
        ->and($scope->dataAccess)->toBe(DataAccess::Module)
        ->and($scope->docScope)->toBe(DocScope::Module);
});

it('builds a generic scope with no module and no data access', function (): void {
    $scope = AssistantScope::generic();
    expect($scope->moduleKey)->toBeNull()
        ->and($scope->dataAccess)->toBe(DataAccess::None)
        ->and($scope->docScope)->toBe(DocScope::Application);
});

it('rejects a module docScope without a moduleKey', function (): void {
    expect(fn () => new AssistantScope(null, DataAccess::None, DocScope::Module))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a module dataAccess without a moduleKey', function (): void {
    expect(fn () => new AssistantScope(null, DataAccess::Module, DocScope::Application))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a malformed module key', function (): void {
    expect(fn () => new AssistantScope('Not A Module', DataAccess::Module, DocScope::Module))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeTest.php`
Expected: FAIL (classes not found).

- [ ] **Step 3: Write minimal implementation**

`DataAccess.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\Assistance\Scope;

enum DataAccess: string
{
    case None = 'none';
    case Module = 'module';
    case Application = 'application';
}
```

`DocScope.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\Assistance\Scope;

enum DocScope: string
{
    case Module = 'module';
    case Application = 'application';
}
```

`AssistantScope.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\Assistance\Scope;

use InvalidArgumentException;

final readonly class AssistantScope
{
    public function __construct(
        public ?string $moduleKey,
        public DataAccess $dataAccess,
        public DocScope $docScope,
    ) {
        if ($this->moduleKey !== null && preg_match('/^[a-z][a-z0-9_]*$/', $this->moduleKey) !== 1) {
            throw new InvalidArgumentException('Assistant scope module key is invalid.');
        }

        if ($this->moduleKey === null
            && ($this->docScope === DocScope::Module || $this->dataAccess === DataAccess::Module)) {
            throw new InvalidArgumentException('Module-scoped assistance requires a module key.');
        }
    }

    public static function generic(): self
    {
        return new self(null, DataAccess::None, DocScope::Application);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/Scope/DataAccess.php Modules/AI/app/Services/Assistance/Scope/DocScope.php Modules/AI/app/Services/Assistance/Scope/AssistantScope.php Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/Scope tests/Unit/Services/Assistance/Scope/AssistantScopeTest.php
git commit -m "feat(ai): assistant scope value object (module/dataAccess/docScope)"
```

---

### Task 2: `AssistantScopeResolver`

**Files:**
- Create: `Modules/AI/app/Services/Assistance/Scope/AssistantScopeResolver.php`
- Test: `Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeResolverTest.php`

**Interfaces:**
- Consumes: `AssistantScope`, `DataAccess`, `DocScope` (Task 1); `Modules\AI\Enums\AssistantProfile`.
- Produces: `final readonly class AssistantScopeResolver` with `resolve(AssistantProfile $profile, ?string $moduleKey): AssistantScope`. `$moduleKey` is the server-verified module of the current page (null when none). Mapping: `InAppAssistance` + module → `(module, Module, Module)`; `InAppAssistance` + null → `AssistantScope::generic()`; `DeveloperHelp` → `AssistantScope::generic()`. (The future superadmin profile is not an input yet.)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Enums\AssistantProfile;
use Modules\AI\Services\Assistance\Scope\AssistantScopeResolver;
use Modules\AI\Services\Assistance\Scope\DataAccess;
use Modules\AI\Services\Assistance\Scope\DocScope;

it('scopes in-app assistance to a verified module', function (): void {
    $scope = (new AssistantScopeResolver)->resolve(AssistantProfile::InAppAssistance, 'erp');
    expect($scope->moduleKey)->toBe('erp')
        ->and($scope->dataAccess)->toBe(DataAccess::Module)
        ->and($scope->docScope)->toBe(DocScope::Module);
});

it('falls back to documentation-only when in-app has no recognizable module', function (): void {
    $scope = (new AssistantScopeResolver)->resolve(AssistantProfile::InAppAssistance, null);
    expect($scope->moduleKey)->toBeNull()
        ->and($scope->dataAccess)->toBe(DataAccess::None)
        ->and($scope->docScope)->toBe(DocScope::Application);
});

it('keeps developer help generic and data-free even if a module is passed', function (): void {
    $scope = (new AssistantScopeResolver)->resolve(AssistantProfile::DeveloperHelp, 'erp');
    expect($scope->moduleKey)->toBeNull()
        ->and($scope->dataAccess)->toBe(DataAccess::None)
        ->and($scope->docScope)->toBe(DocScope::Application);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeResolverTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\AI\Services\Assistance\Scope;

use Modules\AI\Enums\AssistantProfile;

final readonly class AssistantScopeResolver
{
    public function resolve(AssistantProfile $profile, ?string $moduleKey): AssistantScope
    {
        if ($profile !== AssistantProfile::InAppAssistance) {
            return AssistantScope::generic();
        }

        if ($moduleKey === null || preg_match('/^[a-z][a-z0-9_]*$/', $moduleKey) !== 1) {
            return AssistantScope::generic();
        }

        return new AssistantScope($moduleKey, DataAccess::Module, DocScope::Module);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeResolverTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/Scope/AssistantScopeResolver.php Modules/AI/tests/Unit/Services/Assistance/Scope/AssistantScopeResolverTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/Scope/AssistantScopeResolver.php tests/Unit/Services/Assistance/Scope/AssistantScopeResolverTest.php
git commit -m "feat(ai): resolve assistant scope from profile + module context"
```

---

### Task 3: Module clause in documentation retrieval (backward-compatible)

**Files:**
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalContext.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php`
- Modify: `Modules/AI/app/Services/DocumentationService.php`
- Test: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php`

**Interfaces:**
- Consumes: `AssistantScope`, `DocScope` (Task 1).
- Produces: `DocumentationRetrievalContext` gains public `?string $moduleKey` and `DocScope $docScope`, a second factory `fromAccessContextAndScope(AssistantAccessContext $access, AssistantScope $scope): self`, and its `elasticsearchFilter()` adds a `module OR cross_cutting_user` clause when `docScope === DocScope::Module`. `InAppDocumentationRetrieval::retrieve(string $question, AssistantAccessContext $access, ?AssistantScope $scope = null)` and `DocumentationService::retrieveForInApp(string $question, AssistantAccessContext $access, ?AssistantScope $scope = null)` accept an optional scope (null → generic, unchanged behavior).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Enums\AssistantProfile;
use Modules\AI\Enums\AssistantTenantScope;
use Modules\AI\Services\Assistance\AssistantAccessContext;
use Modules\AI\Services\Assistance\Scope\AssistantScope;
use Modules\AI\Services\Assistance\Scope\DataAccess;
use Modules\AI\Services\Assistance\Scope\DocScope;
use Modules\AI\Ai\Rag\Retrieval\DocumentationRetrievalContext;

beforeEach(function (): void {
    config()->set('ai.features.faq.max_documents', 5);
});

function scopeAccess(): AssistantAccessContext
{
    return new AssistantAccessContext(
        profile: AssistantProfile::InAppAssistance,
        userId: 'u1',
        tenantScope: AssistantTenantScope::Global,
        tenantId: null,
        locale: 'en',
        effectivePermissions: [],
        conversationId: 'c1',
    );
}

it('adds a module-or-cross-cutting clause under module scope', function (): void {
    $context = DocumentationRetrievalContext::fromAccessContextAndScope(
        scopeAccess(),
        new AssistantScope('erp', DataAccess::Module, DocScope::Module),
    );

    $filter = $context->elasticsearchFilter('in-app-docs-v1');
    $json = json_encode($filter);

    expect($context->moduleKey)->toBe('erp')
        ->and($json)->toContain('"metadata.module":"erp"')
        ->and($json)->toContain('metadata.cross_cutting_user');
});

it('omits the module clause under generic scope (backward compatible)', function (): void {
    $context = DocumentationRetrievalContext::fromAccessContextAndScope(
        scopeAccess(),
        AssistantScope::generic(),
    );

    $json = json_encode($context->elasticsearchFilter('in-app-docs-v1'));

    expect($context->moduleKey)->toBeNull()
        ->and($json)->not->toContain('metadata.module')
        ->and($json)->not->toContain('cross_cutting_user');
});

it('keeps fromAccessContext (no scope) module-agnostic', function (): void {
    $context = DocumentationRetrievalContext::fromAccessContext(scopeAccess());
    $json = json_encode($context->elasticsearchFilter('in-app-docs-v1'));
    expect($json)->not->toContain('metadata.module');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php`
Expected: FAIL (`fromAccessContextAndScope` / `moduleKey` not defined).

- [ ] **Step 3: Write minimal implementation**

In `DocumentationRetrievalContext.php`:

1. Add `use Modules\AI\Services\Assistance\Scope\AssistantScope;` and `use Modules\AI\Services\Assistance\Scope\DocScope;`.
2. Add two promoted properties to the private constructor, after `int $topK`: `public ?string $moduleKey = null,` and `public DocScope $docScope = DocScope::Application,`.
3. Keep `fromAccessContext()` unchanged (it constructs with the defaults → `moduleKey = null`, `docScope = Application`). Add a new factory:

```php
public static function fromAccessContextAndScope(AssistantAccessContext $access, AssistantScope $scope): self
{
    $base = self::fromAccessContext($access);

    return new self(
        tenantScope: $base->tenantScope,
        tenantId: $base->tenantId,
        locale: $base->locale,
        effectivePermissions: $base->effectivePermissions,
        topK: $base->topK,
        moduleKey: $scope->moduleKey,
        docScope: $scope->docScope,
    );
}
```

4. In `elasticsearchFilter()`, before returning, append the relevance clause to the `filter` array when module-scoped:

```php
$filter = [
    ['terms' => ['metadata.audience' => ['user', 'shared']]],
    ['term' => ['metadata.locale' => $this->locale]],
    ['term' => ['metadata.policy_classification' => 'user_safe']],
    ['term' => ['metadata.policy_classification_version' => $classificationVersion]],
    ['term' => ['metadata.permissions_metadata_validated' => true]],
    $this->tenantFilter(),
    $this->permissionFilter(),
];

if ($this->docScope === DocScope::Module && $this->moduleKey !== null) {
    $filter[] = [
        'bool' => [
            'should' => [
                ['term' => ['metadata.module' => $this->moduleKey]],
                ['term' => ['metadata.cross_cutting_user' => true]],
            ],
            'minimum_should_match' => 1,
        ],
    ];
}

return ['bool' => ['filter' => $filter]];
```

(Adjust to the existing method body; the existing filter list and `tenantFilter()`/`permissionFilter()` calls are unchanged — only the conditional clause and the wrapping are added.)

In `InAppDocumentationRetrieval.php`:

1. Add `use Modules\AI\Services\Assistance\Scope\AssistantScope;`.
2. Change the signature to `public function retrieve(string $question, AssistantAccessContext $access, ?AssistantScope $scope = null): array`.
3. Replace the context construction in `searchUserIndex()` path: pass the scope down. Simplest — resolve the context once in `retrieve()` and pass it to `searchUserIndex()`:

```php
$context = $scope === null
    ? DocumentationRetrievalContext::fromAccessContext($access)
    : DocumentationRetrievalContext::fromAccessContextAndScope($access, $scope);
```

Then use `$context` where `DocumentationRetrievalContext::fromAccessContext($access)` was previously built (the existing `$search`/`searchUserIndex` both already receive a `DocumentationRetrievalContext`; thread the resolved `$context`).

In `DocumentationService.php`:

1. Add `use Modules\AI\Services\Assistance\Scope\AssistantScope;`.
2. Change `retrieveForInApp(string $question, AssistantAccessContext $access, ?AssistantScope $scope = null): array` and pass `$scope` into `$retrieval->retrieve($question, $access, $scope)`.

- [ ] **Step 4: Run the new test AND the R0 suite (backward compat)**

Run:
```bash
php artisan test --compact Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php Modules/AI/tests/Unit/Ai/Rag/Retrieval/InAppDocumentationRetrievalTest.php Modules/AI/tests/Feature/DocumentationBaselineGateTest.php
```
Expected: PASS (new scope test green; existing retrieval + R0 gate still green — null scope unchanged).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalContext.php Modules/AI/app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php Modules/AI/app/Services/DocumentationService.php Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Ai/Rag/Retrieval/DocumentationRetrievalContext.php app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php app/Services/DocumentationService.php tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php
git commit -m "feat(ai): module-scope documentation retrieval via AssistantScope"
```

---

### Task 4: Teach the deterministic fixture the module clause

**Files:**
- Modify: `Modules/AI/tests/Stubs/Documentation/FakeDocumentationSearch.php`
- Test: `Modules/AI/tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchScopeTest.php`

**Interfaces:**
- Consumes: `DocumentationRetrievalContext` (Task 3, now carrying `moduleKey`/`docScope`); `DocScope`.
- Produces: `FakeDocumentationSearch::isVisible()` additionally enforces the module-or-cross-cutting clause when the context is module-scoped; `::document(...)` gains an optional `bool $crossCuttingUser = false` and a `string $module = 'core'` parameter so fixtures can set `metadata.module` and `metadata.cross_cutting_user`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Enums\AssistantProfile;
use Modules\AI\Enums\AssistantTenantScope;
use Modules\AI\Services\Assistance\AssistantAccessContext;
use Modules\AI\Services\Assistance\Scope\AssistantScope;
use Modules\AI\Services\Assistance\Scope\DataAccess;
use Modules\AI\Services\Assistance\Scope\DocScope;
use Modules\AI\Tests\Stubs\Documentation\FakeDocumentationSearch;

beforeEach(function (): void {
    config()->set('ai.features.faq.max_documents', 5);
    config()->set('ai.features.faq.policy_classification_version', 'in-app-docs-v1');
});

function erpAccess(): AssistantAccessContext
{
    return new AssistantAccessContext(
        profile: AssistantProfile::InAppAssistance, userId: 'u1',
        tenantScope: AssistantTenantScope::Global, tenantId: null,
        locale: 'en', effectivePermissions: [], conversationId: 'c1',
    );
}

it('under ERP module scope returns ERP and cross-cutting docs but excludes CMS', function (): void {
    $retrieval = FakeDocumentationSearch::forInAppRetrieval([
        'q' => [
            FakeDocumentationSearch::document('ERP · Orders', 'en', 'erp', ['ERP', 'Orders'], module: 'erp'),
            FakeDocumentationSearch::document('Core · Approve modification', 'en', 'x', ['Core'], module: 'core', crossCuttingUser: true),
            FakeDocumentationSearch::document('CMS · Blocks', 'en', 'cms', ['CMS'], module: 'cms'),
        ],
    ]);

    $docs = $retrieval->retrieve('q', erpAccess(), new AssistantScope('erp', DataAccess::Module, DocScope::Module));
    $labels = array_map(static fn ($d): string => $d->sourceName, $docs);

    expect($labels)->toContain('ERP · Orders')
        ->and($labels)->toContain('Core · Approve modification')
        ->and($labels)->not->toContain('CMS · Blocks');
});

it('with generic scope returns all modules (no module clause)', function (): void {
    $retrieval = FakeDocumentationSearch::forInAppRetrieval([
        'q' => [
            FakeDocumentationSearch::document('ERP · Orders', 'en', 'erp', ['ERP'], module: 'erp'),
            FakeDocumentationSearch::document('CMS · Blocks', 'en', 'cms', ['CMS'], module: 'cms'),
        ],
    ]);

    $docs = $retrieval->retrieve('q', erpAccess(), AssistantScope::generic());
    expect($docs)->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchScopeTest.php`
Expected: FAIL (`document()` has no `module`/`crossCuttingUser` params; `isVisible` ignores module scope).

- [ ] **Step 3: Write minimal implementation**

In `FakeDocumentationSearch.php`:

1. Change the `document()` signature and metadata so callers can set module and the marker. Replace the fixed `'module' => 'core'` with the parameter and add the marker:

```php
public static function document(
    string $label,
    string $locale,
    string $content,
    array $breadcrumb,
    array $requiredPermissions = [],
    string $tenantScope = 'global',
    ?string $tenantId = null,
    string $classificationVersion = 'in-app-docs-v1',
    string $module = 'core',
    bool $crossCuttingUser = false,
): Document {
    // ... existing body, but:
    //   'module' => $module,
    //   ...
    //   'cross_cutting_user' => $crossCuttingUser,
}
```

Add `'cross_cutting_user' => $crossCuttingUser,` to the metadata array and use `$module` for `'module'` (and keep `canonical_source` deriving from `$label` as before).

2. In `isVisible(Document $document, DocumentationRetrievalContext $context): bool`, after the existing locale/tenant/permission checks, add the module-scope check:

```php
if ($context->docScope === \Modules\AI\Services\Assistance\Scope\DocScope::Module && $context->moduleKey !== null) {
    $module = $metadata['module'] ?? null;
    $crossCutting = ($metadata['cross_cutting_user'] ?? false) === true;

    if ($module !== $context->moduleKey && ! $crossCutting) {
        return false;
    }
}
```

(Place it alongside the existing `$metadata = $document->metadata;` read.)

- [ ] **Step 4: Run test to verify it passes, then re-run R0 fixtures**

Run:
```bash
php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchScopeTest.php Modules/AI/tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchTest.php Modules/AI/tests/Feature/DocumentationBaselineGateTest.php
```
Expected: PASS (new scope test green; R0 fixture + gate still green — default `module='core'`, `crossCuttingUser=false`, and no scope passed by R0 → module clause not triggered).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/tests/Stubs/Documentation/FakeDocumentationSearch.php Modules/AI/tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchScopeTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add tests/Stubs/Documentation/FakeDocumentationSearch.php tests/Unit/Services/Documentation/Evaluation/FakeDocumentationSearchScopeTest.php
git commit -m "test(ai): fixture honors module-or-cross-cutting doc scope"
```

---

### Task 5: Wire scope into `InAppAssistanceService::respond()`

**Files:**
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php` (constructor injection of `AssistantScopeResolver` if not auto-resolvable — it is a no-dependency `final readonly` class, so container auto-resolves it; only add a binding if the existing service is constructed manually)
- Test: `Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php`

**Interfaces:**
- Consumes: `AssistantScopeResolver`, `AssistantScope`, `DataAccess` (Tasks 1–2); the module context from `serverApplicationContext()`; `documentation->retrieveForInApp(..., $scope)` / injected `documentation_retrieval` closure (Task 3).
- Produces: `respond()` resolves an `AssistantScope`, passes it to documentation retrieval, and returns an empty tool list when `dataAccess === DataAccess::None`.

- [ ] **Step 1: Write the failing test**

First read one existing `Modules/AI/tests/Unit/Services/Assistance/*Test.php` to copy its exact setup for constructing `InAppAssistanceService` with injected `documentation_retrieval` and `completion` closures and its authenticated-user + `Conversation` fabrication (no live provider/LLM). Then write two tests with this precise arrange/act/assert (no `->todo()` in the committed file):

Test A — "passes a module-scoped AssistantScope to documentation retrieval when a module context is present":
- Arrange: `request()->attributes->set('assistant_application_context', ['module' => 'erp'])`; a `documentation_retrieval` closure `fn(string $q, $access, $scope) => [$captured_scope = $scope][0] ? [] : []` that captures its 3rd argument into a test-visible variable and returns `[]`; a `completion` closure returning a fixed safe non-empty string; authenticate the same non-guest user the access context resolves to.
- Act: `$service->respond($conversation, $user, 'a question')`.
- Assert: the captured scope has `moduleKey === 'erp'` and `dataAccess === DataAccess::Module`.

Test B — "resolves DataAccess::None and builds no application-data tools when no module context is present":
- Arrange: do NOT set `assistant_application_context`; a `completion` closure that captures its 4th argument (`$tools`) into a test-visible variable and returns a fixed safe string.
- Act: `respond(...)`.
- Assert: the captured scope has `dataAccess === DataAccess::None` and the captured `$tools` is an empty array.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php`
Expected: FAIL (scope not resolved / tools not gated).

- [ ] **Step 3: Write minimal implementation**

In `InAppAssistanceService.php`:

1. Inject `AssistantScopeResolver $scope_resolver` (add to the constructor, before the nullable closures — a required, auto-resolvable dependency).
2. In `respond()`, after `$access = ...` and before documentation retrieval, resolve the scope from the profile and the server application context module:

```php
$module_context = $this->serverApplicationContext();
$scope = $this->scope_resolver->resolve($access->profile, $module_context?->module);
```

3. Pass the scope into documentation retrieval:

```php
$documents = $this->documentation_retrieval instanceof Closure
    ? ($this->documentation_retrieval)($input, $access, $scope)
    : $this->documentation->retrieveForInApp($input, $access, $scope);
```

Update the injected-closure PHPDoc to `Closure(string, AssistantAccessContext, AssistantScope): list<Document>`.

4. Gate tools by data access — change `contextualTools()` to receive the scope and short-circuit:

```php
$tools = $scope->dataAccess === DataAccess::None
    ? []
    : $this->contextualTools($access, $input, $policy);
```

Add `use Modules\AI\Services\Assistance\Scope\AssistantScope;`, `use Modules\AI\Services\Assistance\Scope\AssistantScopeResolver;`, and `use Modules\AI\Services\Assistance\Scope\DataAccess;`.

- [ ] **Step 4: Run test to verify it passes, then the assistance suite**

Run:
```bash
php artisan test --compact Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php Modules/AI/tests/Unit/Services/Assistance/ Modules/AI/tests/Unit/Services/Assistance/AssistanceGuardrailPipelineTest.php
```
Expected: PASS (new behavior green; existing assistance tests still green).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint Modules/AI/app/Services/Assistance/InAppAssistanceService.php Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add app/Services/Assistance/InAppAssistanceService.php tests/Feature/Assistance/AssistantScopeRespondTest.php
git commit -m "feat(ai): resolve and apply assistant scope in respond()"
```

---

### Task 6: `cross_cutting_user` frontmatter marker, content tagging, and RAG docs

**Files:**
- Modify (verify + document): `Modules/AI/app/Services/Documentation/FileDocumentReader.php` behavior is used as-is (frontmatter → metadata); confirm the marker survives to the index.
- Modify: frontmatter of the few genuinely cross-cutting **user** guides (e.g. `Modules/Core/docs/rag/*.md` for approve-modification / permissions-you-see / grid export / draft recovery — pick the ones whose audience is `user` and whose task is cross-module).
- Modify: `Modules/AI/docs/rag/DOCUMENTATION_EVALUATION_DEVELOPER.md` is not the target — instead update the assistant-facing RAG docs: `Modules/AI/docs/rag/MODULE.md` (add a "Profile-driven scope" subsection) and create `Modules/AI/docs/rag/ASSISTANT_SCOPE.md` (user + developer notes).
- Test: `Modules/AI/tests/Unit/Services/Documentation/CrossCuttingMarkerTest.php`

**Interfaces:**
- Consumes: `FileDocumentReader` (frontmatter extraction).
- Produces: a documented, tested convention that `cross_cutting_user: true` in a doc's YAML frontmatter reaches chunk metadata as `metadata.cross_cutting_user === true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\AI\Services\Documentation\FileDocumentReader;

it('carries the cross_cutting_user frontmatter marker into document metadata', function (): void {
    $dir = sys_get_temp_dir() . '/laraplate-doc-marker-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    $path = $dir . '/guide.md';
    file_put_contents($path, "---\nmodule: core\naudience: user\ncross_cutting_user: true\n---\n# Approve a modification\nSteps.\n");

    try {
        $documents = (new FileDocumentReader($path))->getDocuments();

        expect($documents)->toHaveCount(1)
            ->and($documents[0]->metadata['cross_cutting_user'] ?? null)->toBeTrue()
            ->and($documents[0]->metadata['module'] ?? null)->toBe('core');
    } finally {
        @unlink($path);
        @rmdir($dir);
    }
});
```

- [ ] **Step 2: Run test to verify it fails or passes**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/CrossCuttingMarkerTest.php`
Expected: PASS immediately if `FileDocumentReader` already passes arbitrary frontmatter through (it does — `extractFrontMatter()` returns the parsed YAML as metadata). If it PASSES, the reader needs no code change; keep the test as a regression guard. If it FAILS (frontmatter stripped or filtered), add the minimal change to `createDocument()`/`extractFrontMatter()` to preserve the key, then re-run.

- [ ] **Step 3: Verify the marker survives indexing to the vector store**

Read `Modules/AI/app/Console/IndexDocumentationCommand.php` and the enrichment path it calls (audience/permission classification before the vector store `put`). Confirm `cross_cutting_user` is not dropped by an allowlist before indexing. If an allowlist exists, add `cross_cutting_user` to the preserved metadata keys (relevance-only; do NOT add it to the user-facing safe projection in `InAppDocumentationRetrieval::safeDocuments()`). State in the report what you found and any change made.

- [ ] **Step 4: Tag the cross-cutting user guides**

For each genuinely cross-cutting, `audience: user` Core guide that answers a task a user performs from any module (approve modification, permission visibility, grid export, draft recovery), add `cross_cutting_user: true` to its YAML frontmatter (add frontmatter if absent, preserving existing keys). Do not tag developer/architecture docs. List each file tagged in the report.

- [ ] **Step 5: Document the scope model**

Create `Modules/AI/docs/rag/ASSISTANT_SCOPE.md` following the RAG section model (Purpose, Capabilities, HowToUse, InternalFlow, PermissionsAndSecurity, ...): explain that the in-app assistant is scoped to the page's module; documentation retrieval returns the current module's user guides plus `cross_cutting_user`-marked guides; app-data tools are withheld when there is no module ("docs only"); the CLI stays generic; scope is server-owned and never model-chosen; the security boundary (audience/permission/tenant/safe projection) is unchanged. Add a one-line pointer from `Modules/AI/docs/rag/MODULE.md`.

- [ ] **Step 6: Run the marker test and commit**

Run: `php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/CrossCuttingMarkerTest.php`
Expected: PASS.

```bash
vendor/bin/pint Modules/AI/tests/Unit/Services/Documentation/CrossCuttingMarkerTest.php
# Core-owned doc frontmatter is a Core submodule change; AI docs + test + any reader change are AI submodule changes.
cd /srv/http/laraplate-stack/laraplate/Modules/Core
git add docs/rag/*.md
git commit -m "docs(core): mark cross-cutting user guides for assistant module scope"
cd /srv/http/laraplate-stack/laraplate/Modules/AI
git add docs/rag/ASSISTANT_SCOPE.md docs/rag/MODULE.md tests/Unit/Services/Documentation/CrossCuttingMarkerTest.php
# include FileDocumentReader.php only if you changed it in Step 2/3
git commit -m "docs(ai): assistant scope model + cross_cutting_user marker convention"
```

---

## Final verification

- [ ] Run the whole scope + R0 surface together (deterministic, no external services):
```bash
php artisan test --compact \
  Modules/AI/tests/Unit/Services/Assistance/Scope/ \
  Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalScopeTest.php \
  Modules/AI/tests/Unit/Services/Documentation/Evaluation/ \
  Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php \
  Modules/AI/tests/Feature/DocumentationBaselineGateTest.php
```
- [ ] Confirm R0 stayed green (null-scope backward compatibility).
- [ ] `vendor/bin/pint` clean on all changed files.

## Self-review notes (author)

- **Spec coverage:** scope value object + resolver (Tasks 1–2); documentation module clause on top of unchanged security filters (Task 3); deterministic fixture emulation (Task 4); `dataAccess=None → docs-only` and scope wiring in `respond()` (Task 5); `cross_cutting_user` marker + content tagging + RAG docs (Task 6). Out of scope (superadmin profile build, per-entity graph filtering, R1b eval, navigation/actions) — intentionally no task.
- **Backward compatibility:** every retrieval entry point keeps a null-scope default equal to today's behavior; R0's gate and the existing retrieval/assistance suites are re-run in Tasks 3–5 and the final verification.
- **Type consistency:** `AssistantScope(moduleKey, dataAccess: DataAccess, docScope: DocScope)`, `DataAccess::{None,Module,Application}`, `DocScope::{Module,Application}`, `DocumentationRetrievalContext->{moduleKey,docScope}`, `retrieve(question, access, ?scope)`, `retrieveForInApp(question, access, ?scope)`, `metadata.cross_cutting_user` — used identically across Tasks 1–6.
- **Security invariant:** the module clause is additive to `bool.filter`; `cross_cutting_user` is relevance-only and excluded from `safeDocuments()` output (Task 6 Step 3 explicitly forbids adding it to the safe projection).
- **Known soft spot (Task 5 test):** the `respond()` feature test's arrange/act/assert are specified precisely, but the exact conversation/user/access fabrication must be copied from an existing `Modules/AI/tests/Unit/Services/Assistance/*Test.php` sibling — the implementer reads one first. No `->todo()` may remain in the committed test.
