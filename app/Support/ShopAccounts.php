<?php

namespace App\Support;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Shop;

class ShopAccounts
{
    /**
     * What each shop was asked for, what it delivered, what was paid and what is still owed.
     *
     * @return array<int, array{shop_id: int, name: string, ordered: int, received: int, paid: int, debt: int}>
     */
    public function all(): array
    {
        return Shop::query()
            ->orderBy('name')
            ->get()
            ->map(function (Shop $shop): array {
                $placed = PurchaseOrder::query()
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', [PurchaseOrderStatus::Sent->value, PurchaseOrderStatus::PartiallyReceived->value, PurchaseOrderStatus::Received->value]);

                $ordered = (int) (clone $placed)->sum('expected_total');
                $received = (int) (clone $placed)->sum('actual_total');
                $paid = $shop->netPaid();

                return [
                    'shop_id' => $shop->id,
                    'name' => $shop->name,
                    'ordered' => $ordered,
                    'received' => $received,
                    'paid' => $paid,
                    'debt' => $received - $paid,
                ];
            })
            ->filter(fn (array $account): bool => $account['ordered'] > 0 || $account['paid'] !== 0)
            ->values()
            ->all();
    }
}
