<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Carbon;

/**
 * Everything the printed commercial sheet (the invoice and the proforma share one layout) shows besides its
 * header: the goods line by line, the amounts, and the reconciliation with what the same client still owed before
 * this order ("rapprochement avant nouvelle commande").
 *
 * Money is integer FCFA. A previous debt is the unpaid balance of an earlier order of the same client that was
 * delivered; a credit is the surplus of an earlier order paid more than it cost. Both are read at the moment the
 * document is issued, and the PDF is frozen with them.
 *
 * @phpstan-type SheetLine array{number: int, designation: string, unit: string, quantity: int, unit_price: int|null, amount: int|null}
 * @phpstan-type DeliveryLine array{number: int, designation: string, unit: string, quantity: int, delivered: int}
 * @phpstan-type SheetDebt array{reference: string, amount: int, partial: bool}
 * @phpstan-type Sheet array{
 *     lines: list<SheetLine>,
 *     total: int,
 *     advance: int,
 *     balance_paid: int,
 *     remaining: int,
 *     previous_debts: list<SheetDebt>,
 *     previous_debt: int,
 *     credit: int,
 *     grand_remaining: int,
 *     ordered_on: string,
 *     delivered_on: string,
 *     cashier: string|null,
 *     zones: list<string>
 * }
 */
class CommercialSheet
{
    /**
     * The sheet of an invoice: what was really paid (advance, balance) and what remains.
     *
     * @return Sheet
     */
    public static function forInvoice(Invoice $invoice, ?string $issuer = null): array
    {
        $order = $invoice->order;
        $order->loadMissing('payments');

        $advance = (int) $order->payments->where('kind', PaymentKind::Advance)->sum('amount');
        $balancePaid = (int) $order->payments->where('kind', PaymentKind::Balance)->sum('amount');
        $refunded = (int) $order->payments->where('kind', PaymentKind::Refund)->sum('amount');

        return self::assemble(
            $order,
            total: $invoice->total,
            advance: max(0, $advance - $refunded),
            balancePaid: $balancePaid,
            remaining: max(0, $invoice->total - $invoice->paid_amount),
            deliveredOn: Carbon::parse($order->deliveries()->max('delivered_on') ?? $order->delivery_week),
            cashier: $issuer ?? ($invoice->created_by === null ? null : User::query()->whereKey($invoice->created_by)->value('name')),
        );
    }

    /**
     * The sheet of a proforma: the advance asked before the delivery and what will remain to pay then.
     *
     * @return Sheet
     */
    public static function forProforma(Order $order, ?string $issuer = null): array
    {
        $advance = min($order->advance_due, $order->total_sale);

        return self::assemble(
            $order,
            total: $order->total_sale,
            advance: $advance,
            balancePaid: 0,
            remaining: max(0, $order->total_sale - $advance),
            deliveredOn: Carbon::parse($order->delivery_week),
            cashier: $issuer,
        );
    }

    /**
     * The sheet of a delivery note: what was ordered and what this delivery brought, product by product, with no
     * price. Each kit holds the same quantity of a product, so a delivery of n kits brings n times that quantity.
     *
     * @return array{lines: list<DeliveryLine>, ordered_on: string, delivered_on: string, zones: list<string>}
     */
    public static function forDelivery(Delivery $delivery): array
    {
        $order = $delivery->order;
        $order->loadMissing('items.product');

        $lines = [];

        foreach ($order->items->values() as $index => $item) {
            $lines[] = [
                'number' => $index + 1,
                'designation' => $item->product->name,
                'unit' => $item->product->unit,
                'quantity' => $item->quantity,
                'delivered' => intdiv($item->quantity, max(1, $order->kits_count)) * $delivery->kits_delivered,
            ];
        }

        return [
            'lines' => $lines,
            'ordered_on' => $order->created_at->format('d/m/Y'),
            'delivered_on' => Carbon::parse($delivery->delivered_on)->format('d/m/Y'),
            'zones' => self::activeZones(),
        ];
    }

    /**
     * @return Sheet
     */
    private static function assemble(Order $order, int $total, int $advance, int $balancePaid, int $remaining, Carbon $deliveredOn, ?string $cashier): array
    {
        $order->loadMissing('items.product');

        $lines = [];

        foreach ($order->items->values() as $index => $item) {
            $amount = $item->line_sale_total;

            $lines[] = [
                'number' => $index + 1,
                'designation' => $item->product->name,
                'unit' => $item->product->unit,
                'quantity' => $item->quantity,
                'unit_price' => $amount !== null && $item->quantity > 0 ? (int) round($amount / $item->quantity) : null,
                'amount' => $amount,
            ];
        }

        [$debts, $credit] = self::previousBalances($order);
        $debt = (int) array_sum(array_column($debts, 'amount'));

        return [
            'lines' => $lines,
            'total' => $total,
            'advance' => $advance,
            'balance_paid' => $balancePaid,
            'remaining' => $remaining,
            'previous_debts' => $debts,
            'previous_debt' => $debt,
            'credit' => $credit,
            'grand_remaining' => max(0, $debt + $remaining - $credit),
            'ordered_on' => $order->created_at->format('d/m/Y'),
            'delivered_on' => $deliveredOn->format('d/m/Y'),
            'cashier' => $cashier,
            'zones' => self::activeZones(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function activeZones(): array
    {
        return array_values(Zone::query()->where('active', true)->orderBy('name')->pluck('name')->map(fn (mixed $name): string => (string) $name)->all());
    }

    /**
     * The earlier delivered orders of the client that are not paid in full, and the surplus of those paid too much.
     *
     * @return array{0: list<SheetDebt>, 1: int}
     */
    private static function previousBalances(Order $order): array
    {
        $debts = [];
        $credit = 0;

        $earlier = Order::query()
            ->where('client_id', $order->client_id)
            ->where('id', '<', $order->id)
            ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Settled, OrderStatus::Closed])
            ->orderBy('id')
            ->get();

        foreach ($earlier as $other) {
            $paid = $other->netPaid();
            $balance = $other->total_sale - $paid;

            if ($balance > 0) {
                $debts[] = ['reference' => (string) $other->reference, 'amount' => $balance, 'partial' => $paid > 0];
            } elseif ($balance < 0) {
                $credit += -$balance;
            }
        }

        return [$debts, $credit];
    }
}
