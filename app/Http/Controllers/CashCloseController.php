<?php

namespace App\Http\Controllers;

use App\Actions\Cash\CloseCashBox;
use App\Actions\Cash\LinkExpensesToCash;
use App\Exceptions\InvalidCashMovement;
use App\Http\Requests\LinkExpensesToCashRequest;
use App\Http\Requests\StoreCashClosingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CashCloseController extends Controller
{
    /**
     * Close the cash box on a date with the amount counted.
     */
    public function store(StoreCashClosingRequest $request, CloseCashBox $close): RedirectResponse
    {
        try {
            $closing = $close->handle($request->validated(), $request->user());
        } catch (InvalidCashMovement $exception) {
            return back()->withErrors([$exception->field => __($exception->messageKey)])->withInput();
        }

        Inertia::flash('toast', [
            'type' => $closing->difference === 0 ? 'success' : 'warning',
            'message' => __('cash.closing.done', ['reference' => $closing->reference]),
        ]);

        return back();
    }

    /**
     * Attach older logistics costs paid in cash to the cash box.
     */
    public function link(LinkExpensesToCashRequest $request, LinkExpensesToCash $link): RedirectResponse
    {
        Gate::authorize('manage-expenses');

        $result = $link->handle($request->validated('expense_ids'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('cash.link.done', $result)]);

        return back();
    }
}
