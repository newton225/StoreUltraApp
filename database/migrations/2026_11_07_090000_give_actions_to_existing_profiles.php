<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Profiles are now managed by menu (what opens) and by action (what can be done inside). Every profile that
 * holds a menu receives all the actions of that menu, so nothing changes for anyone on the day of the update;
 * the owner can then take actions away one by one.
 */
return new class extends Migration
{
    /**
     * The actions of each menu, as they were when this migration was written.
     *
     * @var array<string, list<string>>
     */
    private const ACTIONS = [
        'manage_orders' => [
            'orders.create', 'orders.record_payment', 'orders.record_delivery', 'orders.advance', 'orders.finalize',
            'orders.cancel', 'orders.batch', 'orders.issue_documents', 'orders.cancel_documents', 'orders.download_zip',
            'orders.plans', 'orders.fne',
        ],
        'manage_purchases' => ['purchases.generate', 'purchases.send', 'purchases.receive', 'purchases.cancel', 'purchases.pay_shop'],
        'manage_cash' => ['cash.record', 'cash.cancel', 'cash.close', 'cash.link_expenses'],
        'manage_expenses' => ['expenses.create', 'expenses.cancel', 'expenses.view_proof'],
        'manage_catalog' => ['catalog.shops', 'catalog.products', 'catalog.prices', 'catalog.kits', 'catalog.archive'],
    ];

    /**
     * What the field agent has always done without the orders menu.
     *
     * @var list<string>
     */
    private const FIELD_AGENT = ['orders.record_payment', 'orders.record_delivery', 'orders.issue_documents', 'orders.download_zip'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $permissions = json_decode($profile->permissions, true) ?: [];

            foreach (self::ACTIONS as $menu => $actions) {
                if (in_array($menu, $permissions, true)) {
                    $permissions = [...$permissions, ...$actions];
                }
            }

            if ($profile->code === 'agent') {
                $permissions = [...$permissions, ...self::FIELD_AGENT];
            }

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $actions = [...array_merge(...array_values(self::ACTIONS)), ...self::FIELD_AGENT];

        DB::table('profiles')->orderBy('id')->each(function (object $profile) use ($actions): void {
            $permissions = array_values(array_diff(json_decode($profile->permissions, true) ?: [], $actions));

            DB::table('profiles')->where('id', $profile->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
