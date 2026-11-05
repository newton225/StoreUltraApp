<?php

namespace App\Actions\Payments;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Exceptions\InvalidPayment;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\DocumentNumbers;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function __construct(private DocumentNumbers $numbers, private TransitionOrder $transition) {}

    /**
     * Record money received (an advance or the balance) or given back, issue its receipt number,
     * and move the order forward when the advance or the whole price is now covered.
     *
     * Sending the same idempotency key again for the same order gives back the payment already
     * recorded instead of a second one: a double click or a replayed request takes the money once.
     *
     * @throws InvalidPayment when the payment is not allowed in the state of the order
     */
    public function handle(
        Order $order,
        PaymentKind $kind,
        int $amount,
        PaymentMethod $method,
        ?string $reference,
        CarbonInterface $paidOn,
        ?User $user = null,
        ?Payment $reverses = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?string $payerName = null,
        ?string $payerPhone = null,
        ?int $collectedBy = null,
    ): Payment {
        return DB::transaction(function () use ($order, $kind, $amount, $method, $reference, $paidOn, $user, $reverses, $notes, $idempotencyKey, $payerName, $payerPhone, $collectedBy): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $netPaid = $order->netPaid();

            if ($idempotencyKey !== null) {
                $already = Payment::query()->where('order_id', $order->id)->where('idempotency_key', $idempotencyKey)->first();

                if ($already !== null) {
                    return $already;
                }
            }

            if ($amount <= 0) {
                throw new InvalidPayment('amount', 'payments.errors.amount_positive');
            }

            if ($kind === PaymentKind::Refund) {
                $this->checkRefund($order, $amount, $netPaid, $reverses);
            } else {
                $this->checkIncoming($order, $kind, $amount, $netPaid);
            }

            $payment = Payment::create([
                'receipt_number' => $this->numbers->next('receipt'),
                'order_id' => $order->id,
                'kind' => $kind,
                'amount' => $amount,
                'payment_method_id' => $method->id,
                'reference' => $reference,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'paid_on' => $paidOn->toDateString(),
                'collected_by' => $collectedBy ?? $user?->id,
                'recorded_by' => $user?->id,
                'reverses_payment_id' => $reverses?->id,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditLog::record('payment.recorded', $payment, [], [
                'receipt_number' => $payment->receipt_number,
                'order_id' => $order->id,
                'kind' => $kind->value,
                'amount' => $amount,
                'method' => $method->code,
                'payer_name' => $payerName,
                'collected_by' => $payment->collected_by,
                'reverses_payment_id' => $reverses?->id,
            ], $user);

            $this->advanceOrder($order, $kind, $netPaid + ($kind === PaymentKind::Refund ? -$amount : $amount), $user);

            return $payment;
        });
    }

    private function checkIncoming(Order $order, PaymentKind $kind, int $amount, int $netPaid): void
    {
        if (! in_array($order->status, $kind->allowedStatuses() ?? [], true)) {
            throw new InvalidPayment('kind', 'payments.errors.kind_status');
        }

        if ($amount > $order->total_sale - $netPaid) {
            throw new InvalidPayment('amount', 'payments.errors.amount_too_high');
        }
    }

    private function checkRefund(Order $order, int $amount, int $netPaid, ?Payment $reverses): void
    {
        if ($reverses !== null) {
            $alreadyReversed = Payment::query()->where('reverses_payment_id', $reverses->id)->exists();

            if ($reverses->order_id !== $order->id || $reverses->kind === PaymentKind::Refund || $alreadyReversed) {
                throw new InvalidPayment('reverses_payment_id', 'payments.errors.reversal_invalid');
            }

            if ($amount !== $reverses->amount) {
                throw new InvalidPayment('amount', 'payments.errors.reversal_amount');
            }
        }

        if ($amount > $netPaid) {
            throw new InvalidPayment('amount', 'payments.errors.refund_too_high');
        }
    }

    /**
     * Cover the advance and the order moves to advance received; cover the whole price at delivery
     * and it moves to settled.
     */
    private function advanceOrder(Order $order, PaymentKind $kind, int $netPaid, ?User $user): void
    {
        if ($kind === PaymentKind::Refund) {
            return;
        }

        if ($order->status === OrderStatus::ProformaIssued && $netPaid >= $order->advance_due) {
            $this->transition->handle($order, OrderStatus::AdvanceReceived, null, $user);
        } elseif ($order->status === OrderStatus::Delivered && $netPaid >= $order->total_sale) {
            $this->transition->handle($order, OrderStatus::Settled, null, $user);
        }
    }
}
