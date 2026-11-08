<?php

namespace App\Console\Commands;

use App\Support\ProductionCheck;
use Illuminate\Console\Command;

class CheckProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the settings against what a production server needs (fails on a dangerous one)';

    /**
     * Execute the console command.
     */
    public function handle(ProductionCheck $check): int
    {
        $result = $check->run();
        $role = $check->databaseRole();
        $result = ['errors' => [...$result['errors'], ...$role['errors'], ...$check->pendingMigrations()], 'warnings' => [...$result['warnings'], ...$role['warnings']]];

        foreach ($result['errors'] as $error) {
            $this->components->error($error);
        }

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        if ($result['errors'] !== []) {
            return self::FAILURE;
        }

        $this->components->info($result['warnings'] === [] ? 'The configuration is safe for production.' : 'No dangerous setting; see the warnings above.');

        return self::SUCCESS;
    }
}
