# HasForm Entity → Preset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Core `HasForm` injects Entity → Preset UI for `HasDynamicContents` models and persists only `presettable_id`.

**Architecture:** `configureForm(Schema $schema, ?array $components = null): Schema` prepends dehydrated entity/preset selects + hidden `presettable_id` when the schema model uses `HasDynamicContents`. Legacy `configureForm($schema)` before `->components()` remains a no-op for non-migrated forms.

**Tech Stack:** Laravel 12, Filament 5, Pest, Core `HasDynamicContents` / `Preset::activePresettable()`.

## Global Constraints

- Chat Italian; code/comments English.
- Persist only `presettable_id`; entity/preset selects `dehydrated(false)`.
- Never create presettables from the form.
- Gate: `class_uses_trait($model, HasDynamicContents::class)`.
- Conventional commits if committing (only when user asks).

---

### Task 1: Failing tests for HasForm dynamic wiring

**Files:**
- Modify: `Modules/Core/tests/Feature/Filament/UtilsTest.php`
- Create: `Modules/Core/tests/Feature/Filament/HasFormDynamicContentsTest.php`
- Modify: `Modules/Core/tests/Stubs/Filament/HasFormHarness.php`

- [ ] **Step 1: Update harness to new API**

Harness exposes `run(Schema $schema, ?array $components = null): Schema` calling `configureForm`.

- [ ] **Step 2: Replace permission smoke with non-dynamic assertion**

`User` model + `configureForm($schema, [Toggle::make('x')])` → components must not include `dynamic_entity_id` / `presettable_id`.

- [ ] **Step 3: Write CMS-backed dynamic test**

Use `Content` + `setupCMSEntities` pattern (same as CMS HasTable tests, or Core skip if CMS unavailable): assert component names include `dynamic_entity_id`, `dynamic_preset_id`, `presettable_id`; entity/preset dehydrated false; `presettable_id` present.

- [ ] **Step 4: Run tests — expect RED**

```bash
cd laraplate && php artisan test --compact Modules/Core/tests/Feature/Filament/UtilsTest.php Modules/Core/tests/Feature/Filament/HasFormDynamicContentsTest.php
```

---

### Task 2: Implement Core HasForm

**Files:**
- Modify: `Modules/Core/app/Filament/Utils/HasForm.php`

- [ ] **Step 1: Implement `configureForm`**

```php
protected static function configureForm(Schema $schema, ?array $components = null): Schema
{
    if ($components === null) {
        return $schema;
    }

    $prefix = self::dynamicEntityPresetComponents($schema);

    return $schema->components([...$prefix, ...$components]);
}
```

- [ ] **Step 2: Build Select Entity / Select Preset / Hidden presettable_id**

Resolve active via `Preset::activePresettable()`; clear on entity change; hydrate UI from record presettable on edit; required `presettable_id` when dynamic.

- [ ] **Step 3: Run tests — expect GREEN**

---

### Task 3: Migrate CMS dynamic forms + generator

**Files:**
- Modify: `Modules/CMS/.../ContentForm.php`, `CategoryForm.php`, `ContributorForm.php`
- Modify: `Modules/Core/app/Filament/Generators/LaraplateResourceFormSchemaClassGenerator.php`
- Modify: generator smoke test if body shape changes

- [ ] **Step 1: Content/Category/Contributor** — `return self::configureForm($schema, [...])` without `entity_id` / raw `presettable_id` relationship selects.

- [ ] **Step 2: Generator emits** `return self::configureForm($schema->components([...]));` or array form matching Task 2 API.

- [ ] **Step 3: Run Core Filament generator + HasForm tests**

---

### Task 4: Format and verify

- [ ] `vendor/bin/pint --dirty`
- [ ] Targeted Pest suite green
- [ ] Spec status note if needed (plan checkboxes)
