<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

class Index
{
    /** 
     * @param Collection<string> $columns 
     * @param Collection<string> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly Collection $columns,
        public readonly Collection $attributes,
    ) {}

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
