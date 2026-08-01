<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Seeding\SeedLedger;
use Modules\Core\Seeding\SeedOrchestrator;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $exit_code = app(SeedOrchestrator::class)
            ->withCommand($this->command)
            ->run($this->resumeRunId());

        if ($exit_code !== 0) {
            throw new RuntimeException('Seeding failed; see the run ledger for the failing node.');
        }
    }

    /**
     * Resolve --resume, guarding hasOption() first: DatabaseSeeder can be
     * invoked through db:seed (Modules\Core\Console\SeedCommand, which
     * declares --resume), but also directly without any command at all
     * (e.g. app(DatabaseSeeder::class)->run() in a script or test). Calling
     * option() on an undefined option throws, so this must not assume the
     * option exists just because a command instance is present.
     */
    private function resumeRunId(): ?string
    {
        $wants_resume = $this->command !== null
            && $this->command->hasOption('resume')
            && $this->command->option('resume') === true;

        return $wants_resume ? app(SeedLedger::class)->lastFailedRunId() : null;
    }
}
