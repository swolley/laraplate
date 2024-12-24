<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Seeders;

use Modules\CMS\Models\Field;
use Modules\CMS\Models\Entity;
use Illuminate\Database\Seeder;
use Modules\CMS\Casts\FieldType;
use Illuminate\Support\Facades\DB;
use Modules\Core\Helpers\HasSeedersUtils;
use Illuminate\Database\Eloquent\Collection;

class CMSDatabaseSeeder extends Seeder
{
    use HasSeedersUtils;

    private Collection $entities;
    private Collection $presets;
    private Collection $fields;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDefaultFields();
        $this->createDefaultEntities();
    }

    private function createDefaultFields(): void
    {
        $this->logOperation(Field::class);

        $this->fields = Field::withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function () {
            $text_fields = ['kicker', 'title', 'subtitle'];
            foreach ($text_fields as $field) {
                if (!$this->fields->has($field)) {
                    $options = (object) ['max_length' => 255];
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::TEXT, 'options' => $options]));
                    $this->command->line("    - $field created");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $text_area_fields = ['short_content'];
            foreach ($text_area_fields as $field) {
                if (!$this->fields->has($field)) {
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::TEXTAREA]));
                    $this->command->line("    - $field created");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $json_fields = ['content'];
            foreach ($json_fields as $field) {
                if (!$this->fields->has($field)) {
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::JSON]));
                    $this->command->line("    - $field created");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }

            $date_fields = ['period_from', 'period_to'];
            foreach ($date_fields as $field) {
                if (!$this->fields->has($field)) {
                    $this->fields->put($field, $this->create(Field::class, ['name' => $field, 'type' => FieldType::DATETIME]));
                    $this->command->line("    - $field created");
                } else {
                    $this->command->line("    - $field already exists");
                }
            }
        });
    }

    private function createDefaultEntities(): void
    {
        $this->logOperation(Entity::class);


        $this->entities = Entity::withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function () {
            $standard = 'standard';

            $entity_name = 'article';
            if (!$this->entities->has($entity_name)) {
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                $preset = $entity->presets()->create(['name' => $standard]);
                $preset->fields()->syncWithoutDetaching($this->fields->filter(fn($field) => in_array($field->name, ['kicker', 'title', 'subtitle', 'short_content', 'content'])));
                $this->command->line("    - $entity_name created");
            } else {
                $this->command->line("    - $entity_name already exists");
            }

            $entity_name = 'event';
            if (!$this->entities->has($entity_name)) {
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                $preset = $entity->presets()->create(['name' => $standard]);
                $preset->fields()->syncWithoutDetaching($this->fields->filter(fn($field) => in_array($field->name, ['title', 'subtitle', 'short_content', 'content', 'period_from', 'period_to'])));
                $this->command->line("    - $entity_name created");
            } else {
                $this->command->line("    - $entity_name already exists");
            }

            $entity_name = 'multimedia';
            if (!$this->entities->has($entity_name)) {
                $entity = $this->create(Entity::class, ['name' => $entity_name]);
                $this->entities->put($entity_name, $entity);
                $preset = $entity->presets()->create(['name' => $standard]);
                $preset->fields()->syncWithoutDetaching($this->fields->filter(fn($field) => in_array($field->name, ['title', 'subtitle', 'short_content', 'content'])));
                $this->command->line("    - $entity_name created");
            } else {
                $this->command->line("    - $entity_name already exists");
            }
        });
    }
}
