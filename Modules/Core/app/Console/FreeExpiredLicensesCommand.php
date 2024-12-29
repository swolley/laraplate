<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FreeExpiredLicensesCommand extends Command
{
    protected $signature = 'auth:free-expired-licenses';

    protected $description = 'Free expired licenses. <comment>(⛭ Modules\Core)</comment>';

    public function handle(): void
    {
        $this->info('Freeing expired licenses...');

        $sessions = Redis::connection(config('session.connection'))->keys('*');
        $updated = user_class()::query()->whereNotNull('license_id')->whereNotIn('license_id', $sessions)->update(['license_id' => null]);

        $this->info('Freed ' . $updated . ' expired licenses.');
    }
}
