<?php

namespace App\Enums;

/**
 * What a profile can be allowed to do. The catalogue is closed and lives in the code: a permission is
 * never created from the screens, so access control cannot be forged by typing a new one. Managing
 * users, the settings, the sub-prefectures and the audit log is not in it: those stay with the owner.
 */
enum Permission: string
{
    case ManageClients = 'manage_clients';
    case ManageOrders = 'manage_orders';
    case ManagePurchases = 'manage_purchases';
    case ManageExpenses = 'manage_expenses';
    case ManageCash = 'manage_cash';
    case ManageCatalog = 'manage_catalog';
    case ViewProfit = 'view_profit';

    /**
     * Every permission, as the values stored in a profile.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
