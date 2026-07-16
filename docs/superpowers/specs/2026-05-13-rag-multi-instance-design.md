# RAG documentation — multi-instance readiness (design)

**Status:** Approved direction (user choice: multi-instance, not necessarily immediate; prepare architecture).

**Date:** 2026-05-13

## Problem

The FAQ/documentation RAG path (`DocumentationService` + `DocumentationAgent`) persists vectors in **local filesystem** (`FileVectorStore`) or **process memory** (`MemoryVectorStore`). Multiple application instances do not share that state unless a **shared read-write volume** is mounted at the same path for every pod/VM. Memory is unsuitable for production persistence.

## Goals

1. **Multi-instance correctness:** any app instance can answer RAG questions against the same corpus after indexing runs once (or on a dedicated indexer job).
2. **Operational clarity:** document interim options (shared volume) and the recommended target (central index).
3. **Alignment with existing stack:** reuse `ElasticsearchService` and patterns compatible with `EnsembleSearchService` (dense_vector field name `embedding`, hybrid-capable index later).
4. **Optional analytics (later):** query logging is a **separate** concern from the RAG corpus (privacy, retention); not required for multi-instance correctness.

## Non-goals (YAGNI for v1)

- Automatic learning from user queries without an explicit analytics pipeline and privacy review.
- Replacing Neuron `RAG` / `DocumentationAgent`; keep orchestration, swap **vector store implementation** behind config.
- Pinecone or additional vendors.

## Target architecture

- **Config-driven vector backend:** extend `ai.features.faq.vector_store` (or parallel keys) to support `elasticsearch` in addition to `filesystem` and `memory`.
- **New class:** `ElasticsearchRagVectorStore` implementing `NeuronAI\RAG\VectorStore\VectorStoreInterface` in `Modules/AI`, using the official ES client via `ElasticsearchService::getInstance()->client`.
- **Index:** dedicated index (e.g. `laraplate_rag_docs` configurable) with mapping including `dense_vector` for `embedding` (dimension must match the active embeddings provider), plus `content`, `sourceType`, `sourceName`, `metadata` (flattened or JSON), `indexed_at`.
- **Ingest:** existing `ai:index-docs` flow unchanged at CLI level; `DocumentationService` continues chunking; `DocumentationAgent` resolves the new store when configured.
- **Retrieval:** `similaritySearch` issues kNN against the index; scores mapped to `Document::setScore`.
- **Reindex:** Neuron `reindexBySource` already calls `deleteBy` then `addDocuments`; ES store must implement `deleteBy` via `delete_by_query` on `sourceType` + `sourceName`.

## Interim (before ES is implemented)

- Run `ai:index-docs` on a **single** node or CI job writing to a **shared** path mounted identically on all consumers, **or** accept stale/wrong results on other nodes.

## Risks

- **Embedding dimension drift:** index mapping must be recreated or use a new index if the embedding model dimension changes.
- **Bulk size / timeouts:** large docsets need chunked bulk indexing and observability.
- **Security:** ES index must respect same network/auth as the rest of the cluster.

## Success criteria

- With `vector_store=elasticsearch`, two PHP workers (or sequential processes) see identical retrieval results after one index build.
- `DocumentationService::isAvailable()` reflects ES index readiness when that driver is selected.
- Tests cover the vector store with a mocked ES client (no live cluster required in CI).

## Implementation plan

See `docs/superpowers/plans/2026-05-13-rag-multi-instance-elasticsearch.md`.

## Retrieval strategy boundary (2026-07-16)

This spec decides **where vector data is shared**, not whether Laraplate adopts GraphRAG. The authoritative retrieval decision is `docs/superpowers/specs/2026-07-16-rag-retrieval-strategy-design.md`: vector documentation RAG remains the baseline; hybrid retrieval and reranking require evaluation; graph retrieval is an optional future experiment authorized only by measured multi-hop failures.
