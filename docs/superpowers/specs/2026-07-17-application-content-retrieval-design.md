# Application content retrieval providers

**Status:** Approved direction

**Date:** 2026-07-17

## Decision summary

Laraplate will let the authenticated in-app assistant retrieve grounded evidence from application-module data through a general provider contract. Core owns `ApplicationContentRetrievalProviderInterface`, its registry, authorization gateway, and neutral evidence DTOs. The AI module consumes that gateway through one contextual read-only tool. Optional modules register providers without depending on AI.

CMS is the first provider and proves the extension seam against searchable content records. The contract contains no CMS-specific concepts and is intended to support later providers from ERP, MES, or other modules.

Phase 1 serves authenticated application users only. Every retrieval executes under server-owned identity, tenant, permissions, ACL, locale, provider rules, and safe-field projection. Phase 2 may evaluate a public content assistant, but no public endpoint, profile, or access mode is authorized by this specification.

## Product boundary

Laraplate remains a modular, general-purpose headless platform. Application content retrieval is infrastructure for modules, not a vertical feature set.

The committed product capability is:

- a common retrieval contract;
- authorized provider discovery;
- bounded evidence retrieval;
- canonical citations;
- assistant tool integration;
- evaluation and safe abstention;
- one CMS reference provider.

The following are not part of this phase:

- domain-specific agents or workflows;
- automatic knowledge-graph extraction;
- automatic entity resolution across time;
- direct image, audio, or video understanding;
- autonomous report or content generation;
- public anonymous assistance;
- copying application records into the documentation RAG index;
- introducing a mandatory new search backend.

## Why Graph tools are not sufficient alone

Core Graph and application content retrieval solve related but different problems:

| Capability | Core Graph tools | Application content retrieval |
|---|---|---|
| Locate authorized records | Yes | Yes |
| Traverse explicit relations | Yes | No |
| Compute bounded graph statistics | Yes | No |
| Find relevant passages inside searchable content | Limited | Yes |
| Return evidence-oriented excerpts and citations | Limited | Yes |
| Rank lexical/vector evidence | No | Yes |

The assistant may combine both capabilities. For example, it can retrieve passages through application content search and then expand relations for an authorized record through Core Graph. Neither capability replaces documentation RAG.

## Three independent retrieval surfaces

```mermaid
flowchart LR
  Question[Authenticated application question] --> Policy[Server-owned assistance policy]
  Policy --> Docs[User assistance documentation RAG]
  Policy --> Graph[Core Graph tools]
  Policy --> Content[Application content retrieval tool]
  Docs --> Context[Authorized evidence context]
  Graph --> Context
  Content --> Context
  Context --> LLM[In-app assistant]
  LLM --> Output[Mandatory output validation]
```

1. **Documentation RAG** explains how to use Laraplate and its modules.
2. **Core Graph tools** retrieve live records, relations, and statistics.
3. **Application content retrieval providers** return ranked evidence from module-owned searchable content.

The surfaces keep separate indexes, authorization paths, provenance, evaluation datasets, and failure behavior.

## Ownership and dependency direction

### Core

Core owns the neutral extension boundary because every optional business module already depends on Core, while AI remains optional. Core provides:

- `ApplicationContentRetrievalProviderInterface`;
- `ApplicationContentRetrievalProviderRegistryInterface` and registry;
- typed query, authorization, hit, provenance, and result DTOs;
- `ApplicationContentRetrievalService` as the only gateway;
- permission and ACL resolution before provider execution;
- result bounds and invariant validation.

### AI

AI owns assistant orchestration:

- contextual tool registration;
- conversion between tool arguments and Core query DTOs;
- capability selection by server-owned profile;
- context-budget enforcement;
- instruction-neutral evidence formatting;
- answer citations, abstention, and output validation.

AI does not know how a module stores or searches its data.

### Provider modules

Each module may register zero or more providers. A provider owns:

- supported source keys;
- mapping from a source key to its Core entity;
- application of server-created ACL filters inside its search query;
- search strategy and fallback;
- safe excerpt construction;
- safe source labels and canonical application references;
- module-specific visibility and validity rules;
- provider-specific evaluation cases.

A module does not register tools directly and does not depend on AI.

## Core contract

The canonical contract name is `ApplicationContentRetrievalProviderInterface`, following existing Core provider conventions.

Conceptual API:

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

`ApplicationContentRetrievalService` receives the authenticated Laravel request separately from the public query. It resolves the registered provider, calls `AuthorizationService::ensurePermission(..., 'select')`, resolves ACL filters, constructs `ApplicationContentAuthorization`, invokes the provider, and validates the returned evidence.

The public query contains only:

- server-approved source key;
- natural-language query;
- locale;
- bounded result limit.

It does not contain user ID, tenant ID, roles, permissions, ACL, connection, class name, index name, raw query DSL, arbitrary field selection, or a system prompt.

## Evidence contract

Providers return evidence hits, not arbitrary model serialization. Each `ApplicationContentHit` contains:

- opaque hit ID;
- source key, module, and entity;
- record key suitable for an authorized follow-up request;
- bounded plain-text excerpt;
- safe source label;
- canonical application reference;
- locale;
- normalized retrieval score when meaningful;
- source revision or update marker when available;
- retrieval strategy identifier;
- truncation indicator.

Evidence metadata is typed and allowlisted. Providers cannot return unrestricted model arrays, hidden attributes, raw database fields, internal class names, storage paths, ACL expressions, permission names, or search-engine payloads.

The LLM receives only a sanitized prompt projection of the evidence. Control-plane authorization data remains outside the prompt.

## Registry semantics

The registry follows the existing Core Graph provider pattern:

- registration is keyed by a normalized, stable source key;
- duplicate registration for the same key fails during boot;
- an unknown key returns an unavailable capability, not a dynamic class lookup;
- only installed and enabled modules can register providers;
- provider availability is server-owned and cannot be selected through conversation metadata;
- registry ordering is deterministic;
- the registry exposes no writable operations.

Phase 1 performs single-provider retrieval. Fan-out, score fusion, and cross-provider deduplication are deferred until a measured use case justifies their cost.

## Phase 1 CMS provider

CMS registers `cms.contents` as its first source key. The first implementation reuses the existing Core searchable model and orchestrated search capabilities rather than creating another index.

The provider:

- accepts only the canonical source key;
- uses the existing content search schema and configured search engine;
- applies Core select permission and ACL filters before search results are materialized;
- respects locale, translations, validity, deletion, and module-defined visibility;
- rehydrates authorized records before projection instead of exposing raw search `_source` data;
- projects only approved title, bounded text, path, locale, revision, and score fields;
- returns canonical references to records the same user may open;
- returns no media binary, unrestricted component payload, internal metadata, or hidden relation data;
- degrades from vector/hybrid to the supported Core search path with explicit internal diagnostics;
- returns an empty authorized result when no evidence is available.

The initial provider is a record-level evidence baseline. A separate chunk or passage index is not authorized until evaluation shows that long records materially reduce hit rate, citation precision, or groundedness.

## AI tool contract

The in-app assistant receives one contextual read-only tool:

```text
application_content_search
```

Arguments:

- `source`;
- `query`;
- optional `locale`;
- optional bounded `limit`.

The tool is available only to `InAppAssistance`, only after the server capability policy permits the requested source, and only with an authenticated `AssistantAccessContext`. It invokes `ApplicationContentRetrievalService` directly; it does not call Laraplate over HTTP.

The tool cannot create `ActionRequest` records, enter mutation approval/replay, or accept write operations. Tool timeout, provider unavailability, or authorization uncertainty returns a generic unavailable result without falling back to unfiltered search or documentation RAG.

## Authorization and information-flow invariants

1. Authentication establishes identity but does not grant retrieval by itself.
2. Server code selects profile, source capability, user, tenant, and locale policy.
3. Core permission and ACL checks happen before provider results reach AI.
4. Providers apply ACL filters inside the database/search-engine query, not only after retrieval.
5. Safe-field projection happens after record rehydration and before evidence serialization.
6. Empty and forbidden results are indistinguishable where existence disclosure would be sensitive.
7. Retrieved content is untrusted data and cannot issue instructions to the assistant.
8. The complete assistant response is validated before persistence or delivery.
9. No application content is added to developer or user documentation indexes.
10. Provider diagnostics never expose authorization internals or hidden record counts.

## Retrieval quality and abstention

The first provider must ship with a deterministic evaluation fixture covering:

- exact lexical lookup;
- semantic paraphrase when vector search is available;
- locale and translation selection;
- ACL-excluded records;
- cross-tenant exclusion;
- invalid or deleted records;
- unsupported questions;
- long-record excerpts;
- citation mapping;
- provider timeout and degraded search.

Metrics include hit rate at K, reciprocal rank, citation precision, authorized-empty accuracy, supported-answer rate, abstention accuracy, and latency. A raw similarity score is not presented as answer confidence. The assistant must abstain when evidence is absent or below the approved evidence policy.

## Failure behavior

- Unknown or disabled provider: generic capability unavailable response.
- Missing authentication: reject before registry or provider execution.
- Permission or ACL resolution failure: fail closed.
- Unsupported search driver: return an explicit unavailable/degraded result; never run an unbounded database scan.
- Vector branch failure with supported lexical fallback: return bounded lexical evidence and internal diagnostics.
- Entire provider failure: no model answer based on assumed application data.
- Invalid evidence DTO or unsafe field: discard the provider result and record a payload-free policy reason.
- Insufficient evidence: abstain with a user-safe response.

## Phase 2 — possible public content assistance

Phase 2 is a preserved extension point, not an implementation commitment. It requires a separate design, threat model, evaluation dataset, and approval before any route or profile is created.

That design must decide:

- a dedicated public assistant profile and fixed capability allowlist;
- explicit public-visibility rules independent of authenticated ACL assumptions;
- provider sources and fields safe for anonymous use;
- separate rate limits, quotas, abuse controls, and cost budgets;
- privacy, retention, consent, and logging constraints;
- prompt-injection handling for publicly managed content;
- caching and freshness behavior;
- citations and non-disclosure behavior;
- whether public retrieval needs a separate index or projection;
- whether streaming can satisfy equivalent output validation.

Phase 1 must not expose an internal provider through `/api/v1`, reuse an authenticated profile anonymously, or treat absence of authentication as public authorization.

## Deferred capability gates

The following capabilities require measured evidence and separate specifications:

- passage-level indexing for long records;
- temporal entity disambiguation;
- automatic cross-record entity linking;
- derived text from images, audio, or video;
- graph-aware evidence fusion;
- provider-specific generation workflows;
- public content assistance.

Each gate requires a representative evaluation subset, a baseline without the capability, measurable improvement, bounded cost, provenance, incremental update/deletion behavior, and the same authorization guarantees.

## Success criteria

- Optional modules implement a Core contract without depending on AI.
- AI discovers only installed, registered, server-authorized providers.
- The CMS reference provider returns bounded, cited evidence from records visible to the authenticated user.
- ACL, tenant, locale, validity, deletion, and safe-field rules apply before evidence reaches the LLM.
- Documentation RAG, Core Graph, and application content retrieval remain separate capabilities.
- Provider failure or insufficient evidence produces abstention rather than unsupported answers.
- Phase 1 creates no public or anonymous access surface.
- The contract can accept a future non-CMS provider without changing AI tool schemas.

## Related documents

- `docs/superpowers/specs/2026-07-16-rag-retrieval-strategy-design.md`
- `docs/superpowers/specs/2026-07-16-in-app-ai-assistance-security-design.md`
- `docs/superpowers/specs/2026-06-30-cms-graph-layer-design.md`
- `docs/superpowers/plans/2026-07-17-application-content-retrieval.md`
