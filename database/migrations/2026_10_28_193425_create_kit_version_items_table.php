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
        Schema::create('kit_version_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_per_kit');
            $table->unsignedBigInteger('sale_price_per_kit')->nullable();
            $table->timestamps();

            $table->unique(['kit_version_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kit_version_items');
    }
};
