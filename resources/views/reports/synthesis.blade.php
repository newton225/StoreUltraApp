<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('dashboard.pdf.title') }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px 0; text-transform: uppercase; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f3f4f6; text-align: left; padding: 5px 6px; border: 1px solid #d1d5db; }
        td { padding: 5px 6px; border: 1px solid #d1d5db; }
        .num { text-align: right; }
        tr.strong td { font-weight: bold; }
    </style>
</head>
<body>
    @include('documents.watermark', ['watermark' => $companyName])
    @php($i = $report['indicators'])
    <h1>{{ __('dashboard.pdf.title') }}</h1>
    <div class="muted">
        {{ __('dashboard.pdf.period') }} : {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
        @if ($zone) · {{ $zone }} @endif
        @if ($kit) · {{ $kit }} @endif
        @if ($shop) · {{ $shop }} @endif
        · {{ __('dashboard.pdf.generated') }} {{ $generatedAt }}
    </div>

    <h2>{{ __('dashboard.indicators.title') }}</h2>
    <table>
        @foreach ([
            'active_clients', 'orders', 'kits_ordered', 'kits_delivered', 'kits_remaining',
        ] as $key)
            <tr><td>{{ __('dashboard.indicators.'.$key) }}</td><td class="num">{{ $i[$key] }}</td></tr>
        @endforeach
        @foreach (['sale_total', 'advances', 'balances', 'refunds', 'receivables'] as $key)
            <tr><td>{{ __('dashboard.indicators.'.$key) }}</td><td class="num">{{ $money($i[$key]) }}</td></tr>
        @endforeach
        @if ($report['shops'])
            <tr><td>{{ __('dashboard.indicators.debts') }}</td><td class="num">{{ $money($report['shops']['debts']) }}</td></tr>
        @endif
    </table>

    <h2>{{ __('dashboard.statuses.title') }}</h2>
    <table>
        @foreach ($i['orders_by_status'] as $status => $count)
            <tr><td>{{ __('orders.statuses.'.$status) }}</td><td class="num">{{ $count }}</td></tr>
        @endforeach
    </table>

    @if ($report['shops'])
        <h2>{{ __('dashboard.shops.title') }}</h2>
        <table>
            <tr>
                <th>{{ __('dashboard.shops.shop') }}</th>
                <th class="num">{{ __('dashboard.shops.purchase') }}</th>
                <th class="num">{{ __('dashboard.shops.sale') }}</th>
                <th class="num">{{ __('dashboard.shops.advance') }}</th>
                <th class="num">{{ __('dashboard.shops.percent') }}</th>
                <th class="num">{{ __('dashboard.shops.balance') }}</th>
            </tr>
            @foreach ($report['shops']['summary'] as $row)
                <tr>
                    <td>{{ $row['shop'] }}</td>
                    <td class="num">{{ $money($row['purchase']) }}</td>
                    <td class="num">{{ $money($row['sale']) }}</td>
                    <td class="num">{{ $money($row['advance']) }}</td>
                    <td class="num">{{ $row['percent'] }} %</td>
                    <td class="num">{{ $money($row['balance']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($report['profit'])
        <h2>{{ __('dashboard.profit.title') }}</h2>
        <table>
            @foreach (['theoretical_margin', 'real_margin', 'projected_margin', 'gap', 'actual_purchase', 'logistics'] as $key)
                <tr><td>{{ __('dashboard.profit.'.$key) }}</td><td class="num">{{ $money($report['profit'][$key]) }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
