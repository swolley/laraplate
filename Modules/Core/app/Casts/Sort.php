<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

readonly class Sort
{
    public string $property;
    public SortDirection $direction;

    public function __construct(string $property, string|SortDirection $type = SortDirection::ASC)
    {
        $this->property = $property;
        $this->direction = $type instanceof SortDirection ? $type : SortDirection::tryFrom(mb_strtolower($type));
    }
}
