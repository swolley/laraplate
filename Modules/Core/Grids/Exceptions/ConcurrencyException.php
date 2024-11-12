<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ConcurrencyException extends \Exception
{
    public function __construct(string $message = "You don't have the last version of the records")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
