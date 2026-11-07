<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deliveries, payments and documents became menus of their own, each with an export action. Deliveries were open
 * to every profile; payments and documents to the profiles that manage orders. Each existing profile receives
 * the menus it could already open, so nobody loses anything on the day of the update.
 */
return new class extends Migration
{
    /**
     * The menus and their actions, with the menu that gave access until now (null: every profile).
     *
     * @var list<array{given_by: string|null, rights: list<string>}>
     */
    private const MENUS = [
        ['given_by' => null, 'rights' => ['manage_deliveries', 'deliveries.export']],
        ['given_by' => 'manage_orders', 'rights' => ['manage_payments', 'payments.export']],
        ['given_by' => 'manage_orders', 'rights' => ['manage_documents', 'documents.export']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = json_decode($profile->permissions, true) ?: [];

            foreach (self::MENUS as $menu) {
                if ($menu['given_by'] === null || in_array($menu['given_by'], $permissions, true)) {
                    $permissions = [...$permissions, ...$menu['rights']];
                }
            }

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $rights = array_merge(...array_column(self::MENUS, 'rights'));

        DB::table('profiles')->orderBy('id')->each(function (object $profile) use ($rights): void {
            $permissions = array_values(array_diff(json_decode($profile->permissions, true) ?: [], $rights));

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
