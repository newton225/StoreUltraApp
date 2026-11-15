<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an order line needs to be delivered, product by product (the line itself, or the components of a
     * manufactured product), and where it comes from: the stock, or a purchase. The cost of the line is built from it.
     */
    public function up(): void
    {
        Schema::create('order_item_needs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_from_stock')->default(0);
            $table->decimal('stock_unit_cost', 14, 2)->nullable();
            $table->unsignedInteger('quantity_purchased')->default(0);
            $table->decimal('purchase_unit_cost', 14, 2)->nullable();
            $table->decimal('planned_unit_price', 14, 2);
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_needs');
    }
};
