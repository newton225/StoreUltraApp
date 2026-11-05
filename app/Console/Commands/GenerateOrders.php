<?php

namespace App\Console\Commands;

use App\Actions\Orders\GenerateDueOrders;
use Illuminate\Console\Command;

class GenerateOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the orders of the supply plans that are due';

    /**
     * Execute the console command.
     */
    public function handle(GenerateDueOrders $generate): int
    {
        $result = $generate->handle();

        $this->components->info("Orders created: {$result['created']}.");

        if ($result['incomplete'] > 0) {
            $this->components->warn("Orders not created because a price is missing: {$result['incomplete']}.");
        }

        return self::SUCCESS;
    }
}
