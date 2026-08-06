# Core Versioned Relation Membership — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture and read back the membership of a versioned `reference` relation as normalized version rows inside a version set, activating the dormant `relation_path`/`subject_key` columns — the foundation of the aggregate membership vector.

**Architecture:** A model declares its versioned relations and their ownership through a `HasVersionedRelations` trait (no automatic Eloquent discovery). For milestone 1, membership mutations go through explicit versioned methods (`attachVersioned`/`detachVersioned`) that open one version set per call and write one version row per affected subject, reusing the existing `VersionSetManager` → `VersionWriter` → `VersionChange` path with `relationPath`/`subjectKey`/`contents` populated and `change_type` = `Created` (link added) or `Deleted` (link removed). Membership at a revision is reconstructed by replaying those per-subject events by set sequence. Transparent `BelongsToMany` adapters, owned children (`subject_version_id`), restore, and shared-subject `uuid` are deferred to follow-up plans.

**Tech Stack:** PHP 8.5, Laravel 12, `overtrue/laravel-versionable`, Pest. Module: `Modules/Core`.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- Explicit param and return types; braces on all control structures; PHPDoc over inline comments.
- Core stays generic: no CMS class or table names in Core contracts. Tests use Core stubs under `Modules/Core/tests/Stubs/`.
- No new dependencies. No new base folders.
- Tests live in `Modules/Core/tests/`; no classes/traits/enums declared inside test files (use `tests/Stubs/`).
- Run tests with `php artisan test --compact <path>`; run `vendor/bin/pint --dirty` before each commit.
- Membership entry shape: `[ownership, subject identity (subject_key), pivot attributes (contents)]`. `subject_version_id` (owned children) is out of scope here.
- Revision coordinate is the version set (confirmed milestone-1 scope in `docs/superpowers/specs/2026-07-29-revision-centric-aggregate-history-design.md`).

---

## File Structure

- Create `Modules/Core/app/Versioning/Data/RelationDescriptor.php` — immutable descriptor of one versioned relation (relation path, ownership).
- Create `Modules/Core/app/Enums/RelationOwnership.php` — `Reference` | `Owned`.
- Create `Modules/Core/app/Models/Concerns/HasVersionedRelations.php` — trait: declare descriptors, `attachVersioned`/`detachVersioned`, and `versionedRelationMembership()`. (`syncVersioned` — set the whole membership as one revision — is deferred; see below.)
- Create `Modules/Core/tests/Stubs/Versioning/Relations/*` — a root stub with a `BelongsToMany` relation + the pivot subject stub.
- Create test files under `Modules/Core/tests/Integration/Versioning/`.

---

## Task 1: Relation descriptor + ownership enum

**Files:**
- Create: `Modules/Core/app/Enums/RelationOwnership.php`
- Create: `Modules/Core/app/Versioning/Data/RelationDescriptor.php`
- Test: `Modules/Core/tests/Unit/Versioning/RelationDescriptorTest.php`

**Interfaces:**
- Produces: `enum RelationOwnership: string { case Reference = 'reference'; case Owned = 'owned'; }`
- Produces: `final readonly class RelationDescriptor { public function __construct(public string $relation, public RelationOwnership $ownership) {} public function isOwned(): bool; }`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Enums\RelationOwnership;
use Modules\Core\Versioning\Data\RelationDescriptor;

it('describes a reference relation as not owned', function (): void {
    $descriptor = new RelationDescriptor('categories', RelationOwnership::Reference);

    expect($descriptor->relation)->toBe('categories')
        ->and($descriptor->ownership)->toBe(RelationOwnership::Reference)
        ->and($descriptor->isOwned())->toBeFalse();
});

it('describes an owned relation as owned', function (): void {
    $descriptor = new RelationDescriptor('blocks', RelationOwnership::Owned);

    expect($descriptor->isOwned())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Unit/Versioning/RelationDescriptorTest.php`
Expected: FAIL — `RelationOwnership` / `RelationDescriptor` not found.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum RelationOwnership: string
{
    case Reference = 'reference';
    case Owned = 'owned';
}
```

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

use Modules\Core\Enums\RelationOwnership;

final readonly class RelationDescriptor
{
    public function __construct(
        public string $relation,
        public RelationOwnership $ownership,
    ) {}

    public function isOwned(): bool
    {
        return $this->ownership === RelationOwnership::Owned;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Unit/Versioning/RelationDescriptorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Core/app/Enums/RelationOwnership.php Modules/Core/app/Versioning/Data/RelationDescriptor.php Modules/Core/tests/Unit/Versioning/RelationDescriptorTest.php
git commit -m "feat(core): add versioned relation descriptor and ownership enum"
```

---

## Task 2: Declare versioned relations on a model

**Files:**
- Create: `Modules/Core/app/Models/Concerns/HasVersionedRelations.php`
- Create: `Modules/Core/tests/Stubs/Versioning/Relations/VersionedRelationRoot.php`
- Create: `Modules/Core/tests/Stubs/Versioning/Relations/VersionedRelationSubject.php`
- Test: `Modules/Core/tests/Integration/Versioning/VersionedRelationsDeclarationTest.php`

**Interfaces:**
- Consumes: `RelationDescriptor`, `RelationOwnership` (Task 1).
- Produces: trait `HasVersionedRelations` with:
  - `protected function versionedRelations(): array` — returns `list<RelationDescriptor>`; default `[]`, overridden per model.
  - `public function versionedRelationDescriptor(string $relation): ?RelationDescriptor` — lookup by relation name.
- Produces stubs: `VersionedRelationRoot` (uses `HasVersions` + `HasVersionedRelations`, `versionStrategy = DIFF`, `categories(): BelongsToMany`, declares `categories` as `Reference`); `VersionedRelationSubject` (plain model). Pivot table `core_test_versioned_relation_pivot` with `root_id`, `subject_id`, `position`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Enums\RelationOwnership;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationRoot;

it('exposes the declared descriptor for a versioned relation', function (): void {
    $root = new VersionedRelationRoot();

    $descriptor = $root->versionedRelationDescriptor('categories');

    expect($descriptor)->not->toBeNull()
        ->and($descriptor->relation)->toBe('categories')
        ->and($descriptor->ownership)->toBe(RelationOwnership::Reference);
});

it('returns null for an undeclared relation', function (): void {
    expect((new VersionedRelationRoot())->versionedRelationDescriptor('unknown'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationsDeclarationTest.php`
Expected: FAIL — trait/stubs missing.

- [ ] **Step 3: Write minimal implementation**

Trait:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Modules\Core\Versioning\Data\RelationDescriptor;

trait HasVersionedRelations
{
    /**
     * @return list<RelationDescriptor>
     */
    protected function versionedRelations(): array
    {
        return [];
    }

    public function versionedRelationDescriptor(string $relation): ?RelationDescriptor
    {
        foreach ($this->versionedRelations() as $descriptor) {
            if ($descriptor->relation === $relation) {
                return $descriptor;
            }
        }

        return null;
    }
}
```

Stubs (`VersionedRelationRoot`): uses `HasVersions`, `HasVersionedRelations`; `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;`; `protected array $versionable = ['title'];`; `categories(): BelongsToMany` to `VersionedRelationSubject` on `core_test_versioned_relation_pivot` with pivot `position`; `versionedRelations()` returns `[new RelationDescriptor('categories', RelationOwnership::Reference)]`. `VersionedRelationSubject` is a plain `Model` with `$guarded = []`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationsDeclarationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Core/app/Models/Concerns/HasVersionedRelations.php Modules/Core/tests/Stubs/Versioning/Relations Modules/Core/tests/Integration/Versioning/VersionedRelationsDeclarationTest.php
git commit -m "feat(core): declare versioned relations via HasVersionedRelations"
```

---

## Task 3: Capture reference-relation membership as version rows

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasVersionedRelations.php`
- Test: `Modules/Core/tests/Integration/Versioning/VersionedRelationCaptureTest.php`

**Interfaces:**
- Consumes: `VersionSetManagerInterface`, `VersionSetRoot`, `VersionSetOptions`, `VersionWriterInterface`, `VersionChange`, `VersionChangeType`, `VersionStrategy`, descriptor lookup (Task 2).
- Produces on the trait:
  - `public function attachVersioned(string $relation, int|string $subjectId, array $pivot = []): void`
  - `public function detachVersioned(string $relation, int|string $subjectId): void`
  - Each opens one `VersionSetManager::run()` scope for the root and writes one `VersionChange` with `type` = `Created` (attach) / `Deleted` (detach), `relationPath` = `$relation`, `subjectKey` = `['id' => $subjectId]`, `contents` = `$pivot` (attach) / `[]` (detach), `strategy` = `SNAPSHOT`, after performing the underlying Eloquent `attach`/`detach`.

**Design notes for the implementer:**
- The underlying `$this->{$relation}()->attach($subjectId, $pivot)` runs inside the scope so business write and history commit together.
- A membership version row's `versionable` is the root itself; the subject is identified only by `subjectKey`. Use `VersionStrategy::SNAPSHOT` so each membership event is self-contained.
- Guard: if `versionedRelationDescriptor($relation)` is null, throw `InvalidArgumentException` (undeclared relation is not versioned).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationRoot;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationSubject;
use Overtrue\LaravelVersionable\VersionStrategy;

beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);

    Schema::create('core_test_versioned_relation_roots', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
    Schema::create('core_test_versioned_relation_subjects', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('core_test_versioned_relation_pivot', function (Blueprint $table): void {
        $table->unsignedBigInteger('root_id');
        $table->unsignedBigInteger('subject_id');
        $table->unsignedInteger('position')->default(0);
    });
});

afterEach(function (): void {
    Schema::dropIfExists('core_test_versioned_relation_pivot');
    Schema::dropIfExists('core_test_versioned_relation_subjects');
    Schema::dropIfExists('core_test_versioned_relation_roots');
});

it('records an attach as a Created membership version row', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $subject = VersionedRelationSubject::query()->create(['name' => 'News']);

    $root->attachVersioned('categories', $subject->getKey(), ['position' => 2]);

    $membership = Version::query()->latest('id')->firstOrFail();

    expect($root->categories()->count())->toBe(1)
        ->and($membership->change_type)->toBe(VersionChangeType::Created)
        ->and($membership->relation_path)->toBe('categories')
        ->and($membership->subject_key)->toBe(['id' => $subject->getKey()])
        ->and($membership->contents)->toBe(['position' => 2])
        ->and($membership->version_strategy)->toBe(VersionStrategy::SNAPSHOT);
});

it('records a detach as a Deleted membership version row', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $subject = VersionedRelationSubject::query()->create(['name' => 'News']);
    $root->attachVersioned('categories', $subject->getKey(), ['position' => 2]);

    $root->detachVersioned('categories', $subject->getKey());

    $membership = Version::query()->latest('id')->firstOrFail();

    expect($root->categories()->count())->toBe(0)
        ->and($membership->change_type)->toBe(VersionChangeType::Deleted)
        ->and($membership->relation_path)->toBe('categories')
        ->and($membership->subject_key)->toBe(['id' => $subject->getKey()]);
});

it('rejects an undeclared relation', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);

    expect(fn () => $root->attachVersioned('unknown', 1))->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationCaptureTest.php`
Expected: FAIL — `attachVersioned`/`detachVersioned` not defined.

- [ ] **Step 3: Write minimal implementation**

Add to `HasVersionedRelations` (imports: `InvalidArgumentException`, `VersionSetManagerInterface`, `VersionSetRoot`, `VersionSetOptions`, `VersionWriterInterface`, `VersionChange`, `VersionChangeType`, `VersionStrategy`):

```php
public function attachVersioned(string $relation, int|string $subjectId, array $pivot = []): void
{
    $this->recordRelationMembership($relation, $subjectId, VersionChangeType::Created, $pivot, function () use ($relation, $subjectId, $pivot): void {
        $this->{$relation}()->attach($subjectId, $pivot);
    });
}

public function detachVersioned(string $relation, int|string $subjectId): void
{
    $this->recordRelationMembership($relation, $subjectId, VersionChangeType::Deleted, [], function () use ($relation, $subjectId): void {
        $this->{$relation}()->detach($subjectId);
    });
}

private function recordRelationMembership(string $relation, int|string $subjectId, VersionChangeType $type, array $pivot, \Closure $mutation): void
{
    if ($this->versionedRelationDescriptor($relation) === null) {
        throw new \InvalidArgumentException("Relation '{$relation}' is not a declared versioned relation.");
    }

    app(VersionSetManagerInterface::class)->run(
        VersionSetRoot::forModel($this),
        function () use ($mutation, $type, $relation, $subjectId, $pivot): void {
            $mutation();

            resolve(VersionWriterInterface::class)->write(new VersionChange(
                model: $this,
                type: $type,
                originalContents: [],
                contents: $type === VersionChangeType::Created ? $pivot : [],
                strategy: VersionStrategy::SNAPSHOT,
                relationPath: $relation,
                subjectKey: ['id' => $subjectId],
            ));
        },
        new VersionSetOptions(actor: method_exists($this, 'getVersionUserId') ? $this->getVersionUserId() : null),
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationCaptureTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Core/app/Models/Concerns/HasVersionedRelations.php Modules/Core/tests/Integration/Versioning/VersionedRelationCaptureTest.php
git commit -m "feat(core): capture reference-relation membership as version rows"
```

---

## Task 4: Reconstruct membership at the latest revision

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasVersionedRelations.php`
- Test: `Modules/Core/tests/Integration/Versioning/VersionedRelationMembershipReadTest.php`

**Interfaces:**
- Produces on the trait:
  - `public function versionedRelationMembership(string $relation): array` — returns `list<array{id: int|string, pivot: array<string, mixed>}>`: current membership reconstructed by replaying membership version rows for that relation in ascending order (`version_set_id`, then `sequence`); a `Created` row adds/updates the subject, a `Deleted` row removes it. Keyed internally by `subject_key['id']`.

**Design notes:** query `$this->versions()->where('relation_path', $relation)->orderBy('version_set_id')->orderBy('sequence')->get()`, fold into a keyed map, return `array_values`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationRoot;
use Modules\Core\Tests\Stubs\Versioning\Relations\VersionedRelationSubject;

beforeEach(function (): void {
    config()->set('versionable.version_model', Version::class);
    Schema::create('core_test_versioned_relation_roots', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
    Schema::create('core_test_versioned_relation_subjects', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('core_test_versioned_relation_pivot', function (Blueprint $table): void {
        $table->unsignedBigInteger('root_id');
        $table->unsignedBigInteger('subject_id');
        $table->unsignedInteger('position')->default(0);
    });
});

afterEach(function (): void {
    Schema::dropIfExists('core_test_versioned_relation_pivot');
    Schema::dropIfExists('core_test_versioned_relation_subjects');
    Schema::dropIfExists('core_test_versioned_relation_roots');
});

it('reconstructs membership by replaying attach and detach events', function (): void {
    $root = VersionedRelationRoot::query()->create(['title' => 'First']);
    $a = VersionedRelationSubject::query()->create(['name' => 'A']);
    $b = VersionedRelationSubject::query()->create(['name' => 'B']);

    $root->attachVersioned('categories', $a->getKey(), ['position' => 1]);
    $root->attachVersioned('categories', $b->getKey(), ['position' => 2]);
    $root->detachVersioned('categories', $a->getKey());

    $membership = $root->versionedRelationMembership('categories');

    expect($membership)->toHaveCount(1)
        ->and($membership[0]['id'])->toBe($b->getKey())
        ->and($membership[0]['pivot'])->toBe(['position' => 2]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationMembershipReadTest.php`
Expected: FAIL — `versionedRelationMembership` not defined.

- [ ] **Step 3: Write minimal implementation**

```php
public function versionedRelationMembership(string $relation): array
{
    $events = $this->versions()
        ->where('relation_path', $relation)
        ->orderBy('version_set_id')
        ->orderBy('sequence')
        ->get();

    $membership = [];

    foreach ($events as $event) {
        $id = $event->subject_key['id'] ?? null;

        if ($id === null) {
            continue;
        }

        if ($event->change_type === VersionChangeType::Deleted) {
            unset($membership[$id]);

            continue;
        }

        $membership[$id] = ['id' => $id, 'pivot' => $event->contents ?? []];
    }

    return array_values($membership);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedRelationMembershipReadTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Core/app/Models/Concerns/HasVersionedRelations.php Modules/Core/tests/Integration/Versioning/VersionedRelationMembershipReadTest.php
git commit -m "feat(core): reconstruct versioned relation membership from history"
```

---

## Task 5: Update RAG documentation

**Files:**
- Modify: `Modules/Core/docs/rag/MODULE.md` (versioning section)

**Interfaces:** none (docs).

- [ ] **Step 1: Add a paragraph after the versioning-flow description**

Document: `HasVersionedRelations` declares versioned relations with `RelationOwnership` (`reference`/`owned`); `attachVersioned`/`detachVersioned` write one membership version row per subject inside a version set (`relation_path` + `subject_key` + `contents` = pivot, `change_type` `Created`/`Deleted`, `SNAPSHOT`); `versionedRelationMembership()` reconstructs current membership by replaying those events. Note that owned children (`subject_version_id`), restore, transparent relation adapters, and shared-subject `uuid` are not yet implemented.

- [ ] **Step 2: Commit**

```bash
git add Modules/Core/docs/rag/MODULE.md
git commit -m "docs(core): document versioned relation membership"
```

---

## Deferred to follow-up plans (NOT in this plan)

These require their own plan once the foundation above lands and informs the details:

- **Owned children** — migration adding nullable `subject_version_id` self-FK to `vend_versions`; capture that references the child's version; snapshot so deleted children are recreatable.
- **Restore / reconcile** — apply a target revision's membership: live re-link for `reference`; recreate/revert for `owned`; append-only, transactional, with shared-subject impact preview.
- **`syncVersioned`** — **[DONE 2026-08-06, commit `80b40c8`]** set the whole membership to a target list as a single revision (one version set wrapping the attach/detach diff), built on the idempotent attach/detach primitives.
- **Transparent adapters** — version-aware `BelongsToMany`/`MorphToMany` so ordinary `attach`/`sync` are captured without explicit methods (no-bypass hardening).
- **Shared-subject `uuid`** — stable identity on `reference` subjects (CMS categories/tags).
- **CMS pilot** — wire `HasVersionedRelations` onto CMS `Content` (separate, CMS-scoped plan; blocked until Core foundation is approved).

---

## Self-Review

- **Spec coverage:** implements the confirmed milestone-1 storage decision (normalized rows on `vend_versions.relation_path`/`subject_key`) and the reference-relation half of the membership vector. Owned/restore/uuid/adapters explicitly deferred above.
- **Type consistency:** `RelationOwnership`, `RelationDescriptor`, `versionedRelationDescriptor()`, `attachVersioned()`/`detachVersioned()`, `versionedRelationMembership()` used consistently across Tasks 1-4.
- **Placeholders:** none — every step carries real test or implementation code.
- **Risk to verify at execution:** `VersionWriter` persists `relation_path`/`subject_key` from `VersionChange` (confirmed in `VersionWriter::write()`); the `subject_key` cast round-trips as an array on the `Version` model — confirm the first time Task 3 runs, adjust the assertion to `['id' => (int) ...]` if the JSON cast returns an int-vs-string mismatch.
