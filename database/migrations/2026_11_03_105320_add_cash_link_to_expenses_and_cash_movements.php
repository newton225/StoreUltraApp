<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('paid_from_cash')->default(false)->after('amount');
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->unique()->after('reference')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('paid_from_cash');
        });
    }
};
