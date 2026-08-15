# Core media foundation — promote media library ownership to Core

**Date:** 2026-08-15
**Modules:** `Modules/Core` (new owner), `Modules/CMS` (becomes consumer)
**Prerequisite for:** `docs/superpowers/plans/2026-08-15-sao-phase-1b-ticket-enrichment.md` Task 4 (SAO ticket attachments)
**Status:** Design proposed. No implementation started. **Touches live media data — approval-gated.**

---

## 1. Purpose

`spatie/laravel-medialibrary` is a Core dependency, but its **table, model and global config all live in CMS today**, so any module wanting attachments would depend on CMS being enabled — which SAO (Core-only) must not. This change re-homes media ownership to Core so media works whenever Core is enabled (i.e. always), making CMS and SAO both **consumers** of one shared media foundation.

Current wiring (all CMS-owned):
- Table **`vend_media`** created by a CMS migration.
- Model **`Modules\CMS\Models\Media`** (extends spatie `Media`, adds `HasVersions`, `SoftDeletes`, `expires_at`).
- **`Modules/CMS/config/media-library.php`** sets the **app-wide** `media_model` to CMS's model.

spatie's `media_model` is a single global class, so to serve both CMS (rich) and SAO (basic) the model must live in Core carrying the behaviour CMS needs.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| M1 | **The table name stays `vend_media`.** No rename, no data move. | Zero-risk for existing data; only ownership moves. |
| M2 | The media model moves to **`Modules\Core\Models\Media`**, carrying the behaviour CMS relies on (`HasVersions`, `SoftDeletes`, `expires_at`). `Modules\CMS\Models\Media` is removed and its references repointed to Core. | One global `media_model` must satisfy every consumer; media is generic, not CMS-specific. |
| M3 | The `media-library` config's `media_model` binding is **owned by Core** (Core media-library config), pointing at `Core\Models\Media`. CMS's override is removed. | Foundation config belongs with the foundation. |
| M4 | Core's media migration is **guarded with `Schema::hasTable('vend_media')`** and Core runs before CMS (dependency order); the CMS media migration file is removed. | Backward-compatible: on existing installs the table already exists (skip); on fresh installs Core creates it; no double-create, no data loss. Already-run CMS migration rows are harmless with the file gone. |
| M5 | CMS keeps its **usage helpers** (`HasMedia`, `HasMultimedia`, `MediaFileNamer`) but they reference `Core\Models\Media`. | Those are about how a model uses media, not who owns the table. |
| M6 | `vend_media` is registered as `CoreTables::Media` (value unchanged); `CMSTables::Media` is removed. | Ownership reflected in the table registry. |

---

## 3. Non-goals

- Renaming `vend_media` to a conventional `core_media`/`media` (would force a data migration; out of scope).
- Changing media behaviour, conversions, or the Filament media plugin wiring.
- SAO's own attachment usage — that is SAO 1b Task 4, which consumes this foundation afterwards.

---

## 4. Backward compatibility (the risky part)

Existing installations already have `vend_media` populated, created by the CMS migration recorded under its filename. The safe sequence:

1. Add a Core `create_media_table` migration for `vend_media`, **guarded** by `if (! Schema::hasTable(...))`. On existing DBs it is a no-op; on fresh DBs it creates the table. Core migrations run before CMS's.
2. Remove the CMS media migration file. Already-applied rows in `migrations` stay; Laravel ignores the missing file. Fresh installs no longer double-create.
3. Point the global `media_model` at `Core\Models\Media`; because the table and columns are identical, existing rows load unchanged through the new model class.
4. CMS consumers (`Content`, helpers, config, tests) reference `Core\Models\Media`; behaviour is preserved because the Core model carries the same traits.

No data is moved or transformed; only ownership and the model class change.

---

## 5. Testing

- Core: a `MediaTest` (adapted from CMS's) proves `Core\Models\Media` maps `vend_media`, soft-deletes, versions, and exposes `expires_at`.
- CMS: existing media/`Content` tests pass unchanged against `Core\Models\Media`.
- A guarded-migration test (or a fresh-migrate run) proves a clean install creates `vend_media` from Core with CMS disabled.
- Full Core and CMS suites green; then SAO 1b Task 4 can build on it.

---

## 6. Risks

| Risk | Mitigation |
|------|-----------|
| Double-create of `vend_media` on existing installs. | Guarded Core migration + removed CMS migration (M4). |
| A missed reference to `CMS\Models\Media` breaks at runtime. | Blast radius is enumerated (config, `HasMedia`/`HasMultimedia` helpers, one test, `Content`); grep-verified before and after. |
| CMS loses media behaviour (versions/soft-delete/expires_at). | Core's model carries exactly those traits (M2); CMS tests are the guard. |
| Filament media plugin points at the old model. | The plugin resolves the global `media_model`; repointing the config is sufficient. |
