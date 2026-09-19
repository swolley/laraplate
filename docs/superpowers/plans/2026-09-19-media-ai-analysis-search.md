# Media AI Analysis & Search — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `media` records (image/audio/video/document) first-class in search. Core extracts embedded metadata deterministically on claim and indexes the media even with AI off; when AI is on, an async job adds caption/OCR/transcription/idea/intent, the media is embedded through the existing per-model path, and its owner (article/ticket) is reindexed with a compact surrogate. Phase 1 = "find the right file" (no timestamped chunks, no video visual track — that is Phase 2, out of scope here).

**Architecture:** `Media` becomes `Searchable`/`IEmbeddableModel`. Core display metadata (description/alt_text/keywords) rides Spatie `custom_properties` (no new `media` columns). AI-derived data lives in a new **AI-owned** table `ai_media_analysis` keyed by file `content_hash` (dedup across duplicated files). AI contributes to the media search document through a new **generic Core "searchable contributor" seam** (not CMS's content registry). Indexing/analysis fire on **claim** (real owner via `morphs('model')`), never on drafts; enrichment cascades to that single owner. Media search results share the unified result set and inherit the owner's ACL, re-authorized at rehydration through a Core registry keyed by owner morph type, switchable via a Core setting. Analysis models are a config-driven registry per capability (vision, transcription) reusing neuron-ai's `ProviderFactory`; a runtime Settings master switch gates the whole LLM subsystem.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, `spatie/laravel-medialibrary`, Scout + Core search engines (Elasticsearch/Typesense/Database), neuron-ai (`neuron-core/neuron-ai ^3.2`), Pest 4, PHPStan/Larastan, Pint. New deps (approved): `james-heinrich/getid3`, `smalot/pdfparser`. Native `exif`/`iptcparse` for images. Self-hosted Whisper for transcription.

**Spec:** `docs/superpowers/specs/2026-09-18-media-ai-analysis-search-design.md`. Decisions are numbered M1–M21 there; tasks reference them.

**Coordination:** The media search-document contributor seam (M4a) is a **generic Core** seam; the CMS content extension seam (`docs/superpowers/plans/2026-09-19-cms-content-extension-seam.md`) should build its search-contribution on it rather than a CMS-local mechanism. Align if both are in flight.

**Out of scope (Phase 2 / future):** timestamped `media_chunk` store, keyframe vision / video visual track, true shared-asset DAM.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Classes `final` unless a sibling proves otherwise. `#[Override]` when overriding. Explicit param/return types. Constructor property promotion.
- Ownership boundary (M3): **Core never references the AI module or `ai_media_analysis`.** AI depends on Core, not the reverse. The generic contributor seam and the ACL owner-authorizer registry live in Core and are AI-agnostic; AI registers into them.
- Core work in `Modules/Core` (+ its `tests/`); AI work in `Modules/AI` (+ its `tests/`). Migrations use `MigrateUtils`. Follow existing `Modules/Core/app/Search/`, `Modules/AI/app/Listeners/`, and `Modules/AI/app/Jobs/` patterns.
- New composer deps only the two approved above; nothing else without approval.
- Config via `config()`, not `env()`, outside config files. Feature/model config mirrors `ai.features.embeddings` shape.
- Run only affected tests: `php artisan test --compact --filter=<Name>` from `laraplate/`. Do **not** run the full suite.
- Before finishing each task: `vendor/bin/pint --dirty --format agent`, then `vendor/bin/phpstan analyse --memory-limit=2G` on touched paths.
- No destructive index commands against a real cluster; search tests use the Database/array engine or a disposable test index.
- Update the affected module RAG docs (`Modules/{Core,AI}/docs/`, `docs/rag/`) when behavior is worth documenting (AGENTS.md).

## File Structure

Paths relative to their module.

| File | Responsibility |
|------|----------------|
| `Modules/AI/config/config.php` (edit) | `features.media_analysis` block: master switch, per-module gate, model registry (`vision`/`transcription` capabilities, `active` + `models`) |
| `Modules/AI/app/Ai/MediaAnalysis/MediaAnalysisModelRegistry.php` | Twin of `EmbeddingModelRegistry`; resolves the active model per capability (M21) |
| `Modules/Core/app/Helpers/HasMedia.php` or `MediaObserver` (edit/new) | Compute `content_hash` + write deterministic metadata to `custom_properties` on media create (M2, M15) |
| `Modules/Core/app/Media/MetadataExtractor.php` | Deterministic embedded-metadata extraction (exif/iptcparse/getid3/pdfparser) → `custom_properties` (M2, M3a) |
| `Modules/Core/app/Search/Contracts/ISearchableContributor.php` | Generic contributor contract (fields + mapping section) (M4a) |
| `Modules/Core/app/Search/SearchableContributorRegistry.php` | Core registry of contributors keyed by target model type (M4a) |
| `Modules/Core/app/Search/Contracts/IOwnerAuthorizer.php` + `OwnerAuthorizerRegistry.php` | ACL re-auth by owner morph type (M16) |
| `Modules/Core/app/Models/Media.php` (edit) | `Searchable` + `IEmbeddableModel`; `prepareDataToEmbed*`; `toSearchableArray()` facets + contributor hook; claim-gated indexing (M5,M6,M9,M14) |
| `Modules/Core/app/Events/MediaAnalysisRequested.php` | Fired on claim; carries the media (M11,M14) |
| `Modules/AI/app/Models/MediaAnalysis.php` + migration + factory | AI-owned `ai_media_analysis`, keyed by `content_hash` (M3b) |
| `Modules/AI/app/Listeners/HandleMediaAnalysisListener.php` | Registers `media_analysis` pre-processing, dispatches the job, gated by master switch (M11,M12,M18) |
| `Modules/AI/app/Jobs/AnalyzeMediaJob.php` | Per-mime analysis, lookup-before-work by hash, provenance, degrade-on-failure, chains embeddings (M6,M11,M15,M17,M20,M21) |
| `Modules/AI/app/Ai/MediaAnalysis/Transcription/*` | Whisper backend (self-hosted default) + external-API entry |
| `Modules/AI/app/Providers/*` (edit) | Register the media contributor + owner authorizer (for AI-owned surrogate) into the Core registries |
| `Modules/Core/app/Search/Services/*` (edit) | Unified retrieval: include media, re-auth media hits by owner (M16) |
| `Modules/{Core,AI}/docs/*` | RAG docs |

---

## Task 1: Media-analysis config + model registry (M21, M18)

- [ ] Add a `features.media_analysis` block to `Modules/AI/config/config.php` mirroring `features.embeddings`: `enabled` default false (static default under the runtime master switch), a per-module allowlist (as embeddings has), and a `models` map per **capability** (`vision`, `transcription`) each with an `active` key and entries declaring `provider` + `capability` + `service_model`. Defaults: vision `claude-sonnet-5`, transcription self-hosted Whisper.
- [ ] Create `MediaAnalysisModelRegistry` (twin of `EmbeddingModelRegistry`): `active(string $capability)` returns the resolved profile; validates provider/capability. LLM profiles resolve through the existing `ProviderFactory`.
- [ ] Define the runtime **master switch** (M18) as a Core/AI Settings key readable at runtime (follow the project Settings pattern, not only config), gating all LLM media work; document precedence over the per-module gate.
- [ ] Unit tests: registry resolves the active vision/transcription profile; unknown capability throws; master-switch-off short-circuits (asserted in Task 7/8). Pint + PHPStan.

## Task 2: `content_hash` + deterministic metadata on media create (M2, M15, M3a)

- [ ] Create `MetadataExtractor` (Core): given a media file, extract embedded metadata per mime — images via native `exif_read_data`/`iptcparse`, audio/video via `getid3`, PDF via `smalot/pdfparser` (text + document XMP where present) — and return a normalized array (`description`, `keywords`, technical fields) tagged as embedded-sourced.
- [ ] Compute `content_hash` (sha256 of file bytes); for large files defer to the async job, else compute inline.
- [ ] Wire a `Media` observer (or `HasMedia` hook) on create to run the extractor and write results + `content_hash` into `custom_properties` — **no new columns** (M3a). Never overwrite a human-authored value.
- [ ] Feature tests: an image populates `description`/`keywords` from IPTC; an audio file populates technical tags; `content_hash` is stored; a pre-set human value is not overwritten. Pint + PHPStan.

## Task 3: AI-owned `ai_media_analysis` table + model (M3b, M15)

- [ ] Migration (in `Modules/AI`) for `ai_media_analysis` keyed by `content_hash` (unique): `entities`, `idea`, `intent`, `ocr_text`, `transcript`, `analysis` (json, default empty), `provenance` (json), `analysis_status`, `analyzed_at`, `analysis_model_version`. `MigrateUtils` for timestamps/soft-deletes as siblings do. No `sentiment`.
- [ ] `MediaAnalysis` model + factory/states (analyzed, per-status). It is AI-owned; Core never references it.
- [ ] Unit/feature test: a row is uniquely keyed by hash; two media with the same hash resolve the same analysis row. Pint + PHPStan.

## Task 4: Generic Core searchable-contributor seam (M4a)

- [ ] Create `ISearchableContributor` (contribute fields + a mapping section for a given searchable model) and `SearchableContributorRegistry` (Core singleton) keyed by target model type; empty-registry is a safe no-op.
- [ ] Expose the hook so a Core searchable model's `toSearchableArray()` merges registered contributors' sections, and the index mapping composition includes them. Follow the existing Core search schema/mapping composition.
- [ ] Unit tests: register/resolve by model type; empty registry yields the base document unchanged; a contributor's fields appear in the composed document/mapping. Pint + PHPStan.

## Task 5: `Media` becomes Searchable + IEmbeddableModel (M5, M6, M9, M14)

- [ ] Add `Searchable` + implement `IEmbeddableModel` on `Media` (`prepareDataToEmbed`/`prepareDataToEmbedByLocale`, `embeddings()`), reusing the `ModelEmbedding` morph.
- [ ] `prepareDataToEmbed*` composes the Core surrogate text (description/keywords) + AI-contributed text via the Task 4 seam; `toSearchableArray()` emits facets from day one (M9): media id/type, `mime`, `track='media'`, `locale`, `keywords`.
- [ ] Gate indexing on **claim** (M14): a draft-owned media (owner is a `MediaDraft`) is not indexed/embedded; `isEmbeddable()` also honors the master switch/feature.
- [ ] Feature tests: a claimed media indexes with facets and the deterministic surrogate; a draft-owned media does not index; with AI off it still indexes on the deterministic layer (fallback). Pint + PHPStan.

## Task 6: Event + AI listener + Core fallback (M11, M12, M14, M18)

- [ ] Add `MediaAnalysisRequested` (Core), fired on claim. Media save already emits `ModelRequiresIndexing` via `Searchable`; the AI listener attaches to that (as `HandleModelIndexingListener` does) for `Media`.
- [ ] `HandleMediaAnalysisListener` (AI, registered first): if the master switch + feature/module gate allow and the model is a claimed `Media`, `addRequiredPreProcessing('media_analysis')` and dispatch `AnalyzeMediaJob`; else no-op so Core's `IndexModelFallbackListener` indexes the deterministic layer (M12).
- [ ] Feature tests: AI on → `media_analysis` is registered and the job dispatched; AI off / master switch off → not registered, media still finalizes via fallback. Pint + PHPStan.

## Task 7: `AnalyzeMediaJob` (M6, M11, M15, M17, M20, M21)

- [ ] Queue-backed job with throttling/retry like `GenerateEmbeddingsJob`, on a dedicated `media_analysis` queue/limiter.
- [ ] **Lookup-before-work** (M15): resolve `content_hash`; if a fresh `ai_media_analysis` row exists for it at the active `analysis_model_version`, reuse and skip the expensive work.
- [ ] Per-mime dispatch (M20): image → vision caption (subjects+actions, M7) + OCR; audio → transcription; PDF/document → text + OCR for scans; video → audio transcription only. Every type also produces `idea`+`intent`. Vision via the M21 registry (neuron-ai); transcription via the Whisper backend (Task 8).
- [ ] Locale (M17): transcript/OCR in the source language; surrogate generated once then queued for translation to supported locales via the existing translation pipeline.
- [ ] Write AI fields to `ai_media_analysis` (by hash) with provenance; fill an empty Core `custom_properties` field only if unclaimed (M3c). On success emit `ModelPreProcessingCompleted($media,'media_analysis')`; on failure degrade (M12).
- [ ] Chaining (M11): ensure embeddings run **after** analysis persists (job-chain `GenerateEmbeddingsJob`, or defer its dispatch to `media_analysis` completion).
- [ ] Feature tests (fakes for the vision/transcription providers): populates fields without overwriting human values; same-hash second media reuses the analysis (no provider call); failure still finalizes. Pint + PHPStan.

## Task 8: Transcription backend (M21)

- [ ] A `Transcription` contract with a **self-hosted Whisper** implementation (default) and an external-API entry, selected by the M21 registry. Follow the self-hosted `sentence_transformers` embedding-service pattern for service config/HTTP.
- [ ] Deployment note in module docs for the self-hosted service (out-of-band infra); tests use a fake transcriber.
- [ ] Unit test: the registry returns the configured transcription backend; the fake returns text. Pint + PHPStan.

## Task 9: Embedding dedup + per-media rows (M15)

- [ ] Reuse the embedding vector by embed-text hash (deterministic for text+model) to avoid a second embeddings-service call for a duplicated file; still write `ModelEmbedding` rows **per media** (morph → each copy).
- [ ] Feature test: two media with identical embed-text produce per-media embedding rows but only one embeddings-service call. Pint + PHPStan.

## Task 10: ACL — unified result set, owner-inherited, switchable (M16)

- [ ] `IOwnerAuthorizer` + `OwnerAuthorizerRegistry` (Core) keyed by owner morph type; CMS registers Content visibility, SAO registers `TicketQueryService::visible()` (each in its own module provider).
- [ ] Add the `core.media.search_visibility` setting (`owner` default / `open`). In `owner` mode, media hits in the unified retrieval are grouped by owner type and re-authorized by delegating to the registered authorizer; media whose owner is not visible are dropped. In `open` mode the authorization is skipped.
- [ ] Feature tests: a user who cannot see the owner does not get the media hit (`owner` mode); `open` mode returns it; a same-file media under a visible owner is returned while its duplicate under a hidden owner is not (dedup leak-safety). Pint + PHPStan.

## Task 11: Parent enrichment + single-owner reindex cascade (M9, M14)

- [ ] On `media_analysis` completion, reindex the media's single owner via `media->model`. The owner's `toSearchableArray()` injects only the media surrogate: Core fields directly + the AI surrogate (entities/idea/intent) through the Task 4 seam (owner Core code does not reference AI). Never the heavy tracks.
- [ ] Feature test: an article aggregates its media's surrogate and becomes findable by a concept present only in the media; the transcript is not in the article document. Pint + PHPStan.

## Task 12: Media lifecycle (M19)

- [ ] Metadata-only edit → re-embed + reindex media + owner, no LLM. File replaced (hash change) → re-analyze (or reuse) + re-embed + reindex. Soft-delete → remove from index + reindex owner (analysis kept); restore reverses. Force-delete → remove from index, delete the media's `ModelEmbedding` rows, reindex owner.
- [ ] Hash refcount / orphan sweep: delete a shared `ai_media_analysis` row (and derived data) only when the last media for its hash is force-deleted. Re-analysis fires only on hash change.
- [ ] Feature tests: each transition does the right index/owner action; the shared analysis survives while another media references the hash and is removed with the last one. Pint + PHPStan.

## Task 13: Filament / gallery surface (implementation detail)

- [ ] Editable `custom_properties` display fields (description/alt_text/keywords) in the media Filament surface; read-only view of the AI analysis with provenance so an editor sees what was AI-generated vs human/embedded.
- [ ] Feature/Livewire test for the edit + provenance display. Pint + PHPStan.

## Task 14: Docs + closeout

- [ ] RAG docs: `Modules/Core/docs/` (media searchable, custom_properties fields, contributor + owner-authorizer seams, `core.media.search_visibility`), `Modules/AI/docs/` (media analysis subsystem, master switch, model registry, whisper), plus any new env/config in the module READMEs.
- [ ] Add a `## Delivery status (date): ...` section here and a `**Documented in:**` line naming the module docs (enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`).
- [ ] Ask the user to run the full suite (`php artisan test --compact`) after the feature tests pass.
