# CMS content provenance, references and AI-assistance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add origin metadata on `Content`, a sortable `ContentReference` bibliography (`cms_contents_references`), and per-translation `ai_assistance` enum on `ContentTranslation` for EU AI Act Article 50 disclosure.

**Architecture:** Three additive migrations (origin columns, `ai_assistance` column, references table). New `AiAssistance` enum and `ContentReference` model follow existing CMS/Core patterns (`CMSTables`, `MigrateUtils`, `SortableTrait`, `Core\Overrides\Model` soft deletes). `Content::references()` is a scoped `HasMany`; ordering is per `content_id` via `buildSortQuery()`.

**Tech stack:** Laravel 12, PHP 8.5, Pest, Spatie Eloquent Sortable, `Modules\Core\Overrides\Model`.

**Spec:** `docs/superpowers/specs/2026-07-02-cms-content-provenance-ai-assistance-design.md`

---

## File map

| File | Action | Responsibility |
|------|--------|----------------|
| `Modules/CMS/app/Enums/CMSTables.php` | Modify | Add `ContentsReferences` case |
| `Modules/CMS/app/Enums/AiAssistance.php` | Create | Backed enum for translation AI disclosure |
| `Modules/CMS/database/migrations/2026_07_02_100000_add_origin_columns_to_cms_contents_table.php` | Create | `origin_label`, `origin_url` |
| `Modules/CMS/database/migrations/2026_07_02_100001_add_ai_assistance_to_cms_contents_translations_table.php` | Create | `ai_assistance` column |
| `Modules/CMS/database/migrations/2026_07_02_100002_create_cms_contents_references_table.php` | Create | References child table |
| `Modules/CMS/app/Models/ContentReference.php` | Create | Bibliography row model |
| `Modules/CMS/database/factories/ContentReferenceFactory.php` | Create | Test factory |
| `Modules/CMS/app/Models/Content.php` | Modify | `$fillable`, `references()`, `getRules()` |
| `Modules/CMS/app/Models/Translations/ContentTranslation.php` | Modify | `ai_assistance` fillable/cast/default |
| `Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php` | Create | Enum cases |
| `Modules/CMS/tests/Unit/Models/ContentReferenceTest.php` | Create | Model structure + `buildSortQuery` |
| `Modules/CMS/tests/Feature/Models/ContentOriginTest.php` | Create | Origin persistence + validation |
| `Modules/CMS/tests/Feature/Models/ContentReferenceTest.php` | Create | Relation, ordering, cascade, soft delete |
| `Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php` | Create | Enum cast, default, filtering |

---

### Task 1: `CMSTables` + `AiAssistance` enum

**Files:**
- Modify: `Modules/CMS/app/Enums/CMSTables.php`
- Create: `Modules/CMS/app/Enums/AiAssistance.php`
- Create: `Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\CMS\Enums\AiAssistance;

it('defines all ai assistance cases', function (): void {
    expect(AiAssistance::cases())->toHaveCount(5)
        ->and(AiAssistance::None->value)->toBe('none')
        ->and(AiAssistance::Generated->value)->toBe('generated')
        ->and(AiAssistance::Translated->value)->toBe('translated')
        ->and(AiAssistance::Edited->value)->toBe('edited')
        ->and(AiAssistance::Summarized->value)->toBe('summarized');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php`
Expected: FAIL — class `AiAssistance` not found

- [ ] **Step 3: Add enum case to `CMSTables`**

In `Modules/CMS/app/Enums/CMSTables.php`, after `ContentRatings`:

```php
case ContentsReferences = 'cms_contents_references';
```

- [ ] **Step 4: Create `AiAssistance` enum**

```php
<?php

declare(strict_types=1);

namespace Modules\CMS\Enums;

enum AiAssistance: string
{
    case None = 'none';
    case Generated = 'generated';
    case Translated = 'translated';
    case Edited = 'edited';
    case Summarized = 'summarized';
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add Modules/CMS/app/Enums/CMSTables.php Modules/CMS/app/Enums/AiAssistance.php Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php
git commit -m "feat(cms): add AiAssistance enum and ContentsReferences table constant"
```

---

### Task 2: Origin columns migration + `Content` rules

**Files:**
- Create: `Modules/CMS/database/migrations/2026_07_02_100000_add_origin_columns_to_cms_contents_table.php`
- Modify: `Modules/CMS/app/Models/Content.php`
- Create: `Modules/CMS/tests/Feature/Models/ContentOriginTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\CMSTables;
use Modules\CMS\Models\Content;
use Modules\CMS\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! Schema::hasTable(CMSTables::Contents->value)) {
        $this->markTestSkipped('CMS contents table not migrated.');
    }

    setupCMSEntities();
});

it('persists origin label and url on content', function (): void {
    $content = Content::factory()->create([
        'origin_label' => 'Repubblica',
        'origin_url' => 'https://www.repubblica.it/example',
    ]);

    $content->refresh();

    expect($content->origin_label)->toBe('Repubblica')
        ->and($content->origin_url)->toBe('https://www.repubblica.it/example');
});

it('allows nullable origin fields', function (): void {
    $content = Content::factory()->create([
        'origin_label' => null,
        'origin_url' => null,
    ]);

    expect($content->origin_label)->toBeNull()
        ->and($content->origin_url)->toBeNull();
});

it('validates origin url format on create', function (): void {
    $content = Content::factory()->make([
        'origin_url' => 'not-a-url',
    ]);

    expect(fn () => $content->validateWithRules('create'))->toThrow(Illuminate\Validation\ValidationException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentOriginTest.php`
Expected: FAIL — unknown columns or mass-assignment / validation missing

- [ ] **Step 3: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\CMSTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CMSTables::Contents->value;

        Schema::table($table_name, static function (Blueprint $table): void {
            $table->string('origin_label')->nullable()->after('shared_components')
                ->comment('Human-readable name of the external origin source');
            $table->string('origin_url', 2048)->nullable()->after('origin_label')
                ->comment('Link to the external origin source');
        });
    }

    public function down(): void
    {
        Schema::table(CMSTables::Contents->value, static function (Blueprint $table): void {
            $table->dropColumn(['origin_label', 'origin_url']);
        });
    }
};
```

- [ ] **Step 4: Run migration**

Run: `php artisan migrate --path=Modules/CMS/database/migrations/2026_07_02_100000_add_origin_columns_to_cms_contents_table.php --no-interaction`

- [ ] **Step 5: Update `Content` model**

Add near other property declarations in `Modules/CMS/app/Models/Content.php`:

```php
/**
 * @var list<string>
 */
protected $fillable = [
    'origin_label',
    'origin_url',
];
```

Extend `getRules()` create/update arrays:

```php
'origin_label' => 'sometimes|nullable|string|max:255',
'origin_url' => 'sometimes|nullable|url|max:2048',
```

Add PHPDoc properties on the class docblock:

```php
 * @property string|null $origin_label
 * @property string|null $origin_url
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentOriginTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add Modules/CMS/database/migrations/2026_07_02_100000_add_origin_columns_to_cms_contents_table.php Modules/CMS/app/Models/Content.php Modules/CMS/tests/Feature/Models/ContentOriginTest.php
git commit -m "feat(cms): add origin columns on contents"
```

---

### Task 3: `ai_assistance` migration + `ContentTranslation` model

**Files:**
- Create: `Modules/CMS/database/migrations/2026_07_02_100001_add_ai_assistance_to_cms_contents_translations_table.php`
- Modify: `Modules/CMS/app/Models/Translations/ContentTranslation.php`
- Modify: `Modules/CMS/app/Models/Content.php` (`getRules()` translation rules)
- Create: `Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\AiAssistance;
use Modules\CMS\Enums\CMSTables;
use Modules\CMS\Models\Content;
use Modules\CMS\Models\Translations\ContentTranslation;
use Modules\CMS\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! Schema::hasColumn(CMSTables::ContentsTranslations->value, 'ai_assistance')) {
        $this->markTestSkipped('ai_assistance column not migrated yet.');
    }

    setupCMSEntities();
});

it('defaults ai assistance to none', function (): void {
    $content = Content::factory()->create();
    $locale = (string) config('app.locale');

    $content->setTranslation($locale, [
        'title' => 'Test',
        'slug' => 'test',
        'components' => [],
    ]);
    $content->save();

    $translation = ContentTranslation::query()
        ->where('content_id', $content->id)
        ->where('locale', $locale)
        ->first();

    expect($translation)->toBeInstanceOf(ContentTranslation::class)
        ->and($translation->ai_assistance)->toBe(AiAssistance::None);
});

it('casts ai assistance enum on translation', function (): void {
    $content = Content::factory()->create();
    $locale = (string) config('app.locale');

    $content->setTranslation($locale, [
        'title' => 'Test',
        'slug' => 'test',
        'components' => [],
        'ai_assistance' => AiAssistance::Translated,
    ]);
    $content->save();

    $translation = ContentTranslation::query()
        ->where('content_id', $content->id)
        ->where('locale', $locale)
        ->first();

    expect($translation->ai_assistance)->toBe(AiAssistance::Translated);
});

it('filters translations by ai assistance', function (): void {
    $content = Content::factory()->create();
    $locale = (string) config('app.locale');

    $content->setTranslation($locale, [
        'title' => 'AI article',
        'slug' => 'ai-article',
        'components' => [],
        'ai_assistance' => AiAssistance::Generated,
    ]);
    $content->save();

    $matches = ContentTranslation::query()
        ->where('ai_assistance', AiAssistance::Generated->value)
        ->where('content_id', $content->id)
        ->count();

    expect($matches)->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php`
Expected: SKIP or FAIL

- [ ] **Step 3: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\CMSTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CMSTables::ContentsTranslations->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->string('ai_assistance', 32)->nullable(false)->default('none')
                ->after('components')
                ->index("{$table_name}_ai_assistance_IDX")
                ->comment('How AI assisted this locale-specific version');
        });
    }

    public function down(): void
    {
        Schema::table(CMSTables::ContentsTranslations->value, static function (Blueprint $table): void {
            $table->dropIndex(CMSTables::ContentsTranslations->value . '_ai_assistance_IDX');
            $table->dropColumn('ai_assistance');
        });
    }
};
```

- [ ] **Step 4: Run migration**

Run: `php artisan migrate --path=Modules/CMS/database/migrations/2026_07_02_100001_add_ai_assistance_to_cms_contents_translations_table.php --no-interaction`

- [ ] **Step 5: Update `ContentTranslation`**

Add import: `use Modules\CMS\Enums\AiAssistance;`

Update `$fillable`:

```php
protected $fillable = [
    'content_id',
    'locale',
    'title',
    'slug',
    'components',
    'ai_assistance',
];
```

Update `$attributes`:

```php
protected $attributes = [
    'components' => '[]',
    'ai_assistance' => 'none',
];
```

Update `casts()`:

```php
protected function casts(): array
{
    return [
        'components' => 'json',
        'ai_assistance' => AiAssistance::class,
    ];
}
```

Add PHPDoc: `@property AiAssistance $ai_assistance`

- [ ] **Step 6: Extend `Content::getRules()` translation rules**

In both `create` and `update` rule arrays:

```php
'translations.*.ai_assistance' => 'sometimes|string|in:none,generated,translated,edited,summarized',
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add Modules/CMS/database/migrations/2026_07_02_100001_add_ai_assistance_to_cms_contents_translations_table.php Modules/CMS/app/Models/Translations/ContentTranslation.php Modules/CMS/app/Models/Content.php Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php
git commit -m "feat(cms): add ai_assistance enum column on content translations"
```

---

### Task 4: `cms_contents_references` migration

**Files:**
- Create: `Modules/CMS/database/migrations/2026_07_02_100002_create_cms_contents_references_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\CMSTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CMSTables::ContentsReferences->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('content_id')->nullable(false)
                ->constrained(CMSTables::Contents->value, 'id', "{$table_name}_content_id_FK")
                ->cascadeOnDelete()
                ->comment('The content that the reference belongs to');
            $table->string('label')->nullable(false)->comment('Human-readable name of the source');
            $table->string('url', 2048)->nullable()->comment('Optional link to the source');
            $table->integer('order_column')->nullable(false)->default(0)
                ->index("{$table_name}_order_column_IDX")
                ->comment('Display order of the reference');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['content_id', 'order_column'], "{$table_name}_content_order_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CMSTables::ContentsReferences->value);
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate --path=Modules/CMS/database/migrations/2026_07_02_100002_create_cms_contents_references_table.php --no-interaction`

- [ ] **Step 3: Commit**

```bash
git add Modules/CMS/database/migrations/2026_07_02_100002_create_cms_contents_references_table.php
git commit -m "feat(cms): create cms_contents_references table"
```

---

### Task 5: `ContentReference` model + factory

**Files:**
- Create: `Modules/CMS/app/Models/ContentReference.php`
- Create: `Modules/CMS/database/factories/ContentReferenceFactory.php`
- Create: `Modules/CMS/tests/Unit/Models/ContentReferenceTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Modules\CMS\Enums\CMSTables;
use Modules\CMS\Models\ContentReference;
use ReflectionClass;

it('content reference model has correct structure', function (): void {
    $reflection = new ReflectionClass(ContentReference::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('protected $fillable')
        ->and($source)->toContain('protected $hidden')
        ->and($source)->toContain(CMSTables::ContentsReferences->value);
});

it('content reference model uses sortable and soft delete traits', function (): void {
    $traits = array_values(class_uses_recursive(ContentReference::class));

    expect($traits)->toContain(Modules\Core\Models\Concerns\SortableTrait::class)
        ->and($traits)->toContain(Modules\Core\SoftDeletes\SoftDeletes::class);
});

it('scopes build sort query by content id', function (): void {
    $reference = new ContentReference(['content_id' => 42]);
    $query = $reference->buildSortQuery();

    expect($query)->toBeInstanceOf(Builder::class);

    $sql = $query->toSql();
    expect($sql)->toContain('content_id');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/CMS/tests/Unit/Models/ContentReferenceTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Create model**

```php
<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CMS\Database\Factories\ContentReferenceFactory;
use Modules\CMS\Enums\CMSTables;
use Modules\Core\Models\Concerns\SortableTrait;
use Modules\Core\Overrides\Model;
use Override;
use Spatie\EloquentSortable\Sortable;

/**
 * @property int|string $id
 * @property int $content_id
 * @property string $label
 * @property string|null $url
 * @property int $order_column
 * @mixin \Eloquent
 */
final class ContentReference extends Model implements Sortable
{
    use SortableTrait;

    /**
     * @var string
     */
    #[Override]
    protected $table = CMSTables::ContentsReferences->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'content_id',
        'label',
        'url',
        'order_column',
    ];

    /**
     * @var list<string>
     */
    #[Override]
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * @return Builder<static>
     */
    public function buildSortQuery(): Builder
    {
        return static::query()->where('content_id', $this->content_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'content_id' => ['required', 'integer', 'exists:' . CMSTables::Contents->value . ',id'],
            'label' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'order_column' => ['sometimes', 'integer', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'content_id' => ['sometimes', 'integer', 'exists:' . CMSTables::Contents->value . ',id'],
            'label' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'order_column' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $rules;
    }

    #[Override]
    protected static function newFactory(): ContentReferenceFactory
    {
        return ContentReferenceFactory::new();
    }
}
```

- [ ] **Step 4: Create factory**

```php
<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Factories;

use Modules\CMS\Models\Content;
use Modules\CMS\Models\ContentReference;
use Modules\Core\Overrides\Factory;
use Override;

/**
 * @extends Factory<ContentReference>
 */
final class ContentReferenceFactory extends Factory
{
    #[Override]
    protected $model = ContentReference::class;

    #[Override]
    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'label' => fake()->company(),
            'url' => fake()->optional()->url(),
            'order_column' => 0,
        ];
    }

    public function withoutUrl(): static
    {
        return $this->state(fn (): array => [
            'url' => null,
        ]);
    }
}
```

- [ ] **Step 5: Run unit test to verify it passes**

Run: `php artisan test --compact Modules/CMS/tests/Unit/Models/ContentReferenceTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add Modules/CMS/app/Models/ContentReference.php Modules/CMS/database/factories/ContentReferenceFactory.php Modules/CMS/tests/Unit/Models/ContentReferenceTest.php
git commit -m "feat(cms): add ContentReference model and factory"
```

---

### Task 6: `Content::references()` relation + feature tests

**Files:**
- Modify: `Modules/CMS/app/Models/Content.php`
- Create: `Modules/CMS/tests/Feature/Models/ContentReferenceTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Enums\CMSTables;
use Modules\CMS\Models\Content;
use Modules\CMS\Models\ContentReference;
use Modules\CMS\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! Schema::hasTable(CMSTables::ContentsReferences->value)) {
        $this->markTestSkipped('cms_contents_references table not migrated.');
    }

    setupCMSEntities();
    $this->content = Content::factory()->create();
});

it('creates references through content relation', function (): void {
    $reference = $this->content->references()->create([
        'label' => 'Wikipedia',
        'url' => 'https://en.wikipedia.org/wiki/Example',
    ]);

    expect($reference)->toBeInstanceOf(ContentReference::class)
        ->and($this->content->references)->toHaveCount(1)
        ->and($this->content->references->first()->label)->toBe('Wikipedia');
});

it('orders references per content independently', function (): void {
    $otherContent = Content::factory()->create();

    $first = $this->content->references()->create(['label' => 'First', 'order_column' => 1]);
    $second = $this->content->references()->create(['label' => 'Second', 'order_column' => 2]);
    $otherContent->references()->create(['label' => 'Other', 'order_column' => 1]);

    $ordered = ContentReference::query()
        ->where('content_id', $this->content->id)
        ->ordered()
        ->pluck('label')
        ->all();

    expect($ordered)->toBe(['First', 'Second']);
});

it('cascades delete when content is deleted', function (): void {
    $reference = ContentReference::factory()->create(['content_id' => $this->content->id]);

    $this->content->delete();

    expect(ContentReference::query()->whereKey($reference->id)->exists())->toBeFalse();
});

it('soft deletes a reference', function (): void {
    $reference = ContentReference::factory()->create(['content_id' => $this->content->id]);

    $reference->delete();

    expect($reference->trashed())->toBeTrue()
        ->and(ContentReference::query()->whereKey($reference->id)->exists())->toBeFalse()
        ->and(ContentReference::withTrashed()->whereKey($reference->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentReferenceTest.php`
Expected: FAIL — `references()` missing

- [ ] **Step 3: Add relation to `Content`**

Add import: `use Modules\CMS\Models\ContentReference;` (if not same namespace, only `ContentReference` is fine since same namespace).

In `Modules/CMS/app/Models/Content.php`:

```php
/**
 * The external references (bibliography) cited by this content.
 *
 * @return HasMany<ContentReference, $this>
 */
public function references(): HasMany
{
    return $this->hasMany(ContentReference::class);
}
```

- [ ] **Step 4: Run feature test to verify it passes**

Run: `php artisan test --compact Modules/CMS/tests/Feature/Models/ContentReferenceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Modules/CMS/app/Models/Content.php Modules/CMS/tests/Feature/Models/ContentReferenceTest.php
git commit -m "feat(cms): add references relation on content"
```

---

### Task 7: Format, full test run, version note

**Files:**
- Modify: `docs/superpowers/plans/INDEX.md` (already updated when plan is saved)

- [ ] **Step 1: Run Pint on dirty files**

Run: `vendor/bin/pint --dirty`

- [ ] **Step 2: Run all new CMS tests**

Run: `php artisan test --compact Modules/CMS/tests/Unit/Enums/AiAssistanceTest.php Modules/CMS/tests/Unit/Models/ContentReferenceTest.php Modules/CMS/tests/Feature/Models/ContentOriginTest.php Modules/CMS/tests/Feature/Models/ContentReferenceTest.php Modules/CMS/tests/Feature/Models/ContentTranslationAiAssistanceTest.php`
Expected: all PASS

- [ ] **Step 3: Ask user about CMS version bump**

Per `.cursor/rules/08-versioning.mdc`, propose `composer version:minor` inside `Modules/CMS` (new backward-compatible feature). **Do not run without explicit user confirmation.**

- [ ] **Step 4: Final commit if Pint changed files**

```bash
git add -A
git commit -m "chore(cms): format content provenance and references changes"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `origin_label` / `origin_url` on `cms_contents` | Task 2 |
| `ai_assistance` enum on translations | Task 1, 3 |
| `cms_contents_references` table | Task 1, 4 |
| `CMSTables::ContentsReferences` | Task 1 |
| `ContentReference` model + `SortableTrait` + `buildSortQuery` | Task 5 |
| `Content::references()` | Task 6 |
| Soft delete + settings | Task 4 (MigrateUtils), Task 6 (soft delete test) |
| Validation rules | Task 2, 3, 5 |
| Pest tests | Tasks 1–6 |
| Pint + test run | Task 7 |
| Filament UI | Out of scope (spec non-goal) |
| Search indexing of references | Out of scope (deferred) |
