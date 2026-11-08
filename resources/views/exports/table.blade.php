<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .muted { color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th { background: #f3f4f6; text-align: left; padding: 4px 5px; border: 1px solid #d1d5db; }
        td { padding: 4px 5px; border: 1px solid #d1d5db; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    @include('documents.watermark', ['watermark' => $companyName])
    <h1>{{ $table->title }}</h1>
    <div class="muted">
        {{ $companyName }} · {{ __('exports.pdf.generated', ['date' => $generatedAt, 'name' => $generatedBy]) }}
        · {{ __('exports.pdf.count', ['count' => $total]) }}
        @if ($truncated) · {{ __('exports.pdf.truncated', ['shown' => count($table->rows)]) }} @endif
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($table->columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($table->rows as $row)
                <tr>
                    @foreach ($row as $value)
                        {{-- a phone number is never cut in two lines --}}
                        <td @class(['num' => is_int($value) || is_float($value), 'nowrap' => is_string($value) && preg_match('/^[+\d][\d ().+-]{6,}$/', $value) === 1])>{{ is_int($value) ? number_format($value, 0, ',', ' ') : $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
