<?php

use App\Support\AuditChain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The journal can only be added to: each entry also carries the address and browser of the request and
 * the hash that chains it to the one before, the head of the chain is kept apart, and the database
 * itself refuses to change or remove an entry. The entries already there are chained in the order they
 * were written, as they stand today.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // The journal must outlive the account of the person it names: no foreign key, so that
            // deleting a user never has to touch an entry.
            $table->dropForeign(['user_id']);
            $table->string('ip_address', 45)->nullable()->after('after');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->char('hash', 64)->nullable()->after('user_agent');
        });

        Schema::create('audit_chain', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('last_hash', 64)->nullable();
        });

        $this->chainExistingEntries();
        $this->forbidChanges();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->allowChanges();

        Schema::dropIfExists('audit_chain');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'hash']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function chainExistingEntries(): void
    {
        $previous = null;

        DB::table('audit_logs')->orderBy('id')->each(function (object $entry) use (&$previous): void {
            $decode = fn (?string $json): ?array => $json === null ? null : json_decode($json, true);

            $previous = AuditChain::hash(
                $previous,
                $entry->user_id === null ? null : (int) $entry->user_id,
                $entry->action,
                $entry->subject_type,
                (int) $entry->subject_id,
                $decode($entry->before),
                $decode($entry->after),
                (string) $entry->created_at,
            );

            DB::table('audit_logs')->where('id', $entry->id)->update(['hash' => $previous]);
        });

        DB::table('audit_chain')->insert(['id' => 1, 'last_hash' => $previous]);
    }

    private function forbidChanges(): void
    {
        $message = 'The audit log can only be added to.';

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, '{$message}'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, '{$message}'); END");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION audit_logs_refuse_changes() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION '{$message}'; END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER audit_logs_no_change BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_refuse_changes()');
            DB::unprepared('CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_refuse_changes()');
        }
    }

    private function allowChanges(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_change ON audit_logs');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_refuse_changes()');
        }
    }
};
