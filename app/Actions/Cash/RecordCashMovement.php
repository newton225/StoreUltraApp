<?php

namespace App\Actions\Cash;

use App\Exceptions\InvalidCashMovement;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordCashMovement
{
    /**
     * Record a movement of the cash box and give it its reference (CAI-0001). Sending the same
     * idempotency key again gives back the movement already recorded instead of a second one.
     *
     * @param  array<string, mixed>  $data  direction, amount, occurred_on, label, counterparty, document_reference, notes, idempotency_key.
     */
    public function handle(array $data, ?User $user = null): CashMovement
    {
        $key = filled($data['idempotency_key'] ?? null) ? (string) $data['idempotency_key'] : null;

        if ($key !== null && ($already = CashMovement::query()->where('idempotency_key', $key)->first()) !== null) {
            return $already;
        }

        return DB::transaction(function () use ($data, $user, $key): CashMovement {
            if (CashClosing::isClosed(Carbon::parse((string) $data['occurred_on']))) {
                throw new InvalidCashMovement('occurred_on', 'cash.errors.period_closed');
            }

            $movement = new CashMovement([...$data, 'idempotency_key' => $key, 'recorded_by' => $user?->id]);
            $movement->save();
            $movement->forceFill(['reference' => sprintf('CAI-%04d', $movement->id)])->save();

            AuditLog::record('cash_movement.recorded', $movement, [], [
                'reference' => $movement->reference,
                'direction' => $movement->direction,
                'amount' => $movement->amount,
                'occurred_on' => $movement->occurred_on->toDateString(),
                'label' => $movement->label,
                'counterparty' => $movement->counterparty,
                'document_reference' => $movement->document_reference,
            ], $user);

            return $movement;
        });
    }
}
