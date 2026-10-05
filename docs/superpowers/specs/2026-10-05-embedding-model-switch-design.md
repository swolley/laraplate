# Embedding model switch: design

**Status:** Implemented (2026-10-05) for what Tasks 1 to 10 and 12 of the plan delivered (`docs/superpowers/plans/2026-10-05-embedding-model-switch.md`); Task 13 of the plan is on hold, awaiting a decision, and the plan is not closed. Divergences found while building are recorded inline, marked *As built*, and in section 11. PostgreSQL and the Elasticsearch branches are not verified against a real server (section 10).
**Related:** `2026-09-12-es-multilingual-index-and-multimodel-embeddings-design.md` (the profile registry and the `model_key` stamp this builds on), `2026-09-29-ai-model-selection-and-setting-actions-design.md` (the Settings pattern, which excluded embeddings for the reason this spec answers).

## 1. Purpose

Changing the embedding model changes the vectors, and often their length. Today that is a manual, unguarded operation: an environment variable picks the profile, the vector length comes from a different, global setting, and nothing checks that the two agree. A model with other dimensions needs a new index mapping and every vector recomputed, and an operator can get that wrong without being told.

Success looks like this. An operator picks a model from a dropdown in Settings, is told plainly what the change costs and what search does meanwhile, confirms, and a guided procedure rebuilds what has to be rebuilt and activates the new model only when it has been verified. A mismatch between the model, the stored vectors and the index never reaches a user as wrong results or an engine error: vector search switches itself off with a reason instead.

## 2. What exists today

Facts read from the code on 2026-10-05.

- Profiles are `ai.features.embeddings.models` in config; the active one is `AI_EMBEDDINGS_MODEL`. A profile has `provider`, `service_model`, prefixes and `normalize`. `EmbeddingModelRegistry` builds the profile and takes its `dimensions` and `similarity` from Core's `core.search.vector.*`, never from the profile.
- `search.vector.dimensions` (384) and `search.vector.similarity` are runtime settings in Core. The dimension is read when the Elasticsearch `dense_vector` mapping is created, by the query translator, and by the registry.
- The profile's `provider` field is read by nobody. The provider is chosen separately by `AI_EMBEDDINGS_PROVIDER` in `EmbeddingsProviderFactory`.
- The FAQ/documentation RAG index has its own copy, `ai.features.faq.elasticsearch.embedding_dims` (384), used by `ai:create-rag-index`.
- `core_model_embeddings` stamps each row with `locale` and `model_key`. The index on model, id and locale is not unique, so vectors of two models can coexist. On MySQL the column is JSON with no fixed length; on PostgreSQL with pgvector it is `vector(N)`, fixed when the migration runs.
- The only dimension check is the probe in `ai:embeddings:repair`: it embeds a text and compares the length with the profile. `--stale` already finds rows stamped with another `model_key`.
- `GenerateEmbeddingsJob` stamps the active profile's key. `AdvancedSearchService::resolveVector()` decides whether a query is embedded.
- The multimodel spec of 2026-09-12 decided that the profile is the single source of truth for dimensions and noted that a model with other dimensions "will require a column migration". The implementation did the opposite, and the comment in the registry ("can never drift apart") is not enforced by anything.

## 3. Decisions

1. **During a switch, vector search is off, not zero-gap.** Search continues by keywords. There are no production instances and the corpus is a few hundred contents, so a rebuild takes minutes. A zero-gap switch (section 9) costs far more than the problem it removes today.
2. **The profile declares its dimensions and is the source of truth.** Core keeps reading `core.search.vector.*` and stays independent of the AI module; the AI module writes those two settings when a switch activates, and they stop being hand-editable.
3. **The length of a model's vectors is verified by probing the service**, not trusted from a declaration or from documentation.
4. **The dropdown never changes the serving model.** It records a target. The serving model changes only when the procedure activates it.
5. **A change of model needs a full re-embed even when the dimensions are equal**, because the vector spaces differ. Only a change of dimensions also needs the index mapping rebuilt.
6. **A guard is always on**, outside any switch: a mismatch between profile, stored vectors and index disables vector search with a reason.
7. **A user is warned before it happens**, with the numbers that apply to their corpus.
8. **A model is named `provider:model`**, the convention of the 2026-09-29 spec (`ollama:llama3.2:3b`, split on the first colon). The setting values, the profile keys, the Core setting `search.vector.model` and the `model_key` stamped on every vector are all that one string, so the Core can filter by it with no translation. Embeddings stamped with the old alias keys are not migrated: they are stale and are re-created.

## 4. Components

### 4.1 Profile and dimensions (AI)

Each profile in config gains `dimensions` (and keeps `similarity` optional, default cosine). The registry returns them from the profile, no longer from Core. The profiles that exist both declare 384.

`EmbeddingDimensionProbe` embeds a fixed short text through the provider the profile names and returns the length of the vector. It is the one place that measures; `ai:embeddings:repair` uses it instead of its own inline check. A profile whose declared `dimensions` differs from the measured length is rejected: it cannot be selected as a target and cannot be activated.

`ai:embeddings:probe {profile}` prints the measured length. This is how a new profile is added: run it, put the number in config. The service needs no change, since measuring works for any provider. For a hosted provider that lets the caller choose the dimension (the OpenAI embedding models do, as far as documented; not verified here), the profile declares the dimension it asks for and the probe verifies it.

A profile is keyed by `provider:service_model`, for example `sentence_transformers:intfloat/multilingual-e5-small`; its provider and service model are the two parts of the key, split at the first colon, and the config blocks no longer carry them. The provider replaces `AI_EMBEDDINGS_PROVIDER`; a profile whose provider is not configured is not offered.

### 4.2 Settings (Core and AI)

New command-managed settings, group `ai`, in the sense of the 2026-09-29 spec (written by a command, not by hand):

| Setting | Meaning | Written by |
|---|---|---|
| `features.embeddings.model` | the `provider:model` the operator chose (the **target**) | the dropdown, through the confirmation of 4.3 |
| `features.embeddings.active` | the `provider:model` whose vectors serve search | the switch procedure at activation |
| `search.vector.model` (Core) | the `provider:model` whose vectors the Core queries: the filter of `DatabaseEngine` and the value stamped in `core_model_embeddings.model_key` | the switch procedure at activation |
| `features.embeddings.switch` | state of a switch: status, phase, target, counts, error, timestamps | the switch procedure |

`search.vector.dimensions` and `search.vector.similarity` become command-managed, written together with `active` and `search.vector.model`. `search.vector.suspended_reason` (Core, string or null) is set when a switch starts and cleared when it ends; the guard reads it.

*As built:* a managed setting is a `managed` boolean column of `core_settings` (create migration), read-only in the form and written by `Setting::writeManaged()`, which skips approval. `suspended_reason` holds `switching` during a switch; it is cleared by writing the JSON string `null` directly (the column is NOT NULL and the observer turns `''` into null), then flushing the setting cache. Activation also writes `features.embeddings.model` to the target with a direct update (the setting is not managed and a plain save would go through approval), so the dropdown names the model the switch ended on. The Core seeds `search.vector.model` with a fixed `sentence_transformers:intfloat/multilingual-e5-small`, while the AI seeds `active` from the first configured profile; on an installation whose first configured profile differs the two disagree until the first switch.

The dropdown's choices are the profile keys that are configured and probe-able; no remote listing is needed. A fresh installation seeds `active` from the first profile in code, as the 2026-09-29 spec prescribes (default in code, no environment variable). `AI_EMBEDDINGS_MODEL` and `AI_EMBEDDINGS_PROVIDER` are removed. This is a breaking change with a known footprint, all of it to be updated by the plan: the readers of `ai.features.embeddings.active` and `default_provider` (`EmbeddingModelRegistry`, `EmbeddingsProviderFactory`), the tests that set those config keys (embedding, prefix and documentation-retrieval tests), and the documents that name the variables (the AI README, `SEARCH_AND_TRANSLATION.md`, `SENTENCE_TRANSFORMERS_INSTALLATION.md`, and the Core README).

### 4.3 The alert (Filament)

Choosing a different profile in the dropdown opens a confirmation, not a save. It shows:

- current and target model, and current and target dimensions;
- which class of change it is: **dimensions differ** (mapping rebuilt and everything re-embedded) or **same dimensions** (re-embed only);
- how much work: the number of records to embed (embeddable records times their translations) and a rough time, from the probe's latency times the batch count, labelled as an estimate;
- what happens meanwhile: vector search is off and search uses keywords only, until the switch ends;
- that going back is the same procedure and costs the same.

Confirm sets the target and starts `ai:embeddings:switch` queued. Cancel leaves everything as it was. Choosing the active profile does nothing. While a switch is running or failed, the Settings page shows a persistent banner with the phase and counts, and the dropdown is disabled. The confirmation is a generic Core hook, `ISettingChangeConfirmation`, which the AI module registers for `features.embeddings.model`; the same hook locks the field while a switch runs.

*As built:* there is no page banner: the locked field shows the phase and counts, or the error, as its helper text. The warning is the readonly DTO `Modules/Core/app/Data/SettingChangeWarning.php` (the existing `app/Data` folder, not a new `DTOs` one). The lock is a lock of the settings form only; the invariant is that `ai:embeddings:switch` refuses to start while a switch is running or failed. Core calls `warn()` twice per confirmed change (before the modal, and after the save to check the change is still one it warns about), so an implementation must stay cheap and write nothing. The confirmation queues the command with `--report-failure`: a start refused by its checks is then stored as `failed` in phase `preflight` with the reason, which locks the field and shows it, with vector search left on; `--abandon` clears it. A change captured for approval starts no switch, even once approved. The time estimate is measured for `sentence_transformers` only, with a 3-second timeout, cached.

### 4.4 The switch procedure (AI)

`ai:embeddings:switch {profile} [--resume|--abandon]`, run by an orchestrating job that advances one phase at a time and re-dispatches itself, so a worker restart loses nothing.

1. **Preflight.** Take a lock so only one switch runs. Check the service answers `/health`, that the model it reports is the profile's (the check `ai:embeddings:repair` already does), and that the probe measures the declared dimensions. Set `search.vector.suspended_reason` and the state. Nothing has changed for a user except that vector search is off.
2. **Embeddings.** Re-embed every embeddable record with the **target** profile, stamping the target `model_key`. `GenerateEmbeddingsJob` gains an explicit profile argument; it uses the active one when none is given. Rows of the previous model are kept until activation, so a switch that fails leaves the serving model intact. A row whose `content_hash` and `model_key` already match is skipped, which lets a resumed switch continue where it stopped. A vector whose length differs from the profile's is rejected when stored, not only when probed.
3. **Indexes.** For each searchable embeddable model, recreate the index with the target dimensions and import it; recreate the RAG indexes (`ai:create-rag-index`, `ai:index-rag-docs --full`) with the same dimensions, derived from the profile instead of `faq.elasticsearch.embedding_dims`. When the dimensions are equal the mapping is left alone and the documents are reimported. On PostgreSQL with pgvector the partial index of the target profile is created here (section 5).
4. **Verify.** For every index: the document count equals the number of searchable records; every record has a row stamped with the target key; the mapping's dimensions equal the target's; a smoke vector query succeeds. *As built (correction):* the completion criterion is not "`ai:embeddings:repair --stale` finds none". Rows of the previous model are kept until activation, so `--stale` now selects records that have embeddings but no row of the active key, and the switch checks that every embeddable record has a row of the target key with the hash of its current text for each of its locales. The phase starts with a refresh pass that rewrites every document with the target's vectors, so records edited after the indexes phase do not carry the serving model's vectors; a record edited between that pass and the activation (seconds) keeps them until its next save.
5. **Activate**, in one step: write `active`, `search.vector.dimensions`, `search.vector.similarity` and `search.vector.model`, clear the state and `suspended_reason`, invalidate the settings cache and the guard's cache, then delete the rows of the previous model (and, on PostgreSQL, its index). This is the first moment the new model serves anything.
6. **Cleanup of the previous model is part of activation** (step 5). Going back to it is a new switch and re-embeds everything, which for a corpus of a few hundred records takes minutes. `ai:embeddings:prune --model-key=<key>` remains as a manual command for rows nobody uses (a model removed from config, an abandoned switch) and refuses the active key.

Records created or edited during phases 2 to 4 are embedded with the target, because the old index is being rebuilt and is not serving vectors anyway. A rollback after such a window re-embeds those records.

**Failure.** A failed phase records phase and error in the state, leaves `active` unchanged and vector search off (the indexes may be half rebuilt). `--resume` continues from the failed phase. `--abandon` returns to the previous model: it runs the same procedure with the previous profile as target.

*As built:* `--resume` from a failed `verify` restarts at `indexes` (the indexes are emptied and rebuilt, so whatever the check found is gone; it costs a full index rewrite and RAG rebuild per resume). `--resume` and `--abandon` also act on a `running` switch whose state has not been written for 30 minutes (interrupted). `--abandon` on a start refused in its preflight only resets the state to idle, sets `features.embeddings.model` back to the active model and lifts the suspension, re-embedding nothing; the return switch of a later abandon skips the command's start checks. `SwitchEmbeddingModelJob` runs with a 900-second timeout (`failOnTimeout`), 3 tries with backoff 10 and 30 s, a `WithoutOverlapping` lock, and re-dispatches itself every 5 seconds; a check that does not hold fails the phase at once, any other error is retried and recorded when the tries are spent. In the indexes phase an index whose vectors already have the target's dimensions is emptied before its documents are written again (documents of records no longer searchable must not survive), not only reimported. The RAG indexes are rebuilt only when FAQ is on and its vector store is `elasticsearch`. Activation deletes the rows of every model key other than the target, and rows with no key; a failed pgvector index drop is logged and does not fail the activation.

`ai:embeddings:status` prints the state and counts, so the same information is available without the admin panel.

### 4.5 The guard (Core)

`IVectorSearchAvailability` in Core answers whether a query may use vectors, with a reason when it may not. `AdvancedSearchService::resolveVector()` asks it first; when the answer is no it returns `null` (keyword-only retrieval, which that code already handles) and the result's `meta` carries `vector_disabled: <reason>`. Reasons:

- `suspended`: a switch is in progress or failed;
- `dimension_mismatch`: the index mapping's dimensions differ from `search.vector.dimensions`, read from the mapping and cached for a short time so a search does not pay a call to the engine;
- `disabled`: `search.vector.enabled` is off, as today.

The AI module contributes a second check: no row of the active profile's `model_key` exists for the corpus (`no_vectors`).

*As built:* the AI check is `EmbeddingVectorSearchAvailability`, a decorator bound over Core's `VectorSearchAvailability`; it also answers `no_vectors` when no active profile resolves, and therefore whenever the embeddings feature is off or nothing is embedded yet. The guard is asked only when the plan wants vectors and `ITextEmbedder` is bound. The dimension check is cached 60 seconds per model class; `VectorSearchAvailability::forget()` drops it and activation calls it. A further Core component was added (plan Task 12): `VectorModelContext`, so that an index document carries only the embedding rows of `search.vector.model`, or of the key given to `VectorModelContext::using()` (the switch writes documents with the target's vectors that way).

### 4.6 The dimensions of known models

The operator's question was how to know a model's vector length. The answer in this design is the probe (4.1): measure, then declare. The two profiles that exist are 384. Nothing in the Python service changes: it already returns the vectors, and its `/models` and `/health` already name the model.

## 5. Data and migrations

No change to the shape of `core_model_embeddings`: `model_key` exists and rows coexist; it now holds the `provider:model` string, and rows stamped with the old alias keys are not migrated (they are stale, and `ai:embeddings:repair --all --stale` or `migrate:fresh` re-creates them; nothing in production depends on them). The three new settings and the two command-managed variants are seeded by the AI and Core seeders. The environment variables of 4.2 are removed.

**PostgreSQL with pgvector.** The column is `vector(N)` and N was fixed when the migration ran, which would forbid two models of different dimensions in one table. The create migration declares the column without a dimension, as the plain `vector` type, which pgvector documents as able to hold vectors of different lengths. After it, PostgreSQL behaves like MySQL: rows of two models coexist, stamped by `model_key`, and the procedure of 4.4 is the same on every database.

What changes with it:

- **The index becomes one per profile.** pgvector indexes only vectors of one length, so the index is an expression and partial index, as its documentation prescribes: `USING hnsw ((embedding::vector(N)) vector_cosine_ops) WHERE model_key = '<profile>'`. The index of the target profile is created in phase 3 of the switch, and the one of a model that is pruned is dropped with its rows.
- **The query of `DatabaseEngine`** (`embedding <=> ?::vector` today) casts and filters the same way, `embedding::vector(N) <=> ?::vector` with `model_key = '<core.search.vector.model>'`, with N from `core.search.vector.dimensions` and the key from `core.search.vector.model`; otherwise PostgreSQL would not use the index, and rows of another model would be compared with a vector of a different length.
- **The change is made in the create migration** of `core_model_embeddings` (the project is pre-stable, so `migrate:fresh`), not as a migration of existing installations.

*As built:* the index is named `me_embedding_` plus the first 12 hex characters of the SHA-1 of the key, so any key gives a valid identifier; the statement is `CREATE INDEX IF NOT EXISTS ... USING hnsw (("embedding"::vector(N)) <ops>) WHERE ("model_key" = '<key>')` with `vector_cosine_ops`, `vector_l2_ops` or `vector_ip_ops` for the similarity `cosine`, `l2` or `ip` (anything else throws). `PgvectorProfileIndex` (bound as Core's `IProfileVectorIndex`) reads `pg_indexes` before issuing it, and the AI synchronizer ensures the active profile's index once per process before writing rows. `DatabaseEngine` filters by the key as a literal, not a binding, so a generic plan still matches the partial predicate; with no `search.vector.model` it filters `vector_dims("embedding") = N` instead. The migration creates no vector index. The `similarity` value is copied from the profile to Core as is, and the two engines accept different vocabularies: pgvector `cosine`, `l2`, `ip`; Elasticsearch `cosine`, `l2_norm`, `dot_product`, `max_inner_product`. Only `cosine` is valid on both, and Core's seeded choices for `search.vector.similarity` (`cosine`, `dot_product`, `euclidean`) match neither list fully; the setting is managed, so the choices are not offered to anyone.

The other databases keep their JSON column; nothing changes for them.

## 6. Error handling

- The target model is unreachable, or reports another model, or measures other dimensions: the switch does not start; nothing changed.
- A job fails on a record: the failed jobs are retried by the existing job budget; a phase that cannot complete fails the switch (4.4).
- The settings write at activation fails: the state stays `running` in phase `activate` (there is no `activating` status), the job retries, and `--resume` repeats the step, which is idempotent.
- Two switches at once: refused by the lock.

## 7. Testing

- **Unit:** the probe, the profile's dimension rule, the state transitions, the guard's reasons, the stored-vector length check.
- **Feature:** the Settings confirmation (shown for a changed profile, not shown for the active one, the numbers it displays, cancel changes nothing), following the Filament patterns already used for setting actions; the procedure end to end with a fake provider and the database engine; `--resume` and `--abandon`; the guard disabling `resolveVector()`.
- **Elasticsearch-gated integration:** mapping dimensions read back, mismatch detected, an index recreated with new dimensions. Not part of the deterministic CI gate, like the other engine-dependent tests.
- **PostgreSQL-gated:** the migration removing the dimension, the creation and removal of the partial index, and the `DatabaseEngine` query with cast and filter. They run only where PostgreSQL with pgvector is available and are skipped elsewhere, as the engine-dependent tests are.
- **Regression:** a search with `suspended_reason` set returns keyword results and `meta.vector_disabled`, with no engine error.

## 8. Documentation

Module docs of AI (`docs/rag/MODULE.md`, the installation guide for the service) describe the probe, the procedure and the settings; Core's retrieval pipeline doc describes the guard and its reasons; the AI README's environment table, the two AI guides and the Core README stop naming the two variables. Closing the plan will add a `Documented in` line, as the repository requires.

*As built:* `Modules/AI/docs/rag/MODULE.md` (section *Embedding model and model switch*), `Modules/AI/README.md`, `Modules/AI/docs/SEARCH_AND_TRANSLATION.md`, `Modules/AI/docs/SENTENCE_TRANSFORMERS_INSTALLATION.md`; `Modules/Core/docs/rag/SEARCH_RETRIEVAL_PIPELINE.md` (sections *Vector availability guard* and *PostgreSQL with pgvector*), `Modules/Core/docs/rag/SETTING_ACTIONS_DEVELOPER.md` and `SETTING_ACTIONS_USER.md` (managed settings, change confirmations), `Modules/Core/docs/rag/SEARCH_MATCHING_DEVELOPER.md`, `Modules/Core/README.md`; `.env.example` no longer names `AI_FAQ_ES_EMBEDDING_DIMS`.

## 9. Out of scope, and the path beyond

- **A zero-gap switch.** The cheapest way found is a vector field per profile in the same index (`embeddings.vector_<profile>`): Elasticsearch accepts a new field in an existing mapping, rows of two models already coexist, partial updates fill the new field, new records write both, and the switch is the setting `active` flipping, with an equally instant rollback. It needs the Core to choose the field from the active profile. An index with an alias is the alternative, and needs alias management the Core does not have. Neither is built now; the rows and the state of this design are what both would reuse.
- Several models serving at once, a model per feature, and a remote catalogue of embedding models.

## 10. Open points

- **PostgreSQL is not verified.** Three things come from the pgvector documentation or from reasoning and have not been run: that removing the dimension from an existing `vector(N)` column with `ALTER` is accepted without rewriting the data, the exact index syntax on the pgvector version in use, and that the planner uses the partial index for the cast query. They are to be confirmed on a real PostgreSQL before the part is called done. *As built:* no `ALTER` exists (the create migration was changed), so the first point is moot; nothing of the PostgreSQL part has run on a real server, the gated test included: the dimensionless column, the index syntax and its build on rows of mixed lengths, the planner using the index with the literal filter, and the `vector_dims` fallback.
- **The index build blocks writes.** `CREATE INDEX` is not `CONCURRENTLY`: while it builds, writes to `core_model_embeddings` wait. Accepted because vector search is suspended during a switch; the synchronizer's first-write `ensureOnce` can also trigger a build outside a switch when the active profile's index is missing.
- **The existence check ignores validity.** `PgvectorProfileIndex` checks `pg_indexes` by name and does not read `pg_index.indisvalid`, so an invalid index left by a failed build counts as present and is never rebuilt automatically.
- **The Elasticsearch branches are not verified** against a running Elasticsearch: the document count in verify (refresh then `count`), the RAG rebuild with `ai:create-rag-index --force`, the forced `createIndex` when the dimensions differ, the bulk `update` of documents and the `flush` of an index whose dimensions are equal.
- **Search during the indexes phase.** An index is recreated or emptied before its documents are written again, so keyword search on that model returns fewer results, or none, for the length of the rewrite.
- **RAG during a switch.** The documentation indexes are rebuilt for the target while questions are still embedded with the serving model, so RAG retrieval is inconsistent until activation. A `filesystem` or `memory` documentation store is not rebuilt by the switch at all.
- **Queue worker.** `SwitchEmbeddingModelJob` and the command queued by the confirmation go to the connection's default queue. Its worker (the Horizon supervisor) needs a timeout of at least 900 seconds and the connection a `retry_after` above it; the shipped Horizon supervisors watch only `embeddings` and `indexing`.
- **The similarity vocabularies differ** between pgvector and Elasticsearch (section 5); a profile whose similarity the engine in use does not accept fails at index creation or query time.

- The estimate shown in the alert is a rough one; it can be refined with a real run once the procedure exists.
- Whether a hosted provider's dimension parameter (see 4.1) is read from the profile or from the provider's own setting; to settle when the first hosted profile is added.

## 11. As built: divergences (2026-10-05)

Recorded while Tasks 1 to 10 and 12 of the plan were built; each is also stated where the section it amends says otherwise.

- Profile keys are `provider:service_model`, split at the first colon, and the config blocks no longer carry `provider` or `service_model` (4.1, decision 8). `search.vector.model` is a Core managed setting holding that key (4.2).
- The PostgreSQL change is in the create migration of `core_model_embeddings`; there is no migration of existing installations (5).
- The confirmation hook is `Modules\Core\Contracts\ISettingChangeConfirmation`, registered through `SettingChangeConfirmations`; its DTO is `Modules/Core/app/Data/SettingChangeWarning.php` (4.3).
- No settings banner: the locked field's helper text carries the state (4.3).
- `ai:embeddings:switch` gained `--report-failure`, used by the confirmation (4.3).
- The embeddings phase completes when every embeddable record has a target row with its current content hash for each locale, not when `--stale` finds none; `--stale` was redefined as "embeddings, but no row of the active key" (4.4).
- A refresh pass opens the verify phase; a failed verify resumes from the indexes phase (4.4).
- Equal-dimension indexes are emptied before reimport (4.4).
- Activation writes `features.embeddings.model` with a direct update (4.2).
- `VectorModelContext` (plan Task 12) makes index documents carry one model's vectors (4.5).
- Task 13 of the plan is on hold, awaiting a decision; this spec describes the settings as built, with `features.embeddings.active` as in 4.2.
