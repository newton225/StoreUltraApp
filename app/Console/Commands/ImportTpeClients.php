<?php

namespace App\Console\Commands;

use App\Actions\Clients\ImportTpeClients as ImportTpe;
use Illuminate\Console\Command;
use RuntimeException;

class ImportTpeClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:import-tpe {file : Path of the xlsx workbook of the TPE} {--apply : Write the clients (without it, only a simulation)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register the TPE of a workbook as clients, with their manager (assistants are added later from the client page)';

    /**
     * Execute the console command.
     */
    public function handle(ImportTpe $import): int
    {
        $apply = (bool) $this->option('apply');

        try {
            $result = $import->handle((string) $this->argument('file'), $apply);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(($apply ? 'Imported' : 'Simulation (nothing written, use --apply)').": {$result['created']} TPE, {$result['members']} managers, already registered: {$result['skipped']}.");
        $this->line('Zones: '.implode(', ', $result['zones']));

        foreach ($result['without_phone'] as $name) {
            $this->components->warn("No phone number for the manager of: {$name}");
        }

        foreach ($result['shared_phones'] as $phone => $names) {
            $this->components->warn("Phone {$phone} is already used by another client, not repeated for: ".implode(', ', $names));
        }

        foreach ($result['placeholders'] as $name => $phone) {
            $this->components->info("Placeholder phone {$phone} given to {$name}: to be replaced by the real number.");
        }

        return self::SUCCESS;
    }
}
