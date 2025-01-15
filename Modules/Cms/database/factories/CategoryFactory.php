<?php

namespace Modules\Cms\Database\Factories;

use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Cms\Models\Category::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'entity_id' => Entity::inRandomOrder()->first()?->id,
            'parent_id' => fake()->boolean() ? Category::inRandomOrder()->first() : null,
            'description' => fake()->boolean() ? fake()->text() : null,
        ];
    }
}
