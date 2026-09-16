# In-app assistant tools: reconnecting the approval-gated path

**Status:** draft for review

**Date:** 2026-09-16

**Module:** AI.

## Problem

Laraplate has a complete human-in-the-loop mechanism for letting a language model act on data, and nothing reaches it.

`ToolRegistry::getAllNeuronToolsWithApproval()` wraps tools so that a model's proposed call becomes an `ActionRequest` classified by `RiskClassifier`; `ActionRequestService` creates the row; `ActionRequestController` lists, confirms, approves and rejects; `ExecuteActionRequestJob` runs the handler once approved. All of it works and is tested.

Its only caller was `ChatService::sendMessageWithTools()`, which was superseded when the HTTP boundary moved to `InAppAssistanceService` and has since been removed. So today `ActionRequest` records are managed and executed by code that nothing feeds: the controller and the job administer a table with no producer. The in-app assistant deliberately exposes read-only tools, which by design never create an `ActionRequest`.

`Modules/AI/docs/DESIGN_DECISIONS.md` already recorded the symptom (*"Currently, this never happens because `sendMessageWithTools()` is not exposed"*). It was noticed, written down, and then lost from view. This spec is the decision that ends that state, in one direction or the other.

## Decision

Reconnect it, through the assistant, as a **policy capability**. Tooling is the area of the application where an assistant creates the most value at the least risk, because a write it proposes does not happen until a person says so.

The mechanism is deliberately not a method that assembles tools for a caller.

- `respond()` already compiles a policy for its profile and a list of capabilities (`application_content`, `in_app_rag`, `read_only_graph`) and filters available tools against the policy's `allowedTools`.
- Write tools arrive as one more capability, with the approval wrapper applied to them.
- Which surfaces get it is therefore decided in `AssistantPolicyCatalog`, per profile, and nowhere else.

### Why a capability, and not a parameter or a second method

Because the assistant is not the only future caller, and the alternative is a list maintained by hand in several places.

An MCP session is bound to one specific user; an eventual `/api/v1` exposure of "a few safe, controlled tools" is bound to a token belonging to one user. Both need the same question answered: *which tools may this principal reach, and which of them need a human first?* If the answer lives in a compiled policy, each new surface inherits it by naming the capability. If it lives inside `respond()`, each new surface reimplements it, and the implementations drift apart at the first tool added to one and not the others.

This also keeps the failure mode boring. A tool that must never be exposed is absent from every profile's capability list, rather than absent from one code path and forgotten in another.

### What the assistant returns

A response whose tool calls produced pending approvals carries them in the assistant message metadata, as the superseded path did:

```php
'tool_calls' => [['id' => …, 'tool' => …, 'status' => …, 'risk_level' => …]]
```

The assistant says what it has proposed and that it is waiting. It must not claim the action was performed: a model that reports a pending write as done is worse than one that cannot write at all, because the person stops checking.

## Risk classification is the gate that decides whether this is usable

`RiskClassifier` maps a tool name to `low`, `medium` or `high`, and the risk level decides whether a request is auto-confirmed or waits for a person.

Requiring approval for every write would make the assistant useless for the ordinary cases it exists to serve, and a safety gate that is merely annoying gets switched off wholesale by whoever finds it annoying. Requiring it for none is an unattended model writing to the database.

Open question, and the one to settle with care: whether risk should read only the tool name, or also the **entity and the operation**. Deleting an ERP document and updating a draft note are not the same act, and today the classifier cannot tell them apart. The answer shapes how much this feature is trusted, so it is a product decision, not an implementation detail.

## Relationship to MCP

The MCP server ships read-only in phase 1, and one reason is this same mechanism: an MCP write is model-driven and untrusted by that spec's own premise, which makes it the surface that most needs approval and currently has the least.

Consequence recorded there and repeated here because it constrains this work: `ActionRequestService` and `RiskClassifier` live in `Modules/AI`, and MCP must not depend on the AI module. When MCP writes are opened, the approval mechanism moves to Core behind a contract, with `Modules/AI` overlaying its implementation, exactly as search already works with `ISearchPlanner` and `IReranker`.

**That move does not block this spec.** The in-app assistant may depend on `Modules/AI` because it is `Modules/AI`. Reconnecting the path here is the cheapest way to get the mechanism exercised by real use before it is generalised, and generalising an unexercised mechanism is how a contract ends up shaped for nobody.

## Security invariants

1. A tool is reachable only if the compiled policy for the caller's profile allows it. No caller assembles its own tool list.
2. Authorization is unchanged and additional: the tool executes under the acting user's permissions and ACL, so the capability can only narrow what that user could already do.
3. A write proposed by a model is never executed inside the request that proposed it. It becomes an `ActionRequest` and waits for its risk level's rule.
4. The assistant reports pending actions as pending. No message may state that a waiting action has been carried out.
5. Tool arguments come from a model steered by text we do not fully control, and are validated as untrusted input, not trusted because a model produced them.

## Scope boundaries

In scope: a capability entry for approval-gated tools, its wiring in `respond()`, pending requests surfaced in the assistant message metadata, the profiles that receive it, tests including a profile that does not receive it, and documentation of the reconnected flow.

Out of scope: moving the approval mechanism to Core (belongs to MCP phase 2); MCP write tools; extending `RiskClassifier` beyond the tool name, unless the open question above is settled first; any new tool, this spec reconnects the ones that exist.

## Related

- `Modules/AI/app/Services/Tools/ToolRegistry.php` (`getAllNeuronToolsWithApproval`), `ActionRequestService`, `Tools/RiskClassifier`, `ActionRequestController`, `ExecuteActionRequestJob`
- `Modules/AI/app/Services/Assistance/InAppAssistanceService.php` (`respond()`, `contextualTools()`), `Policies/AssistantPolicyCatalog.php`
- `docs/superpowers/specs/2026-09-12-mcp-server-design.md`, section *Write approval*
- `Modules/AI/docs/TOOLS_USAGE_EXAMPLE.md` (how the flow worked before the entry point was removed; kept as the record)
- `Modules/AI/docs/rag/MODULE.md`, section *Perimeters* (the caveat describing this gap)
