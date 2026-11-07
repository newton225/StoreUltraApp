@extends('documents.layout')

@section('title', __('documents.purchase_order.title'))

@section('content')
    <div class="box">
        <strong>{{ $purchase->shop->name }}</strong><br>
        @if ($purchase->shop->contact){{ $purchase->shop->contact }}<br>@endif
        @if ($purchase->shop->phone){{ $purchase->shop->phone }}<br>@endif
        <span class="muted">{{ __('documents.purchase_order.week', ['date' => $date($purchase->week)]) }}</span>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('documents.common.designation') }}</th>
                <th class="num">{{ __('documents.common.quantity') }}</th>
                <th class="num">{{ __('documents.purchase_order.unit_price') }}</th>
                <th class="num">{{ __('documents.common.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ $item->quantity_ordered }} {{ $item->product->unit }}</td>
                    <td class="num">{{ $money($item->unit_price_expected) }}</td>
                    <td class="num">{{ $money($item->quantity_ordered * $item->unit_price_expected) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="strong"><td>{{ __('documents.purchase_order.total') }}</td><td class="right">{{ $money($purchase->expected_total) }}</td></tr>
    </table>

    <table class="signatures">
        <tr><td>{{ __('documents.purchase_order.signature') }}@if ($company['signatory'])<br><strong>{{ $company['signatory'] }}</strong>@if ($company['signatory_title'])<br>{{ $company['signatory_title'] }}@endif @endif</td><td></td></tr>
    </table>
@endsection
