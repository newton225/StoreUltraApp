<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase order is either made for customer orders (generated) or to fill the stock (restock, made by hand).
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('kind', 10)->default('orders');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
