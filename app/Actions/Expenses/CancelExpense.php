<?php

namespace App\Actions\Expenses;

use App\Actions\Cash\CancelCashMovement;
use App\Exceptions\InvalidCashMovement;
use App\Exceptions\InvalidExpense;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelExpense
{
    public function __construct(private readonly CancelCashMovement $cancelCash) {}

    /**
     * Cancel an expense. It stays in the books, but no order carries its cost any more. The state is
     * read again under a lock, so two requests cannot both cancel it, and the cancellation and its
     * trace are written together.
     *
     * @throws InvalidExpense when it was already cancelled
     */
    public function handle(Expense $expense, string $reason, ?User $user = null): void
    {
        DB::transaction(function () use ($expense, $reason, $user): void {
            $current = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($current->isCancelled()) {
                throw new InvalidExpense('reason', 'expenses.errors.already_cancelled');
            }

            $linked = $current->cashMovement;

            if ($linked !== null && ! $linked->isCancelled() && CashClosing::isClosed($linked->occurred_on)) {
                throw new InvalidExpense('reason', 'cash.errors.period_closed');
            }

            $current->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
                'cancel_reason' => $reason,
            ])->save();

            AuditLog::record('expense.cancelled', $current, [], ['reason' => $reason], $user);

            $movement = $current->cashMovement;

            if ($movement !== null && ! $movement->isCancelled()) {
                try {
                    $this->cancelCash->handle($movement, $reason, $user, withExpense: true);
                } catch (InvalidCashMovement) {
                    // Already cancelled by a concurrent request: the books already agree.
                }
            }
        });

        $expense->refresh();
    }
}
