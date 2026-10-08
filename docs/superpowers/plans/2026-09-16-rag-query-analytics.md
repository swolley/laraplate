# RAG Query Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (or executing-plans). Steps use checkbox (`- [ ]`) tracking.

**Goal:** Record one append-only document per answered documentation-RAG query so corpus gaps, language mix, abstention rate and retrieval latency become visible, without ever reading the log back on the request path or into retrieval.

**Architecture:** A dedicated Elasticsearch index `{app}_rag_queries` (its own index, never mixed with document vectors), written by a queued job dispatched from the in-app documentation answer flow behind the seeded setting `features.faq.query_logging.enabled`. Identity is a keyed hash (`user_ref`), query text is raw or off by setting, guest and impersonated principals are excluded, and a retention job prunes old documents. Write-only telemetry: nothing in the running app reads it.

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

- [x] **Step 1: Produce a short decision record** at `Modules/AI/docs/rag/query-analytics-privacy-review.md` and obtain sign-off before writing any code. Done 2026-10-08: signed off by the owner with three amendments recorded in the spec (*Decision (2026-10-08)*): query text `raw` or `off` (no `hashed`), retention 30 days, switch and tuning values as seeded settings. Key: `APP_KEY`, erasure also matches `APP_PREVIOUS_KEYS`. It must approve, explicitly:
  - the field set in the spec and the `query_text_mode` default (`hashed`);
  - the `user_ref` hashing scheme: HMAC-SHA256 with an app secret, key storage and rotation policy (state that a rotated key breaks cross-rotation correlation, which is acceptable);
  - the `retention_days` window and the pruning job that enforces it;
  - the guest/impersonation exclusion;
  - the erasure mapping: an erasure request deletes documents by the subject's `user_ref` hash.
- [x] **Step 2:** (Not needed: every item was approved.) If any item is not approved, stop. Record the open question in the spec under a *Decision* line and pause the plan. Do not build a logger on an unapproved field set.

### Task 2: Config, index name and mapping

**Files:**

- Modify: `Modules/AI/config/config.php` (add the `query_logging.index` key under `features.faq`)
- Modify: `Modules/AI/database/seeders/AIDatabaseSeeder.php` (seed the three `features.faq.query_logging.*` settings)
- Create: `Modules/AI/app/Console/CreateRagQueryAnalyticsIndexCommand.php`
- Create: `Modules/AI/tests/Feature/CreateRagQueryAnalyticsIndexCommandTest.php`

- [x] **Step 1: Add the settings and the index name** as the privacy review states. Seed in `AIDatabaseSeeder`, beside the other `features.faq.*` settings: `features.faq.query_logging.enabled` (Boolean, `false`), `features.faq.query_logging.query_text_mode` (String, `raw`, options `raw` and `off`), `features.faq.query_logging.retention_days` (Integer, `30`). In `config.php`, only the index name, like the other RAG indexes: Done: the three settings are seeded and mirrored as defaults in `config.php`, as the other seeded `features.faq.*` keys are.

```php
'query_logging' => [
    'index' => env('AI_FAQ_QUERY_LOG_INDEX', Str::slug(config('app.name')).'_rag_queries'),
],
```

- [x] **Step 2: Add the index-creation command** `ai:create-rag-query-index`, mirroring `CreateRagElasticsearchIndexCommand` and using Core `ElasticsearchService`. Mapping: `keyword` for `user_ref`/`tenant`/`profile`/`locale`/`index`/`answered`, `text` (or `keyword`) for `query`, `integer` for the counts, `float` for `latency_ms`, `date` for `logged_at`. No `dense_vector`. The command refuses to recreate an existing index unless `--force`. Done: `CreateRagQueryAnalyticsIndexCommand` takes the container `Client` (the one Core binds) instead of `ElasticsearchService`, so the success path is testable; the mapping is in `RagQueryLogIndex`, `dynamic: strict`, `answered` as `boolean` and `query` as text with a `keyword` sub-field.
- [x] **Step 3: Test** creation and idempotence with a fake/asserted ES client. Verify the mapping has no vector field. Done: `tests/Feature/Console/CreateRagQueryAnalyticsIndexCommandTest.php`.

- [x] **Step 4: Commit** (`Modules/AI` `364b085`; the test lives in `tests/Feature/Console/`)

```bash
rtk git -C Modules/AI add config/config.php app/Console/CreateRagQueryAnalyticsIndexCommand.php tests/Feature/CreateRagQueryAnalyticsIndexCommandTest.php
rtk git -C Modules/AI commit -m "feat(ai): add RAG query analytics index and config"
```

### Task 3: Record DTO and writer service

**Files:**

- Create: `Modules/AI/app/Services/Documentation/Analytics/RagQueryLog.php` (DTO / array shape)
- Create: `Modules/AI/app/Services/Documentation/Analytics/RagQueryAnalyticsWriter.php`
- Create: `Modules/AI/tests/Unit/Services/Documentation/Analytics/RagQueryAnalyticsWriterTest.php`

- [x] **Step 1: Write failing writer tests.** Cover: `user_ref` is the HMAC of the user id and never the raw id; `query_text_mode=raw|off` stores the query as typed / no query; a guest principal produces no write; an impersonated principal produces no write; the document shape matches the mapping; a thrown ES error is swallowed (fail-open) and logged. Done, with one change: guest and impersonated principals are checked where the request is (Task 4), not in the writer. `User::isImpersonated()` is session-backed and always false in a queue, and the `AssistantAccessContext` carries no `User`; so the record is built and screened in the request and the job carries a document with no user id.
- [x] **Step 2: Implement the DTO** as a `final readonly` value carrying exactly the spec's fields. Done: `RagQueryLog::fromAnswer()` and `toDocument()`; `answered` is `citation_count > 0`; `tenant` is the tenant id or `global`.
- [x] **Step 3: Implement the writer.** It takes the `AssistantAccessContext` (the resolved principal, already used by `InAppDocumentationRetrieval`) plus the query, counts, `answered`, latency and serving index. It computes `user_ref` with `hash_hmac('sha256', (string) $userId, <APP_KEY>)`, applies `query_text_mode`, checks eligibility (`isGuest()`/`isImpersonated()` → skip), and indexes one document via Core `ElasticsearchService`. Every ES failure is caught, logged as `rag_query_analytics_write_failed`, and swallowed. Done: `RagQueryUserReference` (HMAC under `APP_KEY`, and `candidatesFor()` under every previous key) and `RagQueryAnalyticsWriter`, which takes the container `Client`; a failure logs the exception class only, since an Elasticsearch error can echo the question.

- [x] **Step 4: Run and commit** (`Modules/AI` `e5949af`)

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

- [x] **Step 1: Write failing feature tests.** With logging enabled: one answered query writes exactly one document with the expected fields. With `enabled=false`: no job is dispatched. With a guest principal: no document. With the queue faked: assert `LogRagQueryJob` is pushed, not run inline. With the writer throwing: the answer still returns normally. Done: `tests/Feature/Assistance/RagQueryAnalyticsLoggingTest.php`. A guest never reaches `respond()` (the access context factory refuses it first), so the guest case is tested on the recorder; the impersonated case goes through `respond()`.
- [x] **Step 2: Implement the job** as a queued job carrying the primitive payload (never an Eloquent model of the user), calling `RagQueryAnalyticsWriter`. Done: `LogRagQueryJob` carries the finished document (no user id, no model), one try.
- [x] **Step 3: Dispatch from the answer flow.** After the answer and its citations are assembled, if `config('ai.features.faq.query_logging.enabled')`, dispatch `LogRagQueryJob` with the query, `retrieved_count`, `citation_count`, `answered`, measured `latency_ms`, serving index and the `AssistantAccessContext`. Guard the dispatch itself in a try/catch so a dispatch failure cannot surface to the caller. Done, in `InAppAssistanceService::respond()`, the in-app flow that calls `InAppDocumentationRetrieval` (not `DocumentationService`, which only forwards to it). `RagQueryRecorder` checks the switch, the guest and the impersonation, builds the record and dispatches, inside a try/catch. `retrieved_count` is the documentation documents after the similarity floor, and every one of them becomes a citation in this flow, so `citation_count` equals it; `answered` is false when retrieval found nothing. Refused turns are not logged.

- [x] **Step 4: Run and commit** (`Modules/AI` `bc3bdb9`)

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

- [x] **Step 1: Write failing tests.** `ai:prune-rag-queries` deletes documents with `logged_at` older than `retention_days` and leaves newer ones. An erasure call for a user deletes every document whose `user_ref` is the HMAC of their id under `APP_KEY` or any key in `APP_PREVIOUS_KEYS`. Done: `tests/Feature/Console/PruneRagQueryAnalyticsCommandTest.php` (prune by the setting, a missing index, erasure under both keys, a failure, the daily schedule).
- [x] **Step 2: Implement** the prune command (delete-by-query on `logged_at`) and an erasure entry point keyed on the subject's user id, matching the hashes under the current and the previous keys. Schedule the prune daily. Wire the erasure entry point to the existing user-erasure path if one exists; otherwise expose it as a documented command and note the integration point. Done: `RagQueryRetention` (`prune()`, `eraseUser()`), `ai:prune-rag-queries` scheduled daily in `AIServiceProvider::registerCommandSchedules()`, and `ai:erase-rag-queries {user}`. No user-erasure path exists in the code, so the command is the documented entry point. Both act even with the log switched off, and do nothing when the index was never created; a retention below one day counts as one.

- [x] **Step 3: Run and commit** (`Modules/AI` `53d0dfe`)

```bash
rtk php artisan test --compact Modules/AI/tests/Feature/PruneRagQueryAnalyticsCommandTest.php
rtk git -C Modules/AI add app/Console/PruneRagQueryAnalyticsCommand.php tests/Feature/PruneRagQueryAnalyticsCommandTest.php
rtk git -C Modules/AI commit -m "feat(ai): retention and erasure for RAG query analytics"
```

### Task 6: Documentation

**Files:**

- Modify: `Modules/AI/docs/rag/MODULE.md`
- Modify: `Modules/AI/README.md` (the new `AI_FAQ_QUERY_LOG_INDEX` env var, in the existing style; the three settings are documented in the module docs)

- [x] **Step 1: Document** the feature in `MODULE.md`: what is logged, that it is off by default and write-only, the privacy posture (hashing, `query_text_mode`, retention, erasure, guest/impersonation exclusion), and that it never feeds retrieval. Record the four env vars in `README.md`. Done: `Modules/AI/docs/rag/MODULE.md` (*Documentation query analytics*, developer), `Modules/AI/docs/rag/DEPLOYMENT.md` (*Documentation query log*, operator), and the README for `AI_FAQ_QUERY_LOG_INDEX`, the only env var: the other three are settings.
- [x] **Step 2: Run the module documentation test and commit** (`Modules/AI` `2a1a624`; the documentation and baseline gate tests pass, no committed evaluation report changed)

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/AiRagModuleDocumentationTest.php
rtk git -C Modules/AI add docs/rag/MODULE.md README.md
rtk git -C Modules/AI commit -m "docs(ai): document RAG query analytics"
```

## Final verification

- [x] Privacy review signed off (Task 1) before any code landed.
- [x] Feature is `enabled=false` by default; nothing writes unless explicitly enabled. (seeded `false`; `RagQueryAnalyticsLoggingTest` covers the switch)
- [x] No code path reads the index; no retrieval or tuning consumes it. (the only clients are the writer, the index command and `RagQueryRetention`, which deletes)
- [x] Guest and impersonated principals never produce a document.
- [x] A writer/queue failure never affects the answer (fail-open verified by test).
- [x] `rtk vendor/bin/pint --dirty` clean. (Pint run from the root on each task's explicit file list, as `.ai/rules/modules.md` requires)

## Out of scope (per spec)

- Any read/reporting UI or dashboard over the index.
- Feeding analytics into retrieval or into L2 tuning (`2026-09-15-measured-retrieval-tuning-l1-design.md`) — a later, separately reviewed decision.
- Logging non-documentation (CRUD / application-content) search.
- The search-timing debug meta of `2026-09-16-search-modes-and-strategy-resolution` (a different path, ephemeral).

## Notes for the executor

- Confirm the exact answer-flow method in `DocumentationService` before editing; `InAppDocumentationRetrieval::retrieve()` returns the documents, but `answered`, `citation_count` and latency are known one level up where the answer is assembled.
- Reuse Core `ElasticsearchService`; do not build a second ES client.
- Keep user input in query *values* only; never interpolate it into field names or raw query JSON.

## Delivery status (2026-10-08): delivered

All six tasks are done, each committed on its own in `Modules/AI` (`364b085`, `e5949af`, `bc3bdb9`, `53d0dfe`, `2a1a624`). Divergences from the plan, and why:

- **The privacy review amended the spec** (*Decision (2026-10-08)*): the question is stored as typed (`raw`) or not at all (`off`), `hashed` is dropped; retention is 30 days; the switch, the mode and the retention are seeded settings, and only the index name is an env var. The HMAC key is `APP_KEY`, and erasure also matches `APP_PREVIOUS_KEYS`.
- **The guest and impersonation screen is in the request, not in the writer.** `User::isImpersonated()` is session-backed and false in a queue, and the access context carries no `User`. `RagQueryRecorder` screens and builds the record in the request; the job carries a finished document with no user id.
- **The answer flow is `InAppAssistanceService::respond()`**, not `DocumentationService`, which only forwards to `InAppDocumentationRetrieval`. In that flow every retrieved document becomes a citation, so `citation_count` equals `retrieved_count`, and `answered` is false when retrieval found nothing. Refused turns are not logged.
- **The Elasticsearch client is the one Core binds in the container**, not the `ElasticsearchService` singleton: same configuration, no client built here, and the success paths are testable with a mocked `Client`.
- **Erasure is a command**, `ai:erase-rag-queries {user}`: no user-erasure flow exists in the code to hook into. When one does, it calls `RagQueryRetention::eraseUser()`.
- **Not run by this plan:** the full suite. The AI suite and the root tests were run after each task. The root tests found one failure unrelated to this plan, `tests/Feature/AdminNavigationGroupsTest.php` (MES declared its "Machine connectivity" group after "MES"), fixed in `MESPlugin` the same day.

**Documented in:** `Modules/AI/docs/rag/MODULE.md` (*Documentation query analytics*), `Modules/AI/docs/rag/DEPLOYMENT.md` (*Documentation query log*), `Modules/AI/docs/rag/query-analytics-privacy-review.md`, `Modules/AI/README.md` (`AI_FAQ_QUERY_LOG_INDEX`).
