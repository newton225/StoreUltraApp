@extends('documents.layout')

@section('title', __('documents.statement.title'))

@section('content')
    <p>{{ __('documents.statement.period', ['start' => $date($periodStart), 'end' => $date($periodEnd)]) }}</p>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('documents.statement.invoice') }}</th>
                <th>{{ __('documents.common.date') }}</th>
                <th>{{ __('documents.common.client') }}</th>
                <th class="num">{{ __('documents.statement.total') }}</th>
                <th class="num">{{ __('documents.statement.paid') }}</th>
                <th class="num">{{ __('documents.statement.remaining') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $date($row['issued_on']) }}</td>
                    <td>{{ $row['client'] }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td class="num">{{ $money($row['paid']) }}</td>
                    <td class="num">{{ $money($row['remaining']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="strong"><td>{{ __('documents.statement.grand_total') }}</td><td class="right">{{ $money($totals['total']) }}</td></tr>
        <tr><td>{{ __('documents.statement.paid') }}</td><td class="right">{{ $money($totals['paid']) }}</td></tr>
        <tr><td>{{ __('documents.statement.remaining') }}</td><td class="right">{{ $money($totals['remaining']) }}</td></tr>
    </table>
@endsection
