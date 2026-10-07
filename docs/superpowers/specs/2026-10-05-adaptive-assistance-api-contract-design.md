# Adaptive assistance: public API contract

**Status:** Draft for owner review. Nothing in this document is built.

**Date:** 2026-10-05

**Modules:** `Modules/Core` (preferences, page context resolution), `Modules/AI` (capabilities, proposal tools, privacy of stored suggestions)

**Related:** `2026-07-16-in-app-ai-assistance-security-design.md` (profiles, fail-closed guardrails, server-owned identity), `2026-08-06-assistant-profile-scope-design.md` (scope resolved from the module of the page), `2026-09-16-in-app-assistant-tools-design.md` (approval-gated writes, invariant that a pending action is never reported as done), `2026-09-29-ai-model-selection-and-setting-actions-design.md` (global settings)

---

## 1. Purpose

Let any client offer an experience that adapts to its user, without the application changing by itself.

The contract covers three things:

1. where a client stores the preferences of a user;
2. how a client tells the assistant where the user is;
3. how the assistant proposes a change that the user accepts or refuses.

The contract is public. A client written by anyone implements it. The backend knows no frontend by name.

## 2. Decisions

Agreed with the project owner in conversation, 2026-10-02 to 2026-10-05.

- **Nothing adapts by itself.** Workflow, results and layout change only after a user gesture. A menu that reorders itself, a default that shifts, a result list that changes order: none of these is allowed.
- **The assistant proposes, the client applies.** A proposal is applied after confirmation, through the same path as a manual change. The assistant never writes a preference.
- **No telemetry.** The project does not collect, send or ask for usage data. The instance stores what a user's own request or setting creates, and nothing recorded in the background. Learning from usage, if it is ever built, runs on the client or on an export that the user triggers and previews in full.
- **Client-neutral.** The backend contains no knowledge of a specific frontend. Meaning specific to a client lives in a namespace that the client owns.
- **Authorization never widens.** Preferences, page context and proposals are untrusted input. They narrow or reorder what a user already sees. They never grant a field, a record, a tool or a permission.
- **A proposal is data.** It names a key and a value. It never carries code, markup, a URL to follow or a tool call.

## 3. Baseline

Read from the module repositories on 2026-10-05, at depth 1.

| Area | State |
|---|---|
| Preferences store | `users.preferences` is a JSON column. `PATCH /user/preferences` validates `required\|array` and replaces the whole bag. The bag is echoed by `UserInfoResponse`. No first-party client writes it yet. |
| Page context | `InAppAssistanceService::serverApplicationContext()` reads the request attribute `assistant_application_context` with `module`, `entity`, `record_key`. `ASSISTANT_SCOPE.md` says it is set server-side before `respond()` runs. No production code sets it. Only tests do. In production the assistant is always generic. |
| Message request | `SendMessageRequest` accepts a `context` array and rejects control keys such as `profile`, `tools`, `permissions`. Only `accessibility`, `locale`, `response_format` and `verbosity` are kept, in the metadata of the user message. |
| Assistant output | `message.metadata` carries `citations` or `refused`. |
| Writes by the assistant | Amended 2026-10-07: `CrudToolProvider` offers create, update, delete and bulk (never approve), opt-in per entity, under the permissions and ACL of the user. A write is a proposal in `metadata.writes` that the person confirms through `/app/crud/update/ai/assistant-writes/{id}/confirm`; see `2026-10-07-assistant-governed-writes-design.md`. The route `messages-with-tools` no longer answers `action_requests`. |
| Conversation title | `conversations.title` is nullable and set only by the caller at creation. Nothing generates it, so a client that does not send one lists every conversation as untitled. |
| Suggestions | `ContextualSuggestion` stores a `context` JSON per user and suggestion, with `dismissed_at`. It is off by default and rate limited. |

## 4. Contract

### 4.1 Preferences

- The store stays `users.preferences`, one JSON object per user.
- Top-level keys are namespaces. A namespace matches `^[a-z][a-z0-9_.-]{0,39}$`. A client uses one namespace of its own.
- Generic limits apply to the whole bag: a maximum serialized size, a maximum depth, JSON-compatible values only. Proposed values: 64 KiB and depth 6. They live in code, not in env.
- `PATCH /user/preferences` replaces each namespace it receives and leaves the others untouched. A namespace sent as `null` is removed. This replaces the current whole-bag replacement, so two clients or two devices cannot erase each other.
- `DELETE /user/preferences` clears the bag. `DELETE /user/preferences/{namespace}` clears one namespace.
- A namespace may register a JSON Schema in Core. Without one, only the generic limits apply. Preferences belong to the user, are cosmetic and are never read for authorization, so an unregistered namespace is accepted.

### 4.2 Page context

- The client sends `context.page` with each message:

```json
{
  "schemaVersion": 1,
  "resource": "erp/orders",
  "recordKey": 42,
  "locale": "it",
  "list": { "filters": {}, "columns": [], "viewMode": "table" },
  "dashboard": { "layout": [] },
  "proposable": []
}
```

- A resolver in `Modules/AI` maps `resource` to a known module and entity, using the same lookup Core uses for `{module}/{entity}` routes. An unknown resource yields no context and the assistant stays generic.
- The resolver, and nothing else, sets `assistant_application_context`. A client cannot set it.
- `list`, `dashboard` and `proposable` are hints. They reach the model only as untrusted data, only through the proposal tools, bounded in size. They never grant field visibility, tools or scope.
- The existing control-plane check of `SendMessageRequest` keeps applying to the whole `context`.

### 4.3 Capabilities

`GET /app/ai/capabilities` answers:

```json
{
  "enabled": true, "configured": true,
  "features": { "proposals": true, "writes": false, "streaming": false },
  "actions": [{ "entity": "core.role", "operation": "update", "kind": "write", "requires_approval": false }]
}
```

`proposals` is true only when the compiled policy of the profile holds the capability `ui_proposals`. A client that sees `false` hides the proposal UI and sends no `proposable` hints.

*Amended 2026-10-07.* `writes` is true when the operator opted an entity into a write operation and the policy grants `governed_writes`. `actions` is what the assistant may do for the signed-in person (`kind` is `read` or `write`; `requires_approval` says a confirmed write then goes to a vote); a client shows it. The messages carry the proposed writes in `metadata.writes`, and the person confirms or rejects each through `POST /app/crud/update/ai/assistant-writes/{id}/confirm|reject` (see `Modules/AI/docs/TOOLS_USAGE_EXAMPLE.md`).

### 4.4 Proposals

```json
{
  "id": "uuid",
  "schemaVersion": 1,
  "kind": "preference",
  "target": { "namespace": "ui", "key": "defaultListLayout" },
  "current": "table",
  "proposed": "cards",
  "reason": "You open this list on a phone most of the time."
}
```

- Kinds: `preference`, targeting a namespace and a key, and `view_state`, targeting a resource and a view.
- The tools `propose_preference_change` and `propose_view_state` create proposals. They belong to the capability `ui_proposals`, granted to `InAppAssistance` only in `AssistantPolicyCatalog`.
- A tool accepts a target only if it appears in `context.page.proposable`. It validates `proposed` against the JSON Schema that the client declared there, and rejects what the schema rejects. A client can only widen what it proposes to its own user.
- `reason` is plain text, at most 240 characters, in the locale of the user. It passes the output guardrails like any assistant text.
- A message carries at most three proposals, in `message.metadata.proposals`.
- A tool never applies anything. It tells the model that the proposal waits for the user. The assistant must not say that a waiting proposal was applied, the same invariant as 2026-09-16.
- Accepting is a client action. The server keeps no record of acceptance or refusal. "Do not suggest this again" is stored by the client in its own namespace, so the user can see it, export it and delete it.

### 4.5 Transport

The base transport is the existing non-streaming message endpoint. The profile validates the complete output before delivery, and the contract keeps that rule.

A streaming agent endpoint, `/app/ai/agent`, may be built later as a wrapper. It would emit lifecycle events and complete validated messages, never raw tokens. Whether to build it is an open question.

### 4.6 What the instance stores

| Data | Owner | Rule |
|---|---|---|
| `users.preferences` | The user | Readable, resettable and exportable by the user. |
| `ContextualSuggestion.context` | The user | Needs a retention limit and a purge when the user is deleted. Reviewed in the plan. |
| Conversation message metadata, now with proposals | The user | Removed with the conversation. |

No new table is added by this contract.

### 4.7 Conversation title

A conversation takes a short title by itself, from its first question and first answer.

- **When.** After the first assistant reply that is not a refusal, if `title` is still null. The reply is returned without waiting: a queued job writes the title, and clients read it the next time they list or load the conversation. A refusal as first reply creates no title until a later reply is not one.
- **Input.** The first user message and the first assistant message, as stored and already validated. Never retrieved passages, tool output, citations or page context.
- **Output.** Plain text in the locale of the user: 2 to 5 words, at most 40 characters, no quotes, no trailing punctuation, no markdown. The result passes the same output guardrails as any assistant text. If the generation fails or is rejected, the title falls back to the first words of the question, cut at a word boundary to the same limit.
- **Policy.** A dedicated capability `conversation_title` in `AssistantPolicyCatalog`, with no tools and no corpora, so the call can read nothing but those two messages. Invariant 1 of `2026-09-16-in-app-assistant-tools-design.md` applies: no caller assembles its own list.
- **Precedence.** A title sent by the caller at creation wins and is never overwritten. A generated title is set once and never regenerated. Renaming by the user is a separate change.
- **Contract.** `title` already appears in the conversation list and detail. The client never sends a title for generation and never receives a separate event for it.
- **Stored data.** The title is user data: removed with the conversation, not logged in raw form, not used for anything but display.

## 5. Out of scope

- Any collection of usage data, by the project or by an operator.
- Applying a proposal on the server.
- Data mutations through proposals. Records change through the existing CRUD tools, with permissions and approvals.
- Anything inside a frontend. The contract states what a client sends and receives.
- Global settings in `core_settings`. They keep their existing path.

## 6. Open questions

1. Build the streaming endpoint now, or ship the base transport first and wrap it later?
2. Register namespace schemas in Core now, or ship with the generic limits only?
3. Default preferences by role or tenant: in this contract, or a later one?
4. `view_state` proposals can target saved views. Server-side saved views do not exist. Is a view only ever local to a client?
5. Title generation: which model and budget serve it, and whether it runs on the configured assistant provider or a smaller one. It must stay a bounded call with a hard output cap.
