<?php

namespace App\Actions\Cash;

use App\Exceptions\InvalidCashMovement;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelCashMovement
{
    /**
     * Cancel a movement. It stays in the books but no longer counts in the balance. The state is read
     * again under a lock, so two requests cannot both cancel it.
     *
     * A movement that pays a logistics cost is cancelled only together with that cost (CancelExpense passes
     * $withExpense), never on its own, so the two books cannot disagree.
     *
     * @throws InvalidCashMovement when it was already cancelled, belongs to a cost and is cancelled alone, or falls in a closed period
     */
    public function handle(CashMovement $movement, string $reason, ?User $user = null, bool $withExpense = false): void
    {
        DB::transaction(function () use ($movement, $reason, $user, $withExpense): void {
            $current = CashMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($current->expense_id !== null && ! $withExpense) {
                throw new InvalidCashMovement('reason', 'cash.errors.linked_to_expense');
            }

            if (CashClosing::isClosed($current->occurred_on)) {
                throw new InvalidCashMovement('reason', 'cash.errors.period_closed');
            }

            if ($current->isCancelled()) {
                throw new InvalidCashMovement('reason', 'cash.errors.already_cancelled');
            }

            $current->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
                'cancel_reason' => $reason,
            ])->save();

            AuditLog::record('cash_movement.cancelled', $current, [], [
                'reference' => $current->reference,
                'amount' => $current->amount,
                'reason' => $reason,
            ], $user);
        });

        $movement->refresh();
    }
}
