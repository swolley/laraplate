# Application Content Passage Index — Evaluation Gate

**Status:** deferred experiment; no implementation or production index is authorized.

## Why this gate exists

The CMS record-level synthetic baseline (`cms-record-v1`, 30 cases) reached hit@5 `0.7619`, citation precision `1.0`, authorized-empty accuracy `1.0`, and supported-answer rate `0.7619`. Exact and normalized lexical cases are strong, but semantic/paraphrase cases and the specific-detail passage candidate score `0.0`; the combined long-record slice reaches only `0.5`.

This result justifies a bounded comparison experiment. It does not prove that a passage index is the right solution, and it does not authorize replacing or silently supplementing record retrieval.

## Required comparison

The experiment must compare the current record provider with an optional passage candidate on the same versioned synthetic dataset. It must report aggregate and sliced hit@5, reciprocal rank, citation precision, authorized-empty accuracy, supported-answer rate, abstention accuracy, unavailable rate, latency, index size, indexing time, and query cost.

Adoption requires a material improvement on semantic/paraphrase and long-record slices without reducing citation precision, authorized-empty accuracy, ACL isolation, or deletion freshness. A result that only improves aggregate recall by returning more irrelevant passages fails the gate.

## Security and ownership invariants

- Core continues to own the provider contract, authenticated gateway, permission resolution, and ACL transport.
- The module owning a record owns passage extraction, safe-field projection, indexing, update, deletion, and locale behavior.
- AI consumes only typed, already-authorized evidence and does not know module tables, model classes, index names, ACL expressions, users, or tenants.
- Candidate lookup must include server-resolved ACL constraints before evidence is returned. Post-filter-only authorization is forbidden.
- Each passage keeps a stable record identity, locale, revision, ordinal, and canonical `/app/...` record reference. It never creates an unverified public content URL.
- Raw search `_source`, unrestricted dynamic components, media payloads, storage paths, and hidden relations remain excluded.
- Missing, stale, denied, unsafe, or timed-out evidence produces the same abstention behavior as the record provider.

## Lifecycle requirements

Before implementation approval, the experiment design must define:

1. deterministic passage segmentation and overlap limits;
2. stable passage identifiers across no-op reindexing;
3. after-commit create/update handling;
4. deletion, soft deletion, validity, and locale removal propagation;
5. full rebuild and interrupted rebuild recovery;
6. tenant/ACL-aware candidate filtering for each supported search driver;
7. bounded per-record passage count and global storage/cost budgets;
8. freshness observability without content, identity, permission, or ACL payloads;
9. fallback to record retrieval whenever passage freshness cannot be proven;
10. a migration and rollback strategy that leaves the record provider functional.

## Explicit non-goals

This gate does not introduce Graphify, GraphRAG, a graph database, automatic multi-provider fan-out, a public assistant, or a new HTTP API. Core Graph remains the relation API and is independent of both record and passage retrieval.

## Decision states

- **Remain record-only:** the passage candidate does not materially improve the failing slices or violates a security/cost/freshness bound.
- **Continue experiment:** quality improves but operational evidence is incomplete; keep the candidate disabled outside evaluation.
- **Approve a separate implementation plan:** every quality, security, lifecycle, latency, and cost threshold is satisfied and explicitly reviewed.
