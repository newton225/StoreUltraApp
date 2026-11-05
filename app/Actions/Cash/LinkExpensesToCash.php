<?php

namespace App\Actions\Cash;

use App\Exceptions\InvalidCashMovement;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LinkExpensesToCash
{
    public function __construct(private readonly RecordCashMovement $cash) {}

    /**
     * Attach older logistics costs, recorded before the link with the cash box existed, to the cash box: each one
     * gets the cash-out movement it would have had, dated the day of the cost, and is marked as paid from the cash
     * box. A cost that is cancelled, already linked, or dated in a closed period is skipped, never forced. One
     * trace per cost is written (playbook SEC-161), each cost in its own transaction so a skipped one never blocks
     * the others.
     *
     * @param  list<int>  $expenseIds
     * @return array{linked: int, skipped: int}
     */
    public function handle(array $expenseIds, ?User $user = null): array
    {
        $linked = 0;
        $skipped = 0;

        foreach (array_values(array_unique($expenseIds)) as $id) {
            $done = DB::transaction(function () use ($id, $user): bool {
                $expense = Expense::query()->whereKey($id)->lockForUpdate()->first();

                if ($expense === null || $expense->isCancelled() || $expense->paid_from_cash || $expense->cashMovement()->exists()) {
                    return false;
                }

                try {
                    $movement = $this->cash->handle([
                        'expense_id' => $expense->id,
                        'direction' => CashMovement::OUT,
                        'amount' => $expense->amount,
                        'occurred_on' => $expense->incurred_on->toDateString(),
                        'label' => __('cash.from_expense', ['type' => ExpenseType::query()->find($expense->expense_type_id)?->name]),
                        'notes' => $expense->notes,
                    ], $user);
                } catch (InvalidCashMovement) {
                    return false;
                }

                $expense->forceFill(['paid_from_cash' => true])->save();

                AuditLog::record('expense.linked_to_cash', $expense, ['paid_from_cash' => false], [
                    'paid_from_cash' => true,
                    'cash_movement' => $movement->reference,
                    'amount' => $expense->amount,
                ], $user);

                return true;
            });

            $done ? $linked++ : $skipped++;
        }

        return ['linked' => $linked, 'skipped' => $skipped];
    }
}
