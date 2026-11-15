<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit prices are kept exactly as typed (12.5 FCFA); the amounts that are added up stay whole FCFA.
     *
     * @var array<string, array<string, bool>> table => column => nullable
     */
    private array $columns = [
        'product_prices' => ['purchase_price' => false, 'sale_price' => true],
        'kit_version_items' => ['sale_price_per_kit' => true],
        'kit_versions' => ['fixed_sale_price' => true],
        'order_items' => ['unit_purchase_price' => false],
        'purchase_order_items' => ['unit_price_expected' => false, 'unit_price_actual' => true],
    ];

    public function up(): void
    {
        $this->convert(fn (Blueprint $table, string $column) => $table->decimal($column, 14, 2));
    }

    public function down(): void
    {
        $this->convert(fn (Blueprint $table, string $column) => $table->unsignedBigInteger($column));
    }

    /**
     * @param  callable(Blueprint, string): ColumnDefinition  $definition
     */
    private function convert(callable $definition): void
    {
        foreach ($this->columns as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns, $definition): void {
                foreach ($columns as $column => $nullable) {
                    $definition($table, $column)->nullable($nullable)->change();
                }
            });
        }
    }
};
