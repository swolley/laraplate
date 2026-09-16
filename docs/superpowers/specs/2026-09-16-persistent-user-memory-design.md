# Persistent user memory (cross-session)

**Status:** draft for review

**Date:** 2026-09-16

**Module:** AI. Adjacent to the RAG assistant program (R-track), but a separate concern: this is *conversational memory of the user*, not document/data retrieval. Inspired by patterns in supermemory (fact profile, contradiction handling, temporal decay), rebuilt inside Laraplate's auth model.

## Problem

The assistant remembers only *within* one conversation: `MemoryService` summarizes a long chat and re-injects that summary into the same chat's system prompt (`getContextForNewMessage`). Extracted facts *are* persisted today, but only inside per-conversation `ConversationSummary.facts` snapshots (a JSON blob tied to one `conversation_id`) — they are never promoted to a cross-session, per-user, queryable store, and injection reads only the current conversation's summaries. So the user repeats stable facts every new conversation.

## Decision summary

Introduce a per-user, tenant-scoped store of durable **memory facts**, extracted from conversations, superseded on contradiction, expired by TTL, and injected (bounded) into the assistant prompt on later conversations. Facts are the user's own data; they are never shared across users or tenants. A dedicated table holds them (not the Core Graph): a fact is a row, retrieval is an indexed per-user query, and supersession/TTL reuse Laraplate's validity/versioning patterns.

## Scope and isolation

- A fact belongs to one **user**. Retrieval and every write are filtered by `user_id`, always. No cross-user read. Tenant is inherited via the Core user: AI tables carry no tenant column today (the assistant is Global-scope, like `Conversation`), so `user_id` is the isolation key; a tenant column is added only if/when AI tenant scoping lands.
- **Module-agnostic:** facts describe the user, so they surface to that same user in any module (a preference stated in CMS is useful in ERP). Module scope (R1a) governs docs/data retrieval, not user memory.
- Facts are the user's own data, not ACL-record-gated content: no per-record ACL applies. Secrets are never stored (see security invariants).

## Data model

New table (module-prefixed, e.g. `ai_memory_facts`), one row per fact:

- `id`, `user_id` (FK to Core users, indexed) — the isolation key, matching `Conversation`. No tenant column (see Scope and isolation).
- `fact` — the bounded plain-text fact.
- `source_message_id` (nullable FK to the message it was extracted from) — provenance.
- `confidence` — normalized score when available.
- `learned_at` — when first extracted.
- `expires_at` (nullable) — TTL; null = no expiry.
- `superseded_by` (nullable self-FK) + `superseded_at` — supersession chain; an active fact has both null.
- standard timestamps / soft-delete per the module model standard.

"Active facts for a user" = `user_id` match, not soft-deleted, `superseded_by` null, `expires_at` null-or-future. One indexed query.

## Extraction and write

- Reuse `MemoryService::extractFacts()` at the point summaries are already produced (`shouldSummarize` / `createSummarySnapshot` in `ChatService`, and the in-app path), so extraction rides the existing lifecycle — no new trigger cadence.
- On write, **dedup + supersede** deterministically for v1: a new fact that duplicates an active one is dropped; a new fact that the writer marks as replacing an existing one sets the old row's `superseded_by`/`superseded_at`. Provenance (`source_message_id`, `learned_at`, `confidence`) is stored on every fact.
- **Contradiction resolution beyond exact/near-duplicate** (LLM-assisted reconciliation of semantically conflicting facts) is deferred to a later iteration; v1 keeps supersession explicit and simple to stay deterministic and testable.
- `expires_at` is set from a configurable default TTL (config key under `ai.features`), overridable per fact; expiry is enforced at read time (active-facts query) and by a prune path.

## Retrieval and injection

- A bounded set of the user's active facts (ordered by recency/confidence, capped by a config limit) is injected into the assistant prompt context as a distinct "user memory" block, with provenance retained internally.
- Injection is **size-bounded** (context-reduction ethos): a small cap, not the whole profile. It sits alongside the existing summary memory-context injection point, not replacing it.
- Injected facts are **data, not instructions** — the same guardrail principle applied to retrieved content: a stored fact can never issue instructions to the assistant.

## Security and information-flow invariants

1. Every read and write is filtered by `user_id`; a fact is never visible to another user (and, transitively, another tenant, since a user belongs to one).
2. Facts are user-owned data; no per-record ACL, but the `user_id` filter is mandatory and fail-closed (a missing/ambiguous principal yields no memory, never all memory).
3. No secrets, credentials, or sensitive tokens are persisted: the extraction prompt and a write-time filter exclude them; when in doubt, drop.
4. Injected facts are untrusted data and cannot instruct the assistant (guardrail-consistent).
5. Provenance (`source_message_id`) never exposes another user's message.
6. Right-to-erasure: deleting a user (or a conversation) cascades/prunes their facts.

## Integration

- Extend the existing memory-context injection (where `MemoryService::getContextForNewMessage()` feeds the system prompt) to also include the bounded active-facts block, for both `ChatService` and the in-app assistant path.
- Persist extracted facts at the existing summarize hook. No change to `respond()`'s security/scope resolution (R1a) or the retrieval surfaces.

## Testing and evaluation

- Unit: the fact model / active-facts scope (user+tenant isolation, expiry, supersession filtering); dedup + supersede write logic; secret-exclusion filter.
- Feature: extraction persists deduped facts at the summarize hook; a later conversation injects the user's active facts and not another user's; expired/superseded facts are excluded.
- **Memory-recall eval (contract, ties to R1b):** a slice where the correct answer depends on a fact stated in an earlier conversation. Defined here; built with or after the assistant eval's Level-2, not required for v1's gate.

## Scope boundaries

In scope (v1): the fact table + model, extraction-to-persistence at the existing hook with dedup/supersede, TTL, bounded injection into the assistant prompt, user/tenant isolation, tests.

Out of scope: LLM-assisted semantic contradiction reconciliation; a graph representation of facts (see Upgrade path); multimodal/connector ingestion; sharing memory across users; any change to document/data retrieval or R1a scoping.

## Upgrade path: Core Graph nodes

The table is the v1 store because a fact is today flat text about the user (preferences, context) with no relational web to traverse; a dedicated table gives explicit user/tenant isolation, reuses validity/versioning, and answers "active facts for a user" with one indexed query. The Core Graph would add weight and a record-oriented ACL that user facts do not need.

The Graph becomes worth it only if facts must **link to domain records and be traversed** — at which point it buys:

- **Fact ↔ record relations:** a fact connected to the actual records it concerns ("prefers supplier Z on ERP orders", "owns project Y"), as navigable edges rather than isolated strings.
- **Multi-hop memory + data in one traversal:** combine the user's memory with the record graph in a single query ("what does this user usually do with orders like this one") instead of memory and retrieval as separate steps.
- **Reuse of the existing Graph surface** (`expand`/`search`/`stats`, ACL-preserving gateway) instead of a bespoke retrieval query.

If that relational need appears, migrate facts to Graph nodes (or project the table into the graph) keeping the same user/tenant isolation and provenance; the table's `fact`/`source_message_id`/supersession fields map onto node attributes and edges. Until then, this is a deliberate YAGNI deferral, not a limitation.

## Related

- `Modules/AI/app/Services/MemoryService.php` (existing `extractFacts` / summary), `Modules/AI/app/Models/ConversationSummary.php`, `Modules/AI/app/Services/ChatService.php` (memory-context injection), `Modules/AI/app/Services/Assistance/InAppAssistanceService.php` (in-app assistant).
- `docs/superpowers/specs/2026-08-29-assistant-end-to-end-evaluation-design.md` (R1b; the memory-recall eval slice extends it).
- Model standard: the Laraplate model conventions (validations in-model, casts/attributes, Core concerns).
