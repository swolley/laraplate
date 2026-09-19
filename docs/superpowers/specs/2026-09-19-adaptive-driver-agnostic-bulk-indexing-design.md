# Adaptive driver-agnostic bulk indexing

**Status:** proposed (draft)

**Date:** 2026-09-19

**Modules:** Core (Search) owns the driver-agnostic controller; AI is touched only for the separate embedding-service batch layer.

## Problem

Scout's engine `update(Collection)` is already the driver-agnostic batch seam: none of the three engines (`ElasticsearchEngine`, `DatabaseEngine`, `TypesenseEngine`) override it, so each inherits the vendor driver's native bulk — Elasticsearch `_bulk`, database multi-row upsert, Typesense import. But the indexing pipeline feeds it **one model per `IndexInSearchJob`** (`update($model->newCollection([$model]))`), so every write is single-document: one round trip plus the full event/queue overhead per record. A bulk reindex of N records is N single writes, and it also defeats embedding batching (each `GenerateEmbeddingsJob` embeds 1-2 texts, never filling the provider's batch).

There is also no backpressure. A weak or temporary search server (or a fragile embedding service) has no adaptive throttling — an uncontrolled burst saturates it (observed: the embedding service restarted under the initial 411-record flood) and failures only retry. We want indexing to behave well on **any** server, weak or strong, without per-server manual tuning.

## Decision summary

- **Keep the real-time per-model path unchanged.** A single content save stays single-document and low-latency; batching there has no value.
- **Add a driver-agnostic bulk path** for mass operations (reindex/import): stream models -> chunk -> `engine->update(chunk)` -> observe -> adapt.
- **An adaptive controller sits above Scout's `update(Collection)`** and uses only driver-agnostic signals — **latency and thrown exceptions** — to size each batch by **AIMD** (multiplicative decrease on strain, additive increase on success), with backoff and optional pacing. It never knows which driver is underneath, so it works identically for Elasticsearch, Typesense and the database engine.
- **Per-driver refinements are optional and isolated** behind the engine interface; the controller degrades gracefully when a driver does not implement them.
- **Embedding-generation batching/adaptivity is a separate, AI-only layer** (vector is Elasticsearch-specific here) and is not part of the agnostic indexing controller.

## The agnostic controller

`AdaptiveBatchController` (Core Search), depending only on Scout's engine contract:

- Inputs: an iterable of searchable models, the target engine, and config (`min_batch`, `max_batch`, `target_latency_ms`, `max_batch_bytes`, backoff and ramp parameters).
- Loop, starting from `min_batch`:
  1. Take up to `batch` models, capped also by an estimated serialized size (sum of each model's `toSearchableArray()` JSON) against `max_batch_bytes`.
  2. Call `engine->update($collection)`, measuring elapsed time and catching exceptions.
  3. **Success and latency <= target** -> additive increase (`batch += step`, up to `max_batch`).
  4. **Failure, or latency > target** -> multiplicative decrease (`batch = max(min_batch, batch / 2)`), sleep a backoff, and retry the batch.
- Terminates when the stream is exhausted; returns per-run stats (batches, final batch size, retries, degradations).

Because the only signals are latency and exceptions, a weak server converges to small batches and a strong one to large — no manual tuning, no driver knowledge.

## Optional per-driver hooks (default no-op)

Added to the engine interface so the controller can refine without losing agnosticism:

- `isOverloadError(Throwable): bool` — lets a driver distinguish genuine overload (Elasticsearch `429` / `es_rejected_execution_exception`, Typesense overload, database deadlock/timeout) from a hard error, so the controller reduces-and-retries instead of failing. Default: treat any exception as strain.
- `maxRecommendedBatchBytes(): ?int` — a driver hint (Elasticsearch favours ~5-15MB bulks; the database cares about row count, not bytes). Default: null, use the configured cap.
- Per-item bulk retry (Elasticsearch): the bulk response carries per-item status, so only rejected items are retried rather than the whole batch. This needs the Elasticsearch engine (or `ElasticsearchService::bulkIndex`) to surface per-item results instead of throwing wholesale — an Elasticsearch-only refinement.

## Bulk entry point

A reindex flow drives the controller directly instead of dispatching a per-model event per record (which is what `scout:import` does today through `ModelRequiresIndexing`). It runs as a single controlled stream (or a small bounded worker count) so a weak server is never hit by many parallel bulks. The per-minute rate limiters (`embeddings`, `indexing`) stay for the real-time path; on the bulk path the controller self-throttles adaptively and the static limiter is largely redundant.

## Embedding layer (separate, AI-only)

When embeddings are enabled, the bulk reindex needs the vectors too. A parallel adaptive batcher targets the **embedding service** (batch by count and bytes, AIMD on timeout / 5xx / empty-reply), reusing the same AIMD idea but with the embedding service as the target rather than a Scout engine. It is orthogonal to the search driver and only relevant where vector search is used (Elasticsearch here). This is where the fragility actually showed up, so it is the higher-value first slice.

## Scope boundaries

In scope: the agnostic `AdaptiveBatchController`, a bulk reindex entry point that uses it, its config, the optional engine hooks, and — separately — the embedding-service adaptive batcher.

Out of scope: changing the real-time single-save path; reimplementing per-driver bulk mechanics (already in the vendor engines); a micro-batching buffer that batches real-time saves too (the "radical" single-path alternative, deferred); meaningful adaptivity for the database engine (its strain is negligible — it is our own database — so latency-AIMD is kept but low-value).

## Notes

- The controller must be deterministic-testable: inject a fake engine and a clock, feed scripted latencies/failures, and assert the AIMD trajectory (decrease on strain, ramp on success, floor at `min_batch`, cap at `max_batch`).
- First slice: the embedding-service adaptive batcher (the server that actually saturated). Then the agnostic ES/Scout bulk path. Then extract the shared controller if the two converge.

## Related

- `Modules/Core/app/Search/Jobs/IndexInSearchJob.php` — the single-document write point.
- `Modules/Core/app/Search/Engines/{Elasticsearch,Database,Typesense}Engine.php` — inherit the vendor `update(Collection)`; the batch seam.
- `Modules/Core/app/Services/ElasticsearchService.php` — unused `bulkIndex`; home of the Elasticsearch byte-cap / per-item refinement.
- `Modules/AI/app/Ai/Embeddings/SentenceTransformersEmbeddingsProvider.php` — fixed batch; target of the embedding adaptive layer.
- `Modules/Core/app/Providers/RouteServiceProvider.php` — `embeddings` / `indexing` rate limiters, relevant to the real-time path.
