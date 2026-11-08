{{-- The header of every document: logo and who the company is, the same everywhere (playbook UI-133). Needs $company. --}}
@php
    $legalLine = array_filter([
        $company['legal_form'],
        $company['capital'] ? __('documents.common.capital').' '.$company['capital'] : null,
    ]);
    $contactLine = array_filter([
        $company['phone'] ? __('documents.common.phone').' : '.$company['phone'] : null,
        $company['email'],
        $company['website'],
    ]);
    $numbersLine = array_filter([...$company['identifiers'], $company['tax_office'] ? __('documents.common.tax_office').' : '.$company['tax_office'] : null]);
@endphp
<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #2f5597; margin-bottom: 12px;">
    <tr>
        @if ($company['logo'])
            <td style="width: 24%; vertical-align: middle; padding: 0 12px 8px 0;">
                <img src="{{ $company['logo'] }}" alt="" style="max-width: 120px; max-height: 64px;">
            </td>
        @endif
        <td style="vertical-align: middle; padding-bottom: 8px; font-size: 9px; color: #374151; line-height: 1.45;">
            @if ($company['name'])<div style="font-size: 15px; font-weight: bold; color: #2f5597;">{{ $company['name'] }}</div>@endif
            @if (count($legalLine) > 0)<div>{{ implode(' · ', $legalLine) }}</div>@endif
            @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
            @if (count($contactLine) > 0)<div>{{ implode(' · ', $contactLine) }}</div>@endif
            @if (count($numbersLine) > 0)<div style="color: #6b7280;">{{ implode(' · ', $numbersLine) }}</div>@endif
        </td>
    </tr>
</table>
