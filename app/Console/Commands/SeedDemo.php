<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load fictitious demonstration data (TPE, orders, advances, deliveries, cash); refused in production, safe to run again';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('The demonstration data is never loaded in production.');

            return self::FAILURE;
        }

        $this->components->warn('The demonstration records cannot be removed afterwards: use a database kept for demonstrations.');

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true]);

        return self::SUCCESS;
    }
}
