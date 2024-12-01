<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\TreeRequest;

class TreeRequestData extends DetailRequestData
{
    public bool $parents;

    public bool $children;

    /**
     * @param string|string[] $primaryKey
     */
    public function __construct(TreeRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->parents = $validated['parents'] ?? false;
        $this->children = $validated['children'] ?? false;
    }
}
