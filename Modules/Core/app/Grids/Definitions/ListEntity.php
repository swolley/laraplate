<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Field;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Grids\Casts\GridRequestData;

/**
 * enanched entity class with common funcionalities for value/label lists
 */
abstract class ListEntity extends Entity
{
    private string $valueFieldName;

    private array $labelFieldsName;

    /**
     * @param  Field|Field[]  $labelField
     */
    final public function __construct(Model|string $model, Field $valueField, Field|array $labelField)
    {
        parent::__construct($model);
        $this->setValueField($valueField);
        $this->setLabelField($labelField);
    }

    /**
     * list entity generator
     *
     * @param  Field|string|string[]|Field[]|null  $labelField
     * @return \Closure construct callback
     *
     * @return \Closure(Model, Field): static
     */
    public static function create(Field|string|array|null $labelField = null): \Closure
    {
        if ($labelField && !($labelField instanceof Field)) {
            $labelField = is_string($labelField) ? self::createField($labelField) : array_map(fn($label) => self::createField($label), $labelField);
        }

        return fn(Model $model, Field $valueField) => new static($model, $valueField, $labelField ?? $valueField);
    }

    /**
     * create field object by path
     */
    private static function createField(string $name): Field
    {
        $fullpath = explode('.', $name);
        $name = array_pop($fullpath);
        $path = implode('.', $fullpath);

        return new Field($path, $name);
    }

    // public function getName(): string
    // {
    // 	return $this->getValueField()->getName();
    // }

    // /**
    //  * gets the object path property
    //  *
    //  * @return string
    //  */
    // public function getPath(): string
    // {
    // 	return $this->getValueField()->getPath();
    // }

    /**
     * gets field used for value data
     */
    public function getValueField(): Field
    {
        return $this->getFields()->offsetGet($this->valueFieldName);
    }

    /**
     * sets field used for value data
     *
     * @return void
     */
    private function setValueField(Field &$valueField)
    {
        $this->path = $valueField->getPath();
        $this->name = $valueField->getName();
        $this->addField($valueField);
        $this->valueFieldName = $valueField->getName();
    }

    /**
     * gets field used for label data
     *
     * @return Field|array<string, mixed>|null
     */
    public function getLabelField(): array|Field|null
    {
        $fields = $this->getFields()->filter(fn($field) => in_array($field->getName(), $this->labelFieldsName));
        $label = $fields->count() === 1 ? $fields->first() : $fields->toArray();

        return $label;
    }

    /**
     * sets field used for label field
     *
     * @param  Field|Field[]  $labelField
     * @return void
     */
    private function setLabelField(Field|array &$labelField)
    {
        foreach ((!is_array($labelField) ? [$labelField] : $labelField) as $field) {
            $this->addField($field);
            $this->labelFieldsName[] = $field->getName();
        }
    }

    /**
     * gets funnel additional fields
     *
     *
     * @return Collection<string, Field>
     */
    public function getAdditionalFields(): Collection
    {
        $value_field = $this->getValueField();
        $label_field = $this->getLabelField();

        return $this->getFields()->filter(fn($field, $key) => $key !== $label_field->getName() && $key !== $value_field->getName());
    }

    /**
     * sets additional fields through relationships
     */
    public function getAdditionalFieldsDeeply(): Collection
    {
        $additional_fields = clone $this->getAdditionalFields();
        foreach ($this->getRelations() as $relation) {
            $additional_fields = $additional_fields->merge($relation->getFields());
        }

        return $additional_fields;
    }

    /**
     * get entity data
     *
     * @return array{0: Collection, 1: int}
     */
    abstract protected function getData(): array;

    /**
     * start processing Entity with request filters
     *
     * {@inheritDoc}
     */
    public function process(GridRequest|GridRequestData $request): ResponseBuilder
    {
        if ($request instanceof GridRequest) {
            $this->parseRequest($request);
        } else {
            $this->requestData = $request;
        }

        $response_builder = new ResponseBuilder($request);
        $data = $this->getData();
        $total_records = $data[1];
        $data = $data[0];

        $this->setDataIntoResponse($response_builder, $data, $total_records);
        $response_builder->setClass($this->getModel());
        $response_builder->setTable($this->getModel()->getTable());

        return $response_builder;
    }
}
