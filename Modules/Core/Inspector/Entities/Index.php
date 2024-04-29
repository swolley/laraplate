<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

class Index
{
    public function __construct(
        public readonly string $name,
        /** @param Collection<string> */
        public readonly Collection $columns,
        /** @param Collection<string> */
        public readonly Collection $attributes,
    ) {
    }

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }
    public function isPrimaryKey(): bool
    {
        return $this->attributes->contains('primary');
    }

    public function isCompositePrimaryKey(): bool
    {
        return $this->isPrimaryKey() && $this->isComposite();
    }
}
