# Deferred search indexing during bulk imports

**Status:** implemented (2026-09-28), see `docs/superpowers/plans/2026-09-28-deferred-import-search-indexing.md`

**Date:** 2026-09-28

**Modules:** Core (Search, Import framework) owns the mechanism; AI adapts the single-locale re-embedding listener; CMS fixes the post-import reindex guard.

## Problem

`{module}:import` writes one record graph at a time, and every write reaches search through the real-time path built for a single interactive save. For one CMS `Content` record:

- `ContentUpserter::upsert` saves the model twice (before and after the translations), so the Scout observer calls `searchable()` twice after the graph transaction commits.
- Each `searchable()` goes through `Searchable::queueMakeSearchable` with a one-model collection: an index-exists HTTP call, a `Cache::put` of the whole `ModelRequiresIndexing` event (model graph serialized into Redis), and one queued job (`IndexInSearchJob`, or `GenerateEmbeddingsJob` followed by `IndexInSearchJob`) that writes one document.
- With vector search enabled, `ContentTranslationObserver` also fires `TranslationRequiresReembedding` per changed locale, and each becomes another `GenerateEmbeddingsJob` plus another index job. The `embeddings` queue is rate limited (`EMBEDDINGS_QUEUE_RATE_PER_MINUTE`, 30/min here), so a few thousand records take hours.

The bulk path delivered by `2026-09-19-adaptive-driver-agnostic-bulk-indexing-design.md` (one batched embedding pass per chunk through `ModelsRequireIndexing`, adaptive `engine->update()` writes through `AdaptiveBatchController`) exists but only runs when `searchable()` receives more than one model, which in practice means `scout:import`. An import never reaches it.

`--no-search` does not help either: it only switches the importing process to Scout's `NullEngine`. The events, cache writes and queued jobs still happen, workers index with the real driver, and embeddings are still generated. `ImportPostProcessor` checks `config('scout.driver') !== null`, which the string `'null'` always passes, so `CMS_IMPORT_REINDEX` still runs a full reindex.

## Decision summary

- **Defer, record keys, flush in bulk.** For the duration of an import command, `searchable()` calls are captured by a Core `DeferredSearchIndexing` service instead of being indexed. It records only `(model class, scout key)`, deduplicated, so the two saves and N locale re-embeddings of one record collapse into one entry.
- **Flush every `batchSize` distinct models and once at the end.** A flush reloads the recorded models by key through Scout's own import query (`makeAllSearchableQuery()`), drops rows that no longer exist or are no longer searchable, and hands them to the existing bulk path: one `ModelsRequireIndexing` pre-process pass (batched embeddings for every locale), then adaptive engine writes.
- **Never flush inside an open transaction.** The threshold flush is scheduled with the connection's `afterCommit()`: it runs at once outside a transaction, after the commit inside one, and not at all on rollback (the keys stay pending for the next flush).
- **Flush inline, in the import process.** No queued bulk job: the importer gets natural backpressure, nothing is serialized into Redis, and the `embeddings` / `indexing` rate limiters, which exist for the real-time path, do not apply.
- **The single-locale re-embed joins the deferral.** `HandleTranslationReembeddingListener` records the model instead of dispatching `GenerateEmbeddingsJob`; the flush embeds all stale locales at once, with the synchronizer's hash freshness check.
- **`--no-search` (and dry-run) means discard.** The same service runs in discard mode: captured calls are dropped, nothing is embedded or indexed. Removals (`unsearchable()`) are untouched in both modes.
- **Failure still flushes.** When the importer throws, the keys recorded for graphs already committed are flushed before the exception propagates; a flush error in that situation is reported, never allowed to replace the import error.

## Interface

`Modules\Core\Search\DeferredSearchIndexing`, a container singleton:

- `run(callable $callback, int $batchSize, bool $discard = false): mixed` runs the callback with deferral active and flushes what is left. A nested `run()` joins the outer one.
- `defer(iterable $models): bool` takes over a `searchable()` call; returns `false` when nothing is deferred, so the caller indexes as usual.
- `isDeferring(): bool`, `flush(): void`.

`ISearchableModel::makeSearchableInBulk(Collection $models): void`, implemented by the `Searchable` trait: index the given models of one class through the bulk path now, whatever `scout.queue` says.

`AbstractImportCommand` wraps the import in `run()`. New option `--index-batch=` (default `scout.chunk.searchable`, 500). `--no-search` and `--dry-run` select discard mode.

## Effect

- **Memory:** the import process holds keys only; models are loaded per flush chunk and released. Redis no longer receives a serialized model graph per save, nor one or more queued jobs per record.
- **Time:** per record, 2 index-exists calls and 2 to 2+2N queued jobs become, per `batchSize` records, one reload, one batched embedding pass and a few `_bulk` writes.

## Trade-offs and limits

- Imported records become searchable in blocks of `batchSize`, not one by one.
- If the process is killed without a chance to react (`kill -9`, fatal error), up to `batchSize - 1` recorded models are not indexed. `scout:import` and `ai:embeddings:repair` recover them. Ctrl+C and SIGTERM are handled, see below.
- Embedding time moves from the workers into the import process.
- `CMS_IMPORT_REINDEX` becomes redundant for the imported records (they are already indexed); when enabled it still reindexes every `Content`, now through the same deferral.
- The residual N+1 inside `Content::toSearchableArray` (spec 2026-09-19, "Still open") becomes the next cost of a flush. It stays a separate task.

## Interrupts (Ctrl+C, SIGTERM)

Added the same day, after the first slice: PHP's default SIGINT handling kills the process without running `catch` or `finally`, so the pending block was lost on Ctrl+C, a regression against the old per-record path.

- **Ask on Ctrl+C.** When models are waiting to be indexed, the operator chooses: index them and quit (default; a flush already running is finished first), quit at once, or resume. SIGTERM, or a run without a terminal / with `--no-interaction`, indexes and quits without asking. With nothing pending the process quits at once.
- **Unwind by exception, not from the handler.** Flushing inside the signal handler would queue a second signal until the handler returns, so a second Ctrl+C could not quit. Instead `DeferredSearchIndexing::interrupt()` throws `DeferredRunInterruptedException` (at once, or after the running flush), the graph transaction open at that moment rolls back, and `run()` flushes on the way out. The command returns 128 + signal. A second signal quits at once.
- **Record saves inside a transaction at save time.** An exception that lands inside a commit's after-commit callbacks skips the remaining ones, so Scout's observer alone would miss models that commit wrote. An `eloquent.saved: *` listener active during the run records searchable models saved inside a transaction; a rolled-back key is harmless because a flush reloads by key.
- **The exception is not a `RuntimeException`**, so domain code that converts runtime failures into row errors cannot swallow a stop.

## Out of scope

- The interactive file import (`Modules/Core/app/Import/Support/ImportRunner.php`); it can adopt `DeferredSearchIndexing::run()` later.
- Deferring removals.
- Memory held by the importers themselves (`ImportIdMap`, `Cache::memo`, source listings kept in memory by plugin importers).
