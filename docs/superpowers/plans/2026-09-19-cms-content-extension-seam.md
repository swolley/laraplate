# CMS Content Extension Seam — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give CMS a generic seam that lets another module attach a richer domain model to a `Content` — without CMS knowing that model and without extended rows leaking into generic CMS surfaces. Deliver it standalone, exercised by a **test-only stub extender**, so it ships and is proven before the first real consumer (`Modules/Ecommerce` `Product`) exists.

**Architecture:** A nullable `contents.extended_type` morph-alias column makes each content self-describing. A default global scope hides extended rows everywhere; an opt-in `withExtended()` scope plus a batched **upcast** returns the extender in place of the content (two queries per entity-scoped page, no N+1). The alias→class map lives in a dedicated in-memory `ContentExtenderRegistry` (not Laravel's global morph map), populated at boot. The extender owns its content's lifecycle (mandatory→symmetric cascade, optional→orphan) with a cascade guard. Search keeps one physical `contents` index the extender enriches with a nested, typed `extension` section; `extended_type` is a filterable attribute; CMS owns the index and composes each extender's mapping fragment.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, Scout + the Core search engines (Elasticsearch/Typesense/Database), Pest 4, PHPStan/Larastan, Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-cms-content-extension-seam-design.md`. Decisions are numbered C1–C18 there; tasks reference them.

**First consumer (out of scope here):** `docs/superpowers/specs/2026-09-17-ecommerce-module-design.md`.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`. Classes are `final` unless a sibling proves otherwise. Use `#[Override]` when overriding. Explicit param/return types.
- All new work lives in `Modules/CMS` (and its `tests/`), except the stub, which is **test-only** (`Modules/CMS/tests/Stubs/`, PSR-4 registered in the module's `composer.json` `autoload-dev`). No new runtime module.
- CMS must gain **no** reference to any concrete extender class. It knows only aliases and the `ExtendsContent` contract (C2, C4, C13).
- No new dependencies. Migrations use `MigrateUtils`. Follow the existing `Modules/CMS/app/Scopes/` and `Modules/Core/app/Search/` patterns.
- Run only the affected tests: `php artisan test --compact --filter=<Name>` from `laraplate/`. Do **not** run the full suite.
- Before finishing each task: `vendor/bin/pint --dirty --format agent` then `vendor/bin/phpstan analyse --memory-limit=2G` on the touched paths.
- Do not run destructive index commands against a real cluster; search tests use the Database/array engine or a disposable test index.

## File Structure

All paths relative to `Modules/CMS/` unless noted.

| File | Responsibility |
|------|----------------|
| `database/migrations/*_add_extended_type_to_contents_table.php` | The `extended_type` nullable, indexed column (+ partitioned variant if present) |
| `app/Scopes/HidesExtendedContent.php` | Global scope `whereNull('extended_type')` |
| `app/Content/ContentExtenderRegistry.php` | Alias→class registry (singleton), keyed by stable alias |
| `app/Contracts/ExtendsContent.php` | Contract: `content()`, `contentAlias()`, `searchableExtension()`, `searchableExtensionMapping()` |
| `app/Content/Concerns/ExtendsContentTrait.php` | Extender-side wiring: back-relation (scope removed), `$with`, temp holder + setter, `save()` override, lifecycle cascade |
| `app/Content/ContentExtensionResolver.php` | The batched upcast pipeline (fail-loud on unknown alias) |
| `app/Observers/ContentExtensionObserver.php` | Reverse lifecycle (cascade/orphan) + cascade guard |
| `Content.php` (edit) | `withExtended()` scope; `toSearchableArray()` `extension` section; searchable schema composition; `extended_type` excluded from versioning |
| `tests/Stubs/ContentExtension/*` | Test-only stub extender: model + migration + registration |
| `docs/` | Seam RAG doc |

---

## Task 1: `extended_type` column (C1, C3) — DONE

- [x] Write a migration adding `extended_type` to `contents`: nullable string, indexed. _Partitioned variant is `*.php.tmp` (disabled/experimental); its forward path clones via `CREATE TABLE ... (LIKE contents ...)` so it inherits the column — only its explicit rollback DDL would need it, reconciled if that migration is ever activated. Left untouched._
- [x] Add `extended_type` to `Content`'s `$fillable`/`$casts` only as far as needed; it is **not** mass-assignable by ordinary callers (it is set by the extender create-path — Task 3). _No change needed: leaving it out of `$fillable` keeps it guarded by default; the trait's `save()` sets it directly on the instance._
- [x] Migrate a fresh test DB; assert the column exists and defaults to `null`. _`tests/Feature/ContentExtension/ExtendedTypeColumnTest.php` (2 passed)._
- [x] `vendor/bin/pint --dirty`; PHPStan clean on the migration. _pint passed, phpstan ok._

## Task 2: `ContentExtenderRegistry` + `ExtendsContent` contract (C2, C7, C13) — DONE

- [x] Create `ExtendsContent` contract: `content(): BelongsTo`, `contentAlias(): string`, `searchableExtension(): array`, `searchableExtensionMapping(): array`. _`app/Contracts/ExtendsContent.php`; `content()` typed `BelongsTo<Content, Model>` (interface can't assert the implementer is a Model)._
- [x] Create `ContentExtenderRegistry` singleton: `register`, `resolve`, `has`, `aliases`. Keyed by the **stable alias**, never `entity_id`. Bind it as a singleton in `CMSServiceProvider`. _`app/Services/ContentExtenderRegistry.php`, bound in `register()`._
- [x] Empty-registry invariant: with nothing registered, every accessor is a safe no-op; a resolve of an unknown alias **fails loud** (used by C12). _Throws `InvalidArgumentException`, matching Core's `ModerationAdapterRegistry` precedent, to avoid a new `app/Exceptions` base folder._
- [x] Unit test: register/resolve/has; unknown alias throws; registry is a container singleton and starts empty. _`tests/Unit/ContentExtension/ContentExtenderRegistryTest.php` (4 passed), stub `tests/Stubs/ContentExtension/FakeContentExtender.php`; `Tests\Stubs` PSR-4 mapping added to the module `composer.json`._
- [x] Pint + PHPStan.

## Task 3: extender-side trait + the stub extender (C2, C6, C8, C15, C17) — DONE

- [x] Create `ExtendsContentTrait` for extenders: `content(): BelongsTo` on `content_id` **with `->withoutGlobalScope(HidesExtendedContent::class)`** (C8); appends `content` to `$with` via `initializeExtendsContentTrait()`; a `?Content $tempContent` holder + `setTempContent()`; a `save()` override that persists the staged content stamping `extended_type = $this->contentAlias()` and links `content_id`, in one `DB::transaction`. _`@phpstan-require-extends Model` + `@phpstan-require-implements ExtendsContent`. No boot registration helper: the consumer registers its alias directly (the stub test does)._
- [x] Enforce **one extender per content** (C15): the extender table's `content_id` is `unique` (the stub table declares it; consumers do the same).
- [x] Build the **test-only stub extender** under `tests/Stubs/ContentExtension/`: `StubExtendedThing` (plain Eloquent model + `SoftDeletes` + the trait, alias `cms.stub_extended`). _Table `cms_stub_extended_things` is created per test via `Schema::create` in `beforeEach` (the project's stub-table pattern), not a runtime migration; `Tests\Stubs` PSR-4 mapping added in Task 2. No Core-model ACL/validation noise._
- [x] `extended_type` immutability (C17): a normal content save never changes `extended_type`.
- [x] Feature test: creating a `StubExtendedThing` with a temp content persists both, links `content_id`, stamps `extended_type = 'cms.stub_extended'`; `$stub->content` resolves through a fresh load (C8). _`tests/Feature/ContentExtension/StubExtendedThingTest.php` (4 passed)._
- [x] Pint + PHPStan.

## Task 4: default-hide global scope + `withExtended()` (C3, C4, C16) — DONE (back-relation check with Task 3)

- [x] Create `HidesExtendedContent` global scope (`Modules/CMS/app/Scopes/`, following `CommentTranslationScope`) adding `whereNull('extended_type')`. Register it in `Content::booted()` alongside the existing `global_ordered` scope. _Executed before Task 3 so the extender trait can reference the scope class._
- [x] Add `Content::withExtended()` (a local scope, `#[Scope]`) that calls **`withoutGlobalScope(HidesExtendedContent::class)` only** — never `withoutGlobalScopes()` (C16).
- [x] Verify the back-relation from Task 3 now resolves (`$stub->content` non-null) because the trait removed the scope (C8). _Done in Task 3's `StubExtendedThingTest`._
- [x] Tests: default query never returns an extended row; `withExtended()` returns them as `Content`; a **soft-deleted** extended content stays excluded under `withExtended()` (C16). _`tests/Feature/ContentExtension/HidesExtendedContentTest.php` (4 passed); 29 existing content tests still green (the new global scope only hides `extended_type IS NOT NULL`)._
- [x] Pint + PHPStan.

## Task 5: the batched upcast (C5, C6, C12, C15) — DONE

- [x] Create `ContentExtensionResolver` (`resolve(Collection $contents): Collection`): group by `extended_type`; resolve each alias from the `ContentExtenderRegistry`; one `whereIn('content_id', $ids)` per alias — **`->without('content')`** so the page's content is not reloaded (keeps it one query per alias, not two); build `content_id => extender`; swap; `setRelation('content', $content)`; `null` rows stay `Content`; order preserved.
- [x] The base `Content` query is **never** mutated (C5): resolution operates on the fetched collection. _No macro added — callers use the resolver service; keeps PHPStan clean._
- [x] Fail-loud (C12): an unregistered alias throws the registry's `InvalidArgumentException`; a registered alias with no owner row throws `RuntimeException` (C15).
- [x] Tests (with the stub): an entity-scoped extended page upcasts to stub extenders with `content` pre-attached and **exactly one extender query** (asserted via query log); a mixed page returns extenders and plain contents in order; unknown alias and missing owner both fail loud. _`tests/Feature/ContentExtension/ContentExtensionResolverTest.php` (4 passed)._
- [x] Pint + PHPStan.

## Task 6: lifecycle — cascade both ways + guard (C9, C10)

- [ ] In `ExtendsContentTrait`, cascade extender → content: `deleting` (soft) soft-deletes the content, `forceDeleting` force-deletes it, `restoring` restores it — one transaction, guarded so it does not re-enter the reverse handler (C10).
- [ ] Create `ContentExtensionObserver` (or extend the existing content observer) for the reverse: a directly deleted content runs the extender's declared policy — **mandatory → symmetric cascade** (delete the extender), **optional → orphan** (`content_id` null, a channel-off hook, a signal), decided from the extender/contract. The stub exercises the **mandatory/symmetric** policy; add a second stub or a flag to exercise **optional/orphan**.
- [ ] Cascade guard (C10): a context flag set by the trait before it cascades makes the observer skip the reverse handler during the extender's own cascade, so the two directions never collide.
- [ ] Tests: extender soft/force/restore cascades to the content; a direct content delete cascades to a mandatory extender and orphans an optional one (order/stock-equivalent links untouched); the guard prevents double-firing.
- [ ] Pint + PHPStan.

## Task 7: search data — `extension` section + `extended_type` filterable (C11)

- [ ] `Content::toSearchableArray()`: when `extended_type` is set, ask the registry for the extender instance's `searchableExtension()` and merge it as a **nested, typed** object `extension: { type: <alias>, ... }` (never flat). Include `extended_type` as a top-level **filterable** attribute. Language-neutral fields flat, per-locale fields locale-keyed, as the extender declares (C18).
- [ ] `shouldBeSearchable()` stays true for extended rows (they are indexed); browsing hides them via the index filter, not by dropping them from the index (C11).
- [ ] Reindex trigger: document and wire the rule that an **extender** change re-pushes its content (`$extender->content->searchable()`); the stub does it via an observer, proving the pattern.
- [ ] **No ERP/external data here** — the `extension` section carries only the extender's own stable fields; any browse snapshot of external data is the consumer's bounded exception, not part of this seam.
- [ ] Tests (Database engine or disposable index): a generic search filtered `extended_type = null` returns no extended rows and no holes; an opt-in search (filter dropped) + upcast returns the stub extender for extended hits; an extender field change re-indexes the content.
- [ ] Pint + PHPStan.

## Task 8: search index ownership — mapping composition + scoped reindex (C14)

- [ ] When CMS builds `Content`'s searchable **schema** (the `FieldDefinition`/`IndexType` the model declares), compose the `extension` mapping by merging every registered extender's `searchableExtensionMapping()`, so index (re)creation maps the fields explicitly instead of falling to dynamic mapping. Keep Core's search engine agnostic (composition happens in CMS's schema, consumed by `ElasticsearchEngine::createIndex`).
- [ ] A module "reindex" is **document-scoped** — `Content::withExtended()->where('extended_type', $alias)->searchable()` — and never calls `deleteIndex`/`createIndex`; a module "unindex" clears/deletes only its documents. Provide the scoped helpers; a guard-rail test asserts no module path issues index-lifecycle commands against `contents`.
- [ ] Test: recreating the `contents` schema includes the stub's `extension` mapping fragment; a full content reindex restores the stub `extension` data (because `toSearchableArray()` pulls it); the scoped reindex touches only stub-extended documents.
- [ ] Pint + PHPStan.

## Task 9: documentation + final verification

- [ ] Write the seam RAG doc in `Modules/CMS/docs/` (concept, the column, registry, `withExtended`+upcast, lifecycle, search/index ownership, the two N+1-safe queries, how a module becomes a consumer). Update the CMS README if it enumerates capabilities.
- [ ] Run the full CMS suite plus the seam tests: `php artisan test --compact Modules/CMS`. Then `vendor/bin/pint Modules/CMS` and `vendor/bin/phpstan analyse --configuration=Modules/CMS/phpstan.neon --memory-limit=2G` (or the module's config).
- [ ] Add the `## Delivery status (date): ...` section and a `**Documented in:**` line naming the CMS doc (enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`). Tick every box that landed; record any divergence.
- [ ] Add the plan to `docs/superpowers/plans/INDEX.md`.

## Notes on scope

- The **create-path** is delivered only as far as the extender trait needs (setting `extended_type`/`content_id`); a general CMS create helper and `cms:import` handling of extended entities stay open (seam spec §9) and belong to the consumer/import slices.
- **Permissions on a mixed upcast list** and the **generic optional-content extender** remain open (seam spec §9); the seam supports both, the stub proves both lifecycle policies, and the first consumer (Ecommerce) uses mandatory content.
