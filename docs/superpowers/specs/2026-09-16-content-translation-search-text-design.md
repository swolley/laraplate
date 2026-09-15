# Content Translation `search_text` — Design

**Status:** Draft (for review)
**Date:** 2026-09-16
**Author:** swolley + Claude
**Follow-up of:** `2026-09-12-es-multilingual-index-and-multimodel-embeddings-design.md` (final-review item 1: Content embeds only `title`, not the body).

## Problem

The multilingual embedding refactor generates one vector per translation from `Content::$embed = ['title', 'textual_only']`. But `textual_only` is a runtime accessor on `HasDynamicContents` (Content), computed from the *current locale*; it does **not** exist on `ContentTranslation` (which uses only `HasSlug`). `Modules\Core\Search\Traits\Searchable::prepareDataToEmbedByLocale` reads `$translation->{$attr}`, so `textual_only` resolves to `null` and **only `title` is embedded**. Verified live: `prepareDataToEmbedByLocale(content 1)` returns the 34-char title only; `$translation->textual_only` throws "attribute does not exist".

The body text lives per translation inside a single `components` JSON column (dynamic fields, names vary by content type). There is no real text column for it, so:
- embeddings miss the body (headline value of the refactor unrealized);
- the Scout **Database** engine (keyword fallback) is blind to translated text — it `LIKE`-matches real columns of the model table (`contents.*`), and `contents` has no textual columns at all.

## Goal

A real, per-translation plain-text column `search_text` on `cms_contents_translations`, kept in sync **by the database itself** so it is correct under any write path (Eloquent, raw, mass/bulk), feeding embeddings (with the body) and later a translation-aware DB keyword/FTS search.

## Decisions

1. **Persist, don't compute-at-read.** All searchable text is per translation (N rows), and indexing needs every language; a per-translation stored column is the natural home and the only SQL-queryable one (`contents` has no text columns). Chosen over a per-translation PHP accessor, which is not queryable in SQL.

2. **The database owns the column, via per-driver triggers.** A trigger regenerates `search_text` on every `INSERT`/`UPDATE` of `cms_contents_translations`, for **any** write path — the "set-and-forget" property the team wants. This is the codebase's established pattern (per-driver switched migrations with `CREATE TRIGGER`, e.g. `create_presettables_table.php`, `create_taxonomies_table.php`, ERP lock-guard triggers), maintained and tested for pgsql/mysql/oracle/sqlite. An app observer was rejected: it would cover only Eloquent writes (all writes are Eloquent *today* — `ContentUpserter` uses `setTranslation()` + `save()`, `HasTranslations` uses model `update()`/`create()`, no raw/bulk writes exist — but it gives no DB guarantee for future paths).

3. **Extraction is schema-agnostic and deterministic.** The trigger reads only `NEW.components`; it does not consult the entity/preset field schema (removes the schema dependency and any schema-change staleness). Semantics: for each top-level key in `components`, if the value is an object with a `blocks` array, concatenate every `blocks[*].data.text` **in array order**; if the value is a string, include it as-is; otherwise skip. Join the per-key pieces **in ascending key order** with a single space. Key ordering is imposed explicitly (`ORDER BY key`) because storage key order differs across engines (MySQL sorts JSON object keys, pgsql `json` preserves insertion order, etc.) — without a fixed order the concatenation would differ and break parity.

4. **The trigger does NO regex and NO normalization — pure extraction + concatenation.** SQLite has no native `regexp_replace`, so neither HTML stripping nor whitespace collapsing can be done identically in SQL across drivers. So the trigger emits the **raw** concatenated text (HTML retained, whitespace as-is) — only JSON extraction + `ORDER BY key` + string aggregation, which are portable and yield identical output on every driver. **All cleanup happens in PHP at read time:** `prepareDataToEmbedByLocale`/`collectEmbedText` applies `strip_tags` + whitespace collapse before embedding (one place, identical everywhere, read-only; does not touch the DB-owned-field property). Part B (FTS) normalizes at index build; `to_tsvector` already drops punctuation.

5. **Embedding integration is a column read.** `Content::$embed` changes from `['title', 'textual_only']` to `['title', 'search_text']`. `prepareDataToEmbedByLocale` already reads `$translation->{$attr}` → now two **real columns**: no accessor, no schema lookup, no N+1. `GenerateEmbeddingsJob` already `->fresh()`es the model, so it reads the trigger-computed value (the in-memory instance does not know a BEFORE/AFTER-trigger result until reloaded). `textual_only` stays as-is (a separate current-locale PHP accessor with other uses); it is not unified with `search_text`.

## Per-driver trigger notes (the hard part — for the plan)

Mirror the existing per-driver trigger migrations. Known dialect differences the implementation must handle, with cross-driver output parity as the acceptance bar:

- **JSON extraction dialects:** pgsql `jsonb_each` + `jsonb_path_query`/`jsonb_array_elements` for `blocks[*].data.text`; MySQL/Oracle `JSON_TABLE`/`JSON_EXTRACT`; SQLite `json_each`/`json_tree`. All must yield the **same** concatenated string for the same `components` (embedding coherence across environments depends on it).
- **`NEW` assignment:** Postgres (`NEW.search_text := ...; RETURN NEW;`), MySQL (`SET NEW.search_text = ...`), Oracle (`:NEW.search_text := ...`) can set the value in a `BEFORE` trigger. **SQLite cannot modify `NEW`** — use an `AFTER INSERT/UPDATE` trigger doing `UPDATE ... SET search_text = <extraction> WHERE rowid = NEW.rowid` (recursive triggers are off by default, so it does not loop); follow the SQLite path already used in `create_presettables_table.php`'s `createSQLiteTriggers()`.
- **NULL / empty `components`** → `search_text = ''` (or NULL, chosen consistently across drivers).
- Trigger fires on soft-delete writes too (harmless: recomputes from unchanged `components`).

## Backfill

The trigger only fires on future writes; existing rows need one-time population. The migration runs a per-driver backfill (the same extraction expression as an `UPDATE ... SET search_text = <extraction>` over all rows) right after creating the triggers, so no application code is required to make existing content correct.

## Testing

- **Cross-driver parity (the key test):** the same `components` fixture produces byte-identical `search_text` on every driver available in CI (pgsql, mysql, sqlite; oracle when its suite runs). Prefer a data-provider fixture set covering: Editor field with multiple blocks, a plain string field, an HTML string field (`contacts`), a NULL components, a field that is neither string nor blocks-object.
- **Trigger regeneration under any write:** an Eloquent save AND a raw/mass `UPDATE ... SET components = ...` both refresh `search_text`.
- **Backfill:** existing rows get populated by the migration.
- **Embedding now includes body:** `prepareDataToEmbedByLocale(content)` returns title + body text, HTML stripped, distinct per locale; `Content::$embed` is `['title','search_text']`.

## Out of scope (Part B — separate follow-up)

- Postgres `tsvector` (generated column `to_tsvector(search_text)` + GIN) and a **translation-aware** Scout DatabaseEngine keyword search over `content_translations.search_text` (the engine currently qualifies columns on `contents`). This is what makes the no-ES keyword fallback actually search the body; it depends on this column existing but is its own design/plan.

## Risks

- **Cross-dialect extraction parity** is the main risk and the bulk of the work; the parity test is the gate.
- SQLite `AFTER`-trigger self-update pattern must match the existing, tested convention.
- HTML retained in the stored column is intentional (see decision 4); any consumer that displays `search_text` must strip.
