<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\CancelExpense;
use App\Actions\Expenses\RecordExpense;
use App\Exceptions\InvalidExpense;
use App\Http\Requests\CancelExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Zone;
use App\Support\ListSort;
use App\Support\UploadedFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the logistics costs with the total of the valid ones.
     */
    public function index(Request $request): Response
    {
        $typeId = $request->integer('type') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');
        $sort = ListSort::resolve($request, Expense::SORTABLE);

        $filtered = fn (): Builder => Expense::query()
            ->when($typeId !== null, fn (Builder $query) => $query->where('expense_type_id', $typeId))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('incurred_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('incurred_on', '<=', $to));

        $total = (int) $filtered()->whereNull('cancelled_at')->sum('amount');

        $expenses = $filtered()
            ->with(['type', 'author:id,name'])
            ->withCount('allocations')
            ->sortedBy($sort['sort'], $sort['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Expense $expense): array => [
                'id' => $expense->id,
                'type' => $expense->type->name,
                'amount' => $expense->amount,
                'incurred_on' => $expense->incurred_on->toDateString(),
                'scope' => $expense->scope,
                'week' => $expense->week?->toDateString(),
                'notes' => $expense->notes,
                'paid_from_cash' => $expense->paid_from_cash,
                'author' => $expense->author?->name,
                'orders' => $expense->allocations_count,
                'has_proof' => $expense->proof_path !== null,
                'cancelled' => $expense->isCancelled(),
                'cancel_reason' => $expense->cancel_reason,
            ]);

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'sort' => $sort,
            'total' => $total,
            'filters' => ['type' => $typeId, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'types' => ExpenseType::query()->orderBy('label_fr')->get()->map(fn (ExpenseType $type): array => ['id' => $type->id, 'name' => $type->name])->values(),
        ]);
    }

    /**
     * Show the form recording a cost.
     */
    public function create(): Response
    {
        $since = today()->subWeeks(8);

        return Inertia::render('expenses/create', [
            'types' => ExpenseType::query()->where('active', true)->orderBy('label_fr')->get()->map(fn (ExpenseType $type): array => ['id' => $type->id, 'name' => $type->name])->values(),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'orders' => Order::query()->with('client:id,name')->whereDate('delivery_week', '>=', $since)->orderByDesc('id')->limit(200)->get()
                ->map(fn (Order $order): array => ['id' => $order->id, 'label' => $order->reference.' — '.$order->client->name])->values(),
            'deliveries' => Delivery::query()->with('order.client:id,name')->whereDate('delivered_on', '>=', $since)->orderByDesc('id')->limit(200)->get()
                ->map(fn (Delivery $delivery): array => ['id' => $delivery->id, 'label' => $delivery->reference.' — '.$delivery->order->client->name])->values(),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Record a cost and share it between the orders it concerns.
     */
    public function store(StoreExpenseRequest $request, RecordExpense $record): RedirectResponse
    {
        try {
            $record->handle($request->safe()->except(['proof']), $request->file('proof'), $request->user());
        } catch (InvalidExpense $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('expenses.recorded')]);

        return to_route('expenses.index');
    }

    /**
     * Cancel a cost with a reason.
     */
    public function cancel(CancelExpenseRequest $request, Expense $expense, CancelExpense $cancel): RedirectResponse
    {
        try {
            $cancel->handle($expense, $request->validated('reason'), $request->user());
        } catch (InvalidExpense $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('expenses.cancelled')]);

        return back();
    }

    /**
     * Open the proof of a cost. It is private.
     */
    public function proof(Expense $expense, UploadedFiles $files): StreamedResponse
    {
        abort_if($expense->proof_path === null, 404);

        AuditLog::record('expense.proof_opened', $expense, [], ['amount' => $expense->amount]);

        return $files->response($expense->proof_path);
    }
}
