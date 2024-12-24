<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Components;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Definitions\HasPath;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Grids\Definitions\FieldType;
use Modules\Core\Grids\Definitions\HasFormatters;
use Modules\Core\Grids\Definitions\HasValidations;

class Field implements \JsonSerializable
{
    use HasFormatters, HasPath, HasValidations;

    private ?Model $model = null;

    // private string $name;
    private string $alias;

    private ?Option $option = null;

    private ?Funnel $funnel = null;

    private bool $readable = true;

    private bool $writable = true;

    private FieldType $fieldType;

    /**
     * @param  Model  $model  field entity related model
     * @param  string  $path  field path (prefix of the full name)
     * @param  string  $name  field name (column)
     * @param  string|null  $alias  field alias (name will be used if nothing assigned)
     */
    public function __construct(string $path, string $name, ?string $alias = null, FieldType $fieldType = FieldType::COLUMN, ?Model $model = null)
    {
        $this->path = $path;
        $this->name = $name;
        $this->alias = $alias ?? $name;
        $this->fieldType = $fieldType;
        if ($model) {
            $this->setModel($model);
        }
    }

    /**
     * field generator
     *
     * @return \Closure(Model): Field
     */
    public static function create(string $fullpath, ?string $alias = null, bool $readable = true, bool $writable = true, FieldType $fieldType = FieldType::COLUMN): \Closure
    {
        [$path, $name] = self::splitPath($fullpath);

        return fn(Model $model): static => (new self($path, $name, $alias ?? $name, $fieldType, $model))->readable($readable)->writable($writable);
    }

    public function getModel(): ?Model
    {
        return $this->model ?? null;
    }

    /**
     * set model object
     *
     * @return void
     */
    public function setModel(Model $model)
    {
        if (!Grid::useGridUtils($model)) {
            throw new \UnexpectedValueException('Model ' . $model::class . ' doesn\'t use ' . HasGridUtils::class);
        }
        $this->model = &$model;
    }

    /**
     * gets field alias
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * gets field full path alias
     */
    public function getFullAlias(): ?string
    {
        return $this->path ? $this->path . '.' . $this->getAlias() : null;
    }

    /**
     * gets field full path alias
     */
    public function getFullQueryAlias(): ?string
    {
        if (!$this->path) {
            return null;
        }

        $exploded = explode('.', $this->getFullAlias());
        $exploded[0] = $this->model->getTable();

        return implode('.', $exploded);
    }

    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }

    /**
     * returns if field has Option set
     */
    public function hasOption(): bool
    {
        return $this->option !== null;
    }

    /**
     * gets field Option if exists
     */
    public function getOption(): ?Option
    {
        return $this->option;
    }

    /**
     * @param  Option  $option  sets option for the current field
     */
    private function setOption(Option $option): void
    {
        $this->option = $option;
    }

    /**
     * field Option public setter (alias of setOption returning self for pipes)
     *
     * @param  callable(Model, Field):Option  $option
     * */
    public function options(callable $option): static
    {
        $this->setOption($option($this->model, $this));

        return $this;
    }

    /**
     * returns if field has Funnel set
     */
    public function hasFunnel(): bool
    {
        return $this->funnel !== null;
    }

    /**
     * gets field Funnel if exists
     */
    public function getFunnel(): ?Funnel
    {
        return $this->funnel;
    }

    /**
     * sets field funnel
     */
    private function setFunnel(Funnel $funnel): void
    {
        $this->funnel = $funnel;
    }

    /**
     * field funnel public setter (alias of setFunnel returning self for pipes)
     *
     * @param callable(Model, Field):Funnel $funnel
     */
    public function funnel(callable $funnel): static
    {
        $this->setFunnel($funnel($this->model, $this));

        return $this;
    }

    /**
     * returns if field is readable
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * readable property setter
     */
    public function readable(bool $isReadable): static
    {
        if ($isReadable && $this->isHidden()) {
            return $this;
        } //throw new \UnexpectedValueException("Cannot set column {$this->name} as readable because is an HIDDEN field in Model " . $this->model::class);
        if (!$isReadable && $this->fieldType !== FieldType::COLUMN) {
            throw new \UnexpectedValueException("Cannot disable read operation for column {$this->name} because is an aggregated field. Remove it from fields if you don't need it");
        }
        if ($isReadable && $this->isAppend() && !$this->hasGetAppend()) {
            throw new \UnexpectedValueException("Cannot set column {$this->name} as readable because it is a calculated field but it doesn't have a getter " . $this->model::class);
        }
        $this->readable = $isReadable;

        return $this;
    }

    /**
     * returns if field is writable
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * writable property setter
     */
    public function writable(bool $isWritable): static
    {
        if ($isWritable && !$this->isFillable()) {
            return $this;
        } //throw new \UnexpectedValueException("Cannot set column {$this->name} as writable because is not a FILLABLE field in Model " . $this->model::class);
        if ($isWritable && $this->fieldType !== FieldType::COLUMN) {
            throw new \UnexpectedValueException("Cannot set column {$this->name} as writable because is an aggregated field");
        }
        if ($isWritable && $this->isAppend() && !$this->hasSetAppend()) {
            throw new \UnexpectedValueException("Cannot set column {$this->name} as readable because it is a calculated field but it doesn't have a setter " . $this->model::class);
        }
        $this->writable = $isWritable;

        return $this;
    }

    public function isFillable(): bool
    {
        return in_array($this->name, $this->model->getFillableFields());
    }

    public function isHidden(): bool
    {
        return in_array($this->name, $this->model->getHiddenFields());
    }

    public function isAppend(): bool
    {
        return $this->model->isAppend($this->name);
    }

    private function hasGetAppend(): bool
    {
        return $this->model->hasGetAppend($this->name);
    }

    private function hasSetAppend(): bool
    {
        return $this->model->hasSetAppend($this->name);
    }

    private function parseValidationsRules(): array
    {
        $validations = $this->model->getRules()[$this->getName()] ?? [];
        if (is_string($validations)) {
            preg_match("/regex:\/(?:.*)\//", $validations, $regex, PREG_UNMATCHED_AS_NULL);
            if (!empty($regex) && $regex[0] !== null) {
                $validations = trim(str_replace($regex[0], '', $validations), '|');
            }
            $validations = explode('|', $validations);
        }

        $filtered_validations = [];
        foreach ($validations as $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    if (is_string($vv)) {
                        $filtered_validations[] = $vv;
                    }
                }
            } else {
                $filtered_validations[] = $v;
            }
        }

        return $filtered_validations;
    }

    public function getRules(): array
    {
        return $this->parseValidationsRules();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return (array|bool|string)[]
     *
     * @psalm-return array{readable: bool, writable: bool, fieldType: string, required: bool, validations: array}
     */
    public function toArray(): array
    {
        $filtered_validations = $this->parseValidationsRules();

        return [
            'readable' => $this->isReadable(),
            'writable' => $this->isWritable(),
            'fieldType' => $this->getFieldType()->value,
            'required' => in_array('required', $filtered_validations),
            'validations' => $filtered_validations,
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
