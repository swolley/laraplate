<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

use Illuminate\Http\Request;
use Modules\Core\App\Models\DynamicEntity;
use Illuminate\Database\Eloquent\Model;

class CrudRequestData
{
    public readonly Request $request;

    public readonly string $mainEntity;

    public readonly ?string $connection;

    public readonly Model $model;

    public readonly string|array $primaryKey;

    public function __construct(Request $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        $this->request = $request;
        $this->mainEntity = $mainEntity;
        $this->primaryKey = $primaryKey;
        $this->connection = $validated['connection'] ?? null;
        $this->model = DynamicEntity::resolve($this->mainEntity, $this->connection, request: $this->request);
    }
}
