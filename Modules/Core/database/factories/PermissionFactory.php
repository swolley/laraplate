<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $connection = fake()->randomElement(['default', mb_strtolower(fake()->word())]);
        $table = mb_strtolower(fake()->word());
        $action = fake()->randomElement(ActionEnum::cases())->value;

        return [
            'name' => "{$connection}.{$table}.{$action}",
            'guard_name' => fake()->randomElement(['web', 'api', null]),
            'connection_name' => $connection,
            'table_name' => $table,
        ];
    }
}
