<?php

namespace App\Actions\Deliveries;

use App\Actions\Orders\TransitionOrder;
use App\Actions\Payments\RecordPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Exceptions\InvalidDelivery;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecordDelivery
{
    public function __construct(private TransitionOrder $transition, private RecordPayment $recordPayment) {}

    /**
     * Record kits handed over. When every kit has been delivered the order moves to delivered, and
     * to settled if it is paid in full. The balance can be collected at the same time: if that payment
     * is refused, nothing is recorded. Sending the same idempotency key again for the same order gives
     * back the delivery already recorded, with its balance, instead of a second one: a double click or
     * a replayed request hands the kits over, and takes the money, once.
     *
     * @param  array{amount: int, method: PaymentMethod, reference: string|null}|null  $payment
     *
     * @throws InvalidDelivery when the order cannot be delivered or too many kits are given
     */
    public function handle(
        Order $order,
        int $kits,
        CarbonInterface $deliveredOn,
        string $receivedBy,
        ?string $signature = null,
        ?User $agent = null,
        ?string $notes = null,
        ?User $user = null,
        ?array $payment = null,
        ?string $idempotencyKey = null,
    ): Delivery {
        $signaturePath = null;

        try {
            return $this->write($order, $kits, $deliveredOn, $receivedBy, $signature, $agent, $notes, $user, $payment, $idempotencyKey, $signaturePath);
        } catch (Throwable $exception) {
            // Whatever failed, the delivery is not kept: neither is the signature written for it.
            if ($signaturePath !== null) {
                Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }
    }

    /**
     * Write the delivery, its payment and its trace together. The signature file goes last, right
     * before the row that points to it, and its path is handed back so that a failure can remove it.
     *
     * @param  array{amount: int, method: PaymentMethod, reference: string|null}|null  $payment
     */
    private function write(
        Order $order,
        int $kits,
        CarbonInterface $deliveredOn,
        string $receivedBy,
        ?string $signature,
        ?User $agent,
        ?string $notes,
        ?User $user,
        ?array $payment,
        ?string $idempotencyKey,
        ?string &$signaturePath,
    ): Delivery {
        return DB::transaction(function () use ($order, $kits, $deliveredOn, $receivedBy, $signature, $agent, $notes, $user, $payment, $idempotencyKey, &$signaturePath): Delivery {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $already = Delivery::query()->where('order_id', $order->id)->where('idempotency_key', $idempotencyKey)->first();

                if ($already !== null) {
                    return $already;
                }
            }

            if ($order->status !== OrderStatus::Received) {
                throw new InvalidDelivery('status', 'deliveries.errors.status');
            }

            $remaining = $order->remainingKits();

            if ($kits < 1 || $kits > $remaining) {
                throw new InvalidDelivery('kits_delivered', 'deliveries.errors.kits');
            }

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'kits_delivered' => $kits,
                'delivered_on' => $deliveredOn->toDateString(),
                'agent_id' => $agent?->id,
                'received_by' => $receivedBy,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $user?->id,
            ]);

            $delivery->forceFill(['reference' => sprintf('LIV-%06d', $delivery->id)])->save();

            if ($kits === $remaining) {
                $this->transition->handle($order, OrderStatus::Delivered, null, $user);
            }

            if ($payment !== null) {
                $this->recordPayment->handle(
                    order: $order,
                    kind: PaymentKind::Balance,
                    amount: $payment['amount'],
                    method: $payment['method'],
                    reference: $payment['reference'],
                    paidOn: $deliveredOn,
                    user: $user,
                );
            }

            $order->refresh();

            if ($order->status === OrderStatus::Delivered && $order->netPaid() >= $order->total_sale) {
                $this->transition->handle($order, OrderStatus::Settled, null, $user);
            }

            AuditLog::record('delivery.recorded', $delivery, [], [
                'reference' => $delivery->reference,
                'order_id' => $order->id,
                'kits_delivered' => $kits,
                'received_by' => $receivedBy,
                'signed' => $signature !== null,
            ], $user);

            if ($signature !== null) {
                $signaturePath = "signatures/delivery-{$delivery->id}.png";
                Storage::disk('local')->put($signaturePath, $signature);
                $delivery->forceFill(['signature_path' => $signaturePath, 'signed_at' => now()])->save();
            }

            return $delivery;
        });
    }
}
