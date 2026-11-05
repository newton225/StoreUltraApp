<?php

namespace App\Console\Commands;

use App\Support\AuditArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyAuditArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-audit-archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the monthly copies of the audit journal: files intact, chain holding, copies agreeing with the journal';

    /**
     * Execute the console command.
     */
    public function handle(AuditArchive $archive): int
    {
        $problems = $archive->verify();
        $copies = count($archive->manifests());

        if ($problems === []) {
            $this->components->info("The copies of the audit journal are intact ({$copies} month(s)).");

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        Log::critical('The copies of the audit journal are broken.', ['problems' => $problems]);

        return self::FAILURE;
    }
}
