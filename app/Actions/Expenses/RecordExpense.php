<?php

namespace App\Actions\Expenses;

use App\Actions\Cash\RecordCashMovement;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidCashMovement;
use App\Exceptions\InvalidExpense;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\User;
use App\Support\AdvanceAllocation;
use App\Support\BusinessSettings;
use App\Support\UploadedFiles;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecordExpense
{
    public function __construct(private WeekCalendar $calendar, private BusinessSettings $settings, private UploadedFiles $uploads, private RecordCashMovement $cash) {}

    /**
     * Record a logistics cost and share it between the orders it concerns: by kits, or equally,
     * according to the setting, to the franc.
     *
     * Sending the same idempotency key again gives back the cost already recorded instead of a second
     * one: a double click or a replayed request records it once.
     *
     * @param  array<string, mixed>  $data  expense_type_id, amount, incurred_on, scope, order_id, delivery_id, zone_id, shop_id, week, notes, idempotency_key.
     *
     * @throws InvalidExpense when no order is concerned
     */
    public function handle(array $data, ?UploadedFile $proof = null, ?User $user = null): Expense
    {
        $key = filled($data['idempotency_key'] ?? null) ? (string) $data['idempotency_key'] : null;

        if ($key !== null && ($already = Expense::query()->where('idempotency_key', $key)->first()) !== null) {
            return $already;
        }

        $week = filled($data['week'] ?? null) ? $this->calendar->startOf(CarbonImmutable::parse($data['week'])) : null;
        $scope = (string) $data['scope'];

        $orders = $this->concernedOrders($scope, $data, $week);

        if ($orders->isEmpty()) {
            throw new InvalidExpense('scope', 'expenses.errors.no_orders');
        }

        $weights = $orders->mapWithKeys(fn (Order $order): array => [
            $order->id => $this->settings->get('expense_allocation') === 'equal' ? 1 : $order->kits_count,
        ])->all();

        $amount = (int) $data['amount'];
        $shares = AdvanceAllocation::split($amount, $weights);

        $proofPath = $proof === null ? null : $this->uploads->store($proof, 'expenses');

        try {
            return $this->write($data, $scope, $week, $amount, $shares, $proofPath, $key, $user);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            // Two identical submissions at once: the other one was written first, give that one back.
            if ($exception instanceof UniqueConstraintViolationException && $key !== null) {
                $existing = Expense::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    /**
     * Write the expense, its shares and its trace together.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int|string, int>  $shares
     */
    private function write(array $data, string $scope, ?CarbonImmutable $week, int $amount, array $shares, ?string $proofPath, ?string $idempotencyKey, ?User $user): Expense
    {
        return DB::transaction(function () use ($data, $scope, $week, $amount, $shares, $proofPath, $idempotencyKey, $user): Expense {
            $expense = Expense::create([
                'expense_type_id' => $data['expense_type_id'],
                'amount' => $amount,
                'paid_from_cash' => (bool) ($data['paid_from_cash'] ?? false),
                'incurred_on' => $data['incurred_on'],
                'scope' => $scope,
                'order_id' => $scope === 'order' ? $data['order_id'] : null,
                'delivery_id' => $scope === 'delivery' ? $data['delivery_id'] : null,
                'zone_id' => $scope === 'zone' ? $data['zone_id'] : null,
                'shop_id' => $scope === 'shop' ? $data['shop_id'] : null,
                'week' => in_array($scope, ['zone', 'shop', 'week'], true) ? $week?->toDateString() : null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'proof_path' => $proofPath,
                'created_by' => $user?->id,
            ]);

            foreach ($shares as $orderId => $share) {
                if ($share > 0) {
                    $expense->allocations()->create(['order_id' => $orderId, 'amount' => $share]);
                }
            }

            if ($expense->paid_from_cash) {
                try {
                    $this->cash->handle([
                        'expense_id' => $expense->id,
                        'direction' => CashMovement::OUT,
                        'amount' => $amount,
                        'occurred_on' => $data['incurred_on'],
                        'label' => __('cash.from_expense', ['type' => ExpenseType::query()->find($expense->expense_type_id)?->name]),
                        'notes' => $data['notes'] ?? null,
                    ], $user);
                } catch (InvalidCashMovement $exception) {
                    throw new InvalidExpense('incurred_on', $exception->messageKey);
                }
            }

            AuditLog::record('expense.recorded', $expense, [], [
                'paid_from_cash' => $expense->paid_from_cash,
                'amount' => $amount,
                'scope' => $scope,
                'orders' => count($shares),
            ], $user);

            return $expense;
        });
    }

    /**
     * The orders an expense reaches, oldest first. Cancelled orders never carry a cost.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Order>
     */
    private function concernedOrders(string $scope, array $data, ?CarbonImmutable $week): Collection
    {
        $query = Order::query()->where('status', '!=', OrderStatus::Cancelled->value)->orderBy('id');

        return match ($scope) {
            'order' => $query->whereKey($data['order_id'])->get(),
            'delivery' => $query->whereKey(Delivery::query()->whereKey($data['delivery_id'])->value('order_id'))->get(),
            'zone' => $query->whereDate('delivery_week', $week)
                ->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $data['zone_id']))
                ->get(),
            'shop' => $query->whereDate('delivery_week', $week)
                ->whereHas('items', fn (Builder $items) => $items->where('shop_id', $data['shop_id']))
                ->get(),
            default => $query->whereDate('delivery_week', $week)->get(),
        };
    }
}
