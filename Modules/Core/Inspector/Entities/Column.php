<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;

class Column
{
    public readonly DoctrineTypeEnum $type;

    public function __construct(
        public readonly string $name,
        /** @param Collection<string> */
        public readonly Collection $attributes,
        public readonly mixed $default,
        string $type,
    ) {
        $this->type = DoctrineTypeEnum::fromString($type);
    }

    /**
     */
    public function isAutoincrement(): bool
    {
        return $this->attributes->contains('autoincrement');
    }

    /**
     */
    public function isNullable(): bool
    {
        return $this->attributes->contains('nullable');
    }

    /**
     */
    public function isUnsigned(): bool
    {
        return Str::contains($this->type, 'unsigned');
    }

    /**
     */
    public function getLength(): ?int
    {
        $length = filter_var($this->type, FILTER_SANITIZE_NUMBER_INT);

        return !empty($length) ? (int) $length : null;
    }
}
