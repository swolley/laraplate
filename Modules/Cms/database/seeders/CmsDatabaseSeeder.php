<?php

declare(strict_types=1);

namespace Modules\Cms\Database\Seeders;

use Modules\Cms\Models\Field;
use Modules\Core\Models\Role;
use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Preset;
use Illuminate\Database\Seeder;
use Modules\Cms\Casts\FieldType;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Models\Permission;
use Modules\Core\Helpers\HasSeedersUtils;
use Illuminate\Database\Eloquent\Collection;

class CmsDatabaseSeeder extends Seeder
{
    use HasSeedersUtils;

    /**
     * @var Collection<string, Entity>
     */
    private Collection $entities;

    /**
     * @var Collection<string, Preset>
     */
    private Collection $presets;

    /**
     * @var Collection<string, Field>
     */
    private Collection $fields;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDefaultFields();
        $this->createDefaultEntities();
        $this->createDefaultRoles();
    }

    private function createDefaultFields(): void
    {
        $this->logOperation(Field::class);

        $this->fields = Field::query()->withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function () {
            $text_fields = ['kicker', 'title', 'subtitle'];
            foreach ($text_fields as $field) {
                if (!$this->fields->has($field)) {
                    $options = (object) ['max_length' => 255];
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::TEXT, 'options' => $options]));
                    $this->command->line("    - $field <fg=green>created</>");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $text_area_fields = ['short_content'];
            foreach ($text_area_fields as $field) {
                if (!$this->fields->has($field)) {
                    $options = (object) ['max_length' => 65535];
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::TEXTAREA, 'options' => $options]));
                    $this->command->line("    - $field <fg=green>created</>");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $json_fields = ['content'];
            foreach ($json_fields as $field) {
                if (!$this->fields->has($field)) {
                    $options = new \stdClass();
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::JSON, 'options' => $options]));
                    $this->command->line("    - $field <fg=green>created</>");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $date_fields = ['period_from', 'period_to'];
            foreach ($date_fields as $field) {
                if (!$this->fields->has($field)) {
                    $options = (object) ['format' => 'Y-m-d H:i:s'];
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::DATETIME, 'options' => $options]));
                    $this->command->line("    - $field <fg=green>created</>");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }
        });
    }

    private function createDefaultEntities(): void
    {
        $this->logOperation(Entity::class);


        $this->entities = Entity::query()->withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function () {
            $standard = 'standard';

            $entity_name = 'article';
            if (!$this->entities->has($entity_name)) {
                /** @var Entity $preset */
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                /** @var Preset $preset */
                $preset = $this->create(Preset::class, ['name' => $standard, 'entity_id' => $entity->id]);
                // required fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['title', 'content']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, true);
                }
                // optional fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['kicker', 'subtitle', 'short_content']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, false);
                }

                $this->command->line("    - $entity_name <fg=green>created</>");
            } else {
                $this->command->line("    - $entity_name already exists");
            }

            $entity_name = 'event';
            if (!$this->entities->has($entity_name)) {
                /** @var Entity $entity */
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                /** @var Preset $preset */
                $preset = $this->create(Preset::class, ['name' => $standard, 'entity_id' => $entity->id]);
                // required fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['title', 'content', 'period_from']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, true);
                }
                // optional fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['subtitle', 'short_content', 'period_to']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, false);
                }

                $this->command->line("    - $entity_name <fg=green>created</>");
            } else {
                $this->command->line("    - $entity_name already exists");
            }

            $entity_name = 'multimedia';
            if (!$this->entities->has($entity_name)) {
                /** @var Entity $entity */
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                /** @var Preset $preset */
                $preset = $this->create(Preset::class, ['name' => $standard, 'entity_id' => $entity->id]);
                // required fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['title', 'content']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, true);
                }
                // optional fields
                $fields = $this->fields->filter(fn($field) => in_array($field->name, ['subtitle', 'short_content']));
                foreach ($fields as $field) {
                    $this->assignFieldToPreset($preset, $field, false);
                }

                $this->command->line("    - $entity_name <fg=green>created</>");
            } else {
                $this->command->line("    - $entity_name already exists");
            }
        });
    }

    private function createDefaultRoles(): void
    {
        $this->logOperation(Role::class);

        $name = 'publisher';
        if (!Role::whereName($name)->exists()) {
            $role = $this->create(Role::class, ['name' => $name]);
            $role->givePermissionTo(Permission::where('name', 'like', '%.' . ActionEnum::APPROVE->value)->get());
            $this->command->line("    - $name <fg=green>created</>");
        } else {
            $this->command->line("    - $name already exists");
        }
    }

    private function assignFieldToPreset(Preset $preset, Field $field, bool $is_required): void
    {
        $pivotAttributes = ['is_required' => $is_required, 'default' => $this->getDefaultFieldValue($field, $is_required), 'preset_id' => $preset->id];
        $preset->fields()->attach($field->id, $pivotAttributes);
    }

    private function getDefaultFieldValue(Field $field, bool $is_required): mixed
    {
        return match ($field->type) {
            FieldType::SELECT && isset($field->options->multiple) && $field->options->multiple => [],
            FieldType::SWITCH => $is_required ? true : false,
            FieldType::CHECKBOX => [],
            default => null,
        };
    }
}
