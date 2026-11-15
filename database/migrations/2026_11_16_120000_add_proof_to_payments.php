<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment can carry a proof (a screenshot or a PDF). Two payments can never share the same
     * proof (`proof_hash`, the fingerprint of the stored file) nor the same transaction reference
     * for the same method of payment (`reference_key`, the reference without spaces, signs or case).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('proof_path')->nullable();
            $table->string('proof_hash', 64)->nullable()->unique();
            $table->string('reference_key', 100)->nullable();
        });

        $seen = [];

        DB::table('payments')->where('kind', '!=', 'refund')->whereNotNull('reference')->orderBy('id')
            ->get(['id', 'payment_method_id', 'reference'])
            ->each(function (object $payment) use (&$seen): void {
                $key = Payment::referenceKey($payment->reference);
                $slot = $payment->payment_method_id.'|'.$key;

                // History that already holds the same reference twice keeps it on the first payment only.
                if ($key === null || isset($seen[$slot])) {
                    return;
                }

                $seen[$slot] = true;
                DB::table('payments')->where('id', $payment->id)->update(['reference_key' => $key]);
            });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(['payment_method_id', 'reference_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['payment_method_id', 'reference_key']);
            $table->dropUnique(['proof_hash']);
            $table->dropColumn(['proof_path', 'proof_hash', 'reference_key']);
        });
    }
};
