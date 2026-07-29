# HasForm — Entity → Preset → Presettable (Design)

Date: 2026-07-29  
Status: implemented (Core `HasForm` + CMS Content/Category/Contributor forms + generator)

## Goal

Finish `Modules\Core\Filament\Utils\HasForm` so Filament create/edit forms for models that use `HasDynamicContents` expose a coherent Entity → Preset cascade, persist only `presettable_id`, and never treat form-submitted `entity_id` as the source of truth.

## Non-goals

- Creating new `presettables` rows from domain create/edit forms.
- Letting the user pick a historical presettable version on create (always active/latest).
- Completing full HasForm auto-build from fillable/casts/attributes (still deferred; see make-resources follow-ups).
- A CMS-specific `HasForm` override (not needed while behaviour is generic Core).

## Decisions locked

| Topic | Decision |
|---|---|
| Placement | Core `HasForm` only |
| Gate | Apply Entity/Preset UI only when the resource model uses `HasDynamicContents` (directly or via `HasTranslatedDynamicContents`) — same idea as CMS `HasTable` |
| Persist | Only `presettable_id` from this wiring |
| Entity select | UI filter only (`dehydrated(false)`); never the save key |
| Preset select | UI filter (`live`); options scoped to selected entity |
| Resolve | On preset change (and on hydrate for edit), set `presettable_id` from `Preset::activePresettable()` |
| Missing active | Fail validation / clear error — do not create a snapshot |
| Generated form fields | Resource Form schemas must not keep Filament-generated `entity_id` / broken `relationship('entity')` selects for dynamic models; trait owns that UX |
| Preset model forms | Unchanged: `Preset` itself still uses real `entity()` BelongsTo |

## Domain facts (why C)

- Domain rows (`Content`, `Contributor`, `Category`, …) store `entity_id` + `presettable_id`.
- There is **no** `preset_id` on the domain row and **no** composite FK `(entity, preset)` on the domain table.
- `entity` / `preset` on dynamic models are **Attribute accessors** via `presettable`, not Eloquent relations — Filament `->relationship('entity')` correctly fails.
- Setting `presettable_id` syncs `entity_id` in `HasDynamicContents::setAttribute`.
- Presettables are created by preset/field versioning (`PresetVersioningService`), not when saving domain content.

## Architecture

### Detection

```php
$model = $schema->getModel();
// Reflect instance if needed
$uses_dynamic = class_uses_trait($model, HasDynamicContents::class);
```

If false → `configureForm` does nothing for entity/preset (other future HasForm wiring may still run).

If true → resolve `$model::getEntityType()` and inject the cascade fields.

### Injected fields (conceptual)

1. `Select::make('_entity_id')` (or equivalent non-column name)
   - Options: available entities for `getEntityType()`
   - `dehydrated(false)`, `live()`
   - On change: clear preset + `presettable_id`
2. `Select::make('_preset_id')`
   - Options: presets for selected entity
   - `dehydrated(false)`, `live()`, disabled until entity chosen
   - On change: resolve `activePresettable()` → set `presettable_id`
3. Hidden / dehydrated `presettable_id` (required for dynamic models)
   - Not shown as a raw relationship select on `presettables.name` (column does not exist)

### Edit hydrate

- From record `presettable` (or `presettable_id`): fill UI entity + preset selects.
- Keep existing `presettable_id` until the user changes entity/preset.
- Changing entity/preset re-resolves to the **active** presettable (may bump an outdated row forward — acceptable for this iteration; migration tooling remains separate).

### Interaction with generated Form schema classes

Today several CMS `*Form` classes still contain Filament `--generate` leftovers:

- `Select::make('entity_id')->relationship('entity', 'name')` — invalid
- `Select::make('presettable_id')->relationship('presettable', 'name')` — wrong title column; redundant once trait owns it

Cleanup in the same implementation slice: remove those selects from dynamic-content CMS forms so they are not duplicated beside HasForm injection.

`PresetForm` keeps its real `entity` relationship select.

### Testing

- Feature: `HasForm` on a model **without** `HasDynamicContents` → no entity/preset fields injected.
- Feature: model **with** `HasDynamicContents` → entity + preset UI present; `presettable_id` set when preset chosen; entity/preset not dehydrated.
- Feature: missing active presettable → validation fails / state not saved with null silently.
- Regression: `Preset` / non-dynamic resources unaffected.

## Follow-ups (out of scope)

- Optional UX to keep sticky historical presettable on edit until explicit migrate.
- Auto-building remaining form body from metadata.
- CMS HasForm specialization if a module later needs different cascade UX.

## Implementation sketch (for planning)

1. Extend Core `HasForm::configureForm` with dynamic-contents gate + field injection.
2. Add Core Pest coverage (harness / stub model as needed).
3. Strip invalid entity/presettable Selects from CMS dynamic `*Form` schemas.
4. Run pint + targeted tests; update Core RAG only if operator-facing form behaviour is worth documenting (likely yes, short note under Filament/dynamic contents).
