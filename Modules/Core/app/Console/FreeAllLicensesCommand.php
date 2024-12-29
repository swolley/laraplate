<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FreeAllLicensesCommand extends Command
{
    protected $signature = 'auth:free-all-licenses';

    protected $description = 'Free all the assigned licenses. <comment>(⛭ Modules\Core)</comment>';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $user_class = user_class();
            if (!$user_class instanceof \Modules\Core\Models\User) {
                $this->output->error('User class is not Modules\Core\Models\User');
                return static::SUCCESS;
            }
            $query = $user_class::query()->whereNotNull('license_id');
            $assigned = $query->count();
            if ($assigned) {
                $query->update(['license_id' => null]);
                $message = "Cleared $assigned licenses";
                $this->output->info($message);
                Log::info($message);
            } else {
                $message = 'No licenses to clear';
                $this->output->info($message);
                Log::info($message);
            }

            return static::SUCCESS;
        } catch (\Throwable $ex) {
            $message = $ex->getMessage();
            $this->output->error($message);
            Log::error($message);

            return static::FAILURE;
        }
    }
}
