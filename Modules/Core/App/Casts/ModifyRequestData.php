<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

use Illuminate\Support\Str;
use Modules\Core\App\Http\Requests\ModifyRequest;

class ModifyRequestData extends CrudRequestData
{
    public array $changes = [];

    public function __construct(ModifyRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);

        foreach ($validated as $property => $value) {
            $this->changes[Str::replaceFirst("{$this->mainEntity}.", '', $property)] = $value;
        }
    }
}
