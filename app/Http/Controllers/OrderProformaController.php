<?php

namespace App\Http\Controllers;

use App\Actions\Orders\IssueProformasForLocality;
use App\Http\Requests\IssueProformasRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderProformaController extends Controller
{
    /**
     * Issue the proformas of every order waiting for one in a locality, in one go.
     */
    public function store(IssueProformasRequest $request, IssueProformasForLocality $batch): RedirectResponse
    {
        $result = $batch->handle($request->validated('locality'), app()->getLocale(), $request->user());

        Inertia::flash('toast', $result['issued'] === 0 && $result['failed'] === 0
            ? ['type' => 'warning', 'message' => __('orders.proformas.none')]
            : ['type' => $result['failed'] > 0 ? 'warning' : 'success', 'message' => __('orders.proformas.done', $result)]);

        return back();
    }
}
