<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The clients became a menu of their own with their actions. Until now every profile could open them, so every
 * existing profile receives the menu and its actions: nobody loses anything on the day of the update, and the
 * owner can then take the menu or an action away from a profile.
 */
return new class extends Migration
{
    /**
     * The menu and its actions, as they were when this migration was written.
     *
     * @var list<string>
     */
    private const RIGHTS = ['manage_clients', 'clients.create', 'clients.update', 'clients.change_status', 'clients.export'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = [...(json_decode($profile->permissions, true) ?: []), ...self::RIGHTS];

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = array_values(array_diff(json_decode($profile->permissions, true) ?: [], self::RIGHTS));

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
