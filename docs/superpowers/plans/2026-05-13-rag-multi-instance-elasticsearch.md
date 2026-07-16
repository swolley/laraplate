# RAG multi-instance (Elasticsearch vector store) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make documentation RAG (`ai:index-docs` / `DocumentationService` / `DocumentationAgent`) safe for **multi-instance** deployments by adding an **Elasticsearch-backed** `VectorStoreInterface` and clear deployment documentation, without changing the public CLI contract of `ai:index-docs`.

**Architecture:** Keep Neuron `RAG` and `DocumentationAgent`; introduce `Modules\AI\Ai\Rag\ElasticsearchRagVectorStore` implementing `NeuronAI\RAG\VectorStore\VectorStoreInterface`, wired from `DocumentationAgent::vectorStore()` when `ai.features.faq.vector_store` is `elasticsearch`. Index documents with `dense_vector` field `embedding` (same field name convention as `EnsembleSearchService`). Interim ops: document shared filesystem volume constraints for teams not yet on ES.

**Tech stack:** Laravel 12, `neuron-core/neuron-ai`, `Modules\Core\Services\ElasticsearchService`, Elastic PHP client v8, Pest/PHPUnit for unit tests with mocked client responses.

**Spec:** `docs/superpowers/specs/2026-05-13-rag-multi-instance-design.md`

**Retrieval boundary:** This plan implements shared vector persistence only. It does not authorize Graphify, GraphRAG, or a graph database. Future retrieval evolution follows `docs/superpowers/plans/2026-07-16-rag-retrieval-strategy.md`.

---

### Task 1: Deployment documentation (filesystem interim)

**Files:**

- Create: `Modules/AI/docs/rag/DEPLOYMENT.md`
- Modify: `Modules/AI/docs/rag/MODULE.md` (add one paragraph + link to DEPLOYMENT.md under ingestion)

**Content (DEPLOYMENT.md):** Explain that `filesystem` store path (`AI_FAQ_VECTOR_STORE_PATH` or default under `storage/app/ai/`) must be on **shared R/W storage** visible to every replica for correct multi-instance behaviour; warn that `memory` is test-only; state that `elasticsearch` (once implemented) is the recommended production default when ES is already in stack.

- [ ] **Step 1:** Add `DEPLOYMENT.md` with the three drivers and a short Kubernetes example (volumeMount pointing to same PVC path for `AI_FAQ_VECTOR_STORE_PATH`).

- [ ] **Step 2:** In `MODULE.md`, after the ingestion overview, add: “For multi-instance deployments see `DEPLOYMENT.md`.”

- [ ] **Step 3:** Commit

```bash
git add Modules/AI/docs/rag/DEPLOYMENT.md Modules/AI/docs/rag/MODULE.md
git commit -m "docs(ai): document RAG vector store deployment for multi-instance"
```

---

### Task 2: Configuration keys for Elasticsearch RAG index

**Files:**

- Modify: `Modules/AI/config/config.php` (inside `features.faq` array)
- Modify: `.env.example` at project root (if present; else document only in DEPLOYMENT.md — do not create `.env.example` if repo does not use it)

Add keys (names illustrative — keep snake_case in config array keys per project style):

```php
// Under 'faq' => [
'elasticsearch' => [
    'index' => env('AI_FAQ_ES_INDEX', 'laraplate_rag_docs'),
    'embedding_dims' => (int) env('AI_FAQ_ES_EMBEDDING_DIMS', 384),
],
```

Document that `embedding_dims` **must** match the dimensionality of `AI_EMBEDDINGS_PROVIDER` / model (e.g. Sentence Transformers often 384; OpenAI `text-embedding-3-small` can be 1536 unless using dimension reduction — align with actual `EmbeddingsProviderFactory` output length).

- [ ] **Step 1:** Add the `elasticsearch` sub-array and extend inline comment on `vector_store` env to list `filesystem`, `memory`, `elasticsearch`.

- [ ] **Step 2:** `.env.example` already includes commented `AI_FAQ_ES_*` keys (pre-seeded when this plan was written). If missing in your branch, add them.

- [ ] **Step 3:** Commit

```bash
git add Modules/AI/config/config.php
git commit -m "feat(ai): add Elasticsearch RAG index config placeholders"
```

---

### Task 3: `ElasticsearchRagVectorStore` (core implementation)

**Files:**

- Create: `Modules/AI/app/Ai/Rag/ElasticsearchRagVectorStore.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/ElasticsearchRagVectorStoreTest.php` (mock ES client via partial mock or wrapper interface — see Task 4 for test strategy if client is hard to mock)

**Responsibilities:**

- Constructor: accept `Client` or `ElasticsearchService` resolver, `string $index`, `int $topK`, `int $embedding_dims` (validate non-empty index, positive dims).
- `addDocuments(array $documents):` bulk index using document `id` as Elasticsearch `_id` (cast `Document::id` to string). `_source` must include fields needed for `similaritySearch` round-trip: `content`, `sourceType`, `sourceName`, `embedding`, `metadata` (json), `neuron_id` duplicate of id if useful.
- `deleteBy(string $sourceType, ?string $sourceName):` use `delete_by_query` with `term` filters on `sourceType` and optionally `sourceName`; if `sourceName` is null, delete all docs matching `sourceType` only if that matches Neuron usage (verify `FileVectorStore::deleteBy` semantics — align exactly).
- `similaritySearch(array $embedding):` run kNN search on field `embedding` with `k` = `$this->topK`, `num_candidates` = `min(topK * 10, 100)` (tune to match `ElasticsearchEngine` style). Map hits back to `Document` instances: set `content`, `sourceType`, `sourceName`, `embedding` from `_source`, `setScore` from `_score`.

**Error handling:** Log and rethrow or wrap `VectorStoreException` (Neuron) on ES failures for add/delete; for search return empty iterator on transport failure (match resilience of `EnsembleSearchService::executeEsSearch` empty catch **or** fail fast — **pick fail fast for retrieval** so RAG does not silently hallucinate without context; document choice in class PHPDoc).

- [ ] **Step 1:** Implement the class with `declare(strict_types=1);`, explicit types, English PHPDoc.

- [ ] **Step 2:** Add unit tests with **mocked** `Client::search`, `bulk`, `deleteByQuery` returning canned arrays (no real ES). At minimum: `similaritySearch` returns two `Document` objects with scores; `addDocuments` builds expected bulk body shape; `deleteBy` sends expected query.

Example test assertion skeleton (Pest):

```php
<?php

declare(strict_types=1);

use NeuronAI\RAG\Document;

it('maps knn hits to documents', function () {
    // Instantiate store with mock client that returns one hit with _source content/sourceType/sourceName
    expect(true)->toBeTrue(); // replace with real assertions once mock wired
});
```

Replace placeholder with real mock once `ElasticsearchRagVectorStore` constructor supports injection of a `Client` interface or closure.

- [ ] **Step 3:** Run `php artisan test --compact Modules/AI/tests/Unit/Ai/Rag/ElasticsearchRagVectorStoreTest.php`

- [ ] **Step 4:** `vendor/bin/pint --dirty`

- [ ] **Step 5:** Commit

```bash
git add Modules/AI/app/Ai/Rag/ElasticsearchRagVectorStore.php Modules/AI/tests/Unit/Ai/Rag/ElasticsearchRagVectorStoreTest.php
git commit -m "feat(ai): add Elasticsearch-backed RAG vector store"
```

---

### Task 4: Index template bootstrap (Artisan or documented curl)

**Files:**

- Create: `Modules/AI/app/Console/CreateRagElasticsearchIndexCommand.php` (optional but recommended) **or** extend docs only with curl/JSON in `DEPLOYMENT.md`

**Mapping body (illustrative — dims from config):**

```json
{
  "mappings": {
    "properties": {
      "content": { "type": "text" },
      "sourceType": { "type": "keyword" },
      "sourceName": { "type": "keyword" },
      "metadata": { "type": "object", "enabled": true },
      "embedding": {
        "type": "dense_vector",
        "dims": 384,
        "index": true,
        "similarity": "cosine"
      }
    }
  }
}
```

Use `config('ai.features.faq.elasticsearch.embedding_dims')` when generating mapping from PHP.

- [ ] **Step 1:** Register command in `Modules/AI/app/Providers/AIServiceProvider` or module `ConsoleServiceProvider` (follow existing pattern for `IndexDocumentationCommand`).

- [ ] **Step 2:** Command calls `ElasticsearchService::getInstance()->createIndex($index, $settings, $mappings)`; idempotent if index exists.

- [ ] **Step 3:** Feature or unit test: mock `ElasticsearchService` / client `indices()->exists` path.

- [ ] **Step 4:** Commit

```bash
git add Modules/AI/app/Console/CreateRagElasticsearchIndexCommand.php Modules/AI/app/Providers/*.php Modules/AI/tests/...
git commit -m "feat(ai): add artisan command to create RAG Elasticsearch index"
```

---

### Task 5: Wire `DocumentationAgent` + `isAvailable()`

**Files:**

- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php`
- Modify: `Modules/AI/app/Services/DocumentationService.php` (`isAvailable`, `filesystemVectorStoreHasData` or new helper)

**DocumentationAgent::vectorStore():** add match arm `'elasticsearch' => new ElasticsearchRagVectorStore(...)` resolving index, topK, dims from config; obtain client via `ElasticsearchService::getInstance()->client`.

**DocumentationService::isAvailable():** when driver is `elasticsearch`, return true only if index exists (indices exists API) **and** optional `count` > 0 — product choice: prefer `exists` only to avoid cost, or `count` for stricter UX (document in MODULE.md).

- [ ] **Step 1:** Implement wiring; keep `filesystem` and `memory` behaviour unchanged.

- [ ] **Step 2:** Pest test: with `Config::set('ai.features.faq.vector_store', 'elasticsearch')` and mocked ES, `isAvailable()` behaves as expected (use `Http::fake` only if store uses Http — prefer injecting mock into container if you introduce an interface).

- [ ] **Step 3:** Run targeted tests including existing AI module tests.

- [ ] **Step 4:** `vendor/bin/pint --dirty`

- [ ] **Step 5:** Commit

```bash
git add Modules/AI/app/Ai/Agents/DocumentationAgent.php Modules/AI/app/Services/DocumentationService.php Modules/AI/tests/...
git commit -m "feat(ai): wire Elasticsearch RAG vector store in documentation agent"
```

---

### Task 6: End-to-end checklist and README touch

**Files:**

- Modify: `Modules/AI/README.md` (FAQ/RAG section): document `AI_FAQ_VECTOR_STORE=elasticsearch`, index creation command, `AI_FAQ_ES_EMBEDDING_DIMS` requirement.

- [ ] **Step 1:** Add short “Production multi-instance” subsection.

- [ ] **Step 2:** Commit

```bash
git add Modules/AI/README.md
git commit -m "docs(ai): document Elasticsearch RAG driver in README"
```

---

### Task 7 (optional / Phase 2): Query analytics index

**Defer** until ES RAG store is stable. Scope: append-only index `laraplate_rag_queries` with hashed user id, timestamp, query text (or hashed), locale, `citation_count`, latency; write from `ChatService` / FAQ path behind `config('ai.features.faq.query_logging.enabled')`. Requires privacy review — **separate plan** if user requests.

---

## Plan self-review

| Spec requirement | Task covering it |
|------------------|------------------|
| Multi-instance shared state | Task 1 (interim), Tasks 3–5 (ES) |
| Reuse ElasticsearchService | Tasks 3–5 |
| `embedding` field convention | Task 3–4 mapping |
| `isAvailable` behaviour | Task 5 |
| Tests | Tasks 3, 4, 5 |
| Non-goal: query logging | Task 7 optional, deferred |

**Placeholder scan:** No TBD in required tasks; Task 7 explicitly deferred.

**Type consistency:** `Document::id` is `string|int` — normalize to string for ES `_id`.

---

**Plan complete and saved to `docs/superpowers/plans/2026-05-13-rag-multi-instance-elasticsearch.md`.**

**Due opzioni di esecuzione:**

1. **Subagent-driven (consigliato)** — un subagent per task, revisione tra i task, iterazione veloce (skill: subagent-driven-development).

2. **Esecuzione inline** — stessa sessione con checkpoint (skill: executing-plans).

Quale approccio preferisci per passare all’implementazione?
