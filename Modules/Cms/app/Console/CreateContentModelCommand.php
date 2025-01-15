<?php

namespace Modules\Cms\Console;

use Illuminate\Support\Str;
use Modules\Cms\Models\Entity;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class CreateContentModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:make-content-model {entity}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new content model <comment>(✎ Modules\Cms)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $entity = $this->argument('entity');
        $class_name = Str::studly($entity);
        $file_path = module_path('Cms', 'app/Models/Contents/' . $class_name . '.php');
        if (!file_exists($file_path)) {
            if (!Entity::query()->withoutGlobalScopes()->where('name', $entity)->exists()) {
                if ($this->confirm("Entity '{$entity}' not found, do you want to create it?", false)) {
                    $this->call('cms:create-entity', ['entity' => $entity, '--content-model' => true]);
                }

                return;
            }

            $class_definition = file_get_contents(module_path('Cms', 'stubs/content.stub'));
            $class_definition = str_replace('$CLASS$', $class_name, $class_definition);
            file_put_contents($file_path, $class_definition);
        }
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['entity', InputArgument::REQUIRED, 'The entity name.'],
        ];
    }
}
