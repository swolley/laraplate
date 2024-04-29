<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

class ForeignKey
{
    public readonly ?string $foreignConnection;

    public function __construct(
        public readonly string $name,
        /** @param Collection<string> */
        public readonly Collection $columns,
        public readonly ?string $foreignSchema,
        public readonly string $foreignTable,
        /** @param Collection<string> */
        public readonly Collection $foreignColumns,
        public readonly string $localSchema,
        public readonly ?string $localConnection,
        public readonly ?string $onUpdate = null,
        public readonly ?string $onDelete = null,
    ) {
        if ($localSchema === $foreignSchema) {
            $this->foreignConnection = $localConnection;
        } else {
            foreach (config('database.connections') as $name => $config) {
                if ($config['database'] === $foreignSchema) {
                    $this->foreignConnection = $name;

                    break;
                }
            }
        }
    }

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }
}
