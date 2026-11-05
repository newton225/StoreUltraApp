@extends('documents.layout')

@section('title', __('documents.proforma.title'))

@section('content')
    <div class="box">
        <strong>{{ $order->client->name }}</strong><br>
        {{ __('documents.common.phone') }} : {{ $order->client->phone }}<br>
        {{ __('documents.common.zone') }} : {{ $order->client->zone->name }}
        @if ($order->client->delivery_address)<br>{{ $order->client->delivery_address }}@endif
    </div>

    <p>
        {{ __('documents.proforma.kit', ['kit' => $order->kitVersion->kit->name, 'count' => $order->kits_count]) }}<br>
        <span class="muted">{{ __('documents.proforma.week', ['date' => $date($order->delivery_week)]) }}
        · {{ __('documents.proforma.validity', ['days' => $validityDays]) }}</span>
    </p>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('documents.common.designation') }}</th>
                <th class="num">{{ __('documents.common.quantity') }}</th>
                <th class="num">{{ __('documents.common.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ $item->quantity }} {{ $item->product->unit }}</td>
                    <td class="num">{{ $item->line_sale_total !== null ? $money($item->line_sale_total) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="strong"><td>{{ __('documents.proforma.total') }}</td><td class="right">{{ $money($order->total_sale) }}</td></tr>
        <tr><td>{{ __('documents.proforma.advance_due') }}</td><td class="right">{{ $money($order->advance_due) }}</td></tr>
        <tr><td>{{ __('documents.proforma.balance') }}</td><td class="right">{{ $money(max(0, $order->total_sale - $order->advance_due)) }}</td></tr>
    </table>
@endsection
