<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(TestCase::class)->in('../Modules/*/tests/Feature');
uses(TestCase::class)->in('../Modules/*/tests/Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidContent', function () {
    return $this->toBeInstanceOf(\Modules\Cms\Models\Content::class)
        ->and($this->value)->toHaveKey('entity_id')
        ->and($this->value)->toHaveKey('preset_id');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

beforeAll(function () {
    // Prepare something once before any of this file's tests run...
});

afterAll(function () {
    // Clean testing data after all tests run...
});

// function createTestEntity(string $name = 'article'): \Modules\Cms\Models\Entity
// {
//     return \Modules\Cms\Models\Entity::factory()->create([
//         'name' => $name,
//         'slug' => $name
//     ]);
// }

// function createTestPreset(\Modules\Cms\Models\Entity $entity): \Modules\Cms\Models\Preset
// {
//     return \Modules\Cms\Models\Preset::factory()->create([
//         'name' => "Default {$entity->name}",
//         'entity_id' => $entity->id
//     ]);
// }