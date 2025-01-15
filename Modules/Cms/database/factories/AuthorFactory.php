<?php

namespace Modules\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Cms\Models\Author::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $user = fake()->boolean() ? user_class()::inRandomOrder()->first() : null;
        return [
            'name' => $user ? $user->name : fake()->name(),
            'public_email' => fake()->boolean() ? fake()->email() : null,
            'user_id' => $user?->id,
        ];
    }
}
