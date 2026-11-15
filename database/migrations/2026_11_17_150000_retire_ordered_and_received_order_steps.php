<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Purchases and stock are followed apart from the orders of the clients: an order no longer goes through
 * "ordered to the shops" and "received". The orders that were there go back to "advance received" (from where kits
 * can be delivered). The history of steps already taken is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereIn('status', ['ordered', 'received'])->update(['status' => 'advance_received']);
    }

    public function down(): void
    {
        // The steps taken are not recoverable: nothing to restore.
    }
};
