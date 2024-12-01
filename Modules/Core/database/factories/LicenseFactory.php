<?php

namespace Modules\Core\Database\Factories;

use Modules\Core\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = License::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => uuid_create(),
            'valid_from' => today(),
            'valid_to' => null,
        ];
    }
}
