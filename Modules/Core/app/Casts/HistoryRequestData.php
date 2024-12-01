<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\HistoryRequest;

class HistoryRequestData extends DetailRequestData
{
    public readonly ?int $limit;

    /**
     * @param string|string[] $primaryKey
     */
    public function __construct(HistoryRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->limit = $validated['limit'];
    }
}
