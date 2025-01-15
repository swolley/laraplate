<?php

namespace Modules\Cms\Console;

use Illuminate\Support\Str;
use Modules\Cms\Models\Field;
use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Preset;
use Illuminate\Console\Command;
use Modules\Cms\Casts\FieldType;
use function Laravel\Prompts\text;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use Modules\Core\Helpers\HasCommandUtils;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
// use Symfony\Component\Console\Input\InputOption;
// use Symfony\Component\Console\Input\InputArgument;

class CreateEntityCommand extends Command
{
    use HasCommandUtils;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:create-entity {entity?} {--content-model}';

    /**
     * The console command description.
     */
    protected $description = 'Create new cms entity <comment>(✎ Modules\Cms)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            $entity = new Entity();
            $fillables = $entity->getFillable();
            $validations = $entity->getOperationRules('create');
            /** @var Collection<int, Field> $all_fields */
            $all_fields = Field::query()->get()->keyBy('id');

            if ($this->argument('entity')) {
                $entity->name = $this->argument('entity');
            }
            foreach ($fillables as $attribute) {
                if ($attribute === 'name' && $entity->name) continue;
                $entity->{$attribute} = text(ucfirst($attribute), '', $attribute === 'slug' ? Str::slug($entity->name) : '', true, fn(string $value) => $this->validationCallback($attribute, $value, $validations));
            }

            $entity->save();

            $this->output->info("A default preset 'standard' will be created for the entity '{$entity->name}'");

            $preset = new Preset();
            $preset->name = 'standard';
            $preset->entity_id = $entity->id;
            $preset->save();

            $preset_fields = multiselect('Choose fields for the preset', $all_fields->pluck('name', 'id'), required: true);

            foreach ($preset_fields as $field) {
                $field = $all_fields->get($field);
                $is_required = confirm("Do you want '{$field['name']}' to be required?", false);
                $this->assignFieldToPreset($preset, $field, $is_required);
            }

            if ($this->option('content-model') || confirm("Do you want to create a content model file for this entity?", false)) {
                $this->call('cms:make-content-model', ['entity' => $entity->name]);
            }

            $this->info("Entity '{$entity->name}' created");
        });
    }

    private function assignFieldToPreset(Preset $preset, Field $field, bool $is_required): void
    {
        $pivotAttributes = ['is_required' => false, 'default' => null, 'preset_id' => $preset->id];
        $pivotAttributes['is_required'] = $is_required;
        $pivotAttributes['default'] = $this->getDefaultFieldValue($field, $is_required);
        $preset->fields()->attach($field['id'], $pivotAttributes);
    }

    private function getDefaultFieldValue(Field $field, bool $is_required): mixed
    {
        $default = text(
            "Specify a default value for '{$field->name}'",
            $is_required,
            match ($field->type) {
                FieldType::SELECT && isset($field->options->multiple) && $field->options->multiple => '[]',
                FieldType::SWITCH => $is_required ? 'true' : 'false',
                FieldType::CHECKBOX => '[]',
                default => 'null',
            },
            hint: "Type 'null' to set the default value to null"
        );
        if ($default === 'null') {
            $default = null;
        } else if (preg_match("/\d+/", $default)) {
            $default = Str::contains($default, '.') ? (float) $default : (int) $default;
        } else if (in_array($default, ['true', 'false'])) {
            $default = $default === 'true';
        } else if (preg_match('/^\[.*\]$/', $default)) {
            $default = json_decode($default);
        }

        return $default;
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['entity', InputArgument::OPTIONAL, 'The entity name.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['content-model', null, InputOption::VALUE_NONE, 'Create a content model file for this entity.', false],
        ];
    }
}
