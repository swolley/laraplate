# Media AI analysis and search — design

**Date:** 2026-09-18
**Module:** `Modules/Core` (media Core fields, deterministic extraction, event, Searchable, contributor seam) + `Modules/AI` (analysis listener, job, and AI-owned extension table)
**Status:** Core architecture agreed (M1-M15). Open questions still to decide are tracked in §13 (design-level: ACL, locale/multilingual, media lifecycle, seam choice, media-type scope, DAM). No implementation started.

---

## 1. Purpose

Make the shared `media` records (images, audio, video, documents, downloads) participate in search the
same way textual content already does. A media file carries information — an image depicts subjects and
actions, a video shows and says things, a document contains text — and today none of it is retrievable.

The design turns each media into a searchable object by producing, at ingestion, a set of readable
fields on the media row (the source of truth) and deriving an embedded search representation from them
through the **existing** `Searchable` / `ModelEmbedding` pipeline. It reuses the established event
pattern (`ModelRequiresIndexing` → AI pre-processing listeners → `ModelPreProcessingCompleted` →
`FinalizeModelIndexingListener`), so media becomes a first-class indexable model without a second
search engine and without requiring the AI module to be active.

Two levels of ambition, delivered in phases:

- **Phase 1 (this spec, "find the right file")**: deterministic embedded-metadata extraction in Core +
  optional AI enrichment (caption, OCR, transcription, idea/intent) written to display columns and
  embedded through the existing per-model embedding path. No per-chunk citation, no video visual track.
- **Phase 2 (designed here as additive, not built): "find the right point inside the file"**: a
  dedicated chunk store carrying text + track + timestamp for transcript / OCR / visual segments, plus
  keyframe vision analysis for video. Enables timestamped citation and the "what is *shown*" query.

The schema and event contract in Phase 1 are shaped so Phase 2 is a pure addition (a new table + a new
job), never a rewrite.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| M1 | **Analysis is asynchronous enrichment, never a blocking gate on upload.** Upload saves the media and returns; AI analysis runs after, in the background, and triggers reindex on completion. | An LLM/transcription in the upload request path would make uploads slow and fragile. A media without analysis is a valid, still-usable media. |
| M2 | **Deterministic embedded metadata is extracted first, in Core, before any LLM work.** IPTC (IIM + IPTC Core/Extension), XMP, EXIF for images; container/ID3 tags for audio/video; XMP document properties for PDFs. Cheap → expensive, deterministic → probabilistic. | Embedded metadata is free and often human-authored (authoritative). Extracting it first avoids paying vision cost on already-described files and avoids overwriting human work. |
| M3 | **AI-owned data lives in an AI extension table + a Core contributor seam, not in `media` columns.** Core owns only generic media fields it uses on its own and can fill by hand (M3a). AI either (a) **fills an existing Core field** as a provider — Core does not know who wrote it — or (b) stores AI-only data in an **AI-owned extension table** keyed to the media (M3b). Core never references the AI table or its concepts. | Cross-module column patching *is* allowed in this codebase, but only coherently: it fits a **single owned attribute used by the patcher** (the sole precedent, MES adding `tracing_type` to `erp_items` — one column, and `tracing_type` is in fact an intrinsic Item attribute later moved into ERP). AI-media is the opposite shape: a **rich, AI-only field set with heavy TEXT and its own lifecycle**. Putting dozens of AI columns (including large `transcript`/`ocr_text`) on `media` — the Spatie table shared by every module — widens the hot shared row and scatters ownership. The coherent, already-established mechanism for rich cross-module enrichment is a separate owned table + seam (the content extension seam, `2026-09-17-cms-content-extension-seam-design.md`). The rule: single attribute owned+used by the patcher → column patch; rich dataset with its own lifecycle → extension table + seam. |
| M3a | **Core-owned media fields live in the existing Spatie `custom_properties` JSON — no new Core columns.** `description`/`caption`, `alt_text`, `keywords` are generic media-library metadata (display, accessibility, tagging), hand-fillable in the gallery/backoffice. They are not DB-searchable (searchability is produced by `toSearchableArray()` projecting them into the engine), so the existing dynamic bag holds them and Phase 1 adds **zero migrations to the shared `media` table**. A dedicated column is added only if a field must be a DB-level filter/sort, which none of these are. | These fields are justified by Core's own use first; AI is one possible filler. `custom_properties` already exists for exactly this, and keeping them out of dedicated columns avoids widening the Spatie table shared by every module. |
| M3b | **AI-owned extension** (a table, e.g. working name `ai_media_analysis`, read/written only by AI), **keyed by file `content_hash`, not 1:1 with the media row** (M15): the AI-derived data — `transcript`, `ocr_text`, `idea`, `intent`, `entities`, the `analysis` bag, `analysis_status`, `analyzed_at`, `analysis_model_version`, and a **provenance ledger** of which fields AI generated. | AI-specific outputs and their lifecycle belong to AI. Keying by content hash lets duplicated media rows (same file, different owners) share one analysis. The provenance ledger lets AI regenerate only its own values and fill a Core field only when it is empty / not human-authored, without Core needing any `source` concept. |
| M3c | **Provenance lives with AI, not Core.** The "do not overwrite a human edit" rule is enforced by AI: before filling a Core field (M3a) AI checks it is empty/unclaimed and records in its own ledger that it wrote it; a human edit in the gallery is a plain Core write AI then leaves alone. | Provenance is an AI concern (it exists because AI regenerates); Core columns stay plain columns anyone may fill. |
| M4 | **The readable data is the source of truth; the search engine holds a derived copy.** Display data lives in real columns (Core `media` for M3a fields, the AI extension for M3b fields), read by PK for the gallery. The media search document is composed from both and an embedded copy goes to the engine. An embedding is neither readable nor editable. | Two distinct reads: display (fast, by PK, no vector) and search (semantic, in the engine). Ownership of each column follows M3, not the read pattern. |
| M4a | **AI contributes to the media search document through a Core-provided extension seam, not by Core reading AI columns.** `Media` is a Core model; its `toSearchableArray()` serializes Core fields and calls a Core-owned extension point that registered contributors (AI) fill with their fields and facets. Core owns the index and the base mapping; AI contributes its section. This mirrors the content extension seam (see `2026-09-17-cms-content-extension-seam-design.md`, C11/C14). | Media standalone + parent enrichment both need AI-derived fields in the index, but Core cannot reference AI. A registered contributor seam keeps the dependency direction intact while letting AI augment the document. |
| M5 | **Media is indexed both standalone and as parent enrichment.** Standalone: `Media` becomes `Searchable`/`IEmbeddableModel` with its own embeddings and appears as an autonomous result. Enrichment: the owner content's `toSearchableArray()` injects **only the media's compact surrogate** (summary + keywords + entities + idea/intent), never the heavy tracks; an owner aggregates the surrogates of its many media. | Standalone lets a media be found as an object in its own right ("find an image of a man smiling"); enrichment lets the article surface for a concept present only in its media ("lions") without bloating the article index with a media's full transcript. Heavy content is linked, never duplicated onto the owner. |
| M6 | **Three analysis layers, kept separate.** (1) *Descriptive* — what is depicted/said: caption, entities, keywords, OCR, transcript. (2) *Interpretive* — `idea` (central concept) and `intent` (communicative purpose), LLM-generated, lower confidence, marked as such. (3) *Sentiment is out of scope entirely* — no field, no computation. | Descriptive facts ("a man smiling") are captured by the caption/description and are semantically searchable already; an abstract `sentiment` scalar serves a query nobody issues. Idea/intent serve real queries ("material that promotes X", "educational content") the descriptive layer does not. |
| M7 | **The caption must describe subjects and actions, not just name objects.** "man smiling", "child running", "hands signing a contract" — not only "man", "child", "contract". | Action/subject queries in natural language ("find an image of a man smiling") are served by a descriptive caption + the vector, so the caption's quality is the feature. |
| M8 | **Every analysis dimension must pay for itself with a real consumer** (a query or a gate). High-ROI, always computed: OCR, transcription, caption, keywords, entities, idea, intent. Deferred/on-demand: video visual keyframe analysis (Phase 2, expensive). Not computed: sentiment/mood. Separately justified only if the product needs it: safety/moderation labels (a gate, not a search field). | Spending compute (or storage) on an access that will not happen is the same mistake as an unused index. YAGNI applied to both storage and computation. |
| M9 | **Search-document facets ship in Phase 1, before the volume exists.** `toSearchableArray()` carries filterable facets from day one: media id/type, `mime`, `track` (fixed to `media` in Phase 1), `locale`, `keywords`, `entities`. Prefiltering is done by the engine (ANN + structured filters), never by a table partition ("address book by initials"). | The chunk/vector volume lives in the engine's ANN index, not in SQL scans; the SQL row is written once and read by PK. Facets are what keep a large index survivable, and adding them later forces a full reindex. |
| M10 | **`analysis` is a JSON extension bag, empty by default.** Type-dependent, query-gated attributes (future mood, safety labels, anything without a current consumer) live here and touch neither the index nor a promoted column until a concrete query/gate justifies promotion. | Keeps the schema lean under the volume concern; leaves a documented extension seam without speculative columns. |
| M11 | **Ordering: media analysis completes and persists before embeddings run.** On a `Media`, the AI indexing flow registers a `media_analysis` pre-processing step that must finish (and write caption/transcript/idea/intent to the row) before `GenerateEmbeddingsJob` reads `prepareDataToEmbed()`. | Embeddings are derived from the analysis output; embedding before the fields are written would index an empty or metadata-only document. |
| M12 | **AI-absent fallback.** If the AI module is inactive or the `media_analysis` feature is off, the media is still indexed by Core's existing `IndexModelFallbackListener` using only the deterministic layer (embedded metadata + filename/type). | The media table must participate in search regardless of AI availability, matching the existing content pattern. |
| M13 | **Phase 2 is additive.** A future `media_chunk` store (`text` + `track` + `offset`/`timestamp` + embedding) and a keyframe vision job add per-point citation and the video visual track. The `track` facet and the event contract are already present in Phase 1; no Phase 1 artifact is rewritten. | Keeps the expensive, complex work (chunk store, per-frame vision) out of the first cut while guaranteeing it drops in without migration of existing documents. |
| M14 | **A media has exactly one owner (Spatie `morphs('model')`); index and analyze on claim, cascade to that single owner.** The owner is the domain record that owns the media (CMS `Content`/article, SAO `Ticket`), or the transient `MediaDraft` while staging. Analysis/indexing fire on **claim** (real owner attached), never while owned by a `MediaDraft`. Enrichment cascades to that one owner via `media->model`; there is no multi-parent fan-out. | Spatie binds a media row to one owner; `MediaDraft` is a Core-owned (not Spatie) staging bucket. Draft media may be discarded, so analyzing them wastes compute. An owner aggregates its many media's surrogates. |
| M16 | **Media results live in the unified result set and inherit the owner's ACL, re-authorized at rehydration through a Core registry — no ACL in the index.** Media hits rank alongside content hits in one result set. Visibility is decided per media row by its owner (`media->model`): at rehydration the hits are grouped by owner morph type and each group is re-authorized by delegating to that owner module's existing visibility (CMS ACL filter, SAO `TicketQueryService::visible()`), via a Core registry keyed by owner morph type (twin of the M4a contributor seam). A media whose owner the user cannot see is dropped. **This behaviour is switchable through a Core setting** (working name `core.media.search_visibility`, values `owner` (default, owner-ACL-filtered) vs `open` (agnostic media gallery — media rank and return on their own, ignoring owner ACL)). The default is the safe owner-filtered mode; `open` is an explicit opt-in for products that want a true owner-agnostic gallery. The registry-based owner authorization runs only in `owner` mode. | Matches the codebase security stance (index holds no ACL; re-authorize at rehydration via the owner) and reuses each module's visibility instead of reimplementing it. Per-row authorization makes the M15 hash dedup leak-safe: shared analysis never crosses a permission boundary because each duplicated media row carries its own owner. Drafts are excluded (M14), so there is no ownerless-media authorization case in Phase 1. |
| M17 | **Raw tracks stay in the source language; the surrogate is multilingual; media belongs to the base article, not to translations.** `transcript`/`ocr_text` are kept in the spoken/source language, not translated, and embedded in that language (a faithful, citable fact). The surrogate (`caption`/`summary`, `idea`, `intent`, `keywords`, `entities`) is generated once, then translated to the supported locales through the existing translation pipeline (`TranslatedModelSaved` / `HandleModelTranslationListener`), yielding **per-locale media embeddings** (the media is findable in any UI language) and **per-locale parent enrichment** (each article translation gets the surrogate in its locale). A media is language-neutral and owned by the base article, never by a specific translation. | A transcript translated would stop being a faithful citation; the surrogate is interpretive and benefits from being reachable in every UI language. Attaching media to the base article (not per-translation) matches the current model and reuses the per-locale embedding/translation infra. Accepted for now, explicitly to evolve (per-translation/localized media is a future need). |
| M19 | **Media lifecycle after claim.** The owner is set once at claim (`morphs('model')` on the media row) and never changes — there is no owner-move flow, so no dual-owner reindex. Events: **metadata-only edit** (Core display fields in `custom_properties`) → re-embed + reindex the media and its owner, no LLM. **File replaced** → new `content_hash` → re-analyze (or reuse an existing analysis for the new hash) + re-embed + reindex. **Soft-delete** → remove the media from the index + reindex owner; **restore** reverses it; the shared analysis is left untouched (the media may return). **Force-delete/prune** → remove from index, delete the media's `ModelEmbedding` rows, reindex owner. The hash-keyed analysis (M15) is refcounted: it survives while ≥1 media references its `content_hash`, and is deleted (with its derived data) only when the **last** media for that hash is force-deleted. Re-analysis fires **only on hash change**, never on a metadata-only edit. | Most save/delete reindexing comes free from the `Searchable` trait; the media-specific rules are the owner-reindex cascade, re-analysis gated on hash change (no wasted LLM on metadata edits), and refcounted cleanup so a shared analysis is never dropped while another copy still uses it. |
| M18 | **A runtime Settings master switch enables/disables the whole media LLM analysis subsystem.** A DB-backed Core/AI setting (runtime-toggleable, not only static config) gates all LLM media work: metadata extraction, caption/OCR/transcription, idea/intent, surrogate translation, and the media-analysis pre-processing. When off, the media still gets the deterministic Core layer and is indexed via the fallback (M12); no LLM job runs and no cost is incurred. This master switch sits above the finer per-feature/per-module gates (M10/`FeatureModuleGate`) and the `core.media.search_visibility` mode (M16). | Operators must be able to turn the expensive AI media pipeline on/off at runtime (cost, incidents, rollout) without a deploy, while keeping media usable and searchable at the deterministic level. |
| M15 | **The file may be duplicated (Spatie has no shared asset), so dedup the expensive work by `content_hash`, not the rows.** A `content_hash` (sha256) is stored in `custom_properties`; the AI analysis is keyed by it (M3b) and computed **once per distinct file** (lookup-before-work: a fresh row for the hash and model version is reused, not recomputed). Embedding **vectors** are reused by embed-text hash (the vector is deterministic for text+model), but `ModelEmbedding` rows are still written **per media** so each copy is an autonomous, rehydratable result. A human edit to a Core field is per-copy; the shared AI analysis is per-hash. A true media library (shared asset ↔ many contents, a DAM) is out of scope here and **not precluded**: analysis is already decoupled from the media row. | A gallery implies file reuse, which Spatie models only by duplication. Deduping by content hash gives reuse where it costs (LLM/transcription/vision + the embedding call) without a DAM, and honors the M8 ROI rule. Per-media embedding rows keep rehydration simple (a result is a `Media`). |

---

## 3. Semantic model (what analysis produces)

For a media, analysis yields three layers that all feed the same index but play different roles:

1. **Descriptive** (facts): `caption`/`description`, `keywords`, `entities`, `ocr_text`, `transcript`.
2. **Interpretive** (meaning): `idea`, `intent` — LLM-generated, explicitly lower confidence, editable.
3. **Out of scope**: sentiment/mood (M6).

From these, a compact **surrogate** is what rises to the parent on enrichment: `summary`/`description` +
`keywords` (Core, M3a) + `entities` + `idea` + `intent` (AI extension, M3b, contributed through the
seam of M4a). The heavy tracks (`transcript`, `ocr_text`, and Phase 2 visual segments) stay on the
media/extension and never propagate to the parent.

Field ownership (M3): `description`/`caption`, `alt_text`, `keywords` are **Core** columns; `entities`,
`idea`, `intent`, `transcript`, `ocr_text`, the `analysis` bag and lifecycle live in the **AI
extension**. AI may also fill the Core fields when empty (M3c).

---

## 4. Event wiring and pipeline

Reuses the existing pattern verified in `Modules/AI/app/Providers/EventServiceProvider.php`,
`Modules/Core/app/Providers/EventServiceProvider.php`, and the `Searchable` trait.

1. **Save.** `Media` uses `Searchable`; saving a claimed (non-draft) media emits
   `ModelRequiresIndexing` (as content models do at `Searchable.php:96`).
2. **AI listeners run first.**
   - A new `HandleMediaAnalysisListener` (AI) sees a `Media`, and if
     `ai.features.media_analysis.enabled` and the module gate allows it, calls
     `addRequiredPreProcessing('media_analysis')` and dispatches `AnalyzeMediaJob`.
   - The existing `HandleModelIndexingListener` still registers `embeddings` and dispatches
     `GenerateEmbeddingsJob`.
3. **Ordering (M11).** `AnalyzeMediaJob` runs, writes AI fields to the AI extension (and fills empty
   Core fields per M3c), then emits
   `ModelPreProcessingCompleted($media, 'media_analysis')`. Embedding generation must observe the
   persisted fields: `GenerateEmbeddingsJob` for a media is **chained after** analysis completion
   (the analysis job dispatches it on success, or the analysis listener defers the embeddings dispatch
   until `media_analysis` completes). Exact chaining mechanism chosen at implementation; the invariant
   is analysis-persisted-before-embed.
4. **Finalize.** Each job emits `ModelPreProcessingCompleted`; Core's `FinalizeModelIndexingListener`
   finalizes when `allPreProcessingCompleted()` is true (`ModelRequiresIndexing` already tracks
   required vs completed).
5. **Fallback (M12).** With AI off, only Core's `IndexModelFallbackListener` runs and indexes the
   deterministic layer.
6. **Graceful degradation.** `AnalyzeMediaJob::failed()` emits
   `ModelPreProcessingCompleted($media, 'media_analysis')` so a failed analysis still lets the document
   index with the deterministic layer, mirroring `GenerateEmbeddingsJob::failed()`.

---

## 5. Deterministic Core layer

- A dedicated Core extractor service (not the controller) reads embedded metadata via the media
  pipeline and writes **Core fields only** (M3a: `description`/`keywords`) plus technical metadata into
  the existing Spatie `custom_properties` bag — no new columns. It writes no AI-extension data and
  knows nothing of AI.
- **Hook point (M14).** Uploads stage through `MediaDraft` (a Core-owned, non-Spatie staging bucket)
  and are attached on *claim* (`MediaController::upload`/`claim`, `PruneMediaDraftsCommand`). The
  deterministic extraction is best driven by a **model observer on `Media`** (on create) so it runs for
  every path that produces a media, but **indexing and AI analysis fire on claim** (real owner
  attached), never while the media is owned by a `MediaDraft`. The exact observer/lifecycle seam is
  confirmed against the draft→claim flow when implementing.
- The `content_hash` (M15) is computed for the file; for large files this happens in the async job, not
  in the upload request.
- Extraction is synchronous and cheap (metadata read only), adding no meaningful latency beyond the IO
  already performed on upload.

---

## 6. AI analysis job

`AnalyzeMediaJob` (queue-backed, throttled/retried like `GenerateEmbeddingsJob`):

- **Per-mime dispatch.** Image → descriptive caption (subjects + actions, M7) + OCR; audio →
  transcription; PDF/document → text extraction + OCR for scans; video → **audio transcription only in
  Phase 1** (keyframe visual analysis is Phase 2). Every type additionally produces `idea` + `intent`.
- **Lookup-before-work (M15).** The job resolves the file `content_hash` first: if a fresh
  `ai_media_analysis` row exists for that hash and the current `analysis_model_version`, it **reuses it
  and skips the expensive work** (transcription/OCR/vision/caption). Otherwise it analyzes once and
  writes the row keyed by hash.
- **Writes AI-derived fields to the AI extension table** (M3b, keyed by hash), and **may fill a Core
  field** (M3a: `description`/`keywords` in `custom_properties`) only when it is empty/unclaimed,
  recording that write in its provenance ledger; it never overwrites a human or embedded-metadata value
  (M3c).
- On success emits `ModelPreProcessingCompleted($media, 'media_analysis')`; on failure degrades (M12).
- `analysis_status` / `analyzed_at` / `analysis_model_version` track state and enable targeted
  regeneration when the model version changes.

---

## 7. Schema, split by ownership (M3)

**Core side — no new columns (M3a).** The Core display metadata lives in the existing Spatie
`custom_properties` JSON on `media`:

- `description` / `caption` — gallery display text (and part of the surrogate).
- `alt_text` — short accessibility string.
- `keywords` — display chips + search facet (projected by `toSearchableArray()`) + parent enrichment.

Technical embedded metadata (dimensions/duration/GPS) likewise lives in `custom_properties`. Phase 1
adds **zero migrations to the shared `media` table**; the deterministic layer and any AI provider-fill
write into `custom_properties`, never into new columns. A dedicated column would be added only for a
field that must be a DB-level filter/sort (none here).

**AI extension table** (working name `ai_media_analysis`, keyed by `content_hash`, used only by AI — M3b/M15):

- `content_hash` — the key; one row per distinct file, shared by duplicated media rows.
- `entities` — recognized subjects/objects.
- `idea`, `intent` — interpretive layer (M6), lower confidence, editable.
- `ocr_text`, `transcript` — heavy descriptive tracks (embedded standalone, not propagated to parent).
- `analysis` — JSON extension bag, empty by default (M10).
- `provenance` — ledger of which fields (including which Core fields) AI generated (M3c).
- `analysis_status`, `analyzed_at`, `analysis_model_version` — lifecycle.

No `sentiment` anywhere (M6). Core never references this table; AI reads/writes it and, through the
seam of M4a, contributes its fields/facets to the media search document.

`Media` currently extends `Spatie\MediaLibrary\...\Media` and uses `HasVersions` + `SoftDeletes`;
adding `Searchable` and implementing `IEmbeddableModel` (`prepareDataToEmbed`, `embeddings()`) makes it
embeddable through the existing morph (`ModelEmbedding`). `prepareDataToEmbed()` builds the Core text;
the AI-contributed text/facets join via the extension seam, so Core does not import AI to embed AI's
fields.

---

## 8. Searchable representation and facets

- `prepareDataToEmbed()` (Core) builds the text from Core fields; the AI extension contributes its text
  (idea/intent/entities + heavy tracks) through the M4a seam. The composed text is embedded through the
  existing per-model path; `GenerateEmbeddingsJob` chunks it into `ModelEmbedding` vector rows (as it
  does today — vectors only, no chunk payload; that payload is Phase 2).
- **Embedding dedup (M15).** The vector is deterministic for (text, model), so it is reused by
  embed-text hash to avoid a second embedding-service call for a duplicated file; `ModelEmbedding` rows
  are still written **per media** (morph → that copy) so each copy is an autonomous, rehydratable
  result.
- `toSearchableArray()` ships facets from day one (M9): media id/type, `mime`, `track` = `media`
  (fixed in Phase 1), `locale`, `keywords` (Core). `entities` (AI) is contributed as a facet through
  the seam. Core owns the base mapping; AI contributes its section (M4a).
- `isEmbeddable()`/the AI contribution are gated on the feature/module so an AI-off install indexes the
  Core deterministic layer only.

---

## 9. Parent enrichment and reindex cascade

- When analysis completes, in addition to indexing the media, enqueue a reindex for the media's **single
  owner** via `media->model` (M14) — the CMS `Content`/article or SAO `Ticket`. No multi-parent fan-out.
- The owner's `toSearchableArray()` injects **only the media surrogate**: the Core fields
  (description/keywords, from `custom_properties`) directly, plus the AI surrogate (entities + idea +
  intent) through the same contributor seam (M4a) so the owner's Core code does not reference AI. Never
  the heavy tracks (M5). An owner aggregates the surrogates of its many media.
- The reindex is async, outside the upload request path.

---

## 10. Feature flag and tier

- **Master switch (M18)**: a runtime Settings key enables/disables the whole media LLM subsystem. Off →
  deterministic layer + fallback indexing only, no LLM work, no cost.
- Below it: a per-module gate consistent with the existing `FeatureModuleGate`, and the config
  `ai.features.media_analysis.enabled` for static defaults.
- Tier seam: "base" (deterministic + transcription/OCR/caption/idea/intent) vs "deep" (Phase 2 keyframe
  vision). Only "base" exists in Phase 1; the deep tier is gated and unbuilt.

---

## 11. Phase 2 (designed, not built)

Additive only:

- **`media_chunk` store**: `media_id`, `track` (`transcript` | `ocr` | `visual`), `offset`/`timestamp`,
  `text`, embedding. Enables timestamped citation and per-track retrieval.
- **Keyframe vision job**: sample frames on scene changes (not blind 1 fps), caption each with
  subjects/actions + entities + timestamp, aggregate to video-level entities/keywords (surrogate) and
  per-segment chunks. This is what serves the "video shows lions and elephants although the narrator
  never names them" case.
- Both ride the same event contract and the already-present `track` facet; no Phase 1 document is
  reindexed to adopt them beyond indexing the new chunk rows.

**True media library / DAM (M15, not precluded).** A shared asset reused across many contents
(asset ↔ content many-to-many), which Spatie does not model, is a separate future decision with its own
spec. Phase 1 already decouples the AI analysis from the media row (keyed by `content_hash`), so a DAM
drops in without redoing analysis; the current design serves reuse-by-duplication in the meantime.

---

## 12. Testing

- Deterministic extraction populates fields with the correct embedded `source`.
- AI off / feature off → media indexed on the deterministic layer only (fallback path).
- AI on → `AnalyzeMediaJob` populates `llm` fields without overwriting `human`/embedded values; ordering
  invariant holds (embeddings see persisted analysis).
- Analysis failure → document still finalizes (degradation).
- Parent reindex receives the surrogate, not the heavy tracks.
- Facets present in the search document from Phase 1.
- Factory/states for an analyzed `Media`.

---

## 13. Open questions — still to discuss or decide

These are deliberately unresolved. The first group can change the schema or the flow and should be
settled before (or early in) planning; the second is implementation detail that a plan can carry.

### 13a. Open design decisions (may change schema/flow)

- **ACL / security — decided (M16).** Unified result set; media inherits the owner's ACL, re-authorized
  at rehydration through a Core registry keyed by owner morph type. Switchable via a Core setting
  (`core.media.search_visibility`: `owner` default vs `open` agnostic gallery). Residual wiring in §13b.
- **Locale / multilingual — decided (M17).** Raw tracks (transcript/OCR) stay in the source language;
  the surrogate is translated to supported locales via the existing pipeline; media belongs to the base
  article, not to translations. Residual mechanics in §13b.
- **Media lifecycle beyond claim — decided (M19).** Owner fixed at claim (no owner-move); edit/replace/
  soft-delete/restore/force-delete paths, owner-reindex cascade, re-analysis gated on hash change, and
  refcounted cleanup of the shared analysis. Residual mechanics in §13b.
- **The media search-document contributor seam (M4a).** Reuse/generalize the existing content extension
  seam (`2026-09-17-cms-content-extension-seam-design.md`, `searchableExtensionMapping()` contract) or
  add a media-scoped equivalent in Core. AI registers as a contributor; Core stays AI-agnostic.
- **Phase 1 media-type scope.** Confirmed so far: image (caption + OCR), audio (transcription),
  document/PDF (text + OCR), video (audio transcription only; visual is Phase 2). Open: which
  `download`/generic mime types get only the deterministic layer, and whether any type is excluded from
  Phase 1 entirely.
- **True media library / DAM (M15).** Shared asset ↔ many contents; left as a non-precluded future, but
  if the product needs a real library soon it becomes a prerequisite decision rather than a follow-up,
  and would also settle the "human edit is per-copy" reconciliation.

### 13b. Implementation details to confirm

- Exact deterministic-extraction observer seam on `Media` (create), with the indexing/analysis trigger
  fixed at claim (M14) — confirm the precise claim hook in `MediaController::claim` / the draft flow.
- Providers/models for the analysis: vision caption, OCR, audio/video transcription — which services and
  which Claude models, gated behind config and the feature flag.
- PHP libraries for embedded-metadata extraction (IPTC/XMP/EXIF/ID3/PDF XMP), subject to the
  no-new-dependency-without-approval rule.
- Chaining mechanism enforcing analysis-before-embeddings (M11): job-chained dispatch vs deferred
  embeddings dispatch on `media_analysis` completion.
- Where `content_hash` is computed (sync small / async large) and stored in `custom_properties`; the
  embed-text-hash reuse mechanism for `ModelEmbedding` (M15).
- Cost / quota / rate-limit sizing for the heavy analysis queue (building on the existing
  `ThrottlesExceptions` / `RateLimited('embeddings')` pattern; a dedicated `media_analysis` limiter).
- Filament / gallery UI for editing the `custom_properties` display fields and viewing (not editing) the
  AI analysis, including provenance so an editor sees what was AI-generated.
- ACL wiring (M16): the `core.media.search_visibility` setting (`owner`/`open`) that gates the whole
  path; the Core registry mapping an owner morph type to its module's authorizer; how the unified
  retrieval groups media hits by owner type and re-authorizes each group in `owner` mode; and cross-type
  score normalization so media and content rank together sensibly in one result set.
- Lifecycle wiring (M19): the owner-reindex cascade on media save/delete, the hash refcount / orphan
  sweep that deletes a shared analysis only when its last media is force-deleted, and detecting a file
  replacement (hash change) to trigger re-analysis.
- Testing depth and factories/states for an analyzed `Media` and a deduplicated (same-hash) pair.
