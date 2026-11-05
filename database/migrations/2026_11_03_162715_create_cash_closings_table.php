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
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->date('closed_on')->unique();
            $table->bigInteger('expected_amount');
            $table->bigInteger('counted_amount');
            $table->bigInteger('difference');
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('closing_id')->nullable()->after('expense_id')->unique()->constrained('cash_closings')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closing_id');
        });

        Schema::dropIfExists('cash_closings');
    }
};
