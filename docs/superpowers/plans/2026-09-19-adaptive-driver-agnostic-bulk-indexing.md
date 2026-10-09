# Adaptive Driver-Agnostic Bulk Indexing Implementation Plan

> **Retroactive plan (written 2026-10-09).** The work shipped on 2026-09-19 straight from the spec, without a plan. This file records it afterwards so the backlog and the indexes stay coherent. The steps below were mapped from what the code does, not from the names the spec imagined; where the code differs from the design, the step says so.

**Goal:** A mass reindex (`scout:import`) writes to the search engine in adaptive batches instead of one document per job, and embeds a whole chunk in one batched pass, so a weak or fragile server converges to small batches and a strong one to large, without per-server tuning.

**Spec:** [`../specs/2026-09-19-adaptive-driver-agnostic-bulk-indexing-design.md`](../specs/2026-09-19-adaptive-driver-agnostic-bulk-indexing-design.md).

**Architecture:** `AdaptiveBatchController` (Core Search) sits above Scout's `engine->update(Collection)` and sizes batches by AIMD using only latency and thrown exceptions. `Searchable::queueMakeSearchable` routes an async multi-model chunk to the bulk path; real-time single saves keep the per-model path. A new `ModelsRequireIndexing` event lets AI embed the whole chunk at once. The embedding service is driven by the same controller in an AI-only layer.

**Tech Stack:** PHP 8.5, Laravel 12, Laravel Scout, Pest. Code in `Modules/Core` (Search) and `Modules/AI` (embeddings).

---

### Task 1: The agnostic controller

- [x] **Step 1:** `Modules/Core/app/Search/AdaptiveBatchController.php`: AIMD on latency and thrown exceptions, backoff on failure, floor retries, configurable `startBatch`, optional `maxBatchBytes` / `sizeOf`.
- [x] **Step 2:** Injectable clock and sleeper, so the trajectory is deterministic to test.
- [x] **Step 3:** Tests (`Modules/Core/tests/Unit/Search/AdaptiveBatchControllerTest.php`): decrease on strain, ramp on success, floor at the minimum, cap at the maximum.

### Task 2: Bulk search path

- [x] **Step 1:** `Searchable::queueMakeSearchable` detects an async multi-model chunk and routes it to `bulkQueueMakeSearchable` -> `adaptiveBulkIndex`, which drives the controller over `engine->update(Collection)`. The per-model event fan-out stays for real-time single saves.
- [x] **Step 2:** Config `core.bulk_index_batch` (env `BULK_INDEX_BATCH`, default 100) caps the optimistic batch.
- [x] **Step 3:** Tests (`Modules/Core/tests/Integration/Search/BulkQueueMakeSearchableTest.php`).
- [-] **Step 4:** Serialized-size cap on the search path. Not built: the count ceiling plus latency-AIMD proved enough. The byte cap stays wired on the embedding-service layer.
- [-] **Step 5:** Optional per-driver hooks (`isOverloadError`, `maxRecommendedBatchBytes`) and Elasticsearch per-item bulk retry. Not built: the agnostic AIMD covers the observed strain; deferred until a real need.
- [-] **Step 6:** A dedicated reindex command. Not built, by standing decision: `scout:import` is the bulk entry point.

### Task 3: Batched embedding pre-process

- [x] **Step 1:** `Modules/Core/app/Events/ModelsRequireIndexing` carries the whole chunk.
- [x] **Step 2:** `Modules/AI/app/Listeners/HandleBulkModelIndexingListener.php` embeds every embeddable model in one pass through `ModelEmbeddingSynchronizer::sync`, instead of one `GenerateEmbeddingsJob` per model.
- [x] **Step 3:** `SentenceTransformersEmbeddingsProvider` drives `/embed` through the same controller; `EmbeddingService::embedDocumentsBatch` collapses a chunk's texts into one provider call; env `SENTENCE_TRANSFORMERS_TIMEOUT` / `_BATCH_SIZE`.

### Task 4: N+1 follow-up found by measuring a real import

A real `scout:import` of 411 `Content` took about 3m20s with an idle search server: the path was query-bound. Now about 37s.

- [x] **Step 1:** `ModelEmbeddingSynchronizer::sync` gains a `reload` flag (bulk passes `false`) and reuses the eager-loaded `embeddings` relation; `toSearchableArray` and `prepareDataToEmbedByLocale` reuse loaded `embeddings` / `translations`; `bulkQueueMakeSearchable` eager-loads both once per chunk. Query-count test in `GenerateEmbeddingsPerLocaleTest`.
- [x] **Step 2:** `adaptiveBulkIndex` honors Scout's `makeSearchableUsing`; `Searchable::makeSearchableUsing` does `loadMissing` of `toSearchableWith()`. Tests (`Modules/Core/tests/Integration/Search/MakeSearchableUsingTest.php`).
- [x] **Step 3:** The residual (2026-09-28) was the hierarchical `path` field serialized per category (lazy recursive-ancestor CTE per category). `Content::toSearchableWith()` eager-loads `categories.translations` and `categories.ancestors.translations`: 313 queries to 0 for 50 Content. Regression test in `Modules/CMS/tests/Integration/Models/ContentTest.php`.

### Task 5: Documentation and close

- [x] **Step 1:** `Modules/Core/README.md` (`BULK_INDEX_BATCH`), `Modules/AI/README.md` (`SENTENCE_TRANSFORMERS_*`).
- [x] **Step 2:** The bulk path as used by imports is described in `Modules/Core/docs/IMPORT_FRAMEWORK.md` and `Modules/Core/docs/rag/MODULE.md`.
- [x] **Step 3:** Close this plan (delivery status, documented in), index entries (plans and specs), `bash plan-status`.

## Delivery status (2026-10-09): shipped

Shipped 2026-09-19, follow-up measured and fixed through 2026-09-28; this plan was written afterwards, so its dates are those of the spec's own delivery record.

**Documented in:** `Modules/Core/docs/IMPORT_FRAMEWORK.md` (bulk indexing as used by imports), `Modules/Core/docs/rag/MODULE.md` (deferred bulk indexing, batched embeddings, adaptive engine writes), `Modules/Core/README.md` (`BULK_INDEX_BATCH`), `Modules/AI/README.md` (`SENTENCE_TRANSFORMERS_*`).

Divergences from the design, deliberately not built (steps 2.4 to 2.6, marked `[-]`): no serialized-size cap on the search path, no per-driver hooks or per-item bulk retry, no dedicated reindex command. The other AIMD parameters (`target_latency`, ramp step, backoff, floor retries) stay as controller constructor defaults rather than config keys.

Known gap: no standalone developer page describes `AdaptiveBatchController` itself (parameters, AIMD rules); that detail lives in the spec and the class.
