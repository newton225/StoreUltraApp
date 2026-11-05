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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('supply_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kit_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('kits_count');
            $table->date('delivery_week');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('advance_due')->default(0);
            $table->unsignedBigInteger('total_sale');
            $table->unsignedBigInteger('total_purchase_expected');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supply_plan_id', 'delivery_week']);
            $table->index('status');
            $table->index('delivery_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
