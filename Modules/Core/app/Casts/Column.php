<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

readonly class Column
{
    public string $name;
    public ColumnType $type;

    public function __construct(string $name, string|ColumnType $type = ColumnType::COLUMN)
    {
        $this->name = $name;
        $this->type = $type instanceof ColumnType ? $type : ColumnType::tryFrom(mb_strtolower($type));
    }
}
