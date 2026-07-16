# Application Content Retrieval Providers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the authenticated in-app assistant retrieve bounded, cited evidence from module-owned searchable data through a general Core provider contract, with CMS as the first provider.

**Architecture:** Core owns neutral contracts, DTOs, registry, and the authorization gateway so optional modules never depend on AI. Provider modules own search and safe projection; AI owns one contextual read-only tool, evidence prompt projection, abstention, and evaluation. Phase 1 is authenticated-only and reuses existing Core search; public assistance and new passage indexes remain separately gated.

**Tech Stack:** Laravel 12, PHP 8.4, Core authorization/ACL and orchestrated search, Laravel Scout, Elasticsearch/Typesense/database search drivers, NeuronAI v3, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-17-application-content-retrieval-design.md`

---

**Prerequisite:** Complete Tasks 1–7 of `docs/superpowers/plans/2026-07-16-in-app-ai-assistance-security.md` before registering `application_content_search` in the in-app assistant. This plan does not authorize a public route or profile.

**Workspace rule:** Run Artisan and tests from the Laraplate application root. `Modules/Core`, `Modules/CMS`, and `Modules/AI` are nested Git repositories. Commit files in the repository that owns them and stage only the exact paths named by the current task.

## Scope and sequencing

Tasks 1–2 establish the Core extension boundary and authorization gateway. Task 3 implements the CMS record-level baseline. Tasks 4–5 expose it safely to the authenticated assistant. Task 6 adds evaluation. Task 7 synchronizes documentation and records the Phase 2 gate. Task 8 is the release gate.

Do not create a second CMS index in this plan. Do not add `/api/v1` or anonymous access. Do not add module-specific fields to Core DTOs. Do not pass raw search `_source`, Eloquent arrays, permission names, or ACL expressions to AI.

### Task 1: Core provider contracts, DTOs, and deterministic registry

**Files:**

- Create: `Modules/Core/app/ApplicationContent/Contracts/ApplicationContentRetrievalProviderInterface.php`
- Create: `Modules/Core/app/ApplicationContent/Contracts/ApplicationContentRetrievalProviderRegistryInterface.php`
- Create: `Modules/Core/app/ApplicationContent/Data/ApplicationContentQuery.php`
- Create: `Modules/Core/app/ApplicationContent/Data/ApplicationContentAuthorization.php`
- Create: `Modules/Core/app/ApplicationContent/Data/ApplicationContentHit.php`
- Create: `Modules/Core/app/ApplicationContent/Data/ApplicationContentResult.php`
- Create: `Modules/Core/app/ApplicationContent/ApplicationContentRetrievalProviderRegistry.php`
- Create: `Modules/Core/app/ApplicationContent/Exceptions/DuplicateApplicationContentSourceException.php`
- Create: `Modules/Core/config/application-content.php`
- Create: `Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalProviderRegistryTest.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`

- [ ] **Step 1: Write the failing registry and DTO tests**

Cover normalized source-key lookup, deterministic source listing, unknown source, duplicate registration failure, immutable DTOs, limit validation, and rejection of unsafe/oversized hit fields. Define an inline fake implementing `ApplicationContentRetrievalProviderInterface` in the test file; it receives its source key through the constructor and returns an empty typed result.

```php
it('rejects duplicate source keys instead of silently replacing providers', function (): void {
    $registry = app(ApplicationContentRetrievalProviderRegistryInterface::class);
    $registry->register(new FakeApplicationContentProvider('cms.contents'));

    expect(fn () => $registry->register(new FakeApplicationContentProvider('CMS.CONTENTS')))
        ->toThrow(DuplicateApplicationContentSourceException::class);
});
```

- [ ] **Step 2: Run the registry test and verify failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalProviderRegistryTest.php
```

Expected: FAIL because the contracts and registry do not exist.

- [ ] **Step 3: Implement the typed contract**

Use this public provider shape:

```php
interface ApplicationContentRetrievalProviderInterface
{
    public function sourceKey(): string;
    public function module(): string;
    public function entity(): string;

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult;
}
```

`ApplicationContentQuery` contains exactly `source`, `query`, `locale`, and `limit`. Clamp `limit` to the Core configuration maximum and reject blank queries. `ApplicationContentAuthorization` contains only the resolved permission name and optional `FiltersGroup`; it is internal control-plane data and must not implement `JsonSerializable`.

`ApplicationContentHit` contains exactly `id`, `source`, `module`, `entity`, `recordKey`, `excerpt`, `label`, `canonicalReference`, `locale`, `strategy`, optional `score`, optional `revision`, and `truncated`. Enforce configured maximum lengths at construction.

Create `application-content.php` with conservative server-owned defaults: `max_results=8`, `max_query_chars=2000`, `max_excerpt_chars=2000`, `max_label_chars=200`, and `max_reference_chars=500`. Environment configuration may lower or raise deployment limits within hard code-level ceilings; request/tool arguments can only lower them.

- [ ] **Step 4: Implement and bind the registry**

Use normalized lowercase source keys. `register()` throws on collision. `providerFor()` returns `null` for unknown keys; it never resolves a class name dynamically. `sources()` returns sorted keys. Bind the interface as a singleton in `CoreServiceProvider`, following `GraphProviderRegistryInterface`.

- [ ] **Step 5: Run tests and commit Core**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalProviderRegistryTest.php
rtk git -C Modules/Core add app/ApplicationContent app/Providers/CoreServiceProvider.php config/application-content.php tests/Feature/ApplicationContent/ApplicationContentRetrievalProviderRegistryTest.php
rtk git -C Modules/Core commit -m "feat(core): add application content provider registry"
```

### Task 2: Authenticated Core retrieval gateway

**Files:**

- Create: `Modules/Core/app/ApplicationContent/ApplicationContentRetrievalService.php`
- Create: `Modules/Core/app/ApplicationContent/Exceptions/ApplicationContentUnavailableException.php`
- Create: `Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalServiceTest.php`

- [ ] **Step 1: Write failing gateway authorization tests**

Use a capturing fake provider. Cover missing authentication, unknown source, disabled provider module, select permission denial, row ACL propagation, provider exception, invalid returned source/entity, oversized result, and successful bounded retrieval. Assert the provider is never called before authorization succeeds.

```php
it('passes server-resolved ACL filters to the provider', function (): void {
    $request = Request::create('/app/ai/messages', 'POST');
    $request->setUserResolver(fn () => $user);

    $result = app(ApplicationContentRetrievalService::class)->retrieve(
        request: $request,
        query: new ApplicationContentQuery('cms.contents', 'renewal policy', 'en', 5),
    );

    expect($provider->capturedAuthorization?->filters)->not->toBeNull()
        ->and($result->hits)->toHaveCount(1);
});
```

- [ ] **Step 2: Run the gateway test and verify failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/ApplicationContent/ApplicationContentRetrievalServiceTest.php
```

Expected: FAIL because the gateway does not exist.

- [ ] **Step 3: Implement the single authorized gateway**

`retrieve(Request $request, ApplicationContentQuery $query)` must:

1. require an authenticated Core user from the request;
2. resolve only a registered source key;
3. confirm that the provider module is installed and enabled;
4. call `AuthorizationService::ensurePermission($request, $provider->entity(), 'select')`;
5. resolve ACL filters for that permission;
6. invoke the provider with `ApplicationContentAuthorization`;
7. validate source/module/entity invariants and global result limits;
8. map internal failures to `ApplicationContentUnavailableException` without sensitive payloads.

Keep request identity, role names, tenant internals, permission name, and ACL expressions out of `ApplicationContentResult`.

- [ ] **Step 4: Test Auth guard consistency**

Add a regression case where the request resolver and global guard disagree. The service must fail closed until both authorization paths refer to the same authenticated user. Do not allow queued/background provider execution to inherit a missing request identity.

- [ ] **Step 5: Run Core authorization regressions and commit**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/ApplicationContent Modules/Core/tests/Integration/Services/Authorization/AuthorizationServiceTest.php
rtk git -C Modules/Core add app/ApplicationContent tests/Feature/ApplicationContent
rtk git -C Modules/Core commit -m "feat(core): authorize application content retrieval"
```

### Task 3: CMS record-level retrieval provider baseline

**Files:**

- Create: `Modules/CMS/app/ApplicationContent/CmsApplicationContentRetrievalProvider.php`
- Create: `Modules/CMS/app/ApplicationContent/CmsContentEvidenceProjector.php`
- Create: `Modules/CMS/tests/Feature/ApplicationContent/CmsApplicationContentRetrievalProviderTest.php`
- Modify: `Modules/CMS/app/Providers/CMSServiceProvider.php`

- [ ] **Step 1: Write failing provider tests**

Cover registration only when CMS is enabled, source key `cms.contents`, exact lexical search, vector/hybrid result adaptation, locale selection, translation fallback policy, validity, soft deletion, row ACL, cross-tenant exclusion, deterministic ordering, excerpt truncation, safe canonical references, and degraded lexical fallback.

Assert that search `_source`, unrestricted components, media payloads, hidden relations, permission names, ACL filters, model/table/class names, and internal storage paths never appear in a hit.

```php
it('rehydrates authorized records before creating safe evidence', function (): void {
    $result = $provider->retrieve($query, $authorization);

    expect($result->hits[0]->excerpt)->toContain('Visible text')
        ->and((array) $result->hits[0])->not->toHaveKeys(['_source', 'components', 'media']);
});
```

- [ ] **Step 2: Run the provider test and verify failure**

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/ApplicationContent/CmsApplicationContentRetrievalProviderTest.php
```

Expected: FAIL because the provider and projector do not exist.

- [ ] **Step 3: Implement authorized search adaptation**

Inject `AdvancedSearchService` and `AuthorizationService`. Search `Content` with the natural query, bounded result count, locale, and the ACL `FiltersGroup` supplied by Core. Do not call `Model::search()` without the Core constraint path and do not return `AdvancedSearchResult::source` directly.

Collect ranked record IDs, then rehydrate them through an Eloquent query that reapplies `AuthorizationService::applyAclFiltersToQuery()` and CMS validity/deletion rules. Preserve the ranked ID order after rehydration. A stale search hit that no longer passes the database query disappears without confirming its existence.

- [ ] **Step 4: Implement the safe evidence projector**

Build evidence from provider-approved title, textual fields, locale, canonical path, update/revision marker, and normalized rank information. Strip markup, normalize whitespace, and truncate by Unicode character count. Never serialize `Content::toArray()` or `toSearchableArray()` into a hit.

Register the provider in `CMSServiceProvider::boot()` through `ApplicationContentRetrievalProviderRegistryInterface`, following the existing Graph provider registration pattern.

- [ ] **Step 5: Run CMS search/ACL regressions and commit**

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/ApplicationContent/CmsApplicationContentRetrievalProviderTest.php Modules/CMS/tests/Integration/Models/ContentTest.php Modules/CMS/tests/Feature/Graph
rtk git -C Modules/CMS add app/ApplicationContent app/Providers/CMSServiceProvider.php tests/Feature/ApplicationContent/CmsApplicationContentRetrievalProviderTest.php
rtk git -C Modules/CMS commit -m "feat(cms): provide authorized content evidence retrieval"
```

### Task 4: Context-bound AI application content tool

**Files:**

- Create: `Modules/AI/app/Services/ApplicationContent/ApplicationContentToolProvider.php`
- Create: `Modules/AI/app/Services/ApplicationContent/ApplicationContentPromptProjector.php`
- Create: `Modules/AI/tests/Unit/Services/ApplicationContent/ApplicationContentToolProviderTest.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`

- [ ] **Step 1: Write failing tool schema and context tests**

Assert one tool named `application_content_search` with only `source`, `query`, `locale`, and `limit`. Assert absence of user/tenant/role/permission/ACL/index/class/field/filter/system-prompt arguments. Cover developer profile exclusion, unauthenticated context, unapproved source, limit clamping, timeout, provider denial, and safe evidence projection.

```php
it('registers content retrieval only for approved authenticated sources', function (): void {
    $tools = $provider->tools($inAppContext);

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name)->toBe('application_content_search')
        ->and(array_keys($tools[0]->parameters))->toBe(['source', 'query', 'locale', 'limit']);
});
```

- [ ] **Step 2: Run the tool test and verify failure**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/ApplicationContent/ApplicationContentToolProviderTest.php
```

Expected: FAIL because the contextual tool provider does not exist.

- [ ] **Step 3: Implement contextual tool creation**

Build the tool per authenticated `AssistantAccessContext`. Intersect the requested source with the server-owned capability policy and the registry. Call `ApplicationContentRetrievalService` directly with the authenticated request; never issue an HTTP request to Laraplate.

Keep the tool outside `ActionRequestService` and mutation replay. Provider errors return a generic unavailable tool result and payload-free reason code.

- [ ] **Step 4: Implement the prompt projector**

Convert hits to bounded instruction-neutral evidence blocks containing excerpt, safe label, canonical reference, locale, revision, and rank. Escape or delimit retrieved text as untrusted data. Exclude internal diagnostics, raw scores not comparable across engines, authorization context, and unknown fields.

- [ ] **Step 5: Run AI tool regressions and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/ApplicationContent/ApplicationContentToolProviderTest.php Modules/AI/tests/Integration/ToolRegistryTest.php
rtk git -C Modules/AI add app/Services/ApplicationContent app/Providers/AIServiceProvider.php tests/Unit/Services/ApplicationContent/ApplicationContentToolProviderTest.php
rtk git -C Modules/AI commit -m "feat(ai): add authenticated application content tool"
```

### Task 5: In-app orchestration, citations, and abstention

**Files:**

- Create: `Modules/AI/app/Services/ApplicationContent/ApplicationContentCitationMapper.php`
- Create: `Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php`
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistanceContextPolicy.php`
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistanceOutputPolicy.php`

- [ ] **Step 1: Write end-to-end failing assistance tests**

Cover a successful CMS question, combined content evidence plus Graph expansion, no-evidence abstention, unsupported source, permission denial, hidden-record equivalence, malicious instructions inside evidence, unsafe citation, provider timeout, and output-policy rejection.

Assert application content is never added to either documentation index and the developer CLI receives no application content tool.

- [ ] **Step 2: Run the feature test and verify failure**

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php
```

Expected: FAIL because the in-app orchestrator does not register or map application content evidence.

- [ ] **Step 3: Integrate evidence under the existing security pipeline**

Register the tool only after profile/access-context creation and input policy success. Add its evidence to `AssistantPromptContext` under the existing total token/chunk/node budget. The orchestration order remains authorization → retrieval → prompt projection → complete generation → output validation → persistence.

- [ ] **Step 4: Map citations and enforce abstention**

Map each used hit to a user-safe citation label and canonical application reference. Do not present raw similarity as confidence. When no sufficient evidence is returned, respond with the localized insufficient-evidence message and do not let the model substitute assumed application data.

- [ ] **Step 5: Run security regressions and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php Modules/AI/tests/Feature/InAppAssistanceAdversarialTest.php
rtk git -C Modules/AI add app/Services/ApplicationContent app/Services/Assistance tests/Feature/InAppApplicationContentAssistanceTest.php
rtk git -C Modules/AI commit -m "feat(ai): ground in-app answers in module evidence"
```

### Task 6: Evaluation harness and CMS baseline report

**Files:**

- Create: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationCase.php`
- Create: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationDataset.php`
- Create: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationService.php`
- Create: `Modules/AI/app/Console/EvaluateApplicationContentCommand.php`
- Create: `Modules/AI/tests/Unit/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationServiceTest.php`
- Create: `Modules/CMS/tests/Fixtures/application-content/cms-contents.json`
- Create after running baseline: `Modules/CMS/docs/evaluations/application-content/2026-07-record-baseline.json`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`

- [ ] **Step 1: Write failing deterministic metric tests**

Use a fake Core retrieval service and assert hit@K, reciprocal rank, citation precision, authorized-empty accuracy, supported-answer rate, abstention accuracy, latency slices, and locale/source slices. Ensure raw engine scores are retained only as internal diagnostics.

- [ ] **Step 2: Implement the evaluation service and command**

Add `ai:evaluate-application-content` with required `--dataset`, `--source`, and `--output` options plus `--force`. The command runs provider-level retrieval evaluation without calling the chat provider and refuses sources not registered by enabled modules. The dataset loader builds typed, evaluation-only `FiltersGroup` access cases; this path is unavailable to HTTP/tools and does not bypass the authenticated gateway in production.

- [ ] **Step 3: Create the CMS fixture**

Create at least 30 versioned cases: exact lookup, paraphrase, locale/translation, validity, ACL exclusion, cross-tenant exclusion, unsupported questions, long records, and citation mapping. Use generated test records and no production/customer data.

- [ ] **Step 4: Run tests and the opt-in baseline**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationServiceTest.php
rtk php artisan ai:evaluate-application-content --dataset=Modules/CMS/tests/Fixtures/application-content/cms-contents.json --source=cms.contents --output=Modules/CMS/docs/evaluations/application-content/2026-07-record-baseline.json
```

The report records driver, corpus revision, provider version, aggregate/sliced metrics, and latency. It contains no prompts, full content, user identifiers, ACL expressions, or secrets.

- [ ] **Step 5: Apply the passage-index gate**

Keep record-level retrieval when hit@5, citation precision, and supported-answer metrics meet the approved baseline. If long-record cases remain a material failure class, write a separate passage-index spec with ingestion, update, deletion, tenant isolation, storage, and cost analysis; do not add that index from this plan.

- [ ] **Step 6: Commit evaluation code and artifacts in their owners**

```bash
rtk git -C Modules/AI add app/Services/ApplicationContent/Evaluation app/Console/EvaluateApplicationContentCommand.php app/Providers/AIServiceProvider.php tests/Unit/Services/ApplicationContent/Evaluation
rtk git -C Modules/AI commit -m "feat(ai): evaluate application content retrieval"
rtk git -C Modules/CMS add tests/Fixtures/application-content/cms-contents.json docs/evaluations/application-content/2026-07-record-baseline.json
rtk git -C Modules/CMS commit -m "test(cms): add application content retrieval baseline"
```

### Task 7: Module documentation and Phase 2 decision gate

**Files:**

- Modify: `Modules/Core/docs/rag/MODULE.md`
- Modify: `Modules/AI/docs/rag/MODULE.md`
- Modify: `Modules/CMS/docs/rag/MODULE.md`
- Modify: `Modules/Core/docs/GRAPH_SYSTEM.md`
- Modify: `Modules/AI/README.md`

- [ ] **Step 1: Document the three separate retrieval surfaces**

Describe documentation RAG, Core Graph, and application content retrieval as independent capabilities with separate authorization, stores, DTOs, citations, evaluation, and failure behavior.

- [ ] **Step 2: Document provider implementation rules**

Record Core ownership, source-key registration, duplicate failure, authenticated gateway use, pre-query ACL, rehydration, safe projection, evidence DTO shape, no raw `_source`, and no module dependency on AI.

- [ ] **Step 3: Record Phase 2 without implementing it**

Add a clearly non-authorized future section requiring a dedicated public profile, public-visibility policy, rate limits, privacy/retention, abuse controls, prompt-injection treatment, safe fields, citations, cost budgets, and separate approval. State that missing authentication never falls back to public mode.

- [ ] **Step 4: Run module documentation tests and commit by owner**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/CoreRagModuleDocumentationTest.php Modules/AI/tests/Integration/AiRagModuleDocumentationTest.php Modules/CMS/tests/Unit/CmsRagModuleDocumentationTest.php
rtk git -C Modules/Core add docs/rag/MODULE.md docs/GRAPH_SYSTEM.md
rtk git -C Modules/Core commit -m "docs(core): document application content providers"
rtk git -C Modules/AI add docs/rag/MODULE.md README.md
rtk git -C Modules/AI commit -m "docs(ai): document module evidence retrieval"
rtk git -C Modules/CMS add docs/rag/MODULE.md
rtk git -C Modules/CMS commit -m "docs(cms): document content retrieval provider"
```

### Task 8: Full security and release gate

**Files:**

- Create: `Modules/AI/tests/Feature/ApplicationContentRetrievalAdversarialTest.php`
- Modify: `Modules/AI/docs/rag/evaluations/2026-07-in-app-security.json`

- [ ] **Step 1: Add adversarial cross-module cases**

Cover forged source keys, identity/tenant/permission arguments, raw filter/query DSL, source collision, disabled module, stale index hit, cross-tenant record, hidden field, malicious retrieved instructions, oversized excerpt, provider timeout, partial diagnostics, citation path injection, anonymous request, and attempted `/api/v1` use.

- [ ] **Step 2: Run the full deterministic release suite**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/ApplicationContent Modules/CMS/tests/Feature/ApplicationContent Modules/AI/tests/Unit/Services/ApplicationContent Modules/AI/tests/Feature/InAppApplicationContentAssistanceTest.php Modules/AI/tests/Feature/ApplicationContentRetrievalAdversarialTest.php Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php Modules/Core/tests/Feature/Graph
```

Expected: PASS without a live LLM or external search cluster.

- [ ] **Step 3: Verify public access is absent**

Assert no application content route is registered under `/api/v1`, anonymous requests cannot invoke the gateway/tool, and no configuration flag converts authenticated retrieval into public retrieval.

- [ ] **Step 4: Update the security report and commit**

Record aggregate pass/fail counts, policy/provider versions, and timings only. Release requires zero unauthorized-context, hidden-field, identity-spoofing, source-collision, public-fallback, or unsafe-output successes.

```bash
rtk git -C Modules/AI add tests/Feature/ApplicationContentRetrievalAdversarialTest.php docs/rag/evaluations/2026-07-in-app-security.json
rtk git -C Modules/AI commit -m "test(ai): gate application content retrieval security"
```

## Completion checklist

- [ ] Core owns neutral contracts and no AI/Neuron dependency.
- [ ] Optional modules register providers without depending on AI.
- [ ] Duplicate source registration fails deterministically.
- [ ] Phase 1 requires an authenticated application user.
- [ ] Permission, ACL, tenant, validity, and deletion constraints apply before evidence reaches AI.
- [ ] Search hits are rehydrated and reauthorized before safe projection.
- [ ] Tool arguments cannot supply identity, authorization, fields, indexes, or raw query DSL.
- [ ] Evidence contains only bounded allowlisted fields and canonical citations.
- [ ] Documentation RAG, Graph tools, and application content retrieval remain distinct.
- [ ] Empty/insufficient evidence causes abstention.
- [ ] Record-level CMS retrieval has a committed evaluation baseline.
- [ ] No passage index, multimodal processing, entity linking, or public endpoint is added without a separate approved design.
