<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        @page { margin: 28px 34px 66px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px 0; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .box { border: 1px solid #d1d5db; padding: 8px 10px; margin-bottom: 14px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.lines th { background: #f3f4f6; text-align: left; padding: 5px 6px; border: 1px solid #d1d5db; }
        table.lines td { padding: 5px 6px; border: 1px solid #d1d5db; }
        table.lines td.num, table.lines th.num { text-align: right; }
        .totals { width: 55%; margin-left: 45%; border-collapse: collapse; }
        .totals td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .totals tr.strong td { font-weight: bold; border-bottom: 2px solid #111827; }
        .signatures { width: 100%; margin-top: 28px; }
        .signatures td { width: 50%; vertical-align: top; height: 90px; }
        .footer { margin-top: 24px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    @include('documents.watermark', ['watermark' => $company['name']])
    @include('documents.letterhead')
    <table class="header">
        <tr>
            <td></td>
            <td class="right">
                <h1>@yield('title')</h1>
                <strong>{{ $number }}</strong><br>
                <span class="muted">{{ __('documents.common.date') }} : {{ $issuedOn }}</span>
            </td>
        </tr>
    </table>

    @yield('content')

    @include('documents.company_footer', ['extra' => $number])
</body>
</html>
