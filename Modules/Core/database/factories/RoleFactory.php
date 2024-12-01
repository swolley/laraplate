<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Role;

class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return (mixed|string)[]
     *
     * @psalm-return array{name: string, guard_name: mixed, description: string}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'guard_name' => fake()->randomElement(['web', 'api', null]),
            'description' => fake()->optional()->text(),
        ];
    }
}
