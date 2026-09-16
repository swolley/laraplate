# In-app Assistant Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (or executing-plans). Steps use checkbox (`- [ ]`) tracking.

**Goal:** Give `ActionRequest` a producer again. The in-app assistant exposes approval-gated write tools through a policy capability, so a model's proposed write becomes a request a person approves, and so every future surface inherits the same rule instead of reimplementing it.

**Architecture:** One new entry in `AssistantPolicyCatalog::capabilities`. `InAppAssistanceService::contextualTools()` adds the approval-wrapped tools when the compiled policy allows them, and `respond()` carries any pending requests in the assistant message metadata. No new approval machinery: `ToolRegistry::getAllNeuronToolsWithApproval()`, `RiskClassifier`, `ActionRequestService` and `ExecuteActionRequestJob` already exist and work.

**Tech Stack:** PHP 8.5, Laravel 12, Pest. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-09-16-in-app-assistant-tools-design.md`

## Global Constraints

- `declare(strict_types=1);`, braces, explicit types, `final`/`readonly` as the surrounding code does, `#[Override]` when overriding.
- **No caller assembles a tool list.** If a step has you writing an array of tool names anywhere outside `AssistantPolicyCatalog`, it is the wrong step.
- **A pending action is never reported as done.** This is a correctness rule, not a wording preference: a person who is told a write happened stops checking the approval queue.
- Tool arguments are untrusted input and are validated as such, regardless of having come from a model.
- Tests in `Modules/AI/tests/`, Pest, factories for setup, no classes declared in test files. `vendor/bin/pint --dirty --format agent` before finalising. Commit inside `Modules/AI`.

**Verified touch points (read before coding):**
- `Modules/AI/app/Services/Assistance/InAppAssistanceService.php:73` — the capability list `respond()` compiles; `contextualTools()` just below it.
- `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php` — `capabilities` map, each an `AssistantPolicyRuleSet(instruction, allowedCorpora, allowedTools, allowedFields)`; `$in_app_tools` at the top lists the read-only set.
- `Modules/AI/app/Services/Tools/ToolRegistry.php` — `getAllNeuronToolsWithApproval(Conversation, ActionRequestService, RiskClassifier, array &$pending)`, currently called by nothing outside its own tests.
- `Modules/AI/docs/TOOLS_USAGE_EXAMPLE.md` — how the flow behaved from the superseded entry point. Read it for the shape of the metadata, not as a description of current code.

---

### Task 1: settle the risk question before writing code

The spec leaves one question open on purpose, and it changes the code.

- [ ] **Step 1:** Read what `RiskClassifier` actually classifies today and write the answer in the spec under a *Decision* line: does risk stay a function of the tool name, or does it also read the entity and the operation? Deleting an ERP document and updating a draft note reaching the same risk level is the case that decides it.
- [ ] **Step 2:** If the answer is "tool name only", record explicitly which tools are `high` and therefore always wait for a person. If it is "entity and operation too", that is its own task and this plan pauses until it exists, because building the capability on a classifier about to change shape means building it twice.

---

### Task 2: the capability

**Files:**
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php`
- Test: `Modules/AI/tests/Feature/Assistance/AssistantToolCapabilityTest.php`

- [ ] **Step 1: Test first** — a profile that lists the capability compiles a policy whose `allowedTools` contains the write tools; a profile that does not list it compiles one that does not, and `DeveloperHelp` is that profile. The negative assertion is the valuable one: it is what stops a future tool leaking into a surface nobody reviewed.

- [ ] **Step 2: Implement.** Add an `approval_gated_tools` entry to `capabilities`, alongside `in_app_rag`, `read_only_graph` and `application_content`, with the write tool names in `allowedTools` and an `instruction` stating the contract the model must follow: it may propose these actions, they do not take effect until a person approves, and it must not describe a proposed action as completed.

- [ ] **Step 3:** run the test (PASS), pint, commit: `feat(ai): policy capability for approval-gated tools`.

---

### Task 3: wire it into `respond()`

**Files:**
- Modify: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`
- Test: `Modules/AI/tests/Feature/Assistance/AssistantApprovalFlowTest.php`

- [ ] **Step 1: Test first** — with the capability enabled and a faked completion that proposes a write, one `ActionRequest` is created for the conversation's user, the assistant message metadata carries `tool_calls` with id, tool, status and risk level, and **no write has been applied to the target record**. Then: approving the request through the existing service executes it. With the capability disabled, the same completion creates nothing. Use the `$completion` closure seam so no live model is needed.

- [ ] **Step 2: Implement.** In `contextualTools()`, when the compiled policy allows the write tools, obtain them from `ToolRegistry::getAllNeuronToolsWithApproval()` with the conversation, `ActionRequestService`, `RiskClassifier` and a `$pending` array by reference. In `respond()`, when `$pending` is non-empty, add `tool_calls` to the assistant message metadata. Change nothing about scope resolution, guardrails or the prompt context.

- [ ] **Step 3: Guard the claim.** The output guardrail already validates the text. Add an assertion at the edge of this flow that a response carrying pending requests is stored with them in metadata, so a client can always distinguish proposed from done without parsing prose. The prose itself is governed by the capability `instruction` from Task 2.

- [ ] **Step 4:** run the tests (PASS), pint, commit: `feat(ai): propose writes through approval-gated tools in the assistant`.

---

### Task 4: which profiles get it

**Files:**
- Modify: `Modules/AI/app/Services/Assistance/Policies/AssistantPolicyCatalog.php` (profile entries)
- Test: extend `Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php`

- [ ] **Step 1:** Add the capability to the in-app profile only. `DeveloperHelp` runs from the console with `userId: null` and must not reach it; assert that, rather than relying on it being true today.
- [ ] **Step 2:** Assert that the acting user's permissions still bound what a tool can do, so the capability narrows and never widens: a user who cannot update an entity gets no successful write through the assistant either.
- [ ] **Step 3:** run the tests (PASS), pint, commit: `feat(ai): grant approval-gated tools to the in-app profile only`.

---

### Task 5: documentation

**Files:**
- Modify: `Modules/AI/docs/rag/MODULE.md` (the *Perimeters* caveat saying `ActionRequest` has no producer)
- Modify: `Modules/AI/docs/TOOLS_USAGE_EXAMPLE.md` (status note at the top)
- Modify: `Modules/AI/docs/GLOSSARY.md` and `Modules/AI/docs/rag/GLOSSARY.md` (the `sendMessageWithTools` entry describing the gap)
- Modify: `Modules/AI/docs/rag/ASSISTANT_DATA_TOOLS_USER.md`

- [ ] **Step 1:** Remove the caveat from *Perimeters*: the producer exists again, and the doc must say how it is reached now, not how it used to be.
- [ ] **Step 2:** Rewrite the status note on `TOOLS_USAGE_EXAMPLE.md`: the flow is live again, reached through a capability rather than a method. Keep the document, correct the route.
- [ ] **Step 3:** Update both glossary entries, which currently end with "no code path creates `ActionRequest` rows today".
- [ ] **Step 4:** Document for the operator what they will see: an assistant that proposes an action, where the approval appears, and that nothing happens until they act.
- [ ] **Step 5:** Add the `**Documented in:**` line to this plan and a `## Delivery status (date)` section. pint, commit: `docs(ai): approval-gated assistant tools`.

---

## Final verification
- [ ] `php artisan test --compact Modules/AI/tests/Feature/Assistance Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php Modules/AI/tests/Integration/ToolRegistryTest.php`
- [ ] `vendor/bin/pint --dirty --format agent` clean.

## Out of scope (per spec)
Moving the approval mechanism to Core, which belongs to MCP phase 2; MCP write tools; new tools of any kind; extending `RiskClassifier` beyond what Task 1 settles.

## Notes for the executor
- Nothing in this plan builds approval machinery. All of it exists and is tested; what was missing was a caller. If you are writing an `ActionRequest` by hand, you have gone off the path.
- When MCP writes are opened, `ActionRequestService` and `RiskClassifier` move to Core behind a contract with `Modules/AI` overlaying the implementation, the way search works with `ISearchPlanner`. Do not pre-emptively generalise them here: the point of reconnecting this path first is to exercise the mechanism with real use before it is shaped into a contract for two consumers.
