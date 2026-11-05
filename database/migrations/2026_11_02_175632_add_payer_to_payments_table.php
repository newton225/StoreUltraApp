<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who handed over the money (a relative, a representative…) and who entered the payment in the app
     * (the logged-in user, which may differ from the person who collected the money).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_name', 120)->nullable()->after('reference');
            $table->string('payer_phone', 30)->nullable()->after('payer_name');
            $table->foreignId('recorded_by')->nullable()->after('collected_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['payer_name', 'payer_phone']);
        });
    }
};
