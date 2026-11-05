<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\AuditChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyAuditLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-audit-log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check that no entry of the audit log was changed, removed or slipped in (fails when the chain is broken)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $problems = [];
        $previous = null;
        $count = 0;

        AuditLog::query()->orderBy('id')->each(function (AuditLog $entry) use (&$previous, &$problems, &$count): void {
            $count++;

            $expected = AuditChain::hash(
                $previous,
                $entry->user_id,
                $entry->action,
                $entry->subject_type,
                $entry->subject_id,
                $entry->before,
                $entry->after,
                $entry->created_at?->format('Y-m-d H:i:s') ?? '',
            );

            if ($entry->hash !== $expected) {
                $problems[] = "Entry #{$entry->id} does not match its fingerprint: it was changed, or an entry before it was removed or added.";
            }

            $previous = $entry->hash;
        });

        $head = DB::table('audit_chain')->where('id', 1)->value('last_hash');

        if ($head !== $previous) {
            $problems[] = 'The head of the chain does not match the last entry: entries at the end were removed.';
        }

        if ($problems === []) {
            $this->components->info("The audit log is intact ({$count} entries).");

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        Log::critical('The audit log chain is broken.', ['problems' => $problems]);

        return self::FAILURE;
    }
}
