<?php

namespace App\Http\Controllers;

use App\Actions\Cash\CancelCashMovement;
use App\Actions\Cash\RecordCashMovement;
use App\Exceptions\InvalidCashMovement;
use App\Http\Requests\CancelCashMovementRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Support\CashBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashMovementController extends Controller
{
    private const PER_PAGE = 30;

    private const CLOSINGS_PER_PAGE = 12;

    /**
     * The cash box: the balance, the movements of the period and the form recording a new one.
     */
    public function index(Request $request, CashBalance $balance): Response
    {
        $direction = in_array($request->string('direction')->toString(), [CashMovement::IN, CashMovement::OUT], true)
            ? $request->string('direction')->toString()
            : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $valid = fn (): Builder => CashMovement::query()->whereNull('cancelled_at');
        $net = fn (Builder $query): int => $balance->net($query);

        $opening = $from === null ? 0 : $net($valid()->whereDate('occurred_on', '<', $from));
        $period = fn (): Builder => $valid()
            ->when($from !== null, fn (Builder $query) => $query->whereDate('occurred_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('occurred_on', '<=', $to));

        $in = (int) $period()->where('direction', CashMovement::IN)->sum('amount');
        $out = (int) $period()->where('direction', CashMovement::OUT)->sum('amount');

        $movements = CashMovement::query()
            ->with('recorder:id,name')
            ->when($direction !== null, fn (Builder $query) => $query->where('direction', $direction))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('occurred_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('occurred_on', '<=', $to))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (CashMovement $movement): array => [
                'id' => $movement->id,
                'reference' => $movement->reference,
                'direction' => $movement->direction,
                'amount' => $movement->amount,
                'occurred_on' => $movement->occurred_on->toDateString(),
                'label' => $movement->label,
                'counterparty' => $movement->counterparty,
                'document_reference' => $movement->document_reference,
                'notes' => $movement->notes,
                'from_expense' => $movement->expense_id !== null,
                'recorder' => $movement->recorder?->name,
                'cancelled' => $movement->isCancelled(),
                'cancel_reason' => $movement->cancel_reason,
            ]);

        $closings = CashClosing::query()
            ->with('closer:id,name')
            ->orderByDesc('closed_on')
            ->paginate(self::CLOSINGS_PER_PAGE, pageName: 'closings_page')
            ->withQueryString()
            ->through(fn (CashClosing $closing): array => [
                'id' => $closing->id,
                'reference' => $closing->reference,
                'closed_on' => $closing->closed_on->toDateString(),
                'expected' => $closing->expected_amount,
                'counted' => $closing->counted_amount,
                'difference' => $closing->difference,
                'notes' => $closing->notes,
                'closer' => $closing->closer?->name,
            ]);

        $lastClosedOn = CashClosing::lastClosedOn();

        $unlinked = ! Gate::allows('manage-expenses') ? [] : Expense::query()
            ->with('type')
            ->whereNull('cancelled_at')
            ->where('paid_from_cash', false)
            ->whereDoesntHave('cashMovement')
            ->when($lastClosedOn !== null, fn (Builder $query) => $query->whereDate('incurred_on', '>', $lastClosedOn))
            ->orderByDesc('incurred_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'type' => $expense->type->name,
                'amount' => $expense->amount,
                'incurred_on' => $expense->incurred_on->toDateString(),
                'notes' => $expense->notes,
            ])
            ->values()
            ->all();

        return Inertia::render('cash/index', [
            'closings' => $closings,
            'last_closed_on' => $lastClosedOn?->toDateString(),
            'balance_today' => $balance->asOf(today()),
            'unlinked_expenses' => $unlinked,
            'movements' => $movements,
            'summary' => ['opening' => $opening, 'in' => $in, 'out' => $out, 'closing' => $opening + $in - $out],
            'filters' => ['direction' => $direction, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'today' => today()->toDateString(),
            'current_user_name' => $request->user()->name,
        ]);
    }

    /**
     * Record a movement of the cash box.
     */
    public function store(StoreCashMovementRequest $request, RecordCashMovement $record): RedirectResponse
    {
        try {
            $record->handle($request->validated(), $request->user());
        } catch (InvalidCashMovement $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('cash.recorded')]);

        return back();
    }

    /**
     * Cancel a movement with a reason.
     */
    public function cancel(CancelCashMovementRequest $request, CashMovement $cashMovement, CancelCashMovement $cancel): RedirectResponse
    {
        try {
            $cancel->handle($cashMovement, $request->validated('reason'), $request->user());
        } catch (InvalidCashMovement $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('cash.cancelled')]);

        return back();
    }
}
