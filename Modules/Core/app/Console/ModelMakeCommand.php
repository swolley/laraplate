<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use function Laravel\Prompts\text;
use Illuminate\Support\Collection;
use function Laravel\Prompts\table;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\suggest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use function Laravel\Prompts\multiselect;
use Symfony\Component\Console\Input\InputInterface;
use Illuminate\Console\Concerns\CreatesMatchingTest;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Console\Concerns\PromptsForMissingInput;
use Illuminate\Foundation\Console\ModelMakeCommand as BaseModelMakeCommand;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ModelMakeCommand extends BaseModelMakeCommand
{
    use PromptsForMissingInput;

    protected $description = 'Create or modify an Eloquent model class <comment>(⛭ Modules\Core)</comment>';

    private bool $isNewClass = false;

    /**
     * @var array
     */
    private $availableClasses = [];

    /**
     * @var string[][]
     *
     * @psalm-var array<string, array<string, string>>
     */
    private array $availableTypes = [
        'Main Types' => [
            'string' => 'string',
            'text' => 'string',
            'boolean' => 'int',
            'integer' => 'int',
            // 'smallint' => 'int',
            // 'bigint' => 'int',
            'float' => 'float',
            'double' => 'float',
            'encrypted' => 'string',
        ],
        'Array/Object Types' => [
            'array' => 'array',
            // 'simple_array',
            'json' => 'array',
            'object' => 'object',
            // 'binary',
            'blob' => 'string',
        ],
        'Date/Time Types' => [
            'datetime' => 'datetime',
            // 'datetimetz',
            'date' => 'datetime',
            /*'time',
            'dateinterval'*/
            'timestamp' => 'datetime',
        ],
        'Other Types' => [
            /*'ascii_string',
            'decimal',
            'guid'*/],
        'Relationships/Associations' => [
            'relation' => 'relation',
            'relation\ManyToOne' => 'belongsTo',
            'relation\OneToMany' => 'hasMany',
            'relation\ManyToMany' => 'belongsToMany',
            'relation\OneToOne' => 'hasOne',
        ],
    ];

    public function __construct(Filesystem $files)
    {
        parent::__construct($files);

        // $this->availableClasses = $this->possibleModels();
    }

    private static function array_last(string $needle, array $array): int|false
    {
        $reversed_array = array_reverse($array);

        /** @var int|false $pos */
        $pos = array_search($needle, $reversed_array, true);

        if ($pos === false) {
            return false;
        }

        return count($array) - $pos - 1;
    }

    private static function addDefaultSections(string $className, string $classCode): string
    {
        $short_name = ModelMakeCommand::stripModelsNamespace($className);

        /** @var int $pos */
        $pos = Str::position($classCode, 'class ' . $short_name);

        /** @var string $classCode */
        $classCode = Str::substrReplace($classCode, "/**\n */\n", $pos, 0);

        $injected_class_code = explode("\n", $classCode);

        /**
         * end of class index.
         *
         * @var int $eoc_idx
         */
        $eoc_idx = ModelMakeCommand::array_last('}', $injected_class_code);
        $table_name = Str::plural(Str::snake($short_name));
        $default_comments = <<<EOL

            #region [ATTRIBUTES]
            public \$connection = 'default';
            public \$table = '{$table_name}';
            
            protected \$fillable = [
            ];

            protected function casts() 
            {
                return [
                ];
            }

            protected \$hidden = [
            ];

            protected \$appends = [
            ];

            public \$rules = [
                'create' => [],
                'update' => [],
                'always' => [],
            ];
            #endregion
        
            // public function __construct() {
            //     parent::__construct();
            // }

            #region [SCOPES]
            #endregion

            #region [ACCESSORS_MUTATORS]
            #endregion
            
            #region [RELATIONS]
            #endregion
        EOL;

        array_splice($injected_class_code, $eoc_idx, 0, $default_comments);

        return implode("\n", $injected_class_code);
    }

    private static function stripModelsNamespace(string $className): string
    {
        return str_replace('App\\Models\\', '', $className);
    }

    public function handle(): void
    {
        $name = Str::studly($this->getNameInput());
        $table_name = Str::plural(Str::snake($name));

        if ($this->isReservedName($name)) {
            $this->error('The name ' . $name . ' is reserved by PHP.');

            return;
        }

        /** @var class-string $name */
        $name = $this->qualifyClass($name);
        $path = $this->getPath($name);

        $bypass_interaction = false;

        $this->isNewClass = !in_array($this->getNameInput(), $this->availableClasses, true);

        if ($this->isNewClass) {
            $this->makeDirectory($path);

            $class_code = $this->sortImports($this->buildClass($name));
            $class_code = $this->addDefaultSections($name, $class_code);
            $dynamicEntityClass = 'Modules\Core\Models\DynamicEntity';

            if (Schema::hasTable($table_name) && class_exists($dynamicEntityClass)) {
                $this->info('An unmapped table with the same name was found in the schema');

                if (confirm(sprintf("Would you like to automatically map '%s' table?", $table_name))) {
                    $all_types = array_merge(...array_values($this->availableTypes));
                    /** @var Model $class */
                    $class = $dynamicEntityClass::resolve($table_name);

                    foreach ($class->getFillable() as $fillable) {
                        /** @var string $class_code */
                        $class_code = $this->addPropertyIntoFillables($class_code, $fillable);
                    }
                    $casts = $class->casts();

                    foreach ($casts as $property => $cast) {
                        $class_code = $this->addPropertyIntoCasts($class_code, $property, $cast);
                    }

                    foreach ($class->getHidden() as $hidden) {
                        /** @var string $class_code */
                        $class_code = $this->addPropertyIntoHidden($class_code, $hidden);
                    }
                    $found_rules = [];

                    if (class_uses_trait($class, HasValidations::class)) {
                        foreach ($class->getRules() as $property => $rules) {
                            $found_rules[$property] = $rules;
                        }
                    }

                    foreach ($class->getAppends() as $append) {
                        $type = $casts[$append] ?? 'text';
                        $nullable = isset($found_rules[$append]) ? (gettype($found_rules[$append]) === 'string' ? Str::contains($found_rules[$append], 'required') : in_array('required', $found_rules[$append], true)) : true;
                        $method_subfix = Str::studly($append) . 'Attribute';

                        if (method_exists($class, 'get' . $method_subfix)) {
                            $getter = new ReflectionMethod($class, 'get' . $method_subfix);
                            $type = $getter->getReturnType() ?? 'text';
                        } elseif (method_exists($class, 'set' . $method_subfix)) {
                            $setter = new ReflectionMethod($class, 'set' . $method_subfix);
                            $type = $setter->getReturnType() ?? 'text';
                        }
                        $class_code = $this->addPropertyIntoAccessorsMutators($class_code, $append, $type, $nullable, $all_types);
                    }

                    $bypass_interaction = true;
                }
            }

            $this->files->put($path, $class_code);

            $info = $this->type;

            if (class_uses_trait($name, CreatesMatchingTest::class)) {
                if ($this->handleTestCreation($path)) {
                    $info .= ' and test';
                }
            }

            $this->info(sprintf('%s [%s] created successfully.', $info, $path));
            $this->newLine();
        } else {
            $class_code = $this->files->get($path);
        }

        if (!$bypass_interaction) {
            $this->proceedWithModelAttributes($name, $class_code, $path);
        }
    }

    protected function possibleModels()
    {
        return models(false);
    }

    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace) || Str::startsWith($name, config('modules.namespace'))) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name,
        );
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return (
            Str::startsWith($name, config('modules.namespace'))
            ? base_path()
            : $this->laravel['path']
        ) . DIRECTORY_SEPARATOR . str_replace('\\', '/', $name) . '.php';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->availableClasses = $this->possibleModels();

        return parent::execute($input, $output);
    }

    protected function promptForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        $this->availableClasses = $this->possibleModels();

        $prompted = collect($this->getDefinition()->getArguments())
            ->filter(fn($argument) => $argument->isRequired() && is_null($input->getArgument($argument->getName())))
            ->filter(fn($argument) => $argument->getName() !== 'command')
            ->each(function ($argument) use ($input): void {
                $question = $this->promptForMissingArgumentsUsing()[$argument->getName()] ?? 'What is ' . lcfirst($argument->getDescription()) . '?';
                $arg_name = $argument->getName();

                /** @psalm-suppress ArgumentTypeCoercion */
                $cb = $arg_name === 'name'
                    ? $this->askPersistentlyWithCompletion($question, $this->availableClasses)
                    : text($question, required: true);
                $input->setArgument($arg_name, $cb);
            })
            ->isNotEmpty();

        if ($prompted) {
            $this->afterPromptingForMissingArguments($input, $output);
        }
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        $this->isNewClass = !in_array($this->getNameInput(), $this->availableClasses, true);

        if (!$this->isNewClass) {
            $this->info(sprintf('%s already exists.', $this->qualifyClass($this->getNameInput())));
        }

        if ($this->isReservedName($this->getNameInput()) || $this->didReceiveOptions($input)) {
            return;
        }

        collect(
            multiselect(
                'Would you like any of the following?',
                [
                    'all',
                    'factory',
                    'form requests',
                    'migration',
                    'policy',
                    'resource controller',
                    'seed',
                ],
                default: $this->isNewClass ? ['migration'] : [],
            ),
        )
            ->map(fn($option) => match ($option) {
                'resource controller' => 'resource',
                'form requests' => 'requests',
                default => $option,
            })
            ->each(function ($option) use ($input) {
                /** @var string $option */
                return $input->setOption($option, true);
            });
    }

    protected function createMigration(): void
    {
        /** @var string $name */
        $name = $this->argument('name');
        $table = Str::snake(Str::pluralStudly(class_basename($name)));

        if ($this->option('pivot')) {
            $table = Str::singular($table);
        }

        $this->call('make:migration', [
            'name' => ($this->isNewClass ? 'create' : 'update') . "_{$table}_table",
            ($this->isNewClass ? '--create' : '--update') => $table,
            '--fullpath' => true,
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $className
     * @return array<int, mixed>
     */
    private function getClassFields(string $className): array
    {
        $ref = new ReflectionClass($className);
        $already_existent_fields = [];

        if ($ref->hasProperty('fillable')) {
            $prop = $ref->getProperty('fillable');

            /** @psalm-suppress UnusedMethodCall */
            $prop->setAccessible(true);
            array_push($already_existent_fields, ...$prop->getDefaultValue());
        }

        if ($ref->hasMethod('casts')) {
            $method = $ref->getMethod('casts');

            /** @psalm-suppress UnusedMethodCall */
            $method->setAccessible(true);
            $already_existent_fields = array_merge($already_existent_fields, array_keys($method->invoke($ref)));
        }

        if ($ref->hasProperty('hidden')) {
            $prop = $ref->getProperty('hidden');

            /** @psalm-suppress UnusedMethodCall */
            $prop->setAccessible(true);
            $already_existent_fields = array_merge($already_existent_fields, $prop->getDefaultValue());
        }

        if ($ref->hasProperty('attributes')) {
            $prop = $ref->getProperty('attributes');

            /** @psalm-suppress UnusedMethodCall */
            $prop->setAccessible(true);
            $already_existent_fields = array_merge($already_existent_fields, array_keys($prop->getDefaultValue()));
        }

        if ($ref->hasProperty('appends')) {
            $prop = $ref->getProperty('appends');

            /** @psalm-suppress UnusedMethodCall */
            $prop->setAccessible(true);
            $already_existent_fields = array_merge($already_existent_fields, $prop->getDefaultValue());
        }

        return $already_existent_fields;
    }

    // private function printAllAvailableTypes(): void
    // {
    //     foreach ($this->availableTypes as $title => $types) {
    //         if (!empty($types)) {
    //             $property_types = array_keys($types);
    //             $this->line($title);
    //             $this->bulletList($property_types);
    //         }
    //     }
    // }

    private function printRelationsChoiceHelper(string $className, string $fieldName, string $relatedName): string
    {
        $exploded = explode('\\', $className);
        $originalEntityShort = end($exploded);
        $exploded = explode('\\', $relatedName);
        $targetEntityShort = end($exploded);

        $filtered_relation_types = array_filter($this->availableTypes['Relationships/Associations'], fn($type) => $type !== 'relation');

        /** @var Collection<int, list{array<array-key, string>|string, string}> */
        $rows = new Collection();

        foreach (array_keys($filtered_relation_types) as $key) {
            $message = '';

            switch (Str::replace('relation\\', '', $key)) {
                case 'ManyToOne':
                    $message = "Each <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.\nEach <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.";

                    break;
                case 'OneToMany':
                    $message = "Each <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.\nEach <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.";

                    break;
                case 'ManyToMany':
                    $message = "Each <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.\nEach <comment>%s</comment> can also relate to (can also have) <info>many</info> <comment>%s</comment> objects.";

                    break;
                case 'OneToOne':
                    $message = "Each <comment>%s</comment> relates to (has) exactly <info>one</info> <comment>%s</comment>.\nEach <comment>%s</comment> also relates to (has) exactly <info>one</info> <comment>%s</comment>.";

                    break;
            }

            $key = Str::replace('relation\\', '', $key);
            $rows->add([$key, sprintf($message, $originalEntityShort, $targetEntityShort, $targetEntityShort, $originalEntityShort)]);
        }

        /** @psalm-suppress InvalidArgument */
        table(['Type', 'Description'], $rows);

        return $this->askPersistentlyWithCompletion("\"{$fieldName}\" relation type:", array_keys($filtered_relation_types));
    }

    /**
     * @return string|string[]
     *
     * @psalm-return array<string>|string
     */
    private function handleRelationDetailsChoice(string $className, string $fieldName, string &$fieldType): array|string
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        $related_class = $this->askPersistentlyWithCompletion('What class should this entity be related to?', $this->availableClasses);

        if ($fieldType === 'relation') {
            $fieldType = $this->printRelationsChoiceHelper($className, $fieldName, $related_class);
        }

        return Str::replace('relation\\', '', $related_class);
    }

    private function recognizeDefaultFieldType(string $fieldName): string
    {
        $defaultType = 'string';

        if ('_at' === $suffix = mb_substr($fieldName, -3)) {
            $defaultType = 'datetime';
        } elseif ($suffix === '_id') {
            $defaultType = 'integer';
        } elseif (str_starts_with($fieldName, 'is_') || str_starts_with($fieldName, 'has_')) {
            $defaultType = 'boolean';
        } elseif ($fieldName === 'uuid') {
            $defaultType = Type::hasType('uuid') ? 'uuid' : 'guid';
        } elseif ($fieldName === 'guid') {
            $defaultType = 'guid';
        }

        return $defaultType;
    }

    private function getCodeTypeFromCast(string $fieldType): string
    {
        $all_types = array_merge(...array_values($this->availableTypes));
        $cast_type = $all_types[$fieldType];

        return $cast_type ?: '';
    }

    private function injectCodeAtTheEnd(string $classCode, string $codeSnippet): string
    {
        return Str::replaceLast('}', $codeSnippet . "\n\n}", $classCode);
    }

    /**
     * @return string|string[]
     *
     * @psalm-return array<string>|string
     */
    private function addNewPropertyAnnotation(string $className, string $classCode, string $fieldName, string $fieldType, bool $fieldNullable): array|string
    {
        /** @var int $pos */
        $pos = Str::position($classCode, 'class ' . ModelMakeCommand::stripModelsNamespace($className));

        /** @var int $pos */
        $pos = Str::position($classCode, ' */', $pos - 20);

        return Str::substrReplace($classCode, sprintf("\n * @property %s $%s\n", $this->getCodeTypeFromCast($fieldType) . ($fieldNullable ? '|null' : ''), $fieldName), $pos, 0);
    }

    /**
     * @return string|string[]
     *
     * @psalm-return array<string>|string
     */
    private function addPropertyIntoFillables(string $classCode, string $fieldName): array|string
    {
        $search = 'protected $fillable = [';
        $pos = Str::position($classCode, $search);

        if ($pos !== false) {
            $needs_newline = $classCode[$pos + mb_strlen($search)] === ']';

            /** @var int $pos */
            $pos = Str::position($classCode, '];', $pos);
            $classCode = Str::substrReplace($classCode, sprintf("%s\t'%s',\n\t", $needs_newline ? "\n" : '', $fieldName), $pos, 0);
        }

        return $classCode;
    }

    /**
     * @return string|string[]
     *
     * @psalm-return array<string>|string
     */
    private function addPropertyIntoHidden(string $classCode, string $fieldName): array|string
    {
        $search = 'protected $hidden = [';
        $pos = Str::position($classCode, $search);

        if ($pos !== false) {
            $needs_newline = $classCode[$pos + mb_strlen($search)] === ']';

            /** @var int $pos */
            $pos = Str::position($classCode, '];', $pos);
            $classCode = Str::substrReplace($classCode, sprintf("%s\t'%s',\n\t", $needs_newline ? "\n" : '', $fieldName), $pos, 0);
        }

        return $classCode;
    }

    private function addPropertyIntoCasts(string $classCode, string $fieldName, string $fieldType): string
    {
        $search = 'protected $casts = [';
        $pos = Str::position($classCode, $search);

        if ($pos !== false) {
            $needs_newline = $classCode[$pos + mb_strlen($search)] === ']';

            /** @var int $pos */
            $pos = Str::position($classCode, '];', $pos);

            /** @var string $pos */
            $classCode = Str::substrReplace($classCode, sprintf("%s\t'%s' => '%s',\n\t", $needs_newline ? "\n" : '', $fieldName, $fieldType), $pos, 0);
        } else {
            $classCode = $this->injectCodeAtTheEnd($classCode, sprintf("\n%s\n\t\t'%s' => '%s',\n\t];", $search, $fieldName, $fieldType));
        }

        return $classCode;
    }

    private function addPropertyIntoValidations(string $classCode, string $fieldName, string $fieldType, bool $fieldNullable): string
    {
        $search = 'public $rules = [';
        $pos = Str::position($classCode, $search);

        if ($pos !== false) {
            $needs_newline = $classCode[$pos + mb_strlen($search)] === ']';

            if ($needs_newline) {
                $classCode = str_replace('public $rules = [];', 'public \$rules = [
                \'create\' => [],
                \'update\' => [],
                \'always\' => [],
            ];', $classCode);
            }

            /** @var int $pos */
            $pos = Str::position($classCode, "'always' => [", $pos);
            $needs_newline = $classCode[$pos + mb_strlen($search)] === ']';

            /** @var int $pos */
            $pos = Str::position($classCode, '];', $pos);

            /** @var string $classCode */
            $classCode = Str::substrReplace($classCode, sprintf("%s\t'%s' => '%s%s',\n\t", $needs_newline ? "\n" : '', $fieldName, Str::startsWith($fieldType, 'date') ? 'date' : $fieldType, !$fieldNullable ? '|required' : ''), $pos, 0);
        } else {
            $classCode = $this->injectCodeAtTheEnd($classCode, sprintf("\n%s\n\t\t'%s' => '%s%s',\n\t];", $search, $fieldName, $fieldType, !$fieldNullable ? '|required' : ''));
        }

        return $classCode;
    }

    private function injectImportClass(string &$classCode, string $importName): bool
    {
        $added_import = false;
        $search = "use {$importName};";
        $relation_import_pos = Str::position($classCode, $search);

        if ($relation_import_pos === false) {
            $relation_import_pos = Str::position($classCode, 'use ');

            if ($relation_import_pos !== false) {
                /** @var string $classCode */
                $classCode = Str::substrReplace($classCode, $search . "\n", $relation_import_pos, 0);
                $classCode = $this->sortImports($classCode);
                $added_import = true;
            }
        } else {
            $added_import = true;
        }

        return $added_import;
    }

    private function addPropertyIntoAccessorsMutators(string $classCode, string $fieldName, string $fieldType, bool $fieldNullable, array $allTypes): string
    {
        if (!$fieldNullable) {
            $search = '#region [ACCESSORS_MUTATORS]';
            $pos = Str::position($classCode, $search);
            $field_real_type = $allTypes[$fieldType];
            $method_name = Str::studly($fieldName);

            if ($field_real_type === 'datetime') {
                $imported_carbon_class = $this->injectImportClass($classCode, 'Illuminate\Support\Carbon');

                if (!$imported_carbon_class) {
                    $field_real_type = 'Illuminate\Support\Carbon';
                }
            }

            $snippet = <<<EOL
                
                public function set{$method_name}Attribute({$field_real_type} \${$fieldName})
                {
                    \$this->{$fieldName} = \${$fieldName};
                }
                
            EOL;

            if ($pos !== false) {
                /** @var int $pos */
                $pos = Str::position($classCode, '#endregion', $pos);

                /** @var string $classCode */
                $classCode = Str::substrReplace($classCode, $snippet, $pos, 0);
            } else {
                $classCode = $this->injectCodeAtTheEnd($classCode, $snippet);
            }
        }

        return $classCode;
    }

    private function updateClassWithNewProperty(string $className, string $classCode, string $classPath, string $fieldName, string $fieldType, bool $fieldNullable, array $allTypes): string
    {
        /** @var string $classCode */
        $classCode = $this->addPropertyIntoFillables($classCode, $fieldName);
        $classCode = $this->addPropertyIntoCasts($classCode, $fieldName, $fieldType);
        $classCode = $this->addPropertyIntoValidations($classCode, $fieldName, $fieldType, $fieldNullable);
        $classCode = $this->addPropertyIntoAccessorsMutators($classCode, $fieldName, $fieldType, $fieldNullable, $allTypes);

        /** @var string $classCode */
        $classCode = $this->addNewPropertyAnnotation($className, $classCode, $fieldName, $fieldType, $fieldNullable);

        $this->files->put($classPath, $classCode);

        return $classCode;
    }

    private function getReversedRelationType(string $relation_type): ?string
    {
        return match ($relation_type) {
            'ManyToOne' => 'OneToMany',
            'OneToMany' => 'ManyToOne',
            'OneToOne' => 'OneToOne',
            'ManyToMany' => 'ManyToMany',
            default => null,
        };
    }

    private function proposeInversedName(string $class, string $relation): ?string
    {
        return match ($relation) {
            'ManyToOne' => Str::snake(Str::plural($class)),
            'OneToMany' => Str::snake(Str::singular($class)),
            'OneToOne' => Str::snake(Str::plural($class)),
            'ManyToMany' => Str::snake(Str::singular($class)),
            default => null,
        };
    }

    private function handleAskInversedRelation(string $className, string $relatedClass, string $fullRelatedClass, string $relationType): void
    {
        $short_class = ModelMakeCommand::stripModelsNamespace($className);
        $create_reverse_relation = $this->confirm("Do you want to create a method into class {$relatedClass} to access {$short_class}?", true);

        if (!$create_reverse_relation) {
            return;
        }
        $reversed_relation = $this->getReversedRelationType($relationType);

        if (!$reversed_relation) {
            return;
        }
        $proposed_name = $this->proposeInversedName($short_class, $reversed_relation);

        if (!$proposed_name) {
            return;
        }
        $inverted_relation_name = text("What is the name of the reversed relation? [{$proposed_name}]", default: $proposed_name);
        $related_path = $this->getPath($fullRelatedClass);
        $related_code = $this->files->get($related_path);
        $this->updateClassWithNewRelation($relatedClass, $related_code, $related_path, $inverted_relation_name, $reversed_relation, $className, true);
    }

    private function updateClassWithNewRelation(string $className, string $classCode, string $classPath, string $relationName, string $relationType, string $relatedClass, bool $isInversed = false): string
    {
        $added_relation_import = $this->injectImportClass($classCode, 'Illuminate\Database\Eloquent\Relations\Relation');
        $full_related_class = $this->qualifyModel($relatedClass);
        $added_model_import = $this->injectImportClass($classCode, $full_related_class);

        $relation_method = $this->availableTypes['Relationships/Associations'][$relationType];
        $class_relation_import = $added_relation_import ? 'Relation' : 'Illuminate\Database\Eloquent\Relations\Relation';
        $class_model_import = $added_model_import ? $relatedClass : $full_related_class;
        $snippet = <<<EOL

                //{$relationType}
                public function {$relationName}(): {$class_relation_import}
                {
                    return \$this->{$relation_method}({$class_model_import}::class);
                }
            
        EOL;

        $search = '#region [RELATIONS]';
        $pos = Str::position($classCode, $search);

        if ($pos !== false) {
            /** @var int $pos */
            $pos = Str::position($classCode, '#endregion', $pos);

            /** @var string $classCode */
            $classCode = Str::substrReplace($classCode, $snippet, $pos, 0);
        } else {
            $classCode = $this->injectCodeAtTheEnd($classCode, $snippet);
        }

        $this->files->put($classPath, $classCode);

        // reverse relation
        if (!$isInversed) {
            $this->handleAskInversedRelation($className, $relatedClass, $full_related_class, $relationType);
        }

        return $classCode;
    }

    private function askForPropertyName(array $newFields, array $alreadyExistentFields): string|false
    {
        $field_name = text(
            (!empty($newFields) ? 'Add another property? ' : '') . 'New property name',
            hint: 'press <return> to stop adding fields',
            validate: function (string $field_name) use ($newFields, $alreadyExistentFields) {
                $field_name = Str::snake(trim($field_name));

                if (in_array($field_name, $newFields, true) || in_array($field_name, $alreadyExistentFields, true)) {
                    return "The \"{$field_name}\" property already exists.";
                }

                return null;
            },
        );

        return $field_name ? Str::snake(trim($field_name)) : false;
    }

    /**
     * @param class-string $className
     */
    private function proceedWithModelAttributes(string $className, string $classCode, string $classPath): void
    {
        $all_types = array_merge(...array_values($this->availableTypes));
        $all_input_types = array_keys($all_types);

        $this->line('Let\'s add some new fields!');
        $this->line('You can always add more fields later manually or by re-running this command.');
        $this->newLine();

        $already_existent_fields = $this->getClassFields($className);
        $fields = [];

        while (true) {
            // NAME
            $field_name = $this->askForPropertyName($fields, $already_existent_fields);

            if ($field_name === false) {
                break;
            }

            // TYPE
            $default_type = $this->recognizeDefaultFieldType($field_name);

            $related_class = null;

            $field_type = suggest(
                "\"{$field_name}\" field type [{$default_type}]:",
                $all_input_types,
                placeholder: $default_type,
                validate: function (string $value) use ($all_input_types) {
                    return match (true) {
                        !in_array($value, $all_input_types, true) => "Invalid type \"{$value}\"",
                        default => null,
                    };
                },
            );

            if (array_key_exists($field_type, $this->availableTypes['Relationships/Associations'])) {
                $related_class = $this->handleRelationDetailsChoice($className, $field_name, $field_type);
            }

            if (array_key_exists($field_type, $this->availableTypes['Relationships/Associations'])) {
                if (!$related_class) {
                    throw new InvalidArgumentException('Missing related class attribute');
                }

                /** @var string $related_class */
                $classCode = $this->updateClassWithNewRelation($className, $classCode, $classPath, $field_name, $field_type, $related_class);
            } else {
                // REQUIRED
                $field_nullable = confirm("Is \"{$field_name}\" nullable:", true);
                $classCode = $this->updateClassWithNewProperty($className, $classCode, $classPath, $field_name, $field_type, $field_nullable, $all_types);
                $already_existent_fields[] = $field_name;
            }

            $this->info("Model {$className} updated.");

            $this->newLine();
        }
    }

    /**
     * @param  (int|string)[]  $choices
     *
     * @psalm-param list<array-key> $choices
     */
    private function askPersistentlyWithCompletion(string $question, array $choices): string
    {
        return suggest(
            $question,
            fn($value) => array_filter($choices, fn($name) => Str::contains($name, $value, ignoreCase: true)),
            required: true,
        );
    }
}
