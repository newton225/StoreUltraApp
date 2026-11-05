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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->unsignedBigInteger('amount');
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'kind']);
            $table->index('paid_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
