<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceFneRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    /**
     * Record the number of the normalized electronic invoice (FNE) issued on the tax platform.
     * It is recorded once; a mistake means issuing a new invoice.
     */
    public function fne(UpdateInvoiceFneRequest $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->fne_status === 'issued' || $invoice->cancelled_at !== null) {
            return back()->withErrors(['fne_number' => __('documents.errors.fne_done')]);
        }

        $invoice->forceFill([
            'fne_number' => $request->validated('fne_number'),
            'fne_status' => 'issued',
        ])->save();

        AuditLog::record('invoice.fne_recorded', $invoice, [], ['fne_number' => $invoice->fne_number], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('documents.ui.fne_recorded')]);

        return back();
    }
}
