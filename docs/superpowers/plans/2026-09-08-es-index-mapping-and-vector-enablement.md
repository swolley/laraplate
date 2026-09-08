# ES index mapping + vector search enablement

> Living tracker for making Elasticsearch index creation (and therefore vector/hybrid search) actually work. Kept updated as work proceeds.

**Context:** R3 (retrieval quality) surfaced that ES vector search has never run end-to-end in this codebase; a chain of latent bugs blocks it. Dimension/config bugs are already fixed & committed. This plan tracks the remaining index-creation + enablement work.

## Root cause (confirmed 2026-09-08)

`ElasticsearchEngine::createIndex()` builds a rich schema from `getSearchMapping()` (= `['mappings' => ['properties' => [...23 fields incl. `embedding: dense_vector dims 384`...]]]`) and then calls `parent::createIndex($collection, $schema)`. The parent is `Elastic\ScoutDriverPlus\Engine` (babenkoivan/elastic-scout-driver), whose `createIndex($name, $options)` creates a **bare** index and does **not** apply field mappings — ScoutDriverPlus expects mappings via explicit `elastic/migrations` classes, not via the Scout engine. Result: the live index is created with `mappings: {}` (empty), so `dense_vector` (which ES cannot infer dynamically) never exists → vector search is impossible.

The app already has `Modules\Core\Services\ElasticsearchService::createIndex($index, $settings, $mappings)` which applies mappings correctly via the ES client (`indices()->create(body.mappings)` on a new index, `indices()->putMapping` on an existing one).

## Fix

In `ElasticsearchEngine::createIndex()`, after `parent::createIndex()` (kept, to preserve any ScoutDriverPlus behaviour), apply the resolved properties explicitly via `ElasticsearchService` so the field mappings (incl. the `embedding` dense_vector) actually reach ES. Additive, lowest-risk on this shared path (used by CMS in prod).

## Status log

- [x] Dimension aligned to 384 everywhere (config/seeder/migration/engine/translator/field-defs) — committed.
- [x] `scout:index` model-resolution fix (createIndex called by collection name) — committed (Core bb14b9e).
- [x] Reranker on by default — committed (Core c4ce693).
- [x] Dev-seed search suppression (SAO) — committed (SAO f7af412).
- [x] RCA of empty-mappings bug — done (above).
- [x] Fix `ElasticsearchEngine::createIndex` to apply the embedding mapping via `ElasticsearchService` — committed (Core 384861c).
- [x] Verify live: recreated `cms_contents` → ES mapping now has `embedding: {type: dense_vector, dims: 384, index: true, similarity: cosine}` (ES adds int8_hnsw index_options). Core search suite 119 passed.
- [x] Embedding backfill + hybrid verification (Horizon running): 3 Content → `GenerateEmbeddingsJob` (sync) → 3 `ModelEmbedding` rows → indexed → ES reports `es_docs_with_embedding=3`. Hybrid query (Core fallback planner, reranker off) returned `strategies=["keyword","vector","hybrid"]`, 3 hits each matched across all three strategies. **Vector/hybrid retrieval works end-to-end against live ES.**
- [x] ES-gated integration test asserting createIndex applies the dense_vector embedding mapping (dedicated throwaway index; skips without ES; verified passing against live ES). Core commit.

## Operational findings — RESOLVED

1. **AI LLM search planner (NeuronAI executor) — FIXED.** Root cause was NOT LLM config: `ChatAgent extends NeuronAI\Agent\Agent -> Workflow`, and `ChatAgent::__construct` had an empty body that never called `parent::__construct()`, so `Workflow::$executor` was never initialised → `must not be accessed before initialization`. Fix: `ChatAgent` now calls `parent::__construct()` (AI 450d045). Additionally, `LlmSearchService` now catches any LLM/agent failure and degrades to the raw query / empty plan (AI bd5dac2), and the intent parser no longer breaks search when the LLM is unavailable.
2. **Cross-encoder reranker down — degrades now.** `EnsembleSearchService` catches a reranker failure (e.g. cross-encoder service at `127.0.0.1:8001` down) and returns the fused results unreranked (`meta.reranked=false`) instead of throwing (Core c0f456a).

**End-to-end result (verified live):** the full assistant search path now returns results even with Ollama (`.239:11434`) AND the cross-encoder (`:8001`) both unreachable — `strategies=[keyword,vector,hybrid]`, `reranked=false`, `hits=3`. The search pipeline is resilient: LLM failures degrade to non-LLM retrieval, reranker failures skip reranking, vector/hybrid still runs.

## RCA extension (2026-09-08, second pass)

Two further ES-side facts found by applying the mapping against the live cluster:
1. `ElasticsearchTranslator` emits `meta.filterable` as a **boolean** and `index: true` / `meta` on **object/relation** fields (e.g. `tags`) — both invalid ES (meta must be strings; object fields reject `index`/`meta`). So applying the FULL translator schema fails with 400s. The translator has never been validated against real ES because index creation never applied mappings.
2. Because mappings were never applied, all indices (CMS included) have run on **dynamic mapping** — ES auto-maps fields on first doc. Applying the full explicit schema would change that behaviour and currently breaks (translator bugs).

**Decision:** scope the fix to apply ONLY the `embedding` dense_vector field (the one thing dynamic mapping cannot infer). All other fields keep their existing dynamic mapping → no behaviour change to CMS keyword search, and the translator's other-field bugs are avoided. Making the full schema explicit (fixing the translator field-by-field vs live ES) is a SEPARATE, larger effort — tracked but out of scope for vector enablement.

## Environment notes

- ES: 192.168.1.233:9200 (http), status green, ~1.5GB / 98% OS mem — small, do not bulk-index.
- Embedding service: localhost:8000, sentence-transformers, 384-dim (verified live).
- `SCOUT_QUEUE=true`, vector enabled, dim 384 (live setting corrected).
- Only `Content` is embedding-ready (`$embed = ['title','textual_only']`); Ticket/Location are not.
