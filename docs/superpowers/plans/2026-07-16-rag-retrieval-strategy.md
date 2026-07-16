# RAG Retrieval Strategy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve documentation RAG through measured metadata scoping, hybrid retrieval, and reranking while keeping vector retrieval as the default and allowing graph retrieval only through a later evidence-gated spike.

**Architecture:** Keep `DocumentationService`, `DocumentationAgent`, NeuronAI `RetrievalInterface`, and the current answer/citation contracts. Add a retrieval evaluation harness first, then introduce replaceable retrieval implementations behind a factory. Elasticsearch may add lexical candidates and deterministic fusion; reranking wraps a bounded candidate set. Graph retrieval is not implemented by this plan: the final task evaluates the adoption gate and, only if it passes, creates a separate graph spike spec and plan.

**Tech Stack:** Laravel 12, PHP 8.4, NeuronAI v3 `RetrievalInterface`, Elasticsearch PHP client v8, Core `IReranker`, Pest 4, JSON evaluation fixtures.

**Spec:** `docs/superpowers/specs/2026-07-16-rag-retrieval-strategy-design.md`

**Security prerequisite:** `docs/superpowers/plans/2026-07-16-in-app-ai-assistance-security.md` owns the separate user corpus, permissions/ACL enforcement, mandatory guardrails, and read-only Core Graph tools. Complete that plan before exposing RAG through the in-app assistant.

---

**Workspace rule:** Run Artisan and tests from the Laraplate application root. `Modules/AI` is a nested Git repository, so commit AI module files with `rtk git -C Modules/AI ...`; commit application-level specs and plans with `rtk git ...` from the Laraplate root.

## Scope and sequencing

Tasks 1–3 establish measurement and a replaceable vector baseline. Tasks 4 and 5 are independently feature-flagged experiments. Task 6 is a decision checkpoint, not permission to implement GraphRAG.

Do not start Task 4 until Tasks 1–3 have produced a committed baseline report. Do not start Task 5 until hybrid results are recorded. Do not authorize a graph implementation from this plan.

This plan evaluates documentation retrieval quality. It does not authorize sharing a corpus between assistant profiles or exposing Core Graph data. Evaluation reports must slice results by server-owned profile, and user-profile cases must use only the physically separate user index defined by the security prerequisite.

### Task 1: Versioned RAG evaluation dataset and loader

**Files:**

- Create: `Modules/AI/tests/Fixtures/rag/evaluation.json`
- Create: `Modules/AI/app/Services/Documentation/Evaluation/RagEvaluationCase.php`
- Create: `Modules/AI/app/Services/Documentation/Evaluation/RagEvaluationDataset.php`
- Create: `Modules/AI/tests/Unit/Services/Documentation/Evaluation/RagEvaluationDatasetTest.php`

- [ ] **Step 1: Write the failing dataset loader test**

Cover valid loading, duplicate IDs, missing expected sources, invalid audience values, and invalid hop classes. The initial fixture must contain at least one `user`, one `developer`, one Italian, one English, one unsupported, and one multi-hop case.

```php
it('loads typed evaluation cases from the versioned fixture', function (): void {
    $dataset = RagEvaluationDataset::fromJson(
        base_path('Modules/AI/tests/Fixtures/rag/evaluation.json'),
    );

    expect($dataset->cases())->not->toBeEmpty()
        ->and($dataset->cases()[0])->toBeInstanceOf(RagEvaluationCase::class);
});
```

- [ ] **Step 2: Run the test and verify failure**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/Evaluation/RagEvaluationDatasetTest.php
```

Expected: FAIL because the evaluation DTO and loader do not exist.

- [ ] **Step 3: Implement the DTO and strict loader**

Use this public shape:

```php
final readonly class RagEvaluationCase
{
    /**
     * @param list<string> $expectedSources
     * @param list<string> $requiredFacts
     */
    public function __construct(
        public string $id,
        public string $question,
        public string $audience,
        public string $locale,
        public string $hopClass,
        public array $expectedSources,
        public array $requiredFacts,
        public bool $answerable,
    ) {}
}
```

`RagEvaluationDataset::fromJson()` must reject malformed JSON, duplicate IDs, audiences outside `user|developer|shared`, hop classes outside `single|multi`, and answerable cases without expected sources.

- [ ] **Step 4: Populate the initial fixture from canonical RAG documents**

Use stable questions whose expected source paths exist in `docs/rag/` or `Modules/*/docs/rag/`. Do not invent expected facts not stated by those documents. Start with 30 cases: 20 single-hop, 5 multi-hop, and 5 unsupported; include both supported audiences and locales represented in the corpus.

- [ ] **Step 5: Run the targeted test**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
rtk git -C Modules/AI add app/Services/Documentation/Evaluation tests/Fixtures/rag/evaluation.json tests/Unit/Services/Documentation/Evaluation
rtk git -C Modules/AI commit -m "test(ai): add versioned RAG evaluation dataset"
```

### Task 2: Retrieval evaluation runner and baseline report

**Files:**

- Create: `Modules/AI/app/Services/Documentation/Evaluation/RagEvaluationResult.php`
- Create: `Modules/AI/app/Services/Documentation/Evaluation/RagEvaluationService.php`
- Create: `Modules/AI/app/Console/EvaluateRagCommand.php`
- Create: `Modules/AI/tests/Unit/Services/Documentation/Evaluation/RagEvaluationServiceTest.php`
- Create: `Modules/AI/tests/Feature/EvaluateRagCommandTest.php`
- Create after running baseline: `Modules/AI/docs/rag/evaluations/2026-07-vector-baseline.json`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`

- [ ] **Step 1: Write failing metric tests**

Use an injected `RetrievalInterface` fake returning known source orders. Assert hit@K, reciprocal rank, unsupported empty-result accuracy, per-audience slices, and per-hop slices.

```php
expect($result->hitRateAt(5))->toBe(0.5)
    ->and($result->meanReciprocalRank())->toBe(0.5)
    ->and($result->slice('hop_class', 'multi')->caseCount)->toBe(1);
```

- [ ] **Step 2: Run the metric tests and verify failure**

Expected: FAIL because the evaluator does not exist.

- [ ] **Step 3: Implement deterministic retrieval metrics**

`RagEvaluationService` accepts a `RetrievalInterface` and evaluates retrieval without calling the chat provider. Normalize citation/source names before matching, preserve raw ranks for diagnostics, and emit a serializable result containing:

```php
[
    'strategy' => 'vector',
    'case_count' => 30,
    'hit_rate_at_5' => 0.0,
    'mrr' => 0.0,
    'unsupported_empty_rate' => 0.0,
    'latency_ms' => ['median' => 0.0, 'p95' => 0.0],
    'slices' => [],
    'cases' => [],
]
```

- [ ] **Step 4: Add the command**

Register `ai:evaluate-rag` with `--dataset=`, `--output=`, and `--strategy=vector`. Refuse to overwrite an existing report unless `--force` is passed. The command must never run as part of the default CI suite because it may require configured embeddings and Elasticsearch.

- [ ] **Step 5: Test the command with a fake evaluator**

Assert successful JSON output, refusal to overwrite, non-zero exit for missing datasets, and no chat-provider call in retrieval-only mode.

- [ ] **Step 6: Run the vector baseline against the configured test corpus**

Run:

```bash
rtk php artisan ai:evaluate-rag --strategy=vector --dataset=Modules/AI/tests/Fixtures/rag/evaluation.json --output=Modules/AI/docs/rag/evaluations/2026-07-vector-baseline.json
```

Expected: a committed JSON report with aggregate and sliced metrics. Record environment, embeddings model, dimensions, index name, and corpus revision in the report metadata.

- [ ] **Step 7: Run targeted tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/Evaluation/RagEvaluationServiceTest.php Modules/AI/tests/Feature/EvaluateRagCommandTest.php
rtk git -C Modules/AI add app/Services/Documentation/Evaluation app/Console/EvaluateRagCommand.php app/Providers/AIServiceProvider.php tests/Unit/Services/Documentation/Evaluation tests/Feature/EvaluateRagCommandTest.php docs/rag/evaluations/2026-07-vector-baseline.json
rtk git -C Modules/AI commit -m "feat(ai): measure documentation RAG retrieval quality"
```

### Task 3: Stable chunk metadata and explicit vector retrieval factory

**Files:**

- Create: `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalFactory.php`
- Create: `Modules/AI/app/Ai/Rag/Retrieval/VectorDocumentationRetrieval.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalFactoryTest.php`
- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php`
- Modify: `Modules/AI/app/Services/Documentation/FileDocumentReader.php`
- Modify: `Modules/AI/app/Services/Documentation/Chunking/MarkdownAwareSplitter.php`
- Modify: `Modules/AI/config/config.php`
- Modify: `Modules/AI/tests/Integration/FileDocumentReaderTest.php`
- Modify: `Modules/AI/tests/Integration/MarkdownAwareSplitterTest.php`

- [ ] **Step 1: Write failing metadata tests**

Assert that every chunk has `audience`, `module`, `locale`, `canonical_source`, `heading_breadcrumb`, and `source_type`. Legacy documents without front matter may receive `shared`, `app`, `und`, the normalized source path, an empty breadcrumb, and `file` only in the developer corpus. They remain ineligible for the user corpus until explicitly classified `user` or `shared` and approved by its policy.

- [ ] **Step 2: Implement metadata normalization**

Read optional front matter keys without changing document content semantics. Propagate normalized metadata from the source document to every split chunk. Reject unknown audience values during indexing with a source-specific exception. Never let developer-compatible defaults weaken the user-index deny-by-default rule.

- [ ] **Step 3: Write the failing retrieval factory test**

Assert that missing configuration and `strategy=vector` both resolve to `VectorDocumentationRetrieval`, and unknown strategies fail during agent construction rather than silently switching behavior.

- [ ] **Step 4: Implement the explicit vector wrapper and factory**

`VectorDocumentationRetrieval` implements NeuronAI `RetrievalInterface` and delegates to `SimilarityRetrieval`. `DocumentationRetrievalFactory::make()` receives the vector store and embeddings provider explicitly. Override `DocumentationAgent::retrieval()` to call the factory.

The initial config is:

```php
'retrieval' => [
    'strategy' => env('AI_FAQ_RETRIEVAL', 'vector'),
],
```

Do not add `graph` as an accepted value.

- [ ] **Step 5: Run regression tests**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Ai/Rag/Retrieval/DocumentationRetrievalFactoryTest.php Modules/AI/tests/Integration/DocumentationAgentTest.php Modules/AI/tests/Integration/FileDocumentReaderTest.php Modules/AI/tests/Integration/MarkdownAwareSplitterTest.php Modules/AI/tests/Integration/DocumentationServiceTest.php
```

Expected: PASS and the default strategy remains vector.

- [ ] **Step 6: Re-run the vector evaluation and compare**

Expected: no retrieval regression caused by the wrapper or metadata defaults. If hit@5 or MRR changes, explain the exact corpus/indexing cause in the new report before continuing.

- [ ] **Step 7: Commit**

```bash
rtk git -C Modules/AI add app/Ai/Rag/Retrieval app/Ai/Agents/DocumentationAgent.php app/Services/Documentation config/config.php tests
rtk git -C Modules/AI commit -m "refactor(ai): make documentation retrieval strategy explicit"
```

### Task 4: Feature-flagged Elasticsearch hybrid retrieval

**Files:**

- Create: `Modules/AI/app/Ai/Rag/Retrieval/HybridDocumentationRetrieval.php`
- Create: `Modules/AI/app/Ai/Rag/Retrieval/ReciprocalRankFusion.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/HybridDocumentationRetrievalTest.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/ReciprocalRankFusionTest.php`
- Modify: `Modules/AI/app/Ai/Rag/ElasticsearchRagVectorStore.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalFactory.php`
- Modify: `Modules/AI/config/config.php`
- Modify: `Modules/AI/tests/Unit/Ai/Rag/ElasticsearchRagVectorStoreTest.php`

- [ ] **Step 1: Write failing reciprocal-rank-fusion tests**

Cover documents present in both lists, documents present in only one list, stable tie-breaking, duplicate IDs, and configurable vector/lexical weights. Fusion must use ranks, not raw Elasticsearch scores.

- [ ] **Step 2: Implement deterministic fusion**

Use `score += weight / (60 + rank)` with one-based ranks. Break equal fused scores by best individual rank, then stable document ID.

- [ ] **Step 3: Write failing lexical retrieval tests**

Assert `multi_match` or `simple_query_string` over `content` and heading metadata, exact filters for audience/module/locale, bounded candidate size, and mapping back to Neuron `Document` objects with canonical provenance.

- [ ] **Step 4: Add lexical search to the Elasticsearch store**

Add a documentation-specific method without changing Neuron's `VectorStoreInterface`:

```php
/** @return list<Document> */
public function lexicalSearch(string $query, int $limit, array $filters = []): array;
```

Keep user input in query values, never interpolate it into field names or raw query JSON.

- [ ] **Step 5: Implement hybrid retrieval and safe fallback**

Run vector and lexical retrieval, fuse candidates, and return the configured limit. If the lexical branch fails, log structured diagnostics and return the vector order. A vector failure remains fail-fast.

Enable only through:

```php
'strategy' => env('AI_FAQ_RETRIEVAL', 'vector'), // vector|hybrid
```

For memory/filesystem stores, requesting `hybrid` must fall back to vector with a configuration warning; do not emulate lexical search in PHP.

- [ ] **Step 6: Run tests and evaluation**

Run the targeted retrieval/store tests, then create `Modules/AI/docs/rag/evaluations/2026-07-hybrid-candidate.json` with the same corpus revision and embeddings model as the baseline.

Promotion gate: hybrid may become the recommended Elasticsearch strategy only if hit@5 improves by at least 5 percentage points on the full dataset, no required slice regresses by more than 2 percentage points, and p95 retrieval latency increases by no more than 25%. Otherwise keep `vector` as the documented default.

- [ ] **Step 7: Commit**

```bash
rtk git -C Modules/AI add app/Ai/Rag config/config.php tests/Unit/Ai/Rag docs/rag/evaluations/2026-07-hybrid-candidate.json
rtk git -C Modules/AI commit -m "feat(ai): add measured hybrid documentation retrieval"
```

### Task 5: Optional bounded reranking

**Files:**

- Create: `Modules/AI/app/Ai/Rag/Retrieval/RerankedDocumentationRetrieval.php`
- Create: `Modules/AI/tests/Unit/Ai/Rag/Retrieval/RerankedDocumentationRetrievalTest.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/DocumentationRetrievalFactory.php`
- Modify: `Modules/AI/config/config.php`
- Modify: `Modules/AI/app/Providers/AIServiceProvider.php`

- [ ] **Step 1: Write failing reranker wrapper tests**

Use a fake Core `IReranker`. Assert bounded candidate count, query/document pair construction, stable score ordering, preservation of document provenance, fallback on exceptions, and fallback when score count is invalid.

- [ ] **Step 2: Implement the wrapper**

`RerankedDocumentationRetrieval` decorates another Neuron `RetrievalInterface`. It sends at most `candidate_limit` pairs to `IReranker`, sorts by reranker score with original rank as tie-breaker, and returns `result_limit` documents. It catches reranker failures, logs `rag_retrieval_reranker_fallback`, and returns the decorated retriever order.

- [ ] **Step 3: Add configuration and factory wiring**

```php
'reranker' => [
    'enabled' => env('AI_FAQ_RERANKER_ENABLED', false),
    'candidate_limit' => (int) env('AI_FAQ_RERANKER_CANDIDATES', 20),
    'result_limit' => (int) env('AI_FAQ_MAX_DOCS', 5),
],
```

Reuse the existing Core `IReranker` binding; do not duplicate the cross-encoder client.

- [ ] **Step 4: Run tests and evaluation**

Create a candidate report using the same dataset and environment metadata. Promotion gate: MRR must improve by at least 5% relative to the selected non-reranked strategy, no required slice may regress by more than 2 percentage points, and p95 must remain within the documented interactive latency budget. If no latency budget has been approved, record results but keep reranking disabled by default.

- [ ] **Step 5: Commit**

```bash
rtk git -C Modules/AI add app/Ai/Rag/Retrieval config/config.php app/Providers/AIServiceProvider.php tests/Unit/Ai/Rag/Retrieval docs/rag/evaluations
rtk git -C Modules/AI commit -m "feat(ai): add optional documentation reranking"
```

### Task 6: Graph retrieval decision checkpoint

**Files:**

- Create: `Modules/AI/docs/rag/evaluations/2026-07-retrieval-failure-analysis.md`
- Modify: `Modules/AI/docs/rag/MODULE.md`
- Modify: `Modules/AI/README.md`
- Create only if the gate passes: `docs/superpowers/specs/2026-07-16-rag-graph-retrieval-spike-design.md`
- Create only after that new spec is approved: `docs/superpowers/plans/2026-07-16-rag-graph-retrieval-spike.md`

- [ ] **Step 1: Classify residual evaluation failures**

For every failed case in the selected vector/hybrid/reranked report, assign exactly one primary category: missing corpus content, bad chunking, metadata/filter error, lexical miss, semantic miss, ranking error, multi-hop relationship loss, unsupported-answer behavior, or evaluation-fixture defect.

- [ ] **Step 2: Apply the graph authorization gate**

Authorize a graph spike only if multi-hop relationship loss accounts for at least 10% of valid residual failures and at least 10 representative multi-hop cases remain unsolved by the best non-graph strategy. Otherwise document “graph spike not authorized” and stop.

- [ ] **Step 3: If authorized, write a separate spike spec**

The spike spec must compare at least one no-graph baseline with the candidate graph implementation and define entity extraction, edge provenance, incremental refresh, deletion, tenant isolation, permissions, fallback, citations, latency, indexing cost, storage cost, and teardown. Graphify may be one candidate but must not appear in public application contracts.

- [ ] **Step 4: Update module documentation**

Record the selected default strategy, evaluation report links, and graph decision. Keep `AI_FAQ_RETRIEVAL=vector` as the default unless the relevant promotion gate was passed and explicitly approved.

- [ ] **Step 5: Run documentation tests and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/AiRagModuleDocumentationTest.php
rtk git -C Modules/AI add docs/rag README.md
rtk git -C Modules/AI commit -m "docs(ai): record measured RAG retrieval decision"
rtk git add docs/superpowers/specs docs/superpowers/plans
rtk git commit -m "docs(ai): record graph retrieval gate"
```

---

## Plan self-review

| Spec requirement | Plan coverage |
|---|---|
| Vector RAG remains baseline | Tasks 2–3 |
| Measurement precedes complexity | Tasks 1–2 and promotion gates |
| Metadata and audience scoping | Task 3 |
| Hybrid before graph | Task 4 |
| Reranking before graph | Task 5 |
| Graph is optional and disabled | Task 6 |
| Graph Explorer remains separate | Spec and Task 6 documentation |
| Canonical citations survive retrieval changes | Tasks 3–5 tests |
| Safe fallback behavior | Tasks 4–5 |
| Tenant/permission graph requirements | Task 6 spike-spec gate |
| In-app corpus isolation, ACL, guardrails, and Core Graph tools | Separate mandatory security prerequisite plan |

**Placeholder scan:** No placeholders remain. The concrete graph-spike filenames are reserved but must not be created unless Task 6 passes and a separate brainstorming cycle approves the spike. No graph implementation task is authorized here.

**Type consistency:** All retrieval implementations use NeuronAI `RetrievalInterface`; reranking reuses Core `IReranker`; retrieved values remain NeuronAI `Document` objects consumed by the existing agent and citation flow.
