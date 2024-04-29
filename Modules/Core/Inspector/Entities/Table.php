<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

class Table
{
    public readonly Index|null $primaryKey;

    public function __construct(
        public readonly string $name,
        /** @param Collection<Column> */
        public readonly Collection $columns,
        /** @param Collection<Index> */
        public readonly Collection $indexes,
        /** @param Collection<ForeignKey> */
        public readonly Collection $foreignKeys,
        public readonly string $schema,
        public readonly ?string $connection = null,
    ) {
        $primaryKey = $indexes->filter(fn ($index) => $index->attributes->contains('primary'));

        if ($primaryKey->isNotEmpty()) {
            $this->primaryKey = $primaryKey->first();
        } else {
            $this->primaryKey = null;
        }
    }

    /**
     *
     * @return Collection<Column>
     */
    public function getPrimaryKeyColumns(): Collection
    {
        return $this->columns->filter(fn ($c) => $this->primaryKey->columns->contains($c->name));
    }
}
