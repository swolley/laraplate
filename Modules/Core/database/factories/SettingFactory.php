<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Support\Carbon;
use Modules\Core\Models\Setting;
use Faker\Extension\ExtensionNotFound;
use Modules\Core\Casts\SettingTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Contracts\Container\BindingResolutionException;

class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Setting>
     */
    protected $model = Setting::class;

    /**
     * @throws BindingResolutionException
     * @throws ExtensionNotFound
     * @return array<string, mixed>
     *
     */
    public function definition(): array
    {
        $type = fake()->randomElement(SettingTypeEnum::cases())->value;

        return [
            'name' => fake()->word(),
            'value' => match ($type) {
                SettingTypeEnum::BOOLEAN => fake()->boolean(),
                SettingTypeEnum::INTEGER => fake()->randomNumber(),
                SettingTypeEnum::FLOAT => fake()->randomFloat(),
                SettingTypeEnum::JSON => [],
                SettingTypeEnum::DATE => new Carbon(fake()->dateTime()),
                default => fake()->text(),
            },
            'encrypted' => fake()->boolean(),
            'choices' => $type === SettingTypeEnum::JSON ? fake()->words() : null,
            'type' => $type,
            'group_name' => fake()->word(),
            'description' => fake()->text(),
        ];
    }
}
