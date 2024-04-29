<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Support\Carbon;
use Faker\Extension\ExtensionNotFound;
use Modules\Core\App\Casts\SettingTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Modules\Core\App\Models\Setting;

class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
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

        /** @psalm-suppress UnhandledMatchCondition */
        return [
            'name' => fake()->word(),
            'value' => match ($type) {
                SettingTypeEnum::BOOLEAN => fake()->boolean(),
                SettingTypeEnum::DATE => new Carbon(fake()->dateTime()),
                SettingTypeEnum::FLOAT => fake()->randomFloat(),
                SettingTypeEnum::INTEGER => fake()->randomNumber(),
                SettingTypeEnum::JSON => [],
                SettingTypeEnum::STRING => fake()->text(),
            },
            'encrypted' => fake()->boolean(),
            'choices' => $type === SettingTypeEnum::JSON ? fake()->words() : null,
            'type' => $type,
            'group_name' => fake()->word(),
            'description' => fake()->text(),
        ];
    }
}
