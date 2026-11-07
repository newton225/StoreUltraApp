<?php

namespace App\Enums;

/**
 * The fine permissions of a profile: one per thing a user can do inside a menu. A menu (`Permission`) opens
 * the screens; an action is needed on top of it to do something there. The catalogue is closed and lives in
 * the code, like the menus: an action is never created from the screens.
 */
enum PermissionAction: string
{
    case OrdersCreate = 'orders.create';
    case OrdersRecordPayment = 'orders.record_payment';
    case OrdersRecordDelivery = 'orders.record_delivery';
    case OrdersAdvance = 'orders.advance';
    case OrdersFinalize = 'orders.finalize';
    case OrdersCancel = 'orders.cancel';
    case OrdersBatch = 'orders.batch';
    case OrdersIssueDocuments = 'orders.issue_documents';
    case OrdersCancelDocuments = 'orders.cancel_documents';
    case OrdersDownloadZip = 'orders.download_zip';
    case OrdersPlans = 'orders.plans';
    case OrdersFne = 'orders.fne';

    case PurchasesGenerate = 'purchases.generate';
    case PurchasesSend = 'purchases.send';
    case PurchasesReceive = 'purchases.receive';
    case PurchasesCancel = 'purchases.cancel';
    case PurchasesPayShop = 'purchases.pay_shop';

    case CashRecord = 'cash.record';
    case CashCancel = 'cash.cancel';
    case CashClose = 'cash.close';
    case CashLinkExpenses = 'cash.link_expenses';

    case ExpensesCreate = 'expenses.create';
    case ExpensesCancel = 'expenses.cancel';
    case ExpensesViewProof = 'expenses.view_proof';

    case CatalogShops = 'catalog.shops';
    case CatalogProducts = 'catalog.products';
    case CatalogPrices = 'catalog.prices';
    case CatalogKits = 'catalog.kits';
    case CatalogArchive = 'catalog.archive';

    /**
     * The menu the action lives in: without the menu, the action is of no use.
     */
    public function menu(): Permission
    {
        return match (true) {
            str_starts_with($this->value, 'orders.') => Permission::ManageOrders,
            str_starts_with($this->value, 'purchases.') => Permission::ManagePurchases,
            str_starts_with($this->value, 'cash.') => Permission::ManageCash,
            str_starts_with($this->value, 'expenses.') => Permission::ManageExpenses,
            default => Permission::ManageCatalog,
        };
    }

    /**
     * Every action, as the values stored in a profile.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The actions of a menu.
     *
     * @return list<self>
     */
    public static function ofMenu(Permission $menu): array
    {
        return array_values(array_filter(self::cases(), fn (self $action): bool => $action->menu() === $menu));
    }

    /**
     * Every action and the menu it belongs to, grouped by menu value, for the screens.
     *
     * @return array<string, list<string>>
     */
    public static function byMenu(): array
    {
        $grouped = [];

        foreach (self::cases() as $action) {
            $grouped[$action->menu()->value][] = $action->value;
        }

        return $grouped;
    }
}
