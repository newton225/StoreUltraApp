@extends('documents.layout')

@section('title', __('documents.receipt.title'))

@section('content')
    <div class="box">
        <strong>{{ $payment->order->client->name }}</strong><br>
        {{ __('documents.common.phone') }} : {{ $payment->order->client->phone }}<br>
        {{ __('documents.receipt.order') }} : {{ $payment->order->reference }}
    </div>

    <table class="lines">
        <tbody>
            <tr><td>{{ __('documents.receipt.kind') }}</td><td>{{ __('documents.receipt.kinds.'.$payment->kind->value) }}</td></tr>
            <tr><td>{{ __('documents.receipt.paid_on') }}</td><td>{{ $date($payment->paid_on) }}</td></tr>
            <tr><td>{{ __('documents.receipt.method') }}</td><td>{{ $payment->method->name }}@if ($payment->reference) ({{ $payment->reference }})@endif</td></tr>
            <tr><td>{{ __('documents.receipt.paid_by') }}</td><td>{{ $payment->payer_name ?: $payment->order->client->name }}@if ($payment->payer_phone) ({{ $payment->payer_phone }})@endif</td></tr>
            @if ($payment->collector)
                <tr><td>{{ __('documents.receipt.collected_by') }}</td><td>{{ $payment->collector->name }}</td></tr>
            @endif
            @if ($payment->recorder)
                <tr><td>{{ __('documents.receipt.recorded_by') }}</td><td>{{ $payment->recorder->name }}</td></tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr class="strong"><td>{{ __('documents.receipt.amount') }}</td><td class="right">{{ $money($payment->amount) }}</td></tr>
        <tr><td>{{ __('documents.receipt.order_total') }}</td><td class="right">{{ $money($payment->order->total_sale) }}</td></tr>
        <tr><td>{{ __('documents.receipt.paid_total') }}</td><td class="right">{{ $money($paidTotal) }}</td></tr>
        <tr><td>{{ __('documents.receipt.remaining') }}</td><td class="right">{{ $money(max(0, $payment->order->total_sale - $paidTotal)) }}</td></tr>
    </table>
@endsection
