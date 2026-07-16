# In-app AI Assistance Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an authenticated in-app assistant that answers only application-usage questions and authorized live-data questions, while preserving unrestricted documentation retrieval for the developer CLI.

**Architecture:** Introduce server-owned assistant profiles, physically separate developer and user RAG indexes, a mandatory fail-closed policy pipeline, and three contextual read-only Core Graph tools. Authorization and ACL filtering happen before documents or graph data reach the LLM; the complete response is validated before persistence and delivery.

**Tech Stack:** Laravel 12, PHP 8.4, NeuronAI v3, Elasticsearch v8, Core Graph and authorization services, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-16-in-app-ai-assistance-security-design.md`

---

**Workspace rule:** Run Artisan and tests from the Laraplate application root. `Modules/AI` and `Modules/Core` are nested Git repositories. Commit AI files with `rtk git -C Modules/AI ...`, Core files with `rtk git -C Modules/Core ...`, and application-level docs with `rtk git ...` from the Laraplate root. Stage only paths named by each task because these repositories may contain unrelated work.

## Scope and sequencing

Tasks 1–4 establish the security boundary before any live-data tool is registered. Tasks 5–6 expose Core Graph through a typed read-only bridge. Task 7 integrates the protected response flow. Task 8 is the release gate.

Do not expose the in-app assistant before every task is complete. Do not enable streaming for the in-app profile. Do not replace these Graph tools with Graphify, Microsoft GraphRAG, a graph database, or an HTTP request back into Laraplate.

### Task 1: Server-owned assistant profiles and access context

**Files:**

- Create: `Modules/AI/app/Enums/AssistantProfile.php`
- Create: `Modules/AI/app/Services/Assistance/AssistantAccessContext.php`
- Create: `Modules/AI/app/Services/Assistance/AssistantAccessContextFactory.php`
- Create: `Modules/AI/tests/Unit/Services/Assistance/AssistantAccessContextFactoryTest.php`
- Modify: `Modules/AI/app/Http/Requests/SendMessageRequest.php`
- Modify: `Modules/AI/app/Http/Controllers/ChatController.php`

- [ ] **Step 1: Write failing access-context tests**

Cover the `DeveloperHelp` and `InAppAssistance` enum cases, authenticated conversation ownership, server-derived tenant/locale/permissions, and rejection before provider execution when the authenticated user differs from the conversation owner.

```php
it('builds in-app identity only from the authenticated owner', function (): void {
    $context = app(AssistantAccessContextFactory::class)->forInApp(
        conversation: $conversation,
        authenticatedUser: $owner,
    );

    expect($context->profile)->toBe(AssistantProfile::InAppAssistance)
        ->and($context->userId)->toBe((string) $owner->getKey())
        ->and($context->tenantId)->toBe((string) $owner->tenant_id);
});
```

- [ ] **Step 2: Run the test and verify failure**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/AssistantAccessContextFactoryTest.php
```

Expected: FAIL because the profile and access-context types do not exist.

- [ ] **Step 3: Implement immutable profile and context types**

`AssistantAccessContext` contains profile, user ID, tenant ID, locale, effective permission names, and conversation ID. `forInApp()` obtains every field from authenticated server state and fails on owner mismatch or unresolved tenant/permissions. `forDeveloperHelp()` is callable only by the Artisan entry point and has no runtime user or tenant access.

- [ ] **Step 4: close request-controlled escalation surfaces**

For the in-app route, reject request keys `profile`, `user_id`, `tenant_id`, `permissions`, `roles`, `tools`, and `system_message`. Remove any use of conversation metadata or message context to select the profile. Keep the server-owned context out of mass-assigned model metadata.

- [ ] **Step 5: Run focused tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/AssistantAccessContextFactoryTest.php Modules/AI/tests/Integration/ChatControllerTest.php
rtk git -C Modules/AI add app/Enums/AssistantProfile.php app/Services/Assistance app/Http/Requests/SendMessageRequest.php app/Http/Controllers/ChatController.php tests/Unit/Services/Assistance tests/Integration/ChatControllerTest.php
rtk git -C Modules/AI commit -m "feat(ai): add server-owned assistant profiles"
```

### Task 2: Physically separate documentation indexes

**Files:**

- Create: `Modules/AI/app/Ai/Rag/DocumentationIndexProfile.php`
- Create: `Modules/AI/app/Services/Documentation/DocumentAudiencePolicy.php`
- Create: `Modules/AI/tests/Unit/Services/Documentation/DocumentAudiencePolicyTest.php`
- Modify: `Modules/AI/app/Ai/Rag/ElasticsearchRagVectorStore.php`
- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php`
- Modify: `Modules/AI/app/Console/IndexDocumentationCommand.php`
- Modify: `Modules/AI/app/Services/Documentation/FileDocumentReader.php`
- Modify: `Modules/AI/config/config.php`
- Modify: `Modules/AI/tests/Integration/IndexDocumentationCommandTest.php`

- [ ] **Step 1: Write failing corpus-isolation tests**

Assert that developer indexing accepts `developer`, `shared`, and `user` documents, while user indexing accepts only explicitly classified `user` or `shared` documents that pass the restricted-topic policy. Missing/invalid audience metadata must be excluded from the user index, never treated as shared.

- [ ] **Step 2: Add distinct index configuration**

Define `AI_FAQ_DEVELOPER_ES_INDEX` and `AI_FAQ_USER_ES_INDEX`. Validate at boot/indexing time that their resolved names differ and do not alias one another. Existing `AI_FAQ_ES_INDEX` may remain a deprecated developer-index fallback for migration compatibility only.

- [ ] **Step 3: make index selection typed and explicit**

The vector store and `DocumentationAgent` receive `DocumentationIndexProfile`; they must not accept a raw request-supplied index name. Add `ai:index-rag-docs --profile=developer|user|all`, with `developer` preserving the current full documentation workflow and `user` applying deny-by-default classification.

- [ ] **Step 4: persist safe retrieval metadata**

Each user chunk stores audience, module, locale, canonical source, safe source label, required permissions, tenant scope/ID, version, heading breadcrumb, and policy classification version. Reject sources whose restricted classification or permission metadata cannot be validated.

- [ ] **Step 5: Run tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/DocumentAudiencePolicyTest.php Modules/AI/tests/Integration/IndexDocumentationCommandTest.php Modules/AI/tests/Unit/Ai/Rag/ElasticsearchRagVectorStoreTest.php
rtk git -C Modules/AI add app/Ai/Rag app/Ai/Agents/DocumentationAgent.php app/Console/IndexDocumentationCommand.php app/Services/Documentation config/config.php tests
rtk git -C Modules/AI commit -m "feat(ai): isolate developer and user RAG corpora"
```

### Task 3: Permission- and tenant-filtered in-app retrieval

**Files:**

- Create: `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalContext.php`
- Create: `Modules/AI/app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/InAppDocumentationRetrievalTest.php`
- Modify: `Modules/AI/app/Ai/Rag/ElasticsearchRagVectorStore.php`
- Modify: `Modules/AI/app/Services/DocumentationService.php`

- [ ] **Step 1: Write failing pre-retrieval authorization tests**

Use an Elasticsearch client fake and assert that tenant scope and effective permissions are encoded in the Elasticsearch query before hits are returned. Cover global help, matching tenant help, cross-tenant exclusion, AND semantics for multiple required permissions, unclassified chunks, and unavailable permission resolution.

- [ ] **Step 2: Implement the typed retrieval context**

Construct `DocumentationRetrievalContext` only from `AssistantAccessContext`. The user retriever always targets `DocumentationIndexProfile::User`, adds exact metadata filters inside Elasticsearch, bounds `topK`, and emits only safe source labels.

- [ ] **Step 3: enforce fail-closed behavior**

If the user index, permission set, tenant context, or filter construction is unavailable, return a policy refusal. Never retry against the developer index or an unfiltered query. Never perform post-retrieval ACL filtering as the only protection.

- [ ] **Step 4: Run tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Ai/Rag/Retrieval/InAppDocumentationRetrievalTest.php Modules/AI/tests/Integration/DocumentationServiceTest.php
rtk git -C Modules/AI add app/Ai/Rag app/Services/DocumentationService.php tests/Unit/Ai/Rag tests/Integration/DocumentationServiceTest.php
rtk git -C Modules/AI commit -m "feat(ai): enforce scoped in-app documentation retrieval"
```

### Task 4: Mandatory fail-closed guardrail pipeline

**Files:**

- Create: `Modules/AI/app/Exceptions/AssistancePolicyViolationException.php`
- Create: `Modules/AI/app/Services/Assistance/AssistanceGuardrailPipeline.php`
- Create: `Modules/AI/app/Services/Assistance/Policies/AssistanceInputPolicy.php`
- Create: `Modules/AI/app/Services/Assistance/Policies/AssistanceContextPolicy.php`
- Create: `Modules/AI/app/Services/Assistance/Policies/AssistanceOutputPolicy.php`
- Create: `Modules/AI/app/Services/Assistance/Policies/RestrictedTopicPolicy.php`
- Create: `Modules/AI/tests/Unit/Services/Assistance/AssistanceGuardrailPipelineTest.php`
- Modify: `Modules/AI/config/config.php`

- [ ] **Step 1: Write failing policy tests**

Cover in-app usage help as allowed and every restricted class as denied: licensing internals, code/stack traces, tokens/secrets, database internals, other users, hidden ACL/permission data, encryption details, system prompts, tool internals, and infrastructure. Add prompt-injection, secret-pattern, unsafe-citation, classifier-timeout, and uncertain-result cases.

- [ ] **Step 2: Implement deterministic policy stages**

The pipeline validates input before retrieval/provider calls, sanitizes bounded context, and validates the complete model output plus citations before persistence. Retrieved text is always labelled as untrusted data. Provider-backed classifiers sit behind interfaces so tests use deterministic fakes.

- [ ] **Step 3: make in-app guardrails non-optional**

Existing general chat guardrail feature flags must not disable in-app policies. Dependency failure or uncertainty raises `AssistancePolicyViolationException`; it cannot log-and-continue. Store reason codes and timing without the rejected prompt/output payload.

- [ ] **Step 4: add generic localized refusals**

Refusals must not reveal whether the requested document, record, user, permission, or secret exists. The raw rejected model response must not be stored in messages, ordinary logs, traces, or exception text.

- [ ] **Step 5: Run tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Assistance/AssistanceGuardrailPipelineTest.php Modules/AI/tests/Integration/GuardrailsServiceTest.php
rtk git -C Modules/AI add app/Exceptions/AssistancePolicyViolationException.php app/Services/Assistance config/config.php tests/Unit/Services/Assistance tests/Integration/GuardrailsServiceTest.php
rtk git -C Modules/AI commit -m "feat(ai): enforce fail-closed in-app guardrails"
```

### Task 5: Typed read-only Core Graph gateway

**Files:**

- Create: `Modules/Core/app/Graph/Contracts/GraphToolGatewayInterface.php`
- Create: `Modules/Core/app/Graph/Data/GraphSearchToolInput.php`
- Create: `Modules/Core/app/Graph/Data/GraphExpandToolInput.php`
- Create: `Modules/Core/app/Graph/Data/GraphStatsToolInput.php`
- Create: `Modules/Core/app/Graph/GraphToolGateway.php`
- Create: `Modules/Core/tests/Feature/Graph/GraphToolGatewayTest.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`

- [ ] **Step 1: Write failing gateway security tests**

Cover `search`, `expand`, and `stats` with visible and hidden records, cross-tenant data, hidden relations, provider-rule exclusions, unauthorized centers, and configured depth/node/relation/detail limits. Assert that input DTOs expose no user ID, tenant ID, class name, connection, SQL, mutation, or arbitrary query JSON.

- [ ] **Step 2: Implement a direct service gateway**

`GraphToolGateway` adapts the typed inputs to existing Core Graph request DTOs and calls `GraphService` directly under the authenticated request/user context. Reuse `AuthorizationService`, ACL query filters, entity resolution, provider rules, traversal limits, and serializers; do not duplicate their logic in AI and do not call Core over HTTP.

- [ ] **Step 3: enforce read-only and bounded output**

Expose only `search`, `expand`, and `stats`. Return user-safe graph DTOs with internal fields removed. Map unauthorized/missing centers to a generic unavailable result that does not confirm existence.

- [ ] **Step 4: Run Core graph tests and commit**

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph/GraphToolGatewayTest.php Modules/Core/tests/Feature/Graph
rtk git -C Modules/Core add app/Graph/Contracts/GraphToolGatewayInterface.php app/Graph/Data app/Graph/GraphToolGateway.php app/Providers/CoreServiceProvider.php tests/Feature/Graph/GraphToolGatewayTest.php
rtk git -C Modules/Core commit -m "feat(core): expose authorized read-only graph gateway"
```

### Task 6: Context-bound AI Graph tools

**Files:**

- Create: `Modules/AI/app/Services/Tools/ContextualToolProviderInterface.php`
- Create: `Modules/AI/app/Services/Tools/GraphToolProvider.php`
- Create: `Modules/AI/tests/Unit/Services/Tools/GraphToolProviderTest.php`
- Modify: `Modules/AI/app/Services/Tools/ToolRegistry.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`

- [ ] **Step 1: Write failing tool-schema and binding tests**

Assert exactly three tools named `graph_search`, `graph_expand`, and `graph_stats`; explicit bounded schemas; no mutation verbs; no identity/tenant/permission arguments; and a handler bound to the current `AssistantAccessContext`. Assert tools are absent for `DeveloperHelp`.

- [ ] **Step 2: implement contextual registration**

`GraphToolProvider` creates tool definitions per request/context and invokes `GraphToolGatewayInterface`. Do not place these handlers in a global registry without access context. Treat tool arguments as untrusted and validate module, entity, relation, depth, limit, and detail against stricter in-app bounds.

- [ ] **Step 3: keep Graph tools outside action replay**

The tools are read-only capabilities, not business actions. They must not create `ActionRequest` records, enter approval/replay mutation flows, or become writable through risk configuration.

- [ ] **Step 4: Run tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Tools/GraphToolProviderTest.php Modules/AI/tests/Integration/ToolRegistryTest.php
rtk git -C Modules/AI add app/Services/Tools app/Providers/AIServiceProvider.php tests/Unit/Services/Tools
rtk git -C Modules/AI commit -m "feat(ai): add contextual read-only graph tools"
```

### Task 7: Protected in-app response flow and non-streaming contract

**Files:**

- Create: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Create: `Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php`
- Modify: `Modules/AI/app/Http/Controllers/ChatController.php`
- Modify: `Modules/AI/app/Services/ChatService.php`
- Modify: `Modules/AI/app/Console/LaraplateHelpCommand.php`
- Modify: `Modules/AI/routes/web.php`

- [ ] **Step 1: Write end-to-end failing security tests**

Assert that the in-app route uses `InAppAssistance`, a fixed server-owned system policy, the user index, and contextual Graph tools. Assert owner mismatch, restricted input, hidden data, unsafe output, and guardrail failure cause no provider/tool execution or raw assistant-message persistence.

- [ ] **Step 2: implement the protected orchestration order**

The service must execute: ownership/context creation → input policy → authorized RAG/Graph context → fixed prompt → complete model response → output/DLP policy → persistence → response. Safe citations contain labels, not internal filesystem paths.

- [ ] **Step 3: lock developer CLI behavior**

`ai:help` explicitly selects `DeveloperHelp`, retrieves all approved documentation audiences, registers no live Graph tools, and receives no runtime secrets or customer data.

- [ ] **Step 4: reject in-app streaming in v1**

The streaming endpoint must not select `InAppAssistance` or register Graph tools. Return a documented validation/capability error if a protected in-app request attempts streaming. No generated token may be persisted or delivered before full output validation.

- [ ] **Step 5: Run tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php Modules/AI/tests/Integration/ChatControllerTest.php Modules/AI/tests/Integration/LaraplateHelpCommandTest.php
rtk git -C Modules/AI add app/Services/Assistance/InAppAssistanceService.php app/Http/Controllers/ChatController.php app/Services/ChatService.php app/Console/LaraplateHelpCommand.php routes/web.php tests/Feature
rtk git -C Modules/AI commit -m "feat(ai): integrate protected in-app assistance"
```

### Task 8: Security release gate, evaluation, and documentation

**Files:**

- Create: `Modules/AI/tests/Feature/InAppAssistanceAdversarialTest.php`
- Create: `Modules/AI/docs/rag/evaluations/2026-07-in-app-security.json`
- Modify: `Modules/AI/docs/rag/MODULE.md`
- Modify: `Modules/AI/README.md`
- Modify: `Modules/Core/docs/GRAPH_SYSTEM.md`
- Modify: `Modules/Core/docs/rag/MODULE.md`

- [ ] **Step 1: add a versioned adversarial evaluation set**

Include direct and indirect prompt injection, corpus crossover, tenant/user spoofing, permission enumeration, hidden-record inference, secret patterns, licensing/code/database/encryption requests, unsafe citations, tool argument abuse, and guardrail dependency failure. Include safe positive cases for app usage and authorized Graph questions.

- [ ] **Step 2: verify invariant tests**

Use fake LLM, embeddings, classifier, Elasticsearch, and Graph gateway implementations. Assert forbidden data never enters the provider prompt, denied tool results disclose no existence signal, raw rejected output is absent from persistence/logs, and all allowed answers pass the output policy.

- [ ] **Step 3: update operator and module documentation**

Document the two profiles/indexes, required metadata, index build commands, mandatory production guardrails, read-only Graph tools, ACL inheritance, limits, refusal behavior, logging constraints, and the no-streaming v1 contract. State explicitly that Core Graph tools are unrelated to Graphify/GraphRAG.

- [ ] **Step 4: Run the complete release gate**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Assistance Modules/AI/tests/Unit/Ai/Rag Modules/AI/tests/Unit/Services/Tools Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php Modules/AI/tests/Feature/InAppAssistanceAdversarialTest.php Modules/Core/tests/Feature/Graph
rtk php artisan test --compact Modules/AI/tests/Integration/AiRagModuleDocumentationTest.php Modules/Core/tests/Integration/CoreRagModuleDocumentationTest.php
```

Expected: PASS with no live LLM or Elasticsearch dependency.

- [ ] **Step 5: record and review the security report**

Commit aggregate pass/fail counts, policy version, fixture revision, and latency only. Do not store adversarial prompts containing real secrets or any rejected raw model output. Release requires zero unauthorized-context, corpus-crossover, identity-spoofing, or unsafe-output successes.

- [ ] **Step 6: commit module documentation and evaluation**

```bash
rtk git -C Modules/AI add tests/Feature/InAppAssistanceAdversarialTest.php docs/rag/evaluations/2026-07-in-app-security.json docs/rag/MODULE.md README.md
rtk git -C Modules/AI commit -m "docs(ai): document protected in-app assistance"
rtk git -C Modules/Core add docs/GRAPH_SYSTEM.md docs/rag/MODULE.md
rtk git -C Modules/Core commit -m "docs(core): document graph tool security boundary"
```

## Completion checklist

- [ ] Developer CLI can retrieve all approved documentation audiences and no live customer data.
- [ ] In-app requests cannot select a profile, identity, tenant, permissions, tools, system prompt, or index.
- [ ] User RAG never queries or falls back to the developer index.
- [ ] Permissions and tenant filters apply before retrieved chunks reach the LLM.
- [ ] Graph `search`, `expand`, and `stats` preserve Core permissions, ACL, provider rules, and limits.
- [ ] Graph tools have no mutation or action-replay path.
- [ ] Restricted inputs and unsafe outputs fail closed without existence disclosure.
- [ ] No rejected raw output is streamed, stored, or logged.
- [ ] In-app streaming remains unavailable in v1.
- [ ] Security and documentation tests pass with deterministic fakes.
