# Content Translation `search_text` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add a DB-maintained, per-translation `search_text` plain-text column on `cms_contents_translations` so embeddings include the body (not just the title) and a future DB keyword/FTS search can reach translated text.

**Architecture:** A per-driver trigger regenerates `search_text` from `components` on every INSERT/UPDATE (any write path), doing only schema-agnostic JSON extraction + deterministic concatenation — no regex. HTML stripping and whitespace collapse happen in PHP at embed-read time. `Content::$embed` reads the new column.

**Tech Stack:** PHP 8.5, Laravel 12, nwidart modules, per-driver trigger migrations (pgsql/mysql/oracle/sqlite), Pest.

**Spec:** `docs/superpowers/specs/2026-09-16-content-translation-search-text-design.md`

## Global Constraints

- `declare(strict_types=1);`; braces; explicit types; `#[Override]` on overrides; PHPDoc over inline comments.
- Multi-DB: any raw SQL is per-driver via a `match($connection->getDriverName())` switch, mirroring the existing trigger migrations (`Modules/Core/database/migrations/2024_11_28_224400_create_presettables_table.php`, `..._create_taxonomies_table.php`, ERP `..._create_lock_guard_triggers.php`). Prefer portable Eloquent elsewhere.
- **Extraction is deterministic and identical across drivers** (this is the acceptance bar): schema-agnostic; per top-level key of `components`, join `blocks[*].data.text` in array order (object-with-`blocks`) or take the string (string value); join per-key pieces in **ascending key order** (`ORDER BY key`); single-space separators; **no regex, no HTML strip, no whitespace normalization in SQL**. NULL/empty `components` → `''`.
- SQLite triggers cannot assign to `NEW`: use `AFTER INSERT/UPDATE` doing `UPDATE ... SET search_text = <extraction> WHERE rowid = NEW.rowid` (recursive triggers off by default), following `createSQLiteTriggers()` in the presettables migration. pgsql/mysql/oracle use `BEFORE INSERT/UPDATE` setting `NEW`/`:NEW`.
- Tests in module `tests/`; stubs in `tests/Stubs`; run `vendor/bin/pint --dirty --format agent` before finishing each task. Commit in the `Modules/CMS` / `Modules/Core` submodule on `master`.

---

### Task 1: `search_text` column + per-driver triggers + backfill

**Files:**
- Create: `Modules/CMS/database/migrations/2026_09_16_000000_add_search_text_to_content_translations.php`
- Test: `Modules/CMS/tests/Feature/Search/ContentTranslationSearchTextTriggerTest.php`

**Interfaces:**
- Produces: column `search_text` (text, nullable) on `cms_contents_translations`, DB-maintained by triggers; value = the deterministic extraction (Global Constraints) of `components`.

- [ ] **Step 1: Write the failing extraction/parity test**

Build a `Content` with one `ContentTranslation` whose `components` is the fixture below (mirrors the real shape: an Editor field with blocks, plain strings, an HTML string, and a non-text value), save it, read `search_text` back from the DB, and assert it equals the expected string. Use a data-provider of fixtures; the **expected value is driver-independent** (so the same assertions gate each driver's CI run). Example fixture + expectation:

```php
$components = [
    'content' => ['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Primo <b>blocco</b>.']],
        ['type' => 'paragraph', 'data' => ['text' => 'Secondo blocco.']],
    ]],
    'short_content' => 'Sommario.',
    'contacts' => '<p><a href="tel://371">371 3139915</a></p>',
    'position' => 3, // non-string, non-blocks → skipped
];
// keys ascending: contacts, content, position(skip), short_content
$expected = '<p><a href="tel://371">371 3139915</a></p> Primo <b>blocco</b>. Secondo blocco. Sommario.';
```

Assert `$translation->fresh()->search_text === $expected`. (HTML retained; no normalization — that is PHP's job later.)

- [ ] **Step 2: Run it — expect FAIL** (column/trigger absent)

Run: `php artisan test --compact Modules/CMS/tests/Feature/Search/ContentTranslationSearchTextTriggerTest.php`

- [ ] **Step 3: Write the migration — column, then per-driver triggers, then backfill**

Add the nullable `search_text` text column. Then, in a `match($connection->getDriverName())`, create the trigger(s) per driver implementing the deterministic extraction from Global Constraints (mirror the structure of `createPostgreSQLTriggers()`/`createSQLiteTriggers()` in the presettables migration; add mysql/oracle following the same shape). pgsql/mysql/oracle: `BEFORE INSERT/UPDATE` setting the value on `NEW`; sqlite: `AFTER INSERT/UPDATE` updating the row by `rowid`. Extraction building blocks per dialect: pgsql `jsonb_each`/`json_each` + `json_array_elements` + `string_agg(... ORDER BY key)`; mysql `JSON_TABLE` + `GROUP_CONCAT(... ORDER BY key)`; sqlite `json_each`/`json_tree` + `group_concat`; oracle `JSON_TABLE` + `LISTAGG`. NULL `components` → `''`. Then backfill existing rows with the same extraction as one `UPDATE cms_contents_translations SET search_text = <extraction>` per driver. Provide a `down()` that drops the triggers and the column.

- [ ] **Step 4: Run it — expect PASS on the current driver**

Run the test file. It must pass on sqlite (the default suite driver); the same assertions gate pgsql/mysql/oracle when the suite runs under those connections.

- [ ] **Step 5: Add the raw-write regeneration test**

Assert the trigger fires on a **non-Eloquent** write: `DB::table('cms_contents_translations')->where('id', $id)->update(['components' => json_encode($otherComponents)])`, then `expect(DB::table(...)->where('id',$id)->value('search_text'))->toBe($otherExpected)`. This proves the DB-owned guarantee.

- [ ] **Step 6: Pint + commit (CMS)**

```bash
vendor/bin/pint --dirty --format agent
cd Modules/CMS && git add -A && git commit -m "feat(search): DB-maintained search_text on content translations (per-driver triggers)"
```

---

### Task 2: Embedding reads `search_text` (with PHP cleanup)

**Files:**
- Modify: `Modules/CMS/app/Models/Content.php` — `$embed`.
- Modify: `Modules/Core/app/Search/Traits/Searchable.php` — `collectEmbedText` (the private helper Task 11 of the ES plan extracted; it gathers embed text per attribute).
- Test: `Modules/CMS/tests/Feature/Search/ContentEmbedTextIncludesBodyTest.php`

**Interfaces:**
- Consumes: `ContentTranslation::$search_text` (real column, Task 1).
- Produces: `prepareDataToEmbedByLocale($content)` returns, per locale with its own translation, `title` + cleaned body text (HTML stripped, whitespace collapsed).

- [ ] **Step 1: Write the failing test**

Build a bilingual `Content` (it + en) with distinct bodies via `components` (containing an Editor field + an HTML string). Assert `prepareDataToEmbedByLocale()` returns one entry per locale, each containing the locale's body words with **no** HTML tags (`str_contains` false for `'<'`), and that `it` and `en` texts differ. Assert `Content::$embed === ['title', 'search_text']`.

- [ ] **Step 2: Run it — expect FAIL** (still `['title','textual_only']`; body absent/HTML present)

- [ ] **Step 3: Implement**

- `Content.php`: `protected array $embed = ['title', 'search_text'];`
- `Searchable::collectEmbedText` (or `isValidEmbedValue`/the concatenation point): strip HTML and collapse whitespace on each collected string, e.g. `mb_trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)))`. Apply it where the per-attribute value is read so both `title` and `search_text` are cleaned (harmless for title). Keep the existing empty/invalid-value guard.

- [ ] **Step 4: Run it — expect PASS**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Search/ContentEmbedTextIncludesBodyTest.php`

- [ ] **Step 5: Pint + commit (Core, then CMS)**

Two submodule commits: Core (`collectEmbedText` cleanup), CMS (`$embed` + test).

```bash
vendor/bin/pint --dirty --format agent
# Core:
cd Modules/Core && git add -A && git commit -m "feat(search): strip HTML/whitespace when gathering embed text"
# CMS:
cd ../CMS && git add -A && git commit -m "feat(search): embed Content body via search_text column"
```

---

### Task 3: Documentation

**Files:**
- Modify: `Modules/CMS/docs/rag/MODULE.md` (or the closest existing CMS search/content doc) and `Modules/Core/docs/rag/SEARCH_RETRIEVAL_PIPELINE.md` — describe `search_text`: what it is (per-translation plain text of `components`), that the **database maintains it via per-driver triggers** on any write, that HTML/whitespace cleanup happens in PHP at embed time, and that Content now embeds title + body. Note Part B (FTS) is a follow-up.

- [ ] **Step 1: Update the module docs** with the above, in the existing style. No env vars added.
- [ ] **Step 2: Commit (CMS, Core)** the doc changes.

---

## Out of scope (Part B — separate plan)

Postgres `tsvector` generated column on `search_text` + GIN, and a translation-aware Scout `DatabaseEngine` keyword search over `content_translations.search_text` (the engine currently qualifies columns on `contents`). Depends on this column; its own design/plan.

## Self-Review

- Spec coverage: Task 1 = column + per-driver triggers + backfill + parity/raw-write tests; Task 2 = `$embed` switch + PHP cleanup + body-in-embed test; Task 3 = docs. All spec sections mapped.
- Determinism: extraction fixes key order (`ORDER BY key`) and does no regex — the one hard parity requirement, encoded in Global Constraints and the Task 1 fixtures.
- The embedding job already `->fresh()`es, so it reads the trigger-computed value; no extra refresh needed (noted in spec decision 5).
