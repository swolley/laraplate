# Profile-driven assistant scope (R1a)

**Status:** Approved for planning

**Date:** 2026-08-06

**Program:** RAG assistant — goal **R1**, sub-project **R1a** (explicit scope). The composed grounded assistant already exists (`InAppAssistanceService::respond()` orchestrates documentation RAG + Core Graph tools + application content providers with policy, guardrails, citations, abstention, clarification, and conversation memory). R1a makes the assistant's **reach** an explicit, server-owned dimension driven by the caller's profile and verified context. R1b (assistant-level evaluation) is a separate sub-project that verifies R1a.

## Problem

The in-app assistant should be **specific to the app/module the user is in**; a CLI or a future Filament superadmin assistant should be **generic across the whole application** (the superadmin administers every module). Today:

- The application-content surface already routes contextually to the current module.
- The **documentation surface is not module-scoped**: `DocumentationRetrievalContext` filters by audience, permission, tenant, and locale, but has no module dimension, so an ERP user can retrieve unrelated CMS/Core guides.
- There is no explicit "scope" abstraction a future generic superadmin profile could inherit, and no explicit model for what an in-app user gets when no module is recognizable.

Scope is a **relevance** concern layered on top of the existing **security** boundary (audience / permission / tenant / safe projection), which is unchanged. Developer-oriented and internal content already lives in a physically separate developer index that the in-app profile cannot reach.

## Decision summary

Introduce a server-owned `AssistantScope` value object, resolved deterministically from the profile plus the server-verified application context, and applied to all three retrieval surfaces. The in-app profile scopes to the current module; the CLI profile stays generic; a future superadmin profile slots in as application-wide without new plumbing. The model never chooses scope.

## The scope model

`AssistantScope` (immutable, server-owned) carries three coordinates:

- `moduleKey: ?string` — the current module key, or null for generic.
- `dataAccess: DataAccess` — `None | Module | Application`. Governs whether the application-data surfaces (Core Graph tools, application content providers) are offered at all, and how broadly.
- `docScope: DocScope` — `Module | Application`. Governs which documentation the documentation surface may retrieve. (The developer index selection remains a separate, existing concern of the profile; `docScope` narrows within the profile's index.)

A new server-owned `AssistantScopeResolver` maps inputs to a scope:

| Profile / context | moduleKey | dataAccess | docScope |
|---|---|---|---|
| `InAppAssistance` + verified module context | module | `Module` | `Module` |
| `InAppAssistance` + no recognizable module | null | `Application` | `Application` (generic docs + authorized data via generic routing) |
| `DeveloperHelp` (CLI) | null | `None` | `Application` (developer index, unchanged) |
| *Superadmin (future seam — not built)* | null | `Application` | `Application` |

Data scope depends on the **module**, not on the frontend page. When an in-app user is not in a recognizable module (e.g. a general dashboard), the assistant stays **generic**: documentation is not module-filtered and authorized application data remains available via generic routing (the existing behavior) — always ACL/permission/tenant filtered. `dataAccess = None` is reserved for profiles that carry no runtime data (developer/CLI, and any future guest profile). Page-level context (entity/record) governs future *actions* (setting table filters, approving rows), not data scope, and is out of scope here.

The resolver's inputs are already server-verified: the profile from `AssistantAccessContextFactory`, and the application context from the request attribute `assistant_application_context` (`module`/`entity`/`record_key`) that `InAppAssistanceService::serverApplicationContext()` already reads. Client-supplied module hints remain untrusted until matched against server-known route metadata; scope can only narrow, never expand, the profile's authorized reach.

## Documentation surface scoping

Add a module dimension to `DocumentationRetrievalContext` and its Elasticsearch filter, populated from `AssistantScope.docScope`/`moduleKey`:

- `docScope = Module`: add a filter clause `metadata.module ∈ {moduleKey}` **OR** `metadata.cross_cutting_user == true`.
- `docScope = Application`: no module clause (generic retrieval within the profile's index).

This clause sits **on top of** the existing audience/permission/tenant/locale filters — the security boundary is unchanged. The filter is a relevance restriction, not an authorization one.

### The `cross_cutting_user` marker

A small set of genuinely hands-on, cross-cutting **end-user** tasks (e.g. approving a modification, why a record is visible/editable, grid export, draft recovery) must remain reachable from any module. Rather than exposing a whole module (e.g. Core) wholesale, these are marked explicitly:

- A **document-level marker** (`cross_cutting_user: true`) declared by the doc that owns the task, read by the RAG indexer into chunk metadata `cross_cutting_user`.
- Any module may mark a guide cross-cutting; Core is not special-cased. Unmarked, non-current-module docs stay out of a module-scoped result.
- The marker is **relevance metadata only**: it does not bypass audience/permission/tenant filtering, and the safe projection does not expose it in user-facing output.

Populating the marker on the few existing cross-cutting user guides is a small content task included in the plan.

## Application-data surfaces gated by `dataAccess`

`InAppAssistanceService` filters the contextual tool set by `AssistantScope.dataAccess` before the completion step:

- `dataAccess = None`: neither the application content tool nor the Core Graph tools are offered (documentation-only). Reserved for profiles without runtime data (developer/CLI, future guest); not the default for an authenticated in-app user who merely lacks a module.
- `dataAccess = Module`: application content is constrained to the current module's source (the existing contextual default); Core Graph tools are offered under their existing ACL/provider bounds.
- `dataAccess = Application`: all authorized sources/tools are offered (future superadmin path).

Per-entity module filtering **inside** the Core Graph framework is out of scope for R1a: graph tools remain ACL-bounded and are offered or withheld as a whole by `dataAccess`. Deepening graph scoping requires its own measured design.

## Integration

`InAppAssistanceService::respond()` gains one resolution step and two applications:

1. Resolve `AssistantScope` from the access context and the server application context (via `AssistantScopeResolver`).
2. Pass `docScope`/`moduleKey` into documentation retrieval so `DocumentationRetrievalContext` applies the module clause.
3. Filter the contextual tool definitions by `dataAccess` before `getNeuronToolsForDefinitions`.

Changes are surgical: the new value object + resolver, a module dimension on `DocumentationRetrievalContext` and its filter, an indexer metadata addition, and the tool-gating step. The policy capabilities (`application_content`, `in_app_rag`, `read_only_graph`), guardrails, citations, abstention, and clarification paths are unchanged.

## Authorization and information-flow invariants

1. Scope is resolved by server code from profile + verified context; conversation metadata and model output can only prioritize or narrow, never expand it.
2. The documentation module clause is relevance-only and is applied in the search query, after the existing audience/permission/tenant/locale filters, never instead of them.
3. `cross_cutting_user` is relevance metadata; it never relaxes audience, permission, or tenant filtering and is absent from user-facing output.
4. `dataAccess = None` guarantees no application-data tool is constructed for that turn.
5. A missing or unrecognized module context for an in-app user yields **generic** authorized access (`dataAccess = Application`) — never module-restricted-to-nothing and never unrestricted; ACL, permissions, and tenant still filter every source.
6. The developer index remains physically separate; `docScope` narrows within a profile's index and never crosses profiles.

## Testing

Deterministic, no live Elasticsearch or LLM (reuse the R0 fixture approach where retrieval is exercised):

- `AssistantScopeResolver`: the full mapping table (profile × presence/absence of module context × tenant) → expected `AssistantScope`.
- Documentation retrieval under `docScope = Module`: a current-module doc is retrieved; an other-module doc is excluded; a `cross_cutting_user` doc is retrieved even under module scope; under `docScope = Application` no module clause applies.
- `respond()` behavior: with module context → only in-scope docs and the module's content tool; with no module context → general docs and **no** application-data tools; developer/CLI stays generic.
- Security regression: audience/permission/tenant exclusion still holds with the module clause present.

## Scope boundaries

In scope (R1a): the scope value object and resolver, documentation module scoping + `cross_cutting_user` marker + indexer change, `dataAccess`-gated tool availability, integration in `respond()`, and RAG doc updates.

Out of scope: the assistant-level evaluation (R1b); building the superadmin profile (only its seam exists); per-entity graph module filtering; navigation/link suggestions and any action/mutation capability (non-RAG N-plans); changes to the documentation security filters.

## Success criteria

- The in-app assistant retrieves only the current module's user documentation plus explicitly cross-cutting user guides; unrelated modules' docs are excluded.
- A page without a recognizable module yields generic assistance (authorized data still available via generic routing), not documentation-only; module scoping applies only when a module is present.
- The CLI/developer profile remains generic and unchanged.
- Scope is a single server-owned value object a future superadmin profile can inherit as application-wide with no new plumbing.
- The existing security boundary (audience/permission/tenant/safe projection) is provably unchanged.

## Related documents

- `docs/superpowers/specs/2026-08-04-documentation-rag-evaluation-baseline-design.md` (R0; the R1 forward-contract this scope will be measured against in R1b)
- `docs/superpowers/specs/2026-07-16-in-app-ai-assistance-security-design.md` (normative profile/index/guardrail boundary)
- `docs/superpowers/specs/2026-07-17-application-content-retrieval-design.md` (contextual vs generic routing for the data surface)
- `docs/superpowers/specs/2026-07-16-rag-retrieval-strategy-design.md` (documentation retrieval architecture)
- Implementation touch points: `Modules/AI/app/Services/Assistance/InAppAssistanceService.php`, `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalContext.php`, `Modules/AI/app/Services/DocumentationService.php`, `Modules/AI/app/Enums/AssistantProfile.php`
