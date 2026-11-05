@extends('documents.layout')

@section('title', __('documents.delivery_note.title'))

@section('content')
    <div class="box">
        <strong>{{ $delivery->order->client->name }}</strong><br>
        {{ __('documents.common.phone') }} : {{ $delivery->order->client->phone }}<br>
        {{ __('documents.common.zone') }} : {{ $delivery->order->client->zone->name }}
        @if ($delivery->order->client->delivery_address)<br>{{ $delivery->order->client->delivery_address }}@endif
    </div>

    <table class="lines">
        <tbody>
            <tr><td>{{ __('documents.delivery_note.order') }}</td><td>{{ $delivery->order->reference }}</td></tr>
            <tr><td>{{ __('documents.delivery_note.kit') }}</td><td>{{ $delivery->order->kitVersion->kit->name }}</td></tr>
            <tr><td>{{ __('documents.delivery_note.kits_delivered') }}</td><td><strong>{{ $delivery->kits_delivered }}</strong> / {{ $delivery->order->kits_count }}</td></tr>
            <tr><td>{{ __('documents.delivery_note.delivered_on') }}</td><td>{{ $date($delivery->delivered_on) }}</td></tr>
            @if ($delivery->agent)
                <tr><td>{{ __('documents.delivery_note.agent') }}</td><td>{{ $delivery->agent->name }}</td></tr>
            @endif
        </tbody>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('documents.common.designation') }}</th>
                <th class="num">{{ __('documents.delivery_note.per_kit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($delivery->order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ intdiv($item->quantity, max(1, $delivery->order->kits_count)) }} {{ $item->product->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($delivery->notes)<p class="muted">{{ $delivery->notes }}</p>@endif

    <table class="signatures">
        <tr>
            <td>
                {{ __('documents.delivery_note.received_by', ['name' => $delivery->received_by]) }}<br>
                @if ($signature)
                    <img src="{{ $signature }}" alt="" style="max-height: 70px; max-width: 220px;">
                @endif
            </td>
            <td>{{ __('documents.delivery_note.agent_signature') }}</td>
        </tr>
    </table>
@endsection
