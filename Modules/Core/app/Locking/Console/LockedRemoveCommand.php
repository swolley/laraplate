<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

class LockedRemoveCommand extends LockedAddCommand
{
    public $signature = 'lock:locked-remove {model} {--namespace=}';

    public $description = 'Add a migration to remove locked columns to a model <comment>(⛭ Modules\Core)</comment>';

    protected $operation = 'remove';
}
