<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

readonly class Filter
{
    public string $property;

    public FilterOperator $operator;

    public mixed $value;

    public function __construct(string $property, mixed $value, FilterOperator $operator = FilterOperator::EQUALS)
    {
        $this->property = $property;
        $this->operator = $operator;
        $this->value = $value;
    }
}
