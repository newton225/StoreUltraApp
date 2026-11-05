<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseTypeRequest;
use App\Models\ExpenseType;
use App\Support\CodeFromLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseTypeController extends Controller
{
    /**
     * List the kinds of logistics cost.
     */
    public function index(): Response
    {
        return Inertia::render('expense-types/index', [
            'types' => ExpenseType::query()->orderBy('label_fr')->get(['id', 'code', 'label_fr', 'label_en', 'active']),
        ]);
    }

    /**
     * Create a kind of cost.
     */
    public function store(ExpenseTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] ??= CodeFromLabel::make($data['label_fr'], fn (string $code): bool => ExpenseType::query()->where('code', $code)->exists());

        ExpenseType::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('expenses.types.created')]);

        return to_route('expense-types.index');
    }

    /**
     * Update a kind of cost. Its code never changes, because past expenses refer to it.
     */
    public function update(ExpenseTypeRequest $request, ExpenseType $expenseType): RedirectResponse
    {
        $expenseType->update($request->safe()->except(['code']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('expenses.types.updated')]);

        return to_route('expense-types.index');
    }
}
