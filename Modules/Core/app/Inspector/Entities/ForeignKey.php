<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

class ForeignKey
{
    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ?string $foreignConnection;

    /** 
     * @param Collection<string> $columns 
     * @param Collection<string> $foreignColumns
     */
    public function __construct(
        public readonly string $name,
        public readonly Collection $columns,
        public readonly ?string $foreignSchema,
        public readonly string $foreignTable,
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
            /** @phpstan-ignore property.uninitializedReadonly */
            if (!isset($this->foreignConnection)) {
                /** @phpstan-ignore assign.readOnlyProperty */
                $this->foreignConnection = null;
            }
        }
    }

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }
}
