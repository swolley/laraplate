# Core Media Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-home `spatie/laravel-medialibrary` ownership from CMS to Core — table, model and global config — keeping the `vend_media` table name and data intact, so CMS and (later) SAO are both consumers of one Core media foundation.

**Architecture:** Core owns `vend_media` (registered `CoreTables::Media`), the `Core\Models\Media` model (carrying `HasVersions`, `SoftDeletes`, `expires_at`), and the app-wide `media_model` config binding. CMS keeps its usage helpers but references Core's model; its own media model, migration and config override are removed. The Core migration is guarded so existing installs are untouched.

**Tech Stack:** PHP 8.5, Laravel 12, `spatie/laravel-medialibrary` 11, Pest 4, Pint, PHPStan.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-08-15-core-media-foundation-design.md` (decisions M1–M6). **Touches live media data** — implement exactly the backward-compatible sequence in §4.
- `declare(strict_types=1);`; explicit types; `#[Override]`; follow sibling conventions in Core/CMS.
- Table name `vend_media` is unchanged. No data is moved.
- Per step: run the relevant Core and CMS tests; before each commit Pint (per-module config) on touched files. Regenerate autoload with `COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload` when classes move.
- This spans two submodules (Core, CMS). Commit Core first (adds the model/table/config), then CMS (repoints + removes its own), so no intermediate state references a missing class.

---

## Task 1: Core owns the media table, model and config

- Edit: `Modules/Core/app/Enums/CoreTables.php` — add `Media = 'vend_media'`.
- Create: `Modules/Core/database/migrations/..._create_media_table.php` — the `vend_media` schema (copied verbatim from the CMS migration), wrapped in `if (! Schema::hasTable(CoreTables::Media->value))`.
- Create: `Modules/Core/app/Models/Media.php` — moved from CMS (`extends Spatie\...\Media`, `HasFactory`, `HasVersions`, `SoftDeletes`, `expires_at` accessor, `$table = CoreTables::Media->value`).
- Create: `Modules/Core/config/media-library.php` (or extend Core config) binding `media_model => Modules\Core\Models\Media::class`; ensure it is the effective app-wide binding.
- Move the media factory to Core if one exists.
- Create: `Modules/Core/tests/.../MediaTest.php` (adapted from CMS): maps `vend_media`, soft-deletes, versions, `expires_at`.

- [ ] **Step 1: failing test** — `Core\Models\Media` uses `vend_media`, soft-deletes and exposes `expires_at`.
- [ ] **Step 2: run to fail → Step 3: implement → Step 4: run to pass** (Core suite green).
- [ ] **Step 5: Pint + commit Core** (`feat(core): own the media library table, model and config`). Push + bump parent.

---

## Task 2: CMS consumes Core media

- Edit: `Modules/CMS/config/media-library.php` — remove the `media_model` override (or repoint to Core) so the Core binding wins.
- Edit: `Modules/CMS/app/Helpers/HasMedia.php`, `HasMultimedia.php` — reference `Modules\Core\Models\Media`.
- Edit: `Modules/CMS/app/Models/Content.php` and any other consumer — resolve to Core's media model.
- Delete: `Modules/CMS/app/Models/Media.php`, the CMS `create_media_table` migration, and `CMSTables::Media`.
- Edit: `Modules/CMS/tests/Unit/Models/MediaTest.php` — point at `Core\Models\Media` (or move its intent into Core's test and drop the CMS one).

- [ ] **Step 1: run the CMS media/Content tests to see them fail against the removed model.**
- [ ] **Step 2: repoint references and remove the CMS-owned pieces.**
- [ ] **Step 3: run to pass** — CMS suite green; a `grep` for `CMS\Models\Media` returns nothing.
- [ ] **Step 4: fresh-migrate check** — migrate a clean DB with CMS enabled and (separately, if feasible) with CMS disabled; `vend_media` exists once, created by Core.
- [ ] **Step 5: Pint + commit CMS** (`refactor(cms): consume Core media model`). Push + bump parent.

---

## Exit criteria

- `vend_media` is owned by Core, created once (guarded), data intact; `Core\Models\Media` is the global `media_model`.
- CMS uses Core's media with unchanged behaviour; no reference to `CMS\Models\Media` remains.
- Core and CMS suites green; Pint/PHPStan clean.
- Unblocks SAO 1b Task 4 (Ticket `HasMedia` on the Core foundation).

## Known risks / rollback

- If a fresh migrate double-creates `vend_media`, the guard or migration order is wrong — fix before merging.
- Rollback is reverting both commits; because the table name and data are unchanged, no data recovery is needed.
