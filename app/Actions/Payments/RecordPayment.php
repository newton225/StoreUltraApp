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
use App\Support\UploadedFiles;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecordPayment
{
    public function __construct(private DocumentNumbers $numbers, private TransitionOrder $transition, private UploadedFiles $uploads) {}

    /**
     * Record money received (an advance or the balance) or given back, issue its receipt number,
     * and move the order forward when the advance or the whole price is now covered.
     *
     * Sending the same idempotency key again for the same order gives back the payment already
     * recorded instead of a second one: a double click or a replayed request takes the money once.
     *
     * A proof (screenshot or PDF) can go with it. The same transaction reference for the same method of
     * payment, or the same proof file, is refused when another payment already holds it: money counted
     * twice. The refusal is written to the audit log.
     *
     * @throws InvalidPayment when the payment is not allowed in the state of the order, or is a duplicate
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
        ?UploadedFile $proof = null,
    ): Payment {
        $proofPath = $proof === null ? null : $this->uploads->store($proof, 'payments');
        $proofHash = $proofPath === null ? null : hash_file('sha256', Storage::disk('local')->path($proofPath));

        try {
            $payment = $this->record($order, $kind, $amount, $method, $reference, $paidOn, $user, $reverses, $notes, $idempotencyKey, $payerName, $payerPhone, $collectedBy, $proofPath, $proofHash ?: null);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            // Two identical submissions at once: the unique index stopped the second one.
            if ($exception instanceof UniqueConstraintViolationException) {
                $exception = $this->duplicateOf($kind, $method, $reference, $proofHash ?: null) ?? $exception;
            }

            if ($exception instanceof InvalidPayment && isset($exception->params['receipt'])) {
                AuditLog::record('payment.duplicate_refused', $order, [], [
                    'field' => $exception->field,
                    'existing_receipt' => $exception->params['receipt'],
                    'amount' => $amount,
                    'method' => $method->code,
                ], $user);
            }

            throw $exception;
        }

        // A replayed request gives back the payment already recorded: the file just stored is not needed.
        if ($proofPath !== null && $payment->proof_path !== $proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        return $payment;
    }

    /**
     * The payment that already holds this transaction reference or this proof, as the refusal to give.
     */
    private function duplicateOf(PaymentKind $kind, PaymentMethod $method, ?string $reference, ?string $proofHash): ?InvalidPayment
    {
        $key = $kind === PaymentKind::Refund ? null : Payment::referenceKey($reference);

        $byReference = $key === null ? null : Payment::query()->with('order')
            ->where('payment_method_id', $method->id)->where('reference_key', $key)->first();

        if ($byReference !== null) {
            return new InvalidPayment('reference', 'payments.errors.reference_duplicate', [
                'receipt' => (string) $byReference->receipt_number,
                'order' => (string) $byReference->order->reference,
            ]);
        }

        $byProof = $proofHash === null ? null : Payment::query()->with('order')->where('proof_hash', $proofHash)->first();

        return $byProof === null ? null : new InvalidPayment('proof', 'payments.errors.proof_duplicate', [
            'receipt' => (string) $byProof->receipt_number,
            'order' => (string) $byProof->order->reference,
        ]);
    }

    private function record(
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
        ?string $proofPath = null,
        ?string $proofHash = null,
    ): Payment {
        return DB::transaction(function () use ($order, $kind, $amount, $method, $reference, $paidOn, $user, $reverses, $notes, $idempotencyKey, $payerName, $payerPhone, $collectedBy, $proofPath, $proofHash): Payment {
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

            $duplicate = $this->duplicateOf($kind, $method, $reference, $proofHash);

            if ($duplicate !== null) {
                throw $duplicate;
            }

            $payment = Payment::create([
                'receipt_number' => $this->numbers->next('receipt'),
                'order_id' => $order->id,
                'kind' => $kind,
                'amount' => $amount,
                'payment_method_id' => $method->id,
                'reference' => $reference,
                'reference_key' => $kind === PaymentKind::Refund ? null : Payment::referenceKey($reference),
                'proof_path' => $proofPath,
                'proof_hash' => $proofHash,
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
                'has_proof' => $proofPath !== null,
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
