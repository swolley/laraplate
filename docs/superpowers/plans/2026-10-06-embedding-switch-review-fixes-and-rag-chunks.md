# Embedding Switch: Review Fixes and RAG Chunks Implementation Plan

> **For agentic workers:** Steps use checkbox syntax for tracking. Tick each step when it finishes.

**Goal:** Fix the review findings on the chunked index writes of the embedding model switch, and stop the switch job from rebuilding the Elasticsearch documentation (RAG) indexes in one synchronous run: the documentation documents are written by chunk jobs like the search documents.

**Follows:** [`2026-10-06-embedding-switch-chunked-indexes.md`](2026-10-06-embedding-switch-chunked-indexes.md) (shipped). **Spec:** [`../specs/2026-10-05-embedding-model-switch-design.md`](../specs/2026-10-05-embedding-model-switch-design.md), sections 4.4, 10 and 11.

**Architecture:** Part A hardens the state handling: a chunked phase fails when its queue holds jobs and no chunk completed for a configurable time, the last round re-reads the pending chunks before failing, chunk sizes fall back to their default when invalid, every command write goes through the state lock, the `indexes` to `verify` transition clears the plan, the job's failure handler survives a lock timeout, and the lock wait is configurable. Part B keeps `ai:create-rag-index --profile=all --force` in the step that prepares the `indexes` phase and adds documentation chunks to the same plan: per documentation profile, ranges of source names written by `IndexDocumentsChunkJob` on `embeddings-index` with the target as the active profile.

**Tech Stack:** PHP 8.5, Laravel 12, Horizon, Pest. Code in `Modules/AI` (`laraplate-ai`); spec and plans in `laraplate`.

---

### Part A: review fixes

- [x] **Step 1 (finding 1):** a chunked phase records `chunkProgressAt` at each dispatch and chunk completion; while `embeddings-index` holds jobs and no chunk completed for `ai.features.embeddings.index_chunk_stall_seconds` (default 900), the phase fails naming the queue. Waiting passes still write `updatedAt`, so `isInterrupted()` keeps meaning "the switch job is lost". Test first.
- [x] **Step 2 (finding 2):** before the round-limit failure the orchestrator re-reads the stored pending chunks and moves on when none is left. Test first.
- [x] **Step 3 (finding 3):** chunk sizes below 1 or not numeric fall back to the default, and are capped; README and MODULE.md state the rule. Test first.
- [x] **Step 4 (finding 5):** `--resume`, `--abandon` and the dispatch rollback write the state through `EmbeddingSwitchStore::update()`.
- [x] **Step 5 (finding 6):** the `indexes` to `verify` transition clears the chunk plan. Test first.
- [x] **Step 6 (finding 7):** `SwitchEmbeddingModelJob::failed()` falls back to a direct write of the failed state on a `LockTimeoutException`. Test first.
- [x] **Step 7 (findings 8, 9, 10):** MODULE.md states which cache stores make the state lock exclusive across workers; the no-write store test moves time before the update; the lock wait is `ai.features.embeddings.state_lock_wait_seconds` (default 10) and a mutual-exclusion test runs with a 1 s wait.
- [x] **Step 8 (finding 4, not fixed):** the growth of the state with the corpus is recorded as an open point in spec section 10.
- [x] **Step 9:** narrow switch tests green, Pint, commit in `Modules/AI`.

### Part B: RAG documentation in chunks

- [x] **Step 1:** `DocumentationService` exposes the unit of work without the console command: the source names of a documentation profile, and the indexing of the documents whose source name falls in a range (each source replaced, so a rewrite is idempotent). `ai:index-rag-docs` keeps its behaviour and tests. Tests.
- [x] **Step 2:** `IRagIndexRebuilder` becomes `prepare()` (recreate the indexes for the target), `plan()` (chunks `rag:<profile>#n` over source-name ranges of `ai.features.embeddings.rag_chunk_size` sources, default 20, same fall-back rule) and `writeChunk()` (with the target as the active profile). Nothing happens unless FAQ is on with the `elasticsearch` store. Tests.
- [x] **Step 3:** the `indexes` plan carries the documentation chunks next to the model chunks; `IndexDocumentsChunkJob` writes either kind; failure messages name them `rag:<profile>:[from, to)`; resume re-dispatches only the incomplete ones; the verify refresh plans no documentation chunk. Tests.
- [x] **Step 4:** the longest synchronous step of the switch job and the timeout it really needs are re-assessed; `SwitchEmbeddingModelJob::TIMEOUT_SECONDS`, the Horizon supervisor timeouts and every sentence about them changed or restated accordingly.
- [x] **Step 5:** MODULE.md, README, spec 4.4, 10 and 11.
- [x] **Step 6:** whole `Modules/AI` suite once, Pint and PHPStan on the changed files, commit in `Modules/AI`.

### Closing

- [x] **Step 1:** close this plan (delivery status, documented in), index entry, `tests/Unit/ClosedPlansPointToDocumentationTest.php`, `bash plan-status`.
- [x] **Step 2:** commits in `laraplate` (spec, plan, index, Horizon when changed) and the submodule pointer.

## Delivery status (2026-10-06): shipped

**Documented in:** `Modules/AI/docs/rag/MODULE.md` (switch procedure, failure, operating notes: stall time, chunk size rules, lock stores, documentation chunks, timeout), `Modules/AI/README.md` (model switch line, `AI_EMBEDDINGS_RAG_CHUNK_SIZE`, `AI_EMBEDDINGS_INDEX_CHUNK_STALL_SECONDS`, `AI_EMBEDDINGS_STATE_LOCK_WAIT_SECONDS`), `Modules/AI/docs/rag/DEPLOYMENT.md`, spec sections 4.4, 10 and 11.

Divergences and decisions, recorded in the spec (section 11, review fixes and documentation chunks):
- Finding 1: the stall check was chosen over "no `updatedAt` on waiting passes", so `isInterrupted()` keeps meaning that the switch job is lost. Default stall 900 s, not 2 x 300 s: one chunk may legitimately take 760 s over its 3 tries with the single worker.
- Finding 4 (state grows with the corpus) not fixed, recorded as an open point (spec section 10).
- `ai:index-rag-docs` already delegated its unit of work to `DocumentationService`; the refactor is there and the command is unchanged.
- The switch job keeps `TIMEOUT_SECONDS = 900`, the supervisor 960 s and `retry_after` >= 1000 s: no run writes documents any more, but corpus walks, the pgvector HNSW build and the activation's row delete grow with the corpus and were never timed.
- Not verified: Elasticsearch (documentation chunks: `deleteBy` then bulk index per source), PostgreSQL, a real redis worker and cache lock.
