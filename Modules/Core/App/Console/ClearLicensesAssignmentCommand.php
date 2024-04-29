<?php

namespace Modules\Core\App\Console;

use Illuminate\Console\Command;
use Modules\Core\App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ClearLicensesAssignmentCommand extends Command
{
    protected $signature = 'auth:clear-licenses';

    protected $description = 'Clear the user assigned licenses.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $query = User::whereNotNull('license_id');
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
