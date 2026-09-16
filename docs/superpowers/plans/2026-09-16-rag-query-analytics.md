# RAG Query Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (or executing-plans). Steps use checkbox (`- [ ]`) tracking.

**Goal:** Record one append-only document per answered documentation-RAG query so corpus gaps, language mix, abstention rate and retrieval latency become visible, without ever reading the log back on the request path or into retrieval.

**Architecture:** A dedicated Elasticsearch index `{app}_rag_queries` (its own index, never mixed with document vectors), written by a queued job dispatched from the in-app documentation answer flow behind `config('ai.features.faq.query_logging.enabled')`. Identity is a keyed hash (`user_ref`), query text is hashed/raw/off by config, guest and impersonated principals are excluded, and a retention job prunes old documents. Write-only telemetry: nothing in the running app reads it.

**Tech Stack:** Laravel 12, PHP 8.5, Core `ElasticsearchService`, queued jobs, Pest 4. Mirrors `CreateRagElasticsearchIndexCommand` for index creation and the `features.faq` config block.

**Spec:** `docs/superpowers/specs/2026-09-16-rag-query-analytics-design.md`

**Workspace rule:** Run Artisan and tests from the Laraplate application root. `Modules/AI` is a nested Git repository, so commit AI module files with `rtk git -C Modules/AI ...`; commit the application-level spec and plan with `rtk git ...` from the Laraplate root.

## Global Constraints

- **Task 1 is a hard gate.** No code in Tasks 2-6 starts until the privacy review signs off. The feature ships `enabled=false` regardless.
- Write-only: the index is never queried by the running application, never feeds retrieval, ranking or tuning.
- Off the hot path: the write is queued and fail-open — a disabled queue, a missing index or a write error must never delay or fail an answer.
- No raw user id, no answer text, no document bodies, no secrets are ever stored.
- Guest (`User::isGuest()`) and impersonated (`User::isImpersonated()`) principals are excluded from logging.
- ES-only: no database table, so no `AITables` entry and no migration.

## Scope and sequencing

Task 1 gates everything. Tasks 2-3 build the index and the writer in isolation (pure unit-testable pieces). Task 4 wires the writer into the answer flow. Task 5 adds retention and erasure. Task 6 documents. Do not dispatch the writer from the answer flow (Task 4) before its unit tests (Task 3) pass.

### Task 1: Privacy review (blocking gate, no code)

- [ ] **Step 1: Produce a short decision record** at `Modules/AI/docs/rag/query-analytics-privacy-review.md` and obtain sign-off before writing any code. It must approve, explicitly:
  - the field set in the spec and the `query_text_mode` default (`hashed`);
  - the `user_ref` hashing scheme: HMAC-SHA256 with an app secret, key storage and rotation policy (state that a rotated key breaks cross-rotation correlation, which is acceptable);
  - the `retention_days` window and the pruning job that enforces it;
  - the guest/impersonation exclusion;
  - the erasure mapping: an erasure request deletes documents by the subject's `user_ref` hash.
- [ ] **Step 2:** If any item is not approved, stop. Record the open question in the spec under a *Decision* line and pause the plan. Do not build a logger on an unapproved field set.

### Task 2: Config, index name and mapping

**Files:**

- Modify: `Modules/AI/config/config.php` (add the `query_logging` subtree under `features.faq`)
- Create: `Modules/AI/app/Console/CreateRagQueryAnalyticsIndexCommand.php`
- Create: `Modules/AI/tests/Feature/CreateRagQueryAnalyticsIndexCommandTest.php`

- [ ] **Step 1: Add config** exactly as the spec states:

```php
'query_logging' => [
    'enabled' => env('AI_FAQ_QUERY_LOGGING_ENABLED', false),
    'index' => env('AI_FAQ_QUERY_LOG_INDEX', Str::slug(config('app.name')).'_rag_queries'),
    'query_text_mode' => env('AI_FAQ_QUERY_LOG_TEXT', 'hashed'), // hashed | raw | off
    'retention_days' => (int) env('AI_FAQ_QUERY_LOG_RETENTION_DAYS', 90),
],
```

- [ ] **Step 2: Add the index-creation command** `ai:create-rag-query-index`, mirroring `CreateRagElasticsearchIndexCommand` and using Core `ElasticsearchService`. Mapping: `keyword` for `user_ref`/`tenant`/`profile`/`locale`/`index`/`answered`, `text` (or `keyword`) for `query`, `integer` for the counts, `float` for `latency_ms`, `date` for `logged_at`. No `dense_vector`. The command refuses to recreate an existing index unless `--force`.
- [ ] **Step 3: Test** creation and idempotence with a fake/asserted ES client. Verify the mapping has no vector field.

- [ ] **Step 4: Commit**

```bash
rtk git -C Modules/AI add config/config.php app/Console/CreateRagQueryAnalyticsIndexCommand.php tests/Feature/CreateRagQueryAnalyticsIndexCommandTest.php
rtk git -C Modules/AI commit -m "feat(ai): add RAG query analytics index and config"
```

### Task 3: Record DTO and writer service

**Files:**

- Create: `Modules/AI/app/Services/Documentation/Analytics/RagQueryLog.php` (DTO / array shape)
- Create: `Modules/AI/app/Services/Documentation/Analytics/RagQueryAnalyticsWriter.php`
- Create: `Modules/AI/tests/Unit/Services/Documentation/Analytics/RagQueryAnalyticsWriterTest.php`

- [ ] **Step 1: Write failing writer tests.** Cover: `user_ref` is the HMAC of the user id and never the raw id; `query_text_mode=hashed|raw|off` stores hashed / raw / no query respectively; a guest principal produces no write; an impersonated principal produces no write; the document shape matches the mapping; a thrown ES error is swallowed (fail-open) and logged.
- [ ] **Step 2: Implement the DTO** as a `final readonly` value carrying exactly the spec's fields.
- [ ] **Step 3: Implement the writer.** It takes the `AssistantAccessContext` (the resolved principal, already used by `InAppDocumentationRetrieval`) plus the query, counts, `answered`, latency and serving index. It computes `user_ref` with `hash_hmac('sha256', (string) $userId, $appSecret)`, applies `query_text_mode`, checks eligibility (`isGuest()`/`isImpersonated()` → skip), and indexes one document via Core `ElasticsearchService`. Every ES failure is caught, logged as `rag_query_analytics_write_failed`, and swallowed.

- [ ] **Step 4: Run and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Unit/Services/Documentation/Analytics/RagQueryAnalyticsWriterTest.php
rtk git -C Modules/AI add app/Services/Documentation/Analytics tests/Unit/Services/Documentation/Analytics
rtk git -C Modules/AI commit -m "feat(ai): add RAG query analytics writer"
```

### Task 4: Queued job and dispatch from the answer flow

**Files:**

- Create: `Modules/AI/app/Jobs/LogRagQueryJob.php`
- Modify: the in-app documentation answer flow (`Modules/AI/app/Services/DocumentationService.php`, where `InAppDocumentationRetrieval::retrieve()` results become the answered response — confirm the exact method before editing)
- Create: `Modules/AI/tests/Feature/RagQueryAnalyticsLoggingTest.php`

- [ ] **Step 1: Write failing feature tests.** With logging enabled: one answered query writes exactly one document with the expected fields. With `enabled=false`: no job is dispatched. With a guest principal: no document. With the queue faked: assert `LogRagQueryJob` is pushed, not run inline. With the writer throwing: the answer still returns normally.
- [ ] **Step 2: Implement the job** as a queued job carrying the primitive payload (never an Eloquent model of the user), calling `RagQueryAnalyticsWriter`.
- [ ] **Step 3: Dispatch from the answer flow.** After the answer and its citations are assembled, if `config('ai.features.faq.query_logging.enabled')`, dispatch `LogRagQueryJob` with the query, `retrieved_count`, `citation_count`, `answered`, measured `latency_ms`, serving index and the `AssistantAccessContext`. Guard the dispatch itself in a try/catch so a dispatch failure cannot surface to the caller.

- [ ] **Step 4: Run and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/RagQueryAnalyticsLoggingTest.php
rtk git -C Modules/AI add app/Jobs/LogRagQueryJob.php app/Services/DocumentationService.php tests/Feature/RagQueryAnalyticsLoggingTest.php
rtk git -C Modules/AI commit -m "feat(ai): log answered RAG queries off the hot path"
```

### Task 5: Retention and erasure

**Files:**

- Create: `Modules/AI/app/Console/PruneRagQueryAnalyticsCommand.php`
- Modify: the AI module schedule registration (confirm where the module schedules commands)
- Create: `Modules/AI/tests/Feature/PruneRagQueryAnalyticsCommandTest.php`

- [ ] **Step 1: Write failing tests.** `ai:prune-rag-queries` deletes documents with `logged_at` older than `retention_days` and leaves newer ones. An erasure call deletes every document for a given `user_ref` hash.
- [ ] **Step 2: Implement** the prune command (delete-by-query on `logged_at`) and an erasure entry point keyed on the subject's `user_ref` hash. Schedule the prune daily. Wire the erasure entry point to the existing user-erasure path if one exists; otherwise expose it as a documented command and note the integration point.

- [ ] **Step 3: Run and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/PruneRagQueryAnalyticsCommandTest.php
rtk git -C Modules/AI add app/Console/PruneRagQueryAnalyticsCommand.php tests/Feature/PruneRagQueryAnalyticsCommandTest.php
rtk git -C Modules/AI commit -m "feat(ai): retention and erasure for RAG query analytics"
```

### Task 6: Documentation

**Files:**

- Modify: `Modules/AI/docs/rag/MODULE.md`
- Modify: `Modules/AI/README.md` (the four new `AI_FAQ_QUERY_LOG*` env vars, in the existing style)

- [ ] **Step 1: Document** the feature in `MODULE.md`: what is logged, that it is off by default and write-only, the privacy posture (hashing, `query_text_mode`, retention, erasure, guest/impersonation exclusion), and that it never feeds retrieval. Record the four env vars in `README.md`.
- [ ] **Step 2: Run the module documentation test and commit**

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/AiRagModuleDocumentationTest.php
rtk git -C Modules/AI add docs/rag/MODULE.md README.md
rtk git -C Modules/AI commit -m "docs(ai): document RAG query analytics"
```

## Final verification

- [ ] Privacy review signed off (Task 1) before any code landed.
- [ ] Feature is `enabled=false` by default; nothing writes unless explicitly enabled.
- [ ] No code path reads the index; no retrieval or tuning consumes it.
- [ ] Guest and impersonated principals never produce a document.
- [ ] A writer/queue failure never affects the answer (fail-open verified by test).
- [ ] `rtk vendor/bin/pint --dirty` clean.

## Out of scope (per spec)

- Any read/reporting UI or dashboard over the index.
- Feeding analytics into retrieval or into L2 tuning (`2026-09-15-measured-retrieval-tuning-l1-design.md`) — a later, separately reviewed decision.
- Logging non-documentation (CRUD / application-content) search.
- The search-timing debug meta of `2026-09-16-search-modes-and-strategy-resolution` (a different path, ephemeral).

## Notes for the executor

- Confirm the exact answer-flow method in `DocumentationService` before editing; `InAppDocumentationRetrieval::retrieve()` returns the documents, but `answered`, `citation_count` and latency are known one level up where the answer is assembled.
- Reuse Core `ElasticsearchService`; do not build a second ES client.
- Keep user input in query *values* only; never interpolate it into field names or raw query JSON.
