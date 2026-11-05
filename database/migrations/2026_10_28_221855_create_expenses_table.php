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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('incurred_on');
            $table->string('scope', 10);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('week')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('incurred_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
