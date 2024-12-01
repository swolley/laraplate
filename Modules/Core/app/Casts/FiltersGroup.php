<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

readonly class FiltersGroup
{
    public WhereClause $operator;

    /**
     *
     * @var array<Filter|FiltersGroup>
     */
    public array $filters;

    public function __construct(array $filters, WhereClause $operator = WhereClause::AND)
    {
        $this->operator = $operator;
        $this->filters = $filters;
    }
}
