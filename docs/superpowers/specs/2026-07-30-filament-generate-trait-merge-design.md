# HasTable/HasForm — Filament generate merge (Design)

Date: 2026-07-30  
Status: implemented (verified 2026-08-14 — generators `LaraplateResourceTable/Form/InfolistClassGenerator`, runtime strip via `FilamentTraitResolver::HAS_TABLE_STRIP_FROM_GENERATED_COLUMNS` in `HasTable::configureColumns`)

## Goal

When `filament:make-resources` (or any rebound Filament make) runs with `--generate`, domain columns/fields from Filament DB inspect are passed into Laraplate traits. Traits keep ownership of platform UX; generated extras that the trait already covers are dropped; the rest are kept.

## Decisions locked

| Topic | Decision |
|---|---|
| Pattern | Approach A — Filament generate feeds trait callbacks / schema body |
| Deduping | Both: generate-time `exceptColumns` **and** runtime merge strip |
| Traits | Always present (`HasTable` / `HasForm`) |
| FK / indexes / calculated | Do **not** exclude generically |
| Timestamps | Trait owns composite `timestamps`; strip Filament `created_at`, `updated_at`, `deleted_at` |
| Validity | Trait owns composite `validity`; strip Filament `valid_from`, `valid_to` at merge/except |
| Forms (HasDynamicContents) | Keep stripping `entity_id` / `presettable_id` (already shipped) |
| Tables + entity_id | Do **not** auto-strip `entity_id` / `presettable_id` on tables (domain tables often show `entity.name` / similar) |

## Table generator output

`LaraplateResourceTableClassGenerator` emits:

```php
return self::configureTable(
    table: $table,
    columns: static function (Collection $default_columns): void {
        $default_columns->unshift(
            // Filament --generate column expressions (except owned names)
        );
    },
);
```

If there are no generated columns, keep `return self::configureTable(table: $table);` (no empty callback required).

Actions/filters stay entirely with `HasTable` (do not copy Filament’s generated actions/filters into the stub).

## Runtime merge (`HasTable::configureColumns`)

After the `$columns` callback runs, reject columns whose names are in the strip list (Filament grezzi replaced by trait composites), then continue with existing `keyBy` / PK ensure / `pushColumns`.

Same-name overlaps (e.g. `order_column`, `is_locked`) remain handled by `keyBy` (trait columns added first, unshift puts generated first → last wins = trait).

## Owned / strip list (tables)

Always (generate except + runtime):

- `created_at`, `updated_at`, `deleted_at`
- `valid_from`, `valid_to`

Plus activation column name when the model exposes `activationColumn()` (generate except; same-name runtime via `keyBy`).

## Forms

Unchanged contract: `HasForm` + `exceptColumns` / runtime strip for `entity_id` + `presettable_id` on `HasDynamicContents` models. `--generate` remains enabled on `filament:make-resources`.

## Testing

- Resolver / helper returns the table strip list.
- Table generator with `isGenerated: true` does not emit `created_at` / `updated_at` / `deleted_at` / `valid_from` / `valid_to` in the stub; does emit `configureTable` + `columns:` callback when other columns exist.
- Optional: runtime strip test via harness if cheap.

## Non-goals

- Replacing `HasTable` column building with Filament-only tables.
- Broad FK exclusion on tables.
- Changing hand-written Core/CMS table callbacks beyond compatibility with the merge rules.
