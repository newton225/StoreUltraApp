{{-- The commercial sheet shared by the invoice and the proforma (playbook UI-122). The including view gives $kind (invoice, proforma or delivery_note) and $client. --}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        @page { margin: 26px 30px 66px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111827; }
        table { border-collapse: collapse; }
        .center { text-align: center; }
        .right { text-align: right; }
        .strong { font-weight: bold; }
        .nowrap { white-space: nowrap; }
        .project { text-align: center; font-weight: bold; font-size: 10.5px; margin: 0 0 2px 0; }
        .zones { text-align: center; font-style: italic; margin: 0 0 8px 0; }
        .banner { width: 100%; border: 2px solid #2f5597; margin-bottom: 14px; }
        .banner td { text-align: center; }
        .banner .title { font-size: 17px; font-weight: bold; color: #2f5597; padding: 3px 0 1px 0; }
        .banner .subtitle { background: #fff2cc; font-weight: bold; font-size: 11px; text-transform: uppercase; padding: 6px 0 14px 0; }
        .info { width: 100%; margin-bottom: 10px; }
        .info td { padding: 2px 0; vertical-align: top; font-size: 10.5px; }
        .info td.left { width: 52%; }
        table.lines { width: 100%; margin-bottom: 0; }
        table.lines th { background: #2f5597; color: #ffffff; font-size: 10.5px; padding: 3px 4px; border: 1px solid #2f5597; }
        table.lines td { border: 1px solid #374151; padding: 2px 5px; }
        table.lines td.num { text-align: right; }
        table.lines td.amount { text-align: right; font-weight: bold; white-space: nowrap; }
        table.lines tr.total td { font-weight: bold; text-align: center; font-size: 11px; padding: 3px 5px; }
        table.lines tr.total td.amount { text-align: right; }
        table.lines tr.net td.amount { background: #ffd966; }
        .words { margin: 14px 0 12px 0; }
        .fne { margin: 0 0 8px 0; color: #4b5563; }
        .section { color: #2f5597; font-weight: bold; text-decoration: underline; margin: 6px 0 10px 0; font-size: 10px; text-transform: uppercase; }
        table.recon { width: 100%; }
        table.recon th { background: #ddebf7; border: 1px solid #374151; padding: 6px 4px; font-size: 10px; }
        table.recon td { border: 1px solid #374151; padding: 6px 5px; text-align: center; }
        table.recon td.label { font-weight: bold; }
        table.recon td.amount { font-weight: bold; white-space: nowrap; }
        table.recon td.ref { font-weight: bold; }
        table.recon td.note { font-weight: bold; font-style: italic; }
        .check { width: 100%; margin: 12px 0 18px 0; }
        .check td { padding: 1px 4px; vertical-align: top; }
        .signatures { width: 100%; margin-top: 8px; }
        .signatures td { width: 33%; vertical-align: top; text-align: center; height: 70px; }
        .signatures .role { font-weight: bold; text-decoration: underline; font-size: 10.5px; }
        .signatures .name { margin-top: 8px; font-size: 9.5px; }
    </style>
</head>
<body>
    @include('documents.watermark', ['watermark' => $company['name']])

    @include('documents.letterhead')

    @if ($projectTitle)
        <p class="project">{{ $projectTitle }}</p>
    @endif
    @if (count($sheet['zones']) > 0)
        <p class="zones">{{ __('documents.sheet.zones') }} : {{ implode(' • ', $sheet['zones']) }}</p>
    @endif

    <table class="banner">
        <tr><td class="title">{{ __("documents.{$kind}.title") }}</td></tr>
        @if ($subtitle)
            <tr><td class="subtitle">{{ $subtitle }}</td></tr>
        @endif
    </table>

    <table class="info">
        <tr>
            <td class="left">@if ($kind === 'delivery_note')<span class="strong">{{ __('documents.delivery_note.order') }} :</span> {{ $delivery->order->reference }}@else&nbsp;@endif</td>
            <td><span class="strong">{{ __('documents.sheet.voucher') }} :</span> &nbsp;{{ $number }}</td>
        </tr>
        <tr>
            <td class="left"><span class="strong">{{ __('documents.sheet.supplier') }} :</span> {{ $company['name'] ?: config('app.name') }}</td>
            <td><span class="strong">{{ __('documents.common.phone') }} :</span> {{ $company['phone'] }}</td>
        </tr>
        <tr>
            <td class="left"><span class="strong">{{ __('documents.common.client') }} :</span> {{ $client->name }}</td>
            <td><span class="strong">{{ __('documents.common.phone') }} :</span> {{ $client->phone }}</td>
        </tr>
        <tr>
            <td class="left"><span class="strong">{{ __('documents.sheet.delivery_place') }} :</span> {{ $client->delivery_address ?: $client->locality }}</td>
            <td><span class="strong">{{ __('documents.sheet.ordered_on') }} :</span> {{ $sheet['ordered_on'] }}</td>
        </tr>
        <tr>
            <td class="left"><span class="strong">{{ __('documents.common.zone') }} :</span> {{ $client->zone->name }}</td>
            <td><span class="strong">{{ __("documents.{$kind}.delivered_on") }} :</span> {{ $sheet['delivered_on'] }}</td>
        </tr>
    </table>

    @if ($kind === 'delivery_note')
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 5%">{{ __('documents.sheet.number_column') }}</th>
                    <th>{{ __('documents.common.designation') }}</th>
                    <th style="width: 12%">{{ __('documents.sheet.unit') }}</th>
                    <th style="width: 16%">{{ __('documents.delivery_note.ordered') }}</th>
                    <th style="width: 16%">{{ __('documents.delivery_note.delivered') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sheet['lines'] as $line)
                    <tr>
                        <td class="center">{{ $line['number'] }}</td>
                        <td>{{ $line['designation'] }}</td>
                        <td class="center">{{ $line['unit'] }}</td>
                        <td class="center">{{ $line['quantity'] }}</td>
                        <td class="center strong">{{ $line['delivered'] }}</td>
                    </tr>
                @endforeach
                <tr class="total net">
                    <td colspan="4">{{ __('documents.delivery_note.kits_delivered') }}</td>
                    <td class="center">{{ $delivery->kits_delivered }} / {{ $delivery->order->kits_count }}</td>
                </tr>
            </tbody>
        </table>

        @if ($delivery->notes)<p class="words">{{ $delivery->notes }}</p>@endif

        <table class="signatures" style="margin-top: 30px;">
            <tr>
                <td><div class="role">{{ __('documents.sheet.storekeeper') }}</div></td>
                <td><div class="role">{{ __('documents.delivery_note.delivered_by') }}</div><div class="name">{{ $delivery->agent?->name }}</div></td>
                <td>
                    <div class="role">{{ __('documents.delivery_note.receiver') }}</div>
                    <div class="name">{{ $delivery->received_by }}</div>
                    @if ($signature)<img src="{{ $signature }}" alt="" style="max-height: 60px; max-width: 200px;">@endif
                </td>
            </tr>
        </table>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 5%">{{ __('documents.sheet.number_column') }}</th>
                    <th>{{ __('documents.common.designation') }}</th>
                    <th style="width: 10%">{{ __('documents.sheet.unit') }}</th>
                    <th style="width: 7%">{{ __('documents.sheet.quantity_short') }}</th>
                    <th style="width: 14%">{{ __('documents.sheet.unit_price') }}</th>
                    <th style="width: 21%">{{ __('documents.sheet.amount_column') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sheet['lines'] as $line)
                    <tr>
                        <td class="center">{{ $line['number'] }}</td>
                        <td>{{ $line['designation'] }}</td>
                        <td class="center">{{ $line['unit'] }}</td>
                        <td class="center">{{ $line['quantity'] }}</td>
                        <td class="num nowrap">{{ $line['unit_price'] !== null ? $plain($line['unit_price']) : '' }}</td>
                        <td class="amount">{{ $line['amount'] !== null ? $money($line['amount']) : '' }}</td>
                    </tr>
                @endforeach
                <tr class="total net">
                    <td colspan="5">{{ __('documents.sheet.net') }}</td>
                    <td class="amount">{{ $money($sheet['total']) }}</td>
                </tr>
                <tr class="total">
                    <td colspan="5">{{ __("documents.{$kind}.advance") }}</td>
                    <td class="amount">{{ $money($sheet['advance']) }}</td>
                </tr>
                @if ($sheet['balance_paid'] > 0)
                    <tr class="total">
                        <td colspan="5">{{ __("documents.{$kind}.balance_paid") }}</td>
                        <td class="amount">{{ $money($sheet['balance_paid']) }}</td>
                    </tr>
                @endif
                <tr class="total">
                    <td colspan="5">{{ __("documents.{$kind}.remaining") }}</td>
                    <td class="amount">{{ $money($sheet['remaining']) }}</td>
                </tr>
            </tbody>
        </table>

        <p class="words">{{ __("documents.{$kind}.in_words") }} <span class="strong">{{ $inWords($sheet['total']) }}</span></p>

        @if ($kind === 'proforma')
            <p class="fne">{{ __('documents.proforma.validity', ['days' => $validityDays]) }}</p>
        @endif

        @if ($kind === 'invoice' && $invoice->fne_number)
            <p class="fne">{{ __("documents.{$kind}.fne_number") }} : <strong>{{ $invoice->fne_number }}</strong></p>
        @elseif ($kind === 'invoice' && $invoice->fne_status === 'to_issue')
            <p class="fne">{{ __("documents.{$kind}.fne_pending") }}</p>
        @endif

        <div class="section">{{ __('documents.sheet.reconciliation') }}</div>
        <table class="recon">
            <thead>
                <tr>
                    <th style="width: 38%">{{ __('documents.sheet.movement') }}</th>
                    <th style="width: 18%">{{ __('documents.sheet.reference') }}</th>
                    <th style="width: 20%">{{ __('documents.sheet.amount_plain') }}</th>
                    <th style="width: 24%">{{ __('documents.sheet.observation') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sheet['previous_debts'] as $debt)
                    <tr>
                        <td class="label">{{ __('documents.sheet.previous_debt') }}</td>
                        <td class="ref">{{ $debt['reference'] }}</td>
                        <td class="amount">{{ $plain($debt['amount']) }}</td>
                        <td class="note">{{ $debt['partial'] ? __('documents.sheet.partial') : __('documents.sheet.unpaid') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="label">{{ __('documents.sheet.previous_debt') }}</td>
                        <td></td>
                        <td class="amount">-</td>
                        <td class="note">{{ __('documents.sheet.none') }}</td>
                    </tr>
                @endforelse
                <tr>
                    <td class="label">{{ __('documents.sheet.surplus') }}</td>
                    <td></td>
                    <td class="amount">{{ $sheet['credit'] > 0 ? $plain($sheet['credit']) : '-' }}</td>
                    <td class="note">{{ __('documents.sheet.ditto') }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('documents.sheet.new_invoice') }}</td>
                    <td>{{ $number }}</td>
                    <td class="amount">{{ $plain($sheet['total']) }}</td>
                    <td class="note">{{ __('documents.sheet.ditto') }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __("documents.{$kind}.advance_received") }}</td>
                    <td></td>
                    <td class="amount">{{ $plain($sheet['advance']) }}</td>
                    <td class="note">{{ $sheet['advance'] > 0 ? ($kind === 'invoice' ? __('documents.sheet.paid') : __('documents.proforma.to_pay')) : '-' }}</td>
                </tr>
                @if ($sheet['balance_paid'] > 0)
                    <tr>
                        <td class="label">{{ __("documents.{$kind}.balance_received") }}</td>
                        <td></td>
                        <td class="amount">{{ $plain($sheet['balance_paid']) }}</td>
                        <td class="note">{{ __('documents.sheet.paid') }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">{{ __("documents.{$kind}.balance_after") }}</td>
                    <td></td>
                    <td class="amount">{{ $plain($sheet['remaining']) }}</td>
                    <td class="note">{{ __('documents.sheet.ditto') }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('documents.sheet.grand_remaining') }}</td>
                    <td></td>
                    <td class="amount">{{ $plain($sheet['grand_remaining']) }}</td>
                    <td class="note">{{ __('documents.sheet.ditto') }}</td>
                </tr>
            </tbody>
        </table>

        @if ($kind === 'invoice')
            <table class="check">
                <tr>
                    <td style="width: 22%" class="nowrap">{{ __('documents.sheet.check') }} :</td>
                    <td style="width: 36%">☐ {{ __('documents.sheet.check_old') }}</td>
                    <td>☐ {{ __('documents.sheet.check_payment') }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>☐ {{ __('documents.sheet.check_advance') }}</td>
                    <td>☐ {{ __('documents.sheet.check_note') }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="2">☐ {{ __('documents.sheet.check_release') }}</td>
                </tr>
            </table>
        @endif

        <table class="signatures">
            <tr>
                <td><div class="role">{{ __('documents.sheet.cashier') }}</div><div class="name">{{ $sheet['cashier'] }}</div></td>
                <td><div class="role">{{ __('documents.sheet.storekeeper') }}</div></td>
                <td><div class="role">{{ __('documents.sheet.tpe_manager') }}</div><div class="name">{{ $client->contact_person ?: $client->name }}</div></td>
            </tr>
        </table>
    @endif

    @include('documents.company_footer', ['extra' => __("documents.{$kind}.title").($subtitle ? ' - '.$subtitle : '').' '.__('documents.sheet.number_short').' '.$number.(count($sheet['zones']) > 0 ? ' · '.__('documents.sheet.zone_footer').' : '.implode(' • ', $sheet['zones']) : '')])
</body>
</html>
