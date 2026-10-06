# Adaptive Assistance API Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Draft. Waits for the owner to settle the open questions of the spec. Tasks 1 to 6 do not depend on them. Task 7 does.

**Goal:** A client can store its preferences safely, tell the assistant where the user is, and receive proposals that the user accepts or refuses, with no usage data collected.

**Architecture:** Core hardens `PATCH /user/preferences` and adds the delete routes. AI gains a resolver that turns `context.page` into the server-owned `assistant_application_context`, a capabilities endpoint, a `ui_proposals` capability with two proposal tools, and a privacy pass over what it stores. Nothing applies a proposal on the server.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, NeuronAI, nwidart modules. `Modules/Core` is the `laraplate-core` repository, `Modules/AI` is `laraplate-ai`, both submodules of `laraplate`.

**Spec:** `docs/superpowers/specs/2026-10-05-adaptive-assistance-api-contract-design.md`

**Client plan:** the commercial UI keeps its own plan in the stack repository. It consumes this contract and nothing in this repository refers to it.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Braces on every control structure, explicit parameter and return types, `#[Override]` on overridden members, `final` and `readonly` where the siblings use them. PHPDoc over inline comments. Code, comments and docs in English.
- No new dependencies, no new base folders.
- Core never depends on AI. AI depends on Core.
- Preferences, page context and proposals are untrusted input. A test for each task proves that none of them widens access.
- No usage data is recorded. A task that adds a column or a table for behaviour is out of this plan.
- Limits live in code, not in env.
- Run tests from the `laraplate` root with `php artisan test --compact <path>`. Never with a cached config: if `bootstrap/cache/config.php` exists, run `php artisan config:clear` first.
- Format from the `laraplate` root with an explicit file list: `vendor/bin/pint --format agent <files>`.
- Tick each step's checkbox when the step is done, in the same work block.
- Closing this plan needs a `**Documented in:**` line naming the module docs that describe the behaviour.

---

### Task 1: Preference limits, namespaces and delete routes

**Files:**
- Modify: `Modules/Core/app/Http/Requests/UpdatePreferencesRequest.php`
- Modify: `Modules/Core/app/Http/Controllers/UserController.php`
- Modify: `Modules/Core/routes/auth.php`
- Create: `Modules/Core/app/Rules/PreferencesBag.php`
- Test: `Modules/Core/tests/Feature/Controllers/UserPreferencesTest.php`

- [x] **Step 1: Write failing tests.** An oversized bag, a bag deeper than the limit, a top-level key that is not a valid namespace, and a non-JSON value are each rejected with 422. A valid namespace is accepted.

- [x] **Step 2: Implement `PreferencesBag`.** One rule class holding the size, depth and key-pattern limits as constants.

- [x] **Step 3: Merge per namespace.** `updatePreferences` replaces each namespace it receives, leaves the others, and removes a namespace sent as `null`. Test that two consecutive writes to different namespaces both survive.

- [x] **Step 4: Delete routes.** `DELETE /user/preferences` clears the bag. `DELETE /user/preferences/{namespace}` clears one namespace. Both write the current session user only, with no target id, like the existing route. Test that a user cannot reach another user's bag.

- [x] **Step 5: Format and run the Core preference tests.**

---

### Task 2: Page context resolver

**Files:**
- Create: `Modules/AI/app/Http/Middleware/ResolveAssistantApplicationContext.php`
- Modify: `Modules/AI/routes/api.php`
- Modify: `Modules/AI/docs/rag/ASSISTANT_SCOPE.md`
- Test: `Modules/AI/tests/Feature/Assistance/ResolveAssistantApplicationContextTest.php` and `AssistantScopeRespondTest.php`

- [x] **Step 1: Locate the lookup.** Find the Core service that resolves a `{module}/{entity}` pair of a CRUD route to a model. Reuse it. Do not write a second registry.  Reused `DynamicEntity::tryResolveModel($entity, null, $module)`: unlike `DynamicEntity::resolve`, which the CRUD request uses and which falls back to a raw table, it matches registered models only.

- [x] **Step 2: Write failing tests.** A known `resource` yields `assistant_application_context` with module, entity and record key. An unknown resource, a malformed one, or a resource the user may not read yields no context. A forged `assistant_application_context` key inside `context` never reaches the attribute.

- [x] **Step 3: Implement the middleware.** It reads `context.page.resource` and `recordKey`, resolves them, and is the only writer of the attribute. Attach it to the message routes of the assistant.

- [x] **Step 4: Prove narrowing.** With a resolved module, scope is `Module`. Without one, scope is generic, as today. Extend `AssistantScopeRespondTest` or add a sibling.

- [x] **Step 5: Fix the documentation.** `ASSISTANT_SCOPE.md` states which class sets the attribute.

- [x] **Step 6: Format and run the assistant feature tests.**

---

### Task 3: Capabilities endpoint

**Files:**
- Create: `Modules/AI/app/Http/Controllers/CapabilitiesController.php`
- Create: `Modules/AI/app/Http/Resources/AiCapabilitiesResource.php`
- Create: `Modules/AI/app/Services/Assistance/AssistantCapabilities.php` (the four answers, so the controller stays thin)
- Modify: `Modules/AI/app/Ai/Providers/ProviderFactory.php` (`isConfigured()`)
- Modify: `Modules/AI/routes/web.php`
- Test: `Modules/AI/tests/Feature/Assistance/CapabilitiesEndpointTest.php`

- [x] **Step 1: Write failing tests.** Module feature off gives `enabled: false`. Provider not configured gives `configured: false`. `features.proposals` follows the compiled policy of `InAppAssistance`. A guest is refused (401 not signed in, 403 for the guest account).

- [x] **Step 2: Implement.** Read the existing feature flags and the model settings. Return the shape of spec section 4.3. Done: `enabled` is the FAQ/RAG switch `features.faq.enabled` (the assistant answers from the user documentation and no other global switch exists), `configured` is whether the chat provider of Settings builds (`ProviderFactory::isConfigured()`), `proposals` is whether the policy compiled for the in-app profile with `ui_proposals` still allows a tool (false until Task 4 adds the capability) and `streaming` is false until Task 7.

- [x] **Step 3: Route.** `GET app/ai/capabilities` under the `web` group with authentication.

- [x] **Step 4: Format and run.**

---

### Task 4: `ui_proposals` capability and proposal tools

**Files:**
- Create: `Modules/AI/app/Data/UiProposal.php`
- Create: `Modules/AI/app/Services/Tools/ProposePreferenceChangeTool.php`
- Create: `Modules/AI/app/Services/Tools/ProposeViewStateTool.php`
- Create: `Modules/AI/app/Services/Tools/ProposalToolText.php`
- Create: `Modules/AI/app/Services/Assistance/Proposals/` (`ProposableTargets`, `ProposableTarget`, `ProposalSchema`, `UiProposalCollector`)
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistanceOutputPolicy.php` and `AssistanceGuardrailPipeline.php`
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php`
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Test: `Modules/AI/tests/Feature/Assistance/UiProposalsTest.php`, `Modules/AI/tests/Unit/Services/Assistance/Proposals/`

- [x] **Step 1: Write failing tests.**
  - The in-app profile with the capability exposes the two tools. A profile without it does not.
  - A target outside `context.page.proposable` is rejected.
  - A value that fails the declared schema is rejected.
  - A message holds at most three proposals.
  - `reason` over 240 characters is rejected or cut, and passes the output guardrails.
  - A prompt-injection string inside `proposable` descriptions or `list` hints never changes tool availability or policy.
  - No proposal changes a preference, a record or a permission on the server.

- [x] **Step 2: Implement `UiProposal`.** An immutable value object with `toArray()` matching spec section 4.4.

- [x] **Step 3: Implement the two tools.** They validate, collect the proposal for the current request, and return text that says the proposal waits for the user.

- [x] **Step 4: Grant the capability.** Add `ui_proposals` to the in-app profile only, in `AssistantPolicyCatalog`. Add it to the capability list in `respond()`.

- [x] **Step 5: Surface proposals.** `respond()` puts the collected proposals in `message.metadata.proposals` of the assistant message.

- [x] **Step 6: Output invariant.** Add a test that a message with pending proposals never states that the change was applied. Reuse the evaluation harness if it fits.

- [x] **Step 7: Format and run.**

**Delivered 2026-10-06, divergences from the steps above.**
- The wire shape of an entry of `context.page.proposable` is `{kind, target, schema, current?, description?}`: the same `kind` and `target` as the proposal, the JSON Schema the client allows for `proposed`, the current value (it becomes the proposal's `current`) and a short text. Entries that do not pass the bounds are dropped, never repaired: at most 30 entries, a schema of at most 2000 bytes and 4 levels, a current value of at most 500 bytes, a description of at most 120 characters on one line.
- No JSON Schema package is installed and none was added. `ProposalSchema` validates a fixed subset (`type`, `enum`, `const`, number and length bounds, `items`, `properties`, `required`, `additionalProperties` as a boolean). It fails closed: a schema with any other keyword (`pattern`, `$ref`, `oneOf`, `format`, ...) or one that does not constrain the value cannot be proposed against, so a client regular expression never runs on the server.
- The model passes `proposed` as JSON text, since its type depends on the target.
- The proposal tools exist for a request only when the compiled policy allows them **and** the page declared a target of that kind. With no `proposable` list the assistant has no proposal tool.
- `reason` over 240 characters, with markup, with a link, with a control character or refused by the output guardrails is refused, not cut. A target already proposed in the message, or a fourth proposal, is refused.
- Step 6 is enforced, not only tested: `AssistanceOutputPolicy::reportPendingProposals()` replaces an answer that claims a change was made (English and Italian patterns) when the message carries a proposal, with a plain statement that the suggestion waits. The capability instruction and the tool answer say the same to the model. The evaluation cases that run it against a real model are Task 5.
- `features.proposals` of the capabilities endpoint is now true by default, since the catalog grants the capability.


---

### Task 5: Evaluation cases

**Files:**
- Modify: `Modules/AI/app/Services/Assistance/Evaluation/` datasets
- Modify: `Modules/AI/docs/rag/ASSISTANT_EVALUATION.md`

- [ ] **Step 1: Add cases.** A request that should yield a proposal, one that should not, one that tries to widen scope through a forged hint, and one that tries to make the assistant claim an applied change.

- [ ] **Step 2: Run the evaluation harness and record the result in the document.**

---

### Task 6: Privacy of stored data

**Files:**
- Modify: `Modules/AI/app/Services/ContextualSuggestionService.php`
- Modify: `Modules/AI/app/Models/ContextualSuggestion.php`
- Test: `Modules/AI/tests/Feature/`

- [ ] **Step 1: Decide what `context` keeps.** List the keys the column stores today. Keep only what the suggestion needs to be generated, and drop the rest.

- [ ] **Step 2: Retention.** Add a scheduled purge of suggestions older than a limit set in code. Test that older rows disappear and recent ones stay.

- [ ] **Step 3: User deletion.** Deleting a user removes their suggestions. Verify the existing cascade, and test it.

- [ ] **Step 4: Conversation deletion.** Deleting a conversation removes the proposals held in its message metadata. Test it.

- [ ] **Step 5: Format and run.**

---

### Task 7: Streaming agent endpoint

Blocked by open question 1 of the spec. Build only if the answer is yes. These steps move here from the commercial UI plan of 2026-06-17.

**Files:**
- Create: `Modules/AI/app/Http/Controllers/AgentController.php`
- Create: `Modules/AI/app/Services/AssistAgentService.php`
- Create: `Modules/AI/app/Policies/AssistIntentPolicy.php`

- [ ] **Step 1: Pest security boundary.** A destructive prompt gives `POLICY_DENIED`. A forged client `permissions`, an enlarged filter schema, `columns`, a dashboard catalog or `assistKind` never add capabilities or reach the provider as trusted context. Role and permission names and ACL expressions are absent from the model prompt.

- [ ] **Step 2: Reconstruct server context.** The controller accepts the validated public subset of the run input, ignores unknown authorization or system fields, and derives user, tenant, effective permissions, ACL, canonical filter schema, safe field projection, widget catalog and feature flags from the authenticated backend context before any provider call. `assistKind` is a narrowing hint.

- [ ] **Step 3: Compile the workflow policy.** Use only versioned server-owned policy identifiers. Effective capabilities are the server allowlist intersected with tenant, role and user restrictions and with backend authorization, and deny wins. Reject free-form prompt fragments stored on tenants, roles, users, conversations or request metadata.

- [ ] **Step 4: Wire the existing agent runtime.** Register only tools that survive the effective capability intersection. Each tool repeats CRUD and field authorization and the safe projection.

- [ ] **Step 5: Safe SSE response.** Return a streamed response of AG-UI JSON lines, never raw model tokens. Emit only lifecycle events, server-authored status labels, validated tool previews and interrupts, and complete messages after output validation. Assert that the endpoint cannot select or emulate `InAppAssistance`.

- [ ] **Step 6: Interrupt.** End the run with an interrupt outcome when a proposal needs the user.

---

### Task 8: Automatic conversation title

Spec section 4.7. Independent of the other tasks; the UI shows `title` when it is set and "untitled" otherwise.

**Files:**
- Create: `Modules/AI/app/Jobs/GenerateConversationTitleJob.php`
- Create: `Modules/AI/app/Services/Assistance/ConversationTitleService.php`
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php`
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Test: `Modules/AI/tests/Feature/Assistance/ConversationTitleTest.php`

- [ ] **Step 1: Write failing tests.**
  - After the first non-refused assistant reply of a conversation with a null title, the job is dispatched and sets a title. The reply itself is returned without it.
  - A title sent at creation is never overwritten. A generated title is not regenerated by later replies.
  - A refusal as first reply dispatches nothing. A later non-refused reply does.
  - The title is 2 to 5 words and at most 40 characters, with no quotes, trailing punctuation or markdown, whatever the model returns.
  - When the generation fails or the output is rejected, the title is the first words of the question, cut at a word boundary to 40 characters.
  - The model call receives only the two messages: no citations, no tool output, no page context, no tools, no corpora.
  - The title is not written to the logs in raw form.

- [ ] **Step 2: Add the capability.** `conversation_title` in `AssistantPolicyCatalog`, with empty `allowedTools` and `allowedCorpora`, used only by the service.

- [ ] **Step 3: Implement the service and the job.** Bounded call with a hard output cap, the output passing the same guardrails as assistant text, then sanitisation (trim, strip quotes and markdown, cut to the limits) and the fallback.

- [ ] **Step 4: Dispatch from `respond()`.** After the assistant message is stored, only when the conversation title is null and the reply is not a refusal.

- [ ] **Step 5: Format and run.** `vendor/bin/pint --dirty --format agent`, then the new test and the existing assistance tests.

- [ ] **Step 6: Commit.** `feat(ai): automatic conversation title from the first exchange`.

---

### Task 9: Documentation

**Files:**
- Create: `Modules/Core/docs/rag/USER_PREFERENCES_USER.md`
- Create: `Modules/Core/docs/rag/USER_PREFERENCES_DEVELOPER.md`
- Create: `Modules/AI/docs/rag/ASSISTANT_PROPOSALS_USER.md`
- Create: `Modules/AI/docs/rag/ASSISTANT_PROPOSALS_DEVELOPER.md`
- Modify: `Modules/AI/README.md`
- Modify: `docs/superpowers/specs/INDEX.md`
- Modify: `docs/superpowers/plans/INDEX.md`

- [ ] **Step 1: User guides.** What the user can store, how to see it, reset it, and what a proposal is and is not.

- [ ] **Step 2: Developer guides.** The wire contract: preferences, `context.page`, capabilities, proposals. Follow the sibling `rag` documents for front matter and audience.

- [ ] **Step 3: State the privacy rule.** The AI README says that no usage data is collected and lists the data the instance stores.

- [ ] **Step 4: Indexes.** Keep both indexes in step with the spec and this plan.

- [ ] **Step 5: Close the plan.** Add the delivery status and the `**Documented in:**` line.
