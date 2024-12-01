<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SelectRequest;

class DetailRequestData extends SelectRequestData
{
    /**
     * @param string|string[] $primaryKey
     */
    public function __construct(SelectRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
    }
}
