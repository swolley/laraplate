<?php

namespace Modules\Cms\Database\Factories;

use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cms\Casts\FieldType;
use Modules\Cms\Models\Author;
use Modules\Cms\Models\Content;
use Modules\Cms\Models\Field;
use Modules\Cms\Models\Preset;
use Modules\Cms\Models\Tag;

class ContentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Cms\Models\Content::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $entity = Entity::inRandomOrder()->first();
        $valid_from = fake()->boolean() ? now()->addDays(fake()->numberBetween(-10, 10)) : null;
        $valid_to = $valid_from && fake()->boolean() ? $valid_from->addDays(fake()->numberBetween(-10, 10)) : null;
        
        return [
            'entity_id' => $entity->id,
            'valid_from' => $valid_from,
            'valid_to' => $valid_to,
        ];
    }
    
    public function configure(): static
    {
        return $this->afterMaking(function (Content $content) {
            $preset = Preset::where('entity_id', $content->entity_id)->inRandomOrder()->first();
            $content->components = $preset->fields->mapWithKeys(function (Field $field) {
                $value = $field->default;
                if ($field->required || fake()->boolean()) {
                    switch ($field->type) {
                        case FieldType::TEXTAREA:
                            $value = fake()->paragraphs(fake()->numberBetween(1, 3), true);
                            break;
                        case FieldType::TEXT:
                            $value = fake()->text(fake()->numberBetween(100, 255));
                            break;
                        case FieldType::NUMBER:
                            $value = fake()->randomNumber();
                            break;
                        default:
                            $value = $field->default;
                    }
                }
    
                return [$field->name => $value];
            })->toArray();
        })->afterCreating(function (Content $content) {
            $authors = Author::inRandomOrder()->limit(fake()->numberBetween(1, 3))->get();
            $content->authors()->attach($authors->isNotEmpty() ? $authors->pluck('id') : Author::factory()->count(rand(1, 3))->create());

            $categories = Category::inRandomOrder()->limit(fake()->numberBetween(1, 2))->get();
            $content->categories()->attach($categories->isNotEmpty() ? $categories->pluck('id') : Category::factory()->count(rand(1, 3))->create());
            
            $tags = Tag::inRandomOrder()->limit(fake()->numberBetween(1, 5))->get();
            $content->tags()->attach($tags->isNotEmpty() ? $tags->pluck('id') : Tag::factory()->count(rand(1, 3))->create());
        });
    }
}

