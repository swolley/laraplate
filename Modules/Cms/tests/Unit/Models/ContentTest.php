<?php

use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Preset;
use Modules\Cms\Models\Content;
use Modules\Cms\Models\Category;
use Modules\Cms\Models\Field;

beforeEach(function () {
    // Crea le dipendenze necessarie
    $this->entity = Entity::factory()->create([
        'name' => 'article',
        'slug' => 'article'
    ]);

    $this->preset = Preset::factory()->create([
        'name' => 'Default Article',
        'entity_id' => $this->entity->id
    ]);

    $this->content = Content::factory()->create([
        'preset_id' => $this->preset->id,
        'entity_id' => $this->entity->id,
        'components' => ['title' => 'Test Article']
    ]);
});

test('can create content from entity', function () {
    $content = Content::makeFromEntity($this->entity);

    expect($content)
        ->toBeInstanceOf(Content::class)
        ->and($content->entity_id)->toBe($this->entity->id)
        ->and($content->preset_id)->toBe($this->preset->id);
});

test('can create content from entity id', function () {
    $content = Content::makeFromEntity($this->entity->id);

    expect($content)
        ->toBeInstanceOf(Content::class)
        ->and($content->entity_id)->toBe($this->entity->id);
});

test('can create content from entity name', function () {
    $content = Content::makeFromEntity('article');

    expect($content)
        ->toBeInstanceOf(Content::class)
        ->and($content->entity_id)->toBe($this->entity->id);
});

test('throws exception for invalid entity', function () {
    Content::makeFromEntity('invalid-entity');
})->throws(\InvalidArgumentException::class);

test('can associate categories with composite keys', function () {
    $category = Category::factory()->create([
        'entity_id' => $this->entity->id
    ]);

    $this->content->categories()->attach($category);

    expect($this->content->categories)->toContain($category);
    
    $this->assertDatabaseHas('categorizables', [
        'content_id' => $this->content->id,
        'entity_id' => $this->entity->id,
        'category_id' => $category->id
    ]);
});

test('merges component values with defaults', function () {
    $field = Field::factory()->create([
        'name' => 'description',
        'default' => 'Default description',
        'preset_id' => $this->preset->id
    ]);

    $content = Content::factory()->create([
        'preset_id' => $this->preset->id,
        'entity_id' => $this->entity->id,
        'components' => ['title' => 'Test']
    ]);

    $components = $content->components;
    
    expect($components)
        ->toHaveKey('title', 'Test')
        ->toHaveKey('description', 'Default description');
});

test('scopes published content correctly', function () {
    $publishedContent = Content::factory()->create([
        'preset_id' => $this->preset->id,
        'entity_id' => $this->entity->id,
        'valid_from' => now()->subDay(),
        'valid_to' => now()->addDay()
    ]);

    $expiredContent = Content::factory()->create([
        'preset_id' => $this->preset->id,
        'entity_id' => $this->entity->id,
        'valid_from' => now()->subDays(2),
        'valid_to' => now()->subDay()
    ]);

    $publishedContents = Content::published()->get();

    expect($publishedContents)
        ->toContain($publishedContent)
        ->not->toContain($expiredContent);
});

test('can create content with valid data', function (array $data) {
    $entity = createTestEntity();
    $preset = createTestPreset($entity);
    
    $content = Content::factory()->create([
        'preset_id' => $preset->id,
        'entity_id' => $entity->id,
        ...$data
    ]);

    expect($content)->toBeValidContent();
})->with('valid_contents'); 