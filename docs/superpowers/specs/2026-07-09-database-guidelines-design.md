# Database Guidelines

Design reference for migrations, queries, and indexes in Laraplate with **multi-database compatibility** as a first-class requirement.

## Supported databases

Laraplate targets compatibility with these drivers (in order of day-to-day attention):

| Driver | Role |
|--------|------|
| **MySQL** | Primary production target |
| **MariaDB** | Production alternative (treat like MySQL unless a known divergence applies) |
| **PostgreSQL** | Primary production target |
| **Oracle** | Production target where OCI8 / Instant Client is available |
| **SQLite** | **Default for automated tests** (in-memory or file); must not break the suite |

Configuration: `config/database.php`. Default connection: `sqlite` (tests and local quick start).

**Rule:** schema and application SQL must remain portable across all five unless a deliberate, documented exception exists. When a feature cannot be supported on every driver, guard it with `DB::getDriverName()` / `match` and document the gap (inline comment + this spec if recurring).

SQLite is not a “second-class” database: if migrations or queries fail on SQLite, CI fails. Production-only shortcuts without a SQLite-safe path are not acceptable for shared Core/module migrations.

## Migration principles

### 1. Prefer Schema Builder

Use `Blueprint` for tables, columns, foreign keys, and standard btree/unique indexes. Keeps Laravel abstraction and improves cross-driver behavior.

### 2. Branch on driver for dialect-specific features

When a capability differs by engine (generated columns, index types, triggers, check constraints), use explicit driver branches — see `Modules/Core/app/Helpers/MigrateUtils.php` and `Modules/Core/database/migrations/2024_03_15_224941_create_permission_tables.php` as reference implementations.

```php
match (DB::connection()->getDriverName()) {
    'pgsql' => /* PostgreSQL-specific */,
    'oracle' => /* Oracle-specific (triggers, etc.) */,
    'sqlite' => /* SQLite fallback (triggers, plain columns) */,
    'mysql', 'mariadb' => /* MySQL/MariaDB */,
    default => /* portable Blueprint fallback */,
};
```

### 3. Reuse `MigrateUtils`

For timestamps, soft deletes, locks, and validity columns, use `MigrateUtils::timestamps()` instead of reimplementing per module. It already encodes driver differences (`is_deleted` / `is_locked` generated columns vs Oracle triggers).

### 4. Raw SQL

`DB::statement()` is allowed when Blueprint cannot express the index or constraint. Requirements:

- Guard with driver check when not portable to all five drivers.
- Use table/column names from enums/constants (e.g. `CMSTables`, `CoreTables`), not hardcoded guesses.
- Prefer `DB::afterCommit()` for secondary indexes created during migration if race conditions were observed (see `MigrateUtils` validity indexes).

### 5. Naming

- Module tables: lowercase module prefix (`core_`, `cms_`, `erp_`, …).
- Index names: `{table}_{column(s)}_{IDX|UN|FK}` or project convention already in sibling migrations.

## Indexes (general)

Add indexes that match **real query patterns**, not “just in case”.

| Pattern | Index type |
|---------|------------|
| `WHERE id = ?`, FK joins | Primary key / foreign key (often automatic) |
| `WHERE slug = ?`, unique business key | `UNIQUE` (+ soft-delete composite if needed) |
| `WHERE status = ?`, `WHERE locale = ?` | `btree` on filter column or composite |
| `ORDER BY created_at DESC` (large tables) | `btree`; PostgreSQL may use `BRIN` for append-only timestamps (`MigrateUtils`) |
| Range on validity (`valid_from`, `valid_to`) | Composite; PostgreSQL may use `DESC` via raw SQL |

Avoid redundant indexes: a `UNIQUE (slug, deleted_at)` often makes a separate `btree` on `slug` unnecessary.

Do not add vendor-only index types (GIN, BRIN, FULLTEXT) without a driver branch and a clear query that uses them.

## FULLTEXT and text search indexes

FULLTEXT / `to_tsvector` is **one index type among many**, not the default for text columns.

### When FULLTEXT is appropriate

- Long prose: `text` / `mediumtext` body, description, notes, comments.
- Multi-word natural-language search **on the database** with relevance ranking.
- Large tables with queries using `whereFullText`, `MATCH ... AGAINST`, or `to_tsvector` / `plainto_tsquery`.

### When FULLTEXT is wrong

- Identifiers: `slug`, `code`, `SKU`, `email`, `UUID`, `locale`, status enums → `UNIQUE` / `btree`.
- Short strings (~<30 chars) with equality or prefix lookup → `btree` + `=` or `LIKE 'term%'`.
- JSON columns → Scout/external index or derived searchable column.
- Fields searched only via Typesense/Elasticsearch unless DB fallback is explicit.

Column max length is secondary: a `varchar(255)` slug is still a bad FULLTEXT candidate.

### Driver notes for FULLTEXT

| Driver | Support |
|--------|---------|
| MySQL / MariaDB | `ALTER TABLE ... ADD FULLTEXT` (InnoDB); min token length ~3–4 |
| PostgreSQL | `GIN (to_tsvector('lang', column))`; pick language config deliberately |
| SQLite | FTS5 possible but **not** assumed in shared migrations; skip or document |
| Oracle | Use Oracle Text only with explicit branch; not in default shared migrations |

### Laraplate / Scout

- Default Scout driver: `typesense` (`Modules/Core/config/scout.php`).
- DB FULLTEXT is used by Scout only when `SCOUT_DRIVER=database` **and** the model has `#[SearchUsingFullText(['column'])]` on `toSearchableArray()`.
- Prefix search: `#[SearchUsingPrefix(['column'])]`, not FULLTEXT.
- A migration FULLTEXT index without matching queries is write overhead only.
- `DatabaseTranslator` must not map every `FieldType::Text` to `fulltext`.

### FULLTEXT migration pattern (MySQL / MariaDB / PostgreSQL only)

```php
if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
    DB::statement("ALTER TABLE {$table} ADD FULLTEXT {$table}_title_IDX (title)");
} elseif (DB::getDriverName() === 'pgsql') {
    DB::statement("CREATE INDEX {$table}_title_fts_idx ON {$table} USING gin(to_tsvector('english', title))");
}
// SQLite / Oracle: omit unless a tested branch is added
```

## Driver-specific patterns (reference)

### Generated / virtual columns

| Concern | MySQL/MariaDB | PostgreSQL | Oracle | SQLite |
|---------|---------------|------------|--------|--------|
| `is_deleted` from `deleted_at` | `storedAs('IF(...)')` | `storedAs('deleted_at IS NOT NULL')` | boolean + trigger | plain column + trigger |
| Permission name parsing | `AS (expression) STORED` | `GENERATED ALWAYS AS (...) STORED` | branch or app-level | plain columns + triggers |

Always provide a SQLite path for shared Core migrations.

### PostgreSQL extras

- `BRIN` for some timestamp indexes (`MigrateUtils::createDateIndex`).
- `DESC` in composite indexes via raw `CREATE INDEX` when query plans benefit.

### Oracle extras

- Triggers for boolean flags when generated columns are not viable (`MigrateUtils`).
- Connection registered only when `oci8` extension is loaded (`config/database.php`).

### SQLite (tests)

- Default test DB; requires `pdo_sqlite` (see `Modules/Core/README.md`).
- No `regexp_replace` in generated columns — use triggers or application logic.
- Some MySQL functions (`REGEXP_INSTR`, etc.) unavailable — branch required.
- Vector search in `DatabaseEngine` has a SQLite-specific code path (in-memory similarity).

## Application queries

- Prefer Eloquent / query builder over raw SQL.
- Raw SQL that is not portable must use driver branching (same rule as migrations).
- Use `config('database.default')` / connection driver at runtime, not `env()` outside config.
- `ILIKE` is PostgreSQL-only; use `like` on MySQL/SQLite or driver-aware helpers (Scout `DatabaseEngine` already branches).

## Testing requirements

- Migrations must run cleanly on **SQLite** (PHPUnit/Pest default).
- When adding driver-specific branches, add or extend tests on SQLite at minimum; add dedicated tests for other drivers only when CI covers them.
- Skip tests that require a specific driver with an explicit `->skip(fn (): bool => ...)` and a clear reason (see existing SQLite-only vector search test pattern).

## Decision flows

### New column

```
Portable type via Blueprint?
  → yes: use Blueprint
  → no: driver branch + SQLite fallback
```

### New index

```
Query pattern?
  → equality / FK / unique business key → btree or unique
  → filter / sort → btree (or BRIN on PG for append-only timestamps)
  → free-text multi-word on DB → FULLTEXT (mysql/mariadb/pgsql branch only)
  → search via Typesense/ES → external index; DB FULLTEXT only for explicit fallback
```

## Current codebase notes (2026-07-09)

| Item | Note |
|------|------|
| `cms_locations.slug` FULLTEXT | Remove — use existing unique/btree |
| `cms_locations.name`, translation `title`/`name` FULLTEXT | Marginal; only if DB Scout fallback is required |
| No `SearchUsingFullText` on models yet | FULLTEXT indexes unused with default `typesense` |
| `MigrateUtils` | Canonical place for cross-driver timestamp/soft-delete/lock patterns |
| `permission_tables` migration | Canonical example of mysql/mariadb/pgsql/sqlite branching |

## Related files

- `config/database.php`
- `Modules/Core/app/Helpers/MigrateUtils.php`
- `Modules/Core/app/Search/Engines/DatabaseEngine.php`
- `Modules/Core/app/Search/Translators/DatabaseTranslator.php`
- `.cursor/rules/09-database-guidelines.mdc` — agent rule summary
- `docs/superpowers/specs/2026-07-09-large-dataset-query-patterns-design.md` — query iteration / Filament performance
