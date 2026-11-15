<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correcting the stock by hand is a new action of the purchases menu: the profiles that already hold the menu
 * receive it, so nothing changes for anyone on the day of the update. The owner can take it away afterwards.
 */
return new class extends Migration
{
    private const ACTION = 'stock.adjust';

    public function up(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = json_decode($profile->permissions, true) ?: [];

            if (in_array('manage_purchases', $permissions, true)) {
                $permissions[] = self::ACTION;

                DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
            }
        });
    }

    public function down(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = array_values(array_diff(json_decode($profile->permissions, true) ?: [], [self::ACTION]));

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
