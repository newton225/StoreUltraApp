<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The cash box had no right of its own: it opened with "manage logistics costs". Every profile that holds that
 * right keeps its access by receiving the new "manage the cash box" right, so nothing changes for anyone on the
 * day of the update; the owner can then make profiles with one right and not the other.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = json_decode($profile->permissions, true) ?: [];

            if (in_array('manage_expenses', $permissions, true) && ! in_array('manage_cash', $permissions, true)) {
                $permissions[] = 'manage_cash';

                DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = array_values(array_diff(json_decode($profile->permissions, true) ?: [], ['manage_cash']));

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
