<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Exceptions;

use RuntimeException;

class StaleModelLockingException extends RuntimeException
{
}
