{{-- The footer of every document, the same everywhere (playbook UI-133). Needs $company; $extra is the line that is specific to the document. --}}
@php
    $footerLines = array_filter([
        implode(' · ', array_filter([$company['name'] ?: config('app.name'), $company['legal_form'], $company['capital'] ? __('documents.common.capital').' '.$company['capital'] : null])),
        implode(' · ', array_filter([$company['address'], $company['phone'], $company['email'], $company['website']])),
        implode(' · ', $company['identifiers']),
        $company['footer_note'],
        $extra ?? null,
    ]);
@endphp
<div style="position: fixed; bottom: -46px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 3px; line-height: 1.4;">
    @foreach ($footerLines as $line)
        <div>{{ $line }}</div>
    @endforeach
</div>
