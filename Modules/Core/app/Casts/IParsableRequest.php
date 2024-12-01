<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

interface IParsableRequest
{
    public function parsed(): CrudRequestData;
}
