@extends('documents.layout')

@section('title', __('documents.invoice.title'))

@section('content')
    <div class="box">
        <strong>{{ $invoice->client->name }}</strong><br>
        {{ __('documents.common.phone') }} : {{ $invoice->client->phone }}<br>
        @if ($invoice->client->commercial_number){{ __('documents.invoice.commercial_number') }} : {{ $invoice->client->commercial_number }}<br>@endif
        {{ __('documents.invoice.order') }} : {{ $invoice->order->reference }}
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('documents.common.designation') }}</th>
                <th class="num">{{ __('documents.common.quantity') }}</th>
                <th class="num">{{ __('documents.invoice.unit_price') }}</th>
                <th class="num">{{ __('documents.common.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('documents.invoice.kit_line', ['kit' => $invoice->order->kitVersion->kit->name]) }}</td>
                <td class="num">{{ $invoice->order->kits_count }}</td>
                <td class="num">{{ $money(intdiv($invoice->total, max(1, $invoice->order->kits_count))) }}</td>
                <td class="num">{{ $money($invoice->total) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr class="strong"><td>{{ __('documents.invoice.total') }}</td><td class="right">{{ $money($invoice->total) }}</td></tr>
        <tr><td>{{ __('documents.invoice.paid') }}</td><td class="right">{{ $money($invoice->paid_amount) }}</td></tr>
        <tr><td>{{ __('documents.invoice.remaining') }}</td><td class="right">{{ $money(max(0, $invoice->total - $invoice->paid_amount)) }}</td></tr>
    </table>

    <p>
        @if ($invoice->fne_number)
            {{ __('documents.invoice.fne_number') }} : <strong>{{ $invoice->fne_number }}</strong>
        @elseif ($invoice->fne_status === 'to_issue')
            <span class="muted">{{ __('documents.invoice.fne_pending') }}</span>
        @endif
    </p>
@endsection
