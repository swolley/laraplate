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
- Test: `Modules/Core/tests/Feature/` (follow the sibling layout for user endpoints)

- [ ] **Step 1: Write failing tests.** An oversized bag, a bag deeper than the limit, a top-level key that is not a valid namespace, and a non-JSON value are each rejected with 422. A valid namespace is accepted.

- [ ] **Step 2: Implement `PreferencesBag`.** One rule class holding the size, depth and key-pattern limits as constants.

- [ ] **Step 3: Merge per namespace.** `updatePreferences` replaces each namespace it receives, leaves the others, and removes a namespace sent as `null`. Test that two consecutive writes to different namespaces both survive.

- [ ] **Step 4: Delete routes.** `DELETE /user/preferences` clears the bag. `DELETE /user/preferences/{namespace}` clears one namespace. Both write the current session user only, with no target id, like the existing route. Test that a user cannot reach another user's bag.

- [ ] **Step 5: Format and run the Core preference tests.**

---

### Task 2: Page context resolver

**Files:**
- Create: `Modules/AI/app/Http/Middleware/ResolveAssistantApplicationContext.php`
- Modify: `Modules/AI/routes/api.php`
- Modify: `Modules/AI/docs/rag/ASSISTANT_SCOPE.md`
- Test: `Modules/AI/tests/Feature/Assistance/`

- [ ] **Step 1: Locate the lookup.** Find the Core service that resolves a `{module}/{entity}` pair of a CRUD route to a model. Reuse it. Do not write a second registry.

- [ ] **Step 2: Write failing tests.** A known `resource` yields `assistant_application_context` with module, entity and record key. An unknown resource, a malformed one, or a resource the user may not read yields no context. A forged `assistant_application_context` key inside `context` never reaches the attribute.

- [ ] **Step 3: Implement the middleware.** It reads `context.page.resource` and `recordKey`, resolves them, and is the only writer of the attribute. Attach it to the message routes of the assistant.

- [ ] **Step 4: Prove narrowing.** With a resolved module, scope is `Module`. Without one, scope is generic, as today. Extend `AssistantScopeRespondTest` or add a sibling.

- [ ] **Step 5: Fix the documentation.** `ASSISTANT_SCOPE.md` states which class sets the attribute.

- [ ] **Step 6: Format and run the assistant feature tests.**

---

### Task 3: Capabilities endpoint

**Files:**
- Create: `Modules/AI/app/Http/Controllers/CapabilitiesController.php`
- Create: `Modules/AI/app/Http/Resources/AiCapabilitiesResource.php`
- Modify: `Modules/AI/routes/web.php`
- Test: `Modules/AI/tests/Feature/`

- [ ] **Step 1: Write failing tests.** Module feature off gives `enabled: false`. Provider not configured gives `configured: false`. `features.proposals` follows the compiled policy of `InAppAssistance`. A guest is refused.

- [ ] **Step 2: Implement.** Read the existing feature flags and the model settings. Return the shape of spec section 4.3.

- [ ] **Step 3: Route.** `GET app/ai/capabilities` under the `web` group with authentication.

- [ ] **Step 4: Format and run.**

---

### Task 4: `ui_proposals` capability and proposal tools

**Files:**
- Create: `Modules/AI/app/Data/UiProposal.php`
- Create: `Modules/AI/app/Services/Tools/ProposePreferenceChangeTool.php`
- Create: `Modules/AI/app/Services/Tools/ProposeViewStateTool.php`
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php`
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Test: `Modules/AI/tests/Feature/Assistance/`

- [ ] **Step 1: Write failing tests.**
  - The in-app profile with the capability exposes the two tools. A profile without it does not.
  - A target outside `context.page.proposable` is rejected.
  - A value that fails the declared schema is rejected.
  - A message holds at most three proposals.
  - `reason` over 240 characters is rejected or cut, and passes the output guardrails.
  - A prompt-injection string inside `proposable` descriptions or `list` hints never changes tool availability or policy.
  - No proposal changes a preference, a record or a permission on the server.

- [ ] **Step 2: Implement `UiProposal`.** An immutable value object with `toArray()` matching spec section 4.4.

- [ ] **Step 3: Implement the two tools.** They validate, collect the proposal for the current request, and return text that says the proposal waits for the user.

- [ ] **Step 4: Grant the capability.** Add `ui_proposals` to the in-app profile only, in `AssistantPolicyCatalog`. Add it to the capability list in `respond()`.

- [ ] **Step 5: Surface proposals.** `respond()` puts the collected proposals in `message.metadata.proposals` of the assistant message.

- [ ] **Step 6: Output invariant.** Add a test that a message with pending proposals never states that the change was applied. Reuse the evaluation harness if it fits.

- [ ] **Step 7: Format and run.**

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

### Task 8: Documentation

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
