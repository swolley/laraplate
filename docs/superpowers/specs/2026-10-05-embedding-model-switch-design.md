# Embedding model switch: design

**Status:** Draft (2026-10-05), awaiting review.
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

## 4. Components

### 4.1 Profile and dimensions (AI)

Each profile in config gains `dimensions` (and keeps `similarity` optional, default cosine). The registry returns them from the profile, no longer from Core. The profiles that exist both declare 384.

`EmbeddingDimensionProbe` embeds a fixed short text through the provider the profile names and returns the length of the vector. It is the one place that measures; `ai:embeddings:repair` uses it instead of its own inline check. A profile whose declared `dimensions` differs from the measured length is rejected: it cannot be selected as a target and cannot be activated.

`ai:embeddings:probe {profile}` prints the measured length. This is how a new profile is added: run it, put the number in config. The service needs no change, since measuring works for any provider. For a hosted provider that lets the caller choose the dimension (the OpenAI embedding models do, as far as documented; not verified here), the profile declares the dimension it asks for and the probe verifies it.

The profile's `provider` field becomes the provider used for that profile, replacing `AI_EMBEDDINGS_PROVIDER`; a profile that names an unconfigured provider is not offered.

### 4.2 Settings (Core and AI)

New command-managed settings, group `ai`, in the sense of the 2026-09-29 spec (written by a command, not by hand):

| Setting | Meaning | Written by |
|---|---|---|
| `features.embeddings.model` | the profile the operator chose (the **target**) | the dropdown, through the confirmation of 4.3 |
| `features.embeddings.active` | the profile whose vectors serve search | the switch procedure at activation |
| `features.embeddings.switch` | state of a switch: status, phase, target, counts, error, timestamps | the switch procedure |

`search.vector.dimensions` and `search.vector.similarity` become command-managed, written together with `active`. `search.vector.suspended_reason` (Core, string or null) is set when a switch starts and cleared when it ends; the guard reads it.

The dropdown's choices are the profile keys that are configured and probe-able; no remote listing is needed. A fresh installation seeds `active` from the first profile in code, as the 2026-09-29 spec prescribes (default in code, no environment variable). `AI_EMBEDDINGS_MODEL` and `AI_EMBEDDINGS_PROVIDER` are removed. This is a breaking change with a known footprint, all of it to be updated by the plan: the readers of `ai.features.embeddings.active` and `default_provider` (`EmbeddingModelRegistry`, `EmbeddingsProviderFactory`), the tests that set those config keys (embedding, prefix and documentation-retrieval tests), and the documents that name the variables (the AI README, `SEARCH_AND_TRANSLATION.md`, `SENTENCE_TRANSFORMERS_INSTALLATION.md`, and the Core README).

### 4.3 The alert (Filament)

Choosing a different profile in the dropdown opens a confirmation, not a save. It shows:

- current and target model, and current and target dimensions;
- which class of change it is: **dimensions differ** (mapping rebuilt and everything re-embedded) or **same dimensions** (re-embed only);
- how much work: the number of records to embed (embeddable records times their translations) and a rough time, from the probe's latency times the batch count, labelled as an estimate;
- what happens meanwhile: vector search is off and search uses keywords only, until the switch ends;
- that going back is the same procedure and costs the same.

Confirm sets the target and starts `ai:embeddings:switch` queued. Cancel leaves everything as it was. Choosing the active profile does nothing. While a switch is running or failed, the Settings page shows a persistent banner with the phase and counts, and the dropdown is disabled.

### 4.4 The switch procedure (AI)

`ai:embeddings:switch {profile} [--resume|--abandon]`, run by an orchestrating job that advances one phase at a time and re-dispatches itself, so a worker restart loses nothing.

1. **Preflight.** Take a lock so only one switch runs. Check the service answers `/health`, that the model it reports is the profile's (the check `ai:embeddings:repair` already does), and that the probe measures the declared dimensions. Set `search.vector.suspended_reason` and the state. Nothing has changed for a user except that vector search is off.
2. **Embeddings.** Re-embed every embeddable record with the **target** profile, stamping the target `model_key`. `GenerateEmbeddingsJob` gains an explicit profile argument; it uses the active one when none is given. Rows of the old model are kept. A row whose `content_hash` and `model_key` already match is skipped, which makes a return to a recent model cheap. A vector whose length differs from the profile's is rejected when stored, not only when probed.
3. **Indexes.** For each searchable embeddable model, recreate the index with the target dimensions and import it; recreate the RAG indexes (`ai:create-rag-index`, `ai:index-rag-docs --full`) with the same dimensions, derived from the profile instead of `faq.elasticsearch.embedding_dims`. When the dimensions are equal the mapping is left alone and the documents are reimported. On PostgreSQL with pgvector the partial index of the target profile is created here (section 5).
4. **Verify.** For every index: the document count equals the number of searchable records; every record has a row stamped with the target key (`ai:embeddings:repair --stale` finds none); the mapping's dimensions equal the target's; a smoke vector query succeeds.
5. **Activate**, in one step: write `active`, `search.vector.dimensions`, `search.vector.similarity`, clear the state and `suspended_reason`, invalidate the settings cache and the guard's cache. This is the first moment the new model serves anything.
6. **Cleanup** is not automatic. `ai:embeddings:prune --model-key=<key>` removes the rows of a model no longer wanted, and on PostgreSQL the index of that model, after the operator has decided.

Records created or edited during phases 2 to 4 are embedded with the target, because the old index is being rebuilt and is not serving vectors anyway. A rollback after such a window re-embeds those records.

**Failure.** A failed phase records phase and error in the state, leaves `active` unchanged and vector search off (the indexes may be half rebuilt). `--resume` continues from the failed phase. `--abandon` returns to the previous model: it runs the same procedure with the previous profile as target.

`ai:embeddings:status` prints the state and counts, so the same information is available without the admin panel.

### 4.5 The guard (Core)

`IVectorSearchAvailability` in Core answers whether a query may use vectors, with a reason when it may not. `AdvancedSearchService::resolveVector()` asks it first; when the answer is no it returns `null` (keyword-only retrieval, which that code already handles) and the result's `meta` carries `vector_disabled: <reason>`. Reasons:

- `suspended`: a switch is in progress or failed;
- `dimension_mismatch`: the index mapping's dimensions differ from `search.vector.dimensions`, read from the mapping and cached for a short time so a search does not pay a call to the engine;
- `disabled`: `search.vector.enabled` is off, as today.

The AI module contributes a second check: no row of the active profile's `model_key` exists for the corpus (`no_vectors`).

### 4.6 The dimensions of known models

The operator's question was how to know a model's vector length. The answer in this design is the probe (4.1): measure, then declare. The two profiles that exist are 384. Nothing in the Python service changes: it already returns the vectors, and its `/models` and `/health` already name the model.

## 5. Data and migrations

No change to `core_model_embeddings`: `model_key` exists and rows coexist. The three new settings and the two command-managed variants are seeded by the AI and Core seeders. The environment variables of 4.2 are removed.

**PostgreSQL with pgvector.** The column is `vector(N)` and N was fixed when the migration ran, which would forbid two models of different dimensions in one table. A migration removes the dimension from the column, leaving the plain `vector` type, which pgvector documents as able to hold vectors of different lengths. After it, PostgreSQL behaves like MySQL: rows of two models coexist, stamped by `model_key`, and the procedure of 4.4 is the same on every database.

What changes with it:

- **The index becomes one per profile.** pgvector indexes only vectors of one length, so the index is an expression and partial index, as its documentation prescribes: `USING hnsw ((embedding::vector(N)) vector_cosine_ops) WHERE model_key = '<profile>'`. The index of the target profile is created in phase 3 of the switch, and the one of a model that is pruned is dropped with its rows.
- **The query of `DatabaseEngine`** (`embedding <=> ?::vector` today) casts and filters the same way, `embedding::vector(N) <=> ?::vector` with `model_key = '<active profile>'`, with N and the key taken from the active profile; otherwise PostgreSQL would not use the index, and rows of another model would be compared with a vector of a different length.
- **An existing installation** is migrated by the same migration: the type loses its dimension and its current index is replaced by the partial one for the active profile.

The other databases keep their JSON column; nothing changes for them.

## 6. Error handling

- The target model is unreachable, or reports another model, or measures other dimensions: the switch does not start; nothing changed.
- A job fails on a record: the failed jobs are retried by the existing job budget; a phase that cannot complete fails the switch (4.4).
- The settings write at activation fails: the state stays `activating`, `--resume` repeats the step, which is idempotent.
- Two switches at once: refused by the lock.

## 7. Testing

- **Unit:** the probe, the profile's dimension rule, the state transitions, the guard's reasons, the stored-vector length check.
- **Feature:** the Settings confirmation (shown for a changed profile, not shown for the active one, the numbers it displays, cancel changes nothing), following the Filament patterns already used for setting actions; the procedure end to end with a fake provider and the database engine; `--resume` and `--abandon`; the guard disabling `resolveVector()`.
- **Elasticsearch-gated integration:** mapping dimensions read back, mismatch detected, an index recreated with new dimensions. Not part of the deterministic CI gate, like the other engine-dependent tests.
- **PostgreSQL-gated:** the migration removing the dimension, the creation and removal of the partial index, and the `DatabaseEngine` query with cast and filter. They run only where PostgreSQL with pgvector is available and are skipped elsewhere, as the engine-dependent tests are.
- **Regression:** a search with `suspended_reason` set returns keyword results and `meta.vector_disabled`, with no engine error.

## 8. Documentation

Module docs of AI (`docs/rag/MODULE.md`, the installation guide for the service) describe the probe, the procedure and the settings; Core's retrieval pipeline doc describes the guard and its reasons; the AI README's environment table, the two AI guides and the Core README stop naming the two variables. Closing the plan will add a `Documented in` line, as the repository requires.

## 9. Out of scope, and the path beyond

- **A zero-gap switch.** The cheapest way found is a vector field per profile in the same index (`embeddings.vector_<profile>`): Elasticsearch accepts a new field in an existing mapping, rows of two models already coexist, partial updates fill the new field, new records write both, and the switch is the setting `active` flipping, with an equally instant rollback. It needs the Core to choose the field from the active profile. An index with an alias is the alternative, and needs alias management the Core does not have. Neither is built now; the rows and the state of this design are what both would reuse.
- Several models serving at once, a model per feature, and a remote catalogue of embedding models.

## 10. Open points

- **PostgreSQL is not verified.** Three things come from the pgvector documentation or from reasoning and have not been run: that removing the dimension from an existing `vector(N)` column with `ALTER` is accepted without rewriting the data, the exact index syntax on the pgvector version in use, and that the planner uses the partial index for the cast query. They are to be confirmed on a real PostgreSQL before the part is called done.

- Whether `ai:embeddings:prune` should become automatic after a retention period.
- The estimate shown in the alert is a rough one; it can be refined with a real run once the procedure exists.
- Whether a hosted provider's dimension parameter (see 4.1) is read from the profile or from the provider's own setting; to settle when the first hosted profile is added.
