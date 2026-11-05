<?php

namespace Database\Seeders;

use App\Actions\Clients\ImportTpeClients;
use Illuminate\Database\Seeder;

class InitialClientsSeeder extends Seeder
{
    /**
     * Load the sub-prefectures and the small businesses (TPE) of the project's workbook, each with its manager
     * and assistants. The workbook is `database/seeders/data/Liste_TPE_Hambol.xlsx`, or the file named by
     * `SEED_TPE_FILE` when the data must stay outside the code. Running it again adds nothing: a TPE already
     * registered in its zone is skipped. A missing file is reported and skipped, it never stops the deployment.
     */
    public function run(ImportTpeClients $import): void
    {
        $path = (string) (config('app.seed_tpe_file') ?: database_path('seeders/data/Liste_TPE_Hambol.xlsx'));

        if (! is_file($path)) {
            $this->command?->warn("TPE workbook not found, no client loaded: {$path}");

            return;
        }

        $result = $import->handle($path, apply: true);

        $this->command?->info("TPE loaded: {$result['created']} created, {$result['skipped']} already there, {$result['members']} people.");
    }
}
