<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

use Illuminate\Support\Collection;
use Modules\Core\Grids\Components\Field;

class Relation extends Entity
{
    public readonly RelationInfo $info;

    /**
     * @param  string  $path  relation path (prefix before current relation name in full relation name)
     * @param  RelationInfo  $info  data about current relation from her parent point of view
     * @param Field[]|Collection<string, Field>|null $fields fields's list
     * */
    public function __construct(string $path, RelationInfo $info, ?iterable $fields = null)
    {
        parent::__construct($info->getModel());
        $this->path = $path;
        $this->info = $info;
        $this->name = $info->getName();
        if ($fields) {
            $this->setFields($fields);
        }
    }
}
